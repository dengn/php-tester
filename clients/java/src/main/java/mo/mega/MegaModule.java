package mo.mega;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.LinkedHashMap;
import java.util.Map;

import mo.harness.BehaviorMismatch;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;

/**
 * High-volume generated coverage (raw JDBC), mirroring the PHP/Python/Node
 * MegaMatrix providers: operator/operand grids, function-input variations, a
 * cast matrix, and parametric query-workload shapes over a shared seeded
 * fixture. Most expression cases assert only that MatrixOne executes them (a
 * divergence => a finding); workload cases assert a plausible scalar.
 *
 * A single shared connection is reused for all (read-only) scenarios to keep
 * the suite fast; failed SELECTs do not corrupt the JDBC connection.
 */
public final class MegaModule implements SuiteModule {
    private static final String FW = "mega";
    private static final String DB = "mo_java_mega";

    private Connection conn;
    private boolean fixtureReady = false;

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private Connection conn(Db db) throws Exception {
        if (conn == null || conn.isClosed()) {
            conn = db.connect(DB);
        }
        return conn;
    }

    private void exprCheck(Db db, String expr, String expect) throws Exception {
        try (Statement st = conn(db).createStatement();
             ResultSet rs = st.executeQuery("SELECT " + expr + " AS v")) {
            String got = rs.next() ? rs.getString(1) : null;
            if (expect != null && !String.valueOf(expect).equals(String.valueOf(got))) {
                try {
                    if (Math.abs(Double.parseDouble(got) - Double.parseDouble(expect)) < 1e-9) {
                        return;
                    }
                } catch (Exception ignore) { /* not numeric */ }
                throw new BehaviorMismatch(expr + ": expected " + expect + " got " + got);
            }
        }
    }

    private void addExpr(Runner r, Db db, String cat, String name, String expr) {
        r.register(FW, cat, name, () -> exprCheck(db, expr, null));
    }

    private static Map<String, String> operands() {
        Map<String, String> m = new LinkedHashMap<>();
        m.put("int0", "0"); m.put("int1", "1"); m.put("intNeg", "-7"); m.put("intBig", "2147483647");
        m.put("bigint", "9223372036854775807"); m.put("dec", "12.50"); m.put("decNeg", "-3.14");
        m.put("frac", "0.001"); m.put("numStr", "'42'"); m.put("mixStr", "'10abc'"); m.put("str", "'hello'");
        m.put("date", "'2026-06-23'"); m.put("dt", "'2026-06-23 10:20:30'"); m.put("nul", "NULL");
        m.put("zeroStr", "'0'"); m.put("bigDec", "99999999999999999999.99");
        return m;
    }

    private void grids(Runner r, Db db) {
        Map<String, String> ops = new LinkedHashMap<>();
        ops.put("+", "add"); ops.put("-", "sub"); ops.put("*", "mul"); ops.put("/", "div");
        ops.put("DIV", "idiv"); ops.put("%", "mod");
        Map<String, String> vals = operands();
        for (Map.Entry<String, String> o : ops.entrySet()) {
            for (Map.Entry<String, String> a : vals.entrySet()) {
                for (Map.Entry<String, String> b : vals.entrySet()) {
                    addExpr(r, db, "grid:arith", a.getKey() + " " + o.getValue() + " " + b.getKey(),
                            a.getValue() + " " + o.getKey() + " " + b.getValue());
                }
            }
        }
        Map<String, String> cmp = new LinkedHashMap<>();
        cmp.put("=", "eq"); cmp.put("<>", "ne"); cmp.put("<", "lt"); cmp.put("<=", "le");
        cmp.put(">", "gt"); cmp.put(">=", "ge"); cmp.put("<=>", "nseq");
        for (Map.Entry<String, String> o : cmp.entrySet()) {
            for (Map.Entry<String, String> a : vals.entrySet()) {
                for (Map.Entry<String, String> b : vals.entrySet()) {
                    addExpr(r, db, "grid:compare", a.getKey() + " " + o.getValue() + " " + b.getKey(),
                            a.getValue() + " " + o.getKey() + " " + b.getValue());
                }
            }
        }
        String[] ints = {"0", "1", "5", "255", "-1", "1024", "9223372036854775807"};
        Map<String, String> bit = new LinkedHashMap<>();
        bit.put("&", "and"); bit.put("|", "or"); bit.put("^", "xor"); bit.put("<<", "shl"); bit.put(">>", "shr");
        for (Map.Entry<String, String> o : bit.entrySet()) {
            for (String a : ints) {
                for (String b : ints) {
                    addExpr(r, db, "grid:bitwise", a + " " + o.getValue() + " " + b, a + " " + o.getKey() + " " + b);
                }
            }
        }
        String[] bools = {"0", "1", "NULL", "5", "-1"};
        Map<String, String> lg = new LinkedHashMap<>();
        lg.put("AND", "and"); lg.put("OR", "or"); lg.put("XOR", "xor");
        for (Map.Entry<String, String> o : lg.entrySet()) {
            for (String a : bools) {
                for (String b : bools) {
                    addExpr(r, db, "grid:logical", a + " " + o.getValue() + " " + b, a + " " + o.getKey() + " " + b);
                }
            }
        }
        for (String a : bools) {
            addExpr(r, db, "grid:logical", "NOT " + a, "NOT " + a);
            addExpr(r, db, "grid:bitwise", "~ " + a, "~ " + a);
        }
    }

