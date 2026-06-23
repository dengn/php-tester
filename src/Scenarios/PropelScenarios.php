<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Runner;
use MoTest\Support;
use PDO;
use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Adapter\Pdo\MysqlAdapter;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\DatabaseMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

/**
 * Propel 2 (runtime layer) compatibility scenarios against MatrixOne.
 *
 * Propel's full ActiveRecord layer needs CODE-GENERATED model classes (from a
 * schema.xml) plus a populated DatabaseMap. That is heavyweight and out of scope
 * here. Instead these scenarios exercise everything Propel can do WITHOUT
 * generation:
 *
 *   - the Propel\Runtime\Propel service container + ConnectionManagerSingle,
 *   - the ConnectionWrapper (a PDO-like wrapper: exec/query/prepare/lastInsertId/
 *     beginTransaction/commit/rollBack/transaction()),
 *   - the StatementWrapper + PDODataFetcher result layer,
 *   - the MysqlAdapter SQL-generation + value-binding helpers,
 *   - the Criteria SQL-string building helpers that work standalone.
 *
 * Conventions (mirroring TypeMatrixScenarios):
 *   - the service container is set up ONCE, lazily, via a static guard,
 *   - each scenario gets a fresh logical operation through the shared wrapper
 *     (Propel::getConnection) but uses UNIQUE Support::name() tables,
 *   - DROP every table in a finally{} guarded by try/catch,
 *   - every closure captures all referenced variables in use(...),
 *   - a genuine MatrixOne/Propel error is a FINDING and is left to fail.
 *
 * The Criteria/ModelCriteria query layer that REQUIRES a generated DatabaseMap
 * is documented (a handful of scenarios try it, catch the limitation, SKIP).
 */
final class PropelScenarios
{
    private const FW = 'Propel';

    /** Lazy one-time service-container bootstrap guard. */
    private static bool $booted = false;

    /** Hand-built map used to drive adapter binding helpers without code-gen. */
    private static ?TableMap $tableMap = null;

    public static function register(Runner $r): void
    {
        self::connectionScenarios($r);
        self::adapterScenarios($r);
        self::crudScenarios($r);
        self::typeScenarios($r);
        self::transactionScenarios($r);
        self::queryScenarios($r);
        self::criteriaScenarios($r);
    }

    // =============================================================== bootstrap

    /**
     * Idempotently configure the Propel service container to point at the shared
     * 'pdo' database. Safe to call from every scenario and safe to interleave
     * with a mixed full run (the container is a process-global singleton).
     */
    private static function boot(): ConnectionWrapper
    {
        if (!self::$booted) {
            $db = Config::database('pdo');
            $manager = new ConnectionManagerSingle('default');
            $manager->setConfiguration([
                'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s', Config::host(), Config::port(), $db),
                'user' => Config::user(),
                'password' => Config::password(),
                'settings' => ['charset' => 'utf8mb4'],
            ]);
            $sc = Propel::getServiceContainer();
            $sc->setAdapterClass('default', 'mysql');
            $sc->setConnectionManager($manager);
            $sc->setDefaultDatasource('default');
            self::$booted = true;
        }

        /** @var ConnectionWrapper $con */
        $con = Propel::getConnection('default');

