package mo.liquibase;

import liquibase.Liquibase;
import liquibase.database.Database;
import liquibase.database.DatabaseFactory;
import liquibase.database.jvm.JdbcConnection;
import liquibase.resource.ClassLoaderResourceAccessor;
import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;

import java.sql.Connection;
import java.sql.Statement;

/**
 * Liquibase schema-migration tooling against MatrixOne. Liquibase creates its
 * DATABASECHANGELOG / DATABASECHANGELOGLOCK tracking tables and introspects the
 * schema; these may surface MatrixOne metadata/locking findings.
 */
public final class LiquibaseModule implements SuiteModule {

    public static final String DB = "mo_java_liquibase";
    private static final String FW = "liquibase";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    @Override
    public void register(Runner r, Db db) {
        r.register(FW, "migration", "update_xml_changelog", () -> runUpdate(db, "db/changelog/changelog-basic.xml", 2));
        r.register(FW, "migration", "update_chain_changelog", () -> runUpdate(db, "db/changelog/changelog-chain.xml", 3));
        r.register(FW, "migration", "changelog_tracking_table_created", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                runLiquibase(c, "db/changelog/changelog-basic.xml");
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM DATABASECHANGELOG");
                Assert.isTrue(n >= 1, "DATABASECHANGELOG tracking table populated");
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "rollback_count", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                Database database = DatabaseFactory.getInstance()
                        .findCorrectDatabaseImplementation(new JdbcConnection(c));
                try (Liquibase lb = new Liquibase("db/changelog/changelog-chain.xml",
                        new ClassLoaderResourceAccessor(getClass().getClassLoader()), database)) {
                    lb.update("");
                    lb.rollback(1, ""); // roll back the most recent changeset
                }
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "status_after_update", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                Database database = DatabaseFactory.getInstance()
                        .findCorrectDatabaseImplementation(new JdbcConnection(c));
                try (Liquibase lb = new Liquibase("db/changelog/changelog-basic.xml",
                        new ClassLoaderResourceAccessor(getClass().getClassLoader()), database)) {
                    lb.update("");
                    // listUnrunChangeSets should be empty after a full update.
                    int unrun = lb.listUnrunChangeSets(null, null).size();
                    Assert.eq(0, unrun, "no unrun changesets after update");
                }
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "tag_and_rollback_to_tag", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                Database database = DatabaseFactory.getInstance()
                        .findCorrectDatabaseImplementation(new JdbcConnection(c));
                try (Liquibase lb = new Liquibase("db/changelog/changelog-basic.xml",
                        new ClassLoaderResourceAccessor(getClass().getClassLoader()), database)) {
                    lb.update("");
                    lb.tag("v1");
                    lb.rollback("v1", "");
                }
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "update_then_validate", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                Database database = DatabaseFactory.getInstance()
                        .findCorrectDatabaseImplementation(new JdbcConnection(c));
                try (Liquibase lb = new Liquibase("db/changelog/changelog-basic.xml",
                        new ClassLoaderResourceAccessor(getClass().getClassLoader()), database)) {
                    lb.update("");
                    lb.validate(); // checksum / changelog validation
                }
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "changeset_with_constraints", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                runLiquibase(c, "db/changelog/changelog-constraints.xml");
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM DATABASECHANGELOG");
                Assert.isTrue(n >= 1, "constraint changeset applied");
            } finally { dropSchema(db, schema); }
        });
        r.register(FW, "migration", "migrated_table_queryable", () -> {
            String schema = Db.uniq("lb");
            createSchema(db, schema);
            try (Connection c = connect(db, schema)) {
                runLiquibase(c, "db/changelog/changelog-basic.xml");
                long n = Db.scalarLong(c, "SELECT COUNT(*) FROM customer");
                Assert.isTrue(n >= 0, "liquibase-created table customer queryable");
            } finally { dropSchema(db, schema); }
        });
    }

    private void runUpdate(Db db, String changelog, int minChanges) throws Exception {
        String schema = Db.uniq("lb");
        createSchema(db, schema);
        try (Connection c = connect(db, schema)) {
            runLiquibase(c, changelog);
            long n = Db.scalarLong(c, "SELECT COUNT(*) FROM DATABASECHANGELOG");
            Assert.isTrue(n >= minChanges, "applied >= " + minChanges + " changesets (got " + n + ")");
        } finally { dropSchema(db, schema); }
    }

    private void runLiquibase(Connection c, String changelog) throws Exception {
        Database database = DatabaseFactory.getInstance()
                .findCorrectDatabaseImplementation(new JdbcConnection(c));
        try (Liquibase lb = new Liquibase(changelog,
                new ClassLoaderResourceAccessor(getClass().getClassLoader()), database)) {
            lb.update("");
        }
    }

    private Connection connect(Db db, String schema) throws Exception {
        return java.sql.DriverManager.getConnection(db.config().dbUrl(schema), db.config().user, db.config().pass);
    }

    private void createSchema(Db db, String schema) {
        try (Connection c = db.connectServer(); Statement st = c.createStatement()) {
            st.execute("DROP DATABASE IF EXISTS " + schema);
            st.execute("CREATE DATABASE " + schema);
        } catch (Exception e) {
            throw new RuntimeException("could not create liquibase schema " + schema, e);
        }
    }

    private void dropSchema(Db db, String schema) {
        try (Connection c = db.connectServer(); Statement st = c.createStatement()) {
            Db.quiet(st, "DROP DATABASE IF EXISTS " + schema);
        } catch (Exception ignore) {}
    }
}
