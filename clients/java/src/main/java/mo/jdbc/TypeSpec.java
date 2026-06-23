package mo.jdbc;

import java.util.ArrayList;
import java.util.List;

/**
 * A catalog of MySQL/MatrixOne column types with sample values used to build
 * the data-type x operation test matrices.
 */
public final class TypeSpec {
    public final String key;        // short identifier used in table/column names
    public final String ddlType;    // the column type as written in CREATE TABLE
    public final String sampleLiteral;   // a typical literal (SQL form)
    public final String minLiteral;      // boundary minimum (SQL form) or null
    public final String maxLiteral;      // boundary maximum (SQL form) or null
    public final boolean orderable;      // sensible to ORDER BY / range-compare
    public final boolean indexable;      // can be part of a normal index
    public final boolean groupable;      // sensible to GROUP BY

    public TypeSpec(String key, String ddlType, String sampleLiteral,
                    String minLiteral, String maxLiteral,
                    boolean orderable, boolean indexable, boolean groupable) {
        this.key = key;
        this.ddlType = ddlType;
        this.sampleLiteral = sampleLiteral;
        this.minLiteral = minLiteral;
        this.maxLiteral = maxLiteral;
        this.orderable = orderable;
        this.indexable = indexable;
        this.groupable = groupable;
    }

    public static List<TypeSpec> all() {
        List<TypeSpec> t = new ArrayList<>();
        // Integer family
        t.add(new TypeSpec("tinyint", "TINYINT", "1", "-128", "127", true, true, true));
        t.add(new TypeSpec("tinyint_u", "TINYINT UNSIGNED", "1", "0", "255", true, true, true));
        t.add(new TypeSpec("smallint", "SMALLINT", "100", "-32768", "32767", true, true, true));
        t.add(new TypeSpec("smallint_u", "SMALLINT UNSIGNED", "100", "0", "65535", true, true, true));
        t.add(new TypeSpec("mediumint", "MEDIUMINT", "1000", "-8388608", "8388607", true, true, true));
        t.add(new TypeSpec("mediumint_u", "MEDIUMINT UNSIGNED", "1000", "0", "16777215", true, true, true));
        t.add(new TypeSpec("int", "INT", "12345", "-2147483648", "2147483647", true, true, true));
        t.add(new TypeSpec("int_u", "INT UNSIGNED", "12345", "0", "4294967295", true, true, true));
        t.add(new TypeSpec("bigint", "BIGINT", "9223372036854775807", "-9223372036854775808", "9223372036854775807", true, true, true));
        t.add(new TypeSpec("bigint_u", "BIGINT UNSIGNED", "100", "0", "18446744073709551615", true, true, true));
        t.add(new TypeSpec("bool", "BOOLEAN", "1", "0", "1", true, true, true));
        t.add(new TypeSpec("bit", "BIT(8)", "b'10101010'", "b'0'", "b'11111111'", true, true, true));
        // Decimal / floating
        t.add(new TypeSpec("decimal", "DECIMAL(18,4)", "12345.6789", "-99999999999999.9999", "99999999999999.9999", true, true, true));
        t.add(new TypeSpec("decimal_small", "DECIMAL(5,2)", "123.45", "-999.99", "999.99", true, true, true));
        t.add(new TypeSpec("numeric", "NUMERIC(10,2)", "98765.43", "-99999999.99", "99999999.99", true, true, true));
        t.add(new TypeSpec("float_p", "FLOAT(10,2)", "3.50", "-99999.99", "99999.99", true, true, true));
        t.add(new TypeSpec("float_bare", "FLOAT", "3.5", null, null, true, true, true));
        t.add(new TypeSpec("double", "DOUBLE", "3.14159265358979", "-1.7e100", "1.7e100", true, true, true));
        t.add(new TypeSpec("double_p", "DOUBLE(20,8)", "3.14159265", null, null, true, true, true));
        t.add(new TypeSpec("double_precision", "DOUBLE PRECISION", "2.718281828", null, null, true, true, true));
        // String family
        t.add(new TypeSpec("char", "CHAR(10)", "'abcdefghij'", "''", "'zzzzzzzzzz'", true, true, true));
        t.add(new TypeSpec("varchar", "VARCHAR(255)", "'hello world'", "''", null, true, true, true));
        t.add(new TypeSpec("varchar_long", "VARCHAR(4000)", "'lorem ipsum dolor sit amet'", null, null, true, true, true));
        t.add(new TypeSpec("tinytext", "TINYTEXT", "'a tiny text'", null, null, false, false, false));
        t.add(new TypeSpec("text", "TEXT", "'some longer text body'", null, null, false, false, false));
        t.add(new TypeSpec("mediumtext", "MEDIUMTEXT", "'medium text'", null, null, false, false, false));
        t.add(new TypeSpec("longtext", "LONGTEXT", "'long text'", null, null, false, false, false));
        // Binary family
        t.add(new TypeSpec("binary", "BINARY(8)", "0x0102030405060708", null, null, true, true, true));
        t.add(new TypeSpec("varbinary", "VARBINARY(255)", "0xDEADBEEF", null, null, true, true, true));
        t.add(new TypeSpec("tinyblob", "TINYBLOB", "0xAB", null, null, false, false, false));
        t.add(new TypeSpec("blob", "BLOB", "0xCAFEBABE", null, null, false, false, false));
        t.add(new TypeSpec("mediumblob", "MEDIUMBLOB", "0x00FF", null, null, false, false, false));
        t.add(new TypeSpec("longblob", "LONGBLOB", "0x01", null, null, false, false, false));
        // Date/time
        t.add(new TypeSpec("date", "DATE", "'2026-06-23'", "'1000-01-01'", "'9999-12-31'", true, true, true));
        t.add(new TypeSpec("time", "TIME", "'11:22:33'", "'-838:59:59'", "'838:59:59'", true, true, true));
        t.add(new TypeSpec("datetime", "DATETIME", "'2026-06-23 11:22:33'", "'1000-01-01 00:00:00'", "'9999-12-31 23:59:59'", true, true, true));
        t.add(new TypeSpec("datetime6", "DATETIME(6)", "'2026-06-23 11:22:33.123456'", null, null, true, true, true));
        t.add(new TypeSpec("timestamp", "TIMESTAMP", "'2026-06-23 11:22:33'", "'1970-01-01 00:00:01'", "'2038-01-19 03:14:07'", true, true, true));
        t.add(new TypeSpec("timestamp6", "TIMESTAMP(6)", "'2026-06-23 11:22:33.654321'", null, null, true, true, true));
        t.add(new TypeSpec("year", "YEAR", "2026", "1901", "2155", true, true, true));
        // Enum/set
        t.add(new TypeSpec("enum", "ENUM('a','b','c')", "'b'", "'a'", "'c'", true, true, true));
        t.add(new TypeSpec("set", "SET('x','y','z')", "'x,y'", "'x'", "'x,y,z'", true, true, true));
        // JSON
        t.add(new TypeSpec("json", "JSON", "'{\"k\":1,\"arr\":[1,2,3]}'", null, null, false, false, false));
        // UUID (MatrixOne native)
        t.add(new TypeSpec("uuid", "UUID", "'6d1b1f48-0b71-4f9a-9e0e-2d5a0d6c1234'", null, null, true, false, true));
        // MatrixOne vector types
        t.add(new TypeSpec("vecf32", "VECF32(3)", "'[1,2,3]'", null, null, false, false, false));
        t.add(new TypeSpec("vecf64", "VECF64(3)", "'[1.5,2.5,3.5]'", null, null, false, false, false));
        return t;
    }
}
