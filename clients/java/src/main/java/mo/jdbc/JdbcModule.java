package mo.jdbc;

import mo.harness.Assert;
import mo.harness.BehaviorMismatch;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;

import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.Savepoint;
import java.sql.Statement;
import java.util.List;

/**
 * Raw JDBC baseline. Registers the bulk of the matrix-generated scenarios:
 * data types x operations, functions, query patterns, binding matrices,
 * transactions, metadata, batch, and load/workload tests.
 */
public final class JdbcModule implements SuiteModule {

    public static final String DB = "mo_java_jdbc";
    private static final String FW = "jdbc";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    @Override
    public void register(Runner r, Db db) {
        JdbcTypeMatrix.register(r, db, FW, DB);
        JdbcPreparedTypeMatrix.register(r, db, FW, DB);
        JdbcColumnMatrix.register(r, db, FW, DB);
        JdbcDmlTypeMatrix.register(r, db, FW, DB);
        JdbcAggWindowTypeMatrix.register(r, db, FW, DB);
        JdbcFunctionMatrix.register(r, db, FW, DB);
        JdbcExpressionMatrix.register(r, db, FW, DB);
        JdbcQueryMatrix.register(r, db, FW, DB);
        JdbcBindingMatrix.register(r, db, FW, DB);
        registerDdl(r, db);
        registerConstraints(r, db);
        registerIndexes(r, db);
        registerTransactions(r, db);
        registerMetadata(r, db);
        registerBatchAndLoad(r, db);
        registerSemantics(r, db);
    }

    // ---------------------------------------------------------------- DDL ----

