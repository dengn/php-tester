package mo.typesx;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;

/**
 * Vector (VECF32/VECF64 + l2/cosine/inner_product + IVFFLAT), full-text
 * (natural/boolean/relevance), and generated-column matrices.
 */
public final class VectorFulltextGrid {

    public static void register(Runner r, Db db, String fw, String dbName) {
        registerVectors(r, db, fw, dbName);
        registerFulltext(r, db, fw, dbName);
        registerGenerated(r, db, fw, dbName);
    }

    // ----------------------------------------------------------- vectors ----

    private static void registerVectors(Runner r, Db db, String fw, String dbName) {
        String[] vtypes = {"VECF32", "VECF64"};
        int[] dims = {3, 8, 128, 768, 1536};
        for (String vt : vtypes) {
            for (int dim : dims) {
                final String key = vt.toLowerCase() + "_d" + dim;
                r.register(fw, "vector", "declare/" + key, () -> {
                    String t = Db.uniq("vec_" + key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v " + vt + "(" + dim + "))");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
                r.register(fw, "vector", "insert_roundtrip/" + key, () -> {
                    String t = Db.uniq("vec_" + key);
                    String lit = vecLiteral(dim);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v " + vt + "(" + dim + "))");
                            st.execute("INSERT INTO " + t + " VALUES (1, '" + lit + "')");
                            Assert.eq(1L, Db.scalarLong(c, "SELECT COUNT(*) FROM " + t), "vector inserted " + key);
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
            // distance functions on a fixed small dim
            String[] distFns = {"l2_distance", "cosine_distance", "inner_product", "l2_distance_sq", "l1_distance"};
            for (String fn : distFns) {
                final String key = vt.toLowerCase() + "_" + fn;
                r.register(fw, "vector", "distance/" + key, () -> {
                    String t = Db.uniq("vd");
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v " + vt + "(3))");
                            st.execute("INSERT INTO " + t + " VALUES (1, '[1,2,3]'),(2,'[4,5,6]')");
                            try (ResultSet rs = st.executeQuery(
                                    "SELECT id, " + fn + "(v, '[1,2,3]') AS d FROM " + t + " ORDER BY d")) {
                                Assert.isTrue(rs.next(), fn + " produced rows");
                            }
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
            // IVFFLAT index per op_type
            String[] opTypes = {"vector_l2_ops", "vector_ip_ops", "vector_cosine_ops"};
            for (String op : opTypes) {
                final String key = vt.toLowerCase() + "_" + op;
                r.register(fw, "vector", "ivfflat_index/" + key, () -> {
                    String t = Db.uniq("vi");
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("SET experimental_ivf_index = 1");
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v " + vt + "(3))");
                            st.execute("INSERT INTO " + t + " VALUES (1, '[1,2,3]'),(2,'[4,5,6]'),(3,'[7,8,9]')");
                            st.execute("CREATE INDEX vidx USING ivfflat ON " + t + "(v) lists=1 op_type '" + op + "'");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
        }
        // KNN search ordering
        r.register(fw, "vector", "knn_order_by_distance", () -> {
            String t = Db.uniq("knn");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v VECF32(3))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'[1,1,1]'),(2,'[9,9,9]'),(3,'[2,2,2]')");
                    try (ResultSet rs = st.executeQuery(
                            "SELECT id FROM " + t + " ORDER BY l2_distance(v, '[1,1,1]') ASC LIMIT 1")) {
                        Assert.isTrue(rs.next(), "knn row");
                        Assert.eq(1, rs.getInt(1), "nearest neighbor is id 1");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private static String vecLiteral(int dim) {
        StringBuilder sb = new StringBuilder("[");
        for (int i = 0; i < dim; i++) {
            if (i > 0) sb.append(',');
            sb.append((i % 9) + 1).append(".5");
        }
        return sb.append(']').toString();
    }

    // --------------------------------------------------------- full-text ----

    private static void registerFulltext(Runner r, Db db, String fw, String dbName) {
        // natural language search
        r.register(fw, "fulltext", "natural_language", () -> {
            String t = Db.uniq("ftn");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("SET experimental_fulltext_index = 1");
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'the quick brown fox'),(2,'lazy dog sleeps')");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE MATCH(body) AGAINST('fox')");
                    Assert.isTrue(n >= 1, "natural-language match found");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        String[][] bool = {
                {"boolean_and", "+quick +fox"},
                {"boolean_not", "+dog -lazy"},
                {"boolean_or", "fox dog"},
                {"boolean_phrase", "\"brown fox\""},
                {"boolean_prefix", "qui*"},
        };
        for (String[] b : bool) {
            r.register(fw, "fulltext", b[0], () -> {
                String t = Db.uniq("ftb");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("SET experimental_fulltext_index = 1");
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
                        st.execute("INSERT INTO " + t + " VALUES (1,'the quick brown fox'),(2,'lazy dog sleeps')");
                        try (ResultSet rs = st.executeQuery("SELECT id FROM " + t
                                + " WHERE MATCH(body) AGAINST('" + b[1].replace("'", "''") + "' IN BOOLEAN MODE)")) {
                            while (rs.next()) rs.getInt(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        r.register(fw, "fulltext", "relevance_score", () -> {
            String t = Db.uniq("ftr");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("SET experimental_fulltext_index = 1");
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'fox fox fox'),(2,'fox once')");
                    try (ResultSet rs = st.executeQuery("SELECT id, MATCH(body) AGAINST('fox') AS rel FROM " + t
                            + " ORDER BY rel DESC")) {
                        Assert.isTrue(rs.next(), "relevance row");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "fulltext", "multi_column_index", () -> {
            String t = Db.uniq("ftm");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("SET experimental_fulltext_index = 1");
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, title VARCHAR(100), body TEXT, FULLTEXT(title, body))");
                    st.execute("INSERT INTO " + t + " VALUES (1,'News','breaking story')");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE MATCH(title, body) AGAINST('story')");
                    Assert.isTrue(n >= 1, "multi-column fulltext match");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // -------------------------------------------------- generated columns ----

    private static void registerGenerated(Runner r, Db db, String fw, String dbName) {
        String[][] gen = {
                {"stored_arith", "b INT GENERATED ALWAYS AS (a + 1) STORED", "a", "5", "b", "6"},
                {"virtual_arith", "b INT GENERATED ALWAYS AS (a * 2) VIRTUAL", "a", "5", "b", "10"},
                {"stored_concat", "full VARCHAR(40) GENERATED ALWAYS AS (CONCAT(a, '-x')) STORED", "a", "'k'", "full", "k-x"},
                {"stored_upper", "u VARCHAR(40) GENERATED ALWAYS AS (UPPER(a)) STORED", "a", "'abc'", "u", "ABC"},
                {"virtual_case", "tier VARCHAR(10) GENERATED ALWAYS AS (CASE WHEN a > 10 THEN 'hi' ELSE 'lo' END) VIRTUAL", "a", "20", "tier", "hi"},
                {"stored_json_extract", "name VARCHAR(40) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(a, '$.n'))) STORED", "a", "'{\"n\":\"Bob\"}'", "name", "Bob"},
        };
        for (String[] g : gen) {
            r.register(fw, "generated", g[0], () -> {
                String t = Db.uniq("gc");
                boolean isJson = g[0].contains("json");
                String aType = g[4].startsWith("'") ? (isJson ? "JSON" : "VARCHAR(40)") : "INT";
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, " + g[2] + " " + aType + ", " + g[1] + ")");
                        st.execute("INSERT INTO " + t + " (id, " + g[2] + ") VALUES (1, " + g[4] + ")");
                        String got = Db.scalarStr(c, "SELECT " + g[3] + " FROM " + t + " WHERE id=1");
                        Assert.eq(g[5], got, "generated column " + g[0]);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
        r.register(fw, "generated", "index_on_stored_generated", () -> {
            String t = Db.uniq("gci");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, a INT, b INT GENERATED ALWAYS AS (a+1) STORED)");
                    st.execute("CREATE INDEX ix_b ON " + t + " (b)");
                    st.execute("INSERT INTO " + t + " (id, a) VALUES (1, 5)");
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t + " WHERE b = 6");
                    Assert.eq(1L, n, "index on generated column used");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private VectorFulltextGrid() {}
}
