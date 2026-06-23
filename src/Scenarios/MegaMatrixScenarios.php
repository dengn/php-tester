<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * High-volume, generated coverage to broaden the surface:
 *  - operator grids (arithmetic / comparison / bitwise / logical over a mixed
 *    operand set) — systematically probes coercion, overflow and collation;
 *  - function-input variations (string / numeric / date / json / cast);
 *  - parametric query-workload shapes (filters / sort+paginate / group+having /
 *    window / join / analytics) run against a shared seeded fixture.
 *
 * Most expression cases assert only that MatrixOne executes them (a divergence
 * therefore surfaces as an error = a real finding); workload cases assert a
 * plausible result so wrong answers surface too.
 */
final class MegaMatrixScenarios
{
    private static bool $fixtureReady = false;

    public static function register(Runner $r): void
    {
        self::arithmeticGrid($r);
        self::comparisonGrid($r);
        self::bitwiseLogicalGrid($r);
        self::functionVariations($r);
        self::castMatrix($r);
        self::workloadMatrix($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    /** SELECT <expr>; pass if it runs (and equals $expect when given). */
    private static function addExpr(Runner $r, string $cat, string $name, string $expr, ?string $expect = null): void
    {
        $r->add('PDO', $cat, $name, function () use ($expr, $expect) {
            $pdo = Connections::pdo(self::db());
            $sql = "SELECT $expr AS v";
            $got = $pdo->query($sql)->fetchColumn();
            if ($expect !== null) {
                Support::assertValueEquals($expect, $got, $expr);
            }
            return ['detail' => 'ran', 'sql' => $sql];
        });
    }

    /** @return array<string,string> label => SQL literal */
    private static function operands(): array
    {
        return [
            'int0' => '0', 'int1' => '1', 'intNeg' => '-7', 'intBig' => '2147483647',
            'bigint' => '9223372036854775807', 'dec' => '12.50', 'decNeg' => '-3.14',
            'frac' => '0.001', 'numStr' => "'42'", 'mixStr' => "'10abc'",
            'str' => "'hello'", 'date' => "'2026-06-23'", 'dt' => "'2026-06-23 10:20:30'",
            'nul' => 'NULL', 'zeroStr' => "'0'", 'bigDec' => '99999999999999999999.99',
        ];
    }

    private static function arithmeticGrid(Runner $r): void
    {
        $ops = ['+' => 'add', '-' => 'sub', '*' => 'mul', '/' => 'div', 'DIV' => 'idiv', '%' => 'mod'];
        $ops2 = $ops;
        $vals = self::operands();
        foreach ($ops as $op => $opn) {
            foreach ($vals as $la => $a) {
                foreach ($vals as $lb => $b) {
                    self::addExpr($r, 'grid:arith', "$la $opn $lb", "$a $op $b");
                }
            }
        }
        unset($ops2);
    }

    private static function comparisonGrid(Runner $r): void
    {
        $ops = ['=' => 'eq', '<>' => 'ne', '<' => 'lt', '<=' => 'le', '>' => 'gt', '>=' => 'ge', '<=>' => 'nseq'];
        $vals = self::operands();
        foreach ($ops as $op => $opn) {
            foreach ($vals as $la => $a) {
                foreach ($vals as $lb => $b) {
                    self::addExpr($r, 'grid:compare', "$la $opn $lb", "$a $op $b");
                }
            }
        }
    }

    private static function bitwiseLogicalGrid(Runner $r): void
    {
        $ints = ['0', '1', '5', '255', '-1', '1024', '9223372036854775807'];
        foreach (['&' => 'and', '|' => 'or', '^' => 'xor', '<<' => 'shl', '>>' => 'shr'] as $op => $n) {
            foreach ($ints as $a) {
                foreach ($ints as $b) {
                    self::addExpr($r, 'grid:bitwise', "$a $n $b", "$a $op $b");
                }
            }
        }
        $bools = ['0', '1', 'NULL', '5', '-1'];
        foreach (['AND' => 'and', 'OR' => 'or', 'XOR' => 'xor'] as $op => $n) {
            foreach ($bools as $a) {
                foreach ($bools as $b) {
                    self::addExpr($r, 'grid:logical', "$a $n $b", "$a $op $b");
                }
            }
        }
        foreach ($bools as $a) {
            self::addExpr($r, 'grid:logical', "NOT $a", "NOT $a");
            self::addExpr($r, 'grid:bitwise', "~ $a", "~ $a");
        }
    }

    private static function functionVariations(Runner $r): void
    {
        $strInputs = ["'abc'", "''", "'Héllo Wörld'", "'  pad  '", "'a,b,c'", "'2026-06-23'", "'123.45'", "'😀emoji'"];
        $strFns = ['UPPER', 'LOWER', 'LENGTH', 'CHAR_LENGTH', 'REVERSE', 'TRIM', 'LTRIM', 'RTRIM', 'HEX', 'TO_BASE64', 'MD5', 'SHA1', 'SOUNDEX', 'ORD', 'ASCII', 'BIT_LENGTH', 'QUOTE', 'SPACE'];
        foreach ($strFns as $fn) {
            foreach ($strInputs as $i => $in) {
                self::addExpr($r, 'fn:string-var', "$fn #$i", "$fn($in)");
            }
        }
        // 2-arg string fns
        foreach (['LEFT', 'RIGHT', 'REPEAT'] as $fn) {
            foreach ($strInputs as $i => $in) {
                foreach (['0', '1', '3', '50'] as $n) {
                    self::addExpr($r, 'fn:string-var', "$fn #$i,$n", "$fn($in, $n)");
                }
            }
        }
        $numInputs = ['0', '1', '-1', '3.14159', '-2.5', '1000000', '0.0001', '255'];
        $numFns = ['ABS', 'CEIL', 'FLOOR', 'SIGN', 'SQRT', 'EXP', 'LN', 'LOG2', 'LOG10', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'DEGREES', 'RADIANS', 'CRC32', 'BIN', 'OCT'];
        foreach ($numFns as $fn) {
            foreach ($numInputs as $i => $in) {
                self::addExpr($r, 'fn:numeric-var', "$fn #$i", "$fn($in)");
            }
        }
        foreach (['ROUND', 'TRUNCATE'] as $fn) {
            foreach ($numInputs as $i => $in) {
                foreach (['-2', '0', '2', '4'] as $d) {
                    self::addExpr($r, 'fn:numeric-var', "$fn #$i,$d", "$fn($in, $d)");
                }
            }
        }
        $dateInputs = ["'2026-06-23'", "'2024-02-29'", "'2026-12-31 23:59:59'", "'2000-01-01'", "'2026-06-23 10:20:30.123456'"];
        $dateFns = ['YEAR', 'MONTH', 'DAY', 'HOUR', 'MINUTE', 'SECOND', 'QUARTER', 'WEEK', 'DAYOFWEEK', 'DAYOFYEAR', 'DAYNAME', 'MONTHNAME', 'LAST_DAY', 'TO_DAYS', 'WEEKDAY', 'DATE', 'TIME'];
        foreach ($dateFns as $fn) {
            foreach ($dateInputs as $i => $in) {
                self::addExpr($r, 'fn:date-var', "$fn #$i", "$fn($in)");
            }
        }
        foreach (['DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR', 'HOUR', 'MINUTE'] as $unit) {
            foreach ($dateInputs as $i => $in) {
                self::addExpr($r, 'fn:date-var', "DATE_ADD $unit #$i", "DATE_ADD($in, INTERVAL 3 $unit)");
                self::addExpr($r, 'fn:date-var', "DATE_SUB $unit #$i", "DATE_SUB($in, INTERVAL 3 $unit)");
            }
        }
        $fmts = ['%Y-%m-%d', '%H:%i:%s', '%W %M %Y', '%j', '%U', '%p %r', '%b %D'];
        foreach ($fmts as $f) {
            foreach ($dateInputs as $i => $in) {
                self::addExpr($r, 'fn:date-format', "fmt $f #$i", "DATE_FORMAT($in, '$f')");
            }
        }
        // json
        $jsonDocs = ["'{\"a\":1,\"b\":[2,3]}'", "'[1,2,3]'", "'{\"x\":{\"y\":\"z\"}}'", "'null'", "'\"s\"'"];
        $jsonFns = ['JSON_VALID', 'JSON_TYPE', 'JSON_LENGTH', 'JSON_KEYS', 'JSON_DEPTH', 'JSON_PRETTY'];
        foreach ($jsonFns as $fn) {
            foreach ($jsonDocs as $i => $d) {
                self::addExpr($r, 'fn:json-var', "$fn #$i", "$fn($d)");
            }
        }
        foreach (["'$.a'", "'$.b[0]'", "'$.x.y'", "'$[1]'"] as $path) {
            foreach ($jsonDocs as $i => $d) {
                self::addExpr($r, 'fn:json-var', "JSON_EXTRACT $path #$i", "JSON_EXTRACT($d, $path)");
            }
        }
    }

    private static function castMatrix(Runner $r): void
    {
        $targets = ['SIGNED', 'UNSIGNED', 'CHAR', 'CHAR(5)', 'DECIMAL(10,2)', 'DECIMAL(20,4)', 'DATE', 'DATETIME', 'TIME', 'DOUBLE', 'FLOAT', 'BINARY', 'JSON', 'NCHAR'];
        $sources = ["'42'", "'-3.14'", "'2026-06-23'", "'2026-06-23 10:20:30'", "'10:20:30'", "'abc'", '255', '3.14159', "'[1,2,3]'", 'NULL'];
        foreach ($targets as $t) {
            foreach ($sources as $i => $s) {
                self::addExpr($r, 'cast:matrix', "CAST $s AS $t", "CAST($s AS $t)");
                self::addExpr($r, 'cast:matrix', "CONVERT $s,$t", "CONVERT($s, $t)");
            }
        }
    }

    /** Ensure a shared seeded fixture exists (created once, read-only thereafter). */
    private static function ensureFixture(\PDO $pdo): void
    {
        if (self::$fixtureReady) {
            return;
        }
        $pdo->exec('DROP TABLE IF EXISTS qw_orders');
        $pdo->exec('DROP TABLE IF EXISTS qw_users');
        $pdo->exec('CREATE TABLE qw_users (id INT PRIMARY KEY, name VARCHAR(40), region VARCHAR(10), tier INT)');
        $pdo->exec('CREATE TABLE qw_orders (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, product VARCHAR(20), qty INT, price DECIMAL(10,2), status VARCHAR(12), region VARCHAR(10), created DATETIME)');
        $regions = ['NA', 'EU', 'APAC', 'LATAM'];
        $status = ['new', 'paid', 'shipped', 'cancelled', 'refunded'];
        $prods = ['widget', 'gadget', 'gizmo', 'doohickey', 'thingamajig'];
        $uvals = [];
        for ($i = 1; $i <= 200; $i++) {
            $uvals[] = sprintf("(%d,'user%d','%s',%d)", $i, $i, $regions[$i % 4], ($i % 3) + 1);
        }
        $pdo->exec('INSERT INTO qw_users VALUES ' . implode(',', $uvals));
        for ($b = 0; $b < 15; $b++) {
            $ovals = [];
            for ($i = 1; $i <= 100; $i++) {
                $n = $b * 100 + $i;
                $ovals[] = sprintf(
                    "(%d,'%s',%d,%.2f,'%s','%s','2026-%02d-%02d %02d:00:00')",
                    ($n % 200) + 1,
                    $prods[$n % 5],
                    ($n % 10) + 1,
                    ($n % 1000) + 0.99,
                    $status[$n % 5],
                    $regions[$n % 4],
                    ($n % 12) + 1,
                    ($n % 27) + 1,
                    ($n % 24)
                );
            }
            $pdo->exec('INSERT INTO qw_orders(user_id,product,qty,price,status,region,created) VALUES ' . implode(',', $ovals));
        }
        self::$fixtureReady = true;
    }

    /** Run a read query against the shared fixture; assert it returns a non-negative scalar. */
    private static function addQuery(Runner $r, string $cat, string $name, string $sql): void
    {
        $r->add('PDO', $cat, $name, function () use ($sql) {
            $pdo = Connections::pdo(self::db());
            self::ensureFixture($pdo);
            $v = $pdo->query($sql)->fetchColumn();
            Support::assert($v !== false, 'query returned no row');
            return ['detail' => "= $v", 'sql' => $sql];
        });
    }

    private static function workloadMatrix(Runner $r): void
    {
        $cols = ['qty', 'price', 'status', 'region', 'product', 'user_id'];
        $ops = ['=', '<>', '<', '<=', '>', '>=', 'LIKE', 'IN'];
        $vals = [
            'qty' => ['5', '0', '11'], 'price' => ['100.00', '0.99', '500'],
            'status' => ["'paid'", "'PAID'", "'unknown'"], 'region' => ["'EU'", "'eu'", "'NA'"],
            'product' => ["'widget'", "'WIDGET'", "'w%'"], 'user_id' => ['1', '100', '999'],
        ];
        foreach ($cols as $c) {
            foreach ($ops as $op) {
                foreach ($vals[$c] as $v) {
                    if ($op === 'IN') {
                        $expr = "$c IN ($v, $v)";
                    } elseif ($op === 'LIKE') {
                        $expr = "$c LIKE $v";
                    } else {
                        $expr = "$c $op $v";
                    }
                    self::addQuery($r, 'workload:filter', "$c $op $v", "SELECT COUNT(*) FROM qw_orders WHERE $expr");
                }
            }
        }
        // multi-condition
        foreach (['AND', 'OR'] as $bool) {
            foreach ($cols as $c1) {
                foreach (array_slice($cols, 0, 4) as $c2) {
                    if ($c1 === $c2) {
                        continue;
                    }
                    self::addQuery($r, 'workload:multi', "$c1 $bool $c2", "SELECT COUNT(*) FROM qw_orders WHERE $c1 IS NOT NULL $bool $c2 IS NOT NULL");
                }
            }
        }
        // sort + paginate
        foreach ($cols as $c) {
            foreach (['ASC', 'DESC'] as $dir) {
                foreach (['LIMIT 10', 'LIMIT 10 OFFSET 50', 'LIMIT 1 OFFSET 999', 'LIMIT 100 OFFSET 1400'] as $pg) {
                    self::addQuery($r, 'workload:sort', "$c $dir $pg", "SELECT COUNT(*) FROM (SELECT id FROM qw_orders ORDER BY $c $dir $pg) z");
                }
            }
        }
        // group + aggregate + having
        $aggs = ['COUNT(*)', 'SUM(price)', 'AVG(qty)', 'MIN(price)', 'MAX(qty)', 'COUNT(DISTINCT user_id)'];
        foreach (['region', 'status', 'product', 'qty'] as $g) {
            foreach ($aggs as $a) {
                self::addQuery($r, 'workload:group', "$g $a", "SELECT COUNT(*) FROM (SELECT $g, $a m FROM qw_orders GROUP BY $g) z");
                self::addQuery($r, 'workload:group', "$g $a having", "SELECT COUNT(*) FROM (SELECT $g, $a m FROM qw_orders GROUP BY $g HAVING $a > 0) z");
            }
        }
        // window functions
        $wins = ['ROW_NUMBER()', 'RANK()', 'DENSE_RANK()', 'SUM(price)', 'AVG(qty)', 'LAG(price)', 'LEAD(qty)', 'NTILE(4)'];
        foreach ($wins as $w) {
            foreach (['region', 'status', 'product'] as $part) {
                self::addQuery($r, 'workload:window', "$w over $part", "SELECT COUNT(*) FROM (SELECT $w OVER (PARTITION BY $part ORDER BY id) wv FROM qw_orders) z");
            }
        }
        // joins
        foreach (['JOIN', 'LEFT JOIN', 'RIGHT JOIN'] as $jt) {
            foreach (['o.user_id=u.id', 'o.region=u.region'] as $on) {
                foreach (['', "WHERE u.tier=1", "WHERE o.status='paid'"] as $w) {
                    self::addQuery($r, 'workload:join', "$jt $on $w", "SELECT COUNT(*) FROM qw_orders o $jt qw_users u ON $on $w");
                }
            }
        }
        // analytics
        foreach (['region', 'status', 'product'] as $g) {
            self::addQuery($r, 'workload:analytics', "rollup $g", "SELECT COUNT(*) FROM (SELECT $g, SUM(price) FROM qw_orders GROUP BY $g WITH ROLLUP) z");
            self::addQuery($r, 'workload:analytics', "distinct $g", "SELECT COUNT(DISTINCT $g) FROM qw_orders");
            self::addQuery($r, 'workload:analytics', "month bucket $g", "SELECT COUNT(*) FROM (SELECT MONTH(created) m, $g, SUM(price) FROM qw_orders GROUP BY MONTH(created), $g) z");
        }
        // subqueries
        foreach (['region', 'status', 'product'] as $g) {
            self::addQuery($r, 'workload:subquery', "in $g", "SELECT COUNT(*) FROM qw_orders WHERE $g IN (SELECT $g FROM qw_orders WHERE price > 100)");
            self::addQuery($r, 'workload:subquery', "exists $g", "SELECT COUNT(*) FROM qw_orders o WHERE EXISTS (SELECT 1 FROM qw_users u WHERE u.id=o.user_id AND u.region=o.region)");
            self::addQuery($r, 'workload:subquery', "scalar $g", "SELECT COUNT(*) FROM qw_orders WHERE price > (SELECT AVG(price) FROM qw_orders)");
        }
    }
}
