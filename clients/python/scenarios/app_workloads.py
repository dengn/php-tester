"""Real-world application workloads + deeper type/semantics/concurrency matrices.

This module drives MatrixOne through the raw PyMySQL wire protocol (no ORM in the
way) but, unlike ``raw_pymysql``, it organises scenarios around *what an
application actually does*: it builds representative schemas for e-commerce,
social graphs, OLAP star schemas, time-series/IoT, financial ledgers, multi-tenant
SaaS, CMS and geo apps, then runs the queries each app would issue. It also adds
the deeper type/semantics grids that the baseline suite did not cover
(full DECIMAL precision/scale, temporal precision + timezone, charset/collation,
three-valued logic, numeric overflow/coercion, ENUM/SET edges, BIT, JSON deep
paths, vectors, full-text, generated columns), plus concurrency & isolation
probes, bulk/streaming at scale, error handling, and the DDL surface
(views, procedures, triggers, partitioning, FK actions, check constraints).

All scenarios live in the ``mo_py_app`` namespace and clean up after themselves.
A small pool of persistent connections keeps the suite fast while staying gentle
on the shared node.
"""

from __future__ import annotations

import contextlib
import decimal
import itertools
import threading

import pymysql

from harness import BehaviorMismatch, SkipScenario, config
from harness import matrices as M
from harness.connections import raw_connect

FW = "app"

# ---------------------------------------------------------------------------
# Connection handling. We keep a tiny pool (default 1 persistent + short-lived
# extras created on demand for concurrency probes) to be gentle on the shared
# node. Each scenario uses autocommit + unique table names so they never collide.
# ---------------------------------------------------------------------------
_conn = None
_conn_lock = threading.Lock()


def _c():
    global _conn
    if _conn is None or not _conn.open:
        _conn = raw_connect(config.DB_APP)
    return _conn


def _exec(sql, args=None):
    try:
        cur = _c().cursor()
        cur.execute(sql, args)
        return cur
    except pymysql.err.InterfaceError:
        # Wire desync: drop the connection so the next scenario starts clean,
        # but re-raise so this scenario is still recorded as a finding.
        global _conn
        with contextlib.suppress(Exception):
            if _conn is not None:
                _conn.close()
        _conn = None
        raise


def _fetchone(sql, args=None):
    return _exec(sql, args).fetchone()


def _fetchall(sql, args=None):
    return _exec(sql, args).fetchall()


def _scalar(sql, args=None):
    row = _fetchone(sql, args)
    return None if row is None else row[0]


def _fresh_conn():
    """A brand-new dedicated connection (caller is responsible for closing)."""
    return raw_connect(config.DB_APP)


@contextlib.contextmanager
def _temp_tables(*names):
    """Drop the given table names on exit (best-effort)."""
    try:
        yield
    finally:
        for n in names:
            with contextlib.suppress(Exception):
                _exec(f"DROP TABLE IF EXISTS `{n}`")


def _names(n, prefix="w"):
    return [M.uname(prefix) for _ in range(n)]


# ===========================================================================
# Generic helpers shared by the workload builders.
# ===========================================================================

def _drop_all(*names):
    for n in names:
        with contextlib.suppress(Exception):
            _exec(f"DROP TABLE IF EXISTS `{n}`")


def _run_write_rollback(sql, args=None):
    """Execute a mutating statement on a dedicated connection, then roll back.

    Used by workload write scenarios so they exercise the write path (and surface
    any MatrixOne incompatibility) without disturbing the shared read fixtures.
    A real DB error still propagates so the scenario is recorded as a finding.
    """
    conn = raw_connect(config.DB_APP)
    conn.autocommit(False)
    try:
        cur = conn.cursor()
        cur.execute(sql, args)
    finally:
        with contextlib.suppress(Exception):
            conn.rollback()
        with contextlib.suppress(Exception):
            conn.close()


# ===========================================================================
# WORKLOAD 1: E-COMMERCE — orders / line-items / inventory / pricing.
# Each scenario builds a fresh mini-schema, seeds it, runs one representative
# query, and tears down. The query text is the variable across scenarios.
# ===========================================================================

# Shared fixtures are built once (lazily on first use) and reused by every
# read query in the same workload. This keeps the run fast and is realistic:
# an application queries a stable schema, it does not recreate it per request.
# Write scenarios that mutate state operate inside their own transaction and
# roll back, leaving the shared fixture untouched.
_FIXTURES: dict[str, dict] = {}
_fixture_lock = threading.Lock()


def _fixture(key, builder):
    with _fixture_lock:
        f = _FIXTURES.get(key)
        if f is None:
            f = builder()
            _FIXTURES[key] = f
        return f


def _build_ecommerce():
    cust, prod, ordr, item, inv = _names(5, "ec")
    _exec(f"CREATE TABLE `{cust}` (id INT PRIMARY KEY, name VARCHAR(64), "
          f"country VARCHAR(2), created DATE)")
    _exec(f"CREATE TABLE `{prod}` (id INT PRIMARY KEY, sku VARCHAR(32), "
          f"name VARCHAR(64), price DECIMAL(10,2), category VARCHAR(32))")
    _exec(f"CREATE TABLE `{inv}` (product_id INT PRIMARY KEY, qty INT, "
          f"warehouse VARCHAR(16))")
    _exec(f"CREATE TABLE `{ordr}` (id INT PRIMARY KEY, customer_id INT, "
          f"status VARCHAR(16), placed DATETIME, total DECIMAL(12,2))")
    _exec(f"CREATE TABLE `{item}` (id INT PRIMARY KEY, order_id INT, "
          f"product_id INT, qty INT, unit_price DECIMAL(10,2))")
    _exec(f"INSERT INTO `{cust}` VALUES "
          "(1,'Alice','US','2025-01-01'),(2,'Bob','GB','2025-02-01'),"
          "(3,'Carol','US','2025-03-01'),(4,'Dan','DE','2025-04-01')")
    _exec(f"INSERT INTO `{prod}` VALUES "
          "(1,'SKU1','Widget',9.99,'tools'),(2,'SKU2','Gadget',19.99,'tools'),"
          "(3,'SKU3','Gizmo',4.50,'toys'),(4,'SKU4','Doohickey',99.00,'toys')")
    _exec(f"INSERT INTO `{inv}` VALUES (1,100,'A'),(2,5,'A'),(3,0,'B'),(4,50,'B')")
    _exec(f"INSERT INTO `{ordr}` VALUES "
          "(1,1,'paid','2025-05-01 10:00:00',39.97),"
          "(2,1,'paid','2025-05-02 11:00:00',99.00),"
          "(3,2,'pending','2025-05-03 12:00:00',19.99),"
          "(4,3,'cancelled','2025-05-04 13:00:00',4.50)")
    _exec(f"INSERT INTO `{item}` VALUES "
          "(1,1,1,2,9.99),(2,1,3,4,4.50),(3,2,4,1,99.00),"
          "(4,3,2,1,19.99),(5,4,3,1,4.50)")
    return dict(cust=cust, prod=prod, ordr=ordr, item=item, inv=inv)


def _ecommerce_query(label, sql_tpl):
    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("ecommerce", _build_ecommerce)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"ecommerce {label}"
    return run


ECOMMERCE_QUERIES = [
    ("order_with_lines",
     "SELECT o.id, o.total, COUNT(li.id) lines FROM `{ordr}` o "
     "JOIN `{item}` li ON li.order_id=o.id GROUP BY o.id, o.total"),
    ("revenue_by_category",
     "SELECT p.category, SUM(li.qty*li.unit_price) rev FROM `{item}` li "
     "JOIN `{prod}` p ON p.id=li.product_id GROUP BY p.category ORDER BY rev DESC"),
    ("top_customers_by_spend",
     "SELECT c.name, SUM(o.total) spend FROM `{ordr}` o "
     "JOIN `{cust}` c ON c.id=o.customer_id WHERE o.status='paid' "
     "GROUP BY c.name ORDER BY spend DESC LIMIT 10"),
    ("low_stock_alert",
     "SELECT p.sku, i.qty FROM `{inv}` i JOIN `{prod}` p ON p.id=i.product_id "
     "WHERE i.qty < 10 ORDER BY i.qty"),
    ("out_of_stock",
     "SELECT p.sku FROM `{prod}` p JOIN `{inv}` i ON i.product_id=p.id "
     "WHERE i.qty = 0"),
    ("avg_order_value",
     "SELECT AVG(total) aov FROM `{ordr}` WHERE status='paid'"),
    ("orders_per_status",
     "SELECT status, COUNT(*) n FROM `{ordr}` GROUP BY status"),
    ("customer_order_count",
     "SELECT c.name, COUNT(o.id) n FROM `{cust}` c "
     "LEFT JOIN `{ordr}` o ON o.customer_id=c.id GROUP BY c.name"),
    ("products_never_ordered",
     "SELECT p.sku FROM `{prod}` p WHERE NOT EXISTS "
     "(SELECT 1 FROM `{item}` li WHERE li.product_id=p.id)"),
    ("repeat_customers",
     "SELECT customer_id FROM `{ordr}` GROUP BY customer_id HAVING COUNT(*)>1"),
    ("daily_revenue",
     "SELECT DATE(placed) d, SUM(total) rev FROM `{ordr}` "
     "WHERE status='paid' GROUP BY DATE(placed) ORDER BY d"),
    ("basket_size_distribution",
     "SELECT cnt, COUNT(*) FROM (SELECT order_id, SUM(qty) cnt FROM `{item}` "
     "GROUP BY order_id) s GROUP BY cnt"),
    ("inventory_value",
     "SELECT SUM(i.qty*p.price) val FROM `{inv}` i "
     "JOIN `{prod}` p ON p.id=i.product_id"),
    ("price_with_discount",
     "SELECT sku, price, ROUND(price*0.9,2) sale FROM `{prod}`"),
    ("orders_last_window",
     "SELECT * FROM `{ordr}` WHERE placed >= '2025-05-02 00:00:00' "
     "ORDER BY placed DESC"),
    ("category_avg_price",
     "SELECT category, AVG(price) FROM `{prod}` GROUP BY category"),
    ("multi_item_orders",
     "SELECT order_id FROM `{item}` GROUP BY order_id HAVING COUNT(*) >= 2"),
    ("running_order_total",
     "SELECT id, total, SUM(total) OVER (ORDER BY placed) running "
     "FROM `{ordr}` WHERE status='paid'"),
    ("rank_products_by_qty",
     "SELECT product_id, SUM(qty) q, RANK() OVER (ORDER BY SUM(qty) DESC) r "
     "FROM `{item}` GROUP BY product_id"),
    ("customer_ltv_window",
     "SELECT customer_id, total, "
     "AVG(total) OVER (PARTITION BY customer_id) avg_cust FROM `{ordr}`"),
    ("country_revenue",
     "SELECT c.country, SUM(o.total) rev FROM `{ordr}` o "
     "JOIN `{cust}` c ON c.id=o.customer_id GROUP BY c.country"),
    ("cancelled_rate",
     "SELECT SUM(status='cancelled')/COUNT(*) rate FROM `{ordr}`"),
    ("upsell_pairs",
     "SELECT a.product_id p1, b.product_id p2 FROM `{item}` a "
     "JOIN `{item}` b ON a.order_id=b.order_id AND a.product_id<b.product_id"),
    ("reorder_point",
     "SELECT p.sku FROM `{prod}` p JOIN `{inv}` i ON i.product_id=p.id "
     "WHERE i.qty < (SELECT AVG(qty) FROM `{inv}`)"),
    ("monthly_cohort",
     "SELECT DATE_FORMAT(created,'%Y-%m') m, COUNT(*) FROM `{cust}` "
     "GROUP BY DATE_FORMAT(created,'%Y-%m')"),
    ("order_item_join_3way",
     "SELECT o.id, c.name, p.name FROM `{ordr}` o "
     "JOIN `{cust}` c ON c.id=o.customer_id "
     "JOIN `{item}` li ON li.order_id=o.id "
     "JOIN `{prod}` p ON p.id=li.product_id"),
    ("paid_pct_of_total",
     "SELECT (SELECT SUM(total) FROM `{ordr}` WHERE status='paid') / "
     "(SELECT SUM(total) FROM `{ordr}`) pct"),
    ("products_above_avg_price",
     "SELECT sku FROM `{prod}` WHERE price > (SELECT AVG(price) FROM `{prod}`)"),
    ("update_inventory_after_order",
     "UPDATE `{inv}` SET qty = qty - 1 WHERE product_id = 1"),
    ("ntile_price_buckets",
     "SELECT sku, NTILE(3) OVER (ORDER BY price) bucket FROM `{prod}`"),
]


# ===========================================================================
# WORKLOAD 2: SOCIAL GRAPH — users / follows / posts / likes.
# ===========================================================================

def _build_social():
    usr, fol, post, like = _names(4, "soc")
    _exec(f"CREATE TABLE `{usr}` (id INT PRIMARY KEY, handle VARCHAR(32), "
          f"joined DATE, bio VARCHAR(255))")
    _exec(f"CREATE TABLE `{fol}` (follower_id INT, followee_id INT, "
          f"since DATETIME, PRIMARY KEY (follower_id, followee_id))")
    _exec(f"CREATE TABLE `{post}` (id INT PRIMARY KEY, author_id INT, "
          f"body VARCHAR(280), created DATETIME, reply_to INT)")
    _exec(f"CREATE TABLE `{like}` (user_id INT, post_id INT, "
          f"liked DATETIME, PRIMARY KEY (user_id, post_id))")
    _exec(f"INSERT INTO `{usr}` VALUES "
          "(1,'alice','2024-01-01','hi'),(2,'bob','2024-02-01','yo'),"
          "(3,'carol','2024-03-01','hey'),(4,'dan','2024-04-01','sup'),"
          "(5,'eve','2024-05-01','hello')")
    _exec(f"INSERT INTO `{fol}` VALUES "
          "(1,2,'2024-06-01 00:00:00'),(1,3,'2024-06-02 00:00:00'),"
          "(2,3,'2024-06-03 00:00:00'),(3,1,'2024-06-04 00:00:00'),"
          "(4,1,'2024-06-05 00:00:00'),(5,1,'2024-06-06 00:00:00'),"
          "(2,4,'2024-06-07 00:00:00')")
    _exec(f"INSERT INTO `{post}` VALUES "
          "(1,1,'first post','2024-07-01 10:00:00',NULL),"
          "(2,2,'second','2024-07-01 11:00:00',NULL),"
          "(3,1,'reply','2024-07-01 12:00:00',2),"
          "(4,3,'third','2024-07-02 10:00:00',NULL),"
          "(5,1,'fourth','2024-07-03 10:00:00',NULL)")
    _exec(f"INSERT INTO `{like}` VALUES "
          "(2,1,'2024-07-01 10:05:00'),(3,1,'2024-07-01 10:06:00'),"
          "(1,2,'2024-07-01 11:05:00'),(4,1,'2024-07-01 10:07:00'),"
          "(5,4,'2024-07-02 10:05:00')")
    return dict(usr=usr, fol=fol, post=post, like=like)


def _social_query(label, sql_tpl):
    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("social", _build_social)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"social {label}"
    return run


