"""SQLAlchemy 2.x (Core + ORM) compatibility scenarios against MatrixOne.

Covers:
  - Type-system mapping for every SQLAlchemy column type (DDL + roundtrip)
  - Table/MetaData DDL + create_all/drop_all
  - Core query construction (select/insert/update/delete, joins, subqueries,
    CTEs, window, set ops, group_by/having, aggregates)
  - ORM declarative models, session add/commit/flush
  - relationships: one-to-one, one-to-many, many-to-many, self-referential,
    backref; lazy vs eager (joinedload/selectinload/subqueryload); cascade
  - transactions incl. nested begin_nested() (SAVEPOINT — expect failure)
  - bulk_insert_mappings / bulk_save_objects / bulk update
  - server-side cursors / streaming (yield_per / stream_results)
  - reflection (Table autoload / Inspector — expect information_schema gaps)
  - ORM events, hybrid properties, association proxy
  - load / workload scenarios

Each scenario builds its own tables with unique names inside mo_py_sa and tears
them down; a shared Engine is reused for speed.
"""

from __future__ import annotations

import contextlib
import datetime
import decimal

import sqlalchemy as sa
from sqlalchemy import (
    CheckConstraint, Column, ForeignKey, Integer, MetaData, String, Table,
    Text, create_engine, func, select, text,
)
from sqlalchemy.orm import (
    DeclarativeBase, Session, aliased, declarative_base, joinedload,
    relationship, selectinload, subqueryload,
)

from harness import BehaviorMismatch, SkipScenario, config
from harness import matrices as M

FW = "sqlalchemy"

_engine = None


def _eng():
    global _engine
    if _engine is None:
        _engine = create_engine(
            config.sqlalchemy_url(config.DB_SA),
            pool_pre_ping=True, pool_recycle=280, future=True,
        )
    return _engine


@contextlib.contextmanager
def _conn():
    with _eng().connect() as c:
        yield c


@contextlib.contextmanager
def _session():
    with Session(_eng()) as s:
        yield s


def _drop(*tables):
    with _eng().begin() as c:
        for t in tables:
            with contextlib.suppress(Exception):
                c.execute(text(f"DROP TABLE IF EXISTS `{t}`"))


# ===========================================================================
# 1. TYPE-SYSTEM MAPPING — every SQLAlchemy column type, create + roundtrip.
# ===========================================================================

# (label, sqlalchemy type instance factory, sample python value)
SA_TYPES = [
    ("Integer", lambda: sa.Integer(), 42),
    ("SmallInteger", lambda: sa.SmallInteger(), 1234),
    ("BigInteger", lambda: sa.BigInteger(), 9000000000000000000),
    ("Boolean", lambda: sa.Boolean(), True),
    ("Float", lambda: sa.Float(), 3.5),
    ("Double", lambda: sa.Double(), 2.718281828459045),
    ("Numeric_10_2", lambda: sa.Numeric(10, 2), decimal.Decimal("12345.67")),
    ("Numeric_38_10", lambda: sa.Numeric(38, 10), decimal.Decimal("1234.5")),
    ("DECIMAL", lambda: sa.DECIMAL(12, 4), decimal.Decimal("9.1234")),
    ("String_64", lambda: sa.String(64), "hello world"),
    ("String_255", lambda: sa.String(255), "abc def"),
    ("Unicode_64", lambda: sa.Unicode(64), "café résumé"),
    ("Text", lambda: sa.Text(), "a longer text value"),
    ("UnicodeText", lambda: sa.UnicodeText(), "unicode text ok"),
    ("CHAR_10", lambda: sa.CHAR(10), "abcdef"),
    ("VARCHAR_50", lambda: sa.VARCHAR(50), "varchar value"),
    ("Date", lambda: sa.Date(), datetime.date(2026, 6, 23)),
    ("Time", lambda: sa.Time(), datetime.time(11, 22, 33)),
    ("DateTime", lambda: sa.DateTime(), datetime.datetime(2026, 6, 23, 11, 22, 33)),
    ("TIMESTAMP", lambda: sa.TIMESTAMP(), datetime.datetime(2026, 6, 23, 11, 22, 33)),
    ("LargeBinary", lambda: sa.LargeBinary(), b"binary data"),
    ("VARBINARY_32", lambda: sa.VARBINARY(32), b"vbin"),
    ("Enum", lambda: sa.Enum("a", "b", "c", name="myenum"), "b"),
    ("JSON", lambda: sa.JSON(), {"k": 1, "arr": [1, 2, 3]}),
    ("Interval", lambda: sa.Interval(), datetime.timedelta(days=1)),
    ("Uuid", lambda: sa.Uuid(as_uuid=True),
     __import__("uuid").UUID("00000000-0000-0000-0000-000000000001")),
]


def _sa_type_create(label, typ_factory):
    def run():
        t = M.uname("sa_typ")
        md = MetaData()
        Table(t, md, Column("id", Integer, primary_key=True),
              Column("c", typ_factory()))
        try:
            md.create_all(_eng())
        finally:
            _drop(t)
        return f"create {label}"
    return run


def _sa_type_roundtrip(label, typ_factory, value):
    def run():
        if value is None:
            raise SkipScenario("no sample value")
        t = M.uname("sa_typ")
        md = MetaData()
        tbl = Table(t, md, Column("id", Integer, primary_key=True),
                    Column("c", typ_factory()))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=1, c=value))
                row = c.execute(select(tbl.c.c).where(tbl.c.id == 1)).first()
                if row is None:
                    raise BehaviorMismatch(f"{label}: no row back")
        finally:
            _drop(t)
        return f"roundtrip {label} -> {row[0]!r}"
    return run


def _sa_type_null(label, typ_factory):
    def run():
        t = M.uname("sa_typ")
        md = MetaData()
        tbl = Table(t, md, Column("id", Integer, primary_key=True),
                    Column("c", typ_factory(), nullable=True))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=1, c=None))
                row = c.execute(select(tbl.c.c).where(tbl.c.id == 1)).first()
                if row[0] is not None:
                    raise BehaviorMismatch(f"{label} NULL came back {row[0]!r}")
        finally:
            _drop(t)
        return f"null {label}"
    return run


# ===========================================================================
# 2. CORE QUERY CONSTRUCTION.
# ===========================================================================

def _core_table(extra_cols=None):
    name = M.uname("sa_core")
    md = MetaData()
    cols = [Column("id", Integer, primary_key=True),
            Column("g", Integer),
            Column("n", Integer),
            Column("s", String(50))]
    if extra_cols:
        cols.extend(extra_cols)
    tbl = Table(name, md, *cols)
    md.create_all(_eng())
    with _eng().begin() as c:
        c.execute(tbl.insert(), [
            {"id": 1, "g": 1, "n": 10, "s": "alpha"},
            {"id": 2, "g": 1, "n": 20, "s": "beta"},
            {"id": 3, "g": 2, "n": 30, "s": "gamma"},
            {"id": 4, "g": 2, "n": 40, "s": "delta"},
        ])
    return tbl, name


