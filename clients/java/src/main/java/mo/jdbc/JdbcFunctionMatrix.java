package mo.jdbc;

import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

/**
 * Function matrix: SELECT expr for a large catalog of MySQL/MatrixOne
 * built-in functions, plus operator and expression variations.
 * Failures (20105 unimplemented, 20203 strict args) are EXPECTED findings.
 */
public final class JdbcFunctionMatrix {

    private record Fn(String name, String expr) {}

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<Fn> fns = new ArrayList<>();

        // String functions
        add(fns, "CONCAT", "CONCAT('a','b','c')");
        add(fns, "CONCAT_WS", "CONCAT_WS('-','a','b')");
        add(fns, "LENGTH", "LENGTH('abc')");
        add(fns, "CHAR_LENGTH", "CHAR_LENGTH('abc')");
        add(fns, "CHARACTER_LENGTH", "CHARACTER_LENGTH('abc')");
        add(fns, "OCTET_LENGTH", "OCTET_LENGTH('abc')");
        add(fns, "BIT_LENGTH", "BIT_LENGTH('abc')");
        add(fns, "UPPER", "UPPER('abc')");
        add(fns, "LOWER", "LOWER('ABC')");
        add(fns, "UCASE", "UCASE('abc')");
        add(fns, "LCASE", "LCASE('ABC')");
        add(fns, "SUBSTRING", "SUBSTRING('abcdef',2,3)");
        add(fns, "SUBSTR", "SUBSTR('abcdef',2,3)");
        add(fns, "MID", "MID('abcdef',2,3)");
        add(fns, "LEFT", "LEFT('abcdef',3)");
        add(fns, "RIGHT", "RIGHT('abcdef',3)");
        add(fns, "TRIM", "TRIM('  abc  ')");
        add(fns, "LTRIM", "LTRIM('  abc')");
        add(fns, "RTRIM", "RTRIM('abc  ')");
        add(fns, "REPLACE", "REPLACE('abcabc','a','X')");
        add(fns, "REVERSE", "REVERSE('abc')");
        add(fns, "REPEAT", "REPEAT('ab',3)");
        add(fns, "LPAD", "LPAD('5',3,'0')");
        add(fns, "RPAD", "RPAD('5',3,'0')");
        add(fns, "LOCATE", "LOCATE('c','abcdef')");
        add(fns, "POSITION", "POSITION('c' IN 'abcdef')");
        add(fns, "INSTR", "INSTR('abcdef','c')");
        add(fns, "INSERT", "INSERT('abcdef',2,3,'XY')");
        add(fns, "ELT", "ELT(2,'a','b','c')");
        add(fns, "FIELD", "FIELD('b','a','b','c')");
        add(fns, "FIND_IN_SET", "FIND_IN_SET('b','a,b,c')");
        add(fns, "ASCII", "ASCII('A')");
        add(fns, "ORD", "ORD('A')");
        add(fns, "CHAR", "CHAR(65)");
        add(fns, "HEX", "HEX('abc')");
        add(fns, "UNHEX", "UNHEX('616263')");
        add(fns, "SOUNDEX", "SOUNDEX('Robert')");
        add(fns, "SPACE", "SPACE(3)");
        add(fns, "FORMAT", "FORMAT(1234.567,2)");
        add(fns, "QUOTE", "QUOTE('a''b')");
        add(fns, "TO_BASE64", "TO_BASE64('abc')");
        add(fns, "FROM_BASE64", "FROM_BASE64('YWJj')");
        add(fns, "WEIGHT_STRING", "WEIGHT_STRING('abc')");
        add(fns, "CONV", "CONV('FF',16,10)");
        add(fns, "EXPORT_SET", "EXPORT_SET(5,'Y','N',',',4)");
        add(fns, "MAKE_SET", "MAKE_SET(3,'a','b','c')");
        add(fns, "REGEXP_LIKE", "REGEXP_LIKE('abc','^a')");
        add(fns, "REGEXP_REPLACE", "REGEXP_REPLACE('abc','b','X')");
        add(fns, "REGEXP_SUBSTR", "REGEXP_SUBSTR('abc','b')");
        add(fns, "REGEXP_INSTR", "REGEXP_INSTR('abc','b')");

