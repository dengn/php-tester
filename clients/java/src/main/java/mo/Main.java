package mo;

import mo.harness.Config;
import mo.harness.Db;
import mo.harness.Reporter;
import mo.harness.Result;
import mo.harness.Runner;
import mo.harness.Scenario;
import mo.harness.SuiteModule;
import mo.hibernate.HibernateModule;
import mo.jdbc.JdbcModule;
import mo.jooq.JooqModule;
import mo.mybatis.MyBatisModule;
import mo.flyway.FlywayModule;
import mo.liquibase.LiquibaseModule;
import mo.springdata.SpringDataModule;
import mo.jdbi.JdbiModule;
import mo.mega.MegaModule;

import java.nio.file.Path;
import java.sql.Connection;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.TreeSet;
import java.util.function.Predicate;

/**
 * Entry point for the MatrixOne Java ORM compatibility suite.
 *
 * Usage:
 *   mvn -q compile exec:java
 *   mvn -q compile exec:java -Dexec.args="--only=hibernate"
 *   mvn -q compile exec:java -Dexec.args="--only=jdbc,jooq --limit-per-framework=200"
 *
 * Args:
 *   --only=fw1,fw2          run only these frameworks (jdbc|hibernate|mybatis|jooq)
 *   --category=cat1,cat2    run only these categories
 *   --limit-per-framework=N representative subset: cap scenarios per framework
 *   --list                  print registered count and exit (no DB activity beyond registration)
 */
public final class Main {

    static {
        // Quiet down Hibernate/MyBatis logging (java.util.logging) so the suite
        // output stays readable; findings are reported via results.json.
        try {
            java.util.logging.LogManager.getLogManager().reset();
            java.util.logging.Logger root = java.util.logging.Logger.getLogger("");
            root.setLevel(java.util.logging.Level.SEVERE);
        } catch (Throwable ignore) {
        }
        System.setProperty("org.jboss.logging.provider", "jdk");
    }