    private void functions(Runner r, Db db) {
        String[] strIn = {"'abc'", "''", "'Hello World'", "'  pad  '", "'a,b,c'", "'2026-06-23'", "'123.45'", "'xyz'"};
        String[] strFns = {"UPPER", "LOWER", "LENGTH", "CHAR_LENGTH", "REVERSE", "TRIM", "LTRIM", "RTRIM",
                "HEX", "TO_BASE64", "MD5", "SHA1", "SOUNDEX", "ORD", "ASCII", "BIT_LENGTH", "QUOTE"};
        for (String fn : strFns) {
            for (int i = 0; i < strIn.length; i++) {
                addExpr(r, db, "fn:string-var", fn + " #" + i, fn + "(" + strIn[i] + ")");
            }
        }
        for (String fn : new String[]{"LEFT", "RIGHT", "REPEAT"}) {
            for (int i = 0; i < strIn.length; i++) {
                for (String k : new String[]{"0", "1", "3", "50"}) {
                    addExpr(r, db, "fn:string-var", fn + " #" + i + "," + k, fn + "(" + strIn[i] + ", " + k + ")");
                }
            }
        }
        String[] numIn = {"0", "1", "-1", "3.14159", "-2.5", "1000000", "0.0001", "255"};
        String[] numFns = {"ABS", "CEIL", "FLOOR", "SIGN", "SQRT", "EXP", "LN", "LOG2", "LOG10",
                "SIN", "COS", "TAN", "ASIN", "ACOS", "ATAN", "DEGREES", "RADIANS", "CRC32", "BIN", "OCT"};
        for (String fn : numFns) {
            for (int i = 0; i < numIn.length; i++) {
                addExpr(r, db, "fn:numeric-var", fn + " #" + i, fn + "(" + numIn[i] + ")");
            }
        }
        for (String fn : new String[]{"ROUND", "TRUNCATE"}) {
            for (int i = 0; i < numIn.length; i++) {
                for (String d : new String[]{"-2", "0", "2", "4"}) {
                    addExpr(r, db, "fn:numeric-var", fn + " #" + i + "," + d, fn + "(" + numIn[i] + ", " + d + ")");
                }
            }
        }
        String[] dateIn = {"'2026-06-23'", "'2024-02-29'", "'2026-12-31 23:59:59'", "'2000-01-01'"};
        String[] dateFns = {"YEAR", "MONTH", "DAY", "HOUR", "MINUTE", "SECOND", "QUARTER", "WEEK",
                "DAYOFWEEK", "DAYOFYEAR", "DAYNAME", "MONTHNAME", "LAST_DAY", "TO_DAYS", "WEEKDAY"};
        for (String fn : dateFns) {
            for (int i = 0; i < dateIn.length; i++) {
                addExpr(r, db, "fn:date-var", fn + " #" + i, fn + "(" + dateIn[i] + ")");
            }
        }
        for (String u : new String[]{"DAY", "WEEK", "MONTH", "QUARTER", "YEAR", "HOUR"}) {
            for (int i = 0; i < dateIn.length; i++) {
                addExpr(r, db, "fn:date-var", "ADD " + u + " #" + i, "DATE_ADD(" + dateIn[i] + ", INTERVAL 3 " + u + ")");
            }
        }
        for (String f : new String[]{"%Y-%m-%d", "%H:%i:%s", "%W %M %Y", "%j", "%p %r"}) {
            for (int i = 0; i < dateIn.length; i++) {
                addExpr(r, db, "fn:date-format", "fmt " + f + " #" + i, "DATE_FORMAT(" + dateIn[i] + ", '" + f + "')");
            }
        }
    }

