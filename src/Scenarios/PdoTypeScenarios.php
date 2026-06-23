<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Ground-truth data-type coverage executed through raw PDO.
 *
 * For each type we test (a) that the column can be declared and (b) that a
 * representative value survives an INSERT -> SELECT round-trip with the value
 * MySQL would preserve. Divergences here are pure MatrixOne behaviour,
 * independent of any ORM.
 */
final class PdoTypeScenarios
{
    public static function register(Runner $r): void
    {
        $db = Config::database('pdo');
        $types = self::types();

        foreach ($types as $t) {
            $col = $t['col'];
            $label = $t['name'];

            // (a) DDL declaration
            $r->add('PDO', 'datatype:ddl', "declare $label", function () use ($db, $col) {
                $pdo = Connections::pdo($db);
                $tbl = Support::name('dt');
                $sql = "CREATE TABLE `$tbl` (id INT PRIMARY KEY, c $col)";
                try {
                    $pdo->exec($sql);
                    return ['detail' => "declared as $col", 'sql' => $sql];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
                }
            });

            // (b) value round-trip (skip pure-DDL types with no simple literal)
            if (array_key_exists('value', $t)) {
                $r->add('PDO', 'datatype:roundtrip', "round-trip $label", function () use ($db, $col, $t, $label) {
                    $pdo = Connections::pdo($db);
                    $tbl = Support::name('dt');
                    $sql = "CREATE TABLE `$tbl` (id INT PRIMARY KEY, c $col)";
                    $pdo->exec($sql);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO `$tbl` (id, c) VALUES (1, ?)");
                        $stmt->execute([$t['value']]);
                        $got = $pdo->query("SELECT c FROM `$tbl` WHERE id=1")->fetchColumn();
                        self::compare($t, $got, $label);
                        return ['detail' => "stored " . self::repr($t['value']) . " -> " . self::repr($got), 'sql' => $sql];
                    } finally {
                        $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
                    }
                });
            }
        }
    }

    private static function compare(array $t, mixed $got, string $label): void
    {
        $mode = $t['compare'] ?? 'exact';
        $expect = $t['expect'] ?? $t['value'];
        switch ($mode) {
            case 'numeric':
                Support::assert($got !== null && abs((float) $got - (float) $expect) < 1e-6, "$label numeric round-trip drifted: expected $expect got " . var_export($got, true));
                break;
            case 'notnull':
                Support::assert($got !== null && $got !== '', "$label stored as empty/null");
                break;
            case 'prefix':
                Support::assert(is_string($got) && str_starts_with((string) $got, (string) $expect), "$label expected prefix $expect got " . var_export($got, true));
                break;
            case 'exact':
            default:
                Support::assertEquals($expect, $got, "$label round-trip");
        }
    }

    private static function repr(mixed $v): string
    {
        if (is_string($v) && strlen($v) > 24) {
            return '"' . substr($v, 0, 24) . '…"';
        }
        return var_export($v, true);
    }

    /** @return array<int, array<string, mixed>> */
    private static function types(): array
    {
        return [
            // Integers
            ['name' => 'TINYINT', 'col' => 'TINYINT', 'value' => 127, 'expect' => '127'],
            ['name' => 'TINYINT negative', 'col' => 'TINYINT', 'value' => -128, 'expect' => '-128'],
            ['name' => 'TINYINT UNSIGNED', 'col' => 'TINYINT UNSIGNED', 'value' => 255, 'expect' => '255'],
            ['name' => 'SMALLINT', 'col' => 'SMALLINT', 'value' => 32767, 'expect' => '32767'],
            ['name' => 'SMALLINT UNSIGNED', 'col' => 'SMALLINT UNSIGNED', 'value' => 65535, 'expect' => '65535'],
            ['name' => 'MEDIUMINT', 'col' => 'MEDIUMINT', 'value' => 8388607, 'expect' => '8388607'],
            ['name' => 'INT', 'col' => 'INT', 'value' => 2147483647, 'expect' => '2147483647'],
            ['name' => 'INT UNSIGNED', 'col' => 'INT UNSIGNED', 'value' => 4294967295, 'expect' => '4294967295'],
            ['name' => 'INTEGER alias', 'col' => 'INTEGER', 'value' => 12345, 'expect' => '12345'],
            ['name' => 'BIGINT', 'col' => 'BIGINT', 'value' => '9223372036854775807', 'expect' => '9223372036854775807'],
            ['name' => 'BIGINT UNSIGNED', 'col' => 'BIGINT UNSIGNED', 'value' => '18446744073709551615', 'expect' => '18446744073709551615'],
            ['name' => 'BOOL', 'col' => 'BOOL', 'value' => 1, 'expect' => '1'],
            ['name' => 'BOOLEAN', 'col' => 'BOOLEAN', 'value' => 0, 'expect' => '0'],
            ['name' => 'BIT(8)', 'col' => 'BIT(8)', 'value' => 5, 'compare' => 'notnull'],

            // Fixed / floating point
            ['name' => 'DECIMAL(10,2)', 'col' => 'DECIMAL(10,2)', 'value' => '1234.56', 'expect' => '1234.56'],
            ['name' => 'DECIMAL(38,10)', 'col' => 'DECIMAL(38,10)', 'value' => '12345678901234567890.1234567890', 'expect' => '12345678901234567890.1234567890'],
            ['name' => 'NUMERIC alias', 'col' => 'NUMERIC(8,3)', 'value' => '99.125', 'expect' => '99.125'],
            ['name' => 'FLOAT', 'col' => 'FLOAT', 'value' => 3.5, 'expect' => 3.5, 'compare' => 'numeric'],
            ['name' => 'FLOAT(7,4)', 'col' => 'FLOAT(7,4)', 'value' => 3.1416, 'expect' => 3.1416, 'compare' => 'numeric'],
            ['name' => 'DOUBLE', 'col' => 'DOUBLE', 'value' => 2.718281828, 'expect' => 2.718281828, 'compare' => 'numeric'],
            ['name' => 'REAL', 'col' => 'REAL', 'value' => 1.25, 'expect' => 1.25, 'compare' => 'numeric'],

            // Character / binary
            ['name' => 'CHAR(10)', 'col' => 'CHAR(10)', 'value' => 'abc', 'expect' => 'abc'],
            ['name' => 'VARCHAR(255)', 'col' => 'VARCHAR(255)', 'value' => 'hello world', 'expect' => 'hello world'],
            ['name' => 'VARCHAR utf8 emoji', 'col' => 'VARCHAR(255)', 'value' => 'café 🚀 测试', 'expect' => 'café 🚀 测试'],
            ['name' => 'BINARY(4)', 'col' => 'BINARY(4)', 'value' => 'ab', 'compare' => 'prefix', 'expect' => 'ab'],
            ['name' => 'VARBINARY(64)', 'col' => 'VARBINARY(64)', 'value' => "bin\x01\x02", 'compare' => 'notnull'],
            ['name' => 'TINYTEXT', 'col' => 'TINYTEXT', 'value' => 'tiny', 'expect' => 'tiny'],
            ['name' => 'TEXT', 'col' => 'TEXT', 'value' => str_repeat('x', 1000), 'expect' => str_repeat('x', 1000)],
            ['name' => 'MEDIUMTEXT', 'col' => 'MEDIUMTEXT', 'value' => 'medium', 'expect' => 'medium'],
            ['name' => 'LONGTEXT', 'col' => 'LONGTEXT', 'value' => 'long', 'expect' => 'long'],
            ['name' => 'TINYBLOB', 'col' => 'TINYBLOB', 'value' => 'tb', 'compare' => 'notnull'],
            ['name' => 'BLOB', 'col' => 'BLOB', 'value' => 'blobdata', 'compare' => 'notnull'],
            ['name' => 'MEDIUMBLOB', 'col' => 'MEDIUMBLOB', 'value' => 'mb', 'compare' => 'notnull'],
            ['name' => 'LONGBLOB', 'col' => 'LONGBLOB', 'value' => 'lb', 'compare' => 'notnull'],

            // Enumerations
            ['name' => 'ENUM', 'col' => "ENUM('low','mid','high')", 'value' => 'mid', 'expect' => 'mid'],
            ['name' => 'SET', 'col' => "SET('a','b','c')", 'value' => 'a,c', 'compare' => 'notnull'],

            // Date / time
            ['name' => 'DATE', 'col' => 'DATE', 'value' => '2026-06-23', 'expect' => '2026-06-23'],
            ['name' => 'DATETIME', 'col' => 'DATETIME', 'value' => '2026-06-23 12:34:56', 'expect' => '2026-06-23 12:34:56'],
            ['name' => 'DATETIME(6)', 'col' => 'DATETIME(6)', 'value' => '2026-06-23 12:34:56.123456', 'expect' => '2026-06-23 12:34:56.123456'],
            ['name' => 'TIMESTAMP', 'col' => 'TIMESTAMP', 'value' => '2026-06-23 12:34:56', 'compare' => 'notnull'],
            ['name' => 'TIME', 'col' => 'TIME', 'value' => '12:34:56', 'expect' => '12:34:56'],
            ['name' => 'TIME(6)', 'col' => 'TIME(6)', 'value' => '12:34:56.123456', 'compare' => 'notnull'],
            ['name' => 'YEAR', 'col' => 'YEAR', 'value' => 2026, 'expect' => '2026'],

            // Structured / special
            ['name' => 'JSON object', 'col' => 'JSON', 'value' => '{"a": 1, "b": [2, 3]}', 'compare' => 'notnull'],
            ['name' => 'UUID', 'col' => 'UUID', 'value' => '6d1b1f00-0000-4000-8000-000000000000', 'compare' => 'notnull'],
            ['name' => 'VECF32(3)', 'col' => 'VECF32(3)', 'value' => '[1, 2, 3]', 'compare' => 'notnull'],
            ['name' => 'VECF64(3)', 'col' => 'VECF64(3)', 'value' => '[1.5, 2.5, 3.5]', 'compare' => 'notnull'],
        ];
    }
}
