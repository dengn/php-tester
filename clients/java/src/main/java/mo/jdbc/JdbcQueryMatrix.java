package mo.jdbc;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

/**
 * Query-pattern matrix: joins, subqueries, set operations, CTEs (incl.
 * recursive), window functions, grouping, aggregates, and DML variations.
 * Each scenario builds its own small fixture tables.
 */
public final class JdbcQueryMatrix {

    private record Q(String cat, String name, String sql, Long expectRows) {}

    public static void register(Runner r, Db db, String fw, String dbName) {
        // Aggregate functions over a fixture.
        String[][] aggs = {
                {"count_star", "SELECT COUNT(*) FROM %s"},
                {"count_col", "SELECT COUNT(v) FROM %s"},
                {"count_distinct", "SELECT COUNT(DISTINCT v) FROM %s"},
                {"sum", "SELECT SUM(v) FROM %s"},
                {"avg", "SELECT AVG(v) FROM %s"},
                {"min", "SELECT MIN(v) FROM %s"},
                {"max", "SELECT MAX(v) FROM %s"},
                {"stddev", "SELECT STDDEV(v) FROM %s"},
                {"stddev_pop", "SELECT STDDEV_POP(v) FROM %s"},
                {"stddev_samp", "SELECT STDDEV_SAMP(v) FROM %s"},
                {"var_pop", "SELECT VAR_POP(v) FROM %s"},
                {"var_samp", "SELECT VAR_SAMP(v) FROM %s"},
                {"variance", "SELECT VARIANCE(v) FROM %s"},
                {"group_concat", "SELECT GROUP_CONCAT(v) FROM %s"},
                {"group_concat_sep", "SELECT GROUP_CONCAT(v SEPARATOR '|') FROM %s"},
                {"group_concat_order", "SELECT GROUP_CONCAT(v ORDER BY v DESC) FROM %s"},
                {"bit_and", "SELECT BIT_AND(v) FROM %s"},
                {"bit_or", "SELECT BIT_OR(v) FROM %s"},
                {"bit_xor", "SELECT BIT_XOR(v) FROM %s"},
                {"json_arrayagg", "SELECT JSON_ARRAYAGG(v) FROM %s"},
                {"json_objectagg", "SELECT JSON_OBJECTAGG(id, v) FROM %s"},
                {"any_value", "SELECT ANY_VALUE(v) FROM %s"},
        };
        for (String[] a : aggs) {
            r.register(fw, "aggregate", a[0], () -> {
                String t = Db.uniq("agg");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1,10),(2,20),(3,30),(4,20)");
                        try (ResultSet rs = st.executeQuery(String.format(a[1], t))) {
                            Assert.isTrue(rs.next(), "aggregate returned a row");
                            rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Joins.
        String[][] joins = {
                {"inner_join", "SELECT a.id FROM %1$s a JOIN %2$s b ON a.id=b.aid"},
                {"left_join", "SELECT a.id FROM %1$s a LEFT JOIN %2$s b ON a.id=b.aid"},
                {"right_join", "SELECT a.id FROM %1$s a RIGHT JOIN %2$s b ON a.id=b.aid"},
                {"cross_join", "SELECT a.id FROM %1$s a CROSS JOIN %2$s b"},
                {"left_join_is_null", "SELECT a.id FROM %1$s a LEFT JOIN %2$s b ON a.id=b.aid WHERE b.aid IS NULL"},
                {"self_join", "SELECT a.id FROM %1$s a JOIN %1$s a2 ON a.id=a2.id"},
                {"join_using", "SELECT id FROM %1$s a JOIN %1$s b USING (id)"},
                {"natural_join", "SELECT a.id FROM %1$s a NATURAL JOIN %1$s b"},
                {"three_way_join", "SELECT a.id FROM %1$s a JOIN %2$s b ON a.id=b.aid JOIN %1$s c ON c.id=a.id"},
                {"join_with_and", "SELECT a.id FROM %1$s a JOIN %2$s b ON a.id=b.aid AND b.aid > 0"},
        };
        for (String[] j : joins) {
            r.register(fw, "join", j[0], () -> {
                String a = Db.uniq("ja");
                String b = Db.uniq("jb");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + a + " (id INT)");
                        st.execute("CREATE TABLE " + b + " (aid INT)");
                        st.execute("INSERT INTO " + a + " VALUES (1),(2),(3)");
                        st.execute("INSERT INTO " + b + " VALUES (1),(2)");
                        try (ResultSet rs = st.executeQuery(String.format(j[1], a, b))) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + a);
                        Db.quiet(st, "DROP TABLE IF EXISTS " + b);
                    }
                }
            });
        }

