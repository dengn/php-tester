<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Semantic-compatibility scenarios.
 *
 * Each case asserts the value MySQL 8 (its documented default behaviour)
 * would return. A failure therefore means MatrixOne diverges from MySQL
 * semantics — the subtle, silent kind of incompatibility that breaks an
 * application ported from MySQL even when no error is raised.
 */
final class BehaviorScenarios
{
    public static function register(Runner $r): void
    {
        self::expressions($r);
        self::collation($r);
        self::strictMode($r);
        self::autoIncrement($r);
        self::nullAndGrouping($r);
    }

    /** SELECT <expr> compared against MySQL's result. */
    private static function expr(Runner $r, string $cat, string $name, string $expr, string $mysql): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', $cat, $name, function () use ($db, $expr, $mysql) {
            $pdo = Connections::pdo($db);
            $got = $pdo->query("SELECT $expr")->fetchColumn();
            if ((string) $got !== (string) $mysql) {
                throw new \MoTest\BehaviorMismatch(sprintf(
                    'MySQL returns %s, MatrixOne returns %s',
                    var_export($mysql, true),
                    var_export($got, true)
                ));
            }
            return ['detail' => "= " . var_export($got, true) . " (matches MySQL)", 'sql' => "SELECT $expr"];
        });
    }

    private static function expressions(Runner $r): void
    {
        // Numeric / operator semantics
        self::expr($r, 'behavior:operator', 'DIV integer division', '5 DIV 2', '2');
        self::expr($r, 'behavior:operator', 'modulo operator', '7 % 3', '1');
        self::expr($r, 'behavior:operator', 'MOD function', 'MOD(7,3)', '1');
        self::expr($r, 'behavior:operator', 'pipe-pipe is logical OR', '1 || 0', '1');
        self::expr($r, 'behavior:operator', 'double-pipe truthy OR', '0 || 5', '1');
        self::expr($r, 'behavior:operator', 'AND operator', '1 AND 0', '0');
        self::expr($r, 'behavior:operator', 'XOR operator', '1 XOR 0', '1');
        self::expr($r, 'behavior:operator', 'NOT operator', 'NOT 0', '1');
        self::expr($r, 'behavior:operator', 'bitwise NOT width', '~0', '18446744073709551615');

        // Type coercion (MySQL implicit conversions)
        self::expr($r, 'behavior:coercion', 'int = string equality', '1 = "1"', '1');
        self::expr($r, 'behavior:coercion', 'numeric-prefixed string add', '"10" + 5', '15');
        self::expr($r, 'behavior:coercion', 'int vs float equality', '2 = 2.0', '1');
        self::expr($r, 'behavior:coercion', 'concat coerces numbers', 'CONCAT(1, 2)', '12');
        self::expr($r, 'behavior:coercion', 'boolean TRUE is 1', 'TRUE', '1');
        self::expr($r, 'behavior:coercion', 'boolean FALSE is 0', 'FALSE', '0');
        self::expr($r, 'behavior:coercion', 'hex literal is string', "0x41 = 'A'", '1');
        self::expr($r, 'behavior:coercion', 'trailing-text string add (warns, =15)', '"10abc" + 5', '15');
        self::expr($r, 'behavior:coercion', 'CAST non-numeric to UNSIGNED = 0', 'CAST("abc" AS UNSIGNED)', '0');

        // NULL semantics
        self::expr($r, 'behavior:null', 'NULL = NULL is NULL', 'ISNULL(NULL = NULL)', '1');
        self::expr($r, 'behavior:null', 'null-safe equal', 'NULL <=> NULL', '1');
        self::expr($r, 'behavior:null', 'GREATEST with NULL is NULL', 'ISNULL(GREATEST(1, NULL))', '1');
        self::expr($r, 'behavior:null', 'COALESCE skips NULL', 'COALESCE(NULL, NULL, 7)', '7');
        self::expr($r, 'behavior:null', 'NULL + 1 is NULL', 'ISNULL(NULL + 1)', '1');
        self::expr($r, 'behavior:null', 'CONCAT with NULL is NULL', 'ISNULL(CONCAT("a", NULL))', '1');

        // String length / multibyte
        self::expr($r, 'behavior:string', 'CHAR_LENGTH multibyte', 'CHAR_LENGTH("héllo")', '5');
        self::expr($r, 'behavior:string', 'LENGTH counts bytes', 'LENGTH("héllo")', '6');
    }

    private static function collation(Runner $r): void
    {
        // These all assert MySQL's default case/accent-insensitive behaviour.
        self::expr($r, 'behavior:collation', 'case-insensitive equality', "'abc' = 'ABC'", '1');
        self::expr($r, 'behavior:collation', 'trailing-space padded equality', "'a ' = 'a'", '1');
        self::expr($r, 'behavior:collation', 'case-insensitive LIKE', "'abc' LIKE 'ABC'", '1');
        self::expr($r, 'behavior:collation', 'case-insensitive INSTR', "INSTR('ABC', 'b')", '2');
        self::expr($r, 'behavior:collation', 'case-insensitive LOCATE', "LOCATE('B', 'abc')", '2');
        self::expr($r, 'behavior:collation', 'case-insensitive FIELD', "FIELD('B', 'a', 'b', 'c')", '2');
        self::expr($r, 'behavior:collation', 'case-insensitive IN', "'ABC' IN ('abc', 'def')", '1');
        self::expr($r, 'behavior:collation', 'accent-insensitive equality', "'café' = 'cafe'", '1');
        self::expr($r, 'behavior:collation', 'ORDER mixes case (ci)', "(SELECT GROUP_CONCAT(x ORDER BY x) FROM (SELECT 'B' x UNION ALL SELECT 'a') t)", 'a,B');

        // Column-level: case-insensitive WHERE match & UNIQUE collision (MySQL default).
        $db = Config::database('pdo');
        $r->add('PDO', 'behavior:collation', 'column WHERE is case-insensitive', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('col');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20))");
            try {
                $pdo->exec("INSERT INTO `$tn` VALUES ('alice')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE c='ALICE'")->fetchColumn();
                if ((string) $n !== '1') {
                    throw new \MoTest\BehaviorMismatch("MySQL ci collation matches 'ALICE' to 'alice' (1 row); MatrixOne returned $n");
                }
                return 'matched case-insensitively';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'behavior:collation', 'UNIQUE collides case-insensitively', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('col');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20) UNIQUE)");
            try {
                $pdo->exec("INSERT INTO `$tn` VALUES ('abc')");
                $collided = false;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES ('ABC')");
                } catch (\PDOException) {
                    $collided = true;
                }
                if (!$collided) {
                    throw new \MoTest\BehaviorMismatch("MySQL default ci collation rejects 'ABC' as duplicate of 'abc'; MatrixOne accepted both (case-sensitive unique)");
                }
                return 'unique collided case-insensitively';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'behavior:collation', 'default column collation is *_ci', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('col');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20))");
            try {
                $coll = $pdo->query("SELECT collation_name FROM information_schema.columns WHERE table_schema='$db' AND table_name='$tn' AND column_name='c'")->fetchColumn();
                if (!str_ends_with((string) $coll, '_ci')) {
                    throw new \MoTest\BehaviorMismatch("MySQL default column collation is case-insensitive (*_ci); MatrixOne used '$coll'");
                }
                return "collation=$coll";
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    /** Strict SQL mode should reject bad data on write (MatrixOne advertises STRICT_TRANS_TABLES). */
    private static function strictMode(Runner $r): void
    {
        $db = Config::database('pdo');
        $reject = function (string $name, string $coldef, string $badValue) use ($r, $db) {
            $r->add('PDO', 'behavior:strict', $name, function () use ($db, $coldef, $badValue) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('st');
                $pdo->exec("CREATE TABLE `$tn` (c $coldef)");
                try {
                    $rejected = false;
                    try {
                        $pdo->exec("INSERT INTO `$tn` (c) VALUES ($badValue)");
                    } catch (\PDOException) {
                        $rejected = true;
                    }
                    if (!$rejected) {
                        $stored = $pdo->query("SELECT c FROM `$tn` LIMIT 1")->fetchColumn();
                        throw new \MoTest\BehaviorMismatch("strict mode should reject $badValue; MatrixOne stored " . var_export($stored, true));
                    }
                    return 'rejected as expected';
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };
        $reject('reject over-length string', 'VARCHAR(5)', "'abcdefghij'");
        $reject('reject out-of-range tinyint', 'TINYINT', '999');
        $reject('reject invalid date', 'DATE', "'2026-13-40'");
        $reject('reject zero date', 'DATE NOT NULL', "'0000-00-00'");
        $reject('reject non-numeric into int', 'INT', "'notanumber'");
        $reject('reject overflow unsigned', 'INT UNSIGNED', '-1');
    }

    private static function autoIncrement(Runner $r): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', 'behavior:autoincrement', 'AUTO_INCREMENT continues after delete', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ai');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, n INT)");
            try {
                $pdo->exec("INSERT INTO `$tn`(n) VALUES (1),(2),(3)");
                $pdo->exec("DELETE FROM `$tn` WHERE id=3");
                $pdo->exec("INSERT INTO `$tn`(n) VALUES (4)");
                $max = $pdo->query("SELECT MAX(id) FROM `$tn`")->fetchColumn();
                // MySQL InnoDB: next id is 4 (does not reuse 3).
                if ((string) $max !== '4') {
                    throw new \MoTest\BehaviorMismatch("MySQL keeps incrementing (expected next id 4); got $max");
                }
                return "next id=$max";
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'behavior:autoincrement', 'LAST_INSERT_ID after multi-row insert', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ai');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, n INT)");
            try {
                $pdo->exec("INSERT INTO `$tn`(n) VALUES (1),(2),(3)");
                $lid = $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                // MySQL: LAST_INSERT_ID() = id of the FIRST inserted row (1).
                if ((string) $lid !== '1') {
                    throw new \MoTest\BehaviorMismatch("MySQL returns first row's id (1) for multi-row insert; got $lid");
                }
                return "last_insert_id=$lid";
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    /** Build a real (n, m) fixture where m has NULLs, then assert against MySQL semantics. */
    private static function withNullTable(Runner $r, string $name, callable $fn): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', 'behavior:aggregate', $name, function () use ($db, $fn) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ng');
            $pdo->exec("CREATE TABLE `$tn` (n INT, m INT)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1,NULL),(2,5),(3,NULL)");
            try {
                return $fn($pdo, $tn);
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function assertScalar(\PDO $pdo, string $sql, string $expected, string $what): string
    {
        $got = $pdo->query($sql)->fetchColumn();
        if ((string) $got !== (string) $expected) {
            throw new \MoTest\BehaviorMismatch("$what: MySQL=$expected, MatrixOne=" . var_export($got, true));
        }
        return "= $got";
    }

    private static function nullAndGrouping(Runner $r): void
    {
        self::withNullTable($r, 'COUNT(col) ignores NULL', fn ($p, $t) => self::assertScalar($p, "SELECT COUNT(m) FROM `$t`", '1', 'COUNT(m)'));
        self::withNullTable($r, 'COUNT(*) includes NULL rows', fn ($p, $t) => self::assertScalar($p, "SELECT COUNT(*) FROM `$t`", '3', 'COUNT(*)'));
        self::withNullTable($r, 'SUM ignores NULL', fn ($p, $t) => self::assertScalar($p, "SELECT SUM(m) FROM `$t`", '5', 'SUM(m)'));
        self::withNullTable($r, 'AVG ignores NULL', function ($p, $t) {
            $got = $p->query("SELECT AVG(m) FROM `$t`")->fetchColumn();
            if (abs((float) $got - 5.0) > 1e-9) {
                throw new \MoTest\BehaviorMismatch('AVG should ignore NULL (=5); got ' . var_export($got, true));
            }
            return "= $got";
        });
        self::withNullTable($r, 'SUM of empty set is NULL', fn ($p, $t) => self::assertScalar($p, "SELECT ISNULL(SUM(m)) FROM `$t` WHERE n>100", '1', 'SUM empty'));
        self::withNullTable($r, 'COUNT of empty set is 0', fn ($p, $t) => self::assertScalar($p, "SELECT COUNT(*) FROM `$t` WHERE n>100", '0', 'COUNT empty'));
        self::withNullTable($r, 'NULLs sort first ascending', function ($p, $t) {
            $first = $p->query("SELECT m FROM `$t` ORDER BY m ASC LIMIT 1")->fetchColumn();
            if ($first !== null) {
                throw new \MoTest\BehaviorMismatch('MySQL sorts NULL first ascending; got ' . var_export($first, true));
            }
            return 'NULL sorted first';
        });
        self::withNullTable($r, 'GROUP BY groups NULLs together', function ($p, $t) {
            $p->exec("INSERT INTO `$t` VALUES (4,NULL)");
            $n = $p->query("SELECT COUNT(*) FROM (SELECT m FROM `$t` GROUP BY m) g")->fetchColumn();
            // distinct m values: NULL and 5 => 2 groups
            if ((string) $n !== '2') {
                throw new \MoTest\BehaviorMismatch("MySQL groups all NULLs into one group (expected 2 groups); got $n");
            }
            return "$n groups";
        });
    }
}