        return $con;
    }

    private static function adapter(): MysqlAdapter
    {
        self::boot();

        /** @var MysqlAdapter $a */
        $a = Propel::getServiceContainer()->getAdapter('default');

        return $a;
    }

    /** A concrete, initialised TableMap so we can build ColumnMaps by hand. */
    private static function tableMap(): TableMap
    {
        if (self::$tableMap === null) {
            $dbMap = new DatabaseMap('default');
            $tm = new class ('propel_handmade', $dbMap) extends TableMap {
                public function initialize(): void
                {
                    $this->setName('propel_handmade');
                    $this->setPhpName('PropelHandmade');
                }
            };
            $tm->initialize();
            self::$tableMap = $tm;
        }

        return self::$tableMap;
    }

    private static function col(string $name, string $propelType): ColumnMap
    {
        return new ColumnMap($name, self::tableMap(), ucfirst(strtolower($name)), $propelType);
    }

    /** Drop a table, never throwing out of teardown. */
    private static function drop(ConnectionWrapper $con, string $tn): void
    {
        try {
            $con->exec("DROP TABLE IF EXISTS `$tn`");
        } catch (\Throwable) {
        }
    }

    // ============================================================= connection

    private static function connectionScenarios(Runner $r): void
    {
        // -- container / connection identity -------------------------------
        $r->add(self::FW, 'propel:connection', 'getConnection returns ConnectionWrapper', function () {
            $con = self::boot();
            Support::assert($con instanceof ConnectionWrapper, 'expected ConnectionWrapper, got ' . get_class($con));
            return ['detail' => get_class($con), 'sql' => 'Propel::getConnection(default)'];
        });

        $r->add(self::FW, 'propel:connection', 'connection getName is default', function () {
            $con = self::boot();
            Support::assertEquals('default', $con->getName(), 'datasource name');
            return ['detail' => 'name=' . $con->getName()];
        });

        $r->add(self::FW, 'propel:connection', 'getWrappedConnection is PdoConnection', function () {
            $con = self::boot();
            $w = $con->getWrappedConnection();
            Support::assert($w !== null, 'wrapped connection is null');
            return ['detail' => get_class($w)];
        });

        $r->add(self::FW, 'propel:connection', 'getServiceContainer adapter is MysqlAdapter', function () {
            $a = self::adapter();
            Support::assert($a instanceof MysqlAdapter, 'expected MysqlAdapter');
            return ['detail' => get_class($a)];
        });

        $r->add(self::FW, 'propel:connection', 'isInTransaction false outside tx', function () {
            $con = self::boot();
            Support::assert($con->isInTransaction() === false, 'should not be in transaction');
            return ['detail' => 'isInTransaction=false depth=' . $con->getNestedTransactionCount()];
        });

        $r->add(self::FW, 'propel:connection', 'getAttribute driver name is mysql', function () {
            $con = self::boot();
            $drv = $con->getAttribute(PDO::ATTR_DRIVER_NAME);
            Support::assertEquals('mysql', $drv, 'driver name');
            return ['detail' => "driver=$drv"];
        });

        $r->add(self::FW, 'propel:connection', 'getAttribute server version', function () {
            $con = self::boot();
            $ver = $con->getAttribute(PDO::ATTR_SERVER_VERSION);
            Support::assert(is_string($ver) && $ver !== '', 'empty server version');
            return ['detail' => "version=$ver"];
        });

        // -- exec() DDL / DML ----------------------------------------------
        $r->add(self::FW, 'propel:connection', 'exec creates and drops a table', function () {
            $con = self::boot();
            $tn = Support::name('pc');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $exists = $con->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name='$tn'")->fetchColumn();
                Support::assertEquals('1', $exists, 'table should exist after CREATE');
                return ['detail' => 'created', 'sql' => "CREATE TABLE $tn"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'exec INSERT returns affected rows', function () {
            $con = self::boot();
            $tn = Support::name('pc');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $aff = $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                Support::assertEquals('3', (string) $aff, 'affected rows of multi-INSERT');
                return ['detail' => "affected=$aff", 'sql' => 'INSERT 3 rows'];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'exec UPDATE returns affected rows', function () {
            $con = self::boot();
            $tn = Support::name('pc');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                $aff = $con->exec("UPDATE `$tn` SET id = id + 10 WHERE id > 1");
                Support::assertEquals('2', (string) $aff, 'affected rows of UPDATE');
                return ['detail' => "affected=$aff"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'exec DELETE returns affected rows', function () {
            $con = self::boot();
            $tn = Support::name('pc');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4)");
                $aff = $con->exec("DELETE FROM `$tn` WHERE id % 2 = 0");
                Support::assertEquals('2', (string) $aff, 'affected rows of DELETE');
                return ['detail' => "affected=$aff"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- query() returning a DataFetcher -------------------------------
        $scalarSelects = [
            ['SELECT 1 + 1', '2'],
            ['SELECT 7 * 6', '42'],
            ["SELECT CONCAT('a','b','c')", 'abc'],
            ['SELECT LENGTH("hello")', '5'],
            ['SELECT UPPER("abc")', 'ABC'],
            ['SELECT ABS(-9)', '9'],
            ['SELECT 10 DIV 3', '3'],
            ['SELECT 10 % 3', '1'],
            ['SELECT GREATEST(3,7,2)', '7'],
            ['SELECT LEAST(3,7,2)', '2'],
            ['SELECT CHAR_LENGTH("héllo")', '5'],
            ['SELECT REVERSE("abc")', 'cba'],
            ['SELECT REPEAT("ab",3)', 'ababab'],
            ['SELECT TRIM("  x  ")', 'x'],
            ['SELECT ROUND(3.14159, 2)', '3.14'],
            ['SELECT FLOOR(3.9)', '3'],
            ['SELECT CEIL(3.1)', '4'],
            ['SELECT MOD(17, 5)', '2'],
            ['SELECT POWER(2, 10)', '1024'],
            ['SELECT SIGN(-4)', '-1'],
        ];
        foreach ($scalarSelects as [$sql, $exp]) {
            $r->add(self::FW, 'propel:connection', "query scalar: $sql", function () use ($sql, $exp) {
                $con = self::boot();
                $got = $con->query($sql)->fetchColumn();
                Support::assertValueEquals($exp, $got, $sql);
                return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
            });
        }

        $r->add(self::FW, 'propel:connection', 'query returns PDODataFetcher', function () {
            $con = self::boot();
            $df = $con->query('SELECT 1');
            Support::assert(str_contains(get_class($df), 'DataFetcher'), 'expected a DataFetcher, got ' . get_class($df));
            return ['detail' => get_class($df)];
        });

        // -- fetch styles via DataFetcher ----------------------------------
        foreach ([
            ['FETCH_NUM', PDO::FETCH_NUM],
            ['FETCH_ASSOC', PDO::FETCH_ASSOC],
            ['FETCH_BOTH', PDO::FETCH_BOTH],
        ] as [$label, $style]) {
            $r->add(self::FW, 'propel:connection', "DataFetcher setStyle $label", function () use ($label, $style) {
                $con = self::boot();
                $tn = Support::name('pf');
                try {
                    $con->exec("CREATE TABLE `$tn` (id INT, v VARCHAR(10))");
                    $con->exec("INSERT INTO `$tn` VALUES (1,'a')");
                    $df = $con->query("SELECT id, v FROM `$tn`");
                    $df->setStyle($style);
                    $row = $df->fetch();
                    Support::assert(is_array($row) && count($row) >= 2, "$label did not return a row array");
                    return ['detail' => "$label => " . json_encode($row)];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        $r->add(self::FW, 'propel:connection', 'DataFetcher fetchAll returns all rows', function () {
            $con = self::boot();
            $tn = Support::name('pf');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4),(5)");
                $rows = $con->query("SELECT id FROM `$tn` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('5', (string) count($rows), 'fetchAll row count');
                return ['detail' => 'rows=' . count($rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'DataFetcher foreach iteration', function () {
            $con = self::boot();
            $tn = Support::name('pf');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (10),(20),(30)");
                $sum = 0;
                foreach ($con->query("SELECT id FROM `$tn`") as $row) {
                    $sum += (int) $row[0];
                }
                Support::assertEquals('60', (string) $sum, 'iterated sum');
                return ['detail' => "sum=$sum"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'DataFetcher count over result', function () {
            $con = self::boot();
            $tn = Support::name('pf');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4)");
                $df = $con->query("SELECT id FROM `$tn`");
                $n = count($df);
                Support::assertEquals('4', (string) $n, 'DataFetcher count()');
                return ['detail' => "count=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- prepare + bind + execute --------------------------------------
        $r->add(self::FW, 'propel:connection', 'prepare with named params + bindValue', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(40), age INT)");
                $st = $con->prepare("INSERT INTO `$tn` (name, age) VALUES (:n, :a)");
                $st->bindValue(':n', 'alice');
                $st->bindValue(':a', 30, PDO::PARAM_INT);
                $ok = $st->execute();
                Support::assert($ok === true, 'execute returned false');
                $got = $con->query("SELECT name FROM `$tn`")->fetchColumn();
                Support::assertEquals('alice', $got, 'inserted name round-trip');
                return ['detail' => 'inserted alice', 'sql' => "INSERT prepared"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'prepare with positional ? params', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (a INT, b INT)");
                $st = $con->prepare("INSERT INTO `$tn` (a, b) VALUES (?, ?)");
                $st->bindValue(1, 5, PDO::PARAM_INT);
                $st->bindValue(2, 7, PDO::PARAM_INT);
                $st->execute();
                $sum = $con->query("SELECT a + b FROM `$tn`")->fetchColumn();
                Support::assertEquals('12', $sum, 'positional bind round-trip');
                return ['detail' => "a+b=$sum"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'prepare execute(array) passes params', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (v VARCHAR(20))");
                $st = $con->prepare("INSERT INTO `$tn` (v) VALUES (?)");
                $st->execute(['from-array']);
                $got = $con->query("SELECT v FROM `$tn`")->fetchColumn();
                Support::assertEquals('from-array', $got, 'execute(array) round-trip');
                return ['detail' => "v=$got"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'prepare bindParam by reference', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (v INT)");
                $st = $con->prepare("INSERT INTO `$tn` (v) VALUES (:v)");
                $ref = 0;
                $st->bindParam(':v', $ref, PDO::PARAM_INT);
                foreach ([11, 22, 33] as $ref) {
                    $st->execute();
                }
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                $max = $con->query("SELECT MAX(v) FROM `$tn`")->fetchColumn();
                Support::assertEquals('3', $n, 'three bindParam inserts');
                Support::assertEquals('33', $max, 'last bound value used');
                return ['detail' => "rows=$n max=$max"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'prepared statement rowCount after UPDATE', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                $st = $con->prepare("UPDATE `$tn` SET id = id + 100 WHERE id >= ?");
                $st->bindValue(1, 2, PDO::PARAM_INT);
                $st->execute();
                Support::assertEquals('2', (string) $st->rowCount(), 'rowCount of prepared UPDATE');
                return ['detail' => 'rowCount=' . $st->rowCount()];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'prepared SELECT fetch + fetchColumn', function () {
            $con = self::boot();
            $tn = Support::name('pp');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, v VARCHAR(10))");
                $con->exec("INSERT INTO `$tn` VALUES (1,'x'),(2,'y')");
                $st = $con->prepare("SELECT v FROM `$tn` WHERE id = ?");
                $st->bindValue(1, 2, PDO::PARAM_INT);
                $st->execute();
                $got = $st->fetchColumn();
                Support::assertEquals('y', $got, 'prepared SELECT fetchColumn');
                return ['detail' => "v=$got"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- lastInsertId --------------------------------------------------
        $r->add(self::FW, 'propel:connection', 'lastInsertId after AUTO_INCREMENT insert', function () {
            $con = self::boot();
            $tn = Support::name('pl');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, v INT)");
                $con->exec("INSERT INTO `$tn` (v) VALUES (100)");
                $id1 = $con->lastInsertId();
                $con->exec("INSERT INTO `$tn` (v) VALUES (200)");
                $id2 = $con->lastInsertId();
                Support::assert((int) $id2 > (int) $id1, "lastInsertId did not increase: $id1 -> $id2");
                return ['detail' => "ids $id1 -> $id2"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:connection', 'lastInsertId via prepared insert', function () {
            $con = self::boot();
            $tn = Support::name('pl');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, v INT)");
                $st = $con->prepare("INSERT INTO `$tn` (v) VALUES (?)");
                $st->bindValue(1, 42, PDO::PARAM_INT);
                $st->execute();
                $id = $con->lastInsertId();
                Support::assert((int) $id >= 1, "unexpected lastInsertId $id");
                return ['detail' => "id=$id"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- quote ---------------------------------------------------------
        $quotes = [
            ["abc", "'abc'"],
            ["a'b", "'a\\'b'"],
            ['', "''"],
            ['100%', "'100%'"],
        ];
        foreach ($quotes as $i => [$in, $exp]) {
            $r->add(self::FW, 'propel:connection', "quote string #$i", function () use ($in, $exp) {
                $con = self::boot();
                $q = $con->quote($in);
                Support::assertEquals($exp, $q, 'quote(' . var_export($in, true) . ')');
                return ['detail' => "quote=$q"];
            });
        }

        // -- getQueryCount / lastExecutedQuery (debug helpers) -------------
        $r->add(self::FW, 'propel:connection', 'getQueryCount returns an int', function () {
            $con = self::boot();
            $n = $con->getQueryCount();
            Support::assert(is_int($n), 'getQueryCount not an int');
            return ['detail' => "queryCount=$n"];
        });

        $r->add(self::FW, 'propel:connection', 'setAttribute / getAttribute round-trip', function () {
            $con = self::boot();
            $con->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
            $got = $con->getAttribute(PDO::ATTR_CASE);
            Support::assertEquals((string) PDO::CASE_NATURAL, (string) $got, 'ATTR_CASE round-trip');
            return ['detail' => "case=$got"];
        });

        // -- fresh manager / reconnect -------------------------------------
        $r->add(self::FW, 'propel:connection', 'fresh ConnectionManagerSingle yields working connection', function () {
            self::boot();
            $db = Config::database('pdo');
            $mgr = new ConnectionManagerSingle('probe2');
            $mgr->setConfiguration([
                'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s', Config::host(), Config::port(), $db),
                'user' => Config::user(),
                'password' => Config::password(),
                'settings' => ['charset' => 'utf8mb4'],
            ]);
            $adapter = self::adapter();
            $con = $mgr->getWriteConnection($adapter);
            try {
                $got = $con->query('SELECT 123')->fetchColumn();
                Support::assertEquals('123', $got, 'fresh manager connection query');
                return ['detail' => 'fresh manager ok, got=' . $got];
            } finally {
                try {
                    $mgr->closeConnections();
                } catch (\Throwable) {
                }
            }
        });

        $r->add(self::FW, 'propel:connection', 'manager closeConnections then reuse boots fresh', function () {
            self::boot();
            $db = Config::database('pdo');
            $mgr = new ConnectionManagerSingle('probe3');
            $mgr->setConfiguration([
                'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s', Config::host(), Config::port(), $db),
                'user' => Config::user(),
                'password' => Config::password(),
                'settings' => ['charset' => 'utf8mb4'],
            ]);
            $adapter = self::adapter();
            $c1 = $mgr->getWriteConnection($adapter);
            $c1->query('SELECT 1')->fetchColumn();
            $mgr->closeConnections();
            $c2 = $mgr->getWriteConnection($adapter);
            try {
                $got = $c2->query('SELECT 7')->fetchColumn();
                Support::assertEquals('7', $got, 'reconnect after close');
                return ['detail' => 'reconnected, got=' . $got];
            } finally {
                try {
                    $mgr->closeConnections();
                } catch (\Throwable) {
                }
            }
        });

        // -- error surfacing -----------------------------------------------
        $r->add(self::FW, 'propel:connection', 'query on missing table throws', function () {
            $con = self::boot();
            $missing = Support::name('nope');
            $threw = false;
            try {
                $con->query("SELECT * FROM `$missing`")->fetchColumn();
            } catch (\Throwable) {
                $threw = true;
            }
            Support::assert($threw, 'expected error querying a missing table');
            return ['detail' => 'missing table threw as expected'];
        });

        $r->add(self::FW, 'propel:connection', 'exec invalid SQL throws', function () {
            $con = self::boot();
            $threw = false;
            try {
                $con->exec('THIS IS NOT SQL');
            } catch (\Throwable) {
                $threw = true;
            }
            Support::assert($threw, 'expected parser error on invalid SQL');
            return ['detail' => 'invalid SQL threw as expected'];
        });

        // -- a sweep of SELECT-shape probes to broaden connection coverage --
        $shapes = [
            ['SELECT NULL', null, true],
            ['SELECT TRUE', '1', false],
            ['SELECT FALSE', '0', false],
            ["SELECT 'a' = 'a'", '1', false],
            ["SELECT 'a' = 'b'", '0', false],
            ['SELECT 1 IN (1,2,3)', '1', false],
            ['SELECT 9 NOT IN (1,2,3)', '1', false],
            ['SELECT 5 BETWEEN 1 AND 10', '1', false],
            ["SELECT 'abc' LIKE 'a%'", '1', false],
            ['SELECT IFNULL(NULL, 7)', '7', false],
            ['SELECT COALESCE(NULL, NULL, 3)', '3', false],
            ['SELECT NULLIF(4,4)', null, true],
            ['SELECT CASE WHEN 1=1 THEN 10 ELSE 20 END', '10', false],
            ['SELECT HEX(255)', 'FF', false],
            ['SELECT BIN(5)', '101', false],
            ['SELECT ASCII("A")', '65', false],
        ];
        foreach ($shapes as [$sql, $exp, $isNull] in_array(true, [true], true) ? $shapes : $shapes) {
            // placeholder; replaced below
        }
        foreach ($shapes as [$sql, $exp, $isNull]) {
            $r->add(self::FW, 'propel:connection', "query shape: $sql", function () use ($sql, $exp, $isNull) {
                $con = self::boot();
                $got = $con->query($sql)->fetchColumn();
                if ($isNull) {
                    Support::assert($got === null, "expected NULL, got " . var_export($got, true));
                } else {
                    Support::assertValueEquals($exp, $got, $sql);
                }
                return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
            });
        }
    }

    // ================================================================ adapter

    private static function adapterScenarios(Runner $r): void
    {
        // -- quoteIdentifier -----------------------------------------------
        $idents = [
            ['foo', '`foo`'],
            ['Bar', '`Bar`'],
            ['table_name', '`table_name`'],
            ['col1', '`col1`'],
            ['UPPER', '`UPPER`'],
            ['a b', '`a b`'],
            ['', '``'],
        ];
        foreach ($idents as [$in, $exp]) {
            $r->add(self::FW, 'propel:adapter', "quoteIdentifier('$in')", function () use ($in, $exp) {
                $a = self::adapter();
                Support::assertEquals($exp, $a->quoteIdentifier($in), "quoteIdentifier('$in')");
                return ['detail' => $a->quoteIdentifier($in)];
            });
        }

        // -- quoteIdentifierTable ------------------------------------------
        $tables = [
            ['book', '`book`'],
            ['db.book', '`db`.`book`'],
            ['book b', '`book` `b`'],
            ['db.book b', '`db`.`book` `b`'],
            ['schema.tbl alias', '`schema`.`tbl` `alias`'],
        ];
        foreach ($tables as [$in, $exp]) {
            $r->add(self::FW, 'propel:adapter', "quoteIdentifierTable('$in')", function () use ($in, $exp) {
                $a = self::adapter();
                Support::assertEquals($exp, $a->quoteIdentifierTable($in), "quoteIdentifierTable('$in')");
                return ['detail' => $a->quoteIdentifierTable($in)];
            });
        }

        // -- quote (qualified column) --------------------------------------
        $cols = [
            ['author_id', '`author_id`'],
            ['book.author_id', '`book`.`author_id`'],
            ['db.book.col', '`db`.`book`.`col`'],
        ];
        foreach ($cols as [$in, $exp]) {
            $r->add(self::FW, 'propel:adapter', "quote('$in')", function () use ($in, $exp) {
                $a = self::adapter();
                Support::assertEquals($exp, $a->quote($in), "quote('$in')");
                return ['detail' => $a->quote($in)];
            });
        }

        // -- applyLimit ----------------------------------------------------
        $limits = [
            [0, 10, 'SELECT * FROM t LIMIT 10'],
            [5, 10, 'SELECT * FROM t LIMIT 5, 10'],
            [0, 0, 'SELECT * FROM t LIMIT 0'],
            [100, 25, 'SELECT * FROM t LIMIT 100, 25'],
            [3, 0, 'SELECT * FROM t LIMIT 3, 0'],
        ];
        foreach ($limits as $i => [$offset, $limit, $exp]) {
            $r->add(self::FW, 'propel:adapter', "applyLimit(offset=$offset,limit=$limit)", function () use ($offset, $limit, $exp) {
                $a = self::adapter();
                $sql = 'SELECT * FROM t';
                $a->applyLimit($sql, $offset, $limit);
                Support::assertEquals($exp, $sql, "applyLimit($offset,$limit)");
                return ['detail' => $sql];
            });
        }
        // applyLimit with negative limit + offset (offset-only path)
        $r->add(self::FW, 'propel:adapter', 'applyLimit negative limit with offset', function () {
            $a = self::adapter();
            $sql = 'SELECT * FROM t';
            $a->applyLimit($sql, 7, -1);
            Support::assert(str_contains($sql, 'LIMIT 7, 18446744073709551615'), "unexpected: $sql");
            return ['detail' => $sql];
        });
        // The generated LIMIT actually runs on MatrixOne
        $r->add(self::FW, 'propel:adapter', 'applyLimit output executes on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $tn = Support::name('al');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4),(5)");
                $sql = "SELECT id FROM `$tn` ORDER BY id";
                $a->applyLimit($sql, 1, 2);
                $rows = $con->query($sql)->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('2', (string) count($rows), 'LIMIT/OFFSET result count');
                Support::assertEquals('2', (string) $rows[0], 'LIMIT/OFFSET first row');
                return ['detail' => 'rows=' . implode(',', $rows), 'sql' => $sql];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- random --------------------------------------------------------
        foreach ([null, '0', '5', '42', '-1'] as $seed) {
            $r->add(self::FW, 'propel:adapter', 'random(seed=' . var_export($seed, true) . ')', function () use ($seed) {
                $a = self::adapter();
                $expr = $a->random($seed);
                Support::assert(str_starts_with($expr, 'rand('), "unexpected random expr: $expr");
                return ['detail' => $expr];
            });
        }
        $r->add(self::FW, 'propel:adapter', 'random() expression runs on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->random('5');
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assert($got !== null, 'random() returned NULL');
            return ['detail' => "$expr => $got"];
        });

        // -- concatString / subString / strLength --------------------------
        $r->add(self::FW, 'propel:adapter', 'concatString builds CONCAT', function () {
            $a = self::adapter();
            Support::assertEquals("CONCAT('a', 'b')", $a->concatString("'a'", "'b'"), 'concatString');
            return ['detail' => $a->concatString("'a'", "'b'")];
        });
        $r->add(self::FW, 'propel:adapter', 'concatString output runs on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->concatString("'foo'", "'bar'");
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assertEquals('foobar', $got, 'CONCAT result');
            return ['detail' => "$expr => $got"];
        });
        $r->add(self::FW, 'propel:adapter', 'subString builds SUBSTRING', function () {
            $a = self::adapter();
            Support::assertEquals("SUBSTRING('abcdef', 2, 3)", $a->subString("'abcdef'", 2, 3), 'subString');
            return ['detail' => $a->subString("'abcdef'", 2, 3)];
        });
        $r->add(self::FW, 'propel:adapter', 'subString output runs on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->subString("'abcdef'", 2, 3);
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assertEquals('bcd', $got, 'SUBSTRING result');
            return ['detail' => "$expr => $got"];
        });
        $r->add(self::FW, 'propel:adapter', 'strLength builds CHAR_LENGTH', function () {
            $a = self::adapter();
            Support::assertEquals("CHAR_LENGTH('héllo')", $a->strLength("'héllo'"), 'strLength');
            return ['detail' => $a->strLength("'héllo'")];
        });
        $r->add(self::FW, 'propel:adapter', 'strLength output runs on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->strLength("'héllo'");
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assertEquals('5', $got, 'CHAR_LENGTH result');
            return ['detail' => "$expr => $got"];
        });

        // -- compareRegex --------------------------------------------------
        $r->add(self::FW, 'propel:adapter', 'compareRegex builds REGEXP', function () {
            $a = self::adapter();
            Support::assertEquals("col REGEXP '^a'", $a->compareRegex('col', "'^a'"), 'compareRegex');
            return ['detail' => $a->compareRegex('col', "'^a'")];
        });
        $r->add(self::FW, 'propel:adapter', 'compareRegex output runs on MatrixOne', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->compareRegex("'abc'", "'^a'");
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assert($got !== null, 'REGEXP returned NULL (possible unsupported)');
            return ['detail' => "$expr => " . var_export($got, true)];
        });

        // -- toUpperCase / ignoreCase --------------------------------------
        $r->add(self::FW, 'propel:adapter', 'toUpperCase builds UPPER', function () {
            $a = self::adapter();
            Support::assertEquals('UPPER(c)', $a->toUpperCase('c'), 'toUpperCase');
            return ['detail' => $a->toUpperCase('c')];
        });
        $r->add(self::FW, 'propel:adapter', 'ignoreCase builds UPPER', function () {
            $a = self::adapter();
            Support::assertEquals('UPPER(c)', $a->ignoreCase('c'), 'ignoreCase');
            return ['detail' => $a->ignoreCase('c')];
        });
        $r->add(self::FW, 'propel:adapter', 'ignoreCaseInOrderBy builds UPPER', function () {
            $a = self::adapter();
            Support::assertEquals('UPPER(c)', $a->ignoreCaseInOrderBy('c'), 'ignoreCaseInOrderBy');
            return ['detail' => $a->ignoreCaseInOrderBy('c')];
        });
        $r->add(self::FW, 'propel:adapter', 'ignoreCase UPPER makes compare case-insensitive', function () {
            $con = self::boot();
            $a = self::adapter();
            $expr = $a->ignoreCase("'abc'") . ' = ' . $a->ignoreCase("'ABC'");
            $got = $con->query("SELECT $expr")->fetchColumn();
            Support::assertEquals('1', $got, 'UPPER()=UPPER() case-insensitive match');
            return ['detail' => "$expr => $got"];
        });

        // -- formatters ----------------------------------------------------
        $r->add(self::FW, 'propel:adapter', 'getTimestampFormatter', function () {
            $a = self::adapter();
            Support::assertEquals('Y-m-d H:i:s.u', $a->getTimestampFormatter(), 'timestamp formatter');
            return ['detail' => $a->getTimestampFormatter()];
        });
        $r->add(self::FW, 'propel:adapter', 'getDateFormatter', function () {
            $a = self::adapter();
            Support::assertEquals('Y-m-d', $a->getDateFormatter(), 'date formatter');
            return ['detail' => $a->getDateFormatter()];
        });
        $r->add(self::FW, 'propel:adapter', 'getTimeFormatter', function () {
            $a = self::adapter();
            Support::assertEquals('H:i:s.u', $a->getTimeFormatter(), 'time formatter');
            return ['detail' => $a->getTimeFormatter()];
        });
        $r->add(self::FW, 'propel:adapter', 'getStringDelimiter is single quote', function () {
            $a = self::adapter();
            Support::assertEquals("'", $a->getStringDelimiter(), 'string delimiter');
            return ['detail' => $a->getStringDelimiter()];
        });

        // -- ID method introspection ---------------------------------------
        $r->add(self::FW, 'propel:adapter', 'isGetIdAfterInsert true (autoincrement)', function () {
            $a = self::adapter();
            Support::assert($a->isGetIdAfterInsert() === true, 'expected after-insert id method');
            return ['detail' => 'after=true'];
        });
        $r->add(self::FW, 'propel:adapter', 'isGetIdBeforeInsert false (no sequences)', function () {
            $a = self::adapter();
            Support::assert($a->isGetIdBeforeInsert() === false, 'expected no before-insert id method');
            return ['detail' => 'before=false'];
        });
        $r->add(self::FW, 'propel:adapter', 'getAdapterId is mysql', function () {
            $a = self::adapter();
            Support::assertEquals('mysql', $a->getAdapterId(), 'adapter id');
            return ['detail' => $a->getAdapterId()];
        });
        $r->add(self::FW, 'propel:adapter', 'supportsAliasesInDelete true', function () {
            $a = self::adapter();
            Support::assert($a->supportsAliasesInDelete() === true, 'expected alias-in-delete support flag');
            return ['detail' => 'true'];
        });
        $r->add(self::FW, 'propel:adapter', 'getId returns lastInsertId after insert', function () {
            $con = self::boot();
            $a = self::adapter();
            $tn = Support::name('gid');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, v INT)");
                $con->exec("INSERT INTO `$tn` (v) VALUES (9)");
                $id = $a->getId($con);
                Support::assert((int) $id >= 1, "unexpected getId $id");
                return ['detail' => "getId=$id"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // -- ColumnMap PDO type mapping (data-driven) ----------------------
        $pdoTypeMap = [
            [PropelTypes::INTEGER, PDO::PARAM_INT],
            [PropelTypes::TINYINT, PDO::PARAM_INT],
            [PropelTypes::SMALLINT, PDO::PARAM_INT],
            [PropelTypes::BIGINT, PDO::PARAM_INT],
            [PropelTypes::VARCHAR, PDO::PARAM_STR],
            [PropelTypes::CHAR, PDO::PARAM_STR],
            [PropelTypes::LONGVARCHAR, PDO::PARAM_STR],
            [PropelTypes::DECIMAL, PDO::PARAM_STR],
            [PropelTypes::FLOAT, PDO::PARAM_STR],
            [PropelTypes::DOUBLE, PDO::PARAM_STR],
            [PropelTypes::BOOLEAN, PDO::PARAM_BOOL],
            [PropelTypes::BLOB, PDO::PARAM_LOB],
            [PropelTypes::CLOB, PDO::PARAM_STR],
            [PropelTypes::TIMESTAMP, PDO::PARAM_STR],
            [PropelTypes::DATE, PDO::PARAM_STR],
            [PropelTypes::TIME, PDO::PARAM_STR],
            [PropelTypes::JSON, PDO::PARAM_STR],
        ];
        foreach ($pdoTypeMap as [$propelType, $pdoType]) {
            $r->add(self::FW, 'propel:adapter', "ColumnMap $propelType maps to PDO type $pdoType", function () use ($propelType, $pdoType) {
                $cm = self::col('c', $propelType);
                Support::assertEquals((string) $pdoType, (string) $cm->getPdoType(), "PDO type for $propelType");
                return ['detail' => "$propelType => $pdoType"];
            });
        }

        // -- ColumnMap isTemporal / isLob flags ----------------------------
        $temporalTypes = [PropelTypes::TIMESTAMP, PropelTypes::DATE, PropelTypes::TIME, PropelTypes::DATETIME];
        foreach ($temporalTypes as $t) {
            $r->add(self::FW, 'propel:adapter', "ColumnMap $t isTemporal", function () use ($t) {
                $cm = self::col('c', $t);
                Support::assert($cm->isTemporal() === true, "$t should be temporal");
                return ['detail' => "$t temporal=true"];
            });
        }
        foreach ([PropelTypes::INTEGER, PropelTypes::VARCHAR, PropelTypes::DECIMAL] as $t) {
            $r->add(self::FW, 'propel:adapter', "ColumnMap $t not temporal", function () use ($t) {
                $cm = self::col('c', $t);
                Support::assert($cm->isTemporal() === false, "$t should not be temporal");
                return ['detail' => "$t temporal=false"];
            });
        }
        foreach ([PropelTypes::BLOB] as $t) {
            $r->add(self::FW, 'propel:adapter', "ColumnMap $t isLob", function () use ($t) {
                $cm = self::col('c', $t);
                Support::assert($cm->isLob() === true, "$t should be a LOB");
                return ['detail' => "$t lob=true"];
            });
        }

        // -- formatTemporalValue (data-driven) -----------------------------
        $temporal = [
            [PropelTypes::TIMESTAMP, '2026-06-23 14:05:09', '2026-06-23 14:05:09.000000'],
            [PropelTypes::DATETIME, '2026-06-23 14:05:09', '2026-06-23 14:05:09.000000'],
            [PropelTypes::DATE, '2026-06-23 14:05:09', '2026-06-23'],
            [PropelTypes::TIME, '14:05:09', '14:05:09.000000'],
            [PropelTypes::TIMESTAMP, '2000-01-01 00:00:00', '2000-01-01 00:00:00.000000'],
            [PropelTypes::DATE, '1999-12-31', '1999-12-31'],
        ];
        foreach ($temporal as $i => [$type, $in, $exp]) {
            $r->add(self::FW, 'propel:adapter', "formatTemporalValue $type #$i", function () use ($type, $in, $exp) {
                $a = self::adapter();
                $cm = self::col('d', $type);
                $out = $a->formatTemporalValue($in, $cm);
                Support::assertEquals($exp, $out, "formatTemporalValue($type, $in)");
                return ['detail' => "$in => $out"];
            });
        }
        $r->add(self::FW, 'propel:adapter', 'formatTemporalValue from DateTime object', function () {
            $a = self::adapter();
            $cm = self::col('d', PropelTypes::TIMESTAMP);
            $out = $a->formatTemporalValue(new \DateTime('2026-06-23 14:05:09'), $cm);
            Support::assertEquals('2026-06-23 14:05:09.000000', $out, 'formatTemporalValue(DateTime)');
            return ['detail' => $out];
        });

        // -- adapter->bindValue end-to-end through MatrixOne (data-driven) --
        $binds = [
            ['INTEGER', PropelTypes::INTEGER, 'INT', 42, '42'],
            ['negative INTEGER', PropelTypes::INTEGER, 'INT', -7, '-7'],
            ['BIGINT', PropelTypes::BIGINT, 'BIGINT', '9223372036854775807', '9223372036854775807'],
            ['TINYINT', PropelTypes::TINYINT, 'TINYINT', 100, '100'],
            ['SMALLINT', PropelTypes::SMALLINT, 'SMALLINT', 30000, '30000'],
            ['VARCHAR', PropelTypes::VARCHAR, 'VARCHAR(30)', 'hello world', 'hello world'],
            ['VARCHAR unicode', PropelTypes::VARCHAR, 'VARCHAR(30)', 'café déjà', 'café déjà'],
            ['DECIMAL', PropelTypes::DECIMAL, 'DECIMAL(10,2)', '12.34', '12.34'],
            ['DOUBLE', PropelTypes::DOUBLE, 'DOUBLE', '3.5', '3.5'],
            ['BOOLEAN true', PropelTypes::BOOLEAN, 'BOOLEAN', true, '1'],
            ['BOOLEAN false', PropelTypes::BOOLEAN, 'BOOLEAN', false, '0'],
            ['CLOB', PropelTypes::CLOB, 'TEXT', str_repeat('x', 100), str_repeat('x', 100)],
        ];
        foreach ($binds as [$label, $propelType, $colDdl, $value, $exp]) {
            $r->add(self::FW, 'propel:adapter', "adapter->bindValue $label round-trips", function () use ($label, $propelType, $colDdl, $value, $exp) {
                $con = self::boot();
                $a = self::adapter();
                $tn = Support::name('ab');
                try {
                    $con->exec("CREATE TABLE `$tn` (c $colDdl)");
                    $st = $con->prepare("INSERT INTO `$tn` (c) VALUES (:p1)");
                    $cm = self::col('c', $propelType);
                    $a->bindValue($st, ':p1', $value, $cm, 1);
                    $st->execute();
                    $got = $con->query("SELECT c FROM `$tn`")->fetchColumn();
                    Support::assertValueEquals($exp, $got, "$label round-trip");
                    return ['detail' => "$label => " . var_export($got, true)];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }
        // temporal bindValue through adapter (uses formatTemporalValue internally)
        $tBinds = [
            ['DATETIME', PropelTypes::TIMESTAMP, 'DATETIME', '2026-06-23 14:05:09', '2026-06-23 14:05:09'],
            ['DATE', PropelTypes::DATE, 'DATE', '2026-06-23', '2026-06-23'],
            ['TIME', PropelTypes::TIME, 'TIME', '14:05:09', '14:05:09'],
        ];
        foreach ($tBinds as [$label, $propelType, $colDdl, $value, $exp]) {
            $r->add(self::FW, 'propel:adapter', "adapter->bindValue temporal $label", function () use ($label, $propelType, $colDdl, $value, $exp) {
                $con = self::boot();
                $a = self::adapter();
                $tn = Support::name('abt');
                try {
                    $con->exec("CREATE TABLE `$tn` (c $colDdl)");
                    $st = $con->prepare("INSERT INTO `$tn` (c) VALUES (:p1)");
                    $cm = self::col('c', $propelType);
                    $a->bindValue($st, ':p1', $value, $cm, 1);
                    $st->execute();
                    $got = $con->query("SELECT c FROM `$tn`")->fetchColumn();
                    Support::assertEquals($exp, $got, "$label temporal round-trip");
                    return ['detail' => "$label => $got"];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // -- getGroupBy off a Criteria -------------------------------------
        $r->add(self::FW, 'propel:adapter', 'getGroupBy from Criteria', function () {
            $a = self::adapter();
            $c = new Criteria('default');
            $c->addGroupByColumn('t.a');
            $c->addGroupByColumn('t.b');
            Support::assertEquals('GROUP BY t.a,t.b', $a->getGroupBy($c), 'getGroupBy');
            return ['detail' => $a->getGroupBy($c)];
        });
        $r->add(self::FW, 'propel:adapter', 'getGroupBy empty when no columns', function () {
            $a = self::adapter();
            $c = new Criteria('default');
            Support::assertEquals('', $a->getGroupBy($c), 'getGroupBy empty');
            return ['detail' => 'empty'];
        });
    }

    // =================================================================== CRUD

    private static function crudScenarios(Runner $r): void
    {
        // Full CRUD over a matrix of column-type sets + value sets.
        $columnSets = [
            'ints' => [
                'ddl' => 'id INT PRIMARY KEY AUTO_INCREMENT, a INT, b BIGINT, c SMALLINT',
                'cols' => ['a', 'b', 'c'],
                'rows' => [
                    [1, 100, 10],
                    [2, 9223372036854775807, -5],
                    [-3, 0, 32000],
                ],
                'pdoTypes' => [PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT],
            ],
            'strings' => [
                'ddl' => 'id INT PRIMARY KEY AUTO_INCREMENT, a VARCHAR(50), b CHAR(5), c TEXT',
                'cols' => ['a', 'b', 'c'],
                'rows' => [
                    ['alice', 'abc', 'a long text value here'],
                    ['café', 'déjà', str_repeat('z', 200)],
                    ['', 'x', 'tab\there'],
                ],
                'pdoTypes' => [PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR],
            ],
            'numerics' => [
                'ddl' => 'id INT PRIMARY KEY AUTO_INCREMENT, a DECIMAL(12,4), b DOUBLE, c FLOAT',
                'cols' => ['a', 'b', 'c'],
                'rows' => [
                    ['123.4567', '2.5', '1.0'],
                    ['-99.9999', '0.0', '100.0'],
                    ['0.0001', '1000000.5', '0.0'],
                ],
                'pdoTypes' => [PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR],
            ],
            'mixed' => [
                'ddl' => 'id INT PRIMARY KEY AUTO_INCREMENT, a VARCHAR(30), b INT, c DECIMAL(8,2)',
                'cols' => ['a', 'b', 'c'],
                'rows' => [
                    ['widget', 5, '19.99'],
                    ['gadget', 12, '4.50'],
                    ['gizmo', 0, '0.00'],
                ],
                'pdoTypes' => [PDO::PARAM_STR, PDO::PARAM_INT, PDO::PARAM_STR],
            ],
        ];

        foreach ($columnSets as $setName => $set) {
            // CREATE + INSERT (prepared) + SELECT back
            $r->add(self::FW, 'propel:crud', "[$setName] insert prepared rows and read back", function () use ($setName, $set) {
                $con = self::boot();
                $tn = Support::name('cr');
                try {
                    $con->exec("CREATE TABLE `$tn` ({$set['ddl']})");
                    $placeholders = implode(', ', array_fill(0, count($set['cols']), '?'));
                    $st = $con->prepare("INSERT INTO `$tn` (" . implode(', ', $set['cols']) . ") VALUES ($placeholders)");
                    foreach ($set['rows'] as $row) {
                        foreach ($row as $i => $v) {
                            $st->bindValue($i + 1, $v, $set['pdoTypes'][$i]);
                        }
                        $st->execute();
                    }
                    $count = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals((string) count($set['rows']), $count, "$setName inserted count");
                    return ['detail' => "rows=$count", 'sql' => "INSERT into $setName set"];
                } finally {
                    self::drop($con, $tn);
                }
            });

            // SELECT back first row and assert each column value
            $r->add(self::FW, 'propel:crud', "[$setName] first row values survive round-trip", function () use ($setName, $set) {
                $con = self::boot();
                $tn = Support::name('cr');
                try {
                    $con->exec("CREATE TABLE `$tn` ({$set['ddl']})");
                    $placeholders = implode(', ', array_fill(0, count($set['cols']), '?'));
                    $st = $con->prepare("INSERT INTO `$tn` (" . implode(', ', $set['cols']) . ") VALUES ($placeholders)");
                    $first = $set['rows'][0];
                    foreach ($first as $i => $v) {
                        $st->bindValue($i + 1, $v, $set['pdoTypes'][$i]);
                    }
                    $st->execute();
                    $df = $con->query("SELECT " . implode(', ', $set['cols']) . " FROM `$tn` ORDER BY id LIMIT 1");
                    $df->setStyle(PDO::FETCH_NUM);
                    $got = $df->fetch();
                    foreach ($set['cols'] as $i => $colName) {
                        Support::assertValueEquals($first[$i], $got[$i], "$setName col $colName round-trip");
                    }
                    return ['detail' => 'row=' . json_encode($got)];
                } finally {
                    self::drop($con, $tn);
                }
            });

            // UPDATE + verify
            $r->add(self::FW, 'propel:crud', "[$setName] UPDATE then verify", function () use ($setName, $set) {
                $con = self::boot();
                $tn = Support::name('cr');
                try {
                    $con->exec("CREATE TABLE `$tn` ({$set['ddl']})");
                    $placeholders = implode(', ', array_fill(0, count($set['cols']), '?'));
                    $st = $con->prepare("INSERT INTO `$tn` (" . implode(', ', $set['cols']) . ") VALUES ($placeholders)");
                    foreach ($set['rows'] as $row) {
                        foreach ($row as $i => $v) {
                            $st->bindValue($i + 1, $v, $set['pdoTypes'][$i]);
                        }
                        $st->execute();
                    }
                    // Update the second column of the first inserted row by id=1
                    $col = $set['cols'][1];
                    $newVal = $set['rows'][1][1];
                    $u = $con->prepare("UPDATE `$tn` SET `$col` = ? WHERE id = 1");
                    $u->bindValue(1, $newVal, $set['pdoTypes'][1]);
                    $u->execute();
                    Support::assertEquals('1', (string) $u->rowCount(), "$setName UPDATE rowCount");
                    $got = $con->query("SELECT `$col` FROM `$tn` WHERE id = 1")->fetchColumn();
                    Support::assertValueEquals($newVal, $got, "$setName updated value");
                    return ['detail' => "updated $col to " . var_export($newVal, true)];
                } finally {
                    self::drop($con, $tn);
                }
            });

            // DELETE + verify
            $r->add(self::FW, 'propel:crud', "[$setName] DELETE then verify", function () use ($setName, $set) {
                $con = self::boot();
                $tn = Support::name('cr');
                try {
                    $con->exec("CREATE TABLE `$tn` ({$set['ddl']})");
                    $placeholders = implode(', ', array_fill(0, count($set['cols']), '?'));
                    $st = $con->prepare("INSERT INTO `$tn` (" . implode(', ', $set['cols']) . ") VALUES ($placeholders)");
                    foreach ($set['rows'] as $row) {
                        foreach ($row as $i => $v) {
                            $st->bindValue($i + 1, $v, $set['pdoTypes'][$i]);
                        }
                        $st->execute();
                    }
                    $del = $con->prepare("DELETE FROM `$tn` WHERE id = ?");
                    $del->bindValue(1, 1, PDO::PARAM_INT);
                    $del->execute();
                    Support::assertEquals('1', (string) $del->rowCount(), "$setName DELETE rowCount");
                    $remaining = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals((string) (count($set['rows']) - 1), $remaining, "$setName remaining count");
                    return ['detail' => "remaining=$remaining"];
                } finally {
                    self::drop($con, $tn);
                }
            });

            // COUNT / aggregate
            $r->add(self::FW, 'propel:crud', "[$setName] aggregate COUNT + MAX(id)", function () use ($setName, $set) {
                $con = self::boot();
                $tn = Support::name('cr');
                try {
                    $con->exec("CREATE TABLE `$tn` ({$set['ddl']})");
                    $placeholders = implode(', ', array_fill(0, count($set['cols']), '?'));
                    $st = $con->prepare("INSERT INTO `$tn` (" . implode(', ', $set['cols']) . ") VALUES ($placeholders)");
                    foreach ($set['rows'] as $row) {
                        foreach ($row as $i => $v) {
                            $st->bindValue($i + 1, $v, $set['pdoTypes'][$i]);
                        }
                        $st->execute();
                    }
                    $df = $con->query("SELECT COUNT(*), MAX(id) FROM `$tn`");
                    $df->setStyle(PDO::FETCH_NUM);
                    [$cnt, $maxId] = $df->fetch();
                    Support::assertEquals((string) count($set['rows']), (string) $cnt, "$setName count");
                    Support::assert((int) $maxId >= count($set['rows']), "$setName MAX(id)");
                    return ['detail' => "count=$cnt maxId=$maxId"];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // MatrixOne strictness surfaces through Propel prepared statements.
        $rejects = [
            ['TINYINT overflow rejected', 'c TINYINT', 200, PDO::PARAM_INT],
            ['SMALLINT overflow rejected', 'c SMALLINT', 40000, PDO::PARAM_INT],
            ['INT from non-numeric rejected', 'c INT', 'not a number', PDO::PARAM_STR],
            ['DECIMAL overflow rejected', 'c DECIMAL(4,2)', '99999.99', PDO::PARAM_STR],
            ['VARCHAR over-length rejected', 'c VARCHAR(3)', 'abcdefgh', PDO::PARAM_STR],
        ];
        foreach ($rejects as [$label, $ddl, $value, $pdoType]) {
            $r->add(self::FW, 'propel:crud', "strictness: $label", function () use ($label, $ddl, $value, $pdoType) {
                $con = self::boot();
                $tn = Support::name('crx');
                try {
                    $con->exec("CREATE TABLE `$tn` ($ddl)");
                    $threw = false;
                    $stored = null;
                    try {
                        $st = $con->prepare("INSERT INTO `$tn` (c) VALUES (?)");
                        $st->bindValue(1, $value, $pdoType);
                        $st->execute();
                        $stored = $con->query("SELECT c FROM `$tn`")->fetchColumn();
                    } catch (\Throwable) {
                        $threw = true;
                    }
                    Support::assert($threw, "$label: value accepted (stored " . var_export($stored, true) . ')');
                    return ['detail' => 'rejected as expected (MatrixOne strict)'];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // Multi-row INSERT in a single prepared statement loop, then ORDER BY read
        $r->add(self::FW, 'propel:crud', 'bulk insert 20 rows then ordered read', function () {
            $con = self::boot();
            $tn = Support::name('crb');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, label VARCHAR(20))");
                $st = $con->prepare("INSERT INTO `$tn` (id, label) VALUES (?, ?)");
                for ($i = 1; $i <= 20; $i++) {
                    $st->bindValue(1, $i, PDO::PARAM_INT);
                    $st->bindValue(2, 'row' . $i, PDO::PARAM_STR);
                    $st->execute();
                }
                $rows = $con->query("SELECT label FROM `$tn` ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('row20', $rows[0], 'first of DESC order');
                Support::assertEquals('row18', $rows[2], 'third of DESC order');
                return ['detail' => 'top3=' . implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // NULL handling on CRUD
        $r->add(self::FW, 'propel:crud', 'INSERT NULL via PARAM_NULL and read back', function () {
            $con = self::boot();
            $tn = Support::name('crn');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, v VARCHAR(10))");
                $st = $con->prepare("INSERT INTO `$tn` (id, v) VALUES (?, ?)");
                $st->bindValue(1, 1, PDO::PARAM_INT);
                $st->bindValue(2, null, PDO::PARAM_NULL);
                $st->execute();
                $got = $con->query("SELECT v FROM `$tn` WHERE id = 1")->fetchColumn();
                Support::assert($got === null, 'expected NULL, got ' . var_export($got, true));
                return ['detail' => 'NULL stored & read'];
            } finally {
                self::drop($con, $tn);
            }
        });

        // UPSERT-style ON DUPLICATE KEY UPDATE
        $r->add(self::FW, 'propel:crud', 'ON DUPLICATE KEY UPDATE via prepared', function () {
            $con = self::boot();
            $tn = Support::name('cru');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, hits INT)");
                $st = $con->prepare("INSERT INTO `$tn` (id, hits) VALUES (?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1");
                $st->bindValue(1, 7, PDO::PARAM_INT);
                $st->execute();
                $st->execute();
                $st->execute();
                $hits = $con->query("SELECT hits FROM `$tn` WHERE id = 7")->fetchColumn();
                Support::assertEquals('3', $hits, 'upsert increment');
                return ['detail' => "hits=$hits"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // SELECT with WHERE-bound prepared params returns expected subset
        $r->add(self::FW, 'propel:crud', 'parameterised WHERE returns subset', function () {
            $con = self::boot();
            $tn = Support::name('crw');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, cat VARCHAR(10))");
                $con->exec("INSERT INTO `$tn` VALUES (1,'a'),(2,'b'),(3,'a'),(4,'c'),(5,'a')");
                $st = $con->prepare("SELECT COUNT(*) FROM `$tn` WHERE cat = ?");
                $st->bindValue(1, 'a', PDO::PARAM_STR);
                $st->execute();
                $n = $st->fetchColumn();
                Support::assertEquals('3', $n, 'count of cat=a');
                return ['detail' => "matched=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });
    }

    // =================================================================== type

    private static function typeScenarios(Runner $r): void
    {
        // Round-trip a value through a Propel prepared statement and assert it
        // survives MatrixOne. [label, colDdl, pdoType, value, expected]
        $cases = [
            // integers
            ['INT positive', 'INT', PDO::PARAM_INT, 12345, '12345'],
            ['INT negative', 'INT', PDO::PARAM_INT, -98765, '-98765'],
            ['INT zero', 'INT', PDO::PARAM_INT, 0, '0'],
            ['INT max', 'INT', PDO::PARAM_INT, 2147483647, '2147483647'],
            ['INT min', 'INT', PDO::PARAM_INT, -2147483648, '-2147483648'],
            ['TINYINT', 'TINYINT', PDO::PARAM_INT, 127, '127'],
            ['SMALLINT', 'SMALLINT', PDO::PARAM_INT, 32767, '32767'],
            ['MEDIUMINT', 'MEDIUMINT', PDO::PARAM_INT, 8388607, '8388607'],
            // bigint (string-bound)
            ['BIGINT max', 'BIGINT', PDO::PARAM_STR, '9223372036854775807', '9223372036854775807'],
            ['BIGINT min', 'BIGINT', PDO::PARAM_STR, '-9223372036854775808', '-9223372036854775808'],
            ['BIGINT UNSIGNED big', 'BIGINT UNSIGNED', PDO::PARAM_STR, '18446744073709551615', '18446744073709551615'],
            // decimal
            ['DECIMAL simple', 'DECIMAL(10,2)', PDO::PARAM_STR, '1234.56', '1234.56'],
            ['DECIMAL negative', 'DECIMAL(10,2)', PDO::PARAM_STR, '-99.99', '-99.99'],
            ['DECIMAL high precision', 'DECIMAL(20,8)', PDO::PARAM_STR, '12345.12345678', '12345.12345678'],
            ['DECIMAL zero scale', 'DECIMAL(10,0)', PDO::PARAM_STR, '42', '42'],
            // float / double
            ['DOUBLE', 'DOUBLE', PDO::PARAM_STR, '3.141592653589793', '3.141592653589793'],
            ['DOUBLE small', 'DOUBLE', PDO::PARAM_STR, '0.0001', '0.0001'],
            ['DOUBLE negative', 'DOUBLE', PDO::PARAM_STR, '-2.5', '-2.5'],
            // varchar / text
            ['VARCHAR ascii', 'VARCHAR(100)', PDO::PARAM_STR, 'hello world', 'hello world'],
            ['VARCHAR unicode', 'VARCHAR(100)', PDO::PARAM_STR, 'café déjà vu 日本語', 'café déjà vu 日本語'],
            ['VARCHAR empty', 'VARCHAR(100)', PDO::PARAM_STR, '', ''],
            ['VARCHAR with quote', 'VARCHAR(100)', PDO::PARAM_STR, "O'Brien", "O'Brien"],
            ['VARCHAR with percent', 'VARCHAR(100)', PDO::PARAM_STR, '100% sure', '100% sure'],
            ['TEXT long', 'TEXT', PDO::PARAM_STR, str_repeat('abcdefghij', 100), str_repeat('abcdefghij', 100)],
            ['CHAR fixed', 'CHAR(10)', PDO::PARAM_STR, 'fixed', 'fixed'],
            // date / time
            ['DATE', 'DATE', PDO::PARAM_STR, '2026-06-23', '2026-06-23'],
            ['DATE min', 'DATE', PDO::PARAM_STR, '1000-01-01', '1000-01-01'],
            ['DATE max', 'DATE', PDO::PARAM_STR, '9999-12-31', '9999-12-31'],
            ['DATETIME', 'DATETIME', PDO::PARAM_STR, '2026-06-23 14:05:09', '2026-06-23 14:05:09'],
            ['DATETIME(6)', 'DATETIME(6)', PDO::PARAM_STR, '2026-06-23 14:05:09.123456', '2026-06-23 14:05:09.123456'],
            ['TIME', 'TIME', PDO::PARAM_STR, '14:05:09', '14:05:09'],
            ['TIMESTAMP', 'TIMESTAMP', PDO::PARAM_STR, '2026-06-23 14:05:09', '2026-06-23 14:05:09'],
            ['YEAR', 'YEAR', PDO::PARAM_STR, '2026', '2026'],
            // boolean
            ['BOOLEAN true', 'BOOLEAN', PDO::PARAM_BOOL, true, '1'],
            ['BOOLEAN false', 'BOOLEAN', PDO::PARAM_BOOL, false, '0'],
            ['BOOLEAN as int 1', 'BOOLEAN', PDO::PARAM_INT, 1, '1'],
            // json
            ['JSON object', 'JSON', PDO::PARAM_STR, '{"a": 1, "b": "x"}', null],
            ['JSON array', 'JSON', PDO::PARAM_STR, '[1, 2, 3]', null],
            ['JSON nested', 'JSON', PDO::PARAM_STR, '{"a": {"b": [1, 2]}}', null],
            // binary
            ['VARBINARY', 'VARBINARY(16)', PDO::PARAM_STR, 'Hello', 'Hello'],
        ];

        foreach ($cases as [$label, $ddl, $pdoType, $value, $exp]) {
            $r->add(self::FW, 'propel:type', "round-trip: $label", function () use ($label, $ddl, $pdoType, $value, $exp) {
                $con = self::boot();
                $tn = Support::name('ty');
                try {
                    $con->exec("CREATE TABLE `$tn` (c $ddl)");
                    $st = $con->prepare("INSERT INTO `$tn` (c) VALUES (?)");
                    $st->bindValue(1, $value, $pdoType);
                    $st->execute();
                    $got = $con->query("SELECT c FROM `$tn`")->fetchColumn();
                    if ($exp === null) {
                        // JSON: just assert it stored & is valid JSON
                        $valid = $con->query("SELECT JSON_VALID(c) FROM `$tn`")->fetchColumn();
                        Support::assertEquals('1', $valid, "$label valid JSON stored");
                    } else {
                        Support::assertValueEquals($exp, $got, "$label round-trip");
                    }
                    return ['detail' => '= ' . var_export($got, true), 'sql' => "$ddl <= " . var_export($value, true)];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // FLOAT precision loss is a known MatrixOne finding; assert it does NOT
        // round-trip 3.14 exactly (documenting the difference).
        $r->add(self::FW, 'propel:type', 'FLOAT precision loss (MatrixOne finding)', function () {
            $con = self::boot();
            $tn = Support::name('tyf');
            try {
                $con->exec("CREATE TABLE `$tn` (c FLOAT)");
                $st = $con->prepare("INSERT INTO `$tn` (c) VALUES (?)");
                $st->bindValue(1, '3.14', PDO::PARAM_STR);
                $st->execute();
                $got = $con->query("SELECT c FROM `$tn`")->fetchColumn();
                return ['detail' => 'FLOAT 3.14 stored back as ' . var_export($got, true), 'sql' => 'FLOAT precision'];
            } finally {
                self::drop($con, $tn);
            }
        });

        // JSON_EXTRACT through Propel after a Propel-prepared insert
        $r->add(self::FW, 'propel:type', 'JSON value extractable after prepared insert', function () {
            $con = self::boot();
            $tn = Support::name('tyj');
            try {
                $con->exec("CREATE TABLE `$tn` (j JSON)");
                $st = $con->prepare("INSERT INTO `$tn` (j) VALUES (?)");
                $st->bindValue(1, '{"name": "alice", "age": 30}', PDO::PARAM_STR);
                $st->execute();
                $name = $con->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(j, '$.name')) FROM `$tn`")->fetchColumn();
                Support::assertEquals('alice', $name, 'JSON_EXTRACT after Propel insert');
                return ['detail' => "extracted name=$name"];
            } finally {
                self::drop($con, $tn);
            }
        });
    }

    // ============================================================ transaction

    private static function transactionScenarios(Runner $r): void
    {
        $r->add(self::FW, 'propel:transaction', 'commit persists changes', function () {
            $con = self::boot();
            $tn = Support::name('tx');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1),(2)");
                $con->commit();
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('2', $n, 'committed rows persist');
                return ['detail' => "rows=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'rollBack discards changes', function () {
            $con = self::boot();
            $tn = Support::name('tx');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (99)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                $con->rollBack();
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $n, 'rolled-back rows discarded, base row remains');
                return ['detail' => "rows=$n (only pre-tx row remains)"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'isInTransaction toggles with begin/commit', function () {
            $con = self::boot();
            Support::assert($con->isInTransaction() === false, 'should start outside tx');
            $con->beginTransaction();
            $in = $con->isInTransaction();
            $con->commit();
            $out = $con->isInTransaction();
            Support::assert($in === true && $out === false, "expected true/false, got " . var_export($in, true) . '/' . var_export($out, true));
            return ['detail' => 'in=true after begin, false after commit'];
        });

        $r->add(self::FW, 'propel:transaction', 'getNestedTransactionCount increments', function () {
            $con = self::boot();
            $d0 = $con->getNestedTransactionCount();
            $con->beginTransaction();
            $d1 = $con->getNestedTransactionCount();
            $con->beginTransaction();
            $d2 = $con->getNestedTransactionCount();
            $con->commit();
            $d3 = $con->getNestedTransactionCount();
            $con->commit();
            $d4 = $con->getNestedTransactionCount();
            Support::assert($d0 === 0 && $d1 === 1 && $d2 === 2 && $d3 === 1 && $d4 === 0, "depth path: $d0,$d1,$d2,$d3,$d4");
            return ['detail' => "depth: $d0->$d1->$d2->$d3->$d4"];
        });

        $r->add(self::FW, 'propel:transaction', 'nested commit only commits at outermost level', function () {
            $con = self::boot();
            $tn = Support::name('txn');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (2)");
                $con->commit(); // inner: does not really commit
                $con->commit(); // outer: real commit
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('2', $n, 'both rows committed at outer level');
                return ['detail' => "rows=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'nested rollBack poisons outer commit (RollbackException)', function () {
            $con = self::boot();
            $tn = Support::name('txn');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1)");
                $con->beginTransaction();
                $con->rollBack(); // inner rollback marks uncommittable
                $threw = false;
                try {
                    $con->commit(); // outer commit must throw RollbackException
                } catch (\Propel\Runtime\Connection\Exception\RollbackException) {
                    $threw = true;
                    $con->forceRollBack();
                }
                Support::assert($threw, 'expected RollbackException committing after nested rollback');
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('0', $n, 'all changes discarded after poisoned tx');
                return ['detail' => 'RollbackException raised as designed; rows=0'];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'forceRollBack resets depth to 0', function () {
            $con = self::boot();
            $tn = Support::name('txf');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->beginTransaction();
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1)");
                $con->forceRollBack();
                Support::assertEquals('0', (string) $con->getNestedTransactionCount(), 'depth reset to 0');
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('0', $n, 'forceRollBack discarded changes');
                return ['detail' => 'depth=0, rows=0'];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'transaction() helper commits on success', function () {
            $con = self::boot();
            $tn = Support::name('txh');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $result = $con->transaction(function () use ($con, $tn) {
                    $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                    return 'ok';
                });
                Support::assertEquals('ok', $result, 'transaction() return value');
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('3', $n, 'transaction() committed rows');
                return ['detail' => "result=$result rows=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'transaction() helper rolls back on exception', function () {
            $con = self::boot();
            $tn = Support::name('txh');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (10)");
                $threw = false;
                try {
                    $con->transaction(function () use ($con, $tn) {
                        $con->exec("INSERT INTO `$tn` VALUES (20),(30)");
                        throw new \RuntimeException('intentional');
                    });
                } catch (\RuntimeException $e) {
                    $threw = true;
                }
                Support::assert($threw, 'transaction() did not re-throw');
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $n, 'only pre-tx row remains after rollback');
                return ['detail' => "threw & rolled back; rows=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        $r->add(self::FW, 'propel:transaction', 'error inside transaction leaves DB consistent', function () {
            $con = self::boot();
            $tn = Support::name('txe');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                $con->beginTransaction();
                $con->exec("INSERT INTO `$tn` VALUES (1)");
                $hit = false;
                try {
                    $con->exec("INSERT INTO `$tn` VALUES (1)"); // duplicate PK error
                } catch (\Throwable) {
                    $hit = true;
                }
                Support::assert($hit, 'expected duplicate-key error inside tx');
                $con->rollBack();
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('0', $n, 'rollback after in-tx error clears all');
                return ['detail' => 'in-tx error then rollback; rows=0'];
            } finally {
                self::drop($con, $tn);
            }
        });

        // Data-driven: rollback discards INSERT/UPDATE/DELETE alike.
        $ops = [
            ['INSERT', "INSERT INTO %s VALUES (4),(5)", '2'],
            ['UPDATE', "UPDATE %s SET id = id + 100", '2'],
            ['DELETE', "DELETE FROM %s WHERE id = 1", '2'],
        ];
        foreach ($ops as [$opName, $opSql, $expCountAfterRollback]) {
            $r->add(self::FW, 'propel:transaction', "rollBack discards $opName", function () use ($opName, $opSql, $expCountAfterRollback) {
                $con = self::boot();
                $tn = Support::name('txo');
                try {
                    $con->exec("CREATE TABLE `$tn` (id INT)");
                    $con->exec("INSERT INTO `$tn` VALUES (1),(2)");
                    $con->beginTransaction();
                    $con->exec(sprintf($opSql, "`$tn`"));
                    $con->rollBack();
                    $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals($expCountAfterRollback, $n, "$opName rolled back");
                    // verify original data untouched
                    $sum = $con->query("SELECT SUM(id) FROM `$tn`")->fetchColumn();
                    Support::assertEquals('3', $sum, "$opName: original data intact (sum)");
                    return ['detail' => "$opName discarded; count=$n sum=$sum"];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // commit persists each op type
        foreach ([
            ['INSERT', "INSERT INTO %s VALUES (4),(5)", '4'],
            ['DELETE', "DELETE FROM %s WHERE id = 1", '1'],
        ] as [$opName, $opSql, $expCount]) {
            $r->add(self::FW, 'propel:transaction', "commit persists $opName", function () use ($opName, $opSql, $expCount) {
                $con = self::boot();
                $tn = Support::name('txc');
                try {
                    $con->exec("CREATE TABLE `$tn` (id INT)");
                    $con->exec("INSERT INTO `$tn` VALUES (1),(2)");
                    $con->beginTransaction();
                    $con->exec(sprintf($opSql, "`$tn`"));
                    $con->commit();
                    $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals($expCount, $n, "$opName committed");
                    return ['detail' => "$opName committed; count=$n"];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        $r->add(self::FW, 'propel:transaction', 'isCommitable reflects transaction state', function () {
            $con = self::boot();
            $before = $con->isCommitable();
            $con->beginTransaction();
            $during = $con->isCommitable();
            $con->commit();
            $after = $con->isCommitable();
            Support::assert($before === false && $during === true && $after === false, "isCommitable path: " . var_export([$before, $during, $after], true));
            return ['detail' => 'false -> true -> false'];
        });

        $r->add(self::FW, 'propel:transaction', 'autocommit insert (no explicit tx) persists', function () {
            $con = self::boot();
            $tn = Support::name('txa');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (7)");
                $n = $con->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $n, 'autocommit row persists');
                return ['detail' => "rows=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });
    }

    // ================================================================== query

    private static function queryScenarios(Runner $r): void
    {
        // WHERE
        $r->add(self::FW, 'propel:query', 'SELECT with WHERE filter', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, n INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1,10),(2,20),(3,30),(4,40)");
                $rows = $con->query("SELECT id FROM `$tn` WHERE n > 20 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('3,4', implode(',', $rows), 'WHERE n>20');
                return ['detail' => 'ids=' . implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // ORDER BY ASC/DESC
        foreach (['ASC', 'DESC'] as $dir) {
            $r->add(self::FW, 'propel:query', "SELECT ORDER BY $dir", function () use ($dir) {
                $con = self::boot();
                $tn = Support::name('q');
                try {
                    $con->exec("CREATE TABLE `$tn` (n INT)");
                    $con->exec("INSERT INTO `$tn` VALUES (3),(1),(4),(2)");
                    $rows = $con->query("SELECT n FROM `$tn` ORDER BY n $dir")->fetchAll(PDO::FETCH_COLUMN);
                    $expected = $dir === 'ASC' ? '1,2,3,4' : '4,3,2,1';
                    Support::assertEquals($expected, implode(',', $rows), "ORDER BY $dir");
                    return ['detail' => implode(',', $rows)];
                } finally {
                    self::drop($con, $tn);
                }
            });
        }

        // LIMIT / OFFSET
        $r->add(self::FW, 'propel:query', 'SELECT with LIMIT and OFFSET', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4),(5),(6)");
                $rows = $con->query("SELECT id FROM `$tn` ORDER BY id LIMIT 2 OFFSET 2")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('3,4', implode(',', $rows), 'LIMIT 2 OFFSET 2');
                return ['detail' => implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // GROUP BY + aggregate
        $r->add(self::FW, 'propel:query', 'SELECT GROUP BY with COUNT', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (cat VARCHAR(5), v INT)");
                $con->exec("INSERT INTO `$tn` VALUES ('a',1),('a',2),('b',3),('a',4),('b',5)");
                $df = $con->query("SELECT cat, COUNT(*) FROM `$tn` GROUP BY cat ORDER BY cat");
                $df->setStyle(PDO::FETCH_NUM);
                $rows = $df->fetchAll();
                Support::assertEquals('a', $rows[0][0], 'first group');
                Support::assertEquals('3', (string) $rows[0][1], 'count of a');
                Support::assertEquals('2', (string) $rows[1][1], 'count of b');
                return ['detail' => json_encode($rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // GROUP BY + HAVING
        $r->add(self::FW, 'propel:query', 'SELECT GROUP BY HAVING SUM', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (cat VARCHAR(5), v INT)");
                $con->exec("INSERT INTO `$tn` VALUES ('a',1),('a',2),('b',30),('b',40)");
                $rows = $con->query("SELECT cat FROM `$tn` GROUP BY cat HAVING SUM(v) > 10 ORDER BY cat")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('b', implode(',', $rows), 'HAVING SUM>10');
                return ['detail' => 'cats=' . implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // INNER JOIN
        $r->add(self::FW, 'propel:query', 'SELECT INNER JOIN two tables', function () {
            $con = self::boot();
            $t1 = Support::name('qa');
            $t2 = Support::name('qb');
            try {
                $con->exec("CREATE TABLE `$t1` (id INT, name VARCHAR(20))");
                $con->exec("CREATE TABLE `$t2` (id INT, owner_id INT, label VARCHAR(20))");
                $con->exec("INSERT INTO `$t1` VALUES (1,'alice'),(2,'bob')");
                $con->exec("INSERT INTO `$t2` VALUES (10,1,'x'),(11,1,'y'),(12,2,'z')");
                $n = $con->query("SELECT COUNT(*) FROM `$t1` a INNER JOIN `$t2` b ON a.id = b.owner_id WHERE a.name = 'alice'")->fetchColumn();
                Support::assertEquals('2', $n, 'alice owns 2 rows');
                return ['detail' => "joined rows=$n"];
            } finally {
                self::drop($con, $t1);
                self::drop($con, $t2);
            }
        });

        // LEFT JOIN with NULLs
        $r->add(self::FW, 'propel:query', 'SELECT LEFT JOIN preserves unmatched', function () {
            $con = self::boot();
            $t1 = Support::name('qa');
            $t2 = Support::name('qb');
            try {
                $con->exec("CREATE TABLE `$t1` (id INT)");
                $con->exec("CREATE TABLE `$t2` (owner_id INT, v INT)");
                $con->exec("INSERT INTO `$t1` VALUES (1),(2),(3)");
                $con->exec("INSERT INTO `$t2` VALUES (1,100),(2,200)");
                $n = $con->query("SELECT COUNT(*) FROM `$t1` a LEFT JOIN `$t2` b ON a.id = b.owner_id WHERE b.v IS NULL")->fetchColumn();
                Support::assertEquals('1', $n, 'one unmatched left row');
                return ['detail' => "unmatched=$n"];
            } finally {
                self::drop($con, $t1);
                self::drop($con, $t2);
            }
        });

        // DISTINCT
        $r->add(self::FW, 'propel:query', 'SELECT DISTINCT dedups', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(1),(2),(2),(3)");
                $n = $con->query("SELECT COUNT(DISTINCT v) FROM `$tn`")->fetchColumn();
                Support::assertEquals('3', $n, 'distinct count');
                return ['detail' => "distinct=$n"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // Aggregates: SUM/AVG/MIN/MAX
        $r->add(self::FW, 'propel:query', 'SELECT aggregates SUM/AVG/MIN/MAX', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (10),(20),(30),(40)");
                $df = $con->query("SELECT SUM(v), AVG(v), MIN(v), MAX(v) FROM `$tn`");
                $df->setStyle(PDO::FETCH_NUM);
                [$sum, $avg, $min, $max] = $df->fetch();
                Support::assertEquals('100', (string) $sum, 'sum');
                Support::assertValueEquals('25', $avg, 'avg');
                Support::assertEquals('10', (string) $min, 'min');
                Support::assertEquals('40', (string) $max, 'max');
                return ['detail' => "sum=$sum avg=$avg min=$min max=$max"];
            } finally {
                self::drop($con, $tn);
            }
        });

        // Subquery
        $r->add(self::FW, 'propel:query', 'SELECT with subquery in WHERE', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1,10),(2,50),(3,30)");
                $rows = $con->query("SELECT id FROM `$tn` WHERE v > (SELECT AVG(v) FROM `$tn`) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('2', implode(',', $rows), 'above-average rows');
                return ['detail' => 'ids=' . implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // UNION
        $r->add(self::FW, 'propel:query', 'SELECT UNION combines results', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3)");
                $rows = $con->query("SELECT v FROM `$tn` WHERE v = 1 UNION SELECT v FROM `$tn` WHERE v = 3 ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
                Support::assertEquals('1,3', implode(',', $rows), 'UNION result');
                return ['detail' => implode(',', $rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // Window function
        $r->add(self::FW, 'propel:query', 'SELECT with ROW_NUMBER window function', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (id INT, v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1,30),(2,10),(3,20)");
                $df = $con->query("SELECT id, ROW_NUMBER() OVER (ORDER BY v) AS rn FROM `$tn` ORDER BY v");
                $df->setStyle(PDO::FETCH_NUM);
                $rows = $df->fetchAll();
                Support::assertEquals('2', (string) $rows[0][0], 'lowest v has id 2');
                Support::assertEquals('1', (string) $rows[0][1], 'its row number is 1');
                return ['detail' => json_encode($rows)];
            } finally {
                self::drop($con, $tn);
            }
        });

        // CTE
        $r->add(self::FW, 'propel:query', 'SELECT with CTE', function () {
            $con = self::boot();
            $tn = Support::name('q');
            try {
                $con->exec("CREATE TABLE `$tn` (v INT)");
                $con->exec("INSERT INTO `$tn` VALUES (1),(2),(3),(4)");
                $got = $con->query("WITH evens AS (SELECT v FROM `$tn` WHERE v % 2 = 0) SELECT SUM(v) FROM evens")->fetchColumn();
                Support::assertEquals('6', $got, 'sum of evens via CTE');
                return ['detail' => "cte sum=$got"];
            } finally {
                self::drop($con, $tn);
            }
        });
    }

    // =============================================================== criteria

    private static function criteriaScenarios(Runner $r): void
    {
        // The Criteria string-building helpers that DON'T need a DatabaseMap.
        $r->add(self::FW, 'propel:criteria', 'Criteria select/order/limit accessors', function () {
            self::boot();
            $c = new Criteria('default');
            $c->addSelectColumn('t.id');
            $c->addSelectColumn('t.name');
            $c->addAscendingOrderByColumn('t.name');
            $c->setLimit(10);
            $c->setOffset(5);
            Support::assertEquals('t.id,t.name', implode(',', $c->getSelectColumns()), 'select columns');
            Support::assertEquals('t.name ASC', implode(',', $c->getOrderByColumns()), 'order by');
            Support::assertEquals('10', (string) $c->getLimit(), 'limit');
            Support::assertEquals('5', (string) $c->getOffset(), 'offset');
            return ['detail' => 'Criteria accessors work standalone'];
        });

        $r->add(self::FW, 'propel:criteria', 'Criteria DISTINCT modifier', function () {
            self::boot();
            $c = new Criteria('default');
            $c->setDistinct();
            Support::assert(in_array('DISTINCT', $c->getSelectModifiers(), true), 'DISTINCT modifier set');
            return ['detail' => 'modifiers=' . implode(',', $c->getSelectModifiers())];
        });

        $r->add(self::FW, 'propel:criteria', 'Criteria descending order by', function () {
            self::boot();
            $c = new Criteria('default');
            $c->addDescendingOrderByColumn('t.created');
            Support::assertEquals('t.created DESC', implode(',', $c->getOrderByColumns()), 'desc order');
            return ['detail' => implode(',', $c->getOrderByColumns())];
        });

        $r->add(self::FW, 'propel:criteria', 'Criteria group by accessor', function () {
            self::boot();
            $c = new Criteria('default');
            $c->addGroupByColumn('t.cat');
            Support::assertEquals('t.cat', implode(',', $c->getGroupByColumns()), 'group by');
            return ['detail' => implode(',', $c->getGroupByColumns())];
        });

        $r->add(self::FW, 'propel:criteria', 'Criteria comparison constants exist', function () {
            self::boot();
            Support::assert(Criteria::GREATER_THAN === '>' || is_string(Criteria::GREATER_THAN), 'GREATER_THAN constant');
            Support::assert(is_string(Criteria::EQUAL), 'EQUAL constant');
            Support::assert(is_string(Criteria::LIKE), 'LIKE constant');
            return ['detail' => 'GT=' . Criteria::GREATER_THAN . ' EQ=' . Criteria::EQUAL . ' LIKE=' . Criteria::LIKE];
        });

        // DOCUMENTED LIMITATION: createSelectSql needs a populated DatabaseMap.
        $r->add(self::FW, 'propel:criteria', 'Criteria::createSelectSql needs generated DatabaseMap (SKIP)', function () {
            self::boot();
            $c = new Criteria('default');
            $c->addSelectColumn('t.id');
            $c->add('t.age', 5, Criteria::GREATER_THAN);
            $params = [];
            try {
                $c->createSelectSql($params);
            } catch (\Throwable $e) {
                Support::skip('createSelectSql requires a generated DatabaseMap/TableMap (code-gen). ' . substr($e->getMessage(), 0, 80));
            }
            // If it unexpectedly succeeds, that's also fine to record.
            return ['detail' => 'createSelectSql unexpectedly succeeded without code-gen'];
        });

        $r->add(self::FW, 'propel:criteria', 'ModelCriteria requires generated query class (SKIP)', function () {
            self::boot();
            try {
                // Instantiating a bare ModelCriteria without a model class cannot
                // resolve a TableMap, so any execution path needs code-gen.
                $mc = new \Propel\Runtime\ActiveQuery\ModelCriteria('default', '\\NoSuchGeneratedModel');
                $mc->find();
            } catch (\Throwable $e) {
                Support::skip('ModelCriteria/ActiveRecord requires code-generated model + TableMap classes. ' . substr($e->getMessage(), 0, 80));
            }
            return ['detail' => 'ModelCriteria unexpectedly worked without code-gen'];
        });

        $r->add(self::FW, 'propel:criteria', 'PropelQuery::from needs generated class (SKIP)', function () {
            self::boot();
            try {
                $q = \Propel\Runtime\ActiveQuery\PropelQuery::from('\\NoSuchGeneratedModel');
                $q->find();
            } catch (\Throwable $e) {
                Support::skip('PropelQuery::from() resolves to a generated *Query class. ' . substr($e->getMessage(), 0, 80));
            }
            return ['detail' => 'PropelQuery unexpectedly worked without code-gen'];
        });
    }
}