        // Subqueries.
        String[][] subs = {
                {"scalar_subquery", "SELECT (SELECT MAX(v) FROM %1$s) AS m"},
                {"in_subquery", "SELECT id FROM %1$s WHERE v IN (SELECT v FROM %1$s WHERE v > 10)"},
                {"not_in_subquery", "SELECT id FROM %1$s WHERE v NOT IN (SELECT v FROM %1$s WHERE v < 0)"},
                {"exists_subquery", "SELECT id FROM %1$s a WHERE EXISTS (SELECT 1 FROM %1$s b WHERE b.v=a.v)"},
                {"not_exists_subquery", "SELECT id FROM %1$s a WHERE NOT EXISTS (SELECT 1 FROM %1$s b WHERE b.v=a.v+1000)"},
                {"correlated_subquery", "SELECT id, (SELECT COUNT(*) FROM %1$s b WHERE b.v=a.v) AS cnt FROM %1$s a"},
                {"derived_table", "SELECT d.mx FROM (SELECT MAX(v) AS mx FROM %1$s) d"},
                {"any_subquery", "SELECT id FROM %1$s WHERE v > ANY (SELECT v FROM %1$s)"},
                {"all_subquery", "SELECT id FROM %1$s WHERE v >= ALL (SELECT v FROM %1$s)"},
                {"subquery_in_select", "SELECT v, (SELECT COUNT(*) FROM %1$s) AS total FROM %1$s"},
        };
        for (String[] s : subs) {
            r.register(fw, "subquery", s[0], () -> {
                String t = Db.uniq("sub");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1,10),(2,20),(3,30)");
                        try (ResultSet rs = st.executeQuery(String.format(s[1], t))) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Set operations.
        String[][] sets = {
                {"union", "SELECT v FROM %1$s UNION SELECT v FROM %1$s"},
                {"union_all", "SELECT v FROM %1$s UNION ALL SELECT v FROM %1$s"},
                {"intersect", "SELECT v FROM %1$s INTERSECT SELECT v FROM %1$s"},
                {"except", "SELECT v FROM %1$s EXCEPT SELECT v FROM %1$s WHERE v > 100"},
                {"union_order_limit", "(SELECT v FROM %1$s) UNION (SELECT v FROM %1$s) ORDER BY v LIMIT 2"},
                {"minus", "SELECT v FROM %1$s MINUS SELECT v FROM %1$s WHERE v > 100"},
        };
        for (String[] s : sets) {
            r.register(fw, "setop", s[0], () -> {
                String t = Db.uniq("setop");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1),(2),(3)");
                        try (ResultSet rs = st.executeQuery(String.format(s[1], t))) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // CTE (incl. recursive).
        r.register(fw, "cte", "simple_cte", () -> {
            String t = Db.uniq("cte");
            try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + t + " (v INT)");
                    st.execute("INSERT INTO " + t + " VALUES (1),(2),(3)");
                    try (ResultSet rs = st.executeQuery(
                            "WITH x AS (SELECT v FROM " + t + " WHERE v > 1) SELECT COUNT(*) FROM x")) {
                        rs.next();
                        Assert.eq(2L, rs.getLong(1), "cte count");
                    }
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "cte", "multi_cte", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement();
                 ResultSet rs = st.executeQuery(
                         "WITH a AS (SELECT 1 AS x), b AS (SELECT 2 AS y) SELECT a.x+b.y FROM a,b")) {
                rs.next();
                Assert.eq(3L, rs.getLong(1), "multi cte");
            }
        });
        r.register(fw, "cte", "recursive_cte_count", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement();
                 ResultSet rs = st.executeQuery(
                         "WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < 10) "
                                 + "SELECT COUNT(*) FROM seq")) {
                rs.next();
                Assert.eq(10L, rs.getLong(1), "recursive cte count");
            }
        });
        r.register(fw, "cte", "recursive_cte_factorial_chain", () -> {
            try (Connection c = db.connect(dbName); Statement st = c.createStatement();
                 ResultSet rs = st.executeQuery(
                         "WITH RECURSIVE f(n, acc) AS (SELECT 1,1 UNION ALL SELECT n+1, acc*(n+1) FROM f WHERE n < 5) "
                                 + "SELECT MAX(acc) FROM f")) {
                rs.next();
                Assert.eq(120L, rs.getLong(1), "recursive factorial");
            }
        });