def _core_scenarios():
    items = []

    def add(name, fn):
        items.append(("core_query", name, fn))

    def core_select():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                rows = c.execute(select(tbl).where(tbl.c.n > 15)).all()
                if len(rows) != 3:
                    raise BehaviorMismatch(f"select where -> {len(rows)} rows")
        finally:
            _drop(name)
    add("select_where", core_select)

    def core_insert():
        tbl, name = _core_table()
        try:
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=5, g=3, n=50, s="x"))
        finally:
            _drop(name)
    add("insert_values", core_insert)

    def core_update():
        tbl, name = _core_table()
        try:
            with _eng().begin() as c:
                c.execute(tbl.update().where(tbl.c.id == 1).values(n=99))
                v = c.execute(select(tbl.c.n).where(tbl.c.id == 1)).scalar()
                if v != 99:
                    raise BehaviorMismatch(f"update -> {v}")
        finally:
            _drop(name)
    add("update", core_update)

    def core_delete():
        tbl, name = _core_table()
        try:
            with _eng().begin() as c:
                c.execute(tbl.delete().where(tbl.c.id == 1))
        finally:
            _drop(name)
    add("delete", core_delete)

    def core_join():
        tbl, name = _core_table()
        a = aliased(tbl)
        try:
            with _conn() as c:
                c.execute(select(tbl.c.id).join(a, tbl.c.g == a.c.g)).all()
        finally:
            _drop(name)
    add("self_join_alias", core_join)

    def core_group_having():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(
                    select(tbl.c.g, func.sum(tbl.c.n))
                    .group_by(tbl.c.g).having(func.sum(tbl.c.n) > 25)
                ).all()
        finally:
            _drop(name)
    add("group_by_having", core_group_having)

    def core_aggregates():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(
                    func.count(), func.sum(tbl.c.n), func.avg(tbl.c.n),
                    func.min(tbl.c.n), func.max(tbl.c.n),
                )).first()
        finally:
            _drop(name)
    add("aggregates", core_aggregates)

    def core_scalar_subquery():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                sub = select(func.max(tbl.c.n)).scalar_subquery()
                c.execute(select(tbl.c.id, sub)).all()
        finally:
            _drop(name)
    add("scalar_subquery", core_scalar_subquery)

    def core_in_subquery():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                sub = select(tbl.c.n).where(tbl.c.n > 20)
                c.execute(select(tbl).where(tbl.c.n.in_(sub))).all()
        finally:
            _drop(name)
    add("in_subquery", core_in_subquery)

    def core_exists():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl).where(
                    select(tbl.c.id).where(tbl.c.g == 1).exists())).all()
        finally:
            _drop(name)
    add("exists_subquery", core_exists)

    def core_cte():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                cte = select(tbl.c.g, tbl.c.n).cte("c")
                c.execute(select(cte.c.g, func.sum(cte.c.n)).group_by(cte.c.g)).all()
        finally:
            _drop(name)
    add("cte", core_cte)

    def core_recursive_cte():
        with _conn() as c:
            base = select(sa.literal(1).label("n")).cte("seq", recursive=True)
            base = base.union_all(
                select((base.c.n + 1).label("n")).where(base.c.n < 5))
            c.execute(select(base.c.n)).all()
    add("recursive_cte", core_recursive_cte)

    def core_window():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(
                    tbl.c.id,
                    func.row_number().over(partition_by=tbl.c.g, order_by=tbl.c.n),
                )).all()
        finally:
            _drop(name)
    add("window_function", core_window)

    def core_union():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                q1 = select(tbl.c.id).where(tbl.c.g == 1)
                q2 = select(tbl.c.id).where(tbl.c.g == 2)
                c.execute(sa.union(q1, q2)).all()
        finally:
            _drop(name)
    add("union", core_union)

    def core_union_all():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                q1 = select(tbl.c.id)
                c.execute(sa.union_all(q1, q1)).all()
        finally:
            _drop(name)
    add("union_all", core_union_all)

    def core_intersect():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(sa.intersect(
                    select(tbl.c.id), select(tbl.c.id).where(tbl.c.g == 1))).all()
        finally:
            _drop(name)
    add("intersect", core_intersect)

    def core_except():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(sa.except_(
                    select(tbl.c.id), select(tbl.c.id).where(tbl.c.g == 1))).all()
        finally:
            _drop(name)
    add("except", core_except)

    def core_order_limit():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl).order_by(tbl.c.n.desc()).limit(2).offset(1)).all()
        finally:
            _drop(name)
    add("order_by_limit_offset", core_order_limit)

    def core_distinct():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl.c.g).distinct()).all()
        finally:
            _drop(name)
    add("distinct", core_distinct)

    def core_case():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl.c.id, sa.case(
                    (tbl.c.n < 25, "lo"), else_="hi"))).all()
        finally:
            _drop(name)
    add("case_expression", core_case)

    def core_executemany_insert():
        tbl, name = _core_table()
        try:
            with _eng().begin() as c:
                c.execute(tbl.insert(), [
                    {"id": 100 + i, "g": 9, "n": i, "s": f"r{i}"} for i in range(20)
                ])
        finally:
            _drop(name)
    add("executemany_insert", core_executemany_insert)

    def core_textual_sql():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(text(f"SELECT COUNT(*) FROM `{name}`")).first()
        finally:
            _drop(name)
    add("textual_sql", core_textual_sql)

    def core_like():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl).where(tbl.c.s.like("a%"))).all()
        finally:
            _drop(name)
    add("like_filter", core_like)

    def core_between():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(select(tbl).where(tbl.c.n.between(15, 35))).all()
        finally:
            _drop(name)
    add("between_filter", core_between)

    return items


# ===========================================================================
# 3. ORM declarative models, sessions, relationships.
# ===========================================================================

