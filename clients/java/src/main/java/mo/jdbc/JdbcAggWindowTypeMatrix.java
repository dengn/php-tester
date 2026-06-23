package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;

/**
 * Per-type aggregate and window matrices: for each orderable type, run a set of
 * aggregate functions and window functions over a column of that type, plus
 * GROUP BY / HAVING / DISTINCT / set-operation / subquery variations.
 */
public final class JdbcAggWindowTypeMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        String[] aggs = {"COUNT", "MIN", "MAX"};
        // SUM/AVG only for numeric-ish types
        for (TypeSpec ts : types) {
            if (!ts.groupable) continue;

            for (String agg : aggs) {
                r.register(fw, "agg_type", agg.toLowerCase() + "/" + ts.key, () -> {
                    String t = Db.uniq("aw_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                            try (ResultSet rs = st.executeQuery("SELECT " + agg + "(c) FROM " + t)) {
                                Assert.isTrue(rs.next(), agg + " returned a row");
                                rs.getObject(1);
                            }
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // GROUP BY this column with COUNT
            r.register(fw, "agg_type", "group_count/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT c, COUNT(*) FROM " + t + " GROUP BY c")) {
                            Assert.isTrue(rs.next(), "group-count row");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // HAVING
            r.register(fw, "agg_type", "having/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT c, COUNT(*) ct FROM " + t + " GROUP BY c HAVING COUNT(*) >= 1")) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            if (!ts.orderable) continue;

            // ROW_NUMBER window ordered by this type
            r.register(fw, "win_type", "row_number/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id, ROW_NUMBER() OVER (ORDER BY c) AS rn FROM " + t)) {
                            while (rs.next()) rs.getObject("rn");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // RANK window partitioned/ordered by this type
            r.register(fw, "win_type", "rank/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id, RANK() OVER (ORDER BY c) AS rk FROM " + t)) {
                            while (rs.next()) rs.getObject("rk");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // LAG window
            r.register(fw, "win_type", "lag/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id, LAG(c) OVER (ORDER BY id) AS lg FROM " + t)) {
                            while (rs.next()) rs.getObject("lg");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // UNION over this column
            r.register(fw, "set_type", "union/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT c FROM " + t + " UNION SELECT c FROM " + t)) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // scalar subquery comparing this column
            r.register(fw, "sub_type", "scalar_cmp/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id FROM " + t + " WHERE c = (SELECT MAX(c) FROM " + t + ")")) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // CTE referencing this column
            r.register(fw, "cte_type", "simple/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "WITH x AS (SELECT c FROM " + t + ") SELECT COUNT(*) FROM x")) {
                            rs.next();
                            rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // self-join on this column
            r.register(fw, "join_type", "self_join/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT a.id FROM " + t + " a JOIN " + t + " b ON a.c = b.c")) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // BETWEEN range predicate on this column
            r.register(fw, "range_type", "between/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id FROM " + t + " WHERE c BETWEEN " + ts.sampleLiteral
                                        + " AND " + ts.sampleLiteral)) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // IN-list predicate on this column
            r.register(fw, "range_type", "in_list/" + ts.key, () -> {
                String t = Db.uniq("aw_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id FROM " + t + " WHERE c IN (" + ts.sampleLiteral + ")")) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }
}