SOCIAL_QUERIES = [
    ("followers_of_user",
     "SELECT u.handle FROM `{fol}` f JOIN `{usr}` u ON u.id=f.follower_id "
     "WHERE f.followee_id=1"),
    ("following_of_user",
     "SELECT u.handle FROM `{fol}` f JOIN `{usr}` u ON u.id=f.followee_id "
     "WHERE f.follower_id=1"),
    ("follower_counts",
     "SELECT followee_id, COUNT(*) c FROM `{fol}` GROUP BY followee_id "
     "ORDER BY c DESC"),
    ("mutual_follows",
     "SELECT a.follower_id, a.followee_id FROM `{fol}` a "
     "JOIN `{fol}` b ON a.follower_id=b.followee_id AND a.followee_id=b.follower_id"),
    ("friends_of_friends",
     "SELECT DISTINCT f2.followee_id FROM `{fol}` f1 "
     "JOIN `{fol}` f2 ON f1.followee_id=f2.follower_id "
     "WHERE f1.follower_id=1 AND f2.followee_id<>1"),
    ("recursive_follower_tree",
     "WITH RECURSIVE tree AS ("
     "SELECT follower_id, followee_id, 1 lvl FROM `{fol}` WHERE followee_id=1 "
     "UNION ALL SELECT f.follower_id, f.followee_id, t.lvl+1 FROM `{fol}` f "
     "JOIN tree t ON f.followee_id=t.follower_id WHERE t.lvl<3) "
     "SELECT DISTINCT follower_id FROM tree"),
    ("post_like_counts",
     "SELECT p.id, COUNT(l.user_id) likes FROM `{post}` p "
     "LEFT JOIN `{like}` l ON l.post_id=p.id GROUP BY p.id ORDER BY likes DESC"),
    ("most_liked_post",
     "SELECT post_id, COUNT(*) c FROM `{like}` GROUP BY post_id "
     "ORDER BY c DESC LIMIT 1"),
    ("user_timeline",
     "SELECT p.body, p.created FROM `{post}` p "
     "WHERE p.author_id IN (SELECT followee_id FROM `{fol}` WHERE follower_id=1) "
     "ORDER BY p.created DESC"),
    ("reply_threads",
     "SELECT p.id, r.id reply FROM `{post}` p "
     "JOIN `{post}` r ON r.reply_to=p.id"),
    ("posts_per_user",
     "SELECT author_id, COUNT(*) FROM `{post}` GROUP BY author_id"),
    ("engagement_rate",
     "SELECT p.author_id, COUNT(DISTINCT l.user_id)/COUNT(DISTINCT p.id) er "
     "FROM `{post}` p LEFT JOIN `{like}` l ON l.post_id=p.id GROUP BY p.author_id"),
    ("inactive_users",
     "SELECT u.handle FROM `{usr}` u WHERE NOT EXISTS "
     "(SELECT 1 FROM `{post}` p WHERE p.author_id=u.id)"),
    ("influencers",
     "SELECT followee_id FROM `{fol}` GROUP BY followee_id HAVING COUNT(*) >= 3"),
    ("liked_by_followers",
     "SELECT p.id FROM `{post}` p JOIN `{like}` l ON l.post_id=p.id "
     "JOIN `{fol}` f ON f.follower_id=l.user_id AND f.followee_id=p.author_id"),
    ("reciprocal_not_followed",
     "SELECT u.handle FROM `{usr}` u WHERE u.id NOT IN "
     "(SELECT followee_id FROM `{fol}` WHERE follower_id=1) AND u.id<>1"),
    ("new_users_by_month",
     "SELECT DATE_FORMAT(joined,'%Y-%m') m, COUNT(*) FROM `{usr}` "
     "GROUP BY DATE_FORMAT(joined,'%Y-%m')"),
    ("post_rank_by_author",
     "SELECT id, author_id, ROW_NUMBER() OVER "
     "(PARTITION BY author_id ORDER BY created) rn FROM `{post}`"),
    ("follower_growth_window",
     "SELECT followee_id, since, COUNT(*) OVER "
     "(PARTITION BY followee_id ORDER BY since) cumulative FROM `{fol}`"),
    ("top_posters",
     "SELECT author_id, COUNT(*) n FROM `{post}` GROUP BY author_id "
     "ORDER BY n DESC LIMIT 3"),
    ("self_likes",
     "SELECT l.user_id FROM `{like}` l JOIN `{post}` p ON p.id=l.post_id "
     "WHERE p.author_id=l.user_id"),
    ("follow_recommendations",
     "SELECT f2.followee_id, COUNT(*) score FROM `{fol}` f1 "
     "JOIN `{fol}` f2 ON f1.followee_id=f2.follower_id "
     "WHERE f1.follower_id=2 AND f2.followee_id<>2 "
     "AND f2.followee_id NOT IN (SELECT followee_id FROM `{fol}` WHERE follower_id=2) "
     "GROUP BY f2.followee_id ORDER BY score DESC"),
    ("orphan_replies",
     "SELECT p.id FROM `{post}` p WHERE p.reply_to IS NOT NULL "
     "AND NOT EXISTS (SELECT 1 FROM `{post}` q WHERE q.id=p.reply_to)"),
    ("two_hop_reach",
     "WITH RECURSIVE reach AS ("
     "SELECT followee_id, 1 d FROM `{fol}` WHERE follower_id=1 "
     "UNION ALL SELECT f.followee_id, r.d+1 FROM `{fol}` f "
     "JOIN reach r ON f.follower_id=r.followee_id WHERE r.d<2) "
     "SELECT COUNT(DISTINCT followee_id) FROM reach"),
    ("create_follow",
     "INSERT INTO `{fol}` VALUES (3,2,'2024-08-01 00:00:00')"),
]


# ===========================================================================
# WORKLOAD 3: ANALYTICS / OLAP — star schema, rollups, GROUPING SETS/ROLLUP/CUBE.
# ===========================================================================

def _build_olap():
    fact, dim_d, dim_p, dim_c = _names(4, "ol")
    _exec(f"CREATE TABLE `{dim_d}` (date_id INT PRIMARY KEY, d DATE, "
          f"year INT, quarter INT, month INT, dow INT)")
    _exec(f"CREATE TABLE `{dim_p}` (product_id INT PRIMARY KEY, name VARCHAR(32), "
          f"category VARCHAR(16), brand VARCHAR(16))")
    _exec(f"CREATE TABLE `{dim_c}` (customer_id INT PRIMARY KEY, region VARCHAR(8), "
          f"segment VARCHAR(16))")
    _exec(f"CREATE TABLE `{fact}` (id INT PRIMARY KEY, date_id INT, product_id INT, "
          f"customer_id INT, qty INT, revenue DECIMAL(12,2), cost DECIMAL(12,2))")
    _exec(f"INSERT INTO `{dim_d}` VALUES "
          "(1,'2025-01-15',2025,1,1,3),(2,'2025-02-15',2025,1,2,6),"
          "(3,'2025-04-15',2025,2,4,2),(4,'2025-07-15',2025,3,7,2)")
    _exec(f"INSERT INTO `{dim_p}` VALUES "
          "(1,'A','tools','Acme'),(2,'B','tools','Beta'),"
          "(3,'C','toys','Acme'),(4,'D','toys','Gamma')")
    _exec(f"INSERT INTO `{dim_c}` VALUES "
          "(1,'NA','enterprise'),(2,'EU','smb'),(3,'NA','smb'),(4,'APAC','enterprise')")
    rows = []
    rid = 1
    for d in (1, 2, 3, 4):
        for p in (1, 2, 3, 4):
            for c in (1, 2, 3, 4):
                if (d + p + c) % 3 == 0:
                    rev = (d * 100 + p * 10 + c) + 0.50
                    rows.append(f"({rid},{d},{p},{c},{(d+p) % 5 + 1},{rev},{rev*0.6:.2f})")
                    rid += 1
    _exec(f"INSERT INTO `{fact}` VALUES " + ",".join(rows))
    return dict(fact=fact, dim_d=dim_d, dim_p=dim_p, dim_c=dim_c)


def _olap_query(label, sql_tpl):
    def run():
        t = _fixture("olap", _build_olap)
        _fetchall(sql_tpl.format(**t))
        return f"olap {label}"
    return run


OLAP_QUERIES = [
    ("revenue_by_category_brand",
     "SELECT p.category, p.brand, SUM(f.revenue) rev FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY p.category, p.brand ORDER BY rev DESC"),
    ("rollup_category",
     "SELECT p.category, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY p.category WITH ROLLUP"),
    ("rollup_two_dims",
     "SELECT p.category, p.brand, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY p.category, p.brand WITH ROLLUP"),
    ("rollup_region_segment",
     "SELECT c.region, c.segment, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id "
     "GROUP BY c.region, c.segment WITH ROLLUP"),
    ("grouping_sets_basic",
     "SELECT p.category, c.region, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id "
     "GROUP BY GROUPING SETS ((p.category),(c.region),())"),
    ("cube_two_dims",
     "SELECT p.category, c.region, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id "
     "GROUP BY CUBE(p.category, c.region)"),
    ("grouping_function",
     "SELECT p.category, GROUPING(p.category) g, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY p.category WITH ROLLUP"),
    ("quarterly_revenue",
     "SELECT d.quarter, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_d}` d ON d.date_id=f.date_id GROUP BY d.quarter ORDER BY d.quarter"),
    ("yoy_window",
     "SELECT d.month, SUM(f.revenue) rev, "
     "LAG(SUM(f.revenue)) OVER (ORDER BY d.month) prev FROM `{fact}` f "
     "JOIN `{dim_d}` d ON d.date_id=f.date_id GROUP BY d.month"),
    ("margin_analysis",
     "SELECT p.category, SUM(f.revenue-f.cost) margin, "
     "SUM(f.revenue-f.cost)/SUM(f.revenue) margin_pct FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id GROUP BY p.category"),
    ("top_n_per_region",
     "SELECT * FROM (SELECT c.region, p.name, SUM(f.revenue) rev, "
     "RANK() OVER (PARTITION BY c.region ORDER BY SUM(f.revenue) DESC) rk "
     "FROM `{fact}` f JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id "
     "GROUP BY c.region, p.name) s WHERE rk<=2"),
    ("revenue_share_window",
     "SELECT p.category, SUM(f.revenue) rev, "
     "SUM(f.revenue)/SUM(SUM(f.revenue)) OVER () share FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id GROUP BY p.category"),
    ("running_total_by_month",
     "SELECT d.month, SUM(f.revenue) rev, "
     "SUM(SUM(f.revenue)) OVER (ORDER BY d.month) running FROM `{fact}` f "
     "JOIN `{dim_d}` d ON d.date_id=f.date_id GROUP BY d.month"),
    ("segment_pivot",
     "SELECT p.category, "
     "SUM(CASE WHEN c.segment='enterprise' THEN f.revenue ELSE 0 END) ent, "
     "SUM(CASE WHEN c.segment='smb' THEN f.revenue ELSE 0 END) smb "
     "FROM `{fact}` f JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id GROUP BY p.category"),
    ("avg_basket_per_segment",
     "SELECT c.segment, AVG(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id GROUP BY c.segment"),
    ("distinct_customers_per_product",
     "SELECT product_id, COUNT(DISTINCT customer_id) FROM `{fact}` GROUP BY product_id"),
    ("percentile_revenue",
     "SELECT product_id, SUM(revenue) rev, "
     "NTILE(4) OVER (ORDER BY SUM(revenue)) quartile FROM `{fact}` GROUP BY product_id"),
    ("cumulative_dist",
     "SELECT product_id, SUM(revenue) rev, "
     "CUME_DIST() OVER (ORDER BY SUM(revenue)) cd FROM `{fact}` GROUP BY product_id"),
    ("region_brand_matrix",
     "SELECT c.region, p.brand, COUNT(*) FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "JOIN `{dim_c}` c ON c.customer_id=f.customer_id "
     "GROUP BY c.region, p.brand"),
    ("having_high_margin",
     "SELECT p.category, SUM(f.revenue-f.cost) m FROM `{fact}` f "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY p.category HAVING SUM(f.revenue-f.cost) > 100"),
    ("dense_rank_products",
     "SELECT product_id, SUM(qty) q, DENSE_RANK() OVER (ORDER BY SUM(qty) DESC) dr "
     "FROM `{fact}` GROUP BY product_id"),
    ("median_approx",
     "SELECT product_id, AVG(revenue) FROM `{fact}` GROUP BY product_id "
     "ORDER BY product_id"),
    ("multi_grouping_sets",
     "SELECT d.year, d.quarter, p.category, SUM(f.revenue) FROM `{fact}` f "
     "JOIN `{dim_d}` d ON d.date_id=f.date_id "
     "JOIN `{dim_p}` p ON p.product_id=f.product_id "
     "GROUP BY GROUPING SETS ((d.year,d.quarter),(p.category),())"),
    ("first_last_value",
     "SELECT d.month, FIRST_VALUE(SUM(f.revenue)) OVER (ORDER BY d.month) f1, "
     "LAST_VALUE(SUM(f.revenue)) OVER (ORDER BY d.month) l1 FROM `{fact}` f "
     "JOIN `{dim_d}` d ON d.date_id=f.date_id GROUP BY d.month"),
]


# ===========================================================================
# WORKLOAD 4: TIME-SERIES / IoT — high-volume inserts, time-bucketed aggregates.
# ===========================================================================

def _build_timeseries():
    readings, = _names(1, "ts")
    _exec(f"CREATE TABLE `{readings}` (id INT PRIMARY KEY AUTO_INCREMENT, "
          f"device_id INT, ts DATETIME, metric VARCHAR(16), value DOUBLE)")
    rows = []
    rid = 1
    import datetime as _dt
    base = _dt.datetime(2025, 6, 1, 0, 0, 0)
    for dev in (1, 2, 3):
        for h in range(24):
            ts = base + _dt.timedelta(hours=h)
            # introduce gaps: skip some hours for device 2
            if dev == 2 and h in (3, 4, 5):
                continue
            val = (dev * 10) + (h % 7) + 0.5
            rows.append(f"({rid},{dev},'{ts:%Y-%m-%d %H:%M:%S}','temp',{val})")
            rid += 1
    _exec(f"INSERT INTO `{readings}` VALUES " + ",".join(rows))
    return dict(readings=readings)


def _timeseries_query(label, sql_tpl):
    if sql_tpl is None:
        # High-volume insert path: 1000-row executemany on a throwaway table.
        def run_batch():
            t, = _names(1, "tsb")
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, device_id INT, "
                  f"ts DATETIME, value DOUBLE)")
            try:
                import datetime as _dt
                base = _dt.datetime(2025, 6, 1)
                rows = [(i, i % 10, base + _dt.timedelta(seconds=i), i * 0.5)
                        for i in range(1000)]
                cur = _c().cursor()
                cur.executemany(
                    f"INSERT INTO `{t}` (id, device_id, ts, value) VALUES (%s,%s,%s,%s)",
                    rows)
                n = _scalar(f"SELECT COUNT(*) FROM `{t}`")
                if n != 1000:
                    raise BehaviorMismatch(f"batch insert: expected 1000, got {n}")
            finally:
                _drop_all(t)
            return "timeseries batch_insert_1000"
        return run_batch

    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("timeseries", _build_timeseries)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"timeseries {label}"
    return run


