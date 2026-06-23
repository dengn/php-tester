package mo.bulk;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Bulk / streaming / load at scale: JDBC batch up to 10k, rewriteBatchedStatements,
 * streaming ResultSet (fetchSize Integer.MIN_VALUE / useCursorFetch) over 100k rows,
 * wide tables (200+ cols), large LOBs, multi-statement.
 */
public final class BulkModule {

    private final String fw;
    private final String dbName;

    public BulkModule(String fw, String dbName) {
        this.fw = fw;
        this.dbName = dbName;
    }

    public void register(Runner r, Db db) {
        // Batch sizes up to 10k with and without rewriteBatchedStatements.
        int[] batchSizes = {500, 2000, 5000, 10000};
        for (int bs : batchSizes) {
            for (boolean rewrite : new boolean[]{false, true}) {
                final String key = "batch_" + bs + (rewrite ? "_rewrite" : "_plain");
                r.register(fw, "bulk_batch", key, () -> {
                    String t = Db.uniq("bb");
                    String url = db.config().dbUrl(dbName)
                            + (rewrite ? "&rewriteBatchedStatements=true" : "");
                    try (Connection c = DriverManager.getConnection(url, db.config().user, db.config().pass)) {
                        try (Statement st = c.createStatement()) {
                            st.execute("CREATE TABLE " + t + " (id INT, v VARCHAR(20))");
                        }
                        c.setAutoCommit(false);
                        try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?,?)")) {
                            for (int i = 0; i < bs; i++) {
                                ps.setInt(1, i);
                                ps.setString(2, "v" + i);
                                ps.addBatch();
                            }
                            int[] res = ps.executeBatch();
                            Assert.isTrue(res.length == bs, "batch result length " + res.length);
                        }
                        c.commit();
                        c.setAutoCommit(true);
                        long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                        Assert.eq((long) bs, n, "rows inserted via " + key);
                        try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
        }

        // executeLargeBatch
        r.register(fw, "bulk_batch", "execute_large_batch_3k", () -> {
            String t = Db.uniq("lb");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                }
                c.setAutoCommit(false);
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?)")) {
                    for (int i = 0; i < 3000; i++) { ps.setInt(1, i); ps.addBatch(); }
                    long[] res = ps.executeLargeBatch();
                    Assert.eq(3000, res.length, "large batch length");
                }
                c.commit();
                c.setAutoCommit(true);
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Streaming with Integer.MIN_VALUE fetch size (row-by-row streaming) over 100k.
        r.register(fw, "bulk_stream", "stream_minvalue_100k", () -> {
            String t = Db.uniq("sm");
            try (Connection c = db.connect(dbName)) {
                seedRows(c, t, 100000);
                try (Statement st = c.createStatement(ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
                    st.setFetchSize(Integer.MIN_VALUE);
                    int n = 0;
                    try (ResultSet rs = st.executeQuery("SELECT id FROM " + t)) {
                        while (rs.next()) n++;
                    }
                    Assert.eq(100000, n, "streamed 100k rows via fetchSize=MIN_VALUE");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });

        // useCursorFetch over 100k.
        r.register(fw, "bulk_stream", "cursor_fetch_100k", () -> {
            String t = Db.uniq("cf");
            String url = db.config().dbUrl(dbName) + "&useCursorFetch=true";
            try (Connection c = DriverManager.getConnection(url, db.config().user, db.config().pass)) {
                seedRows(c, t, 100000);
                try (Statement st = c.createStatement(ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
                    st.setFetchSize(2000);
                    int n = 0;
                    try (ResultSet rs = st.executeQuery("SELECT id FROM " + t)) {
                        while (rs.next()) n++;
                    }
                    Assert.eq(100000, n, "cursor-fetched 100k rows");
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });

        // Wide tables: 200 / 300 columns.
        for (int cols : new int[]{200, 300}) {
            r.register(fw, "bulk_wide", "wide_table_" + cols + "_cols", () -> {
                String t = Db.uniq("wide");
                StringBuilder ddl = new StringBuilder("id INT PRIMARY KEY");
                StringBuilder names = new StringBuilder();
                StringBuilder vals = new StringBuilder("1");
                for (int i = 0; i < cols; i++) {
                    ddl.append(", c").append(i).append(" INT");
                    names.append(", c").append(i);
                    vals.append(", ").append(i);
                }
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (" + ddl + ")");
                        st.execute("INSERT INTO " + t + " (id" + names + ") VALUES (" + vals + ")");
                        long got = Db.scalarLong(c, "SELECT c" + (cols - 1) + " FROM " + t + " WHERE id=1");
                        Assert.eq((long) (cols - 1), got, "wide table last column value");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // Very wide projection (many expressions in SELECT).
        r.register(fw, "bulk_wide", "wide_projection_256_exprs", () -> {
            StringBuilder sel = new StringBuilder("SELECT ");
            for (int i = 0; i < 256; i++) {
                if (i > 0) sel.append(", ");
                sel.append(i).append(" AS c").append(i);
            }
            try (Connection c = db.connect(dbName); Statement st = c.createStatement();
                 ResultSet rs = st.executeQuery(sel.toString())) {
                Assert.isTrue(rs.next(), "wide projection row");
                Assert.eq(256, rs.getMetaData().getColumnCount(), "256 projected columns");
            }
        });

        // Large LOBs.
        int[] lobKb = {256, 1024, 4096};
        for (int kb : lobKb) {
            r.register(fw, "bulk_lob", "longtext_" + kb + "kb", () -> {
                String t = Db.uniq("lt");
                String big = "x".repeat(kb * 1024);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v LONGTEXT)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setString(1, big);
                        ps.executeUpdate();
                    }
                    long len = Db.scalarLong(c, "SELECT CHAR_LENGTH(v) FROM " + t + " WHERE id=1");
                    Assert.eq((long) kb * 1024, len, "longtext length round-trip " + kb + "kb");
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            r.register(fw, "bulk_lob", "longblob_" + kb + "kb", () -> {
                String t = Db.uniq("lbl");
                byte[] data = new byte[kb * 1024];
                for (int i = 0; i < data.length; i++) data[i] = (byte) (i & 0x7F);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v LONGBLOB)");
                    }
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setBytes(1, data);
                        ps.executeUpdate();
                    }
                    long len = Db.scalarLong(c, "SELECT LENGTH(v) FROM " + t + " WHERE id=1");
                    Assert.eq((long) data.length, len, "longblob length round-trip " + kb + "kb");
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        // setBinaryStream / setCharacterStream LOB paths.
        r.register(fw, "bulk_lob", "set_character_stream", () -> {
            String t = Db.uniq("cs");
            String big = "y".repeat(500000);
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v LONGTEXT)");
                }
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setCharacterStream(1, new java.io.StringReader(big), big.length());
                    ps.executeUpdate();
                }
                long len = Db.scalarLong(c, "SELECT CHAR_LENGTH(v) FROM " + t + " WHERE id=1");
                Assert.eq((long) big.length(), len, "character stream round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Multi-statement execution variants.
        r.register(fw, "bulk_multi", "multi_statement_mixed", () -> {
            String t = Db.uniq("ms");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    boolean hasRs = st.execute("INSERT INTO " + t + " VALUES (1);"
                            + "INSERT INTO " + t + " VALUES (2);"
                            + "UPDATE " + t + " SET id = id + 10;"
                            + "SELECT COUNT(*) FROM " + t);
                    int guard = 0;
                    while (guard++ < 20) {
                        if (hasRs) { try (ResultSet rs = st.getResultSet()) { while (rs != null && rs.next()) {} } }
                        else if (st.getUpdateCount() == -1) break;
                        hasRs = st.getMoreResults();
                    }
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    Assert.eq(2L, n, "multi-statement mixed result");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "bulk_multi", "batched_multi_value_insert_10k", () -> {
            // Single statement with 10k VALUES tuples (rewrite-style monolith).
            String t = Db.uniq("mv");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT)");
                    StringBuilder sb = new StringBuilder("INSERT INTO " + t + " VALUES ");
                    for (int i = 0; i < 10000; i++) {
                        if (i > 0) sb.append(',');
                        sb.append('(').append(i).append(')');
                    }
                    st.execute(sb.toString());
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    Assert.eq(10000L, n, "10k multi-value insert");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Scrollable / updatable result sets.
        r.register(fw, "bulk_stream", "scrollable_resultset", () -> {
            String t = Db.uniq("scr");
            try (Connection c = db.connect(dbName)) {
                seedRows(c, t, 1000);
                try (Statement st = c.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
                     ResultSet rs = st.executeQuery("SELECT id FROM " + t + " ORDER BY id")) {
                    Assert.isTrue(rs.last(), "scroll to last");
                    int lastRow = rs.getRow();
                    Assert.isTrue(rs.first(), "scroll to first");
                    Assert.isTrue(lastRow >= 1, "scrollable row count >= 1");
                }
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private static void seedRows(Connection c, String t, int rows) throws java.sql.SQLException {
        try (Statement st = c.createStatement()) {
            st.execute("CREATE TABLE " + t + " (id INT)");
        }
        boolean prevAuto = c.getAutoCommit();
        c.setAutoCommit(false);
        try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?)")) {
            for (int i = 0; i < rows; i++) {
                ps.setInt(1, i);
                ps.addBatch();
                if (i % 2000 == 1999) ps.executeBatch();
            }
            ps.executeBatch();
        }
        c.commit();
        c.setAutoCommit(prevAuto);
    }
}
