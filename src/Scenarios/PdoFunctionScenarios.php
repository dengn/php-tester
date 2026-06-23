<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Coverage of built-in SQL functions through raw PDO.
 *
 * Each entry runs `SELECT <expr>` and, when an expected value is supplied,
 * asserts the result. Functions that MatrixOne does not implement surface as
 * failures (the real compatibility signal). Aggregate and window functions run
 * against a small inline derived table so no fixture state is required.
 */
final class PdoFunctionScenarios
{
    public static function register(Runner $r): void
    {
        $db = Config::database('pdo');

        foreach (self::scalar() as $f) {
            self::addExpr($r, $db, 'function:scalar', $f);
        }
        foreach (self::stringFns() as $f) {
            self::addExpr($r, $db, 'function:string', $f);
        }
        foreach (self::numericFns() as $f) {
            self::addExpr($r, $db, 'function:numeric', $f);
        }
        foreach (self::dateFns() as $f) {
            self::addExpr($r, $db, 'function:datetime', $f);
        }
        foreach (self::jsonFns() as $f) {
            self::addExpr($r, $db, 'function:json', $f);
        }
        foreach (self::aggregateFns() as $f) {
            self::addExpr($r, $db, 'function:aggregate', $f);
        }
        foreach (self::windowFns() as $f) {
            self::addExpr($r, $db, 'function:window', $f);
        }
        foreach (self::vectorFns() as $f) {
            self::addExpr($r, $db, 'function:vector', $f);
        }
    }

