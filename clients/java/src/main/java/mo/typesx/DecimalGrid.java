package mo.typesx;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Full DECIMAL / BigDecimal precision-scale grid: declare, insert via literal and
 * via PreparedStatement.setBigDecimal, round-trip getBigDecimal, arithmetic,
 * boundary, scale-truncation/rounding semantics.
 */
public final class DecimalGrid {

    // precision x scale combinations exercising small, mid, and 128-bit ranges.
    private static final int[][] PS = {
            {5, 0}, {5, 2}, {10, 2}, {10, 4}, {18, 0}, {18, 2}, {18, 6},
            {20, 2}, {28, 8}, {30, 10}, {38, 0}, {38, 2}, {38, 10}, {38, 18}, {38, 38},
    };

    public static void register(Runner r, Db db, String fw, String dbName) {
        for (int[] ps : PS) {
            final int p = ps[0];
            final int s = ps[1];
            final String key = "d" + p + "_" + s;
            final String ddl = "DECIMAL(" + p + "," + s + ")";
            final String cat = "decimal_grid";

            r.register(fw, cat, "declare/" + key, () -> {
                String t = Db.uniq("dg_" + key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "insert_literal/" + key, () -> {
                String t = Db.uniq("dg_" + key);
                BigDecimal v = sampleValue(p, s);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + v.toPlainString() + ")");
                        Assert.eq(1L, Db.scalarLong(c, "SELECT COUNT(*) FROM " + t), "inserted");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "prepared_roundtrip/" + key, () -> {
                String t = Db.uniq("dg_" + key);
                BigDecimal v = sampleValue(p, s);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                    }
                    try (var ins = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ins.setBigDecimal(1, v);
                        ins.executeUpdate();
                    }
                    try (var sel = c.prepareStatement("SELECT c FROM " + t + " WHERE id=1");
                         ResultSet rs = sel.executeQuery()) {
                        Assert.isTrue(rs.next(), "row present");
                        BigDecimal got = rs.getBigDecimal(1);
                        Assert.notNull(got, "getBigDecimal not null");
                        // scale should match column scale
                        Assert.eq(s, got.scale(), "scale of returned decimal for " + key);
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "arithmetic_sum/" + key, () -> {
                String t = Db.uniq("dg_" + key);
                BigDecimal v = sampleValue(p, s);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + v.toPlainString() + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + v.toPlainString() + ")");
                        try (ResultSet rs = st.executeQuery("SELECT SUM(c), AVG(c) FROM " + t)) {
                            Assert.isTrue(rs.next(), "agg row");
                            rs.getBigDecimal(1);
                            rs.getBigDecimal(2);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "scale_rounding/" + key, () -> {
                // Insert a value with more fractional digits than the scale; observe rounding/truncation.
                String t = Db.uniq("dg_" + key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        // value with scale+2 fractional digits; integer part 0 when no integral capacity.
                        String intPart = (p - s) >= 1 ? "1" : "0";
                        StringBuilder val = new StringBuilder(intPart + ".");
                        for (int i = 0; i < s + 2; i++) val.append((i % 9) + 1);
                        if (s == 0) val = new StringBuilder("1.55"); // force rounding into integer
                        st.execute("INSERT INTO " + t + " VALUES (1, " + val + ")");
                        try (ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                            rs.next();
                            BigDecimal got = rs.getBigDecimal(1);
                            Assert.eq(s, got.scale(), "stored scale matches column for " + key);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "overflow_boundary/" + key, () -> {
                // Insert a value that exceeds integral digit capacity (p - s). Expect rejection/overflow.
                String t = Db.uniq("dg_" + key);
                int intDigits = p - s;
                if (intDigits <= 0) throw new mo.harness.SkipException("no integral capacity for " + key);
                StringBuilder big = new StringBuilder();
                for (int i = 0; i < intDigits + 1; i++) big.append('9');
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        boolean rejected = false;
                        try { st.execute("INSERT INTO " + t + " VALUES (1, " + big + ")"); }
                        catch (Exception e) { rejected = true; }
                        // MySQL rejects out-of-range DECIMAL. If MatrixOne accepts it, that is a
                        // BEHAVIOR finding (silent acceptance/truncation), not a harness failure.
                        if (!rejected) {
                            throw new mo.harness.BehaviorMismatch(
                                    "out-of-range DECIMAL(" + p + "," + s + ")",
                                    "reject overflow (MySQL)", "accepted " + big + " (MatrixOne)");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    private static BigDecimal sampleValue(int p, int s) {
        int availInt = p - s;          // available integral digit positions
        StringBuilder sb = new StringBuilder();
        if (availInt >= 1) {
            for (int i = 0; i < Math.min(availInt, 3); i++) sb.append((i % 9) + 1);
        } else {
            sb.append('0');            // scale == precision: integer part must be 0
        }
        if (s > 0) {
            sb.append('.');
            for (int i = 0; i < Math.min(s, 4); i++) sb.append((i % 9) + 1);
        }
        return new BigDecimal(sb.toString());
    }

    private DecimalGrid() {}
}