        // Numeric functions
        add(fns, "ABS", "ABS(-5)");
        add(fns, "CEIL", "CEIL(4.1)");
        add(fns, "CEILING", "CEILING(4.1)");
        add(fns, "FLOOR", "FLOOR(4.9)");
        add(fns, "ROUND", "ROUND(4.567,2)");
        add(fns, "TRUNCATE", "TRUNCATE(4.567,2)");
        add(fns, "MOD", "MOD(10,3)");
        add(fns, "POW", "POW(2,10)");
        add(fns, "POWER", "POWER(2,10)");
        add(fns, "SQRT", "SQRT(16)");
        add(fns, "EXP", "EXP(1)");
        add(fns, "LN", "LN(2.718281828)");
        add(fns, "LOG", "LOG(2.718281828)");
        add(fns, "LOG2", "LOG2(8)");
        add(fns, "LOG10", "LOG10(1000)");
        add(fns, "SIGN", "SIGN(-3)");
        add(fns, "PI", "PI()");
        add(fns, "RAND", "RAND() >= 0");
        add(fns, "RAND_SEED", "RAND(42) >= 0");
        add(fns, "SIN", "SIN(0)");
        add(fns, "COS", "COS(0)");
        add(fns, "TAN", "TAN(0)");
        add(fns, "ASIN", "ASIN(0)");
        add(fns, "ACOS", "ACOS(1)");
        add(fns, "ATAN", "ATAN(1)");
        add(fns, "ATAN2", "ATAN2(1,1)");
        add(fns, "COT", "COT(1)");
        add(fns, "DEGREES", "DEGREES(PI())");
        add(fns, "RADIANS", "RADIANS(180)");
        add(fns, "CRC32", "CRC32('abc')");
        add(fns, "GREATEST", "GREATEST(1,5,3)");
        add(fns, "LEAST", "LEAST(1,5,3)");
        add(fns, "BIN", "BIN(10)");
        add(fns, "OCT", "OCT(64)");
        add(fns, "BIT_COUNT", "BIT_COUNT(255)");

