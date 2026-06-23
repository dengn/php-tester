'use strict';

// Seventh wave: MatrixOne-specific feature coverage (vector columns + KNN,
// fulltext search), plus geometry/spatial attempts, bit operations, and a
// final per-type "select-back-typed" matrix. These exercise both MatrixOne
// extensions (expected to work) and MySQL features that may be gaps.

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
  // A. MatrixOne VECTOR feature matrix (VECF32 / VECF64 + distance fns + index)
  // -------------------------------------------------------------------------
  const vecDims = [3, 8, 16];
  for (const dim of vecDims) {
    const vec = '[' + Array.from({ length: dim }, (_, i) => i + 1).join(',') + ']';
    const vec2 = '[' + Array.from({ length: dim }, () => 0).join(',') + ']';
    runner.add(FW, 'mo-vector', `vecf32-${dim} insert`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF32(${dim}))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?), (2, ?)`, [vec, vec2]);
      }));
    runner.add(FW, 'mo-vector', `vecf64-${dim} insert`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF64(${dim}))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?)`, [vec]);
      }));
    runner.add(FW, 'mo-vector', `vecf32-${dim} l2_distance`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF32(${dim}))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?), (2, ?)`, [vec, vec2]);
        await q(`SELECT id, l2_distance(v, ?) d FROM \`${t}\` ORDER BY d LIMIT 1`, [vec]);
      }));
    runner.add(FW, 'mo-vector', `vecf32-${dim} cosine_distance`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF32(${dim}))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?), (2, ?)`, [vec, vec2]);
        await q(`SELECT id, cosine_distance(v, ?) d FROM \`${t}\` ORDER BY d LIMIT 1`, [vec]);
      }));
    runner.add(FW, 'mo-vector', `vecf32-${dim} inner_product`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF32(${dim}))`, async (t) => {
        await q(`INSERT INTO \`${t}\` VALUES (1, ?)`, [vec]);
        await q(`SELECT inner_product(v, ?) p FROM \`${t}\``, [vec]);
      }));
  }
  // IVFFLAT index on a vector column
  runner.add(FW, 'mo-vector', 'ivfflat-index', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v VECF32(3))`, async (t) => {
      await q('SET experimental_ivf_index=1').catch(() => {});
      await q(`CREATE INDEX ivf ON \`${t}\`(v) USING ivfflat LISTS=1 OP_TYPE 'vector_l2_ops'`);
    }));

  // -------------------------------------------------------------------------
  // B. FULLTEXT search matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'mo-fulltext', 'create-fulltext-index', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, 'the quick brown fox')`);
    }));
  runner.add(FW, 'mo-fulltext', 'match-against-natural', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, 'the quick brown fox'),(2,'lazy dog')`);
      await q(`SELECT * FROM \`${t}\` WHERE MATCH(body) AGAINST('fox')`);
    }));
  runner.add(FW, 'mo-fulltext', 'match-against-boolean', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, 'the quick brown fox')`);
      await q(`SELECT * FROM \`${t}\` WHERE MATCH(body) AGAINST('+quick -dog' IN BOOLEAN MODE)`);
    }));

  // -------------------------------------------------------------------------
  // C. SPATIAL / GEOMETRY matrix (column type accepted, functions may be gaps)
  // -------------------------------------------------------------------------
  runner.add(FW, 'spatial-col', 'geometry-column', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, g GEOMETRY)`, async () => {}));
  runner.add(FW, 'spatial-col', 'point-insert', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, g GEOMETRY)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, ST_GeomFromText('POINT(1 1)'))`);
    }));
  runner.add(FW, 'spatial-col', 'st-astext', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, g GEOMETRY)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, ST_GeomFromText('POINT(1 1)'))`);
      await q(`SELECT ST_AsText(g) x FROM \`${t}\``);
    }));

  // -------------------------------------------------------------------------
  // D. BIT operation matrix on a BIT column
  // -------------------------------------------------------------------------
  runner.add(FW, 'bit-col', 'insert-bit', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, b BIT(8))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, b'10101010')`);
    }));
  runner.add(FW, 'bit-col', 'select-bit-as-int', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, b BIT(8))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, b'00000101')`);
      await q(`SELECT b+0 v FROM \`${t}\``);
    }));
  runner.add(FW, 'bit-col', 'bitwise-and', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, b BIT(8))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, b'00001111')`);
      await q(`SELECT (b & b'00000011')+0 v FROM \`${t}\``);
    }));

  // -------------------------------------------------------------------------
  // E. UUID column / function matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'uuid-feature', 'uuid-column', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, u UUID)`, async () => {}));
  runner.add(FW, 'uuid-feature', 'uuid-insert-select', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, u UUID)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, ?)`, ['6f9619ff-8b86-d011-b42d-00cf4fc964ff']);
      await q(`SELECT u FROM \`${t}\``);
    }));
  runner.add(FW, 'uuid-feature', 'uuid-fn', async () => { await q('SELECT UUID() x'); });
  runner.add(FW, 'uuid-feature', 'char36-uuid-store', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, u CHAR(36))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1, UUID())`);
      await q(`SELECT u FROM \`${t}\``);
    }));

  // -------------------------------------------------------------------------
  // F. SELECT-back-typed roundtrip per type (verify driver decode path)
  // -------------------------------------------------------------------------
  for (const ty of SQL_TYPES.filter((t) => !t.skipValue)) {
    runner.add(FW, `decode/${ty.group}`, `select-back ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        const r = await q(`SELECT c FROM \`${t}\` WHERE id=1`);
        if (r.length !== 1 || !('c' in r[0])) throw new BehaviorMismatch('decode failed: no column');
      }));
  }
}

async function teardown() { if (pool) { await pool.end(); pool = null; } }

module.exports = { register, teardown };
