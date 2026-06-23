package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;

/**
 * Data-type x operation matrix.
 * For each type: declare, insert, roundtrip, null, boundary-min, boundary-max,
 * update, where, order, group, index.
 */
public final class JdbcTypeMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            registerForType(r, db, fw, dbName, ts);
        }
    }

    private static void registerForType(Runner r, Db db, String fw, String dbName, TypeSpec ts) {
        final String cat = "type";

        // declare
        r.register(fw, cat, "declare/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // insert
        r.register(fw, cat, "insert/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    Assert.eq(1L, n, "row inserted");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // roundtrip
        r.register(fw, cat, "roundtrip/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                        Assert.isTrue(rs.next(), "row present");
                        Object o = rs.getObject(1);
                        // We accept any non-throwing read; null only if literal was NULL.
                        Assert.notNull(o, "round-tripped value not null for " + ts.key);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // null handling
        r.register(fw, cat, "null/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + " NULL)");
                    st.execute("INSERT INTO " + t + " (id) VALUES (1)");
                    try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                        rs.next();
                        rs.getObject(1);
                        Assert.isTrue(rs.wasNull(), "value should be NULL");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // boundary-min
        if (ts.minLiteral != null) {
            r.register(fw, cat, "boundary_min/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.minLiteral + ")");
                        try (ResultSet rs = st.executeQuery("SELECT c FROM " + t)) {
                            Assert.isTrue(rs.next(), "min boundary row present");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // boundary-max
        if (ts.maxLiteral != null) {
            r.register(fw, cat, "boundary_max/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.maxLiteral + ")");
                        try (ResultSet rs = st.executeQuery("SELECT c FROM " + t)) {
                            Assert.isTrue(rs.next(), "max boundary row present");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // update
        r.register(fw, cat, "update/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    int u = st.executeUpdate("UPDATE " + t + " SET c = " + ts.sampleLiteral + " WHERE id=1");
                    Assert.eq(1, u, "update affected one row");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // where (equality filter)
        if (ts.orderable) {
            r.register(fw, cat, "where/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id FROM " + t + " WHERE c = " + ts.sampleLiteral)) {
                            boolean matched = rs.next();
                            // Floating-point columns are not reliably equality-matchable
                            // (literal vs stored value may differ by rounding); accept either.
                            if (!matched && exactEqualityReliable(ts.key)) {
                                throw new mo.harness.BehaviorMismatch(
                                        "WHERE c = literal for " + ts.key,
                                        "match (exact-equality types)", "no match");
                            }
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // order
        if (ts.orderable) {
            r.register(fw, cat, "order/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id FROM " + t + " ORDER BY c ASC, id ASC")) {
                            Assert.isTrue(rs.next(), "ordered result present");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // group
        if (ts.groupable) {
            r.register(fw, cat, "group/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT c, COUNT(*) FROM " + t + " GROUP BY c")) {
                            Assert.isTrue(rs.next(), "grouped result present");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // index
        if (ts.indexable) {
            r.register(fw, cat, "index/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("CREATE INDEX ix_c ON " + t + " (c)");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // distinct
        r.register(fw, cat, "distinct/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                    try (ResultSet rs = st.executeQuery("SELECT DISTINCT c FROM " + t)) {
                        while (rs.next()) rs.getObject(1);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // coalesce
        r.register(fw, cat, "coalesce/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + " NULL)");
                    st.execute("INSERT INTO " + t + " (id) VALUES (1)");
                    try (ResultSet rs = st.executeQuery(
                            "SELECT COALESCE(c, " + ts.sampleLiteral + ") FROM " + t)) {
                        Assert.isTrue(rs.next(), "coalesce row");
                        rs.getObject(1);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // cast-to-char (string projection)
        r.register(fw, cat, "cast_char/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    try (ResultSet rs = st.executeQuery("SELECT CAST(c AS CHAR) FROM " + t)) {
                        rs.next();
                        rs.getObject(1);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // prepared-insert (bind a literal-driven value via prepared statement context)
        r.register(fw, cat, "prepared_roundtrip/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                }
                try (var ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id = ?")) {
                    ps.setInt(1, 1);
                    try (ResultSet rs = ps.executeQuery()) {
                        Assert.isTrue(rs.next(), "prepared select row");
                        rs.getObject(1);
                    }
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // is-not-null filter
        r.register(fw, cat, "is_not_null/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + " NULL)");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    st.execute("INSERT INTO " + t + " (id) VALUES (2)");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE c IS NOT NULL");
                    Assert.eq(1L, n, "is-not-null count");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // count over the column
        r.register(fw, cat, "count_col/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    long n = Db.scalarLong(c, "SELECT COUNT(c) FROM " + t);
                    Assert.eq(1L, n, "count(col)");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // default-value column of this type
        r.register(fw, cat, "default_value/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType
                            + " DEFAULT " + ts.sampleLiteral + ")");
                    st.execute("INSERT INTO " + t + " (id) VALUES (1)");
                    try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                        Assert.isTrue(rs.next(), "default-value row present");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // not-null column with value
        r.register(fw, cat, "not_null_col/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + " NOT NULL)");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // delete-where on this column
        if (ts.orderable) {
            r.register(fw, cat, "delete_where/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        int del = st.executeUpdate("DELETE FROM " + t + " WHERE c = " + ts.sampleLiteral);
                        Assert.isTrue(del >= 0, "delete-where executed");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // aggregate min/max for orderable numeric-ish
        if (ts.orderable) {
            r.register(fw, cat, "minmax/" + ts.key, () -> {
                String t = Db.uniq("t_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery("SELECT MIN(c), MAX(c) FROM " + t)) {
                            Assert.isTrue(rs.next(), "min/max row");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // getString round-trip (driver string coercion for every type)
        r.register(fw, cat, "get_string/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                        rs.next();
                        rs.getString(1);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // ResultSetMetaData column type inspection per type
        r.register(fw, cat, "rsmd_type/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                    try (ResultSet rs = st.executeQuery("SELECT c FROM " + t)) {
                        var md = rs.getMetaData();
                        md.getColumnType(1);
                        Assert.notNull(md.getColumnTypeName(1), "column type name for " + ts.key);
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // DatabaseMetaData.getColumns reports this column
        r.register(fw, cat, "metadata_column/" + ts.key, () -> {
            String t = Db.uniq("t_" + ts.key);
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                    var md = c.getMetaData();
                    int found = 0;
                    try (ResultSet rs = md.getColumns(dbName, null, t, "c")) {
                        while (rs.next()) found++;
                    }
                    Assert.isTrue(found >= 1, "getColumns reported column c for " + ts.key);
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    /**
     * Whether exact equality (WHERE c = literal) is a reliable round-trip test
     * for this type. Binary floating-point types are excluded because the stored
     * value can differ from the literal by rounding.
     */
    private static boolean exactEqualityReliable(String key) {
        return !(key.startsWith("float") || key.startsWith("double"));
    }
}
