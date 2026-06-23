<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Broad, data-driven SQL-semantics matrices that complement (and do not
 * duplicate) the existing function/SQL providers. Focus areas:
 *   - deep vector search matrix (distance metrics x dims x ops x index params),
 *   - advanced window functions & frame specifications,
 *   - operator / predicate / comparison matrices,
 *   - set-operation depth, subquery depth, EXISTS/ANY/ALL,
 *   - regular-expression matrix,
 *   - conditional / flow expression matrix,
 *   - aggregate-with-modifier matrix,
 *   - string builtins not covered elsewhere.
 *
 * Every cell of every grid is one registered scenario. Conventions follow
 * TypeMatrixScenarios: fresh PDO per scenario, exec() for writes / query() for
 * reads, unique table names via Support::name() dropped in finally, and every
 * closure captures all referenced variables in use(...).
 */
final class SqlSemanticsScenarios
{
    private const FW = 'PDO';

    public static function register(Runner $r): void
    {
        self::vectorMatrix($r);
        self::windowMatrix($r);
        self::operatorMatrix($r);
        self::predicateMatrix($r);
        self::setOpMatrix($r);
        self::subqueryMatrix($r);
        self::regexMatrix($r);
        self::flowMatrix($r);
        self::aggregateModifierMatrix($r);
        self::stringMatrix($r);
        self::numericFnMatrix($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    /** Evaluate a scalar expression and (optionally) assert its value. */
    private static function addExpr(Runner $r, string $cat, string $name, string $expr, mixed $expected = null, string $mode = 'any'): void
    {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $expr, $expected, $mode, $name) {
            $pdo = Connections::pdo($db);
            $sql = "SELECT $expr AS v";
            $got = $pdo->query($sql)->fetchColumn();
            switch ($mode) {
                case 'null':
                    Support::assert($got === null, "$name: expected NULL got " . var_export($got, true));
                    break;
                case 'notnull':
                    Support::assert($got !== null, "$name: got NULL");
                    break;
                case 'value':
                    Support::assertValueEquals($expected, $got, $name);
                    break;
                case 'exact':
                    Support::assertEquals($expected, $got, $name);
                    break;
                case 'any':
                default:
                    break;
            }
            return ['detail' => '= ' . self::repr($got), 'sql' => $sql];
        });
    }

    private static function repr(mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        $s = (string) $v;
        return strlen($s) > 50 ? substr($s, 0, 50) . '…' : $s;
    }

    // =========================================================== VECTOR matrix