        // Window functions.
        String[][] wins = {
                {"row_number", "ROW_NUMBER() OVER (ORDER BY v)"},
                {"rank", "RANK() OVER (ORDER BY v)"},
                {"dense_rank", "DENSE_RANK() OVER (ORDER BY v)"},
                {"percent_rank", "PERCENT_RANK() OVER (ORDER BY v)"},
                {"cume_dist", "CUME_DIST() OVER (ORDER BY v)"},
                {"ntile", "NTILE(2) OVER (ORDER BY v)"},
                {"lag", "LAG(v) OVER (ORDER BY v)"},
                {"lead", "LEAD(v) OVER (ORDER BY v)"},
                {"first_value", "FIRST_VALUE(v) OVER (ORDER BY v)"},
                {"last_value", "LAST_VALUE(v) OVER (ORDER BY v)"},
                {"nth_value", "NTH_VALUE(v,2) OVER (ORDER BY v)"},
                {"sum_over", "SUM(v) OVER (PARTITION BY g)"},
                {"avg_over", "AVG(v) OVER (PARTITION BY g ORDER BY v)"},
                {"count_over", "COUNT(*) OVER ()"},
                {"sum_frame_rows", "SUM(v) OVER (ORDER BY v ROWS BETWEEN 1 PRECEDING AND CURRENT ROW)"},
                {"sum_frame_range", "SUM(v) OVER (ORDER BY v RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)"},
        };
        for (String[] w : wins) {
            r.register(fw, "window", w[0], () -> {
                String t = Db.uniq("win");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, g INT, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40)");
                        try (ResultSet rs = st.executeQuery(
                                "SELECT id, " + w[1] + " AS w FROM " + t)) {
                            while (rs.next()) rs.getObject("w");
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Grouping / having.
        String[][] groups = {
                {"group_by", "SELECT g, COUNT(*) FROM %1$s GROUP BY g"},
                {"group_by_having", "SELECT g, COUNT(*) c FROM %1$s GROUP BY g HAVING COUNT(*) > 1"},
                {"group_by_multi", "SELECT g, v, COUNT(*) FROM %1$s GROUP BY g, v"},
                {"group_by_expr", "SELECT g*2 AS gg, COUNT(*) FROM %1$s GROUP BY g*2"},
                {"group_by_rollup", "SELECT g, SUM(v) FROM %1$s GROUP BY g WITH ROLLUP"},
                {"order_by_agg", "SELECT g, SUM(v) s FROM %1$s GROUP BY g ORDER BY s DESC"},
                {"distinct_select", "SELECT DISTINCT g FROM %1$s"},
                {"limit_offset", "SELECT id FROM %1$s ORDER BY id LIMIT 2 OFFSET 1"},
                {"limit_comma", "SELECT id FROM %1$s ORDER BY id LIMIT 1, 2"},
        };
        for (String[] g : groups) {
            r.register(fw, "grouping", g[0], () -> {
                String t = Db.uniq("grp");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT, g INT, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1,1,10),(2,1,20),(3,2,30)");
                        try (ResultSet rs = st.executeQuery(String.format(g[1], t))) {
                            while (rs.next()) rs.getObject(1);
                        }
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // DML variations.
        String[][] dml = {
                {"insert_select", "ins_select"},
                {"insert_multi_row", "ins_multi"},
                {"insert_ignore", "ins_ignore"},
                {"insert_on_duplicate", "ins_odku"},
                {"replace_into", "replace"},
                {"update_where", "upd_where"},
                {"update_order_limit", "upd_ol"},
                {"delete_where", "del_where"},
                {"delete_order_limit", "del_ol"},
                {"update_join", "upd_join"},
                {"delete_subselect", "del_subselect"},
        };
        for (String[] d : dml) {
            String name = d[0];
            String op = d[1];
            r.register(fw, "dml", name, () -> {
                String t = Db.uniq("dml");
                String t2 = Db.uniq("dml2");
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1,10),(2,20),(3,30)");
                        switch (op) {
                            case "ins_select" -> {
                                st.execute("CREATE TABLE " + t2 + " (id INT, v INT)");
                                st.execute("INSERT INTO " + t2 + " SELECT id, v FROM " + t);
                            }
                            case "ins_multi" -> st.execute("INSERT INTO " + t + " VALUES (4,40),(5,50)");
                            case "ins_ignore" -> st.execute("INSERT IGNORE INTO " + t + " VALUES (1,99)");
                            case "ins_odku" -> st.execute(
                                    "INSERT INTO " + t + " VALUES (1,99) ON DUPLICATE KEY UPDATE v=VALUES(v)");
                            case "replace" -> st.execute("REPLACE INTO " + t + " VALUES (1,77)");
                            case "upd_where" -> st.executeUpdate("UPDATE " + t + " SET v=v+1 WHERE id=1");
                            case "upd_ol" -> st.executeUpdate("UPDATE " + t + " SET v=v+1 ORDER BY id LIMIT 1");
                            case "del_where" -> st.executeUpdate("DELETE FROM " + t + " WHERE id=3");
                            case "del_ol" -> st.executeUpdate("DELETE FROM " + t + " ORDER BY id LIMIT 1");
                            case "upd_join" -> {
                                st.execute("CREATE TABLE " + t2 + " (id INT, w INT)");
                                st.execute("INSERT INTO " + t2 + " VALUES (1,100)");
                                st.executeUpdate("UPDATE " + t + " a JOIN " + t2 + " b ON a.id=b.id SET a.v=b.w");
                            }
                            case "del_subselect" -> st.executeUpdate(
                                    "DELETE FROM " + t + " WHERE id IN (SELECT id FROM (SELECT id FROM " + t + " WHERE v > 100) x)");
                            default -> throw new IllegalStateException(name);
                        }
                    } finally {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t2);
                    }
                }
            });
        }
    }
}
