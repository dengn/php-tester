import java.math.BigDecimal;
import java.sql.*;
import java.time.*;
import java.util.*;

/**
 * Standalone JDBC API conformance probe.
 *
 * Exercises the java.sql.* feature surface against a target database and
 * classifies each feature as WORKS / UNSUPPORTED / BROKEN, plus dumps the
 * DatabaseMetaData capability picture. Driver-level (not ORM) compatibility.
 *
 * Usage: java -cp .:mysql-connector-j.jar JdbcApiTest <host> <port> <user> <pass>
 */
public class JdbcApiTest {
    enum R { WORKS, UNSUPPORTED, BROKEN }
    static final List<String[]> rows = new ArrayList<>(); // {cat,name,result,detail}
    static int works, unsup, broken;

    interface Act { void run(Connection c) throws Exception; }

    static Connection C;

    static void check(String cat, String name, Act a) {
        try {
            a.run(C);
            rec(cat, name, R.WORKS, "");
        } catch (SQLFeatureNotSupportedException e) {
            rec(cat, name, R.UNSUPPORTED, msg(e));
        } catch (Throwable e) {
            String m = msg(e).toLowerCase();
            if (m.contains("not support") || m.contains("unimplemented") || m.contains("not implemented")
                    || m.contains("unsupported")) {
                rec(cat, name, R.UNSUPPORTED, msg(e));
            } else {
                rec(cat, name, R.BROKEN, msg(e));
            }
        }
    }

    static String msg(Throwable e) {
        String m = e.getMessage();
        return (m == null ? e.getClass().getSimpleName() : m).replaceAll("\\s+", " ");
    }

    static void rec(String cat, String name, R r, String detail) {
        if (r == R.WORKS) works++; else if (r == R.UNSUPPORTED) unsup++; else broken++;
        rows.add(new String[]{cat, name, r.name(), detail.length() > 110 ? detail.substring(0, 110) : detail});
    }

    static void assertTrue(boolean b, String m) { if (!b) throw new RuntimeException("assert: " + m); }

    public static void main(String[] args) throws Exception {
        String host = args.length > 0 ? args[0] : "127.0.0.1";
        String port = args.length > 1 ? args[1] : "6001";
        String user = args.length > 2 ? args[2] : "root";
        String pass = args.length > 3 ? args[3] : "111";
        String base = "jdbc:mysql://" + host + ":" + port + "/";
        String opt = "?allowPublicKeyRetrieval=true&useSSL=false&allowMultiQueries=true";

        // bootstrap db + sample schema
        try (Connection s = DriverManager.getConnection(base + opt, user, pass);
             Statement st = s.createStatement()) {
            st.execute("DROP DATABASE IF EXISTS jdbcapi_test");
            st.execute("CREATE DATABASE jdbcapi_test");
        }
        C = DriverManager.getConnection(base + "jdbcapi_test" + opt, user, pass);
        setupSchema();

        dumpMetaData();
        capabilityFlags();
        metadataMethods();
        connectionFeatures();
        statementFeatures();
        preparedSetters();
        preparedMeta();
        resultSetGetters();
        resultSetNavigation();
        resultSetMetaData();
        transactions();
        lobs();
        callableStatements();
        batch();
        generatedKeys();
        exceptionsWarnings();
        wrapper();

        report();
        C.close();
    }

    static void setupSchema() throws SQLException {
        try (Statement st = C.createStatement()) {
            st.execute("CREATE TABLE parent (id INT PRIMARY KEY, label VARCHAR(20))");
            st.execute("CREATE TABLE sample (" +
                    "id INT PRIMARY KEY AUTO_INCREMENT, b TINYINT, s SMALLINT, i INT, l BIGINT, " +
                    "f FLOAT, d DOUBLE, dec_c DECIMAL(12,3), str VARCHAR(50), txt TEXT, bin VARBINARY(64), " +
                    "dt DATE, tm TIME, ts DATETIME, bl BLOB, flag TINYINT(1), pid INT, " +
                    "UNIQUE KEY uk_str (str), KEY idx_i (i), " +
                    "CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES parent(id))");
            st.execute("INSERT INTO parent VALUES (1,'p1'),(2,'p2')");
            st.execute("INSERT INTO sample (b,s,i,l,f,d,dec_c,str,txt,bin,dt,tm,ts,bl,flag,pid) VALUES " +
                    "(1,2,3,4,1.5,2.5,12.345,'alice','hello text',0x0102,'2026-06-23','10:20:30','2026-06-23 10:20:30',0x0304,1,1)," +
                    "(5,6,7,8,9.5,10.5,99.999,'bob','more text',0x0506,'2025-01-01','01:02:03','2025-01-01 01:02:03',0x0708,0,2)");
        }
    }

