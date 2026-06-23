"""Django ORM compatibility scenarios against MatrixOne.

Uses a standalone Django configuration (no project) pointed at mo_py_dj via the
mysql backend, then django.setup(). Models are defined dynamically per scenario
with unique db_table names and their schema is created/dropped with Django's
SchemaEditor (the same path makemigrations/migrate uses), so DDL generation is
exercised exactly as Django would emit it.

Covers:
  - models with all field types (schema generation + roundtrip)
  - CRUD via Manager
  - QuerySet API: filter/exclude/annotate/aggregate/F/Q/values/values_list/
    distinct/order_by/Subquery/OuterRef/Exists/Window/Case-When
  - relationships ForeignKey/OneToOne/ManyToMany + related managers +
    prefetch_related/select_related
  - transactions (atomic + savepoints — expect failure)
  - bulk_create / bulk_update
  - JSONField queries (expect json function gaps)
  - constraints CheckConstraint/UniqueConstraint (CHECK not enforced)
  - aggregation, pagination
"""

from __future__ import annotations

import contextlib
import itertools
import os

# --- Configure Django before importing anything from django.db ---
# Django's mysql backend imports MySQLdb; install PyMySQL under that name so we
# don't need the C extension (mysqlclient). This is the standard pymysql shim.
import pymysql

pymysql.install_as_MySQLdb()

import django  # noqa: E402
from django.conf import settings  # noqa: E402

from harness import BehaviorMismatch, config
from harness import matrices as M

FW = "django"

_app_label = "modjango"
_model_counter = itertools.count(1)


def _configure():
    if settings.configured:
        return
    settings.configure(
        DEBUG=False,
        USE_TZ=False,
        INSTALLED_APPS=["django.contrib.contenttypes", "django.contrib.auth"],
        DATABASES={
            "default": {
                "ENGINE": "django.db.backends.mysql",
                "NAME": config.DB_DJ,
                "USER": config.user(),
                "PASSWORD": config.password(),
                "HOST": config.host(),
                "PORT": str(config.port()),
                "OPTIONS": {"charset": "utf8mb4"},
                "TIME_ZONE": None,
                "CONN_MAX_AGE": 0,
                "CONN_HEALTH_CHECKS": False,
                "AUTOCOMMIT": True,
                "ATOMIC_REQUESTS": False,
            }
        },
        DEFAULT_AUTO_FIELD="django.db.models.BigAutoField",
    )
    django.setup()


_configure()

from django.db import connection, models, transaction  # noqa: E402
from django.db.models import (  # noqa: E402
    Avg, Case, Count, Exists, F, Max, Min, OuterRef, Q, Subquery, Sum, Value,
    When, Window,
)
from django.db.models.functions import RowNumber  # noqa: E402


def _unique_suffix() -> str:
    return f"{next(_model_counter):06d}"


def _make_model(fields: dict, name_prefix="DjM", table_prefix="dj", meta_extra=None):
    """Dynamically build a Django model class with a unique table name."""
    suffix = _unique_suffix()
    table = f"{table_prefix}_{suffix}"
    cls_name = f"{name_prefix}{suffix}"
    meta_attrs = {"app_label": _app_label, "db_table": table}
    if meta_extra:
        meta_attrs.update(meta_extra)
    Meta = type("Meta", (), meta_attrs)
    attrs = {"__module__": "scenarios.django_suite", "Meta": Meta}
    attrs.update(fields)
    model = type(cls_name, (models.Model,), attrs)
    return model, table


@contextlib.contextmanager
def _schema(*model_classes):
    """Create the schema for the given models, yield, then drop them."""
    with connection.schema_editor() as se:
        created = []
        try:
            for m in model_classes:
                se.create_model(m)
                created.append(m)
            done = True
        except Exception:
            done = False
            raise
        finally:
            if not done:
                for m in reversed(created):
                    with contextlib.suppress(Exception):
                        se.delete_model(m)
    try:
        yield
    finally:
        with connection.schema_editor() as se:
            for m in reversed(model_classes):
                with contextlib.suppress(Exception):
                    se.delete_model(m)


# ===========================================================================
# 1. Field-type matrix: schema generation + roundtrip + null.
# ===========================================================================

