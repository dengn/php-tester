'use strict';

// ---------------------------------------------------------------------------
// Prisma 7 against MatrixOne.
//
// FINDINGS captured by this module:
//  * `prisma db push` FAILS: the schema engine queries
//    information_schema.check_constraints, which MatrixOne 4.0.0-rc3 does not
//    expose ("table check_constraints does not exist"). So Prisma migrations /
//    introspection are unusable.
//  * The runtime @prisma/client via the mariadb driver adapter WORKS for CRUD,
//    relations, aggregates, $queryRaw / $executeRaw, transactions. We create
//    the tables with raw SQL (since db push is unavailable) and exercise the
//    client.
//
// Each Prisma op is wrapped in a local timeout so a stall surfaces as a normal
// failure; the runner's 30s cap is the backstop.
// ---------------------------------------------------------------------------

const {
  withTimeout, prismaAvailable, prismaClient, prismaRawPool, teardownPrisma,
} = require('../lib/orm_shared');
const { prismaState } = require('../lib/prisma_bootstrap');
const { BehaviorMismatch, SkipScenario, expectTrue } = require('../lib/errors');

const OP_TIMEOUT = 12000;

let tablesReady = false;
async function ensureTables() {
  if (tablesReady) return;
  const pool = prismaRawPool();
  // Prisma db push is broken on MatrixOne; create the schema's tables manually.
  await pool.query('DROP TABLE IF EXISTS p_post');
  await pool.query('DROP TABLE IF EXISTS p_user');
  await pool.query('DROP TABLE IF EXISTS p_product');
  await pool.query(
    'CREATE TABLE p_user (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(191) UNIQUE, name VARCHAR(191), age INT)'
  );
  await pool.query(
    'CREATE TABLE p_post (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191) NOT NULL, body TEXT, published TINYINT(1) NOT NULL DEFAULT 0, views INT NOT NULL DEFAULT 0, authorId INT NOT NULL)'
  );
  await pool.query(
    'CREATE TABLE p_product (id INT AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(191) UNIQUE, name VARCHAR(191) NOT NULL, price DECIMAL(12,2) NOT NULL, stock INT NOT NULL DEFAULT 0, meta JSON)'
  );
  tablesReady = true;
}

async function reset() {
  const pool = prismaRawPool();
  await pool.query('DELETE FROM p_post');
  await pool.query('DELETE FROM p_user');
  await pool.query('DELETE FROM p_product');
}