    public static void main(String[] args) throws Exception {
        Locale.setDefault(Locale.US);
        Options opts = Options.parse(args);

        Config cfg = Config.fromEnv();
        System.out.println("=== MatrixOne Java ORM Compatibility Suite ===");
        System.out.println("Target: " + cfg);

        // Detect engine version (best effort).
        String engine = "MatrixOne (unknown)";
        try {
            Db probe = new Db(cfg);
            try (Connection c = probe.connectServer()) {
                engine = Db.scalarStr(c, "select version()");
            }
        } catch (Exception e) {
            System.out.println("WARN: could not read version(): " + e.getMessage());
        }
        System.out.println("Engine: " + engine);

        List<SuiteModule> modules = new ArrayList<>();
        modules.add(new JdbcModule());
        modules.add(new HibernateModule());
        modules.add(new MyBatisModule());
        modules.add(new JooqModule());
        modules.add(new JdbiModule());
        modules.add(new SpringDataModule());
        modules.add(new FlywayModule());
        modules.add(new LiquibaseModule());
        modules.add(new MegaModule());

        Runner runner = new Runner();
        Db db = new Db(cfg);

        // Determine which frameworks are active.
        Set<String> activeFw = new TreeSet<>();
        for (SuiteModule m : modules) {
            if (opts.only.isEmpty() || opts.only.contains(m.framework())) {
                activeFw.add(m.framework());
            }
        }

        // Create databases and register scenarios for active frameworks.
        for (SuiteModule m : modules) {
            if (!activeFw.contains(m.framework())) continue;
            System.out.print("Registering " + m.framework() + " ... ");
            int before = runner.registeredCount();
            try {
                db.createDatabase(m.database());
            } catch (Exception e) {
                System.out.println("FAILED to create database " + m.database() + ": " + e.getMessage());
            }
            try {
                m.register(runner, db);
            } catch (Throwable t) {
                System.out.println("REGISTRATION ERROR in " + m.framework() + ": " + t);
                t.printStackTrace();
            }
            System.out.println((runner.registeredCount() - before) + " scenarios");
        }

        int registered = runner.registeredCount();
        System.out.println("TOTAL REGISTERED SCENARIOS: " + registered);

        // Per-framework registered counts.
        Map<String, Integer> regByFw = new LinkedHashMap<>();
        for (Scenario s : runner.scenarios()) {
            regByFw.merge(s.framework, 1, Integer::sum);
        }
        regByFw.forEach((k, v) -> System.out.println("  registered " + k + ": " + v));

        if (opts.listOnly) {
            System.out.println("--list given; exiting before execution.");
            db.dropDatabase("mo_java_jdbc");
            db.dropDatabase("mo_java_hibernate");
            db.dropDatabase("mo_java_mybatis");
            db.dropDatabase("mo_java_jooq");
            db.dropDatabase("mo_java_jdbi");
            db.dropDatabase("mo_java_springdata");
            db.dropDatabase("mo_java_flyway");
            db.dropDatabase("mo_java_liquibase");
            return;
        }

        // Precompute the selected set (stateless during run) so the per-framework
        // cap is applied exactly once, not consumed twice by counting + running.
        java.util.Set<Scenario> selected = java.util.Collections.newSetFromMap(new java.util.IdentityHashMap<>());
        // First apply only/category filters.
        List<Scenario> eligible = new ArrayList<>();
        for (Scenario s : runner.scenarios()) {
            if (!opts.only.isEmpty() && !opts.only.contains(s.framework)) continue;
            if (!opts.categories.isEmpty() && !opts.categories.contains(s.category.toLowerCase(Locale.US))) continue;
            eligible.add(s);
        }
        if (opts.limitPerFramework <= 0) {
            selected.addAll(eligible);
        } else {
            // Stride-sample up to N per framework so every category is represented.
            Map<String, List<Scenario>> byFwList = new LinkedHashMap<>();
            for (Scenario s : eligible) {
                byFwList.computeIfAbsent(s.framework, k -> new ArrayList<>()).add(s);
            }
            for (var e : byFwList.entrySet()) {
                List<Scenario> list = e.getValue();
                int n = Math.min(opts.limitPerFramework, list.size());
                if (n == list.size()) {
                    selected.addAll(list);
                } else {
                    double stride = (double) list.size() / n;
                    for (int i = 0; i < n; i++) {
                        selected.add(list.get((int) Math.floor(i * stride)));
                    }
                }
            }
        }
        Predicate<Scenario> filter = selected::contains;

        int toRun = selected.size();
        System.out.println("Scenarios selected to run: " + toRun + " of " + registered);
        long t0 = System.currentTimeMillis();
        int ran = runner.run(filter, 250);
        long elapsed = System.currentTimeMillis() - t0;
        System.out.printf("Ran %d scenarios in %.1f s%n", ran, elapsed / 1000.0);

        // Cleanup databases for active frameworks.
        for (SuiteModule m : modules) {
            if (activeFw.contains(m.framework())) {
                db.dropDatabase(m.database());
            }
        }

        // Report.
        List<Result> results = runner.results();
        Reporter reporter = new Reporter(engine, results);
        Path reportsDir = Path.of("reports");
        reporter.write(reportsDir);

        // Console summary.
        int pass = 0, fail = 0, skip = 0;
        for (Result r : results) {
            switch (r.status) {
                case "PASS" -> pass++;
                case "FAIL" -> fail++;
                case "SKIP" -> skip++;
            }
        }
        System.out.println("---------------------------------------------");
        System.out.printf("RESULTS: total=%d pass=%d fail=%d skip=%d%n",
                results.size(), pass, fail, skip);
        Map<String, int[]> byFw = new LinkedHashMap<>();
        for (Result r : results) {
            int[] c = byFw.computeIfAbsent(r.framework, k -> new int[3]);
            switch (r.status) {
                case "PASS" -> c[0]++;
                case "FAIL" -> c[1]++;
                case "SKIP" -> c[2]++;
            }
        }
        byFw.forEach((k, c) ->
                System.out.printf("  %-10s pass=%-5d fail=%-5d skip=%-5d%n", k, c[0], c[1], c[2]));
        System.out.println("Wrote reports/results.json and reports/summary.md");
    }

    static final class Options {
        Set<String> only = new TreeSet<>();
        Set<String> categories = new TreeSet<>();
        int limitPerFramework = 0;
        boolean listOnly = false;

        static Options parse(String[] args) {
            Options o = new Options();
            for (String a : args) {
                if (a.startsWith("--only=")) {
                    for (String f : a.substring("--only=".length()).split(",")) {
                        if (!f.isBlank()) o.only.add(f.trim().toLowerCase(Locale.US));
                    }
                } else if (a.startsWith("--category=")) {
                    for (String f : a.substring("--category=".length()).split(",")) {
                        if (!f.isBlank()) o.categories.add(f.trim().toLowerCase(Locale.US));
                    }
                } else if (a.startsWith("--limit-per-framework=")) {
                    o.limitPerFramework = Integer.parseInt(a.substring("--limit-per-framework=".length()).trim());
                } else if (a.equals("--list")) {
                    o.listOnly = true;
                }
            }
            return o;
        }
    }
}