# (label, field factory, sample value)
DJ_FIELDS = [
    ("AutoField", lambda: models.IntegerField(), 42),
    ("BigIntegerField", lambda: models.BigIntegerField(), 9000000000000000000),
    ("IntegerField", lambda: models.IntegerField(), 2000000000),
    ("SmallIntegerField", lambda: models.SmallIntegerField(), 1234),
    ("PositiveIntegerField", lambda: models.PositiveIntegerField(), 4000000000),
    ("PositiveSmallIntegerField", lambda: models.PositiveSmallIntegerField(), 5000),
    ("PositiveBigIntegerField", lambda: models.PositiveBigIntegerField(), 10**18),
    ("BooleanField", lambda: models.BooleanField(), True),
    ("FloatField", lambda: models.FloatField(), 3.5),
    ("DecimalField_10_2", lambda: models.DecimalField(max_digits=10, decimal_places=2),
     __import__("decimal").Decimal("12345.67")),
    ("CharField_50", lambda: models.CharField(max_length=50), "hello"),
    ("CharField_255", lambda: models.CharField(max_length=255), "abc def"),
    ("TextField", lambda: models.TextField(), "long text value"),
    ("SlugField", lambda: models.SlugField(), "a-slug-value"),
    ("EmailField", lambda: models.EmailField(), "user@example.com"),
    ("URLField", lambda: models.URLField(), "https://example.com"),
    ("DateField", lambda: models.DateField(), __import__("datetime").date(2026, 6, 23)),
    ("TimeField", lambda: models.TimeField(), __import__("datetime").time(11, 22, 33)),
    ("DateTimeField", lambda: models.DateTimeField(),
     __import__("datetime").datetime(2026, 6, 23, 11, 22, 33)),
    ("DurationField", lambda: models.DurationField(),
     __import__("datetime").timedelta(days=1, hours=2)),
    ("BinaryField", lambda: models.BinaryField(), b"binary data"),
    ("UUIDField", lambda: models.UUIDField(),
     __import__("uuid").UUID("00000000-0000-0000-0000-000000000001")),
    ("JSONField", lambda: models.JSONField(), {"k": 1, "arr": [1, 2, 3]}),
    ("GenericIPAddressField", lambda: models.GenericIPAddressField(), "127.0.0.1"),
]


def _field_create(label, factory):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            pass
        return f"create {label}"
    return run


def _field_roundtrip(label, factory, value):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            obj = model.objects.create(val=value)
            got = model.objects.get(pk=obj.pk)
            if got is None:
                raise BehaviorMismatch(f"{label} roundtrip lost row")
        return f"roundtrip {label} -> {getattr(got, 'val')!r}"
    return run


def _field_null(label, factory):
    def run():
        model, _t = _make_model({"val": factory()})
        # rebuild field as nullable
        model._meta.get_field("val").null = True
        with _schema(model):
            obj = model.objects.create(val=None)
            got = model.objects.get(pk=obj.pk)
            if got.val is not None:
                raise BehaviorMismatch(f"{label} NULL -> {got.val!r}")
        return f"null {label}"
    return run


# ===========================================================================
# 2. CRUD + QuerySet API.
# ===========================================================================

def _seed_model():
    """Common model with g/n/s columns and 4 rows. Returns (model, ctx-manager)."""
    model, _t = _make_model({
        "g": models.IntegerField(),
        "n": models.IntegerField(),
        "s": models.CharField(max_length=50),
    })
    return model


def _qs_scenarios():
    items = []

    def add(name, fn):
        items.append(("queryset", name, fn))

    def populate(model):
        model.objects.bulk_create([
            model(g=1, n=10, s="alpha"),
            model(g=1, n=20, s="beta"),
            model(g=2, n=30, s="gamma"),
            model(g=2, n=40, s="delta"),
        ])

    def filter_basic():
        m = _seed_model()
        with _schema(m):
            populate(m)
            n = m.objects.filter(n__gt=15).count()
            if n != 3:
                raise BehaviorMismatch(f"filter -> {n}")
    add("filter_gt", filter_basic)

    def exclude():
        m = _seed_model()
        with _schema(m):
            populate(m)
            n = m.objects.exclude(g=1).count()
            if n != 2:
                raise BehaviorMismatch(f"exclude -> {n}")
    add("exclude", exclude)

    def q_objects():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.filter(Q(n__lt=15) | Q(n__gt=35)))
    add("q_or", q_objects)

    def f_expression():
        m = _seed_model()
        with _schema(m):
            populate(m)
            m.objects.update(n=F("n") + 100)
            if m.objects.filter(n__gte=110).count() != 4:
                raise BehaviorMismatch("F() update failed")
    add("f_expression_update", f_expression)

    def annotate():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.annotate(doubled=F("n") * 2).values("doubled"))
    add("annotate", annotate)

    def aggregate():
        m = _seed_model()
        with _schema(m):
            populate(m)
            res = m.objects.aggregate(total=Sum("n"), avg=Avg("n"),
                                      mn=Min("n"), mx=Max("n"), cnt=Count("id"))
            if res["total"] != 100:
                raise BehaviorMismatch(f"aggregate sum -> {res['total']}")
    add("aggregate", aggregate)

    def values():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.values("g", "n"))
    add("values", values)

    def values_list():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.values_list("n", flat=True))
    add("values_list_flat", values_list)

    def distinct():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.values_list("g", flat=True).distinct())
    add("distinct", distinct)

    def order_by():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.order_by("-n"))
    add("order_by_desc", order_by)

    def group_by_annotate():
        m = _seed_model()
        with _schema(m):
            populate(m)
            res = list(m.objects.values("g").annotate(total=Sum("n")).order_by("g"))
            if len(res) != 2:
                raise BehaviorMismatch(f"group_by -> {len(res)}")
    add("group_by_annotate", group_by_annotate)

    def case_when():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.annotate(bucket=Case(
                When(n__lt=25, then=Value("lo")),
                default=Value("hi"),
                output_field=models.CharField(),
            )).values("bucket"))
    add("case_when", case_when)

    def subquery_outerref():
        m = _seed_model()
        with _schema(m):
            populate(m)
            sub = m.objects.filter(g=OuterRef("g")).order_by("-n").values("n")[:1]
            list(m.objects.annotate(top=Subquery(sub)).values("id", "top"))
    add("subquery_outerref", subquery_outerref)

    def exists_subquery():
        m = _seed_model()
        with _schema(m):
            populate(m)
            sub = m.objects.filter(g=OuterRef("g"), n__gt=35)
            list(m.objects.annotate(has=Exists(sub)).values("id", "has"))
    add("exists_subquery", exists_subquery)

    def window_function():
        m = _seed_model()
        with _schema(m):
            populate(m)
            list(m.objects.annotate(rn=Window(
                expression=RowNumber(),
                partition_by=[F("g")],
                order_by=F("n").asc(),
            )).values("id", "rn"))
    add("window_row_number", window_function)

    def pagination():
        m = _seed_model()
        with _schema(m):
            m.objects.bulk_create([m(g=1, n=i, s=f"r{i}") for i in range(1, 51)])
            page = list(m.objects.order_by("n")[10:20])
            if len(page) != 10:
                raise BehaviorMismatch(f"pagination slice -> {len(page)}")
    add("pagination_slice", pagination)

    def get_or_create():
        m = _seed_model()
        with _schema(m):
            obj, created = m.objects.get_or_create(g=1, n=10, defaults={"s": "x"})
            if not created:
                raise BehaviorMismatch("get_or_create should create")
            obj2, created2 = m.objects.get_or_create(g=1, n=10, defaults={"s": "x"})
            if created2:
                raise BehaviorMismatch("get_or_create should fetch existing")
    add("get_or_create", get_or_create)

    def update_or_create():
        m = _seed_model()
        with _schema(m):
            m.objects.update_or_create(g=1, n=10, defaults={"s": "v1"})
            m.objects.update_or_create(g=1, n=10, defaults={"s": "v2"})
    add("update_or_create", update_or_create)

    return items