    // ---------------------------------------------------------------- metadata
    static void dumpMetaData() throws SQLException {
        DatabaseMetaData m = C.getMetaData();
        System.out.println("==== DatabaseMetaData ====");
        p("databaseProductName", m.getDatabaseProductName());
        p("databaseProductVersion", m.getDatabaseProductVersion());
        p("driverName", m.getDriverName());
        p("driverVersion", m.getDriverVersion());
        p("JDBC version", m.getJDBCMajorVersion() + "." + m.getJDBCMinorVersion());
        p("defaultTxIsolation", String.valueOf(m.getDefaultTransactionIsolation()));
        p("identifierQuote", "[" + m.getIdentifierQuoteString() + "]");
        p("SQLKeywords (len)", String.valueOf(m.getSQLKeywords().length()));
        p("numericFunctions (n)", String.valueOf(m.getNumericFunctions().split(",").length));
        p("stringFunctions (n)", String.valueOf(m.getStringFunctions().split(",").length));
        p("timeDateFunctions (n)", String.valueOf(m.getTimeDateFunctions().split(",").length));
        p("maxConnections", String.valueOf(m.getMaxConnections()));
        p("maxStatementLength", String.valueOf(m.getMaxStatementLength()));
        try { p("rowIdLifetime", String.valueOf(m.getRowIdLifetime())); } catch (Throwable t) { p("rowIdLifetime", "ERR"); }
    }
    static void p(String k, String v) { System.out.printf("  %-26s %s%n", k, v); }

    static void capabilityFlags() throws SQLException {
        DatabaseMetaData m = C.getMetaData();
        System.out.println("==== supportsXxx capability flags ====");
        String[][] flags = {
            {"transactions", b(m.supportsTransactions())},
            {"savepoints", b(m.supportsSavepoints())},
            {"storedProcedures", b(m.supportsStoredProcedures())},
            {"storedFunctionsUsingCallSyntax", b(m.supportsStoredFunctionsUsingCallSyntax())},
            {"batchUpdates", b(m.supportsBatchUpdates())},
            {"getGeneratedKeys", b(m.supportsGetGeneratedKeys())},
            {"generatedKeyAlwaysReturned", b(m.generatedKeyAlwaysReturned())},
            {"multipleResultSets", b(m.supportsMultipleResultSets())},
            {"multipleOpenResults", b(m.supportsMultipleOpenResults())},
            {"namedParameters", b(m.supportsNamedParameters())},
            {"selectForUpdate", b(m.supportsSelectForUpdate())},
            {"outerJoins", b(m.supportsOuterJoins())},
            {"fullOuterJoins", b(m.supportsFullOuterJoins())},
            {"unionAll", b(m.supportsUnionAll())},
            {"correlatedSubqueries", b(m.supportsCorrelatedSubqueries())},
            {"RS:FORWARD_ONLY", b(m.supportsResultSetType(ResultSet.TYPE_FORWARD_ONLY))},
            {"RS:SCROLL_INSENSITIVE", b(m.supportsResultSetType(ResultSet.TYPE_SCROLL_INSENSITIVE))},
            {"RS:SCROLL_SENSITIVE", b(m.supportsResultSetType(ResultSet.TYPE_SCROLL_SENSITIVE))},
            {"RS:CONCUR_UPDATABLE", b(m.supportsResultSetConcurrency(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_UPDATABLE))},
            {"RS:HOLD_OVER_COMMIT", b(m.supportsResultSetHoldability(ResultSet.HOLD_CURSORS_OVER_COMMIT))},
            {"TX:READ_UNCOMMITTED", b(m.supportsTransactionIsolationLevel(Connection.TRANSACTION_READ_UNCOMMITTED))},
            {"TX:READ_COMMITTED", b(m.supportsTransactionIsolationLevel(Connection.TRANSACTION_READ_COMMITTED))},
            {"TX:REPEATABLE_READ", b(m.supportsTransactionIsolationLevel(Connection.TRANSACTION_REPEATABLE_READ))},
            {"TX:SERIALIZABLE", b(m.supportsTransactionIsolationLevel(Connection.TRANSACTION_SERIALIZABLE))},
            {"catalogsInDML", b(m.supportsCatalogsInDataManipulation())},
            {"schemasInDML", b(m.supportsSchemasInDataManipulation())},
            {"dataDefCausesCommit", b(m.dataDefinitionCausesTransactionCommit())},
        };
        for (String[] f : flags) {
            System.out.printf("  %-32s %s%n", f[0], f[1]);
            rec("capability", f[0], R.WORKS, f[1]);
        }
    }
    static String b(boolean v) { return v ? "YES" : "no"; }

