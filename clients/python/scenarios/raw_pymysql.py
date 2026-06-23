"""Raw PyMySQL driver baseline scenarios.

This is the bedrock: it exercises MatrixOne directly through the MySQL wire
protocol with no ORM in between, so the findings here are pure-engine behaviour.

Covers: data types (declare/insert/roundtrip/null/boundary/update/where/order/
group/index), functions (bare/in-where/in-select), DDL, DML, queries,
transactions, prepared statements (binary protocol), and known semantic quirks.

Every scenario gets a fresh cursor on the mo_py_raw database and cleans up its
own tables.
"""

from __future__ import annotations

import contextlib

import pymysql

from harness import BehaviorMismatch, SkipScenario, config
from harness import matrices as M
from harness.connections import raw_connect

FW = "raw"


# ---------------------------------------------------------------------------
# Connection pooling: one persistent connection per worker keeps the suite fast.
# Each scenario uses autocommit and unique table names, so they don't interfere.
# ---------------------------------------------------------------------------
_conn = None


def _c():
    global _conn
    if _conn is None or not _conn.open:
        _conn = raw_connect(config.DB_RAW)
    return _conn


def _exec(sql, args=None):
    try:
        cur = _c().cursor()
        cur.execute(sql, args)
        return cur
    except pymysql.err.InterfaceError:
        # A prior statement may have left the wire in a bad state (packet
        # sequence mismatch). Drop the connection so the next scenario starts
        # clean, then re-raise so this scenario is still recorded as a finding.
        global _conn
        with contextlib.suppress(Exception):
            if _conn is not None:
                _conn.close()
        _conn = None
        raise


def _fetchone(sql, args=None):
    cur = _exec(sql, args)
    return cur.fetchone()


def _fetchall(sql, args=None):
    cur = _exec(sql, args)
    return cur.fetchall()


@contextlib.contextmanager
def _table(ddl_cols: str, prefix="r"):
    name = M.uname(prefix)
    _exec(f"CREATE TABLE `{name}` ({ddl_cols})")
    try:
        yield name
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP TABLE IF EXISTS `{name}`")


def _quote(val: str, quoted: bool) -> str:
    if not quoted:
        return val
    return "'" + val.replace("'", "''") + "'"


# ===========================================================================
# Scenario factory functions (each returns a zero-arg callable).
# ===========================================================================

def _type_declare(label, sqltype):
    def run():
        with _table(f"id INT, c {sqltype}") as t:
            _fetchall(f"SELECT * FROM `{t}` LIMIT 1")
        return f"declared {sqltype}"
    return run


def _type_insert_roundtrip(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {lit})")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row is None:
                raise BehaviorMismatch(f"roundtrip {sqltype}: no row returned")
        return f"roundtrip {sqltype} -> {row[0]!r}"
    return run


def _type_null(label, sqltype):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype} NULL") as t:
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, NULL)")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row[0] is not None:
                raise BehaviorMismatch(f"NULL {sqltype} came back {row[0]!r}")
        return f"null {sqltype}"
    return run


def _type_param_roundtrip(label, sqltype, value, quoted):
    """Insert via a bound parameter (binary-ish path through pymysql)."""
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            pyval = value
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, %s)", (pyval,))
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row is None:
                raise BehaviorMismatch("param roundtrip: no row")
        return f"param {sqltype}"
    return run


def _type_boundary(label, sqltype, val, which):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {val})")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if str(row[0]).rstrip("0").rstrip(".") not in (
                str(val).rstrip("0").rstrip("."), str(val)):
                # numeric equality check (loose for decimals)
                try:
                    if float(row[0]) != float(val):
                        raise BehaviorMismatch(
                            f"{which} {sqltype}: stored {val}, got {row[0]}")
                except (TypeError, ValueError):
                    pass
        return f"{which} {sqltype} {val}"
    return run


def _type_update(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {lit})")
            _exec(f"UPDATE `{t}` SET c = {lit} WHERE id=1")
            _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
        return f"update {sqltype}"
    return run


def _type_where(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {lit})")
            _fetchall(f"SELECT id FROM `{t}` WHERE c = {lit}")
        return f"where {sqltype}"
    return run


def _type_orderby(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {lit})")
            _fetchall(f"SELECT id FROM `{t}` ORDER BY c ASC")
        return f"orderby {sqltype}"
    return run


def _type_groupby(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, {lit})")
            _fetchall(f"SELECT c, COUNT(*) FROM `{t}` GROUP BY c")
        return f"groupby {sqltype}"
    return run


def _type_index(label, sqltype):
    def run():
        klen = ""
        # text/blob columns need a key length
        if any(k in sqltype.upper() for k in ("TEXT", "BLOB")):
            klen = "(16)"
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"CREATE INDEX idx_c ON `{t}` (c{klen})")
        return f"index {sqltype}"
    return run


def _function_bare(label, expr):
    def run():
        _fetchone(f"SELECT {expr}")
        return f"bare {label}"
    return run


def _function_in_where(label, expr):
    def run():
        # use the function in a WHERE predicate against a 1-row derived table
        _fetchall(f"SELECT 1 FROM (SELECT 1 AS x) d WHERE ({expr}) IS NOT NULL")
        return f"where {label}"
    return run


def _function_in_select_with_col(label, expr):
    def run():
        with _table("id INT PRIMARY KEY, v INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1, 10)")
            _fetchall(f"SELECT id, {expr} FROM `{t}`")
        return f"select-col {label}"
    return run


def _aggregate(label, expr_tpl, on_int=True):
    def run():
        coltype = "INT" if on_int else "DOUBLE"
        with _table(f"g INT, c {coltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10),(1,20),(2,30),(2,40)")
            expr = expr_tpl.format(c="c")
            _fetchall(f"SELECT g, {expr} FROM `{t}` GROUP BY g")
        return f"agg {label}"
    return run


def _window(label, expr_tpl):
    def run():
        with _table("g INT, c INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10),(1,20),(2,30),(2,40)")
            expr = expr_tpl.format(c="c")
            _fetchall(
                f"SELECT g, c, {expr} OVER (PARTITION BY g ORDER BY c) FROM `{t}`")
        return f"window {label}"
    return run


# ===========================================================================
# Hand-written DDL / DML / query / transaction / prepared / semantic scenarios.
# Returned as a list of (category, name, callable).
# ===========================================================================

def _ddl_scenarios():
    items = []

    def add(name, fn):
        items.append(("ddl", name, fn))

    def auto_increment():
        with _table("id INT AUTO_INCREMENT PRIMARY KEY, n VARCHAR(10)") as t:
            _exec(f"INSERT INTO `{t}` (n) VALUES ('a'),('b')")
            rows = _fetchall(f"SELECT id FROM `{t}` ORDER BY id")
            if [r[0] for r in rows] != [1, 2]:
                raise BehaviorMismatch(f"auto_increment ids {rows}")
    add("auto_increment", auto_increment)

    def primary_key_composite():
        with _table("a INT, b INT, PRIMARY KEY (a,b)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1),(1,2)")
    add("composite_primary_key", primary_key_composite)

    def unique_constraint():
        with _table("id INT PRIMARY KEY, e VARCHAR(50) UNIQUE") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'a@x')")
            try:
                _exec(f"INSERT INTO `{t}` VALUES (2,'a@x')")
                raise BehaviorMismatch("unique not enforced (dup accepted)")
            except Exception as e:
                if "1062" not in str(e):
                    raise
    add("unique_enforced", unique_constraint)

    def unique_case_sensitive():
        # KNOWN C1: utf8mb4_bin default -> 'a@x' and 'A@X' both accepted
        with _table("id INT PRIMARY KEY, e VARCHAR(50) UNIQUE") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'user@x.com')")
            try:
                _exec(f"INSERT INTO `{t}` VALUES (2,'USER@X.COM')")
                # accepted -> case-sensitive unique (MySQL would reject as dup)
                raise BehaviorMismatch(
                    "UNIQUE is case-sensitive: 'user@x.com' and 'USER@X.COM' coexist")
            except BehaviorMismatch:
                raise
            except Exception:
                pass  # if it raised 1062, MatrixOne matched MySQL here
    add("unique_case_sensitivity", unique_case_sensitive)

    def not_null_enforced():
        with _table("id INT PRIMARY KEY, c INT NOT NULL") as t:
            try:
                _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
                raise BehaviorMismatch("NOT NULL not enforced")
            except BehaviorMismatch:
                raise
            except Exception:
                pass
    add("not_null_enforced", not_null_enforced)

    def default_value():
        with _table("id INT PRIMARY KEY, c INT DEFAULT 7") as t:
            _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row[0] != 7:
                raise BehaviorMismatch(f"DEFAULT 7 -> {row[0]}")
    add("default_value", default_value)

    def check_constraint_not_enforced():
        # KNOWN C4: CHECK parsed but not enforced
        with _table("id INT PRIMARY KEY, age INT CHECK (age >= 0)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1, -5)")
            row = _fetchone(f"SELECT age FROM `{t}` WHERE id=1")
            if row and row[0] == -5:
                raise BehaviorMismatch("CHECK (age>=0) not enforced; stored -5")
    add("check_not_enforced", check_constraint_not_enforced)

    def foreign_key():
        p = M.uname("fk_p")
        ch = M.uname("fk_c")
        _exec(f"CREATE TABLE `{p}` (id INT PRIMARY KEY)")
        try:
            _exec(f"CREATE TABLE `{ch}` (id INT PRIMARY KEY, pid INT, "
                  f"FOREIGN KEY (pid) REFERENCES `{p}`(id))")
            _exec(f"INSERT INTO `{p}` VALUES (1)")
            _exec(f"INSERT INTO `{ch}` VALUES (1,1)")
            try:
                _exec(f"INSERT INTO `{ch}` VALUES (2,999)")
                raise BehaviorMismatch("FK not enforced (orphan accepted)")
            except BehaviorMismatch:
                raise
            except Exception:
                pass
        finally:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{ch}`")
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{p}`")
    add("foreign_key_enforced", foreign_key)

    def generated_column():
        # KNOWN M1: generated columns rejected
        with _table("id INT PRIMARY KEY, a INT, b INT GENERATED ALWAYS AS (a+1) STORED") as t:
            _exec(f"INSERT INTO `{t}` (id,a) VALUES (1,5)")
    add("generated_column_stored", generated_column)

    def alter_add_column():
        with _table("id INT PRIMARY KEY") as t:
            _exec(f"ALTER TABLE `{t}` ADD COLUMN extra VARCHAR(20)")
            _exec(f"INSERT INTO `{t}` VALUES (1,'x')")
    add("alter_add_column", alter_add_column)

    def alter_drop_column():
        with _table("id INT PRIMARY KEY, extra INT") as t:
            _exec(f"ALTER TABLE `{t}` DROP COLUMN extra")
    add("alter_drop_column", alter_drop_column)

    def alter_modify_column():
        with _table("id INT PRIMARY KEY, c VARCHAR(10)") as t:
            _exec(f"ALTER TABLE `{t}` MODIFY COLUMN c VARCHAR(50)")
    add("alter_modify_column", alter_modify_column)

    def alter_add_check():
        # KNOWN M3: ALTER ADD CHECK -> 20101
        with _table("id INT PRIMARY KEY, age INT") as t:
            _exec(f"ALTER TABLE `{t}` ADD CONSTRAINT chk CHECK (age >= 0)")
    add("alter_add_check_constraint", alter_add_check)

    def create_index_btree():
        # KNOWN M3: USING BTREE -> 1064
        with _table("id INT PRIMARY KEY, c INT") as t:
            _exec(f"CREATE INDEX idx ON `{t}` (c) USING BTREE")
    add("create_index_using_btree", create_index_btree)

    def temp_table():
        name = M.uname("tmp")
        _exec(f"CREATE TEMPORARY TABLE `{name}` (id INT)")
        _exec(f"INSERT INTO `{name}` VALUES (1)")
        with contextlib.suppress(Exception):
            _exec(f"DROP TABLE IF EXISTS `{name}`")
    add("temporary_table", temp_table)

    def create_table_as_select():
        with _table("id INT PRIMARY KEY, v INT") as src:
            _exec(f"INSERT INTO `{src}` VALUES (1,10),(2,20)")
            dst = M.uname("ctas")
            try:
                _exec(f"CREATE TABLE `{dst}` AS SELECT * FROM `{src}`")
            finally:
                with contextlib.suppress(Exception):
                    _exec(f"DROP TABLE IF EXISTS `{dst}`")
    add("create_table_as_select", create_table_as_select)

    def fulltext_index():
        with _table("id INT PRIMARY KEY, body TEXT, FULLTEXT(body)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'matrixone fulltext test')")
    add("fulltext_index", fulltext_index)

    return items