# ===========================================================================
# 3. Relationships.
# ===========================================================================

def _rel_scenarios():
    items = []

    def add(name, fn):
        items.append(("relationship", name, fn))

    def foreign_key():
        parent, _pt = _make_model({"name": models.CharField(max_length=30)},
                                  name_prefix="DjP", table_prefix="djp")
        child, _ct = _make_model(
            {"parent": models.ForeignKey(parent, on_delete=models.CASCADE),
             "label": models.CharField(max_length=30)},
            name_prefix="DjC", table_prefix="djc")
        with _schema(parent, child):
            p = parent.objects.create(name="root")
            child.objects.create(parent=p, label="a")
            child.objects.create(parent=p, label="b")
            n = child.objects.filter(parent=p).count()
            if n != 2:
                raise BehaviorMismatch(f"FK related -> {n}")
    add("foreign_key", foreign_key)

    def select_related():
        parent, _pt = _make_model({"name": models.CharField(max_length=30)},
                                  name_prefix="DjP", table_prefix="djp")
        child, _ct = _make_model(
            {"parent": models.ForeignKey(parent, on_delete=models.CASCADE)},
            name_prefix="DjC", table_prefix="djc")
        with _schema(parent, child):
            p = parent.objects.create(name="root")
            child.objects.create(parent=p)
            for c in child.objects.select_related("parent").all():
                _ = c.parent.name
    add("select_related", select_related)

    def prefetch_related():
        parent, _pt = _make_model({"name": models.CharField(max_length=30)},
                                  name_prefix="DjP", table_prefix="djp")
        child, _ct = _make_model(
            {"parent": models.ForeignKey(parent, on_delete=models.CASCADE,
                                         related_name="kids")},
            name_prefix="DjC", table_prefix="djc")
        with _schema(parent, child):
            p = parent.objects.create(name="root")
            child.objects.create(parent=p)
            child.objects.create(parent=p)
            for par in parent.objects.prefetch_related("kids").all():
                if len(par.kids.all()) != 2:
                    raise BehaviorMismatch("prefetch count mismatch")
    add("prefetch_related", prefetch_related)

    def one_to_one():
        parent, _pt = _make_model({"name": models.CharField(max_length=30)},
                                  name_prefix="DjP", table_prefix="djp")
        prof, _ct = _make_model(
            {"user": models.OneToOneField(parent, on_delete=models.CASCADE),
             "bio": models.CharField(max_length=30)},
            name_prefix="DjProf", table_prefix="djprof")
        with _schema(parent, prof):
            p = parent.objects.create(name="root")
            prof.objects.create(user=p, bio="hi")
            got = prof.objects.get(user=p)
            if got.bio != "hi":
                raise BehaviorMismatch("one-to-one mismatch")
    add("one_to_one", one_to_one)

    def many_to_many():
        a, _at = _make_model({"name": models.CharField(max_length=30)},
                             name_prefix="DjA", table_prefix="dja")
        b, _bt = _make_model(
            {"name": models.CharField(max_length=30),
             "tags": models.ManyToManyField(a)},
            name_prefix="DjB", table_prefix="djb")
        # create_model(b) auto-creates the m2m through table, so don't list it
        # again (that would double-create -> 1050). Drop it explicitly after.
        through = b._meta.get_field("tags").remote_field.through
        try:
            with _schema(a, b):
                a1 = a.objects.create(name="t1")
                a2 = a.objects.create(name="t2")
                obj = b.objects.create(name="post")
                obj.tags.add(a1, a2)
                if obj.tags.count() != 2:
                    raise BehaviorMismatch(f"m2m -> {obj.tags.count()}")
        finally:
            with connection.schema_editor() as se:
                with contextlib.suppress(Exception):
                    se.delete_model(through)
        return "m2m ok"
    add("many_to_many", many_to_many)

    def self_fk():
        node, _t = _make_model(
            {"name": models.CharField(max_length=30),
             "parent": models.ForeignKey("self", null=True,
                                         on_delete=models.CASCADE,
                                         related_name="children")},
            name_prefix="DjNode", table_prefix="djnode")
        with _schema(node):
            root = node.objects.create(name="root")
            node.objects.create(name="c1", parent=root)
            node.objects.create(name="c2", parent=root)
            if root.children.count() != 2:
                raise BehaviorMismatch("self-fk children mismatch")
    add("self_referential_fk", self_fk)

    return items


