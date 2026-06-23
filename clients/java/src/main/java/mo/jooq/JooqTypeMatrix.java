package mo.jooq;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.jdbc.TypeSpec;
import org.jooq.DSLContext;
import org.jooq.Record;
import org.jooq.Result;
import org.jooq.SQLDialect;
import org.jooq.conf.Settings;
import org.jooq.impl.DSL;

import java.sql.Connection;
import java.sql.Statement;
import java.util.List;

import static org.jooq.impl.DSL.*;

/**
 * jOOQ per-type matrix: for each type, create a table (via plain DDL), insert a
 * sample row using the jOOQ DSL insert, then SELECT it back through the DSL and
 * count via fetchCount. Exercises jOOQ's value rendering/binding per type.
 */
public final class JooqTypeMatrix {

    public static void register(Runner r, Db db, String fw, String dbName) {
        List<TypeSpec> types = TypeSpec.all();
        for (TypeSpec ts : types) {
            // DSL select round-trip on a literal-inserted row.
            r.register(fw, "type", "select/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        Result<Record> res = ctx.select().from(table(name(t))).fetch();
                        Assert.eq(1, res.size(), "jooq select row for " + ts.key);
                        res.get(0).get(field(name("c")));
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
            // DSL fetchCount with a where predicate.
            if (ts.orderable) {
                r.register(fw, "type", "where/" + ts.key, () -> {
                    String t = Db.uniq("jt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                    new Settings().withRenderSchema(false));
                            int n = ctx.fetchCount(
                                    ctx.selectFrom(table(name(t)))
                                            .where(field(name("c")).eq(field("(" + ts.sampleLiteral + ")"))));
                            Assert.isTrue(n >= 0, "jooq where fetchCount for " + ts.key);
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // DSL insertInto with a bound value (string/literal-derived).
            r.register(fw, "type", "insert/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        // Use a literal-derived value expression so jOOQ renders/binds it.
                        int n = ctx.insertInto(table(name(t)), field(name("id")), field(name("c")))
                                .values(val(1), field("(" + ts.sampleLiteral + ")"))
                                .execute();
                        Assert.eq(1, n, "jooq DSL insert for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL fetchCount of all rows for this type.
            r.register(fw, "type", "fetchcount/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        int n = ctx.fetchCount(table(name(t)));
                        Assert.eq(1, n, "jooq fetchCount for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL ordered select for orderable types.
            if (ts.orderable) {
                r.register(fw, "type", "order/" + ts.key, () -> {
                    String t = Db.uniq("jt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                            DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                    new Settings().withRenderSchema(false));
                            Result<Record> res = ctx.select().from(table(name(t)))
                                    .orderBy(field(name("c")).asc(), field(name("id")).asc()).fetch();
                            Assert.eq(2, res.size(), "jooq ordered fetch for " + ts.key);
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // DSL update of the column.
            r.register(fw, "type", "update/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        int n = ctx.update(table(name(t)))
                                .set(field(name("c")), (Object) field("(" + ts.sampleLiteral + ")"))
                                .where(field(name("id"), Integer.class).eq(1))
                                .execute();
                        Assert.eq(1, n, "jooq DSL update for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL COUNT aggregate.
            r.register(fw, "type", "agg_count/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        Record rec = ctx.select(count(field(name("c")))).from(table(name(t))).fetchOne();
                        Assert.notNull(rec, "jooq count aggregate for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL GROUP BY this column.
            if (ts.groupable) {
                r.register(fw, "type", "group/" + ts.key, () -> {
                    String t = Db.uniq("jt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                            DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                    new Settings().withRenderSchema(false));
                            var res = ctx.select(field(name("c")), count())
                                    .from(table(name(t))).groupBy(field(name("c"))).fetch();
                            Assert.isTrue(res.size() >= 1, "jooq group-by for " + ts.key);
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // DSL DISTINCT select.
            r.register(fw, "type", "distinct/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        var res = ctx.selectDistinct(field(name("c")))
                                .from(table(name(t))).fetch();
                        Assert.isTrue(res.size() >= 1, "jooq distinct for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL window ROW_NUMBER ordered by this column.
            if (ts.orderable) {
                r.register(fw, "type", "window_rownum/" + ts.key, () -> {
                    String t = Db.uniq("jt_" + ts.key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                            st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                            st.execute("INSERT INTO " + t + " VALUES (2, " + ts.sampleLiteral + ")");
                            DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                    new Settings().withRenderSchema(false));
                            var res = ctx.select(field(name("id")),
                                            rowNumber().over(orderBy(field(name("c")))))
                                    .from(table(name(t))).fetch();
                            Assert.eq(2, res.size(), "jooq window for " + ts.key);
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }

            // DSL delete.
            r.register(fw, "type", "delete/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        int n = ctx.deleteFrom(table(name(t)))
                                .where(field(name("id"), Integer.class).eq(1)).execute();
                        Assert.eq(1, n, "jooq DSL delete for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL insert..select via plain DML, fetched through DSL.
            r.register(fw, "type", "fetchmaps/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        var maps = ctx.select().from(table(name(t))).fetchMaps();
                        Assert.eq(1, maps.size(), "jooq fetchMaps for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            // DSL fetchOne single record.
            r.register(fw, "type", "fetchone/" + ts.key, () -> {
                String t = Db.uniq("jt_" + ts.key);
                try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                    try {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ts.ddlType + ")");
                        st.execute("INSERT INTO " + t + " VALUES (1, " + ts.sampleLiteral + ")");
                        DSLContext ctx = DSL.using(c, SQLDialect.MYSQL,
                                new Settings().withRenderSchema(false));
                        var rec = ctx.select(field(name("c"))).from(table(name(t)))
                                .where(field(name("id"), Integer.class).eq(1)).fetchOne();
                        Assert.notNull(rec, "jooq fetchOne for " + ts.key);
                    } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }
    }
}
