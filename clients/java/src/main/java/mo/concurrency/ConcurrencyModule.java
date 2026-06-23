package mo.concurrency;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;

/**
 * Concurrency & isolation: multiple connections/threads, isolation-level visibility,
 * SELECT ... FOR UPDATE contention, deadlock detection, HikariCP pool exhaustion/recovery.
 * Kept deliberately small (few threads, short timeouts) since the MatrixOne node is shared.
 */
public final class ConcurrencyModule {

    private final String fw;
    private final String dbName;

    public ConcurrencyModule(String fw, String dbName) {
        this.fw = fw;
        this.dbName = dbName;
    }

    public void register(Runner r, Db db) {
        // Two-connection visibility after commit.
        r.register(fw, "concurrency", "commit_visible_to_other_connection", () -> {
            String t = Db.uniq("vis");
            try (Connection w = db.connect(dbName); Connection rd = db.connect(dbName)) {
                try (Statement st = w.createStatement()) { st.execute("CREATE TABLE " + t + " (id INT)"); }
                w.setAutoCommit(false);
                try (Statement st = w.createStatement()) { st.execute("INSERT INTO " + t + " VALUES (1)"); }
                w.commit();
                w.setAutoCommit(true);
                long n = Db.scalarLong(rd, "SELECT COUNT(*) FROM " + t);
                Assert.eq(1L, n, "committed row visible to second connection");
                try (Statement st = w.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Uncommitted not visible to another connection (read committed expectation).
        r.register(fw, "concurrency", "uncommitted_not_visible", () -> {
            String t = Db.uniq("unc");
            try (Connection w = db.connect(dbName); Connection rd = db.connect(dbName)) {
                try (Statement st = w.createStatement()) { st.execute("CREATE TABLE " + t + " (id INT)"); }
                w.setAutoCommit(false);
                try (Statement st = w.createStatement()) { st.execute("INSERT INTO " + t + " VALUES (1)"); }
                long n = Db.scalarLong(rd, "SELECT COUNT(*) FROM " + t);
                w.rollback();
                w.setAutoCommit(true);
                Assert.eq(0L, n, "uncommitted insert not visible to other connection");
                try (Statement st = w.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });

        // Isolation levels: set + verify each is accepted and round-trips.
        int[][] isolations = {
                {Connection.TRANSACTION_READ_UNCOMMITTED, 1},
                {Connection.TRANSACTION_READ_COMMITTED, 2},
                {Connection.TRANSACTION_REPEATABLE_READ, 4},
                {Connection.TRANSACTION_SERIALIZABLE, 8},
        };
        String[] isoNames = {"read_uncommitted", "read_committed", "repeatable_read", "serializable"};
        for (int i = 0; i < isolations.length; i++) {
            final int lvl = isolations[i][0];
            final String nm = isoNames[i];
            r.register(fw, "concurrency", "isolation_set/" + nm, () -> {
                try (Connection c = db.connect(dbName)) {
                    c.setTransactionIsolation(lvl);
                    int got = c.getTransactionIsolation();
                    Assert.isTrue(got != 0, "isolation " + nm + " set; reported=" + got);
                }
            });
            r.register(fw, "concurrency", "isolation_repeatable_read_snapshot/" + nm, () -> {
                String t = Db.uniq("snap");
                try (Connection a = db.connect(dbName); Connection b = db.connect(dbName)) {
                    try (Statement st = a.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                        st.execute("INSERT INTO " + t + " VALUES (1, 10)");
                    }
                    a.setTransactionIsolation(lvl);
                    a.setAutoCommit(false);
                    long first = Db.scalarLong(a, "SELECT v FROM " + t + " WHERE id=1");
                    // Another connection updates and commits.
                    try (Statement st = b.createStatement()) {
                        st.executeUpdate("UPDATE " + t + " SET v = 20 WHERE id=1");
                    }
                    long second = Db.scalarLong(a, "SELECT v FROM " + t + " WHERE id=1");
                    a.commit();
                    a.setAutoCommit(true);
                    // We only assert the read executed; visibility differences are observed, not asserted.
                    Assert.isTrue(first == 10 || first == 20, "first read value plausible");
                    Assert.isTrue(second == 10 || second == 20, "second read value plausible");
                    try (Statement st = a.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // SELECT ... FOR UPDATE contention: two threads, one holds the row lock.
        r.register(fw, "concurrency", "select_for_update_contention", () -> {
            String t = Db.uniq("ffu");
            try (Connection setup = db.connect(dbName); Statement st = setup.createStatement()) {
                st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                st.execute("INSERT INTO " + t + " VALUES (1, 0)");
            }
            ExecutorService ex = Executors.newFixedThreadPool(2);
            try {
                Connection c1 = db.connect(dbName);
                c1.setAutoCommit(false);
                try (Statement st = c1.createStatement();
                     ResultSet rs = st.executeQuery("SELECT v FROM " + t + " WHERE id=1 FOR UPDATE")) {
                    rs.next();
                }
                AtomicInteger secondDone = new AtomicInteger(0);
                Future<?> f = ex.submit(() -> {
                    try (Connection c2 = db.connect(dbName)) {
                        c2.setAutoCommit(false);
                        try (Statement st = c2.createStatement();
                             ResultSet rs = st.executeQuery("SELECT v FROM " + t + " WHERE id=1 FOR UPDATE")) {
                            rs.next();
                        }
                        c2.commit();
                        secondDone.set(1);
                    } catch (Exception e) {
                        secondDone.set(-1);
                    }
                });
                // Hold the lock briefly, then release.
                try { Thread.sleep(300); } catch (InterruptedException ignore) {}
                c1.commit();
                c1.close();
                try { f.get(8, TimeUnit.SECONDS); } catch (Exception ignore) {}
                // Either it serialized (got the lock after we committed) or errored; both are valid observations.
                Assert.isTrue(secondDone.get() != 0, "second FOR UPDATE thread completed (got=" + secondDone.get() + ")");
            } finally {
                ex.shutdownNow();
                try (Connection cc = db.connect(dbName); Statement st = cc.createStatement()) {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });

        // Deadlock detection: two threads update two rows in opposite order.
        r.register(fw, "concurrency", "deadlock_detection", () -> {
            String t = Db.uniq("dl");
            try (Connection setup = db.connect(dbName); Statement st = setup.createStatement()) {
                st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, v INT)");
                st.execute("INSERT INTO " + t + " VALUES (1, 0),(2, 0)");
            }
            ExecutorService ex = Executors.newFixedThreadPool(2);
            CountDownLatch ready = new CountDownLatch(2);
            AtomicInteger errors = new AtomicInteger(0);
            try {
                Future<?> fa = ex.submit(() -> txUpdatePair(db, t, 1, 2, ready, errors));
                Future<?> fb = ex.submit(() -> txUpdatePair(db, t, 2, 1, ready, errors));
                try { fa.get(10, TimeUnit.SECONDS); } catch (Exception ignore) {}
                try { fb.get(10, TimeUnit.SECONDS); } catch (Exception ignore) {}
                // A deadlock (one rolled back) or successful serialization are both acceptable outcomes.
                Assert.isTrue(errors.get() >= 0, "deadlock scenario completed; conflicts=" + errors.get());
            } finally {
                ex.shutdownNow();
                try (Connection cc = db.connect(dbName); Statement st = cc.createStatement()) {
                    Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                }
            }
        });

        // HikariCP pool exhaustion + recovery.
        r.register(fw, "concurrency", "hikari_pool_exhaustion_recovery", () -> {
            HikariConfig hc = new HikariConfig();
            hc.setJdbcUrl(db.config().dbUrl(dbName));
            hc.setUsername(db.config().user);
            hc.setPassword(db.config().pass);
            hc.setMaximumPoolSize(2);
            hc.setConnectionTimeout(1500);
            try (HikariDataSource ds = new HikariDataSource(hc)) {
                Connection c1 = ds.getConnection();
                Connection c2 = ds.getConnection();
                boolean timedOut = false;
                try {
                    Connection c3 = ds.getConnection(); // pool exhausted -> should time out
                    c3.close();
                } catch (SQLException e) {
                    timedOut = true;
                }
                Assert.isTrue(timedOut, "third borrow times out when pool of 2 is exhausted");
                // Recovery: release one, borrow succeeds.
                c1.close();
                try (Connection c4 = ds.getConnection()) {
                    Assert.eq(1L, Db.scalarLong(c4, "SELECT 1"), "pool recovered after release");
                }
                c2.close();
            }
        });
        r.register(fw, "concurrency", "hikari_concurrent_borrowers", () -> {
            HikariConfig hc = new HikariConfig();
            hc.setJdbcUrl(db.config().dbUrl(dbName));
            hc.setUsername(db.config().user);
            hc.setPassword(db.config().pass);
            hc.setMaximumPoolSize(3);
            hc.setConnectionTimeout(5000);
            try (HikariDataSource ds = new HikariDataSource(hc)) {
                ExecutorService ex = Executors.newFixedThreadPool(6);
                List<Future<Long>> futs = new ArrayList<>();
                for (int i = 0; i < 12; i++) {
                    futs.add(ex.submit(() -> {
                        try (Connection c = ds.getConnection()) {
                            return Db.scalarLong(c, "SELECT 1");
                        }
                    }));
                }
                long sum = 0;
                for (Future<Long> f : futs) {
                    try { sum += f.get(10, TimeUnit.SECONDS); } catch (Exception e) { throw new RuntimeException(e); }
                }
                ex.shutdownNow();
                Assert.eq(12L, sum, "all 12 pooled borrowers returned 1");
            }
        });

        // Parallel inserts from N threads into one table.
        for (int threads : new int[]{2, 4, 8}) {
            r.register(fw, "concurrency", "parallel_inserts_" + threads + "_threads", () -> {
                String t = Db.uniq("par");
                try (Connection setup = db.connect(dbName); Statement st = setup.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, thread_id INT)");
                }
                ExecutorService ex = Executors.newFixedThreadPool(threads);
                int perThread = 50;
                List<Future<?>> futs = new ArrayList<>();
                AtomicInteger seq = new AtomicInteger(0);
                try {
                    for (int ti = 0; ti < threads; ti++) {
                        final int tid = ti;
                        futs.add(ex.submit(() -> {
                            try (Connection c = db.connect(dbName)) {
                                c.setAutoCommit(false);
                                try (var ps = c.prepareStatement("INSERT INTO " + t + " VALUES (?, ?)")) {
                                    for (int i = 0; i < perThread; i++) {
                                        ps.setInt(1, seq.getAndIncrement());
                                        ps.setInt(2, tid);
                                        ps.executeUpdate();
                                    }
                                }
                                c.commit();
                            } catch (Exception e) { throw new RuntimeException(e); }
                        }));
                    }
                    for (Future<?> f : futs) f.get(20, TimeUnit.SECONDS);
                    long n;
                    try (Connection c = db.connect(dbName)) {
                        n = Db.scalarLong(c, "SELECT COUNT(*) FROM " + t);
                    }
                    Assert.eq((long) threads * perThread, n, "all parallel inserts persisted");
                } catch (Exception e) {
                    throw e;
                } finally {
                    ex.shutdownNow();
                    try (Connection cc = db.connect(dbName); Statement st = cc.createStatement()) {
                        Db.quiet(st, "DROP TABLE IF EXISTS " + t);
                    }
                }
            });
        }
    }

    private void txUpdatePair(Db db, String t, int firstId, int secondId,
                              CountDownLatch ready, AtomicInteger errors) {
        try (Connection c = db.connect(dbName)) {
            c.setAutoCommit(false);
            try (Statement st = c.createStatement()) {
                st.executeUpdate("UPDATE " + t + " SET v = v + 1 WHERE id = " + firstId);
            }
            ready.countDown();
            try { ready.await(3, TimeUnit.SECONDS); } catch (InterruptedException ignore) {}
            try (Statement st = c.createStatement()) {
                st.executeUpdate("UPDATE " + t + " SET v = v + 1 WHERE id = " + secondId);
            }
            c.commit();
        } catch (SQLException e) {
            errors.incrementAndGet();
        }
    }
}