    private static function vectorMatrix(Runner $r): void
    {
        $db = self::db();
        // distance functions over a grid of dimension sizes and vector pairs.
        $metrics = ['l2_distance', 'l2_distance_sq', 'cosine_distance', 'cosine_similarity', 'inner_product'];
        $dims = [2, 3, 4, 8, 16, 32, 64, 128];
        foreach ($metrics as $metric) {
            foreach ($dims as $d) {
                $a = '[' . implode(',', array_fill(0, $d, 1)) . ']';
                $b = '[' . implode(',', array_map(fn ($i) => $i % 3, range(0, $d - 1))) . ']';
                self::addExpr(
                    $r,
                    'sem:vector-fn',
                    "$metric dim=$d",
                    "$metric('$a','$b')",
                    null,
                    'notnull'
                );
            }
        }
        // helper / arithmetic functions
        $helpers = [
            ['normalize_l2 3d', "normalize_l2('[3,4,0]')"],
            ['vector_dims', "vector_dims('[1,2,3,4,5]')"],
            ['subvector', "subvector('[1,2,3,4,5]',2,3)"],
            ['cast vecf32', "CAST('[1,2,3]' AS VECF32(3))"],
            ['cast vecf64', "CAST('[1,2,3]' AS VECF64(3))"],
            ['vec add', "'[1,2,3]' + '[1,1,1]'"],
            ['vec sub', "'[5,5,5]' - '[1,2,3]'"],
            ['vec mul', "'[1,2,3]' * '[2,2,2]'"],
            ['l1_distance (gap?)', "l1_distance('[1,2]','[3,4]')"],
            ['sqrt of l2_sq', "SQRT(l2_distance_sq('[0,0]','[3,4]'))"],
        ];
        foreach ($helpers as [$nm, $expr]) {
            self::addExpr($r, 'sem:vector-fn', $nm, $expr, null, 'notnull');
        }

        // KNN over a built table, across metrics, dims, and op_type index params.
        $knn = [
            ['l2', 'l2_distance', 'vector_l2_ops'],
            ['cosine', 'cosine_distance', 'vector_cosine_ops'],
            ['ip', 'inner_product', 'vector_ip_ops'],
        ];
        foreach ($knn as [$tag, $fn, $opType]) {
            foreach ([3, 8, 16] as $d) {
                // plain KNN (no index)
                $r->add(self::FW, 'sem:vector-knn', "KNN $tag dim=$d no-index", function () use ($db, $fn, $d) {
                    $pdo = Connections::pdo($db);
                    $tn = Support::name('vk');
                    try {
                        $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, v VECF32($d))");
                        for ($i = 1; $i <= 5; $i++) {
                            $vec = '[' . implode(',', array_map(fn ($j) => ($i + $j) % 7, range(0, $d - 1))) . ']';
                            $pdo->exec("INSERT INTO `$tn` VALUES ($i,'$vec')");
                        }
                        $q = '[' . implode(',', array_fill(0, $d, 1)) . ']';
                        $id = $pdo->query("SELECT id FROM `$tn` ORDER BY $fn(v,'$q') LIMIT 1")->fetchColumn();
                        Support::assert($id !== false && $id !== null, "KNN returned no row");
                        return ['detail' => "nearest id=$id", 'sql' => "ORDER BY $fn(v,q) LIMIT 1"];
                    } finally {
                        try {
                            $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                        } catch (\Throwable) {
                        }
                    }
                });
                // KNN with ivfflat index of varying lists
                foreach ([1, 4, 16] as $lists) {
                    $r->add(self::FW, 'sem:vector-index', "ivfflat $tag dim=$d lists=$lists", function () use ($db, $fn, $opType, $d, $lists) {
                        $pdo = Connections::pdo($db);
                        try {
                            $pdo->exec('SET experimental_ivf_index=1');
                        } catch (\Throwable) {
                        }
                        $tn = Support::name('vi');
                        try {
                            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, v VECF32($d))");
                            for ($i = 1; $i <= 8; $i++) {
                                $vec = '[' . implode(',', array_map(fn ($j) => ($i * 2 + $j) % 5, range(0, $d - 1))) . ']';
                                $pdo->exec("INSERT INTO `$tn` VALUES ($i,'$vec')");
                            }
                            $pdo->exec("CREATE INDEX idx_v USING ivfflat ON `$tn`(v) lists=$lists op_type '$opType'");
                            $q = '[' . implode(',', array_fill(0, $d, 1)) . ']';
                            $id = $pdo->query("SELECT id FROM `$tn` ORDER BY $fn(v,'$q') LIMIT 1")->fetchColumn();
                            Support::assert($id !== false && $id !== null, 'indexed KNN returned no row');
                            return ['detail' => "index built lists=$lists nearest=$id", 'sql' => "ivfflat lists=$lists op_type $opType"];
                        } finally {
                            try {
                                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                            } catch (\Throwable) {
                            }
                        }
                    });
                }
            }
        }
        // hybrid filter + vector
        foreach ([3, 8] as $d) {
            $r->add(self::FW, 'sem:vector-hybrid', "filter + KNN dim=$d", function () use ($db, $d) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('vh');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, cat INT, v VECF32($d))");
                    for ($i = 1; $i <= 10; $i++) {
                        $vec = '[' . implode(',', array_map(fn ($j) => ($i + $j) % 4, range(0, $d - 1))) . ']';
                        $cat = $i % 2;
                        $pdo->exec("INSERT INTO `$tn` VALUES ($i,$cat,'$vec')");
                    }
                    $q = '[' . implode(',', array_fill(0, $d, 1)) . ']';
                    $rows = $pdo->query("SELECT id FROM `$tn` WHERE cat=1 ORDER BY l2_distance(v,'$q') LIMIT 3")->fetchAll(\PDO::FETCH_COLUMN);
                    Support::assert(count($rows) > 0, 'hybrid filter+vector returned no rows');
                    return ['detail' => 'ids=' . implode(',', $rows), 'sql' => 'WHERE cat=1 ORDER BY l2_distance LIMIT 3'];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
    }

    // =========================================================== WINDOW matrix

    private static function windowMatrix(Runner $r): void
    {
        $db = self::db();
        // window function x frame spec grid, evaluated over a fixed 8-row fixture.
        $fns = [
            'ROW_NUMBER()', 'RANK()', 'DENSE_RANK()', 'PERCENT_RANK()', 'CUME_DIST()',
            'SUM(val)', 'AVG(val)', 'MIN(val)', 'MAX(val)', 'COUNT(*)',
            'FIRST_VALUE(val)', 'LAST_VALUE(val)', 'NTH_VALUE(val,2)',
            'LAG(val)', 'LEAD(val)', 'LAG(val,2,0)', 'LEAD(val,2,0)', 'NTILE(3)',
        ];
        $frames = [
            'plain' => 'OVER (PARTITION BY grp ORDER BY val)',
            'rows-unbounded' => 'OVER (PARTITION BY grp ORDER BY val ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)',
            'rows-1-1' => 'OVER (PARTITION BY grp ORDER BY val ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING)',
            'rows-curr-unbounded' => 'OVER (PARTITION BY grp ORDER BY val ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING)',
            'range' => 'OVER (PARTITION BY grp ORDER BY val RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)',
            'no-partition' => 'OVER (ORDER BY val)',
        ];
        foreach ($fns as $fn) {
            foreach ($frames as $ftag => $frame) {
                // Some fn/frame combos are illegal (ranking fns don't take frames);
                // we still register them — illegal combos become genuine findings.
                $r->add(self::FW, 'sem:window', "$fn $ftag", function () use ($db, $fn, $frame, $ftag) {
                    $pdo = Connections::pdo($db);
                    $tn = Support::name('wn');
                    try {
                        $pdo->exec("CREATE TABLE `$tn` (grp INT, val INT)");
                        $pdo->exec("INSERT INTO `$tn` VALUES (1,10),(1,20),(1,30),(1,30),(2,5),(2,15),(2,25),(2,35)");
                        $rows = $pdo->query("SELECT $fn $frame AS w FROM `$tn`")->fetchAll(\PDO::FETCH_COLUMN);
                        Support::assert(count($rows) === 8, "window produced " . count($rows) . " rows (expected 8)");
                        return ['detail' => 'w=[' . implode(',', array_map(fn ($v) => $v ?? 'NULL', $rows)) . ']', 'sql' => "$fn $frame"];
                    } finally {
                        try {
                            $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                        } catch (\Throwable) {
                        }
                    }
                });
            }
        }
    }

    // ========================================================= OPERATOR matrix

    private static function operatorMatrix(Runner $r): void
    {
        // arithmetic, bitwise, comparison operators over typed operand pairs.
        $cases = [
            // [name, expr, expected, mode]
            ['add ints', '3 + 4', '7', 'value'],
            ['sub ints', '10 - 3', '7', 'value'],
            ['mul ints', '6 * 7', '42', 'value'],
            ['div ints', '7 / 2', '3.5000', 'value'],
            ['int div', '7 DIV 2', '3', 'value'],
            ['mod', '7 % 3', '1', 'value'],
            ['mod fn', 'MOD(7,3)', '1', 'value'],
            ['neg', '-(5)', '-5', 'value'],
            ['div by zero', '1 / 0', null, 'null'],
            ['mod by zero', '1 % 0', null, 'null'],
            ['pow op', '2 * 2 * 2', '8', 'value'],
            ['bit and', '12 & 10', '8', 'value'],
            ['bit or', '12 | 10', '14', 'value'],
            ['bit xor', '12 ^ 10', '6', 'value'],
            ['bit not', '~0 & 255', '255', 'value'],
            ['shift left', '1 << 8', '256', 'value'],
            ['shift right', '1024 >> 4', '64', 'value'],
            ['eq true', '5 = 5', '1', 'value'],
            ['eq false', '5 = 6', '0', 'value'],
            ['ne', '5 <> 6', '1', 'value'],
            ['ne2', '5 != 6', '1', 'value'],
            ['lt', '3 < 5', '1', 'value'],
            ['le', '5 <= 5', '1', 'value'],
            ['gt', '8 > 2', '1', 'value'],
            ['ge', '8 >= 9', '0', 'value'],
            ['null-safe eq', '1 <=> 1', '1', 'value'],
            ['null-safe eq null', 'NULL <=> NULL', '1', 'value'],
            ['and', '1 AND 1', '1', 'value'],
            ['or', '0 OR 1', '1', 'value'],
            ['xor', '1 XOR 0', '1', 'value'],
            ['not', 'NOT 0', '1', 'value'],
            ['and&&', '1 && 1', '1', 'value'],
            ['or||', '0 || 1', null, 'notnull'],
            ['between', '5 BETWEEN 1 AND 10', '1', 'value'],
            ['not between', '5 NOT BETWEEN 1 AND 4', '1', 'value'],
            ['in list', '3 IN (1,2,3)', '1', 'value'],
            ['not in list', '5 NOT IN (1,2,3)', '1', 'value'],
            ['is true', '1 IS TRUE', '1', 'value'],
            ['is false', '0 IS FALSE', '1', 'value'],
            ['is not true', 'NULL IS NOT TRUE', '1', 'value'],
            ['like pct', "'hello' LIKE 'h%'", '1', 'value'],
            ['like underscore', "'cat' LIKE 'c_t'", '1', 'value'],
            ['not like', "'dog' NOT LIKE 'c%'", '1', 'value'],
            ['like escape', "'50%' LIKE '50\\%'", '1', 'value'],
            ['regexp', "'abc' REGEXP '^a'", '1', 'value'],
            ['not regexp', "'abc' NOT REGEXP '^z'", '1', 'value'],
            ['concat ws', "CONCAT('a','b','c')", 'abc', 'value'],
        ];
        foreach ($cases as [$nm, $expr, $exp, $mode]) {
            self::addExpr($r, 'sem:operator', $nm, $expr, $exp, $mode);
        }
        // operator precedence chains
        $prec = [
            ['2 + 3 * 4', '14'],
            ['(2 + 3) * 4', '20'],
            ['10 - 2 - 3', '5'],
            ['2 * 3 + 4 * 5', '26'],
            ['1 + 2 > 2', '1'],
            ['NOT 1 = 0', null],
            ['1 OR 0 AND 0', '1'],
            ['(1 OR 0) AND 0', '0'],
            ['5 & 3 | 8', '9'],
            ['100 / 10 / 2', '5.00000'],
        ];
        foreach ($prec as $i => [$expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'sem:precedence', "prec: $expr", $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'sem:precedence', "prec: $expr", $expr, $exp, 'value');
            }
        }
    }

    // ======================================================== PREDICATE matrix

    private static function predicateMatrix(Runner $r): void
    {
        $db = self::db();
        // EXISTS / NOT EXISTS / IN subquery / ANY / ALL / correlated, over a
        // fixture of two tables.
        $preds = [
            ['EXISTS true', "EXISTS (SELECT 1 FROM `{t2}` WHERE v > 0)", '4'],
            ['NOT EXISTS', "NOT EXISTS (SELECT 1 FROM `{t2}` WHERE v > 999)", '4'],
            ['IN subquery', "n IN (SELECT v FROM `{t2}`)", null],
            ['NOT IN subquery', "n NOT IN (SELECT v FROM `{t2}` WHERE v < 0)", null],
            ['= ANY', "n = ANY (SELECT v FROM `{t2}`)", null],
            ['> ALL', "n > ALL (SELECT v FROM `{t2}` WHERE v < 0)", null],
            ['< ANY', "n < ANY (SELECT v FROM `{t2}`)", null],
            ['correlated EXISTS', "EXISTS (SELECT 1 FROM `{t2}` WHERE v = n)", null],
            ['scalar subquery cmp', "n > (SELECT AVG(v) FROM `{t2}`)", null],
            ['row subquery IN', "n IN (SELECT MAX(v) FROM `{t2}`)", null],
        ];
        foreach ($preds as [$nm, $pred, $exp]) {
            $r->add(self::FW, 'sem:predicate', $nm, function () use ($db, $pred, $exp, $nm) {
                $pdo = Connections::pdo($db);
                $t1 = Support::name('p1');
                $t2 = Support::name('p2');
                $sub = fn (string $s) => strtr($s, ['{t}' => $t1, '{t2}' => $t2]);
                try {
                    $pdo->exec("CREATE TABLE `$t1` (n INT)");
                    $pdo->exec("CREATE TABLE `$t2` (v INT)");
                    $pdo->exec("INSERT INTO `$t1` VALUES (1),(2),(3),(4)");
                    $pdo->exec("INSERT INTO `$t2` VALUES (2),(3),(5)");
                    $q = "SELECT COUNT(*) FROM `$t1` WHERE " . $sub($pred);
                    $got = $pdo->query($q)->fetchColumn();
                    if ($exp !== null) {
                        Support::assertEquals($exp, $got, $nm);
                    }
                    return ['detail' => "count=$got", 'sql' => $q];
                } finally {
                    foreach ([$t1, $t2] as $t) {
                        try {
                            $pdo->exec("DROP TABLE IF EXISTS `$t`");
                        } catch (\Throwable) {
                        }
                    }
                }
            });
        }
    }

    // =========================================================== SET-OP matrix

    private static function setOpMatrix(Runner $r): void
    {
        // UNION / UNION ALL / INTERSECT / EXCEPT with varying arms and modifiers.
        $ops = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT', 'INTERSECT ALL', 'EXCEPT ALL'];
        $shapes = [
            ['2 arms', ['SELECT 1', 'SELECT 2']],
            ['3 arms', ['SELECT 1', 'SELECT 2', 'SELECT 3']],
            ['overlap', ['SELECT 1 UNION ALL SELECT 2', 'SELECT 2 UNION ALL SELECT 3']],
            ['dup arm', ['SELECT 1 UNION ALL SELECT 1', 'SELECT 1']],
        ];
        foreach ($ops as $op) {
            foreach ($shapes as [$tag, $arms]) {
                // Build  (arm1) OP (arm2) [OP (arm3)]
                $parts = array_map(fn ($a) => "($a)", $arms);
                $sqlInner = implode(" $op ", $parts);
                self::addExpr(
                    $r,
                    'sem:setop',
                    "$op $tag",
                    "(SELECT COUNT(*) FROM ($sqlInner) AS s)",
                    null,
                    'notnull'
                );
            }
        }
        // ordered / limited set ops
        $extra = [
            ['UNION ORDER BY', "SELECT * FROM ((SELECT 3) UNION (SELECT 1) UNION (SELECT 2)) s ORDER BY 1 LIMIT 1", '1'],
            ['UNION ALL count', "SELECT COUNT(*) FROM ((SELECT 1) UNION ALL (SELECT 1) UNION ALL (SELECT 1)) s", '3'],
            ['UNION distinct count', "SELECT COUNT(*) FROM ((SELECT 1) UNION (SELECT 1) UNION (SELECT 1)) s", '1'],
            ['INTERSECT count', "SELECT COUNT(*) FROM ((SELECT 1 UNION SELECT 2) INTERSECT (SELECT 2 UNION SELECT 3)) s", null],
            ['EXCEPT count', "SELECT COUNT(*) FROM ((SELECT 1 UNION SELECT 2) EXCEPT (SELECT 2)) s", null],
        ];
        foreach ($extra as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'sem:setop', $nm, "($expr)", null, 'notnull');
            } else {
                self::addExpr($r, 'sem:setop', $nm, "($expr)", $exp, 'value');
            }
        }
    }

    // ========================================================= SUBQUERY matrix

    private static function subqueryMatrix(Runner $r): void
    {
        $db = self::db();
        $cases = [
            ['scalar in SELECT', "SELECT (SELECT MAX(v) FROM `{t}`) AS m"],
            ['derived table', "SELECT COUNT(*) FROM (SELECT v FROM `{t}` WHERE v > 1) d"],
            ['subquery in FROM with agg', "SELECT AVG(s) FROM (SELECT SUM(v) s FROM `{t}` GROUP BY v) d"],
            ['nested 2-level', "SELECT (SELECT (SELECT MIN(v) FROM `{t}`)) AS x"],
            ['subquery in WHERE', "SELECT COUNT(*) FROM `{t}` WHERE v = (SELECT MAX(v) FROM `{t}`)"],
            ['subquery in HAVING', "SELECT v FROM `{t}` GROUP BY v HAVING COUNT(*) >= (SELECT 1)"],
            ['correlated scalar', "SELECT v, (SELECT COUNT(*) FROM `{t}` b WHERE b.v <= a.v) FROM `{t}` a"],
            ['lateral-ish derived', "SELECT d.v FROM (SELECT v FROM `{t}` ORDER BY v LIMIT 2) d"],
            ['EXISTS in SELECT', "SELECT EXISTS(SELECT 1 FROM `{t}` WHERE v=2)"],
            ['IN with constants', "SELECT COUNT(*) FROM `{t}` WHERE v IN (1,3,5)"],
            ['subquery + UNION', "SELECT COUNT(*) FROM ((SELECT v FROM `{t}`) UNION (SELECT 99)) s"],
            ['subquery with LIMIT', "SELECT MAX(v) FROM (SELECT v FROM `{t}` ORDER BY v DESC LIMIT 2) d"],
        ];
        foreach ($cases as [$nm, $sqlTpl]) {
            $r->add(self::FW, 'sem:subquery', $nm, function () use ($db, $sqlTpl, $nm) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('sq');
                $sql = strtr($sqlTpl, ['{t}' => $tn]);
                try {
                    $pdo->exec("CREATE TABLE `$tn` (v INT)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (1),(2),(2),(3),(5)");
                    $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_NUM);
                    Support::assert(count($rows) >= 1, "$nm produced no rows");
                    return ['detail' => 'rows=' . count($rows), 'sql' => $sql];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
    }

    // ============================================================ REGEX matrix

    private static function regexMatrix(Runner $r): void
    {
        $cases = [
            ['anchor start', "'hello' REGEXP '^h'", '1'],
            ['anchor end', "'hello' REGEXP 'o$'", '1'],
            ['char class', "'abc123' REGEXP '[0-9]+'", '1'],
            ['alternation', "'cat' REGEXP 'cat|dog'", '1'],
            ['quantifier *', "'aaa' REGEXP 'a*'", '1'],
            ['quantifier +', "'' REGEXP 'a+'", '0'],
            ['quantifier ?', "'color' REGEXP 'colou?r'", '1'],
            ['range count', "'aaa' REGEXP 'a{3}'", '1'],
            ['word boundary', "'one two' REGEXP 'two'", '1'],
            ['negated class', "'abc' REGEXP '[^0-9]'", '1'],
            ['dot', "'a.b' REGEXP 'a.b'", '1'],
            ['case sensitive', "'ABC' REGEXP 'abc'", null],
            ['REGEXP_LIKE', "REGEXP_LIKE('abc','^a')", '1'],
            ['REGEXP_INSTR', "REGEXP_INSTR('abcabc','b')", '2'],
            ['REGEXP_INSTR pos', "REGEXP_INSTR('abcabc','b',3)", '5'],
            ['REGEXP_SUBSTR', "REGEXP_SUBSTR('a1b2c3','[0-9]')", '1'],
            ['REGEXP_REPLACE', "REGEXP_REPLACE('a1b2','[0-9]','#')", 'a#b#'],
            ['REGEXP_REPLACE occ', "REGEXP_REPLACE('aaa','a','b',1,2)", null],
            ['REGEXP_LIKE flag i', "REGEXP_LIKE('ABC','abc','i')", null],
        ];
        foreach ($cases as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'sem:regex', $nm, $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'sem:regex', $nm, $expr, $exp, 'value');
            }
        }
    }

    // ============================================================= FLOW matrix

    private static function flowMatrix(Runner $r): void
    {
        $cases = [
            ['CASE simple', "CASE 2 WHEN 1 THEN 'a' WHEN 2 THEN 'b' ELSE 'c' END", 'b'],
            ['CASE searched', "CASE WHEN 5>3 THEN 'big' ELSE 'small' END", 'big'],
            ['CASE no else', "CASE WHEN 1=0 THEN 'x' END", null],
            ['CASE nested', "CASE WHEN 1=1 THEN CASE WHEN 2=2 THEN 'yy' END END", 'yy'],
            ['IF basic', "IF(1>0, 'pos', 'neg')", 'pos'],
            ['IFNULL', "IFNULL(NULL, 'fallback')", 'fallback'],
            ['IFNULL non-null', "IFNULL('x', 'y')", 'x'],
            ['NULLIF equal', 'NULLIF(7,7)', null],
            ['NULLIF diff', 'NULLIF(7,8)', '7'],
            ['COALESCE', 'COALESCE(NULL, NULL, 3, 4)', '3'],
            ['COALESCE all null', 'COALESCE(NULL, NULL)', null],
            ['GREATEST', 'GREATEST(3, 7, 2, 9, 1)', '9'],
            ['LEAST', 'LEAST(3, 7, 2, 9, 1)', '1'],
            ['ELT', "ELT(2, 'a', 'b', 'c')", 'b'],
            ['FIELD', "FIELD('b', 'a', 'b', 'c')", '2'],
            ['CASE with agg', null, null], // placeholder skip below
        ];
        foreach ($cases as [$nm, $expr, $exp]) {
            if ($expr === null) {
                continue;
            }
            if ($exp === null) {
                self::addExpr($r, 'sem:flow', $nm, $expr, null, 'null');
            } else {
                self::addExpr($r, 'sem:flow', $nm, $expr, $exp, 'value');
            }
        }
        // CASE inside aggregation (pivot pattern)
        $db = self::db();
        $r->add(self::FW, 'sem:flow', 'conditional aggregation pivot', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('fl');
            try {
                $pdo->exec("CREATE TABLE `$tn` (cat VARCHAR(5), amt INT)");
                $pdo->exec("INSERT INTO `$tn` VALUES ('a',10),('b',20),('a',5),('b',7)");
                $row = $pdo->query("SELECT SUM(CASE WHEN cat='a' THEN amt ELSE 0 END) AS a, SUM(CASE WHEN cat='b' THEN amt ELSE 0 END) AS b FROM `$tn`")->fetch(\PDO::FETCH_ASSOC);
                Support::assertEquals('15', $row['a'], 'pivot a');
                Support::assertEquals('27', $row['b'], 'pivot b');
                return ['detail' => "a={$row['a']} b={$row['b']}", 'sql' => 'conditional SUM pivot'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
    }

    // ============================================== AGGREGATE-MODIFIER matrix

    private static function aggregateModifierMatrix(Runner $r): void
    {
        $db = self::db();
        // aggregate fn x modifier (DISTINCT / all / filter-ish) over a fixture.
        $aggs = [
            ['COUNT(*)', '7'],
            ['COUNT(v)', '6'],
            ['COUNT(DISTINCT v)', '4'],
            ['SUM(v)', null],
            ['SUM(DISTINCT v)', null],
            ['AVG(v)', null],
            ['AVG(DISTINCT v)', null],
            ['MIN(v)', '1'],
            ['MAX(v)', '5'],
            ['STD(v)', null],
            ['STDDEV(v)', null],
            ['STDDEV_POP(v)', null],
            ['STDDEV_SAMP(v)', null],
            ['VARIANCE(v)', null],
            ['VAR_POP(v)', null],
            ['VAR_SAMP(v)', null],
            ['BIT_AND(v)', null],
            ['BIT_OR(v)', null],
            ['BIT_XOR(v)', null],
            ['GROUP_CONCAT(v)', null],
            ['GROUP_CONCAT(DISTINCT v ORDER BY v)', null],
            ['GROUP_CONCAT(v SEPARATOR ";")', null],
            ['JSON_ARRAYAGG(v)', null],
            ['ANY_VALUE(v)', null],
        ];
        foreach ($aggs as [$agg, $exp]) {
            $r->add(self::FW, 'sem:aggregate', "$agg", function () use ($db, $agg, $exp) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('ag');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (v INT)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (1),(2),(2),(3),(5),(5),(NULL)");
                    $got = $pdo->query("SELECT $agg FROM `$tn`")->fetchColumn();
                    if ($exp !== null) {
                        Support::assertEquals($exp, $got, $agg);
                    } else {
                        Support::assert($got !== false, "$agg returned no value");
                    }
                    return ['detail' => '= ' . self::repr($got), 'sql' => "SELECT $agg"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // GROUP BY with ROLLUP / GROUPING SETS / CUBE on a real 2-dim fixture
        $groupers = [
            ['WITH ROLLUP', 'GROUP BY a, b WITH ROLLUP'],
            ['GROUPING SETS full', 'GROUP BY GROUPING SETS ((a,b),(a),(b),())'],
            ['GROUPING SETS a', 'GROUP BY GROUPING SETS ((a),())'],
            ['CUBE', 'GROUP BY CUBE(a,b)'],
            ['plain 2-col', 'GROUP BY a, b'],
        ];
        foreach ($groupers as [$nm, $clause]) {
            $r->add(self::FW, 'sem:grouping', $nm, function () use ($db, $clause, $nm) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('gr');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, m INT)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (1,1,10),(1,2,20),(2,1,30),(2,2,40)");
                    $rows = $pdo->query("SELECT a, b, SUM(m) FROM `$tn` $clause")->fetchAll(\PDO::FETCH_NUM);
                    Support::assert(count($rows) >= 1, "$nm produced no rows");
                    return ['detail' => 'groups=' . count($rows), 'sql' => "SELECT a,b,SUM(m) $clause"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
    }

    // =========================================================== STRING matrix

    private static function stringMatrix(Runner $r): void
    {
        $cases = [
            ['CONCAT_WS', "CONCAT_WS('-','a','b','c')", 'a-b-c'],
            ['SUBSTRING', "SUBSTRING('hello',2,3)", 'ell'],
            ['SUBSTRING_INDEX', "SUBSTRING_INDEX('a.b.c','.',2)", 'a.b'],
            ['LEFT', "LEFT('hello',2)", 'he'],
            ['RIGHT', "RIGHT('hello',2)", 'lo'],
            ['MID', "MID('hello',2,2)", 'el'],
            ['UPPER', "UPPER('abc')", 'ABC'],
            ['LOWER', "LOWER('ABC')", 'abc'],
            ['LENGTH', "LENGTH('abc')", '3'],
            ['CHAR_LENGTH', "CHAR_LENGTH('abc')", '3'],
            ['REVERSE', "REVERSE('abc')", 'cba'],
            ['REPEAT', "REPEAT('ab',3)", 'ababab'],
            ['REPLACE', "REPLACE('aaa','a','b')", 'bbb'],
            ['TRIM', "TRIM('  x  ')", 'x'],
            ['LTRIM', "LTRIM('  x')", 'x'],
            ['RTRIM', "RTRIM('x  ')", 'x'],
            ['TRIM leading', "TRIM(LEADING 'x' FROM 'xxabc')", 'abc'],
            ['TRIM trailing', "TRIM(TRAILING 'x' FROM 'abcxx')", 'abc'],
            ['LPAD', "LPAD('7',3,'0')", '007'],
            ['RPAD', "RPAD('7',3,'0')", '700'],
            ['SPACE', "LENGTH(SPACE(5))", '5'],
            ['INSTR', "INSTR('hello','ll')", '3'],
            ['LOCATE', "LOCATE('l','hello')", '3'],
            ['POSITION', "POSITION('l' IN 'hello')", '3'],
            ['INSERT fn', "INSERT('hello',2,2,'XY')", 'hXYlo'],
            ['SUBSTR neg', "SUBSTR('hello',-2)", 'lo'],
            ['ASCII', "ASCII('A')", '65'],
            ['CHAR fn', "CHAR(65)", 'A'],
            ['HEX', "HEX('AB')", '4142'],
            ['UNHEX', "UNHEX('4142')", 'AB'],
            ['BIN', "BIN(5)", '101'],
            ['OCT', "OCT(8)", '10'],
            ['QUOTE', "QUOTE('a')", "'a'"],
            ['FORMAT', "FORMAT(1234.5678,2)", '1,234.57'],
            ['STRCMP eq', "STRCMP('a','a')", '0'],
            ['STRCMP lt', "STRCMP('a','b')", '-1'],
            ['SOUNDEX', "SOUNDEX('Smith')", null],
            ['MAKE_SET', "MAKE_SET(5,'a','b','c')", 'a,c'],
            ['EXPORT_SET', "SUBSTRING_INDEX(EXPORT_SET(5,'1','0'),',',4)", '1,0,1,0'],
            ['TO_BASE64', "TO_BASE64('abc')", null],
            ['FROM_BASE64', "FROM_BASE64(TO_BASE64('abc'))", 'abc'],
        ];
        foreach ($cases as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'sem:string', $nm, $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'sem:string', $nm, $expr, $exp, 'value');
            }
        }
    }

    // ========================================================== NUMERIC matrix

    private static function numericFnMatrix(Runner $r): void
    {
        $cases = [
            ['ABS', 'ABS(-7)', '7'],
            ['CEIL', 'CEIL(4.2)', '5'],
            ['CEILING', 'CEILING(4.2)', '5'],
            ['FLOOR', 'FLOOR(4.8)', '4'],
            ['ROUND', 'ROUND(4.567,2)', '4.57'],
            ['ROUND neg', 'ROUND(1234,-2)', '1200'],
            ['TRUNCATE', 'TRUNCATE(4.567,1)', '4.5'],
            ['MOD fn', 'MOD(10,3)', '1'],
            ['POW', 'POW(2,10)', '1024'],
            ['POWER', 'POWER(3,3)', '27'],
            ['SQRT', 'SQRT(144)', '12'],
            ['EXP', 'EXP(0)', '1'],
            ['LN', 'LN(1)', '0'],
            ['LOG', 'LOG(2,8)', '3'],
            ['LOG2', 'LOG2(8)', '3'],
            ['LOG10', 'LOG10(1000)', '3'],
            ['SIGN pos', 'SIGN(5)', '1'],
            ['SIGN neg', 'SIGN(-5)', '-1'],
            ['SIGN zero', 'SIGN(0)', '0'],
            ['PI', 'ROUND(PI(),2)', '3.14'],
            ['SIN', 'ROUND(SIN(0),4)', '0.0000'],
            ['COS', 'ROUND(COS(0),4)', '1.0000'],
            ['TAN', 'ROUND(TAN(0),4)', '0.0000'],
            ['ASIN', 'ROUND(ASIN(1),4)', null],
            ['ACOS', 'ROUND(ACOS(1),4)', '0.0000'],
            ['ATAN', 'ROUND(ATAN(0),4)', '0.0000'],
            ['ATAN2', 'ROUND(ATAN2(1,1),4)', null],
            ['COT', 'ROUND(COT(1),4)', null],
            ['DEGREES', 'ROUND(DEGREES(PI()),0)', '180'],
            ['RADIANS', 'ROUND(RADIANS(180),4)', null],
            ['RAND bounded', 'RAND() >= 0 AND RAND() < 1', null],
            ['CRC32', "CRC32('a')", null],
            ['CONV hex', "CONV('FF',16,10)", '255'],
            ['GREATEST nums', 'GREATEST(1.5,2.5,0.5)', '2.5'],
            ['LEAST nums', 'LEAST(1.5,2.5,0.5)', '0.5'],
        ];
        foreach ($cases as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'sem:numeric', $nm, $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'sem:numeric', $nm, $expr, $exp, 'value');
            }
        }
    }
}
