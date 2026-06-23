<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Second wave of raw-PDO built-in function & operator coverage.
 *
 * Every entry runs `SELECT <expr>` against MatrixOne and, where a MySQL-correct
 * expected value is supplied, asserts the result so any divergence surfaces as a
 * compatibility finding. Areas here are intentionally NOT covered by
 * PdoFunctionScenarios / ExtraScenarios: deeper string/math/datetime edge cases,
 * the full JSON surface (incl. -> / ->> operators on a real column and
 * JSON_ARRAYAGG/OBJECTAGG over derived tables), comparison/conditional operators,
 * exhaustive CAST/CONVERT targets, and the full aggregate/window catalogue.
 *
 * Aggregate/window scenarios use an inline derived table wrapped in a scalar
 * subquery so the whole thing still fits a single `SELECT <expr>`.
 */
final class PdoFunctionScenarios2
{
    public static function register(Runner $r): void
    {
        self::exprBatch($r, 'function:string', self::strings());
        self::exprBatch($r, 'function:math', self::maths());
        self::exprBatch($r, 'function:datetime', self::dates());
        self::exprBatch($r, 'function:json', self::json());
        self::exprBatch($r, 'function:comparison', self::comparison());
        self::exprBatch($r, 'function:conditional', self::conditional());
        self::exprBatch($r, 'function:cast', self::casts());
        self::exprBatch($r, 'function:bit', self::bit());
        self::exprBatch($r, 'function:aggregate', self::aggregate());
        self::exprBatch($r, 'function:window', self::window());
        self::jsonColumnOps($r);
    }

