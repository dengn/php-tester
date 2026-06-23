package mo.workload;

import mo.harness.Assert;
import mo.harness.BehaviorMismatch;
import mo.harness.Db;
import mo.harness.Runner;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Real-world application workloads: representative schemas plus their queries.
 * All registered under framework "jdbc" so they share the JDBC database namespace.
 * Categories: workload_ecommerce, workload_social, workload_olap, workload_timeseries,
 * workload_ledger, workload_saas, workload_cms, workload_geo.
 */
public final class WorkloadModule {

    private final String fw;
    private final String dbName;

    public WorkloadModule(String fw, String dbName) {
        this.fw = fw;
        this.dbName = dbName;
    }

    public void register(Runner r, Db db) {
        ecommerce(r, db);
        social(r, db);
        olap(r, db);
        timeseries(r, db);
        ledger(r, db);
        saas(r, db);
        cms(r, db);
        geo(r, db);
    }

    // ------------------------------------------------------- e-commerce ----

    private void ecommerce(Runner r, Db db) {
        final String cat = "workload_ecommerce";
        // A self-contained scenario per query against a freshly-built shop schema.
        record Q(String name, String sql, java.util.function.BiConsumer<Connection, ResultSet> check) {}
        String[][] queries = {
                {"top_products_by_revenue",
                        "SELECT p.name, SUM(oi.qty * oi.unit_price) rev FROM order_items oi "
                        + "JOIN products p ON p.id = oi.product_id GROUP BY p.id, p.name ORDER BY rev DESC LIMIT 5"},
                {"customer_order_count",
                        "SELECT c.name, COUNT(o.id) n FROM customers c LEFT JOIN orders o ON o.customer_id = c.id "
                        + "GROUP BY c.id, c.name ORDER BY n DESC"},
                {"avg_order_value",
                        "SELECT AVG(t.total) FROM (SELECT o.id, SUM(oi.qty*oi.unit_price) total FROM orders o "
                        + "JOIN order_items oi ON oi.order_id = o.id GROUP BY o.id) t"},
                {"products_never_ordered",
                        "SELECT p.id FROM products p WHERE NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.product_id = p.id)"},
                {"orders_in_status",
                        "SELECT COUNT(*) FROM orders WHERE status = 'shipped'"},
                {"revenue_by_category",
                        "SELECT p.category, SUM(oi.qty*oi.unit_price) rev FROM order_items oi "
                        + "JOIN products p ON p.id = oi.product_id GROUP BY p.category"},
                {"repeat_customers",
                        "SELECT customer_id FROM orders GROUP BY customer_id HAVING COUNT(*) > 1"},
                {"low_stock_products",
                        "SELECT id, stock FROM products WHERE stock < 10 ORDER BY stock"},
                {"order_with_items_join",
                        "SELECT o.id, p.name, oi.qty FROM orders o JOIN order_items oi ON oi.order_id=o.id "
                        + "JOIN products p ON p.id=oi.product_id ORDER BY o.id"},
                {"cart_total_window",
                        "SELECT order_id, qty*unit_price line, SUM(qty*unit_price) OVER (PARTITION BY order_id) cart "
                        + "FROM order_items"},
                {"discount_case_expr",
                        "SELECT id, CASE WHEN stock > 50 THEN price*0.9 ELSE price END eff FROM products"},
                {"recent_orders_pagination",
                        "SELECT id FROM orders ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET 0"},
                {"customer_lifetime_value",
                        "SELECT o.customer_id, SUM(oi.qty*oi.unit_price) ltv FROM orders o "
                        + "JOIN order_items oi ON oi.order_id=o.id GROUP BY o.customer_id ORDER BY ltv DESC"},
                {"product_rank_in_category",
                        "SELECT id, category, RANK() OVER (PARTITION BY category ORDER BY price DESC) rk FROM products"},
                {"upsert_inventory",
                        "INSERT INTO products (id, name, category, price, stock) VALUES (1,'P1','c',1.0,5) "
                        + "ON DUPLICATE KEY UPDATE stock = stock + 1"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    String[] tabs = buildShop(c);
                    try {
                        boolean hasRs = st.execute(q[1]);
                        if (hasRs) { try (ResultSet rs = st.getResultSet()) { while (rs.next()) {} } }
                    } finally { dropAll(st, tabs); }
                }
            });
        }
    }

    private String[] buildShop(Connection c) throws Exception {
        String customers = Db.uniq("customers");
        String products = Db.uniq("products");
        String orders = Db.uniq("orders");
        String orderItems = Db.uniq("order_items");
        // Use stable names referenced in queries by aliasing through views is overkill;
        // instead queries reference fixed names, so we create with fixed names in this DB.
        try (Statement st = c.createStatement()) {
            st.execute("DROP TABLE IF EXISTS order_items");
            st.execute("DROP TABLE IF EXISTS orders");
            st.execute("DROP TABLE IF EXISTS products");
            st.execute("DROP TABLE IF EXISTS customers");
            st.execute("CREATE TABLE customers (id INT PRIMARY KEY, name VARCHAR(50), email VARCHAR(80))");
            st.execute("CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(50), category VARCHAR(20), "
                    + "price DECIMAL(10,2), stock INT)");
            st.execute("CREATE TABLE orders (id INT PRIMARY KEY, customer_id INT, status VARCHAR(20), "
                    + "created_at DATETIME)");
            st.execute("CREATE TABLE order_items (id INT PRIMARY KEY, order_id INT, product_id INT, qty INT, "
                    + "unit_price DECIMAL(10,2))");
            st.execute("INSERT INTO customers VALUES (1,'Ann','a@x'),(2,'Bob','b@x'),(3,'Cy','c@x')");
            st.execute("INSERT INTO products VALUES (1,'Widget','tools',9.99,100),(2,'Gadget','tools',19.99,5),"
                    + "(3,'Book','media',4.50,40),(4,'Pen','office',1.20,200)");
            st.execute("INSERT INTO orders VALUES (1,1,'shipped','2024-01-01 10:00'),(2,1,'pending','2024-02-01 11:00'),"
                    + "(3,2,'shipped','2024-03-01 12:00')");
            st.execute("INSERT INTO order_items VALUES (1,1,1,2,9.99),(2,1,3,1,4.50),(3,2,2,1,19.99),(4,3,4,5,1.20)");
        }
        return new String[]{orderItems, orders, products, customers,
                "order_items", "orders", "products", "customers"};
    }

    private void dropAll(Statement st, String[] tabs) {
        // drop in FK-safe order: children first
        Db.quiet(st, "DROP TABLE IF EXISTS order_items");
        Db.quiet(st, "DROP TABLE IF EXISTS orders");
        Db.quiet(st, "DROP TABLE IF EXISTS products");
        Db.quiet(st, "DROP TABLE IF EXISTS customers");
    }

    // ----------------------------------------------------- social graph ----

    private void social(Runner r, Db db) {
        final String cat = "workload_social";
        String[][] queries = {
                {"mutual_follows",
                        "SELECT a.follower_id, a.followee_id FROM follows a JOIN follows b "
                        + "ON a.follower_id = b.followee_id AND a.followee_id = b.follower_id"},
                {"follower_count",
                        "SELECT followee_id, COUNT(*) n FROM follows GROUP BY followee_id ORDER BY n DESC"},
                {"suggested_follows_2hop",
                        "SELECT DISTINCT f2.followee_id FROM follows f1 JOIN follows f2 "
                        + "ON f1.followee_id = f2.follower_id WHERE f1.follower_id = 1 AND f2.followee_id <> 1"},
                {"feed_from_followees",
                        "SELECT p.id, p.body FROM posts p JOIN follows f ON f.followee_id = p.user_id "
                        + "WHERE f.follower_id = 1 ORDER BY p.created_at DESC"},
                {"not_following_back",
                        "SELECT f.follower_id, f.followee_id FROM follows f WHERE NOT EXISTS "
                        + "(SELECT 1 FROM follows g WHERE g.follower_id = f.followee_id AND g.followee_id = f.follower_id)"},
                {"most_active_posters",
                        "SELECT user_id, COUNT(*) n FROM posts GROUP BY user_id HAVING COUNT(*) >= 1 ORDER BY n DESC"},
                {"like_count_per_post",
                        "SELECT p.id, COUNT(l.user_id) likes FROM posts p LEFT JOIN likes l ON l.post_id = p.id "
                        + "GROUP BY p.id"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildSocial(c);
                    try {
                        try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} }
                    } finally { dropSocial(st); }
                }
            });
        }
        // Recursive CTE: follower tree / reachability
        r.register(fw, cat, "recursive_follower_tree", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildSocial(c);
                try (ResultSet rs = st.executeQuery(
                        "WITH RECURSIVE reach(uid, depth) AS ("
                        + " SELECT followee_id, 1 FROM follows WHERE follower_id = 1"
                        + " UNION ALL"
                        + " SELECT f.followee_id, r.depth + 1 FROM follows f JOIN reach r ON f.follower_id = r.uid"
                        + " WHERE r.depth < 5)"
                        + " SELECT DISTINCT uid FROM reach")) {
                    int n = 0; while (rs.next()) n++;
                    Assert.isTrue(n >= 1, "recursive reachability returned nodes");
                } finally { dropSocial(st); }
            }
        });
        r.register(fw, cat, "recursive_shortest_path_depth", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildSocial(c);
                try (ResultSet rs = st.executeQuery(
                        "WITH RECURSIVE reach(uid, depth) AS ("
                        + " SELECT followee_id, 1 FROM follows WHERE follower_id = 1"
                        + " UNION ALL"
                        + " SELECT f.followee_id, r.depth + 1 FROM follows f JOIN reach r ON f.follower_id = r.uid"
                        + " WHERE r.depth < 5)"
                        + " SELECT uid, MIN(depth) FROM reach GROUP BY uid")) {
                    while (rs.next()) {}
                } finally { dropSocial(st); }
            }
        });
        r.register(fw, cat, "recursive_friend_of_friend_count", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildSocial(c);
                try (ResultSet rs = st.executeQuery(
                        "WITH RECURSIVE reach(uid, depth) AS ("
                        + " SELECT followee_id, 1 FROM follows WHERE follower_id = 1"
                        + " UNION ALL"
                        + " SELECT f.followee_id, r.depth + 1 FROM follows f JOIN reach r ON f.follower_id = r.uid"
                        + " WHERE r.depth < 3)"
                        + " SELECT depth, COUNT(DISTINCT uid) FROM reach GROUP BY depth ORDER BY depth")) {
                    while (rs.next()) {}
                } finally { dropSocial(st); }
            }
        });
    }

    private void buildSocial(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            dropSocial(st);
            st.execute("CREATE TABLE users (id INT PRIMARY KEY, handle VARCHAR(40))");
            st.execute("CREATE TABLE follows (follower_id INT, followee_id INT, PRIMARY KEY (follower_id, followee_id))");
            st.execute("CREATE TABLE posts (id INT PRIMARY KEY, user_id INT, body VARCHAR(200), created_at DATETIME)");
            st.execute("CREATE TABLE likes (post_id INT, user_id INT, PRIMARY KEY (post_id, user_id))");
            st.execute("INSERT INTO users VALUES (1,'a'),(2,'b'),(3,'c'),(4,'d'),(5,'e')");
            st.execute("INSERT INTO follows VALUES (1,2),(2,3),(3,4),(4,5),(2,1),(1,3),(5,1)");
            st.execute("INSERT INTO posts VALUES (1,2,'hi','2024-01-01'),(2,3,'yo','2024-01-02'),(3,2,'again','2024-01-03')");
            st.execute("INSERT INTO likes VALUES (1,1),(1,3),(2,1)");
        }
    }

    private void dropSocial(Statement st) {
        Db.quiet(st, "DROP TABLE IF EXISTS likes");
        Db.quiet(st, "DROP TABLE IF EXISTS posts");
        Db.quiet(st, "DROP TABLE IF EXISTS follows");
        Db.quiet(st, "DROP TABLE IF EXISTS users");
    }

    // ----------------------------------------------- analytics / OLAP ----

    private void olap(Runner r, Db db) {
        final String cat = "workload_olap";
        // Star schema: fact_sales + dim_date, dim_product, dim_store.
        String[][] queries = {
                {"rollup_region", "SELECT s.region, SUM(f.amount) FROM fact_sales f JOIN dim_store s "
                        + "ON s.id=f.store_id GROUP BY s.region WITH ROLLUP"},
                {"rollup_region_product", "SELECT s.region, p.category, SUM(f.amount) FROM fact_sales f "
                        + "JOIN dim_store s ON s.id=f.store_id JOIN dim_product p ON p.id=f.product_id "
                        + "GROUP BY s.region, p.category WITH ROLLUP"},
                {"grouping_sets", "SELECT s.region, p.category, SUM(f.amount) FROM fact_sales f "
                        + "JOIN dim_store s ON s.id=f.store_id JOIN dim_product p ON p.id=f.product_id "
                        + "GROUP BY GROUPING SETS ((s.region),(p.category),())"},
                {"cube", "SELECT s.region, p.category, SUM(f.amount) FROM fact_sales f "
                        + "JOIN dim_store s ON s.id=f.store_id JOIN dim_product p ON p.id=f.product_id "
                        + "GROUP BY CUBE(s.region, p.category)"},
                {"grouping_function", "SELECT s.region, GROUPING(s.region) g, SUM(f.amount) FROM fact_sales f "
                        + "JOIN dim_store s ON s.id=f.store_id GROUP BY s.region WITH ROLLUP"},
                {"running_total_window", "SELECT d.d, SUM(f.amount) OVER (ORDER BY d.d ROWS UNBOUNDED PRECEDING) "
                        + "FROM fact_sales f JOIN dim_date d ON d.id=f.date_id"},
                {"moving_avg_window", "SELECT d.d, AVG(f.amount) OVER (ORDER BY d.d ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) "
                        + "FROM fact_sales f JOIN dim_date d ON d.id=f.date_id"},
                {"rank_stores", "SELECT s.name, SUM(f.amount) tot, RANK() OVER (ORDER BY SUM(f.amount) DESC) rk "
                        + "FROM fact_sales f JOIN dim_store s ON s.id=f.store_id GROUP BY s.id, s.name"},
                {"pct_of_total", "SELECT p.category, SUM(f.amount) / SUM(SUM(f.amount)) OVER () pct "
                        + "FROM fact_sales f JOIN dim_product p ON p.id=f.product_id GROUP BY p.category"},
                {"yoy_lag", "SELECT d.yr, SUM(f.amount) amt, LAG(SUM(f.amount)) OVER (ORDER BY d.yr) prev "
                        + "FROM fact_sales f JOIN dim_date d ON d.id=f.date_id GROUP BY d.yr"},
                {"ntile_quartiles", "SELECT store_id, NTILE(4) OVER (ORDER BY amount) q FROM fact_sales"},
                {"top_n_per_group", "SELECT * FROM (SELECT s.region, f.amount, "
                        + "ROW_NUMBER() OVER (PARTITION BY s.region ORDER BY f.amount DESC) rn "
                        + "FROM fact_sales f JOIN dim_store s ON s.id=f.store_id) t WHERE rn <= 2"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildStar(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { dropStar(st); }
                }
            });
        }
        // KNOWN finding: GROUP BY ... WITH ROLLUP over a SELECT alias fails to resolve.
        r.register(fw, cat, "rollup_alias_resolution_finding", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildStar(c);
                try {
                    boolean ok = true;
                    try {
                        st.executeQuery("SELECT region AS g, SUM(amount) FROM (SELECT s.region region, f.amount amount "
                                + "FROM fact_sales f JOIN dim_store s ON s.id=f.store_id) x GROUP BY g WITH ROLLUP");
                    } catch (Exception e) { ok = false; }
                    if (!ok) {
                        throw new BehaviorMismatch("GROUP BY alias WITH ROLLUP",
                                "alias 'g' resolvable in GROUP BY (MySQL)", "column g does not exist (MatrixOne)");
                    }
                } finally { dropStar(st); }
            }
        });
    }

    private void buildStar(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            dropStar(st);
            st.execute("CREATE TABLE dim_date (id INT PRIMARY KEY, d DATE, yr INT, mo INT)");
            st.execute("CREATE TABLE dim_product (id INT PRIMARY KEY, name VARCHAR(40), category VARCHAR(20))");
            st.execute("CREATE TABLE dim_store (id INT PRIMARY KEY, name VARCHAR(40), region VARCHAR(20))");
            st.execute("CREATE TABLE fact_sales (id INT PRIMARY KEY, date_id INT, product_id INT, store_id INT, "
                    + "amount DECIMAL(12,2), qty INT)");
            st.execute("INSERT INTO dim_date VALUES (1,'2023-01-01',2023,1),(2,'2023-06-01',2023,6),"
                    + "(3,'2024-01-01',2024,1),(4,'2024-06-01',2024,6)");
            st.execute("INSERT INTO dim_product VALUES (1,'A','tools'),(2,'B','media'),(3,'C','tools')");
            st.execute("INSERT INTO dim_store VALUES (1,'S1','east'),(2,'S2','west'),(3,'S3','east')");
            StringBuilder ins = new StringBuilder("INSERT INTO fact_sales VALUES ");
            int id = 1;
            for (int d = 1; d <= 4; d++)
                for (int p = 1; p <= 3; p++)
                    for (int s = 1; s <= 3; s++) {
                        if (id > 1) ins.append(',');
                        ins.append("(").append(id++).append(",").append(d).append(",").append(p).append(",")
                                .append(s).append(",").append((d * 10 + p * 3 + s)).append(".50,").append(d + p).append(")");
                    }
            st.execute(ins.toString());
        }
    }

    private void dropStar(Statement st) {
        Db.quiet(st, "DROP TABLE IF EXISTS fact_sales");
        Db.quiet(st, "DROP TABLE IF EXISTS dim_date");
        Db.quiet(st, "DROP TABLE IF EXISTS dim_product");
        Db.quiet(st, "DROP TABLE IF EXISTS dim_store");
    }

    // -------------------------------------------------- time-series / IoT ----

    private void timeseries(Runner r, Db db) {
        final String cat = "workload_timeseries";
        r.register(fw, cat, "bulk_insert_50k_readings", () -> {
            String t = Db.uniq("readings");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (ts DATETIME, device_id INT, metric VARCHAR(10), val DOUBLE)");
                }
                c.setAutoCommit(false);
                try (PreparedStatement ps = c.prepareStatement(
                        "INSERT INTO " + t + " VALUES (?,?,?,?)")) {
                    long base = System.currentTimeMillis();
                    for (int i = 0; i < 50000; i++) {
                        ps.setTimestamp(1, new java.sql.Timestamp(base + i * 1000L));
                        ps.setInt(2, i % 100);
                        ps.setString(3, "temp");
                        ps.setDouble(4, 20.0 + (i % 50) * 0.1);
                        ps.addBatch();
                        if (i % 2000 == 1999) ps.executeBatch();
                    }
                    ps.executeBatch();
                }
                c.commit();
                c.setAutoCommit(true);
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                Assert.eq(50000L, n, "bulk IoT insert count");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        String[][] queries = {
                {"time_bucket_hourly", "SELECT DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') bucket, AVG(val) FROM readings "
                        + "GROUP BY bucket ORDER BY bucket"},
                {"time_bucket_via_floor", "SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(ts)/3600)*3600) b, COUNT(*) "
                        + "FROM readings GROUP BY b"},
                {"per_device_latest", "SELECT device_id, MAX(ts) FROM readings GROUP BY device_id"},
                {"downsample_avg_min_max", "SELECT device_id, AVG(val), MIN(val), MAX(val) FROM readings GROUP BY device_id"},
                {"gap_detection_lag", "SELECT ts, val, LAG(val) OVER (PARTITION BY device_id ORDER BY ts) prev "
                        + "FROM readings"},
                {"rolling_window_avg", "SELECT ts, AVG(val) OVER (PARTITION BY device_id ORDER BY ts "
                        + "ROWS BETWEEN 4 PRECEDING AND CURRENT ROW) FROM readings"},
                {"rate_of_change", "SELECT ts, val - LAG(val) OVER (PARTITION BY device_id ORDER BY ts) delta "
                        + "FROM readings"},
                {"count_per_minute", "SELECT DATE_FORMAT(ts, '%Y-%m-%d %H:%i') m, COUNT(*) FROM readings GROUP BY m"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildReadings(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { Db.quiet(st, "DROP TABLE IF EXISTS readings"); }
                }
            });
        }
    }

    private void buildReadings(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            Db.quiet(st, "DROP TABLE IF EXISTS readings");
            st.execute("CREATE TABLE readings (ts DATETIME, device_id INT, metric VARCHAR(10), val DOUBLE)");
        }
        c.setAutoCommit(false);
        try (PreparedStatement ps = c.prepareStatement("INSERT INTO readings VALUES (?,?,?,?)")) {
            long base = java.sql.Timestamp.valueOf("2024-01-01 00:00:00").getTime();
            for (int i = 0; i < 600; i++) {
                ps.setTimestamp(1, new java.sql.Timestamp(base + i * 60000L));
                ps.setInt(2, i % 5);
                ps.setString(3, "temp");
                ps.setDouble(4, 20 + (i % 20) * 0.5);
                ps.addBatch();
            }
            ps.executeBatch();
        }
        c.commit();
        c.setAutoCommit(true);
    }

    // -------------------------------------------------- financial ledger ----

    private void ledger(Runner r, Db db) {
        final String cat = "workload_ledger";
        r.register(fw, cat, "double_entry_balanced", () -> {
            String t = Db.uniq("ledger");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, account VARCHAR(20), "
                            + "debit DECIMAL(20,4), credit DECIMAL(20,4))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'cash',100.0000,0),(2,'revenue',0,100.0000)");
                    BigDecimal d = (BigDecimal) Db.scalar(c, "SELECT SUM(debit) FROM " + t);
                    BigDecimal cr = (BigDecimal) Db.scalar(c, "SELECT SUM(credit) FROM " + t);
                    Assert.eq(d, cr, "double-entry debits equal credits");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, cat, "running_balance_window", () -> {
            String t = Db.uniq("ledg2");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, ts DATETIME, amount DECIMAL(20,4))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'2024-01-01',100.0000),(2,'2024-01-02',-30.0000),"
                            + "(3,'2024-01-03',50.0000)");
                    try (ResultSet rs = st.executeQuery("SELECT id, SUM(amount) OVER (ORDER BY ts ROWS UNBOUNDED PRECEDING) bal "
                            + "FROM " + t + " ORDER BY ts")) {
                        BigDecimal last = null;
                        while (rs.next()) last = rs.getBigDecimal(2);
                        Assert.eq(new BigDecimal("120.0000"), last, "final running balance");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, cat, "decimal_money_precision_add", () -> {
            try (Connection c = db.connect(dbName)) {
                BigDecimal v = (BigDecimal) Db.scalar(c,
                        "SELECT CAST('0.10' AS DECIMAL(20,2)) + CAST('0.20' AS DECIMAL(20,2))");
                Assert.eq(new BigDecimal("0.30"), v, "decimal money 0.10+0.20=0.30 (no float error)");
            }
        });
        r.register(fw, cat, "decimal_money_multiply_round", () -> {
            try (Connection c = db.connect(dbName)) {
                BigDecimal v = (BigDecimal) Db.scalar(c,
                        "SELECT ROUND(CAST('19.99' AS DECIMAL(20,2)) * 3, 2)");
                Assert.eq(new BigDecimal("59.97"), v, "19.99 * 3 rounded");
            }
        });
        r.register(fw, cat, "interest_compound_decimal", () -> {
            try (Connection c = db.connect(dbName)) {
                Object v = Db.scalar(c, "SELECT ROUND(1000 * POW(1.05, 3), 2)");
                Assert.notNull(v, "compound interest computed");
            }
        });
        r.register(fw, cat, "account_balances_group", () -> {
            String t = Db.uniq("ledg3");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (account VARCHAR(20), amount DECIMAL(20,4))");
                    st.execute("INSERT INTO " + t + " VALUES ('a',10.5),('a',-3.25),('b',100.0)");
                    try (ResultSet rs = st.executeQuery("SELECT account, SUM(amount) FROM " + t + " GROUP BY account")) {
                        while (rs.next()) rs.getBigDecimal(2);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, cat, "ledger_transfer_transaction", () -> {
            String t = Db.uniq("acct");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, balance DECIMAL(20,2))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 100.00),(2, 0.00)");
                }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement()) {
                    st.executeUpdate("UPDATE " + t + " SET balance = balance - 40.00 WHERE id=1");
                    st.executeUpdate("UPDATE " + t + " SET balance = balance + 40.00 WHERE id=2");
                }
                c.commit();
                c.setAutoCommit(true);
                BigDecimal b2 = (BigDecimal) Db.scalar(c, "SELECT balance FROM " + t + " WHERE id=2");
                Assert.eq(new BigDecimal("40.00"), b2, "transfer credited account 2");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // ------------------------------------------------- multi-tenant SaaS ----

    private void saas(Runner r, Db db) {
        final String cat = "workload_saas";
        // shared-schema tenancy with tenant_id discriminator
        String[][] queries = {
                {"tenant_isolation_filter", "SELECT COUNT(*) FROM docs WHERE tenant_id = 1"},
                {"per_tenant_counts", "SELECT tenant_id, COUNT(*) FROM docs GROUP BY tenant_id"},
                {"tenant_scoped_join", "SELECT d.id, u.name FROM docs d JOIN tenant_users u "
                        + "ON u.id = d.owner_id AND u.tenant_id = d.tenant_id WHERE d.tenant_id = 2"},
                {"composite_pk_tenant", "SELECT * FROM docs WHERE tenant_id = 1 AND id = 1"},
                {"cross_tenant_guard", "SELECT COUNT(*) FROM docs d JOIN tenant_users u ON u.id = d.owner_id "
                        + "WHERE u.tenant_id <> d.tenant_id"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildSaas(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { dropSaas(st); }
                }
            });
        }
        // Schema-per-tenant: create N tenant databases, verify isolation
        r.register(fw, cat, "schema_per_tenant_create", () -> {
            try (Connection c = db.connectServer(); Statement st = c.createStatement()) {
                String[] schemas = {Db.uniq("tnt"), Db.uniq("tnt"), Db.uniq("tnt")};
                try {
                    for (String s : schemas) {
                        st.execute("CREATE DATABASE " + s);
                        st.execute("CREATE TABLE " + s + ".items (id INT PRIMARY KEY, v INT)");
                        st.execute("INSERT INTO " + s + ".items VALUES (1, 100)");
                    }
                    long n = Db.scalarLong(c, "SELECT v FROM " + schemas[0] + ".items WHERE id=1");
                    Assert.eq(100L, n, "schema-per-tenant isolation");
                } finally {
                    for (String s : schemas) Db.quiet(st, "DROP DATABASE IF EXISTS " + s);
                }
            }
        });
        r.register(fw, cat, "tenant_row_level_update", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildSaas(c);
                try {
                    int u = st.executeUpdate("UPDATE docs SET title = 'edited' WHERE tenant_id = 1");
                    Assert.isTrue(u >= 1, "tenant-scoped update affected tenant-1 rows only");
                    long others = Db.scalarLong(c, "SELECT COUNT(*) FROM docs WHERE tenant_id <> 1 AND title = 'edited'");
                    Assert.eq(0L, others, "other tenants untouched");
                } finally { dropSaas(st); }
            }
        });
    }

    private void buildSaas(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            dropSaas(st);
            st.execute("CREATE TABLE tenant_users (id INT, tenant_id INT, name VARCHAR(40), PRIMARY KEY (tenant_id, id))");
            st.execute("CREATE TABLE docs (id INT, tenant_id INT, owner_id INT, title VARCHAR(80), "
                    + "PRIMARY KEY (tenant_id, id))");
            st.execute("INSERT INTO tenant_users VALUES (1,1,'Ann'),(2,1,'Bob'),(1,2,'Cy')");
            st.execute("INSERT INTO docs VALUES (1,1,1,'t1'),(2,1,2,'t2'),(1,2,1,'t3')");
        }
    }

    private void dropSaas(Statement st) {
        Db.quiet(st, "DROP TABLE IF EXISTS docs");
        Db.quiet(st, "DROP TABLE IF EXISTS tenant_users");
    }

    // --------------------------------------------------------------- CMS ----

    private void cms(Runner r, Db db) {
        final String cat = "workload_cms";
        // Adjacency list (parent_id)
        String[][] adj = {
                {"adjacency_children_of", "SELECT id, title FROM pages WHERE parent_id = 1"},
                {"adjacency_roots", "SELECT id FROM pages WHERE parent_id IS NULL"},
                {"adjacency_leaf_nodes", "SELECT p.id FROM pages p WHERE NOT EXISTS "
                        + "(SELECT 1 FROM pages c WHERE c.parent_id = p.id)"},
        };
        for (String[] q : adj) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildCms(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { dropCms(st); }
                }
            });
        }
        r.register(fw, cat, "adjacency_recursive_descendants", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildCms(c);
                try (ResultSet rs = st.executeQuery(
                        "WITH RECURSIVE sub(id, parent_id, depth) AS ("
                        + " SELECT id, parent_id, 0 FROM pages WHERE id = 1"
                        + " UNION ALL"
                        + " SELECT p.id, p.parent_id, s.depth+1 FROM pages p JOIN sub s ON p.parent_id = s.id)"
                        + " SELECT id, depth FROM sub ORDER BY depth")) {
                    int n = 0; while (rs.next()) n++;
                    Assert.isTrue(n >= 1, "recursive descendants found");
                } finally { dropCms(st); }
            }
        });
        r.register(fw, cat, "adjacency_recursive_breadcrumb", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                buildCms(c);
                try (ResultSet rs = st.executeQuery(
                        "WITH RECURSIVE up(id, parent_id, lvl) AS ("
                        + " SELECT id, parent_id, 0 FROM pages WHERE id = 4"
                        + " UNION ALL"
                        + " SELECT p.id, p.parent_id, u.lvl+1 FROM pages p JOIN up u ON p.id = u.parent_id)"
                        + " SELECT id FROM up ORDER BY lvl DESC")) {
                    while (rs.next()) {}
                } finally { dropCms(st); }
            }
        });
        // Nested set model (lft/rgt)
        String[][] nested = {
                {"nested_subtree", "SELECT n.id FROM nodes n JOIN nodes p ON n.lft BETWEEN p.lft AND p.rgt "
                        + "WHERE p.id = 1"},
                {"nested_ancestors", "SELECT p.id FROM nodes n JOIN nodes p ON n.lft BETWEEN p.lft AND p.rgt "
                        + "WHERE n.id = 4 ORDER BY p.lft"},
                {"nested_depth", "SELECT n.id, COUNT(p.id)-1 depth FROM nodes n JOIN nodes p "
                        + "ON n.lft BETWEEN p.lft AND p.rgt GROUP BY n.id, n.lft ORDER BY n.lft"},
                {"nested_leaf_count", "SELECT COUNT(*) FROM nodes WHERE rgt = lft + 1"},
        };
        for (String[] q : nested) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildNested(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { Db.quiet(st, "DROP TABLE IF EXISTS nodes"); }
                }
            });
        }
    }

    private void buildCms(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            dropCms(st);
            st.execute("CREATE TABLE pages (id INT PRIMARY KEY, parent_id INT NULL, title VARCHAR(80))");
            st.execute("INSERT INTO pages VALUES (1,NULL,'root'),(2,1,'a'),(3,1,'b'),(4,2,'a1'),(5,4,'a1x')");
        }
    }

    private void dropCms(Statement st) {
        Db.quiet(st, "DROP TABLE IF EXISTS pages");
    }

    private void buildNested(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            Db.quiet(st, "DROP TABLE IF EXISTS nodes");
            st.execute("CREATE TABLE nodes (id INT PRIMARY KEY, name VARCHAR(40), lft INT, rgt INT)");
            st.execute("INSERT INTO nodes VALUES (1,'root',1,10),(2,'a',2,5),(3,'b',6,9),(4,'a1',3,4),(5,'b1',7,8)");
        }
    }

    // ---------------------------------------------------------------- geo ----

    private void geo(Runner r, Db db) {
        final String cat = "workload_geo";
        // Bounding-box queries on plain lat/lon columns (portable approach).
        String[][] queries = {
                {"bbox_filter", "SELECT id FROM places WHERE lat BETWEEN 40.0 AND 41.0 AND lon BETWEEN -74.5 AND -73.5"},
                {"haversine_approx", "SELECT id, "
                        + "(6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(lat-40.7)/2),2) + "
                        + "COS(RADIANS(40.7))*COS(RADIANS(lat))*POWER(SIN(RADIANS(lon+74.0)/2),2)))) dist "
                        + "FROM places ORDER BY dist LIMIT 5"},
                {"nearest_by_squared_dist", "SELECT id FROM places "
                        + "ORDER BY POWER(lat-40.7,2) + POWER(lon+74.0,2) ASC LIMIT 3"},
                {"count_in_radius_box", "SELECT COUNT(*) FROM places WHERE "
                        + "POWER(lat-40.7,2) + POWER(lon+74.0,2) < 1.0"},
        };
        for (String[] q : queries) {
            r.register(fw, cat, q[0], () -> {
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    buildPlaces(c);
                    try { try (ResultSet rs = st.executeQuery(q[1])) { while (rs.next()) {} } }
                    finally { Db.quiet(st, "DROP TABLE IF EXISTS places"); }
                }
            });
        }
        // Spatial type / function findings (POINT exists; ST_* functions may not parse).
        r.register(fw, cat, "spatial_point_column", () -> {
            String t = Db.uniq("geo");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, p POINT)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        String[][] spatialFns = {
                {"st_distance", "SELECT ST_Distance(POINT(0,0), POINT(3,4))"},
                {"st_contains", "SELECT ST_Contains(ST_GeomFromText('POLYGON((0 0,0 4,4 4,4 0,0 0))'), POINT(1,1))"},
                {"st_within", "SELECT ST_Within(POINT(1,1), ST_GeomFromText('POLYGON((0 0,0 4,4 4,4 0,0 0))'))"},
                {"st_x", "SELECT ST_X(POINT(3,4))"},
                {"st_astext", "SELECT ST_AsText(POINT(3,4))"},
                {"st_geomfromtext", "SELECT ST_GeomFromText('POINT(1 1)')"},
        };
        for (String[] f : spatialFns) {
            r.register(fw, cat, "spatial_fn/" + f[0], () -> {
                // These document spatial-function gaps; FAIL is an expected finding.
                try (Connection c = db.connect(dbName)) {
                    Object o = Db.scalar(c, f[1]);
                    Assert.notNull(o, "spatial function " + f[0] + " returned value");
                }
            });
        }
    }

    private void buildPlaces(Connection c) throws Exception {
        try (Statement st = c.createStatement()) {
            Db.quiet(st, "DROP TABLE IF EXISTS places");
            st.execute("CREATE TABLE places (id INT PRIMARY KEY, name VARCHAR(40), lat DOUBLE, lon DOUBLE)");
            st.execute("INSERT INTO places VALUES (1,'a',40.71,-74.01),(2,'b',40.75,-73.98),"
                    + "(3,'c',41.50,-73.00),(4,'d',40.70,-74.00),(5,'e',42.00,-71.00)");
        }
    }

    private WorkloadModule self() { return this; }
}
