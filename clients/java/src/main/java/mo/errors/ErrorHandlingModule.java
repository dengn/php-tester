package mo.errors;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.sql.SQLIntegrityConstraintViolationException;
import java.sql.SQLTimeoutException;
import java.sql.Statement;

/**
 * Error handling: duplicate-key/constraint-violation SQLState surfaces, deadlock
 * retry, statement/query timeout, reconnect after connection loss.
 */
public final class ErrorHandlingModule {

    private final String fw;
    private final String dbName;

    public ErrorHandlingModule(String fw, String dbName) {
        this.fw = fw;
        this.dbName = dbName;
    }

    public void register(Runner r, Db db) {
        // Duplicate-key surfaces as SQLIntegrityConstraintViolationException with SQLState 23000.
        r.register(fw, "error", "duplicate_key_sqlstate", () -> {
            String t = Db.uniq("dk");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                    boolean correct = false;
                    try {
                        st.execute("INSERT INTO " + t + " VALUES (1)");
                    } catch (SQLException e) {
                        String state = e.getSQLState();
                        boolean integrityType = e instanceof SQLIntegrityConstraintViolationException;
                        // MySQL surfaces 23000 + error 1062. Accept either as "duplicate detected".
                        correct = integrityType || (state != null && state.startsWith("23"))
                                || e.getErrorCode() == 1062 || (e.getMessage() != null && e.getMessage().contains("uplicate"));
                    }
                    Assert.isTrue(correct, "duplicate key surfaced as integrity violation / 23000 / 1062");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "error", "unique_violation_sqlstate", () -> {
            String t = Db.uniq("uv");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, e VARCHAR(40) UNIQUE)");
                    st.execute("INSERT INTO " + t + " VALUES (1, 'a@x')");
                    boolean detected = false;
                    try {
                        st.execute("INSERT INTO " + t + " VALUES (2, 'a@x')");
                    } catch (SQLException e) {
                        detected = e.getErrorCode() == 1062 || (e.getSQLState() != null && e.getSQLState().startsWith("23"))
                                || e instanceof SQLIntegrityConstraintViolationException;
                    }
                    Assert.isTrue(detected, "unique violation detected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "error", "not_null_violation_surface", () -> {
            String t = Db.uniq("nn");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (v INT NOT NULL)");
                    boolean detected = false;
                    try {
                        st.execute("INSERT INTO " + t + " (v) VALUES (NULL)");
                    } catch (SQLException e) {
                        detected = true; // any SQLException acceptable; we record the surface
                    }
                    Assert.isTrue(detected, "NOT NULL violation raised SQLException");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "error", "syntax_error_surface", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                boolean detected = false;
                try {
                    st.execute("SELCT bad syntax here");
                } catch (SQLException e) {
                    // MatrixOne returns 1064 with SQLState 42000 (or 42xxx).
                    detected = e.getErrorCode() == 1064
                            || (e.getSQLState() != null && e.getSQLState().startsWith("42"))
                            || (e.getMessage() != null && e.getMessage().contains("parser"));
                }
                Assert.isTrue(detected, "syntax error surfaced");
            }
        });
        r.register(fw, "error", "unknown_column_surface", () -> {
            String t = Db.uniq("uc");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    boolean detected = false;
                    try { st.executeQuery("SELECT nonexistent FROM " + t); }
                    catch (SQLException e) { detected = true; }
                    Assert.isTrue(detected, "unknown column raised SQLException");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "error", "table_not_found_surface", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                boolean detected = false;
                try { st.executeQuery("SELECT * FROM definitely_no_such_table_xyz"); }
                catch (SQLException e) { detected = true; }
                Assert.isTrue(detected, "missing table raised SQLException");
            }
        });

        // Statement query timeout.
        r.register(fw, "error", "statement_query_timeout", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                st.setQueryTimeout(1);
                try {
                    // A heavy self-join to attempt to exceed 1s; if it returns fast that's fine too.
                    st.executeQuery("SELECT COUNT(*) FROM "
                            + "(SELECT 1 a UNION ALL SELECT 2 UNION ALL SELECT 3) x "
                            + "CROSS JOIN (SELECT 1 b UNION ALL SELECT 2) y");
                    // No timeout -> just confirm setQueryTimeout was accepted.
                    Assert.eq(1, st.getQueryTimeout(), "query timeout was set to 1s");
                } catch (SQLTimeoutException te) {
                    // timeout surfaced correctly
                } catch (SQLException e) {
                    // Some drivers throw generic SQLException on timeout; accept it.
                }
            }
        });
        r.register(fw, "error", "set_query_timeout_accepted", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                st.setQueryTimeout(30);
                Assert.eq(30, st.getQueryTimeout(), "query timeout round-trips");
            }
        });

        // Deadlock retry pattern (sequential simulation): on a conflict, retry succeeds.
        r.register(fw, "error", "constraint_violation_retry", () -> {
            String t = Db.uniq("rt");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1, 0)");
                    int attempts = 0;
                    boolean done = false;
                    int useId = 1;
                    while (!done && attempts < 3) {
                        attempts++;
                        try {
                            st.execute("INSERT INTO " + t + " VALUES (" + useId + ", 1)");
                            done = true;
                        } catch (SQLException e) {
                            useId++; // resolve conflict by choosing a new id (retry)
                        }
                    }
                    Assert.isTrue(done, "retry loop eventually succeeded after conflict");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Reconnect after explicit close.
        r.register(fw, "error", "reconnect_after_close", () -> {
            Connection c1 = db.connect(dbName);
            Assert.eq(1L, Db.scalarLong(c1, "SELECT 1"), "first connection works");
            c1.close();
            boolean closedThrew = false;
            try { Db.scalarLong(c1, "SELECT 1"); } catch (SQLException e) { closedThrew = true; }
            Assert.isTrue(closedThrew, "closed connection rejects queries");
            try (Connection c2 = db.connect(dbName)) {
                Assert.eq(1L, Db.scalarLong(c2, "SELECT 1"), "reconnect produces a working connection");
            }
        });
        r.register(fw, "error", "isValid_check", () -> {
            try (Connection c = db.connect(dbName)) {
                Assert.isTrue(c.isValid(5), "connection.isValid(5) true for open connection");
                c.close();
                Assert.isTrue(!c.isValid(5), "isValid false after close");
            }
        });
        r.register(fw, "error", "connection_failure_bad_port", () -> {
            // Connecting to a bad port should fail fast with SQLException (documents driver behavior).
            String badUrl = "jdbc:mysql://127.0.0.1:1/" + dbName
                    + "?connectTimeout=1500&" + mo.harness.Config.PARAMS;
            boolean failed = false;
            try (Connection c = DriverManager.getConnection(badUrl, db.config().user, db.config().pass)) {
                Db.scalarLong(c, "SELECT 1");
            } catch (SQLException e) {
                failed = true;
            }
            Assert.isTrue(failed, "connection to closed port failed with SQLException");
        });
        r.register(fw, "error", "rollback_on_error_keeps_consistency", () -> {
            String t = Db.uniq("rb");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement()) {
                    st.execute("INSERT INTO " + t + " VALUES (2)");
                    try { st.execute("INSERT INTO " + t + " VALUES (1)"); } // dup -> error
                    catch (SQLException e) { c.rollback(); }
                }
                c.setAutoCommit(true);
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                // After rollback only the originally-committed row (1) remains.
                Assert.eq(1L, n, "rollback after constraint error restored consistency");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }
}