    /**
     * Data-driven runner: each row is [name, expr, expectedOrNull].
     * When expected is null the scenario only asserts the call runs and returns
     * a non-NULL value; otherwise the result is compared numeric-tolerantly.
     *
     * @param array<int,array{0:string,1:string,2?:?string}> $cases
     */
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
                $repr = is_string($got) && strlen($got) > 40 ? substr($got, 0, 40) . '…' : var_export($got, true);
                return ['detail' => '= ' . $repr, 'sql' => $sql];
            });
        }
    }

    // ------------------------------------------------------------------
    // String functions & operators
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function strings(): array
    {
        return [
            // REGEXP_* variants & flags
            ['REGEXP_REPLACE pos', "REGEXP_REPLACE('a1b2c3','[0-9]','#',2)", 'a1b#c#'],
            ['REGEXP_REPLACE occurrence', "REGEXP_REPLACE('a1b2c3','[0-9]','#',1,2)", 'a1b#c3'],
            ['REGEXP_REPLACE ci flag', "REGEXP_REPLACE('AbAb','a','x',1,0,'i')", 'xbxb'],
            ['REGEXP_SUBSTR pos', "REGEXP_SUBSTR('a1b22c333','[0-9]+',2)", '22'],
            ['REGEXP_SUBSTR occurrence', "REGEXP_SUBSTR('a1b22c333','[0-9]+',1,2)", '22'],
            ['REGEXP_INSTR occurrence', "REGEXP_INSTR('a1b2c3','[0-9]',1,2)", '4'],
            ['REGEXP_INSTR return-end', "REGEXP_INSTR('abc123','[0-9]+',1,1,1)", '7'],
            ['REGEXP_LIKE anchored', "REGEXP_LIKE('abc','^abc$')", '1'],
            ['REGEXP_LIKE ci', "REGEXP_LIKE('ABC','abc','i')", '1'],
            ['REGEXP alternation', "'cat' REGEXP 'cat|dog'", '1'],
            ['REGEXP char class', "'a5' REGEXP '[[:digit:]]'", '1'],
            ['NOT REGEXP', "'abc' NOT REGEXP '^x'", '1'],

            // SUBSTRING_INDEX edge cases
            ['SUBSTRING_INDEX count gt parts', "SUBSTRING_INDEX('a.b','.',5)", 'a.b'],
            ['SUBSTRING_INDEX zero', "SUBSTRING_INDEX('a.b.c','.',0)", ''],
            ['SUBSTRING_INDEX neg gt parts', "SUBSTRING_INDEX('a.b','.',-5)", 'a.b'],
            ['SUBSTRING_INDEX multichar delim', "SUBSTRING_INDEX('axxbxxc','xx',2)", 'axxb'],
            ['SUBSTRING_INDEX no delim', "SUBSTRING_INDEX('abc','.',1)", 'abc'],

            // FORMAT
            ['FORMAT zero scale', 'FORMAT(1234.567, 0)', '1,235'],
            ['FORMAT negative', 'FORMAT(-1234.5, 1)', '-1,234.5'],
            ['FORMAT large', 'FORMAT(1234567.891, 2)', '1,234,567.89'],
            ['FORMAT locale de_DE', "FORMAT(1234.56, 2, 'de_DE')", null],
            ['FORMAT locale en_US', "FORMAT(1234.56, 2, 'en_US')", '1,234.56'],

            // LPAD/RPAD edge
            ['LPAD longer than target', "LPAD('abcdef', 4, '-')", 'abcd'],
            ['RPAD multichar pad', "RPAD('5', 7, 'xy')", '5xyxyxy'],
            ['LPAD multichar pad', "LPAD('5', 7, 'xy')", 'xyxyxy5'],
            ['LPAD zero width', "LPAD('abc', 0, '-')", ''],
            ['RPAD empty pad', "RPAD('a', 5, '')", null],

            // FIELD / ELT / INTERVAL
            ['FIELD numeric', 'FIELD(3, 1, 2, 3, 4)', '3'],
            ['FIELD with null', "FIELD(NULL, 'a', NULL)", '0'],
            ['ELT out of range', "ELT(9, 'a', 'b')", null],
            ['ELT first', "ELT(1, 'x', 'y')", 'x'],
            ['INTERVAL function basic', 'INTERVAL(5, 1, 3, 7, 9)', '2'],
            ['INTERVAL below all', 'INTERVAL(0, 1, 3, 5)', '0'],
            ['INTERVAL above all', 'INTERVAL(99, 1, 3, 5)', '3'],

            // EXPORT_SET / MAKE_SET / BIT_LENGTH
            ['EXPORT_SET on/off', "EXPORT_SET(6, 'Y', 'N', ',', 4)", 'N,Y,Y,N'],
            ['EXPORT_SET default sep', "EXPORT_SET(5, '1', '0')", null],
            ['MAKE_SET basic', "MAKE_SET(5, 'a', 'b', 'c')", 'a,c'],
            ['MAKE_SET zero', "MAKE_SET(0, 'a', 'b')", ''],
            ['BIT_LENGTH multibyte', "BIT_LENGTH('café')", '40'],
            ['BIT_LENGTH empty', "BIT_LENGTH('')", '0'],

            // TRIM forms
            ['TRIM LEADING multichar', "TRIM(LEADING 'ab' FROM 'ababxy')", 'xy'],
            ['TRIM TRAILING multichar', "TRIM(TRAILING 'xy' FROM 'abxyxy')", 'ab'],
            ['TRIM BOTH default space', "TRIM('   hi   ')", 'hi'],
            ['TRIM remstr only', "TRIM('x' FROM 'xxhixx')", 'hi'],
            ['TRIM no match', "TRIM(LEADING 'z' FROM 'abc')", 'abc'],

            // REPEAT / REVERSE / SPACE
            ['REPEAT negative count', "REPEAT('a', -1)", ''],
            ['REPEAT multichar', "REPEAT('ab', 4)", 'abababab'],
            ['REVERSE multibyte café', "REVERSE('café')", 'éfac'],
            ['REVERSE empty', "REVERSE('')", ''],

            // HEX/UNHEX/CHAR
            ['HEX of string', "HEX('MO')", '4D4F'],
            ['UNHEX roundtrip', "UNHEX(HEX('hello'))", 'hello'],
            ['HEX of zero', 'HEX(0)', '0'],
            ['CHAR multiple args', 'CHAR(77, 79)', null],
            ['CHAR with using', "CHAR(0xE4 USING utf8mb4)", null],
            ['ASCII multibyte first byte', "ASCII('é')", null],
            ['ASCII normal', "ASCII('b')", '98'],
            ['ORD multibyte', "ORD('é')", null],

            // LOCATE / INSTR / POSITION
            ['LOCATE with start', "LOCATE('a', 'banana', 2)", '4'],
            ['LOCATE not found', "LOCATE('z', 'abc')", '0'],
            ['LOCATE empty needle', "LOCATE('', 'abc')", '1'],
            ['INSTR multibyte', "INSTR('aébc', 'b')", '3'],
            ['POSITION not found', "POSITION('z' IN 'abc')", '0'],

            // STRCMP
            ['STRCMP equal', "STRCMP('abc', 'abc')", '0'],
            ['STRCMP greater', "STRCMP('b', 'a')", '1'],
            ['STRCMP prefix', "STRCMP('ab', 'abc')", '-1'],

            // MD5/SHA2 sizes
            ['SHA2 224', "LENGTH(SHA2('x', 224))", '56'],
            ['SHA2 384', "LENGTH(SHA2('x', 384))", '96'],
            ['SHA2 0 means 256', "LENGTH(SHA2('x', 0))", '64'],
            ['MD5 known value', "MD5('')", 'd41d8cd98f00b204e9800998ecf8427e'],

            // LENGTH vs CHAR_LENGTH multibyte
            ['LENGTH vs CHAR_LENGTH emoji', "LENGTH('😀') - CHAR_LENGTH('😀')", '3'],
            ['CHAR_LENGTH emoji', "CHAR_LENGTH('😀')", '1'],
            ['LENGTH 3byte char', "LENGTH('中')", '3'],
            ['CHAR_LENGTH mixed', "CHAR_LENGTH('a中b')", '3'],
            ['OCTET_LENGTH multibyte', "OCTET_LENGTH('中文')", '6'],

            // misc string
            ['CONCAT with number', "CONCAT('id=', 42)", 'id=42'],
            ['LCASE multibyte', "LCASE('ÉÈ')", null],
            ['QUOTE null', 'QUOTE(NULL)', null],
            ['LOAD_FILE missing', "LOAD_FILE('/nonexistent/path')", null],
            ['SUBSTR negative len', "SUBSTRING('abcdef', 2, -1)", ''],
        ];
    }

    // ------------------------------------------------------------------
    // Math functions & operators
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function maths(): array
    {
        return [
            // LOG variants
            ['LOG one arg', 'LOG(EXP(2))', '2'],
            ['LOG base 2', 'LOG(2, 16)', '4'],
            ['LOG base 10', 'LOG(10, 100)', '2'],
            ['LOG2 fractional', 'LOG2(0.5)', '-1'],
            ['LOG10 of 1', 'LOG10(1)', '0'],
            ['LN of e', 'LN(EXP(1))', '1'],

            // ROUND scales
            ['ROUND scale 3', 'ROUND(3.14159, 3)', '3.142'],
            ['ROUND neg scale 1', 'ROUND(155, -1)', '160'],
            ['ROUND neg scale 3', 'ROUND(12345, -3)', '12000'],
            ['ROUND negative value', 'ROUND(-2.5)', '-3'],
            ['ROUND zero scale', 'ROUND(2.4)', '2'],

            // TRUNCATE
            ['TRUNCATE positive', 'TRUNCATE(9.87654, 3)', '9.876'],
            ['TRUNCATE to int', 'TRUNCATE(9.99, 0)', '9'],
            ['TRUNCATE negative value', 'TRUNCATE(-9.99, 1)', '-9.9'],
            ['TRUNCATE neg scale', 'TRUNCATE(987, -1)', '980'],

            // MOD edge
            ['MOD negative dividend', 'MOD(-7, 3)', '-1'],
            ['MOD negative divisor', 'MOD(7, -3)', '1'],
            ['MOD by zero null', 'ISNULL(MOD(5, 0))', '1'],
            ['MOD float', 'MOD(5.5, 2)', '1.5'],

            // POW/SQRT/EXP/LN ranges
            ['POW zero exp', 'POW(7, 0)', '1'],
            ['POW fractional', 'POW(27, 1.0/3.0)', '3'],
            ['POW negative base', 'POW(-2, 3)', '-8'],
            ['SQRT of 2', 'ROUND(SQRT(2), 5)', '1.41421'],
            ['SQRT negative null', 'ISNULL(SQRT(-1))', '1'],
            ['EXP of 1', 'ROUND(EXP(1), 5)', '2.71828'],
            ['EXP negative', 'ROUND(EXP(-1), 5)', '0.36788'],

            // trig
            ['SIN pi/2', 'ROUND(SIN(PI()/2), 5)', '1'],
            ['COS pi', 'ROUND(COS(PI()), 5)', '-1'],
            ['TAN pi/4', 'ROUND(TAN(PI()/4), 5)', '1'],
            ['ASIN 0.5', 'ROUND(ASIN(0.5), 5)', '0.5236'],
            ['ACOS 0', 'ROUND(ACOS(0), 5)', '1.5708'],
            ['ATAN 0', 'ATAN(0)', '0'],
            ['ATAN2 quadrant', 'ROUND(ATAN2(1, -1), 5)', '2.35619'],
            ['COT pi/4', 'ROUND(COT(PI()/4), 5)', '1'],

            // DEGREES / RADIANS
            ['DEGREES of pi', 'ROUND(DEGREES(PI()), 5)', '180'],
            ['RADIANS of 180', 'ROUND(RADIANS(180), 5)', '3.14159'],
            ['DEGREES roundtrip', 'ROUND(DEGREES(RADIANS(45)), 5)', '45'],

            // SIGN / ABS
            ['SIGN negative float', 'SIGN(-0.001)', '-1'],
            ['SIGN positive float', 'SIGN(0.001)', '1'],
            ['ABS negative big', 'ABS(-1234567890)', '1234567890'],
            ['ABS of zero', 'ABS(0)', '0'],

            // CEIL / FLOOR on negatives
            ['CEIL negative', 'CEIL(-2.1)', '-2'],
            ['FLOOR negative', 'FLOOR(-2.1)', '-3'],
            ['CEIL exact', 'CEIL(5.0)', '5'],
            ['FLOOR exact', 'FLOOR(5.0)', '5'],

            // GREATEST / LEAST
            ['GREATEST floats', 'GREATEST(1.5, 2.5, 0.5)', '2.5'],
            ['LEAST floats', 'LEAST(1.5, 2.5, 0.5)', '0.5'],
            ['GREATEST strings', "GREATEST('apple', 'banana', 'cherry')", 'cherry'],
            ['LEAST strings', "LEAST('apple', 'banana', 'cherry')", 'apple'],
            ['GREATEST mixed neg', 'GREATEST(-5, -1, -10)', '-1'],

            // RAND
            ['RAND in range', 'RAND() >= 0 AND RAND() < 1', '1'],
            ['RAND seeded deterministic', 'RAND(7) = RAND(7)', '1'],

            // CONV across bases
            ['CONV dec to bin', "CONV(10, 10, 2)", '1010'],
            ['CONV hex to dec', "CONV('1A', 16, 10)", '26'],
            ['CONV bin to oct', "CONV('1111', 2, 8)", '17'],
            ['CONV to base 36', "CONV(35, 10, 36)", 'Z'],
            ['CONV negative base', "CONV(-1, 10, 16)", null],

            // bitwise & shifts
            ['bitwise NOT', '~0', null],
            ['bitwise AND chain', '(12 & 10) & 8', '8'],
            ['bitwise OR chain', '(1 | 2) | 4', '7'],
            ['bitwise XOR self', '15 ^ 15', '0'],
            ['shift left large', '1 << 10', '1024'],
            ['shift right to zero', '4 >> 5', '0'],

            // BIT_COUNT
            ['BIT_COUNT 255', 'BIT_COUNT(255)', '8'],
            ['BIT_COUNT 0', 'BIT_COUNT(0)', '0'],
            ['BIT_COUNT big', 'BIT_COUNT(1023)', '10'],

            // misc
            ['ROUND to even check', 'ROUND(0.5)', '1'],
            ['integer division DIV', '17 DIV 5', '3'],
            ['unary minus', '-(-5)', '5'],
            ['LEAST with null', 'ISNULL(LEAST(1, NULL, 3))', '1'],
        ];
    }

    // ------------------------------------------------------------------
    // Datetime functions
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function dates(): array
    {
        return [
            // DATE_ADD / DATE_SUB all interval units
            ['ADD MICROSECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL 500000 MICROSECOND)", '2026-01-01 00:00:00.500000'],
            ['ADD SECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL 90 SECOND)", '2026-01-01 00:01:30'],
            ['ADD MINUTE', "DATE_ADD('2026-01-01 00:00:00', INTERVAL 90 MINUTE)", '2026-01-01 01:30:00'],
            ['ADD HOUR', "DATE_ADD('2026-01-01 00:00:00', INTERVAL 25 HOUR)", '2026-01-02 01:00:00'],
            ['ADD DAY', "DATE_ADD('2026-01-31', INTERVAL 1 DAY)", '2026-02-01'],
            ['ADD WEEK', "DATE_ADD('2026-01-01', INTERVAL 2 WEEK)", '2026-01-15'],
            ['ADD MONTH overflow', "DATE_ADD('2026-01-31', INTERVAL 1 MONTH)", '2026-02-28'],
            ['ADD QUARTER', "DATE_ADD('2026-01-15', INTERVAL 1 QUARTER)", '2026-04-15'],
            ['ADD YEAR', "DATE_ADD('2026-06-23', INTERVAL 2 YEAR)", '2028-06-23'],
            ['SUB DAY', "DATE_SUB('2026-03-01', INTERVAL 1 DAY)", '2026-02-28'],
            ['SUB MONTH', "DATE_SUB('2026-03-31', INTERVAL 1 MONTH)", '2026-02-28'],
            ['SUB WEEK', "DATE_SUB('2026-01-15', INTERVAL 1 WEEK)", '2026-01-08'],
            // compound intervals
            ['ADD DAY_HOUR', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '1 2' DAY_HOUR)", '2026-01-02 02:00:00'],
            ['ADD DAY_MINUTE', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '1 02:30' DAY_MINUTE)", '2026-01-02 02:30:00'],
            ['ADD DAY_SECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '1 02:30:15' DAY_SECOND)", '2026-01-02 02:30:15'],
            ['ADD HOUR_MINUTE', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '2:30' HOUR_MINUTE)", '2026-01-01 02:30:00'],
            ['ADD HOUR_SECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '2:30:15' HOUR_SECOND)", '2026-01-01 02:30:15'],
            ['ADD MINUTE_SECOND', "DATE_ADD('2026-01-01 00:00:00', INTERVAL '30:15' MINUTE_SECOND)", '2026-01-01 00:30:15'],
            ['ADD YEAR_MONTH', "DATE_ADD('2026-01-01', INTERVAL '1-6' YEAR_MONTH)", '2027-07-01'],
            ['ADD negative interval', "DATE_ADD('2026-01-01', INTERVAL -1 DAY)", '2025-12-31'],

            // EXTRACT all units
            ['EXTRACT YEAR', "EXTRACT(YEAR FROM '2026-06-23 14:25:36')", '2026'],
            ['EXTRACT QUARTER', "EXTRACT(QUARTER FROM '2026-06-23')", '2'],
            ['EXTRACT MONTH', "EXTRACT(MONTH FROM '2026-06-23')", '6'],
            ['EXTRACT WEEK', "EXTRACT(WEEK FROM '2026-06-23')", null],
            ['EXTRACT DAY', "EXTRACT(DAY FROM '2026-06-23')", '23'],
            ['EXTRACT HOUR', "EXTRACT(HOUR FROM '2026-06-23 14:25:36')", '14'],
            ['EXTRACT MINUTE', "EXTRACT(MINUTE FROM '2026-06-23 14:25:36')", '25'],
            ['EXTRACT SECOND', "EXTRACT(SECOND FROM '2026-06-23 14:25:36')", '36'],
            ['EXTRACT MICROSECOND', "EXTRACT(MICROSECOND FROM '2026-06-23 14:25:36.123456')", '123456'],
            ['EXTRACT YEAR_MONTH', "EXTRACT(YEAR_MONTH FROM '2026-06-23')", '202606'],
            ['EXTRACT DAY_HOUR', "EXTRACT(DAY_HOUR FROM '2026-06-23 14:25:36')", '2314'],
            ['EXTRACT HOUR_MINUTE', "EXTRACT(HOUR_MINUTE FROM '2026-06-23 14:25:36')", '1425'],
            ['EXTRACT MINUTE_SECOND', "EXTRACT(MINUTE_SECOND FROM '2026-06-23 14:25:36')", '2536'],

            // DATE_FORMAT specifiers
            ['DATE_FORMAT %j', "DATE_FORMAT('2026-01-10', '%j')", '010'],
            ['DATE_FORMAT %U', "DATE_FORMAT('2026-06-23', '%U')", null],
            ['DATE_FORMAT %a', "DATE_FORMAT('2026-06-23', '%a')", null],
            ['DATE_FORMAT %b', "DATE_FORMAT('2026-06-23', '%b')", null],
            ['DATE_FORMAT %D', "DATE_FORMAT('2026-06-23', '%D')", null],
            ['DATE_FORMAT %r', "DATE_FORMAT('2026-06-23 14:05:09', '%r')", '02:05:09 PM'],
            ['DATE_FORMAT %T', "DATE_FORMAT('2026-06-23 14:05:09', '%T')", '14:05:09'],
            ['DATE_FORMAT %f', "DATE_FORMAT('2026-06-23 14:05:09.123456', '%f')", '123456'],
            ['DATE_FORMAT %k', "DATE_FORMAT('2026-06-23 04:05:09', '%k')", '4'],
            ['DATE_FORMAT %l %p', "DATE_FORMAT('2026-06-23 14:05:09', '%l %p')", '2 PM'],
            ['DATE_FORMAT %Y-%m-%d', "DATE_FORMAT('2026-06-23', '%Y-%m-%d')", '2026-06-23'],
            ['DATE_FORMAT %y', "DATE_FORMAT('2026-06-23', '%y')", '26'],

            // STR_TO_DATE forms
            ['STR_TO_DATE datetime', "STR_TO_DATE('23/06/2026 14:25', '%d/%m/%Y %H:%i')", '2026-06-23 14:25:00'],
            ['STR_TO_DATE month name', "STR_TO_DATE('June 23 2026', '%M %d %Y')", '2026-06-23'],
            ['STR_TO_DATE 12h', "STR_TO_DATE('02:05:09 PM', '%h:%i:%s %p')", '14:05:09'],
            ['STR_TO_DATE invalid null', "ISNULL(STR_TO_DATE('not a date', '%Y'))", '1'],

            // TIMESTAMPDIFF / TIMESTAMPADD units
            ['TIMESTAMPDIFF SECOND', "TIMESTAMPDIFF(SECOND, '2026-01-01 00:00:00', '2026-01-01 00:01:30')", '90'],
            ['TIMESTAMPDIFF MINUTE', "TIMESTAMPDIFF(MINUTE, '2026-01-01 00:00:00', '2026-01-01 02:00:00')", '120'],
            ['TIMESTAMPDIFF HOUR', "TIMESTAMPDIFF(HOUR, '2026-01-01 00:00:00', '2026-01-02 00:00:00')", '24'],
            ['TIMESTAMPDIFF WEEK', "TIMESTAMPDIFF(WEEK, '2026-01-01', '2026-01-22')", '3'],
            ['TIMESTAMPDIFF QUARTER', "TIMESTAMPDIFF(QUARTER, '2026-01-01', '2026-10-01')", '3'],
            ['TIMESTAMPADD MINUTE', "TIMESTAMPADD(MINUTE, 90, '2026-01-01 00:00:00')", '2026-01-01 01:30:00'],
            ['TIMESTAMPADD MONTH', "TIMESTAMPADD(MONTH, 2, '2026-01-31')", '2026-03-31'],
            ['TIMESTAMPADD WEEK', "TIMESTAMPADD(WEEK, 1, '2026-01-01')", '2026-01-08'],

            // WEEK modes 0-7
            ['WEEK mode 0', "WEEK('2026-01-04', 0)", null],
            ['WEEK mode 1', "WEEK('2026-01-04', 1)", null],
            ['WEEK mode 2', "WEEK('2026-01-04', 2)", null],
            ['WEEK mode 3', "WEEK('2026-01-04', 3)", null],
            ['WEEK mode 4', "WEEK('2026-01-04', 4)", null],
            ['WEEK mode 5', "WEEK('2026-01-04', 5)", null],
            ['WEEK mode 6', "WEEK('2026-01-04', 6)", null],
            ['WEEK mode 7', "WEEK('2026-01-04', 7)", null],

            // YEARWEEK, DAYNAME, MONTHNAME
            ['YEARWEEK mode 0', "YEARWEEK('2026-01-01', 0)", null],
            ['YEARWEEK mode 3', "YEARWEEK('2026-01-01', 3)", null],
            ['DAYNAME Tuesday', "DAYNAME('2026-06-23')", 'Tuesday'],
            ['MONTHNAME June', "MONTHNAME('2026-06-23')", 'June'],

            // GET_FORMAT
            ['GET_FORMAT DATE USA', "GET_FORMAT(DATE, 'USA')", null],
            ['GET_FORMAT DATETIME ISO', "GET_FORMAT(DATETIME, 'ISO')", null],
            ['GET_FORMAT TIME EUR', "GET_FORMAT(TIME, 'EUR')", null],
            ['STR_TO_DATE with GET_FORMAT', "STR_TO_DATE('06.23.2026', GET_FORMAT(DATE, 'USA'))", '2026-06-23'],

            // CONVERT_TZ
            ['CONVERT_TZ utc to plus8', "CONVERT_TZ('2026-01-01 12:00:00', '+00:00', '+08:00')", '2026-01-01 20:00:00'],
            ['CONVERT_TZ wrap day', "CONVERT_TZ('2026-01-01 20:00:00', '+00:00', '+08:00')", '2026-01-02 04:00:00'],

            // MAKEDATE / MAKETIME
            ['MAKEDATE day 1', 'MAKEDATE(2026, 1)', '2026-01-01'],
            ['MAKEDATE day 365', 'MAKEDATE(2025, 365)', '2025-12-31'],
            ['MAKETIME', 'MAKETIME(13, 30, 45)', '13:30:45'],
            ['MAKETIME over 24h', 'MAKETIME(25, 0, 0)', '25:00:00'],

            // FROM_UNIXTIME / UNIX_TIMESTAMP
            ['UNIX_TIMESTAMP epoch', "UNIX_TIMESTAMP('1970-01-01 00:00:00')", null],
            ['FROM_UNIXTIME format', "FROM_UNIXTIME(1000000000, '%Y-%m-%d')", '2001-09-09'],
            ['UNIX roundtrip', "FROM_UNIXTIME(UNIX_TIMESTAMP('2026-06-23 12:00:00'))", '2026-06-23 12:00:00'],

            // ADDTIME / SUBTIME / TIMEDIFF
            ['ADDTIME with date', "ADDTIME('2026-01-01 23:59:59', '00:00:02')", '2026-01-02 00:00:01'],
            ['SUBTIME', "SUBTIME('2026-01-01 00:00:01', '00:00:02')", '2025-12-31 23:59:59'],
            ['TIMEDIFF wrapped', "TIME_FORMAT(TIMEDIFF('2026-01-01 10:00:00', '2026-01-01 08:30:00'), '%H:%i:%s')", '01:30:00'],
            ['TIMEDIFF negative', "TIME_FORMAT(TIMEDIFF('08:00:00', '10:00:00'), '%H:%i:%s')", null],

            // LAST_DAY across months & leap years
            ['LAST_DAY Jan', "LAST_DAY('2026-01-15')", '2026-01-31'],
            ['LAST_DAY Apr', "LAST_DAY('2026-04-15')", '2026-04-30'],
            ['LAST_DAY Feb non-leap', "LAST_DAY('2026-02-15')", '2026-02-28'],
            ['LAST_DAY Feb leap', "LAST_DAY('2024-02-15')", '2024-02-29'],
            ['LAST_DAY Dec', "LAST_DAY('2026-12-01')", '2026-12-31'],

            // leap-year handling
            ['leap DATE_ADD across', "DATE_ADD('2024-02-28', INTERVAL 1 DAY)", '2024-02-29'],
            ['non-leap DATE_ADD across', "DATE_ADD('2026-02-28', INTERVAL 1 DAY)", '2026-03-01'],
            ['DAYOFYEAR leap day', "DAYOFYEAR('2024-12-31')", '366'],
        ];
    }

    // ------------------------------------------------------------------
    // JSON functions
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function json(): array
    {
        return [
            // JSON_EXTRACT deep / array paths
            ['JSON_EXTRACT deep', "JSON_UNQUOTE(JSON_EXTRACT('{\"a\":{\"b\":{\"c\":42}}}', '$.a.b.c'))", '42'],
            ['JSON_EXTRACT array idx', "JSON_EXTRACT('[10,20,30]', '$[2]')", '30'],
            ['JSON_EXTRACT nested array', "JSON_EXTRACT('{\"x\":[1,2,3]}', '$.x[1]')", '2'],
            ['JSON_EXTRACT wildcard', "JSON_EXTRACT('{\"a\":1,\"b\":2}', '$.*')", null],
            ['JSON_EXTRACT multi path', "JSON_EXTRACT('[1,2,3]', '$[0]', '$[2]')", null],

            // JSON_UNQUOTE
            ['JSON_UNQUOTE escaped', "JSON_UNQUOTE('\"a\\\\nb\"')", null],
            ['JSON_UNQUOTE plain', "JSON_UNQUOTE('\"hello\"')", 'hello'],

            // JSON_OBJECT / JSON_ARRAY nesting
            ['JSON_OBJECT nested', "JSON_EXTRACT(JSON_OBJECT('o', JSON_OBJECT('k', 1)), '$.o.k')", '1'],
            ['JSON_ARRAY nested', "JSON_EXTRACT(JSON_ARRAY(1, JSON_ARRAY(2, 3)), '$[1][0]')", '2'],
            ['JSON_OBJECT multi keys', "JSON_LENGTH(JSON_OBJECT('a',1,'b',2,'c',3))", '3'],

            // JSON_VALID
            ['JSON_VALID true', "JSON_VALID('{\"a\":1}')", '1'],
            ['JSON_VALID false', "JSON_VALID('{not json}')", '0'],
            ['JSON_VALID array', "JSON_VALID('[1,2,3]')", '1'],

            // JSON_TYPE
            ['JSON_TYPE object', "JSON_TYPE('{\"a\":1}')", 'OBJECT'],
            ['JSON_TYPE array', "JSON_TYPE('[1,2]')", 'ARRAY'],
            ['JSON_TYPE integer', "JSON_TYPE('5')", 'INTEGER'],
            ['JSON_TYPE string', "JSON_TYPE('\"s\"')", 'STRING'],
            ['JSON_TYPE boolean', "JSON_TYPE('true')", 'BOOLEAN'],
            ['JSON_TYPE null', "JSON_TYPE('null')", 'NULL'],

            // JSON_KEYS / JSON_LENGTH
            ['JSON_KEYS', "JSON_KEYS('{\"a\":1,\"b\":2}')", null],
            ['JSON_KEYS path', "JSON_KEYS('{\"o\":{\"x\":1,\"y\":2}}', '$.o')", null],
            ['JSON_LENGTH array', "JSON_LENGTH('[1,2,3,4]')", '4'],
            ['JSON_LENGTH object', "JSON_LENGTH('{\"a\":1,\"b\":2}')", '2'],
            ['JSON_LENGTH path', "JSON_LENGTH('{\"o\":[1,2]}', '$.o')", '2'],

            // JSON_SET / INSERT / REPLACE
            ['JSON_SET existing', "JSON_EXTRACT(JSON_SET('{\"a\":1}', '$.a', 9), '$.a')", '9'],
            ['JSON_SET new', "JSON_EXTRACT(JSON_SET('{\"a\":1}', '$.b', 2), '$.b')", '2'],
            ['JSON_INSERT no overwrite', "JSON_EXTRACT(JSON_INSERT('{\"a\":1}', '$.a', 9), '$.a')", '1'],
            ['JSON_INSERT new', "JSON_EXTRACT(JSON_INSERT('{\"a\":1}', '$.b', 5), '$.b')", '5'],
            ['JSON_REPLACE existing', "JSON_EXTRACT(JSON_REPLACE('{\"a\":1}', '$.a', 7), '$.a')", '7'],
            ['JSON_REPLACE missing', "JSON_CONTAINS_PATH(JSON_REPLACE('{\"a\":1}', '$.b', 7), 'one', '$.b')", '0'],

            // JSON_CONTAINS / CONTAINS_PATH
            ['JSON_CONTAINS scalar', "JSON_CONTAINS('[1,2,3]', '2')", '1'],
            ['JSON_CONTAINS missing', "JSON_CONTAINS('[1,2,3]', '9')", '0'],
            ['JSON_CONTAINS object', "JSON_CONTAINS('{\"a\":1,\"b\":2}', '1', '$.a')", '1'],
            ['JSON_CONTAINS_PATH one', "JSON_CONTAINS_PATH('{\"a\":1,\"b\":2}', 'one', '$.c', '$.a')", '1'],
            ['JSON_CONTAINS_PATH all', "JSON_CONTAINS_PATH('{\"a\":1,\"b\":2}', 'all', '$.a', '$.b')", '1'],

            // JSON_DEPTH
            ['JSON_DEPTH flat', "JSON_DEPTH('[1,2,3]')", '2'],
            ['JSON_DEPTH scalar', "JSON_DEPTH('5')", '1'],
            ['JSON_DEPTH nested', "JSON_DEPTH('{\"a\":{\"b\":1}}')", '3'],

            // JSON_REMOVE
            ['JSON_REMOVE key', "JSON_CONTAINS_PATH(JSON_REMOVE('{\"a\":1,\"b\":2}', '$.b'), 'one', '$.b')", '0'],
            ['JSON_REMOVE array elem', "JSON_LENGTH(JSON_REMOVE('[1,2,3]', '$[1]'))", '2'],

            // JSON_MERGE_*
            ['JSON_MERGE_PATCH override', "JSON_EXTRACT(JSON_MERGE_PATCH('{\"a\":1}', '{\"a\":2}'), '$.a')", '2'],
            ['JSON_MERGE_PATCH combine', "JSON_LENGTH(JSON_MERGE_PATCH('{\"a\":1}', '{\"b\":2}'))", '2'],
            ['JSON_MERGE_PRESERVE arrays', "JSON_LENGTH(JSON_MERGE_PRESERVE('[1,2]', '[3,4]'))", '4'],
            ['JSON_MERGE_PRESERVE dup keys', "JSON_LENGTH(JSON_MERGE_PRESERVE('{\"a\":1}', '{\"a\":2}'), '$.a')", '2'],

            // JSON_ARRAY_APPEND / JSON_ARRAY_INSERT
            ['JSON_ARRAY_APPEND', "JSON_LENGTH(JSON_ARRAY_APPEND('[1,2]', '$', 3))", '3'],
            ['JSON_ARRAY_INSERT', "JSON_EXTRACT(JSON_ARRAY_INSERT('[1,3]', '$[1]', 2), '$[1]')", '2'],

            // JSON_SEARCH
            ['JSON_SEARCH one', "JSON_UNQUOTE(JSON_SEARCH('[\"a\",\"b\",\"c\"]', 'one', 'b'))", '$[1]'],
            ['JSON_SEARCH all', "JSON_SEARCH('[\"x\",\"x\"]', 'all', 'x')", null],
            ['JSON_SEARCH not found', "ISNULL(JSON_SEARCH('[\"a\"]', 'one', 'z'))", '1'],
            ['JSON_SEARCH wildcard', "JSON_SEARCH('{\"k\":\"abc\"}', 'one', 'a%c')", null],

            // JSON_OVERLAPS
            ['JSON_OVERLAPS true', "JSON_OVERLAPS('[1,2,3]', '[3,4,5]')", '1'],
            ['JSON_OVERLAPS false', "JSON_OVERLAPS('[1,2]', '[3,4]')", '0'],

            // JSON_PRETTY / JSON_QUOTE
            ['JSON_PRETTY', "JSON_VALID(JSON_PRETTY('{\"a\":1}'))", '1'],
            ['JSON_QUOTE escapes', "JSON_QUOTE('he\"llo')", null],
            ['JSON_QUOTE unquote roundtrip', "JSON_UNQUOTE(JSON_QUOTE('x'))", 'x'],

            // MEMBER OF / JSON_VALUE
            ['MEMBER OF true', "2 MEMBER OF('[1,2,3]')", '1'],
            ['MEMBER OF false', "9 MEMBER OF('[1,2,3]')", '0'],
            ['JSON_VALUE', "JSON_VALUE('{\"a\":42}', '$.a')", '42'],

            // JSON_ARRAYAGG / JSON_OBJECTAGG over derived table
            ['JSON_ARRAYAGG', "(SELECT JSON_LENGTH(JSON_ARRAYAGG(n)) FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3) t)", '3'],
            ['JSON_OBJECTAGG', "(SELECT JSON_LENGTH(JSON_OBJECTAGG(k, v)) FROM (SELECT 'a' k, 1 v UNION ALL SELECT 'b', 2) t)", '2'],
        ];
    }

    // ------------------------------------------------------------------
    // Comparison operators & functions
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function comparison(): array
    {
        return [
            // IN / NOT IN
            ['IN list match', '3 IN (1, 2, 3)', '1'],
            ['IN list miss', '5 IN (1, 2, 3)', '0'],
            ['NOT IN', '5 NOT IN (1, 2, 3)', '1'],
            ['IN strings', "'b' IN ('a', 'b', 'c')", '1'],
            ['IN with null unknown', "ISNULL(NULLIF(5 IN (1, NULL), 0))", '1'],

            // BETWEEN
            ['BETWEEN inclusive', '5 BETWEEN 1 AND 10', '1'],
            ['BETWEEN edge low', '1 BETWEEN 1 AND 10', '1'],
            ['BETWEEN edge high', '10 BETWEEN 1 AND 10', '1'],
            ['NOT BETWEEN', '11 NOT BETWEEN 1 AND 10', '1'],
            ['BETWEEN strings', "'m' BETWEEN 'a' AND 'z'", '1'],
            ['BETWEEN dates', "'2026-06-15' BETWEEN '2026-01-01' AND '2026-12-31'", '1'],

            // <=> null-safe
            ['null-safe both null', 'NULL <=> NULL', '1'],
            ['null-safe one null', '1 <=> NULL', '0'],
            ['null-safe equal', '5 <=> 5', '1'],
            ['null-safe unequal', '5 <=> 6', '0'],

            // IS NULL / ISNULL
            ['IS NULL true', 'NULL IS NULL', '1'],
            ['IS NOT NULL', '1 IS NOT NULL', '1'],
            ['ISNULL func', 'ISNULL(NULL)', '1'],
            ['ISNULL on value', 'ISNULL(0)', '0'],

            // LIKE / NOT LIKE with ESCAPE
            ['LIKE underscore', "'abc' LIKE 'a_c'", '1'],
            ['LIKE escape literal pct', "'50%' LIKE '50!%' ESCAPE '!'", '1'],
            ['LIKE escape underscore', "'a_b' LIKE 'a!_b' ESCAPE '!'", '1'],
            ['NOT LIKE', "'abc' NOT LIKE 'x%'", '1'],
            ['LIKE case insensitive', "'ABC' LIKE 'abc'", '1'],

            // boolean operators
            ['AND both true', '1 AND 1', '1'],
            ['OR one true', '0 OR 1', '1'],
            ['XOR', '1 XOR 0', '1'],
            ['NOT', 'NOT 0', '1'],
            ['AND with null', 'ISNULL(1 AND NULL)', '1'],
            ['OR short circuit', '1 OR NULL', '1'],

            // comparison ops
            ['equal', '5 = 5', '1'],
            ['not equal bang', '5 != 6', '1'],
            ['not equal arrow', '5 <> 6', '1'],
            ['less than', '3 < 5', '1'],
            ['greater equal', '5 >= 5', '1'],
            ['string compare lt', "'apple' < 'banana'", '1'],

            // INTERVAL() (already in math-ish but place comparison ones here)
            ['INTERVAL boundary', 'INTERVAL(3, 1, 3, 5)', '2'],

            // GREATEST/LEAST with NULL
            ['GREATEST with null', 'ISNULL(GREATEST(1, NULL, 3))', '1'],
            ['LEAST with null', 'ISNULL(LEAST(1, NULL, 3))', '1'],
            ['COALESCE skips null', 'COALESCE(NULL, NULL, 7, 8)', '7'],
        ];
    }

    // ------------------------------------------------------------------
    // Conditional functions
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function conditional(): array
    {
        return [
            // CASE forms
            ['CASE simple match', "CASE 2 WHEN 1 THEN 'a' WHEN 2 THEN 'b' END", 'b'],
            ['CASE simple else', "CASE 9 WHEN 1 THEN 'a' ELSE 'z' END", 'z'],
            ['CASE searched', "CASE WHEN 1>2 THEN 'a' WHEN 2>1 THEN 'b' END", 'b'],
            ['CASE no match null', "ISNULL(CASE WHEN 1>2 THEN 'a' END)", '1'],
            ['CASE nested', "CASE WHEN 1=1 THEN CASE WHEN 2=2 THEN 'deep' END END", 'deep'],
            ['CASE with strings', "CASE 'x' WHEN 'y' THEN 1 WHEN 'x' THEN 2 END", '2'],

            // IF
            ['IF true', "IF(1, 'yes', 'no')", 'yes'],
            ['IF false', "IF(0, 'yes', 'no')", 'no'],
            ['IF null cond', "IF(NULL, 'yes', 'no')", 'no'],
            ['IF nested', "IF(1>2, 'a', IF(3>2, 'b', 'c'))", 'b'],
            ['IF numeric expr', 'IF(5 > 3, 100, 200)', '100'],

            // IFNULL
            ['IFNULL first null', 'IFNULL(NULL, 42)', '42'],
            ['IFNULL first set', 'IFNULL(7, 42)', '7'],
            ['IFNULL chain', 'IFNULL(NULL, IFNULL(NULL, 3))', '3'],

            // NULLIF
            ['NULLIF equal null', 'ISNULL(NULLIF(5, 5))', '1'],
            ['NULLIF unequal', 'NULLIF(5, 4)', '5'],
            ['NULLIF strings equal', "ISNULL(NULLIF('a', 'a'))", '1'],

            // COALESCE chains
            ['COALESCE first', 'COALESCE(1, 2, 3)', '1'],
            ['COALESCE middle', 'COALESCE(NULL, 2, 3)', '2'],
            ['COALESCE all null', 'ISNULL(COALESCE(NULL, NULL, NULL))', '1'],
            ['COALESCE mixed types', "COALESCE(NULL, 'fallback')", 'fallback'],

            // IF + comparison combos
            ['IF with IN', "IF(2 IN (1,2,3), 'in', 'out')", 'in'],
            ['IF with BETWEEN', "IF(5 BETWEEN 1 AND 10, 'mid', 'edge')", 'mid'],
        ];
    }

    // ------------------------------------------------------------------
    // CAST / CONVERT targets
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function casts(): array
    {
        return [
            ['CAST SIGNED negative', "CAST('-99' AS SIGNED)", '-99'],
            ['CAST SIGNED from float', 'CAST(3.9 AS SIGNED)', '4'],
            ['CAST UNSIGNED', "CAST('123' AS UNSIGNED)", '123'],
            ['CAST UNSIGNED wrap', 'CAST(-1 AS UNSIGNED)', null],
            ['CAST CHAR no length', 'CAST(98765 AS CHAR)', '98765'],
            ['CAST CHAR(n) truncate', 'CAST(123456 AS CHAR(3))', null],
            ['CAST DECIMAL p s', "CAST('3.14159' AS DECIMAL(6,3))", '3.142'],
            ['CAST DECIMAL rounding', "CAST('2.345' AS DECIMAL(4,2))", '2.35'],
            ['CAST DECIMAL zero scale', "CAST('5.9' AS DECIMAL(3,0))", '6'],
            ['CAST DATE', "CAST('2026-06-23 14:00:00' AS DATE)", '2026-06-23'],
            ['CAST DATETIME', "CAST('2026-06-23' AS DATETIME)", '2026-06-23 00:00:00'],
            ['CAST TIME', "CAST('14:30:00' AS TIME)", '14:30:00'],
            ['CAST JSON valid', "JSON_VALID(CAST('[1,2,3]' AS JSON))", '1'],
            ['CAST BINARY', "CAST('xyz' AS BINARY)", 'xyz'],
            ['CAST DOUBLE', "CAST('2.5' AS DOUBLE)", '2.5'],
            ['CAST FLOAT', "CAST('1.25' AS FLOAT)", '1.25'],
            ['CAST NCHAR', "CAST('abc' AS NCHAR)", 'abc'],
            ['CAST CHAR charset', "CAST('abc' AS CHAR CHARACTER SET utf8mb4)", 'abc'],

            ['CONVERT SIGNED', "CONVERT('-7', SIGNED)", '-7'],
            ['CONVERT UNSIGNED', "CONVERT('15', UNSIGNED)", '15'],
            ['CONVERT CHAR', 'CONVERT(456, CHAR)', '456'],
            ['CONVERT CHAR(n)', 'CONVERT(123456, CHAR(4))', null],
            ['CONVERT DECIMAL', "CONVERT('9.876', DECIMAL(5,2))", '9.88'],
            ['CONVERT DATE', "CONVERT('2026-06-23', DATE)", '2026-06-23'],
            ['CONVERT DATETIME', "CONVERT('2026-06-23 01:02:03', DATETIME)", '2026-06-23 01:02:03'],
            ['CONVERT TIME', "CONVERT('01:02:03', TIME)", '01:02:03'],
            ['CONVERT DOUBLE', "CONVERT('3.5', DOUBLE)", '3.5'],
            ['CONVERT BINARY', "CONVERT('q', BINARY)", 'q'],

            // CONVERT USING charset
            ['CONVERT USING utf8mb4', "CONVERT('héllo' USING utf8mb4)", 'héllo'],
            ['CONVERT USING latin1', "CONVERT('abc' USING latin1)", 'abc'],
            ['CONVERT USING ascii', "CONVERT('abc' USING ascii)", 'abc'],

            // BINARY operator
            ['BINARY operator case sensitive', "BINARY 'a' = 'A'", '0'],
            ['BINARY operator string', "BINARY 'abc'", 'abc'],

            // CAST chained / edge
            ['CAST then arithmetic', "CAST('10' AS SIGNED) + 5", '15'],
            ['CAST bool true', 'CAST(TRUE AS SIGNED)', '1'],
            ['CAST high precision DECIMAL', "CAST('12345678901234.5678' AS DECIMAL(20,4))", '12345678901234.5678'],
        ];
    }

    // ------------------------------------------------------------------
    // Bit functions
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function bit(): array
    {
        return [
            ['BIT_COUNT of 1', 'BIT_COUNT(1)', '1'],
            ['BIT_COUNT of 7', 'BIT_COUNT(7)', '3'],
            ['BIT_COUNT of 256', 'BIT_COUNT(256)', '1'],
            ['AND mask', '0xFF & 0x0F', '15'],
            ['OR combine flags', '0x01 | 0x10', '17'],
            ['XOR toggle', '0xFF ^ 0x0F', '240'],
            ['NOT then mask', '(~0) & 0xFF', '255'],
            ['left shift compound', '(3 << 4) | 1', '49'],
            ['right shift mask', '(255 >> 4) & 0x0F', '15'],
            ['bit roundtrip', '((1 << 5) >> 5)', '1'],
            ['nested bit ops', '((6 & 12) | (3 ^ 1))', '6'],
            ['BIT_COUNT max byte', 'BIT_COUNT(0xFF)', '8'],
        ];
    }

    // ------------------------------------------------------------------
    // Aggregate functions (over inline derived table, scalar-wrapped)
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function aggregate(): array
    {
        // n in {2,4,6,8}, g alternates 'x'/'y'
        $src = "(SELECT 2 AS n, 'x' AS g UNION ALL SELECT 4, 'y' UNION ALL SELECT 6, 'x' UNION ALL SELECT 8, 'y') t";
        return [
            ['COUNT star', "(SELECT COUNT(*) FROM $src)", '4'],
            ['COUNT col', "(SELECT COUNT(n) FROM $src)", '4'],
            ['COUNT DISTINCT g', "(SELECT COUNT(DISTINCT g) FROM $src)", '2'],
            ['SUM', "(SELECT SUM(n) FROM $src)", '20'],
            ['AVG', "(SELECT AVG(n) FROM $src)", '5'],
            ['MIN', "(SELECT MIN(n) FROM $src)", '2'],
            ['MAX', "(SELECT MAX(n) FROM $src)", '8'],
            ['MIN string', "(SELECT MIN(g) FROM $src)", 'x'],
            ['MAX string', "(SELECT MAX(g) FROM $src)", 'y'],
            ['GROUP_CONCAT ordered', "(SELECT GROUP_CONCAT(n ORDER BY n) FROM $src)", '2,4,6,8'],
            ['GROUP_CONCAT desc sep', "(SELECT GROUP_CONCAT(n ORDER BY n DESC SEPARATOR '|') FROM $src)", '8|6|4|2'],
            ['GROUP_CONCAT DISTINCT', "(SELECT GROUP_CONCAT(DISTINCT g ORDER BY g) FROM $src)", 'x,y'],
            ['STD', "(SELECT ROUND(STD(n), 5) FROM $src)", '2.23607'],
            ['STDDEV', "(SELECT ROUND(STDDEV(n), 5) FROM $src)", '2.23607'],
            ['STDDEV_POP', "(SELECT ROUND(STDDEV_POP(n), 5) FROM $src)", '2.23607'],
            ['STDDEV_SAMP', "(SELECT ROUND(STDDEV_SAMP(n), 5) FROM $src)", '2.58199'],
            ['VAR_POP', "(SELECT VAR_POP(n) FROM $src)", '5'],
            ['VAR_SAMP', "(SELECT ROUND(VAR_SAMP(n), 5) FROM $src)", '6.66667'],
            ['VARIANCE', "(SELECT VARIANCE(n) FROM $src)", '5'],
            ['BIT_AND', "(SELECT BIT_AND(n) FROM $src)", '0'],
            ['BIT_OR', "(SELECT BIT_OR(n) FROM $src)", '14'],
            ['BIT_XOR', "(SELECT BIT_XOR(n) FROM $src)", '8'],
            ['ANY_VALUE', "(SELECT ANY_VALUE(n) IN (2,4,6,8) FROM $src)", '1'],
            ['SUM with CASE', "(SELECT SUM(CASE WHEN g='x' THEN n ELSE 0 END) FROM $src)", '8'],
            ['COUNT filtered', "(SELECT COUNT(*) FROM $src WHERE n > 4)", '2'],
            ['AVG of group', "(SELECT AVG(n) FROM $src WHERE g='y')", '6'],
        ];
    }

    // ------------------------------------------------------------------
    // Window functions (over inline derived table, scalar-wrapped)
    // ------------------------------------------------------------------

    /** @return array<int,array{0:string,1:string,2?:?string}> */
    private static function window(): array
    {
        $src = "(SELECT 1 AS n, 'a' AS g UNION ALL SELECT 2, 'a' UNION ALL SELECT 3, 'b' UNION ALL SELECT 4, 'b' UNION ALL SELECT 5, 'b') t";
        return [
            ['ROW_NUMBER count', "(SELECT MAX(rn) FROM (SELECT ROW_NUMBER() OVER (ORDER BY n) rn FROM $src) z)", '5'],
            ['ROW_NUMBER partition', "(SELECT MAX(rn) FROM (SELECT ROW_NUMBER() OVER (PARTITION BY g ORDER BY n) rn FROM $src) z)", '3'],
            ['RANK with ties', "(SELECT MAX(r) FROM (SELECT RANK() OVER (ORDER BY g) r FROM $src) z)", '3'],
            ['DENSE_RANK', "(SELECT MAX(r) FROM (SELECT DENSE_RANK() OVER (ORDER BY g) r FROM $src) z)", '2'],
            ['NTILE 2', "(SELECT MAX(t2) FROM (SELECT NTILE(2) OVER (ORDER BY n) t2 FROM $src) z)", '2'],
            ['NTILE 5', "(SELECT MAX(t5) FROM (SELECT NTILE(5) OVER (ORDER BY n) t5 FROM $src) z)", '5'],
            ['LAG default', "(SELECT SUM(l) FROM (SELECT LAG(n) OVER (ORDER BY n) l FROM $src) z)", '10'],
            ['LAG offset 2', "(SELECT SUM(l) FROM (SELECT LAG(n, 2, 0) OVER (ORDER BY n) l FROM $src) z)", '6'],
            ['LEAD default', "(SELECT SUM(l) FROM (SELECT LEAD(n) OVER (ORDER BY n) l FROM $src) z)", '14'],
            ['LEAD offset default', "(SELECT SUM(l) FROM (SELECT LEAD(n, 1, 0) OVER (ORDER BY n) l FROM $src) z)", '14'],
            ['FIRST_VALUE', "(SELECT MIN(f) FROM (SELECT FIRST_VALUE(n) OVER (ORDER BY n) f FROM $src) z)", '1'],
            ['LAST_VALUE frame', "(SELECT MAX(f) FROM (SELECT LAST_VALUE(n) OVER (ORDER BY n ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) f FROM $src) z)", '5'],
            ['NTH_VALUE 2', "(SELECT MAX(f) FROM (SELECT NTH_VALUE(n, 2) OVER (ORDER BY n ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) f FROM $src) z)", '2'],
            ['CUME_DIST max', "(SELECT MAX(c) FROM (SELECT CUME_DIST() OVER (ORDER BY n) c FROM $src) z)", '1'],
            ['PERCENT_RANK first', "(SELECT MIN(c) FROM (SELECT PERCENT_RANK() OVER (ORDER BY n) c FROM $src) z)", '0'],
            ['SUM OVER total', "(SELECT MAX(s) FROM (SELECT SUM(n) OVER (ORDER BY n) s FROM $src) z)", '15'],
            ['SUM OVER partition', "(SELECT MAX(s) FROM (SELECT SUM(n) OVER (PARTITION BY g) s FROM $src) z)", '12'],
            ['AVG OVER partition', "(SELECT MAX(a) FROM (SELECT AVG(n) OVER (PARTITION BY g) a FROM $src) z)", '4'],
            ['SUM ROWS frame', "(SELECT MAX(s) FROM (SELECT SUM(n) OVER (ORDER BY n ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) s FROM $src) z)", '12'],
            ['SUM RANGE frame', "(SELECT MAX(s) FROM (SELECT SUM(n) OVER (ORDER BY n RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) s FROM $src) z)", '15'],
            ['COUNT OVER', "(SELECT MAX(c) FROM (SELECT COUNT(*) OVER () c FROM $src) z)", '5'],
            ['MIN OVER partition', "(SELECT MIN(m) FROM (SELECT MIN(n) OVER (PARTITION BY g) m FROM $src) z)", '1'],
            ['MAX OVER partition', "(SELECT MAX(m) FROM (SELECT MAX(n) OVER (PARTITION BY g) m FROM $src) z)", '5'],
            ['ROW_NUMBER desc', "(SELECT MIN(rn) FROM (SELECT ROW_NUMBER() OVER (ORDER BY n DESC) rn FROM $src) z)", '1'],
        ];
    }

    // ------------------------------------------------------------------
    // JSON column operators ( -> and ->> ) on a real table
    // ------------------------------------------------------------------

    private static function jsonColumnOps(Runner $r): void
    {
        $db = Config::database('pdo');
        // [name, selectExpr-on-column-j, expectedOrNull]
        $cases = [
            ['arrow extract scalar', "j -> '$.a'", '1'],
            ['arrow extract nested', "j -> '$.nested.x'", '5'],
            ['arrow extract array', "j -> '$.arr[0]'", '10'],
            ['arrow-unquote string', "j ->> '$.name'", 'mo'],
            ['arrow-unquote nested', "j ->> '$.nested.x'", '5'],
            ['arrow-unquote array elem', "j ->> '$.arr[1]'", '20'],
            ['JSON_EXTRACT on column', "JSON_EXTRACT(j, '$.a')", '1'],
            ['JSON_KEYS on column', "JSON_LENGTH(JSON_KEYS(j))", null],
            ['JSON_LENGTH arr on column', "JSON_LENGTH(j -> '$.arr')", '3'],
            ['JSON_CONTAINS on column', "JSON_CONTAINS(j -> '$.arr', '20')", '1'],
        ];
        foreach ($cases as [$name, $expr, $expect]) {
            $r->add('PDO', 'function:json', 'col ' . $name, function () use ($db, $expr, $expect) {
                $pdo = Connections::pdo($db);
                $tn = Support::name('jcol');
                $pdo->exec("CREATE TABLE `$tn` (id INT PRIMARY KEY, j JSON)");
                try {
                    $doc = '{"a":1,"name":"mo","nested":{"x":5},"arr":[10,20,30]}';
                    $st = $pdo->prepare("INSERT INTO `$tn`(id, j) VALUES (1, ?)");
                    $st->execute([$doc]);
                    $sql = "SELECT $expr AS v FROM `$tn` WHERE id = 1";
                    $got = $pdo->query($sql)->fetchColumn();
                    if ($expect !== null) {
                        Support::assertValueEquals($expect, $got, $expr);
                    } else {
                        Support::assert($got !== null && $got !== false, 'returned NULL/false');
                    }
                    return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
                } finally {
                    $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }
}