def _orm_basic_scenarios():
    items = []

    def add(name, fn):
        items.append(("orm", name, fn))

    def make_base():
        class Base(DeclarativeBase):
            pass
        return Base

    def orm_crud():
        Base = make_base()
        tname = M.uname("orm_u")

        class U(Base):
            __tablename__ = tname
            id = Column(Integer, primary_key=True, autoincrement=True)
            name = Column(String(50))
            age = Column(Integer)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                u = U(name="Alice", age=30)
                s.add(u)
                s.commit()
                got = s.get(U, u.id)
                if got is None or got.name != "Alice":
                    raise BehaviorMismatch("ORM crud get failed")
                got.age = 31
                s.commit()
                s.delete(got)
                s.commit()
        finally:
            _drop(tname)
    add("crud_declarative", orm_crud)

    def orm_flush_autoincrement():
        Base = make_base()
        tname = M.uname("orm_u")

        class U(Base):
            __tablename__ = tname
            id = Column(Integer, primary_key=True, autoincrement=True)
            name = Column(String(50))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                u = U(name="x")
                s.add(u)
                s.flush()
                if u.id is None:
                    raise BehaviorMismatch("flush did not populate id")
                s.commit()
        finally:
            _drop(tname)
    add("flush_autoincrement", orm_flush_autoincrement)

    def orm_query_execute():
        Base = make_base()
        tname = M.uname("orm_u")

        class U(Base):
            __tablename__ = tname
            id = Column(Integer, primary_key=True)
            name = Column(String(50))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add_all([U(id=i, name=f"n{i}") for i in range(1, 6)])
                s.commit()
                rows = s.execute(
                    select(U).where(U.id > 2).order_by(U.id)).scalars().all()
                if len(rows) != 3:
                    raise BehaviorMismatch(f"orm query -> {len(rows)}")
        finally:
            _drop(tname)
    add("session_execute_select", orm_query_execute)

    def orm_legacy_query():
        Base = make_base()
        tname = M.uname("orm_u")

        class U(Base):
            __tablename__ = tname
            id = Column(Integer, primary_key=True)
            name = Column(String(50))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add_all([U(id=i, name=f"n{i}") for i in range(1, 4)])
                s.commit()
                n = s.query(U).filter(U.id >= 2).count()
                if n != 2:
                    raise BehaviorMismatch(f"query.count -> {n}")
        finally:
            _drop(tname)
    add("legacy_query_api", orm_legacy_query)

    def orm_one_to_many():
        Base = make_base()
        pn = M.uname("orm_parent")
        cn = M.uname("orm_child")

        class P(Base):
            __tablename__ = pn
            id = Column(Integer, primary_key=True)
            children = relationship("C", back_populates="parent",
                                    cascade="all, delete-orphan")

        class C(Base):
            __tablename__ = cn
            id = Column(Integer, primary_key=True)
            pid = Column(Integer, ForeignKey(f"{pn}.id"))
            parent = relationship("P", back_populates="children")
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                p = P(id=1)
                p.children = [C(id=1), C(id=2)]
                s.add(p)
                s.commit()
                got = s.get(P, 1)
                if len(got.children) != 2:
                    raise BehaviorMismatch(f"one-to-many -> {len(got.children)}")
        finally:
            _drop(cn, pn)
    add("relationship_one_to_many", orm_one_to_many)

    def orm_cascade_delete():
        Base = make_base()
        pn = M.uname("orm_parent")
        cn = M.uname("orm_child")

        class P(Base):
            __tablename__ = pn
            id = Column(Integer, primary_key=True)
            children = relationship("C", cascade="all, delete-orphan")

        class C(Base):
            __tablename__ = cn
            id = Column(Integer, primary_key=True)
            pid = Column(Integer, ForeignKey(f"{pn}.id"))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                p = P(id=1)
                p.children = [C(id=1), C(id=2)]
                s.add(p)
                s.commit()
                s.delete(p)
                s.commit()
                remaining = s.query(C).count()
                if remaining != 0:
                    raise BehaviorMismatch(f"cascade delete left {remaining}")
        finally:
            _drop(cn, pn)
    add("cascade_delete_orphan", orm_cascade_delete)

    def orm_one_to_one():
        Base = make_base()
        pn = M.uname("orm_u")
        cn = M.uname("orm_prof")

        class Usr(Base):
            __tablename__ = pn
            id = Column(Integer, primary_key=True)
            profile = relationship("Prof", back_populates="user", uselist=False)

        class Prof(Base):
            __tablename__ = cn
            id = Column(Integer, primary_key=True)
            uid = Column(Integer, ForeignKey(f"{pn}.id"))
            user = relationship("Usr", back_populates="profile")
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                u = Usr(id=1)
                u.profile = Prof(id=1)
                s.add(u)
                s.commit()
                got = s.get(Usr, 1)
                if got.profile is None:
                    raise BehaviorMismatch("one-to-one missing")
        finally:
            _drop(cn, pn)
    add("relationship_one_to_one", orm_one_to_one)

    def orm_many_to_many():
        Base = make_base()
        an = M.uname("orm_a")
        bn = M.uname("orm_b")
        assoc = M.uname("orm_ab")

        assoc_tbl = Table(
            assoc, Base.metadata,
            Column("a_id", ForeignKey(f"{an}.id"), primary_key=True),
            Column("b_id", ForeignKey(f"{bn}.id"), primary_key=True),
        )

        class A(Base):
            __tablename__ = an
            id = Column(Integer, primary_key=True)
            bs = relationship("B", secondary=assoc_tbl, back_populates="as_")

        class B(Base):
            __tablename__ = bn
            id = Column(Integer, primary_key=True)
            as_ = relationship("A", secondary=assoc_tbl, back_populates="bs")
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                a = A(id=1)
                a.bs = [B(id=1), B(id=2)]
                s.add(a)
                s.commit()
                got = s.get(A, 1)
                if len(got.bs) != 2:
                    raise BehaviorMismatch(f"m2m -> {len(got.bs)}")
        finally:
            _drop(assoc, bn, an)
    add("relationship_many_to_many", orm_many_to_many)

    def orm_self_referential():
        Base = make_base()
        tn = M.uname("orm_node")

        class Node(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            pid = Column(Integer, ForeignKey(f"{tn}.id"))
            children = relationship(
                "Node", backref=sa.orm.backref("parent", remote_side=[id]))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                root = Node(id=1)
                root.children = [Node(id=2), Node(id=3)]
                s.add(root)
                s.commit()
                got = s.get(Node, 1)
                if len(got.children) != 2:
                    raise BehaviorMismatch(f"self-ref -> {len(got.children)}")
        finally:
            _drop(tn)
    add("relationship_self_referential", orm_self_referential)

    def _eager(strategy):
        def run():
            Base = make_base()
            pn = M.uname("orm_parent")
            cn = M.uname("orm_child")

            class P(Base):
                __tablename__ = pn
                id = Column(Integer, primary_key=True)
                children = relationship("C", back_populates="parent")

            class C(Base):
                __tablename__ = cn
                id = Column(Integer, primary_key=True)
                pid = Column(Integer, ForeignKey(f"{pn}.id"))
                parent = relationship("P", back_populates="children")
            try:
                Base.metadata.create_all(_eng())
                with _session() as s:
                    p = P(id=1)
                    p.children = [C(id=1), C(id=2)]
                    s.add(p)
                    s.commit()
                with _session() as s:
                    loader = {"joined": joinedload, "selectin": selectinload,
                              "subquery": subqueryload}[strategy]
                    result = s.execute(select(P).options(loader(P.children)))
                    # joinedload against a collection requires .unique()
                    res = result.unique().scalars().all()
                    if not res or len(res[0].children) != 2:
                        raise BehaviorMismatch(f"{strategy} eager load failed")
            finally:
                _drop(cn, pn)
        return run
    add("eager_joinedload", _eager("joined"))
    add("eager_selectinload", _eager("selectin"))
    add("eager_subqueryload", _eager("subquery"))

    def orm_lazy_load():
        Base = make_base()
        pn = M.uname("orm_parent")
        cn = M.uname("orm_child")

        class P(Base):
            __tablename__ = pn
            id = Column(Integer, primary_key=True)
            children = relationship("C", lazy="select")

        class C(Base):
            __tablename__ = cn
            id = Column(Integer, primary_key=True)
            pid = Column(Integer, ForeignKey(f"{pn}.id"))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                p = P(id=1)
                p.children = [C(id=1)]
                s.add(p)
                s.commit()
                got = s.get(P, 1)
                _ = list(got.children)
        finally:
            _drop(cn, pn)
    add("lazy_load", orm_lazy_load)

    return items


# ===========================================================================
# 4. Transactions (incl. nested SAVEPOINT — expect failure).
# ===========================================================================

def _tx_scenarios():
    items = []

    def add(name, fn):
        items.append(("transaction", name, fn))

    def commit_rollback():
        Base = declarative_base()
        tn = M.uname("sa_tx")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(T(id=1))
                s.commit()
                s.add(T(id=2))
                s.rollback()
                n = s.query(T).count()
                if n != 1:
                    raise BehaviorMismatch(f"rollback left {n}")
        finally:
            _drop(tn)
    add("commit_rollback", commit_rollback)

    def nested_savepoint():
        # KNOWN C5: begin_nested() -> SAVEPOINT; rollback -> ROLLBACK TO SAVEPOINT
        # which is unimplemented (20101).
        Base = declarative_base()
        tn = M.uname("sa_tx")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            name = Column(String(20))
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(T(id=1, name="outer"))
                s.flush()
                sp = s.begin_nested()
                s.add(T(id=2, name="inner"))
                s.flush()
                sp.rollback()  # ROLLBACK TO SAVEPOINT -> error
                s.commit()
                cnt = s.query(T).count()
                if cnt != 1:
                    raise BehaviorMismatch(
                        f"nested rollback leaked: {cnt} rows (expected 1)")
        finally:
            _drop(tn)
    add("nested_begin_nested_savepoint", nested_savepoint)

    def explicit_begin():
        Base = declarative_base()
        tn = M.uname("sa_tx")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
        try:
            Base.metadata.create_all(_eng())
            with _eng().begin() as c:
                c.execute(text(f"INSERT INTO `{tn}` VALUES (1)"))
        finally:
            _drop(tn)
    add("engine_begin_block", explicit_begin)

    return items


# ===========================================================================
# 5. Bulk operations.
# ===========================================================================

def _bulk_scenarios():
    items = []

    def add(name, fn):
        items.append(("bulk", name, fn))

    def make_model():
        Base = declarative_base()
        tn = M.uname("sa_bulk")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            n = Column(Integer)
            s = Column(String(30))
        return Base, T, tn

    def bulk_save_objects():
        Base, T, tn = make_model()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.bulk_save_objects([T(id=i, n=i, s=f"r{i}") for i in range(1, 101)])
                s.commit()
                if s.query(T).count() != 100:
                    raise BehaviorMismatch("bulk_save_objects count")
        finally:
            _drop(tn)
    add("bulk_save_objects", bulk_save_objects)

    def bulk_insert_mappings():
        Base, T, tn = make_model()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.bulk_insert_mappings(
                    T, [{"id": i, "n": i, "s": f"m{i}"} for i in range(1, 101)])
                s.commit()
                if s.query(T).count() != 100:
                    raise BehaviorMismatch("bulk_insert_mappings count")
        finally:
            _drop(tn)
    add("bulk_insert_mappings", bulk_insert_mappings)

    def bulk_update_mappings():
        Base, T, tn = make_model()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.bulk_insert_mappings(
                    T, [{"id": i, "n": i, "s": "x"} for i in range(1, 51)])
                s.commit()
                s.bulk_update_mappings(
                    T, [{"id": i, "n": i * 10} for i in range(1, 51)])
                s.commit()
        finally:
            _drop(tn)
    add("bulk_update_mappings", bulk_update_mappings)

    def core_bulk_update():
        Base, T, tn = make_model()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.bulk_insert_mappings(
                    T, [{"id": i, "n": i, "s": "x"} for i in range(1, 51)])
                s.commit()
                s.execute(sa.update(T).where(T.n > 25).values(s="hi"))
                s.commit()
        finally:
            _drop(tn)
    add("core_update_statement", core_bulk_update)

    def core_insert_many_execute():
        Base, T, tn = make_model()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.execute(sa.insert(T), [
                    {"id": i, "n": i, "s": "z"} for i in range(1, 201)])
                s.commit()
                if s.query(T).count() != 200:
                    raise BehaviorMismatch("insert-execute many count")
        finally:
            _drop(tn)
    add("insert_execute_many", core_insert_many_execute)

    return items


# ===========================================================================
# 6. Streaming / server-side cursors.
# ===========================================================================

def _stream_scenarios():
    items = []

    def add(name, fn):
        items.append(("stream", name, fn))

    def seed_big(n=5000):
        Base = declarative_base()
        tn = M.uname("sa_stream")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            v = Column(Integer)
        Base.metadata.create_all(_eng())
        with _eng().begin() as c:
            tbl = T.__table__
            batch = []
            for i in range(1, n + 1):
                batch.append({"id": i, "v": i})
                if len(batch) == 1000:
                    c.execute(tbl.insert(), batch)
                    batch = []
            if batch:
                c.execute(tbl.insert(), batch)
        return T, tn

    def yield_per():
        T, tn = seed_big(5000)
        try:
            with _session() as s:
                total = 0
                for _obj in s.execute(
                        select(T).execution_options(yield_per=500)).scalars():
                    total += 1
                if total != 5000:
                    raise BehaviorMismatch(f"yield_per streamed {total}")
        finally:
            _drop(tn)
    add("orm_yield_per", yield_per)

    def stream_results():
        T, tn = seed_big(5000)
        try:
            with _eng().connect().execution_options(stream_results=True) as c:
                cnt = 0
                for _row in c.execute(select(T.__table__)):
                    cnt += 1
                if cnt != 5000:
                    raise BehaviorMismatch(f"stream_results {cnt}")
        finally:
            _drop(tn)
    add("core_stream_results", stream_results)

    def fetch_10k():
        Base = declarative_base()
        tn = M.uname("sa_big")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            v = Column(Integer)
        try:
            Base.metadata.create_all(_eng())
            with _eng().begin() as c:
                for start in range(1, 10001, 1000):
                    c.execute(T.__table__.insert(), [
                        {"id": i, "v": i} for i in range(start, start + 1000)])
            with _conn() as c:
                rows = c.execute(select(T.__table__)).all()
                if len(rows) != 10000:
                    raise BehaviorMismatch(f"fetch 10k -> {len(rows)}")
        finally:
            _drop(tn)
    add("fetch_10k_rows", fetch_10k)

    return items


# ===========================================================================
# 7. Reflection / Inspector (expect information_schema gaps).
# ===========================================================================

def _reflection_scenarios():
    items = []

    def add(name, fn):
        items.append(("reflection", name, fn))

    def reflect_single_table():
        tn = M.uname("sa_refl")
        md = MetaData()
        Table(tn, md, Column("id", Integer, primary_key=True),
              Column("name", String(50)), Column("age", Integer))
        try:
            md.create_all(_eng())
            md2 = MetaData()
            reflected = Table(tn, md2, autoload_with=_eng())
            if "name" not in reflected.c:
                raise BehaviorMismatch("reflected table missing column")
        finally:
            _drop(tn)
    add("autoload_table", reflect_single_table)

    def inspector_columns():
        tn = M.uname("sa_refl")
        md = MetaData()
        Table(tn, md, Column("id", Integer, primary_key=True),
              Column("name", String(50)))
        try:
            md.create_all(_eng())
            insp = sa.inspect(_eng())
            cols = insp.get_columns(tn)
            if not cols:
                raise BehaviorMismatch("inspector got no columns")
        finally:
            _drop(tn)
    add("inspector_get_columns", inspector_columns)

    def inspector_indexes():
        tn = M.uname("sa_refl")
        md = MetaData()
        t = Table(tn, md, Column("id", Integer, primary_key=True),
                  Column("name", String(50)))
        sa.Index("ix_name", t.c.name)
        try:
            md.create_all(_eng())
            insp = sa.inspect(_eng())
            insp.get_indexes(tn)
        finally:
            _drop(tn)
    add("inspector_get_indexes", inspector_indexes)

    def inspector_fks():
        pn = M.uname("sa_refl_p")
        cn = M.uname("sa_refl_c")
        md = MetaData()
        Table(pn, md, Column("id", Integer, primary_key=True))
        Table(cn, md, Column("id", Integer, primary_key=True),
              Column("pid", Integer, ForeignKey(f"{pn}.id")))
        try:
            md.create_all(_eng())
            insp = sa.inspect(_eng())
            insp.get_foreign_keys(cn)
        finally:
            _drop(cn, pn)
    add("inspector_get_foreign_keys", inspector_fks)

    def inspector_table_names():
        insp = sa.inspect(_eng())
        names = insp.get_table_names()
        return f"{len(names)} tables"
    add("inspector_get_table_names", inspector_table_names)

    def reflect_check_constraints():
        tn = M.uname("sa_refl")
        md = MetaData()
        Table(tn, md, Column("id", Integer, primary_key=True),
              Column("age", Integer, CheckConstraint("age >= 0")))
        try:
            md.create_all(_eng())
            insp = sa.inspect(_eng())
            insp.get_check_constraints(tn)
        finally:
            _drop(tn)
    add("inspector_get_check_constraints", reflect_check_constraints)

    def reflect_metadata_all():
        tn = M.uname("sa_refl")
        md = MetaData()
        Table(tn, md, Column("id", Integer, primary_key=True))
        try:
            md.create_all(_eng())
            md2 = MetaData()
            md2.reflect(bind=_eng(), only=[tn])
        finally:
            _drop(tn)
    add("metadata_reflect", reflect_metadata_all)

    def inspector_pk():
        tn = M.uname("sa_refl")
        md = MetaData()
        Table(tn, md, Column("id", Integer, primary_key=True),
              Column("name", String(20)))
        try:
            md.create_all(_eng())
            insp = sa.inspect(_eng())
            insp.get_pk_constraint(tn)
        finally:
            _drop(tn)
    add("inspector_get_pk_constraint", inspector_pk)

    return items


# ===========================================================================
# 8. ORM advanced: events, hybrid properties, association proxy.
# ===========================================================================

def _advanced_scenarios():
    items = []

    def add(name, fn):
        items.append(("orm_advanced", name, fn))

    def hybrid_property():
        from sqlalchemy.ext.hybrid import hybrid_property
        Base = declarative_base()
        tn = M.uname("sa_hyb")

        class U(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            first = Column(String(20))
            last = Column(String(20))

            @hybrid_property
            def full(self):
                return self.first + " " + self.last
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(U(id=1, first="Ada", last="Lovelace"))
                s.commit()
                u = s.get(U, 1)
                if u.full != "Ada Lovelace":
                    raise BehaviorMismatch("hybrid instance failed")
                rows = s.execute(
                    select(U).where(U.full == "Ada Lovelace")).scalars().all()
                if not rows:
                    raise BehaviorMismatch("hybrid SQL expression matched 0")
        finally:
            _drop(tn)
    add("hybrid_property", hybrid_property)

    def association_proxy():
        from sqlalchemy.ext.associationproxy import association_proxy
        Base = declarative_base()
        un = M.uname("sa_ap_u")
        kn = M.uname("sa_ap_k")

        class Keyword(Base):
            __tablename__ = kn
            id = Column(Integer, primary_key=True)
            uid = Column(Integer, ForeignKey(f"{un}.id"))
            word = Column(String(30))

        class User(Base):
            __tablename__ = un
            id = Column(Integer, primary_key=True)
            kw = relationship("Keyword")
            words = association_proxy("kw", "word")
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                u = User(id=1)
                u.kw = [Keyword(id=1, word="a"), Keyword(id=2, word="b")]
                s.add(u)
                s.commit()
                got = s.get(User, 1)
                if set(got.words) != {"a", "b"}:
                    raise BehaviorMismatch(f"assoc proxy -> {list(got.words)}")
        finally:
            _drop(kn, un)
    add("association_proxy", association_proxy)

    def orm_event():
        from sqlalchemy import event
        Base = declarative_base()
        tn = M.uname("sa_evt")

        class U(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            name = Column(String(20))
        fired = {"v": False}

        @event.listens_for(U, "before_insert")
        def _bi(mapper, connection, target):
            fired["v"] = True
            if target.name:
                target.name = target.name.upper()
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(U(id=1, name="abc"))
                s.commit()
                u = s.get(U, 1)
                if not fired["v"] or u.name != "ABC":
                    raise BehaviorMismatch("before_insert event not applied")
        finally:
            _drop(tn)
    add("orm_before_insert_event", orm_event)

    def column_property():
        from sqlalchemy.orm import column_property
        Base = declarative_base()
        tn = M.uname("sa_colprop")

        class U(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            a = Column(Integer)
            b = Column(Integer)
            total = column_property(a + b)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(U(id=1, a=3, b=4))
                s.commit()
                u = s.get(U, 1)
                if u.total != 7:
                    raise BehaviorMismatch(f"column_property -> {u.total}")
        finally:
            _drop(tn)
    add("column_property", column_property)

    return items


# ===========================================================================
# 9. Load / workload scenarios.
# ===========================================================================

def _workload_scenarios():
    items = []

    def add(name, fn):
        items.append(("workload", name, fn))

    def batch_insert(size):
        def run():
            Base = declarative_base()
            tn = M.uname("sa_load")

            class T(Base):
                __tablename__ = tn
                id = Column(Integer, primary_key=True)
                v = Column(Integer)
            try:
                Base.metadata.create_all(_eng())
                with _eng().begin() as c:
                    c.execute(T.__table__.insert(),
                              [{"id": i, "v": i} for i in range(1, size + 1)])
                with _conn() as c:
                    n = c.execute(
                        select(func.count()).select_from(T.__table__)).scalar()
                    if n != size:
                        raise BehaviorMismatch(f"batch {size} -> {n}")
            finally:
                _drop(tn)
        return run
    for sz in (1, 10, 100, 1000):
        add(f"batch_insert_{sz}", batch_insert(sz))

    def wide_table():
        Base = declarative_base()
        tn = M.uname("sa_wide")
        attrs = {"__tablename__": tn, "id": Column(Integer, primary_key=True)}
        for i in range(120):
            attrs[f"c{i}"] = Column(Integer)
        T = type("WideT", (Base,), attrs)
        try:
            Base.metadata.create_all(_eng())
            with _eng().begin() as c:
                vals = {"id": 1}
                vals.update({f"c{i}": i for i in range(120)})
                c.execute(T.__table__.insert().values(**vals))
        finally:
            _drop(tn)
    add("wide_table_120_columns", wide_table)

    def many_column_index():
        tn = M.uname("sa_idx")
        md = MetaData()
        cols = [Column("id", Integer, primary_key=True)]
        for i in range(8):
            cols.append(Column(f"c{i}", Integer))
        t = Table(tn, md, *cols)
        sa.Index("ix_multi", *[t.c[f"c{i}"] for i in range(8)])
        try:
            md.create_all(_eng())
        finally:
            _drop(tn)
    add("many_column_index", many_column_index)

    def deep_json():
        Base = declarative_base()
        tn = M.uname("sa_json")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            data = Column(sa.JSON)
        try:
            Base.metadata.create_all(_eng())
            deep = {"a": {"b": {"c": {"d": [1, 2, {"e": "f"}]}}}}
            with _session() as s:
                s.add(T(id=1, data=deep))
                s.commit()
                t = s.get(T, 1)
                if t.data is None:
                    raise BehaviorMismatch("deep json roundtrip null")
        finally:
            _drop(tn)
    add("deep_json", deep_json)

    def long_string():
        Base = declarative_base()
        tn = M.uname("sa_long")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            body = Column(Text)
        try:
            Base.metadata.create_all(_eng())
            big = "x" * 100000
            with _session() as s:
                s.add(T(id=1, body=big))
                s.commit()
                t = s.get(T, 1)
                if len(t.body) != 100000:
                    raise BehaviorMismatch(f"long string len {len(t.body)}")
        finally:
            _drop(tn)
    add("long_string_100k", long_string)

    def large_blob():
        Base = declarative_base()
        tn = M.uname("sa_blob")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            blob = Column(sa.LargeBinary)
        try:
            Base.metadata.create_all(_eng())
            data = b"\x00\x01\x02" * 50000
            with _session() as s:
                s.add(T(id=1, blob=data))
                s.commit()
                t = s.get(T, 1)
                if t.blob is None or len(t.blob) != len(data):
                    raise BehaviorMismatch("blob roundtrip mismatch")
        finally:
            _drop(tn)
    add("large_blob", large_blob)

    def many_statement_transaction():
        Base = declarative_base()
        tn = M.uname("sa_manystmt")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            v = Column(Integer)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                for i in range(1, 501):
                    s.add(T(id=i, v=i))
                    if i % 50 == 0:
                        s.flush()
                s.commit()
                if s.query(T).count() != 500:
                    raise BehaviorMismatch("many-stmt tx count")
        finally:
            _drop(tn)
    add("transaction_500_statements", many_statement_transaction)

    return items


# ===========================================================================
# 10. Generated matrices: MySQL-dialect type x operation, and Core function
#     expressions. These broaden the SQLAlchemy surface programmatically.
# ===========================================================================

from sqlalchemy.dialects import mysql as _mysql  # noqa: E402

# (label, SQLAlchemy MySQL dialect type instance factory, sample value, py-ok)
MYSQL_DIALECT_TYPES = [
    ("TINYINT", lambda: _mysql.TINYINT(), 42),
    ("SMALLINT", lambda: _mysql.SMALLINT(), 1234),
    ("MEDIUMINT", lambda: _mysql.MEDIUMINT(), 123456),
    ("INTEGER", lambda: _mysql.INTEGER(), 2000000000),
    ("INTEGER_unsigned", lambda: _mysql.INTEGER(unsigned=True), 4000000000),
    ("BIGINT", lambda: _mysql.BIGINT(), 9000000000000000000),
    ("BIGINT_unsigned", lambda: _mysql.BIGINT(unsigned=True), 18000000000000000000),
    ("DECIMAL", lambda: _mysql.DECIMAL(10, 2), decimal.Decimal("123.45")),
    ("FLOAT", lambda: _mysql.FLOAT(), 3.5),
    ("DOUBLE", lambda: _mysql.DOUBLE(), 2.71828),
    ("BIT", lambda: _mysql.BIT(8), None),
    ("CHAR", lambda: _mysql.CHAR(10), "abc"),
    ("VARCHAR", lambda: _mysql.VARCHAR(50), "hello"),
    ("TINYTEXT", lambda: _mysql.TINYTEXT(), "tiny"),
    ("TEXT", lambda: _mysql.TEXT(), "text value"),
    ("MEDIUMTEXT", lambda: _mysql.MEDIUMTEXT(), "medium"),
    ("LONGTEXT", lambda: _mysql.LONGTEXT(), "long"),
    ("TINYBLOB", lambda: _mysql.TINYBLOB(), b"tb"),
    ("BLOB", lambda: _mysql.BLOB(), b"blob"),
    ("MEDIUMBLOB", lambda: _mysql.MEDIUMBLOB(), b"mb"),
    ("LONGBLOB", lambda: _mysql.LONGBLOB(), b"lb"),
    ("BINARY", lambda: _mysql.BINARY(8), b"bin"),
    ("VARBINARY", lambda: _mysql.VARBINARY(32), b"vbin"),
    ("DATE", lambda: _mysql.DATE(), datetime.date(2026, 6, 23)),
    ("TIME", lambda: _mysql.TIME(), datetime.time(11, 22, 33)),
    ("DATETIME", lambda: _mysql.DATETIME(), datetime.datetime(2026, 6, 23, 1, 2, 3)),
    ("TIMESTAMP", lambda: _mysql.TIMESTAMP(), datetime.datetime(2026, 6, 23, 1, 2, 3)),
    ("YEAR", lambda: _mysql.YEAR(), 2026),
    ("ENUM", lambda: _mysql.ENUM("a", "b", "c"), "b"),
    ("SET", lambda: _mysql.SET("x", "y", "z"), "x"),
    ("JSON", lambda: _mysql.JSON(), {"k": 1}),
]


def _dialect_type_op(label, factory, value, op):
    def run():
        tn = M.uname("sa_dt")
        md = MetaData()
        tbl = Table(tn, md, Column("id", Integer, primary_key=True),
                    Column("c", factory(), nullable=True))
        try:
            md.create_all(_eng())
            if op == "declare":
                return f"declare {label}"
            if value is None and op in ("roundtrip", "where", "orderby", "groupby"):
                raise SkipScenario("no sample value")
            with _eng().begin() as c:
                if op == "null":
                    c.execute(tbl.insert().values(id=1, c=None))
                    r = c.execute(select(tbl.c.c).where(tbl.c.id == 1)).first()
                    if r[0] is not None:
                        raise BehaviorMismatch(f"{label} NULL -> {r[0]!r}")
                    return f"null {label}"
                c.execute(tbl.insert().values(id=1, c=value))
                if op == "roundtrip":
                    c.execute(select(tbl.c.c).where(tbl.c.id == 1)).first()
                elif op == "where":
                    c.execute(select(tbl.c.id).where(tbl.c.c == value)).all()
                elif op == "orderby":
                    c.execute(select(tbl.c.id).order_by(tbl.c.c)).all()
                elif op == "groupby":
                    c.execute(select(tbl.c.c, func.count())
                              .group_by(tbl.c.c)).all()
                elif op == "update":
                    c.execute(tbl.update().where(tbl.c.id == 1).values(c=value))
                elif op == "distinct":
                    c.execute(select(tbl.c.c).distinct()).all()
                elif op == "count":
                    c.execute(select(func.count(tbl.c.c))).first()
            return f"{op} {label}"
        finally:
            _drop(tn)
    return run


# Core function-expression matrix via func.* (exercises SQLAlchemy compilation).
SA_FUNC_EXPRS = [
    ("concat", lambda: func.concat("a", "b", "c")),
    ("length", lambda: func.length("hello")),
    ("upper", lambda: func.upper("abc")),
    ("lower", lambda: func.lower("ABC")),
    ("substr", lambda: func.substr("abcdef", 2, 3)),
    ("trim", lambda: func.trim("  x  ")),
    ("replace", lambda: func.replace("aXb", "X", "-")),
    ("abs", lambda: func.abs(-5)),
    ("round", lambda: func.round(3.14159, 2)),
    ("ceil", lambda: func.ceil(4.2)),
    ("floor", lambda: func.floor(4.8)),
    ("mod", lambda: func.mod(10, 3)),
    ("power", lambda: func.power(2, 10)),
    ("sqrt", lambda: func.sqrt(16)),
    ("coalesce", lambda: func.coalesce(None, "x")),
    ("nullif", lambda: func.nullif(1, 1)),
    ("now", lambda: func.now()),
    ("curdate", lambda: func.current_date()),
    ("year", lambda: func.year("2026-06-23")),
    ("month", lambda: func.month("2026-06-23")),
    ("day", lambda: func.day("2026-06-23")),
    ("date_add", lambda: func.date_add(sa.text("'2026-06-23'"),
                                       sa.text("INTERVAL 1 DAY"))),
    ("greatest", lambda: func.greatest(1, 2, 3)),
    ("least", lambda: func.least(3, 1, 2)),
    ("cast_signed", lambda: sa.cast("42", sa.Integer)),
    ("cast_decimal", lambda: sa.cast("1.5", sa.Numeric(10, 2))),
    ("md5", lambda: func.md5("abc")),
    ("sha1", lambda: func.sha1("abc")),
    ("hex", lambda: func.hex(255)),
    ("char_length", lambda: func.char_length("hello")),
    ("json_extract", lambda: func.json_extract(
        sa.literal('{"a": 1}'), sa.literal("$.a"))),
    ("json_contains", lambda: func.json_contains(
        sa.literal("[1,2]"), sa.literal("1"))),
    ("inet_aton", lambda: func.inet_aton("127.0.0.1")),
    ("uuid", lambda: func.uuid()),
    ("rand", lambda: func.rand()),
    ("pi", lambda: func.pi()),
]


def _sa_func_bare(label, expr_factory):
    def run():
        with _conn() as c:
            c.execute(select(expr_factory())).first()
        return f"func {label}"
    return run


def _sa_func_in_where(label, expr_factory):
    def run():
        tn = M.uname("sa_fn")
        md = MetaData()
        tbl = Table(tn, md, Column("id", Integer, primary_key=True),
                    Column("s", String(50)), Column("n", Integer))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=1, s="hello", n=10))
            with _conn() as c:
                c.execute(select(tbl.c.id).where(expr_factory().isnot(None))).all()
        finally:
            _drop(tn)
        return f"func-where {label}"
    return run


# --- Generated ORM declarative type-mapping x operation matrix. ------------
# Each mapped column type is declared in a declarative model, then exercised
# through ORM create/query/filter/order. Distinct from the Core type matrix.
ORM_TYPE_MAP = [
    ("Integer", lambda: sa.Integer(), 42, True),
    ("BigInteger", lambda: sa.BigInteger(), 10**18, True),
    ("SmallInteger", lambda: sa.SmallInteger(), 1234, True),
    ("Boolean", lambda: sa.Boolean(), True, False),
    ("Float", lambda: sa.Float(), 3.5, True),
    ("Double", lambda: sa.Double(), 2.5, True),
    ("Numeric", lambda: sa.Numeric(10, 2), decimal.Decimal("12.34"), True),
    ("String", lambda: sa.String(50), "hello", True),
    ("Text", lambda: sa.Text(), "text value", False),
    ("Date", lambda: sa.Date(), datetime.date(2026, 6, 23), True),
    ("Time", lambda: sa.Time(), datetime.time(1, 2, 3), True),
    ("DateTime", lambda: sa.DateTime(), datetime.datetime(2026, 6, 23, 1, 2, 3), True),
    ("LargeBinary", lambda: sa.LargeBinary(), b"data", False),
    ("Enum", lambda: sa.Enum("a", "b", "c", name="e"), "b", True),
    ("JSON", lambda: sa.JSON(), {"k": 1}, False),
    ("Uuid", lambda: sa.Uuid(as_uuid=True),
     __import__("uuid").UUID("00000000-0000-0000-0000-000000000002"), True),
]


def _orm_type_op(label, factory, value, orderable, op):
    def run():
        from sqlalchemy.orm import declarative_base
        Base = declarative_base()
        tn = M.uname("sa_otm")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            val = Column(factory(), nullable=True)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                if op == "null":
                    s.add(T(id=1, val=None))
                    s.commit()
                    obj = s.get(T, 1)
                    if obj.val is not None:
                        raise BehaviorMismatch(f"{label} ORM NULL -> {obj.val!r}")
                    return f"orm-null {label}"
                s.add(T(id=1, val=value))
                s.commit()
                if op == "roundtrip":
                    s.get(T, 1)
                elif op == "filter":
                    s.execute(select(T).where(T.val == value)).scalars().all()
                elif op == "order":
                    if not orderable:
                        raise SkipScenario("not orderable")
                    s.execute(select(T).order_by(T.val)).scalars().all()
            return f"orm-{op} {label}"
        finally:
            _drop(tn)
    return run


# --- Generated relationship-loader x relationship-kind matrix. -------------
def _rel_loader(kind, strategy):
    def run():
        from sqlalchemy.orm import declarative_base
        Base = declarative_base()
        pn = M.uname("sa_rl_p")
        cn = M.uname("sa_rl_c")
        is_collection = kind in ("one_to_many", "many_to_many")

        if kind == "many_to_many":
            assoc = M.uname("sa_rl_a")
            assoc_tbl = Table(
                assoc, Base.metadata,
                Column("p_id", ForeignKey(f"{pn}.id"), primary_key=True),
                Column("c_id", ForeignKey(f"{cn}.id"), primary_key=True))

            class P(Base):
                __tablename__ = pn
                id = Column(Integer, primary_key=True)
                children = relationship("C", secondary=assoc_tbl)

            class C(Base):
                __tablename__ = cn
                id = Column(Integer, primary_key=True)
            drop_names = (assoc, cn, pn)
        else:
            uselist = (kind == "one_to_many")

            class P(Base):
                __tablename__ = pn
                id = Column(Integer, primary_key=True)
                children = relationship("C", uselist=uselist)

            class C(Base):
                __tablename__ = cn
                id = Column(Integer, primary_key=True)
                pid = Column(Integer, ForeignKey(f"{pn}.id"))
            drop_names = (cn, pn)

        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                p = P(id=1)
                if is_collection:
                    p.children = [C(id=1), C(id=2)]
                else:
                    p.children = C(id=1)
                s.add(p)
                s.commit()
            with _session() as s:
                loader = {"joined": joinedload, "selectin": selectinload,
                          "subquery": subqueryload, "lazy": None}[strategy]
                stmt = select(P)
                if loader is not None:
                    stmt = stmt.options(loader(P.children))
                result = s.execute(stmt)
                if strategy == "joined" and is_collection:
                    result = result.unique()
                objs = result.scalars().all()
                if not objs:
                    raise BehaviorMismatch("no parent loaded")
                _ = objs[0].children  # trigger lazy if needed
            return f"{kind}/{strategy}"
        finally:
            _drop(*drop_names)
    return run


# --- SA Core DML-on-type matrix (insert/update/delete per dialect type). ---
def _sa_dml_on_type(label, factory, value, op):
    def run():
        if value is None:
            raise SkipScenario("no sample value")
        tn = M.uname("sa_dml")
        md = MetaData()
        tbl = Table(tn, md, Column("id", Integer, primary_key=True),
                    Column("c", factory()))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=1, c=value))
                if op == "insert_more":
                    c.execute(tbl.insert().values(id=2, c=value))
                elif op == "update":
                    c.execute(tbl.update().where(tbl.c.c == value).values(c=value))
                elif op == "delete":
                    c.execute(tbl.delete().where(tbl.c.c == value))
        finally:
            _drop(tn)
        return f"dml-{op} {label}"
    return run