def _dml_scenarios():
    items = []

    def add(name, fn):
        items.append(("dml", name, fn))

    def multi_row_insert():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1),(2,2),(3,3)")
            if _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0] != 3:
                raise BehaviorMismatch("multi-row insert count")
    add("multi_row_insert", multi_row_insert)

    def insert_select():
        with _table("id INT PRIMARY KEY, n INT") as a:
            _exec(f"INSERT INTO `{a}` VALUES (1,10),(2,20)")
            with _table("id INT PRIMARY KEY, n INT") as b:
                _exec(f"INSERT INTO `{b}` SELECT * FROM `{a}`")
    add("insert_select", insert_select)

    def insert_ignore():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1)")
            _exec(f"INSERT IGNORE INTO `{t}` VALUES (1,2)")
    add("insert_ignore", insert_ignore)

    def on_duplicate_key():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1)")
            _exec(f"INSERT INTO `{t}` VALUES (1,9) ON DUPLICATE KEY UPDATE n=9")
            if _fetchone(f"SELECT n FROM `{t}` WHERE id=1")[0] != 9:
                raise BehaviorMismatch("ON DUPLICATE KEY did not update")
    add("on_duplicate_key_update", on_duplicate_key)

    def on_duplicate_composite():
        # KNOWN: ON DUPLICATE on composite unique throws 1062
        with _table("a INT, b INT, n INT, UNIQUE KEY uq (a,b)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1,1)")
            _exec(f"INSERT INTO `{t}` VALUES (1,1,9) ON DUPLICATE KEY UPDATE n=9")
    add("on_duplicate_composite_unique", on_duplicate_composite)

    def replace_into():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1)")
            _exec(f"REPLACE INTO `{t}` VALUES (1,2)")
    add("replace_into", replace_into)

    def update_where():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1),(2,2)")
            _exec(f"UPDATE `{t}` SET n = n + 10 WHERE id = 1")
    add("update_where", update_where)

    def update_order_limit():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1),(2,2),(3,3)")
            _exec(f"UPDATE `{t}` SET n = 0 ORDER BY id DESC LIMIT 1")
    add("update_order_limit", update_order_limit)

    def delete_where():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1),(2,2)")
            _exec(f"DELETE FROM `{t}` WHERE id = 1")
    add("delete_where", delete_where)

    def multi_table_delete_join():
        # KNOWN C3: multi-table DELETE..JOIN deletes ALL rows
        a = M.uname("dj_a")
        b = M.uname("dj_b")
        _exec(f"CREATE TABLE `{a}` (id INT PRIMARY KEY, n INT)")
        _exec(f"CREATE TABLE `{b}` (id INT PRIMARY KEY)")
        try:
            _exec(f"INSERT INTO `{a}` VALUES (1,1),(2,2)")
            _exec(f"INSERT INTO `{b}` VALUES (1)")
            _exec(f"DELETE x FROM `{a}` x JOIN `{b}` y ON x.id = y.id")
            remaining = _fetchone(f"SELECT COUNT(*) FROM `{a}`")[0]
            if remaining != 1:
                raise BehaviorMismatch(
                    f"DELETE..JOIN should leave 1 row, left {remaining}")
        finally:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{a}`")
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{b}`")
    add("multi_table_delete_join", multi_table_delete_join)

    def truncate():
        with _table("id INT PRIMARY KEY") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1),(2)")
            _exec(f"TRUNCATE TABLE `{t}`")
            if _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0] != 0:
                raise BehaviorMismatch("TRUNCATE left rows")
    add("truncate_table", truncate)

    def last_insert_id_single():
        with _table("id INT AUTO_INCREMENT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` (n) VALUES (5)")
            lid = _fetchone("SELECT LAST_INSERT_ID()")[0]
            if lid != 1:
                raise BehaviorMismatch(f"LAST_INSERT_ID single -> {lid}")
    add("last_insert_id_single", last_insert_id_single)

    def last_insert_id_multi():
        # KNOWN C6: after multi-row insert, returns last id not first
        with _table("id INT AUTO_INCREMENT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` (n) VALUES (1),(2),(3)")
            lid = _fetchone("SELECT LAST_INSERT_ID()")[0]
            if lid != 1:
                raise BehaviorMismatch(
                    f"LAST_INSERT_ID after multi-row -> {lid} (MySQL=1, first)")
    add("last_insert_id_multi_row", last_insert_id_multi)

    return items


def _query_scenarios():
    items = []

    def add(name, fn):
        items.append(("query", name, fn))

    def seed(t, rows):
        _exec(f"INSERT INTO `{t}` VALUES " + ",".join(rows))

    def inner_join():
        with _table("id INT PRIMARY KEY, n INT") as a, \
                _table("id INT PRIMARY KEY, aid INT") as b:
            seed(a, ["(1,10)", "(2,20)"])
            seed(b, ["(1,1)", "(2,2)"])
            _fetchall(f"SELECT a.n FROM `{a}` a JOIN `{b}` b ON a.id=b.aid")
    add("inner_join", inner_join)

    def left_join():
        with _table("id INT PRIMARY KEY, n INT") as a, \
                _table("id INT PRIMARY KEY, aid INT") as b:
            seed(a, ["(1,10)", "(2,20)"])
            seed(b, ["(1,1)"])
            _fetchall(f"SELECT a.n, b.id FROM `{a}` a LEFT JOIN `{b}` b ON a.id=b.aid")
    add("left_join", left_join)

    def right_join():
        with _table("id INT PRIMARY KEY, n INT") as a, \
                _table("id INT PRIMARY KEY, aid INT") as b:
            seed(a, ["(1,10)"])
            seed(b, ["(1,1)", "(2,2)"])
            _fetchall(f"SELECT a.n, b.id FROM `{a}` a RIGHT JOIN `{b}` b ON a.id=b.aid")
    add("right_join", right_join)

    def cross_join():
        with _table("id INT PRIMARY KEY") as a, _table("id INT PRIMARY KEY") as b:
            seed(a, ["(1)", "(2)"])
            seed(b, ["(1)", "(2)"])
            _fetchall(f"SELECT * FROM `{a}` CROSS JOIN `{b}`")
    add("cross_join", cross_join)

    def self_join():
        with _table("id INT PRIMARY KEY, pid INT") as t:
            seed(t, ["(1,NULL)", "(2,1)", "(3,1)"])
            _fetchall(f"SELECT c.id, p.id FROM `{t}` c LEFT JOIN `{t}` p ON c.pid=p.id")
    add("self_join", self_join)

    def scalar_subquery():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,10)", "(2,20)"])
            _fetchall(f"SELECT id, (SELECT MAX(n) FROM `{t}`) FROM `{t}`")
    add("scalar_subquery", scalar_subquery)

    def in_subquery():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,10)", "(2,20)"])
            _fetchall(f"SELECT * FROM `{t}` WHERE n IN (SELECT n FROM `{t}` WHERE n>10)")
    add("in_subquery", in_subquery)

    def exists_subquery():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,10)"])
            _fetchall(f"SELECT * FROM `{t}` a WHERE EXISTS (SELECT 1 FROM `{t}` b WHERE b.n=a.n)")
    add("exists_subquery", exists_subquery)

    def correlated_subquery():
        with _table("id INT PRIMARY KEY, g INT, n INT") as t:
            seed(t, ["(1,1,10)", "(2,1,20)", "(3,2,30)"])
            _fetchall(
                f"SELECT * FROM `{t}` a WHERE n = "
                f"(SELECT MAX(n) FROM `{t}` b WHERE b.g=a.g)")
    add("correlated_subquery", correlated_subquery)

    def derived_table():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,10)", "(2,20)"])
            _fetchall(f"SELECT * FROM (SELECT n*2 AS d FROM `{t}`) x WHERE d>10")
    add("derived_table", derived_table)

    def cte():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,10)", "(2,20)"])
            _fetchall(f"WITH c AS (SELECT n FROM `{t}`) SELECT SUM(n) FROM c")
    add("cte_basic", cte)

    def recursive_cte():
        _fetchall(
            "WITH RECURSIVE seq(n) AS "
            "(SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n<5) "
            "SELECT * FROM seq")
    add("recursive_cte", recursive_cte)

    def union():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)", "(2)"])
            _fetchall(f"SELECT id FROM `{t}` UNION SELECT id FROM `{t}`")
    add("union", union)

    def union_all():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)", "(2)"])
            _fetchall(f"SELECT id FROM `{t}` UNION ALL SELECT id FROM `{t}`")
    add("union_all", union_all)

    def intersect():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)", "(2)"])
            _fetchall(f"SELECT id FROM `{t}` INTERSECT SELECT 1")
    add("intersect", intersect)

    def except_op():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)", "(2)"])
            _fetchall(f"SELECT id FROM `{t}` EXCEPT SELECT 1")
    add("except", except_op)

    def group_by_having():
        with _table("g INT, n INT") as t:
            seed(t, ["(1,10)", "(1,20)", "(2,30)"])
            _fetchall(f"SELECT g, SUM(n) FROM `{t}` GROUP BY g HAVING SUM(n)>15")
    add("group_by_having", group_by_having)

    def distinct():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,1)", "(2,1)", "(3,2)"])
            _fetchall(f"SELECT DISTINCT n FROM `{t}`")
    add("distinct", distinct)

    def order_by_limit_offset():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, [f"({i})" for i in range(1, 11)])
            _fetchall(f"SELECT id FROM `{t}` ORDER BY id DESC LIMIT 3 OFFSET 2")
    add("order_by_limit_offset", order_by_limit_offset)

    def window_over():
        with _table("g INT, n INT") as t:
            seed(t, ["(1,10)", "(1,20)", "(2,30)"])
            _fetchall(
                f"SELECT g, n, ROW_NUMBER() OVER (PARTITION BY g ORDER BY n) FROM `{t}`")
    add("window_row_number", window_over)

    def case_expr():
        with _table("id INT PRIMARY KEY, n INT") as t:
            seed(t, ["(1,5)", "(2,15)"])
            _fetchall(
                f"SELECT id, CASE WHEN n<10 THEN 'lo' ELSE 'hi' END FROM `{t}`")
    add("case_when", case_expr)

    def select_for_update():
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)"])
            _fetchall(f"SELECT * FROM `{t}` WHERE id=1 FOR UPDATE")
    add("select_for_update", select_for_update)

    def lock_in_share_mode():
        # KNOWN M3: LOCK IN SHARE MODE -> 1064
        with _table("id INT PRIMARY KEY") as t:
            seed(t, ["(1)"])
            _fetchall(f"SELECT * FROM `{t}` WHERE id=1 LOCK IN SHARE MODE")
    add("lock_in_share_mode", lock_in_share_mode)

    return items


def _transaction_scenarios():
    items = []

    def add(name, fn):
        items.append(("transaction", name, fn))

    def commit():
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("tx")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY)")
            conn.commit()
            cur.execute(f"INSERT INTO `{t}` VALUES (1)")
            conn.commit()
            cur.execute(f"SELECT COUNT(*) FROM `{t}`")
            if cur.fetchone()[0] != 1:
                raise BehaviorMismatch("commit lost row")
            cur.execute(f"DROP TABLE IF EXISTS `{t}`")
            conn.commit()
        finally:
            conn.close()
    add("commit", commit)

    def rollback():
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("tx")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY)")
            conn.commit()
            cur.execute(f"INSERT INTO `{t}` VALUES (1)")
            conn.rollback()
            cur.execute(f"SELECT COUNT(*) FROM `{t}`")
            n = cur.fetchone()[0]
            cur.execute(f"DROP TABLE IF EXISTS `{t}`")
            conn.commit()
            if n != 0:
                raise BehaviorMismatch(f"rollback left {n} rows")
        finally:
            conn.close()
    add("rollback", rollback)

    def savepoint_rollback():
        # KNOWN C5: ROLLBACK TO SAVEPOINT unimplemented (20101)
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("tx")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY)")
            conn.commit()
            cur.execute(f"INSERT INTO `{t}` VALUES (1)")
            cur.execute("SAVEPOINT sp1")
            cur.execute(f"INSERT INTO `{t}` VALUES (2)")
            cur.execute("ROLLBACK TO SAVEPOINT sp1")
            conn.commit()
            with contextlib.suppress(Exception):
                cur.execute(f"DROP TABLE IF EXISTS `{t}`")
                conn.commit()
        finally:
            conn.close()
    add("rollback_to_savepoint", savepoint_rollback)

    def autocommit_toggle():
        conn = raw_connect(config.DB_RAW)
        try:
            conn.autocommit(True)
            conn.autocommit(False)
            conn.autocommit(True)
        finally:
            conn.close()
    add("autocommit_toggle", autocommit_toggle)

    def isolation_levels():
        for lvl in ["READ COMMITTED", "REPEATABLE READ", "SERIALIZABLE",
                    "READ UNCOMMITTED"]:
            try:
                _exec(f"SET SESSION TRANSACTION ISOLATION LEVEL {lvl}")
            except Exception as e:
                raise BehaviorMismatch(f"isolation {lvl}: {e}") from e
    add("set_isolation_levels", isolation_levels)

    return items


def _prepared_scenarios():
    items = []

    def add(name, fn):
        items.append(("prepared", name, fn))

    def param_int():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (%s,%s)", (1, 100))
            row = _fetchone(f"SELECT n FROM `{t}` WHERE id=%s", (1,))
            if row[0] != 100:
                raise BehaviorMismatch(f"param int -> {row}")
    add("param_int", param_int)

    def param_string():
        with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (%s,%s)", (1, "hello"))
            _fetchone(f"SELECT s FROM `{t}` WHERE s=%s", ("hello",))
    add("param_string", param_string)

    def param_null():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (%s,%s)", (1, None))
            row = _fetchone(f"SELECT n FROM `{t}` WHERE id=1")
            if row[0] is not None:
                raise BehaviorMismatch("param NULL not null")
    add("param_null", param_null)

    def param_limit():
        with _table("id INT PRIMARY KEY") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1),(2),(3)")
            _fetchall(f"SELECT id FROM `{t}` ORDER BY id LIMIT %s", (2,))
    add("param_limit_placeholder", param_limit)

    def executemany():
        with _table("id INT PRIMARY KEY, n INT") as t:
            cur = _c().cursor()
            cur.executemany(f"INSERT INTO `{t}` VALUES (%s,%s)",
                            [(1, 10), (2, 20), (3, 30)])
            if _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0] != 3:
                raise BehaviorMismatch("executemany count")
    add("executemany", executemany)

    def server_side_prepare():
        # binary prepared protocol via SQL PREPARE/EXECUTE
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10)")
            _exec(f"PREPARE st FROM 'SELECT n FROM `{t}` WHERE id = ?'")
            _exec("SET @v = 1")
            _fetchall("EXECUTE st USING @v")
            with contextlib.suppress(Exception):
                _exec("DEALLOCATE PREPARE st")
    add("sql_prepare_execute", server_side_prepare)

    return items