        // Date/time functions
        add(fns, "NOW", "NOW()");
        add(fns, "CURDATE", "CURDATE()");
        add(fns, "CURRENT_DATE", "CURRENT_DATE()");
        add(fns, "CURTIME", "CURTIME()");
        add(fns, "CURRENT_TIME", "CURRENT_TIME()");
        add(fns, "CURRENT_TIMESTAMP", "CURRENT_TIMESTAMP()");
        add(fns, "SYSDATE", "SYSDATE()");
        add(fns, "UTC_DATE", "UTC_DATE()");
        add(fns, "UTC_TIME", "UTC_TIME()");
        add(fns, "UTC_TIMESTAMP", "UTC_TIMESTAMP()");
        add(fns, "UNIX_TIMESTAMP", "UNIX_TIMESTAMP('2026-06-23 00:00:00')");
        add(fns, "FROM_UNIXTIME", "FROM_UNIXTIME(1700000000)");
        add(fns, "DATE", "DATE('2026-06-23 11:22:33')");
        add(fns, "TIME", "TIME('2026-06-23 11:22:33')");
        add(fns, "YEAR", "YEAR('2026-06-23')");
        add(fns, "MONTH", "MONTH('2026-06-23')");
        add(fns, "DAY", "DAY('2026-06-23')");
        add(fns, "DAYOFMONTH", "DAYOFMONTH('2026-06-23')");
        add(fns, "DAYOFWEEK", "DAYOFWEEK('2026-06-23')");
        add(fns, "DAYOFYEAR", "DAYOFYEAR('2026-06-23')");
        add(fns, "WEEKDAY", "WEEKDAY('2026-06-23')");
        add(fns, "WEEK", "WEEK('2026-06-23')");
        add(fns, "WEEKOFYEAR", "WEEKOFYEAR('2026-06-23')");
        add(fns, "QUARTER", "QUARTER('2026-06-23')");
        add(fns, "HOUR", "HOUR('11:22:33')");
        add(fns, "MINUTE", "MINUTE('11:22:33')");
        add(fns, "SECOND", "SECOND('11:22:33')");
        add(fns, "MICROSECOND", "MICROSECOND('11:22:33.123456')");
        add(fns, "DAYNAME", "DAYNAME('2026-06-23')");
        add(fns, "MONTHNAME", "MONTHNAME('2026-06-23')");
        add(fns, "LAST_DAY", "LAST_DAY('2026-06-23')");
        add(fns, "DATE_ADD", "DATE_ADD('2026-06-23', INTERVAL 1 DAY)");
        add(fns, "DATE_SUB", "DATE_SUB('2026-06-23', INTERVAL 1 DAY)");
        add(fns, "ADDDATE", "ADDDATE('2026-06-23', 7)");
        add(fns, "SUBDATE", "SUBDATE('2026-06-23', 7)");
        add(fns, "ADDTIME", "ADDTIME('11:22:33','01:00:00')");
        add(fns, "SUBTIME", "SUBTIME('11:22:33','01:00:00')");
        add(fns, "DATEDIFF", "DATEDIFF('2026-06-23','2026-06-01')");
        add(fns, "TIMEDIFF", "TIMEDIFF('11:00:00','10:00:00')");
        add(fns, "TIMESTAMPDIFF", "TIMESTAMPDIFF(DAY,'2026-06-01','2026-06-23')");
        add(fns, "TIMESTAMPADD", "TIMESTAMPADD(DAY,7,'2026-06-23')");
        add(fns, "DATE_FORMAT", "DATE_FORMAT('2026-06-23','%Y/%m/%d')");
        add(fns, "TIME_FORMAT", "TIME_FORMAT('11:22:33','%H:%i')");
        add(fns, "STR_TO_DATE", "STR_TO_DATE('2026-06-23','%Y-%m-%d')");
        add(fns, "EXTRACT", "EXTRACT(YEAR FROM '2026-06-23')");
        add(fns, "MAKEDATE", "MAKEDATE(2026,100)");
        add(fns, "MAKETIME", "MAKETIME(11,22,33)");
        add(fns, "SEC_TO_TIME", "SEC_TO_TIME(3661)");
        add(fns, "TIME_TO_SEC", "TIME_TO_SEC('01:01:01')");
        add(fns, "TO_DAYS", "TO_DAYS('2026-06-23')");
        add(fns, "FROM_DAYS", "FROM_DAYS(739000)");
        add(fns, "PERIOD_ADD", "PERIOD_ADD(202606,2)");
        add(fns, "PERIOD_DIFF", "PERIOD_DIFF(202606,202601)");
        add(fns, "CONVERT_TZ", "CONVERT_TZ('2026-06-23 12:00:00','+00:00','+08:00')");

        // Control flow / conditional
        add(fns, "IF", "IF(1>0,'y','n')");
        add(fns, "IFNULL", "IFNULL(NULL,'x')");
        add(fns, "NULLIF", "NULLIF(1,1)");
        add(fns, "COALESCE", "COALESCE(NULL,NULL,'z')");
        add(fns, "CASE_simple", "CASE 1 WHEN 1 THEN 'a' ELSE 'b' END");
        add(fns, "CASE_searched", "CASE WHEN 1>0 THEN 'a' ELSE 'b' END");
        add(fns, "ISNULL", "ISNULL(NULL)");

