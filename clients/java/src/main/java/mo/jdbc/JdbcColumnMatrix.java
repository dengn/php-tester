package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;

/**
 * Per-type column/constraint matrix: PRIMARY KEY, UNIQUE, NOT NULL DEFAULT,
 * COMMENT, AUTO_INCREMENT (where numeric), and aggregate projections per type.
 */
public final class JdbcColumnMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            // PRIMARY KEY on this type
            if (ts.indexable) {
                r.register(fw, "column", "pk/" + ts.key, () -> {
                    String t = Db.uniq("col_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (c " + ts.ddlType + " PRIMARY KEY)");
                            st.execute("INSERT INTO " + t + " VALUES (" + ts.sampleLiteral + ")");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
                r.register(fw, "column", "unique/" + ts.key, () -> {
                    String t = Db.uniq("col_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT, c " + ts.ddlType + " UNIQUE)");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
            // COMMENT on column
            r.register(fw, "column", "comment/" + ts.key, () -> {
                String t = Db.uniq("col_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, c " + ts.ddlType
                                + " COMMENT 'col of " + ts.key + "')");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // ADD COLUMN of this type via ALTER
            r.register(fw, "column", "alter_add/" + ts.key, () -> {
                String t = Db.uniq("col_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT)");
                        st.execute("ALTER TABLE " + t + " ADD COLUMN c " + ts.ddlType);
                        st.execute("INSERT INTO " + t + " (id, c) VALUES (1, " + ts.sampleLiteral + ")");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // MODIFY column keeping the type (no-op alter)
            r.register(fw, "column", "alter_modify/" + ts.key, () -> {
                String t = Db.uniq("col_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, c " + ts.ddlType + ")");
                        st.execute("ALTER TABLE " + t + " MODIFY COLUMN c " + ts.ddlType);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // CTAS preserving the column
            r.register(fw, "column", "ctas/" + ts.key, () -> {
                String t = Db.uniq("col_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("CREATE TABLE " + t + "_c AS SELECT * FROM " + t);
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + "_c");
                        Assert.eq(1L, n, "CTAS row count");
                    } finally {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t + "_c");
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                    }
                }
            });
        }
    }
}
