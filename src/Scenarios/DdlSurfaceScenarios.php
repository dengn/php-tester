<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * DDL surface + error-handling sweeps for MatrixOne over raw PDO.
 *
 * Part A ("DDL surface as findings") sweeps matrices of DDL features — views,
 * stored procedures, triggers, events, partitioning, foreign-key actions, CHECK
 * constraints, generated columns and indexes — and records whether MatrixOne
 * supports each one. A scenario PASSES when the feature behaves as the spec
 * predicts and FAILS (as a genuine finding) when it surprises us; for the
 * features that are simply not implemented we let the engine error surface
 * naturally and assert that it does.
 *
 * Part B ("error handling") triggers a known error condition and asserts on the
 * ERROR SURFACE an application actually sees: the PDOException is caught INSIDE
 * the scenario, and we assert on $e->getCode() (SQLSTATE) and $e->errorInfo
 * (driver SQLSTATE + MySQL errno + message). MatrixOne surfaces a generic
 * HY000 SQLSTATE for almost everything (never the canonical 23000/22003/22007)
 * but does carry a MySQL-style errno in errorInfo[1]; both facts are recorded.
 *
 * Conventions (mirroring TypeMatrixScenarios):
 *   - one fresh PDO connection per scenario via Connections::pdo($db),
 *   - unique table/view names via Support::name(), dropped in finally,
 *   - DDL/DML via exec() (text protocol), reads via query(),
 *   - every closure captures all referenced variables in use(...),
 *   - lock-contention scenarios use ONLY GET_LOCK(name, timeout) which is
 *     provably bounded; row-level lock waits are deliberately avoided because
 *     MatrixOne ignores lock_wait_timeout for them and would hang the suite.
 */
final class DdlSurfaceScenarios
{
    private const FW = 'PDO';

    public static function register(Runner $r): void
    {
        // Part A — DDL surface.
        self::views($r);
        self::procedures($r);
        self::triggers($r);
        self::events($r);
        self::partitioning($r);
        self::foreignKeys($r);
        self::checks($r);
        self::generated($r);
        self::indexes($r);

        // Part B — error handling.
        self::duplicateKey($r);
        self::constraintViolation($r);
        self::typeRangeErrors($r);
        self::deadlockAndLock($r);
        self::statementTimeout($r);
        self::reconnect($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    // =====================================================================
    // Small shared helpers
    // =====================================================================

    /**
     * Run a list of DDL/DML statements (exec) that are EXPECTED to all succeed,
     * then optionally assert a scalar read. Any engine error fails the scenario
     * as a genuine finding. $temps are dropped (table or view) in finally.
     *
     * @param list<string>     $setup
     * @param array{0:string,1:mixed}|null $check  [select sql, expected value] or null
     * @param list<array{0:string,1:bool}> $drops  [name, isView] pairs to drop
     */
    private static function addWorks(
        Runner $r,
        string $cat,
        string $name,
        array $setup,
        ?array $check,
        array $drops,
        string $sqlNote
    ): void {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $setup, $check, $drops, $sqlNote, $name) {
            $pdo = Connections::pdo($db);
            try {
                foreach ($setup as $sql) {
                    $pdo->exec($sql);
                }
                $detail = 'ddl accepted';
                if ($check !== null) {
                    [$sel, $expected] = $check;
                    $got = $pdo->query($sel)->fetchColumn();
                    Support::assertValueEquals($expected, $got, $name);
                    $detail = '= ' . var_export($got, true);
                }
                return ['detail' => $detail, 'sql' => $sqlNote];
            } finally {
                self::dropAll($pdo, $drops);
            }
        });
    }

