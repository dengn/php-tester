"""Peewee ORM compatibility scenarios against MatrixOne.

Peewee is a lightweight ORM widely used for small/medium MySQL apps. This module
exercises its field types, CRUD, query DSL, relationships, transactions
(including savepoints — expect failure), and bulk operations against mo_py_pw.

Models are defined dynamically per scenario with unique table names; tables are
created and dropped by the harness.
"""

from __future__ import annotations

import contextlib
import datetime
import decimal
import itertools

import peewee
from peewee import (
    AutoField, BigIntegerField, BlobField, BooleanField, CharField, DateField,
    DateTimeField, DecimalField, DoubleField, FloatField, ForeignKeyField,
    IntegerField, Model, MySQLDatabase, SmallIntegerField, TextField, TimeField,
    UUIDField, fn,
)

from harness import BehaviorMismatch, config
from harness import matrices as M

FW = "peewee"

_db = None
_counter = itertools.count(1)


def _database():
    global _db
    if _db is None:
        _db = MySQLDatabase(
            config.DB_PW,
            host=config.host(),
            port=config.port(),
            user=config.user(),
            password=config.password(),
            charset="utf8mb4",
            autoconnect=True,
        )
    return _db


def _suffix():
    return f"{next(_counter):06d}"


def _make_model(fields: dict, table_prefix="pw"):
    table = f"{table_prefix}_{_suffix()}"
    Meta = type("Meta", (), {"database": _database(), "table_name": table})
    attrs = {"Meta": Meta}
    attrs.update(fields)
    model = type(f"PwM{_suffix()}", (Model,), attrs)
    return model, table


@contextlib.contextmanager
def _tables(*models):
    db = _database()
    db.create_tables(list(models), safe=True)
    try:
        yield
    finally:
        with contextlib.suppress(Exception):
            db.drop_tables(list(reversed(models)), safe=True)


# ===========================================================================
# 1. Field-type matrix.
# ===========================================================================

PW_FIELDS = [
    ("IntegerField", lambda: IntegerField(null=True), 42),
    ("BigIntegerField", lambda: BigIntegerField(null=True), 9000000000000000000),
    ("SmallIntegerField", lambda: SmallIntegerField(null=True), 1234),
    ("FloatField", lambda: FloatField(null=True), 3.5),
    ("DoubleField", lambda: DoubleField(null=True), 2.718281828459045),
    ("DecimalField", lambda: DecimalField(max_digits=10, decimal_places=2, null=True),
     decimal.Decimal("12345.67")),
    ("CharField", lambda: CharField(max_length=50, null=True), "hello"),
    ("TextField", lambda: TextField(null=True), "long text"),
    ("BooleanField", lambda: BooleanField(null=True), True),
    ("DateField", lambda: DateField(null=True), datetime.date(2026, 6, 23)),
    ("TimeField", lambda: TimeField(null=True), datetime.time(11, 22, 33)),
    ("DateTimeField", lambda: DateTimeField(null=True),
     datetime.datetime(2026, 6, 23, 11, 22, 33)),
    ("BlobField", lambda: BlobField(null=True), b"binary data"),
    ("UUIDField", lambda: UUIDField(null=True),
     __import__("uuid").UUID("00000000-0000-0000-0000-000000000001")),
]


def _field_create(label, factory):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            pass
        return f"create {label}"
    return run


