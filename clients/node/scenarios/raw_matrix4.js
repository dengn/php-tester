'use strict';

// Fourth wave of raw-driver matrices: functions applied to stored column data
// (table context), numeric precision boundaries, string-collation comparisons,
// date-format token matrix, and comparison-coercion across type pairs.

const { makePool, uniq } = require('../lib/db');
const { SQL_FUNCTIONS } = require('../lib/matrix');
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
  // A. STRING functions applied to a stored VARCHAR column (table context)
  // -------------------------------------------------------------------------
  const strColFns = SQL_FUNCTIONS.filter(([cat]) => cat === 'string');
  for (const [, name, expr] of strColFns) {
    // Replace the first literal with the column reference where feasible by
    // simply wrapping: run the function on a constant but in a row context so
    // the optimizer path through a table scan is exercised.
    runner.add(FW, 'func-table/string', `${name} over-rows`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, s VARCHAR(50))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1,'Robert'),(2,'alice')`);
        await q(`SELECT id, (${expr}) AS r FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // B. NUMERIC functions over a stored DECIMAL column
  // -------------------------------------------------------------------------
  const numColFns = SQL_FUNCTIONS.filter(([cat]) => cat === 'numeric');
  for (const [, name, expr] of numColFns) {
    runner.add(FW, 'func-table/numeric', `${name} over-rows`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v DECIMAL(12,4))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1,3.5),(2,-7.25)`);
        await q(`SELECT id, (${expr}) AS r FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // C. DATE/TIME functions over a stored DATETIME column
  // -------------------------------------------------------------------------
  const dtColFns = SQL_FUNCTIONS.filter(([cat]) => cat === 'datetime');
  for (const [, name, expr] of dtColFns) {
    runner.add(FW, 'func-table/datetime', `${name} over-rows`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, d DATETIME)`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1,'2026-06-23 11:30:45'),(2,'2026-01-01 00:00:00')`);
        await q(`SELECT id, (${expr}) AS r FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // D. DATE_FORMAT token matrix
  // -------------------------------------------------------------------------
  const fmtTokens = ['%Y', '%y', '%m', '%c', '%d', '%e', '%H', '%h', '%i', '%s',
    '%p', '%M', '%b', '%W', '%a', '%j', '%U', '%u', '%D', '%T', '%r', '%f', '%w', '%%'];
  for (const tok of fmtTokens) {
    runner.add(FW, 'date-format-token', `fmt ${tok}`, async () => {
      await q(`SELECT DATE_FORMAT('2026-06-23 14:05:09.123456', '${tok}') x`);
    });
  }

  // -------------------------------------------------------------------------
  // E. NUMERIC precision / rounding boundary matrix
  // -------------------------------------------------------------------------
  const precisionCases = [
    ['decimal-18-4', 'DECIMAL(18,4)', '12345678901234.5678'],
    ['decimal-38-10', 'DECIMAL(38,10)', '1234567890.1234567890'],
    ['decimal-tiny', 'DECIMAL(4,2)', '12.34'],
    ['decimal-neg', 'DECIMAL(10,2)', '-99.99'],
    ['decimal-zero-scale', 'DECIMAL(10,0)', '12345'],
    ['double-precision', 'DOUBLE', '3.141592653589793'],
    ['double-large', 'DOUBLE', '1.5e100'],
    ['double-small', 'DOUBLE', '1.5e-100'],
    ['bigint-max', 'BIGINT', '9223372036854775807'],
    ['bigint-unsigned-max', 'BIGINT UNSIGNED', '18446744073709551615'],
  ];
  for (const [nm, type, val] of precisionCases) {
    runner.add(FW, 'precision', nm, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${type})`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?)`, [val]);
        const r = await q(`SELECT c FROM \`${t}\` WHERE id=1`);
        // round-trip sanity for decimals (string-equal expected)
        if (type.startsWith('DECIMAL') && String(r[0].c) !== String(val)) {
          throw new BehaviorMismatch(`${type} stored ${val} -> ${r[0].c}`);
        }
      }));
  }

  // -------------------------------------------------------------------------
  // F. COMPARISON COERCION across type pairs (int vs string, etc.)
  // -------------------------------------------------------------------------
  const coercions = [
    ['int-eq-string', "1 = '1'", 1],
    ['int-eq-string-pad', "1 = '1 '", 1],
    ['float-eq-int', '1.0 = 1', 1],
    ['string-lt-num', "'2' < 10", 1],
    ['date-eq-string', "DATE '2026-06-23' = '2026-06-23'", 1],
    ['null-eq-null', 'NULL = NULL', null],
    ['null-safe-eq', 'NULL <=> NULL', 1],
    ['bool-eq-int', 'TRUE = 1', 1],
    ['hex-eq-int', "0x41 = 65", 1],
    ['empty-eq-zero', "'' = 0", null],
  ];
  for (const [nm, expr, expected] of coercions) {
    runner.add(FW, 'coercion', nm, async () => {
      const r = await q(`SELECT (${expr}) AS x`);
      if (expected !== undefined) {
        const got = r[0].x === null ? null : Number(r[0].x);
        if (JSON.stringify(got) !== JSON.stringify(expected)) {
          throw new BehaviorMismatch(`${expr} => ${JSON.stringify(r[0].x)}; MySQL expects ${JSON.stringify(expected)}`);
        }
      }
    });
  }

  // -------------------------------------------------------------------------
  // G. LIKE pattern matrix
  // -------------------------------------------------------------------------
  const likePatterns = [
    ['prefix', "'abc' LIKE 'a%'", 1],
    ['suffix', "'abc' LIKE '%c'", 1],
    ['contains', "'abc' LIKE '%b%'", 1],
    ['single', "'abc' LIKE 'a_c'", 1],
    ['exact', "'abc' LIKE 'abc'", 1],
    ['no-match', "'abc' LIKE 'x%'", 0],
    ['escape-pct', "'a%b' LIKE 'a\\%b'", 1],
    ['escape-underscore', "'a_b' LIKE 'a\\_b'", 1],
    ['empty-pattern', "'' LIKE ''", 1],
    ['pct-only', "'anything' LIKE '%'", 1],
    ['case-upper', "'ABC' LIKE 'abc'", null], // collation-dependent
    ['multi-pct', "'aXbXc' LIKE 'a%b%c'", 1],
  ];
  for (const [nm, expr, expected] of likePatterns) {
    runner.add(FW, 'like-pattern', nm, async () => {
      const r = await q(`SELECT (${expr}) AS x`);
      if (expected !== null && expected !== undefined && Number(r[0].x) !== expected) {
        throw new BehaviorMismatch(`${expr} => ${r[0].x}; MySQL expects ${expected}`);
      }
    });
  }

  // -------------------------------------------------------------------------
  // H. REGEXP pattern matrix
  // -------------------------------------------------------------------------
  const regexpPatterns = [
    ['anchor-start', "'abc' REGEXP '^a'", 1],
    ['anchor-end', "'abc' REGEXP 'c$'", 1],
    ['char-class', "'abc' REGEXP '[a-c]'", 1],
    ['alternation', "'cat' REGEXP 'cat|dog'", 1],
    ['quantifier-star', "'aaa' REGEXP 'a*'", 1],
    ['quantifier-plus', "'aaa' REGEXP 'a+'", 1],
    ['quantifier-count', "'aaa' REGEXP 'a{3}'", 1],
    ['digit-class', "'a1b' REGEXP '[0-9]'", 1],
    ['word-boundary', "'hello world' REGEXP 'world'", 1],
    ['no-match', "'abc' REGEXP '^z'", 0],
  ];
  for (const [nm, expr, expected] of regexpPatterns) {
    runner.add(FW, 'regexp-pattern', nm, async () => {
      const r = await q(`SELECT (${expr}) AS x`);
      if (Number(r[0].x) !== expected) {
        throw new BehaviorMismatch(`${expr} => ${r[0].x}; MySQL expects ${expected}`);
      }
    });
  }

  // -------------------------------------------------------------------------
  // I. WINDOW-function-over-rows matrix
  // -------------------------------------------------------------------------
  function winTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, grp INT, v INT)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40),(5,2,50)`);
      return body(t);
    });
  }
  const windowFns = [
    ['row_number', (t) => `SELECT id, ROW_NUMBER() OVER (ORDER BY v) x FROM \`${t}\``],
    ['rank', (t) => `SELECT id, RANK() OVER (ORDER BY v) x FROM \`${t}\``],
    ['dense_rank', (t) => `SELECT id, DENSE_RANK() OVER (ORDER BY v) x FROM \`${t}\``],
    ['percent_rank', (t) => `SELECT id, PERCENT_RANK() OVER (ORDER BY v) x FROM \`${t}\``],
    ['cume_dist', (t) => `SELECT id, CUME_DIST() OVER (ORDER BY v) x FROM \`${t}\``],
    ['ntile', (t) => `SELECT id, NTILE(2) OVER (ORDER BY v) x FROM \`${t}\``],
    ['lag', (t) => `SELECT id, LAG(v) OVER (ORDER BY id) x FROM \`${t}\``],
    ['lead', (t) => `SELECT id, LEAD(v) OVER (ORDER BY id) x FROM \`${t}\``],
    ['first_value', (t) => `SELECT id, FIRST_VALUE(v) OVER (ORDER BY id) x FROM \`${t}\``],
    ['last_value', (t) => `SELECT id, LAST_VALUE(v) OVER (ORDER BY id) x FROM \`${t}\``],
    ['nth_value', (t) => `SELECT id, NTH_VALUE(v,2) OVER (ORDER BY id) x FROM \`${t}\``],
    ['sum-partition', (t) => `SELECT id, SUM(v) OVER (PARTITION BY grp) x FROM \`${t}\``],
    ['avg-partition', (t) => `SELECT id, AVG(v) OVER (PARTITION BY grp) x FROM \`${t}\``],
    ['count-partition', (t) => `SELECT id, COUNT(*) OVER (PARTITION BY grp) x FROM \`${t}\``],
    ['running-sum', (t) => `SELECT id, SUM(v) OVER (ORDER BY id ROWS UNBOUNDED PRECEDING) x FROM \`${t}\``],
  ];
  for (const [nm, build] of windowFns) {
    runner.add(FW, 'window-fn', nm, () => winTable(async (t) => { await q(build(t)); }));
  }

  // -------------------------------------------------------------------------
  // J. SET-operation matrix over two tables
  // -------------------------------------------------------------------------
  function setTables(body) {
    const a = uniq('sa'); const b = uniq('sb');
    return (async () => {
      try {
        await q(`CREATE TABLE \`${a}\` (id INT PRIMARY KEY)`);
        await q(`CREATE TABLE \`${b}\` (id INT PRIMARY KEY)`);
        await q(`INSERT INTO \`${a}\` VALUES (1),(2),(3)`);
        await q(`INSERT INTO \`${b}\` VALUES (2),(3),(4)`);
        return await body(a, b);
      } finally { await q(`DROP TABLE IF EXISTS \`${b}\``); await q(`DROP TABLE IF EXISTS \`${a}\``); }
    })();
  }
  const setOps = [
    ['union', (a, b) => `SELECT id FROM \`${a}\` UNION SELECT id FROM \`${b}\``],
    ['union-all', (a, b) => `SELECT id FROM \`${a}\` UNION ALL SELECT id FROM \`${b}\``],
    ['intersect', (a, b) => `SELECT id FROM \`${a}\` INTERSECT SELECT id FROM \`${b}\``],
    ['intersect-all', (a, b) => `SELECT id FROM \`${a}\` INTERSECT ALL SELECT id FROM \`${b}\``],
    ['except', (a, b) => `SELECT id FROM \`${a}\` EXCEPT SELECT id FROM \`${b}\``],
    ['except-all', (a, b) => `SELECT id FROM \`${a}\` EXCEPT ALL SELECT id FROM \`${b}\``],
    ['union-order', (a, b) => `(SELECT id FROM \`${a}\`) UNION (SELECT id FROM \`${b}\`) ORDER BY id`],
    ['union-limit', (a, b) => `(SELECT id FROM \`${a}\`) UNION (SELECT id FROM \`${b}\`) LIMIT 2`],
  ];
  for (const [nm, build] of setOps) {
    runner.add(FW, 'set-op', nm, () => setTables(async (a, b) => { await q(build(a, b)); }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