# --- SA Core join-style matrix. --------------------------------------------
def _sa_join_style(style):
    def run():
        an = M.uname("sa_j_a")
        bn = M.uname("sa_j_b")
        md = MetaData()
        a = Table(an, md, Column("id", Integer, primary_key=True),
                  Column("n", Integer))
        b = Table(bn, md, Column("id", Integer, primary_key=True),
                  Column("aid", Integer))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(a.insert(), [{"id": 1, "n": 10}, {"id": 2, "n": 20}])
                c.execute(b.insert(), [{"id": 1, "aid": 1}])
            with _conn() as c:
                if style == "inner":
                    stmt = select(a.c.n).join(b, a.c.id == b.c.aid)
                elif style == "outer":
                    stmt = select(a.c.n).outerjoin(b, a.c.id == b.c.aid)
                elif style == "full":
                    stmt = select(a.c.n).join(b, a.c.id == b.c.aid, full=True)
                elif style == "cross":
                    stmt = select(a.c.n).join(b, sa.true())
                else:
                    stmt = select(a.c.n).select_from(
                        a.join(b, a.c.id == b.c.aid, isouter=True))
                c.execute(stmt).all()
        finally:
            _drop(bn, an)
        return f"join {style}"
    return run


# --- SA Core select-construct variants on a seeded table. ------------------
_SA_SELECT_VARIANTS = [
    ("columns", lambda t: select(t.c.id, t.c.n)),
    ("whole_table", lambda t: select(t)),
    ("label", lambda t: select(t.c.n.label("value"))),
    ("distinct", lambda t: select(t.c.g).distinct()),
    ("order_by", lambda t: select(t).order_by(t.c.n.desc())),
    ("limit", lambda t: select(t).limit(2)),
    ("offset", lambda t: select(t).limit(2).offset(1)),
    ("where_and", lambda t: select(t).where(t.c.n > 5, t.c.g == 1)),
    ("where_or", lambda t: select(t).where(sa.or_(t.c.n < 5, t.c.n > 50))),
    ("group_by", lambda t: select(t.c.g, func.count()).group_by(t.c.g)),
    ("having", lambda t: select(t.c.g, func.sum(t.c.n)).group_by(t.c.g)
        .having(func.sum(t.c.n) > 10)),
    ("scalar", lambda t: select(func.count()).select_from(t)),
    ("subquery", lambda t: select(select(t.c.n).where(t.c.g == 1).subquery())),
    ("union", lambda t: sa.union(select(t.c.id).where(t.c.g == 1),
                                 select(t.c.id).where(t.c.g == 2))),
    ("exists", lambda t: select(t).where(
        select(t.c.id).where(t.c.n > 5).exists())),
    ("case", lambda t: select(t.c.id, sa.case((t.c.n > 10, "hi"), else_="lo"))),
    ("count_filter", lambda t: select(
        func.count().filter(t.c.n > 10))),
    ("coalesce", lambda t: select(func.coalesce(t.c.s, sa.literal("x")))),
    ("with_for_update", lambda t: select(t).where(t.c.id == 1).with_for_update()),
    ("cte", lambda t: select(select(t.c.g, t.c.n).cte("x").c.g)),
]


