'use strict';

// Third wave of raw-driver matrices: full-type-set operation coverage,
// prepared-statement re-execution, multi-row roundtrip integrity, expression
// projections per type, and ALTER-type-change matrix. These broaden coverage
// to every SQL type (not just the numeric/string/date subset).

const { makePool, uniq } = require('../lib/db');
const { SQL_TYPES } = require('../lib/matrix');
const { BehaviorMismatch } = require('../lib/errors');

let pool = null;
function getPool() { if (!pool) pool = makePool('raw', { connectionLimit: 4 }); return pool; }
async function q(sql, params) { const [r] = await getPool().query(sql, params); return r; }
async function exec(sql, params) { const [r] = await getPool().execute(sql, params); return r; }
async function withTable(ddl, body) {
  const t = uniq('t'); await q(ddl(t));
  try { return await body(t); } finally { try { await q(`DROP TABLE IF EXISTS \`${t}\``); } catch (_) {} }
}

function register(runner) {
  const FW = 'raw';
  const valueTypes = SQL_TYPES.filter((t) => !t.skipValue);

  // -------------------------------------------------------------------------
  // A. FULL-type multi-row roundtrip integrity (3 rows, verify count + values)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes) {
    runner.add(FW, `multirow/${ty.group}`, `roundtrip-3 ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?),(3,?)`, [ty.sample, ty.boundary, ty.sample]);
        const r = await q(`SELECT COUNT(*) n FROM \`${t}\``);
        if (Number(r[0].n) !== 3) throw new BehaviorMismatch(`expected 3 rows, got ${r[0].n}`);
      }));
  }

  // -------------------------------------------------------------------------
  // B. FULL-type SELECT-projection expressions (IS NULL / IFNULL / coalesce)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes) {
    runner.add(FW, `project/${ty.group}`, `select-ifnull ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT id, IFNULL(c, c) y FROM \`${t}\``);
      }));
    runner.add(FW, `project/${ty.group}`, `select-as-char ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT CAST(c AS CHAR) y FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // C. PREPARED re-execution (statement reuse across multiple param sets)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes) {
    runner.add(FW, `prepared-reuse/${ty.group}`, `reuse ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        for (let i = 1; i <= 3; i++) {
          await exec(`INSERT INTO \`${t}\` (id,c) VALUES (?,?)`, [i, i % 2 ? ty.sample : ty.boundary]);
        }
        await exec(`SELECT * FROM \`${t}\` WHERE id = ?`, [2]);
      }));
  }

  // -------------------------------------------------------------------------
  // D. ALTER add-column-of-type matrix (every type via ALTER)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes) {
    runner.add(FW, `alter-add/${ty.group}`, `add ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
        await q(`ALTER TABLE \`${t}\` ADD COLUMN c ${ty.sql}`);
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
      }));
  }

  // -------------------------------------------------------------------------
  // E. TYPE-CHANGE via MODIFY matrix (widen / convert)
  // -------------------------------------------------------------------------
  const typeChanges = [
    ['int-to-bigint', 'INT', 'BIGINT', 100],
    ['smallint-to-int', 'SMALLINT', 'INT', 1000],
    ['varchar-widen', 'VARCHAR(10)', 'VARCHAR(100)', 'short'],
    ['char-to-varchar', 'CHAR(10)', 'VARCHAR(50)', 'abc'],
    ['decimal-rescale', 'DECIMAL(10,2)', 'DECIMAL(18,4)', '1.50'],
    ['text-to-longtext', 'TEXT', 'LONGTEXT', 'hello'],
    ['date-to-datetime', 'DATE', 'DATETIME', '2026-06-23'],
    ['int-to-varchar', 'INT', 'VARCHAR(20)', 42],
    ['float-to-double', 'FLOAT', 'DOUBLE', 1.5],
    ['varchar-to-text', 'VARCHAR(50)', 'TEXT', 'abc'],
  ];
  for (const [nm, from, to, val] of typeChanges) {
    runner.add(FW, 'alter-modify', nm, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${from})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [val]);
        await q(`ALTER TABLE \`${t}\` MODIFY COLUMN c ${to}`);
        await q(`SELECT c FROM \`${t}\` WHERE id=1`);
      }));
  }

  // -------------------------------------------------------------------------
  // F. INSERT-form matrix per representative type (literal vs bound vs SET)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes.filter((t) => ['int', 'num', 'str', 'dt'].includes(t.group))) {
    runner.add(FW, `insert-form/${ty.name}`, 'bound-vs-select', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`INSERT INTO \`${t}\` (id,c) SELECT 2, c FROM \`${t}\` WHERE id=1`);
        const r = await q(`SELECT COUNT(*) n FROM \`${t}\``);
        if (Number(r[0].n) !== 2) throw new BehaviorMismatch(`insert-select produced ${r[0].n} rows`);
      }));
  }

  // -------------------------------------------------------------------------
  // G. ORDER BY direction / nulls handling per type
  // -------------------------------------------------------------------------
  for (const ty of valueTypes.filter((t) => ['int', 'num', 'str', 'dt'].includes(t.group))) {
    runner.add(FW, `orderby/${ty.name}`, 'asc-with-null', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,NULL),(3,?)`, [ty.sample, ty.boundary]);
        await q(`SELECT * FROM \`${t}\` ORDER BY c ASC`);
        await q(`SELECT * FROM \`${t}\` ORDER BY c DESC`);
      }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
