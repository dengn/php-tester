<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * DDL, DML, query, constraint, index and transaction coverage via raw PDO.
 *
 * Cases are data-driven. Placeholders {t}, {t2}, {t3} are replaced with unique
 * table names per scenario and auto-dropped afterwards so every case is
 * isolated.
 */
final class PdoSqlScenarios
{
    public static function register(Runner $r): void
    {
        self::ddl($r);
        self::dml($r);
        self::queries($r);
        self::constraints($r);
        self::indexes($r);
        self::transactions($r);
        self::introspection($r);
    }

    /**
     * Run a DDL/DML statement through the text protocol (COM_QUERY).
     *
     * exec() is used deliberately rather than query(): the baseline connection
     * disables prepared-statement emulation, so query() would issue a binary
     * COM_STMT_PREPARE. That conflates "is this statement supported" with "can
     * it be prepared" (a separate axis covered by PreparedStatementScenarios).
     * Statement support is what these DDL/DML cases mean to test.
     */
    private static function exec1(\PDO $pdo, string $sql): void
    {
        // Read statements may be prepared safely; route them through query()
        // and free the cursor. DDL/DML go through the text protocol so
        // statements that the engine cannot prepare (REPLACE, TRUNCATE, …) are
        // measured for support rather than preparability.
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
     * @param array{setup?:array<string>,run?:string|array<string>,check?:array{0:string,1:mixed},tables?:int} $opts
     */
    private static function addCase(Runner $r, string $cat, string $name, array $opts): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', $cat, $name, function () use ($db, $opts, $name) {
            $pdo = Connections::pdo($db);
            $nTables = $opts['tables'] ?? 1;
            $names = [];
            for ($i = 1; $i <= max($nTables, 3); $i++) {
                $names['{t' . ($i === 1 ? '' : $i) . '}'] = Support::name('s');
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
                foreach (array_values($names) as $tn) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            }
        });
    }

    private static function ddl(Runner $r): void
    {
        $base = 'CREATE TABLE {t} (id INT PRIMARY KEY)';
        $cases = [
            ['ddl:create', 'CREATE TABLE basic', ['run' => $base]],
            ['ddl:create', 'CREATE TABLE IF NOT EXISTS', ['run' => 'CREATE TABLE IF NOT EXISTS {t} (id INT)']],
            ['ddl:create', 'CREATE TABLE AS SELECT', ['setup' => [$base], 'run' => 'CREATE TABLE {t2} AS SELECT * FROM {t}']],
            ['ddl:create', 'CREATE TABLE LIKE', ['setup' => [$base], 'run' => 'CREATE TABLE {t2} LIKE {t}']],
            ['ddl:create', 'TEMPORARY TABLE', ['run' => 'CREATE TEMPORARY TABLE {t} (id INT)']],
            ['ddl:create', 'table with ENGINE option', ['run' => 'CREATE TABLE {t} (id INT) ENGINE=InnoDB']],
            ['ddl:create', 'table with AUTO_INCREMENT start', ['run' => 'CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT) AUTO_INCREMENT=100']],
            ['ddl:create', 'table with CHARSET option', ['run' => 'CREATE TABLE {t} (id INT) DEFAULT CHARSET=utf8mb4']],
            ['ddl:create', 'table with COLLATE option', ['run' => 'CREATE TABLE {t} (c VARCHAR(10)) COLLATE=utf8mb4_general_ci']],
            ['ddl:create', 'table COMMENT option', ['run' => "CREATE TABLE {t} (id INT) COMMENT='hello'"]],
            ['ddl:create', 'column COMMENT', ['run' => "CREATE TABLE {t} (id INT COMMENT 'primary')"]],
            ['ddl:create', 'composite PRIMARY KEY', ['run' => 'CREATE TABLE {t} (a INT, b INT, PRIMARY KEY(a,b))']],
            ['ddl:create', 'column DEFAULT literal', ['run' => "CREATE TABLE {t} (id INT, s VARCHAR(5) DEFAULT 'x')"]],
            ['ddl:create', 'DEFAULT CURRENT_TIMESTAMP', ['run' => 'CREATE TABLE {t} (id INT, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP)']],
            ['ddl:create', 'ON UPDATE CURRENT_TIMESTAMP', ['run' => 'CREATE TABLE {t} (id INT, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)']],
            ['ddl:create', 'AUTO_INCREMENT column', ['run' => 'CREATE TABLE {t} (id INT AUTO_INCREMENT PRIMARY KEY)']],
            ['ddl:create', 'generated column STORED', ['run' => 'CREATE TABLE {t} (a INT, b INT GENERATED ALWAYS AS (a+1) STORED)']],
            ['ddl:create', 'generated column VIRTUAL', ['run' => 'CREATE TABLE {t} (a INT, b INT GENERATED ALWAYS AS (a+1) VIRTUAL)']],
            ['ddl:create', 'CLUSTER BY', ['run' => 'CREATE TABLE {t} (a INT, b INT) CLUSTER BY (a)']],

            ['ddl:alter', 'ALTER ADD COLUMN', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c VARCHAR(10)']],
            ['ddl:alter', 'ALTER ADD COLUMN AFTER', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c INT AFTER id']],
            ['ddl:alter', 'ALTER ADD COLUMN FIRST', ['setup' => [$base], 'run' => 'ALTER TABLE {t} ADD COLUMN c INT FIRST']],
            ['ddl:alter', 'ALTER DROP COLUMN', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} DROP COLUMN c']],
            ['ddl:alter', 'ALTER MODIFY COLUMN', ['setup' => ['CREATE TABLE {t} (id INT, c VARCHAR(10))'], 'run' => 'ALTER TABLE {t} MODIFY COLUMN c VARCHAR(50)']],
            ['ddl:alter', 'ALTER CHANGE COLUMN (rename)', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} CHANGE COLUMN c d BIGINT']],
            ['ddl:alter', 'ALTER RENAME COLUMN', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} RENAME COLUMN c TO d']],
            ['ddl:alter', 'ALTER ALTER SET DEFAULT', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} ALTER COLUMN c SET DEFAULT 5']],
            ['ddl:alter', 'ALTER ADD INDEX', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} ADD INDEX idx_c (c)']],
            ['ddl:alter', 'ALTER ADD UNIQUE', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'ALTER TABLE {t} ADD UNIQUE uq_c (c)']],
            ['ddl:alter', 'ALTER DROP INDEX', ['setup' => ['CREATE TABLE {t} (id INT, c INT, INDEX idx_c(c))'], 'run' => 'ALTER TABLE {t} DROP INDEX idx_c']],
            ['ddl:alter', 'ALTER ADD PRIMARY KEY', ['setup' => ['CREATE TABLE {t} (id INT NOT NULL)'], 'run' => 'ALTER TABLE {t} ADD PRIMARY KEY (id)']],
            ['ddl:alter', 'ALTER DROP PRIMARY KEY', ['setup' => ['CREATE TABLE {t} (id INT NOT NULL PRIMARY KEY)'], 'run' => 'ALTER TABLE {t} DROP PRIMARY KEY']],
            ['ddl:alter', 'ALTER ADD FOREIGN KEY', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY)', 'CREATE TABLE {t2} (id INT PRIMARY KEY, fid INT)'], 'run' => 'ALTER TABLE {t2} ADD CONSTRAINT fk FOREIGN KEY (fid) REFERENCES {t}(id)']],
            ['ddl:alter', 'ALTER ADD CHECK', ['setup' => ['CREATE TABLE {t} (id INT, age INT)'], 'run' => 'ALTER TABLE {t} ADD CONSTRAINT chk CHECK (age > 0)']],
            ['ddl:alter', 'ALTER table COMMENT', ['setup' => [$base], 'run' => "ALTER TABLE {t} COMMENT='changed'"]],
            ['ddl:alter', 'ALTER RENAME TO', ['setup' => [$base], 'run' => 'ALTER TABLE {t} RENAME TO {t2}']],
            ['ddl:alter', 'RENAME TABLE', ['setup' => [$base], 'run' => 'RENAME TABLE {t} TO {t2}']],

            ['ddl:drop', 'DROP TABLE', ['setup' => [$base], 'run' => 'DROP TABLE {t}']],
            ['ddl:drop', 'DROP TABLE IF EXISTS', ['run' => 'DROP TABLE IF EXISTS {t}']],
            ['ddl:drop', 'TRUNCATE TABLE', ['setup' => [$base], 'run' => 'TRUNCATE TABLE {t}']],

            ['ddl:view', 'CREATE VIEW', ['setup' => [$base], 'run' => 'CREATE VIEW {t2} AS SELECT * FROM {t}']],
            ['ddl:view', 'CREATE OR REPLACE VIEW', ['setup' => [$base, 'CREATE VIEW {t2} AS SELECT * FROM {t}'], 'run' => 'CREATE OR REPLACE VIEW {t2} AS SELECT id FROM {t}']],
            ['ddl:view', 'DROP VIEW', ['setup' => [$base, 'CREATE VIEW {t2} AS SELECT * FROM {t}'], 'run' => 'DROP VIEW {t2}']],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    private static function dml(Runner $r): void
    {
        $t = 'CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, n INT, s VARCHAR(20))';
        $cases = [
            ['dml:insert', 'INSERT single row', ['setup' => [$t], 'run' => "INSERT INTO {t}(n,s) VALUES (1,'a')", 'check' => ['SELECT COUNT(*) FROM {t}', '1']]],
            ['dml:insert', 'INSERT multi row', ['setup' => [$t], 'run' => "INSERT INTO {t}(n,s) VALUES (1,'a'),(2,'b'),(3,'c')", 'check' => ['SELECT COUNT(*) FROM {t}', '3']]],
            ['dml:insert', 'INSERT SELECT', ['setup' => [$t, "INSERT INTO {t}(n,s) VALUES (1,'a')"], 'run' => 'INSERT INTO {t}(n,s) SELECT n,s FROM {t}', 'check' => ['SELECT COUNT(*) FROM {t}', '2']]],
            ['dml:insert', 'INSERT DEFAULT VALUES via ()', ['setup' => ['CREATE TABLE {t} (id INT DEFAULT 7)'], 'run' => 'INSERT INTO {t} () VALUES ()', 'check' => ['SELECT id FROM {t}', '7']]],
            ['dml:insert', 'INSERT IGNORE', ['setup' => [$t, "INSERT INTO {t}(id,n) VALUES (1,1)"], 'run' => 'INSERT IGNORE INTO {t}(id,n) VALUES (1,2)', 'check' => ['SELECT n FROM {t} WHERE id=1', '1']]],
            ['dml:insert', 'ON DUPLICATE KEY UPDATE', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'INSERT INTO {t}(id,n) VALUES (1,5) ON DUPLICATE KEY UPDATE n=VALUES(n)', 'check' => ['SELECT n FROM {t} WHERE id=1', '5']]],
            ['dml:insert', 'REPLACE INTO', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'REPLACE INTO {t}(id,n) VALUES (1,9)', 'check' => ['SELECT n FROM {t} WHERE id=1', '9']]],
            ['dml:update', 'UPDATE basic', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1)'], 'run' => 'UPDATE {t} SET n=2 WHERE id=1', 'check' => ['SELECT n FROM {t} WHERE id=1', '2']]],
            ['dml:update', 'UPDATE all rows', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2)'], 'run' => 'UPDATE {t} SET n=0', 'check' => ['SELECT SUM(n) FROM {t}', '0']]],
            ['dml:update', 'UPDATE ORDER BY LIMIT', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2),(3)'], 'run' => 'UPDATE {t} SET n=0 ORDER BY id LIMIT 1', 'check' => ['SELECT SUM(n) FROM {t}', '5']]],
            ['dml:update', 'UPDATE expression', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,10)'], 'run' => 'UPDATE {t} SET n=n+5 WHERE id=1', 'check' => ['SELECT n FROM {t} WHERE id=1', '15']]],
            ['dml:update', 'multi-table UPDATE join', ['setup' => [$t, 'CREATE TABLE {t2}(id INT, n INT)', 'INSERT INTO {t}(id,n) VALUES (1,1)', 'INSERT INTO {t2} VALUES (1,99)'], 'run' => 'UPDATE {t} a JOIN {t2} b ON a.id=b.id SET a.n=b.n', 'check' => ['SELECT n FROM {t} WHERE id=1', '99']]],
            ['dml:delete', 'DELETE basic', ['setup' => [$t, 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2)'], 'run' => 'DELETE FROM {t} WHERE id=1', 'check' => ['SELECT COUNT(*) FROM {t}', '1']]],
            ['dml:delete', 'DELETE ORDER BY LIMIT', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2),(3)'], 'run' => 'DELETE FROM {t} ORDER BY id LIMIT 1', 'check' => ['SELECT COUNT(*) FROM {t}', '2']]],
            ['dml:delete', 'DELETE all', ['setup' => [$t, 'INSERT INTO {t}(n) VALUES (1),(2)'], 'run' => 'DELETE FROM {t}', 'check' => ['SELECT COUNT(*) FROM {t}', '0']]],
            ['dml:delete', 'multi-table DELETE join', ['setup' => [$t, 'CREATE TABLE {t2}(id INT)', 'INSERT INTO {t}(id,n) VALUES (1,1),(2,2)', 'INSERT INTO {t2} VALUES (1)'], 'run' => 'DELETE a FROM {t} a JOIN {t2} b ON a.id=b.id', 'check' => ['SELECT COUNT(*) FROM {t}', '1']]],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    private static function queries(Runner $r): void
    {
        // Fixtures created inline per case.
        $a = 'CREATE TABLE {t} (id INT, grp INT, val INT)';
        $seed = "INSERT INTO {t} VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40)";
        $b = 'CREATE TABLE {t2} (id INT, label VARCHAR(10))';
        $seedB = "INSERT INTO {t2} VALUES (1,'x'),(2,'y')";

        $cases = [
            ['query:select', 'SELECT literal', ['check' => ['SELECT 1 + 1', '2'], 'tables' => 0]],
            ['query:select', 'DO statement', ['run' => 'DO 1', 'tables' => 0]],
            ['query:select', 'SELECT with WHERE', ['setup' => [$a, $seed], 'run' => 'CREATE TABLE {t3} AS SELECT * FROM {t} WHERE val>15', 'check' => ['SELECT COUNT(*) FROM {t3}', '3'], 'tables' => 3]],
            ['query:select', 'DISTINCT', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(DISTINCT grp) FROM {t}', '2'], 'run' => 'SELECT 1']],
            ['query:select', 'ORDER BY DESC', ['setup' => [$a, $seed], 'check' => ['SELECT id FROM {t} ORDER BY id DESC LIMIT 1', '4'], 'run' => 'SELECT 1']],
            ['query:select', 'LIMIT OFFSET', ['setup' => [$a, $seed], 'check' => ['SELECT id FROM {t} ORDER BY id LIMIT 1 OFFSET 2', '3'], 'run' => 'SELECT 1']],
            ['query:select', 'LIMIT n,m syntax', ['setup' => [$a, $seed], 'check' => ['SELECT id FROM {t} ORDER BY id LIMIT 2,1', '3'], 'run' => 'SELECT 1']],
            ['query:group', 'GROUP BY + aggregate', ['setup' => [$a, $seed], 'check' => ['SELECT SUM(val) FROM {t} GROUP BY grp ORDER BY grp LIMIT 1', '30'], 'run' => 'SELECT 1']],
            ['query:group', 'HAVING', ['setup' => [$a, $seed], 'check' => ['SELECT grp FROM {t} GROUP BY grp HAVING SUM(val)>40 ORDER BY grp LIMIT 1', '2'], 'run' => 'SELECT 1']],
            ['query:group', 'GROUP BY WITH ROLLUP', ['setup' => [$a, $seed], 'run' => 'CREATE TABLE {t3} AS SELECT grp, SUM(val) s FROM {t} GROUP BY grp WITH ROLLUP', 'tables' => 3]],
            ['query:join', 'INNER JOIN', ['setup' => [$a, $seed, $b, $seedB], 'check' => ['SELECT COUNT(*) FROM {t} a JOIN {t2} b ON a.grp=b.id', '4'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:join', 'LEFT JOIN', ['setup' => [$a, $seed, $b, $seedB], 'check' => ['SELECT COUNT(*) FROM {t} a LEFT JOIN {t2} b ON a.id=b.id', '4'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:join', 'RIGHT JOIN', ['setup' => [$a, $seed, $b, $seedB], 'check' => ['SELECT COUNT(*) FROM {t2} b RIGHT JOIN {t} a ON a.id=b.id', '4'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:join', 'CROSS JOIN', ['setup' => [$a, $seed, $b, $seedB], 'check' => ['SELECT COUNT(*) FROM {t} a CROSS JOIN {t2} b', '8'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:join', 'USING clause', ['setup' => [$a, $seed, 'CREATE TABLE {t2}(id INT, x INT)', 'INSERT INTO {t2} VALUES (1,7)'], 'check' => ['SELECT COUNT(*) FROM {t} JOIN {t2} USING(id)', '1'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:join', 'self join', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} a JOIN {t} b ON a.grp=b.grp WHERE a.id<b.id', '2'], 'run' => 'SELECT 1']],
            ['query:join', 'NATURAL JOIN', ['setup' => [$a, $seed, 'CREATE TABLE {t2}(id INT, x INT)', 'INSERT INTO {t2} VALUES (1,7)'], 'check' => ['SELECT COUNT(*) FROM {t} NATURAL JOIN {t2}', '1'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:subquery', 'scalar subquery', ['setup' => [$a, $seed], 'check' => ['SELECT (SELECT MAX(val) FROM {t})', '40'], 'run' => 'SELECT 1']],
            ['query:subquery', 'IN subquery', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} WHERE grp IN (SELECT grp FROM {t} WHERE val>30)', '2'], 'run' => 'SELECT 1']],
            ['query:subquery', 'EXISTS subquery', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} a WHERE EXISTS (SELECT 1 FROM {t} b WHERE b.grp=a.grp AND b.val>a.val)', '2'], 'run' => 'SELECT 1']],
            ['query:subquery', 'correlated subquery', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} a WHERE val=(SELECT MAX(val) FROM {t} b WHERE b.grp=a.grp)', '2'], 'run' => 'SELECT 1']],
            ['query:subquery', 'derived table', ['setup' => [$a, $seed], 'check' => ['SELECT SUM(s) FROM (SELECT grp, SUM(val) s FROM {t} GROUP BY grp) d', '100'], 'run' => 'SELECT 1']],
            ['query:setop', 'UNION', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM (SELECT val FROM {t} UNION SELECT val FROM {t}) u', '4'], 'run' => 'SELECT 1']],
            ['query:setop', 'UNION ALL', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM (SELECT val FROM {t} UNION ALL SELECT val FROM {t}) u', '8'], 'run' => 'SELECT 1']],
            ['query:setop', 'INTERSECT', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM (SELECT val FROM {t} INTERSECT SELECT val FROM {t}) u', '4'], 'run' => 'SELECT 1']],
            ['query:setop', 'EXCEPT', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM (SELECT val FROM {t} EXCEPT SELECT val FROM {t} WHERE val>30) u', '3'], 'run' => 'SELECT 1']],
            ['query:cte', 'CTE', ['setup' => [$a, $seed], 'check' => ['WITH c AS (SELECT SUM(val) s FROM {t}) SELECT s FROM c', '100'], 'run' => 'SELECT 1']],
            ['query:cte', 'recursive CTE', ['check' => ['WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<5) SELECT SUM(x) FROM n', '15'], 'run' => 'SELECT 1', 'tables' => 0]],
            ['query:misc', 'CASE in SELECT', ['setup' => [$a, $seed], 'check' => ["SELECT SUM(CASE WHEN val>25 THEN 1 ELSE 0 END) FROM {t}", '2'], 'run' => 'SELECT 1']],
            ['query:misc', 'IN list', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} WHERE val IN (10,20)', '2'], 'run' => 'SELECT 1']],
            ['query:misc', 'BETWEEN', ['setup' => [$a, $seed], 'check' => ['SELECT COUNT(*) FROM {t} WHERE val BETWEEN 15 AND 35', '2'], 'run' => 'SELECT 1']],
            ['query:misc', 'IS NULL / IS NOT NULL', ['setup' => [$a, "INSERT INTO {t} VALUES (5,NULL,NULL)", $seed], 'check' => ['SELECT COUNT(*) FROM {t} WHERE grp IS NULL', '1'], 'run' => 'SELECT 1']],
            ['query:misc', 'LIKE pattern', ['setup' => [$b, $seedB], 'check' => ["SELECT COUNT(*) FROM {t2} WHERE label LIKE 'x%'", '1'], 'run' => 'SELECT 1', 'tables' => 2]],
            ['query:misc', 'SELECT FOR UPDATE', ['setup' => [$a, $seed], 'run' => 'CREATE TABLE {t3} AS SELECT * FROM {t} WHERE id=1 FOR UPDATE', 'tables' => 3]],
            ['query:misc', 'window OVER PARTITION', ['setup' => [$a, $seed], 'run' => 'CREATE TABLE {t3} AS SELECT id, SUM(val) OVER (PARTITION BY grp) s FROM {t}', 'tables' => 3]],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    private static function constraints(Runner $r): void
    {
        // Constraint enforcement: each should *reject* the bad row.
        self::addReject($r, 'constraint:notnull', 'NOT NULL rejects NULL',
            ['CREATE TABLE {t} (id INT, n INT NOT NULL)'],
            'INSERT INTO {t}(id,n) VALUES (1,NULL)');
        self::addReject($r, 'constraint:unique', 'UNIQUE rejects duplicate',
            ['CREATE TABLE {t} (id INT, u INT UNIQUE)', 'INSERT INTO {t} VALUES (1,5)'],
            'INSERT INTO {t} VALUES (2,5)');
        self::addReject($r, 'constraint:pk', 'PRIMARY KEY rejects duplicate',
            ['CREATE TABLE {t} (id INT PRIMARY KEY)', 'INSERT INTO {t} VALUES (1)'],
            'INSERT INTO {t} VALUES (1)');
        self::addReject($r, 'constraint:check', 'CHECK rejects violation',
            ['CREATE TABLE {t} (id INT, age INT CHECK (age >= 0))'],
            'INSERT INTO {t} VALUES (1,-5)');
        self::addReject($r, 'constraint:fk', 'FOREIGN KEY rejects orphan',
            ['CREATE TABLE {t} (id INT PRIMARY KEY)', 'CREATE TABLE {t2} (id INT, fid INT, FOREIGN KEY(fid) REFERENCES {t}(id))'],
            'INSERT INTO {t2} VALUES (1,999)', 2);

        // Behaviors that should SUCCEED:
        $cases = [
            ['constraint:default', 'DEFAULT applied', ['setup' => ['CREATE TABLE {t} (id INT, n INT DEFAULT 42)', 'INSERT INTO {t}(id) VALUES (1)'], 'check' => ['SELECT n FROM {t}', '42'], 'run' => 'SELECT 1']],
            ['constraint:autoinc', 'AUTO_INCREMENT increments', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, n INT)', 'INSERT INTO {t}(n) VALUES (1),(2)'], 'check' => ['SELECT MAX(id) FROM {t}', '2'], 'run' => 'SELECT 1']],
            ['constraint:fk', 'FK CASCADE delete', ['setup' => ['CREATE TABLE {t} (id INT PRIMARY KEY)', 'CREATE TABLE {t2} (id INT, fid INT, FOREIGN KEY(fid) REFERENCES {t}(id) ON DELETE CASCADE)', 'INSERT INTO {t} VALUES (1)', 'INSERT INTO {t2} VALUES (1,1)', 'DELETE FROM {t} WHERE id=1'], 'check' => ['SELECT COUNT(*) FROM {t2}', '0'], 'run' => 'SELECT 1', 'tables' => 2]],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    private static function addReject(Runner $r, string $cat, string $name, array $setup, string $bad, int $tables = 1): void
    {
        $db = Config::database('pdo');
        $r->add('PDO', $cat, $name, function () use ($db, $setup, $bad, $tables) {
            $pdo = Connections::pdo($db);
            $names = [];
            for ($i = 1; $i <= max($tables, 2); $i++) {
                $names['{t' . ($i === 1 ? '' : $i) . '}'] = Support::name('c');
            }
            $sub = fn (string $s) => strtr($s, $names);
            try {
                foreach ($setup as $s) {
                    $pdo->exec($sub($s));
                }
                $rejected = false;
                try {
                    $pdo->exec($sub($bad));
                } catch (\PDOException) {
                    $rejected = true;
                }
                Support::assert($rejected, 'constraint was NOT enforced (bad row accepted)');
                return ['detail' => 'constraint enforced', 'sql' => $sub($bad)];
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

    private static function indexes(Runner $r): void
    {
        $cases = [
            ['index:create', 'simple index', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'CREATE INDEX idx ON {t}(c)']],
            ['index:create', 'composite index', ['setup' => ['CREATE TABLE {t} (a INT, b INT)'], 'run' => 'CREATE INDEX idx ON {t}(a,b)']],
            ['index:create', 'unique index', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'CREATE UNIQUE INDEX idx ON {t}(c)']],
            ['index:create', 'prefix index', ['setup' => ['CREATE TABLE {t} (id INT, c VARCHAR(100))'], 'run' => 'CREATE INDEX idx ON {t}(c(10))']],
            ['index:create', 'descending index', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'CREATE INDEX idx ON {t}(c DESC)']],
            ['index:create', 'index USING BTREE', ['setup' => ['CREATE TABLE {t} (id INT, c INT)'], 'run' => 'CREATE INDEX idx ON {t}(c) USING BTREE']],
            ['index:create', 'inline INDEX in CREATE', ['run' => 'CREATE TABLE {t} (id INT, c INT, INDEX(c))']],
            ['index:create', 'inline KEY in CREATE', ['run' => 'CREATE TABLE {t} (id INT, c INT, KEY k_c (c))']],
            ['index:create', 'FULLTEXT index (with PK)', ['run' => 'CREATE TABLE {t} (id INT PRIMARY KEY, txt TEXT, FULLTEXT(txt))']],
            ['index:create', 'spatial index', ['run' => 'CREATE TABLE {t} (id INT, g GEOMETRY NOT NULL, SPATIAL INDEX(g))']],
            ['index:create', 'vector IVFFLAT index', ['run' => 'CREATE TABLE {t} (id INT PRIMARY KEY, v VECF32(3))', 'tables' => 1]],
            ['index:drop', 'DROP INDEX', ['setup' => ['CREATE TABLE {t} (id INT, c INT, INDEX idx(c))'], 'run' => 'DROP INDEX idx ON {t}']],
        ];
        foreach ($cases as [$cat, $name, $opts]) {
            self::addCase($r, $cat, $name, $opts);
        }
    }

    private static function transactions(Runner $r): void
    {
        $db = Config::database('pdo');

        // Commit persists
        $r->add('PDO', 'transaction:commit', 'COMMIT persists changes', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->beginTransaction();
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $pdo->commit();
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('1', $c, 'commit count');
                return 'committed row visible';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Rollback reverts
        $r->add('PDO', 'transaction:rollback', 'ROLLBACK reverts changes', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->beginTransaction();
                $pdo->exec("INSERT INTO `$tn` VALUES (1)");
                $pdo->rollBack();
                $c = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('0', $c, 'rollback count');
                return 'rolled back row absent';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Savepoint
        $r->add('PDO', 'transaction:savepoint', 'SAVEPOINT + ROLLBACK TO', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx');
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
                try {
                    $pdo->exec('ROLLBACK');
                } catch (\Throwable) {
                }
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Isolation level set
        foreach (['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'] as $iso) {
            $r->add('PDO', 'transaction:isolation', "SET ISOLATION $iso", function () use ($db, $iso) {
                $pdo = Connections::pdo($db);
                $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL $iso");
                return "set $iso";
            });
        }

        // Autocommit toggling
        $r->add('PDO', 'transaction:autocommit', 'SET autocommit=0/1', function () use ($db) {
            $pdo = Connections::pdo($db);
            $pdo->exec('SET autocommit=0');
            $pdo->exec('SET autocommit=1');
            return 'toggled autocommit';
        });

        // Locking read syntaxes
        $r->add('PDO', 'transaction:lock', 'LOCK IN SHARE MODE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tx');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $pdo->exec("INSERT INTO `$tn` VALUES (1)");
            try {
                $pdo->query("SELECT * FROM `$tn` WHERE id=1 LOCK IN SHARE MODE")->fetchAll();
                return 'lock in share mode ok';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function introspection(Runner $r): void
    {
        $db = Config::database('pdo');
        $queries = [
            ['introspection', 'SHOW DATABASES', 'SHOW DATABASES'],
            ['introspection', 'SHOW TABLES', 'SHOW TABLES'],
            ['introspection', 'SHOW FULL TABLES', 'SHOW FULL TABLES'],
            ['introspection', 'SHOW TABLE STATUS', 'SHOW TABLE STATUS'],
            ['introspection', 'SHOW VARIABLES', 'SHOW VARIABLES LIKE "version"'],
            ['introspection', 'SHOW STATUS', 'SHOW STATUS'],
            ['introspection', 'SHOW ENGINES', 'SHOW ENGINES'],
            ['introspection', 'SHOW CHARACTER SET', 'SHOW CHARACTER SET'],
            ['introspection', 'SHOW COLLATION', 'SHOW COLLATION'],
            ['introspection', 'SHOW PROCESSLIST', 'SHOW PROCESSLIST'],
            ['introspection', 'SHOW GRANTS', 'SHOW GRANTS'],
            ['introspection', 'SHOW WARNINGS', 'SHOW WARNINGS'],
            ['introspection', 'information_schema.TABLES', 'SELECT COUNT(*) FROM information_schema.TABLES'],
            ['introspection', 'information_schema.COLUMNS', 'SELECT COUNT(*) FROM information_schema.COLUMNS'],
            ['introspection', 'information_schema.STATISTICS', 'SELECT COUNT(*) FROM information_schema.STATISTICS'],
            ['introspection', 'information_schema.KEY_COLUMN_USAGE', 'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE'],
            ['introspection', 'information_schema.TABLE_CONSTRAINTS', 'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS'],
            ['introspection', 'information_schema.SCHEMATA', 'SELECT COUNT(*) FROM information_schema.SCHEMATA'],
            ['introspection', 'information_schema.VIEWS', 'SELECT COUNT(*) FROM information_schema.VIEWS'],
            ['introspection', 'information_schema.REFERENTIAL_CONSTRAINTS', 'SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS'],
            ['introspection', 'SELECT @@version', 'SELECT @@version'],
            ['introspection', 'SELECT @@sql_mode', 'SELECT @@sql_mode'],
            ['introspection', 'SELECT @@max_allowed_packet', 'SELECT @@max_allowed_packet'],
        ];
        foreach ($queries as [$cat, $name, $sql]) {
            $r->add('PDO', $cat, $name, function () use ($db, $sql) {
                $pdo = Connections::pdo($db);
                $pdo->query($sql)->fetchAll();
                return ['detail' => 'ok', 'sql' => $sql];
            });
        }

        // SHOW CREATE TABLE / DESCRIBE / EXPLAIN need a table.
        $r->add('PDO', 'introspection', 'SHOW CREATE TABLE', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('i');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, c VARCHAR(10))");
            try {
                $row = $pdo->query("SHOW CREATE TABLE `$tn`")->fetch(\PDO::FETCH_NUM);
                Support::assert(is_array($row) && isset($row[1]), 'no create statement returned');
                return 'returned DDL';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection', 'DESCRIBE table', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('i');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, c VARCHAR(10))");
            try {
                $rows = $pdo->query("DESCRIBE `$tn`")->fetchAll();
                Support::assert(count($rows) === 2, 'expected 2 columns');
                return 'described 2 columns';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('PDO', 'introspection', 'EXPLAIN SELECT', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('i');
            $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $pdo->query("EXPLAIN SELECT * FROM `$tn`")->fetchAll();
                return 'explained';
            } finally {
                $pdo->exec("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }
}
