package mo.ddlsurface;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.CallableStatement;
import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * DDL surface as findings: views (+updatable), stored procedures (CallableStatement),
 * triggers, events, partitioning, FK actions, check constraints. Many of these are
 * expected FAILs on MatrixOne and are recorded as compatibility findings.
 */
public final class DdlSurfaceModule {

    private final String fw;
    private final String dbName;

    public DdlSurfaceModule(String fw, String dbName) {
        this.fw = fw;
        this.dbName = dbName;
    }

    public void register(Runner r, Db db) {
        views(r, db);
        storedProcedures(r, db);
        triggers(r, db);
        events(r, db);
        partitioning(r, db);
        foreignKeyActions(r, db);
        checkConstraints(r, db);
    }

    // -------------------------------------------------------------- views ----

    private void views(Runner r, Db db) {
        r.register(fw, "view", "simple_view_select", () -> {
            String t = Db.uniq("vt");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1,10),(2,20)");
                    st.execute("CREATE VIEW " + t + "_v AS SELECT id, v FROM " + t + " WHERE v > 5");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + "_v");
                    Assert.eq(2L, n, "view returns filtered rows");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_v");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "view", "view_with_join", () -> {
            String a = Db.uniq("va"); String b = Db.uniq("vb");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + a + " (id INT PRIMARY KEY, name VARCHAR(20))");
                    st.execute("CREATE TABLE " + b + " (id INT, a_id INT, v INT)");
                    st.execute("INSERT INTO " + a + " VALUES (1,'x')");
                    st.execute("INSERT INTO " + b + " VALUES (1,1,99)");
                    st.execute("CREATE VIEW " + a + "_jv AS SELECT a.name, b.v FROM " + a + " a JOIN " + b + " b ON b.a_id = a.id");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + a + "_jv");
                    Assert.eq(1L, n, "join view rows");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + a + "_jv");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + b);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + a);
                }
            }
        });
        r.register(fw, "view", "view_aggregate", () -> {
            String t = Db.uniq("vag");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (g INT, v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1,10),(1,20),(2,5)");
                    st.execute("CREATE VIEW " + t + "_av AS SELECT g, SUM(v) total FROM " + t + " GROUP BY g");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + "_av");
                    Assert.eq(2L, n, "aggregate view rows");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_av");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "view", "updatable_view_insert", () -> {
            // Insert through a simple updatable view; MatrixOne may not support this.
            String t = Db.uniq("uv");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("CREATE VIEW " + t + "_uv AS SELECT id, v FROM " + t);
                    st.executeUpdate("INSERT INTO " + t + "_uv VALUES (1, 100)");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE id=1");
                    Assert.eq(1L, n, "insert through updatable view persisted to base table");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_uv");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "view", "updatable_view_update", () -> {
            String t = Db.uniq("uvu");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1, 1)");
                    st.execute("CREATE VIEW " + t + "_uv AS SELECT id, v FROM " + t);
                    st.executeUpdate("UPDATE " + t + "_uv SET v = 5 WHERE id = 1");
                    long v = Db.scalarLong(c, "SELECT v FROM " + t + " WHERE id=1");
                    Assert.eq(5L, v, "update through view");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_uv");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "view", "view_with_check_option", () -> {
            String t = Db.uniq("vco");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("CREATE VIEW " + t + "_v AS SELECT id, v FROM " + t + " WHERE v > 0 WITH CHECK OPTION");
                } finally {
                    Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_v");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "view", "materialized_view", () -> {
            // MatrixOne has its own materialized view syntax; standard CREATE MATERIALIZED VIEW likely fails.
            String t = Db.uniq("mv");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, v INT)");
                    st.execute("CREATE MATERIALIZED VIEW " + t + "_mv AS SELECT id, SUM(v) FROM " + t + " GROUP BY id");
                } finally {
                    Db.quiet(st, "DROP MATERIALIZED VIEW IF EXISTS " + t + "_mv");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
    }

    // ------------------------------------------------- stored procedures ----

    private void storedProcedures(Runner r, Db db) {
        r.register(fw, "procedure", "create_simple_procedure", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE PROCEDURE sp_one() BEGIN SELECT 1; END");
                } finally { Db.quiet(st, "DROP PROCEDURE IF EXISTS sp_one"); }
            }
        });
        r.register(fw, "procedure", "create_procedure_with_params", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE PROCEDURE sp_add(IN a INT, IN b INT, OUT s INT) BEGIN SET s = a + b; END");
                } finally { Db.quiet(st, "DROP PROCEDURE IF EXISTS sp_add"); }
            }
        });
        r.register(fw, "procedure", "callable_statement_call", () -> {
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE PROCEDURE sp_get() BEGIN SELECT 42; END");
                }
                try (CallableStatement cs = c.prepareCall("{call sp_get()}");
                     ResultSet rs = cs.executeQuery()) {
                    Assert.isTrue(rs.next(), "callable statement result");
                    Assert.eq(42, rs.getInt(1), "procedure returned 42");
                } finally {
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP PROCEDURE IF EXISTS sp_get"); }
                }
            }
        });
        r.register(fw, "procedure", "callable_statement_out_param", () -> {
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE PROCEDURE sp_sum(IN a INT, IN b INT, OUT s INT) BEGIN SET s = a + b; END");
                }
                try (CallableStatement cs = c.prepareCall("{call sp_sum(?, ?, ?)}")) {
                    cs.setInt(1, 3);
                    cs.setInt(2, 4);
                    cs.registerOutParameter(3, java.sql.Types.INTEGER);
                    cs.execute();
                    Assert.eq(7, cs.getInt(3), "OUT param sum");
                } finally {
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP PROCEDURE IF EXISTS sp_sum"); }
                }
            }
        });
        r.register(fw, "procedure", "stored_function", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE FUNCTION fn_double(x INT) RETURNS INT DETERMINISTIC RETURN x * 2");
                    long v = Db.scalarLong(c, "SELECT fn_double(21)");
                    Assert.eq(42L, v, "stored function returned doubled value");
                } finally { Db.quiet(st, "DROP FUNCTION IF EXISTS fn_double"); }
            }
        });
    }

    // ----------------------------------------------------------- triggers ----

    private void triggers(Runner r, Db db) {
        r.register(fw, "trigger", "before_insert_trigger", () -> {
            String t = Db.uniq("tg");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("CREATE TRIGGER trg_bi BEFORE INSERT ON " + t
                            + " FOR EACH ROW SET NEW.v = NEW.v + 1");
                    st.execute("INSERT INTO " + t + " VALUES (1, 10)");
                    long v = Db.scalarLong(c, "SELECT v FROM " + t + " WHERE id=1");
                    Assert.eq(11L, v, "before-insert trigger modified value");
                } finally {
                    Db.quiet(st, "DROP TRIGGER IF EXISTS trg_bi");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(fw, "trigger", "after_insert_audit_trigger", () -> {
            String t = Db.uniq("ta"); String aud = Db.uniq("audit");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("CREATE TABLE " + aud + " (id INT, when_ts DATETIME)");
                    st.execute("CREATE TRIGGER trg_ai AFTER INSERT ON " + t
                            + " FOR EACH ROW INSERT INTO " + aud + " VALUES (NEW.id, NOW())");
                    st.execute("INSERT INTO " + t + " VALUES (1, 10)");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + aud);
                    Assert.eq(1L, n, "after-insert trigger wrote audit row");
                } finally {
                    Db.quiet(st, "DROP TRIGGER IF EXISTS trg_ai");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + aud);
                }
            }
        });
        r.register(fw, "trigger", "before_update_trigger", () -> {
            String t = Db.uniq("tu");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    st.execute("CREATE TRIGGER trg_bu BEFORE UPDATE ON " + t
                            + " FOR EACH ROW SET NEW.v = OLD.v + NEW.v");
                } finally {
                    Db.quiet(st, "DROP TRIGGER IF EXISTS trg_bu");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
    }

    // ------------------------------------------------------------- events ----

    private void events(Runner r, Db db) {
        r.register(fw, "event", "create_recurring_event", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE EVENT ev_daily ON SCHEDULE EVERY 1 DAY DO SELECT 1");
                } finally { Db.quiet(st, "DROP EVENT IF EXISTS ev_daily"); }
            }
        });
        r.register(fw, "event", "create_one_time_event", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE EVENT ev_once ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 HOUR DO SELECT 1");
                } finally { Db.quiet(st, "DROP EVENT IF EXISTS ev_once"); }
            }
        });
    }

    // ------------------------------------------------------- partitioning ----

    private void partitioning(Runner r, Db db) {
        String[][] parts = {
                {"hash", "CREATE TABLE %s (id INT) PARTITION BY HASH(id) PARTITIONS 4"},
                {"key", "CREATE TABLE %s (id INT PRIMARY KEY) PARTITION BY KEY(id) PARTITIONS 4"},
                {"range", "CREATE TABLE %s (id INT) PARTITION BY RANGE(id) "
                        + "(PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20), "
                        + "PARTITION p2 VALUES LESS THAN MAXVALUE)"},
                {"list", "CREATE TABLE %s (id INT) PARTITION BY LIST(id) "
                        + "(PARTITION pa VALUES IN (1,2,3), PARTITION pb VALUES IN (4,5,6))"},
                {"range_columns", "CREATE TABLE %s (a INT, b INT) PARTITION BY RANGE COLUMNS(a) "
                        + "(PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (MAXVALUE))"},
        };
        for (String[] p : parts) {
            r.register(fw, "partition", "create_" + p[0], () -> {
                String t = Db.uniq("part");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute(String.format(p[1], t));
                        st.execute("INSERT INTO " + t + " (" + (p[0].equals("range_columns") ? "a,b) VALUES (5,1" : "id) VALUES (5") + ")");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        r.register(fw, "partition", "partition_pruning_select", () -> {
            String t = Db.uniq("pp");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT) PARTITION BY RANGE(id) "
                            + "(PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN MAXVALUE)");
                    st.execute("INSERT INTO " + t + " VALUES (1),(5),(15),(25)");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE id < 10");
                    Assert.eq(2L, n, "partition-pruned count");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // -------------------------------------------------------- FK actions ----

    private void foreignKeyActions(Runner r, Db db) {
        String[][] actions = {
                {"on_delete_cascade", "ON DELETE CASCADE"},
                {"on_delete_set_null", "ON DELETE SET NULL"},
                {"on_delete_restrict", "ON DELETE RESTRICT"},
                {"on_delete_no_action", "ON DELETE NO ACTION"},
                {"on_update_cascade", "ON UPDATE CASCADE"},
                {"on_update_set_null", "ON UPDATE SET NULL"},
        };
        for (String[] act : actions) {
            r.register(fw, "fk_action", "create_" + act[0], () -> {
                String p = Db.uniq("fkp"); String ch = Db.uniq("fkc");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + p + " (id INT PRIMARY KEY)");
                        st.execute("CREATE TABLE " + ch + " (id INT PRIMARY KEY, pid INT, "
                                + "FOREIGN KEY (pid) REFERENCES " + p + "(id) " + act[1] + ")");
                    } finally {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + ch);
                        Db.quiet(st, "DROP TABLE IF EXISTS " + p);
                    }
                }
            });
        }
        r.register(fw, "fk_action", "cascade_delete_behavior", () -> {
            String p = Db.uniq("cdp"); String ch = Db.uniq("cdc");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + p + " (id INT PRIMARY KEY)");
                    st.execute("CREATE TABLE " + ch + " (id INT PRIMARY KEY, pid INT, "
                            + "FOREIGN KEY (pid) REFERENCES " + p + "(id) ON DELETE CASCADE)");
                    st.execute("INSERT INTO " + p + " VALUES (1)");
                    st.execute("INSERT INTO " + ch + " VALUES (1, 1)");
                    st.execute("DELETE FROM " + p + " WHERE id = 1");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + ch);
                    Assert.eq(0L, n, "ON DELETE CASCADE removed child rows");
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + ch);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + p);
                }
            }
        });
    }

    // -------------------------------------------------- check constraints ----

    private void checkConstraints(Runner r, Db db) {
        r.register(fw, "check_constraint", "inline_check_create", () -> {
            String t = Db.uniq("ck");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, age INT CHECK (age >= 0))");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "check_constraint", "named_check_create", () -> {
            String t = Db.uniq("nck");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, sal INT, CONSTRAINT chk_sal CHECK (sal > 0))");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "check_constraint", "multi_column_check", () -> {
            String t = Db.uniq("mck");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (lo INT, hi INT, CHECK (lo <= hi))");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }
}