TIMESERIES_QUERIES = [
    ("hourly_avg",
     "SELECT device_id, DATE_FORMAT(ts,'%Y-%m-%d %H:00:00') bucket, AVG(value) "
     "FROM `{readings}` GROUP BY device_id, DATE_FORMAT(ts,'%Y-%m-%d %H:00:00')"),
    ("daily_minmax",
     "SELECT device_id, DATE(ts) d, MIN(value) mn, MAX(value) mx "
     "FROM `{readings}` GROUP BY device_id, DATE(ts)"),
    ("latest_per_device",
     "SELECT device_id, value FROM `{readings}` r WHERE ts = "
     "(SELECT MAX(ts) FROM `{readings}` r2 WHERE r2.device_id=r.device_id)"),
    ("moving_avg_window",
     "SELECT device_id, ts, value, AVG(value) OVER "
     "(PARTITION BY device_id ORDER BY ts ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) ma "
     "FROM `{readings}`"),
    ("delta_from_previous",
     "SELECT device_id, ts, value - LAG(value) OVER "
     "(PARTITION BY device_id ORDER BY ts) delta FROM `{readings}`"),
    ("gap_detection",
     "SELECT device_id, ts, "
     "TIMESTAMPDIFF(HOUR, LAG(ts) OVER (PARTITION BY device_id ORDER BY ts), ts) gap "
     "FROM `{readings}`"),
    ("readings_per_hour_count",
     "SELECT HOUR(ts) h, COUNT(*) FROM `{readings}` GROUP BY HOUR(ts) ORDER BY h"),
    ("device_summary",
     "SELECT device_id, COUNT(*) n, AVG(value) a, STDDEV(value) sd "
     "FROM `{readings}` GROUP BY device_id"),
    ("downsample_4h",
     "SELECT device_id, FLOOR(HOUR(ts)/4) bucket, AVG(value) "
     "FROM `{readings}` GROUP BY device_id, FLOOR(HOUR(ts)/4)"),
    ("anomaly_above_threshold",
     "SELECT * FROM `{readings}` WHERE value > 25 ORDER BY ts"),
    ("first_reading_each_device",
     "SELECT device_id, MIN(ts) FROM `{readings}` GROUP BY device_id"),
    ("rolling_sum",
     "SELECT device_id, ts, SUM(value) OVER "
     "(PARTITION BY device_id ORDER BY ts) cumsum FROM `{readings}`"),
    ("hour_over_hour_pct",
     "SELECT device_id, ts, "
     "(value - LAG(value) OVER (PARTITION BY device_id ORDER BY ts)) / "
     "LAG(value) OVER (PARTITION BY device_id ORDER BY ts) pct FROM `{readings}`"),
    ("active_devices_window",
     "SELECT DATE_FORMAT(ts,'%Y-%m-%d %H:00:00') b, COUNT(DISTINCT device_id) "
     "FROM `{readings}` GROUP BY DATE_FORMAT(ts,'%Y-%m-%d %H:00:00')"),
    ("p95_window",
     "SELECT device_id, value, "
     "PERCENT_RANK() OVER (PARTITION BY device_id ORDER BY value) pr "
     "FROM `{readings}`"),
    ("batch_insert_1000",
     None),  # special: handled in factory
    ("count_total", "SELECT COUNT(*) FROM `{readings}`"),
    ("metric_distribution",
     "SELECT ROUND(value) bucket, COUNT(*) FROM `{readings}` GROUP BY ROUND(value)"),
    ("device_correlation",
     "SELECT a.ts, a.value v1, b.value v2 FROM `{readings}` a "
     "JOIN `{readings}` b ON a.ts=b.ts AND a.device_id=1 AND b.device_id=3"),
    ("interpolation_candidates",
     "SELECT device_id, ts FROM `{readings}` WHERE device_id=2 ORDER BY ts"),
]


# ===========================================================================
# WORKLOAD 5: FINANCIAL LEDGER — decimal money, double-entry, running balances.
# ===========================================================================

def _build_ledger():
    acct, entry = _names(2, "led")
    _exec(f"CREATE TABLE `{acct}` (id INT PRIMARY KEY, name VARCHAR(32), "
          f"type VARCHAR(16), currency CHAR(3))")
    _exec(f"CREATE TABLE `{entry}` (id INT PRIMARY KEY, txn_id INT, account_id INT, "
          f"debit DECIMAL(18,4), credit DECIMAL(18,4), posted DATETIME, memo VARCHAR(64))")
    _exec(f"INSERT INTO `{acct}` VALUES "
          "(1,'Cash','asset','USD'),(2,'Revenue','income','USD'),"
          "(3,'Expenses','expense','USD'),(4,'AP','liability','USD')")
    _exec(f"INSERT INTO `{entry}` VALUES "
          "(1,1,1,1000.0000,0,'2025-01-01 09:00:00','sale'),"
          "(2,1,2,0,1000.0000,'2025-01-01 09:00:00','sale'),"
          "(3,2,3,250.5000,0,'2025-01-02 10:00:00','rent'),"
          "(4,2,1,0,250.5000,'2025-01-02 10:00:00','rent'),"
          "(5,3,1,500.2500,0,'2025-01-03 11:00:00','deposit'),"
          "(6,3,2,0,500.2500,'2025-01-03 11:00:00','deposit')")
    return dict(acct=acct, entry=entry)


def _ledger_query(label, sql_tpl):
    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("ledger", _build_ledger)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"ledger {label}"
    return run


LEDGER_QUERIES = [
    ("account_balance",
     "SELECT a.name, SUM(e.debit)-SUM(e.credit) bal FROM `{entry}` e "
     "JOIN `{acct}` a ON a.id=e.account_id GROUP BY a.name"),
    ("trial_balance_check",
     "SELECT SUM(debit) d, SUM(credit) c, SUM(debit)-SUM(credit) diff FROM `{entry}`"),
    ("double_entry_validation",
     "SELECT txn_id, SUM(debit)-SUM(credit) imbalance FROM `{entry}` "
     "GROUP BY txn_id HAVING SUM(debit)<>SUM(credit)"),
    ("running_balance_cash",
     "SELECT posted, debit, credit, "
     "SUM(debit-credit) OVER (ORDER BY posted, id) running FROM `{entry}` "
     "WHERE account_id=1 ORDER BY posted"),
    ("decimal_precision_sum",
     "SELECT SUM(debit*1.0000) FROM `{entry}`"),
    ("money_rounding",
     "SELECT id, ROUND(debit*0.0825,2) tax FROM `{entry}` WHERE debit>0"),
    ("decimal_division",
     "SELECT SUM(debit)/COUNT(*) avg_debit FROM `{entry}` WHERE debit>0"),
    ("monthly_pnl",
     "SELECT DATE_FORMAT(posted,'%Y-%m') m, "
     "SUM(CASE WHEN a.type='income' THEN credit ELSE 0 END) "
     "- SUM(CASE WHEN a.type='expense' THEN debit ELSE 0 END) pnl "
     "FROM `{entry}` e JOIN `{acct}` a ON a.id=e.account_id "
     "GROUP BY DATE_FORMAT(posted,'%Y-%m')"),
    ("ledger_by_account_type",
     "SELECT a.type, SUM(e.debit) d, SUM(e.credit) c FROM `{entry}` e "
     "JOIN `{acct}` a ON a.id=e.account_id GROUP BY a.type"),
    ("largest_transactions",
     "SELECT txn_id, SUM(debit) total FROM `{entry}` GROUP BY txn_id "
     "ORDER BY total DESC LIMIT 5"),
    ("entries_per_txn",
     "SELECT txn_id, COUNT(*) FROM `{entry}` GROUP BY txn_id"),
    ("negative_balance_accounts",
     "SELECT account_id FROM `{entry}` GROUP BY account_id "
     "HAVING SUM(debit)-SUM(credit) < 0"),
    ("decimal_overflow_guard",
     "SELECT CAST(99999999999999.9999 AS DECIMAL(18,4)) + "
     "CAST(0.0001 AS DECIMAL(18,4))"),
    ("post_transaction_atomic",
     "INSERT INTO `{entry}` VALUES "
     "(100,9,1,300.0000,0,'2025-02-01 00:00:00','test'),"
     "(101,9,2,0,300.0000,'2025-02-01 00:00:00','test')"),
    ("reconciliation_window",
     "SELECT account_id, posted, "
     "SUM(debit-credit) OVER (PARTITION BY account_id ORDER BY posted) bal "
     "FROM `{entry}`"),
    ("currency_grouping",
     "SELECT a.currency, SUM(e.debit) FROM `{entry}` e "
     "JOIN `{acct}` a ON a.id=e.account_id GROUP BY a.currency"),
    ("zero_sum_invariant",
     "SELECT SUM(debit - credit) net FROM `{entry}`"),
]


# ===========================================================================
# WORKLOAD 6: MULTI-TENANT SaaS — tenant-scoped queries, row filtering.
# ===========================================================================

def _build_saas():
    tenant, proj, task = _names(3, "saas")
    _exec(f"CREATE TABLE `{tenant}` (id INT PRIMARY KEY, name VARCHAR(32), "
          f"plan VARCHAR(16))")
    _exec(f"CREATE TABLE `{proj}` (id INT PRIMARY KEY, tenant_id INT, "
          f"name VARCHAR(32), created DATE)")
    _exec(f"CREATE TABLE `{task}` (id INT PRIMARY KEY, tenant_id INT, project_id INT, "
          f"title VARCHAR(64), status VARCHAR(16), assignee VARCHAR(32), due DATE)")
    _exec(f"INSERT INTO `{tenant}` VALUES "
          "(1,'Acme','pro'),(2,'Beta','free'),(3,'Gamma','enterprise')")
    _exec(f"INSERT INTO `{proj}` VALUES "
          "(1,1,'Website','2025-01-01'),(2,1,'Mobile','2025-02-01'),"
          "(3,2,'Internal','2025-03-01'),(4,3,'Platform','2025-04-01')")
    _exec(f"INSERT INTO `{task}` VALUES "
          "(1,1,1,'Design','done','alice','2025-05-01'),"
          "(2,1,1,'Build','active','bob','2025-06-01'),"
          "(3,1,2,'Spec','active','alice','2025-06-15'),"
          "(4,2,3,'Setup','todo','carol','2025-07-01'),"
          "(5,3,4,'Plan','done','dan','2025-05-15'),"
          "(6,3,4,'Exec','active','eve','2025-08-01')")
    return dict(tenant=tenant, proj=proj, task=task)


def _saas_query(label, sql_tpl):
    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("saas", _build_saas)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"saas {label}"
    return run


SAAS_QUERIES = [
    ("tenant_scoped_tasks",
     "SELECT * FROM `{task}` WHERE tenant_id=1"),
    ("tenant_project_counts",
     "SELECT tenant_id, COUNT(*) FROM `{proj}` GROUP BY tenant_id"),
    ("tenant_task_status",
     "SELECT tenant_id, status, COUNT(*) FROM `{task}` "
     "GROUP BY tenant_id, status"),
    ("cross_tenant_leak_guard",
     "SELECT t.title FROM `{task}` t JOIN `{proj}` p ON p.id=t.project_id "
     "WHERE t.tenant_id=1 AND p.tenant_id=t.tenant_id"),
    ("tenant_with_plan",
     "SELECT te.name, COUNT(ta.id) tasks FROM `{tenant}` te "
     "LEFT JOIN `{task}` ta ON ta.tenant_id=te.id "
     "WHERE te.plan='enterprise' GROUP BY te.name"),
    ("active_tasks_per_assignee",
     "SELECT assignee, COUNT(*) FROM `{task}` WHERE status='active' "
     "GROUP BY assignee"),
    ("overdue_tasks",
     "SELECT * FROM `{task}` WHERE due < '2025-06-01' AND status<>'done'"),
    ("project_completion_rate",
     "SELECT project_id, SUM(status='done')/COUNT(*) rate FROM `{task}` "
     "GROUP BY project_id"),
    ("tenant_isolation_subquery",
     "SELECT * FROM `{task}` WHERE project_id IN "
     "(SELECT id FROM `{proj}` WHERE tenant_id=1)"),
    ("plan_distribution",
     "SELECT plan, COUNT(*) FROM `{tenant}` GROUP BY plan"),
    ("tenant_resource_usage",
     "SELECT te.name, COUNT(DISTINCT p.id) projects, COUNT(t.id) tasks "
     "FROM `{tenant}` te LEFT JOIN `{proj}` p ON p.tenant_id=te.id "
     "LEFT JOIN `{task}` t ON t.tenant_id=te.id GROUP BY te.name"),
    ("free_plan_limits",
     "SELECT te.name, COUNT(t.id) FROM `{tenant}` te "
     "JOIN `{task}` t ON t.tenant_id=te.id WHERE te.plan='free' "
     "GROUP BY te.name HAVING COUNT(t.id) > 5"),
    ("update_tenant_scoped",
     "UPDATE `{task}` SET status='archived' WHERE tenant_id=2 AND status='done'"),
    ("delete_tenant_scoped",
     "DELETE FROM `{task}` WHERE tenant_id=2"),
    ("rank_tasks_per_tenant",
     "SELECT tenant_id, title, ROW_NUMBER() OVER "
     "(PARTITION BY tenant_id ORDER BY due) rn FROM `{task}`"),
    ("tenant_recent_activity",
     "SELECT tenant_id, MAX(due) latest FROM `{task}` GROUP BY tenant_id"),
]


# ===========================================================================
# WORKLOAD 7: CMS — nested categories, slugs, soft-delete.
# ===========================================================================

def _build_cms():
    cat, art = _names(2, "cms")
    _exec(f"CREATE TABLE `{cat}` (id INT PRIMARY KEY, parent_id INT, "
          f"name VARCHAR(32), slug VARCHAR(48))")
    _exec(f"CREATE TABLE `{art}` (id INT PRIMARY KEY, category_id INT, "
          f"title VARCHAR(128), slug VARCHAR(160), body TEXT, "
          f"published DATETIME, deleted_at DATETIME)")
    _exec(f"INSERT INTO `{cat}` VALUES "
          "(1,NULL,'Root','root'),(2,1,'Tech','tech'),(3,1,'Life','life'),"
          "(4,2,'Programming','programming'),(5,2,'Hardware','hardware'),"
          "(6,4,'Python','python')")
    _exec(f"INSERT INTO `{art}` VALUES "
          "(1,6,'Intro to Python','intro-to-python','...','2025-01-01 00:00:00',NULL),"
          "(2,6,'Advanced Python','advanced-python','...','2025-02-01 00:00:00',NULL),"
          "(3,5,'GPU Guide','gpu-guide','...','2025-03-01 00:00:00','2025-04-01 00:00:00'),"
          "(4,3,'Travel','travel','...','2025-03-15 00:00:00',NULL),"
          "(5,4,'Rust Basics','rust-basics','...',NULL,NULL)")
    return dict(cat=cat, art=art)