def _semantic_scenarios():
    items = []

    def add(name, fn):
        items.append(("semantics", name, fn))

    def case_eq():
        # KNOWN C1
        row = _fetchone("SELECT 'abc' = 'ABC'")
        if row[0] != 1:
            raise BehaviorMismatch(
                f"'abc' = 'ABC' -> {row[0]} (MySQL default=1, case-insensitive)")
    add("string_equality_case", case_eq)

    def accent_eq():
        row = _fetchone("SELECT 'café' = 'cafe'")
        if row[0] != 1:
            raise BehaviorMismatch(
                f"'café'='cafe' -> {row[0]} (MySQL ai_ci=1)")
    add("string_equality_accent", accent_eq)

    def trailing_space_eq():
        row = _fetchone("SELECT 'a ' = 'a'")
        if row[0] != 1:
            raise BehaviorMismatch(
                f"'a ' = 'a' -> {row[0]} (MySQL PAD SPACE=1)")
    add("string_equality_trailing_space", trailing_space_eq)

    def like_case():
        row = _fetchone("SELECT 'abc' LIKE 'ABC'")
        if row[0] != 1:
            raise BehaviorMismatch(f"'abc' LIKE 'ABC' -> {row[0]} (MySQL=1)")
    add("like_case_insensitive", like_case)

    def collate_ci_ignored():
        # explicit _ci collation ignored
        try:
            row = _fetchone("SELECT 'abc' = 'ABC' COLLATE utf8mb4_general_ci")
            if row[0] != 1:
                raise BehaviorMismatch(
                    f"COLLATE utf8mb4_general_ci ignored -> {row[0]}")
        except BehaviorMismatch:
            raise
        except Exception as e:
            raise BehaviorMismatch(f"COLLATE ci errored: {e}") from e
    add("collate_ci_ignored", collate_ci_ignored)

    def pipe_concat():
        # KNOWN C7
        row = _fetchone("SELECT 1 || 0")
        if str(row[0]) != "1":
            raise BehaviorMismatch(
                f"1 || 0 -> {row[0]!r} (MySQL=1 logical OR, MO='10' concat)")
    add("pipe_is_concat", pipe_concat)

    def float_rounds():
        # KNOWN C2
        with _table("id INT PRIMARY KEY, c FLOAT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1, 3.5)")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if abs(float(row[0]) - 3.5) > 0.01:
                raise BehaviorMismatch(
                    f"bare FLOAT 3.5 stored as {row[0]} (data corruption)")
    add("bare_float_rounding", float_rounds)

    def string_to_num():
        # KNOWN Low: '10abc' + 5 errors in MatrixOne
        row = _fetchone("SELECT '10abc' + 5")
        if int(row[0]) != 15:
            raise BehaviorMismatch(f"'10abc'+5 -> {row[0]} (MySQL=15)")
    add("string_arith_coercion", string_to_num)

    def cast_truncate():
        # KNOWN M3: CAST AS CHAR(n) errors when wider
        row = _fetchone("SELECT CAST('abcdef' AS CHAR(3))")
        if row[0] != "abc":
            raise BehaviorMismatch(f"CAST wide->CHAR(3) -> {row[0]!r} (MySQL='abc')")
    add("cast_char_truncate", cast_truncate)

    def division_by_zero():
        row = _fetchone("SELECT 1/0")
        # MySQL default returns NULL with a warning
        if row[0] is not None:
            raise BehaviorMismatch(f"1/0 -> {row[0]} (MySQL=NULL)")
    add("division_by_zero", division_by_zero)

    def boolean_type():
        row = _fetchone("SELECT TRUE, FALSE")
        if (row[0], row[1]) != (1, 0):
            raise BehaviorMismatch(f"TRUE/FALSE -> {row}")
    add("boolean_literals", boolean_type)

    return items


def _show_scenarios():
    items = []

    def add(name, fn):
        items.append(("show", name, fn))

    # Statements that simply need to run (row count is environment-dependent).
    for stmt in ["SHOW DATABASES", "SHOW TABLES", "SHOW PROCESSLIST", "SHOW GRANTS"]:
        def mk_run(s):
            def run():
                rows = _fetchall(s)
                return f"{s} -> {len(rows)} rows"
            return run
        add(stmt.lower().replace(" ", "_"), mk_run(stmt))

    # Statements MySQL returns non-empty rows for but MatrixOne returns 0 (KNOWN M4).
    for stmt in [
        "SHOW VARIABLES LIKE 'version'", "SHOW STATUS", "SHOW ENGINES",
        "SHOW CHARACTER SET", "SHOW COLLATION", "SHOW WARNINGS",
    ]:
        def mk_assert(s):
            def run():
                rows = _fetchall(s)
                if not rows:
                    raise BehaviorMismatch(f"{s} returned 0 rows (MySQL non-empty)")
                return f"{s} -> {len(rows)} rows"
            return run
        add(stmt.lower().replace(" ", "_").replace("'", ""), mk_assert(stmt))

    return items


# ===========================================================================
# Additional generated matrices to broaden coverage.
# ===========================================================================

# Binary / comparison / arithmetic / logical operators: (label, expr, group)
OPERATORS = [
    ("add", "5 + 3", "arith"), ("sub", "5 - 3", "arith"),
    ("mul", "5 * 3", "arith"), ("div", "7 / 2", "arith"),
    ("intdiv", "7 DIV 2", "arith"), ("mod", "7 % 3", "arith"),
    ("mod_kw", "7 MOD 3", "arith"), ("neg", "- 5", "arith"),
    ("eq", "5 = 5", "compare"), ("ne", "5 <> 4", "compare"),
    ("ne2", "5 != 4", "compare"), ("lt", "4 < 5", "compare"),
    ("le", "4 <= 4", "compare"), ("gt", "5 > 4", "compare"),
    ("ge", "5 >= 5", "compare"), ("nullsafe_eq", "1 <=> 1", "compare"),
    ("between", "5 BETWEEN 1 AND 10", "compare"),
    ("not_between", "5 NOT BETWEEN 6 AND 10", "compare"),
    ("in", "5 IN (1,5,9)", "compare"), ("not_in", "5 NOT IN (1,2,3)", "compare"),
    ("is_null", "NULL IS NULL", "compare"),
    ("is_not_null", "1 IS NOT NULL", "compare"),
    ("like", "'abc' LIKE 'a%'", "compare"),
    ("not_like", "'abc' NOT LIKE 'z%'", "compare"),
    ("regexp", "'abc' REGEXP '^a'", "compare"),
    ("and", "1 AND 1", "logic"), ("or", "0 OR 1", "logic"),
    ("not", "NOT 0", "logic"), ("xor", "1 XOR 0", "logic"),
    ("bit_and", "12 & 10", "bit"), ("bit_or", "12 | 10", "bit"),
    ("bit_xor", "12 ^ 10", "bit"), ("bit_not", "~ 0", "bit"),
    ("shift_left", "1 << 4", "bit"), ("shift_right", "256 >> 2", "bit"),
]


def _operator(label, expr):
    def run():
        _fetchone(f"SELECT {expr}")
        return f"op {label}"
    return run


def _operator_in_where(label, expr):
    def run():
        _fetchall(f"SELECT 1 FROM (SELECT 1 x) d WHERE {expr}")
        return f"op-where {label}"
    return run


# CAST target-type matrix: (label, cast_target, sample_value)
CAST_TARGETS = [
    ("signed", "SIGNED", "'42'"),
    ("unsigned", "UNSIGNED", "'42'"),
    ("decimal", "DECIMAL(10,2)", "'1.5'"),
    ("char", "CHAR", "42"),
    ("date", "DATE", "'2026-06-23'"),
    ("datetime", "DATETIME", "'2026-06-23 1:2:3'"),
    ("time", "TIME", "'11:22:33'"),
    ("binary", "BINARY", "'abc'"),
    ("double", "DOUBLE", "'3.14'"),
    ("float", "FLOAT", "'3.14'"),
    ("json", "JSON", "'[1,2,3]'"),
]


def _cast(label, target, value):
    def run():
        _fetchone(f"SELECT CAST({value} AS {target})")
        return f"cast {label}"
    return run


def _convert(label, target, value):
    def run():
        _fetchone(f"SELECT CONVERT({value}, {target})")
        return f"convert {label}"
    return run


# Charset/collation DDL matrix: (label, clause)
COLLATIONS = [
    ("utf8mb4_bin", "CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"),
    ("utf8mb4_general_ci", "CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"),
    ("utf8mb4_0900_ai_ci", "CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"),
    ("utf8mb4_unicode_ci", "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"),
    ("latin1_swedish_ci", "CHARACTER SET latin1 COLLATE latin1_swedish_ci"),
    ("ascii_bin", "CHARACTER SET ascii COLLATE ascii_bin"),
    ("utf8_general_ci", "CHARACTER SET utf8 COLLATE utf8_general_ci"),
]


def _collation_create(label, clause):
    def run():
        with _table(f"id INT PRIMARY KEY, c VARCHAR(50) {clause}") as _t:
            pass
        return f"collation {label}"
    return run


def _collation_case_test(label, clause):
    """Insert 'abc' then check whether 'ABC' counts as a duplicate under UNIQUE."""
    def run():
        with _table(f"id INT PRIMARY KEY, c VARCHAR(50) {clause} UNIQUE") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'abc')")
            try:
                _exec(f"INSERT INTO `{t}` VALUES (2,'ABC')")
                # accepted => case-sensitive. For *_ci collations MySQL rejects.
                if "_ci" in label:
                    raise BehaviorMismatch(
                        f"{label}: 'abc' and 'ABC' both accepted (collation ignored)")
            except BehaviorMismatch:
                raise
            except Exception:
                pass  # rejected as duplicate (case-insensitive)
        return f"collation-case {label}"
    return run


# Function used inside ORDER BY / GROUP BY / HAVING (extra usage variants for a
# curated subset of deterministic scalar functions).
ORDERABLE_FUNCS = [
    ("length", "LENGTH(s)"), ("upper", "UPPER(s)"), ("lower", "LOWER(s)"),
    ("abs", "ABS(n)"), ("round", "ROUND(n)"), ("mod", "MOD(n,3)"),
    ("substring", "SUBSTRING(s,1,2)"), ("concat", "CONCAT(s,'!')"),
    ("coalesce", "COALESCE(n,0)"), ("ceil", "CEIL(n)"), ("floor", "FLOOR(n)"),
    ("ifnull", "IFNULL(n,0)"),
]


def _func_in_orderby(label, expr):
    def run():
        with _table("id INT PRIMARY KEY, n INT, s VARCHAR(20)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10,'aa'),(2,20,'bbb')")
            _fetchall(f"SELECT id FROM `{t}` ORDER BY {expr}")
        return f"orderby-fn {label}"
    return run


def _func_in_groupby(label, expr):
    def run():
        with _table("id INT PRIMARY KEY, n INT, s VARCHAR(20)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10,'aa'),(2,20,'bbb')")
            _fetchall(f"SELECT {expr}, COUNT(*) FROM `{t}` GROUP BY {expr}")
        return f"groupby-fn {label}"
    return run


def _func_in_having(label, expr):
    def run():
        with _table("id INT PRIMARY KEY, n INT, s VARCHAR(20)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10,'aa'),(2,20,'bbb')")
            # Wrap the function in MAX() so the HAVING is valid under
            # ONLY_FULL_GROUP_BY (the column isn't in GROUP BY).
            _fetchall(
                f"SELECT n, COUNT(*) FROM `{t}` GROUP BY n "
                f"HAVING MAX({expr}) IS NOT NULL")
        return f"having-fn {label}"
    return run


# Aggregate with DISTINCT / over different column types.
def _aggregate_distinct(label, expr_tpl):
    def run():
        with _table("g INT, c INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10),(1,10),(2,20)")
            expr = expr_tpl.format(c="DISTINCT c")
            _fetchall(f"SELECT {expr} FROM `{t}`")
        return f"agg-distinct {label}"
    return run


# JOIN-pattern matrix over types: a small set of join styles applied to several
# join-key column types.
JOIN_KEY_TYPES = [
    ("int", "INT", "1"), ("bigint", "BIGINT", "1"),
    ("varchar", "VARCHAR(20)", "'k1'"), ("char", "CHAR(8)", "'k1'"),
    ("date", "DATE", "'2026-06-23'"), ("decimal", "DECIMAL(10,2)", "1.50"),
]
JOIN_STYLES = ["JOIN", "LEFT JOIN", "RIGHT JOIN", "INNER JOIN"]


def _join_on_type(style, keylabel, keytype, keyval):
    def run():
        a = M.uname("jt_a")
        b = M.uname("jt_b")
        _exec(f"CREATE TABLE `{a}` (id INT PRIMARY KEY, k {keytype})")
        _exec(f"CREATE TABLE `{b}` (id INT PRIMARY KEY, k {keytype})")
        try:
            _exec(f"INSERT INTO `{a}` VALUES (1,{keyval})")
            _exec(f"INSERT INTO `{b}` VALUES (1,{keyval})")
            _fetchall(f"SELECT a.id FROM `{a}` a {style} `{b}` b ON a.k = b.k")
        finally:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{a}`")
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{b}`")
        return f"{style} on {keylabel}"
    return run


# Default-value DDL matrix over types.
def _default_value(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype} DEFAULT {lit}") as t:
            _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
            _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
        return f"default {label}"
    return run


# Auto-increment behaviour over integer types.
AUTO_INC_TYPES = ["TINYINT", "SMALLINT", "MEDIUMINT", "INT", "BIGINT",
                  "INT UNSIGNED", "BIGINT UNSIGNED"]


def _autoincrement(sqltype):
    def run():
        with _table(f"id {sqltype} AUTO_INCREMENT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` (n) VALUES (1),(2),(3)")
            rows = _fetchall(f"SELECT id FROM `{t}` ORDER BY id")
            if [r[0] for r in rows] != [1, 2, 3]:
                raise BehaviorMismatch(f"auto-increment {sqltype}: {rows}")
        return f"autoinc {sqltype}"
    return run


# Subquery-position matrix: same subquery used in SELECT/WHERE/FROM/HAVING.
def _subquery_position(position):
    def run():
        with _table("id INT PRIMARY KEY, g INT, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1,10),(2,1,20),(3,2,30)")
            if position == "select":
                _fetchall(f"SELECT id, (SELECT MAX(n) FROM `{t}`) FROM `{t}`")
            elif position == "where":
                _fetchall(f"SELECT id FROM `{t}` WHERE n > (SELECT AVG(n) FROM `{t}`)")
            elif position == "from":
                _fetchall(f"SELECT * FROM (SELECT g, SUM(n) s FROM `{t}` GROUP BY g) x")
            elif position == "having":
                _fetchall(
                    f"SELECT g, SUM(n) FROM `{t}` GROUP BY g "
                    f"HAVING SUM(n) > (SELECT AVG(n) FROM `{t}`)")
            elif position == "in":
                _fetchall(f"SELECT id FROM `{t}` WHERE n IN (SELECT n FROM `{t}`)")
            elif position == "exists":
                _fetchall(
                    f"SELECT id FROM `{t}` a WHERE EXISTS "
                    f"(SELECT 1 FROM `{t}` b WHERE b.g=a.g AND b.n>a.n)")
        return f"subquery-{position}"
    return run


