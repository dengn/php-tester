"""Scenario runner with MatrixOne-aware error classification.

A scenario is a zero-arg callable that performs an operation against MatrixOne
and:
  - returns (anything) on success                => PASS
  - raises SkipScenario                          => SKIP
  - raises BehaviorMismatch                      => FAIL, code 'BEHAVIOR'
  - raises anything else                         => FAIL, code = classified

Each scenario runs in isolation inside try/except so one explosion never aborts
the run. Timing and a classified error code are captured for the report.

Harness-bug detection
---------------------
DB-driven failures are EXPECTED and GOOD here — they are the incompatibilities
we want to surface. But a bug in *our* code (NameError, a malformed SQL string
unrelated to MatrixOne, a bad import) must never masquerade as a DB finding. The
classifier flags those with code 'HARNESS-BUG' so they can be audited and driven
to zero.
"""

from __future__ import annotations

import re
import time
import traceback
from typing import Callable

import pymysql

from .result import FAIL, PASS, SKIP, BehaviorMismatch, SkipScenario, TestResult

# Exception types that always indicate a bug in the harness, never a MatrixOne
# incompatibility.
_HARNESS_BUG_TYPES = (
    NameError,
    AttributeError,
    ImportError,
    ModuleNotFoundError,
    IndentationError,
    SyntaxError,
    TypeError,
    KeyError,
    IndexError,
    UnboundLocalError,
    AssertionError,
    ZeroDivisionError,
)

# MatrixOne / MySQL numeric codes we care to surface explicitly.
_KNOWN_CODES = {
    "1064",   # SQL parser error / syntax / feature not supported by parser
    "1062",   # duplicate entry for unique key
    "1149",   # SQL syntax error (e.g. aggregate in WHERE, ONLY_FULL_GROUP_BY)
    "1690",   # out of range value
    "1054",   # unknown column
    "1146",   # table doesn't exist
    "1452",   # FK constraint fails
    "1366",   # incorrect value for column
    "1050",   # table already exists
    "1105",   # unknown/internal error (e.g. unsupported type)
    "20101",  # internal "not implemented yet"
    "20102",  # clause not yet implemented (e.g. EXCEPT ALL)
    "20105",  # function / operator not supported
    "20203",  # stricter arg/type validation
    "20301",  # other internal
}


def classify(exc: BaseException) -> tuple[str, str]:
    """Return (error_code, error_message) for a raised exception.

    Order of precedence:
      1. Harness-bug Python exception types -> 'HARNESS-BUG'
      2. pymysql.err.MySQLError carrying a numeric args[0]
      3. Numeric MatrixOne code parsed from the message text
      4. SQLSTATE token from the message
      5. fallback 'ERROR'
    """
    msg = _message_of(exc)

    # 1. Pure-Python bugs in our own code. But: SQLAlchemy/Django/pymysql wrap
    #    driver errors; only treat as a harness bug when nothing in the chain is
    #    a database error and no numeric DB code appears in the text.
    if _is_harness_bug(exc, msg):
        return "HARNESS-BUG", msg

    # 2. pymysql numeric error code in args.
    code = _pymysql_code(exc)
    if code:
        return code, msg

    # 3. Numeric MatrixOne code embedded in the message.
    m = re.search(r"\((\d{4,5}),", msg)  # "(20105, '...')"
    if m and m.group(1) in _KNOWN_CODES:
        return m.group(1), msg
    m = re.search(r"\berror code:?\s*(\d{4,5})\b", msg, re.IGNORECASE)
    if m:
        return m.group(1), msg
    m = re.search(r"\b(\d{4,5})\b", msg)
    if m and m.group(1) in _KNOWN_CODES:
        return m.group(1), msg

    # 4. SQLSTATE.
    m = re.search(r"SQLSTATE\[?(\w{5})\]?", msg)
    if m:
        return m.group(1), msg

    # 5. Phrase-based fallbacks.
    low = msg.lower()
    if "not supported" in low or "not been implemented" in low or "not implemented" in low:
        return "UNIMPLEMENTED", msg

    return "ERROR", msg


def _message_of(exc: BaseException) -> str:
    """Build a message string, walking the exception chain for the richest text."""
    parts = []
    seen = set()
    cur: BaseException | None = exc
    depth = 0
    while cur is not None and id(cur) not in seen and depth < 6:
        seen.add(id(cur))
        s = str(cur)
        if s and s not in parts:
            parts.append(s)
        cur = cur.__cause__ or cur.__context__
        depth += 1
    out = " | ".join(parts) if parts else repr(exc)
    return re.sub(r"\s+", " ", out).strip()


def _pymysql_code(exc: BaseException) -> str | None:
    """Extract a numeric MySQL error code from a pymysql error anywhere in chain."""
    cur: BaseException | None = exc
    seen = set()
    depth = 0
    while cur is not None and id(cur) not in seen and depth < 6:
        seen.add(id(cur))
        if isinstance(cur, pymysql.err.MySQLError):
            args = getattr(cur, "args", None)
            if args and isinstance(args[0], int):
                return str(args[0])
        cur = cur.__cause__ or cur.__context__
        depth += 1
    return None