# ===========================================================================
# 4. Transactions (atomic + savepoints — expect failure).
# ===========================================================================

def _tx_scenarios():
    items = []

    def add(name, fn):
        items.append(("transaction", name, fn))

    def atomic_commit():
        m = _seed_model()
        with _schema(m):
            with transaction.atomic():
                m.objects.create(g=1, n=1, s="x")
            if m.objects.count() != 1:
                raise BehaviorMismatch("atomic commit lost row")
    add("atomic_commit", atomic_commit)

    def atomic_rollback():
        m = _seed_model()
        with _schema(m):
            try:
                with transaction.atomic():
                    m.objects.create(g=1, n=1, s="x")
                    raise RuntimeError("force rollback")
            except RuntimeError:
                pass
            if m.objects.count() != 0:
                raise BehaviorMismatch("atomic rollback left rows")
    add("atomic_rollback", atomic_rollback)

    def nested_atomic_savepoint():
        # KNOWN C5: inner atomic() -> SAVEPOINT; on rollback Django issues
        # ROLLBACK TO SAVEPOINT, which MatrixOne rejects (20101). MySQL would
        # keep the outer "outer" row (1 row); MatrixOne diverges.
        m = _seed_model()
        with _schema(m):
            try:
                with transaction.atomic():
                    m.objects.create(g=1, n=1, s="outer")
                    # inner atomic creates a savepoint; the RuntimeError makes
                    # Django roll back to it -> the unsupported statement.
                    with contextlib.suppress(RuntimeError):
                        with transaction.atomic():
                            m.objects.create(g=2, n=2, s="inner")
                            raise RuntimeError("rollback inner")
            except Exception:
                # The savepoint-rollback failure may surface here.
                pass
            cnt = m.objects.count()
            if cnt != 1:
                raise BehaviorMismatch(
                    f"nested atomic rollback diverges: {cnt} rows "
                    f"(MySQL would keep 1 'outer' row)")
    add("nested_atomic_savepoint", nested_atomic_savepoint)

    def explicit_savepoint():
        m = _seed_model()
        with _schema(m):
            with transaction.atomic():
                m.objects.create(g=1, n=1, s="x")
                sid = transaction.savepoint()
                m.objects.create(g=2, n=2, s="y")
                transaction.savepoint_rollback(sid)
            cnt = m.objects.count()
            if cnt != 1:
                raise BehaviorMismatch(f"explicit savepoint leaked {cnt}")
    add("explicit_savepoint_rollback", explicit_savepoint)

    return items


# ===========================================================================
# 5. Bulk operations.
# ===========================================================================

def _bulk_scenarios():
    items = []

    def add(name, fn):
        items.append(("bulk", name, fn))

    def bulk_create(size):
        def run():
            m = _seed_model()
            with _schema(m):
                m.objects.bulk_create([m(g=1, n=i, s=f"r{i}") for i in range(size)])
                if m.objects.count() != size:
                    raise BehaviorMismatch(f"bulk_create {size} mismatch")
        return run
    for sz in (1, 10, 100, 1000):
        add(f"bulk_create_{sz}", bulk_create(sz))

    def bulk_update():
        m = _seed_model()
        with _schema(m):
            objs = [m(g=1, n=i, s="x") for i in range(50)]
            m.objects.bulk_create(objs)
            fetched = list(m.objects.all())
            for o in fetched:
                o.n = o.n * 10
            m.objects.bulk_update(fetched, ["n"])
    add("bulk_update", bulk_update)

    return items


# ===========================================================================
# 6. JSONField queries + constraints.
# ===========================================================================

