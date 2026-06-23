'use strict';

// Fifth wave of raw-driver matrices: math-function argument variations, type
// conversion via column-context, join-type × on-condition matrix, GROUP BY
// modifiers, and subquery-shape matrix. Pure-SQL, fast, baseline-focused.

const { makePool, uniq } = require('../lib/db');
const { SQL_TYPES } = require('../lib/matrix');

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
  // A. MATH function argument-variation matrix (edge inputs)
  // -------------------------------------------------------------------------
  const mathArgs = [
    ['abs-neg', 'ABS(-12.5)'], ['abs-zero', 'ABS(0)'],
    ['round-0', 'ROUND(3.567)'], ['round-2', 'ROUND(3.567, 2)'], ['round-neg', 'ROUND(1234.5, -2)'],
    ['truncate-2', 'TRUNCATE(3.567, 2)'], ['truncate-neg', 'TRUNCATE(1234, -2)'],
    ['ceil-neg', 'CEIL(-4.2)'], ['floor-neg', 'FLOOR(-4.2)'],
    ['mod-fn', 'MOD(17, 5)'], ['mod-neg', 'MOD(-17, 5)'],
    ['pow-frac', 'POW(2, 0.5)'], ['pow-neg', 'POW(2, -2)'], ['pow-zero', 'POW(0, 0)'],
    ['sqrt-2', 'SQRT(2)'], ['sqrt-zero', 'SQRT(0)'],
    ['log-base', 'LOG(2, 8)'], ['log10-1000', 'LOG10(1000)'], ['log2-1024', 'LOG2(1024)'],
    ['exp-1', 'EXP(1)'], ['exp-0', 'EXP(0)'],
    ['sign-pos', 'SIGN(5)'], ['sign-neg', 'SIGN(-5)'], ['sign-zero', 'SIGN(0)'],
    ['greatest-3', 'GREATEST(3, 1, 4, 1, 5)'], ['least-3', 'LEAST(3, 1, 4, 1, 5)'],
    ['greatest-neg', 'GREATEST(-1, -5, -3)'], ['least-mixed', 'LEAST(1.5, 2, 0.5)'],
    ['rand-bounded', 'FLOOR(RAND() * 100)'], ['pi', 'PI()'],
    ['degrees-pi', 'DEGREES(PI())'], ['radians-180', 'RADIANS(180)'],
    ['sin-0', 'SIN(0)'], ['cos-0', 'COS(0)'], ['tan-0', 'TAN(0)'],
    ['atan2', 'ATAN2(1, 1)'], ['cot-1', 'COT(1)'],
    ['crc32', "CRC32('MySQL')"], ['conv-hex', "CONV('FF', 16, 10)"], ['conv-bin', "CONV('1010', 2, 10)"],
  ];
  for (const [nm, expr] of mathArgs) {
    runner.add(FW, 'math-args', nm, async () => { await q(`SELECT ${expr} AS x`); });
  }

  // -------------------------------------------------------------------------
  // B. JOIN-type × on-condition matrix (two seeded tables)
  // -------------------------------------------------------------------------
  function joinTables(body) {
    const a = uniq('ja'); const b = uniq('jb');
    return (async () => {
      try {
        await q(`CREATE TABLE \`${a}\` (id INT PRIMARY KEY, grp INT, v INT)`);
        await q(`CREATE TABLE \`${b}\` (id INT PRIMARY KEY, aid INT, w INT)`);
        await q(`INSERT INTO \`${a}\` VALUES (1,1,10),(2,1,20),(3,2,30)`);
        await q(`INSERT INTO \`${b}\` VALUES (1,1,100),(2,2,200),(3,5,300)`);
        return await body(a, b);
      } finally { await q(`DROP TABLE IF EXISTS \`${b}\``); await q(`DROP TABLE IF EXISTS \`${a}\``); }
    })();
  }
  const joinKinds = ['JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'LEFT OUTER JOIN', 'RIGHT OUTER JOIN'];
  const onConds = [
    ['eq', (a, b) => `${a}.id = ${b}.aid`],
    ['eq-and-filter', (a, b) => `${a}.id = ${b}.aid AND ${b}.w > 50`],
    ['eq-or', (a, b) => `${a}.id = ${b}.aid OR ${a}.grp = ${b}.aid`],
    ['range', (a, b) => `${a}.v <= ${b}.w`],
  ];
  for (const jk of joinKinds) {
    for (const [cnm, cond] of onConds) {
      runner.add(FW, 'join-matrix', `${jk.replace(/ /g, '-').toLowerCase()} ${cnm}`, () =>
        joinTables(async (a, b) => { await q(`SELECT * FROM \`${a}\` ${jk} \`${b}\` ON ${cond(a, b)}`); }));
    }
  }

  // -------------------------------------------------------------------------
  // C. GROUP BY modifier matrix
  // -------------------------------------------------------------------------
  function grpTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, a INT, b INT, v INT)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,1,1,10),(2,1,2,20),(3,2,1,30),(4,2,2,40)`);
      return body(t);
    });
  }
  const grpCases = [
    ['single-col', (t) => `SELECT a, SUM(v) s FROM \`${t}\` GROUP BY a`],
    ['multi-col', (t) => `SELECT a, b, SUM(v) s FROM \`${t}\` GROUP BY a, b`],
    ['rollup', (t) => `SELECT a, SUM(v) s FROM \`${t}\` GROUP BY a WITH ROLLUP`],
    ['expr', (t) => `SELECT a+b g, SUM(v) s FROM \`${t}\` GROUP BY a+b`],
    ['having-count', (t) => `SELECT a, COUNT(*) c FROM \`${t}\` GROUP BY a HAVING COUNT(*) > 1`],
    ['having-sum', (t) => `SELECT a, SUM(v) s FROM \`${t}\` GROUP BY a HAVING SUM(v) > 30`],
    ['having-and', (t) => `SELECT a, SUM(v) s FROM \`${t}\` GROUP BY a HAVING SUM(v) > 20 AND COUNT(*) >= 1`],
    ['order-after-group', (t) => `SELECT a, SUM(v) s FROM \`${t}\` GROUP BY a ORDER BY s DESC`],
    ['group-concat', (t) => `SELECT a, GROUP_CONCAT(v ORDER BY v) g FROM \`${t}\` GROUP BY a`],
    ['count-distinct-group', (t) => `SELECT a, COUNT(DISTINCT b) c FROM \`${t}\` GROUP BY a`],
  ];
  for (const [nm, build] of grpCases) {
    runner.add(FW, 'groupby-modifier', nm, () => grpTable(async (t) => { await q(build(t)); }));
  }

  // -------------------------------------------------------------------------
  // D. SUBQUERY-shape matrix
  // -------------------------------------------------------------------------
  function subTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, grp INT, v INT)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,1,10),(2,1,20),(3,2,30),(4,2,40)`);
      return body(t);
    });
  }
  const subCases = [
    ['scalar-select', (t) => `SELECT (SELECT MAX(v) FROM \`${t}\`) m`],
    ['scalar-correlated', (t) => `SELECT id, (SELECT COUNT(*) FROM \`${t}\` x WHERE x.grp=y.grp) c FROM \`${t}\` y`],
    ['where-in', (t) => `SELECT * FROM \`${t}\` WHERE grp IN (SELECT grp FROM \`${t}\` WHERE v>25)`],
    ['where-not-in', (t) => `SELECT * FROM \`${t}\` WHERE id NOT IN (SELECT id FROM \`${t}\` WHERE v>25)`],
    ['where-exists', (t) => `SELECT * FROM \`${t}\` y WHERE EXISTS (SELECT 1 FROM \`${t}\` x WHERE x.grp=y.grp AND x.v>y.v)`],
    ['where-not-exists', (t) => `SELECT * FROM \`${t}\` y WHERE NOT EXISTS (SELECT 1 FROM \`${t}\` x WHERE x.v>y.v)`],
    ['where-any', (t) => `SELECT * FROM \`${t}\` WHERE v > ANY (SELECT v FROM \`${t}\` WHERE grp=1)`],
    ['where-all', (t) => `SELECT * FROM \`${t}\` WHERE v > ALL (SELECT v FROM \`${t}\` WHERE grp=1)`],
    ['from-derived', (t) => `SELECT * FROM (SELECT grp, SUM(v) s FROM \`${t}\` GROUP BY grp) d WHERE d.s>30`],
    ['from-derived-join', (t) => `SELECT a.id FROM \`${t}\` a JOIN (SELECT grp, MAX(v) mv FROM \`${t}\` GROUP BY grp) b ON a.grp=b.grp AND a.v=b.mv`],
    ['in-with-distinct', (t) => `SELECT * FROM \`${t}\` WHERE grp IN (SELECT DISTINCT grp FROM \`${t}\`)`],
    ['comparison-subquery', (t) => `SELECT * FROM \`${t}\` WHERE v > (SELECT AVG(v) FROM \`${t}\`)`],
  ];
  for (const [nm, build] of subCases) {
    runner.add(FW, 'subquery-shape', nm, () => subTable(async (t) => { await q(build(t)); }));
  }

  // -------------------------------------------------------------------------
  // E. TRANSACTION isolation / behavior matrix (single conn)
  // -------------------------------------------------------------------------
  async function withConn(body) {
    const c = await getPool().getConnection();
    try { return await body(c); } finally { c.release(); }
  }
  const isoLevels = ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'];
  for (const lvl of isoLevels) {
    runner.add(FW, 'tx-isolation', `set ${lvl}`, () => withConn(async (c) => {
      await c.query(`SET SESSION TRANSACTION ISOLATION LEVEL ${lvl}`);
      await c.beginTransaction();
      await c.query('SELECT 1');
      await c.commit();
    }));
  }
  runner.add(FW, 'tx-isolation', 'start-transaction-read-only', () => withConn(async (c) => {
    await c.query('START TRANSACTION READ ONLY'); await c.query('SELECT 1'); await c.query('COMMIT');
  }));
  runner.add(FW, 'tx-isolation', 'start-transaction-read-write', () => withConn(async (c) => {
    await c.query('START TRANSACTION READ WRITE'); await c.query('SELECT 1'); await c.query('COMMIT');
  }));
  runner.add(FW, 'tx-isolation', 'consistent-snapshot', () => withConn(async (c) => {
    await c.query('START TRANSACTION WITH CONSISTENT SNAPSHOT'); await c.query('SELECT 1'); await c.query('COMMIT');
  }));

  // -------------------------------------------------------------------------
  // F. INDEX-type DDL matrix per indexable type
  // -------------------------------------------------------------------------
  const indexable = SQL_TYPES.filter((t) => ['int', 'num', 'dt', 'enum'].includes(t.group));
  for (const ty of indexable) {
    runner.add(FW, `index-type/${ty.group}`, `btree ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql}, INDEX ix(c))`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT * FROM \`${t}\` WHERE c=?`, [ty.sample]);
      }));
    runner.add(FW, `index-type/${ty.group}`, `unique ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql}, UNIQUE KEY uq(c))`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
      }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