# Index variety matrix.
def _index_variety(label, ddl_extra, index_sql):
    def run():
        with _table(f"id INT PRIMARY KEY, a INT, b INT, s VARCHAR(50){ddl_extra}") as t:
            _exec(index_sql.format(t=t))
        return f"index {label}"
    return run


INDEX_VARIETIES = [
    ("single", "", "CREATE INDEX ix1 ON `{t}` (a)"),
    ("composite", "", "CREATE INDEX ix2 ON `{t}` (a, b)"),
    ("unique", "", "CREATE UNIQUE INDEX ux1 ON `{t}` (a)"),
    ("prefix", "", "CREATE INDEX ix3 ON `{t}` (s(10))"),
    ("desc", "", "CREATE INDEX ix4 ON `{t}` (a DESC)"),
    ("alter_add_index", "", "ALTER TABLE `{t}` ADD INDEX ix5 (b)"),
    ("alter_add_unique", "", "ALTER TABLE `{t}` ADD UNIQUE (a)"),
]


# --- Per-type extended operations (distinct/count/cast/coalesce/min-max). ----
def _type_distinct(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit}),(2,{lit})")
            _fetchall(f"SELECT DISTINCT c FROM `{t}`")
        return f"distinct {label}"
    return run


def _type_count(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _fetchone(f"SELECT COUNT(c), COUNT(DISTINCT c) FROM `{t}`")
        return f"count {label}"
    return run


def _type_minmax(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _fetchone(f"SELECT MIN(c), MAX(c) FROM `{t}`")
        return f"minmax {label}"
    return run


def _type_coalesce(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype} NULL") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,NULL)")
            _fetchone(f"SELECT COALESCE(c, {lit}) FROM `{t}` WHERE id=1")
        return f"coalesce {label}"
    return run


def _type_in_filter(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _fetchall(f"SELECT id FROM `{t}` WHERE c IN ({lit})")
        return f"in_filter {label}"
    return run


def _type_case_when(label, sqltype, value, quoted):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _fetchall(
                f"SELECT CASE WHEN c = {lit} THEN 'y' ELSE 'n' END FROM `{t}`")
        return f"case {label}"
    return run


def _type_prepared_roundtrip(label, sqltype, value, quoted):
    """Insert + select a value of this type through a bound parameter."""
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            pyval = value
            # cast quoted-but-numeric handling: pass raw python str/number
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (%s, %s)", (1, pyval))
            _fetchall(f"SELECT c FROM `{t}` WHERE c = %s", (pyval,))
        return f"prepared {label}"
    return run


# --- Temporal DATE_FORMAT specifier matrix. --------------------------------
DATE_FORMAT_SPECS = [
    "%Y", "%y", "%m", "%c", "%d", "%e", "%H", "%h", "%i", "%s", "%p",
    "%M", "%b", "%W", "%a", "%j", "%U", "%u", "%D", "%r", "%T", "%f",
    "%Y-%m-%d", "%H:%i:%s", "%d/%m/%Y", "%W %M %Y",
]


def _date_format_spec(spec):
    def run():
        _fetchone(f"SELECT DATE_FORMAT('2026-06-23 14:05:09.123456', %s)", (spec,))
        return f"date_format {spec}"
    return run


# --- INTERVAL unit matrix for DATE_ADD. ------------------------------------
INTERVAL_UNITS = [
    "MICROSECOND", "SECOND", "MINUTE", "HOUR", "DAY", "WEEK", "MONTH",
    "QUARTER", "YEAR", "SECOND_MICROSECOND", "MINUTE_SECOND", "HOUR_MINUTE",
    "DAY_HOUR", "YEAR_MONTH",
]


def _interval_unit(unit):
    def run():
        val = "1" if "_" not in unit else "'1:1'"
        _fetchone(f"SELECT DATE_ADD('2026-06-23 10:00:00', INTERVAL {val} {unit})")
        return f"interval {unit}"
    return run


# --- EXTRACT unit matrix. --------------------------------------------------
EXTRACT_UNITS = [
    "MICROSECOND", "SECOND", "MINUTE", "HOUR", "DAY", "WEEK", "MONTH",
    "QUARTER", "YEAR", "SECOND_MICROSECOND", "MINUTE_SECOND", "HOUR_SECOND",
    "DAY_HOUR", "DAY_MINUTE", "YEAR_MONTH",
]


def _extract_unit(unit):
    def run():
        _fetchone(f"SELECT EXTRACT({unit} FROM '2026-06-23 14:05:09.123456')")
        return f"extract {unit}"
    return run


# --- Literal-value roundtrip matrix (SQL literal parsing/evaluation). ------
LITERALS = [
    ("int", "42"), ("neg_int", "-42"), ("zero", "0"),
    ("float", "3.14159"), ("sci", "1.5e3"), ("decimal", "12345.6789"),
    # hex/binary literals are rendered through HEX() so the driver receives
    # printable text (a raw 0xFF byte would break utf8mb4 decoding — a separate
    # driver-level concern covered by the binary type roundtrip tests).
    ("hex_literal", "HEX(0xFF)"), ("hex_str", "HEX(X'414243')"),
    ("bit_literal", "b'1010' + 0"),
    ("string", "'hello'"), ("escaped", "'it''s'"), ("empty_str", "''"),
    ("unicode", "'café'"), ("emoji", "'\\U0001F600'"), ("null", "NULL"),
    ("true", "TRUE"), ("false", "FALSE"),
    ("date", "DATE '2026-06-23'"), ("time", "TIME '11:22:33'"),
    ("timestamp", "TIMESTAMP '2026-06-23 11:22:33'"),
    ("json_obj", "JSON_OBJECT('a',1)"), ("interval", "INTERVAL 5 DAY"),
]


def _literal(label, lit):
    def run():
        _fetchone(f"SELECT {lit}")
        return f"literal {label}"
    return run


# --- String concat-with-self / comparison per string type. -----------------
STRING_TYPES = [
    ("char10", "CHAR(10)"), ("varchar64", "VARCHAR(64)"), ("text", "TEXT"),
    ("tinytext", "TINYTEXT"), ("mediumtext", "MEDIUMTEXT"), ("longtext", "LONGTEXT"),
]


def _string_concat(label, sqltype):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'abc')")
            _fetchone(f"SELECT CONCAT(c, c) FROM `{t}` WHERE id=1")
        return f"concat-self {label}"
    return run


def _string_like(label, sqltype):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef')")
            _fetchall(f"SELECT id FROM `{t}` WHERE c LIKE 'abc%'")
        return f"like {label}"
    return run


def _string_length_fns(label, sqltype):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef')")
            _fetchone(
                f"SELECT LENGTH(c), CHAR_LENGTH(c), UPPER(c), LOWER(c) "
                f"FROM `{t}` WHERE id=1")
        return f"strfns {label}"
    return run


# --- Comparison operator x type matrix on a stored column. -----------------
COMPARE_OPS = ["=", "<>", "<", "<=", ">", ">=", "<=>"]


def _compare_op_on_type(label, sqltype, value, quoted, op):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            lit = _quote(value, quoted)
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _fetchall(f"SELECT id FROM `{t}` WHERE c {op} {lit}")
        return f"{op} {label}"
    return run


# --- NULL-propagation per function: f(NULL) should be NULL. ----------------
NULL_PROP_FUNCS = [
    ("length", "LENGTH(NULL)"), ("upper", "UPPER(NULL)"),
    ("concat", "CONCAT('a', NULL)"), ("abs", "ABS(NULL)"),
    ("round", "ROUND(NULL)"), ("substring", "SUBSTRING(NULL,1,2)"),
    ("year", "YEAR(NULL)"), ("date_format", "DATE_FORMAT(NULL,'%Y')"),
    ("trim", "TRIM(NULL)"), ("replace", "REPLACE(NULL,'a','b')"),
    ("coalesce", "COALESCE(NULL, NULL, 5)"), ("ifnull", "IFNULL(NULL, 7)"),
    ("greatest", "GREATEST(1, NULL, 3)"), ("least", "LEAST(1, NULL, 3)"),
    ("add", "1 + NULL"), ("concat_op", "CONCAT(1, NULL)"),
]


def _null_prop(label, expr, expect_null):
    def run():
        row = _fetchone(f"SELECT {expr}")
        is_null = row[0] is None
        if expect_null and not is_null:
            raise BehaviorMismatch(f"{expr} -> {row[0]!r} (MySQL=NULL)")
        if not expect_null and is_null:
            raise BehaviorMismatch(f"{expr} -> NULL (MySQL non-NULL)")
        return f"null-prop {label}"
    return run


# --- Numeric precision / rounding behaviour matrix. ------------------------
PRECISION_CASES = [
    ("decimal_div", "SELECT 10.0 / 3.0", None),
    ("decimal_mul", "SELECT 1.1 * 1.1", None),
    ("float_sum", "SELECT 0.1 + 0.2", None),
    ("int_div_floor", "SELECT 7 DIV 2", None),
    ("decimal_round_half_up", "SELECT ROUND(2.5), ROUND(3.5), ROUND(-2.5)", None),
    ("avg_scale", "SELECT AVG(n) FROM (SELECT 1 n UNION SELECT 2) x", None),
    ("sum_decimal", "SELECT SUM(c) FROM (SELECT 1.50 c UNION ALL SELECT 2.25) x", None),
    ("decimal_compare", "SELECT 0.1 + 0.2 = 0.3", None),
    ("cast_round", "SELECT CAST(2.5 AS SIGNED), CAST(3.5 AS SIGNED)", None),
    ("mod_decimal", "SELECT MOD(10.5, 3)", None),
]


def _precision_case(sql):
    def run():
        _fetchone(sql)
        return "precision"
    return run


# --- ENUM / SET behaviour matrix. ------------------------------------------
def _enum_scenarios_list():
    out = []

    def insert_valid():
        with _table("id INT PRIMARY KEY, c ENUM('a','b','c')") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'b')")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row[0] != "b":
                raise BehaviorMismatch(f"enum stored {row[0]!r}")
        return "enum valid"
    out.append(("enum_insert_valid", insert_valid))

    def insert_invalid():
        with _table("id INT PRIMARY KEY, c ENUM('a','b','c')") as t:
            try:
                _exec(f"INSERT INTO `{t}` VALUES (1,'z')")
                row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
                # MySQL strict mode rejects invalid enum
                raise BehaviorMismatch(f"invalid enum 'z' accepted -> {row[0]!r}")
            except BehaviorMismatch:
                raise
            except Exception:
                pass
        return "enum invalid rejected"
    out.append(("enum_insert_invalid", insert_invalid))

    def enum_ordinal():
        with _table("id INT PRIMARY KEY, c ENUM('lo','mid','hi')") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'hi'),(2,'lo')")
            _fetchall(f"SELECT c FROM `{t}` ORDER BY c+0")
        return "enum ordinal sort"
    out.append(("enum_ordinal_sort", enum_ordinal))

    def set_multi():
        with _table("id INT PRIMARY KEY, c SET('x','y','z')") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'x,z')")
            _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
        return "set multi"
    out.append(("set_multi_value", set_multi))

    def set_find():
        with _table("id INT PRIMARY KEY, c SET('x','y','z')") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'x,z')")
            _fetchall(f"SELECT id FROM `{t}` WHERE FIND_IN_SET('z', c)")
        return "set find_in_set"
    out.append(("set_find_in_set", set_find))

    return out


# --- Column-modifier DDL matrix: each modifier on a representative column. --
COLUMN_MODIFIERS = [
    ("not_null", "INT NOT NULL"),
    ("null", "INT NULL"),
    ("default_int", "INT DEFAULT 7"),
    ("default_str", "VARCHAR(20) DEFAULT 'x'"),
    ("default_now", "DATETIME DEFAULT CURRENT_TIMESTAMP"),
    ("on_update_now", "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"),
    ("unsigned", "INT UNSIGNED"),
    ("zerofill", "INT ZEROFILL"),
    ("auto_increment", "INT AUTO_INCREMENT PRIMARY KEY"),
    ("comment", "INT COMMENT 'a column comment'"),
    ("unique_inline", "INT UNIQUE"),
    ("charset_col", "VARCHAR(20) CHARACTER SET utf8mb4"),
    ("collate_col", "VARCHAR(20) COLLATE utf8mb4_bin"),
    ("decimal_unsigned", "DECIMAL(10,2) UNSIGNED"),
    ("bool_default", "BOOL DEFAULT TRUE"),
    ("enum_default", "ENUM('a','b') DEFAULT 'a'"),
    ("text_no_default", "TEXT"),
    ("invisible", "INT INVISIBLE"),
    ("generated_virtual", "INT GENERATED ALWAYS AS (1+1) VIRTUAL"),
    ("generated_stored", "INT GENERATED ALWAYS AS (1+1) STORED"),
    ("srid", "INT SRID 4326"),
    ("check_inline", "INT CHECK (extra >= 0)"),
]


def _column_modifier(label, coldef):
    def run():
        # auto_increment / primary key modifiers can't coexist with a separate PK
        if "PRIMARY KEY" in coldef or "AUTO_INCREMENT" in coldef:
            ddl = f"extra {coldef}"
        else:
            ddl = f"id INT PRIMARY KEY, extra {coldef}"
        with _table(ddl) as _t:
            pass
        return f"modifier {label}"
    return run


# --- ALTER TABLE operation matrix. -----------------------------------------
ALTER_OPS = [
    ("add_column", "ALTER TABLE `{t}` ADD COLUMN x INT"),
    ("add_column_after", "ALTER TABLE `{t}` ADD COLUMN x INT AFTER a"),
    ("add_column_first", "ALTER TABLE `{t}` ADD COLUMN x INT FIRST"),
    ("drop_column", "ALTER TABLE `{t}` DROP COLUMN b"),
    ("modify_column", "ALTER TABLE `{t}` MODIFY COLUMN a BIGINT"),
    ("change_column", "ALTER TABLE `{t}` CHANGE COLUMN a aa INT"),
    ("rename_column", "ALTER TABLE `{t}` RENAME COLUMN a TO aa"),
    ("alter_set_default", "ALTER TABLE `{t}` ALTER COLUMN a SET DEFAULT 9"),
    ("alter_drop_default", "ALTER TABLE `{t}` ALTER COLUMN a DROP DEFAULT"),
    ("add_index", "ALTER TABLE `{t}` ADD INDEX ix (b)"),
    ("add_unique", "ALTER TABLE `{t}` ADD UNIQUE uq (b)"),
    ("drop_index", "CREATE INDEX preidx ON `{t}` (b); ALTER TABLE `{t}` DROP INDEX preidx"),
    ("add_primary_key", "ALTER TABLE `{t}` ADD PRIMARY KEY (a)"),
    ("rename_table", "ALTER TABLE `{t}` RENAME TO `{t}_renamed`"),
    ("add_column_not_null", "ALTER TABLE `{t}` ADD COLUMN x INT NOT NULL DEFAULT 0"),
    ("convert_charset", "ALTER TABLE `{t}` CONVERT TO CHARACTER SET utf8mb4"),
    ("add_check", "ALTER TABLE `{t}` ADD CONSTRAINT ck CHECK (a > 0)"),
    ("comment", "ALTER TABLE `{t}` COMMENT = 'tbl comment'"),
    ("auto_increment_val", "ALTER TABLE `{t}` AUTO_INCREMENT = 1000"),
    ("add_fulltext", "ALTER TABLE `{t}` ADD FULLTEXT(c)"),
]