        // Cast / convert
        add(fns, "CAST_signed", "CAST('123' AS SIGNED)");
        add(fns, "CAST_unsigned", "CAST('123' AS UNSIGNED)");
        add(fns, "CAST_decimal", "CAST('1.5' AS DECIMAL(10,2))");
        add(fns, "CAST_char", "CAST(123 AS CHAR)");
        add(fns, "CAST_date", "CAST('2026-06-23' AS DATE)");
        add(fns, "CAST_datetime", "CAST('2026-06-23 11:22:33' AS DATETIME)");
        add(fns, "CAST_double", "CAST('1.5' AS DOUBLE)");
        add(fns, "CONVERT_signed", "CONVERT('42', SIGNED)");

        // JSON functions
        add(fns, "JSON_OBJECT", "JSON_OBJECT('k',1)");
        add(fns, "JSON_ARRAY", "JSON_ARRAY(1,2,3)");
        add(fns, "JSON_EXTRACT", "JSON_EXTRACT('{\"a\":1}','$.a')");
        add(fns, "JSON_UNQUOTE", "JSON_UNQUOTE('\"abc\"')");
        add(fns, "JSON_VALID", "JSON_VALID('{\"a\":1}')");
        add(fns, "JSON_TYPE", "JSON_TYPE('{\"a\":1}')");
        add(fns, "JSON_LENGTH", "JSON_LENGTH('[1,2,3]')");
        add(fns, "JSON_KEYS", "JSON_KEYS('{\"a\":1,\"b\":2}')");
        add(fns, "JSON_DEPTH", "JSON_DEPTH('{\"a\":{\"b\":1}}')");
        add(fns, "JSON_CONTAINS", "JSON_CONTAINS('[1,2,3]','2')");
        add(fns, "JSON_CONTAINS_PATH", "JSON_CONTAINS_PATH('{\"a\":1}','one','$.a')");
        add(fns, "JSON_SET", "JSON_SET('{\"a\":1}','$.b',2)");
        add(fns, "JSON_INSERT", "JSON_INSERT('{\"a\":1}','$.b',2)");
        add(fns, "JSON_REPLACE", "JSON_REPLACE('{\"a\":1}','$.a',9)");
        add(fns, "JSON_REMOVE", "JSON_REMOVE('{\"a\":1,\"b\":2}','$.b')");
        add(fns, "JSON_MERGE_PATCH", "JSON_MERGE_PATCH('{\"a\":1}','{\"b\":2}')");
        add(fns, "JSON_MERGE_PRESERVE", "JSON_MERGE_PRESERVE('{\"a\":1}','{\"b\":2}')");
        add(fns, "JSON_ARRAY_APPEND", "JSON_ARRAY_APPEND('[1]','$',2)");
        add(fns, "JSON_ARRAY_INSERT", "JSON_ARRAY_INSERT('[1,2]','$[0]',0)");
        add(fns, "JSON_SEARCH", "JSON_SEARCH('[\"a\",\"b\"]','one','b')");
        add(fns, "JSON_OVERLAPS", "JSON_OVERLAPS('[1,2]','[2,3]')");
        add(fns, "JSON_PRETTY", "JSON_PRETTY('{\"a\":1}')");
        add(fns, "JSON_STORAGE_SIZE", "JSON_STORAGE_SIZE('{\"a\":1}')");
        add(fns, "JSON_QUOTE", "JSON_QUOTE('abc')");

        // Encoding / hashing / network
        add(fns, "MD5", "MD5('abc')");
        add(fns, "SHA1", "SHA1('abc')");
        add(fns, "SHA2", "SHA2('abc',256)");
        add(fns, "UUID", "UUID()");
        add(fns, "UUID_SHORT", "UUID_SHORT()");
        add(fns, "INET_ATON", "INET_ATON('127.0.0.1')");
        add(fns, "INET_NTOA", "INET_NTOA(2130706433)");
        add(fns, "INET6_ATON", "INET6_ATON('::1')");
        add(fns, "COMPRESS", "LENGTH(COMPRESS('abc'))");
        add(fns, "RANDOM_BYTES", "LENGTH(RANDOM_BYTES(4))");

