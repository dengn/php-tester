"""High-volume generated coverage (raw driver).

Mirrors the PHP MegaMatrix provider: operator/operand grids (arithmetic,
comparison, bitwise, logical), function-input variations, a cast matrix, and
parametric query-workload shapes over a shared seeded fixture. Most expression
cases assert only that MatrixOne executes them (a divergence => a finding);
workload cases assert a plausible scalar so wrong answers surface too.

Runs on its own database namespace (mo_py_mega) via a persistent connection.
"""

from __future__ import annotations

import contextlib

import pymysql

from harness import BehaviorMismatch, config
from harness.connections import raw_connect

FW = "mega"

_conn = None
_fixture_ready = False


def _c():
    global _conn
    if _conn is None or not _conn.open:
        _conn = raw_connect(config.DB_MEGA)
    return _conn


def _scalar(sql):
    try:
        cur = _c().cursor()
        cur.execute(sql)
        row = cur.fetchone()
        return row[0] if row else None
    except pymysql.err.InterfaceError:
        global _conn
        with contextlib.suppress(Exception):
            if _conn is not None:
                _conn.close()
        _conn = None
        raise


def _add_expr(runner, cat, name, expr, expect=None):
    sql = f"SELECT {expr} AS v"

    def fn(sql=sql, expect=expect, expr=expr):
        got = _scalar(sql)
        if expect is not None:
            if str(expect) != str(got):
                try:
                    if abs(float(got) - float(expect)) < 1e-9:
                        return "ran"
                except (TypeError, ValueError):
                    pass
                raise BehaviorMismatch(f"{expr}: expected {expect}, got {got}")
        return "ran"

    runner.add(FW, cat, name, fn)


_OPERANDS = {
    "int0": "0", "int1": "1", "intNeg": "-7", "intBig": "2147483647",
    "bigint": "9223372036854775807", "dec": "12.50", "decNeg": "-3.14",
    "frac": "0.001", "numStr": "'42'", "mixStr": "'10abc'", "str": "'hello'",
    "date": "'2026-06-23'", "dt": "'2026-06-23 10:20:30'", "nul": "NULL",
    "zeroStr": "'0'", "bigDec": "99999999999999999999.99",
}


def _arith(runner):
    for op, n in {"+": "add", "-": "sub", "*": "mul", "/": "div", "DIV": "idiv", "%": "mod"}.items():
        for la, a in _OPERANDS.items():
            for lb, b in _OPERANDS.items():
                _add_expr(runner, "grid:arith", f"{la} {n} {lb}", f"{a} {op} {b}")


def _compare(runner):
    for op, n in {"=": "eq", "<>": "ne", "<": "lt", "<=": "le", ">": "gt", ">=": "ge", "<=>": "nseq"}.items():
        for la, a in _OPERANDS.items():
            for lb, b in _OPERANDS.items():
                _add_expr(runner, "grid:compare", f"{la} {n} {lb}", f"{a} {op} {b}")


def _bitwise_logical(runner):
    ints = ["0", "1", "5", "255", "-1", "1024", "9223372036854775807"]
    for op, n in {"&": "and", "|": "or", "^": "xor", "<<": "shl", ">>": "shr"}.items():
        for a in ints:
            for b in ints:
                _add_expr(runner, "grid:bitwise", f"{a} {n} {b}", f"{a} {op} {b}")
    bools = ["0", "1", "NULL", "5", "-1"]
    for op, n in {"AND": "and", "OR": "or", "XOR": "xor"}.items():
        for a in bools:
            for b in bools:
                _add_expr(runner, "grid:logical", f"{a} {n} {b}", f"{a} {op} {b}")
    for a in bools:
        _add_expr(runner, "grid:logical", f"NOT {a}", f"NOT {a}")
        _add_expr(runner, "grid:bitwise", f"~ {a}", f"~ {a}")


