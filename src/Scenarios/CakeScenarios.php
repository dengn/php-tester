<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Cake\Database\Connection;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * CakePHP's database layer (cakephp/database) — the query builder + schema
 * reflection that powers the CakePHP ORM.
 *
 * CakePHP auto-introspects table schemas via describe(), so any gap in
 * information_schema directly affects every ORM operation.
 */
final class CakeScenarios
{
    public static function register(Runner $r): void
    {
        self::connection($r);
        self::schema($r);
        self::queryBuilder($r);
        self::types($r);
        self::transactions($r);
    }

    private static function withTable(Runner $r, string $cat, string $name, callable $fn): void
    {
        $r->add('CakePHP', $cat, $name, function () use ($fn) {
            $conn = Connections::cake();
            $tn = Support::name('ck');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50), age INT, city VARCHAR(50))");
            $conn->execute("INSERT INTO `$tn` (name,age,city) VALUES ('alice',30,'NY'),('bob',25,'LA'),('carol',40,'NY')");
            try {
                return $fn($conn, $tn);
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function connection(Runner $r): void
    {
        $r->add('CakePHP', 'connection', 'connect + version', function () {
            $conn = Connections::cake();
            $v = $conn->execute('SELECT version()')->fetch()[0];
            Support::assert(str_contains((string) $v, 'MatrixOne'), 'unexpected version');
            return $v;
        });
        $r->add('CakePHP', 'connection', 'execute raw select', function () {
            $conn = Connections::cake();
            $row = $conn->execute('SELECT 1 + 1 AS s')->fetch('assoc');
            Support::assertEquals(2, $row['s'], 'raw select');
            return 'ok';
        });
    }

    private static function schema(Runner $r): void
    {
        $r->add('CakePHP', 'schema', 'getSchemaCollection listTables', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $tables = $conn->getSchemaCollection()->listTables();
                Support::assert(in_array($tn, $tables, true), 'listTables missing created table');
                return count($tables) . ' tables';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', 'schema', 'describe() table reflection', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50) NOT NULL, age INT DEFAULT 0)");
            try {
                $desc = $conn->getSchemaCollection()->describe($tn);
                $cols = $desc->columns();
                Support::assert(in_array('name', $cols, true), 'describe missing name column');
                return implode(',', $cols);
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', 'schema', 'create table via TableSchema', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck');
            $schema = new \Cake\Database\Schema\TableSchema($tn);
            $schema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true, 'null' => false])
                ->addColumn('title', ['type' => 'string', 'length' => 100])
                ->addColumn('amount', ['type' => 'decimal', 'precision' => 10, 'scale' => 2])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            try {
                foreach ($schema->createSql($conn) as $sql) {
                    $conn->execute($sql);
                }
                Support::assert(in_array($tn, $conn->getSchemaCollection()->listTables(), true), 'table not created');
                return 'created via TableSchema';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function queryBuilder(Runner $r): void
    {
        self::withTable($r, 'query-builder', 'select where + order', function (Connection $conn, string $t) {
            $q = $conn->selectQuery()->select(['name', 'age'])->from($t)
                ->where(['age >' => 26])->orderBy(['age' => 'DESC']);
            $rows = $q->all();
            Support::assertEquals(2, count($rows), 'where count');
            Support::assertEquals('carol', $rows[0]['name'], 'order');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'select limit + offset', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->orderBy(['id' => 'ASC'])->limit(1)->offset(1)->all();
            Support::assertEquals('bob', $rows[0]['name'], 'limit/offset');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'where IN', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['*'])->from($t)
                ->where(['name IN' => ['alice', 'bob']])->all();
            Support::assertEquals(2, count($rows), 'where IN');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'where LIKE', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['*'])->from($t)
                ->where(['city LIKE' => 'N%'])->all();
            Support::assertEquals(2, count($rows), 'where LIKE');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'aggregate count via func', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['c' => $q->func()->count('*')])->from($t);
            $row = $q->all()[0];
            Support::assertEquals(3, $row['c'], 'count func');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'group by + having', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['city', 'c' => $q->func()->count('*')])->from($t)
                ->groupBy(['city'])->having(['count(*) >' => 1]);
            $rows = $q->all();
            Support::assertEquals(1, count($rows), 'group/having');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'self join', function (Connection $conn, string $t) {
            $q = $conn->selectQuery()->select(['a.name'])->from(['a' => $t])
                ->innerJoin(['b' => $t], ['a.city = b.city', 'a.id < b.id']);
            $rows = $q->all();
            return 'rows=' . count($rows);
        });
        self::withTable($r, 'query-builder', 'insert builder', function (Connection $conn, string $t) {
            $conn->insertQuery()->insert(['name', 'age'])->into($t)
                ->values(['name' => 'dave', 'age' => 50])->execute();
            $n = $conn->execute("SELECT COUNT(*) FROM `$t`")->fetch()[0];
            Support::assertEquals(4, $n, 'insert builder');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'update builder', function (Connection $conn, string $t) {
            $conn->updateQuery()->update($t)->set(['age' => 99])->where(['name' => 'bob'])->execute();
            $age = $conn->execute("SELECT age FROM `$t` WHERE name='bob'")->fetch()[0];
            Support::assertEquals(99, $age, 'update builder');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'delete builder', function (Connection $conn, string $t) {
            $conn->deleteQuery()->delete()->from($t)->where(['name' => 'alice'])->execute();
            $n = $conn->execute("SELECT COUNT(*) FROM `$t`")->fetch()[0];
            Support::assertEquals(2, $n, 'delete builder');
            return 'ok';
        });
        self::withTable($r, 'query-builder', 'identifier quoting', function (Connection $conn, string $t) {
            // force identifier quoting on the driver
            $conn->getDriver()->enableAutoQuoting(true);
            try {
                $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['age >' => 0])->all();
                return 'rows=' . count($rows);
            } finally {
                $conn->getDriver()->enableAutoQuoting(false);
            }
        });
    }

    private static function types(Runner $r): void
    {
        $cases = [
            ['integer', 'INT', 'integer', 12345, '12345'],
            ['biginteger', 'BIGINT', 'biginteger', '9007199254740993', '9007199254740993'],
            ['decimal', 'DECIMAL(10,2)', 'decimal', '123.45', '123.45'],
            ['float', 'DOUBLE', 'float', 1.5, '1.5'],
            ['string', 'VARCHAR(50)', 'string', 'hello', 'hello'],
            ['text', 'TEXT', 'text', 'longtext', 'longtext'],
            ['boolean', 'TINYINT(1)', 'boolean', true, '1'],
            ['date', 'DATE', 'date', new \DateTime('2026-06-23'), '2026-06-23'],
            ['datetime', 'DATETIME', 'datetime', new \DateTime('2026-06-23 10:20:30'), '2026-06-23 10:20:30'],
            ['json', 'JSON', 'json', ['a' => 1, 'b' => 2], null],
        ];
        foreach ($cases as [$label, $colType, $cakeType, $value, $expect]) {
            $r->add('CakePHP', 'type', "type $label", function () use ($colType, $cakeType, $value, $expect, $label) {
                $conn = Connections::cake();
                $tn = Support::name('ckt');
                $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $colType)");
                try {
                    $conn->execute(
                        "INSERT INTO `$tn` (id, c) VALUES (:id, :c)",
                        ['id' => 1, 'c' => $value],
                        ['c' => $cakeType]
                    );
                    $raw = $conn->execute("SELECT c FROM `$tn` WHERE id=1")->fetch()[0];
                    if ($expect !== null) {
                        Support::assertEquals($expect, $raw, "$label round-trip");
                    } else {
                        Support::assert($raw !== null, "$label stored null");
                    }
                    return 'round-trip ok';
                } finally {
                    $conn->execute("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }

    private static function transactions(Runner $r): void
    {
        $r->add('CakePHP', 'transaction', 'transactional() commit', function () {
            $conn = Connections::cake();
            $tn = Support::name('cktx');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->transactional(function (Connection $c) use ($tn) {
                    $c->execute("INSERT INTO `$tn` VALUES (1)");
                    return true;
                });
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(1, $n, 'tx commit');
                return 'ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('CakePHP', 'transaction', 'manual begin/rollback', function () {
            $conn = Connections::cake();
            $tn = Support::name('cktx');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->begin();
                $conn->execute("INSERT INTO `$tn` VALUES (1)");
                $conn->rollback();
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(0, $n, 'rollback');
                return 'ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }
}