def _sa_select_variant(label, build):
    def run():
        tbl, name = _core_table()
        try:
            with _conn() as c:
                c.execute(build(tbl)).all()
        finally:
            _drop(name)
        return f"select-variant {label}"
    return run


def _sa_func_labeled(label, expr_factory):
    def run():
        with _conn() as c:
            c.execute(select(expr_factory().label("result"))).first()
        return f"func-labeled {label}"
    return run


def _orm_type_extra(label, factory, value, op):
    def run():
        from sqlalchemy.orm import declarative_base
        Base = declarative_base()
        tn = M.uname("sa_ote")

        class T(Base):
            __tablename__ = tn
            id = Column(Integer, primary_key=True)
            val = Column(factory(), nullable=True)
        try:
            Base.metadata.create_all(_eng())
            with _session() as s:
                s.add(T(id=1, val=value))
                s.commit()
                if op == "update":
                    s.execute(sa.update(T).where(T.id == 1).values(val=value))
                    s.commit()
                elif op == "delete":
                    s.execute(sa.delete(T).where(T.id == 1))
                    s.commit()
                elif op == "count":
                    s.execute(select(func.count(T.val))).first()
                elif op == "distinct":
                    s.execute(select(T.val).distinct()).all()
            return f"orm-{op} {label}"
        finally:
            _drop(tn)
    return run


