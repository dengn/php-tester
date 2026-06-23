package mo.jooq;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.Record;
import org.jooq.Result;
import org.jooq.SQLDialect;
import org.jooq.Table;
import org.jooq.conf.Settings;
import org.jooq.impl.DSL;

import java.sql.Connection;
import java.sql.Statement;
import java.util.List;

import static org.jooq.impl.DSL.*;

/**
 * jOOQ module using the DSL WITHOUT code generation: DSL.using(connection) plus
 * DSL.table/field/name and plain-SQL building. Covers SELECT/INSERT/UPDATE/
 * DELETE, joins, CTEs, window functions, set operations, aggregates.
 */
public final class JooqModule implements SuiteModule {
    public static final String DB = "mo_java_jooq";
    private static final String FW = "jooq";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private DSLContext dsl(Connection c) {
        Settings settings = new Settings().withRenderSchema(false);
        return DSL.using(c, SQLDialect.MYSQL, settings);
    }

    @Override
    public void register(Runner r, Db db) {
        registerCrud(r, db);
        registerQueries(r, db);
        registerAggregates(r, db);
        registerWindow(r, db);
        registerSetOps(r, db);
        registerCte(r, db);
        registerPlainSql(r, db);
        JooqTypeMatrix.register(r, db, FW, DB);
    }