    static void metadataMethods() {
        check("meta:getX", "getCatalogs", c -> drain(c.getMetaData().getCatalogs()));
        check("meta:getX", "getSchemas", c -> drain(c.getMetaData().getSchemas()));
        check("meta:getX", "getTableTypes", c -> drain(c.getMetaData().getTableTypes()));
        check("meta:getX", "getTables", c -> assertTrue(count(c.getMetaData().getTables("jdbcapi_test", null, "sample", null)) >= 1, "sample table"));
        check("meta:getX", "getColumns", c -> assertTrue(count(c.getMetaData().getColumns("jdbcapi_test", null, "sample", null)) >= 15, "columns"));
        check("meta:getX", "getPrimaryKeys", c -> assertTrue(count(c.getMetaData().getPrimaryKeys("jdbcapi_test", null, "sample")) >= 1, "pk"));
        check("meta:getX", "getImportedKeys (FK)", c -> assertTrue(count(c.getMetaData().getImportedKeys("jdbcapi_test", null, "sample")) >= 1, "FK metadata exposed"));
        check("meta:getX", "getExportedKeys", c -> drain(c.getMetaData().getExportedKeys("jdbcapi_test", null, "parent")));
        check("meta:getX", "getIndexInfo", c -> assertTrue(count(c.getMetaData().getIndexInfo("jdbcapi_test", null, "sample", false, false)) >= 1, "indexes"));
        check("meta:getX", "getTypeInfo", c -> assertTrue(count(c.getMetaData().getTypeInfo()) >= 1, "type info"));
        check("meta:getX", "getBestRowIdentifier", c -> drain(c.getMetaData().getBestRowIdentifier("jdbcapi_test", null, "sample", DatabaseMetaData.bestRowSession, true)));
        check("meta:getX", "getColumnPrivileges", c -> drain(c.getMetaData().getColumnPrivileges("jdbcapi_test", null, "sample", null)));
        check("meta:getX", "getFunctions", c -> drain(c.getMetaData().getFunctions("jdbcapi_test", null, null)));
        check("meta:getX", "getProcedures", c -> drain(c.getMetaData().getProcedures("jdbcapi_test", null, null)));
        check("meta:getX", "getClientInfoProperties", c -> drain(c.getMetaData().getClientInfoProperties()));
        check("meta:getX", "getCrossReference", c -> drain(c.getMetaData().getCrossReference("jdbcapi_test", null, "parent", "jdbcapi_test", null, "sample")));
    }