def _alter_op(label, sql_tpl):
    def run():
        with _table("a INT, b INT, c VARCHAR(50)") as t:
            for stmt in sql_tpl.split(";"):
                stmt = stmt.strip()
                if stmt:
                    _exec(stmt.format(t=t))
            # clean up a possible rename
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{t}_renamed`")
        return f"alter {label}"
    return run


# --- INSERT-variant matrix. ------------------------------------------------
def _insert_variants_list():
    out = []

    def values_default():
        with _table("id INT PRIMARY KEY, n INT DEFAULT 5") as t:
            _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
        return "insert default"
    out.append(("insert_default", values_default))

    def insert_set():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` SET id=1, n=2")
        return "insert set"
    out.append(("insert_set_syntax", insert_set))

    def insert_select_where():
        with _table("id INT PRIMARY KEY, n INT") as a:
            _exec(f"INSERT INTO `{a}` VALUES (1,10),(2,20)")
            with _table("id INT PRIMARY KEY, n INT") as b:
                _exec(f"INSERT INTO `{b}` SELECT id, n FROM `{a}` WHERE n > 10")
        return "insert select where"
    out.append(("insert_select_where", insert_select_where))

    def insert_multi_default():
        with _table("id INT AUTO_INCREMENT PRIMARY KEY, n INT DEFAULT 0") as t:
            _exec(f"INSERT INTO `{t}` () VALUES (),(),()")
        return "insert empty rows"
    out.append(("insert_default_rows", insert_multi_default))

    def insert_ignore_dup():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1)")
            _exec(f"INSERT IGNORE INTO `{t}` VALUES (1,2),(2,3)")
            if _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0] != 2:
                raise BehaviorMismatch("insert ignore count")
        return "insert ignore dup"
    out.append(("insert_ignore_dup", insert_ignore_dup))

    def insert_on_dup_values():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1)")
            _exec(f"INSERT INTO `{t}` VALUES (1,5) "
                  f"ON DUPLICATE KEY UPDATE n = VALUES(n)")
        return "on dup VALUES()"
    out.append(("on_dup_values_fn", insert_on_dup_values))

    return out


# --- Bound-parameter type matrix: insert + select + where via %s for each
#     python value type the driver must round-trip. -------------------------
import datetime as _dt  # noqa: E402
import decimal as _dec  # noqa: E402

BIND_VALUES = [
    ("int", "INT", 42),
    ("bigint", "BIGINT", 9000000000000000000),
    ("negative", "INT", -123),
    ("zero", "INT", 0),
    ("float", "DOUBLE", 3.14159),
    ("decimal", "DECIMAL(10,2)", _dec.Decimal("123.45")),
    ("string", "VARCHAR(64)", "hello world"),
    ("empty_string", "VARCHAR(64)", ""),
    ("unicode", "VARCHAR(64)", "café ✓ 日本語"),
    ("long_string", "TEXT", "x" * 5000),
    ("bytes", "VARBINARY(64)", b"binary\x00data"),
    ("date", "DATE", _dt.date(2026, 6, 23)),
    ("time", "TIME", _dt.time(11, 22, 33)),
    ("datetime", "DATETIME", _dt.datetime(2026, 6, 23, 11, 22, 33)),
    ("bool_true", "BOOL", True),
    ("bool_false", "BOOL", False),
    ("none", "INT", None),
]


def _bind_insert_select(label, sqltype, value):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (%s, %s)", (1, value))
            _fetchone(f"SELECT c FROM `{t}` WHERE id = %s", (1,))
        return f"bind-insert {label}"
    return run


def _bind_where(label, sqltype, value):
    def run():
        if value is None:
            raise SkipScenario("NULL not usable in equality where")
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (%s, %s)", (1, value))
            _fetchall(f"SELECT id FROM `{t}` WHERE c = %s", (value,))
        return f"bind-where {label}"
    return run


def _bind_update(label, sqltype, value):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (%s, %s)", (1, value))
            _exec(f"UPDATE `{t}` SET c = %s WHERE id = %s", (value, 1))
        return f"bind-update {label}"
    return run


# --- Query-shape matrix over a richer seeded dataset. ----------------------
def _seed_dataset():
    """Create + seed a department/employee pair; returns (dept, emp) names."""
    dept = M.uname("qs_dept")
    emp = M.uname("qs_emp")
    _exec(f"CREATE TABLE `{dept}` (id INT PRIMARY KEY, name VARCHAR(30))")
    _exec(f"CREATE TABLE `{emp}` (id INT PRIMARY KEY, dept_id INT, "
          f"name VARCHAR(30), salary DECIMAL(10,2))")
    _exec(f"INSERT INTO `{dept}` VALUES (1,'Eng'),(2,'Sales'),(3,'HR')")
    _exec(f"INSERT INTO `{emp}` VALUES "
          f"(1,1,'Ann',100.00),(2,1,'Bob',120.00),(3,2,'Cy',90.00),"
          f"(4,2,'Di',110.00),(5,3,'Ed',80.00)")
    return dept, emp


QUERY_SHAPES = [
    ("inner_join_agg",
     "SELECT d.name, COUNT(*), AVG(e.salary) FROM `{emp}` e "
     "JOIN `{dept}` d ON e.dept_id=d.id GROUP BY d.name"),
    ("left_join_null",
     "SELECT d.name, e.name FROM `{dept}` d "
     "LEFT JOIN `{emp}` e ON e.dept_id=d.id"),
    ("having_avg",
     "SELECT dept_id, AVG(salary) a FROM `{emp}` GROUP BY dept_id HAVING a > 95"),
    ("subquery_in",
     "SELECT name FROM `{emp}` WHERE dept_id IN "
     "(SELECT id FROM `{dept}` WHERE name <> 'HR')"),
    ("correlated_max",
     "SELECT name, salary FROM `{emp}` e WHERE salary = "
     "(SELECT MAX(salary) FROM `{emp}` x WHERE x.dept_id=e.dept_id)"),
    ("window_rank",
     "SELECT name, dept_id, RANK() OVER "
     "(PARTITION BY dept_id ORDER BY salary DESC) FROM `{emp}`"),
    ("window_running_sum",
     "SELECT name, SUM(salary) OVER (ORDER BY id) FROM `{emp}`"),
    ("cte_join",
     "WITH high AS (SELECT * FROM `{emp}` WHERE salary > 100) "
     "SELECT d.name, h.name FROM high h JOIN `{dept}` d ON h.dept_id=d.id"),
    ("union_depts",
     "SELECT name FROM `{dept}` UNION SELECT name FROM `{emp}`"),
    ("order_limit",
     "SELECT name, salary FROM `{emp}` ORDER BY salary DESC LIMIT 3"),
    ("count_distinct",
     "SELECT COUNT(DISTINCT dept_id) FROM `{emp}`"),
    ("case_bucket",
     "SELECT name, CASE WHEN salary >= 110 THEN 'high' "
     "WHEN salary >= 90 THEN 'mid' ELSE 'low' END FROM `{emp}`"),
    ("group_concat",
     "SELECT dept_id, GROUP_CONCAT(name) FROM `{emp}` GROUP BY dept_id"),
    ("exists_corr",
     "SELECT d.name FROM `{dept}` d WHERE EXISTS "
     "(SELECT 1 FROM `{emp}` e WHERE e.dept_id=d.id AND e.salary > 100)"),
    ("not_exists",
     "SELECT d.name FROM `{dept}` d WHERE NOT EXISTS "
     "(SELECT 1 FROM `{emp}` e WHERE e.dept_id=d.id)"),
    ("self_join_salary",
     "SELECT a.name, b.name FROM `{emp}` a JOIN `{emp}` b "
     "ON a.dept_id=b.dept_id AND a.salary < b.salary"),
    ("derived_avg",
     "SELECT * FROM (SELECT dept_id, AVG(salary) a FROM `{emp}` "
     "GROUP BY dept_id) t WHERE t.a > 90"),
    ("nested_subquery",
     "SELECT name FROM `{emp}` WHERE salary > "
     "(SELECT AVG(salary) FROM `{emp}` WHERE dept_id IN "
     "(SELECT id FROM `{dept}`))"),
    ("multi_join_three",
     "SELECT e.name FROM `{emp}` e JOIN `{dept}` d ON e.dept_id=d.id "
     "JOIN `{dept}` d2 ON d.id=d2.id"),
    ("aggregate_all",
     "SELECT COUNT(*), SUM(salary), AVG(salary), MIN(salary), MAX(salary) "
     "FROM `{emp}`"),
]


def _query_shape(label, sql_tpl):
    def run():
        dept, emp = _seed_dataset()
        try:
            _fetchall(sql_tpl.format(dept=dept, emp=emp))
        finally:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{emp}`")
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{dept}`")
        return f"shape {label}"
    return run


# --- Function-over-stored-column matrix (string & math & date). ------------
STR_COL_FUNCS = [
    "UPPER(s)", "LOWER(s)", "LENGTH(s)", "CHAR_LENGTH(s)", "REVERSE(s)",
    "TRIM(s)", "LTRIM(s)", "RTRIM(s)", "CONCAT(s,'!')", "SUBSTRING(s,1,3)",
    "LEFT(s,3)", "RIGHT(s,3)", "REPLACE(s,'a','X')", "LPAD(s,12,'*')",
    "RPAD(s,12,'*')", "REPEAT(s,2)", "LOCATE('a',s)", "INSTR(s,'b')",
    "MD5(s)", "SHA1(s)", "SHA2(s,256)", "HEX(s)", "TO_BASE64(s)", "ASCII(s)",
    "SUBSTRING_INDEX(s,'a',1)", "REGEXP_REPLACE(s,'[aeiou]','_')",
    "CONCAT_WS('-',s,s)", "QUOTE(s)", "SOUNDEX(s)", "FIELD(s,'a','b')",
]
MATH_COL_FUNCS = [
    "ABS(n)", "CEIL(n)", "FLOOR(n)", "ROUND(n)", "ROUND(n,1)", "TRUNCATE(n,0)",
    "SQRT(n)", "POWER(n,2)", "MOD(n,3)", "SIGN(n)", "EXP(n)", "LN(n)",
    "LOG10(n)", "LOG2(n)", "SIN(n)", "COS(n)", "TAN(n)", "ATAN(n)",
    "DEGREES(n)", "RADIANS(n)", "GREATEST(n,50)", "LEAST(n,50)", "n+1",
    "n*2", "n-1", "n/2", "n DIV 2", "-n", "n%3", "BIN(n)",
]
DATE_COL_FUNCS = [
    "YEAR(d)", "MONTH(d)", "DAY(d)", "HOUR(d)", "MINUTE(d)", "SECOND(d)",
    "QUARTER(d)", "WEEK(d)", "DAYOFWEEK(d)", "DAYOFYEAR(d)", "DAYNAME(d)",
    "MONTHNAME(d)", "LAST_DAY(d)", "DATE(d)", "TIME(d)",
    "DATE_ADD(d,INTERVAL 1 DAY)", "DATE_SUB(d,INTERVAL 1 MONTH)",
    "DATE_FORMAT(d,'%Y-%m')", "TO_DAYS(d)", "UNIX_TIMESTAMP(d)",
    "DATEDIFF(d,'2026-01-01')", "TIMESTAMPDIFF(DAY,'2026-01-01',d)",
    "EXTRACT(YEAR FROM d)", "WEEKDAY(d)", "YEARWEEK(d)",
]


def _func_over_col(group, expr):
    def run():
        if group == "str":
            with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef')")
                _fetchall(f"SELECT {expr} FROM `{t}`")
        elif group == "math":
            with _table("id INT PRIMARY KEY, n DOUBLE") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,16.5)")
                _fetchall(f"SELECT {expr} FROM `{t}`")
        else:  # date
            with _table("id INT PRIMARY KEY, d DATETIME") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'2026-06-23 14:05:09')")
                _fetchall(f"SELECT {expr} FROM `{t}`")
        return f"{group}-col {expr}"
    return run


def _func_over_col_label(expr):
    # turn "SUBSTRING(s,1,3)" into a compact label
    import re as _re
    return _re.sub(r"[^a-zA-Z0-9]+", "_", expr).strip("_").lower()[:40]


def _func_over_col_where(group, expr):
    def run():
        if group == "str":
            with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef')")
                _fetchall(f"SELECT id FROM `{t}` WHERE {expr} IS NOT NULL")
        elif group == "math":
            with _table("id INT PRIMARY KEY, n DOUBLE") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,16.5)")
                _fetchall(f"SELECT id FROM `{t}` WHERE {expr} IS NOT NULL")
        else:
            with _table("id INT PRIMARY KEY, d DATETIME") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'2026-06-23 14:05:09')")
                _fetchall(f"SELECT id FROM `{t}` WHERE {expr} IS NOT NULL")
        return f"{group}-where {expr}"
    return run


def _func_over_col_orderby(group, expr):
    def run():
        if group == "str":
            with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef'),(2,'ghijkl')")
                _fetchall(f"SELECT id FROM `{t}` ORDER BY {expr}")
        elif group == "math":
            with _table("id INT PRIMARY KEY, n DOUBLE") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,16.5),(2,4.0)")
                _fetchall(f"SELECT id FROM `{t}` ORDER BY {expr}")
        else:
            with _table("id INT PRIMARY KEY, d DATETIME") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'2026-06-23 14:05:09'),"
                      f"(2,'2025-01-01 00:00:00')")
                _fetchall(f"SELECT id FROM `{t}` ORDER BY {expr}")
        return f"{group}-orderby {expr}"
    return run