    private void createT(Statement st, String t) throws Exception {
        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, name VARCHAR(50), g INT, v INT)");
    }

    private void seed(DSLContext ctx, String t) {
        ctx.insertInto(table(name(t)),
                        field(name("id")), field(name("name")), field(name("g")), field(name("v")))
                .values(1, "alice", 1, 10)
                .values(2, "bob", 1, 20)
                .values(3, "carol", 2, 30)
                .values(4, "dave", 2, 40)
                .execute();
    }

    private void registerCrud(Runner r, Db db) {
        r.register(FW, "crud", "insert_values", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    int n = ctx.insertInto(table(name(t)),
                                    field(name("id")), field(name("name")), field(name("g")), field(name("v")))
                            .values(1, "x", 1, 100)
                            .execute();
                    Assert.eq(1, n, "jooq insert affected rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "insert_multi_values", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    int n = ctx.insertInto(table(name(t)),
                                    field(name("id")), field(name("name")), field(name("g")), field(name("v")))
                            .values(1, "a", 1, 1).values(2, "b", 1, 2).values(3, "c", 2, 3)
                            .execute();
                    Assert.eq(3, n, "jooq multi-insert affected rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "select_fetch", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.select().from(table(name(t))).fetch();
                    Assert.eq(4, res.size(), "jooq select fetched rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "select_where", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.select().from(table(name(t)))
                            .where(field(name("v"), Integer.class).gt(15)).fetch();
                    Assert.eq(3, res.size(), "jooq where filtered rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "update", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    int n = ctx.update(table(name(t)))
                            .set(field(name("v"), Integer.class), 999)
                            .where(field(name("id"), Integer.class).eq(1))
                            .execute();
                    Assert.eq(1, n, "jooq update affected rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "delete", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    int n = ctx.deleteFrom(table(name(t)))
                            .where(field(name("id"), Integer.class).eq(3))
                            .execute();
                    Assert.eq(1, n, "jooq delete affected rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "crud", "select_order_limit", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.select().from(table(name(t)))
                            .orderBy(field(name("v")).desc()).limit(2).fetch();
                    Assert.eq(2, res.size(), "jooq order+limit rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private void registerQueries(Runner r, Db db) {
        r.register(FW, "join", "inner_join", () -> {
            String a = Db.uniq("ja");
            String b = Db.uniq("jb");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + a + " (id INT, name VARCHAR(20))");
                    st.execute("CREATE TABLE " + b + " (aid INT, score INT)");
                    DSLContext ctx = dsl(c);
                    ctx.insertInto(table(name(a)), field(name("id")), field(name("name")))
                            .values(1, "x").values(2, "y").execute();
                    ctx.insertInto(table(name(b)), field(name("aid")), field(name("score")))
                            .values(1, 100).values(2, 200).execute();
                    Result<Record> res = ctx.select()
                            .from(table(name(a)).as("a"))
                            .join(table(name(b)).as("b"))
                            .on(field(name("a", "id"), Integer.class).eq(field(name("b", "aid"), Integer.class)))
                            .fetch();
                    Assert.eq(2, res.size(), "jooq inner join rows");
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + a);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + b);
                }
            }
        });
        r.register(FW, "join", "left_join", () -> {
            String a = Db.uniq("ja");
            String b = Db.uniq("jb");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    st.execute("CREATE TABLE " + a + " (id INT)");
                    st.execute("CREATE TABLE " + b + " (aid INT)");
                    DSLContext ctx = dsl(c);
                    ctx.insertInto(table(name(a)), field(name("id"))).values(1).values(2).values(3).execute();
                    ctx.insertInto(table(name(b)), field(name("aid"))).values(1).execute();
                    Result<Record> res = ctx.select().from(table(name(a)).as("a"))
                            .leftJoin(table(name(b)).as("b"))
                            .on(field(name("a", "id"), Integer.class).eq(field(name("b", "aid"), Integer.class)))
                            .fetch();
                    Assert.eq(3, res.size(), "jooq left join rows");
                } finally {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + a);
                    Db.quiet(st, "DROP TABLE IF EXISTS " + b);
                }
            }
        });
        r.register(FW, "subquery", "in_subquery", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.select().from(table(name(t)))
                            .where(field(name("v"), Integer.class)
                                    .in(select(field(name("v"), Integer.class)).from(table(name(t)))
                                            .where(field(name("v"), Integer.class).gt(20))))
                            .fetch();
                    Assert.isTrue(res.size() >= 1, "jooq in-subquery rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "subquery", "exists_subquery", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.select().from(table(name(t)).as("a"))
                            .whereExists(selectOne().from(table(name(t)).as("b"))
                                    .where(field(name("b", "v"), Integer.class)
                                            .eq(field(name("a", "v"), Integer.class))))
                            .fetch();
                    Assert.eq(4, res.size(), "jooq exists-subquery rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "grouping", "group_by_having", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    var res = ctx.select(field(name("g")), count())
                            .from(table(name(t)))
                            .groupBy(field(name("g")))
                            .having(count().gt(1))
                            .fetch();
                    Assert.isTrue(res.size() >= 1, "jooq group-by-having rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private void registerAggregates(Runner r, Db db) {
        String[][] aggs = {
                {"count", "count"},
                {"sum", "sum"},
                {"avg", "avg"},
                {"min", "min"},
                {"max", "max"},
        };
        for (String[] a : aggs) {
            r.register(FW, "aggregate", a[0], () -> {
                String t = Db.uniq("jq");
                try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                    try {
                        createT(st, t);
                        DSLContext ctx = dsl(c);
                        seed(ctx, t);
                        Field<Integer> v = field(name("v"), Integer.class);
                        Field<?> agg = switch (a[1]) {
                            case "count" -> count();
                            case "sum" -> sum(v);
                            case "avg" -> avg(v);
                            case "min" -> min(v);
                            case "max" -> max(v);
                            default -> count();
                        };
                        Record rec = ctx.select(agg).from(table(name(t))).fetchOne();
                        Assert.notNull(rec, "jooq aggregate " + a[0] + " result");
                        Assert.notNull(rec.get(0), "jooq aggregate value");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    private void registerWindow(Runner r, Db db) {
        String[] wins = {"row_number", "rank", "dense_rank", "lag", "lead", "sum_over"};
        for (String w : wins) {
            r.register(FW, "window", w, () -> {
                String t = Db.uniq("jq");
                try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                    try {
                        createT(st, t);
                        DSLContext ctx = dsl(c);
                        seed(ctx, t);
                        Field<Integer> v = field(name("v"), Integer.class);
                        Field<?> winExpr = switch (w) {
                            case "row_number" -> rowNumber().over(orderBy(v));
                            case "rank" -> rank().over(orderBy(v));
                            case "dense_rank" -> denseRank().over(orderBy(v));
                            case "lag" -> lag(v).over(orderBy(v));
                            case "lead" -> lead(v).over(orderBy(v));
                            case "sum_over" -> sum(v).over(partitionBy(field(name("g"))));
                            default -> rowNumber().over(orderBy(v));
                        };
                        var res = ctx.select(field(name("id")), winExpr).from(table(name(t))).fetch();
                        Assert.eq(4, res.size(), "jooq window " + w + " rows");
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }

    private void registerSetOps(Runner r, Db db) {
        r.register(FW, "setop", "union", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    var res = ctx.select(field(name("v"))).from(table(name(t)))
                            .union(select(field(name("v"))).from(table(name(t)))).fetch();
                    Assert.isTrue(res.size() >= 1, "jooq union rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "setop", "union_all", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    var res = ctx.select(field(name("v"))).from(table(name(t)))
                            .unionAll(select(field(name("v"))).from(table(name(t)))).fetch();
                    Assert.eq(8, res.size(), "jooq union all rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "setop", "intersect", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    var res = ctx.select(field(name("v"))).from(table(name(t)))
                            .intersect(select(field(name("v"))).from(table(name(t)))).fetch();
                    Assert.isTrue(res.size() >= 1, "jooq intersect rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "setop", "except", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    var res = ctx.select(field(name("v"))).from(table(name(t)))
                            .except(select(field(name("v"))).from(table(name(t)))
                                    .where(field(name("v"), Integer.class).gt(100))).fetch();
                    Assert.isTrue(res.size() >= 1, "jooq except rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private void registerCte(Runner r, Db db) {
        r.register(FW, "cte", "simple_cte", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                try {
                    createT(st, t);
                    DSLContext ctx = dsl(c);
                    seed(ctx, t);
                    Result<Record> res = ctx.with("x").as(
                                    select(field(name("v"))).from(table(name(t)))
                                            .where(field(name("v"), Integer.class).gt(15)))
                            .select().from(table(name("x"))).fetch();
                    Assert.eq(3, res.size(), "jooq cte rows");
                } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(FW, "cte", "recursive_cte", () -> {
            try (Connection c = db.connect(DB)) {
                DSLContext ctx = dsl(c);
                Result<Record> res = ctx.withRecursive("seq", "n").as(
                                select(val(1))
                                        .unionAll(
                                                select(field(name("n"), Integer.class).plus(1))
                                                        .from(table(name("seq")))
                                                        .where(field(name("n"), Integer.class).lt(10))))
                        .select().from(table(name("seq"))).fetch();
                Assert.eq(10, res.size(), "jooq recursive cte rows");
            }
        });
    }

    private void registerPlainSql(Runner r, Db db) {
        r.register(FW, "plainsql", "fetch_value", () -> {
            try (Connection c = db.connect(DB)) {
                DSLContext ctx = dsl(c);
                Integer v = ctx.fetchValue(field("1 + 1", Integer.class));
                Assert.eq(2, v, "jooq plain-sql scalar");
            }
        });
        r.register(FW, "plainsql", "result_query", () -> {
            try (Connection c = db.connect(DB)) {
                DSLContext ctx = dsl(c);
                Result<Record> res = ctx.fetch("SELECT 1 AS a UNION SELECT 2 UNION SELECT 3");
                Assert.eq(3, res.size(), "jooq plain-sql result query");
            }
        });
        r.register(FW, "plainsql", "execute_ddl_dml", () -> {
            String t = Db.uniq("jq");
            try (Connection c = db.connect(DB)) {
                DSLContext ctx = dsl(c);
                try {
                    ctx.execute("CREATE TABLE " + t + " (id INT)");
                    ctx.execute("INSERT INTO " + t + " VALUES (1),(2)");
                    int n = ctx.fetchCount(table(name(t)));
                    Assert.eq(2, n, "jooq fetchCount after plain dml");
                } finally {
                    ctx.execute("DROP TABLE IF EXISTS " + t);
                }
            }
        });
        r.register(FW, "plainsql", "bind_params", () -> {
            try (Connection c = db.connect(DB)) {
                DSLContext ctx = dsl(c);
                Result<Record> res = ctx.fetch("SELECT ? + ? AS s", 3, 4);
                Assert.eq(1, res.size(), "jooq plain-sql bind params");
            }
        });
    }
}
