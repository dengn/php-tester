package mo.flyway;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import org.flywaydb.core.Flyway;
import org.flywaydb.core.api.MigrationInfo;
import org.flywaydb.core.api.output.MigrateResult;

import java.sql.Connection;
import java.sql.Statement;

/**
 * Flyway schema-migration tooling against MatrixOne. Flyway introspects the
 * schema history table and uses information_schema; some of this may surface
 * MatrixOne compatibility findings (checksum / metadata-table issues).
 *
 * Each scenario runs in its own freshly-created schema so migration state is
 * isolated. Migration SQL is provided inline via setLocations on classpath
 * folders under db/migration/*.
 */
public final class FlywayModule implements SuiteModule {

    public static final String DB = "mo_java_flyway";
    private static final String FW = "flyway";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private Flyway flyway(Db db, String schema, String... locations) {
        return Flyway.configure(getClass().getClassLoader())
                .dataSource(db.config().dbUrl(schema), db.config().user, db.config().pass)
                .schemas(schema)
                .locations(locations)
                .baselineOnMigrate(true)
                .validateOnMigrate(false)
                .cleanDisabled(false)
                .load();
    }

    @Override
    public void register(Runner r, Db db) {
        // Core happy-path migration.
        r.register(FW, "migration", "migrate_basic", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/basic");
                MigrateResult res = fw.migrate();
                Assert.isTrue(res.migrationsExecuted >= 1, "at least one migration executed");
            } finally { dropSchema(db, schema); }
        });
        // Multiple versioned migrations applied in order.
        r.register(FW, "migration", "migrate_versioned_chain", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/chain");
                MigrateResult res = fw.migrate();
                Assert.isTrue(res.migrationsExecuted >= 2, "versioned chain executed >= 2 migrations");
            } finally { dropSchema(db, schema); }
        });
        // info() after migrate reads the schema history table.
        r.register(FW, "migration", "info_reads_history_table", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/basic");
                fw.migrate();
                MigrationInfo[] all = fw.info().all();
                Assert.isTrue(all.length >= 1, "flyway info read migration history");
            } finally { dropSchema(db, schema); }
        });
        // validate() after migrate — checksum verification (potential finding).
        r.register(FW, "migration", "validate_after_migrate", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = Flyway.configure(getClass().getClassLoader())
                        .dataSource(db.config().dbUrl(schema), db.config().user, db.config().pass)
                        .schemas(schema)
                        .locations("classpath:db/migration/basic")
                        .baselineOnMigrate(true)
                        .cleanDisabled(false)
                        .load();
                fw.migrate();
                fw.validate(); // throws if checksums/metadata mismatch
            } finally { dropSchema(db, schema); }
        });
        // baseline an existing schema.
        r.register(FW, "migration", "baseline_existing_schema", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try (Connection c = db.connect(DB); Statement st = c.createStatement()) {
                st.execute("CREATE TABLE " + schema + ".legacy (id INT)");
            } catch (Exception e) { /* schema may differ; ignore */ }
            try {
                Flyway fw = Flyway.configure(getClass().getClassLoader())
                        .dataSource(db.config().dbUrl(schema), db.config().user, db.config().pass)
                        .schemas(schema)
                        .locations("classpath:db/migration/basic")
                        .baselineVersion("1")
                        .load();
                fw.baseline();
                MigrationInfo[] all = fw.info().all();
                Assert.notNull(all, "baseline produced info");
            } finally { dropSchema(db, schema); }
        });
        // clean() then migrate() round-trip.
        r.register(FW, "migration", "clean_then_migrate", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/basic");
                fw.migrate();
                fw.clean();
                MigrateResult res = fw.migrate();
                Assert.isTrue(res.migrationsExecuted >= 1, "re-migrate after clean executed migrations");
            } finally { dropSchema(db, schema); }
        });
        // repeatable migration.
        r.register(FW, "migration", "repeatable_migration", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/repeatable");
                MigrateResult res = fw.migrate();
                Assert.isTrue(res.migrationsExecuted >= 1, "repeatable migration executed");
            } finally { dropSchema(db, schema); }
        });
        // migration with a failing SQL (DDL MatrixOne rejects) — documents partial-apply behavior.
        r.register(FW, "migration", "migration_with_rejected_ddl", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/failing");
                fw.migrate(); // expected to throw (MatrixOne rejects the procedure DDL)
            } finally { dropSchema(db, schema); }
        });
        // verify migrated table actually exists & is queryable.
        r.register(FW, "migration", "migrated_table_queryable", () -> {
            String schema = Db.uniq("fw");
            createSchema(db, schema);
            try {
                Flyway fw = flyway(db, schema, "classpath:db/migration/basic");
                fw.migrate();
                try (Connection c = db.config().dbUrl(schema) != null
                        ? java.sql.DriverManager.getConnection(db.config().dbUrl(schema), db.config().user, db.config().pass)
                        : null) {
                    long n = Db.scalarLong(c, "SELECT COUNT(*) FROM app_user");
                    Assert.isTrue(n >= 0, "migrated table app_user is queryable");
                }
            } finally { dropSchema(db, schema); }
        });
    }

    private void createSchema(Db db, String schema) {
        try (Connection c = db.connectServer(); Statement st = c.createStatement()) {
            st.execute("DROP DATABASE IF EXISTS " + schema);
            st.execute("CREATE DATABASE " + schema);
        } catch (Exception e) {
            throw new RuntimeException("could not create flyway schema " + schema, e);
        }
    }

    private void dropSchema(Db db, String schema) {
        try (Connection c = db.connectServer(); Statement st = c.createStatement()) {
            Db.quiet(st, "DROP DATABASE IF EXISTS " + schema);
        } catch (Exception ignore) {}
    }
}
