'use strict';

// Additional generated matrices for the raw mysql2 baseline to broaden coverage
// (operators, casts, expression semantics, prepared-statement type binding,
//  more query patterns). Reuses the same shared pool as scenarios/raw.js.

const { makePool, uniq } = require('../lib/db');
const { SQL_TYPES, SQL_FUNCTIONS } = require('../lib/matrix');
const { BehaviorMismatch, expect } = require('../lib/errors');

let pool = null;
function getPool() {
  if (!pool) pool = makePool('raw', { connectionLimit: 4 });
  return pool;
}
async function q(sql, params) { const [r] = await getPool().query(sql, params); return r; }
async function exec(sql, params) { const [r] = await getPool().execute(sql, params); return r; }
async function withTable(ddl, body) {
  const t = uniq('t');
  await q(ddl(t));
  try { return await body(t); } finally { try { await q(`DROP TABLE IF EXISTS \`${t}\``); } catch (_) {} }
}

function register(runner) {
  const FW = 'raw';

  // -------------------------------------------------------------------------
  // A. OPERATOR matrix (arithmetic / comparison / logical / bitwise) as SELECTs
  // -------------------------------------------------------------------------
  const operators = [
    ['arith', 'plus', '2 + 3'], ['arith', 'minus', '5 - 2'], ['arith', 'mul', '4 * 3'],
    ['arith', 'div', '10 / 4'], ['arith', 'intdiv', '10 DIV 3'], ['arith', 'mod', '10 % 3'],
    ['arith', 'mod-kw', '10 MOD 3'], ['arith', 'neg', '- 5'], ['arith', 'paren', '(2 + 3) * 4'],
    ['cmp', 'eq', '1 = 1'], ['cmp', 'ne', '1 <> 2'], ['cmp', 'ne2', '1 != 2'],
    ['cmp', 'lt', '1 < 2'], ['cmp', 'le', '1 <= 1'], ['cmp', 'gt', '2 > 1'], ['cmp', 'ge', '2 >= 2'],
    ['cmp', 'nullsafe', '1 <=> 1'], ['cmp', 'nullsafe-null', 'NULL <=> NULL'],
    ['cmp', 'between', '5 BETWEEN 1 AND 10'], ['cmp', 'not-between', '5 NOT BETWEEN 6 AND 10'],
    ['cmp', 'in', '3 IN (1,2,3)'], ['cmp', 'not-in', '4 NOT IN (1,2,3)'],
    ['cmp', 'is-null', 'NULL IS NULL'], ['cmp', 'is-not-null', '1 IS NOT NULL'],
    ['cmp', 'like', "'abc' LIKE 'a%'"], ['cmp', 'not-like', "'abc' NOT LIKE 'z%'"],
    ['cmp', 'like-underscore', "'abc' LIKE 'a_c'"], ['cmp', 'like-escape', "'a%b' LIKE 'a\\%b'"],
    ['cmp', 'regexp', "'abc' REGEXP '^a'"], ['cmp', 'rlike', "'abc' RLIKE 'b'"],
    ['cmp', 'not-regexp', "'abc' NOT REGEXP '^z'"],
    ['logic', 'and', '1 AND 1'], ['logic', 'or', '1 OR 0'], ['logic', 'not', 'NOT 0'],
    ['logic', 'xor', '1 XOR 0'], ['logic', 'and-symbol', '1 && 1'],
    ['bitwise', 'band', '6 & 3'], ['bitwise', 'bor', '4 | 1'], ['bitwise', 'bxor', '5 ^ 1'],
    ['bitwise', 'bnot', '~ 0'], ['bitwise', 'lshift', '1 << 4'], ['bitwise', 'rshift', '16 >> 2'],
    ['string', 'concat-fn', "CONCAT('a','b')"], ['string', 'collate', "'a' COLLATE utf8mb4_bin"],
    ['cast', 'cast-signed', "CAST('5' AS SIGNED)"], ['cast', 'cast-decimal', "CAST('5.5' AS DECIMAL(10,2))"],
    ['cast', 'cast-char', "CAST(5 AS CHAR)"], ['cast', 'cast-date', "CAST('2026-06-23' AS DATE)"],
    ['cast', 'cast-datetime', "CAST('2026-06-23 11:00' AS DATETIME)"], ['cast', 'cast-time', "CAST('11:00:00' AS TIME)"],
    ['cast', 'convert-signed', "CONVERT('5', SIGNED)"], ['cast', 'convert-using', "CONVERT('a' USING utf8mb4)"],
    ['cast', 'binary-cast', "BINARY 'abc'"],
  ];
  for (const [cat, name, expr] of operators) {
    runner.add(FW, `op/${cat}`, name, async () => { await q(`SELECT ${expr} AS r`); });
  }

  // -------------------------------------------------------------------------
  // B. Type × COMPARISON-IN-WHERE matrix (broader than raw.js where test):
  //    for each type, test =, >, <, BETWEEN, IN, ORDER, GROUP, COUNT, DISTINCT
  // -------------------------------------------------------------------------
  const compTypes = SQL_TYPES.filter((t) => !t.skipValue && ['int', 'num', 'str', 'dt'].includes(t.group));
  for (const ty of compTypes) {
    const cat = `where/${ty.name}`;
    const ddl = (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`;
    const seed = async (t) => { await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]); };
    const cases = [
      ['gt', (t) => `SELECT * FROM \`${t}\` WHERE c > ?`, [ty.sample]],
      ['lt', (t) => `SELECT * FROM \`${t}\` WHERE c < ?`, [ty.boundary]],
      ['ge', (t) => `SELECT * FROM \`${t}\` WHERE c >= ?`, [ty.sample]],
      ['le', (t) => `SELECT * FROM \`${t}\` WHERE c <= ?`, [ty.boundary]],
      ['ne', (t) => `SELECT * FROM \`${t}\` WHERE c <> ?`, [ty.sample]],
      ['in', (t) => `SELECT * FROM \`${t}\` WHERE c IN (?,?)`, [ty.sample, ty.boundary]],
      ['between', (t) => `SELECT * FROM \`${t}\` WHERE c BETWEEN ? AND ?`, [ty.sample, ty.boundary]],
      ['order-asc', (t) => `SELECT * FROM \`${t}\` ORDER BY c ASC`, []],
      ['order-desc', (t) => `SELECT * FROM \`${t}\` ORDER BY c DESC`, []],
      ['distinct', (t) => `SELECT DISTINCT c FROM \`${t}\``, []],
      ['count-group', (t) => `SELECT c, COUNT(*) n FROM \`${t}\` GROUP BY c`, []],
      ['min-max', (t) => `SELECT MIN(c) mn, MAX(c) mx FROM \`${t}\``, []],
    ];
    for (const [nm, build, params] of cases) {
      runner.add(FW, cat, nm, () => withTable(ddl, async (t) => { await seed(t); await q(build(t), params); }));
    }
  }

  // -------------------------------------------------------------------------
  // C. PREPARED-STATEMENT type binding matrix (binary protocol), broad
  // -------------------------------------------------------------------------
  for (const ty of SQL_TYPES.filter((t) => !t.skipValue)) {
    const ddl = (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`;
    runner.add(FW, `prepared-bind/${ty.group}`, `bind-insert ${ty.sql}`, () =>
      withTable(ddl, async (t) => { await exec(`INSERT INTO \`${t}\` (id,c) VALUES (?,?)`, [1, ty.sample]); }));
    runner.add(FW, `prepared-bind/${ty.group}`, `bind-where ${ty.sql}`, () =>
      withTable(ddl, async (t) => {
        await exec(`INSERT INTO \`${t}\` (id,c) VALUES (?,?)`, [1, ty.sample]);
        await exec(`SELECT * FROM \`${t}\` WHERE c = ?`, [ty.sample]);
      }));
  }

  // -------------------------------------------------------------------------
  // D. FUNCTION matrix — additional usage form: inside WHERE and ORDER BY
  // -------------------------------------------------------------------------
  for (const [cat, name, expr] of SQL_FUNCTIONS) {
    runner.add(FW, `func-where/${cat}`, `${name} in-where`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1)`);
        await q(`SELECT * FROM \`${t}\` WHERE (${expr}) IS NOT NULL`);
      }));
  }

  // -------------------------------------------------------------------------
  // E. Expression / semantics matrix (numeric formatting, NULL handling, etc.)
  // -------------------------------------------------------------------------
  const exprs = [
    ['null-arith', 'NULL + 1', null],
    ['null-concat', "CONCAT('a', NULL)", null],
    ['coalesce-chain', 'COALESCE(NULL, NULL, 5)', 5],
    ['ifnull', "IFNULL(NULL, 'x')", 'x'],
    ['nullif-eq', 'NULLIF(5, 5)', null],
    ['empty-string-num', "'' + 0", null],
    ['bool-true', 'TRUE', 1],
    ['bool-false', 'FALSE', 0],
    ['int-div-zero', '1 DIV 0', null],
    ['mod-zero', '1 % 0', null],
  ];
  for (const [nm, expr, expected] of exprs) {
    runner.add(FW, 'semantics', nm, async () => {
      const r = await q(`SELECT ${expr} AS x`);
      if (expected !== undefined) {
        const got = r[0].x;
        const norm = (v) => (v === null ? null : Number(v));
        if (JSON.stringify(norm(got)) !== JSON.stringify(expected)) {
          throw new BehaviorMismatch(`${expr} => ${JSON.stringify(got)}, MySQL expects ${JSON.stringify(expected)}`);
        }
      }
    });
  }

  // -------------------------------------------------------------------------
  // F. More QUERY patterns (limit forms, joins with conditions, set ops)
  // -------------------------------------------------------------------------
  function twoTables(body) {
    const a = uniq('qa'); const b = uniq('qb');
    return (async () => {
      try {
        await q(`CREATE TABLE \`${a}\` (id INT PRIMARY KEY, grp INT, amt INT)`);
        await q(`CREATE TABLE \`${b}\` (id INT PRIMARY KEY, aid INT)`);
        await q(`INSERT INTO \`${a}\` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40)`);
        await q(`INSERT INTO \`${b}\` VALUES (1,1),(2,2),(3,3)`);
        return await body(a, b);
      } finally { await q(`DROP TABLE IF EXISTS \`${b}\``); await q(`DROP TABLE IF EXISTS \`${a}\``); }
    })();
  }
  const morePatterns = [
    ['limit-2-args', (a) => `SELECT * FROM \`${a}\` LIMIT 1, 2`],
    ['order-limit', (a) => `SELECT * FROM \`${a}\` ORDER BY amt DESC LIMIT 2`],
    ['group-rollup', (a) => `SELECT grp, SUM(amt) s FROM \`${a}\` GROUP BY grp WITH ROLLUP`],
    ['having-agg', (a) => `SELECT grp, SUM(amt) s FROM \`${a}\` GROUP BY grp HAVING SUM(amt) > 25`],
    ['count-distinct', (a) => `SELECT COUNT(DISTINCT grp) c FROM \`${a}\``],
    ['join-using', (a, b) => `SELECT * FROM \`${a}\` JOIN \`${b}\` USING (id)`],
    ['join-multi-cond', (a, b) => `SELECT * FROM \`${a}\` x JOIN \`${b}\` y ON x.id=y.aid AND y.id>0`],
    ['natural-join', (a, b) => `SELECT * FROM \`${a}\` NATURAL JOIN \`${b}\``],
    ['in-subquery-corr', (a, b) => `SELECT * FROM \`${a}\` x WHERE x.id IN (SELECT aid FROM \`${b}\` y WHERE y.id=x.id)`],
    ['scalar-in-select', (a) => `SELECT id, (SELECT COUNT(*) FROM \`${a}\`) total FROM \`${a}\``],
    ['case-in-order', (a) => `SELECT * FROM \`${a}\` ORDER BY CASE WHEN grp=1 THEN 0 ELSE 1 END`],
    ['union-distinct', (a) => `SELECT grp FROM \`${a}\` UNION SELECT grp FROM \`${a}\``],
    ['nested-derived', (a) => `SELECT * FROM (SELECT * FROM (SELECT grp, SUM(amt) s FROM \`${a}\` GROUP BY grp) d1) d2`],
    ['exists-not-exists', (a, b) => `SELECT * FROM \`${a}\` x WHERE NOT EXISTS (SELECT 1 FROM \`${b}\` y WHERE y.aid=x.id)`],
    ['multi-window', (a) => `SELECT id, ROW_NUMBER() OVER w rn, RANK() OVER w rk FROM \`${a}\` WINDOW w AS (ORDER BY amt)`],
    ['window-frame', (a) => `SELECT id, SUM(amt) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) s FROM \`${a}\``],
    ['first-last-value', (a) => `SELECT id, FIRST_VALUE(amt) OVER (ORDER BY id) f FROM \`${a}\``],
    ['cume-dist', (a) => `SELECT id, CUME_DIST() OVER (ORDER BY amt) c FROM \`${a}\``],
    ['percent-rank', (a) => `SELECT id, PERCENT_RANK() OVER (ORDER BY amt) p FROM \`${a}\``],
    ['lead', (a) => `SELECT id, LEAD(amt) OVER (ORDER BY id) l FROM \`${a}\``],
  ];
  for (const [nm, build] of morePatterns) {
    runner.add(FW, 'query2', nm, () => twoTables(async (a, b) => { await q(build(a, b)); }));
  }

  // -------------------------------------------------------------------------
  // G. CONSTRAINT enforcement matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'constraint', 'pk-duplicate-rejected', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1)`);
      try { await q(`INSERT INTO \`${t}\` VALUES (1)`); }
      catch (e) { return; }
      throw new BehaviorMismatch('duplicate PK accepted; MySQL rejects (1062)');
    }));
  runner.add(FW, 'constraint', 'unique-duplicate-rejected', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, e INT UNIQUE)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,5)`);
      try { await q(`INSERT INTO \`${t}\` VALUES (2,5)`); }
      catch (e) { return; }
      throw new BehaviorMismatch('duplicate UNIQUE accepted; MySQL rejects (1062)');
    }));
  runner.add(FW, 'constraint', 'not-null-rejected', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n INT NOT NULL)`, async (t) => {
      try { await q(`INSERT INTO \`${t}\` (id) VALUES (1)`); }
      catch (e) { return; }
      throw new BehaviorMismatch('NULL into NOT NULL accepted; MySQL rejects');
    }));
  runner.add(FW, 'constraint', 'fk-violation-rejected', async () => {
    const p = uniq('p'); const c = uniq('c');
    try {
      await q(`CREATE TABLE \`${p}\` (id INT PRIMARY KEY)`);
      await q(`CREATE TABLE \`${c}\` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES \`${p}\`(id))`);
      try { await q(`INSERT INTO \`${c}\` VALUES (1, 999)`); }
      catch (e) { return; }
      throw new BehaviorMismatch('FK violation accepted; MySQL rejects');
    } finally { await q(`DROP TABLE IF EXISTS \`${c}\``); await q(`DROP TABLE IF EXISTS \`${p}\``); }
  });
  runner.add(FW, 'constraint', 'enum-invalid-value', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, e ENUM('a','b'))`, async (t) => {
      try { await q(`INSERT INTO \`${t}\` VALUES (1,'zzz')`); }
      catch (e) { return; }
      const r = await q(`SELECT e FROM \`${t}\` WHERE id=1`);
      throw new BehaviorMismatch(`invalid ENUM accepted as ${JSON.stringify(r[0] && r[0].e)}; MySQL strict rejects`);
    }));
  runner.add(FW, 'constraint', 'int-overflow', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c TINYINT)`, async (t) => {
      try { await q(`INSERT INTO \`${t}\` VALUES (1, 9999)`); }
      catch (e) { return; }
      const r = await q(`SELECT c FROM \`${t}\` WHERE id=1`);
      throw new BehaviorMismatch(`TINYINT 9999 accepted as ${r[0] && r[0].c}; MySQL strict rejects (1264)`);
    }));
  runner.add(FW, 'constraint', 'string-truncation', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c VARCHAR(3))`, async (t) => {
      try { await q(`INSERT INTO \`${t}\` VALUES (1, 'abcdefg')`); }
      catch (e) { return; }
      const r = await q(`SELECT c FROM \`${t}\` WHERE id=1`);
      throw new BehaviorMismatch(`oversized string accepted as ${JSON.stringify(r[0] && r[0].c)}; MySQL strict rejects (1406)`);
    }));

  // -------------------------------------------------------------------------
  // H. INFORMATION_SCHEMA introspection matrix (ORM tooling depends on these)
  // -------------------------------------------------------------------------
  const isTables = [
    'tables', 'columns', 'statistics', 'key_column_usage', 'referential_constraints',
    'table_constraints', 'schemata', 'views', 'check_constraints',
    'collation_character_set_applicability', 'character_sets', 'collations',
    'engines', 'table_options', 'column_statistics', 'partitions', 'triggers', 'routines',
    'parameters', 'events', 'user_privileges', 'schema_privileges', 'table_privileges', 'column_privileges',
  ];
  for (const tbl of isTables) {
    runner.add(FW, 'info-schema', `query ${tbl}`, async () => {
      await q(`SELECT * FROM information_schema.\`${tbl}\` LIMIT 1`);
    });
  }
  // SHOW surface matrix
  const shows = [
    'SHOW DATABASES', 'SHOW TABLES', 'SHOW VARIABLES LIKE "version"', 'SHOW STATUS',
    'SHOW ENGINES', 'SHOW CHARACTER SET', 'SHOW COLLATION', 'SHOW WARNINGS',
    'SHOW PROCESSLIST', 'SHOW GRANTS', 'SHOW INDEX FROM information_schema.tables',
  ];
  for (const s of shows) {
    runner.add(FW, 'show', s.replace(/[^A-Za-z]/g, '_').slice(0, 30), async () => { await q(s); });
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
