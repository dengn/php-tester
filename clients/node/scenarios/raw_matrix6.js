'use strict';

// Sixth wave: per-type DEFAULT/NOT-NULL/AUTO column-attribute matrix, distinct
// query-shape coverage per type, UPSERT/REPLACE/IGNORE per type, and an
// expression-projection matrix over multiple functions per stored column.

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
  const valueTypes = SQL_TYPES.filter((t) => !t.skipValue);

  // -------------------------------------------------------------------------
  // A. NOT NULL column attribute per type (insert valid value)
  // -------------------------------------------------------------------------
  for (const ty of valueTypes) {
    runner.add(FW, `attr/${ty.group}`, `not-null ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NOT NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
      }));
  }

  // -------------------------------------------------------------------------
  // B. INSERT IGNORE / REPLACE / ON DUPLICATE per representative type
  // -------------------------------------------------------------------------
  for (const ty of valueTypes.filter((t) => ['int', 'num', 'str', 'dt'].includes(t.group))) {
    runner.add(FW, `dml-form/${ty.name}`, 'replace', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`REPLACE INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.boundary]);
      }));
    runner.add(FW, `dml-form/${ty.name}`, 'insert-ignore', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`INSERT IGNORE INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.boundary]);
      }));
    runner.add(FW, `dml-form/${ty.name}`, 'on-duplicate-update', () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?) ON DUPLICATE KEY UPDATE c=VALUES(c)`, [ty.boundary]);
      }));
  }

  // -------------------------------------------------------------------------
  // C. DISTINCT / GROUP query shapes per type
  // -------------------------------------------------------------------------
  for (const ty of valueTypes.filter((t) => ['int', 'num', 'str', 'dt', 'enum'].includes(t.group))) {
    runner.add(FW, `distinct/${ty.group}`, `distinct ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?),(3,?)`, [ty.sample, ty.sample, ty.boundary]);
        const r = await q(`SELECT DISTINCT c FROM \`${t}\``);
        if (r.length !== 2) throw new BehaviorMismatch(`DISTINCT returned ${r.length}, expected 2`);
      }));
    runner.add(FW, `distinct/${ty.group}`, `count-distinct ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]);
        await q(`SELECT COUNT(DISTINCT c) n FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // D. CASE/IF expression over typed columns
  // -------------------------------------------------------------------------
  for (const ty of valueTypes.filter((t) => ['int', 'num', 'str', 'dt'].includes(t.group))) {
    runner.add(FW, `case-expr/${ty.group}`, `case ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]);
        await q(`SELECT id, CASE WHEN c = ? THEN 'a' ELSE 'b' END label FROM \`${t}\``, [ty.sample]);
      }));
    runner.add(FW, `case-expr/${ty.group}`, `coalesce-nullif ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,NULL)`, [ty.sample]);
        await q(`SELECT COALESCE(c, ?) v, NULLIF(c, ?) w FROM \`${t}\``, [ty.boundary, ty.sample]);
      }));
  }

  // -------------------------------------------------------------------------
  // E. PROJECTION-with-arithmetic over numeric columns
  // -------------------------------------------------------------------------
  const numTypes = valueTypes.filter((t) => t.group === 'num' || t.group === 'int');
  for (const ty of numTypes) {
    runner.add(FW, `arith-proj/${ty.name}`, `column-arithmetic ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]);
        await q(`SELECT c + 1 a, c * 2 b, c - 1 d FROM \`${t}\``);
      }));
    runner.add(FW, `arith-proj/${ty.name}`, `aggregate-arithmetic ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]);
        await q(`SELECT SUM(c) s, AVG(c) a, MAX(c)-MIN(c) range_ FROM \`${t}\``);
      }));
  }

  // -------------------------------------------------------------------------
  // F. WHERE-with-function over string columns
  // -------------------------------------------------------------------------
  const strTypes = valueTypes.filter((t) => t.group === 'str');
  for (const ty of strTypes) {
    runner.add(FW, `str-where/${ty.name}`, `lower-eq ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT * FROM \`${t}\` WHERE LOWER(c) = LOWER(?)`, [ty.sample]);
      }));
    runner.add(FW, `str-where/${ty.name}`, `length-gt ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT * FROM \`${t}\` WHERE LENGTH(c) > 0`);
      }));
    runner.add(FW, `str-where/${ty.name}`, `concat-proj ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        await q(`SELECT CONCAT('[', c, ']') x FROM \`${t}\``);
      }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