# --- SA Core operator / expression matrix on a stored column. --------------
_SA_OPERATORS = [
    ("eq", lambda c: c.n == 10),
    ("ne", lambda c: c.n != 10),
    ("lt", lambda c: c.n < 10),
    ("le", lambda c: c.n <= 10),
    ("gt", lambda c: c.n > 10),
    ("ge", lambda c: c.n >= 10),
    ("in", lambda c: c.n.in_([10, 20])),
    ("notin", lambda c: c.n.notin_([99])),
    ("between", lambda c: c.n.between(5, 15)),
    ("isnull", lambda c: c.n.is_(None)),
    ("isnotnull", lambda c: c.n.isnot(None)),
    ("like", lambda c: c.s.like("a%")),
    ("ilike", lambda c: c.s.ilike("A%")),
    ("notlike", lambda c: c.s.notlike("z%")),
    ("startswith", lambda c: c.s.startswith("a")),
    ("endswith", lambda c: c.s.endswith("a")),
    ("contains", lambda c: c.s.contains("b")),
    ("and", lambda c: sa.and_(c.n > 5, c.n < 50)),
    ("or", lambda c: sa.or_(c.n < 5, c.n > 50)),
    ("not", lambda c: sa.not_(c.n == 0)),
    ("add", lambda c: (c.n + 5) > 0),
    ("sub", lambda c: (c.n - 5) > 0),
    ("mul", lambda c: (c.n * 2) > 0),
    ("concat", lambda c: (c.s + "x").isnot(None)),
    ("regexp", lambda c: c.s.regexp_match("^a")),
]


