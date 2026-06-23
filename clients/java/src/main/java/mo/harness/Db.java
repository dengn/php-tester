package mo.harness;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.concurrent.atomic.AtomicLong;

/** Database namespace lifecycle + connection helpers. */
public final class Db {

    private final Config cfg;
    private static final AtomicLong COUNTER = new AtomicLong(0);

    public Db(Config cfg) {
        this.cfg = cfg;
    }

    public Config config() {
        return cfg;
    }

    /** Create (drop first) a database namespace. */
    public void createDatabase(String db) throws SQLException {
        try (Connection c = DriverManager.getConnection(cfg.serverUrl(), cfg.user, cfg.pass);
             Statement st = c.createStatement()) {
            st.execute("DROP DATABASE IF EXISTS " + db);
            st.execute("CREATE DATABASE " + db);
        }
    }

    public void dropDatabase(String db) {
        try (Connection c = DriverManager.getConnection(cfg.serverUrl(), cfg.user, cfg.pass);
             Statement st = c.createStatement()) {
            st.execute("DROP DATABASE IF EXISTS " + db);
        } catch (SQLException ignore) {
            // best-effort cleanup
        }
    }

    /** A fresh connection into the given database. Caller closes. */
    public Connection connect(String db) throws SQLException {
        return DriverManager.getConnection(cfg.dbUrl(db), cfg.user, cfg.pass);
    }

    /** Server-level connection (no database). Caller closes. */
    public Connection connectServer() throws SQLException {
        return DriverManager.getConnection(cfg.serverUrl(), cfg.user, cfg.pass);
    }

    /** A globally-unique table-name suffix so scenarios never collide. */
    public static String uniq(String base) {
        return base + "_" + Long.toHexString(System.nanoTime()) + "_" + COUNTER.incrementAndGet();
    }

    /** Execute, ignoring errors (used for best-effort cleanup). */
    public static void quiet(Statement st, String sql) {
        try {
            st.execute(sql);
        } catch (SQLException ignore) {
        }
    }

    /** Read a single scalar value from a query. */
    public static Object scalar(Connection c, String sql) throws SQLException {
        try (Statement st = c.createStatement(); ResultSet rs = st.executeQuery(sql)) {
            if (rs.next()) {
                return rs.getObject(1);
            }
            return null;
        }
    }

    public static String scalarStr(Connection c, String sql) throws SQLException {
        Object o = scalar(c, sql);
        return o == null ? null : o.toString();
    }

    public static long scalarLong(Connection c, String sql) throws SQLException {
        Object o = scalar(c, sql);
        if (o == null) return 0;
        if (o instanceof Number n) return n.longValue();
        return Long.parseLong(o.toString().trim());
    }
}