def _json_constraint_scenarios():
    items = []

    def add(cat, name, fn):
        items.append((cat, name, fn))

    def json_roundtrip():
        m, _t = _make_model({"data": models.JSONField()})
        with _schema(m):
            obj = m.objects.create(data={"a": 1, "b": [1, 2, 3]})
            got = m.objects.get(pk=obj.pk)
            if got.data.get("a") != 1:
                raise BehaviorMismatch("json roundtrip mismatch")
    add("json", "json_roundtrip", json_roundtrip)

    def json_key_lookup():
        # KNOWN: JSON lookups need JSON_EXTRACT/JSON functions (gaps)
        m, _t = _make_model({"data": models.JSONField()})
        with _schema(m):
            m.objects.create(data={"a": 1})
            m.objects.create(data={"a": 2})
            n = m.objects.filter(data__a=1).count()
            if n != 1:
                raise BehaviorMismatch(f"json key lookup -> {n}")
    add("json", "json_key_lookup", json_key_lookup)

    def json_contains():
        m, _t = _make_model({"data": models.JSONField()})
        with _schema(m):
            m.objects.create(data={"tags": ["x", "y"]})
            list(m.objects.filter(data__contains={"tags": ["x"]}))
    add("json", "json_contains", json_contains)

    def json_nested_lookup():
        m, _t = _make_model({"data": models.JSONField()})
        with _schema(m):
            m.objects.create(data={"a": {"b": 5}})
            list(m.objects.filter(data__a__b=5))
    add("json", "json_nested_lookup", json_nested_lookup)

    def unique_constraint():
        m, _t = _make_model(
            {"email": models.CharField(max_length=50)},
            meta_extra={"constraints": [
                models.UniqueConstraint(fields=["email"], name="uq_email_%s" % _unique_suffix())]})
        with _schema(m):
            m.objects.create(email="a@x.com")
            try:
                m.objects.create(email="a@x.com")
                raise BehaviorMismatch("unique not enforced")
            except BehaviorMismatch:
                raise
            except Exception:
                pass
    add("constraint", "unique_constraint", unique_constraint)

    def check_constraint():
        # KNOWN C4: CHECK not enforced
        m, _t = _make_model(
            {"age": models.IntegerField()},
            meta_extra={"constraints": [
                models.CheckConstraint(check=Q(age__gte=0),
                                       name="chk_age_%s" % _unique_suffix())]})
        with _schema(m):
            try:
                m.objects.create(age=-5)
                got = m.objects.first()
                if got and got.age == -5:
                    raise BehaviorMismatch("CHECK constraint not enforced (-5 stored)")
            except BehaviorMismatch:
                raise
            except Exception:
                pass  # if it raised a DB error, MatrixOne matched MySQL
    add("constraint", "check_constraint_not_enforced", check_constraint)

    def unique_together():
        m, _t = _make_model(
            {"a": models.IntegerField(), "b": models.IntegerField()},
            meta_extra={"unique_together": (("a", "b"),)})
        with _schema(m):
            m.objects.create(a=1, b=1)
            try:
                m.objects.create(a=1, b=1)
                raise BehaviorMismatch("unique_together not enforced")
            except BehaviorMismatch:
                raise
            except Exception:
                pass
    add("constraint", "unique_together", unique_together)

    def db_index():
        m, _t = _make_model({"name": models.CharField(max_length=50, db_index=True)})
        with _schema(m):
            m.objects.create(name="x")
    add("constraint", "db_index", db_index)

    return items


# ===========================================================================
# 7. Workload scenarios.
# ===========================================================================

def _workload_scenarios():
    items = []

    def add(name, fn):
        items.append(("workload", name, fn))

    def wide_table():
        fields = {f"c{i}": models.IntegerField(default=0) for i in range(100)}
        m, _t = _make_model(fields, name_prefix="DjWide", table_prefix="djwide")
        with _schema(m):
            m.objects.create(**{f"c{i}": i for i in range(100)})
    add("wide_table_100_columns", wide_table)

    def deep_json():
        m, _t = _make_model({"data": models.JSONField()})
        with _schema(m):
            deep = {"a": {"b": {"c": {"d": [1, 2, {"e": "f"}]}}}}
            obj = m.objects.create(data=deep)
            got = m.objects.get(pk=obj.pk)
            if got.data is None:
                raise BehaviorMismatch("deep json null")
    add("deep_json", deep_json)

    def long_text():
        m, _t = _make_model({"body": models.TextField()})
        with _schema(m):
            big = "x" * 100000
            obj = m.objects.create(body=big)
            got = m.objects.get(pk=obj.pk)
            if len(got.body) != 100000:
                raise BehaviorMismatch(f"long text len {len(got.body)}")
    add("long_text_100k", long_text)

    def fetch_10k():
        m = _seed_model()
        with _schema(m):
            for start in range(0, 10000, 1000):
                m.objects.bulk_create(
                    [m(g=1, n=i, s="x") for i in range(start, start + 1000)])
            rows = list(m.objects.all())
            if len(rows) != 10000:
                raise BehaviorMismatch(f"fetch 10k -> {len(rows)}")
    add("fetch_10k_rows", fetch_10k)

    def iterator_streaming():
        m = _seed_model()
        with _schema(m):
            m.objects.bulk_create([m(g=1, n=i, s="x") for i in range(5000)])
            cnt = sum(1 for _ in m.objects.all().iterator(chunk_size=500))
            if cnt != 5000:
                raise BehaviorMismatch(f"iterator -> {cnt}")
    add("iterator_streaming", iterator_streaming)

    def many_statement_tx():
        m = _seed_model()
        with _schema(m):
            with transaction.atomic():
                for i in range(500):
                    m.objects.create(g=1, n=i, s="x")
            if m.objects.count() != 500:
                raise BehaviorMismatch("many-stmt tx count")
    add("transaction_500_statements", many_statement_tx)

    return items