def _func_over_col_groupby(group, expr):
    def run():
        if group == "str":
            with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef'),(2,'abcdef'),(3,'ghijkl')")
                _fetchall(f"SELECT {expr}, COUNT(*) FROM `{t}` GROUP BY {expr}")
        elif group == "math":
            with _table("id INT PRIMARY KEY, n DOUBLE") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,16.5),(2,16.5),(3,4.0)")
                _fetchall(f"SELECT {expr}, COUNT(*) FROM `{t}` GROUP BY {expr}")
        else:
            with _table("id INT PRIMARY KEY, d DATETIME") as t:
                _exec(f"INSERT INTO `{t}` VALUES (1,'2026-06-23 14:05:09'),"
                      f"(2,'2026-06-23 14:05:09'),(3,'2025-01-01 00:00:00')")
                _fetchall(f"SELECT {expr}, COUNT(*) FROM `{t}` GROUP BY {expr}")
        return f"{group}-groupby {expr}"
    return run


# --- Aggregate-function x window-clause matrix. ----------------------------
WINDOW_CLAUSES = [
    ("partition", "OVER (PARTITION BY g)"),
    ("order", "OVER (ORDER BY n)"),
    ("partition_order", "OVER (PARTITION BY g ORDER BY n)"),
    ("rows_unbounded", "OVER (ORDER BY n ROWS UNBOUNDED PRECEDING)"),
    ("rows_between", "OVER (ORDER BY n ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING)"),
    ("range_current", "OVER (ORDER BY n RANGE CURRENT ROW)"),
    ("empty_over", "OVER ()"),
]
WINDOW_AGG_FUNCS = ["SUM(n)", "AVG(n)", "COUNT(*)", "MIN(n)", "MAX(n)",
                    "ROW_NUMBER()", "RANK()", "DENSE_RANK()"]


def _window_clause(aggfn, clabel, clause):
    def run():
        with _table("id INT PRIMARY KEY, g INT, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40)")
            _fetchall(f"SELECT id, {aggfn} {clause} FROM `{t}`")
        return f"window {aggfn} {clabel}"
    return run


# --- LIKE / pattern-matching matrix. ---------------------------------------
LIKE_PATTERNS = [
    ("prefix", "LIKE 'abc%'"), ("suffix", "LIKE '%xyz'"),
    ("contains", "LIKE '%bcd%'"), ("single", "LIKE 'a_c'"),
    ("exact", "LIKE 'abcdef'"), ("escape", "LIKE 'a\\\\%c' ESCAPE '\\\\'"),
    ("not_like", "NOT LIKE 'z%'"), ("empty", "LIKE ''"),
    ("all_wild", "LIKE '%'"), ("underscore_all", "LIKE '______'"),
    ("regexp_start", "REGEXP '^abc'"), ("regexp_end", "REGEXP 'def$'"),
    ("regexp_class", "REGEXP '[a-c]+'"), ("regexp_alt", "REGEXP 'abc|xyz'"),
    ("regexp_digit", "REGEXP '[0-9]'"), ("rlike", "RLIKE '^a'"),
    ("not_regexp", "NOT REGEXP '^z'"),
]


def _like_pattern(label, pattern):
    def run():
        with _table("id INT PRIMARY KEY, s VARCHAR(50)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,'abcdef'),(2,'xyzdef')")
            _fetchall(f"SELECT id FROM `{t}` WHERE s {pattern}")
        return f"like {label}"
    return run


# --- GROUP BY variants matrix. ---------------------------------------------
GROUP_BY_VARIANTS = [
    ("single", "SELECT g, COUNT(*) FROM `{t}` GROUP BY g"),
    ("multi", "SELECT g, n, COUNT(*) FROM `{t}` GROUP BY g, n"),
    ("expr", "SELECT g+1, COUNT(*) FROM `{t}` GROUP BY g+1"),
    ("position", "SELECT g, COUNT(*) FROM `{t}` GROUP BY 1"),
    ("having_count", "SELECT g, COUNT(*) c FROM `{t}` GROUP BY g HAVING c > 0"),
    ("rollup", "SELECT g, SUM(n) FROM `{t}` GROUP BY g WITH ROLLUP"),
    ("order_after", "SELECT g, SUM(n) s FROM `{t}` GROUP BY g ORDER BY s DESC"),
    ("distinct_in_agg", "SELECT g, COUNT(DISTINCT n) FROM `{t}` GROUP BY g"),
    ("multiple_aggs",
     "SELECT g, COUNT(*), SUM(n), AVG(n), MIN(n), MAX(n) FROM `{t}` GROUP BY g"),
    ("group_concat_order",
     "SELECT g, GROUP_CONCAT(n ORDER BY n DESC) FROM `{t}` GROUP BY g"),
    ("group_concat_sep",
     "SELECT g, GROUP_CONCAT(n SEPARATOR '|') FROM `{t}` GROUP BY g"),
]


def _group_by_variant(label, sql_tpl):
    def run():
        with _table("id INT PRIMARY KEY, g INT, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,30)")
            _fetchall(sql_tpl.format(t=t))
        return f"groupby {label}"
    return run


# --- CAST-from-stored-column matrix: cast a typed column to many targets. ---
CAST_FROM_COL = [
    ("int_to_char", "INT", "42", "CAST(c AS CHAR)"),
    ("int_to_decimal", "INT", "42", "CAST(c AS DECIMAL(10,2))"),
    ("int_to_signed", "INT", "42", "CAST(c AS SIGNED)"),
    ("int_to_unsigned", "INT", "42", "CAST(c AS UNSIGNED)"),
    ("int_to_double", "INT", "42", "CAST(c AS DOUBLE)"),
    ("decimal_to_signed", "DECIMAL(10,2)", "42.75", "CAST(c AS SIGNED)"),
    ("decimal_to_char", "DECIMAL(10,2)", "42.75", "CAST(c AS CHAR)"),
    ("varchar_to_signed", "VARCHAR(20)", "'123'", "CAST(c AS SIGNED)"),
    ("varchar_to_decimal", "VARCHAR(20)", "'1.5'", "CAST(c AS DECIMAL(10,2))"),
    ("varchar_to_date", "VARCHAR(20)", "'2026-06-23'", "CAST(c AS DATE)"),
    ("varchar_to_datetime", "VARCHAR(30)", "'2026-06-23 1:2:3'", "CAST(c AS DATETIME)"),
    ("varchar_to_binary", "VARCHAR(20)", "'abc'", "CAST(c AS BINARY)"),
    ("date_to_char", "DATE", "'2026-06-23'", "CAST(c AS CHAR)"),
    ("datetime_to_date", "DATETIME", "'2026-06-23 11:22:33'", "CAST(c AS DATE)"),
    ("datetime_to_time", "DATETIME", "'2026-06-23 11:22:33'", "CAST(c AS TIME)"),
    ("double_to_signed", "DOUBLE", "42.9", "CAST(c AS SIGNED)"),
    ("double_to_decimal", "DOUBLE", "42.9", "CAST(c AS DECIMAL(10,2))"),
    ("int_to_json", "INT", "42", "CAST(c AS JSON)"),
    ("varchar_to_json", "VARCHAR(50)", "'[1,2,3]'", "CAST(c AS JSON)"),
]


def _cast_from_col(label, sqltype, value, cast_expr):
    def run():
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{value})")
            _fetchone(f"SELECT {cast_expr} FROM `{t}` WHERE id=1")
        return f"cast-col {label}"
    return run


# --- CTE / subquery nesting-depth matrix. ----------------------------------
def _nesting_scenarios():
    out = []

    def cte_chain(n):
        def run():
            parts = ["WITH"]
            prev = None
            for i in range(n):
                if i == 0:
                    parts.append(f"c0 AS (SELECT 1 AS v)")
                else:
                    parts.append(f", c{i} AS (SELECT v+1 AS v FROM c{i-1})")
                prev = f"c{i}"
            sql = "".join(parts) + f" SELECT * FROM {prev}"
            _fetchall(sql)
            return f"cte chain {n}"
        return run
    for n in (1, 2, 3, 5, 8):
        out.append((f"cte_chain_{n}", cte_chain(n)))

    def subquery_depth(n):
        def run():
            inner = "SELECT 1 AS v"
            for _ in range(n):
                inner = f"SELECT v FROM ({inner}) x"
            _fetchall(inner)
            return f"subquery depth {n}"
        return run
    for n in (1, 2, 3, 5, 8):
        out.append((f"subquery_depth_{n}", subquery_depth(n)))

    def recursive_depth(n):
        def run():
            _fetchall(
                f"WITH RECURSIVE seq(x) AS (SELECT 1 UNION ALL "
                f"SELECT x+1 FROM seq WHERE x < {n}) SELECT COUNT(*) FROM seq")
            return f"recursive depth {n}"
        return run
    for n in (5, 10, 50, 100, 1000):
        out.append((f"recursive_depth_{n}", recursive_depth(n)))

    return out


# --- SET-operation matrix. -------------------------------------------------
SET_OPS = [
    ("union", "SELECT 1 UNION SELECT 2 UNION SELECT 1"),
    ("union_all", "SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 2"),
    ("union_distinct", "SELECT 1 UNION DISTINCT SELECT 1"),
    ("intersect", "SELECT 1 INTERSECT SELECT 1"),
    ("intersect_all", "SELECT 1 INTERSECT ALL SELECT 1"),
    ("except", "(SELECT 1 UNION SELECT 2) EXCEPT SELECT 1"),
    ("except_all", "(SELECT 1 UNION ALL SELECT 1) EXCEPT ALL SELECT 1"),
    ("union_order", "SELECT 2 AS x UNION SELECT 1 ORDER BY x"),
    ("union_limit", "(SELECT 1) UNION (SELECT 2) LIMIT 1"),
    ("union_three", "SELECT 1 UNION SELECT 2 UNION SELECT 3"),
    ("nested_union", "(SELECT 1 UNION SELECT 2) UNION (SELECT 3 UNION SELECT 4)"),
    ("union_with_where",
     "SELECT 1 WHERE 1=1 UNION SELECT 2 WHERE 1=1"),
]


def _set_op(label, sql):
    def run():
        _fetchall(sql)
        return f"setop {label}"
    return run


# --- Expression-evaluation matrix (arithmetic / boolean / mixed). ----------
EXPRESSIONS = [
    ("int_arith", "SELECT (2 + 3) * 4 - 10 / 2"),
    ("precedence", "SELECT 2 + 3 * 4"),
    ("paren", "SELECT (2 + 3) * 4"),
    ("float_arith", "SELECT 1.5 * 2.0 + 0.5"),
    ("decimal_arith", "SELECT 10.00 / 3"),
    ("mod_chain", "SELECT 17 % 5 % 2"),
    ("bool_and_or", "SELECT (1 AND 0) OR (1 AND 1)"),
    ("bool_not", "SELECT NOT (1 = 1)"),
    ("comparison_chain", "SELECT 1 < 2, 2 <= 2, 3 > 2, 2 >= 3"),
    ("between_and", "SELECT 5 BETWEEN 1 AND 10 AND 7 BETWEEN 5 AND 9"),
    ("in_list", "SELECT 3 IN (1, 2, 3, 4)"),
    ("null_arith", "SELECT 1 + NULL, NULL * 5"),
    ("null_compare", "SELECT NULL = NULL, NULL <=> NULL"),
    ("coalesce_chain", "SELECT COALESCE(NULL, NULL, NULL, 4)"),
    ("case_in_expr", "SELECT 1 + CASE WHEN 1=1 THEN 10 ELSE 0 END"),
    ("string_compare", "SELECT 'a' < 'b', 'abc' = 'abc'"),
    ("mixed_concat", "SELECT CONCAT('val=', 42)"),
    ("nested_func", "SELECT UPPER(CONCAT('a', LOWER('BCD')))"),
    ("bitwise_chain", "SELECT (12 & 10) | (5 ^ 3)"),
    ("shift_chain", "SELECT (1 << 4) >> 2"),
    ("if_in_arith", "SELECT 100 + IF(1>2, 1, -1)"),
    ("greatest_least", "SELECT GREATEST(1,2,3) - LEAST(1,2,3)"),
    ("cast_in_arith", "SELECT CAST('5' AS SIGNED) + 5"),
    ("nullif_coalesce", "SELECT COALESCE(NULLIF(0, 0), 99)"),
    ("interval_arith", "SELECT '2026-06-23' + INTERVAL 1 DAY"),
    ("pow_nest", "SELECT POW(2, POW(2, 3))"),
    ("abs_neg", "SELECT ABS(-5) + ABS(5)"),
    ("round_chain", "SELECT ROUND(ROUND(3.14159, 3), 1)"),
    ("div_zero_guard", "SELECT IFNULL(5 / NULLIF(0, 0), -1)"),
    ("ternary_like", "SELECT IF(5 > 3, IF(2 > 1, 'a', 'b'), 'c')"),
]


def _expression(label, sql):
    def run():
        _fetchone(sql)
        return f"expr {label}"
    return run


# --- DML-operation x type matrix: insert/update/delete WHERE on each type. --
def _dml_insert_type(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit}),(2,{lit})")
            n = _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0]
            if n != 2:
                raise BehaviorMismatch(f"insert {label} count {n}")
        return f"dml-insert {label}"
    return run


def _dml_update_type(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit}),(2,{lit})")
            _exec(f"UPDATE `{t}` SET c = {lit} WHERE c = {lit}")
        return f"dml-update {label}"
    return run


def _dml_delete_type(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit}),(2,{lit})")
            _exec(f"DELETE FROM `{t}` WHERE c = {lit}")
            n = _fetchone(f"SELECT COUNT(*) FROM `{t}`")[0]
            if n != 0:
                raise BehaviorMismatch(f"delete {label} left {n}")
        return f"dml-delete {label}"
    return run


