'use strict';

const { makePool, uniq } = require('../lib/db');
const { BATCH_SIZES } = require('../lib/matrix');
const { BehaviorMismatch } = require('../lib/errors');

let pool = null;
function getPool() {
  if (!pool) pool = makePool('raw', { connectionLimit: 6 });
  return pool;
}
async function q(sql, params) {
  const [rows] = await getPool().query(sql, params);
  return rows;
}
async function withTable(ddl, body) {
  const t = uniq('w');
  await q(ddl(t));
  try { return await body(t); } finally { try { await q(`DROP TABLE IF EXISTS \`${t}\``); } catch (_) {} }
}

function register(runner) {
  const FW = 'raw';

  // ---- batch insert sizes 1/10/100/1000 ----
  for (const n of BATCH_SIZES) {
    runner.add(FW, 'workload/batch-insert', `multi-values ${n}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n VARCHAR(30), v INT)`, async (t) => {
        const vals = [];
        const ph = [];
        for (let i = 0; i < n; i++) { ph.push('(?,?,?)'); vals.push(i, `r${i}`, i * 2); }
        await q(`INSERT INTO \`${t}\` (id,n,v) VALUES ${ph.join(',')}`, vals);
        const r = await q(`SELECT COUNT(*) c FROM \`${t}\``);
        if (Number(r[0].c) !== n) throw new BehaviorMismatch(`inserted ${r[0].c}, expected ${n}`);
      }));

    runner.add(FW, 'workload/batch-insert', `row-by-row ${n}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v INT)`, async (t) => {
        for (let i = 0; i < n; i++) await q(`INSERT INTO \`${t}\` (id,v) VALUES (?,?)`, [i, i]);
      }));
  }

  // ---- bulk update / delete ----
  runner.add(FW, 'workload/bulk', 'bulk-update-1000', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v INT)`, async (t) => {
      const ph = []; const vals = [];
      for (let i = 0; i < 1000; i++) { ph.push('(?,?)'); vals.push(i, i); }
      await q(`INSERT INTO \`${t}\` (id,v) VALUES ${ph.join(',')}`, vals);
      await q(`UPDATE \`${t}\` SET v=v+1`);
      const r = await q(`SELECT SUM(v) s FROM \`${t}\``);
      if (Number(r[0].s) !== 500500) throw new BehaviorMismatch(`sum after bulk update=${r[0].s}`);
    }));
  runner.add(FW, 'workload/bulk', 'bulk-delete-1000', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v INT)`, async (t) => {
      const ph = []; const vals = [];
      for (let i = 0; i < 1000; i++) { ph.push('(?,?)'); vals.push(i, i % 2); }
      await q(`INSERT INTO \`${t}\` (id,v) VALUES ${ph.join(',')}`, vals);
      await q(`DELETE FROM \`${t}\` WHERE v=0`);
      const r = await q(`SELECT COUNT(*) c FROM \`${t}\``);
      if (Number(r[0].c) !== 500) throw new BehaviorMismatch(`after bulk delete count=${r[0].c}`);
    }));

  // ---- large result fetch (10k rows) ----
  runner.add(FW, 'workload/large-fetch', 'fetch-10k', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v INT)`, async (t) => {
      for (let b = 0; b < 10; b++) {
        const ph = []; const vals = [];
        for (let i = 0; i < 1000; i++) { const id = b * 1000 + i; ph.push('(?,?)'); vals.push(id, id); }
        await q(`INSERT INTO \`${t}\` (id,v) VALUES ${ph.join(',')}`, vals);
      }
      const rows = await q(`SELECT * FROM \`${t}\``);
      if (rows.length !== 10000) throw new BehaviorMismatch(`fetched ${rows.length}, expected 10000`);
    }));

  // ---- streaming a large result ----
  runner.add(FW, 'workload/large-fetch', 'stream-10k', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, v INT)`, async (t) => {
      for (let b = 0; b < 10; b++) {
        const ph = []; const vals = [];
        for (let i = 0; i < 1000; i++) { const id = b * 1000 + i; ph.push('(?,?)'); vals.push(id, id); }
        await q(`INSERT INTO \`${t}\` (id,v) VALUES ${ph.join(',')}`, vals);
      }
      const conn = await getPool().getConnection();
      try {
        const count = await new Promise((resolve, reject) => {
          let n = 0;
          const stream = conn.connection.query(`SELECT * FROM \`${t}\``).stream();
          stream.on('data', () => { n++; });
          stream.on('end', () => resolve(n));
          stream.on('error', reject);
        });
        if (count !== 10000) throw new BehaviorMismatch(`streamed ${count}, expected 10000`);
      } finally { conn.release(); }
    }));

  // ---- wide table (many columns) ----
  for (const cols of [50, 100, 200]) {
    runner.add(FW, 'workload/wide', `wide-${cols}-cols`, () =>
      withTable((t) => {
        const defs = ['id INT PRIMARY KEY'];
        for (let i = 0; i < cols; i++) defs.push(`c${i} INT`);
        return `CREATE TABLE \`${t}\` (${defs.join(',')})`;
      }, async (t) => {
        const names = ['id']; const ph = ['1']; const vals = [];
        for (let i = 0; i < cols; i++) { names.push(`c${i}`); ph.push('?'); vals.push(i); }
        await q(`INSERT INTO \`${t}\` (${names.join(',')}) VALUES (${ph.join(',')})`, vals);
        const r = await q(`SELECT * FROM \`${t}\``);
        if (Object.keys(r[0]).length !== cols + 1) throw new BehaviorMismatch('wide column count mismatch');
      }));
  }

  // ---- deep / large JSON ----
  for (const depth of [5, 20, 50]) {
    runner.add(FW, 'workload/json', `deep-json-${depth}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, doc JSON)`, async (t) => {
        let obj = { v: 1 };
        for (let i = 0; i < depth; i++) obj = { nest: obj };
        await q(`INSERT INTO \`${t}\` VALUES (1,?)`, [JSON.stringify(obj)]);
        const r = await q(`SELECT JSON_VALID(doc) v FROM \`${t}\``);
        if (Number(r[0].v) !== 1) throw new BehaviorMismatch('deep JSON not valid after roundtrip');
      }));
  }
  runner.add(FW, 'workload/json', 'large-json-array-1000', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, doc JSON)`, async (t) => {
      const arr = []; for (let i = 0; i < 1000; i++) arr.push({ i, name: `item${i}` });
      await q(`INSERT INTO \`${t}\` VALUES (1,?)`, [JSON.stringify(arr)]);
      const r = await q(`SELECT JSON_LENGTH(doc) n FROM \`${t}\``);
      if (Number(r[0].n) !== 1000) throw new BehaviorMismatch(`JSON_LENGTH=${r[0].n}`);
    }));

  // ---- long strings / BLOB ----
  for (const kb of [1, 64, 256, 1024]) {
    runner.add(FW, 'workload/long', `long-text-${kb}kb`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body LONGTEXT)`, async (t) => {
        const s = 'a'.repeat(kb * 1024);
        await q(`INSERT INTO \`${t}\` VALUES (1,?)`, [s]);
        const r = await q(`SELECT LENGTH(body) n FROM \`${t}\``);
        if (Number(r[0].n) !== kb * 1024) throw new BehaviorMismatch(`length=${r[0].n}, expected ${kb * 1024}`);
      }));
    runner.add(FW, 'workload/long', `long-blob-${kb}kb`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body LONGBLOB)`, async (t) => {
        const buf = Buffer.alloc(kb * 1024, 0x41);
        await q(`INSERT INTO \`${t}\` VALUES (1,?)`, [buf]);
        const r = await q(`SELECT LENGTH(body) n FROM \`${t}\``);
        if (Number(r[0].n) !== kb * 1024) throw new BehaviorMismatch(`blob length=${r[0].n}`);
      }));
  }

  // ---- connection pool behavior ----
  runner.add(FW, 'workload/pool', 'sequential-queries-50', async () => {
    for (let i = 0; i < 50; i++) {
      const r = await q('SELECT ? AS x', [i]);
      if (Number(r[0].x) !== i) throw new BehaviorMismatch('pool query returned wrong value');
    }
  });
  runner.add(FW, 'workload/pool', 'concurrent-queries-limited', async () => {
    // Modest concurrency bounded by the pool's connectionLimit (per harness rules).
    const ps = [];
    for (let i = 0; i < 12; i++) ps.push(q('SELECT SLEEP(0) AS s'));
    await Promise.all(ps);
  });
  runner.add(FW, 'workload/pool', 'getconnection-release-cycle', async () => {
    for (let i = 0; i < 10; i++) {
      const c = await getPool().getConnection();
      await c.query('SELECT 1');
      c.release();
    }
  });
  runner.add(FW, 'workload/pool', 'transaction-on-pooled-conn', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
      const c = await getPool().getConnection();
      try {
        await c.beginTransaction();
        await c.query(`INSERT INTO \`${t}\` VALUES (1)`);
        await c.commit();
      } finally { c.release(); }
    }));
}

async function teardown() {
  if (pool) { await pool.end(); pool = null; }
}

module.exports = { register, teardown };