function register(runner) {
  const FW = 'prisma';

  // If Prisma client could not be generated at all, register a handful of
  // documentation scenarios and bail (keeps the suite resilient).
  if (!prismaAvailable() || !prismaState.generated) {
    runner.add(FW, 'availability', 'prisma-client-generated', () => {
      throw new BehaviorMismatch('Prisma client was not generated: ' + (prismaState.generateError || 'unknown'));
    });
    for (let i = 0; i < 5; i++) {
      runner.add(FW, 'availability', `prisma-unavailable-${i}`, () => {
        throw new SkipScenario('Prisma client unavailable in this environment');
      });
    }
    return;
  }

  // -------------------------------------------------------------------------
  // 1. Migration / introspection findings (db push outcome).
  // -------------------------------------------------------------------------
  runner.add(FW, 'migration', 'db-push-against-matrixone', () => {
    if (prismaState.dbPushOk) return 'prisma db push unexpectedly succeeded';
    // Expected failure path: surface the captured engine error as a finding.
    throw new BehaviorMismatch(
      'prisma db push failed (expected): ' + (prismaState.dbPushError || 'no error captured')
    );
  });
  runner.add(FW, 'migration', 'check-constraints-introspection-gap', () => {
    const e = (prismaState.dbPushError || '').toLowerCase();
    if (e.includes('check_constraints')) {
      throw new BehaviorMismatch('MatrixOne lacks information_schema.check_constraints (Prisma schema engine requires it)');
    }
    if (prismaState.dbPushOk) return 'db push succeeded; introspection gap not triggered';
    throw new BehaviorMismatch('db push failed for another reason: ' + (prismaState.dbPushError || ''));
  });

  // -------------------------------------------------------------------------
  // 2. Runtime client CRUD (tables created via raw SQL).
  // -------------------------------------------------------------------------
  const crud = (name, fn) => runner.add(FW, 'crud', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });

  crud('create-returns-id', async (p) => {
    const u = await p.pUser.create({ data: { email: 'a@x.com', name: 'Alice', age: 30 } });
    if (!u.id) throw new BehaviorMismatch('create did not return generated id');
  });
  crud('create-many', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'b@x.com' }, { email: 'c@x.com' }, { email: 'd@x.com' }] });
    const n = await p.pUser.count();
    if (n !== 3) throw new BehaviorMismatch(`createMany count=${n}`);
  });
  crud('find-unique-by-unique', async (p) => {
    await p.pUser.create({ data: { email: 'u@x.com', name: 'U' } });
    const f = await p.pUser.findUnique({ where: { email: 'u@x.com' } });
    if (!f || f.name !== 'U') throw new BehaviorMismatch('findUnique mismatch');
  });
  crud('find-many-filter', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'e1@x.com', age: 10 }, { email: 'e2@x.com', age: 20 }, { email: 'e3@x.com', age: 30 }] });
    const r = await p.pUser.findMany({ where: { age: { gte: 20 } } });
    if (r.length !== 2) throw new BehaviorMismatch(`filter count=${r.length}`);
  });
  crud('update', async (p) => {
    const u = await p.pUser.create({ data: { email: 'up@x.com', name: 'Old' } });
    const r = await p.pUser.update({ where: { id: u.id }, data: { name: 'New' } });
    if (r.name !== 'New') throw new BehaviorMismatch('update failed');
  });
  crud('update-many', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'um1@x.com', age: 1 }, { email: 'um2@x.com', age: 1 }] });
    const r = await p.pUser.updateMany({ where: { age: 1 }, data: { age: 2 } });
    if (r.count !== 2) throw new BehaviorMismatch(`updateMany count=${r.count}`);
  });
  crud('upsert-insert', async (p) => {
    const r = await p.pUser.upsert({ where: { email: 'ups@x.com' }, create: { email: 'ups@x.com', name: 'C' }, update: { name: 'U' } });
    if (r.name !== 'C') throw new BehaviorMismatch('upsert insert path failed');
  });
  crud('upsert-update', async (p) => {
    await p.pUser.create({ data: { email: 'ups2@x.com', name: 'orig' } });
    const r = await p.pUser.upsert({ where: { email: 'ups2@x.com' }, create: { email: 'ups2@x.com', name: 'C' }, update: { name: 'U' } });
    if (r.name !== 'U') throw new BehaviorMismatch('upsert update path failed');
  });
  crud('delete', async (p) => {
    const u = await p.pUser.create({ data: { email: 'del@x.com' } });
    await p.pUser.delete({ where: { id: u.id } });
    const n = await p.pUser.count();
    if (n !== 0) throw new BehaviorMismatch(`after delete count=${n}`);
  });
  crud('delete-many', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'dm1@x.com' }, { email: 'dm2@x.com' }] });
    const r = await p.pUser.deleteMany({});
    if (r.count !== 2) throw new BehaviorMismatch(`deleteMany count=${r.count}`);
  });

  // -------------------------------------------------------------------------
  // 3. Relations.
  // -------------------------------------------------------------------------
  const rel = (name, fn) => runner.add(FW, 'relation', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  rel('nested-create', async (p) => {
    const u = await p.pUser.create({
      data: { email: 'au@x.com', name: 'Author', posts: { create: [{ title: 'p1' }, { title: 'p2' }] } },
      include: { posts: true },
    });
    if (u.posts.length !== 2) throw new BehaviorMismatch(`nested create posts=${u.posts.length}`);
  });
  rel('include-relation', async (p) => {
    const u = await p.pUser.create({ data: { email: 'inc@x.com', posts: { create: [{ title: 't' }] } } });
    const f = await p.pUser.findUnique({ where: { id: u.id }, include: { posts: true } });
    if (f.posts.length !== 1) throw new BehaviorMismatch('include relation failed');
  });
  rel('filter-by-relation', async (p) => {
    await p.pUser.create({ data: { email: 'fr@x.com', posts: { create: [{ title: 'hasit' }] } } });
    await p.pUser.create({ data: { email: 'fr2@x.com' } });
    const r = await p.pUser.findMany({ where: { posts: { some: {} } } });
    if (r.length !== 1) throw new BehaviorMismatch(`relation filter count=${r.length}`);
  });

  // -------------------------------------------------------------------------
  // 4. Aggregates / groupBy.
  // -------------------------------------------------------------------------
  const agg = (name, fn) => runner.add(FW, 'aggregate', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  agg('count', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'g1@x.com' }, { email: 'g2@x.com' }] });
    const n = await p.pUser.count();
    if (n !== 2) throw new BehaviorMismatch(`count=${n}`);
  });
  agg('aggregate-avg-sum', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'a1@x.com', age: 10 }, { email: 'a2@x.com', age: 20 }] });
    const r = await p.pUser.aggregate({ _avg: { age: true }, _sum: { age: true }, _max: { age: true } });
    if (Number(r._sum.age) !== 30) throw new BehaviorMismatch(`sum=${r._sum.age}`);
  });
  agg('group-by', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'gb1@x.com', age: 10 }, { email: 'gb2@x.com', age: 10 }, { email: 'gb3@x.com', age: 20 }] });
    const r = await p.pUser.groupBy({ by: ['age'], _count: { _all: true } });
    if (r.length !== 2) throw new BehaviorMismatch(`groupBy buckets=${r.length}`);
  });

  // -------------------------------------------------------------------------
  // 5. Decimal / JSON column handling.
  // -------------------------------------------------------------------------
  const typ = (name, fn) => runner.add(FW, 'types', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  typ('decimal-money-precision', async (p) => {
    const prod = await p.pProduct.create({ data: { sku: 'SKU1', name: 'Widget', price: '19.99' } });
    const f = await p.pProduct.findUnique({ where: { id: prod.id } });
    // Prisma returns Decimal as a Decimal.js instance; compare via string.
    if (String(f.price) !== '19.99') throw new BehaviorMismatch(`decimal roundtrip=${f.price}`);
  });
  typ('decimal-arithmetic', async (p) => {
    await p.pProduct.create({ data: { sku: 'SKU2', name: 'X', price: '0.10', stock: 3 } });
    const r = await p.$queryRaw`SELECT price * stock AS total FROM p_product WHERE sku = 'SKU2'`;
    if (String(r[0].total) !== '0.30') throw new BehaviorMismatch(`decimal math=${r[0].total}`);
  });
  typ('json-column', async (p) => {
    const prod = await p.pProduct.create({ data: { sku: 'SKU3', name: 'J', price: '1.00', meta: { color: 'red', tags: [1, 2, 3] } } });
    const f = await p.pProduct.findUnique({ where: { id: prod.id } });
    if (!f.meta || f.meta.color !== 'red') throw new BehaviorMismatch('json roundtrip failed');
  });

  // -------------------------------------------------------------------------
  // 6. Raw query interfaces.
  // -------------------------------------------------------------------------
  const raw = (name, fn) => runner.add(FW, 'raw', name, async () => {
    await ensureTables();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  raw('queryRaw-scalar', async (p) => {
    const r = await p.$queryRaw`SELECT 1 + 1 AS v`;
    if (Number(r[0].v) !== 2) throw new BehaviorMismatch('queryRaw scalar failed');
  });
  raw('queryRawUnsafe', async (p) => {
    const r = await p.$queryRawUnsafe('SELECT ? AS v', 42);
    if (Number(r[0].v) !== 42) throw new BehaviorMismatch('queryRawUnsafe param failed');
  });
  raw('executeRaw-ddl-dml', async (p) => {
    await reset();
    const n = await p.$executeRawUnsafe("INSERT INTO p_user (email) VALUES ('raw@x.com')");
    if (Number(n) !== 1) throw new BehaviorMismatch(`executeRaw affected=${n}`);
  });
  raw('queryRaw-window-function', async (p) => {
    const r = await p.$queryRawUnsafe('SELECT ROW_NUMBER() OVER (ORDER BY n) AS rn FROM (SELECT 1 n UNION SELECT 2) t');
    if (r.length !== 2) throw new BehaviorMismatch('window via queryRaw failed');
  });

  // -------------------------------------------------------------------------
  // 7. Transactions.
  // -------------------------------------------------------------------------
  const tx = (name, fn) => runner.add(FW, 'transaction', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  tx('sequential-transaction', async (p) => {
    const [a, b] = await p.$transaction([
      p.pUser.create({ data: { email: 't1@x.com' } }),
      p.pUser.create({ data: { email: 't2@x.com' } }),
    ]);
    if (!a.id || !b.id) throw new BehaviorMismatch('transaction array failed');
  });
  tx('interactive-transaction', async (p) => {
    await p.$transaction(async (txc) => {
      await txc.pUser.create({ data: { email: 'it1@x.com' } });
      await txc.pUser.create({ data: { email: 'it2@x.com' } });
    });
    const n = await p.pUser.count();
    if (n !== 2) throw new BehaviorMismatch(`interactive tx count=${n}`);
  });
  tx('transaction-rollback', async (p) => {
    try {
      await p.$transaction(async (txc) => {
        await txc.pUser.create({ data: { email: 'rb@x.com' } });
        throw new Error('force rollback');
      });
    } catch (_) { /* expected */ }
    const n = await p.pUser.count();
    if (n !== 0) throw new BehaviorMismatch(`rollback left ${n} rows`);
  });

  // -------------------------------------------------------------------------
  // 8. Pagination / ordering / distinct.
  // -------------------------------------------------------------------------
  const qy = (name, fn) => runner.add(FW, 'query', name, async () => {
    await ensureTables();
    await reset();
    const p = await prismaClient();
    return withTimeout(fn(p), OP_TIMEOUT, name);
  });
  qy('order-by', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'o1@x.com', age: 30 }, { email: 'o2@x.com', age: 10 }, { email: 'o3@x.com', age: 20 }] });
    const r = await p.pUser.findMany({ orderBy: { age: 'asc' } });
    if (r[0].age !== 10) throw new BehaviorMismatch('orderBy failed');
  });
  qy('skip-take', async (p) => {
    await p.pUser.createMany({ data: Array.from({ length: 10 }, (_, i) => ({ email: `pg${i}@x.com`, age: i })) });
    const r = await p.pUser.findMany({ orderBy: { age: 'asc' }, skip: 2, take: 3 });
    if (r.length !== 3 || r[0].age !== 2) throw new BehaviorMismatch('skip/take failed');
  });
  qy('distinct', async (p) => {
    await p.pUser.createMany({ data: [{ email: 'd1@x.com', age: 5 }, { email: 'd2@x.com', age: 5 }, { email: 'd3@x.com', age: 9 }] });
    const r = await p.pUser.findMany({ distinct: ['age'], orderBy: { age: 'asc' } });
    if (r.length !== 2) throw new BehaviorMismatch(`distinct count=${r.length}`);
  });
  qy('select-projection', async (p) => {
    await p.pUser.create({ data: { email: 'sel@x.com', name: 'N', age: 1 } });
    const r = await p.pUser.findMany({ select: { email: true } });
    if (r[0].name !== undefined) throw new BehaviorMismatch('select projection leaked fields');
  });
}

async function teardown() { await teardownPrisma(); }

module.exports = { register, teardown };