# --- ORDER BY direction / nulls / multi-column matrix. ---------------------
ORDER_VARIANTS = [
    ("asc", "ORDER BY n ASC"),
    ("desc", "ORDER BY n DESC"),
    ("multi", "ORDER BY g ASC, n DESC"),
    ("expr", "ORDER BY n * -1"),
    ("func", "ORDER BY ABS(n)"),
    ("position", "ORDER BY 2"),
    ("case", "ORDER BY CASE WHEN n > 20 THEN 0 ELSE 1 END"),
    ("nulls_implicit", "ORDER BY c"),
    ("limit", "ORDER BY n LIMIT 2"),
    ("limit_offset", "ORDER BY n LIMIT 2 OFFSET 1"),
    ("desc_limit", "ORDER BY n DESC LIMIT 1"),
    ("multi_dir", "ORDER BY g DESC, n ASC, id DESC"),
]


def _order_variant(label, clause):
    def run():
        with _table("id INT PRIMARY KEY, g INT, n INT, c VARCHAR(10)") as t:
            _exec(f"INSERT INTO `{t}` VALUES "
                  f"(1,1,30,'a'),(2,1,10,NULL),(3,2,20,'b'),(4,2,40,'c')")
            _fetchall(f"SELECT id, n FROM `{t}` {clause}")
        return f"order {label}"
    return run


# --- JOIN on-condition matrix. ---------------------------------------------
JOIN_CONDITIONS = [
    ("equi", "a.k = b.k"),
    ("multi_col", "a.k = b.k AND a.g = b.g"),
    ("range", "a.k >= b.k"),
    ("expr", "a.k = b.k + 0"),
    ("function", "ABS(a.k) = ABS(b.k)"),
    ("or_cond", "a.k = b.k OR a.g = b.g"),
    ("inequality", "a.k <> b.k"),
    ("between", "a.k BETWEEN b.k - 1 AND b.k + 1"),
    ("using", None),  # special-cased -> JOIN USING (k)
    ("natural", None),  # special-cased -> NATURAL JOIN
]


def _join_condition(label, cond):
    def run():
        a = M.uname("jc_a")
        b = M.uname("jc_b")
        _exec(f"CREATE TABLE `{a}` (id INT PRIMARY KEY, k INT, g INT)")
        _exec(f"CREATE TABLE `{b}` (id INT PRIMARY KEY, k INT, g INT)")
        try:
            _exec(f"INSERT INTO `{a}` VALUES (1,5,1),(2,6,2)")
            _exec(f"INSERT INTO `{b}` VALUES (1,5,1),(2,6,3)")
            if label == "using":
                _fetchall(f"SELECT a.id FROM `{a}` a JOIN `{b}` b USING (k)")
            elif label == "natural":
                _fetchall(f"SELECT * FROM `{a}` a NATURAL JOIN `{b}` b")
            else:
                _fetchall(f"SELECT a.id FROM `{a}` a JOIN `{b}` b ON {cond}")
        finally:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{a}`")
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{b}`")
        return f"join-cond {label}"
    return run


# --- WHERE-clause complexity matrix. ---------------------------------------
WHERE_CLAUSES = [
    ("simple_eq", "n = 20"),
    ("and_chain", "n > 10 AND n < 40 AND g = 1"),
    ("or_chain", "n = 10 OR n = 30 OR n = 50"),
    ("mixed_and_or", "(n > 10 AND g = 1) OR (n < 50 AND g = 2)"),
    ("not_clause", "NOT (n = 20)"),
    ("in_list", "n IN (10, 20, 30)"),
    ("not_in_list", "n NOT IN (99, 88)"),
    ("between", "n BETWEEN 15 AND 35"),
    ("like", "s LIKE 'a%'"),
    ("is_null", "s IS NULL"),
    ("is_not_null", "s IS NOT NULL"),
    ("func_compare", "ABS(n) > 15"),
    ("expr_compare", "n * 2 > 30"),
    ("nested_paren", "((n > 10) AND ((g = 1) OR (g = 2)))"),
    ("subquery_in", "n IN (SELECT n FROM `{t}` WHERE g = 1)"),
    ("exists", "EXISTS (SELECT 1 FROM `{t}` x WHERE x.n = `{t}`.n)"),
    ("case_in_where", "CASE WHEN g = 1 THEN n ELSE 0 END > 5"),
    ("coalesce_where", "COALESCE(s, 'x') = 'x'"),
    ("multiple_funcs", "LENGTH(s) > 0 AND UPPER(s) <> ''"),
    ("arithmetic_both", "n + g > 11"),
]


def _where_clause(label, clause):
    def run():
        with _table("id INT PRIMARY KEY, g INT, n INT, s VARCHAR(20)") as t:
            _exec(f"INSERT INTO `{t}` VALUES "
                  f"(1,1,10,'alpha'),(2,1,20,'beta'),(3,2,30,'gamma'),"
                  f"(4,2,40,NULL)")
            _fetchall(f"SELECT id FROM `{t}` WHERE " + clause.format(t=t))
        return f"where {label}"
    return run


# --- COUNT / LIMIT / pagination matrix. ------------------------------------
PAGINATION_CASES = [
    ("limit_1", "LIMIT 1"), ("limit_10", "LIMIT 10"),
    ("limit_0", "LIMIT 0"), ("limit_offset", "LIMIT 5 OFFSET 10"),
    ("limit_comma", "LIMIT 10, 5"), ("limit_large", "LIMIT 1000000"),
    ("offset_beyond", "LIMIT 5 OFFSET 1000"),
]


def _pagination(label, clause):
    def run():
        with _table("id INT PRIMARY KEY, n INT") as t:
            _exec(f"INSERT INTO `{t}` VALUES " +
                  ",".join(f"({i},{i})" for i in range(1, 51)))
            _fetchall(f"SELECT id FROM `{t}` ORDER BY id {clause}")
        return f"pagination {label}"
    return run


