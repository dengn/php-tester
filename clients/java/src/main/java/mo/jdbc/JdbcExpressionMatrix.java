package mo.jdbc;

import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Expression-level matrices: arithmetic/comparison operators across operand
 * pairs, date/time interval arithmetic across units, CAST across target types,
 * and JSON path extraction variations. Each is a single SELECT.
 */
public final class JdbcExpressionMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        registerArithmetic(r, db, fw, dbName);
        registerComparison(r, db, fw, dbName);
        registerInterval(r, db, fw, dbName);
        registerCast(r, db, fw, dbName);
        registerJsonPath(r, db, fw, dbName);
        registerStringExpr(r, db, fw, dbName);
    }

    private static void select(Runner r, String fw, String dbName, Db db,
                               String cat, String name, String expr) {
        r.register(fw, cat, name, () -> {
            try (Connection c = db.connect(dbName);
                 Statement st = c.createStatement();
                 ResultSet rs = st.executeQuery("SELECT " + expr)) {
                if (rs.next()) rs.getObject(1);
            }
        });
    }

    private static void registerArithmetic(Runner r, Db db, String fw, String dbName) {
        String[] ops = {"+", "-", "*", "/", "%", "DIV"};
        String[] operands = {"7", "3", "2.5", "10", "-4", "100", "0.1", "9999999"};
        for (String op : ops) {
            for (int i = 0; i < operands.length; i++) {
                String left = operands[i];
                String right = operands[(i + 1) % operands.length];
                String expr = op.equals("DIV") ? (left + " DIV " + right) : (left + " " + op + " " + right);
                select(r, fw, dbName, db, "expr_arith",
                        "arith_" + sanitize(op) + "_" + i, expr);
            }
        }
    }

    private static void registerComparison(Runner r, Db db, String fw, String dbName) {
        String[] ops = {"=", "<>", "<", ">", "<=", ">=", "<=>"};
        String[] operands = {"1", "2", "1.5", "'a'", "'b'", "NULL"};
        for (String op : ops) {
            for (int i = 0; i < operands.length; i++) {
                String left = operands[i];
                String right = operands[(i + 1) % operands.length];
                select(r, fw, dbName, db, "expr_cmp",
                        "cmp_" + sanitize(op) + "_" + i, left + " " + op + " " + right);
            }
        }
    }

    private static void registerInterval(Runner r, Db db, String fw, String dbName) {
        String[] units = {"MICROSECOND", "SECOND", "MINUTE", "HOUR", "DAY", "WEEK", "MONTH", "QUARTER", "YEAR"};
        String base = "'2026-06-23 11:22:33'";
        for (String u : units) {
            select(r, fw, dbName, db, "expr_interval",
                    "date_add_" + u, "DATE_ADD(" + base + ", INTERVAL 1 " + u + ")");
            select(r, fw, dbName, db, "expr_interval",
                    "date_sub_" + u, "DATE_SUB(" + base + ", INTERVAL 1 " + u + ")");
            select(r, fw, dbName, db, "expr_interval",
                    "extract_" + u, "EXTRACT(" + u + " FROM " + base + ")");
        }
        String[] diffUnits = {"SECOND", "MINUTE", "HOUR", "DAY", "WEEK", "MONTH", "QUARTER", "YEAR"};
        for (String u : diffUnits) {
            select(r, fw, dbName, db, "expr_interval",
                    "timestampdiff_" + u,
                    "TIMESTAMPDIFF(" + u + ", '2026-01-01 00:00:00', '2026-06-23 11:22:33')");
            select(r, fw, dbName, db, "expr_interval",
                    "timestampadd_" + u, "TIMESTAMPADD(" + u + ", 3, " + base + ")");
        }
    }

    private static void registerCast(Runner r, Db db, String fw, String dbName) {
        String[][] casts = {
                {"int_from_str", "CAST('123' AS SIGNED)"},
                {"uint_from_str", "CAST('123' AS UNSIGNED)"},
                {"dec_from_str", "CAST('1.50' AS DECIMAL(10,2))"},
                {"char_from_int", "CAST(123 AS CHAR)"},
                {"char_from_dec", "CAST(1.5 AS CHAR)"},
                {"date_from_str", "CAST('2026-06-23' AS DATE)"},
                {"time_from_str", "CAST('11:22:33' AS TIME)"},
                {"datetime_from_str", "CAST('2026-06-23 11:22:33' AS DATETIME)"},
                {"double_from_str", "CAST('3.14' AS DOUBLE)"},
                {"float_from_str", "CAST('3.5' AS FLOAT)"},
                {"binary_from_str", "CAST('abc' AS BINARY)"},
                {"signed_from_dec", "CAST(9.9 AS SIGNED)"},
                {"json_from_str", "CAST('{\"a\":1}' AS JSON)"},
                {"nchar_from_int", "CAST(42 AS NCHAR)"},
                {"convert_signed", "CONVERT('77', SIGNED)"},
                {"convert_char", "CONVERT(123, CHAR)"},
                {"convert_decimal", "CONVERT('9.99', DECIMAL(6,2))"},
                {"convert_date", "CONVERT('2026-06-23', DATE)"},
                {"convert_using_utf8", "CONVERT('abc' USING utf8mb4)"},
        };
        for (String[] cst : casts) {
            select(r, fw, dbName, db, "expr_cast", cst[0], cst[1]);
        }
    }

    private static void registerJsonPath(Runner r, Db db, String fw, String dbName) {
        String doc = "'{\"a\":1,\"b\":{\"c\":2},\"arr\":[10,20,30],\"s\":\"text\"}'";
        String[][] paths = {
                {"extract_a", "JSON_EXTRACT(" + doc + ", '$.a')"},
                {"extract_nested", "JSON_EXTRACT(" + doc + ", '$.b.c')"},
                {"extract_arr_idx", "JSON_EXTRACT(" + doc + ", '$.arr[1]')"},
                {"extract_arr_all", "JSON_EXTRACT(" + doc + ", '$.arr[*]')"},
                {"extract_string", "JSON_EXTRACT(" + doc + ", '$.s')"},
                {"arrow_a", doc + "->'$.a'"},
                {"arrow_unquote_s", doc + "->>'$.s'"},
                {"length_arr", "JSON_LENGTH(" + doc + ", '$.arr')"},
                {"keys", "JSON_KEYS(" + doc + ")"},
                {"type_root", "JSON_TYPE(" + doc + ")"},
                {"valid", "JSON_VALID(" + doc + ")"},
                {"unquote_extract", "JSON_UNQUOTE(JSON_EXTRACT(" + doc + ", '$.s'))"},
        };
        for (String[] p : paths) {
            select(r, fw, dbName, db, "expr_json", p[0], p[1]);
        }
    }

    private static void registerStringExpr(Runner r, Db db, String fw, String dbName) {
        String[][] exprs = {
                {"concat_chain", "CONCAT('a', CONCAT('b', 'c'))"},
                {"substring_neg", "SUBSTRING('abcdef', -2)"},
                {"substring_for", "SUBSTRING('abcdef' FROM 2 FOR 3)"},
                {"trim_leading", "TRIM(LEADING 'x' FROM 'xxabc')"},
                {"trim_trailing", "TRIM(TRAILING 'x' FROM 'abcxx')"},
                {"trim_both", "TRIM(BOTH 'x' FROM 'xxabcxx')"},
                {"replace_nested", "REPLACE(REPLACE('a-b-c', '-', '_'), '_', '.')"},
                {"lpad_zero", "LPAD('5', 4, '0')"},
                {"upper_concat", "UPPER(CONCAT('a','b'))"},
                {"like_escape", "'a%b' LIKE 'a\\%b'"},
                {"regexp_anchor", "'abc' REGEXP '^abc$'"},
                {"char_in_set", "FIND_IN_SET('b', 'a,b,c')"},
        };
        for (String[] e : exprs) {
            select(r, fw, dbName, db, "expr_string", e[0], e[1]);
        }
    }

    private static String sanitize(String op) {
        return op.replace("+", "plus").replace("-", "minus").replace("*", "mul")
                .replace("/", "div").replace("%", "mod")
                .replace("<=>", "nullsafe").replace("<=", "le").replace(">=", "ge")
                .replace("<>", "ne").replace("<", "lt").replace(">", "gt").replace("=", "eq")
                .replace(" ", "_");
    }
}