    private void casts(Runner r, Db db) {
        String[] targets = {"SIGNED", "UNSIGNED", "CHAR", "CHAR(5)", "DECIMAL(10,2)", "DECIMAL(20,4)",
                "DATE", "DATETIME", "TIME", "DOUBLE", "FLOAT", "BINARY", "JSON", "NCHAR"};
        String[] sources = {"'42'", "'-3.14'", "'2026-06-23'", "'2026-06-23 10:20:30'", "'10:20:30'",
                "'abc'", "255", "3.14159", "'[1,2,3]'", "NULL"};
        for (String t : targets) {
            for (String s : sources) {
                addExpr(r, db, "cast:matrix", "CAST " + s + " AS " + t, "CAST(" + s + " AS " + t + ")");
                addExpr(r, db, "cast:matrix", "CONVERT " + s + "," + t, "CONVERT(" + s + ", " + t + ")");
            }
        }
    }

    private void ensureFixture(Db db) throws Exception {
        if (fixtureReady) {
            return;
        }
        Connection c = conn(db);
        try (Statement st = c.createStatement()) {
            st.execute("DROP TABLE IF EXISTS qw_orders");
            st.execute("DROP TABLE IF EXISTS qw_users");
            st.execute("CREATE TABLE qw_users (id INT PRIMARY KEY, name VARCHAR(40), region VARCHAR(10), tier INT)");
            st.execute("CREATE TABLE qw_orders (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, product VARCHAR(20), qty INT, price DECIMAL(10,2), status VARCHAR(12), region VARCHAR(10), created DATETIME)");
            String[] regions = {"NA", "EU", "APAC", "LATAM"};
            String[] status = {"new", "paid", "shipped", "cancelled", "refunded"};
            String[] prods = {"widget", "gadget", "gizmo", "doohickey", "thingamajig"};
            StringBuilder u = new StringBuilder("INSERT INTO qw_users VALUES ");
            for (int i = 1; i <= 200; i++) {
                if (i > 1) u.append(',');
                u.append('(').append(i).append(",'user").append(i).append("','").append(regions[i % 4]).append("',").append((i % 3) + 1).append(')');
            }
            st.execute(u.toString());
            for (int b = 0; b < 15; b++) {
                StringBuilder o = new StringBuilder("INSERT INTO qw_orders(user_id,product,qty,price,status,region,created) VALUES ");
                for (int i = 1; i <= 100; i++) {
                    int n = b * 100 + i;
                    if (i > 1) o.append(',');
                    o.append('(').append((n % 200) + 1).append(",'").append(prods[n % 5]).append("',").append((n % 10) + 1)
                            .append(',').append(String.format("%.2f", (n % 1000) + 0.99)).append(",'").append(status[n % 5])
                            .append("','").append(regions[n % 4]).append("','")
                            .append(String.format("2026-%02d-%02d %02d:00:00", (n % 12) + 1, (n % 27) + 1, n % 24)).append("')");
                }
                st.execute(o.toString());
            }
        }
        fixtureReady = true;
    }

    private void addQuery(Runner r, Db db, String cat, String name, String sql) {
        r.register(FW, cat, name, () -> {
            ensureFixture(db);
            try (Statement st = conn(db).createStatement(); ResultSet rs = st.executeQuery(sql)) {
                if (!rs.next() || rs.getObject(1) == null) {
                    throw new BehaviorMismatch("query returned no row");
                }
            }
        });
    }