def _cms_query(label, sql_tpl):
    is_write = sql_tpl.lstrip().upper().startswith(("UPDATE", "DELETE", "INSERT"))

    def run():
        t = _fixture("cms", _build_cms)
        sql = sql_tpl.format(**t)
        if is_write:
            _run_write_rollback(sql)
        else:
            _fetchall(sql)
        return f"cms {label}"
    return run


CMS_QUERIES = [
    ("category_tree",
     "WITH RECURSIVE tree AS ("
     "SELECT id, parent_id, name, 0 depth, CAST(slug AS CHAR(200)) path "
     "FROM `{cat}` WHERE parent_id IS NULL "
     "UNION ALL SELECT c.id, c.parent_id, c.name, t.depth+1, "
     "CONCAT(t.path,'/',c.slug) FROM `{cat}` c JOIN tree t ON c.parent_id=t.id) "
     "SELECT * FROM tree ORDER BY path"),
    ("breadcrumb",
     "WITH RECURSIVE up AS ("
     "SELECT id, parent_id, name FROM `{cat}` WHERE id=6 "
     "UNION ALL SELECT c.id, c.parent_id, c.name FROM `{cat}` c "
     "JOIN up ON c.id=up.parent_id) SELECT name FROM up"),
    ("articles_in_category_tree",
     "WITH RECURSIVE sub AS (SELECT id FROM `{cat}` WHERE id=2 "
     "UNION ALL SELECT c.id FROM `{cat}` c JOIN sub ON c.parent_id=sub.id) "
     "SELECT a.title FROM `{art}` a WHERE a.category_id IN (SELECT id FROM sub)"),
    ("published_articles",
     "SELECT title, slug FROM `{art}` WHERE published IS NOT NULL "
     "AND deleted_at IS NULL ORDER BY published DESC"),
    ("soft_deleted",
     "SELECT title FROM `{art}` WHERE deleted_at IS NOT NULL"),
    ("draft_articles",
     "SELECT title FROM `{art}` WHERE published IS NULL AND deleted_at IS NULL"),
    ("slug_lookup",
     "SELECT * FROM `{art}` WHERE slug='intro-to-python' AND deleted_at IS NULL"),
    ("articles_per_category",
     "SELECT c.name, COUNT(a.id) FROM `{cat}` c "
     "LEFT JOIN `{art}` a ON a.category_id=c.id AND a.deleted_at IS NULL "
     "GROUP BY c.name"),
    ("leaf_categories",
     "SELECT c.name FROM `{cat}` c WHERE NOT EXISTS "
     "(SELECT 1 FROM `{cat}` ch WHERE ch.parent_id=c.id)"),
    ("category_depth",
     "WITH RECURSIVE d AS (SELECT id, parent_id, 0 lvl FROM `{cat}` "
     "WHERE parent_id IS NULL UNION ALL SELECT c.id, c.parent_id, d.lvl+1 "
     "FROM `{cat}` c JOIN d ON c.parent_id=d.id) "
     "SELECT MAX(lvl) max_depth FROM d"),
    ("recent_published_window",
     "SELECT title, published, ROW_NUMBER() OVER (ORDER BY published DESC) rn "
     "FROM `{art}` WHERE published IS NOT NULL"),
    ("duplicate_slug_check",
     "SELECT slug, COUNT(*) FROM `{art}` GROUP BY slug HAVING COUNT(*)>1"),
    ("soft_delete_article",
     "UPDATE `{art}` SET deleted_at=NOW() WHERE id=1"),
    ("restore_article",
     "UPDATE `{art}` SET deleted_at=NULL WHERE id=3"),
    ("orphan_articles",
     "SELECT a.title FROM `{art}` a WHERE NOT EXISTS "
     "(SELECT 1 FROM `{cat}` c WHERE c.id=a.category_id)"),
    ("path_count_per_root",
     "SELECT COUNT(*) FROM `{cat}` WHERE parent_id IS NOT NULL"),
]


# ===========================================================================
# WORKLOAD 8: GEO — bounding-box, nearest (note spatial gaps).
# ===========================================================================

def _build_geo():
    place, = _names(1, "geo")
    _exec(f"CREATE TABLE `{place}` (id INT PRIMARY KEY, name VARCHAR(32), "
          f"lat DOUBLE, lon DOUBLE, kind VARCHAR(16))")
    _exec(f"INSERT INTO `{place}` VALUES "
          "(1,'A',37.7749,-122.4194,'cafe'),(2,'B',37.7849,-122.4094,'shop'),"
          "(3,'C',37.7649,-122.4294,'cafe'),(4,'D',40.7128,-74.0060,'shop'),"
          "(5,'E',37.8049,-122.2711,'park')")
    return dict(place=place)


def _geo_query(label, sql_tpl):
    def run():
        t = _fixture("geo", _build_geo)
        _fetchall(sql_tpl.format(**t))
        return f"geo {label}"
    return run


GEO_QUERIES = [
    ("bounding_box",
     "SELECT name FROM `{place}` WHERE lat BETWEEN 37.76 AND 37.79 "
     "AND lon BETWEEN -122.43 AND -122.40"),
    ("nearest_euclidean",
     "SELECT name, SQRT(POW(lat-37.7749,2)+POW(lon+122.4194,2)) dist "
     "FROM `{place}` ORDER BY dist LIMIT 3"),
    ("haversine_approx",
     "SELECT name, 6371*ACOS(COS(RADIANS(37.7749))*COS(RADIANS(lat))*"
     "COS(RADIANS(lon)-RADIANS(-122.4194))+SIN(RADIANS(37.7749))*"
     "SIN(RADIANS(lat))) km FROM `{place}` ORDER BY km LIMIT 3"),
    ("places_by_kind",
     "SELECT kind, COUNT(*) FROM `{place}` GROUP BY kind"),
    ("within_radius",
     "SELECT name FROM `{place}` WHERE "
     "POW(lat-37.7749,2)+POW(lon+122.4194,2) < 0.001"),
    ("centroid",
     "SELECT AVG(lat) clat, AVG(lon) clon FROM `{place}`"),
    ("st_point_attempt",
     "SELECT ST_AsText(ST_Point(lon, lat)) FROM `{place}` LIMIT 1"),
    ("st_distance_attempt",
     "SELECT ST_Distance(ST_Point(lon,lat), ST_Point(-122.4194,37.7749)) "
     "FROM `{place}` LIMIT 1"),
    ("geometry_column_attempt",
     "SELECT name FROM `{place}` ORDER BY "
     "GLENGTH(LINESTRING(POINT(lon,lat),POINT(0,0))) LIMIT 1"),
    ("nearest_per_kind",
     "SELECT kind, name, SQRT(POW(lat-37.7749,2)+POW(lon+122.4194,2)) d "
     "FROM (SELECT *, ROW_NUMBER() OVER (PARTITION BY kind ORDER BY "
     "POW(lat-37.7749,2)+POW(lon+122.4194,2)) rn FROM `{place}`) s WHERE rn=1"),
    ("bbox_count",
     "SELECT COUNT(*) FROM `{place}` WHERE lat > 37 AND lat < 41"),
    ("distance_matrix",
     "SELECT a.name, b.name, SQRT(POW(a.lat-b.lat,2)+POW(a.lon-b.lon,2)) d "
     "FROM `{place}` a JOIN `{place}` b ON a.id<b.id"),
]


# ===========================================================================
# DEEPER MATRIX 1: FULL DECIMAL precision/scale grid.
# Declare, roundtrip, arithmetic, aggregate over a wide (precision,scale) grid.
# ===========================================================================

DECIMAL_GRID = [
    (p, s) for p in (1, 2, 4, 8, 10, 16, 18, 20, 30, 38, 50, 65)
    for s in (0, 1, 2, 4, 8, 10, 16, 30)
    if s <= p and s <= 38
]


def _decimal_value(p, s):
    intdigits = max(1, p - s)
    whole = "9" * min(intdigits, 8)
    if s > 0:
        frac = "1" * min(s, 6)
        return f"{whole}.{frac}"
    return whole


def _decimal_declare(p, s):
    def run():
        t, = _names(1, "dec")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT, c DECIMAL({p},{s}))")
        finally:
            _drop_all(t)
        return f"decimal({p},{s}) declare"
    return run


def _decimal_roundtrip(p, s):
    def run():
        t, = _names(1, "dec")
        val = _decimal_value(p, s)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c DECIMAL({p},{s}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, {val})")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row is None:
                raise BehaviorMismatch(f"decimal({p},{s}) roundtrip: no row")
        finally:
            _drop_all(t)
        return f"decimal({p},{s}) -> {row[0]}"
    return run


def _decimal_arith(p, s):
    def run():
        t, = _names(1, "dec")
        val = _decimal_value(p, s)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c DECIMAL({p},{s}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, {val}),(2, {val})")
            _fetchone(f"SELECT SUM(c), AVG(c), MAX(c)-MIN(c) FROM `{t}`")
        finally:
            _drop_all(t)
        return f"decimal({p},{s}) arith"
    return run


# ===========================================================================
# DEEPER MATRIX 2: TEMPORAL precision + timezone handling.
# ===========================================================================

TEMPORAL_PRECISION = [
    ("datetime_0", "DATETIME", "2026-06-23 11:22:33"),
    ("datetime_1", "DATETIME(1)", "2026-06-23 11:22:33.1"),
    ("datetime_2", "DATETIME(2)", "2026-06-23 11:22:33.12"),
    ("datetime_3", "DATETIME(3)", "2026-06-23 11:22:33.123"),
    ("datetime_4", "DATETIME(4)", "2026-06-23 11:22:33.1234"),
    ("datetime_5", "DATETIME(5)", "2026-06-23 11:22:33.12345"),
    ("datetime_6", "DATETIME(6)", "2026-06-23 11:22:33.123456"),
    ("timestamp_0", "TIMESTAMP", "2026-06-23 11:22:33"),
    ("timestamp_3", "TIMESTAMP(3)", "2026-06-23 11:22:33.123"),
    ("timestamp_6", "TIMESTAMP(6)", "2026-06-23 11:22:33.123456"),
    ("time_0", "TIME", "11:22:33"),
    ("time_3", "TIME(3)", "11:22:33.123"),
    ("time_6", "TIME(6)", "11:22:33.123456"),
]


def _temporal_roundtrip(label, sqltype, value):
    def run():
        t, = _names(1, "tmp")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{value}')")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            if row is None:
                raise BehaviorMismatch(f"{label} roundtrip: no row")
        finally:
            _drop_all(t)
        return f"{label} -> {row[0]!r}"
    return run


def _temporal_fracsec_truncation(label, sqltype, value):
    """Insert a high-precision value into a lower-precision column; observe."""
    def run():
        t, = _names(1, "tmp")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, '2026-06-23 11:22:33.654321')"
                  if "TIME(" not in sqltype and "TIME " != sqltype and sqltype != "TIME"
                  else f"INSERT INTO `{t}` VALUES (1, '11:22:33.654321')")
            _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"{label} fracsec"
    return run


TZ_CONVERSIONS = [
    ("utc_to_tokyo", "CONVERT_TZ('2026-06-23 12:00:00','+00:00','+09:00')"),
    ("utc_to_la", "CONVERT_TZ('2026-06-23 12:00:00','+00:00','-08:00')"),
    ("named_utc", "CONVERT_TZ('2026-06-23 12:00:00','UTC','Asia/Shanghai')"),
    ("system_to_utc", "CONVERT_TZ('2026-06-23 12:00:00','SYSTEM','+00:00')"),
    ("offset_half", "CONVERT_TZ('2026-06-23 12:00:00','+00:00','+05:30')"),
    ("ts_at_tz_var", "@@time_zone"),
    ("ts_session_tz", "@@session.time_zone"),
    ("from_unixtime_tz", "FROM_UNIXTIME(1750680000)"),
    ("unix_timestamp_now", "UNIX_TIMESTAMP('2026-06-23 12:00:00')"),
    ("utc_timestamp", "UTC_TIMESTAMP()"),
]


def _tz_query(label, expr):
    def run():
        _fetchone(f"SELECT {expr}")
        return f"tz {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 3: CHARSET / COLLATION grid.
# ===========================================================================

CHARSETS = ["utf8mb4", "utf8", "latin1", "ascii", "gbk", "binary"]
COLLATIONS_GRID = [
    ("utf8mb4_general_ci", "utf8mb4"),
    ("utf8mb4_bin", "utf8mb4"),
    ("utf8mb4_unicode_ci", "utf8mb4"),
    ("utf8mb4_0900_ai_ci", "utf8mb4"),
    ("utf8_general_ci", "utf8"),
    ("utf8_bin", "utf8"),
    ("latin1_swedish_ci", "latin1"),
    ("latin1_bin", "latin1"),
    ("ascii_general_ci", "ascii"),
    ("ascii_bin", "ascii"),
    ("gbk_chinese_ci", "gbk"),
    ("binary", "binary"),
]


def _charset_create(cs):
    def run():
        t, = _names(1, "cs")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) CHARACTER SET {cs})")
            _exec(f"INSERT INTO `{t}` VALUES (1, 'hello')")
            _fetchone(f"SELECT c FROM `{t}`")
        finally:
            _drop_all(t)
        return f"charset {cs}"
    return run


def _collation_create(coll, cs):
    def run():
        t, = _names(1, "coll")
        try:
            if coll == "binary":
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) BINARY)")
            else:
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) "
                      f"CHARACTER SET {cs} COLLATE {coll})")
        finally:
            _drop_all(t)
        return f"collation {coll}"
    return run


def _collation_case_sensitivity(coll, cs):
    """Insert 'abc' and 'ABC', test whether = and DISTINCT treat them equal."""
    def run():
        t, = _names(1, "coll")
        try:
            if coll == "binary":
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) BINARY)")
            else:
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) "
                      f"CHARACTER SET {cs} COLLATE {coll})")
            _exec(f"INSERT INTO `{t}` VALUES (1,'abc'),(2,'ABC')")
            row = _fetchone(f"SELECT COUNT(*) FROM `{t}` WHERE c='abc'")
            ci = coll.endswith("_ci") or coll.endswith("general_ci")
            n = row[0]
            if ci and n != 2:
                raise BehaviorMismatch(
                    f"{coll}: case-insensitive but 'abc'='abc' matched {n} rows")
            if (coll.endswith("_bin") or coll == "binary") and n != 1:
                raise BehaviorMismatch(
                    f"{coll}: binary collation but 'abc' matched {n} rows")
        finally:
            _drop_all(t)
        return f"collation case {coll}: matched"
    return run


def _collation_orderby(coll, cs):
    def run():
        t, = _names(1, "coll")
        try:
            if coll == "binary":
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) BINARY)")
            else:
                _exec(f"CREATE TABLE `{t}` (id INT, c VARCHAR(64) "
                      f"CHARACTER SET {cs} COLLATE {coll})")
            _exec(f"INSERT INTO `{t}` VALUES (1,'Banana'),(2,'apple'),(3,'Cherry')")
            _fetchall(f"SELECT c FROM `{t}` ORDER BY c")
        finally:
            _drop_all(t)
        return f"collation orderby {coll}"
    return run


# ===========================================================================
# DEEPER MATRIX 4: NULL-handling / three-valued logic grid.
# ===========================================================================

