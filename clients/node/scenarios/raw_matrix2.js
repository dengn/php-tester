'use strict';

// Second wave of generated raw-driver matrices to broaden coverage:
//  - JSON path access & function variations
//  - date/time INTERVAL arithmetic matrix
//  - CAST/CONVERT target-type matrix
//  - string-function argument variations
//  - NULL/empty/boundary handling per type
//  - charset / collation declaration matrix
//  - aggregate-over-table matrix
//  - default-value DDL matrix per type

const { makePool, uniq } = require('../lib/db');
const { SQL_TYPES } = require('../lib/matrix');
const { BehaviorMismatch } = require('../lib/errors');

let pool = null;
function getPool() { if (!pool) pool = makePool('raw', { connectionLimit: 4 }); return pool; }
async function q(sql, params) { const [r] = await getPool().query(sql, params); return r; }
async function withTable(ddl, body) {
  const t = uniq('t'); await q(ddl(t));
  try { return await body(t); } finally { try { await q(`DROP TABLE IF EXISTS \`${t}\``); } catch (_) {} }
}

function register(runner) {
  const FW = 'raw';

  // -------------------------------------------------------------------------
  // A. JSON access / path / function matrix on a stored JSON column
  // -------------------------------------------------------------------------
  function jsonTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, doc JSON)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, ?)`, [JSON.stringify({ a: 1, b: { c: 2 }, arr: [10, 20, 30], s: 'hi' })]);
      return body(t);
    });
  }
  const jsonCases = [
    ['arrow-extract', (t) => `SELECT doc->'$.a' x FROM \`${t}\``],
    ['arrow-unquote', (t) => `SELECT doc->>'$.s' x FROM \`${t}\``],
    ['json-extract', (t) => `SELECT JSON_EXTRACT(doc,'$.a') x FROM \`${t}\``],
    ['json-extract-nested', (t) => `SELECT JSON_EXTRACT(doc,'$.b.c') x FROM \`${t}\``],
    ['json-extract-array', (t) => `SELECT JSON_EXTRACT(doc,'$.arr[1]') x FROM \`${t}\``],
    ['json-unquote', (t) => `SELECT JSON_UNQUOTE(JSON_EXTRACT(doc,'$.s')) x FROM \`${t}\``],
    ['json-keys', (t) => `SELECT JSON_KEYS(doc) x FROM \`${t}\``],
    ['json-length', (t) => `SELECT JSON_LENGTH(doc->'$.arr') x FROM \`${t}\``],
    ['json-type', (t) => `SELECT JSON_TYPE(doc->'$.arr') x FROM \`${t}\``],
    ['json-valid', (t) => `SELECT JSON_VALID(doc) x FROM \`${t}\``],
    ['json-depth', (t) => `SELECT JSON_DEPTH(doc) x FROM \`${t}\``],
    ['json-set', (t) => `SELECT JSON_SET(doc,'$.a',99) x FROM \`${t}\``],
    ['json-insert', (t) => `SELECT JSON_INSERT(doc,'$.z',5) x FROM \`${t}\``],
    ['json-replace', (t) => `SELECT JSON_REPLACE(doc,'$.a',7) x FROM \`${t}\``],
    ['json-remove', (t) => `SELECT JSON_REMOVE(doc,'$.a') x FROM \`${t}\``],
    ['json-contains', (t) => `SELECT JSON_CONTAINS(doc->'$.arr','10') x FROM \`${t}\``],
    ['json-contains-path', (t) => `SELECT JSON_CONTAINS_PATH(doc,'one','$.a') x FROM \`${t}\``],
    ['json-search', (t) => `SELECT JSON_SEARCH(doc,'one','hi') x FROM \`${t}\``],
    ['json-array-append', (t) => `SELECT JSON_ARRAY_APPEND(doc,'$.arr',40) x FROM \`${t}\``],
    ['json-merge-patch', (t) => `SELECT JSON_MERGE_PATCH(doc,'{"z":1}') x FROM \`${t}\``],
    ['json-pretty', (t) => `SELECT JSON_PRETTY(doc) x FROM \`${t}\``],
    ['json-overlaps', (t) => `SELECT JSON_OVERLAPS(doc->'$.arr','[10,99]') x FROM \`${t}\``],
    ['json-quote', (t) => `SELECT JSON_QUOTE(doc->>'$.s') x FROM \`${t}\``],
    ['where-json-path-eq', (t) => `SELECT * FROM \`${t}\` WHERE doc->>'$.a' = '1'`],
    ['where-json-extract', (t) => `SELECT * FROM \`${t}\` WHERE JSON_EXTRACT(doc,'$.a') = 1`],
    ['order-by-json', (t) => `SELECT * FROM \`${t}\` ORDER BY doc->'$.a'`],
    ['group-by-json', (t) => `SELECT doc->>'$.a' a, COUNT(*) n FROM \`${t}\` GROUP BY doc->>'$.a'`],
  ];
  for (const [nm, build] of jsonCases) {
    runner.add(FW, 'json-path', nm, () => jsonTable(async (t) => { await q(build(t)); }));
  }

  // -------------------------------------------------------------------------
  // B. DATE INTERVAL arithmetic matrix
  // -------------------------------------------------------------------------
  const intervals = ['MICROSECOND', 'SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR',
    'SECOND_MICROSECOND', 'MINUTE_SECOND', 'HOUR_MINUTE', 'DAY_HOUR', 'YEAR_MONTH'];
  for (const unit of intervals) {
    const arg = unit.includes('_') ? "'1:1'" : '1';
    runner.add(FW, 'date-interval', `date_add ${unit}`, async () => {
      await q(`SELECT DATE_ADD('2026-06-23 10:00:00', INTERVAL ${arg} ${unit}) x`);
    });
    runner.add(FW, 'date-interval', `date_sub ${unit}`, async () => {
      await q(`SELECT DATE_SUB('2026-06-23 10:00:00', INTERVAL ${arg} ${unit}) x`);
    });
  }
  // timestampdiff / extract per unit
  for (const unit of ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR']) {
    runner.add(FW, 'date-extract', `timestampdiff ${unit}`, async () => {
      await q(`SELECT TIMESTAMPDIFF(${unit}, '2026-01-01', '2026-12-31') x`);
    });
    runner.add(FW, 'date-extract', `extract ${unit}`, async () => {
      await q(`SELECT EXTRACT(${unit} FROM '2026-06-23 11:30:45') x`);
    });
  }

  // -------------------------------------------------------------------------
  // C. CAST / CONVERT target-type matrix
  // -------------------------------------------------------------------------
  const castTargets = [
    ['SIGNED', '42'], ['UNSIGNED', '42'], ['SIGNED INTEGER', '42'],
    ['DECIMAL', '3.14'], ['DECIMAL(10,2)', '3.14'], ['DECIMAL(20,5)', '3.14159'],
    ['CHAR', '42'], ['CHAR(10)', 'abc'], ['NCHAR', 'abc'],
    ['DATE', '2026-06-23'], ['DATETIME', '2026-06-23 10:00'], ['TIME', '10:00:00'],
    ['BINARY', 'abc'], ['BINARY(10)', 'abc'], ['DOUBLE', '3.14'], ['FLOAT', '3.14'],
    ['JSON', '{"a":1}'], ['YEAR', '2026'],
  ];
  for (const [target, val] of castTargets) {
    runner.add(FW, 'cast-target', `cast ${target}`, async () => {
      await q(`SELECT CAST(? AS ${target}) x`, [val]);
    });
    runner.add(FW, 'convert-target', `convert ${target}`, async () => {
      await q(`SELECT CONVERT(?, ${target}) x`, [val]);
    });
  }

  // -------------------------------------------------------------------------
  // D. STRING function argument-variation matrix
  // -------------------------------------------------------------------------
  const strFns = [
    ['substring-neg', "SUBSTRING('abcdef', -3)"],
    ['substring-from-for', "SUBSTRING('abcdef' FROM 2 FOR 3)"],
    ['locate-pos', "LOCATE('c', 'abcabc', 4)"],
    ['lpad-truncate', "LPAD('abcdef', 3, '*')"],
    ['rpad-truncate', "RPAD('abcdef', 3, '*')"],
    ['replace-multi', "REPLACE('aaa', 'a', 'bb')"],
    ['trim-leading', "TRIM(LEADING 'x' FROM 'xxabc')"],
    ['trim-trailing', "TRIM(TRAILING 'x' FROM 'abcxx')"],
    ['trim-both', "TRIM(BOTH 'x' FROM 'xabcx')"],
    ['concat-ws-nulls', "CONCAT_WS('-', 'a', NULL, 'b')"],
    ['elt-out-of-range', "ELT(5, 'a', 'b')"],
    ['field-not-found', "FIELD('z', 'a', 'b')"],
    ['substring-index', "SUBSTRING_INDEX('a.b.c', '.', 2)"],
    ['substring-index-neg', "SUBSTRING_INDEX('a.b.c', '.', -1)"],
    ['format-locale', "FORMAT(1234567.891, 2)"],
    ['lower-unicode', "LOWER('ÀÉÎ')"],
    ['char-multi', "CHAR(72, 73)"],
    ['hex-number', "HEX(255)"],
    ['repeat-zero', "REPEAT('a', 0)"],
    ['space-fn', "CONCAT('[', SPACE(3), ']')"],
  ];
  for (const [nm, expr] of strFns) {
    runner.add(FW, 'string-fn-variation', nm, async () => { await q(`SELECT ${expr} x`); });
  }

  // -------------------------------------------------------------------------
  // E. DEFAULT-value DDL matrix per representative type
  // -------------------------------------------------------------------------
  const defaults = [
    ['int-default', 'INT DEFAULT 5'],
    ['varchar-default', "VARCHAR(20) DEFAULT 'hi'"],
    ['decimal-default', 'DECIMAL(10,2) DEFAULT 1.5'],
    ['bool-default', 'BOOLEAN DEFAULT TRUE'],
    ['date-default', "DATE DEFAULT '2026-01-01'"],
    ['datetime-now', 'DATETIME DEFAULT CURRENT_TIMESTAMP'],
    ['datetime-on-update', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ['timestamp-now', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    ['enum-default', "ENUM('a','b') DEFAULT 'a'"],
    ['text-no-default', 'TEXT'],
    ['json-default', "JSON"],
    ['null-default', 'INT DEFAULT NULL'],
    ['expr-default', 'INT DEFAULT (1+1)'],
  ];
  for (const [nm, decl] of defaults) {
    runner.add(FW, 'ddl-default', nm, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${decl})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id) VALUES (1)`);
      }));
  }

  // -------------------------------------------------------------------------
  // F. CHARSET / COLLATION declaration matrix
  // -------------------------------------------------------------------------
  const collations = [
    'utf8mb4_bin', 'utf8mb4_general_ci', 'utf8mb4_unicode_ci', 'utf8mb4_0900_ai_ci',
    'utf8_bin', 'utf8_general_ci', 'latin1_bin', 'latin1_swedish_ci', 'ascii_bin', 'binary',
  ];
  for (const coll of collations) {
    runner.add(FW, 'collation-ddl', `collate ${coll}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c VARCHAR(50) COLLATE ${coll})`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, 'Abc')`);
      }));
    // behavior: does an explicit _ci collation actually do case-insensitive compare?
    if (coll.endsWith('_ci')) {
      runner.add(FW, 'collation-behavior', `ci-compare ${coll}`, async () => {
        let r;
        try { r = await q(`SELECT ('abc' = 'ABC' COLLATE ${coll}) x`); }
        catch (e) { throw e; } // unsupported collation -> recorded as error
        if (Number(r[0].x) !== 1) {
          throw new BehaviorMismatch(`'abc'='ABC' COLLATE ${coll} => 0; MySQL ci => 1 (collation ignored)`);
        }
      });
    }
  }

  // -------------------------------------------------------------------------
  // G. AGGREGATE-over-table matrix (real GROUP BY with data per agg fn)
  // -------------------------------------------------------------------------
  function aggTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, grp INT, v DECIMAL(12,2))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40),(5,2,50)`);
      return body(t);
    });
  }
  const aggFns = [
    ['count', (t) => `SELECT grp, COUNT(*) x FROM \`${t}\` GROUP BY grp`],
    ['count-distinct', (t) => `SELECT COUNT(DISTINCT grp) x FROM \`${t}\``],
    ['sum', (t) => `SELECT grp, SUM(v) x FROM \`${t}\` GROUP BY grp`],
    ['avg', (t) => `SELECT grp, AVG(v) x FROM \`${t}\` GROUP BY grp`],
    ['min', (t) => `SELECT grp, MIN(v) x FROM \`${t}\` GROUP BY grp`],
    ['max', (t) => `SELECT grp, MAX(v) x FROM \`${t}\` GROUP BY grp`],
    ['group-concat', (t) => `SELECT grp, GROUP_CONCAT(v) x FROM \`${t}\` GROUP BY grp`],
    ['group-concat-order', (t) => `SELECT grp, GROUP_CONCAT(v ORDER BY v DESC SEPARATOR '|') x FROM \`${t}\` GROUP BY grp`],
    ['std', (t) => `SELECT grp, STD(v) x FROM \`${t}\` GROUP BY grp`],
    ['stddev', (t) => `SELECT grp, STDDEV(v) x FROM \`${t}\` GROUP BY grp`],
    ['stddev-samp', (t) => `SELECT grp, STDDEV_SAMP(v) x FROM \`${t}\` GROUP BY grp`],
    ['variance', (t) => `SELECT grp, VARIANCE(v) x FROM \`${t}\` GROUP BY grp`],
    ['var-samp', (t) => `SELECT grp, VAR_SAMP(v) x FROM \`${t}\` GROUP BY grp`],
    ['bit-and', (t) => `SELECT grp, BIT_AND(id) x FROM \`${t}\` GROUP BY grp`],
    ['bit-or', (t) => `SELECT grp, BIT_OR(id) x FROM \`${t}\` GROUP BY grp`],
    ['bit-xor', (t) => `SELECT grp, BIT_XOR(id) x FROM \`${t}\` GROUP BY grp`],
    ['having-sum', (t) => `SELECT grp, SUM(v) x FROM \`${t}\` GROUP BY grp HAVING SUM(v) > 25`],
    ['rollup', (t) => `SELECT grp, SUM(v) x FROM \`${t}\` GROUP BY grp WITH ROLLUP`],
    ['json-arrayagg', (t) => `SELECT JSON_ARRAYAGG(v) x FROM \`${t}\``],
    ['json-objectagg', (t) => `SELECT JSON_OBJECTAGG(id, v) x FROM \`${t}\``],
  ];
  for (const [nm, build] of aggFns) {
    runner.add(FW, 'aggregate-table', nm, () => aggTable(async (t) => { await q(build(t)); }));
  }

  // -------------------------------------------------------------------------
  // H. NULL/empty/zero edge handling per type
  // -------------------------------------------------------------------------
  for (const ty of SQL_TYPES.filter((t) => !t.skipValue && ['int', 'num', 'str', 'dt'].includes(t.group))) {
    runner.add(FW, `edge/${ty.name}`, 'insert-null-then-coalesce', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1, NULL)`);
        await q(`SELECT COALESCE(c, ?) x FROM \`${t}\``, [ty.sample]);
      }));
    runner.add(FW, `edge/${ty.name}`, 'count-non-null', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,NULL)`, [ty.sample]);
        const r = await q(`SELECT COUNT(c) x FROM \`${t}\``);
        if (Number(r[0].x) !== 1) throw new BehaviorMismatch(`COUNT(c) ignoring NULL => ${r[0].x}, expected 1`);
      }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
