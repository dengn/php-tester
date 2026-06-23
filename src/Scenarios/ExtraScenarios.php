<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Extended raw-SQL coverage: a broader sweep of built-in functions, CAST/CONVERT
 * targets, spatial & info functions, type edge values and advanced query
 * constructs. Kept separate from the core providers so the baseline stays
 * stable.
 */
final class ExtraScenarios
{
    public static function register(Runner $r): void
    {
        self::exprBatch($r, 'function:string', self::strings());
        self::exprBatch($r, 'function:numeric', self::maths());
        self::exprBatch($r, 'function:datetime', self::dates());
        self::exprBatch($r, 'function:cast', self::casts());
        self::exprBatch($r, 'function:info', self::info());
        self::exprBatch($r, 'function:encryption', self::crypto());
        self::exprBatch($r, 'function:spatial', self::spatial());
        self::exprBatch($r, 'function:json', self::json());
        self::exprBatch($r, 'function:flow', self::flow());
        self::typeEdges($r);
        self::advancedSql($r);
    }

    /** @param array<int,array{0:string,1:string,2?:string}> $cases [name, expr, expectedOrNull] */
    private static function exprBatch(Runner $r, string $cat, array $cases): void
    {
        $db = Config::database('pdo');
        foreach ($cases as $c) {
            $name = $c[0];
            $expr = $c[1];
            $expect = $c[2] ?? null;
            $r->add('PDO', $cat, $name, function () use ($db, $expr, $expect) {
                $pdo = Connections::pdo($db);
                $sql = "SELECT $expr AS v";
                $got = $pdo->query($sql)->fetchColumn();
                if ($expect !== null) {
                    Support::assertValueEquals($expect, $got, $expr);
                } else {
                    Support::assert($got !== null && $got !== false, 'returned NULL/false');
                }
                return ['detail' => '= ' . (is_string($got) && strlen($got) > 30 ? substr($got, 0, 30) . '…' : var_export($got, true)), 'sql' => $sql];
            });
        }
    }

    private static function strings(): array
    {
        return [
            ['TRIM LEADING', "TRIM(LEADING 'x' FROM 'xxabc')", 'abc'],
            ['TRIM TRAILING', "TRIM(TRAILING 'x' FROM 'abcxx')", 'abc'],
            ['SUBSTRING negative start', "SUBSTRING('abcdef', -3)", 'def'],
            ['SUBSTRING FROM FOR', "SUBSTRING('abcdef' FROM 2 FOR 3)", 'bcd'],
            ['LEFT zero', "LEFT('abc', 0)", ''],
            ['REPEAT zero', "REPEAT('a', 0)", ''],
            ['CONCAT_WS skips null', "CONCAT_WS('-', 'a', NULL, 'c')", 'a-c'],
            ['EXPORT_SET', "EXPORT_SET(5, 'Y', 'N', ',', 4)", null],
            ['MAKE_SET', "MAKE_SET(3, 'a', 'b', 'c')", null],
            ['WEIGHT_STRING', "HEX(WEIGHT_STRING('a'))", null],
            ['CHAR USING', "CHAR(72, 105 USING utf8mb4)", null],
            ['NCHAR-like CONVERT', "CONVERT('abc' USING utf8mb4)", 'abc'],
            ['SUBSTRING_INDEX neg', "SUBSTRING_INDEX('a.b.c', '.', -1)", 'c'],
            ['LPAD truncates', "LPAD('abcdef', 3, '0')", 'abc'],
            ['UPPER multibyte', "UPPER('café')", 'CAFÉ'],
            ['REVERSE multibyte', "REVERSE('abc')", 'cba'],
            ['ASCII of empty', "ASCII('')", '0'],
            ['REPLACE empty search', "REPLACE('abc', '', 'x')", 'abc'],
            ['FIELD not found', "FIELD('z', 'a', 'b')", '0'],
            ['QUOTE wraps', "QUOTE('a')", "'a'"],
            ['SPACE length', "CHAR_LENGTH(SPACE(5))", '5'],
            ['HEX of number', "HEX(255)", 'FF'],
            ['LOWER ascii', "LOWER('ABC123')", 'abc123'],
            ['CONCAT empty args', "CONCAT('', '')", ''],
            ['INSTR not found', "INSTR('abc', 'z')", '0'],
        ];
    }