# ===========================================================================
# Generated field x lookup matrix + field x operation matrix.
# ===========================================================================

# Per-field-type queryset lookups: (lookup_name, value-builder from sample)
TEXT_LOOKUPS = ["exact", "iexact", "contains", "icontains", "startswith",
                "istartswith", "endswith", "iendswith", "in", "isnull",
                "regex", "iregex"]
NUM_LOOKUPS = ["exact", "gt", "gte", "lt", "lte", "in", "range", "isnull"]
DATE_LOOKUPS = ["exact", "gt", "lt", "year", "month", "day", "isnull"]

# (label, field factory, sample value, lookup-group)
LOOKUP_FIELDS = [
    ("IntegerField", lambda: models.IntegerField(null=True), 10, "num"),
    ("BigIntegerField", lambda: models.BigIntegerField(null=True), 10, "num"),
    ("SmallIntegerField", lambda: models.SmallIntegerField(null=True), 10, "num"),
    ("PositiveIntegerField", lambda: models.PositiveIntegerField(null=True), 10, "num"),
    ("DecimalField", lambda: models.DecimalField(max_digits=10, decimal_places=2, null=True),
     __import__("decimal").Decimal("10.50"), "num"),
    ("CharField", lambda: models.CharField(max_length=50, null=True), "Hello", "text"),
    ("TextField", lambda: models.TextField(null=True), "Hello World", "text"),
    ("SlugField", lambda: models.SlugField(null=True), "hello-world", "text"),
    ("EmailField", lambda: models.EmailField(null=True), "user@example.com", "text"),
    ("DateField", lambda: models.DateField(null=True),
     __import__("datetime").date(2026, 6, 23), "date"),
    ("DateTimeField", lambda: models.DateTimeField(null=True),
     __import__("datetime").datetime(2026, 6, 23, 1, 2, 3), "date"),
    ("TimeField", lambda: models.TimeField(null=True),
     __import__("datetime").time(11, 22, 33), "num"),
]


# --- Django F-expression arithmetic / annotation matrix. -------------------
def _dj_f_expressions():
    items = []

    def add(name, build, expect=None):
        items.append(("f_expression", name, build, expect))

    add("add", lambda: F("a") + F("b"))
    add("sub", lambda: F("a") - F("b"))
    add("mul", lambda: F("a") * F("b"))
    add("div", lambda: F("a") / F("b"))
    add("mod", lambda: F("a") % F("b"))
    add("add_const", lambda: F("a") + 100)
    add("mul_const", lambda: F("a") * 2)
    add("neg", lambda: -F("a"))
    add("pow", lambda: F("a") ** 2)
    add("nested", lambda: (F("a") + F("b")) * 2)
    add("compare_gt", lambda: F("a") + 5)

    out = []
    for cat, name, build, _exp in items:
        def mk(build=build, name=name):
            def run():
                m, _t = _make_model({"a": models.IntegerField(),
                                     "b": models.IntegerField()})
                with _schema(m):
                    m.objects.create(a=10, b=3)
                    list(m.objects.annotate(r=build()).values("r"))
                return name
            return run
        out.append((cat, name, mk()))
    return out


# --- Django conditional expression matrix (Case/When). ---------------------
def _dj_conditional():
    out = []

    def add(name, fn):
        out.append(("conditional", name, fn))

    def case_numeric():
        m, _t = _make_model({"n": models.IntegerField()})
        with _schema(m):
            m.objects.bulk_create([m(n=5), m(n=15), m(n=25)])
            list(m.objects.annotate(bucket=Case(
                When(n__lt=10, then=Value("lo")),
                When(n__lt=20, then=Value("mid")),
                default=Value("hi"),
                output_field=models.CharField(),
            )).values("bucket"))
        return "case numeric"
    add("case_numeric", case_numeric)

    def conditional_aggregate():
        m, _t = _make_model({"g": models.IntegerField(), "n": models.IntegerField()})
        with _schema(m):
            m.objects.bulk_create([m(g=1, n=5), m(g=1, n=15), m(g=2, n=25)])
            m.objects.aggregate(
                hi=Count("id", filter=Q(n__gte=10)),
                lo=Count("id", filter=Q(n__lt=10)))
        return "conditional aggregate"
    add("conditional_aggregate", conditional_aggregate)

    def when_with_f():
        m, _t = _make_model({"a": models.IntegerField(), "b": models.IntegerField()})
        with _schema(m):
            m.objects.create(a=5, b=10)
            list(m.objects.annotate(bigger=Case(
                When(a__gt=F("b"), then=F("a")),
                default=F("b"),
            )).values("bigger"))
        return "when with F"
    add("when_with_f", when_with_f)

    return out