THREE_VALUED_LOGIC = [
    ("null_eq_null", "NULL = NULL", None),
    ("null_ne_null", "NULL <> NULL", None),
    ("null_lt_1", "NULL < 1", None),
    ("null_is_null", "NULL IS NULL", 1),
    ("null_is_not_null", "NULL IS NOT NULL", 0),
    ("null_and_true", "NULL AND TRUE", None),
    ("null_and_false", "NULL AND FALSE", 0),
    ("null_or_true", "NULL OR TRUE", 1),
    ("null_or_false", "NULL OR FALSE", None),
    ("not_null", "NOT NULL", None),
    ("null_safe_eq", "NULL <=> NULL", 1),
    ("null_safe_eq_val", "NULL <=> 1", 0),
    ("null_in_list", "NULL IN (1,2,3)", None),
    ("val_in_null_list", "1 IN (NULL,2)", None),
    ("null_plus", "NULL + 1", None),
    ("null_concat", "CONCAT('a', NULL)", None),
    ("coalesce_all_null", "COALESCE(NULL,NULL)", None),
    ("ifnull_null", "IFNULL(NULL, 'x')", "x"),
    ("nullif_equal", "NULLIF(5,5)", None),
    ("greatest_with_null", "GREATEST(1, NULL, 3)", None),
    ("least_with_null", "LEAST(1, NULL, 3)", None),
    ("null_between", "NULL BETWEEN 1 AND 10", None),
    ("null_like", "NULL LIKE 'a%'", None),
    ("case_null", "CASE WHEN NULL THEN 'a' ELSE 'b' END", "b"),
    ("count_null", "COUNT(NULL)", 0),
    ("sum_null", "SUM(NULL)", None),
]


def _three_valued_logic(label, expr, expected):
    def run():
        row = _fetchone(f"SELECT {expr}")
        got = row[0]
        # normalise booleans MatrixOne may return as 0/1
        norm = got
        if isinstance(got, (int,)) and expected in (0, 1):
            norm = int(got)
        if expected is None and got is not None:
            raise BehaviorMismatch(f"{label}: expected NULL, got {got!r}")
        if expected is not None and norm != expected and str(got) != str(expected):
            raise BehaviorMismatch(f"{label}: expected {expected!r}, got {got!r}")
        return f"3vl {label} -> {got!r}"
    return run


# Per-type NULL handling in DISTINCT/GROUP BY/aggregates/ordering.
def _null_in_aggregate(label, sqltype, value, quoted):
    def run():
        t, = _names(1, "nul")
        lit = ("'" + value.replace("'", "''") + "'") if quoted else value
        try:
            _exec(f"CREATE TABLE `{t}` (id INT, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {lit}),(2, NULL),(3, NULL)")
            _fetchone(f"SELECT COUNT(c), COUNT(*), COUNT(DISTINCT c) FROM `{t}`")
        finally:
            _drop_all(t)
        return f"null_agg {label}"
    return run


def _null_ordering(label, sqltype, value, quoted):
    def run():
        t, = _names(1, "nul")
        lit = ("'" + value.replace("'", "''") + "'") if quoted else value
        try:
            _exec(f"CREATE TABLE `{t}` (id INT, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {lit}),(2, NULL)")
            asc = _fetchall(f"SELECT c FROM `{t}` ORDER BY c ASC")
            desc = _fetchall(f"SELECT c FROM `{t}` ORDER BY c DESC")
        finally:
            _drop_all(t)
        return f"null_order {label} asc={asc[0][0] is None} desc={desc[0][0] is None}"
    return run


# ===========================================================================
# DEEPER MATRIX 5: NUMERIC overflow / coercion grid.
# ===========================================================================

OVERFLOW_CASES = [
    ("tinyint_over", "TINYINT", "128"),
    ("tinyint_under", "TINYINT", "-129"),
    ("tinyint_u_over", "TINYINT UNSIGNED", "256"),
    ("tinyint_u_neg", "TINYINT UNSIGNED", "-1"),
    ("smallint_over", "SMALLINT", "32768"),
    ("smallint_u_over", "SMALLINT UNSIGNED", "65536"),
    ("int_over", "INT", "2147483648"),
    ("int_u_over", "INT UNSIGNED", "4294967296"),
    ("bigint_over", "BIGINT", "9223372036854775808"),
    ("bigint_u_over", "BIGINT UNSIGNED", "18446744073709551616"),
    ("decimal_int_over", "DECIMAL(4,0)", "12345"),
    ("decimal_scale_round", "DECIMAL(6,2)", "123.456"),
    ("decimal_scale_round2", "DECIMAL(6,2)", "123.999"),
    ("tinyint_float", "TINYINT", "42.9"),
    ("int_string_num", "INT", "'123'"),
    ("int_string_alpha", "INT", "'abc'"),
    ("decimal_neg_zero", "DECIMAL(5,2)", "-0.00"),
    ("float_precision", "FLOAT", "0.1"),
    ("double_precision", "DOUBLE", "0.1"),
    ("float_huge", "FLOAT", "3.4e38"),
    ("float_overflow", "FLOAT", "3.5e38"),
    ("smallint_float_round", "SMALLINT", "100.5"),
]


def _overflow_case(label, sqltype, value):
    """Insert a possibly-out-of-range value; record whether it errors/clamps."""
    def run():
        t, = _names(1, "ovf")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {value})")
            row = _fetchone(f"SELECT c FROM `{t}` WHERE id=1")
            stored = row[0] if row else None
        finally:
            _drop_all(t)
        return f"overflow {label}: stored {stored!r}"
    return run


COERCION_CASES = [
    ("str_plus_int", "'5' + 3"),
    ("str_concat_int", "CONCAT(5, '3')"),
    ("int_div_int", "7 / 2"),
    ("int_intdiv", "7 DIV 2"),
    ("float_to_int_implicit", "CAST(3.9 AS SIGNED)"),
    ("str_num_compare", "'10' > '9'"),
    ("str_num_compare2", "'10' > 9"),
    ("bool_arith", "TRUE + TRUE"),
    ("hex_to_int", "0x41 + 0"),
    ("date_to_int", "'2026-06-23' + 0"),
    ("null_coalesce_chain", "COALESCE(NULL, '', 'x')"),
    ("empty_str_to_num", "'' + 0"),
    ("scientific_notation", "1.5e3 + 0"),
    ("mixed_decimal_float", "CAST(1.5 AS DECIMAL(10,2)) + 0.1"),
    ("bigint_decimal_mix", "9223372036854775807 + 1.0"),
]


def _coercion_case(label, expr):
    def run():
        _fetchone(f"SELECT {expr}")
        return f"coercion {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 6: ENUM / SET edge behaviour.
# ===========================================================================

ENUM_SET_CASES = [
    ("enum_valid", "ENUM('a','b','c')", "'b'", "select"),
    ("enum_invalid", "ENUM('a','b','c')", "'z'", "select"),
    ("enum_empty", "ENUM('a','b','c')názov", "''", "select"),
    ("enum_numeric_index", "ENUM('a','b','c')", "2", "select"),
    ("enum_case", "ENUM('Apple','Banana')", "'apple'", "select"),
    ("enum_order", "ENUM('low','mid','high')", "'mid'", "order"),
    ("enum_compare", "ENUM('low','mid','high')", "'mid'", "compare"),
    ("set_single", "SET('a','b','c')", "'b'", "select"),
    ("set_multi", "SET('a','b','c')", "'a,c'", "select"),
    ("set_dup", "SET('a','b','c')", "'a,a'", "select"),
    ("set_invalid", "SET('a','b','c')", "'a,z'", "select"),
    ("set_reorder", "SET('a','b','c')", "'c,a'", "select"),
    ("set_empty", "SET('a','b','c')", "''", "select"),
    ("set_find_in", "SET('a','b','c')", "'a,b'", "findinset"),
    ("set_numeric", "SET('a','b','c')", "5", "select"),
]