def _sa_operator(label, build):
    def run():
        tn = M.uname("sa_op")
        md = MetaData()
        tbl = Table(tn, md, Column("id", Integer, primary_key=True),
                    Column("n", Integer), Column("s", String(50)))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert().values(id=1, n=10, s="abc"))
            with _conn() as c:
                c.execute(select(tbl.c.id).where(build(tbl.c))).all()
        finally:
            _drop(tn)
        return f"operator {label}"
    return run


# --- SA Core aggregate x column-type matrix. -------------------------------
_SA_AGG_TYPES = [
    ("int", lambda: sa.Integer(), [10, 20, 30]),
    ("bigint", lambda: sa.BigInteger(), [100, 200, 300]),
    ("decimal", lambda: sa.Numeric(10, 2),
     [decimal.Decimal("1.5"), decimal.Decimal("2.5"), decimal.Decimal("3.5")]),
    ("double", lambda: sa.Double(), [1.1, 2.2, 3.3]),
    ("string", lambda: sa.String(20), ["a", "b", "c"]),
    ("date", lambda: sa.Date(),
     [datetime.date(2026, 1, 1), datetime.date(2026, 6, 1),
      datetime.date(2026, 12, 1)]),
]
_SA_AGGS = [
    ("count", lambda col: func.count(col)),
    ("count_distinct", lambda col: func.count(col.distinct())),
    ("min", lambda col: func.min(col)),
    ("max", lambda col: func.max(col)),
    ("sum", lambda col: func.sum(col)),
    ("avg", lambda col: func.avg(col)),
    ("group_concat", lambda col: func.group_concat(col)),
]