    private void workload(Runner r, Db db) {
        String[] cols = {"qty", "price", "status", "region", "product", "user_id"};
        String[] ops = {"=", "<>", "<", "<=", ">", ">=", "LIKE", "IN"};
        Map<String, String[]> vals = new LinkedHashMap<>();
        vals.put("qty", new String[]{"5", "0", "11"});
        vals.put("price", new String[]{"100.00", "0.99", "500"});
        vals.put("status", new String[]{"'paid'", "'PAID'", "'unknown'"});
        vals.put("region", new String[]{"'EU'", "'eu'", "'NA'"});
        vals.put("product", new String[]{"'widget'", "'WIDGET'", "'w%'"});
        vals.put("user_id", new String[]{"1", "100", "999"});
        for (String c : cols) {
            for (String op : ops) {
                for (String v : vals.get(c)) {
                    String expr;
                    if (op.equals("IN")) {
                        expr = c + " IN (" + v + ", " + v + ")";
                    } else if (op.equals("LIKE")) {
                        expr = c + " LIKE " + v;
                    } else {
                        expr = c + " " + op + " " + v;
                    }
                    addQuery(r, db, "workload:filter", c + " " + op + " " + v, "SELECT COUNT(*) FROM qw_orders WHERE " + expr);
                }
            }
        }
        for (String c : cols) {
            for (String d : new String[]{"ASC", "DESC"}) {
                for (String pg : new String[]{"LIMIT 10", "LIMIT 10 OFFSET 50", "LIMIT 1 OFFSET 999", "LIMIT 100 OFFSET 1400"}) {
                    addQuery(r, db, "workload:sort", c + " " + d + " " + pg,
                            "SELECT COUNT(*) FROM (SELECT id FROM qw_orders ORDER BY " + c + " " + d + " " + pg + ") z");
                }
            }
        }
        String[] aggs = {"COUNT(*)", "SUM(price)", "AVG(qty)", "MIN(price)", "MAX(qty)", "COUNT(DISTINCT user_id)"};
        for (String g : new String[]{"region", "status", "product", "qty"}) {
            for (String a : aggs) {
                addQuery(r, db, "workload:group", g + " " + a,
                        "SELECT COUNT(*) FROM (SELECT " + g + ", " + a + " m FROM qw_orders GROUP BY " + g + ") z");
                addQuery(r, db, "workload:group", g + " " + a + " having",
                        "SELECT COUNT(*) FROM (SELECT " + g + ", " + a + " m FROM qw_orders GROUP BY " + g + " HAVING " + a + " > 0) z");
            }
        }
        for (String w : new String[]{"ROW_NUMBER()", "RANK()", "DENSE_RANK()", "SUM(price)", "AVG(qty)", "LAG(price)", "LEAD(qty)", "NTILE(4)"}) {
            for (String part : new String[]{"region", "status", "product"}) {
                addQuery(r, db, "workload:window", w + " over " + part,
                        "SELECT COUNT(*) FROM (SELECT " + w + " OVER (PARTITION BY " + part + " ORDER BY id) wv FROM qw_orders) z");
            }
        }
        for (String jt : new String[]{"JOIN", "LEFT JOIN", "RIGHT JOIN"}) {
            for (String on : new String[]{"o.user_id=u.id", "o.region=u.region"}) {
                for (String w : new String[]{"", "WHERE u.tier=1", "WHERE o.status='paid'"}) {
                    addQuery(r, db, "workload:join", jt + " " + on + " " + w,
                            "SELECT COUNT(*) FROM qw_orders o " + jt + " qw_users u ON " + on + " " + w);
                }
            }
        }
        for (String g : new String[]{"region", "status", "product"}) {
            addQuery(r, db, "workload:analytics", "rollup " + g,
                    "SELECT COUNT(*) FROM (SELECT " + g + ", SUM(price) FROM qw_orders GROUP BY " + g + " WITH ROLLUP) z");
            addQuery(r, db, "workload:analytics", "month " + g,
                    "SELECT COUNT(*) FROM (SELECT MONTH(created) m, " + g + ", SUM(price) FROM qw_orders GROUP BY MONTH(created), " + g + ") z");
            addQuery(r, db, "workload:subquery", "in " + g,
                    "SELECT COUNT(*) FROM qw_orders WHERE " + g + " IN (SELECT " + g + " FROM qw_orders WHERE price > 100)");
            addQuery(r, db, "workload:subquery", "scalar " + g,
                    "SELECT COUNT(*) FROM qw_orders WHERE price > (SELECT AVG(price) FROM qw_orders)");
        }
    }

    @Override
    public void register(Runner r, Db db) {
        grids(r, db);
        functions(r, db);
        casts(r, db);
        workload(r, db);
    }
}