def _field_roundtrip(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            obj = model.create(val=value)
            got = model.get(model.id == obj.id)
            if got is None:
                raise BehaviorMismatch(f"{label} roundtrip lost row")
        return f"roundtrip {label}"
    return run


def _field_null(label, factory):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            obj = model.create(val=None)
            got = model.get(model.id == obj.id)
            if got.val is not None:
                raise BehaviorMismatch(f"{label} NULL -> {got.val!r}")
        return f"null {label}"
    return run


# ===========================================================================
# 2. CRUD + query DSL.
# ===========================================================================

def _seed_model():
    model, _t = _make_model({
        "id": AutoField(),
        "g": IntegerField(),
        "n": IntegerField(),
        "s": CharField(max_length=50),
    })
    return model


def _query_scenarios():
    items = []

    def add(name, fn_):
        items.append(("query", name, fn_))

    def populate(m):
        m.insert_many([
            {"g": 1, "n": 10, "s": "alpha"},
            {"g": 1, "n": 20, "s": "beta"},
            {"g": 2, "n": 30, "s": "gamma"},
            {"g": 2, "n": 40, "s": "delta"},
        ]).execute()

    def crud():
        m = _seed_model()
        with _tables(m):
            obj = m.create(g=1, n=10, s="x")
            obj.n = 99
            obj.save()
            got = m.get(m.id == obj.id)
            if got.n != 99:
                raise BehaviorMismatch("update failed")
            got.delete_instance()
            if m.select().count() != 0:
                raise BehaviorMismatch("delete failed")
    add("crud", crud)

    def where_filter():
        m = _seed_model()
        with _tables(m):
            populate(m)
            n = m.select().where(m.n > 15).count()
            if n != 3:
                raise BehaviorMismatch(f"where -> {n}")
    add("where_filter", where_filter)

    def order_by():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select().order_by(m.n.desc()))
    add("order_by", order_by)

    def group_by_having():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select(m.g, fn.SUM(m.n).alias("t"))
                 .group_by(m.g).having(fn.SUM(m.n) > 25))
    add("group_by_having", group_by_having)

    def aggregate():
        m = _seed_model()
        with _tables(m):
            populate(m)
            total = m.select(fn.SUM(m.n)).scalar()
            if total != 100:
                raise BehaviorMismatch(f"sum -> {total}")
    add("aggregate_sum", aggregate)

    def distinct():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select(m.g).distinct())
    add("distinct", distinct)

    def limit_offset():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select().order_by(m.n).limit(2).offset(1))
    add("limit_offset", limit_offset)

    def subquery():
        m = _seed_model()
        with _tables(m):
            populate(m)
            sub = m.select(fn.MAX(m.n))
            list(m.select().where(m.n == sub))
    add("subquery", subquery)

    def in_query():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select().where(m.n.in_([10, 30])))
    add("in_filter", in_query)

    def like_query():
        m = _seed_model()
        with _tables(m):
            populate(m)
            list(m.select().where(m.s ** "a%"))
    add("like_filter", like_query)

    def update_expr():
        m = _seed_model()
        with _tables(m):
            populate(m)
            m.update(n=m.n + 100).execute()
            if m.select().where(m.n >= 110).count() != 4:
                raise BehaviorMismatch("update expr failed")
    add("update_expression", update_expr)

    def case_expr():
        m = _seed_model()
        with _tables(m):
            populate(m)
            from peewee import Case
            list(m.select(m.id, Case(None, [(m.n < 25, "lo")], "hi")))
    add("case_expression", case_expr)

    return items


# ===========================================================================
# 3. Relationships.
# ===========================================================================

def _rel_scenarios():
    items = []

    def add(name, fn_):
        items.append(("relationship", name, fn_))

    def foreign_key():
        parent, _pt = _make_model({"id": AutoField(),
                                   "name": CharField(max_length=30)},
                                  table_prefix="pwp")
        child, _ct = _make_model({"id": AutoField(),
                                  "parent": ForeignKeyField(parent, backref="kids"),
                                  "label": CharField(max_length=30)},
                                 table_prefix="pwc")
        with _tables(parent, child):
            p = parent.create(name="root")
            child.create(parent=p, label="a")
            child.create(parent=p, label="b")
            if p.kids.count() != 2:
                raise BehaviorMismatch(f"FK backref -> {p.kids.count()}")
    add("foreign_key_backref", foreign_key)

    def join_query():
        parent, _pt = _make_model({"id": AutoField(),
                                   "name": CharField(max_length=30)},
                                  table_prefix="pwp")
        child, _ct = _make_model({"id": AutoField(),
                                  "parent": ForeignKeyField(parent),
                                  "label": CharField(max_length=30)},
                                 table_prefix="pwc")
        with _tables(parent, child):
            p = parent.create(name="root")
            child.create(parent=p, label="a")
            rows = list(child.select(child, parent).join(parent))
            if len(rows) != 1:
                raise BehaviorMismatch(f"join -> {len(rows)}")
    add("join_query", join_query)

    def self_referential():
        node, _t = _make_model({"id": AutoField(),
                                "name": CharField(max_length=30),
                                "parent": ForeignKeyField("self", null=True,
                                                          backref="children")},
                               table_prefix="pwnode")
        with _tables(node):
            root = node.create(name="root")
            node.create(name="c1", parent=root)
            node.create(name="c2", parent=root)
            if root.children.count() != 2:
                raise BehaviorMismatch("self-ref children mismatch")
    add("self_referential", self_referential)

    return items