        // Misc / info
        add(fns, "DATABASE", "DATABASE()");
        add(fns, "VERSION", "VERSION()");
        add(fns, "CONNECTION_ID", "CONNECTION_ID()");
        add(fns, "USER", "USER()");
        add(fns, "CURRENT_USER", "CURRENT_USER()");
        add(fns, "BENCHMARK", "BENCHMARK(2,1+1)");

        // Operators / expressions
        add(fns, "op_plus", "1 + 2");
        add(fns, "op_minus", "5 - 3");
        add(fns, "op_mult", "4 * 3");
        add(fns, "op_div", "10 / 4");
        add(fns, "op_intdiv", "10 DIV 3");
        add(fns, "op_mod", "10 % 3");
        add(fns, "op_and", "1 AND 1");
        add(fns, "op_or", "1 OR 0");
        add(fns, "op_xor", "1 XOR 0");
        add(fns, "op_not", "NOT 0");
        add(fns, "op_bit_and", "6 & 3");
        add(fns, "op_bit_or", "6 | 1");
        add(fns, "op_bit_xor", "6 ^ 3");
        add(fns, "op_bit_shl", "1 << 4");
        add(fns, "op_bit_shr", "16 >> 2");
        add(fns, "op_eq", "1 = 1");
        add(fns, "op_ne", "1 <> 2");
        add(fns, "op_lt", "1 < 2");
        add(fns, "op_nullsafe_eq", "1 <=> 1");
        add(fns, "op_between", "5 BETWEEN 1 AND 10");
        add(fns, "op_in", "3 IN (1,2,3)");
        add(fns, "op_like", "'abc' LIKE 'a%'");
        add(fns, "op_regexp", "'abc' REGEXP '^a'");
        add(fns, "op_is_null", "NULL IS NULL");
        add(fns, "op_concat_pipe", "'a' || 'b'");
        add(fns, "op_interval_fn", "INTERVAL(5,1,3,7)");

        for (Fn fn : fns) {
            // Variation 1: SELECT expr (scalar).
            r.register(fw, "function", fn.name(), () -> {
                try (Connection c = db.connect(dbName);
                     Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery("SELECT " + fn.expr())) {
                    if (rs.next()) rs.getObject(1);
                }
            });
            // Variation 2: SELECT expr AS alias FROM a one-row table.
            r.register(fw, "function_aliased", fn.name(), () -> {
                String t = Db.uniq("fn");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (x INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1)");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT (" + fn.expr() + ") AS r FROM " + t)) {
                            if (rs.next()) rs.getObject("r");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // Variation 3: expr used in a WHERE predicate.
            r.register(fw, "function_where", fn.name(), () -> {
                String t = Db.uniq("fn");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (x INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1)");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT x FROM " + t + " WHERE (" + fn.expr() + ") IS NOT NULL")) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // Variation 4: expr inside a CTE projection.
            r.register(fw, "function_cte", fn.name(), () -> {
                try (Connection c = db.connect(dbName);
                     Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery(
                             "WITH w AS (SELECT (" + fn.expr() + ") AS r) SELECT r FROM w")) {
                    if (rs.next()) rs.getObject("r");
                }
            });
            // Variation 5: expr inside a derived table / subquery.
            r.register(fw, "function_subquery", fn.name(), () -> {
                try (Connection c = db.connect(dbName);
                     Statement st = c.createStatement();
                     ResultSet rs = st.executeQuery(
                             "SELECT d.r FROM (SELECT (" + fn.expr() + ") AS r) d")) {
                    if (rs.next()) rs.getObject("r");
                }
            });
        }
    }

    private static void add(List<Fn> list, String name, String expr) {
        list.add(new Fn(name, expr));
    }
}
