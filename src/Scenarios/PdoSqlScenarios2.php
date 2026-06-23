<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\BehaviorMismatch;
use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Second wave of raw-PDO compatibility scenarios for MatrixOne.
 *
 * Where PdoSqlScenarios covers the breadth of basic DDL/DML/query/introspection,
 * this provider drills into the *advanced* corners: multi-table joins, deep
 * subqueries, the full set-operation matrix, CTEs (incl. recursive), window
 * frames, grouping extensions (ROLLUP/CUBE/GROUPING SETS), DDL/DML edge syntax,
 * data-type boundaries, charset/collation handling, transaction semantics and
 * the *accuracy* of information_schema metadata.
 *
 * Conventions mirror PdoSqlScenarios:
 *   - one fresh PDO connection per scenario,
 *   - unique table names via Support::name() with {t},{t2},{t3},{t4} placeholders,
 *   - every table is DROPped in a finally block,
 *   - DDL/DML go through exec() (text protocol); reads go through query().
 *
 * A failing scenario is a legitimate compatibility *finding*, not a test bug.
 */
final class PdoSqlScenarios2
{
    public static function register(Runner $r): void
    {
        self::joins($r);
        self::subqueries($r);
        self::setops($r);
        self::ctes($r);
        self::windows($r);
        self::grouping($r);
        self::ddl2($r);
        self::dml2($r);
        self::typeEdges2($r);
        self::charset($r);
        self::collation($r);
        self::transactions2($r);
        self::introspection2($r);
    }

    // ---------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------

    /**
     * Run a DDL/DML/read statement choosing the right protocol, matching the
     * behaviour of PdoSqlScenarios::exec1 (reads via query(), writes via exec()).
     */
    private static function exec1(\PDO $pdo, string $sql): void
    {
        if (preg_match('/^\s*(SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN|VALUES|TABLE)\b/i', $sql)) {
            $st = $pdo->query($sql);
            if ($st instanceof \PDOStatement) {
                $st->closeCursor();
            }
            return;
        }
        $pdo->exec($sql);
    }