def _chain_has_db_error(exc: BaseException) -> bool:
    cur: BaseException | None = exc
    seen = set()
    depth = 0
    while cur is not None and id(cur) not in seen and depth < 8:
        seen.add(id(cur))
        if isinstance(cur, pymysql.err.MySQLError):
            return True
        # SQLAlchemy / Django DB-API errors expose .orig pointing at the driver err.
        orig = getattr(cur, "orig", None)
        if isinstance(orig, pymysql.err.MySQLError):
            return True
        cur = cur.__cause__ or cur.__context__
        depth += 1
    return False


def _is_harness_bug(exc: BaseException, msg: str) -> bool:
    # If any layer of the chain is a real DB error, it's a DB finding, not a bug.
    if _chain_has_db_error(exc):
        return False
    # A numeric MatrixOne code in the text also means it reached the server.
    if re.search(r"\((\d{4,5}),", msg):
        return False
    # Otherwise, a pure-Python exception type is a bug in our scenario code.
    return isinstance(exc, _HARNESS_BUG_TYPES)


class Runner:
    def __init__(self, verbose: bool = False, fail_fast: bool = False):
        self.scenarios: list[tuple[str, str, str, Callable[[], object]]] = []
        self.results: list[TestResult] = []
        self.verbose = verbose
        self.fail_fast = fail_fast
        self._next_id = 1

    def add(self, framework: str, category: str, name: str, fn: Callable[[], object]) -> None:
        self.scenarios.append((framework, category, name, fn))

    def count(self) -> int:
        return len(self.scenarios)

    def frameworks(self) -> set[str]:
        return {s[0] for s in self.scenarios}

    def select(self, only: set[str] | None, limit: int | None,
               stratified: bool = False) -> list[tuple[str, str, str, Callable]]:
        """Filter/limit the registered scenarios for partial runs."""
        pool = self.scenarios
        if only:
            pool = [s for s in pool if s[0] in only]
        if limit is None or limit >= len(pool):
            return list(pool)
        if not stratified:
            return list(pool[:limit])
        # Stratified: spread the limit across (framework, category) buckets so a
        # subset run still touches every framework and category.
        from collections import OrderedDict

        buckets: "OrderedDict[tuple[str, str], list]" = OrderedDict()
        for s in pool:
            buckets.setdefault((s[0], s[1]), []).append(s)
        chosen: list = []
        idx = 0
        # round-robin draw from each bucket until we hit the limit
        while len(chosen) < limit:
            progressed = False
            for key, items in buckets.items():
                if idx < len(items):
                    chosen.append(items[idx])
                    progressed = True
                    if len(chosen) >= limit:
                        break
            if not progressed:
                break
            idx += 1
        return chosen

    def run(self, selection: list[tuple[str, str, str, Callable]] | None = None) -> None:
        sel = selection if selection is not None else self.scenarios
        total = len(sel)
        for framework, category, name, fn in sel:
            sid = self._next_id
            self._next_id += 1
            start = time.perf_counter()
            status = PASS
            detail = None
            error_code = None
            error_message = None
            try:
                ret = fn()
                if isinstance(ret, str):
                    detail = ret
            except SkipScenario as e:
                status = SKIP
                detail = str(e)
            except BehaviorMismatch as e:
                status = FAIL
                error_code = "BEHAVIOR"
                error_message = re.sub(r"\s+", " ", str(e)).strip()
            except BaseException as e:  # noqa: BLE001 - intentional catch-all
                status = FAIL
                error_code, error_message = classify(e)
                if error_code == "HARNESS-BUG" and self.verbose:
                    traceback.print_exc()
            duration_ms = (time.perf_counter() - start) * 1000.0
            self.results.append(
                TestResult(sid, framework, category, name, status, duration_ms,
                           detail, error_code, error_message)
            )
            self._emit(sid, total, framework, category, name, status, error_code, error_message)
            if self.fail_fast and status == FAIL and error_code == "HARNESS-BUG":
                raise RuntimeError(f"fail-fast on harness bug in {framework}/{name}: {error_message}")

    def _emit(self, sid, total, framework, category, name, status, code, message):
        if status == FAIL and code == "HARNESS-BUG":
            print(f"\n[HARNESS-BUG #{sid}] {framework}/{category}/{name}: {message[:200]}")
        elif self.verbose and status == FAIL:
            print(f"\n[FAIL #{sid}] {framework}/{category}/{name} ({code}) {(message or '')[:120]}")
        else:
            ch = "." if status == PASS else ("s" if status == SKIP else "F")
            print(ch, end="", flush=True)
        if sid % 200 == 0:
            print(f" [{sid}/{total}]", flush=True)