def _sa_aggregate(tlabel, tfactory, tvalue, albel, abuild):
    def run():
        tn = M.uname("sa_agg")
        md = MetaData()
        tbl = Table(tn, md, Column("id", Integer, primary_key=True),
                    Column("g", Integer), Column("c", tfactory()))
        try:
            md.create_all(_eng())
            with _eng().begin() as c:
                c.execute(tbl.insert(), [
                    {"id": i + 1, "g": i % 2, "c": v}
                    for i, v in enumerate(tvalue)])
            with _conn() as c:
                c.execute(select(tbl.c.g, abuild(tbl.c.c))
                          .group_by(tbl.c.g)).all()
        finally:
            _drop(tn)
        return f"agg {tlabel}/{albel}"
    return run


# ===========================================================================
# Registration.
# ===========================================================================

def register(runner):
    for label, factory, value in SA_TYPES:
        runner.add(FW, "type_create", f"{label}:create", _sa_type_create(label, factory))
        runner.add(FW, "type_roundtrip", f"{label}:roundtrip",
                   _sa_type_roundtrip(label, factory, value))
        runner.add(FW, "type_null", f"{label}:null", _sa_type_null(label, factory))

    # generated MySQL-dialect type x operation matrix
    for label, factory, value in MYSQL_DIALECT_TYPES:
        for op in ("declare", "roundtrip", "null", "where", "orderby",
                   "groupby", "update", "distinct", "count"):
            runner.add(FW, f"dialect_type_{op}", f"{label}:{op}",
                       _dialect_type_op(label, factory, value, op))

    # generated Core function expression matrix
    for label, ef in SA_FUNC_EXPRS:
        runner.add(FW, "core_func", f"{label}:bare", _sa_func_bare(label, ef))
        runner.add(FW, "core_func", f"{label}:in_where", _sa_func_in_where(label, ef))

    # generated ORM declarative type-mapping x operation matrix
    for label, factory, value, orderable in ORM_TYPE_MAP:
        for op in ("roundtrip", "null", "filter", "order"):
            runner.add(FW, f"orm_type_{op}", f"{label}:{op}",
                       _orm_type_op(label, factory, value, orderable, op))

    # generated relationship-loader x relationship-kind matrix
    for kind in ("one_to_one", "one_to_many", "many_to_many"):
        for strategy in ("lazy", "joined", "selectin", "subquery"):
            runner.add(FW, "rel_loader", f"{kind}:{strategy}",
                       _rel_loader(kind, strategy))

    # generated Core operator/expression matrix
    for label, build in _SA_OPERATORS:
        runner.add(FW, "core_operator", label, _sa_operator(label, build))

    # generated Core aggregate matrix over column types
    for tlabel, tfactory, tvalue in _SA_AGG_TYPES:
        for albel, abuild in _SA_AGGS:
            runner.add(FW, "core_aggregate", f"{tlabel}:{albel}",
                       _sa_aggregate(tlabel, tfactory, tvalue, albel, abuild))

    # generated Core DML-on-type matrix
    for label, factory, value in MYSQL_DIALECT_TYPES:
        for op in ("insert_more", "update", "delete"):
            runner.add(FW, f"core_dml_{op}", f"{label}:{op}",
                       _sa_dml_on_type(label, factory, value, op))

    # generated Core join-style matrix
    for style in ("inner", "outer", "full", "cross", "select_from"):
        runner.add(FW, "core_join_style", style, _sa_join_style(style))

    # generated Core select-construct variants
    for label, build in _SA_SELECT_VARIANTS:
        runner.add(FW, "core_select_variant", label, _sa_select_variant(label, build))

    # generated textual func.* over a literal value matrix
    for label, ef in SA_FUNC_EXPRS:
        runner.add(FW, "core_func_label", f"{label}:labeled",
                   _sa_func_labeled(label, ef))

    # generated ORM type-mapping update/delete/count ops
    for label, factory, value, orderable in ORM_TYPE_MAP:
        for op in ("update", "delete", "count", "distinct"):
            runner.add(FW, f"orm_type_{op}", f"{label}:{op}",
                       _orm_type_extra(label, factory, value, op))

    for cat, name, fn in (
        _core_scenarios() + _orm_basic_scenarios() + _tx_scenarios()
        + _bulk_scenarios() + _stream_scenarios() + _reflection_scenarios()
        + _advanced_scenarios() + _workload_scenarios()
    ):
        runner.add(FW, cat, name, fn)