def _enum_set_case(label, coltype, value, op):
    # fix accidental non-ascii in test data
    coltype = coltype.replace("názov", "")

    def run():
        t, = _names(1, "es")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {coltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {value})")
            if op == "order":
                _fetchall(f"SELECT c FROM `{t}` ORDER BY c")
            elif op == "compare":
                _fetchall(f"SELECT c FROM `{t}` WHERE c > 'low'")
            elif op == "findinset":
                _fetchall(f"SELECT FIND_IN_SET('b', c) FROM `{t}`")
            else:
                _fetchone(f"SELECT c, c+0 FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"enumset {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 7: BIT type behaviour.
# ===========================================================================

BIT_CASES = [
    ("bit1_0", "BIT(1)", "b'0'"),
    ("bit1_1", "BIT(1)", "b'1'"),
    ("bit8", "BIT(8)", "b'10101010'"),
    ("bit16", "BIT(16)", "b'1111000011110000'"),
    ("bit32", "BIT(32)", "b'1'"),
    ("bit64", "BIT(64)", "b'1111111111111111111111111111111111111111111111111111111111111111'"),
    ("bit_int", "BIT(8)", "255"),
    ("bit_zero", "BIT(8)", "0"),
]


def _bit_roundtrip(label, sqltype, value):
    def run():
        t, = _names(1, "bit")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {value})")
            _fetchone(f"SELECT c+0, BIN(c), HEX(c) FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"bit {label}"
    return run


def _bit_ops(label, sqltype, value):
    def run():
        t, = _names(1, "bit")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {value})")
            _fetchone(f"SELECT c & b'1', c | b'1', c ^ b'1', ~c, "
                      f"BIT_COUNT(c) FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"bit_ops {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 8: JSON deep paths + JSON column operations.
# ===========================================================================

_JSON_DOC = ('{"id":1,"name":"root","tags":["a","b","c"],'
             '"meta":{"created":"2025-01-01","nested":{"deep":{"value":42}}},'
             '"items":[{"k":1,"v":"x"},{"k":2,"v":"y"}],'
             '"flags":{"active":true,"score":3.14}}')

JSON_PATHS = [
    ("root_id", "$.id"),
    ("name", "$.name"),
    ("tags_all", "$.tags"),
    ("tags_first", "$.tags[0]"),
    ("tags_last", "$.tags[2]"),
    ("meta_created", "$.meta.created"),
    ("deep_value", "$.meta.nested.deep.value"),
    ("items_array", "$.items"),
    ("items_0_k", "$.items[0].k"),
    ("items_1_v", "$.items[1].v"),
    ("flag_active", "$.flags.active"),
    ("flag_score", "$.flags.score"),
    ("wildcard_items", "$.items[*].k"),
    ("recursive_k", "$**.k"),
    ("missing", "$.does.not.exist"),
]


def _json_path_extract(label, path):
    def run():
        t, = _names(1, "js")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, doc JSON)")
            _exec(f"INSERT INTO `{t}` VALUES (1, %s)", (_JSON_DOC,))
            _fetchone(f"SELECT JSON_EXTRACT(doc, %s) FROM `{t}` WHERE id=1", (path,))
        finally:
            _drop_all(t)
        return f"json_extract {label}"
    return run


def _json_arrow_op(label, path):
    def run():
        t, = _names(1, "js")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, doc JSON)")
            _exec(f"INSERT INTO `{t}` VALUES (1, %s)", (_JSON_DOC,))
            # both -> (JSON) and ->> (unquoted) operators
            _fetchone(f"SELECT doc->'{path}', doc->>'{path}' FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"json_arrow {label}"
    return run


JSON_OPS_DEEP = [
    ("json_keys", "JSON_KEYS(doc)"),
    ("json_keys_path", "JSON_KEYS(doc, '$.meta')"),
    ("json_length", "JSON_LENGTH(doc)"),
    ("json_length_arr", "JSON_LENGTH(doc, '$.tags')"),
    ("json_type", "JSON_TYPE(doc)"),
    ("json_type_path", "JSON_TYPE(JSON_EXTRACT(doc,'$.tags'))"),
    ("json_valid", "JSON_VALID(doc)"),
    ("json_contains", "JSON_CONTAINS(doc, '1', '$.id')"),
    ("json_contains_path", "JSON_CONTAINS_PATH(doc, 'one', '$.meta')"),
    ("json_search", "JSON_SEARCH(doc, 'one', 'x')"),
    ("json_depth", "JSON_DEPTH(doc)"),
    ("json_set", "JSON_SET(doc, '$.id', 99)"),
    ("json_insert", "JSON_INSERT(doc, '$.new', 1)"),
    ("json_replace", "JSON_REPLACE(doc, '$.name', 'changed')"),
    ("json_remove", "JSON_REMOVE(doc, '$.tags[0]')"),
    ("json_merge_patch", "JSON_MERGE_PATCH(doc, '{\"x\":1}')"),
    ("json_array_append", "JSON_ARRAY_APPEND(doc, '$.tags', 'd')"),
    ("json_unquote", "JSON_UNQUOTE(JSON_EXTRACT(doc,'$.name'))"),
    ("json_pretty", "JSON_PRETTY(doc)"),
    ("json_storage_size", "JSON_STORAGE_SIZE(doc)"),
    ("json_overlaps", "JSON_OVERLAPS(JSON_EXTRACT(doc,'$.tags'), '[\"a\"]')"),
    ("json_quote_name", "JSON_QUOTE('test')"),
]


def _json_op_on_col(label, expr):
    def run():
        t, = _names(1, "js")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, doc JSON)")
            _exec(f"INSERT INTO `{t}` VALUES (1, %s)", (_JSON_DOC,))
            _fetchone(f"SELECT {expr} FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"json_op {label}"
    return run


def _json_where_filter(label, path):
    def run():
        t, = _names(1, "js")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, doc JSON)")
            _exec(f"INSERT INTO `{t}` VALUES (1, %s),(2,%s)",
                  (_JSON_DOC, '{"id":2,"name":"other"}'))
            _fetchall(f"SELECT id FROM `{t}` WHERE "
                      f"JSON_EXTRACT(doc, %s) IS NOT NULL", (path,))
        finally:
            _drop_all(t)
        return f"json_where {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 9: VECTOR (VECF32/64 dims, distance fns, IVFFLAT, hybrid).
# ===========================================================================

VEC_DIMS = [2, 3, 4, 8, 16, 32, 64, 128, 256, 512, 768, 1024]
VEC_DISTANCE_FNS = [
    ("l2_distance", "l2_distance"),
    ("l2_distance_sq", "l2_distance_sq"),
    ("cosine_distance", "cosine_distance"),
    ("inner_product", "inner_product"),
    ("cosine_similarity", "cosine_similarity"),
    ("negative_inner_product", "negative_inner_product"),
]
VEC_TYPES = ["vecf32", "vecf64"]


def _vec_literal(dim):
    return "[" + ",".join(str(i + 1) for i in range(dim)) + "]"


def _vec_declare(vtype, dim):
    def run():
        t, = _names(1, "vec")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{_vec_literal(dim)}')")
            _fetchone(f"SELECT e FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"vec {vtype}({dim}) declare"
    return run


def _vec_distance(vtype, dim, fnlabel, fn):
    def run():
        t, = _names(1, "vec")
        lit = _vec_literal(dim)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{lit}'),(2, '{lit}')")
            _fetchall(f"SELECT id, {fn}(e, '{lit}') d FROM `{t}` "
                      f"ORDER BY {fn}(e, '{lit}') LIMIT 5")
        finally:
            _drop_all(t)
        return f"vec {vtype}({dim}) {fnlabel}"
    return run


def _vec_arithmetic(vtype, dim):
    def run():
        t, = _names(1, "vec")
        lit = _vec_literal(dim)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{lit}')")
            _fetchone(f"SELECT e + e, e - e, e * 2 FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"vec {vtype}({dim}) arith"
    return run


def _vec_aggregate(vtype, dim):
    def run():
        t, = _names(1, "vec")
        lit = _vec_literal(dim)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{lit}'),(2, '{lit}')")
            _fetchone(f"SELECT SUM(e), l2_norm(e) FROM `{t}` GROUP BY id LIMIT 1")
        finally:
            _drop_all(t)
        return f"vec {vtype}({dim}) agg"
    return run


def _vec_ivfflat_index(vtype, dim, optype):
    def run():
        t, = _names(1, "vec")
        lit = _vec_literal(dim)
        try:
            _exec("SET experimental_ivf_index=1")
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1, '{lit}'),(2, '{lit}'),(3, '{lit}')")
            _exec(f"CREATE INDEX idx_{t} USING ivfflat ON `{t}`(e) "
                  f"lists=1 op_type '{optype}'")
            _fetchall(f"SELECT id FROM `{t}` ORDER BY l2_distance(e,'{lit}') LIMIT 1")
        finally:
            _drop_all(t)
        return f"vec ivfflat {vtype}({dim}) {optype}"
    return run


def _vec_hybrid_filter(vtype, dim):
    def run():
        t, = _names(1, "vec")
        lit = _vec_literal(dim)
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, cat VARCHAR(8), "
                  f"e {vtype}({dim}))")
            _exec(f"INSERT INTO `{t}` VALUES (1,'a','{lit}'),(2,'b','{lit}'),"
                  f"(3,'a','{lit}')")
            _fetchall(f"SELECT id FROM `{t}` WHERE cat='a' "
                      f"ORDER BY l2_distance(e,'{lit}') LIMIT 2")
        finally:
            _drop_all(t)
        return f"vec hybrid {vtype}({dim})"
    return run


# ===========================================================================
# DEEPER MATRIX 10: FULL-TEXT (natural / boolean / relevance).
# ===========================================================================

def _build_fulltext():
    ft, = _names(1, "ft")
    _exec(f"CREATE TABLE `{ft}` (id INT PRIMARY KEY, title VARCHAR(128), "
          f"body TEXT, FULLTEXT idx_ft (title, body))")
    _exec(f"INSERT INTO `{ft}` VALUES "
          "(1,'MatrixOne database','a fast cloud native database engine'),"
          "(2,'Vector search','approximate nearest neighbor vector search'),"
          "(3,'SQL tutorial','learn SQL query language basics'),"
          "(4,'Database internals','storage engine and query optimizer'),"
          "(5,'Cloud computing','distributed systems and cloud native apps')")
    return dict(ft=ft)


def _fulltext_query(label, sql_tpl):
    def run():
        t = _fixture("fulltext", _build_fulltext)
        _fetchall(sql_tpl.format(**t))
        return f"fulltext {label}"
    return run


FULLTEXT_QUERIES = [
    ("natural_single",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) AGAINST('database')"),
    ("natural_multi",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) AGAINST('cloud native')"),
    ("natural_mode_explicit",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('vector' IN NATURAL LANGUAGE MODE)"),
    ("boolean_required",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('+database +engine' IN BOOLEAN MODE)"),
    ("boolean_excluded",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('+database -vector' IN BOOLEAN MODE)"),
    ("boolean_optional",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('database cloud' IN BOOLEAN MODE)"),
    ("boolean_wildcard",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('data*' IN BOOLEAN MODE)"),
    ("boolean_phrase",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) "
     "AGAINST('\"vector search\"' IN BOOLEAN MODE)"),
    ("relevance_score",
     "SELECT id, MATCH(title,body) AGAINST('database') score FROM `{ft}` "
     "ORDER BY score DESC"),
    ("relevance_threshold",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) AGAINST('database') > 0.1"),
    ("match_in_select",
     "SELECT id, MATCH(title,body) AGAINST('cloud') FROM `{ft}`"),
    ("no_match",
     "SELECT id FROM `{ft}` WHERE MATCH(title,body) AGAINST('nonexistentword')"),
]


# ===========================================================================
# DEEPER MATRIX 11: GENERATED columns variants.
# ===========================================================================

GENERATED_COLUMN_CASES = [
    ("stored_arith", "a INT, b INT AS (a+1) STORED", "(5)", "a"),
    ("virtual_arith", "a INT, b INT AS (a*2) VIRTUAL", "(5)", "a"),
    ("stored_concat", "f VARCHAR(20), l VARCHAR(20), "
     "fullname VARCHAR(41) AS (CONCAT(f,' ',l)) STORED", "('John','Doe')", "f, l"),
    ("virtual_concat", "f VARCHAR(20), l VARCHAR(20), "
     "fullname VARCHAR(41) AS (CONCAT(f,' ',l)) VIRTUAL", "('Jane','Roe')", "f, l"),
    ("stored_expr", "price DECIMAL(10,2), qty INT, "
     "total DECIMAL(12,2) AS (price*qty) STORED", "(9.99, 3)", "price, qty"),
    ("virtual_case", "score INT, "
     "grade VARCHAR(2) AS (CASE WHEN score>=90 THEN 'A' ELSE 'B' END) VIRTUAL",
     "(95)", "score"),
    ("stored_json", "doc JSON, "
     "name VARCHAR(64) AS (JSON_UNQUOTE(JSON_EXTRACT(doc,'$.name'))) STORED",
     "('{\"name\":\"test\"}')", "doc"),
    ("virtual_date", "d DATE, y INT AS (YEAR(d)) VIRTUAL", "('2026-06-23')", "d"),
    ("default_keyword", "a INT AS (1+1)", "()", ""),
    ("stored_math", "r DOUBLE, area DOUBLE AS (3.14159*r*r) STORED", "(2.0)", "r"),
]


def _generated_column(label, coldef, insert_vals, insert_cols):
    def run():
        t, = _names(1, "gen")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, {coldef})")
            if insert_cols:
                _exec(f"INSERT INTO `{t}` (id, {insert_cols}) "
                      f"VALUES (1, {insert_vals.strip('()')})")
            else:
                _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
            _fetchone(f"SELECT * FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"generated {label}"
    return run


def _generated_column_update(label, coldef, insert_vals, insert_cols):
    """Generated columns should reject direct writes; observe behaviour."""
    def run():
        t, = _names(1, "gen")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, {coldef})")
            if insert_cols:
                _exec(f"INSERT INTO `{t}` (id, {insert_cols}) "
                      f"VALUES (1, {insert_vals.strip('()')})")
            else:
                _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
            # Try to recompute by updating a base column
            base = insert_cols.split(",")[0].strip() if insert_cols else None
            if base:
                _exec(f"UPDATE `{t}` SET {base} = {base} WHERE id=1")
            _fetchone(f"SELECT * FROM `{t}` WHERE id=1")
        finally:
            _drop_all(t)
        return f"generated_update {label}"
    return run


# ===========================================================================
# DEEPER MATRIX 12: CONCURRENCY & ISOLATION.
# These use short-lived dedicated connections so they don't disturb the shared
# pool. Pools are kept tiny (<=4 connections) to be gentle on the shared node.
# ===========================================================================

ISOLATION_LEVELS = [
    "READ UNCOMMITTED", "READ COMMITTED", "REPEATABLE READ", "SERIALIZABLE",
]


def _isolation_set_and_read(level):
    def run():
        conn = _fresh_conn()
        try:
            cur = conn.cursor()
            cur.execute(f"SET SESSION TRANSACTION ISOLATION LEVEL {level}")
            cur.execute("SELECT @@transaction_isolation")
            cur.fetchone()
        finally:
            with contextlib.suppress(Exception):
                conn.close()
        return f"isolation set {level}"
    return run


def _isolation_visibility(level):
    """Writer commits a row; reader (at given isolation) checks visibility."""
    def run():
        t, = _names(1, "iso")
        w = _fresh_conn()
        r = _fresh_conn()
        try:
            wc = w.cursor()
            wc.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
            wc.execute(f"INSERT INTO `{t}` VALUES (1, 10)")
            rc = r.cursor()
            rc.execute(f"SET SESSION TRANSACTION ISOLATION LEVEL {level}")
            r.begin()
            rc.execute(f"SELECT v FROM `{t}` WHERE id=1")
            before = rc.fetchone()
            # writer updates and commits
            w.begin()
            wc.execute(f"UPDATE `{t}` SET v=20 WHERE id=1")
            w.commit()
            # reader reads again within same transaction
            rc.execute(f"SELECT v FROM `{t}` WHERE id=1")
            after = rc.fetchone()
            r.commit()
            return (f"isolation visibility {level}: "
                    f"before={before[0] if before else None} "
                    f"after={after[0] if after else None}")
        finally:
            for conn in (w, r):
                with contextlib.suppress(Exception):
                    conn.rollback()
            with contextlib.suppress(Exception):
                w.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
            for conn in (w, r):
                with contextlib.suppress(Exception):
                    conn.close()
    return run


def _concurrency_probe(name):
    probes = {
        "concurrent_inserts": _probe_concurrent_inserts,
        "concurrent_readers": _probe_concurrent_readers,
        "lost_update": _probe_lost_update,
        "write_skew": _probe_write_skew,
        "select_for_update": _probe_select_for_update,
        "deadlock": _probe_deadlock,
        "pool_exhaustion": _probe_pool_exhaustion,
        "autocommit_visibility": _probe_autocommit_visibility,
        "rollback_isolation": _probe_rollback_isolation,
        "concurrent_update_same_row": _probe_concurrent_update_same_row,
    }
    return probes[name]


def _probe_concurrent_inserts():
    t, = _names(1, "cc")
    conns = [_fresh_conn() for _ in range(3)]
    try:
        c0 = conns[0].cursor()
        c0.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, who INT)")
        for i, conn in enumerate(conns):
            cur = conn.cursor()
            cur.execute(f"INSERT INTO `{t}` VALUES ({i}, {i})")
        n = c0.execute(f"SELECT COUNT(*) FROM `{t}`")
        row = c0.fetchone()
        if row[0] != 3:
            raise BehaviorMismatch(f"concurrent inserts: expected 3, got {row[0]}")
        return "concurrent inserts ok"
    finally:
        with contextlib.suppress(Exception):
            conns[0].cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in conns:
            with contextlib.suppress(Exception):
                conn.close()


def _probe_concurrent_readers():
    t, = _names(1, "cc")
    conns = [_fresh_conn() for _ in range(4)]
    try:
        c0 = conns[0].cursor()
        c0.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        c0.execute(f"INSERT INTO `{t}` VALUES (1,1),(2,2),(3,3)")
        results = []
        for conn in conns:
            cur = conn.cursor()
            cur.execute(f"SELECT SUM(v) FROM `{t}`")
            results.append(cur.fetchone()[0])
        if len(set(results)) != 1:
            raise BehaviorMismatch(f"concurrent readers disagreed: {results}")
        return f"concurrent readers ok (all saw {results[0]})"
    finally:
        with contextlib.suppress(Exception):
            conns[0].cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in conns:
            with contextlib.suppress(Exception):
                conn.close()


def _probe_lost_update():
    t, = _names(1, "cc")
    a = _fresh_conn()
    b = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, bal INT)")
        ca.execute(f"INSERT INTO `{t}` VALUES (1, 100)")
        cb = b.cursor()
        a.begin(); b.begin()
        ca.execute(f"SELECT bal FROM `{t}` WHERE id=1"); va = ca.fetchone()[0]
        cb.execute(f"SELECT bal FROM `{t}` WHERE id=1"); vb = cb.fetchone()[0]
        ca.execute(f"UPDATE `{t}` SET bal={va+10} WHERE id=1"); a.commit()
        cb.execute(f"UPDATE `{t}` SET bal={vb+20} WHERE id=1"); b.commit()
        ca.execute(f"SELECT bal FROM `{t}` WHERE id=1"); final = ca.fetchone()[0]
        # If lost-update occurred, final == 120 (b overwrote a). 130 = serialized.
        return f"lost_update: final bal={final} (130=safe, 120=lost)"
    finally:
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.rollback()
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.close()


def _probe_write_skew():
    t, = _names(1, "cc")
    a = _fresh_conn()
    b = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, on_call INT)")
        ca.execute(f"INSERT INTO `{t}` VALUES (1,1),(2,1)")
        cb = b.cursor()
        a.begin(); b.begin()
        ca.execute(f"SELECT SUM(on_call) FROM `{t}`"); sa = ca.fetchone()[0]
        cb.execute(f"SELECT SUM(on_call) FROM `{t}`"); sb = cb.fetchone()[0]
        if sa >= 2:
            ca.execute(f"UPDATE `{t}` SET on_call=0 WHERE id=1")
        if sb >= 2:
            cb.execute(f"UPDATE `{t}` SET on_call=0 WHERE id=2")
        a.commit(); b.commit()
        ca.execute(f"SELECT SUM(on_call) FROM `{t}`"); total = ca.fetchone()[0]
        return f"write_skew: total on_call={total} (0=skew occurred)"
    finally:
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.rollback()
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.close()


def _probe_select_for_update():
    t, = _names(1, "cc")
    a = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        ca.execute(f"INSERT INTO `{t}` VALUES (1, 5)")
        a.begin()
        ca.execute(f"SELECT v FROM `{t}` WHERE id=1 FOR UPDATE")
        ca.fetchone()
        a.commit()
        return "select_for_update ok"
    finally:
        with contextlib.suppress(Exception):
            a.rollback()
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        with contextlib.suppress(Exception):
            a.close()


def _probe_deadlock():
    t1, t2 = _names(2, "cc")
    a = _fresh_conn()
    b = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t1}` (id INT PRIMARY KEY, v INT)")
        ca.execute(f"CREATE TABLE `{t2}` (id INT PRIMARY KEY, v INT)")
        ca.execute(f"INSERT INTO `{t1}` VALUES (1,1)")
        ca.execute(f"INSERT INTO `{t2}` VALUES (1,1)")
        cb = b.cursor()
        a.begin(); b.begin()
        ca.execute(f"UPDATE `{t1}` SET v=2 WHERE id=1")
        cb.execute(f"UPDATE `{t2}` SET v=2 WHERE id=1")
        # cross lock attempt — may deadlock or block
        ca.execute(f"UPDATE `{t2}` SET v=3 WHERE id=1")
        cb.execute(f"UPDATE `{t1}` SET v=3 WHERE id=1")
        a.commit(); b.commit()
        return "deadlock probe: no deadlock raised"
    finally:
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.rollback()
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t1}`")
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t2}`")
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.close()


def _probe_pool_exhaustion():
    conns = []
    try:
        for _ in range(8):
            conns.append(_fresh_conn())
        # verify each is usable
        for conn in conns:
            cur = conn.cursor()
            cur.execute("SELECT 1")
            cur.fetchone()
        return f"pool: opened {len(conns)} connections ok"
    finally:
        for conn in conns:
            with contextlib.suppress(Exception):
                conn.close()


def _probe_autocommit_visibility():
    t, = _names(1, "cc")
    a = _fresh_conn()
    b = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        ca.execute(f"INSERT INTO `{t}` VALUES (1, 1)")  # autocommit on
        cb = b.cursor()
        cb.execute(f"SELECT v FROM `{t}` WHERE id=1")
        row = cb.fetchone()
        if row is None or row[0] != 1:
            raise BehaviorMismatch("autocommit write not visible to other conn")
        return "autocommit visibility ok"
    finally:
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.close()


def _probe_rollback_isolation():
    t, = _names(1, "cc")
    a = _fresh_conn()
    b = _fresh_conn()
    try:
        ca = a.cursor()
        ca.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        ca.execute(f"INSERT INTO `{t}` VALUES (1, 1)")
        a.begin()
        ca.execute(f"UPDATE `{t}` SET v=99 WHERE id=1")
        a.rollback()
        cb = b.cursor()
        cb.execute(f"SELECT v FROM `{t}` WHERE id=1")
        row = cb.fetchone()
        if row[0] != 1:
            raise BehaviorMismatch(f"rollback did not restore: v={row[0]}")
        return "rollback isolation ok"
    finally:
        with contextlib.suppress(Exception):
            a.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in (a, b):
            with contextlib.suppress(Exception):
                conn.close()


def _probe_concurrent_update_same_row():
    t, = _names(1, "cc")
    conns = [_fresh_conn() for _ in range(3)]
    try:
        c0 = conns[0].cursor()
        c0.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, ctr INT)")
        c0.execute(f"INSERT INTO `{t}` VALUES (1, 0)")
        for conn in conns:
            cur = conn.cursor()
            cur.execute(f"UPDATE `{t}` SET ctr=ctr+1 WHERE id=1")
        c0.execute(f"SELECT ctr FROM `{t}` WHERE id=1")
        final = c0.fetchone()[0]
        return f"concurrent increment: ctr={final} (3=all applied)"
    finally:
        with contextlib.suppress(Exception):
            conns[0].cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
        for conn in conns:
            with contextlib.suppress(Exception):
                conn.close()


CONCURRENCY_PROBES = [
    "concurrent_inserts", "concurrent_readers", "lost_update", "write_skew",
    "select_for_update", "deadlock", "pool_exhaustion", "autocommit_visibility",
    "rollback_isolation", "concurrent_update_same_row",
]


# ===========================================================================
# DEEPER MATRIX 13: BULK / STREAMING / LOAD at scale.
# ===========================================================================

def _bulk_executemany(batch):
    def run():
        t, = _names(1, "blk")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT, s VARCHAR(32))")
            rows = [(i, i * 2, f"row{i}") for i in range(batch)]
            cur = _c().cursor()
            cur.executemany(
                f"INSERT INTO `{t}` (id, v, s) VALUES (%s, %s, %s)", rows)
            n = _scalar(f"SELECT COUNT(*) FROM `{t}`")
            if n != batch:
                raise BehaviorMismatch(
                    f"executemany {batch}: expected {batch}, got {n}")
        finally:
            _drop_all(t)
        return f"bulk executemany {batch}"
    return run


def _bulk_multivalue_insert(batch):
    def run():
        t, = _names(1, "blk")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
            vals = ",".join(f"({i},{i*3})" for i in range(batch))
            _exec(f"INSERT INTO `{t}` (id, v) VALUES {vals}")
            n = _scalar(f"SELECT COUNT(*) FROM `{t}`")
            if n != batch:
                raise BehaviorMismatch(
                    f"multivalue {batch}: expected {batch}, got {n}")
        finally:
            _drop_all(t)
        return f"bulk multivalue {batch}"
    return run


BULK_BATCH_SIZES = [10, 50, 100, 500, 1000, 2000, 5000, 10000]


def _streaming_sscursor(nrows):
    """Use a server-side cursor (SSCursor) to stream nrows."""
    def run():
        t, = _names(1, "stm")
        conn = _fresh_conn()
        try:
            cur = conn.cursor()
            cur.execute(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
            vals = ",".join(f"({i},{i})" for i in range(nrows))
            cur.execute(f"INSERT INTO `{t}` (id, v) VALUES {vals}")
            sscur = conn.cursor(pymysql.cursors.SSCursor)
            sscur.execute(f"SELECT id, v FROM `{t}` ORDER BY id")
            count = 0
            while True:
                batch = sscur.fetchmany(100)
                if not batch:
                    break
                count += len(batch)
            sscur.close()
            if count != nrows:
                raise BehaviorMismatch(
                    f"SSCursor stream: expected {nrows}, streamed {count}")
        finally:
            with contextlib.suppress(Exception):
                conn.cursor().execute(f"DROP TABLE IF EXISTS `{t}`")
            with contextlib.suppress(Exception):
                conn.close()
        return f"stream SSCursor {nrows}"
    return run


def _streaming_fetchmany(nrows, chunk):
    def run():
        t, = _names(1, "stm")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
            vals = ",".join(f"({i},{i})" for i in range(nrows))
            _exec(f"INSERT INTO `{t}` (id, v) VALUES {vals}")
            cur = _c().cursor()
            cur.execute(f"SELECT id FROM `{t}` ORDER BY id")
            total = 0
            while True:
                rows = cur.fetchmany(chunk)
                if not rows:
                    break
                total += len(rows)
            if total != nrows:
                raise BehaviorMismatch(
                    f"fetchmany {nrows}/{chunk}: got {total}")
        finally:
            _drop_all(t)
        return f"stream fetchmany {nrows}/{chunk}"
    return run


STREAM_SIZES = [1000, 10000, 50000, 100000]


def _wide_table(ncols):
    def run():
        t, = _names(1, "wide")
        try:
            cols = ", ".join(f"c{i} INT" for i in range(ncols))
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, {cols})")
            colnames = ", ".join(f"c{i}" for i in range(ncols))
            vals = ", ".join(str(i) for i in range(ncols))
            _exec(f"INSERT INTO `{t}` (id, {colnames}) VALUES (1, {vals})")
            row = _fetchone(f"SELECT * FROM `{t}` WHERE id=1")
            if row is None or len(row) != ncols + 1:
                raise BehaviorMismatch(
                    f"wide {ncols}: expected {ncols+1} cols, got "
                    f"{len(row) if row else 0}")
        finally:
            _drop_all(t)
        return f"wide table {ncols} cols"
    return run


WIDE_COL_COUNTS = [50, 100, 200, 300, 500]


def _large_value(label, sqltype, size):
    def run():
        t, = _names(1, "big")
        payload = "x" * size
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, c {sqltype})")
            _exec(f"INSERT INTO `{t}` (id, c) VALUES (1, %s)", (payload,))
            n = _scalar(f"SELECT LENGTH(c) FROM `{t}` WHERE id=1")
            if n != size:
                raise BehaviorMismatch(
                    f"large {label}: stored {n} bytes, expected {size}")
        finally:
            _drop_all(t)
        return f"large {label} {size}b"
    return run


LARGE_VALUE_CASES = [
    ("text_1k", "TEXT", 1000),
    ("text_64k", "TEXT", 60000),
    ("mediumtext_1m", "MEDIUMTEXT", 1000000),
    ("blob_1k", "BLOB", 1000),
    ("blob_64k", "BLOB", 60000),
    ("longblob_1m", "LONGBLOB", 1000000),
    ("longtext_4m", "LONGTEXT", 4000000),
    ("varchar_8k", "VARCHAR(10000)", 8000),
]


# ===========================================================================
# DEEPER MATRIX 14: ERROR HANDLING.
# ===========================================================================

def _err_duplicate_key():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        _exec(f"INSERT INTO `{t}` VALUES (1, 1)")
        try:
            _exec(f"INSERT INTO `{t}` VALUES (1, 2)")
        except pymysql.err.IntegrityError as e:
            code = e.args[0]
            if code != 1062:
                raise BehaviorMismatch(f"dup key: expected 1062, got {code}")
            return f"duplicate key -> {code}"
        raise BehaviorMismatch("duplicate key insert did not raise")
    finally:
        _drop_all(t)


def _err_unique_violation():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, u INT UNIQUE)")
        _exec(f"INSERT INTO `{t}` VALUES (1, 5)")
        try:
            _exec(f"INSERT INTO `{t}` VALUES (2, 5)")
        except pymysql.err.IntegrityError as e:
            return f"unique violation -> {e.args[0]}"
        raise BehaviorMismatch("unique violation did not raise")
    finally:
        _drop_all(t)


def _err_not_null_violation():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT NOT NULL)")
        try:
            _exec(f"INSERT INTO `{t}` (id) VALUES (1)")
        except pymysql.err.MySQLError as e:
            return f"not null -> {e.args[0]}"
        # MatrixOne may insert a default 0 instead of raising
        row = _fetchone(f"SELECT v FROM `{t}` WHERE id=1")
        return f"not null: no error, stored {row[0] if row else None}"
    finally:
        _drop_all(t)


def _err_check_violation():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, age INT CHECK(age>=0))")
        try:
            _exec(f"INSERT INTO `{t}` VALUES (1, -5)")
        except pymysql.err.MySQLError as e:
            return f"check -> {e.args[0]}"
        row = _fetchone(f"SELECT age FROM `{t}` WHERE id=1")
        return f"check: not enforced, stored {row[0] if row else None}"
    finally:
        _drop_all(t)


def _err_fk_violation():
    par, ch = _names(2, "err")
    try:
        _exec(f"CREATE TABLE `{par}` (id INT PRIMARY KEY)")
        _exec(f"CREATE TABLE `{ch}` (id INT PRIMARY KEY, pid INT, "
              f"FOREIGN KEY (pid) REFERENCES `{par}`(id))")
        try:
            _exec(f"INSERT INTO `{ch}` VALUES (1, 999)")
        except pymysql.err.MySQLError as e:
            return f"fk violation -> {e.args[0]}"
        return "fk: not enforced"
    finally:
        _drop_all(ch, par)


def _err_unknown_column():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY)")
        try:
            _exec(f"SELECT nonexistent FROM `{t}`")
        except pymysql.err.MySQLError as e:
            return f"unknown column -> {e.args[0]}"
        raise BehaviorMismatch("unknown column did not raise")
    finally:
        _drop_all(t)


def _err_table_not_exist():
    try:
        _exec(f"SELECT * FROM nonexistent_table_{M.uname('x')}")
    except pymysql.err.MySQLError as e:
        return f"table not exist -> {e.args[0]}"
    raise BehaviorMismatch("missing table did not raise")


def _err_div_by_zero():
    row = _fetchone("SELECT 1/0, 1 DIV 0, 1 % 0")
    return f"div by zero -> {row}"


def _err_invalid_date():
    t, = _names(1, "err")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, d DATE)")
        try:
            _exec(f"INSERT INTO `{t}` VALUES (1, '2026-02-30')")
        except pymysql.err.MySQLError as e:
            return f"invalid date -> {e.args[0]}"
        row = _fetchone(f"SELECT d FROM `{t}` WHERE id=1")
        return f"invalid date: stored {row[0] if row else None}"
    finally:
        _drop_all(t)


def _err_reconnect_after_error():
    """Trigger an error, then verify the connection still works (reconnect)."""
    with contextlib.suppress(pymysql.err.MySQLError):
        _exec("SELECT * FROM definitely_missing_table_xyz")
    row = _fetchone("SELECT 1")
    if row[0] != 1:
        raise BehaviorMismatch("connection unusable after error")
    return "reconnect after error ok"


def _err_statement_after_syntax_error():
    with contextlib.suppress(pymysql.err.MySQLError):
        _exec("SELCT bad syntax")
    row = _fetchone("SELECT 2+2")
    if row[0] != 4:
        raise BehaviorMismatch("connection broken after syntax error")
    return "recovered after syntax error"


ERROR_SCENARIOS = [
    ("duplicate_key", _err_duplicate_key),
    ("unique_violation", _err_unique_violation),
    ("not_null_violation", _err_not_null_violation),
    ("check_violation", _err_check_violation),
    ("fk_violation", _err_fk_violation),
    ("unknown_column", _err_unknown_column),
    ("table_not_exist", _err_table_not_exist),
    ("div_by_zero", _err_div_by_zero),
    ("invalid_date", _err_invalid_date),
    ("reconnect_after_error", _err_reconnect_after_error),
    ("recover_after_syntax_error", _err_statement_after_syntax_error),
]


# ===========================================================================
# DEEPER MATRIX 15: DDL SURFACE — views, procs, triggers, events, partitioning,
# FK actions, check constraints.
# ===========================================================================

def _ddl_view_simple():
    t, v = _names(2, "ddl")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        _exec(f"INSERT INTO `{t}` VALUES (1,10),(2,20)")
        _exec(f"CREATE VIEW `{v}` AS SELECT id, v*2 dbl FROM `{t}`")
        _fetchall(f"SELECT * FROM `{v}`")
        return "view simple ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v}`")
        _drop_all(t)