def _lookup_value(lookup, sample):
    if lookup in ("in",):
        return [sample]
    if lookup == "range":
        return (sample, sample)
    if lookup == "isnull":
        return False
    if lookup in ("year", "month", "day"):
        return getattr(sample, lookup, 1)
    return sample


def _field_lookup(label, factory, sample, lookup):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            model.objects.create(val=sample)
            kwargs = {f"val__{lookup}": _lookup_value(lookup, sample)}
            list(model.objects.filter(**kwargs))
        return f"{label} __{lookup}"
    return run


def _field_orderby(label, factory, sample):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            model.objects.create(val=sample)
            list(model.objects.order_by("val"))
            list(model.objects.order_by("-val"))
        return f"{label} order_by"
    return run


def _field_aggregate(label, factory, sample):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            model.objects.create(val=sample)
            model.objects.aggregate(c=Count("val"), mx=Max("val"), mn=Min("val"))
        return f"{label} aggregate"
    return run


def _field_distinct(label, factory, sample):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            model.objects.create(val=sample)
            model.objects.create(val=sample)
            list(model.objects.values_list("val", flat=True).distinct())
        return f"{label} distinct"
    return run


def _field_index(label, factory):
    def run():
        # rebuild as indexed
        model, _t = _make_model({"val": factory()})
        f = model._meta.get_field("val")
        f.db_index = True
        with _schema(model):
            pass
        return f"{label} db_index"
    return run


def _field_crud(label, factory, value, op):
    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            obj = model.objects.create(val=value)
            if op == "update":
                model.objects.filter(pk=obj.pk).update(val=value)
            elif op == "delete":
                model.objects.filter(pk=obj.pk).delete()
                if model.objects.count() != 0:
                    raise BehaviorMismatch(f"{label} delete left rows")
            elif op == "filter_exact":
                list(model.objects.filter(val=value))
            elif op == "save_modify":
                got = model.objects.get(pk=obj.pk)
                got.val = value
                got.save()
        return f"{label} {op}"
    return run


def _field_agg(label, factory, value, agg_name):
    agg_cls = {"count": Count, "min": Min, "max": Max}[agg_name]

    def run():
        model, _t = _make_model({"val": factory()})
        with _schema(model):
            model.objects.create(val=value)
            model.objects.aggregate(r=agg_cls("val"))
        return f"{label} {agg_name}"
    return run


