package mo.typesx;

import mo.harness.Assert;
import mo.harness.BehaviorMismatch;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SkipException;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Deeper type/semantics matrices: charset/collation, NULL/three-valued-logic,
 * numeric overflow/coercion, ENUM/SET/BIT, UUID strategies, JSON deep paths.
 */
public final class SemanticsGrid {

    public static void register(Runner r, Db db, String fw, String dbName) {
        registerCharsetCollation(r, db, fw, dbName);
        registerNullLogic(r, db, fw, dbName);
        registerNumericCoercion(r, db, fw, dbName);
        registerEnumSetBit(r, db, fw, dbName);
        registerUuid(r, db, fw, dbName);
        registerJsonPaths(r, db, fw, dbName);
    }

    // ------------------------------------------------ charset / collation ----

    private static void registerCharsetCollation(Runner r, Db db, String fw, String dbName) {
        String[] charsets = {"utf8mb4", "utf8", "latin1", "ascii", "gbk", "binary"};
        for (String cs : charsets) {
            r.register(fw, "charset", "column_charset/" + cs, () -> {
                String t = Db.uniq("cs_" + cs);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v VARCHAR(64) CHARACTER SET " + cs + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, 'hello')");
                        Assert.eq("hello", Db.scalarStr(c, "SELECT v FROM " + t + " WHERE id=1"), "charset round-trip " + cs);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        String[] collations = {
                "utf8mb4_general_ci", "utf8mb4_unicode_ci", "utf8mb4_bin", "utf8mb4_0900_ai_ci",
                "latin1_swedish_ci", "utf8mb4_0900_as_cs",
        };
        for (String coll : collations) {
            r.register(fw, "collation", "column_collation/" + coll, () -> {
                String t = Db.uniq("coll");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v VARCHAR(64) COLLATE " + coll + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, 'Hello')");
                        st.execute("INSERT INTO " + t + " VALUES (2, 'hello')");
                        // _ci collations would match both; _bin would match one.
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE v = 'hello'");
                        boolean ci = coll.endsWith("_ci");
                        if (ci && n < 2) {
                            throw new BehaviorMismatch("case-insensitive collation " + coll,
                                    "2 rows match 'hello' (MySQL _ci)", n + " (MatrixOne ignores _ci)");
                        }
                        Assert.isTrue(n >= 1, "collation equality matched at least one");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // Unicode round-trip with multibyte content
        String[][] unicode = {
                {"emoji", "Hello 😀 World 🎉"},
                {"cjk", "你好世界"},
                {"arabic", "مرحبا"},
                {"combining", "é̂̃"},
        };
        for (String[] u : unicode) {
            r.register(fw, "charset", "unicode_roundtrip/" + u[0], () -> {
                String t = Db.uniq("uni_" + u[0]);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v VARCHAR(64) CHARACTER SET utf8mb4)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setString(1, u[1]);
                        ps.executeUpdate();
                    }
                    String got = Db.scalarStr(c, "SELECT v FROM " + t + " WHERE id=1");
                    Assert.eq(u[1], got, "unicode round-trip " + u[0]);
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    // ----------------------------------------- NULL / three-valued logic ----

    private static void registerNullLogic(Runner r, Db db, String fw, String dbName) {
        // Each test is a SELECT returning an expected boolean/NULL.
        String[][] cases = {
                {"null_eq_null", "SELECT NULL = NULL", "null"},
                {"null_neq_null", "SELECT NULL <> NULL", "null"},
                {"null_is_null", "SELECT NULL IS NULL", "1"},
                {"null_is_not_null", "SELECT NULL IS NOT NULL", "0"},
                {"null_safe_eq", "SELECT NULL <=> NULL", "1"},
                {"null_safe_eq_val", "SELECT 1 <=> NULL", "0"},
                {"null_and_true", "SELECT NULL AND TRUE", "null"},
                {"null_and_false", "SELECT NULL AND FALSE", "0"},
                {"null_or_true", "SELECT NULL OR TRUE", "1"},
                {"null_or_false", "SELECT NULL OR FALSE", "null"},
                {"null_plus", "SELECT NULL + 1", "null"},
                {"coalesce_null", "SELECT COALESCE(NULL, NULL, 3)", "3"},
                {"ifnull", "SELECT IFNULL(NULL, 7)", "7"},
                {"nullif_equal", "SELECT NULLIF(5, 5)", "null"},
                {"nullif_diff", "SELECT NULLIF(5, 6)", "5"},
                {"in_with_null", "SELECT 1 IN (2, NULL)", "null"},
                {"not_in_with_null", "SELECT 1 NOT IN (2, NULL)", "null"},
                {"null_concat", "SELECT CONCAT('a', NULL)", "null"},
                {"count_star_with_null", "SELECT COUNT(*) FROM (SELECT NULL UNION ALL SELECT 1) x", "2"},
                {"count_col_with_null", "SELECT COUNT(c) FROM (SELECT NULL c UNION ALL SELECT 1) x", "1"},
                {"sum_with_null", "SELECT SUM(c) FROM (SELECT NULL c UNION ALL SELECT 5) x", "5"},
                {"avg_ignores_null", "SELECT AVG(c) FROM (SELECT NULL c UNION ALL SELECT 4 UNION ALL SELECT 6) x", "5"},
        };
        for (String[] cse : cases) {
            r.register(fw, "null_logic", cse[0], () -> {
                try (Connection c = db.connect(dbName)) {
                    Object o = Db.scalar(c, cse[1]);
                    String got = o == null ? "null" : o.toString();
                    String exp = cse[2];
                    // numeric normalization: MO may return BIGINT 0/1 or decimals
                    if (!exp.equals("null") && !got.equals("null")) {
                        try { got = String.valueOf((long) Double.parseDouble(got)); exp = String.valueOf((long) Double.parseDouble(exp)); }
                        catch (NumberFormatException ignore) {}
                    }
                    Assert.eq(exp, got, "three-valued-logic " + cse[0]);
                }
            });
        }
    }

    // --------------------------------------- numeric overflow / coercion ----

    private static void registerNumericCoercion(Runner r, Db db, String fw, String dbName) {
        // overflow on insert into narrow integer types
        String[][] over = {
                {"tinyint_over", "TINYINT", "200"},
                {"tinyint_under", "TINYINT", "-200"},
                {"smallint_over", "SMALLINT", "70000"},
                {"int_over", "INT", "5000000000"},
                {"tinyint_unsigned_neg", "TINYINT UNSIGNED", "-1"},
        };
        for (String[] o : over) {
            r.register(fw, "numeric_overflow", o[0], () -> {
                String t = Db.uniq("ovf");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (c " + o[1] + ")");
                        boolean rejected = false;
                        try { st.execute("INSERT INTO " + t + " VALUES (" + o[2] + ")"); }
                        catch (Exception e) { rejected = true; }
                        Assert.isTrue(rejected, "out-of-range " + o[1] + " value " + o[2] + " should be rejected");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // coercion expressions (MySQL implicit conversions)
        String[][] coerce = {
                {"string_to_int_add", "SELECT '10' + 5", "15"},
                {"int_to_string_concat", "SELECT CONCAT(1, 2)", "12"},
                {"bool_arithmetic", "SELECT TRUE + TRUE", "2"},
                {"float_to_int_cast", "SELECT CAST(3.7 AS SIGNED)", "4"},
                {"hex_literal", "SELECT 0x41 + 0", "65"},
                {"division_returns_decimal", "SELECT 5 / 2", "2.5000"},
                {"integer_division", "SELECT 5 DIV 2", "2"},
                {"modulo", "SELECT 7 % 3", "1"},
                {"negative_mod", "SELECT -7 % 3", "-1"},
                {"scientific_notation", "SELECT 1.5e2 + 0", "150"},
        };
        for (String[] cse : coerce) {
            r.register(fw, "numeric_coercion", cse[0], () -> {
                try (Connection c = db.connect(dbName)) {
                    String got = Db.scalarStr(c, cse[1]);
                    Assert.notNull(got, "coercion produced a value for " + cse[0]);
                    // normalize numeric comparison
                    try {
                        double g = Double.parseDouble(got), e = Double.parseDouble(cse[2]);
                        Assert.isTrue(Math.abs(g - e) < 1e-6, cse[0] + " expected=" + cse[2] + " got=" + got);
                    } catch (NumberFormatException nfe) {
                        Assert.eq(cse[2], got, "coercion " + cse[0]);
                    }
                }
            });
        }
    }

    // ------------------------------------------------ ENUM / SET / BIT ----

    private static void registerEnumSetBit(Runner r, Db db, String fw, String dbName) {
        r.register(fw, "enumset", "enum_roundtrip", () -> {
            String t = Db.uniq("en");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c ENUM('low','mid','high'))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 'mid')");
                    Assert.eq("mid", Db.scalarStr(c, "SELECT c FROM " + t + " WHERE id=1"), "enum value");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "enumset", "enum_invalid_value", () -> {
            String t = Db.uniq("en2");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (c ENUM('a','b'))");
                    boolean rejected = false;
                    try { st.execute("INSERT INTO " + t + " VALUES ('zzz')"); } catch (Exception e) { rejected = true; }
                    Assert.isTrue(rejected, "invalid enum value should be rejected");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "enumset", "enum_ordinal_index", () -> {
            String t = Db.uniq("en3");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c ENUM('a','b','c'))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 'b')");
                    long idx = Db.scalarLong(c, "SELECT c+0 FROM " + t + " WHERE id=1");
                    Assert.eq(2L, idx, "enum ordinal index (1-based)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "enumset", "set_roundtrip", () -> {
            String t = Db.uniq("se");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c SET('r','w','x'))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 'r,x')");
                    String v = Db.scalarStr(c, "SELECT c FROM " + t + " WHERE id=1");
                    Assert.notNull(v, "set value");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "enumset", "set_find_in_set", () -> {
            String t = Db.uniq("se2");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c SET('r','w','x'))");
                    st.execute("INSERT INTO " + t + " VALUES (1, 'r,x')");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE FIND_IN_SET('x', c) > 0");
                    Assert.eq(1L, n, "find_in_set on SET column");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        for (int bits : new int[]{1, 8, 16, 32, 64}) {
            r.register(fw, "enumset", "bit_" + bits + "_roundtrip", () -> {
                String t = Db.uniq("bit");
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c BIT(" + bits + "))");
                    }
                    long val = bits >= 64 ? 0x0123456789ABCDEFL : ((1L << bits) - 1);
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setLong(1, val);
                        ps.executeUpdate();
                    }
                    try (Statement st = c.createStatement();
                         ResultSet rs = st.executeQuery("SELECT c+0 FROM " + t + " WHERE id=1")) {
                        rs.next();
                        Assert.eq(val, rs.getLong(1), "BIT(" + bits + ") round-trip");
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    // ----------------------------------------------------- UUID strategies ----

    private static void registerUuid(Runner r, Db db, String fw, String dbName) {
        r.register(fw, "uuid", "uuid_as_char36", () -> {
            String t = Db.uniq("uu");
            java.util.UUID id = java.util.UUID.randomUUID();
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id CHAR(36) PRIMARY KEY, v INT)");
                }
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, 1)")) {
                    ps.setString(1, id.toString());
                    ps.executeUpdate();
                }
                Assert.eq(id.toString(), Db.scalarStr(c, "SELECT id FROM " + t), "uuid char36 round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "uuid", "uuid_as_binary16", () -> {
            String t = Db.uniq("uub");
            java.util.UUID id = java.util.UUID.randomUUID();
            byte[] bytes = new byte[16];
            java.nio.ByteBuffer.wrap(bytes).putLong(id.getMostSignificantBits()).putLong(id.getLeastSignificantBits());
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id BINARY(16) PRIMARY KEY, v INT)");
                }
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, 1)")) {
                    ps.setBytes(1, bytes);
                    ps.executeUpdate();
                }
                try (Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT id FROM " + t)) {
                    rs.next();
                    Assert.eq(16, rs.getBytes(1).length, "uuid binary16 length");
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "uuid", "uuid_function", () -> {
            try (Connection c = db.connect(dbName)) {
                String u = Db.scalarStr(c, "SELECT UUID()");
                Assert.isTrue(u != null && u.length() >= 32, "UUID() returned a uuid string");
            }
        });
        r.register(fw, "uuid", "uuid_short_function", () -> {
            try (Connection c = db.connect(dbName)) {
                Object o = Db.scalar(c, "SELECT UUID_SHORT()");
                Assert.notNull(o, "UUID_SHORT()");
            }
        });
    }

    // ------------------------------------------------------- JSON deep paths ----

    private static void registerJsonPaths(Runner r, Db db, String fw, String dbName) {
        String doc = "{\"user\":{\"name\":\"Ann\",\"roles\":[\"admin\",\"editor\"],"
                + "\"prefs\":{\"theme\":\"dark\",\"langs\":[\"en\",\"fr\"]}},\"active\":true,\"score\":42.5}";
        String[][] paths = {
                {"extract_nested", "JSON_EXTRACT(doc, '$.user.name')", "\"Ann\""},
                {"extract_array_elem", "JSON_EXTRACT(doc, '$.user.roles[0]')", "\"admin\""},
                {"extract_deep_array", "JSON_EXTRACT(doc, '$.user.prefs.langs[1]')", "\"fr\""},
                {"arrow_op", "doc -> '$.score'", "42.5"},
                {"unquote_arrow", "doc ->> '$.user.name'", "Ann"},
                {"json_unquote", "JSON_UNQUOTE(JSON_EXTRACT(doc, '$.user.prefs.theme'))", "dark"},
                {"json_length_array", "JSON_LENGTH(JSON_EXTRACT(doc, '$.user.roles'))", "2"},
                {"json_keys", "JSON_KEYS(JSON_EXTRACT(doc, '$.user'))", null},
                {"json_contains", "JSON_CONTAINS(doc, '\"admin\"', '$.user.roles')", "1"},
                {"json_type", "JSON_TYPE(JSON_EXTRACT(doc, '$.user.roles'))", null},
                {"json_valid", "JSON_VALID(doc)", "1"},
                {"json_search", "JSON_SEARCH(doc, 'one', 'Ann')", null},
                {"json_array_agg_path", "JSON_EXTRACT(doc, '$.active')", "true"},
        };
        for (String[] p : paths) {
            r.register(fw, "json_paths", p[0], () -> {
                String t = Db.uniq("jp");
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, doc JSON)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setString(1, doc);
                        ps.executeUpdate();
                    }
                    try (Statement st = c.createStatement();
                         ResultSet rs = st.executeQuery("SELECT " + p[1] + " FROM " + t + " WHERE id=1")) {
                        Assert.isTrue(rs.next(), "json path row");
                        String got = rs.getString(1);
                        if (p[2] != null) {
                            Assert.eq(p[2], got == null ? null : got.replace(" ", ""), "json path " + p[0]);
                        } else {
                            Assert.notNull(got, "json path " + p[0] + " returned value");
                        }
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // JSON mutation functions
        String[][] mutate = {
                {"json_set", "JSON_SET('{\"a\":1}', '$.b', 2)"},
                {"json_insert", "JSON_INSERT('{\"a\":1}', '$.b', 2)"},
                {"json_replace", "JSON_REPLACE('{\"a\":1}', '$.a', 9)"},
                {"json_remove", "JSON_REMOVE('{\"a\":1,\"b\":2}', '$.b')"},
                {"json_merge_patch", "JSON_MERGE_PATCH('{\"a\":1}', '{\"b\":2}')"},
                {"json_array", "JSON_ARRAY(1, 'x', true)"},
                {"json_object", "JSON_OBJECT('k', 'v', 'n', 1)"},
                {"json_quote", "JSON_QUOTE('hello')"},
                {"json_depth", "JSON_DEPTH('{\"a\":{\"b\":1}}')"},
                {"json_pretty", "JSON_PRETTY('{\"a\":1}')"},
        };
        for (String[] m : mutate) {
            r.register(fw, "json_paths", "mutate/" + m[0], () -> {
                try (Connection c = db.connect(dbName)) {
                    Object o = Db.scalar(c, "SELECT " + m[1]);
                    Assert.notNull(o, "json mutation " + m[0]);
                }
            });
        }
    }

    private SemanticsGrid() {}
}
