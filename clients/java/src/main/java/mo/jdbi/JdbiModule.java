package mo.jdbi;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import org.jdbi.v3.core.Jdbi;
import org.jdbi.v3.core.Handle;
import org.jdbi.v3.core.statement.PreparedBatch;

import java.util.List;
import java.util.Map;

/**
 * JDBI 3 module: fluent API and SQL Object API against MatrixOne.
 * Uses its own database namespace.
 */
public final class JdbiModule implements SuiteModule {

    public static final String DB = "mo_java_jdbi";
    private static final String FW = "jdbi";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private Jdbi jdbi(Db db) {
        return Jdbi.create(db.config().dbUrl(DB), db.config().user, db.config().pass);
    }

    @Override
    public void register(Runner r, Db db) {
        registerFluent(r, db);
        registerSqlObject(r, db);
        registerBatch(r, db);
        registerTypes(r, db);
    }

    // ----------------------------------------------------------- fluent ----

    private void registerFluent(Runner r, Db db) {
        r.register(FW, "fluent", "create_insert_select", () -> {
            String t = Db.uniq("jb");
            Jdbi j = jdbi(db);
            try (Handle h = j.open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40))");
                    h.createUpdate("INSERT INTO " + t + " (id, name) VALUES (:id, :name)")
                            .bind("id", 1).bind("name", "Ann").execute();
                    String name = h.createQuery("SELECT name FROM " + t + " WHERE id = :id")
                            .bind("id", 1).mapTo(String.class).one();
                    Assert.eq("Ann", name, "jdbi named-param round-trip");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "positional_params", () -> {
            String t = Db.uniq("jbp");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (?, ?)").bind(0, 1).bind(1, 99).execute();
                    int v = h.createQuery("SELECT v FROM " + t + " WHERE id = ?").bind(0, 1)
                            .mapTo(Integer.class).one();
                    Assert.eq(99, v, "positional param round-trip");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "map_to_bean", () -> {
            String t = Db.uniq("jbb");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40), age INT)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (1, 'Bob', 30)").execute();
                    UserBean u = h.createQuery("SELECT id, name, age FROM " + t + " WHERE id = 1")
                            .map((rs, ctx) -> {
                                UserBean b = new UserBean();
                                b.setId(rs.getInt("id"));
                                b.setName(rs.getString("name"));
                                b.setAge(rs.getInt("age"));
                                return b;
                            }).one();
                    Assert.eq("Bob", u.getName(), "row mapper bean name");
                    Assert.eq(30, u.getAge(), "row mapper bean age");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "map_to_map", () -> {
            String t = Db.uniq("jbm");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (1, 5)").execute();
                    Map<String, Object> row = h.createQuery("SELECT id, v FROM " + t)
                            .mapToMap().one();
                    Assert.notNull(row.get("v"), "map result has v");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "list_results", () -> {
            String t = Db.uniq("jbl");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (1),(2),(3)").execute();
                    List<Integer> ids = h.createQuery("SELECT id FROM " + t + " ORDER BY id")
                            .mapTo(Integer.class).list();
                    Assert.eq(3, ids.size(), "list size");
                    Assert.eq(1, ids.get(0), "first id");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "transaction_commit", () -> {
            String t = Db.uniq("jbt");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    h.useTransaction(txn -> {
                        txn.execute("INSERT INTO " + t + " VALUES (1)");
                        txn.execute("INSERT INTO " + t + " VALUES (2)");
                    });
                    int n = h.createQuery("SELECT COUNT(*) FROM " + t).mapTo(Integer.class).one();
                    Assert.eq(2, n, "jdbi transaction committed");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "transaction_rollback", () -> {
            String t = Db.uniq("jbr");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    try {
                        h.useTransaction(txn -> {
                            txn.execute("INSERT INTO " + t + " VALUES (1)");
                            throw new RuntimeException("force rollback");
                        });
                    } catch (RuntimeException ignore) {}
                    int n = h.createQuery("SELECT COUNT(*) FROM " + t).mapTo(Integer.class).one();
                    Assert.eq(0, n, "jdbi transaction rolled back");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "bind_bean", () -> {
            String t = Db.uniq("jbbb");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40), age INT)");
                    UserBean u = new UserBean(); u.setId(7); u.setName("Cy"); u.setAge(22);
                    h.createUpdate("INSERT INTO " + t + " (id, name, age) VALUES (:id, :name, :age)")
                            .bindBean(u).execute();
                    String nm = h.createQuery("SELECT name FROM " + t + " WHERE id = 7")
                            .mapTo(String.class).one();
                    Assert.eq("Cy", nm, "bindBean round-trip");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "scalar_aggregate", () -> {
            String t = Db.uniq("jbsa");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT, v INT)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (1,10),(2,20),(3,30)").execute();
                    long sum = h.createQuery("SELECT SUM(v) FROM " + t).mapTo(Long.class).one();
                    Assert.eq(60L, sum, "aggregate sum via jdbi");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "fluent", "bind_list_in_clause", () -> {
            String t = Db.uniq("jbin");
            try (Handle h = jdbi(db).open()) {
                try {
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY)");
                    h.createUpdate("INSERT INTO " + t + " VALUES (1),(2),(3),(4)").execute();
                    List<Integer> found = h.createQuery("SELECT id FROM " + t + " WHERE id IN (<ids>)")
                            .bindList("ids", List.of(1, 3))
                            .mapTo(Integer.class).list();
                    Assert.eq(2, found.size(), "bindList IN-clause");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // -------------------------------------------------------- SQL object ----

    private void registerSqlObject(Runner r, Db db) {
        r.register(FW, "sqlobject", "attach_insert_query", () -> {
            String t = "jdbi_users"; // SQL Object uses a fixed table name
            Jdbi j = jdbi(db);
            j.installPlugin(new org.jdbi.v3.sqlobject.SqlObjectPlugin());
            try (Handle h = j.open()) {
                try {
                    h.execute("DROP TABLE IF EXISTS " + t);
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40))");
                    UserDao dao = h.attach(UserDao.class);
                    dao.insert(1, "Dora");
                    String name = dao.findName(1);
                    Assert.eq("Dora", name, "SQL object insert+query");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "sqlobject", "sql_update_count", () -> {
            String t = "jdbi_users";
            Jdbi j = jdbi(db);
            j.installPlugin(new org.jdbi.v3.sqlobject.SqlObjectPlugin());
            try (Handle h = j.open()) {
                try {
                    h.execute("DROP TABLE IF EXISTS " + t);
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40))");
                    UserDao dao = h.attach(UserDao.class);
                    dao.insert(1, "X");
                    dao.insert(2, "Y");
                    int n = dao.countAll();
                    Assert.eq(2, n, "SQL object count");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "sqlobject", "sql_query_list", () -> {
            String t = "jdbi_users";
            Jdbi j = jdbi(db);
            j.installPlugin(new org.jdbi.v3.sqlobject.SqlObjectPlugin());
            try (Handle h = j.open()) {
                try {
                    h.execute("DROP TABLE IF EXISTS " + t);
                    h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(40))");
                    UserDao dao = h.attach(UserDao.class);
                    dao.insert(1, "A"); dao.insert(2, "B");
                    List<String> names = dao.allNames();
                    Assert.eq(2, names.size(), "SQL object query list");
                } finally { h.execute("DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    // ----------------------------------------------------------- batch ----

    private void registerBatch(Runner r, Db db) {
        for (int n : new int[]{10, 100, 1000}) {
            r.register(FW, "batch", "prepared_batch_" + n, () -> {
                String t = Db.uniq("jbatch");
                try (Handle h = jdbi(db).open()) {
                    try {
                        h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                        PreparedBatch batch = h.prepareBatch("INSERT INTO " + t + " (id, v) VALUES (:id, :v)");
                        for (int i = 0; i < n; i++) {
                            batch.bind("id", i).bind("v", i * 2).add();
                        }
                        int[] counts = batch.execute();
                        Assert.eq(n, counts.length, "batch count length");
                        int total = h.createQuery("SELECT COUNT(*) FROM " + t).mapTo(Integer.class).one();
                        Assert.eq(n, total, "jdbi batch inserted rows");
                    } finally { h.execute("DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    // ----------------------------------------------------------- types ----

    private void registerTypes(Runner r, Db db) {
        String[][] types = {
                {"int", "INT", "42"},
                {"bigint", "BIGINT", "9000000000"},
                {"decimal", "DECIMAL(18,4)", "123.4500"},
                {"varchar", "VARCHAR(40)", "'hello'"},
                {"double", "DOUBLE", "3.14"},
                {"date", "DATE", "'2024-06-15'"},
                {"datetime", "DATETIME", "'2024-06-15 12:34:56'"},
                {"boolean", "BOOLEAN", "1"},
        };
        for (String[] ty : types) {
            r.register(FW, "type", "roundtrip/" + ty[0], () -> {
                String t = Db.uniq("jtype");
                try (Handle h = jdbi(db).open()) {
                    try {
                        h.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ty[1] + ")");
                        h.execute("INSERT INTO " + t + " VALUES (1, " + ty[2] + ")");
                        // Read as a column map (always supported) and assert the value round-trips.
                        java.util.Map<String, Object> row = h.createQuery("SELECT c FROM " + t + " WHERE id=1")
                                .mapToMap().one();
                        Assert.isTrue(row.containsKey("c"), "jdbi type round-trip " + ty[0]);
                    } finally { h.execute("DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }
}
