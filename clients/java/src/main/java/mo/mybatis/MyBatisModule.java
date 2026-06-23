package mo.mybatis;

import mo.harness.Assert;
import mo.harness.Config;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import org.apache.ibatis.session.ExecutorType;
import org.apache.ibatis.session.SqlSession;
import org.apache.ibatis.session.SqlSessionFactory;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * MyBatis module: annotation mappers, result maps, dynamic SQL, type handlers,
 * batch executor, transactions, plus a per-type binding matrix run through
 * SqlSession.selectOne / insert with inline SQL.
 */
public final class MyBatisModule implements SuiteModule {
    public static final String DB = "mo_java_mybatis";
    private static final String FW = "mybatis";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private SqlSessionFactory factory;

    private SqlSessionFactory factory(Config cfg) {
        if (factory == null) {
            factory = MyBatisBoot.build(cfg, DB, UserMapper.class);
        }
        return factory;
    }

    @Override
    public void register(Runner r, Db db) {
        Config cfg = db.config();
        registerMapperFeatures(r, cfg);
        registerTypeHandlers(r, cfg);
        registerBatch(r, cfg);
        registerTransactions(r, cfg);
        registerDynamicSql(r, cfg);
        MyBatisTypeMatrix.register(r, db, FW, DB);
    }

    private void freshTable(SqlSession session) {
        UserMapper m = session.getMapper(UserMapper.class);
        m.dropTable();
        m.createTable();
        session.commit();
    }