    // -------------------------------------------------------------- connection
    static void connectionFeatures() {
        check("conn", "getAutoCommit", c -> c.getAutoCommit());
        check("conn", "setAutoCommit(false/true)", c -> { c.setAutoCommit(false); c.setAutoCommit(true); });
        check("conn", "getTransactionIsolation", c -> c.getTransactionIsolation());
        for (int lvl : new int[]{Connection.TRANSACTION_READ_UNCOMMITTED, Connection.TRANSACTION_READ_COMMITTED,
                Connection.TRANSACTION_REPEATABLE_READ, Connection.TRANSACTION_SERIALIZABLE}) {
            check("conn", "setTransactionIsolation(" + lvl + ")", c -> c.setTransactionIsolation(lvl));
        }
        check("conn", "setReadOnly/isReadOnly", c -> { c.setReadOnly(true); c.isReadOnly(); c.setReadOnly(false); });
        check("conn", "isValid(2)", c -> assertTrue(c.isValid(2), "isValid"));
        check("conn", "getCatalog", c -> c.getCatalog());
        check("conn", "setCatalog", c -> c.setCatalog("jdbcapi_test"));
        check("conn", "getSchema", c -> c.getSchema());
        check("conn", "setSchema", c -> c.setSchema("jdbcapi_test"));
        check("conn", "getHoldability", c -> c.getHoldability());
        check("conn", "setHoldability(CLOSE)", c -> c.setHoldability(ResultSet.CLOSE_CURSORS_AT_COMMIT));
        check("conn", "nativeSQL", c -> c.nativeSQL("SELECT 1"));
        check("conn", "getNetworkTimeout", c -> c.getNetworkTimeout());
        check("conn", "getClientInfo", c -> c.getClientInfo());
        check("conn", "getTypeMap", c -> c.getTypeMap());
        check("conn", "createStatement(SCROLL_INSENSITIVE,READ_ONLY)", c -> c.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY).close());
        check("conn", "createArrayOf", c -> c.createArrayOf("INTEGER", new Object[]{1, 2, 3}));
        check("conn", "createBlob", c -> c.createBlob());
        check("conn", "createClob", c -> c.createClob());
        check("conn", "getWarnings", c -> c.getWarnings());
    }

    // --------------------------------------------------------------- statement
    static void statementFeatures() {
        check("stmt", "executeUpdate", c -> { try (Statement s = c.createStatement()) { s.executeUpdate("CREATE TABLE st1 (id INT)"); s.executeUpdate("DROP TABLE st1"); } });
        check("stmt", "executeLargeUpdate", c -> { try (Statement s = c.createStatement()) { s.executeLargeUpdate("CREATE TABLE st2 (id INT)"); s.executeLargeUpdate("DROP TABLE st2"); } });
        check("stmt", "getUpdateCount/getResultSet", c -> { try (Statement s = c.createStatement()) { s.execute("SELECT 1"); s.getResultSet(); s.getUpdateCount(); } });
        check("stmt", "getMoreResults (multi RS)", c -> { try (Statement s = c.createStatement()) { boolean has = s.execute("SELECT 1; SELECT 2"); int n = 1; while (s.getMoreResults()) n++; assertTrue(n >= 2, "multiple result sets, got " + n); } });
        check("stmt", "setMaxRows", c -> { try (Statement s = c.createStatement()) { s.setMaxRows(1); assertTrue(count(s.executeQuery("SELECT * FROM sample")) == 1, "maxRows honoured"); } });
        check("stmt", "setLargeMaxRows", c -> { try (Statement s = c.createStatement()) { s.setLargeMaxRows(1); } });
        check("stmt", "setFetchSize", c -> { try (Statement s = c.createStatement()) { s.setFetchSize(10); } });
        check("stmt", "setFetchSize(Integer.MIN) stream", c -> { try (Statement s = c.createStatement(ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) { s.setFetchSize(Integer.MIN_VALUE); drain(s.executeQuery("SELECT * FROM sample")); } });
        check("stmt", "setQueryTimeout", c -> { try (Statement s = c.createStatement()) { s.setQueryTimeout(5); drain(s.executeQuery("SELECT 1")); } });
        check("stmt", "setMaxFieldSize", c -> { try (Statement s = c.createStatement()) { s.setMaxFieldSize(100); } });
        check("stmt", "setEscapeProcessing", c -> { try (Statement s = c.createStatement()) { s.setEscapeProcessing(true); } });
        check("stmt", "closeOnCompletion", c -> { try (Statement s = c.createStatement()) { s.closeOnCompletion(); s.isCloseOnCompletion(); } });
        check("stmt", "setPoolable", c -> { try (Statement s = c.createStatement()) { s.setPoolable(true); s.isPoolable(); } });
        check("stmt", "setCursorName", c -> { try (Statement s = c.createStatement()) { s.setCursorName("cur1"); } });
        check("stmt", "JDBC escape {fn now()}", c -> { try (Statement s = c.createStatement()) { drain(s.executeQuery("SELECT {fn NOW()}")); } });
    }

    // -------------------------------------------------------- preparedstatement
    static void preparedSetters() {
        // round-trip a value through a typed column via a prepared insert+select
        setTest("setBoolean", "flag TINYINT(1)", (ps) -> ps.setBoolean(1, true));
        setTest("setByte", "b TINYINT", (ps) -> ps.setByte(1, (byte) 7));
        setTest("setShort", "s SMALLINT", (ps) -> ps.setShort(1, (short) 12));
        setTest("setInt", "i INT", (ps) -> ps.setInt(1, 42));
        setTest("setLong", "l BIGINT", (ps) -> ps.setLong(1, 9000000000L));
        setTest("setFloat", "f FLOAT", (ps) -> ps.setFloat(1, 1.5f));
        setTest("setDouble", "d DOUBLE", (ps) -> ps.setDouble(1, 2.5));
        setTest("setBigDecimal", "dec_c DECIMAL(12,3)", (ps) -> ps.setBigDecimal(1, new BigDecimal("12.345")));
        setTest("setString", "str VARCHAR(50)", (ps) -> ps.setString(1, "hello"));
        setTest("setBytes", "bin VARBINARY(64)", (ps) -> ps.setBytes(1, new byte[]{1, 2, 3}));
        setTest("setDate", "dt DATE", (ps) -> ps.setDate(1, java.sql.Date.valueOf("2026-06-23")));
        setTest("setTime", "tm TIME", (ps) -> ps.setTime(1, Time.valueOf("10:20:30")));
        setTest("setTimestamp", "ts DATETIME", (ps) -> ps.setTimestamp(1, Timestamp.valueOf("2026-06-23 10:20:30")));
        setTest("setNull(INTEGER)", "i INT", (ps) -> ps.setNull(1, Types.INTEGER));
        setTest("setObject(Integer)", "i INT", (ps) -> ps.setObject(1, 99));
        setTest("setObject(targetSqlType)", "i INT", (ps) -> ps.setObject(1, "77", Types.INTEGER));
        setTest("setObject(LocalDate)", "dt DATE", (ps) -> ps.setObject(1, LocalDate.of(2026, 6, 23)));
        setTest("setObject(LocalDateTime)", "ts DATETIME", (ps) -> ps.setObject(1, LocalDateTime.of(2026, 6, 23, 10, 20, 30)));
        setTest("setObject(LocalTime)", "tm TIME", (ps) -> ps.setObject(1, LocalTime.of(10, 20, 30)));
        setTest("setBlob(byte stream)", "bl BLOB", (ps) -> ps.setBinaryStream(1, new java.io.ByteArrayInputStream(new byte[]{9, 8, 7})));
        setTest("setCharacterStream", "txt TEXT", (ps) -> ps.setCharacterStream(1, new java.io.StringReader("streamed")));
        setTest("setNString", "str VARCHAR(50)", (ps) -> ps.setNString(1, "ncar"));
    }

    interface Setter { void set(PreparedStatement ps) throws Exception; }
    static void setTest(String name, String coldef, Setter setter) {
        check("pstmt:set", name, c -> {
            String t = "ps_" + Math.abs(name.hashCode());
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS `" + t + "`"); s.execute("CREATE TABLE `" + t + "` (c " + coldef.split(" ", 2)[1] + ")"); }
            try (PreparedStatement ps = c.prepareStatement("INSERT INTO `" + t + "` (c) VALUES (?)")) { setter.set(ps); ps.executeUpdate(); }
            try (Statement s = c.createStatement()) { drain(s.executeQuery("SELECT c FROM `" + t + "`")); s.execute("DROP TABLE `" + t + "`"); }
        });
    }

    static void preparedMeta() {
        check("pstmt:meta", "ParameterMetaData getParameterCount", c -> { try (PreparedStatement ps = c.prepareStatement("SELECT * FROM sample WHERE i=? AND str=?")) { assertTrue(ps.getParameterMetaData().getParameterCount() == 2, "param count"); } });
        check("pstmt:meta", "ParameterMetaData getParameterType", c -> { try (PreparedStatement ps = c.prepareStatement("SELECT * FROM sample WHERE i=?")) { ps.getParameterMetaData().getParameterType(1); } });
        check("pstmt:meta", "getMetaData before execute", c -> { try (PreparedStatement ps = c.prepareStatement("SELECT id,str FROM sample")) { ResultSetMetaData md = ps.getMetaData(); assertTrue(md != null && md.getColumnCount() == 2, "prepared RS metadata"); } });
        check("pstmt:meta", "prepared batch", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS pb"); s.execute("CREATE TABLE pb (id INT)"); }
            try (PreparedStatement ps = c.prepareStatement("INSERT INTO pb VALUES (?)")) {
                for (int i = 0; i < 5; i++) { ps.setInt(1, i); ps.addBatch(); }
                int[] r = ps.executeBatch(); assertTrue(r.length == 5, "batch counts");
            }
            try (Statement s = c.createStatement()) { assertTrue(Db.cnt(c, "SELECT COUNT(*) FROM pb") == 5, "batch rows"); s.execute("DROP TABLE pb"); }
        });
    }

    // ---------------------------------------------------------------- resultset
    static void resultSetGetters() {
        String[][] g = {
            {"getInt", "i"}, {"getLong", "l"}, {"getShort", "s"}, {"getByte", "b"}, {"getDouble", "d"},
            {"getFloat", "f"}, {"getBigDecimal", "dec_c"}, {"getString", "str"}, {"getBytes", "bin"},
            {"getDate", "dt"}, {"getTime", "tm"}, {"getTimestamp", "ts"}, {"getBoolean", "flag"},
            {"getObject", "i"}, {"getObject(Class<Integer>)", "i"}, {"getObject(LocalDate)", "dt"},
            {"getObject(LocalDateTime)", "ts"}, {"getNString", "str"}, {"getCharacterStream", "txt"},
            {"getBinaryStream", "bl"}, {"getBlob", "bl"},
        };
        for (String[] gx : g) {
            check("rs:get", gx[0], c -> {
                try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT * FROM sample ORDER BY id LIMIT 1")) {
                    rs.next();
                    switch (gx[0]) {
                        case "getInt": rs.getInt(gx[1]); break;
                        case "getLong": rs.getLong(gx[1]); break;
                        case "getShort": rs.getShort(gx[1]); break;
                        case "getByte": rs.getByte(gx[1]); break;
                        case "getDouble": rs.getDouble(gx[1]); break;
                        case "getFloat": rs.getFloat(gx[1]); break;
                        case "getBigDecimal": rs.getBigDecimal(gx[1]); break;
                        case "getString": rs.getString(gx[1]); break;
                        case "getBytes": rs.getBytes(gx[1]); break;
                        case "getDate": rs.getDate(gx[1]); break;
                        case "getTime": rs.getTime(gx[1]); break;
                        case "getTimestamp": rs.getTimestamp(gx[1]); break;
                        case "getBoolean": rs.getBoolean(gx[1]); break;
                        case "getObject": rs.getObject(gx[1]); break;
                        case "getObject(Class<Integer>)": rs.getObject(gx[1], Integer.class); break;
                        case "getObject(LocalDate)": rs.getObject(gx[1], LocalDate.class); break;
                        case "getObject(LocalDateTime)": rs.getObject(gx[1], LocalDateTime.class); break;
                        case "getNString": rs.getNString(gx[1]); break;
                        case "getCharacterStream": rs.getCharacterStream(gx[1]); break;
                        case "getBinaryStream": rs.getBinaryStream(gx[1]); break;
                        case "getBlob": rs.getBlob(gx[1]); break;
                        default: break;
                    }
                }
            });
        }
        check("rs:get", "wasNull", c -> { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT NULL AS n")) { rs.next(); rs.getObject(1); assertTrue(rs.wasNull(), "wasNull"); } });
        check("rs:get", "findColumn", c -> { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT i FROM sample")) { assertTrue(rs.findColumn("i") == 1, "findColumn"); } });
    }

    static void resultSetNavigation() {
        check("rs:nav", "forward next/getRow", c -> { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT id FROM sample ORDER BY id")) { rs.next(); assertTrue(rs.getRow() == 1, "getRow"); } });
        check("rs:nav", "SCROLL_INSENSITIVE last/first/absolute", c -> {
            try (Statement s = c.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
                 ResultSet rs = s.executeQuery("SELECT id FROM sample ORDER BY id")) {
                assertTrue(rs.last(), "last"); int n = rs.getRow();
                assertTrue(rs.first(), "first"); assertTrue(rs.absolute(n), "absolute"); rs.previous(); rs.relative(1); rs.beforeFirst(); rs.afterLast();
            }
        });
        check("rs:nav", "SCROLL_SENSITIVE", c -> { try (Statement s = c.createStatement(ResultSet.TYPE_SCROLL_SENSITIVE, ResultSet.CONCUR_READ_ONLY); ResultSet rs = s.executeQuery("SELECT id FROM sample")) { rs.last(); } });
        check("rs:nav", "getType/getConcurrency/getHoldability", c -> { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT 1")) { rs.getType(); rs.getConcurrency(); rs.getHoldability(); } });
        check("rs:nav", "setFetchDirection", c -> { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT 1")) { rs.setFetchDirection(ResultSet.FETCH_FORWARD); rs.setFetchSize(10); } });
        check("rs:update", "CONCUR_UPDATABLE updateRow", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS upd"); s.execute("CREATE TABLE upd (id INT PRIMARY KEY, v INT)"); s.execute("INSERT INTO upd VALUES (1,10)"); }
            try (Statement s = c.createStatement(ResultSet.TYPE_SCROLL_SENSITIVE, ResultSet.CONCUR_UPDATABLE);
                 ResultSet rs = s.executeQuery("SELECT id,v FROM upd")) { rs.next(); rs.updateInt("v", 20); rs.updateRow(); }
            assertTrue(Db.cnt(C, "SELECT v FROM upd WHERE id=1") == 20, "updatable RS updateRow took effect");
            try (Statement s = C.createStatement()) { s.execute("DROP TABLE upd"); }
        });
        check("rs:update", "CONCUR_UPDATABLE insertRow", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS ins"); s.execute("CREATE TABLE ins (id INT PRIMARY KEY, v INT)"); }
            try (Statement s = c.createStatement(ResultSet.TYPE_SCROLL_SENSITIVE, ResultSet.CONCUR_UPDATABLE);
                 ResultSet rs = s.executeQuery("SELECT id,v FROM ins")) { rs.moveToInsertRow(); rs.updateInt("id", 1); rs.updateInt("v", 5); rs.insertRow(); }
            try (Statement s = C.createStatement()) { s.execute("DROP TABLE ins"); }
        });
    }

    static void resultSetMetaData() {
        check("rs:meta", "ResultSetMetaData full", c -> {
            try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT id, str, dec_c FROM sample")) {
                ResultSetMetaData m = rs.getMetaData();
                assertTrue(m.getColumnCount() == 3, "colCount");
                for (int i = 1; i <= 3; i++) {
                    m.getColumnName(i); m.getColumnLabel(i); m.getColumnType(i); m.getColumnTypeName(i);
                    m.getColumnClassName(i); m.getPrecision(i); m.getScale(i); m.isNullable(i);
                    m.isAutoIncrement(i); m.isCaseSensitive(i); m.isSigned(i); m.getColumnDisplaySize(i);
                    m.getTableName(i); m.getSchemaName(i); m.getCatalogName(i); m.isReadOnly(i); m.isSearchable(i);
                }
            }
        });
        check("rs:meta", "isAutoIncrement correct", c -> {
            try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT id FROM sample")) {
                assertTrue(rs.getMetaData().isAutoIncrement(1), "id should report auto_increment");
            }
        });
    }

    static void transactions() {
        check("tx", "commit", c -> { c.setAutoCommit(false); try (Statement s = c.createStatement()) { s.execute("CREATE TABLE tx1 (id INT)"); } c.commit(); try (Statement s = c.createStatement()) { s.execute("DROP TABLE tx1"); } c.commit(); c.setAutoCommit(true); });
        check("tx", "rollback", c -> { c.setAutoCommit(false); try (Statement s = c.createStatement()) { s.execute("CREATE TABLE tx2 (id INT)"); s.execute("INSERT INTO tx2 VALUES (1)"); } long before = Db.cnt(c, "SELECT COUNT(*) FROM tx2"); c.rollback(); try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS tx2"); } c.commit(); c.setAutoCommit(true); assertTrue(before == 1, "insert visible pre-rollback"); });
        check("tx", "setSavepoint()", c -> { c.setAutoCommit(false); Savepoint sp = c.setSavepoint(); c.releaseSavepoint(sp); c.rollback(); c.setAutoCommit(true); });
        check("tx", "setSavepoint(name)", c -> { c.setAutoCommit(false); Savepoint sp = c.setSavepoint("sp1"); c.rollback(sp); c.rollback(); c.setAutoCommit(true); });
        check("tx", "rollback(savepoint) semantics", c -> {
            c.setAutoCommit(false);
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS sp_t"); s.execute("CREATE TABLE sp_t (id INT)"); s.execute("INSERT INTO sp_t VALUES (1)"); }
            Savepoint sp = c.setSavepoint("a");
            try (Statement s = c.createStatement()) { s.execute("INSERT INTO sp_t VALUES (2)"); }
            c.rollback(sp);
            long n = Db.cnt(c, "SELECT COUNT(*) FROM sp_t");
            c.commit();
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE sp_t"); } c.commit();
            c.setAutoCommit(true);
            assertTrue(n == 1, "rollback-to-savepoint should leave 1 row, got " + n);
        });
    }

    static void lobs() {
        check("lob", "createBlob + setBytes + getBytes", c -> { Blob b = c.createBlob(); b.setBytes(1, new byte[]{1, 2, 3}); assertTrue(b.length() == 3, "blob length"); b.getBytes(1, 3); b.free(); });
        check("lob", "createClob + setString + getSubString", c -> { Clob cl = c.createClob(); cl.setString(1, "hello"); assertTrue(cl.length() == 5, "clob length"); cl.getSubString(1, 5); cl.free(); });
        check("lob", "BLOB column round-trip via getBlob", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS lobt"); s.execute("CREATE TABLE lobt (id INT, b BLOB)"); }
            try (PreparedStatement ps = c.prepareStatement("INSERT INTO lobt VALUES (1, ?)")) { ps.setBytes(1, new byte[]{4, 5, 6}); ps.executeUpdate(); }
            try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery("SELECT b FROM lobt")) { rs.next(); Blob bl = rs.getBlob(1); assertTrue(bl.length() == 3, "read blob"); }
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE lobt"); }
        });
    }

    static void callableStatements() {
        check("callable", "prepareCall + stored procedure", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP PROCEDURE IF EXISTS p1"); s.execute("CREATE PROCEDURE p1() BEGIN SELECT 1; END"); }
            try (CallableStatement cs = c.prepareCall("{call p1()}")) { cs.execute(); }
        });
        check("callable", "registerOutParameter + function", c -> {
            try (CallableStatement cs = c.prepareCall("{? = call abs(?)}")) { cs.registerOutParameter(1, Types.INTEGER); cs.setInt(2, -5); cs.execute(); }
        });
    }

    static void batch() {
        check("batch", "Statement addBatch/executeBatch", c -> {
            try (Statement s = c.createStatement()) {
                s.execute("DROP TABLE IF EXISTS bt"); s.execute("CREATE TABLE bt (id INT)");
                s.addBatch("INSERT INTO bt VALUES (1)"); s.addBatch("INSERT INTO bt VALUES (2)"); s.addBatch("INSERT INTO bt VALUES (3)");
                int[] r = s.executeBatch(); assertTrue(r.length == 3, "batch length");
                assertTrue(Db.cnt(c, "SELECT COUNT(*) FROM bt") == 3, "batch rows");
                s.execute("DROP TABLE bt");
            }
        });
        check("batch", "executeLargeBatch", c -> { try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS bt2"); s.execute("CREATE TABLE bt2 (id INT)"); s.addBatch("INSERT INTO bt2 VALUES (1)"); s.executeLargeBatch(); s.execute("DROP TABLE bt2"); } });
    }

    static void generatedKeys() {
        check("genkeys", "Statement RETURN_GENERATED_KEYS", c -> {
            try (Statement s = c.createStatement()) {
                s.execute("DROP TABLE IF EXISTS gk"); s.execute("CREATE TABLE gk (id INT PRIMARY KEY AUTO_INCREMENT, v INT)");
                s.executeUpdate("INSERT INTO gk (v) VALUES (10)", Statement.RETURN_GENERATED_KEYS);
                try (ResultSet rs = s.getGeneratedKeys()) { assertTrue(rs.next() && rs.getLong(1) >= 1, "generated key returned"); }
                s.execute("DROP TABLE gk");
            }
        });
        check("genkeys", "PreparedStatement getGeneratedKeys", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS gk2"); s.execute("CREATE TABLE gk2 (id INT PRIMARY KEY AUTO_INCREMENT, v INT)"); }
            try (PreparedStatement ps = c.prepareStatement("INSERT INTO gk2 (v) VALUES (?)", Statement.RETURN_GENERATED_KEYS)) { ps.setInt(1, 5); ps.executeUpdate(); try (ResultSet rs = ps.getGeneratedKeys()) { assertTrue(rs.next(), "pstmt gen key"); } }
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE gk2"); }
        });
    }

    static void exceptionsWarnings() {
        check("exc", "SQLException errorCode/SQLState", c -> {
            try (Statement s = c.createStatement()) { s.execute("SELECT * FROM no_such_table_xyz"); throw new RuntimeException("expected an exception"); }
            catch (SQLException e) { e.getErrorCode(); e.getSQLState(); /* both must be accessible */ }
        });
        check("exc", "SQLWarning getWarnings", c -> { c.getWarnings(); c.clearWarnings(); });
        check("exc", "BatchUpdateException on bad batch", c -> {
            try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS be"); s.execute("CREATE TABLE be (id INT PRIMARY KEY)"); s.addBatch("INSERT INTO be VALUES (1)"); s.addBatch("INSERT INTO be VALUES (1)"); s.executeBatch(); }
            catch (BatchUpdateException e) { e.getUpdateCounts(); }
            finally { try (Statement s = c.createStatement()) { s.execute("DROP TABLE IF EXISTS be"); } catch (Exception ig) {} }
        });
    }

    static void wrapper() {
        check("wrapper", "Connection unwrap", c -> { c.isWrapperFor(Connection.class); });
        check("wrapper", "Statement unwrap", c -> { try (Statement s = c.createStatement()) { s.isWrapperFor(Statement.class); } });
    }

    // ------------------------------------------------------------------ helpers
    static void drain(ResultSet rs) throws SQLException { try (rs) { while (rs.next()) { } } }
    static int count(ResultSet rs) throws SQLException { int n = 0; try (rs) { while (rs.next()) n++; } return n; }

    static void report() {
        System.out.println("\n==================== JDBC API COMPATIBILITY ====================");
        // group by category
        Map<String, int[]> byCat = new LinkedHashMap<>();
        for (String[] r : rows) {
            if (r[0].equals("capability")) continue;
            int[] a = byCat.computeIfAbsent(r[0], k -> new int[3]);
            if (r[2].equals("WORKS")) a[0]++; else if (r[2].equals("UNSUPPORTED")) a[1]++; else a[2]++;
        }
        System.out.printf("%-16s %6s %12s %8s%n", "category", "WORKS", "UNSUPPORTED", "BROKEN");
        for (Map.Entry<String, int[]> e : byCat.entrySet())
            System.out.printf("%-16s %6d %12d %8d%n", e.getKey(), e.getValue()[0], e.getValue()[1], e.getValue()[2]);
        System.out.println("\n-- UNSUPPORTED features --");
        for (String[] r : rows) if (r[2].equals("UNSUPPORTED")) System.out.printf("  %-14s %-34s %s%n", r[0], r[1], r[3]);
        System.out.println("\n-- BROKEN features (driver error other than 'not supported') --");
        for (String[] r : rows) if (r[2].equals("BROKEN")) System.out.printf("  %-14s %-34s %s%n", r[0], r[1], r[3]);
        System.out.printf("%nTOTAL (excl. capability flags): WORKS=%d UNSUPPORTED=%d BROKEN=%d%n",
                works - countCap(), unsup, broken);
    }
    static int countCap() { int n = 0; for (String[] r : rows) if (r[0].equals("capability")) n++; return n; }

    // tiny scalar helper (avoids a separate Db class)
    static class Db { static long cnt(Connection c, String sql) throws SQLException { try (Statement s = c.createStatement(); ResultSet rs = s.executeQuery(sql)) { rs.next(); return rs.getLong(1); } } }
}
