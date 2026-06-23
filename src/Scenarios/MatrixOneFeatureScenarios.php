<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * MatrixOne headline features (vector search, full-text) plus the practical
 * workarounds an ORM user needs for the documented incompatibilities (most
 * importantly the case-sensitive default collation).
 */
final class MatrixOneFeatureScenarios
{
    public static function register(Runner $r): void
    {
        self::collationWorkarounds($r);
        self::vector($r);
        self::fulltext($r);
        self::upsert($r);
    }

    private static function pdo(): \PDO
    {
        return Connections::pdo(Config::database('pdo'));
    }

    private static function collationWorkarounds(Runner $r): void
    {
        // The normal MySQL fix — request a *_ci collation — and whether it works.
        $r->add('PDO', 'feature:collation', 'COLLATE utf8mb4_general_ci in comparison', function () {
            $v = self::pdo()->query("SELECT 'abc' = 'ABC' COLLATE utf8mb4_general_ci")->fetchColumn();
            if ((string) $v !== '1') {
                throw new \MoTest\BehaviorMismatch("explicit _ci collation should give case-insensitive equality (1); MatrixOne returned $v");
            }
            return 'ci collation honoured';
        });
        $r->add('PDO', 'feature:collation', 'column COLLATE utf8mb4_general_ci case-insensitive WHERE', function () {
            $pdo = self::pdo();
            $tn = Support::name('co');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20) COLLATE utf8mb4_general_ci)");
            try {
                $pdo->exec("INSERT INTO `$tn` VALUES ('alice')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE c='ALICE'")->fetchColumn();
                if ((string) $n !== '1') {
                    throw new \MoTest\BehaviorMismatch("explicit _ci column collation should match 'ALICE'=='alice'; got $n rows");
                }
                return 'ci column matched';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        // The workaround that actually works: LOWER() on both sides.
        $r->add('PDO', 'feature:collation', 'LOWER() workaround gives case-insensitive match', function () {
            $pdo = self::pdo();
            $tn = Support::name('co');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20))");
            try {
                $pdo->exec("INSERT INTO `$tn` VALUES ('alice')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE LOWER(c)=LOWER('ALICE')")->fetchColumn();
                Support::assertEquals('1', $n, 'LOWER() workaround');
                return 'LOWER() works as workaround';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'feature:collation', 'UPPER() comparison equality', function () {
            $v = self::pdo()->query("SELECT UPPER('abc') = UPPER('ABC')")->fetchColumn();
            Support::assertEquals('1', $v, 'UPPER both sides');
            return 'ok';
        });
        $r->add('PDO', 'feature:collation', 'list available *_ci collations', function () {
            $pdo = self::pdo();
            $n = $pdo->query("SELECT COUNT(*) FROM information_schema.collations WHERE collation_name LIKE '%_ci'")->fetchColumn();
            return "$n *_ci collations advertised";
        });
    }

    private static function vector(Runner $r): void
    {
        $with = function (string $name, callable $fn) use ($r) {
            $r->add('PDO', 'feature:vector', $name, function () use ($fn) {
                $pdo = self::pdo();
                try {
                    $pdo->exec('SET experimental_ivf_index=1');
                } catch (\Throwable) {
                }
                $tn = Support::name('vec');
                try {
                    return $fn($pdo, $tn);
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };

        $with('VECF32 column + insert', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF32(3))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'[1,2,3]'),(2,'[4,5,6]')");
            Support::assertEquals(2, $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(), 'vec rows');
            return 'ok';
        });
        $with('VECF64 column + insert', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF64(3))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'[1.5,2.5,3.5]')");
            Support::assert($pdo->query("SELECT v FROM `$t` WHERE id=1")->fetchColumn() !== null, 'vecf64 stored');
            return 'ok';
        });
        $with('KNN by l2_distance', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF32(3))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'[1,0,0]'),(2,'[0,1,0]'),(3,'[0,0,1]')");
            $id = $pdo->query("SELECT id FROM `$t` ORDER BY l2_distance(v,'[0.9,0.1,0]') LIMIT 1")->fetchColumn();
            Support::assertEquals('1', $id, 'nearest by l2');
            return 'ok';
        });
        $with('KNN by cosine_distance', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF32(3))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'[1,0,0]'),(2,'[0,1,0]')");
            $id = $pdo->query("SELECT id FROM `$t` ORDER BY cosine_distance(v,'[1,0.1,0]') LIMIT 1")->fetchColumn();
            Support::assertEquals('1', $id, 'nearest by cosine');
            return 'ok';
        });
        $with('IVFFLAT index creation', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF32(3))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'[1,0,0]'),(2,'[0,1,0]')");
            $pdo->exec("CREATE INDEX idx_v USING ivfflat ON `$t`(v) lists=1 op_type 'vector_l2_ops'");
            return 'ivfflat index created';
        });
        $with('vector arithmetic (+)', function (\PDO $pdo, string $t) {
            $v = $pdo->query("SELECT '[1,2,3]' + '[1,1,1]'")->fetchColumn();
            Support::assert($v !== null && $v !== false, 'vector add returned nothing');
            return "sum=$v";
        });
        $with('inner_product function', function (\PDO $pdo, string $t) {
            $v = $pdo->query("SELECT inner_product('[1,2,3]','[1,1,1]')")->fetchColumn();
            Support::assert($v !== null, 'inner_product null');
            return "ip=$v";
        });
        $with('dimension mismatch rejected', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, v VECF32(3))");
            $rejected = false;
            try {
                $pdo->exec("INSERT INTO `$t` VALUES (1,'[1,2]')");
            } catch (\PDOException) {
                $rejected = true;
            }
            Support::assert($rejected, 'dimension mismatch was not rejected');
            return 'dimension enforced';
        });
    }

    private static function fulltext(Runner $r): void
    {
        $with = function (string $name, callable $fn) use ($r) {
            $r->add('PDO', 'feature:fulltext', $name, function () use ($fn) {
                $pdo = self::pdo();
                try {
                    $pdo->exec('SET experimental_fulltext_index=1');
                } catch (\Throwable) {
                }
                $tn = Support::name('ft');
                try {
                    return $fn($pdo, $tn);
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };

        $with('FULLTEXT index + natural language MATCH', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'the quick brown fox'),(2,'lazy dog sleeps')");
            $n = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE MATCH(body) AGAINST('fox')")->fetchColumn();
            Support::assert((int) $n >= 1, "MATCH AGAINST matched $n rows");
            return "matched $n";
        });
        $with('FULLTEXT boolean mode', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'apple banana'),(2,'banana cherry')");
            $n = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE MATCH(body) AGAINST('+banana -cherry' IN BOOLEAN MODE)")->fetchColumn();
            Support::assert((int) $n >= 1, "boolean mode matched $n");
            return "matched $n";
        });
        $with('FULLTEXT relevance score', function (\PDO $pdo, string $t) {
            $pdo->exec("CREATE TABLE `$t` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))");
            $pdo->exec("INSERT INTO `$t` VALUES (1,'database systems'),(2,'distributed database database')");
            $rows = $pdo->query("SELECT id, MATCH(body) AGAINST('database') AS score FROM `$t` ORDER BY score DESC")->fetchAll(\PDO::FETCH_ASSOC);
            Support::assert(count($rows) >= 1, 'no relevance rows');
            return 'scored ' . count($rows) . ' rows';
        });
    }

    private static function upsert(Runner $r): void
    {
        $with = function (string $name, callable $fn) use ($r) {
            $r->add('PDO', 'feature:upsert', $name, function () use ($fn) {
                $pdo = self::pdo();
                $tn = Support::name('up');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, n INT, s VARCHAR(20))");
                try {
                    return $fn($pdo, $tn);
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };
        $with('ON DUPLICATE KEY UPDATE inserts then updates', function (\PDO $pdo, string $t) {
            $pdo->exec("INSERT INTO `$t`(id,n) VALUES (1,10) ON DUPLICATE KEY UPDATE n=VALUES(n)");
            $pdo->exec("INSERT INTO `$t`(id,n) VALUES (1,20) ON DUPLICATE KEY UPDATE n=VALUES(n)");
            Support::assertEquals('20', $pdo->query("SELECT n FROM `$t` WHERE id=1")->fetchColumn(), 'upsert update');
            return 'ok';
        });
        $with('REPLACE INTO', function (\PDO $pdo, string $t) {
            $pdo->exec("INSERT INTO `$t`(id,n,s) VALUES (1,1,'a')");
            $pdo->exec("REPLACE INTO `$t`(id,n) VALUES (1,9)");
            Support::assertEquals('9', $pdo->query("SELECT n FROM `$t` WHERE id=1")->fetchColumn(), 'replace');
            return 'ok';
        });
        $with('INSERT IGNORE skips duplicate', function (\PDO $pdo, string $t) {
            $pdo->exec("INSERT INTO `$t`(id,n) VALUES (1,1)");
            $pdo->exec("INSERT IGNORE INTO `$t`(id,n) VALUES (1,2)");
            Support::assertEquals('1', $pdo->query("SELECT n FROM `$t` WHERE id=1")->fetchColumn(), 'insert ignore');
            return 'ok';
        });
        $with('ON DUPLICATE with VALUES() expression', function (\PDO $pdo, string $t) {
            $pdo->exec("INSERT INTO `$t`(id,n) VALUES (1,5)");
            $pdo->exec("INSERT INTO `$t`(id,n) VALUES (1,3) ON DUPLICATE KEY UPDATE n=n+VALUES(n)");
            Support::assertEquals('8', $pdo->query("SELECT n FROM `$t` WHERE id=1")->fetchColumn(), 'n+VALUES(n)');
            return 'ok';
        });
    }
}
