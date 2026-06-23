<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * MySQL binary prepared-statement protocol (COM_STMT_PREPARE / EXECUTE).
 *
 * Connections::pdo() runs with PDO::ATTR_EMULATE_PREPARES = false, so every
 * prepare() round-trips a real server-side prepared statement. This is where
 * many MySQL-compatible engines diverge: typed parameter encoding, LIMIT
 * placeholders, statement reuse, and which statement kinds can be prepared.
 */
final class PreparedStatementScenarios
{
    public static function register(Runner $r): void
    {
        self::typedBinding($r);
        self::paramEdgeCases($r);
        self::nonPreparableStatements($r);
        self::metadataAndReuse($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    private static function typedBinding(Runner $r): void
    {
        $cases = [
            ['INT + PARAM_INT', 'INT', 2147483647, \PDO::PARAM_INT, '2147483647'],
            ['BIGINT + PARAM_INT', 'BIGINT', 9007199254740991, \PDO::PARAM_INT, '9007199254740991'],
            ['BIGINT string + PARAM_STR', 'BIGINT', '9223372036854775807', \PDO::PARAM_STR, '9223372036854775807'],
            ['TINYINT + PARAM_INT', 'TINYINT', -128, \PDO::PARAM_INT, '-128'],
            ['DECIMAL + PARAM_STR', 'DECIMAL(12,4)', '1234.5678', \PDO::PARAM_STR, '1234.5678'],
            ['DOUBLE + PARAM_STR', 'DOUBLE', '3.141592653589', \PDO::PARAM_STR, null],
            ['VARCHAR + PARAM_STR', 'VARCHAR(100)', 'prepared value', \PDO::PARAM_STR, 'prepared value'],
            ['VARCHAR unicode', 'VARCHAR(100)', 'café 🚀 测试', \PDO::PARAM_STR, 'café 🚀 测试'],
            ['CHAR + PARAM_STR', 'CHAR(10)', 'fixed', \PDO::PARAM_STR, 'fixed'],
            ['TEXT + PARAM_STR', 'TEXT', str_repeat('z', 2000), \PDO::PARAM_STR, null],
            ['DATE + PARAM_STR', 'DATE', '2026-06-23', \PDO::PARAM_STR, '2026-06-23'],
            ['DATETIME + PARAM_STR', 'DATETIME', '2026-06-23 12:34:56', \PDO::PARAM_STR, '2026-06-23 12:34:56'],
            ['TIME + PARAM_STR', 'TIME', '12:34:56', \PDO::PARAM_STR, '12:34:56'],
            ['JSON + PARAM_STR', 'JSON', '{"k": 1}', \PDO::PARAM_STR, null],
            ['BOOL + PARAM_BOOL', 'TINYINT(1)', true, \PDO::PARAM_BOOL, '1'],
            ['BLOB + PARAM_LOB', 'BLOB', 'binary-ish data', \PDO::PARAM_LOB, null],
            ['NULL + PARAM_NULL', 'INT', null, \PDO::PARAM_NULL, null],
            ['VARBINARY + PARAM_STR', 'VARBINARY(64)', "abc\x00def", \PDO::PARAM_STR, null],
        ];
        foreach ($cases as [$name, $coltype, $value, $paramType, $expect]) {
            $r->add('PDO', 'prepared:typed-bind', $name, function () use ($name, $coltype, $value, $paramType, $expect) {
                $pdo = Connections::pdo(self::db());
                $tn = Support::name('ps');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $coltype)");
                try {
                    $ins = $pdo->prepare("INSERT INTO `$tn` (id, c) VALUES (1, ?)");
                    $ins->bindValue(1, $value, $paramType);
                    $ins->execute();
                    $sel = $pdo->prepare("SELECT c FROM `$tn` WHERE id = ?");
                    $sel->execute([1]);
                    $got = $sel->fetchColumn();
                    if ($value === null) {
                        Support::assert($got === null, 'expected NULL round-trip');
                    } elseif ($expect !== null) {
                        Support::assertEquals($expect, $got, "$name round-trip");
                    } else {
                        Support::assert($got !== null && $got !== false, 'value not stored');
                    }
                    return 'bound + read';
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }

    private static function paramEdgeCases(Runner $r): void
    {
        $with = function (string $name, callable $fn) use ($r) {
            $r->add('PDO', 'prepared:param', $name, function () use ($fn) {
                $pdo = Connections::pdo(self::db());
                $tn = Support::name('ps');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, n INT, s VARCHAR(50))");
                $stmt = $pdo->prepare("INSERT INTO `$tn`(n, s) VALUES (?, ?)");
                foreach ([[1, 'a'], [2, 'b'], [3, 'c'], [4, 'd'], [5, 'e']] as $row) {
                    $stmt->execute($row);
                }
                try {
                    return $fn($pdo, $tn);
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };

        $with('positional ? params in WHERE', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT s FROM `$t` WHERE n = ?");
            $st->execute([3]);
            Support::assertEquals('c', $st->fetchColumn(), 'positional where');
            return 'ok';
        });
        $with('named :params in WHERE', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT s FROM `$t` WHERE n = :n");
            $st->execute(['n' => 2]);
            Support::assertEquals('b', $st->fetchColumn(), 'named where');
            return 'ok';
        });
        $with('LIMIT with bound param', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT n FROM `$t` ORDER BY n LIMIT ?");
            $st->bindValue(1, 2, \PDO::PARAM_INT);
            $st->execute();
            Support::assertEquals(2, count($st->fetchAll()), 'LIMIT ? count');
            return 'ok';
        });
        $with('LIMIT + OFFSET bound params', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT n FROM `$t` ORDER BY n LIMIT ? OFFSET ?");
            $st->bindValue(1, 2, \PDO::PARAM_INT);
            $st->bindValue(2, 1, \PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_COLUMN);
            Support::assertEquals('2', (string) $rows[0], 'LIMIT/OFFSET ?');
            return 'ok';
        });
        $with('IN clause with multiple binds', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE n IN (?, ?, ?)");
            $st->execute([1, 3, 5]);
            Support::assertEquals('3', $st->fetchColumn(), 'IN binds');
            return 'ok';
        });
        $with('100 bound parameters', function (\PDO $pdo, string $t) {
            $ph = implode(',', array_fill(0, 100, '?'));
            $vals = range(1, 100);
            $st = $pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE n IN ($ph)");
            $st->execute($vals);
            Support::assertEquals('5', $st->fetchColumn(), '100 params');
            return 'ok';
        });
        $with('bound param in expression', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT n + ? FROM `$t` WHERE n = ?");
            $st->execute([10, 1]);
            Support::assertEquals('11', $st->fetchColumn(), 'param in expr');
            return 'ok';
        });
        $with('prepared UPDATE with params', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("UPDATE `$t` SET s = ? WHERE n = ?");
            $st->execute(['updated', 2]);
            Support::assertEquals(1, $st->rowCount(), 'update rowCount');
            return 'ok';
        });
        $with('prepared DELETE with params', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("DELETE FROM `$t` WHERE n = ?");
            $st->execute([4]);
            Support::assertEquals(1, $st->rowCount(), 'delete rowCount');
            return 'ok';
        });
        $with('execute reused 3x with different values', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT s FROM `$t` WHERE n = ?");
            $out = [];
            foreach ([1, 3, 5] as $v) {
                $st->execute([$v]);
                $out[] = $st->fetchColumn();
            }
            Support::assertEquals('a,c,e', implode(',', $out), 'reuse executes');
            return 'ok';
        });
        $with('bindParam by reference', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT s FROM `$t` WHERE n = :n");
            $n = 0;
            $st->bindParam(':n', $n, \PDO::PARAM_INT);
            $n = 5;
            $st->execute();
            Support::assertEquals('e', $st->fetchColumn(), 'bindParam ref');
            return 'ok';
        });
        $with('fetch FETCH_ASSOC from prepared', function (\PDO $pdo, string $t) {
            $st = $pdo->prepare("SELECT n, s FROM `$t` WHERE n = ?");
            $st->execute([1]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            Support::assert(isset($row['n'], $row['s']), 'assoc keys');
            return 'ok';
        });
    }

    private static function nonPreparableStatements(Runner $r): void
    {
        // Statements that often cannot be run through COM_STMT_PREPARE.
        $stmts = [
            'prepare DESCRIBE' => 'DESCRIBE information_schema.tables',
            'prepare EXPLAIN' => 'EXPLAIN SELECT 1',
            'prepare SHOW COLLATION' => 'SHOW COLLATION',
            'prepare SHOW WARNINGS' => 'SHOW WARNINGS',
            'prepare SHOW TABLES' => 'SHOW TABLES',
            'prepare SET (session var)' => 'SET @x = 1',
            'prepare SHOW VARIABLES LIKE' => "SHOW VARIABLES LIKE 'version'",
        ];
        foreach ($stmts as $name => $sql) {
            $r->add('PDO', 'prepared:non-preparable', $name, function () use ($sql) {
                $pdo = Connections::pdo(self::db());
                $st = $pdo->prepare($sql);
                $st->execute();
                $st->fetchAll();
                return ['detail' => 'prepared + executed', 'sql' => $sql];
            });
        }
    }

    private static function metadataAndReuse(Runner $r): void
    {
        $r->add('PDO', 'prepared:metadata', 'columnCount on prepared SELECT', function () {
            $pdo = Connections::pdo(self::db());
            $tn = Support::name('ps');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b VARCHAR(10), c DATE)");
            try {
                $st = $pdo->prepare("SELECT a, b, c FROM `$tn`");
                $st->execute();
                Support::assertEquals(3, $st->columnCount(), 'columnCount');
                return 'ok';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'prepared:metadata', 'getColumnMeta on prepared SELECT', function () {
            $pdo = Connections::pdo(self::db());
            $tn = Support::name('ps');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b VARCHAR(10))");
            $pdo->exec("INSERT INTO `$tn` VALUES (1,'x')");
            try {
                $st = $pdo->prepare("SELECT a, b FROM `$tn`");
                $st->execute();
                $meta = $st->getColumnMeta(0);
                Support::assert(is_array($meta) && isset($meta['name']), 'no column meta');
                return $meta['name'] . ':' . ($meta['native_type'] ?? '?');
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'prepared:metadata', 'rowCount after prepared INSERT', function () {
            $pdo = Connections::pdo(self::db());
            $tn = Support::name('ps');
            $pdo->exec("CREATE TABLE `$tn` (id INT)");
            try {
                $st = $pdo->prepare("INSERT INTO `$tn` VALUES (?),(?),(?)");
                $st->execute([1, 2, 3]);
                Support::assertEquals(3, $st->rowCount(), 'insert rowCount');
                return 'ok';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }
}