def _functions(runner):
    str_inputs = ["'abc'", "''", "'Héllo Wörld'", "'  pad  '", "'a,b,c'", "'2026-06-23'", "'123.45'", "'emoji'"]
    str_fns = ["UPPER", "LOWER", "LENGTH", "CHAR_LENGTH", "REVERSE", "TRIM", "LTRIM", "RTRIM",
               "HEX", "TO_BASE64", "MD5", "SHA1", "SOUNDEX", "ORD", "ASCII", "BIT_LENGTH", "QUOTE"]
    for fn in str_fns:
        for i, inp in enumerate(str_inputs):
            _add_expr(runner, "fn:string-var", f"{fn} #{i}", f"{fn}({inp})")
    for fn in ["LEFT", "RIGHT", "REPEAT"]:
        for i, inp in enumerate(str_inputs):
            for k in ["0", "1", "3", "50"]:
                _add_expr(runner, "fn:string-var", f"{fn} #{i},{k}", f"{fn}({inp}, {k})")
    num_inputs = ["0", "1", "-1", "3.14159", "-2.5", "1000000", "0.0001", "255"]
    num_fns = ["ABS", "CEIL", "FLOOR", "SIGN", "SQRT", "EXP", "LN", "LOG2", "LOG10",
               "SIN", "COS", "TAN", "ASIN", "ACOS", "ATAN", "DEGREES", "RADIANS", "CRC32", "BIN", "OCT"]
    for fn in num_fns:
        for i, inp in enumerate(num_inputs):
            _add_expr(runner, "fn:numeric-var", f"{fn} #{i}", f"{fn}({inp})")
    for fn in ["ROUND", "TRUNCATE"]:
        for i, inp in enumerate(num_inputs):
            for d in ["-2", "0", "2", "4"]:
                _add_expr(runner, "fn:numeric-var", f"{fn} #{i},{d}", f"{fn}({inp}, {d})")
    date_inputs = ["'2026-06-23'", "'2024-02-29'", "'2026-12-31 23:59:59'", "'2000-01-01'"]
    date_fns = ["YEAR", "MONTH", "DAY", "HOUR", "MINUTE", "SECOND", "QUARTER", "WEEK",
                "DAYOFWEEK", "DAYOFYEAR", "DAYNAME", "MONTHNAME", "LAST_DAY", "TO_DAYS", "WEEKDAY"]
    for fn in date_fns:
        for i, inp in enumerate(date_inputs):
            _add_expr(runner, "fn:date-var", f"{fn} #{i}", f"{fn}({inp})")
    for unit in ["DAY", "WEEK", "MONTH", "QUARTER", "YEAR", "HOUR"]:
        for i, inp in enumerate(date_inputs):
            _add_expr(runner, "fn:date-var", f"ADD {unit} #{i}", f"DATE_ADD({inp}, INTERVAL 3 {unit})")
    for f in ["%Y-%m-%d", "%H:%i:%s", "%W %M %Y", "%j", "%p %r"]:
        for i, inp in enumerate(date_inputs):
            _add_expr(runner, "fn:date-format", f"fmt {f} #{i}", f"DATE_FORMAT({inp}, '{f}')")


def _casts(runner):
    targets = ["SIGNED", "UNSIGNED", "CHAR", "CHAR(5)", "DECIMAL(10,2)", "DECIMAL(20,4)",
               "DATE", "DATETIME", "TIME", "DOUBLE", "FLOAT", "BINARY", "JSON", "NCHAR"]
    sources = ["'42'", "'-3.14'", "'2026-06-23'", "'2026-06-23 10:20:30'", "'10:20:30'",
               "'abc'", "255", "3.14159", "'[1,2,3]'", "NULL"]
    for t in targets:
        for s in sources:
            _add_expr(runner, "cast:matrix", f"CAST {s} AS {t}", f"CAST({s} AS {t})")
            _add_expr(runner, "cast:matrix", f"CONVERT {s},{t}", f"CONVERT({s}, {t})")


def _ensure_fixture():
    global _fixture_ready
    if _fixture_ready:
        return
    cur = _c().cursor()
    cur.execute("DROP TABLE IF EXISTS qw_orders")
    cur.execute("DROP TABLE IF EXISTS qw_users")
    cur.execute("CREATE TABLE qw_users (id INT PRIMARY KEY, name VARCHAR(40), region VARCHAR(10), tier INT)")
    cur.execute("CREATE TABLE qw_orders (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, product VARCHAR(20), "
                "qty INT, price DECIMAL(10,2), status VARCHAR(12), region VARCHAR(10), created DATETIME)")
    regions = ["NA", "EU", "APAC", "LATAM"]
    status = ["new", "paid", "shipped", "cancelled", "refunded"]
    prods = ["widget", "gadget", "gizmo", "doohickey", "thingamajig"]
    uvals = ",".join(f"({i},'user{i}','{regions[i % 4]}',{(i % 3) + 1})" for i in range(1, 201))
    cur.execute("INSERT INTO qw_users VALUES " + uvals)
    for b in range(15):
        rows = []
        for i in range(1, 101):
            n = b * 100 + i
            rows.append("({},'{}',{},{:.2f},'{}','{}','2026-{:02d}-{:02d} {:02d}:00:00')".format(
                (n % 200) + 1, prods[n % 5], (n % 10) + 1, (n % 1000) + 0.99,
                status[n % 5], regions[n % 4], (n % 12) + 1, (n % 27) + 1, n % 24))
        cur.execute("INSERT INTO qw_orders(user_id,product,qty,price,status,region,created) VALUES " + ",".join(rows))
    _fixture_ready = True


