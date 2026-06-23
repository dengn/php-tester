"""Writes reports/results.json and reports/summary.md from TestResults."""

from __future__ import annotations

import json
import os
from collections import Counter, defaultdict

from .result import FAIL, PASS, SKIP, TestResult


class Reporter:
    def __init__(self, results: list[TestResult], out_dir: str, engine: str):
        self.results = results
        self.out_dir = out_dir
        self.engine = engine

    def write(self) -> dict:
        os.makedirs(self.out_dir, exist_ok=True)
        total = len(self.results)
        npass = sum(1 for r in self.results if r.status == PASS)
        nfail = sum(1 for r in self.results if r.status == FAIL)
        nskip = sum(1 for r in self.results if r.status == SKIP)

        payload = {
            "engine": self.engine,
            "summary": {"total": total, "pass": npass, "fail": nfail, "skip": nskip},
            "results": [r.to_dict() for r in self.results],
        }
        with open(os.path.join(self.out_dir, "results.json"), "w", encoding="utf-8") as f:
            json.dump(payload, f, indent=2, ensure_ascii=False)

        with open(os.path.join(self.out_dir, "summary.md"), "w", encoding="utf-8") as f:
            f.write(self._markdown(total, npass, nfail, nskip))

        return {"total": total, "pass": npass, "fail": nfail, "skip": nskip}

    def _markdown(self, total, npass, nfail, nskip) -> str:
        out: list[str] = []
        out.append("# Python ORM ⇆ MatrixOne Compatibility Summary")
        out.append("")
        out.append(f"- **Engine:** `{self.engine}`")
        out.append(f"- **Scenarios:** {total} total — "
                   f"**{npass} pass**, **{nfail} fail**, **{nskip} skip**")
        denom = max(1, total - nskip)
        out.append(f"- **Pass rate (excl. skip):** {npass / denom * 100:.1f}%")
        out.append("")

        # By framework
        out.append("## Pass/fail by framework")
        out.append("")
        out.append("| Framework | Total | Pass | Fail | Skip | Pass % |")
        out.append("|---|--:|--:|--:|--:|--:|")
        by_fw: dict[str, list[TestResult]] = defaultdict(list)
        for r in self.results:
            by_fw[r.framework].append(r)
        for fw in sorted(by_fw):
            rs = by_fw[fw]
            t = len(rs)
            p = sum(1 for r in rs if r.status == PASS)
            fl = sum(1 for r in rs if r.status == FAIL)
            sk = sum(1 for r in rs if r.status == SKIP)
            pr = p / max(1, t - sk) * 100
            out.append(f"| {fw} | {t} | {p} | {fl} | {sk} | {pr:.1f}% |")
        out.append("")

        # By category
        out.append("## Pass/fail by category")
        out.append("")
        out.append("| Category | Total | Pass | Fail | Skip |")
        out.append("|---|--:|--:|--:|--:|")
        by_cat: dict[str, list[TestResult]] = defaultdict(list)
        for r in self.results:
            by_cat[r.category].append(r)
        for cat in sorted(by_cat):
            rs = by_cat[cat]
            t = len(rs)
            p = sum(1 for r in rs if r.status == PASS)
            fl = sum(1 for r in rs if r.status == FAIL)
            sk = sum(1 for r in rs if r.status == SKIP)
            out.append(f"| {cat} | {t} | {p} | {fl} | {sk} |")
        out.append("")

        # Failures by error signature
        out.append("## Failures by error signature")
        out.append("")
        code_counts: Counter = Counter()
        code_sample: dict[str, str] = {}
        for r in self.results:
            if r.status == FAIL:
                code = r.error_code or "ERROR"
                code_counts[code] += 1
                code_sample.setdefault(code, (r.error_message or "")[:200])
        if not code_counts:
            out.append("_No failures recorded._")
        else:
            out.append("| Error code | Count | Sample message |")
            out.append("|---|--:|---|")
            for code, cnt in code_counts.most_common():
                sample = _md_escape(code_sample.get(code, ""))
                out.append(f"| `{code}` | {cnt} | {sample} |")
        out.append("")

        # Harness-bug callout (should be zero)
        hb = [r for r in self.results if r.status == FAIL and r.error_code == "HARNESS-BUG"]
        out.append("## Harness-bug failures (must be zero)")
        out.append("")
        if not hb:
            out.append("**0 harness-bug failures.** All FAILs are DB-driven incompatibilities.")
        else:
            out.append(f"**{len(hb)} harness-bug failures — these are bugs in the suite:**")
            out.append("")
            for r in hb[:50]:
                out.append(f"- #{r.id} {r.framework}/{r.category}/{r.name}: "
                           f"{_md_escape((r.error_message or '')[:160])}")
        out.append("")

        # Top distinct failure messages
        out.append("## Top distinct failure messages")
        out.append("")
        msg_counts: Counter = Counter()
        for r in self.results:
            if r.status == FAIL:
                msg_counts[_norm_msg(r.error_message or "")] += 1
        out.append("| Count | Message |")
        out.append("|--:|---|")
        for msg, cnt in msg_counts.most_common(30):
            out.append(f"| {cnt} | {_md_escape(msg[:180])} |")
        out.append("")

        return "\n".join(out) + "\n"


def _norm_msg(msg: str) -> str:
    import re
    msg = re.sub(r"\s+", " ", msg).strip()
    # Collapse table/column suffixes so similar messages group together.
    msg = re.sub(r"__?[a-z0-9]{6,}\b", "<id>", msg)
    msg = re.sub(r"\b\d{4,}\b", "<n>", msg)
    return msg


def _md_escape(s: str) -> str:
    return s.replace("|", "\\|").replace("\n", " ")