    /**
     * Run setup statements (all expected to succeed), then run $bad which is
     * EXPECTED to raise an engine error. Passes when $bad throws (recording the
     * captured SQLSTATE/errno); FAILS as a finding when $bad is accepted.
     *
     * @param list<string> $setup
     * @param list<array{0:string,1:bool}> $drops
     */
    private static function addExpectError(
        Runner $r,
        string $cat,
        string $name,
        array $setup,
        string $bad,
        array $drops,
        string $sqlNote
    ): void {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $setup, $bad, $drops, $sqlNote, $name) {
            $pdo = Connections::pdo($db);
            try {
                foreach ($setup as $sql) {
                    $pdo->exec($sql);
                }
                $threw = false;
                $info = '';
                try {
                    $pdo->exec($bad);
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, "$name: statement was accepted but an error was expected");
                return ['detail' => "errored as expected: $info", 'sql' => $sqlNote];
            } finally {
                self::dropAll($pdo, $drops);
            }
        });
    }

    /** Format a PDOException's surfaced error info compactly. */
    private static function errInfo(\PDOException $e): string
    {
        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
        $errno = $e->errorInfo[1] ?? '?';
        return "SQLSTATE=$sqlstate errno=$errno";
    }

    /** @param list<array{0:string,1:bool}> $drops */
    private static function dropAll(\PDO $pdo, array $drops): void
    {
        foreach ($drops as [$obj, $isView]) {
            try {
                $pdo->exec($isView ? "DROP VIEW IF EXISTS `$obj`" : "DROP TABLE IF EXISTS `$obj`");
            } catch (\Throwable) {
            }
        }
    }

    // =====================================================================
    // Part A.1 — VIEWS (~120)
    // =====================================================================

    private static function views(Runner $r): void
    {
        $cat = 'ddlsurf:view';
        $db = self::db();

        // Base-table shapes to multiply the view matrix over.
        $shapes = [
            'int' => 'id INT PRIMARY KEY, n INT, cat VARCHAR(10)',
            'mixed' => 'id INT PRIMARY KEY, n INT, cat VARCHAR(10), d DATE',
            'decimal' => 'id INT PRIMARY KEY, n DECIMAL(10,2), cat VARCHAR(10)',
            'nullable' => 'id INT PRIMARY KEY, n INT NULL, cat VARCHAR(10) NULL',
        ];
        $seeds = [
            'int' => "(1,10,'x'),(2,20,'x'),(3,30,'y')",
            'mixed' => "(1,10,'x','2026-01-01'),(2,20,'x','2026-02-01'),(3,30,'y','2026-03-01')",
            'decimal' => "(1,10.50,'x'),(2,20.25,'x'),(3,30.00,'y')",
            'nullable' => "(1,10,'x'),(2,NULL,'x'),(3,30,NULL)",
        ];

        foreach ($shapes as $shape => $cols) {
            $seed = $seeds[$shape];

            // CREATE VIEW over single table + query it.
            $r->add(self::FW, $cat, "single-table view ($shape) + query", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n, cat FROM `$bt`");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                    Support::assertEquals('3', $n, "view count ($shape)");
                    return ['detail' => "rows=$n", 'sql' => "CREATE VIEW over $shape table"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // View with WHERE filter.
            $r->add(self::FW, $cat, "view with WHERE ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt` WHERE n > 15");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                    return ['detail' => "filtered rows=$n", 'sql' => "view WHERE n>15 ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // Aggregate view.
            $r->add(self::FW, $cat, "aggregate view ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT cat, SUM(n) total, COUNT(*) c FROM `$bt` GROUP BY cat");
                    $rows = $pdo->query("SELECT cat, total FROM `$vw` ORDER BY cat")->fetchAll(\PDO::FETCH_KEY_PAIR);
                    Support::assert(count($rows) >= 1, "aggregate view rows ($shape)");
                    return ['detail' => json_encode($rows), 'sql' => "view GROUP BY ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // Join view across two base tables.
            $r->add(self::FW, $cat, "join view ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $bt2 = Support::name('vbu');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE TABLE `$bt2` (id INT PRIMARY KEY, label VARCHAR(10))");
                    $pdo->exec("INSERT INTO `$bt2` VALUES (1,'one'),(2,'two')");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT a.id, b.label FROM `$bt` a JOIN `$bt2` b ON a.id=b.id");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                    Support::assertEquals('2', $n, "join view count ($shape)");
                    return ['detail' => "joined rows=$n", 'sql' => "view over JOIN ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt2, false], [$bt, false]]);
                }
            });

            // CREATE OR REPLACE VIEW.
            $r->add(self::FW, $cat, "CREATE OR REPLACE VIEW ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id FROM `$bt`");
                    $pdo->exec("CREATE OR REPLACE VIEW `$vw` AS SELECT id, n FROM `$bt`");
                    $cols2 = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name='$vw'")->fetchColumn();
                    Support::assertEquals('2', $cols2, "replaced view column count ($shape)");
                    return ['detail' => "view now has $cols2 cols", 'sql' => "CREATE OR REPLACE VIEW ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // Nested view (view over view).
            $r->add(self::FW, $cat, "nested view ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $v1 = Support::name('vv');
                $v2 = Support::name('vw');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$v1` AS SELECT id, n FROM `$bt` WHERE n > 5");
                    $pdo->exec("CREATE VIEW `$v2` AS SELECT id FROM `$v1` WHERE id < 3");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$v2`")->fetchColumn();
                    return ['detail' => "nested rows=$n", 'sql' => "view over view ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$v2, true], [$v1, true], [$bt, false]]);
                }
            });

            // View over a CTE.
            $r->add(self::FW, $cat, "view over CTE ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS WITH t AS (SELECT id, n FROM `$bt`) SELECT id FROM t WHERE n IS NOT NULL");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                    return ['detail' => "cte view rows=$n", 'sql' => "CREATE VIEW ... WITH cte ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // ALTER VIEW.
            $r->add(self::FW, $cat, "ALTER VIEW ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id FROM `$bt`");
                    $pdo->exec("ALTER VIEW `$vw` AS SELECT id, n, cat FROM `$bt`");
                    $c = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name='$vw'")->fetchColumn();
                    Support::assertEquals('3', $c, "altered view cols ($shape)");
                    return ['detail' => "altered to $c cols", 'sql' => "ALTER VIEW ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // DROP VIEW removes it from information_schema.views.
            $r->add(self::FW, $cat, "DROP VIEW + catalog ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id FROM `$bt`");
                    $pdo->exec("DROP VIEW `$vw`");
                    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.views WHERE table_name='$vw'")->fetchColumn();
                    Support::assertEquals('0', $n, "view gone after drop ($shape)");
                    return ['detail' => 'view dropped + absent from catalog', 'sql' => "DROP VIEW ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // information_schema.views exposes the definition.
            $r->add(self::FW, $cat, "information_schema.views ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id FROM `$bt`");
                    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.views WHERE table_name='$vw'")->fetchColumn();
                    Support::assertEquals('1', $n, "view present in catalog ($shape)");
                    return ['detail' => "catalog rows=$n", 'sql' => "information_schema.views ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // Updatable view: INSERT through view (KNOWN finding — rejected 20301).
            $r->add(self::FW, $cat, "INSERT through view rejected ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt`");
                    $threw = false;
                    $info = '';
                    try {
                        $pdo->exec("INSERT INTO `$vw` (id, n) VALUES (99, 1)");
                    } catch (\PDOException $e) {
                        $threw = true;
                        $info = self::errInfo($e);
                    }
                    Support::assert($threw, "$shape: INSERT through view was accepted (MatrixOne views are not updatable)");
                    return ['detail' => "INSERT-through-view rejected: $info", 'sql' => "INSERT INTO view ($shape) — expect reject"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // Updatable view: UPDATE through view (KNOWN finding — rejected).
            $r->add(self::FW, $cat, "UPDATE through view rejected ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt`");
                    $threw = false;
                    $info = '';
                    try {
                        $pdo->exec("UPDATE `$vw` SET n = n + 1 WHERE id = 1");
                    } catch (\PDOException $e) {
                        $threw = true;
                        $info = self::errInfo($e);
                    }
                    Support::assert($threw, "$shape: UPDATE through view was accepted (MatrixOne views are not updatable)");
                    return ['detail' => "UPDATE-through-view rejected: $info", 'sql' => "UPDATE view ($shape) — expect reject"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // DELETE through view (likewise rejected).
            $r->add(self::FW, $cat, "DELETE through view rejected ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt`");
                    $threw = false;
                    $info = '';
                    try {
                        $pdo->exec("DELETE FROM `$vw` WHERE id = 1");
                    } catch (\PDOException $e) {
                        $threw = true;
                        $info = self::errInfo($e);
                    }
                    Support::assert($threw, "$shape: DELETE through view was accepted (MatrixOne views are not updatable)");
                    return ['detail' => "DELETE-through-view rejected: $info", 'sql' => "DELETE view ($shape) — expect reject"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });

            // WITH CHECK OPTION accepted at DDL time.
            $r->add(self::FW, $cat, "view WITH CHECK OPTION accepted ($shape)", function () use ($db, $cols, $seed, $shape) {
                $pdo = Connections::pdo($db);
                $bt = Support::name('vbt');
                $vw = Support::name('vv');
                try {
                    $pdo->exec("CREATE TABLE `$bt` ($cols)");
                    $pdo->exec("INSERT INTO `$bt` VALUES $seed");
                    $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt` WHERE n > 0 WITH CHECK OPTION");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                    return ['detail' => "WITH CHECK OPTION view rows=$n", 'sql' => "CREATE VIEW ... WITH CHECK OPTION ($shape)"];
                } finally {
                    self::dropAll($pdo, [[$vw, true], [$bt, false]]);
                }
            });
        }

        // A few view scenarios that don't need the shape multiply.
        $r->add(self::FW, $cat, 'view with computed columns', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('vbt');
            $vw = Support::name('vv');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT, a INT, b INT)");
                $pdo->exec("INSERT INTO `$bt` VALUES (1,3,4),(2,5,6)");
                $pdo->exec("CREATE VIEW `$vw` AS SELECT id, a + b AS s, a * b AS p FROM `$bt`");
                $s = $pdo->query("SELECT s FROM `$vw` WHERE id=1")->fetchColumn();
                Support::assertEquals('7', $s, 'computed view column');
                return ['detail' => "a+b=$s", 'sql' => 'view with a+b, a*b'];
            } finally {
                self::dropAll($pdo, [[$vw, true], [$bt, false]]);
            }
        });

        $r->add(self::FW, $cat, 'view with UNION', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('vbt');
            $vw = Support::name('vv');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT, n INT)");
                $pdo->exec("INSERT INTO `$bt` VALUES (1,10),(2,20)");
                $pdo->exec("CREATE VIEW `$vw` AS SELECT id FROM `$bt` WHERE n<15 UNION SELECT id FROM `$bt` WHERE n>15");
                $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                return ['detail' => "union view rows=$n", 'sql' => 'view over UNION'];
            } finally {
                self::dropAll($pdo, [[$vw, true], [$bt, false]]);
            }
        });

        $r->add(self::FW, $cat, 'view ORDER BY + LIMIT', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('vbt');
            $vw = Support::name('vv');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT, n INT)");
                $pdo->exec("INSERT INTO `$bt` VALUES (1,30),(2,10),(3,20)");
                $pdo->exec("CREATE VIEW `$vw` AS SELECT id, n FROM `$bt` ORDER BY n DESC LIMIT 2");
                $first = $pdo->query("SELECT id FROM `$vw` LIMIT 1")->fetchColumn();
                return ['detail' => "top id=$first", 'sql' => 'view ORDER BY n DESC LIMIT 2'];
            } finally {
                self::dropAll($pdo, [[$vw, true], [$bt, false]]);
            }
        });

        $r->add(self::FW, $cat, 'view over subquery in FROM', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('vbt');
            $vw = Support::name('vv');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT, n INT)");
                $pdo->exec("INSERT INTO `$bt` VALUES (1,10),(2,20),(3,30)");
                $pdo->exec("CREATE VIEW `$vw` AS SELECT t.id FROM (SELECT id, n FROM `$bt` WHERE n>=20) t");
                $n = $pdo->query("SELECT COUNT(*) FROM `$vw`")->fetchColumn();
                return ['detail' => "derived-table view rows=$n", 'sql' => 'view over (SELECT ...) derived table'];
            } finally {
                self::dropAll($pdo, [[$vw, true], [$bt, false]]);
            }
        });

        $r->add(self::FW, $cat, 'view with window function', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('vbt');
            $vw = Support::name('vv');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT, n INT)");
                $pdo->exec("INSERT INTO `$bt` VALUES (1,10),(2,20),(3,30)");
                $pdo->exec("CREATE VIEW `$vw` AS SELECT id, ROW_NUMBER() OVER (ORDER BY n DESC) rn FROM `$bt`");
                $top = $pdo->query("SELECT id FROM `$vw` WHERE rn=1")->fetchColumn();
                Support::assertEquals('3', $top, 'window view rn=1');
                return ['detail' => "rn=1 id=$top", 'sql' => 'view with ROW_NUMBER() window'];
            } finally {
                self::dropAll($pdo, [[$vw, true], [$bt, false]]);
            }
        });

        // DROP VIEW IF EXISTS on a missing view is a no-op.
        $r->add(self::FW, $cat, 'DROP VIEW IF EXISTS missing is no-op', function () use ($db) {
            $pdo = Connections::pdo($db);
            $vw = Support::name('vv');
            $pdo->exec("DROP VIEW IF EXISTS `$vw`");
            return ['detail' => 'no error on missing view', 'sql' => 'DROP VIEW IF EXISTS <missing>'];
        });
    }

    // =====================================================================
    // Part A.2 — STORED PROCEDURES / FUNCTIONS (~80)
    // =====================================================================

    private static function procedures(Runner $r): void
    {
        $cat = 'ddlsurf:proc';

        // Every CREATE PROCEDURE/FUNCTION variant is a KNOWN parser gap (1064).
        // We assert the engine rejects it; passing here documents the gap.
        $procBodies = [
            'BEGIN..END no params' => 'BEGIN SELECT 1; END',
            'single SELECT body' => 'SELECT 1',
            'BEGIN..END two statements' => 'BEGIN SELECT 1; SELECT 2; END',
            'DECLARE local var' => 'BEGIN DECLARE i INT DEFAULT 0; SET i = i + 1; END',
            'IF block' => 'BEGIN IF 1=1 THEN SELECT 1; END IF; END',
            'WHILE loop' => 'BEGIN DECLARE i INT DEFAULT 0; WHILE i < 3 DO SET i = i + 1; END WHILE; END',
            'LOOP with LEAVE' => 'BEGIN DECLARE i INT DEFAULT 0; lbl: LOOP SET i = i + 1; IF i > 2 THEN LEAVE lbl; END IF; END LOOP; END',
            'REPEAT until' => 'BEGIN DECLARE i INT DEFAULT 0; REPEAT SET i = i + 1; UNTIL i > 2 END REPEAT; END',
            'CASE statement' => 'BEGIN CASE 1 WHEN 1 THEN SELECT 1; ELSE SELECT 2; END CASE; END',
            'cursor declaration' => 'BEGIN DECLARE c CURSOR FOR SELECT 1; END',
            'handler declaration' => 'BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @x = 1; SELECT 1; END',
            'INSERT inside body' => 'BEGIN INSERT INTO nonexist VALUES (1); END',
        ];
        foreach ($procBodies as $label => $body) {
            self::addExpectError(
                $r,
                $cat,
                "CREATE PROCEDURE: $label rejected",
                [],
                "CREATE PROCEDURE `" . self::procName() . "`() $body",
                [],
                "CREATE PROCEDURE () $label — expect 1064"
            );
        }

        // Parameter-mode variants — all rejected (parser gap).
        $paramSigs = [
            'IN param' => '(IN x INT)',
            'OUT param' => '(OUT x INT)',
            'INOUT param' => '(INOUT x INT)',
            'multiple params' => '(IN a INT, IN b VARCHAR(10), OUT c INT)',
            'typed varchar param' => '(IN name VARCHAR(50))',
            'decimal param' => '(IN amt DECIMAL(10,2))',
            'date param' => '(IN d DATE, OUT res DATE)',
        ];
        foreach ($paramSigs as $label => $sig) {
            self::addExpectError(
                $r,
                $cat,
                "CREATE PROCEDURE with $label rejected",
                [],
                "CREATE PROCEDURE `" . self::procName() . "`$sig BEGIN SELECT x; END",
                [],
                "CREATE PROCEDURE $sig — expect 1064"
            );
        }

        // Stored FUNCTION variants — rejected.
        $funcs = [
            'scalar RETURN' => '() RETURNS INT DETERMINISTIC RETURN 1',
            'param + body' => '(x INT) RETURNS INT DETERMINISTIC RETURN x * 2',
            'BEGIN body' => '() RETURNS INT DETERMINISTIC BEGIN RETURN 42; END',
            'string function' => '(s VARCHAR(10)) RETURNS VARCHAR(20) DETERMINISTIC RETURN CONCAT(s, s)',
            'reads SQL' => '() RETURNS INT READS SQL DATA RETURN (SELECT 1)',
        ];
        foreach ($funcs as $label => $sig) {
            self::addExpectError(
                $r,
                $cat,
                "CREATE FUNCTION: $label rejected",
                [],
                "CREATE FUNCTION `" . self::procName() . "`$sig",
                [],
                "CREATE FUNCTION $label — expect 1064"
            );
        }

        // CALL of a non-existent procedure surfaces an error.
        self::addExpectError(
            $r,
            $cat,
            'CALL non-existent procedure errors',
            [],
            'CALL ' . self::procName() . '(1)',
            [],
            'CALL <missing>() — expect error'
        );

        // DELIMITER is a client-only directive; the server rejects it.
        self::addExpectError(
            $r,
            $cat,
            'DELIMITER directive rejected by server',
            [],
            'DELIMITER //',
            [],
            'DELIMITER // — expect parser error'
        );

        $db = self::db();

        // DROP PROCEDURE IF EXISTS is tolerated (no proc exists).
        $r->add(self::FW, $cat, 'DROP PROCEDURE IF EXISTS missing tolerated', function () use ($db) {
            $pdo = Connections::pdo($db);
            $threw = false;
            $info = '';
            try {
                $pdo->exec('DROP PROCEDURE IF EXISTS ' . DdlSurfaceScenarios::procNamePublic());
            } catch (\PDOException $e) {
                $threw = true;
                $info = self::errInfo($e);
            }
            return ['detail' => $threw ? "rejected: $info" : 'accepted (no-op)', 'sql' => 'DROP PROCEDURE IF EXISTS <missing>'];
        });

        // information_schema.routines is empty (no stored routines supported).
        $r->add(self::FW, $cat, 'information_schema.routines reports none', function () use ($db) {
            $pdo = Connections::pdo($db);
            $n = $pdo->query("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE()")->fetchColumn();
            Support::assertEquals('0', $n, 'routines count in this schema');
            return ['detail' => "routines=$n", 'sql' => 'SELECT COUNT(*) FROM information_schema.routines'];
        });

        // SHOW PROCEDURE STATUS returns no rows.
        $r->add(self::FW, $cat, 'SHOW PROCEDURE STATUS returns none', function () use ($db) {
            $pdo = Connections::pdo($db);
            $rows = $pdo->query('SHOW PROCEDURE STATUS')->fetchAll();
            Support::assert(count($rows) === 0, 'SHOW PROCEDURE STATUS rows=' . count($rows));
            return ['detail' => 'no procedures listed', 'sql' => 'SHOW PROCEDURE STATUS'];
        });
    }

    private static function procName(): string
    {
        return Support::name('proc');
    }

    /** Public wrapper so closures can mint a proc name without a captured var. */
    public static function procNamePublic(): string
    {
        return Support::name('proc');
    }

    // =====================================================================
    // Part A.3 — TRIGGERS (~80)
    // =====================================================================

    private static function triggers(Runner $r): void
    {
        $cat = 'ddlsurf:trigger';
        $db = self::db();

        $timings = ['BEFORE', 'AFTER'];
        $events = ['INSERT', 'UPDATE', 'DELETE'];
        // Bodies referencing NEW/OLD — all KNOWN parser gaps (1064).
        $bodies = [
            'SET NEW.col' => fn (string $bt) => "SET NEW.n = NEW.n + 1",
            'simple SET local' => fn (string $bt) => "SET @audit = 1",
            'INSERT into audit' => fn (string $bt) => "INSERT INTO `$bt`_audit VALUES (1)",
            'BEGIN..END body' => fn (string $bt) => "BEGIN SET @x = 1; END",
        ];

        foreach ($timings as $timing) {
            foreach ($events as $event) {
                foreach ($bodies as $blabel => $bodyFn) {
                    // OLD makes no sense on INSERT, NEW none on DELETE, but the
                    // statements all fail at the parser before semantics anyway.
                    $r->add(self::FW, $cat, "CREATE TRIGGER $timing $event ($blabel) rejected", function () use ($db, $timing, $event, $bodyFn, $blabel) {
                        $pdo = Connections::pdo($db);
                        $bt = Support::name('trg');
                        try {
                            $pdo->exec("CREATE TABLE `$bt` (id INT PRIMARY KEY, n INT)");
                            $body = $bodyFn($bt);
                            $trg = Support::name('tg');
                            $threw = false;
                            $info = '';
                            try {
                                $pdo->exec("CREATE TRIGGER `$trg` $timing $event ON `$bt` FOR EACH ROW $body");
                            } catch (\PDOException $e) {
                                $threw = true;
                                $info = self::errInfo($e);
                            }
                            Support::assert($threw, "$timing $event $blabel: trigger was created (unexpected — MatrixOne lacks triggers)");
                            return ['detail' => "trigger rejected: $info", 'sql' => "CREATE TRIGGER $timing $event $blabel — expect 1064"];
                        } finally {
                            self::dropAll($pdo, [[$bt, false]]);
                        }
                    });
                }
            }
        }

        // Trigger referencing OLD explicitly.
        $r->add(self::FW, $cat, 'CREATE TRIGGER referencing OLD rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $bt = Support::name('trg');
            try {
                $pdo->exec("CREATE TABLE `$bt` (id INT PRIMARY KEY, n INT)");
                $trg = Support::name('tg');
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("CREATE TRIGGER `$trg` AFTER UPDATE ON `$bt` FOR EACH ROW SET @last = OLD.n");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'OLD-referencing trigger was created unexpectedly');
                return ['detail' => "rejected: $info", 'sql' => 'TRIGGER ... SET @last=OLD.n — expect 1064'];
            } finally {
                self::dropAll($pdo, [[$bt, false]]);
            }
        });

        // DROP TRIGGER IF EXISTS — also a parser gap in MatrixOne.
        $r->add(self::FW, $cat, 'DROP TRIGGER IF EXISTS behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            $threw = false;
            $info = '';
            try {
                $pdo->exec('DROP TRIGGER IF EXISTS ' . Support::name('tg'));
            } catch (\PDOException $e) {
                $threw = true;
                $info = self::errInfo($e);
            }
            return ['detail' => $threw ? "rejected: $info" : 'accepted (no-op)', 'sql' => 'DROP TRIGGER IF EXISTS <missing>'];
        });

        // information_schema.triggers is queryable and empty.
        $r->add(self::FW, $cat, 'information_schema.triggers empty', function () use ($db) {
            $pdo = Connections::pdo($db);
            $n = $pdo->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()")->fetchColumn();
            Support::assertEquals('0', $n, 'triggers count in this schema');
            return ['detail' => "triggers=$n", 'sql' => 'SELECT COUNT(*) FROM information_schema.triggers'];
        });

        // SHOW TRIGGERS returns no rows (or errors — record either).
        $r->add(self::FW, $cat, 'SHOW TRIGGERS behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $rows = $pdo->query('SHOW TRIGGERS')->fetchAll();
                return ['detail' => 'rows=' . count($rows), 'sql' => 'SHOW TRIGGERS'];
            } catch (\PDOException $e) {
                return ['detail' => 'rejected: ' . self::errInfo($e), 'sql' => 'SHOW TRIGGERS'];
            }
        });
    }

    // =====================================================================
    // Part A.4 — EVENTS (~40)
    // =====================================================================

    private static function events(Runner $r): void
    {
        $cat = 'ddlsurf:event';

        // CREATE EVENT variants — all KNOWN parser gaps (1064).
        $eventDefs = [
            'EVERY interval' => 'ON SCHEDULE EVERY 1 HOUR DO SELECT 1',
            'AT timestamp' => "ON SCHEDULE AT '2030-01-01 00:00:00' DO SELECT 1",
            'EVERY with STARTS' => "ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01 00:00:00' DO SELECT 1",
            'EVERY with ENDS' => "ON SCHEDULE EVERY 1 MINUTE ENDS '2030-01-01 00:00:00' DO SELECT 1",
            'ON COMPLETION PRESERVE' => 'ON SCHEDULE EVERY 1 HOUR ON COMPLETION PRESERVE DO SELECT 1',
            'DISABLE state' => 'ON SCHEDULE EVERY 1 HOUR DISABLE DO SELECT 1',
            'BEGIN..END body' => 'ON SCHEDULE EVERY 1 HOUR DO BEGIN SELECT 1; END',
            'EVERY 1 WEEK' => 'ON SCHEDULE EVERY 1 WEEK DO SELECT 1',
            'EVERY 30 SECOND' => 'ON SCHEDULE EVERY 30 SECOND DO SELECT 1',
            'IF NOT EXISTS' => 'ON SCHEDULE EVERY 1 HOUR DO SELECT 1',
        ];
        foreach ($eventDefs as $label => $def) {
            $ine = $label === 'IF NOT EXISTS' ? 'IF NOT EXISTS ' : '';
            self::addExpectError(
                $r,
                $cat,
                "CREATE EVENT: $label rejected",
                [],
                "CREATE EVENT $ine`" . Support::name('ev') . "` $def",
                [],
                "CREATE EVENT $label — expect 1064"
            );
        }

        // ALTER EVENT / DROP EVENT — parser gaps.
        self::addExpectError(
            $r,
            $cat,
            'ALTER EVENT rejected',
            [],
            'ALTER EVENT ' . Support::name('ev') . ' ON SCHEDULE EVERY 2 HOUR DO SELECT 1',
            [],
            'ALTER EVENT — expect 1064'
        );
        self::addExpectError(
            $r,
            $cat,
            'DROP EVENT IF EXISTS rejected',
            [],
            'DROP EVENT IF EXISTS ' . Support::name('ev'),
            [],
            'DROP EVENT IF EXISTS — expect 1064'
        );

        $db = self::db();

        // SET GLOBAL event_scheduler is accepted (the variable exists).
        $r->add(self::FW, $cat, 'SET GLOBAL event_scheduler accepted', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $pdo->exec('SET GLOBAL event_scheduler=ON');
                return ['detail' => 'event_scheduler set ON accepted', 'sql' => 'SET GLOBAL event_scheduler=ON'];
            } catch (\PDOException $e) {
                return ['detail' => 'rejected: ' . self::errInfo($e), 'sql' => 'SET GLOBAL event_scheduler=ON'];
            }
        });

        // @@event_scheduler readable?
        $r->add(self::FW, $cat, 'read @@event_scheduler', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $v = $pdo->query('SELECT @@event_scheduler')->fetchColumn();
                return ['detail' => "event_scheduler=$v", 'sql' => 'SELECT @@event_scheduler'];
            } catch (\PDOException $e) {
                return ['detail' => 'rejected: ' . self::errInfo($e), 'sql' => 'SELECT @@event_scheduler'];
            }
        });

        // information_schema.events queryable and empty.
        $r->add(self::FW, $cat, 'information_schema.events behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $n = $pdo->query("SELECT COUNT(*) FROM information_schema.events")->fetchColumn();
                return ['detail' => "events=$n", 'sql' => 'SELECT COUNT(*) FROM information_schema.events'];
            } catch (\PDOException $e) {
                return ['detail' => 'rejected: ' . self::errInfo($e), 'sql' => 'information_schema.events'];
            }
        });

        // SHOW EVENTS behaviour.
        $r->add(self::FW, $cat, 'SHOW EVENTS behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $rows = $pdo->query('SHOW EVENTS')->fetchAll();
                return ['detail' => 'rows=' . count($rows), 'sql' => 'SHOW EVENTS'];
            } catch (\PDOException $e) {
                return ['detail' => 'rejected: ' . self::errInfo($e), 'sql' => 'SHOW EVENTS'];
            }
        });
    }

    // =====================================================================
    // Part A.5 — PARTITIONING (~80)
    // =====================================================================

    private static function partitioning(Runner $r): void
    {
        $cat = 'ddlsurf:partition';
        $db = self::db();

        // PARTITION BY HASH/KEY with N partitions — supported per spec.
        foreach (['HASH', 'KEY'] as $kind) {
            foreach ([2, 3, 4, 6, 8, 12, 16] as $nparts) {
                $r->add(self::FW, $cat, "PARTITION BY $kind PARTITIONS $nparts", function () use ($db, $kind, $nparts) {
                    $pdo = Connections::pdo($db);
                    $tn = Support::name('pt');
                    try {
                        $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, v INT) PARTITION BY $kind(id) PARTITIONS $nparts");
                        $pdo->exec("INSERT INTO `$tn` VALUES (1,1),(2,2),(3,3),(4,4),(5,5)");
                        $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                        Support::assertEquals('5', $n, "$kind $nparts query count");
                        return ['detail' => "rows=$n across $nparts $kind partitions", 'sql' => "PARTITION BY $kind PARTITIONS $nparts"];
                    } finally {
                        self::dropAll($pdo, [[$tn, false]]);
                    }
                });
            }
        }

        // PARTITION BY RANGE with N partitions.
        foreach ([2, 3, 4, 5] as $nparts) {
            $r->add(self::FW, $cat, "PARTITION BY RANGE $nparts partitions", function () use ($db, $nparts) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('pt');
                try {
                    $defs = [];
                    for ($i = 1; $i <= $nparts; $i++) {
                        $bound = $i * 100;
                        $defs[] = "PARTITION p$i VALUES LESS THAN ($bound)";
                    }
                    $defs[] = "PARTITION pmax VALUES LESS THAN MAXVALUE";
                    $defSql = implode(', ', $defs);
                    $pdo->exec("CREATE TABLE `$tn` (id INT, v INT) PARTITION BY RANGE(id) ($defSql)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (1,1),(150,2),(999,3)");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals('3', $n, "RANGE $nparts query count");
                    return ['detail' => "rows=$n", 'sql' => "PARTITION BY RANGE ($nparts+MAXVALUE)"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // PARTITION BY LIST.
        foreach ([2, 3, 4] as $nparts) {
            $r->add(self::FW, $cat, "PARTITION BY LIST $nparts partitions", function () use ($db, $nparts) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('pt');
                try {
                    $defs = [];
                    $val = 1;
                    for ($i = 1; $i <= $nparts; $i++) {
                        $a = $val++;
                        $b = $val++;
                        $defs[] = "PARTITION p$i VALUES IN ($a, $b)";
                    }
                    $defSql = implode(', ', $defs);
                    $pdo->exec("CREATE TABLE `$tn` (id INT, v INT) PARTITION BY LIST(id) ($defSql)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (1,1),(2,2)");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals('2', $n, "LIST $nparts query count");
                    return ['detail' => "rows=$n", 'sql' => "PARTITION BY LIST $nparts partitions"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // RANGE COLUMNS / LIST COLUMNS variants.
        $r->add(self::FW, $cat, 'PARTITION BY RANGE COLUMNS', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT) PARTITION BY RANGE COLUMNS(a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))");
                $pdo->exec("INSERT INTO `$tn` VALUES (5,1),(15,2)");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                return ['detail' => "rows=$n", 'sql' => 'PARTITION BY RANGE COLUMNS(a)'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        $r->add(self::FW, $cat, 'PARTITION BY LIST COLUMNS', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b VARCHAR(5)) PARTITION BY LIST COLUMNS(b) (PARTITION p0 VALUES IN ('x'), PARTITION p1 VALUES IN ('y'))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1,'x'),(2,'y')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                return ['detail' => "rows=$n", 'sql' => 'PARTITION BY LIST COLUMNS(b)'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // RANGE on an expression (YEAR()).
        $r->add(self::FW, $cat, 'PARTITION BY RANGE(YEAR(d))', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT, d DATE) PARTITION BY RANGE(YEAR(d)) (PARTITION p0 VALUES LESS THAN (2025), PARTITION p1 VALUES LESS THAN (2030))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1,'2024-01-01'),(2,'2026-01-01')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                return ['detail' => "rows=$n", 'sql' => 'PARTITION BY RANGE(YEAR(d))'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Subpartitioning.
        $r->add(self::FW, $cat, 'subpartition BY HASH', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT, d DATE) PARTITION BY RANGE(YEAR(d)) SUBPARTITION BY HASH(id) SUBPARTITIONS 2 (PARTITION p0 VALUES LESS THAN (2025), PARTITION p1 VALUES LESS THAN (2030))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1,'2024-01-01'),(2,'2026-01-01')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                return ['detail' => "rows=$n", 'sql' => 'RANGE + SUBPARTITION BY HASH'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // information_schema.partitions introspection.
        $r->add(self::FW, $cat, 'information_schema.partitions lists partitions', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY) PARTITION BY HASH(id) PARTITIONS 4");
                $n = $pdo->query("SELECT COUNT(*) FROM information_schema.partitions WHERE table_name='$tn'")->fetchColumn();
                return ['detail' => "partition rows reported=$n", 'sql' => 'information_schema.partitions'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Partition pruning: query restricted to a partition still returns correct rows.
        $r->add(self::FW, $cat, 'partition pruning by predicate', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT, v INT) PARTITION BY RANGE(id) (PARTITION p0 VALUES LESS THAN (100), PARTITION p1 VALUES LESS THAN (200), PARTITION p2 VALUES LESS THAN MAXVALUE)");
                $pdo->exec("INSERT INTO `$tn` VALUES (5,1),(150,2),(250,3)");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE id BETWEEN 100 AND 199")->fetchColumn();
                Support::assertEquals('1', $n, 'pruned range count');
                return ['detail' => "ranged rows=$n", 'sql' => 'SELECT ... WHERE id BETWEEN 100 AND 199'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // EXPLAIN on a partitioned table (does it surface partition info?).
        $r->add(self::FW, $cat, 'EXPLAIN partitioned table', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY) PARTITION BY HASH(id) PARTITIONS 4");
                $rows = $pdo->query("EXPLAIN SELECT * FROM `$tn` WHERE id=1")->fetchAll();
                return ['detail' => 'explain rows=' . count($rows), 'sql' => 'EXPLAIN over HASH-partitioned table'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // DROP PARTITION (RANGE) — supported per probe.
        $r->add(self::FW, $cat, 'ALTER TABLE DROP PARTITION', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('pt');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT) PARTITION BY RANGE(id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))");
                $pdo->exec("ALTER TABLE `$tn` DROP PARTITION p1");
                $n = $pdo->query("SELECT COUNT(*) FROM information_schema.partitions WHERE table_name='$tn'")->fetchColumn();
                return ['detail' => "partitions after drop=$n", 'sql' => 'ALTER TABLE DROP PARTITION p1'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // ADD PARTITION — KNOWN gap (1064).
        self::addExpectError(
            $r,
            $cat,
            'ALTER TABLE ADD PARTITION rejected',
            ["CREATE TABLE `__addpart_probe` (id INT PRIMARY KEY) PARTITION BY HASH(id) PARTITIONS 2"],
            'ALTER TABLE `__addpart_probe` ADD PARTITION PARTITIONS 2',
            [['__addpart_probe', false]],
            'ALTER TABLE ADD PARTITION — expect 1064'
        );

        // REORGANIZE PARTITION — likely a gap.
        self::addExpectError(
            $r,
            $cat,
            'ALTER TABLE REORGANIZE PARTITION rejected',
            ["CREATE TABLE `__reorg_probe` (id INT) PARTITION BY RANGE(id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))"],
            'ALTER TABLE `__reorg_probe` REORGANIZE PARTITION p0,p1 INTO (PARTITION pall VALUES LESS THAN (20))',
            [['__reorg_probe', false]],
            'ALTER TABLE REORGANIZE PARTITION — expect error'
        );

        // COALESCE PARTITION on HASH.
        self::addExpectError(
            $r,
            $cat,
            'ALTER TABLE COALESCE PARTITION rejected',
            ["CREATE TABLE `__coal_probe` (id INT PRIMARY KEY) PARTITION BY HASH(id) PARTITIONS 4"],
            'ALTER TABLE `__coal_probe` COALESCE PARTITION 2',
            [['__coal_probe', false]],
            'ALTER TABLE COALESCE PARTITION — expect error'
        );

        // TRUNCATE PARTITION.
        self::addExpectError(
            $r,
            $cat,
            'ALTER TABLE TRUNCATE PARTITION rejected',
            ["CREATE TABLE `__trunc_probe` (id INT) PARTITION BY RANGE(id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))"],
            'ALTER TABLE `__trunc_probe` TRUNCATE PARTITION p0',
            [['__trunc_probe', false]],
            'ALTER TABLE TRUNCATE PARTITION — expect error'
        );
    }

    // =====================================================================
    // Part A.6 — FOREIGN-KEY ACTIONS (~80)
    // =====================================================================

    private static function foreignKeys(Runner $r): void
    {
        $cat = 'ddlsurf:fk';
        $db = self::db();

        // ON DELETE CASCADE: deleting the parent cascades to children.
        $r->add(self::FW, $cat, 'ON DELETE CASCADE cascades', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id) ON DELETE CASCADE)");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10,1),(11,1)");
                $pdo->exec("DELETE FROM `$p` WHERE id=1");
                $remaining = $pdo->query("SELECT COUNT(*) FROM `$c`")->fetchColumn();
                Support::assertEquals('0', $remaining, 'children should be cascade-deleted');
                return ['detail' => "children after cascade=$remaining", 'sql' => 'ON DELETE CASCADE'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // ON UPDATE CASCADE: updating the parent key cascades to children.
        $r->add(self::FW, $cat, 'ON UPDATE CASCADE cascades', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id) ON UPDATE CASCADE)");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10,1)");
                $pdo->exec("UPDATE `$p` SET id=2 WHERE id=1");
                $childPid = $pdo->query("SELECT pid FROM `$c` WHERE id=10")->fetchColumn();
                Support::assertEquals('2', $childPid, 'child pid should follow parent update');
                return ['detail' => "child pid after update=$childPid", 'sql' => 'ON UPDATE CASCADE'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // ON DELETE SET NULL: deleting parent nulls the child FK.
        $r->add(self::FW, $cat, 'ON DELETE SET NULL nulls child', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT NULL, FOREIGN KEY (pid) REFERENCES `$p`(id) ON DELETE SET NULL)");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10,1)");
                $pdo->exec("DELETE FROM `$p` WHERE id=1");
                $childPid = $pdo->query("SELECT pid FROM `$c` WHERE id=10")->fetchColumn();
                Support::assert($childPid === null, 'child pid should be NULL, got ' . var_export($childPid, true));
                return ['detail' => 'child pid set to NULL', 'sql' => 'ON DELETE SET NULL'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // ON DELETE RESTRICT blocks deleting a referenced parent.
        $r->add(self::FW, $cat, 'ON DELETE RESTRICT blocks parent delete', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id) ON DELETE RESTRICT)");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10,1)");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("DELETE FROM `$p` WHERE id=1");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'RESTRICT should block deleting referenced parent');
                return ['detail' => "blocked: $info", 'sql' => 'ON DELETE RESTRICT'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // ON DELETE NO ACTION blocks deleting a referenced parent.
        $r->add(self::FW, $cat, 'ON DELETE NO ACTION blocks parent delete', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id) ON DELETE NO ACTION)");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10,1)");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("DELETE FROM `$p` WHERE id=1");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'NO ACTION should block deleting referenced parent');
                return ['detail' => "blocked: $info", 'sql' => 'ON DELETE NO ACTION'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // Default (no action) FK + child violation on insert.
        $r->add(self::FW, $cat, 'FK child insert without parent rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("INSERT INTO `$c` VALUES (10, 999)");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'inserting child without parent should be rejected');
                return ['detail' => "rejected: $info", 'sql' => 'INSERT child without parent'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // FK to a non-unique column is rejected at DDL.
        $r->add(self::FW, $cat, 'FK to non-unique column rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (a INT)");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("CREATE TABLE `$c` (b INT, FOREIGN KEY (b) REFERENCES `$p`(a))");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'FK referencing a non-unique column should be rejected');
                return ['detail' => "rejected: $info", 'sql' => 'FK -> non-unique column'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // Multi-column FK works.
        $r->add(self::FW, $cat, 'multi-column FK enforced', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (a INT, b INT, PRIMARY KEY (a,b))");
                $pdo->exec("CREATE TABLE `$c` (x INT, y INT, FOREIGN KEY (x,y) REFERENCES `$p`(a,b))");
                $pdo->exec("INSERT INTO `$p` VALUES (1,2)");
                $pdo->exec("INSERT INTO `$c` VALUES (1,2)");
                $threw = false;
                try {
                    $pdo->exec("INSERT INTO `$c` VALUES (9,9)");
                } catch (\PDOException) {
                    $threw = true;
                }
                Support::assert($threw, 'multi-column FK should reject (9,9)');
                return ['detail' => 'composite FK enforced', 'sql' => 'FOREIGN KEY (x,y) REFERENCES p(a,b)'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // Self-referencing FK works.
        $r->add(self::FW, $cat, 'self-referencing FK', function () use ($db) {
            $pdo = Connections::pdo($db);
            $t = Support::name('fks');
            try {
                $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, mgr INT NULL, FOREIGN KEY (mgr) REFERENCES `$t`(id))");
                $pdo->exec("INSERT INTO `$t` VALUES (1, NULL)");
                $pdo->exec("INSERT INTO `$t` VALUES (2, 1)");
                $threw = false;
                try {
                    $pdo->exec("INSERT INTO `$t` VALUES (3, 999)");
                } catch (\PDOException) {
                    $threw = true;
                }
                Support::assert($threw, 'self-FK should reject mgr=999');
                return ['detail' => 'self-FK enforced', 'sql' => 'FK (mgr) REFERENCES self(id)'];
            } finally {
                self::dropAll($pdo, [[$t, false]]);
            }
        });

        // Combinatorial sweep of FK action pairs at DDL: every combo should
        // at least be accepted at CREATE time.
        $delActions = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];
        $updActions = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];
        foreach ($delActions as $da) {
            foreach ($updActions as $ua) {
                $r->add(self::FW, $cat, "FK ON DELETE $da ON UPDATE $ua accepted", function () use ($db, $da, $ua) {
                    $pdo = Connections::pdo($db);
                    $p = Support::name('fkp');
                    $c = Support::name('fkc');
                    try {
                        $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                        $nullable = ($da === 'SET NULL' || $ua === 'SET NULL') ? 'NULL' : 'NOT NULL';
                        $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT $nullable, FOREIGN KEY (pid) REFERENCES `$p`(id) ON DELETE $da ON UPDATE $ua)");
                        $pdo->exec("INSERT INTO `$p` VALUES (1)");
                        $pdo->exec("INSERT INTO `$c` VALUES (10,1)");
                        $n = $pdo->query("SELECT COUNT(*) FROM `$c`")->fetchColumn();
                        return ['detail' => "rows=$n with ON DELETE $da ON UPDATE $ua", 'sql' => "FK ON DELETE $da ON UPDATE $ua"];
                    } finally {
                        self::dropAll($pdo, [[$c, false], [$p, false]]);
                    }
                });
            }
        }

        // FK declared via ALTER TABLE ADD FOREIGN KEY.
        $r->add(self::FW, $cat, 'ALTER TABLE ADD FOREIGN KEY', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT)");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("ALTER TABLE `$c` ADD FOREIGN KEY (pid) REFERENCES `$p`(id)");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                return ['detail' => $threw ? "rejected: $info" : 'accepted', 'sql' => 'ALTER TABLE ADD FOREIGN KEY'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // information_schema FK introspection.
        $r->add(self::FW, $cat, 'FK in information_schema.key_column_usage', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('fkp');
            $c = Support::name('fkc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                $n = $pdo->query("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_name='$c' AND referenced_table_name IS NOT NULL")->fetchColumn();
                return ['detail' => "fk catalog rows=$n", 'sql' => 'information_schema.key_column_usage'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });
    }

    // =====================================================================
    // Part A.7 — CHECK CONSTRAINTS (~40)
    // =====================================================================

    private static function checks(Runner $r): void
    {
        $cat = 'ddlsurf:check';
        $db = self::db();

        // Inline CHECK accepted in DDL.
        $checkDefs = [
            'inline >= 0' => 'id INT, age INT CHECK (age >= 0)',
            'inline range' => 'id INT, pct INT CHECK (pct BETWEEN 0 AND 100)',
            'inline IN list' => "id INT, st VARCHAR(5) CHECK (st IN ('a','b','c'))",
            'inline <>' => 'id INT, n INT CHECK (n <> 0)',
            'inline LENGTH' => 'id INT, s VARCHAR(20) CHECK (LENGTH(s) > 2)',
            'named single' => 'id INT, CONSTRAINT chk_pos CHECK (id > 0)',
            'named multi-col' => 'lo INT, hi INT, CONSTRAINT chk_order CHECK (lo < hi)',
            'multi-col anon' => 'a INT, b INT, CHECK (a + b > 0)',
            'expression' => 'id INT, x INT, y INT, CHECK (x * y >= 0)',
        ];
        foreach ($checkDefs as $label => $cols) {
            self::addWorks(
                $r,
                $cat,
                "CHECK accepted in DDL: $label",
                ["CREATE TABLE `__chk_tmp` ($cols)"],
                null,
                [['__chk_tmp', false]],
                "CREATE TABLE with CHECK ($label)"
            );
        }

        // KEY FINDING: is the CHECK actually ENFORCED on a violating insert?
        // Per the probe MatrixOne accepts CHECK syntax but does NOT enforce it.
        $enforceCases = [
            'age >= 0' => ['id INT, age INT CHECK (age >= 0)', '(1, -5)', 'age', '-5'],
            'pct 0..100' => ['id INT, pct INT CHECK (pct BETWEEN 0 AND 100)', '(1, 250)', 'pct', '250'],
            'n <> 0' => ['id INT, n INT CHECK (n <> 0)', '(1, 0)', 'n', '0'],
            'named lo<hi' => ['lo INT, hi INT, CONSTRAINT c CHECK (lo < hi)', '(9, 1)', 'lo', '9'],
        ];
        foreach ($enforceCases as $label => [$cols, $vals, $readCol, $badVal]) {
            $r->add(self::FW, $cat, "CHECK enforcement: $label", function () use ($db, $cols, $vals, $readCol, $badVal, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('chk');
                try {
                    $pdo->exec("CREATE TABLE `$tn` ($cols)");
                    $enforced = false;
                    $stored = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` VALUES $vals");
                        $stored = $pdo->query("SELECT `$readCol` FROM `$tn`")->fetchColumn();
                    } catch (\PDOException) {
                        $enforced = true;
                    }
                    // PASS only if MatrixOne actually enforces the constraint.
                    Support::assert(
                        $enforced,
                        "CHECK ($label) is NOT enforced: violating value stored = " . var_export($stored, true)
                    );
                    return ['detail' => 'CHECK enforced (rejected violation)', 'sql' => "INSERT violating CHECK ($label)"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // ALTER TABLE ADD CHECK — KNOWN gap.
        self::addExpectError(
            $r,
            $cat,
            'ALTER TABLE ADD CHECK rejected',
            ["CREATE TABLE `__altchk_tmp` (id INT)"],
            'ALTER TABLE `__altchk_tmp` ADD CONSTRAINT chk2 CHECK (id < 100)',
            [['__altchk_tmp', false]],
            'ALTER TABLE ADD CHECK — expect error'
        );

        // information_schema.check_constraints introspection (if present).
        $r->add(self::FW, $cat, 'information_schema.check_constraints behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('chk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT, CONSTRAINT c1 CHECK (id > 0))");
                try {
                    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.check_constraints")->fetchColumn();
                    return ['detail' => "check_constraints rows=$n", 'sql' => 'information_schema.check_constraints'];
                } catch (\PDOException $e) {
                    return ['detail' => 'catalog rejected: ' . self::errInfo($e), 'sql' => 'information_schema.check_constraints'];
                }
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });
    }

    // =====================================================================
    // Part A.8 — GENERATED COLUMNS (~50)
    // =====================================================================

    private static function generated(Runner $r): void
    {
        $cat = 'ddlsurf:generated';
        $db = self::db();

        // STORED vs VIRTUAL across a variety of expressions; insert + read back.
        $exprCases = [
            'a+b stored' => ['a INT, b INT', "c INT GENERATED ALWAYS AS (a + b) STORED", '(3, 4)', 'a,b', '7'],
            'a+b virtual' => ['a INT, b INT', "c INT GENERATED ALWAYS AS (a + b) VIRTUAL", '(3, 4)', 'a,b', '7'],
            'a*b stored' => ['a INT, b INT', "c INT GENERATED ALWAYS AS (a * b) STORED", '(5, 6)', 'a,b', '30'],
            'a-b virtual' => ['a INT, b INT', "c INT GENERATED ALWAYS AS (a - b) VIRTUAL", '(10, 3)', 'a,b', '7'],
            'CONCAT stored' => ["f VARCHAR(10), l VARCHAR(10)", "c VARCHAR(21) GENERATED ALWAYS AS (CONCAT(f, l)) STORED", "('ab', 'cd')", 'f,l', 'abcd'],
            'UPPER virtual' => ["s VARCHAR(10)", "c VARCHAR(10) GENERATED ALWAYS AS (UPPER(s)) VIRTUAL", "('hi')", 's', 'HI'],
            'ABS stored' => ['n INT', "c INT GENERATED ALWAYS AS (ABS(n)) STORED", '(-9)', 'n', '9'],
            'length stored' => ["s VARCHAR(20)", "c INT GENERATED ALWAYS AS (CHAR_LENGTH(s)) STORED", "('hello')", 's', '5'],
            'arith virtual' => ['p INT, q INT', "c INT GENERATED ALWAYS AS (p * 2 + q) VIRTUAL", '(5, 3)', 'p,q', '13'],
            'coalesce stored' => ['a INT, b INT', "c INT GENERATED ALWAYS AS (COALESCE(a, b, 0)) STORED", '(NULL, 7)', 'a,b', '7'],
        ];
        foreach ($exprCases as $label => [$baseCols, $genCol, $vals, $insCols, $expected]) {
            $r->add(self::FW, $cat, "generated col: $label", function () use ($db, $baseCols, $genCol, $vals, $insCols, $expected, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('gen');
                try {
                    $pdo->exec("CREATE TABLE `$tn` ($baseCols, $genCol)");
                    $pdo->exec("INSERT INTO `$tn` ($insCols) VALUES $vals");
                    $got = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                    Support::assertValueEquals($expected, $got, "generated $label");
                    return ['detail' => "computed c=$got", 'sql' => "generated column ($label)"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // JSON_EXTRACT-based generated column.
        $r->add(self::FW, $cat, 'generated col from JSON_EXTRACT', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('gen');
            try {
                $pdo->exec("CREATE TABLE `$tn` (doc JSON, v INT GENERATED ALWAYS AS (JSON_EXTRACT(doc, '$.v')) STORED)");
                $st = $pdo->prepare("INSERT INTO `$tn` (doc) VALUES (?)");
                $st->execute(['{"v": 42}']);
                $got = $pdo->query("SELECT v FROM `$tn`")->fetchColumn();
                Support::assertValueEquals('42', $got, 'JSON generated col');
                return ['detail' => "v=$got", 'sql' => "GENERATED AS (JSON_EXTRACT(doc,'$.v'))"];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Generated column with an INDEX on it.
        $r->add(self::FW, $cat, 'index on generated column', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('gen');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT GENERATED ALWAYS AS (a * 2) STORED, INDEX idx_b (b))");
                $pdo->exec("INSERT INTO `$tn` (a) VALUES (5),(10)");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE b = 20")->fetchColumn();
                Support::assertEquals('1', $n, 'index on generated col query');
                return ['detail' => "b=20 rows=$n", 'sql' => 'INDEX on STORED generated column'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // UNIQUE on a generated column.
        $r->add(self::FW, $cat, 'unique on generated column', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('gen');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT GENERATED ALWAYS AS (a + 1) STORED, UNIQUE KEY uk_b (b))");
                $pdo->exec("INSERT INTO `$tn` (a) VALUES (1)");
                $threw = false;
                try {
                    $pdo->exec("INSERT INTO `$tn` (a) VALUES (1)");
                } catch (\PDOException) {
                    $threw = true;
                }
                Support::assert($threw, 'duplicate generated value should violate UNIQUE');
                return ['detail' => 'unique generated col enforced', 'sql' => 'UNIQUE on generated column'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Attempt to INSERT directly into a generated column — must be rejected.
        foreach (['STORED', 'VIRTUAL'] as $kind) {
            $r->add(self::FW, $cat, "insert into $kind generated col rejected", function () use ($db, $kind) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('gen');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, c INT GENERATED ALWAYS AS (a + b) $kind)");
                    $threw = false;
                    $info = '';
                    try {
                        $pdo->exec("INSERT INTO `$tn` (a, b, c) VALUES (1, 2, 99)");
                    } catch (\PDOException $e) {
                        $threw = true;
                        $info = self::errInfo($e);
                    }
                    Support::assert($threw, "writing a literal to a $kind generated column should be rejected");
                    return ['detail' => "rejected: $info", 'sql' => "INSERT into generated $kind col — expect reject"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // Inserting DEFAULT into a generated column is allowed (computes it).
        $r->add(self::FW, $cat, 'insert DEFAULT into generated col allowed', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('gen');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, c INT GENERATED ALWAYS AS (a + b) STORED)");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("INSERT INTO `$tn` (a, b, c) VALUES (2, 3, DEFAULT)");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                if ($threw) {
                    return ['detail' => "DEFAULT into generated rejected: $info", 'sql' => 'INSERT ... c=DEFAULT'];
                }
                $got = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                return ['detail' => "computed c=$got via DEFAULT", 'sql' => 'INSERT ... c=DEFAULT'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });
    }

    // =====================================================================
    // Part A.9 — INDEXES (~30)
    // =====================================================================

    private static function indexes(Runner $r): void
    {
        $cat = 'ddlsurf:index';
        $db = self::db();

        // Composite index.
        self::addWorks(
            $r,
            $cat,
            'composite index create + query',
            ["CREATE TABLE `__idx1` (a INT, b INT, INDEX idx_ab (a, b))", "INSERT INTO `__idx1` VALUES (1,2),(3,4)"],
            ["SELECT COUNT(*) FROM `__idx1` WHERE a=1 AND b=2", '1'],
            [['__idx1', false]],
            'INDEX (a, b)'
        );

        // Prefix index on VARCHAR(n).
        foreach ([5, 10, 20] as $plen) {
            self::addWorks(
                $r,
                $cat,
                "prefix index VARCHAR($plen)",
                ["CREATE TABLE `__idxp_$plen` (s VARCHAR(100), INDEX idx_s (s($plen)))", "INSERT INTO `__idxp_$plen` VALUES ('hello world')"],
                ["SELECT COUNT(*) FROM `__idxp_$plen`", '1'],
                [["__idxp_$plen", false]],
                "INDEX (s($plen))"
            );
        }

        // UNIQUE index enforced.
        $r->add(self::FW, $cat, 'UNIQUE index enforced', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, UNIQUE KEY uk (a))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $threw = false;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                } catch (\PDOException) {
                    $threw = true;
                }
                Support::assert($threw, 'UNIQUE index should reject duplicate');
                return ['detail' => 'unique enforced', 'sql' => 'UNIQUE KEY (a)'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Descending index accepted.
        self::addWorks(
            $r,
            $cat,
            'descending index accepted',
            ["CREATE TABLE `__idxd` (a INT, INDEX idx_d (a DESC))", "INSERT INTO `__idxd` VALUES (3),(1),(2)"],
            ["SELECT COUNT(*) FROM `__idxd`", '3'],
            [['__idxd', false]],
            'INDEX (a DESC)'
        );

        // SHOW INDEX.
        $r->add(self::FW, $cat, 'SHOW INDEX lists index', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, INDEX idx_a (a))");
                $rows = $pdo->query("SHOW INDEX FROM `$tn`")->fetchAll();
                Support::assert(count($rows) >= 1, 'SHOW INDEX should return >=1 row');
                return ['detail' => 'index rows=' . count($rows), 'sql' => 'SHOW INDEX FROM tbl'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // CREATE INDEX / DROP INDEX standalone.
        $r->add(self::FW, $cat, 'CREATE INDEX then DROP INDEX', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT)");
                $pdo->exec("CREATE INDEX ix_a ON `$tn` (a)");
                $pdo->exec("DROP INDEX ix_a ON `$tn`");
                return ['detail' => 'create+drop index ok', 'sql' => 'CREATE INDEX / DROP INDEX'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // ALTER TABLE ADD INDEX / DROP INDEX.
        $r->add(self::FW, $cat, 'ALTER TABLE ADD/DROP INDEX', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT)");
                $pdo->exec("ALTER TABLE `$tn` ADD INDEX ix_b (b)");
                $pdo->exec("ALTER TABLE `$tn` DROP INDEX ix_b");
                return ['detail' => 'alter add/drop index ok', 'sql' => 'ALTER TABLE ADD/DROP INDEX'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Functional / expression index — KNOWN gap.
        self::addExpectError(
            $r,
            $cat,
            'functional index rejected',
            [],
            "CREATE TABLE `__idxf` (a INT, INDEX ((a + 1)))",
            [['__idxf', false]],
            'INDEX ((a+1)) — expect error'
        );

        // FULLTEXT index needs the experimental flag and a primary key.
        $r->add(self::FW, $cat, 'FULLTEXT index requires experimental flag + PK', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("SET experimental_fulltext_index=1");
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, s TEXT, FULLTEXT(s))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1, 'matrixone full text search')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE MATCH(s) AGAINST('matrixone')")->fetchColumn();
                return ['detail' => "fulltext match rows=$n", 'sql' => 'FULLTEXT(s) + MATCH ... AGAINST'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // FULLTEXT without primary key is rejected.
        $r->add(self::FW, $cat, 'FULLTEXT without PK rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('idx');
            try {
                $pdo->exec("SET experimental_fulltext_index=1");
                $threw = false;
                $info = '';
                try {
                    $pdo->exec("CREATE TABLE `$tn` (s TEXT, FULLTEXT(s))");
                } catch (\PDOException $e) {
                    $threw = true;
                    $info = self::errInfo($e);
                }
                Support::assert($threw, 'FULLTEXT without a primary key should be rejected');
                return ['detail' => "rejected: $info", 'sql' => 'FULLTEXT(s) without PK — expect reject'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });
    }

    // =====================================================================
    // Part B.1 — DUPLICATE KEY (~80)
    // =====================================================================

    /**
     * Capture a PDOException from a duplicate-key insert and assert the surfaced
     * error info. MatrixOne surfaces SQLSTATE HY000 (NOT the canonical 23000)
     * but DOES carry MySQL errno 1062 in errorInfo[1]; we assert the errno.
     */
    private static function duplicateKey(Runner $r): void
    {
        $cat = 'err:dupkey';
        $db = self::db();

        // Duplicate PK / UNIQUE across several key types.
        $keyTypes = [
            'int-pk' => ['id INT PRIMARY KEY', '(1)', '(1)'],
            'bigint-pk' => ['id BIGINT PRIMARY KEY', '(9000000000)', '(9000000000)'],
            'varchar-pk' => ['id VARCHAR(20) PRIMARY KEY', "('abc')", "('abc')"],
            'char-pk' => ['id CHAR(5) PRIMARY KEY', "('xy')", "('xy')"],
            'decimal-pk' => ['id DECIMAL(10,2) PRIMARY KEY', '(12.34)', '(12.34)'],
            'date-pk' => ['id DATE PRIMARY KEY', "('2026-01-01')", "('2026-01-01')"],
            'int-unique' => ['a INT UNIQUE', '(5)', '(5)'],
            'bigint-unique' => ['a BIGINT UNIQUE', '(7000000000)', '(7000000000)'],
            'varchar-unique' => ['a VARCHAR(20) UNIQUE', "('dup')", "('dup')"],
            'decimal-unique' => ['a DECIMAL(8,3) UNIQUE', '(1.250)', '(1.250)'],
        ];
        foreach ($keyTypes as $label => [$cols, $first, $dup]) {
            $r->add(self::FW, $cat, "duplicate $label surfaces errno 1062", function () use ($db, $cols, $first, $dup, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('dk');
                try {
                    $pdo->exec("CREATE TABLE `$tn` ($cols)");
                    $pdo->exec("INSERT INTO `$tn` VALUES $first");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` VALUES $dup");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$label: duplicate insert did not surface an error");
                    Support::assertEquals('1062', (string) $errno, "$label dup errno");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno (MySQL parity 1062)", 'sql' => "duplicate $label"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // Composite UNIQUE duplicate.
        $r->add(self::FW, $cat, 'composite UNIQUE duplicate errno 1062', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, UNIQUE(a,b))");
                $pdo->exec("INSERT INTO `$tn` VALUES (1,2)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1,2)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('1062', (string) $errno, 'composite unique dup errno');
                return ['detail' => "errno=$errno", 'sql' => 'duplicate (a,b) composite UNIQUE'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Duplicate inside a multi-row batch insert.
        $r->add(self::FW, $cat, 'duplicate within batch insert errno 1062', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1),(2),(1)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('1062', (string) $errno, 'batch dup errno');
                return ['detail' => "errno=$errno", 'sql' => 'INSERT (1),(2),(1) batch dup'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // SQLSTATE finding: MatrixOne reports HY000, not MySQL's 23000.
        $r->add(self::FW, $cat, 'dup-key SQLSTATE is HY000 not 23000 (finding)', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $sqlstate = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                }
                // Document the divergence: MySQL uses 23000, MatrixOne HY000.
                Support::assert($sqlstate !== null, 'no SQLSTATE surfaced');
                Support::assert(
                    $sqlstate === 'HY000',
                    "expected MatrixOne's generic HY000 SQLSTATE, got " . var_export($sqlstate, true)
                );
                return ['detail' => "SQLSTATE=$sqlstate (MySQL would be 23000)", 'sql' => 'dup-key SQLSTATE check'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // ON DUPLICATE KEY UPDATE recovery: no error, row updated.
        $r->add(self::FW, $cat, 'ON DUPLICATE KEY UPDATE recovers', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, v INT)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1, 10)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1, 20) ON DUPLICATE KEY UPDATE v = VALUES(v)");
                $v = $pdo->query("SELECT v FROM `$tn` WHERE id=1")->fetchColumn();
                Support::assertEquals('20', $v, 'ON DUPLICATE KEY UPDATE value');
                return ['detail' => "recovered, v=$v", 'sql' => 'ON DUPLICATE KEY UPDATE'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // INSERT IGNORE on duplicate: no error, row skipped.
        $r->add(self::FW, $cat, 'INSERT IGNORE skips duplicate', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                try {
                    $pdo->exec("INSERT IGNORE INTO `$tn` VALUES (1),(2)");
                } catch (\PDOException $e) {
                    return ['detail' => 'INSERT IGNORE rejected: ' . self::errInfo($e), 'sql' => 'INSERT IGNORE dup'];
                }
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                return ['detail' => "rows after IGNORE=$n", 'sql' => 'INSERT IGNORE dup'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // REPLACE INTO on duplicate.
        $r->add(self::FW, $cat, 'REPLACE INTO overwrites duplicate', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, v INT)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1, 10)");
                $pdo->exec("REPLACE INTO `$tn` VALUES (1, 99)");
                $v = $pdo->query("SELECT v FROM `$tn` WHERE id=1")->fetchColumn();
                Support::assertEquals('99', $v, 'REPLACE value');
                return ['detail' => "replaced v=$v", 'sql' => 'REPLACE INTO dup'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Duplicate against a prepared statement (param path).
        $r->add(self::FW, $cat, 'duplicate via prepared statement errno 1062', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $st = $pdo->prepare("INSERT INTO `$tn` VALUES (?)");
                $st->execute([1]);
                $errno = null;
                try {
                    $st->execute([1]);
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('1062', (string) $errno, 'prepared dup errno');
                return ['detail' => "errno=$errno", 'sql' => 'prepared INSERT dup'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Re-insert after a duplicate failure still works (connection healthy).
        $r->add(self::FW, $cat, 'connection healthy after duplicate error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('dk');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                } catch (\PDOException) {
                }
                $pdo->exec("INSERT INTO `$tn` VALUES (2)");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('2', $n, 'rows after recovery');
                return ['detail' => "rows=$n after recovering from dup error", 'sql' => 'recover after dup'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Many int-key duplicate variants to broaden coverage.
        foreach (['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT', 'INT UNSIGNED'] as $it) {
            $r->add(self::FW, $cat, "duplicate $it PK errno 1062", function () use ($db, $it) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('dk');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (id $it PRIMARY KEY)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (7)");
                    $errno = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` VALUES (7)");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assertEquals('1062', (string) $errno, "$it dup errno");
                    return ['detail' => "errno=$errno", 'sql' => "duplicate $it PK"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // VARCHAR-key duplicate variants over different lengths.
        foreach ([5, 32, 100, 255] as $len) {
            $r->add(self::FW, $cat, "duplicate VARCHAR($len) unique errno 1062", function () use ($db, $len) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('dk');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (a VARCHAR($len) UNIQUE)");
                    $pdo->exec("INSERT INTO `$tn` VALUES ('key')");
                    $errno = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` VALUES ('key')");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assertEquals('1062', (string) $errno, "VARCHAR($len) dup errno");
                    return ['detail' => "errno=$errno", 'sql' => "duplicate VARCHAR($len) unique"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }
    }

    // =====================================================================
    // Part B.2 — CONSTRAINT VIOLATIONS (~80)
    // =====================================================================

    private static function constraintViolation(Runner $r): void
    {
        $cat = 'err:constraint';
        $db = self::db();

        // NOT NULL violation: MatrixOne surfaces errno 3819 (MySQL uses 1048).
        $notNullTypes = ['INT', 'BIGINT', 'VARCHAR(20)', 'DATE', 'DECIMAL(10,2)', 'TINYINT', 'DOUBLE', 'CHAR(5)'];
        foreach ($notNullTypes as $t) {
            $r->add(self::FW, $cat, "NOT NULL violation ($t) surfaces error", function () use ($db, $t) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('cv');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (a $t NOT NULL)");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (a) VALUES (NULL)");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$t: NULL into NOT NULL was accepted");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno (MySQL parity 1048)", 'sql' => "INSERT NULL into $t NOT NULL"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // NOT NULL violation errno value is specifically 3819 (finding).
        $r->add(self::FW, $cat, 'NOT NULL violation errno is 3819 (finding)', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('cv');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT NOT NULL)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (a) VALUES (NULL)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('3819', (string) $errno, 'NOT NULL errno (MatrixOne uses 3819, MySQL 1048)');
                return ['detail' => "errno=$errno (MatrixOne-specific; MySQL would be 1048)", 'sql' => 'NOT NULL errno check'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Missing column with no default (NOT NULL, no default) surfaces error.
        $r->add(self::FW, $cat, 'missing NOT NULL column without default errors', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('cv');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT NOT NULL, b INT)");
                $errno = null;
                $sqlstate = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (b) VALUES (5)");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'omitting a NOT NULL no-default column was accepted');
                return ['detail' => "SQLSTATE=$sqlstate errno=$errno (MySQL parity 1364)", 'sql' => 'INSERT omitting NOT NULL column'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Several missing-column variants.
        foreach (['INT', 'VARCHAR(10)', 'DATE', 'DECIMAL(6,2)'] as $t) {
            $r->add(self::FW, $cat, "missing NOT NULL $t column errors", function () use ($db, $t) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('cv');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (req $t NOT NULL, opt INT)");
                    $errno = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (opt) VALUES (1)");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$t: missing required column accepted");
                    return ['detail' => "errno=$errno", 'sql' => "INSERT omitting NOT NULL $t"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // FK violation on insert child without parent: errno surfaced (20101).
        $r->add(self::FW, $cat, 'FK insert violation surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('cvp');
            $c = Support::name('cvc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                $errno = null;
                $sqlstate = null;
                try {
                    $pdo->exec("INSERT INTO `$c` VALUES (1, 999)");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'FK child insert violation was accepted');
                return ['detail' => "SQLSTATE=$sqlstate errno=$errno (MySQL parity 1452)", 'sql' => 'FK insert child w/o parent'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // FK violation on delete parent with child: errno surfaced (20101).
        $r->add(self::FW, $cat, 'FK delete-parent violation surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('cvp');
            $c = Support::name('cvc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                $pdo->exec("INSERT INTO `$p` VALUES (1)");
                $pdo->exec("INSERT INTO `$c` VALUES (10, 1)");
                $errno = null;
                $sqlstate = null;
                try {
                    $pdo->exec("DELETE FROM `$p` WHERE id=1");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'deleting a referenced parent was accepted');
                return ['detail' => "SQLSTATE=$sqlstate errno=$errno (MySQL parity 1451)", 'sql' => 'DELETE referenced parent'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // FK errno is specifically 20101 (MatrixOne-specific, finding).
        $r->add(self::FW, $cat, 'FK violation errno is 20101 (finding)', function () use ($db) {
            $pdo = Connections::pdo($db);
            $p = Support::name('cvp');
            $c = Support::name('cvc');
            try {
                $pdo->exec("CREATE TABLE `$p` (id INT PRIMARY KEY)");
                $pdo->exec("CREATE TABLE `$c` (id INT, pid INT, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$c` VALUES (1, 999)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('20101', (string) $errno, 'FK errno (MatrixOne uses 20101, MySQL 1452)');
                return ['detail' => "errno=$errno (MatrixOne-specific; MySQL would be 1452)", 'sql' => 'FK errno check'];
            } finally {
                self::dropAll($pdo, [[$c, false], [$p, false]]);
            }
        });

        // Primary-key NULL rejection.
        $r->add(self::FW, $cat, 'NULL into PRIMARY KEY rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('cv');
            try {
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (id) VALUES (NULL)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'NULL into PK was accepted');
                return ['detail' => "errno=$errno", 'sql' => 'INSERT NULL into PRIMARY KEY'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // String NULL into NOT NULL via prepared param.
        $r->add(self::FW, $cat, 'NOT NULL via prepared NULL param errors', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('cv');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT NOT NULL)");
                $st = $pdo->prepare("INSERT INTO `$tn` (a) VALUES (?)");
                $errno = null;
                try {
                    $st->execute([null]);
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'prepared NULL into NOT NULL was accepted');
                return ['detail' => "errno=$errno", 'sql' => 'prepared NULL into NOT NULL'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // SQLSTATE for NOT NULL is HY000 (finding).
        $r->add(self::FW, $cat, 'NOT NULL SQLSTATE is HY000 not 23000 (finding)', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('cv');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT NOT NULL)");
                $sqlstate = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (a) VALUES (NULL)");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                }
                Support::assert($sqlstate === 'HY000', 'expected HY000, got ' . var_export($sqlstate, true));
                return ['detail' => "SQLSTATE=$sqlstate (MySQL would be 23000)", 'sql' => 'NOT NULL SQLSTATE'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // FK child insert across multiple key types.
        foreach (['INT', 'BIGINT', 'VARCHAR(20)'] as $kt) {
            $r->add(self::FW, $cat, "FK child insert violation ($kt key)", function () use ($db, $kt) {
                $pdo = Connections::pdo($db);
                $p = Support::name('cvp');
                $c = Support::name('cvc');
                try {
                    $pdo->exec("CREATE TABLE `$p` (id $kt PRIMARY KEY)");
                    $pdo->exec("CREATE TABLE `$c` (id INT PRIMARY KEY, pid $kt, FOREIGN KEY (pid) REFERENCES `$p`(id))");
                    $badVal = str_starts_with($kt, 'VARCHAR') ? "'nope'" : '12345';
                    $errno = null;
                    try {
                        $pdo->exec("INSERT INTO `$c` VALUES (1, $badVal)");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$kt: FK child violation accepted");
                    return ['detail' => "errno=$errno", 'sql' => "FK child violation ($kt key)"];
                } finally {
                    self::dropAll($pdo, [[$c, false], [$p, false]]);
                }
            });
        }
    }

    // =====================================================================
    // Part B.3 — TYPE / RANGE ERRORS (~80)
    // =====================================================================

    private static function typeRangeErrors(Runner $r): void
    {
        $cat = 'err:type';
        $db = self::db();

        // Numeric out-of-range — MatrixOne surfaces errno 1690.
        $rangeCases = [
            'TINYINT 128' => ['TINYINT', '128'],
            'TINYINT -129' => ['TINYINT', '-129'],
            'TINYINT UNSIGNED 256' => ['TINYINT UNSIGNED', '256'],
            'TINYINT UNSIGNED -1' => ['TINYINT UNSIGNED', '-1'],
            'SMALLINT 32768' => ['SMALLINT', '32768'],
            'SMALLINT UNSIGNED 65536' => ['SMALLINT UNSIGNED', '65536'],
            'MEDIUMINT 8388608' => ['MEDIUMINT', '8388608'],
            'INT 2147483648' => ['INT', '2147483648'],
            'INT UNSIGNED -1' => ['INT UNSIGNED', '-1'],
            'BIGINT overflow' => ['BIGINT', '99999999999999999999'],
        ];
        foreach ($rangeCases as $label => [$t, $val]) {
            $r->add(self::FW, $cat, "out-of-range $label surfaces error", function () use ($db, $t, $val, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c $t)");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ($val)");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$label: out-of-range value accepted (no clamping expected)");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno", 'sql' => "INSERT $val into $t"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // Out-of-range errno is 1690 (MySQL parity for ER_DATA_OUT_OF_RANGE).
        $r->add(self::FW, $cat, 'numeric overflow errno is 1690', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tr');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c TINYINT)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (c) VALUES (200)");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assertEquals('1690', (string) $errno, 'overflow errno');
                return ['detail' => "errno=$errno (matches MySQL 1690)", 'sql' => 'TINYINT overflow errno'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Invalid datetime/date values.
        $dateCases = [
            'bad date string' => ['DATE', "'not-a-date'"],
            'impossible date' => ['DATE', "'2026-13-40'"],
            'bad datetime' => ['DATETIME', "'2026-02-30 99:99:99'"],
            'bad time' => ['TIME', "'99:99:99'"],
            'bad timestamp' => ['TIMESTAMP', "'garbage'"],
            'alpha in date' => ['DATE', "'2026-ab-cd'"],
        ];
        foreach ($dateCases as $label => [$t, $val]) {
            $r->add(self::FW, $cat, "invalid temporal $label surfaces error", function () use ($db, $t, $val, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c $t)");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ($val)");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$label: invalid temporal value accepted");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno", 'sql' => "INSERT $val into $t"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // Invalid ENUM / SET values.
        $r->add(self::FW, $cat, 'invalid ENUM value surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tr');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c ENUM('a','b','c'))");
                $errno = null;
                $sqlstate = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (c) VALUES ('z')");
                } catch (\PDOException $e) {
                    $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'invalid ENUM value accepted');
                return ['detail' => "SQLSTATE=$sqlstate errno=$errno", 'sql' => "INSERT 'z' into ENUM"];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        $r->add(self::FW, $cat, 'invalid SET member surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tr');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c SET('r','w','x'))");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (c) VALUES ('zzz')");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'invalid SET member accepted');
                return ['detail' => "errno=$errno", 'sql' => "INSERT 'zzz' into SET"];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // Bad DECIMAL (overflow / non-numeric).
        $decCases = [
            'decimal overflow' => ['DECIMAL(3,2)', '999.99'],
            'decimal int overflow' => ['DECIMAL(4,0)', '99999'],
            'decimal non-numeric' => ['DECIMAL(10,2)', "'abc'"],
        ];
        foreach ($decCases as $label => [$t, $val]) {
            $r->add(self::FW, $cat, "$label surfaces error", function () use ($db, $t, $val, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c $t)");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ($val)");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$label: bad decimal accepted");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno", 'sql' => "INSERT $val into $t"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // INT from non-numeric / float strings.
        $intStrCases = [
            'INT from abc' => ['INT', "'abc'"],
            'INT from float string' => ['INT', "'3.9'"],
            'INT from empty' => ['INT', "''"],
            'BIGINT from text' => ['BIGINT', "'xyz'"],
            'INT from hex string' => ['INT', "'0xFF'"],
        ];
        foreach ($intStrCases as $label => [$t, $val]) {
            $r->add(self::FW, $cat, "$label surfaces error", function () use ($db, $t, $val, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c $t)");
                    $errno = null;
                    $sqlstate = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ($val)");
                    } catch (\PDOException $e) {
                        $sqlstate = $e->errorInfo[0] ?? $e->getCode();
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "$label: non-numeric value silently accepted");
                    return ['detail' => "SQLSTATE=$sqlstate errno=$errno", 'sql' => "INSERT $val into $t"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // VARCHAR over-length (strict) rejection.
        foreach ([3, 5, 10] as $len) {
            $r->add(self::FW, $cat, "VARCHAR($len) over-length rejected", function () use ($db, $len) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c VARCHAR($len))");
                    $over = str_repeat('A', $len + 5);
                    $errno = null;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ('$over')");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, "VARCHAR($len): over-length value accepted (no truncation expected)");
                    return ['detail' => "errno=$errno", 'sql' => "INSERT over-length into VARCHAR($len)"];
                } finally {
                    self::dropAll($pdo, [[$tn, false]]);
                }
            });
        }

        // Invalid JSON document.
        $r->add(self::FW, $cat, 'invalid JSON document surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tr');
            try {
                $pdo->exec("CREATE TABLE `$tn` (j JSON)");
                $errno = null;
                try {
                    $pdo->exec("INSERT INTO `$tn` (j) VALUES ('{not valid json}')");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'invalid JSON accepted');
                return ['detail' => "errno=$errno", 'sql' => 'INSERT invalid JSON'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });

        // CAST failures at SELECT time.
        $castCases = [
            'CAST abc AS SIGNED' => "CAST('abc' AS SIGNED)",
            'CAST text AS DECIMAL' => "CAST('xyz' AS DECIMAL(10,2))",
            'CAST bad AS DATE' => "CAST('not-date' AS DATE)",
        ];
        foreach ($castCases as $label => $expr) {
            $r->add(self::FW, $cat, "$label behaviour", function () use ($db, $expr, $label) {
                $pdo = Connections::pdo($db);
                try {
                    $got = $pdo->query("SELECT $expr AS v")->fetchColumn();
                    // MatrixOne is strict: this likely errors. If it returns a
                    // value, that is itself the recorded finding.
                    return ['detail' => 'returned ' . var_export($got, true), 'sql' => "SELECT $expr"];
                } catch (\PDOException $e) {
                    return ['detail' => 'errored: ' . self::errInfo($e), 'sql' => "SELECT $expr"];
                }
            });
        }

        // Division by zero behaviour (NULL vs error).
        $r->add(self::FW, $cat, 'division by zero behaviour', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $got = $pdo->query("SELECT 1/0 AS v")->fetchColumn();
                return ['detail' => '1/0 = ' . var_export($got, true), 'sql' => 'SELECT 1/0'];
            } catch (\PDOException $e) {
                return ['detail' => 'errored: ' . self::errInfo($e), 'sql' => 'SELECT 1/0'];
            }
        });

        // Parse error surfaces errno 1064.
        $r->add(self::FW, $cat, 'syntax error surfaces errno 1064', function () use ($db) {
            $pdo = Connections::pdo($db);
            $errno = null;
            try {
                $pdo->exec("SELCT 1");
            } catch (\PDOException $e) {
                $errno = $e->errorInfo[1] ?? null;
            }
            Support::assertEquals('1064', (string) $errno, 'parse error errno');
            return ['detail' => "errno=$errno", 'sql' => 'SELCT 1 (typo)'];
        });

        // Unknown table surfaces an error (MatrixOne uses 1064 here, finding).
        $r->add(self::FW, $cat, 'unknown table surfaces error (errno finding)', function () use ($db) {
            $pdo = Connections::pdo($db);
            $errno = null;
            try {
                $pdo->query("SELECT * FROM " . Support::name('nope'));
            } catch (\PDOException $e) {
                $errno = $e->errorInfo[1] ?? null;
            }
            Support::assert($errno !== null, 'query on missing table did not error');
            return ['detail' => "errno=$errno (MySQL would be 1146)", 'sql' => 'SELECT FROM <missing table>'];
        });

        // Unknown column surfaces an error.
        $r->add(self::FW, $cat, 'unknown column surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tr');
            try {
                $pdo->exec("CREATE TABLE `$tn` (a INT)");
                $errno = null;
                try {
                    $pdo->query("SELECT nonexistent_col FROM `$tn`");
                } catch (\PDOException $e) {
                    $errno = $e->errorInfo[1] ?? null;
                }
                Support::assert($errno !== null, 'unknown column did not error');
                return ['detail' => "errno=$errno", 'sql' => 'SELECT unknown_col'];
            } finally {
                self::dropAll($pdo, [[$tn, false]]);
            }
        });
    }

    // =====================================================================
    // Part B.4 — DEADLOCK / LOCK (~30)
    // =====================================================================
    //
    // SAFETY: row-level lock waits in MatrixOne ignore lock_wait_timeout and can
    // hang indefinitely, so we NEVER contend on row locks here. Instead we use
    // GET_LOCK(name, timeout) — a named, advisory lock with a bounded timeout —
    // which is the only contention primitive observed to be reliably bounded.

    private static function deadlockAndLock(Runner $r): void
    {
        $cat = 'err:deadlock';
        $db = self::db();

        // Two connections contend for the same named lock; the second one waits
        // up to the (short) timeout and returns 0 — bounded, never hangs.
        for ($i = 0; $i < 8; $i++) {
            $r->add(self::FW, $cat, "GET_LOCK contention #$i times out (bounded)", function () use ($db, $i) {
                $a = Connections::pdo($db);
                $b = Connections::pdo($db);
                $lock = 'mo_ddlsurf_lock_' . $i . '_' . bin2hex(random_bytes(3));
                $st = microtime(true);
                try {
                    $aGot = $a->query("SELECT GET_LOCK('$lock', 1)")->fetchColumn();
                    Support::assertEquals('1', (string) $aGot, 'connection A should acquire the lock');
                    $t0 = microtime(true);
                    $bGot = $b->query("SELECT GET_LOCK('$lock', 1)")->fetchColumn();
                    $waited = microtime(true) - $t0;
                    Support::assertEquals('0', (string) $bGot, 'connection B should time out (lock held)');
                    Support::assert($waited < 3.0, 'GET_LOCK wait should be bounded, waited ' . round($waited, 2) . 's');
                    return ['detail' => "B timed out after " . round($waited, 2) . "s (returned 0)", 'sql' => "GET_LOCK contention"];
                } finally {
                    try {
                        $a->query("SELECT RELEASE_LOCK('$lock')")->fetchColumn();
                    } catch (\Throwable) {
                    }
                }
            });
        }

        // After A releases, B can acquire the lock.
        for ($i = 0; $i < 6; $i++) {
            $r->add(self::FW, $cat, "GET_LOCK handoff #$i succeeds after release", function () use ($db, $i) {
                $a = Connections::pdo($db);
                $b = Connections::pdo($db);
                $lock = 'mo_ddlsurf_handoff_' . $i . '_' . bin2hex(random_bytes(3));
                try {
                    $a->query("SELECT GET_LOCK('$lock', 1)")->fetchColumn();
                    $a->query("SELECT RELEASE_LOCK('$lock')")->fetchColumn();
                    $bGot = $b->query("SELECT GET_LOCK('$lock', 1)")->fetchColumn();
                    Support::assertEquals('1', (string) $bGot, 'B should acquire after A releases');
                    return ['detail' => 'B acquired after release', 'sql' => 'GET_LOCK after RELEASE_LOCK'];
                } finally {
                    try {
                        $b->query("SELECT RELEASE_LOCK('$lock')")->fetchColumn();
                    } catch (\Throwable) {
                    }
                }
            });
        }

        // IS_FREE_LOCK / IS_USED_LOCK reflect lock state.
        for ($i = 0; $i < 4; $i++) {
            $r->add(self::FW, $cat, "IS_FREE_LOCK reflects state #$i", function () use ($db, $i) {
                $a = Connections::pdo($db);
                $lock = 'mo_ddlsurf_free_' . $i . '_' . bin2hex(random_bytes(3));
                try {
                    $free1 = $a->query("SELECT IS_FREE_LOCK('$lock')")->fetchColumn();
                    Support::assertEquals('1', (string) $free1, 'lock should be free initially');
                    $a->query("SELECT GET_LOCK('$lock', 1)")->fetchColumn();
                    $b = Connections::pdo($db);
                    $free2 = $b->query("SELECT IS_FREE_LOCK('$lock')")->fetchColumn();
                    Support::assertEquals('0', (string) $free2, 'lock should be held now');
                    return ['detail' => "free before=$free1 after=$free2", 'sql' => 'IS_FREE_LOCK'];
                } finally {
                    try {
                        $a->query("SELECT RELEASE_LOCK('$lock')")->fetchColumn();
                    } catch (\Throwable) {
                    }
                }
            });
        }

        // Two transactions inserting the SAME unique key: the second one errors
        // on commit/insert rather than deadlocking. Bounded (no lock wait).
        for ($i = 0; $i < 6; $i++) {
            $r->add(self::FW, $cat, "concurrent duplicate-insert conflict #$i", function () use ($db, $i) {
                $a = Connections::pdo($db);
                $tn = Support::name('dl');
                try {
                    $a->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                    $a->exec("INSERT INTO `$tn` VALUES (1)");
                    $b = Connections::pdo($db);
                    $errno = null;
                    try {
                        $b->exec("INSERT INTO `$tn` VALUES (1)");
                    } catch (\PDOException $e) {
                        $errno = $e->errorInfo[1] ?? null;
                    }
                    Support::assert($errno !== null, 'concurrent duplicate insert should conflict');
                    return ['detail' => "second connection conflicted errno=$errno", 'sql' => 'concurrent duplicate insert'];
                } finally {
                    self::dropAll($a, [[$tn, false]]);
                }
            });
        }

        // RELEASE_ALL_LOCKS / releasing a not-held lock returns NULL.
        $r->add(self::FW, $cat, 'RELEASE_LOCK of not-held lock', function () use ($db) {
            $a = Connections::pdo($db);
            $lock = 'mo_ddlsurf_nothere_' . bin2hex(random_bytes(3));
            $r = $a->query("SELECT RELEASE_LOCK('$lock')")->fetchColumn();
            return ['detail' => 'RELEASE_LOCK(not held) = ' . var_export($r, true), 'sql' => 'RELEASE_LOCK not held'];
        });
    }

    // =====================================================================
    // Part B.5 — STATEMENT TIMEOUT (~15)
    // =====================================================================
    //
    // Bounded: every SLEEP is <= 1s and every timeout setting is short so the
    // suite cannot hang regardless of whether the timeout is enforced.

    private static function statementTimeout(Runner $r): void
    {
        $cat = 'err:timeout';
        $db = self::db();

        // SET_VAR(max_execution_time) optimizer hint over a SLEEP(1). MatrixOne
        // does NOT interrupt SLEEP, so we record the observed (non-)interruption.
        for ($i = 0; $i < 5; $i++) {
            $r->add(self::FW, $cat, "MAX_EXECUTION_TIME hint vs SLEEP(1) #$i", function () use ($db, $i) {
                $pdo = Connections::pdo($db);
                $t0 = microtime(true);
                $interrupted = false;
                $info = '';
                try {
                    $pdo->query("SELECT /*+ SET_VAR(max_execution_time=200) */ SLEEP(1)")->fetchColumn();
                } catch (\PDOException $e) {
                    $interrupted = true;
                    $info = self::errInfo($e);
                }
                $elapsed = microtime(true) - $t0;
                $note = $interrupted
                    ? "interrupted ($info) after " . round($elapsed, 2) . 's'
                    : 'NOT interrupted, ran full ' . round($elapsed, 2) . 's (timeout not enforced)';
                return ['detail' => $note, 'sql' => 'SET_VAR(max_execution_time=200) + SLEEP(1)'];
            });
        }

        // Session max_execution_time + SLEEP(1).
        for ($i = 0; $i < 5; $i++) {
            $r->add(self::FW, $cat, "session max_execution_time vs SLEEP(1) #$i", function () use ($db, $i) {
                $pdo = Connections::pdo($db);
                $set = false;
                try {
                    $pdo->exec("SET max_execution_time=200");
                    $set = true;
                } catch (\PDOException) {
                }
                $t0 = microtime(true);
                $interrupted = false;
                try {
                    $pdo->query("SELECT SLEEP(1)")->fetchColumn();
                } catch (\PDOException) {
                    $interrupted = true;
                }
                $elapsed = microtime(true) - $t0;
                $note = sprintf(
                    'set=%s, %s after %ss',
                    $set ? 'ok' : 'unsupported',
                    $interrupted ? 'interrupted' : 'completed',
                    round($elapsed, 2)
                );
                return ['detail' => $note, 'sql' => 'SET max_execution_time + SLEEP(1)'];
            });
        }

        // Just confirm SLEEP works and is bounded.
        for ($i = 0; $i < 5; $i++) {
            $r->add(self::FW, $cat, "SLEEP(0.2) completes promptly #$i", function () use ($db, $i) {
                $pdo = Connections::pdo($db);
                $t0 = microtime(true);
                $pdo->query("SELECT SLEEP(0.2)")->fetchColumn();
                $elapsed = microtime(true) - $t0;
                Support::assert($elapsed < 3.0, 'SLEEP(0.2) should be quick, took ' . round($elapsed, 2) . 's');
                return ['detail' => 'slept ' . round($elapsed, 2) . 's', 'sql' => 'SELECT SLEEP(0.2)'];
            });
        }
    }

    // =====================================================================
    // Part B.6 — RECONNECT (~15)
    // =====================================================================

    private static function reconnect(Runner $r): void
    {
        $cat = 'err:reconnect';
        $db = self::db();

        // A fresh connection after dropping the old one always works.
        for ($i = 0; $i < 5; $i++) {
            $r->add(self::FW, $cat, "fresh connection recovers #$i", function () use ($db, $i) {
                $c = Connections::pdo($db);
                $one = $c->query("SELECT 1")->fetchColumn();
                Support::assertEquals('1', (string) $one, 'first connection query');
                $c = null; // close the first connection
                $d = Connections::pdo($db);
                $two = $d->query("SELECT 2")->fetchColumn();
                Support::assertEquals('2', (string) $two, 'fresh connection query');
                return ['detail' => 'fresh connection serves queries after close', 'sql' => 'reopen Connections::pdo()'];
            });
        }

        // Distinct connection ids prove genuine reconnect.
        for ($i = 0; $i < 4; $i++) {
            $r->add(self::FW, $cat, "reconnect yields new connection id #$i", function () use ($db, $i) {
                $c = Connections::pdo($db);
                $id1 = $c->query("SELECT CONNECTION_ID()")->fetchColumn();
                $c = null;
                $d = Connections::pdo($db);
                $id2 = $d->query("SELECT CONNECTION_ID()")->fetchColumn();
                Support::assert($id1 !== $id2, "expected distinct connection ids, both = $id1");
                return ['detail' => "old id=$id1 new id=$id2", 'sql' => 'CONNECTION_ID() across reconnect'];
            });
        }

        // After an error on a connection, a fresh connection still works.
        for ($i = 0; $i < 3; $i++) {
            $r->add(self::FW, $cat, "fresh connection after error #$i", function () use ($db, $i) {
                $c = Connections::pdo($db);
                try {
                    $c->exec("SELCT bad syntax");
                } catch (\PDOException) {
                }
                $c = null;
                $d = Connections::pdo($db);
                $ok = $d->query("SELECT 42")->fetchColumn();
                Support::assertEquals('42', (string) $ok, 'fresh connection after prior error');
                return ['detail' => 'recovered with a brand-new connection', 'sql' => 'reconnect after error'];
            });
        }

        // KILL of a non-existent / other connection id behaviour (bounded).
        $r->add(self::FW, $cat, 'KILL of unknown connection id surfaces error', function () use ($db) {
            $pdo = Connections::pdo($db);
            $errno = null;
            $info = '';
            try {
                $pdo->exec("KILL 999999999");
            } catch (\PDOException $e) {
                $errno = $e->errorInfo[1] ?? null;
                $info = self::errInfo($e);
            }
            Support::assert($errno !== null, 'KILL of unknown id should error');
            return ['detail' => "KILL unknown id: $info", 'sql' => 'KILL <unknown id>'];
        });

        // A connection survives a self-KILL attempt by reconnecting fresh.
        $r->add(self::FW, $cat, 'reconnect after KILL works', function () use ($db) {
            $victim = Connections::pdo($db);
            $vid = $victim->query("SELECT CONNECTION_ID()")->fetchColumn();
            $killer = Connections::pdo($db);
            try {
                $killer->exec("KILL $vid");
            } catch (\PDOException) {
                // KILL may be unsupported; either way we reconnect below.
            }
            $fresh = Connections::pdo($db);
            $ok = $fresh->query("SELECT 7")->fetchColumn();
            Support::assertEquals('7', (string) $ok, 'fresh connection after KILL');
            return ['detail' => 'fresh connection serves queries after KILL attempt', 'sql' => 'reconnect after KILL'];
        });
    }
}