    private void registerDdl(Runner r, Db db) {
        String[][] ddls = {
                {"create_drop_table", "CREATE TABLE %1$s (id INT PRIMARY KEY, v VARCHAR(20))"},
                {"create_table_as_select", "CREATE TABLE %1$s AS SELECT 1 AS a, 'x' AS b"},
                {"create_table_like", null},
                {"alter_add_column", null},
                {"alter_drop_column", null},
                {"alter_modify_column", null},
                {"alter_rename_column", null},
                {"alter_add_index", null},
                {"truncate_table", null},
                {"rename_table", null},
                {"create_view", null},
                {"create_temporary_table", null},
                {"auto_increment_column", null},
                {"default_value_column", null},
                {"generated_stored_column", null},
                {"generated_virtual_column", null},
                {"comment_on_column", null},
                {"create_table_charset_utf8mb4", null},
                {"create_table_if_not_exists", null},
                {"drop_table_if_exists", null},
        };
        for (String[] d : ddls) {
            String name = d[0];
            String inlineSql = d[1];
            r.register(FW, "ddl", name, () -> {
                String t = Db.uniq("ddl_" + name);
                try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                    try {
                        switch (name) {
                            case "create_drop_table" -> {
                                st.execute(String.format(inlineSql, t));
                                st.execute("DROP TABLE " + t);
                            }
                            case "create_table_as_select" -> st.execute(String.format(inlineSql, t));
                            case "create_table_like" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("CREATE TABLE " + t + "_c LIKE " + t);
                            }
                            case "alter_add_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("ALTER TABLE " + t + " ADD COLUMN extra VARCHAR(20)");
                            }
                            case "alter_drop_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT, x INT)");
                                st.execute("ALTER TABLE " + t + " DROP COLUMN x");
                            }
                            case "alter_modify_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT, x VARCHAR(10))");
                                st.execute("ALTER TABLE " + t + " MODIFY COLUMN x VARCHAR(50)");
                            }
                            case "alter_rename_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT, x INT)");
                                st.execute("ALTER TABLE " + t + " RENAME COLUMN x TO y");
                            }
                            case "alter_add_index" -> {
                                st.execute("CREATE TABLE " + t + " (id INT, x INT)");
                                st.execute("ALTER TABLE " + t + " ADD INDEX idx_x (x)");
                            }
                            case "truncate_table" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("INSERT INTO " + t + " VALUES (1)");
                                st.execute("TRUNCATE TABLE " + t);
                            }
                            case "rename_table" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("RENAME TABLE " + t + " TO " + t + "_r");
                            }
                            case "create_view" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("CREATE VIEW " + t + "_v AS SELECT id FROM " + t);
                            }
                            case "create_temporary_table" ->
                                    st.execute("CREATE TEMPORARY TABLE " + t + " (id INT)");
                            case "auto_increment_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT AUTO_INCREMENT PRIMARY KEY, v INT)");
                                st.execute("INSERT INTO " + t + " (v) VALUES (10)");
                            }
                            case "default_value_column" -> {
                                st.execute("CREATE TABLE " + t + " (id INT, v INT DEFAULT 42)");
                                st.execute("INSERT INTO " + t + " (id) VALUES (1)");
                                long got = Db.scalarLong(c, "SELECT v FROM " + t + " WHERE id=1");
                                Assert.eq(42L, got, "default value");
                            }
                            case "generated_stored_column" -> st.execute(
                                    "CREATE TABLE " + t + " (a INT, b INT GENERATED ALWAYS AS (a+1) STORED)");
                            case "generated_virtual_column" -> st.execute(
                                    "CREATE TABLE " + t + " (a INT, b INT GENERATED ALWAYS AS (a*2) VIRTUAL)");
                            case "comment_on_column" -> st.execute(
                                    "CREATE TABLE " + t + " (id INT COMMENT 'the id')");
                            case "create_table_charset_utf8mb4" -> st.execute(
                                    "CREATE TABLE " + t + " (v VARCHAR(20)) DEFAULT CHARSET=utf8mb4");
                            case "create_table_if_not_exists" -> {
                                st.execute("CREATE TABLE IF NOT EXISTS " + t + " (id INT)");
                                st.execute("CREATE TABLE IF NOT EXISTS " + t + " (id INT)");
                            }
                            case "drop_table_if_exists" -> {
                                st.execute("CREATE TABLE " + t + " (id INT)");
                                st.execute("DROP TABLE IF EXISTS " + t);
                                st.execute("DROP TABLE IF EXISTS " + t);
                            }
                            default -> throw new IllegalStateException("unmapped ddl " + name);
                        }
                    } finally {
                        Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_v");
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t + "_c");
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t + "_r");
                    }
                }
            });
        }
    }

    // -------------------------------------------------------- Constraints ----

    private void registerConstraints(Runner r, Db db) {
        // Primary key enforcement
        r.register(FW, "constraint", "primary_key_enforced", () -> {
            String t = Db.uniq("pk");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                    boolean threw = false;
                    try { st.execute("INSERT INTO " + t + " VALUES (1)"); }
                    catch (Exception e) { threw = true; }
                    Assert.isTrue(threw, "duplicate PK should be rejected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "unique_enforced", () -> {
            String t = Db.uniq("uq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, e VARCHAR(50) UNIQUE)");
                    st.execute("INSERT INTO " + t + " VALUES (1,'a@x.com')");
                    boolean threw = false;
                    try { st.execute("INSERT INTO " + t + " VALUES (2,'a@x.com')"); }
                    catch (Exception e) { threw = true; }
                    Assert.isTrue(threw, "duplicate unique should be rejected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "unique_case_sensitive_collation", () -> {
            // MatrixOne default utf8mb4_bin -> 'a@x' and 'A@X' both allowed (MySQL would too on bin,
            // but MySQL default collation is _ci and would reject). BEHAVIOR difference.
            String t = Db.uniq("uqcase");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (e VARCHAR(50) UNIQUE)");
                    st.execute("INSERT INTO " + t + " VALUES ('user@x.com')");
                    boolean secondAccepted;
                    try { st.execute("INSERT INTO " + t + " VALUES ('USER@X.COM')"); secondAccepted = true; }
                    catch (Exception e) { secondAccepted = false; }
                    // MySQL default _ci: would REJECT (duplicate). MatrixOne _bin: accepts.
                    if (secondAccepted) {
                        throw new BehaviorMismatch("UNIQUE case-sensitivity",
                                "duplicate rejected (MySQL _ci)", "both rows accepted (MatrixOne _bin)");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "not_null_enforced", () -> {
            String t = Db.uniq("nn");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (v INT NOT NULL)");
                    boolean threw = false;
                    try { st.execute("INSERT INTO " + t + " (v) VALUES (NULL)"); }
                    catch (Exception e) { threw = true; }
                    Assert.isTrue(threw, "NULL into NOT NULL should be rejected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "foreign_key_enforced", () -> {
            String p = Db.uniq("fkp");
            String ch = Db.uniq("fkc");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + p + " (id INT PRIMARY KEY)");
                    st.execute("CREATE TABLE " + ch + " (id INT, pid INT, FOREIGN KEY (pid) REFERENCES " + p + "(id))");
                    st.execute("INSERT INTO " + p + " VALUES (1)");
                    st.execute("INSERT INTO " + ch + " VALUES (1,1)");
                    boolean threw = false;
                    try { st.execute("INSERT INTO " + ch + " VALUES (2,999)"); }
                    catch (Exception e) { threw = true; }
                    Assert.isTrue(threw, "FK violation should be rejected");
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + ch);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + p);
                }
            }
        });
        r.register(FW, "constraint", "check_constraint_enforced", () -> {
            // KNOWN: CHECK is parsed but not enforced. MySQL 8.0.16+ rejects -5.
            String t = Db.uniq("chk");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (age INT CHECK (age >= 0))");
                    boolean accepted;
                    try { st.execute("INSERT INTO " + t + " VALUES (-5)"); accepted = true; }
                    catch (Exception e) { accepted = false; }
                    if (accepted) {
                        throw new BehaviorMismatch("CHECK constraint enforcement",
                                "reject -5 (MySQL)", "accepted -5 (MatrixOne CHECK is a no-op)");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "alter_add_check_constraint", () -> {
            String t = Db.uniq("addchk");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (age INT)");
                    st.execute("ALTER TABLE " + t + " ADD CONSTRAINT chk_age CHECK (age >= 0)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "composite_primary_key", () -> {
            String t = Db.uniq("cpk");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (a INT, b INT, PRIMARY KEY (a,b))");
                    st.execute("INSERT INTO " + t + " VALUES (1,1),(1,2)");
                    boolean threw = false;
                    try { st.execute("INSERT INTO " + t + " VALUES (1,1)"); } catch (Exception e) { threw = true; }
                    Assert.isTrue(threw, "duplicate composite PK rejected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "constraint", "on_duplicate_composite_unique", () -> {
            // KNOWN: ON DUPLICATE on composite unique throws 1062.
            String t = Db.uniq("oddup");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (a INT, b INT, v INT, UNIQUE KEY uq (a,b))");
                    st.execute("INSERT INTO " + t + " VALUES (1,1,10)");
                    st.execute("INSERT INTO " + t + " VALUES (1,1,20) ON DUPLICATE KEY UPDATE v=VALUES(v)");
                    long v = Db.scalarLong(c, "SELECT v FROM " + t + " WHERE a=1 AND b=1");
                    Assert.eq(20L, v, "on-duplicate update value");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // ------------------------------------------------------------ Indexes ----

    private void registerIndexes(Runner r, Db db) {
        String[][] idx = {
                {"create_index_simple", "CREATE INDEX %2$s ON %1$s (a)"},
                {"create_index_composite", "CREATE INDEX %2$s ON %1$s (a,b)"},
                {"create_unique_index", "CREATE UNIQUE INDEX %2$s ON %1$s (a)"},
                {"create_index_using_btree", "CREATE INDEX %2$s ON %1$s (a) USING BTREE"},
                {"create_index_prefix", "CREATE INDEX %2$s ON %1$s (c(5))"},
                {"create_index_desc", "CREATE INDEX %2$s ON %1$s (a DESC)"},
                {"drop_index", "CREATE INDEX %2$s ON %1$s (a)"},
                {"fulltext_index", "CREATE FULLTEXT INDEX %2$s ON %1$s (c)"},
        };
        for (String[] x : idx) {
            String name = x[0];
            String sqlTmpl = x[1];
            r.register(FW, "index", name, () -> {
                String t = Db.uniq("idx_" + name);
                String iname = "i_" + Long.toHexString(System.nanoTime() % 0xFFFFFF);
                try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, a INT, b INT, c VARCHAR(100))");
                        st.execute(String.format(sqlTmpl, t, iname));
                        if (name.equals("drop_index")) {
                            st.execute("DROP INDEX " + iname + " ON " + t);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    // ------------------------------------------------------- Transactions ----

    private void registerTransactions(Runner r, Db db) {
        r.register(FW, "transaction", "commit_persists", () -> {
            String t = Db.uniq("tx");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement()) {
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                }
                c.commit();
                c.setAutoCommit(true);
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                Assert.eq(1L, n, "committed row count");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "transaction", "rollback_reverts", () -> {
            String t = Db.uniq("txr");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) { st.execute("CREATE TABLE " + t + " (id INT)"); }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement()) { st.execute("INSERT INTO " + t + " VALUES (1)"); }
                c.rollback();
                c.setAutoCommit(true);
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                Assert.eq(0L, n, "rolled-back row count");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "transaction", "savepoint_create", () -> {
            try (Connection c = db.connect(DB)) {
                c.setAutoCommit(false);
                Savepoint sp = c.setSavepoint("sp1"); // may or may not work
                Assert.notNull(sp, "savepoint handle");
                c.rollback();
                c.setAutoCommit(true);
            }
        });
        r.register(FW, "transaction", "rollback_to_savepoint", () -> {
            // KNOWN: ROLLBACK TO SAVEPOINT unimplemented (20101). Expect failure.
            String t = Db.uniq("txsp");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) { st.execute("CREATE TABLE " + t + " (id INT)"); }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement()) { st.execute("INSERT INTO " + t + " VALUES (1)"); }
                Savepoint sp = c.setSavepoint("sp1");
                try (Statement st = c.createStatement()) { st.execute("INSERT INTO " + t + " VALUES (2)"); }
                c.rollback(sp); // expected to throw on MatrixOne
                c.commit();
                c.setAutoCommit(true);
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                Assert.eq(1L, n, "after rollback-to-savepoint row count");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "transaction", "release_savepoint", () -> {
            try (Connection c = db.connect(DB)) {
                c.setAutoCommit(false);
                Savepoint sp = c.setSavepoint("sp_rel");
                c.releaseSavepoint(sp); // may throw
                c.commit();
                c.setAutoCommit(true);
            }
        });
        r.register(FW, "transaction", "isolation_read_committed", () -> {
            try (Connection c = db.connect(DB)) {
                c.setTransactionIsolation(Connection.TRANSACTION_READ_COMMITTED);
                int lvl = c.getTransactionIsolation();
                Assert.isTrue(lvl != 0, "isolation level set");
            }
        });
        r.register(FW, "transaction", "autocommit_toggle", () -> {
            try (Connection c = db.connect(DB)) {
                c.setAutoCommit(false);
                Assert.isTrue(!c.getAutoCommit(), "autocommit off");
                c.setAutoCommit(true);
                Assert.isTrue(c.getAutoCommit(), "autocommit on");
            }
        });
        r.register(FW, "transaction", "select_for_update", () -> {
            String t = Db.uniq("ffu");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                }
                c.setAutoCommit(false);
                try (Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT * FROM " + t + " WHERE id=1 FOR UPDATE")) {
                    Assert.isTrue(rs.next(), "for-update row");
                }
                c.commit();
                c.setAutoCommit(true);
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "transaction", "lock_in_share_mode", () -> {
            String t = Db.uniq("lism");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    st.execute("INSERT INTO " + t + " VALUES (1)");
                }
                try (Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT * FROM " + t + " WHERE id=1 LOCK IN SHARE MODE")) {
                    rs.next();
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // ---------------------------------------------------------- Metadata ----

    private void registerMetadata(Runner r, Db db) {
        r.register(FW, "metadata", "getColumns", () -> {
            String t = Db.uniq("md_cols");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v VARCHAR(30), n DECIMAL(10,2))");
                    DatabaseMetaData md = c.getMetaData();
                    int cols = 0;
                    try (ResultSet rs = md.getColumns(DB, null, t, "%")) {
                        while (rs.next()) cols++;
                    }
                    Assert.isTrue(cols >= 3, "getColumns returned " + cols + " columns (expected >=3)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "metadata", "getPrimaryKeys", () -> {
            String t = Db.uniq("md_pk");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    DatabaseMetaData md = c.getMetaData();
                    int n = 0;
                    try (ResultSet rs = md.getPrimaryKeys(DB, null, t)) { while (rs.next()) n++; }
                    Assert.isTrue(n >= 1, "getPrimaryKeys returned " + n);
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "metadata", "getImportedKeys", () -> {
            String p = Db.uniq("md_p");
            String ch = Db.uniq("md_c");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + p + " (id INT PRIMARY KEY)");
                    st.execute("CREATE TABLE " + ch + " (id INT, pid INT, FOREIGN KEY (pid) REFERENCES " + p + "(id))");
                    DatabaseMetaData md = c.getMetaData();
                    int n = 0;
                    try (ResultSet rs = md.getImportedKeys(DB, null, ch)) { while (rs.next()) n++; }
                    if (n < 1) {
                        // KNOWN: MatrixOne does not expose FK relationships via
                        // getImportedKeys / information_schema.key_column_usage.
                        throw new BehaviorMismatch("DatabaseMetaData.getImportedKeys",
                                ">=1 FK row (MySQL)", n + " rows (MatrixOne exposes no FK metadata)");
                    }
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + ch);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + p);
                }
            }
        });
        r.register(FW, "metadata", "getIndexInfo", () -> {
            String t = Db.uniq("md_idx");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, a INT)");
                    st.execute("CREATE INDEX ix_a ON " + t + " (a)");
                    DatabaseMetaData md = c.getMetaData();
                    int n = 0;
                    try (ResultSet rs = md.getIndexInfo(DB, null, t, false, false)) { while (rs.next()) n++; }
                    Assert.isTrue(n >= 1, "getIndexInfo returned " + n);
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "metadata", "getTables", () -> {
            String t = Db.uniq("md_tbl");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    DatabaseMetaData md = c.getMetaData();
                    boolean found = false;
                    try (ResultSet rs = md.getTables(DB, null, t, new String[]{"TABLE"})) {
                        while (rs.next()) found = true;
                    }
                    Assert.isTrue(found, "getTables found table");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "metadata", "getTypeInfo", () -> {
            try (Connection c = db.connect(DB)) {
                DatabaseMetaData md = c.getMetaData();
                int n = 0;
                try (ResultSet rs = md.getTypeInfo()) { while (rs.next()) n++; }
                Assert.isTrue(n >= 1, "getTypeInfo rows");
            }
        });
        r.register(FW, "metadata", "driver_version_info", () -> {
            try (Connection c = db.connect(DB)) {
                DatabaseMetaData md = c.getMetaData();
                Assert.notNull(md.getDatabaseProductVersion(), "db product version");
                Assert.notNull(md.getDriverVersion(), "driver version");
            }
        });
        r.register(FW, "metadata", "resultset_metadata", () -> {
            String t = Db.uniq("rsmd");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT, v VARCHAR(20), d DECIMAL(8,2))");
                    try (ResultSet rs = st.executeQuery("SELECT id, v, d FROM " + t)) {
                        ResultSetMetaData m = rs.getMetaData();
                        Assert.eq(3, m.getColumnCount(), "column count");
                        Assert.notNull(m.getColumnType(1) + "", "col1 type");
                        Assert.notNull(m.getColumnTypeName(2), "col2 type name");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // information_schema gaps (KNOWN: missing tables)
        String[] isTables = {
                "check_constraints",
                "collation_character_set_applicability",
                "columns",
                "tables",
                "key_column_usage",
                "statistics",
                "table_constraints",
                "referential_constraints",
                "schemata",
                "views",
        };
        for (String ist : isTables) {
            r.register(FW, "metadata", "information_schema_" + ist, () -> {
                try (Connection c = db.connect(DB); Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT * FROM information_schema." + ist + " LIMIT 1")) {
                    rs.getMetaData();
                }
            });
        }
    }

    // ---------------------------------------------------- Batch and Load ----

    private void registerBatchAndLoad(Runner r, Db db) {
        int[] batchSizes = {1, 10, 100, 1000};
        for (int bs : batchSizes) {
            r.register(FW, "batch", "addBatch_executeBatch_" + bs, () -> {
                String t = Db.uniq("batch_" + bs);
                try (Connection c = db.connect(DB)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT, v VARCHAR(20))");
                    }
                    c.setAutoCommit(false);
                    try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?,?)")) {
                        for (int i = 0; i < bs; i++) {
                            ps.setInt(1, i);
                            ps.setString(2, "v" + i);
                            ps.addBatch();
                        }
                        int[] res = ps.executeBatch();
                        Assert.eq(bs, res.length, "batch result length");
                    }
                    c.commit();
                    c.setAutoCommit(true);
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    Assert.eq((long) bs, n, "rows inserted via batch");
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // Bulk update / delete
        r.register(FW, "load", "bulk_update_10k", () -> {
            String t = Db.uniq("bulkupd");
            try (Connection c = db.connect(DB)) {
                seedRows(c, t, 10000);
                try (Statement st = c.createStatement()) {
                    int updated = st.executeUpdate("UPDATE " + t + " SET v = v + 1");
                    Assert.eq(10000, updated, "bulk update affected");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(FW, "load", "bulk_delete_10k", () -> {
            String t = Db.uniq("bulkdel");
            try (Connection c = db.connect(DB)) {
                seedRows(c, t, 10000);
                try (Statement st = c.createStatement()) {
                    int del = st.executeUpdate("DELETE FROM " + t + " WHERE id >= 5000");
                    Assert.eq(5000, del, "bulk delete affected");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        // Large streaming result with fetchSize + cursor
        r.register(FW, "load", "stream_10k_fetchsize", () -> {
            String t = Db.uniq("stream");
            try (Connection c = db.connect(DB)) {
                seedRows(c, t, 10000);
                try (Statement st = c.createStatement(ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
                    st.setFetchSize(500);
                    int n = 0;
                    try (ResultSet rs = st.executeQuery("SELECT id FROM " + t)) {
                        while (rs.next()) n++;
                    }
                    Assert.eq(10000, n, "streamed row count");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(FW, "load", "stream_cursor_fetch_minvalue", () -> {
            // useCursorFetch via Integer.MIN_VALUE fetch size on a streaming forward-only RS.
            String t = Db.uniq("streamcur");
            String url = db.config().dbUrl(DB) + "&useCursorFetch=true";
            try (Connection c = java.sql.DriverManager.getConnection(url, db.config().user, db.config().pass)) {
                seedRows(c, t, 12000);
                try (var st = c.createStatement(ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
                    st.setFetchSize(1000);
                    int n = 0;
                    try (ResultSet rs = st.executeQuery("SELECT id FROM " + t)) {
                        while (rs.next()) n++;
                    }
                    Assert.eq(12000, n, "cursor-fetched row count");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });
        // Wide table (100+ cols)
        r.register(FW, "load", "wide_table_120_cols", () -> {
            String t = Db.uniq("wide");
            StringBuilder cols = new StringBuilder("id INT PRIMARY KEY");
            StringBuilder names = new StringBuilder();
            StringBuilder vals = new StringBuilder("1");
            for (int i = 0; i < 120; i++) {
                cols.append(", c").append(i).append(" INT");
                names.append(", c").append(i);
                vals.append(", ").append(i);
            }
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (" + cols + ")");
                    st.execute("INSERT INTO " + t + " (id" + names + ") VALUES (" + vals + ")");
                    long got = Db.scalarLong(c, "SELECT c119 FROM " + t + " WHERE id=1");
                    Assert.eq(119L, got, "wide table last column");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // Deep JSON
        r.register(FW, "load", "deep_json_document", () -> {
            String t = Db.uniq("deepjson");
            StringBuilder json = new StringBuilder();
            for (int i = 0; i < 30; i++) json.append("{\"l\":");
            json.append("42");
            for (int i = 0; i < 30; i++) json.append("}");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT, doc JSON)");
                }
                try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setString(1, json.toString());
                    ps.executeUpdate();
                }
                String back = Db.scalarStr(c, "SELECT doc FROM " + t + " WHERE id=1");
                Assert.notNull(back, "deep json round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // Big VARCHAR
        r.register(FW, "load", "big_varchar_60kb", () -> {
            String t = Db.uniq("bigvc");
            String big = "x".repeat(60000);
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT, v LONGTEXT)");
                }
                try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setString(1, big);
                    ps.executeUpdate();
                }
                long len = Db.scalarLong(c, "SELECT CHAR_LENGTH(v) FROM " + t + " WHERE id=1");
                Assert.eq(60000L, len, "big varchar length round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // Big BLOB
        r.register(FW, "load", "big_blob_1mb", () -> {
            String t = Db.uniq("bigblob");
            byte[] data = new byte[1024 * 1024];
            for (int i = 0; i < data.length; i++) data[i] = (byte) (i & 0xFF);
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT, b LONGBLOB)");
                }
                try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setBytes(1, data);
                    ps.executeUpdate();
                }
                long len = Db.scalarLong(c, "SELECT LENGTH(b) FROM " + t + " WHERE id=1");
                Assert.eq((long) data.length, len, "big blob length round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // Multi-statement
        r.register(FW, "load", "multi_statement_query", () -> {
            String t = Db.uniq("multi");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    boolean hasResult = st.execute(
                            "INSERT INTO " + t + " VALUES (1); INSERT INTO " + t + " VALUES (2); SELECT COUNT(*) FROM " + t);
                    // drain results
                    int guard = 0;
                    while (guard++ < 10) {
                        if (hasResult) {
                            try (ResultSet rs = st.getResultSet()) { while (rs != null && rs.next()) {} }
                        } else {
                            if (st.getUpdateCount() == -1) break;
                        }
                        hasResult = st.getMoreResults();
                    }
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    Assert.eq(2L, n, "multi-statement inserted rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // HikariCP pool behavior
        r.register(FW, "load", "hikaricp_pool_roundtrip", () -> {
            com.zaxxer.hikari.HikariConfig hc = new com.zaxxer.hikari.HikariConfig();
            hc.setJdbcUrl(db.config().dbUrl(DB));
            hc.setUsername(db.config().user);
            hc.setPassword(db.config().pass);
            hc.setMaximumPoolSize(4);
            hc.setConnectionTimeout(8000);
            try (com.zaxxer.hikari.HikariDataSource ds = new com.zaxxer.hikari.HikariDataSource(hc)) {
                for (int i = 0; i < 8; i++) {
                    try (Connection c = ds.getConnection()) {
                        long one = Db.scalarLong(c, "SELECT 1");
                        Assert.eq(1L, one, "pooled select 1");
                    }
                }
            }
        });
        // Generated keys
        r.register(FW, "load", "generated_keys_single", () -> {
            String t = Db.uniq("gk");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT AUTO_INCREMENT PRIMARY KEY, v INT)");
                }
                try (var ps = c.prepareStatement("INSERT INTO " + t + " (v) VALUES (?)", Statement.RETURN_GENERATED_KEYS)) {
                    ps.setInt(1, 7);
                    ps.executeUpdate();
                    try (ResultSet rs = ps.getGeneratedKeys()) {
                        Assert.isTrue(rs.next(), "generated key present");
                        Assert.isTrue(rs.getLong(1) >= 1, "generated key value");
                    }
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "load", "last_insert_id_multirow", () -> {
            // KNOWN C6: after multi-row insert, LAST_INSERT_ID returns LAST id (MySQL: first).
            String t = Db.uniq("lii");
            try (Connection c = db.connect(DB)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT AUTO_INCREMENT PRIMARY KEY, n INT)");
                    st.execute("INSERT INTO " + t + " (n) VALUES (1),(2),(3)");
                }
                long lii = Db.scalarLong(c, "SELECT LAST_INSERT_ID()");
                if (lii != 1) {
                    throw new BehaviorMismatch("LAST_INSERT_ID after multi-row insert",
                            "1 (first id, MySQL)", lii + " (last id, MatrixOne)");
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private static void seedRows(Connection c, String t, int rows) throws java.sql.SQLException {
        try (Statement st = c.createStatement()) {
            st.execute("CREATE TABLE " + t + " (id INT, v INT)");
        }
        boolean prevAuto = c.getAutoCommit();
        c.setAutoCommit(false);
        try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?,?)")) {
            for (int i = 0; i < rows; i++) {
                ps.setInt(1, i);
                ps.setInt(2, i);
                ps.addBatch();
                if (i % 1000 == 999) ps.executeBatch();
            }
            ps.executeBatch();
        }
        c.commit();
        c.setAutoCommit(prevAuto);
    }

    // ---------------------------------------------------------- Semantics ----

    private void registerSemantics(Runner r, Db db) {
        // C1: collation case sensitivity
        r.register(FW, "semantics", "string_equality_case_insensitive", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT ('abc' = 'ABC')");
                if (v != 1) throw new BehaviorMismatch("'abc' = 'ABC'", "1 (MySQL _ci)", v);
            }
        });
        r.register(FW, "semantics", "string_equality_accent_insensitive", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT (CONVERT('café' USING utf8mb4) = CONVERT('cafe' USING utf8mb4))");
                if (v != 1) throw new BehaviorMismatch("'café' = 'cafe'", "1 (MySQL _ci)", v);
            }
        });
        r.register(FW, "semantics", "trailing_space_equality", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT ('a ' = 'a')");
                if (v != 1) throw new BehaviorMismatch("'a ' = 'a'", "1 (MySQL PAD SPACE)", v);
            }
        });
        r.register(FW, "semantics", "like_case_insensitive", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT ('abc' LIKE 'ABC')");
                if (v != 1) throw new BehaviorMismatch("'abc' LIKE 'ABC'", "1 (MySQL _ci)", v);
            }
        });
        r.register(FW, "semantics", "collate_ci_override_honored", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT ('abc' = 'ABC' COLLATE utf8mb4_general_ci)");
                if (v != 1) throw new BehaviorMismatch("explicit COLLATE _ci", "1 (honored)", v + " (ignored)");
            }
        });
        // C2: bare FLOAT rounding
        r.register(FW, "semantics", "bare_float_preserves_fraction", () -> {
            String t = Db.uniq("flt");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (c FLOAT)");
                    st.execute("INSERT INTO " + t + " VALUES (3.5)");
                    String v = Db.scalarStr(c, "SELECT c FROM " + t);
                    double d = Double.parseDouble(v);
                    if (Math.abs(d - 3.5) > 1e-6) {
                        throw new BehaviorMismatch("bare FLOAT stores 3.5", "3.5", v);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // C3: multi-table DELETE..JOIN
        r.register(FW, "semantics", "multi_table_delete_join", () -> {
            String d1 = Db.uniq("d1");
            String d2 = Db.uniq("d2");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + d1 + " (id INT, x INT)");
                    st.execute("CREATE TABLE " + d2 + " (id INT)");
                    st.execute("INSERT INTO " + d1 + " VALUES (1,1),(2,2)");
                    st.execute("INSERT INTO " + d2 + " VALUES (1)");
                    st.execute("DELETE a FROM " + d1 + " a JOIN " + d2 + " b ON a.id = b.id");
                    long remaining = Db.scalarLong(c, "SELECT COUNT(*) FROM " + d1);
                    if (remaining != 1) {
                        throw new BehaviorMismatch("multi-table DELETE..JOIN remaining rows",
                                "1 (MySQL deletes only matched)", remaining + " (MatrixOne emptied table)");
                    }
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + d1);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + d2);
                }
            }
        });
        // C7: || concat vs OR
        r.register(FW, "semantics", "pipe_pipe_is_or", () -> {
            try (Connection c = db.connect(DB)) {
                String v = Db.scalarStr(c, "SELECT 1 || 0");
                if (!"1".equals(v)) {
                    throw new BehaviorMismatch("1 || 0", "1 (MySQL logical OR)", v + " (MatrixOne concat)");
                }
            }
        });
        // DOUBLE PRECISION keyword
        r.register(FW, "semantics", "double_precision_keyword", () -> {
            String t = Db.uniq("dp");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (c DOUBLE PRECISION)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // CAST narrowing throws instead of truncating
        r.register(FW, "semantics", "cast_char_narrowing", () -> {
            try (Connection c = db.connect(DB)) {
                String v = Db.scalarStr(c, "SELECT CAST('abcdef' AS CHAR(3))");
                Assert.eq("abc", v, "CAST narrowing should truncate to 'abc'");
            }
        });
        // string-to-number coercion
        r.register(FW, "semantics", "string_number_coercion", () -> {
            try (Connection c = db.connect(DB)) {
                long v = Db.scalarLong(c, "SELECT '10abc' + 5");
                Assert.eq(15L, v, "'10abc' + 5 should be 15 (MySQL coercion)");
            }
        });
        // ROLLBACK semantics covered in transactions.
    }
}
