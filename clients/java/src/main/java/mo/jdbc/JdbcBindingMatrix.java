package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.Date;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;
import java.sql.Time;
import java.sql.Timestamp;
import java.sql.Types;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.OffsetDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

/**
 * PreparedStatement typed-binding matrix: bind every JDBC/Java type through a
 * PreparedStatement, round-trip it, and read it back.
 */
public final class JdbcBindingMatrix {

    @FunctionalInterface
    private interface Binder {
        void bind(PreparedStatement ps, int idx) throws Exception;
    }

    private record Bind(String name, String colType, Binder binder) {}

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<Bind> binds = new ArrayList<>();

        binds.add(new Bind("setInt", "INT", (ps, i) -> ps.setInt(i, 12345)));
        binds.add(new Bind("setLong", "BIGINT", (ps, i) -> ps.setLong(i, 9_000_000_000L)));
        binds.add(new Bind("setShort", "SMALLINT", (ps, i) -> ps.setShort(i, (short) 1234)));
        binds.add(new Bind("setByte", "TINYINT", (ps, i) -> ps.setByte(i, (byte) 12)));
        binds.add(new Bind("setBoolean", "BOOLEAN", (ps, i) -> ps.setBoolean(i, true)));
        binds.add(new Bind("setFloat", "FLOAT(10,2)", (ps, i) -> ps.setFloat(i, 3.5f)));
        binds.add(new Bind("setDouble", "DOUBLE", (ps, i) -> ps.setDouble(i, 3.14159)));
        binds.add(new Bind("setBigDecimal", "DECIMAL(18,4)", (ps, i) -> ps.setBigDecimal(i, new BigDecimal("12345.6789"))));
        binds.add(new Bind("setString_varchar", "VARCHAR(255)", (ps, i) -> ps.setString(i, "hello")));
        binds.add(new Bind("setString_char", "CHAR(10)", (ps, i) -> ps.setString(i, "abc")));
        binds.add(new Bind("setString_text", "TEXT", (ps, i) -> ps.setString(i, "longer text body")));
        binds.add(new Bind("setNString", "VARCHAR(255)", (ps, i) -> ps.setNString(i, "unicode éè")));
        binds.add(new Bind("setBytes_varbinary", "VARBINARY(255)", (ps, i) -> ps.setBytes(i, new byte[]{1, 2, 3, 4})));
        binds.add(new Bind("setBytes_blob", "BLOB", (ps, i) -> ps.setBytes(i, new byte[]{(byte) 0xCA, (byte) 0xFE})));
        binds.add(new Bind("setDate", "DATE", (ps, i) -> ps.setDate(i, Date.valueOf("2026-06-23"))));
        binds.add(new Bind("setTime", "TIME", (ps, i) -> ps.setTime(i, Time.valueOf("11:22:33"))));
        binds.add(new Bind("setTimestamp", "DATETIME", (ps, i) -> ps.setTimestamp(i, Timestamp.valueOf("2026-06-23 11:22:33"))));
        binds.add(new Bind("setTimestamp_ts", "TIMESTAMP", (ps, i) -> ps.setTimestamp(i, Timestamp.valueOf("2026-06-23 11:22:33"))));
        binds.add(new Bind("setObject_localdate", "DATE", (ps, i) -> ps.setObject(i, LocalDate.of(2026, 6, 23))));
        binds.add(new Bind("setObject_localdatetime", "DATETIME", (ps, i) -> ps.setObject(i, LocalDateTime.of(2026, 6, 23, 11, 22, 33))));
        binds.add(new Bind("setObject_localtime", "TIME", (ps, i) -> ps.setObject(i, LocalTime.of(11, 22, 33))));
        binds.add(new Bind("setObject_offsetdatetime", "TIMESTAMP", (ps, i) -> ps.setObject(i, OffsetDateTime.now())));
        binds.add(new Bind("setObject_bigdecimal", "DECIMAL(10,2)", (ps, i) -> ps.setObject(i, new BigDecimal("99.99"))));
        binds.add(new Bind("setObject_uuid_char", "CHAR(36)", (ps, i) -> ps.setObject(i, UUID.randomUUID().toString())));
        binds.add(new Bind("setObject_integer", "INT", (ps, i) -> ps.setObject(i, Integer.valueOf(77))));
        binds.add(new Bind("setObject_with_type", "INT", (ps, i) -> ps.setObject(i, 88, Types.INTEGER)));
        binds.add(new Bind("setNull_int", "INT", (ps, i) -> ps.setNull(i, Types.INTEGER)));
        binds.add(new Bind("setNull_varchar", "VARCHAR(50)", (ps, i) -> ps.setNull(i, Types.VARCHAR)));
        binds.add(new Bind("setObject_json_string", "JSON", (ps, i) -> ps.setObject(i, "{\"k\":1}")));
        binds.add(new Bind("setString_enum", "ENUM('a','b','c')", (ps, i) -> ps.setString(i, "b")));
        binds.add(new Bind("setInt_year", "YEAR", (ps, i) -> ps.setInt(i, 2026)));