# ===========================================================================
# 4. Transactions.
# ===========================================================================

def _tx_scenarios():
    items = []

    def add(name, fn_):
        items.append(("transaction", name, fn_))

    def commit():
        m = _seed_model()
        with _tables(m):
            with _database().atomic():
                m.create(g=1, n=1, s="x")
            if m.select().count() != 1:
                raise BehaviorMismatch("commit lost row")
    add("atomic_commit", commit)

    def rollback():
        m = _seed_model()
        with _tables(m):
            try:
                with _database().atomic():
                    m.create(g=1, n=1, s="x")
                    raise RuntimeError("rollback")
            except RuntimeError:
                pass
            if m.select().count() != 0:
                raise BehaviorMismatch("rollback left rows")
    add("atomic_rollback", rollback)

    def nested_savepoint():
        # KNOWN C5: nested atomic() -> savepoint; rollback -> ROLLBACK TO SAVEPOINT
        m = _seed_model()
        with _tables(m):
            try:
                with _database().atomic():
                    m.create(g=1, n=1, s="outer")
                    with contextlib.suppress(RuntimeError):
                        with _database().atomic():  # savepoint
                            m.create(g=2, n=2, s="inner")
                            raise RuntimeError("rollback inner")
            except Exception:
                pass
            cnt = m.select().count()
            if cnt != 1:
                raise BehaviorMismatch(
                    f"nested savepoint diverges: {cnt} rows (MySQL keeps 1)")
    add("nested_savepoint", nested_savepoint)

    return items


# ===========================================================================
# 5. Bulk + workload.
# ===========================================================================

def _bulk_scenarios():
    items = []

    def add(name, fn_):
        items.append(("bulk", name, fn_))

    def insert_many(size):
        def run():
            m = _seed_model()
            with _tables(m):
                m.insert_many(
                    [{"g": 1, "n": i, "s": f"r{i}"} for i in range(size)]).execute()
                if m.select().count() != size:
                    raise BehaviorMismatch(f"insert_many {size} mismatch")
        return run
    for sz in (1, 10, 100, 1000):
        add(f"insert_many_{sz}", insert_many(sz))

    def bulk_update():
        m = _seed_model()
        with _tables(m):
            m.insert_many([{"g": 1, "n": i, "s": "x"} for i in range(50)]).execute()
            m.update(n=m.n * 10).where(m.n > 25).execute()
    add("bulk_update", bulk_update)

    def fetch_10k():
        m = _seed_model()
        with _tables(m):
            for start in range(0, 10000, 1000):
                m.insert_many(
                    [{"g": 1, "n": i, "s": "x"} for i in range(start, start + 1000)]
                ).execute()
            rows = list(m.select())
            if len(rows) != 10000:
                raise BehaviorMismatch(f"fetch 10k -> {len(rows)}")
    add("fetch_10k_rows", fetch_10k)

    def long_text():
        m, _t = _make_model({"id": AutoField(), "body": TextField()})
        with _tables(m):
            big = "x" * 100000
            obj = m.create(body=big)
            got = m.get(m.id == obj.id)
            if len(got.body) != 100000:
                raise BehaviorMismatch(f"long text len {len(got.body)}")
    add("long_text_100k", long_text)

    return items


