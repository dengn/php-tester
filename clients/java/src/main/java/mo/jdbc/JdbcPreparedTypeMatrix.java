package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;

/**
 * PreparedStatement-driven per-type matrix: for each type, INSERT a sample value
 * via setObject through a PreparedStatement (binary-ish path), then read it back
 * via a parameterized SELECT and via an ORDER/WHERE bound query.
 *
 * The bound value is derived from the type's sample literal where it is a plain
 * Java object; otherwise the row is built with a literal and only the read path
 * is parameterized. This exercises the driver's typed binding for each column
 * type without depending on JDBC type codes.
 */
public final class JdbcPreparedTypeMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            // setObject bound insert (string form of the value).
            r.register(fw, "ptype", "setobject_insert/" + ts.key, () -> {
                String t = Db.uniq("pt_" + ts.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    }
                    Object bound = literalToBound(ts.sampleLiteral);
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                        ps.setInt(1, 1);
                        ps.setObject(2, bound);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "setObject insert affected one row");
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // parameterized read by id
            r.register(fw, "ptype", "param_read/" + ts.key, () -> {
                String t = Db.uniq("pt_" + ts.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id = ?")) {
                        ps.setInt(1, 1);
                        try (ResultSet rs = ps.executeQuery()) {
                            Assert.isTrue(rs.next(), "param read row present");
                            rs.getObject(1);
                        }
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // bound update
            r.register(fw, "ptype", "param_update/" + ts.key, () -> {
                String t = Db.uniq("pt_" + ts.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    Object bound = literalToBound(ts.sampleLiteral);
                    try (PreparedStatement ps = c.prepareStatement("UPDATE " + t + " SET c = ? WHERE id = ?")) {
                        ps.setObject(1, bound);
                        ps.setInt(2, 1);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "param update affected one row");
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // bound where-predicate per type (SELECT ... WHERE c = ?)
            if (ts.orderable) {
                r.register(fw, "ptype", "param_where/" + ts.key, () -> {
                    String t = Db.uniq("pt_" + ts.key);
                    try (Connection c = db.connect(dbName)) {
                        try (Statement st = c.createStatement()) {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        }
                        Object bound = literalToBound(ts.sampleLiteral);
                        try (PreparedStatement ps = c.prepareStatement(
                                "SELECT COUNT(*) FROM " + t + " WHERE c = ?")) {
                            ps.setObject(1, bound);
                            try (ResultSet rs = ps.executeQuery()) {
                                Assert.isTrue(rs.next(), "param where count row");
                                rs.getObject(1);
                            }
                        }
                        try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // null-bind per type (setNull / setObject(null))
            r.register(fw, "ptype", "null_bind/" + ts.key, () -> {
                String t = Db.uniq("pt_" + ts.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + " NULL)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                        ps.setInt(1, 1);
                        ps.setObject(2, null);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "null-bind insert affected one row");
                    }
                    long nullCount = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE c IS NULL");
                    Assert.eq(1L, nullCount, "null-bound value stored as NULL");
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // bound delete per type
            if (ts.orderable) {
                r.register(fw, "ptype", "param_delete/" + ts.key, () -> {
                    String t = Db.uniq("pt_" + ts.key);
                    try (Connection c = db.connect(dbName)) {
                        try (Statement st = c.createStatement()) {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        }
                        Object bound = literalToBound(ts.sampleLiteral);
                        try (PreparedStatement ps = c.prepareStatement(
                                "DELETE FROM " + t + " WHERE c = ?")) {
                            ps.setObject(1, bound);
                            int n = ps.executeUpdate();
                            Assert.isTrue(n >= 0, "param delete executed");
                        }
                        try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // batch insert per type
            r.register(fw, "ptype", "batch_insert/" + ts.key, () -> {
                String t = Db.uniq("pt_" + ts.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    }
                    Object bound = literalToBound(ts.sampleLiteral);
                    c.setAutoCommit(false);
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                        for (int i = 1; i <= 5; i++) {
                            ps.setInt(1, i);
                            ps.setObject(2, bound);
                            ps.addBatch();
                        }
                        int[] res = ps.executeBatch();
                        Assert.eq(5, res.length, "batch length per type");
                    }
                    c.commit();
                    c.setAutoCommit(true);
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    /**
     * Convert an SQL literal form to a bound Java object. For quoted strings we
     * strip the quotes and bind the string; for hex literals we bind bytes; for
     * everything else we bind the raw token as a String and let the driver/server
     * coerce it (MatrixOne accepts string-form binds for numeric/temporal types).
     */
    private static Object literalToBound(String literal) {
        String s = literal.trim();
        if (s.startsWith("'") && s.endsWith("'") && s.length() >= 2) {
            return s.substring(1, s.length() - 1).replace("''", "'");
        }
        if (s.startsWith("0x") || s.startsWith("0X")) {
            String hex = s.substring(2);
            int len = hex.length() / 2;
            byte[] out = new byte[len];
            for (int i = 0; i < len; i++) {
                out[i] = (byte) Integer.parseInt(hex.substring(i * 2, i * 2 + 2), 16);
            }
            return out;
        }
        if (s.startsWith("b'") && s.endsWith("'")) {
            // bit literal -> integer value
            return Long.parseLong(s.substring(2, s.length() - 1), 2);
        }
        // numeric token
        try {
            if (s.contains(".") || s.toLowerCase().contains("e")) {
                return Double.parseDouble(s);
            }
            return Long.parseLong(s);
        } catch (NumberFormatException e) {
            return s;
        }
    }
}
