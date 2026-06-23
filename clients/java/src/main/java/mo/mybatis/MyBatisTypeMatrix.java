package mo.mybatis;

import mo.harness.Assert;
import mo.harness.Config;
import mo.harness.Db;
import mo.harness.Runner;
import mo.jdbc.TypeSpec;
import org.apache.ibatis.session.SqlSession;
import org.apache.ibatis.session.SqlSessionFactory;

import java.sql.Connection;
import java.sql.Statement;
import java.util.List;
import java.util.Map;

/**
 * MyBatis per-type read matrix: for each type, set up a fixed table
 * (mb_typetest) with a column of that type via the SqlSession connection,
 * insert a sample row, then read it back through a MyBatis mapper so the
 * MyBatis type-handling / ResultMap machinery is exercised end to end.
 */
public final class MyBatisTypeMatrix {

    private static SqlSessionFactory factory;

    private static SqlSessionFactory factory(Config cfg, String db) {
        if (factory == null) {
            factory = MyBatisBoot.build(cfg, db, GenericMapper.class);
        }
        return factory;
    }

    public static void register(Runner r, Db db, String fw, String dbName) {
        Config cfg = db.config();
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            r.register(fw, "type", "read/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    long n = m.count();
                    Assert.eq(1L, n, "row count via MyBatis");
                    List<Map<String, Object>> rows = m.readAll();
                    Assert.isTrue(!rows.isEmpty(), "MyBatis read back rows for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // roundtrip via mapper readOne
            r.register(fw, "type", "roundtrip/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    Object v = m.readOne(1);
                    Assert.notNull(v, "MyBatis mapped value for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // not-null filtered read via mapper
            r.register(fw, "type", "notnull/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + " NULL)");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO mb_typetest (id) VALUES (2)");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    List<Map<String, Object>> rows = m.readNotNull();
                    Assert.eq(1, rows.size(), "MyBatis not-null read for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // ordered id read via mapper
            r.register(fw, "type", "order/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (2, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    List<Integer> ids = m.readIdsDesc();
                    Assert.eq(2, ids.size(), "MyBatis ordered read for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // count via mapper
            r.register(fw, "type", "count/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (2, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    Assert.eq(2L, m.count(), "MyBatis count for " + ts.key);
                    Assert.notNull(m.maxId(), "MyBatis max(id) for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // delete via mapper
            r.register(fw, "type", "delete/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    int n = m.deleteById(1);
                    s.commit();
                    Assert.eq(1, n, "MyBatis delete for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // update (touch) via mapper
            r.register(fw, "type", "update/" + ts.key, () -> {
                try (SqlSession s = factory(cfg, dbName).openSession()) {
                    Connection c = s.getConnection();
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                        st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                    }
                    s.commit();
                    GenericMapper m = s.getMapper(GenericMapper.class);
                    int n = m.touch(1);
                    s.commit();
                    Assert.eq(1, n, "MyBatis update for " + ts.key);
                    try (Statement st = c.createStatement()) {
                        st.execute("DROP TABLE IF EXISTS mb_typetest");
                    }
                    s.commit();
                }
            });
            // group-by via mapper (groupable types)
            if (ts.groupable) {
                r.register(fw, "type", "group/" + ts.key, () -> {
                    try (SqlSession s = factory(cfg, dbName).openSession()) {
                        Connection c = s.getConnection();
                        try (Statement st = c.createStatement()) {
                            st.execute("DROP TABLE IF EXISTS mb_typetest");
                            st.execute("CREATE TABLE mb_typetest (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO mb_typetest VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO mb_typetest VALUES (2, " + ts.sampleLiteral + ")");
                        }
                        s.commit();
                        GenericMapper m = s.getMapper(GenericMapper.class);
                        List<Object> g = m.groupByC();
                        Assert.isTrue(!g.isEmpty(), "MyBatis group-by for " + ts.key);
                        try (Statement st = c.createStatement()) {
                            st.execute("DROP TABLE IF EXISTS mb_typetest");
                        }
                        s.commit();
                    }
                });
            }
        }
    }
}