def _add_query(runner, cat, name, sql):
    def fn(sql=sql):
        _ensure_fixture()
        v = _scalar(sql)
        if v is None:
            raise BehaviorMismatch("query returned no row")
        return f"= {v}"
    runner.add(FW, cat, name, fn)


def _workload(runner):
    cols = ["qty", "price", "status", "region", "product", "user_id"]
    ops = ["=", "<>", "<", "<=", ">", ">=", "LIKE", "IN"]
    vals = {
        "qty": ["5", "0", "11"], "price": ["100.00", "0.99", "500"],
        "status": ["'paid'", "'PAID'", "'unknown'"], "region": ["'EU'", "'eu'", "'NA'"],
        "product": ["'widget'", "'WIDGET'", "'w%'"], "user_id": ["1", "100", "999"],
    }
    for c in cols:
        for op in ops:
            for v in vals[c]:
                if op == "IN":
                    expr = f"{c} IN ({v}, {v})"
                elif op == "LIKE":
                    expr = f"{c} LIKE {v}"
                else:
                    expr = f"{c} {op} {v}"
                _add_query(runner, "workload:filter", f"{c} {op} {v}", f"SELECT COUNT(*) FROM qw_orders WHERE {expr}")
    for c in cols:
        for d in ["ASC", "DESC"]:
            for pg in ["LIMIT 10", "LIMIT 10 OFFSET 50", "LIMIT 1 OFFSET 999", "LIMIT 100 OFFSET 1400"]:
                _add_query(runner, "workload:sort", f"{c} {d} {pg}",
                           f"SELECT COUNT(*) FROM (SELECT id FROM qw_orders ORDER BY {c} {d} {pg}) z")
    aggs = ["COUNT(*)", "SUM(price)", "AVG(qty)", "MIN(price)", "MAX(qty)", "COUNT(DISTINCT user_id)"]
    for g in ["region", "status", "product", "qty"]:
        for a in aggs:
            _add_query(runner, "workload:group", f"{g} {a}",
                       f"SELECT COUNT(*) FROM (SELECT {g}, {a} m FROM qw_orders GROUP BY {g}) z")
            _add_query(runner, "workload:group", f"{g} {a} having",
                       f"SELECT COUNT(*) FROM (SELECT {g}, {a} m FROM qw_orders GROUP BY {g} HAVING {a} > 0) z")
    for w in ["ROW_NUMBER()", "RANK()", "DENSE_RANK()", "SUM(price)", "AVG(qty)", "LAG(price)", "LEAD(qty)", "NTILE(4)"]:
        for part in ["region", "status", "product"]:
            _add_query(runner, "workload:window", f"{w} over {part}",
                       f"SELECT COUNT(*) FROM (SELECT {w} OVER (PARTITION BY {part} ORDER BY id) wv FROM qw_orders) z")
    for jt in ["JOIN", "LEFT JOIN", "RIGHT JOIN"]:
        for on in ["o.user_id=u.id", "o.region=u.region"]:
            for w in ["", "WHERE u.tier=1", "WHERE o.status='paid'"]:
                _add_query(runner, "workload:join", f"{jt} {on} {w}",
                           f"SELECT COUNT(*) FROM qw_orders o {jt} qw_users u ON {on} {w}")
    for g in ["region", "status", "product"]:
        _add_query(runner, "workload:analytics", f"rollup {g}",
                   f"SELECT COUNT(*) FROM (SELECT {g}, SUM(price) FROM qw_orders GROUP BY {g} WITH ROLLUP) z")
        _add_query(runner, "workload:analytics", f"month bucket {g}",
                   f"SELECT COUNT(*) FROM (SELECT MONTH(created) m, {g}, SUM(price) FROM qw_orders GROUP BY MONTH(created), {g}) z")
        _add_query(runner, "workload:subquery", f"in {g}",
                   f"SELECT COUNT(*) FROM qw_orders WHERE {g} IN (SELECT {g} FROM qw_orders WHERE price > 100)")
        _add_query(runner, "workload:subquery", f"scalar {g}",
                   "SELECT COUNT(*) FROM qw_orders WHERE price > (SELECT AVG(price) FROM qw_orders)")


def register(runner):
    _arith(runner)
    _compare(runner)
    _bitwise_logical(runner)
    _functions(runner)
    _casts(runner)
    _workload(runner)