    private void registerMapperFeatures(Runner r, Config cfg) {
        r.register(FW, "mapper", "insert_generated_key", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Alice");
                u.setAge(30);
                u.setBalance(new BigDecimal("100.50"));
                u.setActive(true);
                u.setBirthDate(LocalDate.of(1990, 1, 1));
                u.setCreatedAt(LocalDateTime.now());
                u.setTags("a,b");
                int n = m.insert(u);
                s.commit();
                Assert.eq(1, n, "insert affected rows");
                Assert.notNull(u.getId(), "generated key populated");
                m.dropTable();
                s.commit();
            }
        });
        r.register(FW, "mapper", "select_by_id", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Bob"); u.setAge(40);
                m.insertMinimal(u); s.commit();
                Long id = u.getId() == null ? 1L : u.getId();
                UserRecord got = m.findById(id);
                Assert.notNull(got, "found by id");
                Assert.eq("Bob", got.getName(), "name matched");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "mapper", "select_all", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                for (int i = 0; i < 5; i++) {
                    UserRecord u = new UserRecord();
                    u.setName("U" + i); u.setAge(20 + i);
                    m.insertMinimal(u);
                }
                s.commit();
                List<UserRecord> all = m.findAll();
                Assert.eq(5, all.size(), "select all count");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "mapper", "update", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Carol"); u.setAge(25);
                m.insertMinimal(u); s.commit();
                long id = m.findAll().get(0).getId();
                int n = m.updateAge(id, 26);
                s.commit();
                Assert.eq(1, n, "update affected rows");
                Assert.eq(26, m.findById(id).getAge(), "updated age");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "mapper", "delete", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Dave"); u.setAge(50);
                m.insertMinimal(u); s.commit();
                long id = m.findAll().get(0).getId();
                int n = m.delete(id);
                s.commit();
                Assert.eq(1, n, "delete affected rows");
                Assert.eq(0L, m.count(), "table empty after delete");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "mapper", "result_map", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                for (int i = 0; i < 5; i++) {
                    UserRecord u = new UserRecord();
                    u.setName("R" + i); u.setAge(10 + i * 10);
                    m.insertMinimal(u);
                }
                s.commit();
                List<UserRecord> older = m.findOlderThan(25);
                Assert.isTrue(!older.isEmpty(), "result-map query returned rows");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "mapper", "map_key_result", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("MapKey"); u.setAge(33);
                m.insertMinimal(u); s.commit();
                Map<String, Map<String, Object>> map = m.findAsMap();
                Assert.isTrue(!map.isEmpty() && map.keySet().stream()
                        .anyMatch(k -> k != null && k.equalsIgnoreCase("MapKey")),
                        "@MapKey indexed by name (keys=" + map.keySet() + ")");
                m.dropTable(); s.commit();
            }
        });
    }

    private void registerTypeHandlers(Runner r, Config cfg) {
        r.register(FW, "typehandler", "decimal_roundtrip", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Dec"); u.setAge(1);
                u.setBalance(new BigDecimal("9999.99"));
                u.setActive(false);
                u.setBirthDate(LocalDate.of(2000, 6, 15));
                u.setCreatedAt(LocalDateTime.of(2026, 6, 23, 12, 0, 0));
                m.insert(u); s.commit();
                UserRecord got = m.findById(u.getId());
                Assert.notNull(got.getBalance(), "decimal round-trip");
                Assert.eq(0, new BigDecimal("9999.99").compareTo(got.getBalance()), "decimal value");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "typehandler", "boolean_roundtrip", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Bool"); u.setAge(1); u.setActive(true);
                m.insert(u); s.commit();
                UserRecord got = m.findById(u.getId());
                Assert.eq(Boolean.TRUE, got.getActive(), "boolean round-trip");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "typehandler", "date_roundtrip", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Date"); u.setAge(1);
                u.setBirthDate(LocalDate.of(1985, 12, 25));
                m.insert(u); s.commit();
                UserRecord got = m.findById(u.getId());
                Assert.eq(LocalDate.of(1985, 12, 25), got.getBirthDate(), "date round-trip");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "typehandler", "datetime_roundtrip", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("DT"); u.setAge(1);
                u.setCreatedAt(LocalDateTime.of(2026, 6, 23, 9, 30, 0));
                m.insert(u); s.commit();
                UserRecord got = m.findById(u.getId());
                Assert.notNull(got.getCreatedAt(), "datetime round-trip");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "typehandler", "blob_roundtrip", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("Blob"); u.setAge(1);
                u.setAvatar(new byte[]{1, 2, 3, 4, 5});
                m.insert(u); s.commit();
                UserRecord got = m.findById(u.getId());
                Assert.notNull(got.getAvatar(), "blob round-trip");
                Assert.eq(5, got.getAvatar().length, "blob length");
                m.dropTable(); s.commit();
            }
        });
    }

    private void registerBatch(Runner r, Config cfg) {
        int[] sizes = {10, 100, 500};
        for (int sz : sizes) {
            r.register(FW, "batch", "batch_executor_" + sz, () -> {
                try (SqlSession setup = factory(cfg).openSession()) {
                    freshTable(setup);
                }
                try (SqlSession s = factory(cfg).openSession(ExecutorType.BATCH)) {
                    UserMapper m = s.getMapper(UserMapper.class);
                    for (int i = 0; i < sz; i++) {
                        UserRecord u = new UserRecord();
                        u.setName("Batch" + i);
                        u.setAge(i);
                        m.insertMinimal(u);
                    }
                    s.flushStatements();
                    s.commit();
                    Assert.eq((long) sz, m.count(), "batch executor inserted rows");
                    m.dropTable();
                    s.commit();
                }
            });
        }
    }

    private void registerTransactions(Runner r, Config cfg) {
        r.register(FW, "transaction", "commit", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("TxC"); u.setAge(1);
                m.insertMinimal(u);
                s.commit();
                Assert.eq(1L, m.count(), "committed row present");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "transaction", "rollback", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
            }
            try (SqlSession s = factory(cfg).openSession()) {
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("TxR"); u.setAge(1);
                m.insertMinimal(u);
                s.rollback();
            }
            try (SqlSession s = factory(cfg).openSession()) {
                UserMapper m = s.getMapper(UserMapper.class);
                Assert.eq(0L, m.count(), "rolled-back row absent");
                m.dropTable(); s.commit();
            }
        });
    }

    private void registerDynamicSql(Runner r, Config cfg) {
        r.register(FW, "dynamicsql", "search_by_name", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                for (String nm : List.of("Anna", "Anton", "Bob")) {
                    UserRecord u = new UserRecord();
                    u.setName(nm); u.setAge(30);
                    m.insertMinimal(u);
                }
                s.commit();
                List<UserRecord> res = m.search("An%", null);
                Assert.isTrue(!res.isEmpty(), "dynamic name search");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "dynamicsql", "search_by_name_and_age", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                for (int i = 0; i < 5; i++) {
                    UserRecord u = new UserRecord();
                    u.setName("Dyn" + i); u.setAge(20 + i * 5);
                    m.insertMinimal(u);
                }
                s.commit();
                List<UserRecord> res = m.search("Dyn%", 30);
                Assert.notNull(res, "dynamic name+age search");
                m.dropTable(); s.commit();
            }
        });
        r.register(FW, "dynamicsql", "search_no_filter", () -> {
            try (SqlSession s = factory(cfg).openSession()) {
                freshTable(s);
                UserMapper m = s.getMapper(UserMapper.class);
                UserRecord u = new UserRecord();
                u.setName("NoFilter"); u.setAge(1);
                m.insertMinimal(u); s.commit();
                List<UserRecord> res = m.search(null, null);
                Assert.isTrue(!res.isEmpty(), "dynamic no-filter search returns all");
                m.dropTable(); s.commit();
            }
        });
    }
}