        for (Bind b : binds) {
            r.register(fw, "binding", b.name(), () -> {
                String t = Db.uniq("bind");
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + b.colType() + ")");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " (id, c) VALUES (?, ?)")) {
                        ps.setInt(1, 1);
                        b.binder().bind(ps, 2);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "bound insert affected one row");
                    }
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id = ?")) {
                        ps.setInt(1, 1);
                        try (ResultSet rs = ps.executeQuery()) {
                            Assert.isTrue(rs.next(), "bound row present");
                            rs.getObject(1);
                        }
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // setObject(value, java.sql.Types.X) across the JDBC type-code space.
        Object[][] typed = {
                {"INTEGER", Types.INTEGER, 42, "INT"},
                {"BIGINT", Types.BIGINT, 9_000_000_000L, "BIGINT"},
                {"SMALLINT", Types.SMALLINT, (short) 99, "SMALLINT"},
                {"TINYINT", Types.TINYINT, (byte) 7, "TINYINT"},
                {"BOOLEAN", Types.BOOLEAN, Boolean.TRUE, "BOOLEAN"},
                {"FLOAT", Types.FLOAT, 1.5f, "FLOAT(10,2)"},
                {"DOUBLE", Types.DOUBLE, 3.14159, "DOUBLE"},
                {"REAL", Types.REAL, 2.5f, "FLOAT(10,2)"},
                {"DECIMAL", Types.DECIMAL, new BigDecimal("99.99"), "DECIMAL(10,2)"},
                {"NUMERIC", Types.NUMERIC, new BigDecimal("12.34"), "DECIMAL(10,2)"},
                {"VARCHAR", Types.VARCHAR, "hello", "VARCHAR(50)"},
                {"CHAR", Types.CHAR, "abc", "CHAR(10)"},
                {"LONGVARCHAR", Types.LONGVARCHAR, "long text", "TEXT"},
                {"NVARCHAR", Types.NVARCHAR, "nchar", "VARCHAR(50)"},
                {"DATE", Types.DATE, Date.valueOf("2026-06-23"), "DATE"},
                {"TIME", Types.TIME, Time.valueOf("11:22:33"), "TIME"},
                {"TIMESTAMP", Types.TIMESTAMP, Timestamp.valueOf("2026-06-23 11:22:33"), "DATETIME"},
                {"VARBINARY", Types.VARBINARY, new byte[]{1, 2, 3}, "VARBINARY(50)"},
                {"BINARY", Types.BINARY, new byte[]{4, 5, 6, 7, 8, 9, 10, 11}, "BINARY(8)"},
                {"BLOB", Types.BLOB, new byte[]{9, 8, 7}, "BLOB"},
        };
        for (Object[] tp : typed) {
            String label = (String) tp[0];
            int jdbcType = (Integer) tp[1];
            Object value = tp[2];
            String colType = (String) tp[3];
            r.register(fw, "binding_typed", "setobject_" + label, () -> {
                String t = Db.uniq("bt");
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + colType + ")");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                        ps.setInt(1, 1);
                        ps.setObject(2, value, jdbcType);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "setObject(" + label + ") inserted one row");
                    }
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id = ?")) {
                        ps.setInt(1, 1);
                        try (ResultSet rs = ps.executeQuery()) {
                            Assert.isTrue(rs.next(), "row present");
                            rs.getObject(1);
                        }
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // typed null
            r.register(fw, "binding_typed", "setnull_" + label, () -> {
                String t = Db.uniq("bt");
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + colType + " NULL)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                        ps.setInt(1, 1);
                        ps.setNull(2, jdbcType);
                        int n = ps.executeUpdate();
                        Assert.eq(1, n, "setNull(" + label + ") inserted one row");
                    }
                    long nulls = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE c IS NULL");
                    Assert.eq(1L, nulls, "setNull stored NULL for " + label);
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Binding in WHERE / LIMIT placeholders.
        r.register(fw, "binding", "bind_where_param", () -> {
            String t = Db.uniq("bw");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT, v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1,10),(2,20),(3,30)");
                }
                try (PreparedStatement ps = c.prepareStatement("SELECT COUNT(*) FROM " + t + " WHERE v > ?")) {
                    ps.setInt(1, 15);
                    try (ResultSet rs = ps.executeQuery()) {
                        rs.next();
                        Assert.eq(2L, rs.getLong(1), "where param count");
                    }
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "binding", "bind_limit_param", () -> {
            String t = Db.uniq("bl");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1),(2),(3),(4),(5)");
                }
                try (PreparedStatement ps = c.prepareStatement("SELECT id FROM " + t + " ORDER BY id LIMIT ?")) {
                    ps.setInt(1, 2);
                    int n = 0;
                    try (ResultSet rs = ps.executeQuery()) { while (rs.next()) n++; }
                    Assert.eq(2, n, "limit param rows");
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "binding", "bind_in_list_params", () -> {
            String t = Db.uniq("bin");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1),(2),(3),(4)");
                }
                try (PreparedStatement ps = c.prepareStatement("SELECT COUNT(*) FROM " + t + " WHERE id IN (?,?,?)")) {
                    ps.setInt(1, 1); ps.setInt(2, 2); ps.setInt(3, 3);
                    try (ResultSet rs = ps.executeQuery()) {
                        rs.next();
                        Assert.eq(3L, rs.getLong(1), "in-list params count");
                    }
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        // ResultSet typed getters
        r.register(fw, "binding", "resultset_typed_getters", () -> {
            String t = Db.uniq("rsg");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (i INT, l BIGINT, d DOUBLE, s VARCHAR(20), dt DATE, n DECIMAL(10,2))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 2, 3.5, 'x', '2026-06-23', 9.99)");
                }
                try (Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT i,l,d,s,dt,n FROM " + t)) {
                    rs.next();
                    Assert.eq(1, rs.getInt("i"), "getInt");
                    Assert.eq(2L, rs.getLong("l"), "getLong");
                    Assert.isTrue(Math.abs(rs.getDouble("d") - 3.5) < 1e-9, "getDouble");
                    Assert.eq("x", rs.getString("s"), "getString");
                    Assert.notNull(rs.getDate("dt"), "getDate");
                    Assert.notNull(rs.getBigDecimal("n"), "getBigDecimal");
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }
}