# ===========================================================================
# Generated field x operation matrix.
# ===========================================================================

def _field_where(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            model.create(val=value)
            list(model.select().where(model.val == value))
        return f"where {label}"
    return run


def _field_orderby(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            model.create(val=value)
            list(model.select().order_by(model.val))
            list(model.select().order_by(model.val.desc()))
        return f"orderby {label}"
    return run


def _field_groupby(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            model.create(val=value)
            list(model.select(model.val, fn.COUNT(model.id)).group_by(model.val))
        return f"groupby {label}"
    return run


def _field_aggregate(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            model.create(val=value)
            model.select(fn.COUNT(model.val), fn.MAX(model.val),
                         fn.MIN(model.val)).scalar(as_tuple=True)
        return f"aggregate {label}"
    return run


def _field_index(label, factory, value):
    def run():
        f = factory()
        f.index = True
        model, _t = _make_model({"id": AutoField(), "val": f})
        with _tables(model):
            model.create(val=value)
        return f"index {label}"
    return run


def _field_update(label, factory, value):
    def run():
        model, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(model):
            obj = model.create(val=value)
            model.update(val=value).where(model.id == obj.id).execute()
        return f"update {label}"
    return run


# --- Peewee fn.* function matrix (applied to a stored column). -------------
PW_STR_FUNCS = [
    ("UPPER", lambda c: fn.UPPER(c)),
    ("LOWER", lambda c: fn.LOWER(c)),
    ("LENGTH", lambda c: fn.LENGTH(c)),
    ("CHAR_LENGTH", lambda c: fn.CHAR_LENGTH(c)),
    ("TRIM", lambda c: fn.TRIM(c)),
    ("LTRIM", lambda c: fn.LTRIM(c)),
    ("RTRIM", lambda c: fn.RTRIM(c)),
    ("REVERSE", lambda c: fn.REVERSE(c)),
    ("CONCAT", lambda c: fn.CONCAT(c, "!")),
    ("SUBSTRING", lambda c: fn.SUBSTRING(c, 1, 3)),
    ("REPLACE", lambda c: fn.REPLACE(c, "a", "X")),
    ("MD5", lambda c: fn.MD5(c)),
    ("SHA1", lambda c: fn.SHA1(c)),
    ("HEX", lambda c: fn.HEX(c)),
    ("LEFT", lambda c: fn.LEFT(c, 2)),
    ("RIGHT", lambda c: fn.RIGHT(c, 2)),
    ("LOCATE", lambda c: fn.LOCATE("b", c)),
    ("LPAD", lambda c: fn.LPAD(c, 8, "*")),
]
PW_NUM_FUNCS = [
    ("ABS", lambda c: fn.ABS(c)),
    ("CEIL", lambda c: fn.CEIL(c)),
    ("FLOOR", lambda c: fn.FLOOR(c)),
    ("ROUND", lambda c: fn.ROUND(c)),
    ("SQRT", lambda c: fn.SQRT(c)),
    ("POWER", lambda c: fn.POWER(c, 2)),
    ("MOD", lambda c: fn.MOD(c, 3)),
    ("SIGN", lambda c: fn.SIGN(c)),
    ("GREATEST", lambda c: fn.GREATEST(c, 5)),
    ("LEAST", lambda c: fn.LEAST(c, 5)),
]


def _pw_str_func(fname, build):
    def run():
        m, _t = _make_model({"id": AutoField(), "s": CharField(max_length=50)})
        with _tables(m):
            m.create(s="hello")
            list(m.select(build(m.s).alias("r")))
        return f"str {fname}"
    return run


def _pw_num_func(fname, build):
    def run():
        m, _t = _make_model({"id": AutoField(), "n": IntegerField()})
        with _tables(m):
            m.create(n=10)
            list(m.select(build(m.n).alias("r")))
        return f"num {fname}"
    return run


# --- Peewee where-operator matrix on a stored column. ----------------------
PW_OPERATORS = [
    ("eq", lambda m: m.n == 10),
    ("ne", lambda m: m.n != 10),
    ("lt", lambda m: m.n < 10),
    ("le", lambda m: m.n <= 10),
    ("gt", lambda m: m.n > 10),
    ("ge", lambda m: m.n >= 10),
    ("in", lambda m: m.n.in_([10, 20])),
    ("not_in", lambda m: m.n.not_in([99])),
    ("between", lambda m: m.n.between(5, 15)),
    ("is_null", lambda m: m.n.is_null(True)),
    ("like", lambda m: m.s ** "a%"),
    ("ilike", lambda m: m.s ** "A%"),
    ("contains", lambda m: m.s.contains("b")),
    ("startswith", lambda m: m.s.startswith("a")),
    ("endswith", lambda m: m.s.endswith("c")),
    ("regexp", lambda m: m.s.regexp("^a")),
    ("and", lambda m: (m.n > 5) & (m.n < 50)),
    ("or", lambda m: (m.n < 5) | (m.n > 50)),
    ("add_expr", lambda m: (m.n + 5) > 0),
    ("mul_expr", lambda m: (m.n * 2) > 0),
]


def _pw_operator(label, build):
    def run():
        m, _t = _make_model({"id": AutoField(), "n": IntegerField(),
                             "s": CharField(max_length=50)})
        with _tables(m):
            m.create(n=10, s="abc")
            list(m.select().where(build(m)))
        return f"op {label}"
    return run


def _pw_field_count(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            m.create(val=value)
            m.create(val=value)
            m.select(fn.COUNT(m.val), fn.COUNT(fn.DISTINCT(m.val))).scalar(as_tuple=True)
        return f"count {label}"
    return run


def _pw_field_distinct(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            m.create(val=value)
            m.create(val=value)
            list(m.select(m.val).distinct())
        return f"distinct {label}"
    return run


def _pw_field_in(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            m.create(val=value)
            list(m.select().where(m.val.in_([value])))
        return f"in {label}"
    return run


def _pw_field_limit(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            m.create(val=value)
            list(m.select().order_by(m.val).limit(1))
        return f"limit {label}"
    return run


def _pw_field_save(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            obj = m.create(val=value)
            obj.val = value
            obj.save()
        return f"save {label}"
    return run


def _pw_field_delete(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            obj = m.create(val=value)
            obj.delete_instance()
            if m.select().count() != 0:
                raise BehaviorMismatch(f"{label} delete left rows")
        return f"delete {label}"
    return run


def _pw_field_insert_many(label, factory, value):
    def run():
        m, _t = _make_model({"id": AutoField(), "val": factory()})
        with _tables(m):
            m.insert_many([{"val": value} for _ in range(5)]).execute()
            if m.select().count() != 5:
                raise BehaviorMismatch(f"{label} insert_many count")
        return f"insert_many {label}"
    return run


# --- Peewee query-pattern matrix over dept/emp. ----------------------------
def _pw_query_patterns():
    out = []

    def make_models():
        dept, _dt = _make_model({"id": AutoField(),
                                 "name": CharField(max_length=30)},
                                table_prefix="pwdept")
        emp, _et = _make_model({"id": AutoField(),
                                "dept": ForeignKeyField(dept, backref="emps"),
                                "name": CharField(max_length=30),
                                "salary": IntegerField()},
                               table_prefix="pwemp")
        return dept, emp

    def seed(dept, emp):
        d1 = dept.create(name="Eng")
        d2 = dept.create(name="Sales")
        emp.create(dept=d1, name="Ann", salary=100)
        emp.create(dept=d1, name="Bob", salary=120)
        emp.create(dept=d2, name="Cy", salary=90)

    patterns = [
        ("join_count", lambda dept, emp:
            list(emp.select(emp, dept).join(dept))),
        ("group_avg", lambda dept, emp:
            list(emp.select(emp.dept, fn.AVG(emp.salary).alias("a"))
                 .group_by(emp.dept))),
        ("having", lambda dept, emp:
            list(emp.select(emp.dept, fn.SUM(emp.salary).alias("s"))
                 .group_by(emp.dept).having(fn.SUM(emp.salary) > 150))),
        ("order_limit", lambda dept, emp:
            list(emp.select().order_by(emp.salary.desc()).limit(2))),
        ("subquery", lambda dept, emp:
            list(emp.select().where(
                emp.salary == emp.select(fn.MAX(emp.salary)).scalar()))),
        ("distinct", lambda dept, emp:
            list(emp.select(emp.dept).distinct())),
        ("count_star", lambda dept, emp:
            emp.select().count()),
        ("aggregate_all", lambda dept, emp:
            list(emp.select(fn.COUNT(emp.id), fn.SUM(emp.salary),
                            fn.AVG(emp.salary), fn.MIN(emp.salary),
                            fn.MAX(emp.salary)))),
        ("filter_join", lambda dept, emp:
            list(emp.select().join(dept).where(dept.name == "Eng"))),
        ("backref", lambda dept, emp:
            [list(d.emps) for d in dept.select()]),
    ]

    for label, fn_ in patterns:
        def mk(fn_=fn_, label=label):
            def run():
                dept, emp = make_models()
                with _tables(dept, emp):
                    seed(dept, emp)
                    fn_(dept, emp)
                return f"pattern {label}"
            return run
        out.append((label, mk()))
    return out


# ===========================================================================
# Registration.
# ===========================================================================

def register(runner):
    for label, factory, value in PW_FIELDS:
        runner.add(FW, "field_create", f"{label}:create", _field_create(label, factory))
        runner.add(FW, "field_roundtrip", f"{label}:roundtrip",
                   _field_roundtrip(label, factory, value))
        runner.add(FW, "field_null", f"{label}:null", _field_null(label, factory))
        runner.add(FW, "field_where", f"{label}:where", _field_where(label, factory, value))
        runner.add(FW, "field_orderby", f"{label}:orderby", _field_orderby(label, factory, value))
        runner.add(FW, "field_groupby", f"{label}:groupby", _field_groupby(label, factory, value))
        runner.add(FW, "field_aggregate", f"{label}:aggregate", _field_aggregate(label, factory, value))
        runner.add(FW, "field_index", f"{label}:index", _field_index(label, factory, value))
        runner.add(FW, "field_update", f"{label}:update", _field_update(label, factory, value))

    # generated fn.* function matrix
    for fname, build in PW_STR_FUNCS:
        runner.add(FW, "str_function", fname, _pw_str_func(fname, build))
    for fname, build in PW_NUM_FUNCS:
        runner.add(FW, "num_function", fname, _pw_num_func(fname, build))

    # generated operator matrix
    for label, build in PW_OPERATORS:
        runner.add(FW, "operator", label, _pw_operator(label, build))

    # generated field x extra-operation matrix (count/distinct/in/limit/like)
    for label, factory, value in PW_FIELDS:
        runner.add(FW, "field_count", f"{label}:count", _pw_field_count(label, factory, value))
        runner.add(FW, "field_distinct", f"{label}:distinct", _pw_field_distinct(label, factory, value))
        runner.add(FW, "field_in", f"{label}:in", _pw_field_in(label, factory, value))
        runner.add(FW, "field_limit", f"{label}:limit", _pw_field_limit(label, factory, value))
        runner.add(FW, "field_save", f"{label}:save", _pw_field_save(label, factory, value))
        runner.add(FW, "field_delete", f"{label}:delete", _pw_field_delete(label, factory, value))
        runner.add(FW, "field_insert_many", f"{label}:insert_many",
                   _pw_field_insert_many(label, factory, value))

    # generated query-pattern matrix
    for label, fn_ in _pw_query_patterns():
        runner.add(FW, "query_pattern", label, fn_)

    for cat, name, fn_ in (
        _query_scenarios() + _rel_scenarios() + _tx_scenarios()
        + _bulk_scenarios()
    ):
        runner.add(FW, cat, name, fn_)