def _ddl_view_join():
    a, b, v = _names(3, "ddl")
    try:
        _exec(f"CREATE TABLE `{a}` (id INT PRIMARY KEY, name VARCHAR(16))")
        _exec(f"CREATE TABLE `{b}` (id INT PRIMARY KEY, aid INT, val INT)")
        _exec(f"INSERT INTO `{a}` VALUES (1,'x')")
        _exec(f"INSERT INTO `{b}` VALUES (1,1,5)")
        _exec(f"CREATE VIEW `{v}` AS SELECT a.name, b.val FROM `{a}` a "
              f"JOIN `{b}` b ON b.aid=a.id")
        _fetchall(f"SELECT * FROM `{v}`")
        return "view join ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v}`")
        _drop_all(a, b)


def _ddl_view_aggregate():
    t, v = _names(2, "ddl")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, g INT, v INT)")
        _exec(f"INSERT INTO `{t}` VALUES (1,1,10),(2,1,20),(3,2,30)")
        _exec(f"CREATE VIEW `{v}` AS SELECT g, SUM(v) tot FROM `{t}` GROUP BY g")
        _fetchall(f"SELECT * FROM `{v}` ORDER BY g")
        return "view aggregate ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v}`")
        _drop_all(t)


def _ddl_view_updatable():
    t, v = _names(2, "ddl")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        _exec(f"INSERT INTO `{t}` VALUES (1,10)")
        _exec(f"CREATE VIEW `{v}` AS SELECT id, v FROM `{t}` WHERE v>5")
        # MatrixOne does not allow writes through views — expect a finding
        _exec(f"UPDATE `{v}` SET v=99 WHERE id=1")
        return "view updatable: write accepted"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v}`")
        _drop_all(t)


def _ddl_view_nested():
    t, v1, v2 = _names(3, "ddl")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        _exec(f"INSERT INTO `{t}` VALUES (1,10),(2,20)")
        _exec(f"CREATE VIEW `{v1}` AS SELECT id, v FROM `{t}` WHERE v>5")
        _exec(f"CREATE VIEW `{v2}` AS SELECT id, v*10 b FROM `{v1}`")
        _fetchall(f"SELECT * FROM `{v2}`")
        return "view nested ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v2}`")
        with contextlib.suppress(Exception):
            _exec(f"DROP VIEW IF EXISTS `{v1}`")
        _drop_all(t)