# --- Django database-function matrix (django.db.models.functions). ---------
def _dj_db_functions():
    from django.db.models import functions as F_
    items = []

    def add(name, build):
        items.append(("db_function", name, build))

    # text functions: applied to a CharField column
    text_funcs = [
        ("Upper", lambda c: F_.Upper(c)),
        ("Lower", lambda c: F_.Lower(c)),
        ("Length", lambda c: F_.Length(c)),
        ("Trim", lambda c: F_.Trim(c)),
        ("LTrim", lambda c: F_.LTrim(c)),
        ("RTrim", lambda c: F_.RTrim(c)),
        ("Concat", lambda c: F_.Concat(c, Value("!"))),
        ("Substr", lambda c: F_.Substr(c, 1, 3)),
        ("Left", lambda c: F_.Left(c, 3)),
        ("Right", lambda c: F_.Right(c, 3)),
        ("LPad", lambda c: F_.LPad(c, 10, Value("*"))),
        ("RPad", lambda c: F_.RPad(c, 10, Value("*"))),
        ("Replace", lambda c: F_.Replace(c, Value("a"), Value("X"))),
        ("Reverse", lambda c: F_.Reverse(c)),
        ("MD5", lambda c: F_.MD5(c)),
        ("SHA1", lambda c: F_.SHA1(c)),
        ("SHA256", lambda c: F_.SHA256(c)),
        ("Ord", lambda c: F_.Ord(c)),
        ("Chr", lambda c: F_.Chr(F_.Length(c))),
        ("StrIndex", lambda c: F_.StrIndex(c, Value("e"))),
    ]
    for fname, build in text_funcs:
        def mk_text(build=build, fname=fname):
            def run():
                m, _t = _make_model({"s": models.CharField(max_length=50)})
                with _schema(m):
                    m.objects.create(s="hello")
                    list(m.objects.annotate(r=build(F("s"))).values("r"))
                return fname
            return run
        add(f"text_{fname}", mk_text())

    # math functions: applied to a FloatField -> use IntegerField to dodge
    # FloatField(double precision) DDL issue but still exercise the function.
    math_funcs = [
        ("Abs", lambda c: F_.Abs(c)),
        ("Ceil", lambda c: F_.Ceil(c)),
        ("Floor", lambda c: F_.Floor(c)),
        ("Round", lambda c: F_.Round(c)),
        ("Sqrt", lambda c: F_.Sqrt(c)),
        ("Power", lambda c: F_.Power(c, 2)),
        ("Mod", lambda c: F_.Mod(c, 3)),
        ("Sign", lambda c: F_.Sign(c)),
        ("Exp", lambda c: F_.Exp(c)),
        ("Ln", lambda c: F_.Ln(c)),
        ("Log", lambda c: F_.Log(c, 2)),
        ("Greatest", lambda c: F_.Greatest(c, Value(5))),
        ("Least", lambda c: F_.Least(c, Value(5))),
    ]
    for fname, build in math_funcs:
        def mk_math(build=build, fname=fname):
            def run():
                m, _t = _make_model({"n": models.IntegerField()})
                with _schema(m):
                    m.objects.create(n=10)
                    list(m.objects.annotate(r=build(F("n"))).values("r"))
                return fname
            return run
        add(f"math_{fname}", mk_math())

    # date functions: applied to a DateTimeField
    date_funcs = [
        ("ExtractYear", lambda c: F_.ExtractYear(c)),
        ("ExtractMonth", lambda c: F_.ExtractMonth(c)),
        ("ExtractDay", lambda c: F_.ExtractDay(c)),
        ("ExtractHour", lambda c: F_.ExtractHour(c)),
        ("ExtractWeekDay", lambda c: F_.ExtractWeekDay(c)),
        ("TruncDate", lambda c: F_.TruncDate(c)),
        ("TruncMonth", lambda c: F_.TruncMonth(c)),
        ("TruncYear", lambda c: F_.TruncYear(c)),
        ("Now", lambda c: F_.Now()),
    ]
    for fname, build in date_funcs:
        def mk_date(build=build, fname=fname):
            def run():
                m, _t = _make_model({"d": models.DateTimeField()})
                with _schema(m):
                    m.objects.create(
                        d=__import__("datetime").datetime(2026, 6, 23, 1, 2, 3))
                    list(m.objects.annotate(r=build(F("d"))).values("r"))
                return fname
            return run
        add(f"date_{fname}", mk_date())

    # null functions
    null_funcs = [
        ("Coalesce", lambda: F_.Coalesce(F("n"), Value(0))),
        ("Greatest2", lambda: F_.Greatest(F("n"), Value(0))),
        ("NullIf", lambda: F_.NullIf(F("n"), Value(0))),
        ("Cast", lambda: F_.Cast(F("n"), models.CharField(max_length=20))),
    ]
    for fname, build in null_funcs:
        def mk_null(build=build, fname=fname):
            def run():
                m, _t = _make_model({"n": models.IntegerField(null=True)})
                with _schema(m):
                    m.objects.create(n=5)
                    list(m.objects.annotate(r=build()).values("r"))
                return fname
            return run
        add(f"null_{fname}", mk_null())

    return items


# ===========================================================================
# Registration.
# ===========================================================================

def register(runner):
    for label, factory, value in DJ_FIELDS:
        runner.add(FW, "field_create", f"{label}:create", _field_create(label, factory))
        runner.add(FW, "field_roundtrip", f"{label}:roundtrip",
                   _field_roundtrip(label, factory, value))
        runner.add(FW, "field_null", f"{label}:null", _field_null(label, factory))

    # generated field x lookup matrix
    for label, factory, sample, group in LOOKUP_FIELDS:
        lookups = {"num": NUM_LOOKUPS, "text": TEXT_LOOKUPS,
                   "date": DATE_LOOKUPS}[group]
        for lk in lookups:
            runner.add(FW, "field_lookup", f"{label}:{lk}",
                       _field_lookup(label, factory, sample, lk))
        runner.add(FW, "field_orderby", f"{label}:orderby",
                   _field_orderby(label, factory, sample))
        runner.add(FW, "field_aggregate", f"{label}:aggregate",
                   _field_aggregate(label, factory, sample))
        runner.add(FW, "field_distinct", f"{label}:distinct",
                   _field_distinct(label, factory, sample))
        runner.add(FW, "field_index", f"{label}:db_index",
                   _field_index(label, factory))

    # generated database-function matrix
    for cat, name, fn in _dj_db_functions():
        runner.add(FW, cat, name, fn)

    # generated F-expression and conditional matrices
    for cat, name, fn in _dj_f_expressions():
        runner.add(FW, cat, name, fn)
    for cat, name, fn in _dj_conditional():
        runner.add(FW, cat, name, fn)

    # generated field CRUD matrix (create/update/delete/filter per field type)
    for label, factory, value in DJ_FIELDS:
        for op in ("update", "delete", "filter_exact", "save_modify"):
            runner.add(FW, f"field_crud_{op}", f"{label}:{op}",
                       _field_crud(label, factory, value, op))

    # generated field x aggregate-function matrix
    for label, factory, value in DJ_FIELDS:
        for agg_name in ("count", "min", "max"):
            runner.add(FW, "field_agg", f"{label}:{agg_name}",
                       _field_agg(label, factory, value, agg_name))

    for cat, name, fn in (
        _qs_scenarios() + _rel_scenarios() + _tx_scenarios()
        + _bulk_scenarios() + _json_constraint_scenarios()
        + _workload_scenarios()
    ):
        runner.add(FW, cat, name, fn)