    private static function maths(): array
    {
        return [
            ['ROUND negative places', 'ROUND(1234.5, -2)', '1200'],
            ['ROUND half up', 'ROUND(2.5)', '3'],
            ['TRUNCATE negative', 'TRUNCATE(1234.56, -2)', '1200'],
            ['CEIL negative', 'CEIL(-1.5)', '-1'],
            ['FLOOR negative', 'FLOOR(-1.5)', '-2'],
            ['ABS float', 'ABS(-3.14)', '3.14'],
            ['MOD float', 'MOD(5.5, 2)', '1.5'],
            ['POW negative exp', 'POW(2, -1)', '0.5'],
            ['LOG base', 'LOG(2, 8)', '3'],
            ['LEAST mixed', 'LEAST(3, 1, 2)', '1'],
            ['GREATEST strings', "GREATEST('a', 'b', 'c')", 'c'],
            ['SIGN zero', 'SIGN(0)', '0'],
            ['SIGN positive', 'SIGN(42)', '1'],
            ['CONV bin to hex', "CONV('1010', 2, 16)", 'A'],
            ['CRC32 known', "CRC32('MySQL')", '3259397556'],
            ['RAND seeded', 'ROUND(RAND(1), 0) IN (0,1)', '1'],
            ['PI rounded', 'ROUND(PI(), 2)', '3.14'],
            ['bitwise complex', '(5 & 3) | 8', '9'],
            ['shift chain', '(1 << 3) >> 1', '4'],
            ['DEGREES half pi', 'ROUND(DEGREES(PI()/2))', '90'],
        ];
    }