# --- Type roundtrip via alternate insert methods. --------------------------
def _type_insert_set(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` SET id=1, c={lit}")
            _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
        return f"insert-set {label}"
    return run


def _type_insert_select(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as src:
            _exec(f"INSERT INTO `{src}` VALUES (1,{lit})")
            with _table(f"id INT PRIMARY KEY, c {sqltype}") as dst:
                _exec(f"INSERT INTO `{dst}` SELECT * FROM `{src}`")
                _fetchone(f"SELECT c FROM `{dst}` WHERE id=1")
        return f"insert-select {label}"
    return run


def _type_replace(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit})")
            _exec(f"REPLACE INTO `{t}` VALUES (1,{lit})")
        return f"replace {label}"
    return run


# --- Multi-function-in-select projection matrix. ---------------------------
PROJECTION_SETS = [
    ("str_projection",
     "id, UPPER(s), LENGTH(s), CONCAT(s,'!'), SUBSTRING(s,1,2)"),
    ("math_projection",
     "id, ABS(n), ROUND(n,1), CEIL(n), FLOOR(n), MOD(n,2)"),
    ("mixed_projection",
     "id, CONCAT(s, n), n + LENGTH(s), IF(n>0,'p','n')"),
    ("aliased_projection",
     "id AS row_id, s AS label, n * 2 AS doubled"),
    ("case_projection",
     "id, CASE WHEN n > 5 THEN 'hi' ELSE 'lo' END AS bucket"),
    ("nested_func_projection",
     "id, UPPER(SUBSTRING(s, 1, 3)), ROUND(SQRT(ABS(n)), 2)"),
    ("coalesce_projection",
     "id, COALESCE(s, 'none'), IFNULL(n, 0)"),
    ("computed_projection",
     "id, n * n AS sq, n + 1 AS inc, n - 1 AS dec"),
    ("literal_projection",
     "id, 'constant' AS lit, 42 AS num, NULL AS nothing"),
    ("expr_alias_reuse",
     "id, n + 1 AS a, (n + 1) * 2 AS b"),
]


def _projection(label, proj):
    def run():
        with _table("id INT PRIMARY KEY, n INT, s VARCHAR(20)") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,10,'alpha'),(2,20,'beta')")
            _fetchall(f"SELECT {proj} FROM `{t}`")
        return f"projection {label}"
    return run


# --- JSON operation matrix on a stored JSON column. ------------------------
JSON_OPS = [
    ("extract_arrow", "c -> '$.a'"),
    ("extract_unquote_arrow", "c ->> '$.a'"),
    ("json_extract", "JSON_EXTRACT(c, '$.a')"),
    ("json_extract_nested", "JSON_EXTRACT(c, '$.nested.x')"),
    ("json_extract_array", "JSON_EXTRACT(c, '$.arr[0]')"),
    ("json_unquote", "JSON_UNQUOTE(JSON_EXTRACT(c, '$.s'))"),
    ("json_keys", "JSON_KEYS(c)"),
    ("json_length", "JSON_LENGTH(c)"),
    ("json_type", "JSON_TYPE(c)"),
    ("json_valid", "JSON_VALID(c)"),
    ("json_depth", "JSON_DEPTH(c)"),
    ("json_contains", "JSON_CONTAINS(c, '1', '$.a')"),
    ("json_contains_path", "JSON_CONTAINS_PATH(c, 'one', '$.a')"),
    ("json_set", "JSON_SET(c, '$.a', 99)"),
    ("json_insert", "JSON_INSERT(c, '$.new', 1)"),
    ("json_replace", "JSON_REPLACE(c, '$.a', 5)"),
    ("json_remove", "JSON_REMOVE(c, '$.a')"),
    ("json_merge_patch", "JSON_MERGE_PATCH(c, '{\"z\":9}')"),
    ("json_array_append", "JSON_ARRAY_APPEND(c, '$.arr', 99)"),
    ("json_search", "JSON_SEARCH(c, 'one', 'hello')"),
    ("json_pretty", "JSON_PRETTY(c)"),
    ("json_quote", "JSON_QUOTE('value')"),
    ("json_overlaps", "JSON_OVERLAPS(c, '{\"a\":1}')"),
]


def _json_op(label, expr):
    def run():
        with _table("id INT PRIMARY KEY, c JSON") as t:
            _exec(
                f"INSERT INTO `{t}` VALUES "
                f"""(1, '{{"a":1,"s":"hello","arr":[1,2,3],"nested":{{"x":5}}}}')""")
            _fetchall(f"SELECT {expr} FROM `{t}` WHERE id=1")
        return f"json {label}"
    return run


# --- Transaction-pattern matrix. -------------------------------------------
def _tx_pattern_scenarios():
    out = []

    def commit_visibility():
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("txp")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, n INT)")
            conn.commit()
            cur.execute(f"INSERT INTO `{t}` VALUES (1,1),(2,2)")
            conn.commit()
            cur.execute(f"UPDATE `{t}` SET n=99 WHERE id=1")
            cur.execute(f"SELECT n FROM `{t}` WHERE id=1")
            v = cur.fetchone()[0]
            conn.commit()
            cur.execute(f"DROP TABLE IF EXISTS `{t}`")
            conn.commit()
            if v != 99:
                raise BehaviorMismatch(f"in-tx visibility {v}")
        finally:
            conn.close()
        return "commit visibility"
    out.append(("commit_visibility", commit_visibility))

    def rollback_dml():
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("txp")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, n INT)")
            conn.commit()
            cur.execute(f"INSERT INTO `{t}` VALUES (1,1)")
            conn.commit()
            cur.execute(f"UPDATE `{t}` SET n=99 WHERE id=1")
            cur.execute(f"DELETE FROM `{t}` WHERE id=1")
            conn.rollback()
            cur.execute(f"SELECT n FROM `{t}` WHERE id=1")
            row = cur.fetchone()
            n = row[0] if row else None
            cur.execute(f"DROP TABLE IF EXISTS `{t}`")
            conn.commit()
            if n != 1:
                raise BehaviorMismatch(f"rollback DML -> {n}")
        finally:
            conn.close()
        return "rollback dml"
    out.append(("rollback_dml", rollback_dml))

    def multi_table_tx():
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            a = M.uname("txp_a")
            b = M.uname("txp_b")
            cur.execute(f"CREATE TABLE `{a}` (id INT PRIMARY KEY)")
            cur.execute(f"CREATE TABLE `{b}` (id INT PRIMARY KEY)")
            conn.commit()
            cur.execute(f"INSERT INTO `{a}` VALUES (1)")
            cur.execute(f"INSERT INTO `{b}` VALUES (1)")
            conn.commit()
            cur.execute(f"DROP TABLE IF EXISTS `{a}`")
            cur.execute(f"DROP TABLE IF EXISTS `{b}`")
            conn.commit()
        finally:
            conn.close()
        return "multi-table tx"
    out.append(("multi_table_tx", multi_table_tx))

    def ddl_implicit_commit():
        # DDL implicitly commits in MySQL; verify behaviour
        conn = raw_connect(config.DB_RAW)
        conn.autocommit(False)
        try:
            cur = conn.cursor()
            t = M.uname("txp")
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY)")
            cur.execute(f"INSERT INTO `{t}` VALUES (1)")
            cur.execute(f"CREATE TABLE `{t}_2` (id INT PRIMARY KEY)")  # implicit commit
            conn.rollback()
            cur.execute(f"DROP TABLE IF EXISTS `{t}`")
            cur.execute(f"DROP TABLE IF EXISTS `{t}_2`")
            conn.commit()
        finally:
            conn.close()
        return "ddl implicit commit"
    out.append(("ddl_implicit_commit", ddl_implicit_commit))

    return out


# --- Comparison literal-pair matrix (value semantics). ---------------------
COMPARE_PAIRS = [
    ("int_eq", "SELECT 5 = 5"),
    ("int_lt", "SELECT 3 < 5"),
    ("float_eq", "SELECT 1.5 = 1.5"),
    ("str_eq_same", "SELECT 'abc' = 'abc'"),
    ("str_eq_case", "SELECT 'abc' = 'ABC'"),
    ("str_lt", "SELECT 'a' < 'b'"),
    ("null_eq", "SELECT NULL = NULL"),
    ("nullsafe", "SELECT NULL <=> NULL"),
    ("date_eq", "SELECT DATE '2026-06-23' = DATE '2026-06-23'"),
    ("date_lt", "SELECT DATE '2026-01-01' < DATE '2026-12-31'"),
    ("mixed_int_str", "SELECT 5 = '5'"),
    ("mixed_int_float", "SELECT 5 = 5.0"),
    ("bool_compare", "SELECT TRUE = 1"),
    ("in_compare", "SELECT 3 IN (1,2,3)"),
    ("between_compare", "SELECT 5 BETWEEN 1 AND 10"),
    ("greatest_eq", "SELECT GREATEST(1,2,3) = 3"),
    ("string_num_coerce", "SELECT '10' + 5"),
    ("decimal_eq", "SELECT 0.1 + 0.2 = 0.3"),
    ("trailing_space", "SELECT 'a ' = 'a'"),
    ("collate_pair", "SELECT 'a' = 'A' COLLATE utf8mb4_general_ci"),
]


def _compare_pair(label, sql):
    def run():
        _fetchone(sql)
        return f"compare-pair {label}"
    return run


# --- Subquery-over-typed-column matrix. ------------------------------------
def _subquery_type(label, sqltype, value, quoted):
    def run():
        lit = _quote(value, quoted)
        with _table(f"id INT PRIMARY KEY, c {sqltype}") as t:
            _exec(f"INSERT INTO `{t}` VALUES (1,{lit}),(2,{lit})")
            _fetchall(
                f"SELECT id FROM `{t}` WHERE c IN "
                f"(SELECT c FROM `{t}` WHERE id = 1)")
        return f"subquery-type {label}"
    return run


# ===========================================================================
# Registration entry point.
# ===========================================================================

def register(runner):
    # --- generated TYPE x OPERATION matrix ---
    for label, sqltype, value, quoted in M.TYPES:
        runner.add(FW, "type_declare", f"{label}:declare", _type_declare(label, sqltype))
        runner.add(FW, "type_roundtrip", f"{label}:insert_roundtrip",
                   _type_insert_roundtrip(label, sqltype, value, quoted))
        runner.add(FW, "type_null", f"{label}:null", _type_null(label, sqltype))
        runner.add(FW, "type_param", f"{label}:param_roundtrip",
                   _type_param_roundtrip(label, sqltype, value, quoted))
        runner.add(FW, "type_update", f"{label}:update",
                   _type_update(label, sqltype, value, quoted))

    # indexable subset gets where/order/group/index ops
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "type_where", f"{label}:where", _type_where(label, sqltype, value, quoted))
        runner.add(FW, "type_orderby", f"{label}:orderby", _type_orderby(label, sqltype, value, quoted))
        runner.add(FW, "type_groupby", f"{label}:groupby", _type_groupby(label, sqltype, value, quoted))
        runner.add(FW, "type_index", f"{label}:index", _type_index(label, sqltype))

    # numeric boundaries (min/max)
    for label, sqltype, mn, mx in M.NUMERIC_BOUNDS:
        runner.add(FW, "type_boundary", f"{label}:min", _type_boundary(label, sqltype, mn, "min"))
        runner.add(FW, "type_boundary", f"{label}:max", _type_boundary(label, sqltype, mx, "max"))

    # --- generated FUNCTION x USAGE matrix ---
    for label, expr, group in M.FUNCTIONS:
        runner.add(FW, "func_bare", f"{label}:bare", _function_bare(label, expr))
        runner.add(FW, "func_where", f"{label}:in_where", _function_in_where(label, expr))
        runner.add(FW, "func_select", f"{label}:in_select", _function_in_select_with_col(label, expr))

    # --- aggregates / window funcs ---
    for label, expr_tpl, _w in M.AGGREGATES:
        runner.add(FW, "aggregate", f"{label}", _aggregate(label, expr_tpl))
        if "{c}" in expr_tpl and "DISTINCT" not in expr_tpl and "*" not in expr_tpl:
            runner.add(FW, "aggregate_distinct", f"{label}:distinct",
                       _aggregate_distinct(label, expr_tpl))
    for label, expr_tpl in M.WINDOW_FUNCS:
        runner.add(FW, "window", f"{label}", _window(label, expr_tpl))

    # --- operator matrix (bare + in-where) ---
    for label, expr, _grp in OPERATORS:
        runner.add(FW, "operator", f"{label}:bare", _operator(label, expr))
        runner.add(FW, "operator", f"{label}:in_where", _operator_in_where(label, expr))

    # --- cast / convert target matrix ---
    for label, target, value in CAST_TARGETS:
        runner.add(FW, "cast", f"cast_{label}", _cast(label, target, value))
        runner.add(FW, "convert", f"convert_{label}", _convert(label, target, value))

    # --- collation / charset DDL matrix ---
    for label, clause in COLLATIONS:
        runner.add(FW, "collation", f"{label}:create", _collation_create(label, clause))
        runner.add(FW, "collation", f"{label}:case_test", _collation_case_test(label, clause))

    # --- function in order-by / group-by / having ---
    for label, expr in ORDERABLE_FUNCS:
        runner.add(FW, "func_orderby", f"{label}:orderby", _func_in_orderby(label, expr))
        runner.add(FW, "func_groupby", f"{label}:groupby", _func_in_groupby(label, expr))
        runner.add(FW, "func_having", f"{label}:having", _func_in_having(label, expr))

    # --- join style x key-type matrix ---
    for style in JOIN_STYLES:
        for keylabel, keytype, keyval in JOIN_KEY_TYPES:
            runner.add(FW, "join_matrix",
                       f"{style.replace(' ', '_').lower()}:{keylabel}",
                       _join_on_type(style, keylabel, keytype, keyval))

    # --- default-value DDL over types ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "default_value", f"{label}:default",
                   _default_value(label, sqltype, value, quoted))

    # --- auto-increment over integer types ---
    for sqltype in AUTO_INC_TYPES:
        runner.add(FW, "autoincrement", f"{sqltype.replace(' ', '_').lower()}",
                   _autoincrement(sqltype))

    # --- subquery position matrix ---
    for pos in ["select", "where", "from", "having", "in", "exists"]:
        runner.add(FW, "subquery_position", pos, _subquery_position(pos))

    # --- index variety matrix ---
    for label, extra, idxsql in INDEX_VARIETIES:
        runner.add(FW, "index_variety", label, _index_variety(label, extra, idxsql))

    # --- per-type extended operations ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "type_distinct", f"{label}:distinct",
                   _type_distinct(label, sqltype, value, quoted))
        runner.add(FW, "type_count", f"{label}:count",
                   _type_count(label, sqltype, value, quoted))
        runner.add(FW, "type_minmax", f"{label}:minmax",
                   _type_minmax(label, sqltype, value, quoted))
        runner.add(FW, "type_coalesce", f"{label}:coalesce",
                   _type_coalesce(label, sqltype, value, quoted))
        runner.add(FW, "type_in", f"{label}:in_filter",
                   _type_in_filter(label, sqltype, value, quoted))
        runner.add(FW, "type_case", f"{label}:case",
                   _type_case_when(label, sqltype, value, quoted))
        runner.add(FW, "type_prepared", f"{label}:prepared",
                   _type_prepared_roundtrip(label, sqltype, value, quoted))

    # --- DATE_FORMAT specifier matrix ---
    for spec in DATE_FORMAT_SPECS:
        runner.add(FW, "date_format_spec", spec, _date_format_spec(spec))

    # --- INTERVAL unit matrix ---
    for unit in INTERVAL_UNITS:
        runner.add(FW, "interval_unit", unit, _interval_unit(unit))

    # --- EXTRACT unit matrix ---
    for unit in EXTRACT_UNITS:
        runner.add(FW, "extract_unit", unit, _extract_unit(unit))

    # --- literal value matrix ---
    for label, lit in LITERALS:
        runner.add(FW, "literal", label, _literal(label, lit))

    # --- string-type specific operations ---
    for label, sqltype in STRING_TYPES:
        runner.add(FW, "string_concat", f"{label}:concat", _string_concat(label, sqltype))
        runner.add(FW, "string_like", f"{label}:like", _string_like(label, sqltype))
        runner.add(FW, "string_fns", f"{label}:fns", _string_length_fns(label, sqltype))

    # --- comparison operator x type matrix ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        for op in COMPARE_OPS:
            runner.add(FW, "compare_op", f"{label}:{op}",
                       _compare_op_on_type(label, sqltype, value, quoted, op))

    # --- NULL-propagation function matrix ---
    for label, expr in NULL_PROP_FUNCS:
        # functions with explicit non-NULL fallback should NOT be null
        expect_null = label not in ("coalesce", "ifnull", "greatest", "least")
        runner.add(FW, "null_propagation", label, _null_prop(label, expr, expect_null))

    # --- numeric precision matrix ---
    for label, sql, _x in PRECISION_CASES:
        runner.add(FW, "precision", label, _precision_case(sql))

    # --- enum / set behaviour ---
    for name, fn in _enum_scenarios_list():
        runner.add(FW, "enum_set", name, fn)

    # --- column-modifier DDL matrix ---
    for label, coldef in COLUMN_MODIFIERS:
        runner.add(FW, "column_modifier", label, _column_modifier(label, coldef))

    # --- ALTER TABLE operation matrix ---
    for label, sql in ALTER_OPS:
        runner.add(FW, "alter_table", label, _alter_op(label, sql))

    # --- INSERT-variant matrix ---
    for name, fn in _insert_variants_list():
        runner.add(FW, "insert_variant", name, fn)

    # --- bound-parameter type matrix ---
    for label, sqltype, value in BIND_VALUES:
        runner.add(FW, "bind_insert", f"{label}", _bind_insert_select(label, sqltype, value))
        runner.add(FW, "bind_where", f"{label}", _bind_where(label, sqltype, value))
        runner.add(FW, "bind_update", f"{label}", _bind_update(label, sqltype, value))

    # --- query-shape matrix over a richer dataset ---
    for label, sql in QUERY_SHAPES:
        runner.add(FW, "query_shape", label, _query_shape(label, sql))

    # --- function-over-stored-column matrix (select / where / orderby) ---
    for grp, exprs in (("str", STR_COL_FUNCS), ("math", MATH_COL_FUNCS),
                       ("date", DATE_COL_FUNCS)):
        for expr in exprs:
            lbl = _func_over_col_label(expr)
            runner.add(FW, f"func_col_{grp}_select", lbl, _func_over_col(grp, expr))
            runner.add(FW, f"func_col_{grp}_where", lbl, _func_over_col_where(grp, expr))
            runner.add(FW, f"func_col_{grp}_orderby", lbl, _func_over_col_orderby(grp, expr))

    # --- aggregate x window-clause matrix ---
    for aggfn in WINDOW_AGG_FUNCS:
        for clabel, clause in WINDOW_CLAUSES:
            agglbl = aggfn.replace("(", "_").replace(")", "").replace("*", "star").lower()
            runner.add(FW, "window_clause", f"{agglbl}:{clabel}",
                       _window_clause(aggfn, clabel, clause))

    # --- LIKE / pattern-matching matrix ---
    for label, pattern in LIKE_PATTERNS:
        runner.add(FW, "like_pattern", label, _like_pattern(label, pattern))

    # --- GROUP BY variants matrix ---
    for label, sql in GROUP_BY_VARIANTS:
        runner.add(FW, "group_by_variant", label, _group_by_variant(label, sql))

    # --- CAST-from-stored-column matrix ---
    for label, sqltype, value, cast_expr in CAST_FROM_COL:
        runner.add(FW, "cast_from_col", label,
                   _cast_from_col(label, sqltype, value, cast_expr))

    # --- nesting-depth matrix ---
    for name, fn in _nesting_scenarios():
        runner.add(FW, "nesting", name, fn)

    # --- SET-operation matrix ---
    for label, sql in SET_OPS:
        runner.add(FW, "set_operation", label, _set_op(label, sql))

    # --- expression-evaluation matrix ---
    for label, sql in EXPRESSIONS:
        runner.add(FW, "expression", label, _expression(label, sql))

    # --- DML-operation x type matrix ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "dml_insert_type", label, _dml_insert_type(label, sqltype, value, quoted))
        runner.add(FW, "dml_update_type", label, _dml_update_type(label, sqltype, value, quoted))
        runner.add(FW, "dml_delete_type", label, _dml_delete_type(label, sqltype, value, quoted))

    # --- ORDER BY variants matrix ---
    for label, clause in ORDER_VARIANTS:
        runner.add(FW, "order_variant", label, _order_variant(label, clause))

    # --- JOIN on-condition matrix ---
    for label, cond in JOIN_CONDITIONS:
        runner.add(FW, "join_condition", label, _join_condition(label, cond))

    # --- WHERE-clause complexity matrix ---
    for label, clause in WHERE_CLAUSES:
        runner.add(FW, "where_clause", label, _where_clause(label, clause))

    # --- pagination matrix ---
    for label, clause in PAGINATION_CASES:
        runner.add(FW, "pagination", label, _pagination(label, clause))

    # --- type roundtrip via alternate insert methods ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "type_insert_set", label, _type_insert_set(label, sqltype, value, quoted))
        runner.add(FW, "type_insert_select", label, _type_insert_select(label, sqltype, value, quoted))
        runner.add(FW, "type_replace", label, _type_replace(label, sqltype, value, quoted))

    # --- multi-function projection matrix ---
    for label, proj in PROJECTION_SETS:
        runner.add(FW, "projection", label, _projection(label, proj))

    # --- JSON operation matrix ---
    for label, expr in JSON_OPS:
        runner.add(FW, "json_op", label, _json_op(label, expr))

    # --- transaction-pattern matrix ---
    for name, fn in _tx_pattern_scenarios():
        runner.add(FW, "tx_pattern", name, fn)

    # --- comparison literal-pair matrix ---
    for label, sql in COMPARE_PAIRS:
        runner.add(FW, "compare_pair", label, _compare_pair(label, sql))

    # --- subquery-over-typed-column matrix ---
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "subquery_type", f"{label}:in", _subquery_type(label, sqltype, value, quoted))

    # --- function-over-column GROUP BY matrix (str & math) ---
    for expr in STR_COL_FUNCS:
        runner.add(FW, "func_col_str_groupby", _func_over_col_label(expr),
                   _func_over_col_groupby("str", expr))
    for expr in MATH_COL_FUNCS:
        runner.add(FW, "func_col_math_groupby", _func_over_col_label(expr),
                   _func_over_col_groupby("math", expr))
    for expr in DATE_COL_FUNCS:
        runner.add(FW, "func_col_date_groupby", _func_over_col_label(expr),
                   _func_over_col_groupby("date", expr))

    # --- hand-written DDL / DML / query / tx / prepared / semantic / show ---
    for cat, name, fn in (
        _ddl_scenarios() + _dml_scenarios() + _query_scenarios()
        + _transaction_scenarios() + _prepared_scenarios()
        + _semantic_scenarios() + _show_scenarios()
    ):
        runner.add(FW, cat, name, fn)
