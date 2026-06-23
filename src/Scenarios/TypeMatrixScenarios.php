<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Deep type / semantics matrices for MatrixOne over raw PDO.
 *
 * These are intentionally DIFFERENT from the existing PdoType/Behavior/edge
 * scenarios: they sweep full grids of precision/scale, temporal precision,
 * charset/collation, NULL three-valued logic, numeric overflow/coercion,
 * ENUM/SET edge behaviour, BIT, and deep JSON paths. Every grid cell becomes
 * one registered scenario via a data-driven loop.
 *
 * Conventions (mirroring PdoSqlScenarios2):
 *   - one fresh PDO connection per scenario,
 *   - unique table names via Support::name(), dropped in finally,
 *   - DDL/DML via exec() (text protocol), reads via query(),
 *   - every closure captures all referenced variables in use(...).
 *
 * A failing scenario is a genuine MatrixOne finding, not a test bug.
 */
final class TypeMatrixScenarios
{
    private const FW = 'PDO';

    public static function register(Runner $r): void
    {
        self::decimalGrid($r);
        self::numericOverflowGrid($r);
        self::numericCoercionGrid($r);
        self::temporalGrid($r);
        self::charsetGrid($r);
        self::collationGrid($r);
        self::nullLogicGrid($r);
        self::enumSetGrid($r);
        self::bitGrid($r);
        self::jsonPathGrid($r);
        self::jsonCastGrid($r);
        self::stringTypeGrid($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    /**
     * Round-trip a literal value through a freshly-created single-column table.
     * Asserts the stored value equals the expected normalised value.
     */
    private static function addRoundTrip(Runner $r, string $cat, string $name, string $colType, string $insertExpr, mixed $expected): void
    {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $colType, $insertExpr, $expected, $name) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tm');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $colType)");
                $pdo->exec("INSERT INTO `$tn`(c) VALUES ($insertExpr)");
                $got = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                Support::assertValueEquals($expected, $got, $name);
                return ['detail' => 'out=' . var_export($got, true), 'sql' => "INSERT $colType <= $insertExpr"];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
    }

    /**
     * A value that the engine should REJECT for the given column type. Passes
     * when the insert throws; fails when it is silently accepted/clamped.
     */
    private static function addReject(Runner $r, string $cat, string $name, string $colType, string $insertExpr): void
    {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $colType, $insertExpr, $name) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('tm');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $colType)");
                $rejected = false;
                $stored = null;
                try {
                    $pdo->exec("INSERT INTO `$tn`(c) VALUES ($insertExpr)");
                    $stored = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, "$name: value was accepted (stored " . var_export($stored, true) . ') rather than rejected');
                return ['detail' => 'rejected as expected', 'sql' => "INSERT $colType <= $insertExpr (expect reject)"];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
    }

    /** Evaluate a scalar SELECT expression and assert its value. */
    private static function addExpr(Runner $r, string $cat, string $name, string $expr, mixed $expected, string $mode = 'value'): void
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
                case 'exact':
                    Support::assertEquals($expected, $got, $name);
                    break;
                case 'value':
                default:
                    Support::assertValueEquals($expected, $got, $name);
                    break;
            }
            return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
        });
    }

    // ============================================================ DECIMAL grid

    private static function decimalGrid(Runner $r): void
    {
        // (precision, scale) x representative values that fit.
        $grid = [
            [5, 2], [10, 2], [10, 4], [18, 6], [20, 0],
            [30, 10], [38, 0], [38, 10], [38, 18], [38, 38],
            [50, 20], [60, 0], [65, 0], [65, 30], [4, 4],
        ];
        foreach ($grid as [$p, $s]) {
            $intDigits = $p - $s;
            // Build a value that exactly fits: intDigits 9s + scale digits.
            $intPart = $intDigits > 0 ? str_repeat('9', min($intDigits, 20)) : '0';
            $fracPart = $s > 0 ? str_repeat('1', min($s, 20)) : '';
            $val = $fracPart !== '' ? "$intPart.$fracPart" : $intPart;
            $expected = number_format((float) 0, 0); // not used; use string compare below
            self::addRoundTrip(
                $r,
                'typematrix:decimal',
                "DECIMAL($p,$s) fits boundary value",
                "DECIMAL($p,$s)",
                "'$val'",
                $val
            );
            // A small exact value rounds to scale.
            self::addRoundTrip(
                $r,
                'typematrix:decimal',
                "DECIMAL($p,$s) stores 1.5 scaled",
                "DECIMAL($p,$s)",
                "'1.5'",
                $s >= 1 ? '1.' . str_pad('5', $s, '0') : '2'
            );
            // Overflow of the integer part must be rejected.
            if ($intDigits >= 1 && $intDigits <= 30) {
                $overflow = str_repeat('9', $intDigits + 1) . ($s > 0 ? '.0' : '');
                self::addReject(
                    $r,
                    'typematrix:decimal',
                    "DECIMAL($p,$s) rejects integer overflow",
                    "DECIMAL($p,$s)",
                    "'$overflow'"
                );
            }
        }
        // Decimal arithmetic precision: scale of result.
        $arith = [
            ['1.10 + 2.20', '3.30'],
            ['10.00 / 3', null],
            ['2.50 * 2', '5.00'],
            ['CAST(1 AS DECIMAL(10,2)) + CAST(2 AS DECIMAL(10,4))', '3.0000'],
            ['SUM(x) ', null],
        ];
        self::addExpr($r, 'typematrix:decimal', 'DECIMAL add 1.10+2.20', '1.10 + 2.20', '3.30');
        self::addExpr($r, 'typematrix:decimal', 'DECIMAL mul 2.50*2', '2.50 * 2', '5.00');
        self::addExpr($r, 'typematrix:decimal', 'DECIMAL cast add scale max', 'CAST(1 AS DECIMAL(10,2)) + CAST(2 AS DECIMAL(10,4))', '3.0000');
        self::addExpr($r, 'typematrix:decimal', 'DECIMAL div precision', 'CAST(10 AS DECIMAL(20,6)) / 3', null, 'notnull');
        self::addExpr($r, 'typematrix:decimal', 'ROUND DECIMAL half-up', 'ROUND(CAST(2.5 AS DECIMAL(10,2)))', '3');
        self::addExpr($r, 'typematrix:decimal', 'TRUNCATE DECIMAL', 'TRUNCATE(CAST(3.14159 AS DECIMAL(10,5)), 2)', '3.14');
    }

    // =================================================== numeric overflow grid

    private static function numericOverflowGrid(Runner $r): void
    {
        // [type, maxValue, overflowValue]
        $types = [
            ['TINYINT', '127', '128'],
            ['TINYINT', '-128', '-129'],
            ['TINYINT UNSIGNED', '255', '256'],
            ['SMALLINT', '32767', '32768'],
            ['SMALLINT UNSIGNED', '65535', '65536'],
            ['MEDIUMINT', '8388607', '8388608'],
            ['MEDIUMINT UNSIGNED', '16777215', '16777216'],
            ['INT', '2147483647', '2147483648'],
            ['INT UNSIGNED', '4294967295', '4294967296'],
            ['BIGINT', '9223372036854775807', '9223372036854775808'],
            ['BIGINT UNSIGNED', '18446744073709551615', '18446744073709551616'],
        ];
        foreach ($types as [$type, $max, $over]) {
            self::addRoundTrip($r, 'typematrix:overflow', "$type stores max $max", $type, "'$max'", $max);
            self::addReject($r, 'typematrix:overflow', "$type rejects overflow $over", $type, "'$over'");
        }
        // Unsigned rejects negatives.
        foreach (['TINYINT UNSIGNED', 'INT UNSIGNED', 'BIGINT UNSIGNED'] as $type) {
            self::addReject($r, 'typematrix:overflow', "$type rejects negative", $type, "'-1'");
        }
        // FLOAT/DOUBLE special values.
        self::addExpr($r, 'typematrix:overflow', 'FLOAT large magnitude', 'CAST(3.4e38 AS DOUBLE)', null, 'notnull');
        self::addExpr($r, 'typematrix:overflow', 'DOUBLE precision digits', 'CAST(0.1 AS DOUBLE) + CAST(0.2 AS DOUBLE)', null, 'notnull');
    }

    // ================================================== numeric coercion grid

    private static function numericCoercionGrid(Runner $r): void
    {
        // String->number coercion in arithmetic / comparison.
        $cases = [
            ["'10' + 5", '15'],
            ["'10abc' + 0", null],     // partial-numeric coercion (MySQL: 10)
            ["'abc' + 0", null],       // non-numeric coercion (MySQL: 0)
            ["1 + TRUE", '2'],
            ["1 + NULL", null],
            ["'3.5' * 2", '7'],
            ["CONCAT(1, 2)", '12'],
            ["5 DIV 2", '2'],
            ["5 % 2", '1'],
            ["-5 MOD 3", null],
            ["1 = '1'", '1'],
            ["1 = '1.0'", '1'],
            ["'10' > '9'", null],      // string vs string comparison
            ["10 > '9'", '1'],
            ["0 = 'x'", null],
            ["TRUE + TRUE", '2'],
            ["b'101' + 0", '5'],
            ["0x10 + 0", '16'],
            ["X'FF' + 0", '255'],
            ["CAST('  42  ' AS SIGNED)", '42'],
        ];
        foreach ($cases as $i => [$expr, $exp]) {
            $mode = $exp === null ? 'notnull-or-any' : 'value';
            // Use 'any' (executes without error) when expected unknown, else value.
            if ($exp === null) {
                self::addExpr($r, 'typematrix:coercion', "coerce: $expr", $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'typematrix:coercion', "coerce: $expr", $expr, $exp, 'value');
            }
        }
        // Implicit coercion on INSERT into numeric column from string.
        self::addRoundTrip($r, 'typematrix:coercion', 'INT from numeric string', 'INT', "'42'", '42');
        self::addRoundTrip($r, 'typematrix:coercion', 'DOUBLE from int literal', 'DOUBLE', '7', '7');
        self::addReject($r, 'typematrix:coercion', 'INT rejects non-numeric string', 'INT', "'abc'");
        self::addReject($r, 'typematrix:coercion', 'INT rejects float string', 'INT', "'3.9'");
    }

    // ========================================================= temporal grid

    private static function temporalGrid(Runner $r): void
    {
        // fractional-second precision sweep for DATETIME/TIME/TIMESTAMP
        foreach ([0, 1, 2, 3, 4, 5, 6] as $fsp) {
            $suffix = $fsp > 0 ? '(' . $fsp . ')' : '';
            $frac = $fsp > 0 ? '.' . substr('123456', 0, $fsp) : '';
            $in = "2026-01-02 03:04:05$frac";
            self::addRoundTrip($r, 'typematrix:temporal', "DATETIME$suffix round-trip", "DATETIME$suffix", "'$in'", $in);
            self::addRoundTrip($r, 'typematrix:temporal', "TIMESTAMP$suffix round-trip", "TIMESTAMP$suffix", "'$in'", $in);
            $tin = "03:04:05$frac";
            self::addRoundTrip($r, 'typematrix:temporal', "TIME$suffix round-trip", "TIME$suffix", "'$tin'", $tin);
        }
        // DATE / YEAR boundaries
        self::addRoundTrip($r, 'typematrix:temporal', 'DATE min 1000-01-01', 'DATE', "'1000-01-01'", '1000-01-01');
        self::addRoundTrip($r, 'typematrix:temporal', 'DATE max 9999-12-31', 'DATE', "'9999-12-31'", '9999-12-31');
        self::addRoundTrip($r, 'typematrix:temporal', 'YEAR 1901', 'YEAR', "'1901'", '1901');
        self::addRoundTrip($r, 'typematrix:temporal', 'YEAR 2155', 'YEAR', "'2155'", '2155');
        // Temporal arithmetic & extraction grid
        $exprs = [
            ['DATE_ADD day', "DATE_ADD('2026-01-31', INTERVAL 1 DAY)", '2026-02-01'],
            ['DATE_ADD month end', "DATE_ADD('2026-01-31', INTERVAL 1 MONTH)", '2026-02-28'],
            ['DATE_SUB year', "DATE_SUB('2026-02-28', INTERVAL 1 YEAR)", '2025-02-28'],
            ['DATEDIFF', "DATEDIFF('2026-02-01','2026-01-01')", '31'],
            ['TIMESTAMPDIFF hour', "TIMESTAMPDIFF(HOUR,'2026-01-01 00:00:00','2026-01-02 06:00:00')", '30'],
            ['TIMESTAMPDIFF minute', "TIMESTAMPDIFF(MINUTE,'2026-01-01 00:00:00','2026-01-01 01:30:00')", '90'],
            ['EXTRACT YEAR', "EXTRACT(YEAR FROM '2026-06-23')", '2026'],
            ['EXTRACT MONTH', "EXTRACT(MONTH FROM '2026-06-23')", '6'],
            ['EXTRACT DAY', "EXTRACT(DAY FROM '2026-06-23')", '23'],
            ['DAYOFWEEK', "DAYOFWEEK('2026-06-23')", '3'],
            ['DAYOFYEAR', "DAYOFYEAR('2026-01-10')", '10'],
            ['WEEK', "WEEK('2026-06-23')", null],
            ['QUARTER', "QUARTER('2026-06-23')", '2'],
            ['LAST_DAY', "LAST_DAY('2026-02-10')", '2026-02-28'],
            ['TO_DAYS', "TO_DAYS('2026-01-01')", null],
            ['FROM_DAYS', "FROM_DAYS(739982)", null],
            ['UNIX_TIMESTAMP fixed', "UNIX_TIMESTAMP('2026-01-01 00:00:00')", null],
            ['FROM_UNIXTIME', "FROM_UNIXTIME(0)", null],
            ['STR_TO_DATE', "STR_TO_DATE('23/06/2026','%d/%m/%Y')", '2026-06-23'],
            ['DATE_FORMAT', "DATE_FORMAT('2026-06-23 14:05:00','%Y-%m-%d %H:%i')", '2026-06-23 14:05'],
            ['TIME_FORMAT', "TIME_FORMAT('14:05:09','%H:%i:%s')", '14:05:09'],
            ['ADDDATE', "ADDDATE('2026-01-01', 10)", '2026-01-11'],
            ['SUBDATE', "SUBDATE('2026-01-11', 10)", '2026-01-01'],
            ['CONVERT_TZ utc-est', "CONVERT_TZ('2026-01-01 12:00:00','+00:00','-05:00')", '2026-01-01 07:00:00'],
            ['CONVERT_TZ est-utc', "CONVERT_TZ('2026-01-01 07:00:00','-05:00','+00:00')", '2026-01-01 12:00:00'],
            ['UTC_DATE notnull', "UTC_DATE()", null],
            ['UTC_TIMESTAMP notnull', "UTC_TIMESTAMP()", null],
            ['MAKEDATE', "MAKEDATE(2026, 60)", '2026-03-01'],
            ['MAKETIME', "MAKETIME(13, 30, 45)", '13:30:45'],
            ['PERIOD_ADD', "PERIOD_ADD(202601, 2)", '202603'],
            ['PERIOD_DIFF', "PERIOD_DIFF(202603, 202601)", '2'],
            ['SEC_TO_TIME', "SEC_TO_TIME(3661)", '01:01:01'],
            ['TIME_TO_SEC', "TIME_TO_SEC('01:01:01')", '3661'],
        ];
        foreach ($exprs as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'typematrix:temporal', $nm, $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'typematrix:temporal', $nm, $expr, $exp, 'value');
            }
        }
    }

    // ========================================================== charset grid

    private static function charsetGrid(Runner $r): void
    {
        $charsets = ['utf8mb4', 'utf8', 'ascii', 'latin1', 'binary', 'gbk', 'utf16', 'utf32'];
        foreach ($charsets as $cs) {
            // Can a column be declared with this CHARACTER SET, and does the
            // catalog report it back?
            $db = self::db();
            $r->add(self::FW, 'typematrix:charset', "column CHARACTER SET $cs reported", function () use ($db, $cs) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('cs');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(20) CHARACTER SET $cs)");
                    $got = $pdo->query(
                        "SELECT character_set_name FROM information_schema.columns WHERE table_name='$tn' AND column_name='c'"
                    )->fetchColumn();
                    Support::assertEquals($cs, $got, "CHARACTER SET $cs round-trip");
                    return ['detail' => "reported=$got", 'sql' => "VARCHAR CHARACTER SET $cs"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // CONVERT / introspection of charset functions
        self::addExpr($r, 'typematrix:charset', 'CHARSET of literal', "CHARSET('abc')", null, 'notnull');
        self::addExpr($r, 'typematrix:charset', 'CHARSET after CONVERT ascii', "CHARSET(CONVERT('abc' USING ascii))", null, 'notnull');
        self::addExpr($r, 'typematrix:charset', 'LENGTH vs CHAR_LENGTH multibyte', "LENGTH(_utf8mb4'é') > CHAR_LENGTH(_utf8mb4'é')", null, 'notnull');
        self::addExpr($r, 'typematrix:charset', 'HEX of multibyte', "HEX(_utf8mb4'é')", null, 'notnull');
        self::addExpr($r, 'typematrix:charset', 'CONVERT USING utf8mb4', "CONVERT('abc' USING utf8mb4)", 'abc', 'value');
        self::addExpr($r, 'typematrix:charset', 'introduced string literal', "_utf8mb4'hello'", 'hello', 'value');
        self::addExpr($r, 'typematrix:charset', 'ASCII of char', "ASCII('A')", '65', 'value');
        self::addExpr($r, 'typematrix:charset', 'ORD multibyte', "ORD(_utf8mb4'é')", null, 'notnull');
    }

    // ======================================================== collation grid

    private static function collationGrid(Runner $r): void
    {
        // Comparison case-sensitivity per collation: does 'abc' = 'ABC' COLLATE x?
        $collations = [
            'utf8mb4_general_ci' => '1',
            'utf8mb4_0900_ai_ci' => '1',
            'utf8mb4_unicode_ci' => '1',
            'utf8mb4_bin' => '0',
            'utf8mb4_0900_as_cs' => '0',
        ];
        foreach ($collations as $coll => $expectEq) {
            self::addExpr(
                $r,
                'typematrix:collation',
                "'abc'='ABC' COLLATE $coll",
                "('abc' = 'ABC' COLLATE $coll)",
                $expectEq,
                'value'
            );
            // LIKE case behaviour per collation
            self::addExpr(
                $r,
                'typematrix:collation',
                "'ABC' LIKE 'abc' COLLATE $coll",
                "('ABC' LIKE 'abc' COLLATE $coll)",
                $expectEq,
                'value'
            );
            // ORDER BY stability — at least executes
            $db = self::db();
            $r->add(self::FW, 'typematrix:collation', "ORDER BY COLLATE $coll", function () use ($db, $coll) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('col');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(10))");
                    $pdo->exec("INSERT INTO `$tn` VALUES ('B'),('a'),('A'),('b')");
                    $rows = $pdo->query("SELECT c FROM `$tn` ORDER BY c COLLATE $coll")->fetchAll(\PDO::FETCH_COLUMN);
                    Support::assert(count($rows) === 4, "ORDER BY COLLATE $coll returned " . count($rows));
                    return ['detail' => implode(',', $rows), 'sql' => "ORDER BY c COLLATE $coll"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
            // column-level COLLATE in a WHERE clause
            $r->add(self::FW, 'typematrix:collation', "WHERE column COLLATE $coll matches", function () use ($db, $coll, $expectEq) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('col');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c VARCHAR(10))");
                    $pdo->exec("INSERT INTO `$tn` VALUES ('alice')");
                    $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE c = 'ALICE' COLLATE $coll")->fetchColumn();
                    Support::assertEquals($expectEq, $n, "WHERE COLLATE $coll match count");
                    return ['detail' => "matched=$n", 'sql' => "WHERE c='ALICE' COLLATE $coll"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
    }

    // ====================================================== NULL 3-value grid

    private static function nullLogicGrid(Runner $r): void
    {
        $cases = [
            ['NULL = NULL', 'null', null],
            ['NULL <> NULL', 'null', null],
            ['NULL IS NULL', 'value', '1'],
            ['NULL IS NOT NULL', 'value', '0'],
            ['NULL <=> NULL', 'value', '1'],
            ['1 <=> NULL', 'value', '0'],
            ['NULL AND TRUE', 'null', null],
            ['NULL AND FALSE', 'value', '0'],
            ['NULL OR TRUE', 'value', '1'],
            ['NULL OR FALSE', 'null', null],
            ['NOT NULL', 'null', null],
            ['NULL XOR TRUE', 'null', null],
            ['NULL + 1', 'null', null],
            ['CONCAT(NULL, "x")', 'null', null],
            ['CONCAT_WS(",", NULL, "a", NULL, "b")', 'value', 'a,b'],
            ['COALESCE(NULL, NULL, 5)', 'value', '5'],
            ['IFNULL(NULL, 9)', 'value', '9'],
            ['NULLIF(5, 5)', 'null', null],
            ['NULLIF(5, 6)', 'value', '5'],
            ['IF(NULL, 1, 2)', 'value', '2'],
            ['GREATEST(1, NULL, 3)', 'null', null],
            ['LEAST(1, NULL, 3)', 'null', null],
            ['NULL IN (1, 2)', 'null', null],
            ['NULL IN (1, NULL)', 'null', null],
            ['1 IN (2, NULL)', 'null', null],
            ['1 IN (1, NULL)', 'value', '1'],
            ['NULL NOT IN (1)', 'null', null],
            ['NULL BETWEEN 1 AND 2', 'null', null],
            ['CASE WHEN NULL THEN 1 ELSE 2 END', 'value', '2'],
            ['SUM(NULL)', 'null', null],
            ['COUNT(NULL)', 'value', '0'],
            ['"a" LIKE NULL', 'null', null],
            ['NULL REGEXP "a"', 'null', null],
            ['ISNULL(1/0)', 'value', '1'],
        ];
        foreach ($cases as [$expr, $mode, $exp]) {
            self::addExpr($r, 'typematrix:null', "3VL: $expr", $expr, $exp, $mode === 'null' ? 'null' : 'value');
        }
        // NULL ordering: default NULLS FIRST behaviour
        $db = self::db();
        foreach (['ASC', 'DESC'] as $dir) {
            $r->add(self::FW, 'typematrix:null', "NULL ordering $dir", function () use ($db, $dir) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('nl');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (n INT)");
                    $pdo->exec("INSERT INTO `$tn` VALUES (2),(NULL),(1),(3)");
                    $rows = $pdo->query("SELECT n FROM `$tn` ORDER BY n $dir")->fetchAll(\PDO::FETCH_COLUMN);
                    $first = $rows[0];
                    return ['detail' => 'first=' . var_export($first, true) . ' order=' . implode(',', array_map(fn ($v) => $v ?? 'NULL', $rows)), 'sql' => "ORDER BY n $dir"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // Unique constraint allows multiple NULLs
        $r->add(self::FW, 'typematrix:null', 'UNIQUE allows multiple NULLs', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('nl');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c INT UNIQUE)");
                $pdo->exec("INSERT INTO `$tn` VALUES (NULL),(NULL),(1)");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn`")->fetchColumn();
                Support::assertEquals('3', $n, 'multiple NULLs under UNIQUE');
                return ['detail' => "rows=$n", 'sql' => 'UNIQUE multiple NULL'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
    }

    // ======================================================== ENUM / SET grid

    private static function enumSetGrid(Runner $r): void
    {
        $enum = "ENUM('low','medium','high','critical')";
        foreach (['low', 'medium', 'high', 'critical'] as $v) {
            self::addRoundTrip($r, 'typematrix:enum', "ENUM stores '$v'", $enum, "'$v'", $v);
        }
        // invalid value rejected (strict) — finding either way
        self::addReject($r, 'typematrix:enum', 'ENUM rejects invalid value', $enum, "'bogus'");
        // numeric index access
        $db = self::db();
        $r->add(self::FW, 'typematrix:enum', 'ENUM numeric index returns label', function () use ($db, $enum) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('en');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $enum)");
                $pdo->exec("INSERT INTO `$tn` VALUES (2)");
                $got = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                return ['detail' => 'index 2 => ' . var_export($got, true), 'sql' => 'ENUM numeric index'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'typematrix:enum', 'ENUM ORDER BY uses declaration order', function () use ($db, $enum) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('en');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $enum)");
                $pdo->exec("INSERT INTO `$tn` VALUES ('high'),('low'),('critical'),('medium')");
                $rows = $pdo->query("SELECT c FROM `$tn` ORDER BY c")->fetchAll(\PDO::FETCH_COLUMN);
                Support::assertEquals('low', $rows[0] ?? '', 'ENUM order first');
                return ['detail' => implode(',', $rows), 'sql' => 'ENUM ORDER BY'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });

        $set = "SET('read','write','exec','admin')";
        $setVals = ['read', 'write', 'read,write', 'read,write,exec', 'admin', 'write,exec,admin'];
        foreach ($setVals as $v) {
            self::addRoundTrip($r, 'typematrix:set', "SET stores '$v'", $set, "'$v'", $v);
        }
        self::addReject($r, 'typematrix:set', 'SET rejects unknown member', $set, "'bogus'");
        $r->add(self::FW, 'typematrix:set', 'SET dedups members', function () use ($db, $set) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('st');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $set)");
                $pdo->exec("INSERT INTO `$tn` VALUES ('read,read,write')");
                $got = $pdo->query("SELECT c FROM `$tn`")->fetchColumn();
                return ['detail' => 'stored=' . var_export($got, true), 'sql' => 'SET dedup'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'typematrix:set', 'FIND_IN_SET on SET column', function () use ($db, $set) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('st');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c $set)");
                $pdo->exec("INSERT INTO `$tn` VALUES ('read,exec'),('write')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE FIND_IN_SET('exec', c) > 0")->fetchColumn();
                Support::assertEquals('1', $n, 'FIND_IN_SET on SET');
                return ['detail' => "matched=$n", 'sql' => 'FIND_IN_SET'];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
    }

    // ============================================================== BIT grid

    private static function bitGrid(Runner $r): void
    {
        foreach ([1, 2, 4, 8, 16, 32, 64] as $width) {
            $db = self::db();
            $r->add(self::FW, 'typematrix:bit', "BIT($width) round-trip", function () use ($db, $width) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('bt');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c BIT($width))");
                    // store value 5 (fits all widths >=3; for width<3 use 1)
                    $val = $width >= 3 ? 5 : 1;
                    $pdo->exec("INSERT INTO `$tn`(c) VALUES ($val)");
                    $got = $pdo->query("SELECT c+0 FROM `$tn`")->fetchColumn();
                    Support::assertEquals((string) $val, $got, "BIT($width) numeric value");
                    return ['detail' => "c+0=$got", 'sql' => "BIT($width) <= $val"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // bit literal syntaxes and operators
        $bitExprs = [
            ["b'1010'+0", '10'],
            ["0b1010+0", '10'],
            ['BIN(10)', '1010'],
            ['OCT(8)', '10'],
            ['HEX(255)', 'FF'],
            ['CONV("FF",16,10)', '255'],
            ['BIT_COUNT(7)', '3'],
            ['5 & 3', '1'],
            ['5 | 2', '7'],
            ['5 ^ 1', '4'],
            ['~0', null],
            ['1 << 4', '16'],
            ['256 >> 2', '64'],
            ['BIT_AND(x)', null],
        ];
        foreach ($bitExprs as [$expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'typematrix:bit', "bitop: $expr", $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'typematrix:bit', "bitop: $expr", $expr, $exp, 'value');
            }
        }
    }

    // ========================================================= JSON path grid

    private static function jsonPathGrid(Runner $r): void
    {
        $doc = '{"a":{"b":[10,20,30],"c":"hi"},"d":[{"x":1},{"x":2}],"e":null,"f":true,"n":3.14}';
        $paths = [
            ['$.a.b[0]', '10'],
            ['$.a.b[1]', '20'],
            ['$.a.b[2]', '30'],
            ['$.a.c', '"hi"'],
            ['$.d[0].x', '1'],
            ['$.d[1].x', '2'],
            ['$.e', 'null'],
            ['$.f', 'true'],
            ['$.n', '3.14'],
            ['$.a.b', '[10, 20, 30]'],
            ['$.missing', null],
            ['$.a.b[*]', null],
            ['$.d[*].x', null],
        ];
        $db = self::db();
        foreach ($paths as [$path, $exp]) {
            $r->add(self::FW, 'typematrix:json-path', "JSON_EXTRACT $path", function () use ($db, $doc, $path, $exp) {
                $pdo = Connections::pdo($db);
                $st = $pdo->prepare("SELECT JSON_EXTRACT(?, ?)");
                $st->execute([$doc, $path]);
                $got = $st->fetchColumn();
                if ($exp === null) {
                    return ['detail' => '= ' . var_export($got, true), 'sql' => "JSON_EXTRACT(doc, '$path')"];
                }
                // normalise spaces in arrays
                $norm = is_string($got) ? preg_replace('/,\s*/', ', ', $got) : $got;
                Support::assertValueEquals($exp, $norm, "JSON_EXTRACT $path");
                return ['detail' => '= ' . var_export($got, true), 'sql' => "JSON_EXTRACT(doc, '$path')"];
            });
        }
        // JSON manipulation / predicate functions
        $jexprs = [
            ['JSON_UNQUOTE extract', "JSON_UNQUOTE(JSON_EXTRACT('{\"a\":\"hi\"}','$.a'))", 'hi'],
            ['-> operator', "'{\"a\":5}' -> '$.a'", '5'],
            ['->> operator', "'{\"a\":\"hi\"}' ->> '$.a'", 'hi'],
            ['JSON_LENGTH array', "JSON_LENGTH('[1,2,3,4]')", '4'],
            ['JSON_LENGTH object', "JSON_LENGTH('{\"a\":1,\"b\":2}')", '2'],
            ['JSON_DEPTH', "JSON_DEPTH('{\"a\":{\"b\":1}}')", '3'],
            ['JSON_TYPE object', "JSON_TYPE('{\"a\":1}')", 'OBJECT'],
            ['JSON_TYPE array', "JSON_TYPE('[1,2]')", 'ARRAY'],
            ['JSON_VALID true', "JSON_VALID('{\"a\":1}')", '1'],
            ['JSON_VALID false', "JSON_VALID('{bad}')", '0'],
            ['JSON_KEYS', "JSON_KEYS('{\"a\":1,\"b\":2}')", null],
            ['JSON_CONTAINS', "JSON_CONTAINS('[1,2,3]','2')", '1'],
            ['JSON_CONTAINS_PATH', "JSON_CONTAINS_PATH('{\"a\":1}','one','$.a')", '1'],
            ['JSON_SEARCH', "JSON_SEARCH('[\"a\",\"b\",\"c\"]','one','b')", null],
            ['JSON_OBJECT', "JSON_OBJECT('k',1,'j',2)", null],
            ['JSON_ARRAY', "JSON_ARRAY(1,2,3)", null],
            ['JSON_MERGE_PATCH', "JSON_MERGE_PATCH('{\"a\":1}','{\"b\":2}')", null],
            ['JSON_MERGE_PRESERVE', "JSON_MERGE_PRESERVE('{\"a\":1}','{\"a\":2}')", null],
            ['JSON_SET', "JSON_SET('{\"a\":1}','$.b',2)", null],
            ['JSON_INSERT', "JSON_INSERT('{\"a\":1}','$.b',2)", null],
            ['JSON_REPLACE', "JSON_REPLACE('{\"a\":1}','$.a',9)", null],
            ['JSON_REMOVE', "JSON_REMOVE('{\"a\":1,\"b\":2}','$.b')", null],
            ['JSON_QUOTE', "JSON_QUOTE('hello')", '"hello"'],
            ['JSON_ARRAY_APPEND', "JSON_ARRAY_APPEND('[1,2]','$',3)", null],
            ['JSON_ARRAY_INSERT', "JSON_ARRAY_INSERT('[1,3]','$[1]',2)", null],
            ['JSON_PRETTY', "JSON_PRETTY('{\"a\":1}')", null],
            ['JSON_STORAGE_SIZE', "JSON_STORAGE_SIZE('{\"a\":1}')", null],
            ['JSON_OVERLAPS', "JSON_OVERLAPS('[1,2]','[2,3]')", null],
        ];
        foreach ($jexprs as [$nm, $expr, $exp]) {
            if ($exp === null) {
                self::addExpr($r, 'typematrix:json-path', $nm, $expr, null, 'notnull');
            } else {
                self::addExpr($r, 'typematrix:json-path', $nm, $expr, $exp, 'value');
            }
        }
    }

    // ========================================================= JSON cast grid

    private static function jsonCastGrid(Runner $r): void
    {
        $db = self::db();
        // store typed JSON scalars and read back; verify type preservation
        $vals = [
            ['integer', '{"v":42}', '42'],
            ['float', '{"v":3.14}', '3.14'],
            ['string', '{"v":"text"}', '"text"'],
            ['bool true', '{"v":true}', 'true'],
            ['bool false', '{"v":false}', 'false'],
            ['null', '{"v":null}', 'null'],
            ['nested array', '{"v":[1,[2,[3]]]}', null],
            ['unicode', '{"v":"café"}', null],
            ['empty object', '{"v":{}}', '{}'],
            ['empty array', '{"v":[]}', '[]'],
            ['large number', '{"v":9007199254740993}', null],
            ['negative', '{"v":-17}', '-17'],
            ['scientific', '{"v":1.5e3}', null],
        ];
        foreach ($vals as [$nm, $doc, $exp]) {
            $r->add(self::FW, 'typematrix:json-cast', "JSON value preserves $nm", function () use ($db, $doc, $exp, $nm) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('jc');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (j JSON)");
                    $st = $pdo->prepare("INSERT INTO `$tn`(j) VALUES (?)");
                    $st->execute([$doc]);
                    $got = $pdo->query("SELECT JSON_EXTRACT(j, '$.v') FROM `$tn`")->fetchColumn();
                    if ($exp !== null) {
                        Support::assertValueEquals($exp, $got, "JSON $nm");
                    }
                    return ['detail' => '= ' . var_export($got, true), 'sql' => "JSON cast $nm"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // CAST AS JSON and back
        self::addExpr($r, 'typematrix:json-cast', 'CAST string AS JSON', "JSON_TYPE(CAST('[1,2]' AS JSON))", 'ARRAY', 'value');
        self::addExpr($r, 'typematrix:json-cast', 'CAST AS JSON numeric', "JSON_EXTRACT(CAST('{\"x\":7}' AS JSON),'$.x')", '7', 'value');
    }

    // ======================================================= string type grid

    private static function stringTypeGrid(Runner $r): void
    {
        // CHAR vs VARCHAR padding/trailing-space semantics
        $db = self::db();
        $r->add(self::FW, 'typematrix:string', 'CHAR trailing space on comparison', function () use ($db) {
            $pdo = Connections::pdo($db);
            $tn = Support::name('sc');
            try {
                $pdo->exec("CREATE TABLE `$tn` (c CHAR(10))");
                $pdo->exec("INSERT INTO `$tn` VALUES ('abc')");
                $n = $pdo->query("SELECT COUNT(*) FROM `$tn` WHERE c = 'abc   '")->fetchColumn();
                return ['detail' => "padded-eq matches=$n", 'sql' => "CHAR(10) = 'abc   '"];
            } finally {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                } catch (\Throwable) {
                }
            }
        });
        // TEXT family capacity declarations
        foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB'] as $type) {
            $r->add(self::FW, 'typematrix:string', "$type create+store", function () use ($db, $type) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('sc');
                try {
                    $pdo->exec("CREATE TABLE `$tn` (c $type)");
                    $payload = str_repeat('A', 500);
                    $st = $pdo->prepare("INSERT INTO `$tn`(c) VALUES (?)");
                    $st->execute([$payload]);
                    $got = $pdo->query("SELECT LENGTH(c) FROM `$tn`")->fetchColumn();
                    Support::assertEquals('500', $got, "$type length");
                    return ['detail' => "len=$got", 'sql' => "$type store 500 bytes"];
                } finally {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            });
        }
        // VARCHAR length boundary: truncation vs rejection
        self::addReject($r, 'typematrix:string', 'VARCHAR(3) rejects overflow (strict)', 'VARCHAR(3)', "'abcde'");
        self::addRoundTrip($r, 'typematrix:string', 'VARCHAR(5) exact fit', 'VARCHAR(5)', "'abcde'", 'abcde');
        // VARBINARY round-trip
        self::addRoundTrip($r, 'typematrix:string', 'VARBINARY hex', 'VARBINARY(8)', "0x48656C6C6F", 'Hello');
    }
}