def _ddl_stored_procedure():
    p = M.uname("proc")
    try:
        _exec(f"CREATE PROCEDURE `{p}`() BEGIN SELECT 1; END")
        _exec(f"CALL `{p}`()")
        return "stored procedure ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP PROCEDURE IF EXISTS `{p}`")


def _ddl_stored_function():
    f = M.uname("fn")
    try:
        _exec(f"CREATE FUNCTION `{f}`(x INT) RETURNS INT DETERMINISTIC "
              f"RETURN x*2")
        _fetchone(f"SELECT `{f}`(21)")
        return "stored function ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP FUNCTION IF EXISTS `{f}`")


def _ddl_trigger():
    t, log = _names(2, "ddl")
    trg = M.uname("trg")
    try:
        _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, v INT)")
        _exec(f"CREATE TABLE `{log}` (id INT PRIMARY KEY AUTO_INCREMENT, msg VARCHAR(32))")
        _exec(f"CREATE TRIGGER `{trg}` AFTER INSERT ON `{t}` FOR EACH ROW "
              f"INSERT INTO `{log}` (msg) VALUES ('inserted')")
        _exec(f"INSERT INTO `{t}` VALUES (1, 5)")
        _fetchall(f"SELECT * FROM `{log}`")
        return "trigger ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP TRIGGER IF EXISTS `{trg}`")
        _drop_all(t, log)


def _ddl_event():
    e = M.uname("evt")
    try:
        _exec(f"CREATE EVENT `{e}` ON SCHEDULE EVERY 1 HOUR "
              f"DO SELECT 1")
        return "event ok"
    finally:
        with contextlib.suppress(Exception):
            _exec(f"DROP EVENT IF EXISTS `{e}`")


def _ddl_partition(kind, clause):
    def run():
        t, = _names(1, "part")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT, dt DATE, v INT) {clause}")
            _exec(f"INSERT INTO `{t}` VALUES (1,'2025-01-01',10),(2,'2025-06-01',20)")
            _fetchall(f"SELECT * FROM `{t}`")
            return f"partition {kind} ok"
        finally:
            _drop_all(t)
    return run


PARTITION_CASES = [
    ("hash", "PARTITION BY HASH(id) PARTITIONS 4"),
    ("key", "PARTITION BY KEY(id) PARTITIONS 4"),
    ("range", "PARTITION BY RANGE(id) ("
     "PARTITION p0 VALUES LESS THAN (10), "
     "PARTITION p1 VALUES LESS THAN (100), "
     "PARTITION p2 VALUES LESS THAN MAXVALUE)"),
    ("range_columns", "PARTITION BY RANGE COLUMNS(dt) ("
     "PARTITION p0 VALUES LESS THAN ('2025-04-01'), "
     "PARTITION p1 VALUES LESS THAN ('2026-01-01'))"),
    ("list", "PARTITION BY LIST(id) ("
     "PARTITION p0 VALUES IN (1,2,3), "
     "PARTITION p1 VALUES IN (4,5,6))"),
    ("range_year", "PARTITION BY RANGE(YEAR(dt)) ("
     "PARTITION p0 VALUES LESS THAN (2025), "
     "PARTITION p1 VALUES LESS THAN (2027))"),
]


def _ddl_fk_action(action_label, on_clause):
    def run():
        par, ch = _names(2, "fk")
        try:
            _exec(f"CREATE TABLE `{par}` (id INT PRIMARY KEY, name VARCHAR(16))")
            _exec(f"CREATE TABLE `{ch}` (id INT PRIMARY KEY, pid INT, "
                  f"FOREIGN KEY (pid) REFERENCES `{par}`(id) {on_clause})")
            _exec(f"INSERT INTO `{par}` VALUES (1,'a')")
            _exec(f"INSERT INTO `{ch}` VALUES (1, 1)")
            # exercise the action
            if "DELETE" in on_clause:
                _exec(f"DELETE FROM `{par}` WHERE id=1")
            else:
                _exec(f"UPDATE `{par}` SET id=2 WHERE id=1")
            _fetchall(f"SELECT pid FROM `{ch}`")
            return f"fk action {action_label} ok"
        finally:
            _drop_all(ch, par)
    return run


FK_ACTIONS = [
    ("on_delete_cascade", "ON DELETE CASCADE"),
    ("on_delete_set_null", "ON DELETE SET NULL"),
    ("on_delete_restrict", "ON DELETE RESTRICT"),
    ("on_delete_no_action", "ON DELETE NO ACTION"),
    ("on_update_cascade", "ON UPDATE CASCADE"),
    ("on_update_set_null", "ON UPDATE SET NULL"),
    ("on_update_restrict", "ON UPDATE RESTRICT"),
    ("on_delete_cascade_on_update_cascade",
     "ON DELETE CASCADE ON UPDATE CASCADE"),
]


def _ddl_check_constraint(label, coldef, good, bad):
    def run():
        t, = _names(1, "chk")
        try:
            _exec(f"CREATE TABLE `{t}` (id INT PRIMARY KEY, {coldef})")
            _exec(f"INSERT INTO `{t}` VALUES (1, {good})")
            try:
                _exec(f"INSERT INTO `{t}` VALUES (2, {bad})")
                return f"check {label}: bad value accepted (not enforced)"
            except pymysql.err.MySQLError as e:
                return f"check {label}: rejected bad value -> {e.args[0]}"
        finally:
            _drop_all(t)
    return run


CHECK_CONSTRAINTS = [
    ("positive", "n INT CHECK(n > 0)", "5", "-1"),
    ("range", "age INT CHECK(age BETWEEN 0 AND 150)", "30", "200"),
    ("enum_like", "s VARCHAR(8) CHECK(s IN ('a','b','c'))", "'a'", "'z'"),
    ("not_empty", "name VARCHAR(32) CHECK(CHAR_LENGTH(name) > 0)", "'x'", "''"),
    ("named", "v INT, CONSTRAINT ck1 CHECK(v < 100)", "50", "150"),
    ("multi_col", "lo INT, hi INT, CHECK(lo <= hi)", "1, 2", "5, 2"),
]


# ===========================================================================
# REGISTRATION
# ===========================================================================

def register(runner):
    # --- Workload 1: e-commerce ---
    for label, sql in ECOMMERCE_QUERIES:
        runner.add(FW, "ecommerce", label, _ecommerce_query(label, sql))

    # --- Workload 2: social graph ---
    for label, sql in SOCIAL_QUERIES:
        runner.add(FW, "social_graph", label, _social_query(label, sql))

    # --- Workload 3: OLAP / analytics ---
    for label, sql in OLAP_QUERIES:
        runner.add(FW, "olap", label, _olap_query(label, sql))

    # --- Workload 4: time-series / IoT ---
    for label, sql in TIMESERIES_QUERIES:
        runner.add(FW, "timeseries", label, _timeseries_query(label, sql))

    # --- Workload 5: financial ledger ---
    for label, sql in LEDGER_QUERIES:
        runner.add(FW, "financial_ledger", label, _ledger_query(label, sql))

    # --- Workload 6: multi-tenant SaaS ---
    for label, sql in SAAS_QUERIES:
        runner.add(FW, "multitenant", label, _saas_query(label, sql))

    # --- Workload 7: CMS ---
    for label, sql in CMS_QUERIES:
        runner.add(FW, "cms", label, _cms_query(label, sql))

    # --- Workload 8: geo ---
    for label, sql in GEO_QUERIES:
        runner.add(FW, "geo", label, _geo_query(label, sql))

    # --- Deeper matrix 1: full DECIMAL precision/scale grid ---
    for p, s in DECIMAL_GRID:
        runner.add(FW, "decimal_declare", f"decimal_{p}_{s}", _decimal_declare(p, s))
        runner.add(FW, "decimal_roundtrip", f"decimal_{p}_{s}", _decimal_roundtrip(p, s))
        runner.add(FW, "decimal_arith", f"decimal_{p}_{s}", _decimal_arith(p, s))

    # --- Deeper matrix 2: temporal precision + tz ---
    for label, sqltype, value in TEMPORAL_PRECISION:
        runner.add(FW, "temporal_roundtrip", label,
                   _temporal_roundtrip(label, sqltype, value))
        runner.add(FW, "temporal_fracsec", label,
                   _temporal_fracsec_truncation(label, sqltype, value))
    for label, expr in TZ_CONVERSIONS:
        runner.add(FW, "timezone", label, _tz_query(label, expr))

    # --- Deeper matrix 3: charset / collation grid ---
    for cs in CHARSETS:
        runner.add(FW, "charset", cs, _charset_create(cs))
    for coll, cs in COLLATIONS_GRID:
        runner.add(FW, "collation_create", coll, _collation_create(coll, cs))
        runner.add(FW, "collation_case", coll, _collation_case_sensitivity(coll, cs))
        runner.add(FW, "collation_orderby", coll, _collation_orderby(coll, cs))

    # --- Deeper matrix 4: NULL / three-valued logic ---
    for label, expr, expected in THREE_VALUED_LOGIC:
        runner.add(FW, "three_valued_logic", label,
                   _three_valued_logic(label, expr, expected))
    for label, sqltype, value, quoted in M.INDEXABLE_TYPES:
        runner.add(FW, "null_aggregate", label,
                   _null_in_aggregate(label, sqltype, value, quoted))
        runner.add(FW, "null_ordering", label,
                   _null_ordering(label, sqltype, value, quoted))

    # --- Deeper matrix 5: numeric overflow / coercion ---
    for label, sqltype, value in OVERFLOW_CASES:
        runner.add(FW, "numeric_overflow", label,
                   _overflow_case(label, sqltype, value))
    for label, expr in COERCION_CASES:
        runner.add(FW, "type_coercion", label, _coercion_case(label, expr))

    # --- Deeper matrix 6: ENUM / SET edges ---
    for label, coltype, value, op in ENUM_SET_CASES:
        runner.add(FW, "enum_set_edge", label,
                   _enum_set_case(label, coltype, value, op))

    # --- Deeper matrix 7: BIT ---
    for label, sqltype, value in BIT_CASES:
        runner.add(FW, "bit_roundtrip", label, _bit_roundtrip(label, sqltype, value))
        runner.add(FW, "bit_ops", label, _bit_ops(label, sqltype, value))

    # --- Deeper matrix 8: JSON deep paths + ops ---
    for label, path in JSON_PATHS:
        runner.add(FW, "json_path_extract", label, _json_path_extract(label, path))
        runner.add(FW, "json_arrow", label, _json_arrow_op(label, path))
        runner.add(FW, "json_where", label, _json_where_filter(label, path))
    for label, expr in JSON_OPS_DEEP:
        runner.add(FW, "json_op_deep", label, _json_op_on_col(label, expr))

    # --- Deeper matrix 9: VECTOR ---
    for vtype in VEC_TYPES:
        for dim in VEC_DIMS:
            runner.add(FW, "vector_declare", f"{vtype}_{dim}",
                       _vec_declare(vtype, dim))
            runner.add(FW, "vector_arith", f"{vtype}_{dim}",
                       _vec_arithmetic(vtype, dim))
            runner.add(FW, "vector_aggregate", f"{vtype}_{dim}",
                       _vec_aggregate(vtype, dim))
            runner.add(FW, "vector_hybrid", f"{vtype}_{dim}",
                       _vec_hybrid_filter(vtype, dim))
            for fnlabel, fn in VEC_DISTANCE_FNS:
                runner.add(FW, "vector_distance", f"{vtype}_{dim}_{fnlabel}",
                           _vec_distance(vtype, dim, fnlabel, fn))
    # IVFFLAT index over a representative subset of dims/op-types
    for vtype in VEC_TYPES:
        for dim in (3, 8, 32, 128, 512):
            for optype in ("vector_l2_ops", "vector_ip_ops", "vector_cosine_ops"):
                runner.add(FW, "vector_ivfflat",
                           f"{vtype}_{dim}_{optype}",
                           _vec_ivfflat_index(vtype, dim, optype))

    # --- Deeper matrix 10: full-text ---
    for label, sql in FULLTEXT_QUERIES:
        runner.add(FW, "fulltext", label, _fulltext_query(label, sql))

    # --- Deeper matrix 11: generated columns ---
    for label, coldef, vals, cols in GENERATED_COLUMN_CASES:
        runner.add(FW, "generated_column", label,
                   _generated_column(label, coldef, vals, cols))
        runner.add(FW, "generated_column_update", label,
                   _generated_column_update(label, coldef, vals, cols))

    # --- Deeper matrix 12: concurrency & isolation ---
    for level in ISOLATION_LEVELS:
        runner.add(FW, "isolation_set", level.replace(" ", "_").lower(),
                   _isolation_set_and_read(level))
        runner.add(FW, "isolation_visibility", level.replace(" ", "_").lower(),
                   _isolation_visibility(level))
    for name in CONCURRENCY_PROBES:
        runner.add(FW, "concurrency", name, _concurrency_probe(name))

    # --- Deeper matrix 13: bulk / streaming / load ---
    for batch in BULK_BATCH_SIZES:
        runner.add(FW, "bulk_executemany", f"batch_{batch}", _bulk_executemany(batch))
        runner.add(FW, "bulk_multivalue", f"batch_{batch}",
                   _bulk_multivalue_insert(batch))
    for nrows in STREAM_SIZES:
        runner.add(FW, "stream_sscursor", f"rows_{nrows}",
                   _streaming_sscursor(nrows))
        runner.add(FW, "stream_fetchmany", f"rows_{nrows}",
                   _streaming_fetchmany(nrows, 1000))
    for ncols in WIDE_COL_COUNTS:
        runner.add(FW, "wide_table", f"cols_{ncols}", _wide_table(ncols))
    for label, sqltype, size in LARGE_VALUE_CASES:
        runner.add(FW, "large_value", label, _large_value(label, sqltype, size))

    # --- Deeper matrix 14: error handling ---
    for name, fn in ERROR_SCENARIOS:
        runner.add(FW, "error_handling", name, fn)

    # --- Deeper matrix 15: DDL surface ---
    runner.add(FW, "ddl_view", "view_simple", _ddl_view_simple)
    runner.add(FW, "ddl_view", "view_join", _ddl_view_join)
    runner.add(FW, "ddl_view", "view_aggregate", _ddl_view_aggregate)
    runner.add(FW, "ddl_view", "view_updatable", _ddl_view_updatable)
    runner.add(FW, "ddl_view", "view_nested", _ddl_view_nested)
    runner.add(FW, "ddl_routine", "stored_procedure", _ddl_stored_procedure)
    runner.add(FW, "ddl_routine", "stored_function", _ddl_stored_function)
    runner.add(FW, "ddl_routine", "trigger", _ddl_trigger)
    runner.add(FW, "ddl_routine", "event", _ddl_event)
    for kind, clause in PARTITION_CASES:
        runner.add(FW, "ddl_partition", kind, _ddl_partition(kind, clause))
    for label, on_clause in FK_ACTIONS:
        runner.add(FW, "ddl_fk_action", label, _ddl_fk_action(label, on_clause))
    for label, coldef, good, bad in CHECK_CONSTRAINTS:
        runner.add(FW, "ddl_check_constraint", label,
                   _ddl_check_constraint(label, coldef, good, bad))
