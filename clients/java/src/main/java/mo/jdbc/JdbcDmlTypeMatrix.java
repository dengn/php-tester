package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;

/**
 * Per-type DML/DDL variation matrix: views, INSERT...SELECT, REPLACE/IGNORE,
 * ON DUPLICATE KEY UPDATE, TRUNCATE, and multi-row inserts for each type.
 */
public final class JdbcDmlTypeMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            // CREATE VIEW projecting this column
            r.register(fw, "dml_type", "view/" + ts.key, () -> {
                String t = Db.uniq("dt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("CREATE VIEW " + t + "_v AS SELECT id, c FROM " + t);
                        try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + "_v")) {
                            Assert.isTrue(rs.next(), "view returned a row");
                        }
                    } finally {
                        Db.quiet(st, "DROP VIEW IF EXISTS " + t + "_v");
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                    }
                }
            });
            // INSERT ... SELECT preserving this column
            r.register(fw, "dml_type", "insert_select/" + ts.key, () -> {
                String t = Db.uniq("dt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("CREATE TABLE " + t + "_2 (id INT, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + "_2 SELECT id, c FROM " + t);
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + "_2");
                        Assert.eq(1L, n, "insert-select copied row");
                    } finally {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t + "_2");
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                    }
                }
            });
            // multi-row insert
            r.register(fw, "dml_type", "multi_insert/" + ts.key, () -> {
                String t = Db.uniq("dt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral
                                + "), (2, " + ts.sampleLiteral + "), (3, " + ts.sampleLiteral + ")");
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                        Assert.eq(3L, n, "multi-row insert count");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // TRUNCATE then reinsert
            r.register(fw, "dml_type", "truncate/" + ts.key, () -> {
                String t = Db.uniq("dt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("TRUNCATE TABLE " + t);
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                        Assert.eq(0L, n, "truncate emptied table");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // ON DUPLICATE KEY UPDATE on indexable types
            if (ts.indexable) {
                r.register(fw, "dml_type", "on_dup_key/" + ts.key, () -> {
                    String t = Db.uniq("dt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral
                                    + ") ON DUPLICATE KEY UPDATE c = VALUES(c)");
                            long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                            Assert.eq(1L, n, "on-dup-key kept single row");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
                r.register(fw, "dml_type", "replace_into/" + ts.key, () -> {
                    String t = Db.uniq("dt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("REPLACE INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                            Assert.eq(1L, n, "replace-into kept single row");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
                r.register(fw, "dml_type", "insert_ignore/" + ts.key, () -> {
                    String t = Db.uniq("dt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT IGNORE INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                            Assert.eq(1L, n, "insert-ignore kept single row");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
        }
    }
}