    private static function dates(): array
    {
        return [
            ['YEARWEEK', "YEARWEEK('2026-01-01')", null],
            ['WEEK mode 0', "WEEK('2026-01-01', 0)", null],
            ['WEEK mode 3', "WEEK('2026-01-01', 3)", null],
            ['GET_FORMAT', "GET_FORMAT(DATE, 'ISO')", null],
            ['PERIOD_ADD', 'PERIOD_ADD(202601, 2)', '202603'],
            ['PERIOD_DIFF', 'PERIOD_DIFF(202603, 202601)', '2'],
            ['EXTRACT MONTH', "EXTRACT(MONTH FROM '2026-06-23')", '6'],
            ['EXTRACT DAY', "EXTRACT(DAY FROM '2026-06-23')", '23'],
            ['EXTRACT HOUR', "EXTRACT(HOUR FROM '2026-06-23 14:00:00')", '14'],
            ['DATE_ADD WEEK', "DATE_ADD('2026-01-01', INTERVAL 1 WEEK)", '2026-01-08'],
            ['DATE_ADD MONTH', "DATE_ADD('2026-01-31', INTERVAL 1 MONTH)", '2026-02-28'],
            ['DATE_ADD YEAR', "DATE_ADD('2024-02-29', INTERVAL 1 YEAR)", '2025-02-28'],
            ['DATE_SUB HOUR', "DATE_SUB('2026-01-01 01:00:00', INTERVAL 2 HOUR)", '2025-12-31 23:00:00'],
            ['INTERVAL MINUTE_SECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '1:30' MINUTE_SECOND)", '2026-01-01 00:01:30'],
            ['TIMESTAMPDIFF MONTH', "TIMESTAMPDIFF(MONTH, '2026-01-01', '2026-04-01')", '3'],
            ['TIMESTAMPDIFF YEAR', "TIMESTAMPDIFF(YEAR, '2020-01-01', '2026-01-01')", '6'],
            ['DATEDIFF negative', "DATEDIFF('2026-01-01', '2026-01-10')", '-9'],
            ['DAYOFWEEK Sunday', "DAYOFWEEK('2026-06-21')", '1'],
            ['QUARTER Q4', "QUARTER('2026-11-01')", '4'],
            ['LAST_DAY Feb leap', "LAST_DAY('2024-02-10')", '2024-02-29'],
            ['STR_TO_DATE time', "STR_TO_DATE('14:30:00', '%H:%i:%s')", '14:30:00'],
            ['DATE_FORMAT weekday', "DATE_FORMAT('2026-06-23', '%W')", null],
            ['DATE_FORMAT 12h', "DATE_FORMAT('2026-06-23 14:05:00', '%h:%i %p')", '02:05 PM'],
            ['UNIX_TIMESTAMP roundtrip', "FROM_UNIXTIME(UNIX_TIMESTAMP('2026-06-23 00:00:00'))", '2026-06-23 00:00:00'],
            ['TIME_TO_SEC', "TIME_TO_SEC('00:01:40')", '100'],
        ];
    }

    private static function casts(): array
    {
        return [
            ['CAST AS SIGNED', "CAST('-42' AS SIGNED)", '-42'],
            ['CAST AS UNSIGNED', "CAST(42 AS UNSIGNED)", '42'],
            ['CAST AS CHAR(n)', "CAST(12345 AS CHAR(3))", null],
            ['CAST AS DECIMAL', "CAST('3.14159' AS DECIMAL(5,2))", '3.14'],
            ['CAST AS DATE', "CAST('2026-06-23' AS DATE)", '2026-06-23'],
            ['CAST AS DATETIME', "CAST('2026-06-23 10:00:00' AS DATETIME)", '2026-06-23 10:00:00'],
            ['CAST AS TIME', "CAST('10:20:30' AS TIME)", '10:20:30'],
            ['CAST AS DOUBLE', "CAST('1.5' AS DOUBLE)", null],
            ['CAST AS FLOAT', "CAST('1.5' AS FLOAT)", null],
            ['CAST AS BINARY', "CAST('abc' AS BINARY)", 'abc'],
            ['CAST AS JSON', "CAST('[1,2,3]' AS JSON)", null],
            ['CONVERT AS SIGNED', "CONVERT('99', SIGNED)", '99'],
            ['CONVERT AS CHAR', "CONVERT(99, CHAR)", '99'],
            ['CONVERT AS DECIMAL', "CONVERT('1.25', DECIMAL(4,2))", '1.25'],
            ['CONVERT USING', "CONVERT('abc' USING utf8mb4)", 'abc'],
            ['BINARY operator', "BINARY 'abc'", 'abc'],
            ['CAST DECIMAL high prec', "CAST('123456789.123456' AS DECIMAL(20,6))", '123456789.123456'],
            ['CAST negative DECIMAL', "CAST('-0.5' AS DECIMAL(3,1))", '-0.5'],
            ['implicit date compare', "'2026-01-01' < '2026-12-31'", '1'],
            ['CAST bool to char', "CAST(TRUE AS CHAR)", '1'],
        ];
    }

    private static function info(): array
    {
        return [
            ['SCHEMA()', 'SCHEMA()', null],
            ['SESSION_USER()', 'SESSION_USER()', null],
            ['SYSTEM_USER()', 'SYSTEM_USER()', null],
            ['CURRENT_USER', 'CURRENT_USER', null],
            ['CHARSET of string', "CHARSET('abc')", null],
            ['COLLATION of string', "COLLATION('abc')", null],
            ['COERCIBILITY', "COERCIBILITY('abc')", null],
            ['FOUND_ROWS', 'FOUND_ROWS()', null],
            ['ROW_COUNT', 'ROW_COUNT()', null],
            ['CONNECTION_ID', 'CONNECTION_ID() > 0', '1'],
            ['LAST_INSERT_ID arg', 'LAST_INSERT_ID()', null],
            ['UUID_SHORT', 'UUID_SHORT()', null],
        ];
    }

    private static function crypto(): array
    {
        return [
            ['MD5 length', "LENGTH(MD5('x'))", '32'],
            ['SHA1 length', "LENGTH(SHA1('x'))", '40'],
            ['SHA2-256 length', "LENGTH(SHA2('x', 256))", '64'],
            ['SHA2-512 length', "LENGTH(SHA2('x', 512))", '128'],
            ['AES round-trip', "CAST(AES_DECRYPT(AES_ENCRYPT('secret','k'),'k') AS CHAR)", 'secret'],
            ['TO_BASE64 round-trip', "FROM_BASE64(TO_BASE64('hello'))", 'hello'],
            ['COMPRESS round-trip', "CAST(UNCOMPRESS(COMPRESS('payload')) AS CHAR)", 'payload'],
            ['RANDOM_BYTES length', "LENGTH(RANDOM_BYTES(16))", '16'],
            ['CRC32 stable', "CRC32('abc')", '891568578'],
            ['HEX of MD5 upper', "UPPER(MD5('a')) = UPPER(MD5('a'))", '1'],
        ];
    }

    private static function spatial(): array
    {
        return [
            ['ST_GeomFromText POINT', "ST_AsText(ST_GeomFromText('POINT(1 2)'))", null],
            ['POINT constructor', "ST_AsText(POINT(3, 4))", null],
            ['ST_X', "ST_X(POINT(3, 4))", null],
            ['ST_Y', "ST_Y(POINT(3, 4))", null],
            ['ST_AsText linestring', "ST_AsText(ST_GeomFromText('LINESTRING(0 0,1 1)'))", null],
            ['ST_Distance', "ST_Distance(POINT(0,0), POINT(3,4))", null],
            ['ST_GeomFromText POLYGON', "ST_AsText(ST_GeomFromText('POLYGON((0 0,4 0,4 4,0 4,0 0))'))", null],
            ['ST_Contains', "ST_Contains(ST_GeomFromText('POLYGON((0 0,4 0,4 4,0 4,0 0))'), POINT(1,1))", null],
            ['ST_SRID', "ST_SRID(POINT(1,1))", null],
            ['GeomFromText alias', "ST_AsText(GeomFromText('POINT(5 5)'))", null],
            ['ST_AsWKT', "ST_AsWKT(POINT(1,1))", null],
            ['ST_GeometryType', "ST_GeometryType(POINT(1,1))", null],
        ];
    }

    private static function json(): array
    {
        return [
            ['JSON_PRETTY', "JSON_PRETTY('{\"a\":1}')", null],
            ['JSON_STORAGE_SIZE', "JSON_STORAGE_SIZE('{\"a\":1}')", null],
            ['JSON_VALUE', "JSON_VALUE('{\"a\":42}', '$.a')", null],
            ['MEMBER OF', "1 MEMBER OF('[1,2,3]')", null],
            ['JSON_TABLE', "(SELECT COUNT(*) FROM JSON_TABLE('[1,2,3]', '$[*]' COLUMNS(v INT PATH '$')) jt)", null],
            ['nested JSON_EXTRACT', "JSON_UNQUOTE(JSON_EXTRACT('{\"a\":{\"b\":\"deep\"}}', '$.a.b'))", 'deep'],
            ['JSON_EXTRACT array idx', "JSON_EXTRACT('[10,20,30]', '$[1]')", '20'],
            ['JSON_ARRAYAGG', "(SELECT JSON_ARRAYAGG(n) FROM (SELECT 1 n UNION ALL SELECT 2) t)", null],
            ['JSON_OBJECTAGG', "(SELECT JSON_OBJECTAGG(k,v) FROM (SELECT 'a' k, 1 v) t)", null],
            ['JSON quote/unquote', "JSON_UNQUOTE(JSON_QUOTE('x'))", 'x'],
        ];
    }

    private static function flow(): array
    {
        return [
            ['nested IF', "IF(1>2, 'a', IF(2>1, 'b', 'c'))", 'b'],
            ['CASE multi WHEN', "CASE 3 WHEN 1 THEN 'a' WHEN 3 THEN 'c' ELSE 'z' END", 'c'],
            ['CASE searched', "CASE WHEN 5>10 THEN 'x' WHEN 5>1 THEN 'y' END", 'y'],
            ['IFNULL chain', 'IFNULL(NULL, IFNULL(NULL, 9))', '9'],
            ['NULLIF equal', 'ISNULL(NULLIF(5, 5))', '1'],
            ['NULLIF unequal', 'NULLIF(5, 4)', '5'],
            ['COALESCE all null', 'ISNULL(COALESCE(NULL, NULL))', '1'],
            ['GREATEST in CASE', "CASE WHEN GREATEST(1,2,3)=3 THEN 'ok' END", 'ok'],
        ];
    }

    private static function typeEdges(Runner $r): void
    {
        $db = Config::database('pdo');
        $cases = [
            // [name, coltype, value, expected]
            ['INT zero', 'INT', 0, '0'],
            ['INT min', 'INT', -2147483648, '-2147483648'],
            ['BIGINT max', 'BIGINT', '9223372036854775807', '9223372036854775807'],
            ['DECIMAL zero scale', 'DECIMAL(10,0)', '12345', '12345'],
            ['DECIMAL negative', 'DECIMAL(6,2)', '-12.34', '-12.34'],
            ['VARCHAR empty', 'VARCHAR(10)', '', ''],
            ['VARCHAR max len', 'VARCHAR(255)', str_repeat('a', 255), str_repeat('a', 255)],
            ['CHAR trailing trim', 'CHAR(5)', 'ab', 'ab'],
            ['TEXT large', 'TEXT', str_repeat('x', 10000), null],
            ['DATE min', 'DATE', '1000-01-01', '1000-01-01'],
            ['DATE max', 'DATE', '9999-12-31', '9999-12-31'],
            ['DATETIME frac', 'DATETIME(3)', '2026-06-23 10:00:00.123', '2026-06-23 10:00:00.123'],
            ['TIME negative', 'TIME', '-12:00:00', null],
            ['DOUBLE scientific', 'DOUBLE', '1.5e10', null],
            ['DOUBLE tiny', 'DOUBLE', '0.0000001', null],
            ['BOOL via tinyint', 'TINYINT(1)', 1, '1'],
            ['unicode 4byte emoji', 'VARCHAR(50)', '👨‍👩‍👧‍👦', null],
            ['ENUM by value', "ENUM('s','m','l')", 'm', 'm'],
            ['ENUM invalid rejected', "ENUM('s','m','l')", 'm', 'm'],
            ['JSON array', 'JSON', '[1,2,3]', null],
            ['JSON nested', 'JSON', '{"a":{"b":[1,2]}}', null],
            ['UUID stored', 'UUID', '123e4567-e89b-12d3-a456-426614174000', null],
            ['VECF32 dim', 'VECF32(4)', '[1,2,3,4]', null],
            ['negative zero float', 'DOUBLE', '-0.0', null],
            ['DECIMAL high precision', 'DECIMAL(38,0)', '99999999999999999999999999999999999999', '99999999999999999999999999999999999999'],
        ];
        foreach ($cases as [$name, $coltype, $value, $expect]) {
            $r->add('PDO', 'datatype:edge', $name, function () use ($db, $name, $coltype, $value, $expect) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('te');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $coltype)");
                try {
                    $st = $pdo->prepare("INSERT INTO `$tn`(id,c) VALUES (1, ?)");
                    $st->execute([$value]);
                    $got = $pdo->query("SELECT c FROM `$tn` WHERE id=1")->fetchColumn();
                    if ($expect !== null) {
                        Support::assertEquals($expect, $got, $name);
                    } else {
                        Support::assert($got !== null && $got !== false, 'not stored');
                    }
                    return 'ok';
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }

    private static function advancedSql(Runner $r): void
    {
        $db = Config::database('pdo');
        $seed = ['CREATE TABLE {t} (id INT PRIMARY KEY AUTO_INCREMENT, grp VARCHAR(10), val INT)',
            "INSERT INTO {t}(grp,val) VALUES ('a',10),('a',20),('b',30),('b',40),('b',50)"];
        $cases = [
            ['CTE chained', "(WITH s AS (SELECT grp, SUM(val) sv FROM {t} GROUP BY grp), m AS (SELECT MAX(sv) ms FROM s) SELECT ms FROM m)", '120'],
            ['window RANK partition', "(SELECT MAX(r) FROM (SELECT RANK() OVER (PARTITION BY grp ORDER BY val DESC) r FROM {t}) z)", '3'],
            ['window running sum', "(SELECT MAX(rs) FROM (SELECT SUM(val) OVER (ORDER BY id) rs FROM {t}) z)", '150'],
            ['window ROWS frame', "(SELECT MAX(s) FROM (SELECT SUM(val) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) s FROM {t}) z)", '90'],
            ['correlated EXISTS', "(SELECT COUNT(*) FROM {t} a WHERE EXISTS (SELECT 1 FROM {t} b WHERE b.grp=a.grp AND b.val > a.val))", '3'],
            ['scalar subquery select', "(SELECT (SELECT COUNT(*) FROM {t}) )", '5'],
            ['HAVING with alias', "(SELECT grp FROM {t} GROUP BY grp HAVING SUM(val) > 100 ORDER BY grp LIMIT 1)", 'b'],
            ['DISTINCT count', "(SELECT COUNT(DISTINCT grp) FROM {t})", '2'],
            ['CASE aggregate pivot', "(SELECT SUM(CASE WHEN grp='a' THEN val ELSE 0 END) FROM {t})", '30'],
            ['IN subquery correlated', "(SELECT COUNT(*) FROM {t} WHERE val > (SELECT AVG(val) FROM {t}))", '2'],
            ['UNION with ORDER', "(SELECT val FROM (SELECT val FROM {t} WHERE grp='a' UNION SELECT val FROM {t} WHERE grp='b' ORDER BY val DESC LIMIT 1) u)", '50'],
            ['GROUP_CONCAT ordered', "(SELECT GROUP_CONCAT(val ORDER BY val SEPARATOR '-') FROM {t} WHERE grp='a')", '10-20'],
            ['nested derived tables', "(SELECT SUM(s) FROM (SELECT grp, SUM(val) s FROM {t} GROUP BY grp) d)", '150'],
            ['LIMIT in derived', "(SELECT COUNT(*) FROM (SELECT * FROM {t} ORDER BY val DESC LIMIT 3) d)", '3'],
            ['COUNT with GROUP and JOIN', "(SELECT COUNT(*) FROM {t} a JOIN {t} b ON a.grp=b.grp WHERE a.id<>b.id)", '8'],
        ];
        foreach ($cases as [$name, $expr, $expect]) {
            $r->add('PDO', 'sql:advanced', $name, function () use ($db, $seed, $expr, $expect, $name) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('adv');
                foreach ($seed as $s) {
                    $st = $pdo->query(strtr($s, ['{t}' => $tn]));
                    if ($st) {
                        $st->closeCursor();
                    }
                }
                try {
                    $got = $pdo->query("SELECT " . strtr($expr, ['{t}' => $tn]))->fetchColumn();
                    Support::assertValueEquals($expect, $got, $name);
                    return ['detail' => "= $got", 'sql' => strtr($expr, ['{t}' => $tn])];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }
}