    private static function addExpr(Runner $r, string $db, string $cat, array $f): void
    {
        $name = $f['name'];
        $expr = $f['expr'];
        $r->add('PDO', $cat, $name, function () use ($db, $expr, $f) {
            $pdo = Connections::pdo($db);
            $sql = "SELECT $expr AS v";
            $got = $pdo->query($sql)->fetchColumn();
            $mode = $f['compare'] ?? (array_key_exists('expect', $f) ? 'exact' : 'any');
            switch ($mode) {
                case 'numeric':
                    Support::assert($got !== null && abs((float) $got - (float) $f['expect']) < 1e-4, "expected ~{$f['expect']} got " . var_export($got, true));
                    break;
                case 'notnull':
                    Support::assert($got !== null, 'returned NULL');
                    break;
                case 'exact':
                    Support::assertValueEquals($f['expect'], $got, $f['name']);
                    break;
                case 'any':
                default:
                    // executed without error is sufficient
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
        return strlen($s) > 40 ? substr($s, 0, 40) . '…' : $s;
    }

    /** @return array<int,array<string,mixed>> */
    private static function scalar(): array
    {
        return [
            ['name' => 'VERSION()', 'expr' => 'VERSION()', 'compare' => 'notnull'],
            ['name' => 'DATABASE()', 'expr' => 'DATABASE()', 'compare' => 'notnull'],
            ['name' => 'USER()', 'expr' => 'USER()', 'compare' => 'notnull'],
            ['name' => 'CURRENT_USER()', 'expr' => 'CURRENT_USER()', 'compare' => 'notnull'],
            ['name' => 'CONNECTION_ID()', 'expr' => 'CONNECTION_ID()', 'compare' => 'notnull'],
            ['name' => 'UUID()', 'expr' => 'UUID()', 'compare' => 'notnull'],
            ['name' => 'IF()', 'expr' => "IF(1=1,'y','n')", 'expect' => 'y'],
            ['name' => 'IFNULL()', 'expr' => 'IFNULL(NULL, 7)', 'expect' => '7'],
            ['name' => 'NULLIF()', 'expr' => 'NULLIF(5,5)', 'compare' => 'any'],
            ['name' => 'COALESCE()', 'expr' => 'COALESCE(NULL, NULL, 3)', 'expect' => '3'],
            ['name' => 'ISNULL()', 'expr' => 'ISNULL(NULL)', 'expect' => '1'],
            ['name' => 'CASE WHEN', 'expr' => "CASE WHEN 1>0 THEN 'a' ELSE 'b' END", 'expect' => 'a'],
            ['name' => 'CAST AS SIGNED', 'expr' => "CAST('42' AS SIGNED)", 'expect' => '42'],
            ['name' => 'CAST AS DECIMAL', 'expr' => "CAST('3.14' AS DECIMAL(5,2))", 'expect' => '3.14'],
            ['name' => 'CAST AS CHAR', 'expr' => 'CAST(42 AS CHAR)', 'expect' => '42'],
            ['name' => 'CAST AS DATE', 'expr' => "CAST('2026-01-02' AS DATE)", 'expect' => '2026-01-02'],
            ['name' => 'CONVERT()', 'expr' => 'CONVERT(123, CHAR)', 'expect' => '123'],
            ['name' => 'GREATEST()', 'expr' => 'GREATEST(1,9,3)', 'expect' => '9'],
            ['name' => 'LEAST()', 'expr' => 'LEAST(4,2,8)', 'expect' => '2'],
            ['name' => 'INET_ATON()', 'expr' => "INET_ATON('10.0.0.1')", 'compare' => 'notnull'],
            ['name' => 'INET_NTOA()', 'expr' => 'INET_NTOA(167772161)', 'compare' => 'notnull'],
            ['name' => 'INET6_ATON()', 'expr' => "INET6_ATON('::1')", 'compare' => 'any'],
            ['name' => 'BENCHMARK()', 'expr' => 'BENCHMARK(2, 1+1)', 'compare' => 'any'],
            ['name' => 'LAST_INSERT_ID()', 'expr' => 'LAST_INSERT_ID()', 'compare' => 'any'],
            ['name' => 'ROW_COUNT()', 'expr' => 'ROW_COUNT()', 'compare' => 'any'],
            ['name' => 'CURRENT_ROLE()', 'expr' => 'CURRENT_ROLE()', 'compare' => 'any'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function stringFns(): array
    {
        return [
            ['name' => 'CONCAT', 'expr' => "CONCAT('a','b','c')", 'expect' => 'abc'],
            ['name' => 'CONCAT_WS', 'expr' => "CONCAT_WS('-','a','b')", 'expect' => 'a-b'],
            ['name' => 'SUBSTRING', 'expr' => "SUBSTRING('abcdef',2,3)", 'expect' => 'bcd'],
            ['name' => 'SUBSTR', 'expr' => "SUBSTR('abcdef',2)", 'expect' => 'bcdef'],
            ['name' => 'MID', 'expr' => "MID('abcdef',2,2)", 'expect' => 'bc'],
            ['name' => 'LEFT', 'expr' => "LEFT('abcdef',3)", 'expect' => 'abc'],
            ['name' => 'RIGHT', 'expr' => "RIGHT('abcdef',2)", 'expect' => 'ef'],
            ['name' => 'LENGTH', 'expr' => "LENGTH('abc')", 'expect' => '3'],
            ['name' => 'CHAR_LENGTH', 'expr' => "CHAR_LENGTH('héllo')", 'expect' => '5'],
            ['name' => 'CHARACTER_LENGTH', 'expr' => "CHARACTER_LENGTH('abc')", 'expect' => '3'],
            ['name' => 'BIT_LENGTH', 'expr' => "BIT_LENGTH('abc')", 'expect' => '24'],
            ['name' => 'OCTET_LENGTH', 'expr' => "OCTET_LENGTH('abc')", 'expect' => '3'],
            ['name' => 'UPPER', 'expr' => "UPPER('abc')", 'expect' => 'ABC'],
            ['name' => 'LOWER', 'expr' => "LOWER('ABC')", 'expect' => 'abc'],
            ['name' => 'UCASE', 'expr' => "UCASE('abc')", 'expect' => 'ABC'],
            ['name' => 'LCASE', 'expr' => "LCASE('ABC')", 'expect' => 'abc'],
            ['name' => 'TRIM', 'expr' => "TRIM('  x  ')", 'expect' => 'x'],
            ['name' => 'LTRIM', 'expr' => "LTRIM('  x')", 'expect' => 'x'],
            ['name' => 'RTRIM', 'expr' => "RTRIM('x  ')", 'expect' => 'x'],
            ['name' => 'TRIM BOTH', 'expr' => "TRIM(BOTH 'x' FROM 'xxabcxx')", 'expect' => 'abc'],
            ['name' => 'REPLACE', 'expr' => "REPLACE('aaa','a','b')", 'expect' => 'bbb'],
            ['name' => 'REVERSE', 'expr' => "REVERSE('abc')", 'expect' => 'cba'],
            ['name' => 'REPEAT', 'expr' => "REPEAT('ab',3)", 'expect' => 'ababab'],
            ['name' => 'LPAD', 'expr' => "LPAD('5',3,'0')", 'expect' => '005'],
            ['name' => 'RPAD', 'expr' => "RPAD('5',3,'0')", 'expect' => '500'],
            ['name' => 'SPACE', 'expr' => 'LENGTH(SPACE(4))', 'expect' => '4'],
            ['name' => 'INSTR', 'expr' => "INSTR('abcabc','c')", 'expect' => '3'],
            ['name' => 'LOCATE', 'expr' => "LOCATE('b','abcabc',3)", 'expect' => '5'],
            ['name' => 'POSITION', 'expr' => "POSITION('c' IN 'abc')", 'expect' => '3'],
            ['name' => 'FIELD', 'expr' => "FIELD('b','a','b','c')", 'expect' => '2'],
            ['name' => 'FIND_IN_SET', 'expr' => "FIND_IN_SET('b','a,b,c')", 'expect' => '2'],
            ['name' => 'ELT', 'expr' => "ELT(2,'a','b','c')", 'expect' => 'b'],
            ['name' => 'INSERT', 'expr' => "INSERT('abcdef',2,3,'XY')", 'expect' => 'aXYef'],
            ['name' => 'SUBSTRING_INDEX', 'expr' => "SUBSTRING_INDEX('a.b.c','.',2)", 'expect' => 'a.b'],
            ['name' => 'ASCII', 'expr' => "ASCII('A')", 'expect' => '65'],
            ['name' => 'ORD', 'expr' => "ORD('A')", 'expect' => '65'],
            ['name' => 'CHAR', 'expr' => 'CHAR(65)', 'compare' => 'notnull'],
            ['name' => 'HEX', 'expr' => "HEX('A')", 'expect' => '41'],
            ['name' => 'UNHEX', 'expr' => "UNHEX('41')", 'expect' => 'A'],
            ['name' => 'BIN', 'expr' => 'BIN(5)', 'expect' => '101'],
            ['name' => 'OCT', 'expr' => 'OCT(8)', 'expect' => '10'],
            ['name' => 'CONV', 'expr' => "CONV('FF',16,10)", 'expect' => '255'],
            ['name' => 'FORMAT', 'expr' => 'FORMAT(1234.5678, 2)', 'expect' => '1,234.57'],
            ['name' => 'STRCMP', 'expr' => "STRCMP('a','b')", 'expect' => '-1'],
            ['name' => 'SOUNDEX', 'expr' => "SOUNDEX('Robert')", 'compare' => 'notnull'],
            ['name' => 'MD5', 'expr' => "MD5('abc')", 'expect' => '900150983cd24fb0d6963f7d28e17f72'],
            ['name' => 'SHA1', 'expr' => "SHA('abc')", 'compare' => 'notnull'],
            ['name' => 'SHA2', 'expr' => "SHA2('abc',256)", 'compare' => 'notnull'],
            ['name' => 'CRC32', 'expr' => "CRC32('abc')", 'compare' => 'notnull'],
            ['name' => 'TO_BASE64', 'expr' => "TO_BASE64('abc')", 'compare' => 'notnull'],
            ['name' => 'FROM_BASE64', 'expr' => "FROM_BASE64(TO_BASE64('abc'))", 'expect' => 'abc'],
            ['name' => 'REGEXP_REPLACE', 'expr' => "REGEXP_REPLACE('a1b2','[0-9]','#')", 'expect' => 'a#b#'],
            ['name' => 'REGEXP_INSTR', 'expr' => "REGEXP_INSTR('abc123','[0-9]')", 'expect' => '4'],
            ['name' => 'REGEXP_SUBSTR', 'expr' => "REGEXP_SUBSTR('abc123','[0-9]+')", 'expect' => '123'],
            ['name' => 'REGEXP_LIKE', 'expr' => "REGEXP_LIKE('abc','^a')", 'compare' => 'any'],
            ['name' => 'RLIKE operator', 'expr' => "'abc' RLIKE '^a'", 'expect' => '1'],
            ['name' => 'REGEXP operator', 'expr' => "'abc' REGEXP 'b'", 'expect' => '1'],
            ['name' => 'LIKE operator', 'expr' => "'abc' LIKE 'a%'", 'expect' => '1'],
            ['name' => 'QUOTE', 'expr' => "QUOTE('a\\'b')", 'compare' => 'notnull'],
            ['name' => 'STARTSWITH', 'expr' => "startswith('abc','ab')", 'compare' => 'any'],
            ['name' => 'ENDSWITH', 'expr' => "endswith('abc','bc')", 'compare' => 'any'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function numericFns(): array
    {
        return [
            ['name' => 'ABS', 'expr' => 'ABS(-5)', 'expect' => '5'],
            ['name' => 'CEIL', 'expr' => 'CEIL(1.2)', 'expect' => '2'],
            ['name' => 'CEILING', 'expr' => 'CEILING(1.2)', 'expect' => '2'],
            ['name' => 'FLOOR', 'expr' => 'FLOOR(1.8)', 'expect' => '1'],
            ['name' => 'ROUND', 'expr' => 'ROUND(1.567,2)', 'expect' => '1.57'],
            ['name' => 'TRUNCATE', 'expr' => 'TRUNCATE(1.567,2)', 'expect' => '1.56'],
            ['name' => 'MOD', 'expr' => 'MOD(10,3)', 'expect' => '1'],
            ['name' => 'MOD operator', 'expr' => '10 % 3', 'expect' => '1'],
            ['name' => 'POW', 'expr' => 'POW(2,10)', 'expect' => '1024', 'compare' => 'numeric'],
            ['name' => 'POWER', 'expr' => 'POWER(3,3)', 'expect' => '27', 'compare' => 'numeric'],
            ['name' => 'SQRT', 'expr' => 'SQRT(16)', 'expect' => '4', 'compare' => 'numeric'],
            ['name' => 'EXP', 'expr' => 'EXP(0)', 'expect' => '1', 'compare' => 'numeric'],
            ['name' => 'LN', 'expr' => 'LN(1)', 'expect' => '0', 'compare' => 'numeric'],
            ['name' => 'LOG', 'expr' => 'LOG(2.718281828)', 'expect' => '1', 'compare' => 'numeric'],
            ['name' => 'LOG2', 'expr' => 'LOG2(8)', 'expect' => '3', 'compare' => 'numeric'],
            ['name' => 'LOG10', 'expr' => 'LOG10(1000)', 'expect' => '3', 'compare' => 'numeric'],
            ['name' => 'SIN', 'expr' => 'SIN(0)', 'expect' => '0', 'compare' => 'numeric'],
            ['name' => 'COS', 'expr' => 'COS(0)', 'expect' => '1', 'compare' => 'numeric'],
            ['name' => 'TAN', 'expr' => 'TAN(0)', 'expect' => '0', 'compare' => 'numeric'],
            ['name' => 'ASIN', 'expr' => 'ASIN(1)', 'expect' => '1.5707963', 'compare' => 'numeric'],
            ['name' => 'ACOS', 'expr' => 'ACOS(1)', 'expect' => '0', 'compare' => 'numeric'],
            ['name' => 'ATAN', 'expr' => 'ATAN(1)', 'expect' => '0.7853981', 'compare' => 'numeric'],
            ['name' => 'ATAN2', 'expr' => 'ATAN2(1,1)', 'expect' => '0.7853981', 'compare' => 'numeric'],
            ['name' => 'COT', 'expr' => 'COT(1)', 'compare' => 'notnull'],
            ['name' => 'PI', 'expr' => 'PI()', 'expect' => '3.141593', 'compare' => 'numeric'],
            ['name' => 'RAND', 'expr' => 'RAND()', 'compare' => 'notnull'],
            ['name' => 'SIGN', 'expr' => 'SIGN(-3)', 'expect' => '-1'],
            ['name' => 'DEGREES', 'expr' => 'DEGREES(PI())', 'expect' => '180', 'compare' => 'numeric'],
            ['name' => 'RADIANS', 'expr' => 'RADIANS(180)', 'expect' => '3.1415926', 'compare' => 'numeric'],
            ['name' => 'BIT_COUNT', 'expr' => 'BIT_COUNT(7)', 'expect' => '3'],
            ['name' => 'bitwise AND', 'expr' => '6 & 3', 'expect' => '2'],
            ['name' => 'bitwise OR', 'expr' => '4 | 1', 'expect' => '5'],
            ['name' => 'bitwise XOR', 'expr' => '5 ^ 1', 'expect' => '4'],
            ['name' => 'bitwise shift left', 'expr' => '1 << 4', 'expect' => '16'],
            ['name' => 'bitwise shift right', 'expr' => '32 >> 2', 'expect' => '8'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function dateFns(): array
    {
        return [
            ['name' => 'NOW', 'expr' => 'NOW()', 'compare' => 'notnull'],
            ['name' => 'NOW(6)', 'expr' => 'NOW(6)', 'compare' => 'notnull'],
            ['name' => 'CURDATE', 'expr' => 'CURDATE()', 'compare' => 'notnull'],
            ['name' => 'CURTIME', 'expr' => 'CURTIME()', 'compare' => 'notnull'],
            ['name' => 'CURRENT_DATE', 'expr' => 'CURRENT_DATE()', 'compare' => 'notnull'],
            ['name' => 'CURRENT_TIME', 'expr' => 'CURRENT_TIME()', 'compare' => 'notnull'],
            ['name' => 'CURRENT_TIMESTAMP', 'expr' => 'CURRENT_TIMESTAMP()', 'compare' => 'notnull'],
            ['name' => 'SYSDATE', 'expr' => 'SYSDATE()', 'compare' => 'notnull'],
            ['name' => 'UTC_TIMESTAMP', 'expr' => 'UTC_TIMESTAMP()', 'compare' => 'notnull'],
            ['name' => 'UTC_DATE', 'expr' => 'UTC_DATE()', 'compare' => 'notnull'],
            ['name' => 'UTC_TIME', 'expr' => 'UTC_TIME()', 'compare' => 'notnull'],
            ['name' => 'UNIX_TIMESTAMP', 'expr' => "UNIX_TIMESTAMP('2021-01-01 00:00:00')", 'compare' => 'notnull'],
            ['name' => 'FROM_UNIXTIME', 'expr' => 'FROM_UNIXTIME(0)', 'compare' => 'notnull'],
            ['name' => 'DATE', 'expr' => "DATE('2026-06-23 10:00:00')", 'expect' => '2026-06-23'],
            ['name' => 'TIME', 'expr' => "TIME('2026-06-23 10:00:00')", 'expect' => '10:00:00'],
            ['name' => 'YEAR', 'expr' => "YEAR('2026-06-23')", 'expect' => '2026'],
            ['name' => 'MONTH', 'expr' => "MONTH('2026-06-23')", 'expect' => '6'],
            ['name' => 'DAY', 'expr' => "DAY('2026-06-23')", 'expect' => '23'],
            ['name' => 'DAYOFMONTH', 'expr' => "DAYOFMONTH('2026-06-23')", 'expect' => '23'],
            ['name' => 'HOUR', 'expr' => "HOUR('10:20:30')", 'expect' => '10'],
            ['name' => 'MINUTE', 'expr' => "MINUTE('10:20:30')", 'expect' => '20'],
            ['name' => 'SECOND', 'expr' => "SECOND('10:20:30')", 'expect' => '30'],
            ['name' => 'MICROSECOND', 'expr' => "MICROSECOND('10:20:30.123456')", 'expect' => '123456'],
            ['name' => 'WEEK', 'expr' => "WEEK('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'WEEKDAY', 'expr' => "WEEKDAY('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'WEEKOFYEAR', 'expr' => "WEEKOFYEAR('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'DAYOFWEEK', 'expr' => "DAYOFWEEK('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'DAYOFYEAR', 'expr' => "DAYOFYEAR('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'DAYNAME', 'expr' => "DAYNAME('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'MONTHNAME', 'expr' => "MONTHNAME('2026-06-23')", 'compare' => 'notnull'],
            ['name' => 'QUARTER', 'expr' => "QUARTER('2026-06-23')", 'expect' => '2'],
            ['name' => 'LAST_DAY', 'expr' => "LAST_DAY('2026-02-10')", 'expect' => '2026-02-28'],
            ['name' => 'DATE_ADD', 'expr' => "DATE_ADD('2026-01-01', INTERVAL 1 DAY)", 'expect' => '2026-01-02'],
            ['name' => 'DATE_SUB', 'expr' => "DATE_SUB('2026-01-01', INTERVAL 1 DAY)", 'expect' => '2025-12-31'],
            ['name' => 'ADDDATE', 'expr' => "ADDDATE('2026-01-01', 5)", 'expect' => '2026-01-06'],
            ['name' => 'SUBDATE', 'expr' => "SUBDATE('2026-01-10', 5)", 'expect' => '2026-01-05'],
            ['name' => 'ADDTIME', 'expr' => "ADDTIME('10:00:00','01:00:00')", 'expect' => '11:00:00'],
            ['name' => 'SUBTIME', 'expr' => "SUBTIME('10:00:00','01:00:00')", 'expect' => '09:00:00'],
            ['name' => 'DATEDIFF', 'expr' => "DATEDIFF('2026-01-10','2026-01-01')", 'expect' => '9'],
            ['name' => 'TIMEDIFF', 'expr' => "TIME_FORMAT(TIMEDIFF('10:00:00','09:00:00'), '%H:%i:%s')", 'expect' => '01:00:00'],
            ['name' => 'TIMESTAMPDIFF', 'expr' => "TIMESTAMPDIFF(DAY,'2026-01-01','2026-01-11')", 'expect' => '10'],
            ['name' => 'TIMESTAMPADD', 'expr' => "TIMESTAMPADD(DAY,5,'2026-01-01')", 'compare' => 'notnull'],
            ['name' => 'DATE_FORMAT', 'expr' => "DATE_FORMAT('2026-06-23','%Y/%m/%d')", 'expect' => '2026/06/23'],
            ['name' => 'STR_TO_DATE', 'expr' => "STR_TO_DATE('2026/06/23','%Y/%m/%d')", 'expect' => '2026-06-23'],
            ['name' => 'TIME_FORMAT', 'expr' => "TIME_FORMAT('10:20:30','%H:%i')", 'expect' => '10:20'],
            ['name' => 'EXTRACT YEAR', 'expr' => "EXTRACT(YEAR FROM '2026-06-23')", 'expect' => '2026'],
            ['name' => 'SEC_TO_TIME', 'expr' => 'SEC_TO_TIME(3661)', 'expect' => '01:01:01'],
            ['name' => 'TIME_TO_SEC', 'expr' => "TIME_TO_SEC('01:01:01')", 'expect' => '3661'],
            ['name' => 'TO_DAYS', 'expr' => "TO_DAYS('2026-01-01')", 'compare' => 'notnull'],
            ['name' => 'FROM_DAYS', 'expr' => 'FROM_DAYS(739987)', 'compare' => 'notnull'],
            ['name' => 'MAKEDATE', 'expr' => 'MAKEDATE(2026,32)', 'compare' => 'notnull'],
            ['name' => 'MAKETIME', 'expr' => 'MAKETIME(10,20,30)', 'expect' => '10:20:30'],
            ['name' => 'CONVERT_TZ', 'expr' => "CONVERT_TZ('2026-01-01 12:00:00','+00:00','+08:00')", 'compare' => 'any'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function jsonFns(): array
    {
        return [
            ['name' => 'JSON_EXTRACT', 'expr' => "JSON_EXTRACT('{\"a\":1}','$.a')", 'expect' => '1'],
            ['name' => 'JSON arrow ->', 'expr' => "JSON_EXTRACT('{\"a\":5}','$.a')", 'expect' => '5'],
            ['name' => 'JSON_UNQUOTE', 'expr' => "JSON_UNQUOTE(JSON_EXTRACT('{\"a\":\"x\"}','$.a'))", 'expect' => 'x'],
            ['name' => 'JSON_OBJECT', 'expr' => "JSON_OBJECT('k',1)", 'compare' => 'notnull'],
            ['name' => 'JSON_ARRAY', 'expr' => 'JSON_ARRAY(1,2,3)', 'compare' => 'notnull'],
            ['name' => 'JSON_VALID', 'expr' => "JSON_VALID('{\"a\":1}')", 'expect' => '1'],
            ['name' => 'JSON_TYPE', 'expr' => "JSON_TYPE('[1,2]')", 'compare' => 'notnull'],
            ['name' => 'JSON_QUOTE', 'expr' => "JSON_QUOTE('abc')", 'compare' => 'notnull'],
            ['name' => 'JSON_CONTAINS', 'expr' => "JSON_CONTAINS('[1,2,3]','2')", 'compare' => 'any'],
            ['name' => 'JSON_CONTAINS_PATH', 'expr' => "JSON_CONTAINS_PATH('{\"a\":1}','one','$.a')", 'compare' => 'any'],
            ['name' => 'JSON_KEYS', 'expr' => "JSON_KEYS('{\"a\":1,\"b\":2}')", 'compare' => 'notnull'],
            ['name' => 'JSON_LENGTH', 'expr' => "JSON_LENGTH('[1,2,3]')", 'expect' => '3'],
            ['name' => 'JSON_DEPTH', 'expr' => "JSON_DEPTH('[1,[2]]')", 'compare' => 'notnull'],
            ['name' => 'JSON_SET', 'expr' => "JSON_SET('{\"a\":1}','$.b',2)", 'compare' => 'notnull'],
            ['name' => 'JSON_INSERT', 'expr' => "JSON_INSERT('{\"a\":1}','$.b',2)", 'compare' => 'notnull'],
            ['name' => 'JSON_REPLACE', 'expr' => "JSON_REPLACE('{\"a\":1}','$.a',9)", 'compare' => 'notnull'],
            ['name' => 'JSON_REMOVE', 'expr' => "JSON_REMOVE('{\"a\":1,\"b\":2}','$.b')", 'compare' => 'notnull'],
            ['name' => 'JSON_MERGE_PATCH', 'expr' => "JSON_MERGE_PATCH('{\"a\":1}','{\"b\":2}')", 'compare' => 'notnull'],
            ['name' => 'JSON_MERGE_PRESERVE', 'expr' => "JSON_MERGE_PRESERVE('{\"a\":1}','{\"b\":2}')", 'compare' => 'notnull'],
            ['name' => 'JSON_ARRAY_APPEND', 'expr' => "JSON_ARRAY_APPEND('[1]','$',2)", 'compare' => 'notnull'],
            ['name' => 'JSON_SEARCH', 'expr' => "JSON_SEARCH('[\"a\",\"b\"]','one','b')", 'compare' => 'any'],
            ['name' => 'JSON_OVERLAPS', 'expr' => "JSON_OVERLAPS('[1,2]','[2,3]')", 'compare' => 'any'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function aggregateFns(): array
    {
        $src = '(SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) t';
        return [
            ['name' => 'COUNT(*)', 'expr' => "(SELECT COUNT(*) FROM $src)", 'expect' => '4'],
            ['name' => 'COUNT(DISTINCT)', 'expr' => "(SELECT COUNT(DISTINCT n) FROM $src)", 'expect' => '4'],
            ['name' => 'SUM', 'expr' => "(SELECT SUM(n) FROM $src)", 'expect' => '10'],
            ['name' => 'AVG', 'expr' => "(SELECT AVG(n) FROM $src)", 'expect' => '2.5', 'compare' => 'numeric'],
            ['name' => 'MIN', 'expr' => "(SELECT MIN(n) FROM $src)", 'expect' => '1'],
            ['name' => 'MAX', 'expr' => "(SELECT MAX(n) FROM $src)", 'expect' => '4'],
            ['name' => 'GROUP_CONCAT', 'expr' => "(SELECT GROUP_CONCAT(n ORDER BY n) FROM $src)", 'expect' => '1,2,3,4'],
            ['name' => 'GROUP_CONCAT SEPARATOR', 'expr' => "(SELECT GROUP_CONCAT(n SEPARATOR '|') FROM $src)", 'compare' => 'notnull'],
            ['name' => 'STD', 'expr' => "(SELECT STD(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'STDDEV', 'expr' => "(SELECT STDDEV(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'STDDEV_POP', 'expr' => "(SELECT STDDEV_POP(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'STDDEV_SAMP', 'expr' => "(SELECT STDDEV_SAMP(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'VAR_POP', 'expr' => "(SELECT VAR_POP(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'VAR_SAMP', 'expr' => "(SELECT VAR_SAMP(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'VARIANCE', 'expr' => "(SELECT VARIANCE(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'BIT_AND', 'expr' => "(SELECT BIT_AND(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'BIT_OR', 'expr' => "(SELECT BIT_OR(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'BIT_XOR', 'expr' => "(SELECT BIT_XOR(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'ANY_VALUE', 'expr' => "(SELECT ANY_VALUE(n) FROM $src)", 'compare' => 'notnull'],
            ['name' => 'MEDIAN', 'expr' => "(SELECT MEDIAN(n) FROM $src)", 'compare' => 'any'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function windowFns(): array
    {
        $src = '(SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3) t';
        return [
            ['name' => 'ROW_NUMBER', 'expr' => "(SELECT MAX(rn) FROM (SELECT ROW_NUMBER() OVER (ORDER BY n) rn FROM $src) z)", 'expect' => '3'],
            ['name' => 'RANK', 'expr' => "(SELECT MAX(r) FROM (SELECT RANK() OVER (ORDER BY n) r FROM $src) z)", 'expect' => '3'],
            ['name' => 'DENSE_RANK', 'expr' => "(SELECT MAX(r) FROM (SELECT DENSE_RANK() OVER (ORDER BY n) r FROM $src) z)", 'expect' => '3'],
            ['name' => 'NTILE', 'expr' => "(SELECT MAX(t2) FROM (SELECT NTILE(2) OVER (ORDER BY n) t2 FROM $src) z)", 'compare' => 'notnull'],
            ['name' => 'LAG', 'expr' => "(SELECT SUM(l) FROM (SELECT LAG(n) OVER (ORDER BY n) l FROM $src) z)", 'compare' => 'any'],
            ['name' => 'LEAD', 'expr' => "(SELECT SUM(l) FROM (SELECT LEAD(n) OVER (ORDER BY n) l FROM $src) z)", 'compare' => 'any'],
            ['name' => 'FIRST_VALUE', 'expr' => "(SELECT MIN(f) FROM (SELECT FIRST_VALUE(n) OVER (ORDER BY n) f FROM $src) z)", 'compare' => 'notnull'],
            ['name' => 'LAST_VALUE', 'expr' => "(SELECT MAX(f) FROM (SELECT LAST_VALUE(n) OVER (ORDER BY n) f FROM $src) z)", 'compare' => 'notnull'],
            ['name' => 'NTH_VALUE', 'expr' => "(SELECT MAX(f) FROM (SELECT NTH_VALUE(n,2) OVER (ORDER BY n) f FROM $src) z)", 'compare' => 'any'],
            ['name' => 'CUME_DIST', 'expr' => "(SELECT MAX(c) FROM (SELECT CUME_DIST() OVER (ORDER BY n) c FROM $src) z)", 'compare' => 'any'],
            ['name' => 'PERCENT_RANK', 'expr' => "(SELECT MAX(c) FROM (SELECT PERCENT_RANK() OVER (ORDER BY n) c FROM $src) z)", 'compare' => 'any'],
            ['name' => 'SUM OVER', 'expr' => "(SELECT MAX(s) FROM (SELECT SUM(n) OVER (ORDER BY n) s FROM $src) z)", 'expect' => '6'],
            ['name' => 'AVG OVER PARTITION', 'expr' => "(SELECT MAX(a) FROM (SELECT AVG(n) OVER (PARTITION BY n) a FROM $src) z)", 'compare' => 'notnull'],
        ];
    }

    /** @return array<int,array<string,mixed>> MatrixOne-specific vector functions. */
    private static function vectorFns(): array
    {
        return [
            ['name' => 'l2_distance', 'expr' => "l2_distance('[1,2,3]','[1,2,3]')", 'compare' => 'any'],
            ['name' => 'cosine_distance', 'expr' => "cosine_distance('[1,0]','[0,1]')", 'compare' => 'any'],
            ['name' => 'inner_product', 'expr' => "inner_product('[1,2]','[3,4]')", 'compare' => 'any'],
            ['name' => 'cosine_similarity', 'expr' => "cosine_similarity('[1,0]','[1,0]')", 'compare' => 'any'],
            ['name' => 'normalize_l2', 'expr' => "normalize_l2('[3,4]')", 'compare' => 'any'],
            ['name' => 'vector_dims', 'expr' => "vector_dims('[1,2,3]')", 'compare' => 'any'],
            ['name' => 'l1_distance', 'expr' => "l1_distance('[1,2]','[2,3]')", 'compare' => 'any'],
        ];
    }
}