    /**
     * Generic data-driven case: optional setup statements, optional run
     * statement(s), optional single-column check. Tables named {t}..{t4}.
     *
     * @param array{setup?:array<string>,run?:string|array<string>,check?:array{0:string,1:mixed},tables?:int} $opts
     */
    private static function addCase(Runner $r, string $cat, string $name, array $opts): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', $cat, $name, function () use ($db, $opts, $name) {
            $pdo = Connections::pdo($db);
            $nTables = $opts['tables'] ?? 1;
            $names = [];
            for ($i = 1; $i <= max($nTables, 4); $i++) {
                $names['{t' . ($i === 1 ? '' : $i) . '}'] = Support::name('q');
            }
            $sub = fn (string $s) => strtr($s, $names);
            $lastSql = null;
            try {
                foreach ($opts['setup'] ?? [] as $s) {
                    self::exec1($pdo, $sub($s));
                }
                foreach ((array) ($opts['run'] ?? []) as $s) {
                    $lastSql = $sub($s);
                    self::exec1($pdo, $lastSql);
                }
                if (isset($opts['check'])) {
                    [$q, $expected] = $opts['check'];
                    $st = $pdo->query($sub($q));
                    $got = $st->fetchColumn();
                    $st->closeCursor();
                    Support::assertEquals($expected, $got, $name);
                    return ['detail' => 'check=' . var_export($got, true), 'sql' => $lastSql ?? $sub($q)];
                }
                return ['detail' => 'ok', 'sql' => $lastSql];
            } finally {
                foreach (array_reverse(array_values($names)) as $tn) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            }
        });
    }

    /**
     * A statement that *should* be rejected by the engine. Passes when the bad
     * statement throws; fails (BehaviorMismatch) when it is wrongly accepted.
     *
     * @param array<string> $setup
     */
    private static function addReject(Runner $r, string $cat, string $name, array $setup, string $bad, int $tables = 1): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', $cat, $name, function () use ($db, $setup, $bad, $tables) {
            $pdo = Connections::pdo($db);
            $names = [];
            for ($i = 1; $i <= max($tables, 2); $i++) {
                $names['{t' . ($i === 1 ? '' : $i) . '}'] = Support::name('rj');
            }
            $sub = fn (string $s) => strtr($s, $names);
            try {
                foreach ($setup as $s) {
                    self::exec1($pdo, $sub($s));
                }
                $rejected = false;
                try {
                    self::exec1($pdo, $sub($bad));
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, 'statement was NOT rejected (accepted unexpectedly)');
                return ['detail' => 'rejected as expected', 'sql' => $sub($bad)];
            } finally {
                foreach (array_reverse(array_values($names)) as $tn) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            }
        });
    }

    /**
     * Seed one table {t}, then evaluate a scalar expression (typically a wrapped
     * subquery) and compare against an expected value. Mirrors ExtraScenarios's
     * advancedSql helper but allows arbitrary seed/expr per call.
     *
     * @param array<int,array{0:string,1:string,2:mixed}> $cases [name, scalarExpr, expected]
     * @param array<string> $seed
     */
    private static function scalarBatch(Runner $r, string $cat, array $seed, array $cases): void
    {
        $db = Config::database('pdo');
        foreach ($cases as [$name, $expr, $expect]) {
            $r->add('PDO', $cat, $name, function () use ($db, $seed, $expr, $expect, $name) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('sc');
                $sub = fn (string $s) => strtr($s, ['{t}' => $tn]);
                try {
                    foreach ($seed as $s) {
                        self::exec1($pdo, $sub($s));
                    }
                    $sql = 'SELECT ' . $sub($expr);
                    $got = $pdo->query($sql)->fetchColumn();
                    Support::assertValueEquals($expect, $got, $name);
                    return ['detail' => '= ' . var_export($got, true), 'sql' => $sub($expr)];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }

    // ---------------------------------------------------------------------
    // 1. Joins
    // ---------------------------------------------------------------------

    private static function joins(Runner $r): void
    {
        // Three related tables: emp(e) -> dept(d) -> loc(l), plus a bonus table.
        $seed = [
            'CREATE TABLE {t} (eid INT PRIMARY KEY, did INT, name VARCHAR(10), sal INT)',
            'CREATE TABLE {t2} (did INT PRIMARY KEY, dname VARCHAR(10), lid INT)',
            'CREATE TABLE {t3} (lid INT PRIMARY KEY, city VARCHAR(10))',
            'CREATE TABLE {t4} (eid INT, bonus INT)',
            "INSERT INTO {t} VALUES (1,10,'a',100),(2,10,'b',200),(3,20,'c',300),(4,NULL,'d',400)",
            "INSERT INTO {t2} VALUES (10,'eng',1),(20,'sales',2),(30,'hr',3)",
            "INSERT INTO {t3} VALUES (1,'nyc'),(2,'sf'),(3,'la')",
            'INSERT INTO {t4} VALUES (1,5),(1,7),(3,9)',
        ];

        $cases = [
            ['join:multi', '3-table inner join', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON e.did=d.did JOIN {t3} l ON d.lid=l.lid', '3']],
            ['join:multi', '3-table join select city', ["SELECT l.city FROM {t} e JOIN {t2} d ON e.did=d.did JOIN {t3} l ON d.lid=l.lid WHERE e.eid=1", 'nyc']],
            ['join:multi', '4-table join with agg', ['SELECT SUM(b.bonus) FROM {t} e JOIN {t2} d ON e.did=d.did JOIN {t3} l ON d.lid=l.lid JOIN {t4} b ON e.eid=b.eid', '21']],
            ['join:chain', 'chained LEFT JOINs', ['SELECT COUNT(*) FROM {t} e LEFT JOIN {t2} d ON e.did=d.did LEFT JOIN {t3} l ON d.lid=l.lid', '4']],
            ['join:chain', 'LEFT then INNER', ['SELECT COUNT(*) FROM {t} e LEFT JOIN {t2} d ON e.did=d.did INNER JOIN {t3} l ON d.lid=l.lid', '3']],
            ['join:chain', 'RIGHT JOIN chain', ['SELECT COUNT(*) FROM {t4} b RIGHT JOIN {t} e ON b.eid=e.eid', '5']],
            ['join:using', 'JOIN USING(did)', ['SELECT COUNT(*) FROM {t} JOIN {t2} USING(did)', '3']],
            ['join:using', 'LEFT JOIN USING(did)', ['SELECT COUNT(*) FROM {t} LEFT JOIN {t2} USING(did)', '4']],
            ['join:expr', 'join on expression', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON e.did=d.did+0', '3']],
            ['join:expr', 'join on function', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON ABS(e.did)=ABS(d.did)', '3']],
            ['join:expr', 'join with OR condition', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON e.did=d.did OR e.did=d.lid', '3']],
            ['join:expr', 'join with range condition', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON e.sal BETWEEN 0 AND 1000 AND e.did=d.did', '3']],
            ['join:anti', 'anti-join via IS NULL', ['SELECT COUNT(*) FROM {t} e LEFT JOIN {t2} d ON e.did=d.did WHERE d.did IS NULL', '1']],
            ['join:anti', 'anti-join names', ["SELECT e.name FROM {t} e LEFT JOIN {t2} d ON e.did=d.did WHERE d.did IS NULL", 'd']],
            ['join:semi', 'semi-join via EXISTS', ['SELECT COUNT(*) FROM {t} e WHERE EXISTS (SELECT 1 FROM {t2} d WHERE d.did=e.did)', '3']],
            ['join:semi', 'semi-join via IN', ['SELECT COUNT(*) FROM {t} e WHERE e.did IN (SELECT did FROM {t2})', '3']],
            ['join:cross', 'CROSS JOIN cardinality', ['SELECT COUNT(*) FROM {t} CROSS JOIN {t3}', '12']],
            ['join:cross', 'implicit cross (comma)', ['SELECT COUNT(*) FROM {t} e, {t3} l', '12']],
            ['join:self', 'self join higher salary', ['SELECT COUNT(*) FROM {t} a JOIN {t} b ON a.did=b.did AND a.sal<b.sal', '1']],
            ['join:self', 'self join same dept pairs', ['SELECT COUNT(*) FROM {t} a JOIN {t} b ON a.did=b.did AND a.eid<>b.eid', '2']],
            ['join:agg', 'join + GROUP BY + SUM', ['SELECT SUM(e.sal) FROM {t} e JOIN {t2} d ON e.did=d.did GROUP BY d.did ORDER BY d.did LIMIT 1', '300']],
            ['join:agg', 'join + COUNT per dept', ['SELECT COUNT(*) FROM {t} e JOIN {t2} d ON e.did=d.did GROUP BY d.dname HAVING COUNT(*)=2', '2']],
            ['join:subq', 'join with derived table', ['SELECT COUNT(*) FROM {t} e JOIN (SELECT did FROM {t2} WHERE lid<3) d ON e.did=d.did', '3']],
            ['join:subq', 'join to aggregated subquery', ['SELECT t.tot FROM {t2} d JOIN (SELECT did, SUM(sal) tot FROM {t} GROUP BY did) t ON d.did=t.did WHERE d.did=10', '300']],
            ['join:full', 'FULL OUTER JOIN (likely unsupported)', ['SELECT COUNT(*) FROM {t} e FULL OUTER JOIN {t2} d ON e.did=d.did', '5']],
            ['join:straight', 'STRAIGHT_JOIN', ['SELECT COUNT(*) FROM {t} e STRAIGHT_JOIN {t2} d ON e.did=d.did', '3']],
            ['join:natural', 'NATURAL LEFT JOIN', ['SELECT COUNT(*) FROM {t} NATURAL LEFT JOIN {t2}', '4']],
        ];

        $db = Config::database('pdo');
        foreach ($cases as [$cat, $name, $chk]) {
            self::addCase($r, $cat, $name, ['setup' => $seed, 'check' => $chk, 'tables' => 4]);
        }
    }

    // ---------------------------------------------------------------------
    // 2. Subqueries
    // ---------------------------------------------------------------------

    private static function subqueries(Runner $r): void
    {
        $seed = [
            'CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, grp VARCHAR(10), val INT)',
            "INSERT INTO {t}(grp,val) VALUES ('a',10),('a',20),('b',30),('b',40),('b',50)",
        ];
        $cases = [
            ['scalar in SELECT', "(SELECT (SELECT MAX(val) FROM {t}))", '50'],
            ['scalar arithmetic in SELECT', "(SELECT (SELECT SUM(val) FROM {t}) - (SELECT MIN(val) FROM {t}))", '140'],
            ['scalar in WHERE', "(SELECT COUNT(*) FROM {t} WHERE val > (SELECT AVG(val) FROM {t}))", '2'],
            ['scalar in HAVING', "(SELECT grp FROM {t} GROUP BY grp HAVING SUM(val) > (SELECT AVG(val) FROM {t}) ORDER BY grp LIMIT 1)", 'a'],
            ['correlated scalar', "(SELECT COUNT(*) FROM {t} a WHERE val = (SELECT MAX(val) FROM {t} b WHERE b.grp=a.grp))", '2'],
            ['correlated in SELECT list', "(SELECT SUM(x) FROM (SELECT (SELECT COUNT(*) FROM {t} b WHERE b.grp=a.grp) x FROM {t} a) z)", '13'],
            ['EXISTS', "(SELECT COUNT(*) FROM {t} a WHERE EXISTS (SELECT 1 FROM {t} b WHERE b.val>a.val))", '4'],
            ['NOT EXISTS', "(SELECT COUNT(*) FROM {t} a WHERE NOT EXISTS (SELECT 1 FROM {t} b WHERE b.val>a.val))", '1'],
            ['IN subquery', "(SELECT COUNT(*) FROM {t} WHERE grp IN (SELECT grp FROM {t} WHERE val>=40))", '3'],
            ['NOT IN subquery', "(SELECT COUNT(*) FROM {t} WHERE grp NOT IN (SELECT grp FROM {t} WHERE val<=10))", '3'],
            ['= ANY subquery', "(SELECT COUNT(*) FROM {t} WHERE val = ANY (SELECT val FROM {t} WHERE grp='b'))", '3'],
            ['> ALL subquery', "(SELECT COUNT(*) FROM {t} WHERE val > ALL (SELECT val FROM {t} WHERE grp='a'))", '3'],
            ['= SOME subquery', "(SELECT COUNT(*) FROM {t} WHERE val = SOME (SELECT val FROM {t} WHERE val=30))", '1'],
            ['< ANY subquery', "(SELECT COUNT(*) FROM {t} WHERE val < ANY (SELECT val FROM {t}))", '4'],
            ['NOT IN with NULL trap', "(SELECT COUNT(*) FROM {t} WHERE val NOT IN (SELECT val FROM {t} WHERE val>40))", '4'],
            ['derived table aliased', "(SELECT SUM(s) FROM (SELECT grp, SUM(val) s FROM {t} GROUP BY grp) d)", '150'],
            ['derived with WHERE on alias', "(SELECT COUNT(*) FROM (SELECT grp, SUM(val) s FROM {t} GROUP BY grp) d WHERE d.s>100)", '1'],
            ['derived join derived', "(SELECT COUNT(*) FROM (SELECT grp FROM {t}) a JOIN (SELECT grp FROM {t}) b ON a.grp=b.grp)", '13'],
            ['nested 2-deep subquery', "(SELECT COUNT(*) FROM {t} WHERE val IN (SELECT val FROM {t} WHERE grp IN (SELECT grp FROM {t} WHERE val=50)))", '3'],
            ['nested 3-deep subquery', "(SELECT (SELECT (SELECT (SELECT COUNT(*) FROM {t}))))", '5'],
            ['subquery in FROM with GROUP BY', "(SELECT MAX(s) FROM (SELECT grp, SUM(val) s FROM {t} GROUP BY grp) d)", '120'],
            ['row subquery comparison', "(SELECT COUNT(*) FROM {t} WHERE (grp,val)=('b',30))", '1'],
            ['scalar subquery in arithmetic', "(SELECT val * (SELECT COUNT(*) FROM {t}) FROM {t} ORDER BY val LIMIT 1)", '50'],
            ['correlated EXISTS count', "(SELECT COUNT(*) FROM {t} a WHERE EXISTS (SELECT 1 FROM {t} b WHERE b.grp=a.grp AND b.val>a.val))", '3'],
            ['IN with constant + subquery', "(SELECT COUNT(*) FROM {t} WHERE val IN (10, (SELECT MAX(val) FROM {t})))", '2'],
        ];
        self::scalarBatch($r, 'sql:subquery', $seed, $cases);
    }

    // ---------------------------------------------------------------------
    // 3. Set operations
    // ---------------------------------------------------------------------

    private static function setops(Runner $r): void
    {
        $seed = [
            'CREATE TABLE {t} (id INT, val INT)',
            'INSERT INTO {t} VALUES (1,10),(2,20),(3,30),(4,30)',
        ];
        $cases = [
            ['UNION distinct', "(SELECT COUNT(*) FROM (SELECT val FROM {t} UNION SELECT val FROM {t}) u)", '3'],
            ['UNION ALL', "(SELECT COUNT(*) FROM (SELECT val FROM {t} UNION ALL SELECT val FROM {t}) u)", '8'],
            ['UNION two literals', "(SELECT COUNT(*) FROM (SELECT 1 UNION SELECT 2 UNION SELECT 1) u)", '2'],
            ['INTERSECT', "(SELECT COUNT(*) FROM (SELECT val FROM {t} INTERSECT SELECT val FROM {t} WHERE val>=20) u)", '2'],
            ['INTERSECT ALL', "(SELECT COUNT(*) FROM (SELECT val FROM {t} INTERSECT ALL SELECT val FROM {t}) u)", '4'],
            ['EXCEPT', "(SELECT COUNT(*) FROM (SELECT val FROM {t} EXCEPT SELECT val FROM {t} WHERE val=30) u)", '2'],
            ['EXCEPT ALL', "(SELECT COUNT(*) FROM (SELECT val FROM {t} EXCEPT ALL SELECT val FROM {t} WHERE val=10) u)", '3'],
            ['MINUS keyword', "(SELECT COUNT(*) FROM (SELECT val FROM {t} MINUS SELECT val FROM {t} WHERE val=30) u)", '2'],
            ['UNION ORDER BY LIMIT', "(SELECT val FROM (SELECT val FROM {t} UNION SELECT val FROM {t} ORDER BY val DESC LIMIT 1) u)", '30'],
            ['UNION ALL ORDER BY', "(SELECT COUNT(*) FROM (SELECT val FROM {t} UNION ALL SELECT val FROM {t} ORDER BY val) u)", '8'],
            ['chained UNION 3-way', "(SELECT COUNT(*) FROM (SELECT 1 v UNION SELECT 2 UNION SELECT 3) u)", '3'],
            ['chained mixed UNION/INTERSECT', "(SELECT COUNT(*) FROM (SELECT val FROM {t} INTERSECT SELECT val FROM {t} UNION SELECT 99) u)", '4'],
            ['set op in subquery IN', "(SELECT COUNT(*) FROM {t} WHERE val IN (SELECT 10 UNION SELECT 20))", '2'],
            ['UNION of aggregates', "(SELECT COUNT(*) FROM (SELECT SUM(val) FROM {t} UNION SELECT MAX(val) FROM {t}) u)", '2'],
            ['UNION with NULL', "(SELECT COUNT(*) FROM (SELECT NULL UNION SELECT NULL) u)", '1'],
            ['parenthesized UNION branches', "(SELECT COUNT(*) FROM ((SELECT val FROM {t}) UNION (SELECT val FROM {t})) u)", '3'],
        ];
        self::scalarBatch($r, 'sql:setop', $seed, $cases);

        // Column-count mismatch must be rejected.
        self::addReject($r, 'sql:setop', 'UNION different column counts rejected',
            ['CREATE TABLE {t} (a INT, b INT)', 'INSERT INTO {t} VALUES (1,2)'],
            'SELECT a, b FROM {t} UNION SELECT a FROM {t}');
        self::addReject($r, 'sql:setop', 'INTERSECT different column counts rejected',
            ['CREATE TABLE {t} (a INT, b INT)', 'INSERT INTO {t} VALUES (1,2)'],
            'SELECT a FROM {t} INTERSECT SELECT a, b FROM {t}');
    }

    // ---------------------------------------------------------------------
    // 4. CTEs
    // ---------------------------------------------------------------------

    private static function ctes(Runner $r): void
    {
        $seed = [
            'CREATE TABLE {t} (id INT PRIMARY KEY, parent INT, val INT)',
            'INSERT INTO {t} VALUES (1,NULL,10),(2,1,20),(3,1,30),(4,2,40),(5,4,50)',
        ];
        $cases = [
            ['single CTE', "(WITH c AS (SELECT SUM(val) s FROM {t}) SELECT s FROM c)", '150'],
            ['CTE with column list', "(WITH c(total) AS (SELECT SUM(val) FROM {t}) SELECT total FROM c)", '150'],
            ['two chained CTEs', "(WITH a AS (SELECT val FROM {t}), b AS (SELECT MAX(val) m FROM a) SELECT m FROM b)", '50'],
            ['three chained CTEs', "(WITH a AS (SELECT val FROM {t}), b AS (SELECT val FROM a WHERE val>20), c AS (SELECT COUNT(*) n FROM b) SELECT n FROM c)", '3'],
            ['CTE referenced twice', "(WITH c AS (SELECT val FROM {t}) SELECT (SELECT COUNT(*) FROM c) + (SELECT COUNT(*) FROM c))", '10'],
            ['CTE joined to itself', "(WITH c AS (SELECT id, val FROM {t}) SELECT COUNT(*) FROM c a JOIN c b ON a.id=b.id)", '5'],
            ['CTE in subquery', "(SELECT COUNT(*) FROM (WITH c AS (SELECT val FROM {t} WHERE val>=30) SELECT * FROM c) z)", '3'],
            ['CTE feeding aggregate', "(WITH c AS (SELECT val FROM {t}) SELECT AVG(val) FROM c)", '30'],
            ['recursive numbers 1..5', "(WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<5) SELECT SUM(x) FROM n)", '15'],
            ['recursive count rows', "(WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<10) SELECT COUNT(*) FROM n)", '10'],
            ['recursive factorial-ish', "(WITH RECURSIVE f(n,acc) AS (SELECT 1,1 UNION ALL SELECT n+1, acc*(n+1) FROM f WHERE n<5) SELECT MAX(acc) FROM f)", '120'],
            ['recursive even numbers', "(WITH RECURSIVE e(x) AS (SELECT 0 UNION ALL SELECT x+2 FROM e WHERE x<8) SELECT COUNT(*) FROM e)", '5'],
            ['recursive hierarchy depth', "(WITH RECURSIVE h(id,depth) AS (SELECT id,0 FROM {t} WHERE parent IS NULL UNION ALL SELECT t.id, h.depth+1 FROM {t} t JOIN h ON t.parent=h.id) SELECT MAX(depth) FROM h)", '3'],
            ['recursive hierarchy count', "(WITH RECURSIVE h(id) AS (SELECT id FROM {t} WHERE parent IS NULL UNION ALL SELECT t.id FROM {t} t JOIN h ON t.parent=h.id) SELECT COUNT(*) FROM h)", '5'],
            ['recursive sum of branch', "(WITH RECURSIVE h(id,val) AS (SELECT id,val FROM {t} WHERE id=1 UNION ALL SELECT t.id,t.val FROM {t} t JOIN h ON t.parent=h.id) SELECT SUM(val) FROM h)", '150'],
            ['recursive with limit guard', "(WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<3) SELECT GROUP_CONCAT(x ORDER BY x) FROM n)", '1,2,3'],
            ['nested CTE in CTE body', "(WITH outer_c AS (SELECT (SELECT COUNT(*) FROM {t}) c) SELECT c FROM outer_c)", '5'],
        ];
        self::scalarBatch($r, 'sql:cte', $seed, $cases);

        // CTE driving a CREATE TABLE AS (statement form rather than scalar).
        self::addCase($r, 'sql:cte', 'CTE in CREATE TABLE AS SELECT', [
            'setup' => ['CREATE TABLE {t} (id INT, val INT)', 'INSERT INTO {t} VALUES (1,10),(2,20)'],
            'run' => 'CREATE TABLE {t2} AS WITH c AS (SELECT SUM(val) s FROM {t}) SELECT s FROM c',
            'check' => ['SELECT s FROM {t2}', '30'],
            'tables' => 2,
        ]);
    }

    // ---------------------------------------------------------------------
    // 5. Window functions
    // ---------------------------------------------------------------------

    private static function windows(Runner $r): void
    {
        $seed = [
            'CREATE TABLE {t} (id INT PRIMARY KEY, grp VARCHAR(10), sub VARCHAR(10), val INT)',
            "INSERT INTO {t} VALUES (1,'a','x',10),(2,'a','x',20),(3,'a','y',20),(4,'b','x',30),(5,'b','y',40)",
        ];
        $cases = [
            ['ROW_NUMBER', "(SELECT MAX(rn) FROM (SELECT ROW_NUMBER() OVER (ORDER BY id) rn FROM {t}) z)", '5'],
            ['RANK with ties', "(SELECT MAX(r) FROM (SELECT RANK() OVER (ORDER BY val) r FROM {t}) z)", '4'],
            ['DENSE_RANK with ties', "(SELECT MAX(r) FROM (SELECT DENSE_RANK() OVER (ORDER BY val) r FROM {t}) z)", '4'],
            ['PERCENT_RANK', "(SELECT ROUND(MAX(p),2) FROM (SELECT PERCENT_RANK() OVER (ORDER BY val) p FROM {t}) z)", '1'],
            ['CUME_DIST', "(SELECT ROUND(MAX(c),2) FROM (SELECT CUME_DIST() OVER (ORDER BY val) c FROM {t}) z)", '1'],
            ['NTILE(2)', "(SELECT MAX(n) FROM (SELECT NTILE(2) OVER (ORDER BY id) n FROM {t}) z)", '2'],
            ['LAG default', "(SELECT SUM(IFNULL(l,0)) FROM (SELECT LAG(val) OVER (ORDER BY id) l FROM {t}) z)", '80'],
            ['LEAD default', "(SELECT SUM(IFNULL(l,0)) FROM (SELECT LEAD(val) OVER (ORDER BY id) l FROM {t}) z)", '110'],
            ['LAG with offset/default', "(SELECT MIN(l) FROM (SELECT LAG(val,1,-1) OVER (ORDER BY id) l FROM {t}) z)", '-1'],
            ['FIRST_VALUE', "(SELECT DISTINCT fv FROM (SELECT FIRST_VALUE(val) OVER (ORDER BY id) fv FROM {t}) z)", '10'],
            ['LAST_VALUE full frame', "(SELECT DISTINCT lv FROM (SELECT LAST_VALUE(val) OVER (ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) lv FROM {t}) z)", '40'],
            ['NTH_VALUE', "(SELECT DISTINCT nv FROM (SELECT NTH_VALUE(val,2) OVER (ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) nv FROM {t}) z)", '20'],
            ['SUM running', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY id) s FROM {t}) z)", '120'],
            ['AVG over partition', "(SELECT MAX(a) FROM (SELECT AVG(val) OVER (PARTITION BY grp) a FROM {t}) z)", '35'],
            ['COUNT over partition', "(SELECT MAX(c) FROM (SELECT COUNT(*) OVER (PARTITION BY grp) c FROM {t}) z)", '3'],
            ['MIN over partition', "(SELECT MAX(m) FROM (SELECT MIN(val) OVER (PARTITION BY grp) m FROM {t}) z)", '30'],
            ['MAX over whole', "(SELECT DISTINCT m FROM (SELECT MAX(val) OVER () m FROM {t}) z)", '40'],
            ['PARTITION BY two cols', "(SELECT MAX(c) FROM (SELECT COUNT(*) OVER (PARTITION BY grp,sub) c FROM {t}) z)", '2'],
            ['ROWS 1 PRECEDING CURRENT', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) s FROM {t}) z)", '70'],
            ['ROWS CURRENT to 1 FOLLOWING', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) s FROM {t}) z)", '70'],
            ['ROWS UNBOUNDED PRECEDING', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY id ROWS UNBOUNDED PRECEDING) s FROM {t}) z)", '120'],
            ['ROWS 2 PRECEDING 2 FOLLOWING', "(SELECT MAX(c) FROM (SELECT COUNT(*) OVER (ORDER BY id ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING) c FROM {t}) z)", '5'],
            ['RANGE UNBOUNDED to CURRENT', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY val RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) s FROM {t}) z)", '120'],
            ['RANGE CURRENT to UNBOUNDED', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY val RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) s FROM {t}) z)", '120'],
            ['ranking partitioned with ties', "(SELECT MAX(r) FROM (SELECT RANK() OVER (PARTITION BY grp ORDER BY val) r FROM {t}) z)", '2'],
            ['window + plain aggregate mix', "(SELECT MAX(d) FROM (SELECT val - AVG(val) OVER () d FROM {t}) z)", '16'],
            ['named WINDOW clause', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER w s FROM {t} WINDOW w AS (ORDER BY id)) z)", '120'],
            ['two windows same query', "(SELECT MAX(a+b) FROM (SELECT ROW_NUMBER() OVER (ORDER BY id) a, ROW_NUMBER() OVER (ORDER BY id DESC) b FROM {t}) z)", '6'],
        ];
        self::scalarBatch($r, 'sql:window', $seed, $cases);
    }

    // ---------------------------------------------------------------------
    // 6. Grouping
    // ---------------------------------------------------------------------

    private static function grouping(Runner $r): void
    {
        $seed = [
            'CREATE TABLE {t} (id INT, region VARCHAR(10), product VARCHAR(10), qty INT)',
            "INSERT INTO {t} VALUES (1,'east','a',10),(2,'east','b',20),(3,'west','a',30),(4,'west','b',40),(5,'west','a',5)",
        ];
        $cases = [
            ['GROUP BY two cols', "(SELECT MAX(s) FROM (SELECT region,product,SUM(qty) s FROM {t} GROUP BY region,product) z)", '40'],
            ['GROUP BY expression', "(SELECT COUNT(*) FROM (SELECT LENGTH(region) l, SUM(qty) s FROM {t} GROUP BY LENGTH(region)) z)", '1'],
            ['GROUP BY position', "(SELECT MAX(s) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY 1) z)", '75'],
            ['GROUP BY with COUNT DISTINCT', "(SELECT COUNT(DISTINCT product) FROM {t})", '2'],
            ['HAVING aggregate', "(SELECT region FROM {t} GROUP BY region HAVING SUM(qty)>50 ORDER BY region LIMIT 1)", 'west'],
            ['HAVING on alias', "(SELECT COUNT(*) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY region HAVING s>50) z)", '1'],
            ['HAVING two conditions', "(SELECT COUNT(*) FROM (SELECT region, SUM(qty) s, COUNT(*) c FROM {t} GROUP BY region HAVING SUM(qty)>30 AND COUNT(*)>=2) z)", '2'],
            ['ORDER BY aggregate', "(SELECT region FROM {t} GROUP BY region ORDER BY SUM(qty) DESC LIMIT 1)", 'west'],
            ['WITH ROLLUP row count', "(SELECT COUNT(*) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY region WITH ROLLUP) z)", '3'],
            ['WITH ROLLUP grand total', "(SELECT MAX(s) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY region WITH ROLLUP) z)", '105'],
            ['ROLLUP two cols count', "(SELECT COUNT(*) FROM (SELECT region, product, SUM(qty) s FROM {t} GROUP BY region, product WITH ROLLUP) z)", '7'],
            ['GROUPING SETS basic', "(SELECT COUNT(*) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY GROUPING SETS((region),())) z)", '3'],
            ['GROUPING SETS two cols', "(SELECT COUNT(*) FROM (SELECT region, product, SUM(qty) s FROM {t} GROUP BY GROUPING SETS((region),(product))) z)", '4'],
            ['CUBE expansion', "(SELECT COUNT(*) FROM (SELECT region, product, SUM(qty) s FROM {t} GROUP BY CUBE(region,product)) z)", '9'],
            ['GROUPING() function', "(SELECT MAX(g) FROM (SELECT GROUPING(region) g FROM {t} GROUP BY region WITH ROLLUP) z)", '1'],
            ['GROUP_CONCAT basic', "(SELECT GROUP_CONCAT(qty ORDER BY qty) FROM {t} WHERE region='east')", '10,20'],
            ['GROUP_CONCAT SEPARATOR', "(SELECT GROUP_CONCAT(qty ORDER BY qty SEPARATOR '|') FROM {t} WHERE region='east')", '10|20'],
            ['GROUP_CONCAT DISTINCT', "(SELECT GROUP_CONCAT(DISTINCT product ORDER BY product) FROM {t} WHERE region='west')", 'a,b'],
            ['GROUP_CONCAT DESC', "(SELECT GROUP_CONCAT(qty ORDER BY qty DESC) FROM {t} WHERE region='east')", '20,10'],
            ['COUNT with FILTER-like CASE', "(SELECT SUM(CASE WHEN qty>20 THEN 1 ELSE 0 END) FROM {t})", '2'],
            ['AVG grouped', "(SELECT ROUND(AVG(s),2) FROM (SELECT region, SUM(qty) s FROM {t} GROUP BY region) z)", '52.5'],
            ['STD aggregate', "(SELECT ROUND(STD(qty),0) FROM {t})", '13'],
            ['VARIANCE aggregate', "(SELECT ROUND(VARIANCE(qty),0) FROM {t})", '171'],
            ['BIT_OR aggregate', "(SELECT BIT_OR(qty) FROM {t} WHERE region='east')", '30'],
            ['BIT_AND aggregate', "(SELECT BIT_AND(qty) FROM {t} WHERE region='east')", '0'],
        ];
        self::scalarBatch($r, 'sql:grouping', $seed, $cases);
    }

    // ---------------------------------------------------------------------
    // 7. DDL edge syntax
    // ---------------------------------------------------------------------

    private static function ddl2(Runner $r): void
    {
        $base = 'CREATE TABLE {t} (id INT PRIMARY KEY)';
        $cases = [
            // CREATE option combos
            ['ddl2:create', 'many options combined', ['run' => "CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, c VARCHAR(20)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=50 COMMENT='combo'"]],
            ['ddl2:create', 'multiple indexes inline', ['run' => 'CREATE TABLE {t} (a INT, b INT, c INT, INDEX i1(a), INDEX i2(b,c), UNIQUE u1(c))']],
            ['ddl2:create', 'composite + prefix index inline', ['run' => 'CREATE TABLE {t} (a VARCHAR(50), b INT, INDEX i(a(10), b))']],
            ['ddl2:create', 'functional index', ['run' => 'CREATE TABLE {t} (a INT, b INT, INDEX i((a+b)))']],
            ['ddl2:create', 'AUTO_INCREMENT seeding + read', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, n INT) AUTO_INCREMENT=1000', "INSERT INTO {t}(n) VALUES (1)"], 'check' => ['SELECT id FROM {t}', '1000']]],
            ['ddl2:create', 'generated STORED with expression', ['run' => 'CREATE TABLE {t} (a INT, b INT, c INT GENERATED ALWAYS AS (a*b+1) STORED)']],
            ['ddl2:create', 'generated VIRTUAL with function', ['run' => 'CREATE TABLE {t} (s VARCHAR(20), n INT GENERATED ALWAYS AS (CHAR_LENGTH(s)) VIRTUAL)']],
            ['ddl2:create', 'generated column read back', ['setup' => ['CREATE TABLE {t} (a INT, b INT GENERATED ALWAYS AS (a+10) STORED)', 'INSERT INTO {t}(a) VALUES (5)'], 'check' => ['SELECT b FROM {t}', '15']]],
            ['ddl2:create', 'TEMPORARY TABLE with data', ['setup' => ['CREATE TEMPORARY TABLE {t} (id INT)', 'INSERT INTO {t} VALUES (1),(2)'], 'check' => ['SELECT COUNT(*) FROM {t}', '2']]],
            ['ddl2:create', 'CREATE TABLE IF NOT EXISTS twice', ['setup' => [$base], 'run' => 'CREATE TABLE IF NOT EXISTS {t} (id INT PRIMARY KEY)']],
            ['ddl2:create', 'NULL/NOT NULL mix', ['run' => 'CREATE TABLE {t} (a INT NULL, b INT NOT NULL DEFAULT 0, c VARCHAR(5) NULL DEFAULT NULL)']],
            ['ddl2:create', 'ROW_FORMAT option', ['run' => 'CREATE TABLE {t} (id INT) ROW_FORMAT=DYNAMIC']],
            ['ddl2:create', 'KEY_BLOCK_SIZE option', ['run' => 'CREATE TABLE {t} (id INT) KEY_BLOCK_SIZE=8']],
            ['ddl2:create', 'column CHARACTER SET', ['run' => 'CREATE TABLE {t} (c VARCHAR(20) CHARACTER SET utf8mb4)']],
            ['ddl2:create', 'CHECK constraint inline', ['run' => 'CREATE TABLE {t} (id INT, age INT CHECK (age >= 0))']],
            ['ddl2:create', 'named CHECK constraint', ['run' => 'CREATE TABLE {t} (id INT, age INT, CONSTRAINT chk_age CHECK (age >= 0))']],

            // Partitioning (likely unsupported on persistent table => findings)
            ['ddl2:partition', 'PARTITION BY HASH', ['run' => 'CREATE TABLE {t} (id INT) PARTITION BY HASH(id) PARTITIONS 4']],
            ['ddl2:partition', 'PARTITION BY KEY', ['run' => 'CREATE TABLE {t} (id INT) PARTITION BY KEY(id) PARTITIONS 2']],
            ['ddl2:partition', 'PARTITION BY RANGE', ['run' => 'CREATE TABLE {t} (id INT) PARTITION BY RANGE(id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (100))']],
            ['ddl2:partition', 'PARTITION BY LIST', ['run' => 'CREATE TABLE {t} (id INT) PARTITION BY LIST(id) (PARTITION p0 VALUES IN (1,2,3), PARTITION p1 VALUES IN (4,5,6))']],

            // ALTER combos
            ['ddl2:alter', 'ALTER multiple ADD COLUMN', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN a INT, ADD COLUMN b VARCHAR(5)']],
            ['ddl2:alter', 'ALTER ADD + DROP same statement', ['setup' => ['CREATE TABLE {t} (id INT, old INT)'], 'run' => 'ALTER TABLE {t} ADD COLUMN newc INT, DROP COLUMN old']],
            ['ddl2:alter', 'ALTER MODIFY widen type', ['setup' => ['CREATE TABLE {t} (id INT, c VARCHAR(5))'], 'run' => 'ALTER TABLE {t} MODIFY c VARCHAR(200)']],
            ['ddl2:alter', 'ALTER CHANGE rename+retype', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} CHANGE c d BIGINT']],
            ['ddl2:alter', 'ALTER MODIFY reorder AFTER', ['setup' => ['CREATE TABLE {t} (id INT, a INT, b INT)'], 'run' => 'ALTER TABLE {t} MODIFY b INT AFTER id']],
            ['ddl2:alter', 'ALTER ALGORITHM=INPLACE', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c INT, ALGORITHM=INPLACE']],
            ['ddl2:alter', 'ALTER ALGORITHM=COPY', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c INT, ALGORITHM=COPY']],
            ['ddl2:alter', 'ALTER LOCK=NONE', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c INT, LOCK=NONE']],
            ['ddl2:alter', 'ALTER multiple ADD INDEX', ['setup' => ['CREATE TABLE {t} (a INT, b INT, c INT)'], 'run' => 'ALTER TABLE {t} ADD INDEX i1(a), ADD INDEX i2(b)']],
            ['ddl2:alter', 'ALTER DROP + ADD index', ['setup' => ['CREATE TABLE {t} (a INT, b INT, INDEX i1(a))'], 'run' => 'ALTER TABLE {t} DROP INDEX i1, ADD INDEX i2(b)']],
            ['ddl2:alter', 'ALTER CONVERT TO CHARSET', ['setup' => [$base], 'run' => 'ALTER TABLE {t} CONVERT TO CHARACTER SET utf8mb4']],
            ['ddl2:alter', 'ALTER set AUTO_INCREMENT', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT)'], 'run' => 'ALTER TABLE {t} AUTO_INCREMENT=500']],

            // DROP variants
            ['ddl2:drop', 'DROP TABLE multiple', ['setup' => [$base, 'CREATE TABLE {t2} (id INT)'], 'run' => 'DROP TABLE {t}, {t2}', 'tables' => 2]],
            ['ddl2:drop', 'DROP TABLE RESTRICT', ['setup' => [$base], 'run' => 'DROP TABLE {t} RESTRICT']],
            ['ddl2:drop', 'DROP TABLE CASCADE', ['setup' => [$base], 'run' => 'DROP TABLE {t} CASCADE']],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    // ---------------------------------------------------------------------
    // 8. DML edge syntax
    // ---------------------------------------------------------------------

    private static function dml2(Runner $r): void
    {
        $t = 'CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, n INT, s VARCHAR(20))';
        $cases = [
            ['dml2:insert', 'INSERT ... SELECT from join', ['setup' => [$t, 'CREATE TABLE {t2} (n INT, s VARCHAR(20))', "INSERT INTO {t2} VALUES (1,'a'),(2,'b')"], 'run' => 'INSERT INTO {t}(n,s) SELECT n,s FROM {t2}', 'check' => ['SELECT COUNT(*) FROM {t}', '2'], 'tables' => 2]],
            ['dml2:insert', 'INSERT with subquery value', ['setup' => [$t, 'CREATE TABLE {t2} (m INT)', 'INSERT INTO {t2} VALUES (7)'], 'run' => 'INSERT INTO {t}(n) VALUES ((SELECT MAX(m) FROM {t2}))', 'check' => ['SELECT n FROM {t}', '7'], 'tables' => 2]],
            ['dml2:insert', 'INSERT SET syntax', ['setup' => [$t], 'run' => "INSERT INTO {t} SET n=9, s='z'", 'check' => ['SELECT n FROM {t}', '9']]],
            ['dml2:insert', 'multi-row VALUES large', ['setup' => [$t], 'run' => 'INSERT INTO {t}(n) VALUES (1),(2),(3),(4),(5),(6),(7),(8)', 'check' => ['SELECT COUNT(*) FROM {t}', '8']]],
            ['dml2:insert', 'ODKU with VALUES()', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'INSERT INTO {t}(id,n) VALUES (1,5) ON DUPLICATE KEY UPDATE n=VALUES(n)+100', 'check' => ['SELECT n FROM {t} WHERE id=1', '105']]],
            ['dml2:insert', 'ODKU with expression', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,10)'], 'run' => 'INSERT INTO {t}(id,n) VALUES (1,1) ON DUPLICATE KEY UPDATE n=n+1', 'check' => ['SELECT n FROM {t} WHERE id=1', '11']]],
            ['dml2:insert', 'ODKU inserts when absent', ['setup' => [$t], 'run' => 'INSERT INTO {t}(id,n) VALUES (5,5) ON DUPLICATE KEY UPDATE n=99', 'check' => ['SELECT n FROM {t} WHERE id=5', '5']]],
            ['dml2:insert', 'INSERT IGNORE skips dup', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'INSERT IGNORE INTO {t}(id,n) VALUES (1,2),(3,3)', 'check' => ['SELECT COUNT(*) FROM {t}', '2']]],
            ['dml2:insert', 'REPLACE multi-row', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'REPLACE INTO {t}(id,n) VALUES (1,9),(2,2)', 'check' => ['SELECT SUM(n) FROM {t}', '11']]],
            ['dml2:update', 'UPDATE with JOIN', ['setup' => [$t, 'CREATE TABLE {t2} (id INT, nn INT)', 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2)', 'INSERT INTO {t2} VALUES (1,100),(2,200)'], 'run' => 'UPDATE {t} a JOIN {t2} b ON a.id=b.id SET a.n=b.nn', 'check' => ['SELECT SUM(n) FROM {t}', '300'], 'tables' => 2]],
            ['dml2:update', 'UPDATE with LEFT JOIN', ['setup' => [$t, 'CREATE TABLE {t2} (id INT, nn INT)', 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2)', 'INSERT INTO {t2} VALUES (1,100)'], 'run' => 'UPDATE {t} a LEFT JOIN {t2} b ON a.id=b.id SET a.n=IFNULL(b.nn,0)', 'check' => ['SELECT SUM(n) FROM {t}', '100'], 'tables' => 2]],
            ['dml2:update', 'UPDATE with subquery', ['setup' => [$t, 'CREATE TABLE {t2} (m INT)', 'INSERT INTO {t}(id,n) VALUES (1,1)', 'INSERT INTO {t2} VALUES (42)'], 'run' => 'UPDATE {t} SET n=(SELECT MAX(m) FROM {t2}) WHERE id=1', 'check' => ['SELECT n FROM {t} WHERE id=1', '42'], 'tables' => 2]],
            ['dml2:update', 'UPDATE ORDER BY LIMIT', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2),(3)'], 'run' => 'UPDATE {t} SET n=0 ORDER BY id DESC LIMIT 1', 'check' => ['SELECT SUM(n) FROM {t}', '3']]],
            ['dml2:update', 'UPDATE multiple columns', ['setup' => [$t, "INSERT INTO {t}(id,n,s) VALUES (1,1,'a')"], 'run' => "UPDATE {t} SET n=n+1, s=CONCAT(s,'b') WHERE id=1", 'check' => ["SELECT s FROM {t} WHERE id=1", 'ab']]],
            ['dml2:delete', 'DELETE with subquery', ['setup' => [$t, 'CREATE TABLE {t2} (bad INT)', 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2),(3,3)', 'INSERT INTO {t2} VALUES (2)'], 'run' => 'DELETE FROM {t} WHERE id IN (SELECT bad FROM {t2})', 'check' => ['SELECT COUNT(*) FROM {t}', '2'], 'tables' => 2]],
            ['dml2:delete', 'DELETE ORDER BY LIMIT', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2),(3)'], 'run' => 'DELETE FROM {t} ORDER BY id DESC LIMIT 1', 'check' => ['SELECT MAX(id) FROM {t}', '2']]],
            ['dml2:delete', 'multi-table DELETE join', ['setup' => [$t, 'CREATE TABLE {t2} (id INT)', 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2)', 'INSERT INTO {t2} VALUES (1)'], 'run' => 'DELETE a FROM {t} a JOIN {t2} b ON a.id=b.id', 'check' => ['SELECT COUNT(*) FROM {t}', '1'], 'tables' => 2]],
            ['dml2:delete', 'TRUNCATE resets', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2)'], 'run' => 'TRUNCATE TABLE {t}', 'check' => ['SELECT COUNT(*) FROM {t}', '0']]],
            ['dml2:delete', 'DELETE then AUTO_INCREMENT continues', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, n INT)', 'INSERT INTO {t}(n) VALUES (1),(2)', 'DELETE FROM {t}', 'INSERT INTO {t}(n) VALUES (9)'], 'check' => ['SELECT id FROM {t}', '3']]],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    // ---------------------------------------------------------------------
    // 9. Data-type edges (round-trip via CREATE/INSERT/SELECT)
    // ---------------------------------------------------------------------

    private static function typeEdges2(Runner $r): void
    {
        $db = Config::database('pdo');
        // [name, coltype, value, expectedOrNull]
        $cases = [
            ['TINYINT max', 'TINYINT', 127, '127'],
            ['TINYINT min', 'TINYINT', -128, '-128'],
            ['TINYINT UNSIGNED max', 'TINYINT UNSIGNED', 255, '255'],
            ['SMALLINT max', 'SMALLINT', 32767, '32767'],
            ['SMALLINT min', 'SMALLINT', -32768, '-32768'],
            ['SMALLINT UNSIGNED max', 'SMALLINT UNSIGNED', 65535, '65535'],
            ['MEDIUMINT max', 'MEDIUMINT', 8388607, '8388607'],
            ['MEDIUMINT min', 'MEDIUMINT', -8388608, '-8388608'],
            ['INT UNSIGNED max', 'INT UNSIGNED', '4294967295', '4294967295'],
            ['BIGINT min', 'BIGINT', '-9223372036854775808', '-9223372036854775808'],
            ['BIGINT UNSIGNED max', 'BIGINT UNSIGNED', '18446744073709551615', '18446744073709551615'],
            ['DECIMAL max precision', 'DECIMAL(38,0)', '12345678901234567890123456789012345678', '12345678901234567890123456789012345678'],
            ['DECIMAL high scale', 'DECIMAL(20,10)', '1234567890.1234567890', '1234567890.1234567890'],
            ['DECIMAL 65,30', 'DECIMAL(65,30)', '1.000000000000000000000000000001', null],
            ['DECIMAL rounding on insert', 'DECIMAL(5,2)', '1.005', null],
            ['FLOAT precision', 'FLOAT', '3.14159', null],
            ['DOUBLE large', 'DOUBLE', '1.7976931348623157e308', null],
            ['DOUBLE tiny', 'DOUBLE', '2.2e-308', null],
            ['DOUBLE negative zero', 'DOUBLE', '-0.0', null],
            ['VARCHAR 4000', 'VARCHAR(4000)', str_repeat('y', 4000), null],
            ['VARCHAR utf8 multibyte', 'VARCHAR(10)', '日本語テスト', '日本語テスト'],
            ['CHAR padding semantics', 'CHAR(10)', 'abc', 'abc'],
            ['TEXT 64KB', 'TEXT', str_repeat('z', 60000), null],
            ['MEDIUMTEXT large', 'MEDIUMTEXT', str_repeat('m', 70000), null],
            ['LONGTEXT', 'LONGTEXT', str_repeat('l', 1000), null],
            ['VARBINARY roundtrip', 'VARBINARY(16)', "\x00\x01\x02\xff", null],
            ['BLOB binary', 'BLOB', "\x00\xde\xad\xbe\xef", null],
            ['DATE min 1000', 'DATE', '1000-01-01', '1000-01-01'],
            ['DATE max 9999', 'DATE', '9999-12-31', '9999-12-31'],
            ['DATETIME(6) micros', 'DATETIME(6)', '2026-06-23 10:00:00.123456', '2026-06-23 10:00:00.123456'],
            ['TIMESTAMP frac', 'TIMESTAMP(3)', '2026-06-23 10:00:00.123', null],
            ['TIME large hours', 'TIME', '838:59:59', null],
            ['TIME negative', 'TIME', '-100:00:00', null],
            ['TIME(6) micros', 'TIME(6)', '10:20:30.123456', null],
            ['YEAR type', 'YEAR', 2026, null],
            ['BIT(8) value', 'BIT(8)', 255, null],
            ['ENUM valid', "ENUM('xs','s','m','l','xl')", 'l', 'l'],
            ['ENUM first', "ENUM('a','b')", 'a', 'a'],
            ['SET multiple', "SET('r','g','b')", 'r,b', null],
            ['SET single', "SET('r','g','b')", 'g', 'g'],
            ['JSON deep nesting', 'JSON', '{"a":{"b":{"c":{"d":[1,2,3]}}}}', null],
            ['JSON array of objects', 'JSON', '[{"x":1},{"x":2}]', null],
            ['JSON unicode', 'JSON', '{"name":"日本"}', null],
            ['VECF32 dim 3', 'VECF32(3)', '[1.5,2.5,3.5]', null],
            ['VECF32 dim 8', 'VECF32(8)', '[1,2,3,4,5,6,7,8]', null],
            ['VECF64 dim 3', 'VECF64(3)', '[0.1,0.2,0.3]', null],
            ['UUID stored', 'UUID', '123e4567-e89b-12d3-a456-426614174000', null],
            ['NULL into nullable', 'INT', null, null],
            ['empty string into VARCHAR', 'VARCHAR(5)', '', ''],
        ];
        foreach ($cases as [$name, $coltype, $value, $expect]) {
            $r->add('PDO', 'datatype:edge2', $name, function () use ($db, $name, $coltype, $value, $expect) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('te2');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $coltype)");
                try {
                    $st = $pdo->prepare("INSERT INTO `$tn`(id,c) VALUES (1, ?)");
                    $st->execute([$value]);
                    $got = $pdo->query("SELECT c FROM `$tn` WHERE id=1")->fetchColumn();
                    if ($value === null) {
                        Support::assert($got === null, "expected NULL stored, got " . var_export($got, true));
                    } elseif ($expect !== null) {
                        Support::assertEquals($expect, $got, $name);
                    } else {
                        Support::assert($got !== null && $got !== false, 'not stored');
                    }
                    return ['detail' => 'stored', 'sql' => "INSERT $coltype"];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // Overflow / out-of-range insertions that should be rejected by strict mode.
        self::addReject($r, 'datatype:edge2', 'TINYINT overflow rejected',
            ['CREATE TABLE {t} (id INT PRIMARY KEY, c TINYINT)'],
            'INSERT INTO {t} VALUES (1, 9999)');
        self::addReject($r, 'datatype:edge2', 'unsigned negative rejected',
            ['CREATE TABLE {t} (id INT PRIMARY KEY, c INT UNSIGNED)'],
            'INSERT INTO {t} VALUES (1, -1)');
        self::addReject($r, 'datatype:edge2', 'ENUM invalid value rejected',
            ["CREATE TABLE {t} (id INT PRIMARY KEY, c ENUM('a','b'))"],
            "INSERT INTO {t} VALUES (1, 'zzz')");
        self::addReject($r, 'datatype:edge2', 'VARCHAR overflow rejected',
            ['CREATE TABLE {t} (id INT PRIMARY KEY, c VARCHAR(3))'],
            "INSERT INTO {t} VALUES (1, 'abcdefgh')");
        self::addReject($r, 'datatype:edge2', 'invalid DATE rejected',
            ['CREATE TABLE {t} (id INT PRIMARY KEY, c DATE)'],
            "INSERT INTO {t} VALUES (1, '2026-13-45')");
    }

    // ---------------------------------------------------------------------
    // 10. Charset
    // ---------------------------------------------------------------------

    private static function charset(Runner $r): void
    {
        $db = Config::database('pdo');

        // CREATE TABLE with explicit CHARACTER SET, then verify the column's
        // reported charset via information_schema.
        $charsetCases = [
            ['utf8mb4', 'utf8mb4'],
            ['utf8', 'utf8'],
            ['ascii', 'ascii'],
            ['latin1', 'latin1'],
            ['binary', 'binary'],
        ];
        foreach ($charsetCases as [$label, $cs]) {
            $r->add('PDO', 'charset', "column CHARACTER SET $label honored", function () use ($db, $cs, $label) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('cs');
                $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20) CHARACTER SET $cs)");
                try {
                    $got = $pdo->query("SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME='$tn' AND COLUMN_NAME='c'")->fetchColumn();
                    Support::assert($got !== false && $got !== null, "no CHARACTER_SET_NAME reported");
                    // Engines may normalise (utf8->utf8mb3, binary->NULL). Record any divergence.
                    if ($cs !== 'binary' && (string) $got !== $cs && strpos((string) $got, $cs) === false) {
                        throw new BehaviorMismatch("declared CHARACTER SET=$cs, information_schema reports=" . var_export($got, true));
                    }
                    return ['detail' => "charset=$got", 'sql' => "CHARACTER SET $cs"];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // Table-level DEFAULT CHARSET creation should succeed for each charset.
        foreach (['utf8mb4', 'utf8', 'ascii', 'latin1'] as $cs) {
            self::addCase($r, 'charset', "table DEFAULT CHARSET=$cs", ['run' => "CREATE TABLE {t} (c VARCHAR(10)) DEFAULT CHARSET=$cs"]);
        }

        // Charset functions / conversions.
        $exprCases = [
            ['CONVERT USING utf8mb4', "CONVERT('abc' USING utf8mb4)", 'abc'],
            ['CONVERT USING ascii', "CONVERT('abc' USING ascii)", 'abc'],
            ['CONVERT USING latin1', "CONVERT('abc' USING latin1)", 'abc'],
            ['CONVERT USING binary', "CAST(CONVERT('abc' USING binary) AS CHAR)", 'abc'],
            ['CHARSET of literal', "CHARSET('abc')", null],
            ['CHARSET after CONVERT', "CHARSET(CONVERT('abc' USING ascii))", 'ascii'],
            ['_utf8mb4 introducer', "_utf8mb4'hello'", 'hello'],
            ['_ascii introducer', "_ascii'hi'", 'hi'],
            ['LENGTH vs CHAR_LENGTH multibyte', "LENGTH('日') > CHAR_LENGTH('日')", '1'],
            ['CONVERT changes byte length', "LENGTH(CONVERT('é' USING ascii))", null],
            ['HEX of utf8 multibyte', "HEX(CONVERT('A' USING utf8mb4))", '41'],
            ['CAST AS CHAR charset', "CHARSET(CAST(1 AS CHAR))", null],
        ];
        foreach ($exprCases as [$name, $expr, $expect]) {
            $r->add('PDO', 'charset', $name, function () use ($db, $expr, $expect, $name) {
                $pdo = Connections::pdo($db);
                $got = $pdo->query("SELECT $expr")->fetchColumn();
                if ($expect !== null) {
                    Support::assertValueEquals($expect, $got, $name);
                } else {
                    Support::assert($got !== null && $got !== false, 'returned NULL/false');
                }
                return ['detail' => '= ' . var_export($got, true), 'sql' => $expr];
            });
        }
    }

    // ---------------------------------------------------------------------
    // 11. Collation
    // ---------------------------------------------------------------------

    private static function collation(Runner $r): void
    {
        $db = Config::database('pdo');

        // COLLATE in expression context.
        $exprCases = [
            ['COLLATE utf8mb4_bin literal', "'a' COLLATE utf8mb4_bin", 'a'],
            ['COLLATE utf8mb4_general_ci', "'a' COLLATE utf8mb4_general_ci", 'a'],
            ['COLLATE utf8mb4_0900_ai_ci', "'a' COLLATE utf8mb4_0900_ai_ci", 'a'],
            ['_bin case-sensitive compare', "'A' = 'a' COLLATE utf8mb4_bin", '0'],
            ['_general_ci case-insensitive', "'A' = 'a' COLLATE utf8mb4_general_ci", '1'],
            ['_0900_ai_ci case-insensitive', "'A' = 'a' COLLATE utf8mb4_0900_ai_ci", '1'],
            ['COLLATE in comparison both sides', "('abc' COLLATE utf8mb4_bin) = ('abc' COLLATE utf8mb4_bin)", '1'],
            ['COLLATION of literal', "COLLATION('x')", null],
            ['COLLATION after COLLATE', "COLLATION('x' COLLATE utf8mb4_bin)", 'utf8mb4_bin'],
            ['_bin ordering distinct', "'B' < 'a' COLLATE utf8mb4_bin", '1'],
            ['_general_ci ordering', "STRCMP('a' COLLATE utf8mb4_general_ci, 'A' COLLATE utf8mb4_general_ci)", '0'],
            ['ascii_bin collation', "'a' COLLATE ascii_bin", 'a'],
        ];
        foreach ($exprCases as [$name, $expr, $expect]) {
            $r->add('PDO', 'collation', $name, function () use ($db, $expr, $expect, $name) {
                $pdo = Connections::pdo($db);
                $got = $pdo->query("SELECT $expr")->fetchColumn();
                if ($expect !== null) {
                    Support::assertValueEquals($expect, $got, $name);
                } else {
                    Support::assert($got !== null && $got !== false, 'returned NULL/false');
                }
                return ['detail' => '= ' . var_export($got, true), 'sql' => $expr];
            });
        }

        // COLLATE in ORDER BY / WHERE / GROUP BY against a real column.
        $orderSeed = [
            'CREATE TABLE {t} (id INT, s VARCHAR(10))',
            "INSERT INTO {t} VALUES (1,'Apple'),(2,'banana'),(3,'apple'),(4,'Banana')",
        ];
        $orderCases = [
            ['ORDER BY COLLATE bin', "(SELECT s FROM {t} ORDER BY s COLLATE utf8mb4_bin LIMIT 1)", 'Apple'],
            ['WHERE COLLATE ci matches both', "(SELECT COUNT(*) FROM {t} WHERE s='apple' COLLATE utf8mb4_general_ci)", '2'],
            ['WHERE COLLATE bin exact', "(SELECT COUNT(*) FROM {t} WHERE s='apple' COLLATE utf8mb4_bin)", '1'],
            ['GROUP BY COLLATE ci buckets', "(SELECT COUNT(*) FROM (SELECT s FROM {t} GROUP BY s COLLATE utf8mb4_general_ci) z)", '2'],
            ['GROUP BY COLLATE bin buckets', "(SELECT COUNT(*) FROM (SELECT s FROM {t} GROUP BY s COLLATE utf8mb4_bin) z)", '4'],
            ['DISTINCT COLLATE ci', "(SELECT COUNT(*) FROM (SELECT DISTINCT s COLLATE utf8mb4_general_ci cs FROM {t}) z)", '2'],
        ];
        self::scalarBatch($r, 'collation', $orderSeed, $orderCases);

        // Column declared with explicit COLLATE: verify it is reported back.
        $collCols = ['utf8mb4_bin', 'utf8mb4_general_ci', 'utf8mb4_0900_ai_ci'];
        foreach ($collCols as $coll) {
            $r->add('PDO', 'collation', "column COLLATE $coll honored", function () use ($db, $coll) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('col');
                $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20) COLLATE $coll)");
                try {
                    $got = $pdo->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME='$tn' AND COLUMN_NAME='c'")->fetchColumn();
                    Support::assert($got !== false && $got !== null, 'no COLLATION_NAME reported');
                    if ((string) $got !== $coll) {
                        throw new BehaviorMismatch("declared COLLATE=$coll, information_schema reports=" . var_export($got, true));
                    }
                    return ['detail' => "collation=$got", 'sql' => "COLLATE $coll"];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // Mixing incompatible collations explicitly should raise an error.
        self::addReject($r, 'collation', 'illegal mix of collations rejected',
            ['CREATE TABLE {t} (a VARCHAR(5) COLLATE utf8mb4_bin, b VARCHAR(5) COLLATE utf8mb4_general_ci)', "INSERT INTO {t} VALUES ('x','x')"],
            'SELECT COUNT(*) FROM {t} WHERE a=b');
    }

    // ---------------------------------------------------------------------
    // 12. Transactions (advanced)
    // ---------------------------------------------------------------------

    private static function transactions2(Runner $r): void
    {
        $db = Config::database('pdo');

        // Isolation levels: set then run a trivial committed write.
        foreach (['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'] as $iso) {
            $r->add('PDO', 'transaction2', "isolation $iso write-read", function () use ($db, $iso) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('tx2');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
                try {
                    $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL $iso");
                    $pdo->exec('START TRANSACTION');
                    $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                    $pdo->exec('COMMIT');
                    $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                    Support::assertEquals('1', $c, "committed under $iso");
                    return "ok under $iso";
                } finally {
                    try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // SET TRANSACTION (next-transaction scope).
        $r->add('PDO', 'transaction2', 'SET TRANSACTION ISOLATION next-tx', function () use ($db) {
            $pdo = Connections::pdo($db);
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            return 'accepted SET TRANSACTION';
        });

        // START TRANSACTION READ ONLY / READ WRITE.
        $r->add('PDO', 'transaction2', 'START TRANSACTION READ ONLY', function () use ($db) {
            $pdo = Connections::pdo($db);
            $pdo->exec('START TRANSACTION READ ONLY');
            $v = $pdo->query('SELECT 1')->fetchColumn();
            $pdo->exec('COMMIT');
            Support::assertEquals('1', $v, 'read in RO tx');
            return 'read-only tx ok';
        });
        $r->add('PDO', 'transaction2', 'START TRANSACTION READ WRITE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->exec('START TRANSACTION READ WRITE');
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $pdo->exec('COMMIT');
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $c, 'write in RW tx');
                return 'read-write tx ok';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Writing inside a READ ONLY transaction should be rejected.
        $r->add('PDO', 'transaction2', 'write in READ ONLY tx rejected', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->exec('START TRANSACTION READ ONLY');
                $rejected = false;
                try {
                    $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                } catch (\Throwable) {
                    $rejected = true;
                }
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                Support::assert($rejected, 'write was allowed inside READ ONLY transaction');
                return 'RO write correctly rejected';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Autocommit toggle affecting visibility.
        $r->add('PDO', 'transaction2', 'autocommit=0 then rollback', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->exec('SET autocommit=0');
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $pdo->exec('ROLLBACK');
                $pdo->exec('SET autocommit=1');
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('0', $c, 'rollback under autocommit=0');
                return 'autocommit=0 rollback reverted';
            } finally {
                try { $pdo->exec('SET autocommit=1'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // DDL inside a transaction implicitly commits prior DML (MySQL semantics).
        $r->add('PDO', 'transaction2', 'DDL implicit commit', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $tn2 = Support::name('tx2b');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->exec('START TRANSACTION');
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                // This DDL should implicitly commit the INSERT above in MySQL.
                $pdo->exec("CREATE TABLE `$tn2` (id INT)");
                $pdo->exec('ROLLBACK');
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                if ((string) $c !== '1') {
                    throw new BehaviorMismatch("MySQL: DDL implicitly commits prior INSERT (count=1); MatrixOne count=$c");
                }
                return 'DDL implicitly committed prior DML';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn2`");
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Savepoints (expected unimplemented in MatrixOne).
        $r->add('PDO', 'transaction2', 'SAVEPOINT + ROLLBACK TO partial', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->exec('START TRANSACTION');
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $pdo->exec('SAVEPOINT sp1');
                $pdo->exec("INSERT INTO `$tn` VALUES (2)");
                $pdo->exec('ROLLBACK TO SAVEPOINT sp1');
                $pdo->exec('COMMIT');
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $c, 'savepoint partial rollback count');
                return 'savepoint rollback worked';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'transaction2', 'RELEASE SAVEPOINT', function () use ($db) {
            $pdo = Connections::pdo($db);
            try {
                $pdo->exec('START TRANSACTION');
                $pdo->exec('SAVEPOINT sp1');
                $pdo->exec('RELEASE SAVEPOINT sp1');
                $pdo->exec('COMMIT');
                return 'release savepoint ok';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
            }
        });

        // Locking reads.
        $r->add('PDO', 'transaction2', 'SELECT ... FOR UPDATE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $pdo->exec('START TRANSACTION');
                $rows = $pdo->query("SELECT * FROM `$tn` WHERE id=1 FOR UPDATE")->fetchAll();
                $pdo->exec('COMMIT');
                Support::assert(count($rows) === 1, 'FOR UPDATE returned wrong rows');
                return 'FOR UPDATE ok';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'transaction2', 'SELECT ... FOR SHARE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $rows = $pdo->query("SELECT * FROM `$tn` WHERE id=1 FOR SHARE")->fetchAll();
                Support::assert(count($rows) === 1, 'FOR SHARE returned wrong rows');
                return 'FOR SHARE ok';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'transaction2', 'LOCK IN SHARE MODE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $rows = $pdo->query("SELECT * FROM `$tn` WHERE id=1 LOCK IN SHARE MODE")->fetchAll();
                Support::assert(count($rows) === 1, 'LOCK IN SHARE MODE wrong rows');
                return 'lock in share mode ok';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'transaction2', 'FOR UPDATE NOWAIT', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $pdo->exec('START TRANSACTION');
                $pdo->query("SELECT * FROM `$tn` WHERE id=1 FOR UPDATE NOWAIT")->fetchAll();
                $pdo->exec('COMMIT');
                return 'FOR UPDATE NOWAIT ok';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'transaction2', 'FOR UPDATE SKIP LOCKED', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx2');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $pdo->exec('START TRANSACTION');
                $pdo->query("SELECT * FROM `$tn` FOR UPDATE SKIP LOCKED")->fetchAll();
                $pdo->exec('COMMIT');
                return 'FOR UPDATE SKIP LOCKED ok';
            } finally {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    // ---------------------------------------------------------------------
    // 13. Introspection accuracy
    // ---------------------------------------------------------------------

    private static function introspection2(Runner $r): void
    {
        $db = Config::database('pdo');

        // Build a table with known shape, then assert information_schema.COLUMNS
        // reports each property MySQL-accurately. Each property is its own
        // scenario so divergences are pinpointed.
        $ddl = "CREATE TABLE `%s` (
            id INT NOT NULL,
            name VARCHAR(40) NOT NULL DEFAULT 'anon',
            price DECIMAL(10,2) DEFAULT NULL,
            qty SMALLINT UNSIGNED DEFAULT 0,
            bio TEXT,
            created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )";
        // [scenario name, column, info_schema field, MySQL-expected value]
        $colChecks = [
            ['COLUMNS DATA_TYPE int lowercase', 'id', 'DATA_TYPE', 'int'],
            ['COLUMNS DATA_TYPE varchar', 'name', 'DATA_TYPE', 'varchar'],
            ['COLUMNS DATA_TYPE decimal', 'price', 'DATA_TYPE', 'decimal'],
            ['COLUMNS DATA_TYPE smallint', 'qty', 'DATA_TYPE', 'smallint'],
            ['COLUMNS DATA_TYPE text', 'bio', 'DATA_TYPE', 'text'],
            ['COLUMNS IS_NULLABLE NOT NULL', 'id', 'IS_NULLABLE', 'NO'],
            ['COLUMNS IS_NULLABLE nullable', 'price', 'IS_NULLABLE', 'YES'],
            ['COLUMNS COLUMN_DEFAULT string', 'name', 'COLUMN_DEFAULT', 'anon'],
            ['COLUMNS COLUMN_DEFAULT numeric', 'qty', 'COLUMN_DEFAULT', '0'],
            ['COLUMNS COLUMN_DEFAULT NULL', 'bio', 'COLUMN_DEFAULT', null],
            ['COLUMNS CHAR_MAX_LENGTH varchar', 'name', 'CHARACTER_MAXIMUM_LENGTH', '40'],
            ['COLUMNS CHAR_MAX_LENGTH text', 'bio', 'CHARACTER_MAXIMUM_LENGTH', '65535'],
            ['COLUMNS NUMERIC_PRECISION decimal', 'price', 'NUMERIC_PRECISION', '10'],
            ['COLUMNS NUMERIC_SCALE decimal', 'price', 'NUMERIC_SCALE', '2'],
            ['COLUMNS NUMERIC_PRECISION int', 'id', 'NUMERIC_PRECISION', '10'],
            ['COLUMNS COLUMN_KEY primary', 'id', 'COLUMN_KEY', 'PRI'],
            ['COLUMNS COLUMN_TYPE int', 'id', 'COLUMN_TYPE', 'int'],
            ['COLUMNS COLUMN_TYPE unsigned', 'qty', 'COLUMN_TYPE', 'smallint unsigned'],
            ['COLUMNS EXTRA empty', 'id', 'EXTRA', ''],
        ];
        foreach ($colChecks as [$name, $col, $field, $expected]) {
            $r->add('PDO', 'introspection2', $name, function () use ($db, $ddl, $col, $field, $expected, $name) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('isc');
                $pdo->exec(sprintf($ddl, $tn));
                try {
                    $sql = "SELECT $field FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND COLUMN_NAME='$col'";
                    $got = $pdo->query($sql)->fetchColumn();
                    $gotN = $got === false ? null : $got;
                    if ($expected === null) {
                        if ($gotN !== null) {
                            throw new BehaviorMismatch("MySQL=$field NULL, MatrixOne=" . var_export($gotN, true));
                        }
                    } else {
                        // case-insensitive compare for type names; exact otherwise
                        if (strcasecmp((string) $expected, (string) $gotN) !== 0) {
                            throw new BehaviorMismatch("MySQL=$field '$expected', MatrixOne=" . var_export($gotN, true));
                        }
                    }
                    return ['detail' => "$field=" . var_export($gotN, true), 'sql' => $sql];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // STATISTICS: an index over two columns should report two rows.
        $r->add('PDO', 'introspection2', 'STATISTICS reports composite index', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ist');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, c INT, INDEX idx_ab (a,b))");
            try {
                $sql = "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND INDEX_NAME='idx_ab'";
                $c = $pdo->query($sql)->fetchColumn();
                Support::assertEquals('2', $c, 'composite index column count in STATISTICS');
                return ['detail' => "rows=$c", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'STATISTICS SEQ_IN_INDEX ordering', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ist');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, INDEX idx_ab (a,b))");
            try {
                $sql = "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND INDEX_NAME='idx_ab' ORDER BY SEQ_IN_INDEX LIMIT 1";
                $first = $pdo->query($sql)->fetchColumn();
                Support::assertEquals('a', $first, 'first column by SEQ_IN_INDEX');
                return ['detail' => "first=$first", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'STATISTICS NON_UNIQUE flag', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ist');
            $pdo->exec("CREATE TABLE `$tn` (a INT, UNIQUE uq_a (a))");
            try {
                $sql = "SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND INDEX_NAME='uq_a'";
                $nu = $pdo->query($sql)->fetchColumn();
                Support::assertEquals('0', $nu, 'unique index NON_UNIQUE should be 0');
                return ['detail' => "NON_UNIQUE=$nu", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // KEY_COLUMN_USAGE for the primary key.
        $r->add('PDO', 'introspection2', 'KEY_COLUMN_USAGE primary key', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iskc');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, PRIMARY KEY (a,b))");
            try {
                $sql = "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND CONSTRAINT_NAME='PRIMARY'";
                $c = $pdo->query($sql)->fetchColumn();
                Support::assertEquals('2', $c, 'PK columns in KEY_COLUMN_USAGE');
                return ['detail' => "rows=$c", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // KEY_COLUMN_USAGE for a foreign key (REFERENCED columns populated).
        $r->add('PDO', 'introspection2', 'KEY_COLUMN_USAGE foreign key referenced', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tp = Support::name('iskp');
            $tc = Support::name('iskc');
            $pdo->exec("CREATE TABLE `$tp` (id INT PRIMARY KEY)");
            $pdo->exec("CREATE TABLE `$tc` (id INT PRIMARY KEY, pid INT, CONSTRAINT fk_pid FOREIGN KEY (pid) REFERENCES `$tp`(id))");
            try {
                $sql = "SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tc' AND CONSTRAINT_NAME='fk_pid'";
                $ref = $pdo->query($sql)->fetchColumn();
                if ((string) $ref !== $tp) {
                    throw new BehaviorMismatch("MySQL REFERENCED_TABLE_NAME='$tp', MatrixOne=" . var_export($ref, true));
                }
                return ['detail' => "referenced=$ref", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tc`");
                $pdo->exec("DROP TABLE IF EXISTS `$tp`");
            }
        });

        // TABLE_CONSTRAINTS classifies PK / UNIQUE.
        $r->add('PDO', 'introspection2', 'TABLE_CONSTRAINTS PK type', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('istc');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, u INT UNIQUE)");
            try {
                $sql = "SELECT CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn' AND CONSTRAINT_NAME='PRIMARY'";
                $t = $pdo->query($sql)->fetchColumn();
                Support::assertEquals('PRIMARY KEY', $t, 'PK constraint type');
                return ['detail' => "type=$t", 'sql' => $sql];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // SHOW CREATE TABLE round-trip: re-create from the emitted DDL.
        $r->add('PDO', 'introspection2', 'SHOW CREATE TABLE round-trip', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iscr');
            $tn2 = $tn . '_rt';
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, amt DECIMAL(8,2))");
            try {
                $row = $pdo->query("SHOW CREATE TABLE `$tn`")->fetch(\PDO::FETCH_NUM);
                Support::assert(is_array($row) && isset($row[1]), 'no DDL emitted');
                $ddl = (string) $row[1];
                // Rename target table so the re-create does not collide.
                $ddl2 = preg_replace('/`' . preg_quote($tn, '/') . '`/', "`$tn2`", $ddl, 1);
                $pdo->exec($ddl2);
                $c = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tn2'")->fetchColumn();
                Support::assertEquals('3', $c, 're-created table column count');
                return ['detail' => 'round-trip ok', 'sql' => 'SHOW CREATE TABLE'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn2`");
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // SHOW COLUMNS / SHOW INDEX / SHOW KEYS shape.
        $r->add('PDO', 'introspection2', 'SHOW COLUMNS count', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iss');
            $pdo->exec("CREATE TABLE `$tn` (a INT, b INT, c INT)");
            try {
                $rows = $pdo->query("SHOW COLUMNS FROM `$tn`")->fetchAll();
                Support::assertEquals('3', (string) count($rows), 'SHOW COLUMNS count');
                return ['detail' => count($rows) . ' columns', 'sql' => 'SHOW COLUMNS'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'SHOW INDEX rows', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iss');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT, INDEX i_a(a))");
            try {
                $rows = $pdo->query("SHOW INDEX FROM `$tn`")->fetchAll();
                Support::assert(count($rows) >= 2, 'expected >=2 index rows (PK + idx)');
                return ['detail' => count($rows) . ' index rows', 'sql' => 'SHOW INDEX'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'SHOW KEYS rows', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iss');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $rows = $pdo->query("SHOW KEYS FROM `$tn`")->fetchAll();
                Support::assert(count($rows) >= 1, 'expected >=1 key row');
                return ['detail' => count($rows) . ' key rows', 'sql' => 'SHOW KEYS'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'SHOW FULL COLUMNS has Collation', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('iss');
            $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(10))");
            try {
                $row = $pdo->query("SHOW FULL COLUMNS FROM `$tn`")->fetch(\PDO::FETCH_ASSOC);
                Support::assert(is_array($row) && array_key_exists('Collation', $row), 'no Collation column in SHOW FULL COLUMNS');
                return ['detail' => 'has Collation', 'sql' => 'SHOW FULL COLUMNS'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Probe each commonly-referenced information_schema table with a simple SELECT.
        $isTables = [
            'TABLES', 'COLUMNS', 'STATISTICS', 'KEY_COLUMN_USAGE', 'TABLE_CONSTRAINTS',
            'REFERENTIAL_CONSTRAINTS', 'SCHEMATA', 'VIEWS', 'CHARACTER_SETS', 'COLLATIONS',
            'ENGINES', 'PARTITIONS', 'TRIGGERS', 'ROUTINES', 'PARAMETERS', 'EVENTS',
            'COLUMN_PRIVILEGES', 'TABLE_PRIVILEGES', 'USER_PRIVILEGES', 'SCHEMA_PRIVILEGES',
            'PROCESSLIST', 'FILES', 'PLUGINS', 'CHECK_CONSTRAINTS',
            'COLLATION_CHARACTER_SET_APPLICABILITY', 'KEYWORDS',
        ];
        foreach ($isTables as $tbl) {
            $r->add('PDO', 'introspection2', "information_schema.$tbl SELECT", function () use ($db, $tbl) {
                $pdo = Connections::pdo($db);
                $sql = "SELECT * FROM information_schema.$tbl LIMIT 1";
                $st = $pdo->query($sql);
                $st->fetchAll();
                return ['detail' => 'queryable', 'sql' => $sql];
            });
        }

        // DESCRIBE / EXPLAIN extended forms.
        $r->add('PDO', 'introspection2', 'EXPLAIN ANALYZE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ise');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $pdo->query("EXPLAIN ANALYZE SELECT * FROM `$tn`")->fetchAll();
                return ['detail' => 'explained', 'sql' => 'EXPLAIN ANALYZE'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection2', 'EXPLAIN FORMAT=JSON', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('ise');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->query("EXPLAIN FORMAT=JSON SELECT * FROM `$tn`")->fetchAll();
                return ['detail' => 'explained', 'sql' => 'EXPLAIN FORMAT=JSON'];
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }
}
