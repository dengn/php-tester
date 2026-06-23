'use strict';

// ---------------------------------------------------------------------------
// Drizzle ORM (drizzle-orm/mysql2) against MatrixOne.
//
// Drizzle is schema-as-code: tables are declared with the mysql-core builders.
// drizzle-kit migrations are out of scope (it shells out + needs introspection
// that mirrors the Prisma gap); we create tables with raw SQL via the same
// mysql2 pool and exercise Drizzle's query builder: insert/select/update/delete,
// where operators, joins, aggregates, ordering, transactions, JSON columns.
// ---------------------------------------------------------------------------

const {
  drizzle, drizzlePool, teardownDrizzle, withTimeout,
} = require('../lib/orm_shared');
const { BehaviorMismatch } = require('../lib/errors');

const {
  mysqlTable, int, varchar, text, decimal, json, boolean,
} = require('drizzle-orm/mysql-core');
const {
  eq, gt, gte, and, or, inArray, like, asc, desc, count, sum, avg, sql,
} = require('drizzle-orm');

// ---- Schema-as-code -----------------------------------------------------
const users = mysqlTable('dz_user', {
  id: int('id').autoincrement().primaryKey(),
  name: varchar('name', { length: 191 }),
  email: varchar('email', { length: 191 }),
  age: int('age'),
  active: boolean('active'),
});
const posts = mysqlTable('dz_post', {
  id: int('id').autoincrement().primaryKey(),
  title: varchar('title', { length: 191 }),
  body: text('body'),
  authorId: int('author_id'),
  score: decimal('score', { precision: 12, scale: 2 }),
  meta: json('meta'),
});

let schemaReady = false;
async function ensureSchema() {
  if (schemaReady) return;
  const pool = drizzlePool();
  await pool.query('DROP TABLE IF EXISTS dz_post');
  await pool.query('DROP TABLE IF EXISTS dz_user');
  await pool.query('CREATE TABLE dz_user (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191), email VARCHAR(191), age INT, active TINYINT(1))');
  await pool.query('CREATE TABLE dz_post (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191), body TEXT, author_id INT, score DECIMAL(12,2), meta JSON)');
  schemaReady = true;
}
async function reset() {
  const pool = drizzlePool();
  await pool.query('DELETE FROM dz_post');
  await pool.query('DELETE FROM dz_user');
}

const OP_TIMEOUT = 12000;

function register(runner) {
  const FW = 'drizzle';

  const t = (cat, name, fn) => runner.add(FW, cat, name, async () => {
    await ensureSchema();
    await reset();
    const db = drizzle();
    return withTimeout(fn(db), OP_TIMEOUT, name);
  });

  // ---- insert / select ----
  t('crud', 'insert', async (db) => {
    await db.insert(users).values({ name: 'A', email: 'a@x.com', age: 30 });
    const r = await db.select().from(users);
    if (r.length !== 1) throw new BehaviorMismatch(`insert select count=${r.length}`);
  });
  t('crud', 'insert-multiple', async (db) => {
    await db.insert(users).values([{ name: 'a' }, { name: 'b' }, { name: 'c' }]);
    const r = await db.select().from(users);
    if (r.length !== 3) throw new BehaviorMismatch(`multi insert count=${r.length}`);
  });
  t('crud', 'insert-returns-insertId', async (db) => {
    const res = await db.insert(users).values({ name: 'ret' });
    if (res[0].insertId === undefined && res.insertId === undefined) throw new BehaviorMismatch('no insertId');
  });
  t('crud', 'update', async (db) => {
    await db.insert(users).values({ name: 'old', age: 1 });
    await db.update(users).set({ age: 2 }).where(eq(users.name, 'old'));
    const r = await db.select().from(users).where(eq(users.name, 'old'));
    if (r[0].age !== 2) throw new BehaviorMismatch('update failed');
  });
  t('crud', 'delete', async (db) => {
    await db.insert(users).values([{ name: 'a' }, { name: 'b' }]);
    await db.delete(users).where(eq(users.name, 'a'));
    const r = await db.select().from(users);
    if (r.length !== 1) throw new BehaviorMismatch('delete failed');
  });

  // ---- where operators ----
  async function seed(db) {
    await db.insert(users).values([
      { name: 'u1', age: 10, active: true }, { name: 'u2', age: 20, active: false },
      { name: 'u3', age: 30, active: true }, { name: 'u4', age: 40, active: false },
    ]);
  }
  t('where', 'eq', async (db) => { await seed(db); const r = await db.select().from(users).where(eq(users.age, 20)); if (r.length !== 1) throw new BehaviorMismatch('eq'); });
  t('where', 'gt', async (db) => { await seed(db); const r = await db.select().from(users).where(gt(users.age, 20)); if (r.length !== 2) throw new BehaviorMismatch('gt'); });
  t('where', 'gte', async (db) => { await seed(db); const r = await db.select().from(users).where(gte(users.age, 20)); if (r.length !== 3) throw new BehaviorMismatch('gte'); });
  t('where', 'and', async (db) => { await seed(db); const r = await db.select().from(users).where(and(gt(users.age, 10), eq(users.active, true))); if (r.length !== 1) throw new BehaviorMismatch('and'); });
  t('where', 'or', async (db) => { await seed(db); const r = await db.select().from(users).where(or(eq(users.age, 10), eq(users.age, 40))); if (r.length !== 2) throw new BehaviorMismatch('or'); });
  t('where', 'inArray', async (db) => { await seed(db); const r = await db.select().from(users).where(inArray(users.age, [10, 30])); if (r.length !== 2) throw new BehaviorMismatch('inArray'); });
  t('where', 'like', async (db) => { await seed(db); const r = await db.select().from(users).where(like(users.name, 'u%')); if (r.length !== 4) throw new BehaviorMismatch('like'); });

  // ---- ordering / limit ----
  t('query', 'orderBy-asc', async (db) => { await seed(db); const r = await db.select().from(users).orderBy(asc(users.age)); if (r[0].age !== 10) throw new BehaviorMismatch('asc'); });
  t('query', 'orderBy-desc', async (db) => { await seed(db); const r = await db.select().from(users).orderBy(desc(users.age)); if (r[0].age !== 40) throw new BehaviorMismatch('desc'); });
  t('query', 'limit-offset', async (db) => { await seed(db); const r = await db.select().from(users).orderBy(asc(users.age)).limit(2).offset(1); if (r.length !== 2 || r[0].age !== 20) throw new BehaviorMismatch('limit/offset'); });

  // ---- aggregates ----
  t('aggregate', 'count', async (db) => { await seed(db); const r = await db.select({ c: count() }).from(users); if (Number(r[0].c) !== 4) throw new BehaviorMismatch(`count=${r[0].c}`); });
  t('aggregate', 'sum', async (db) => { await seed(db); const r = await db.select({ s: sum(users.age) }).from(users); if (Number(r[0].s) !== 100) throw new BehaviorMismatch(`sum=${r[0].s}`); });
  t('aggregate', 'avg', async (db) => { await seed(db); const r = await db.select({ a: avg(users.age) }).from(users); if (Number(r[0].a) !== 25) throw new BehaviorMismatch(`avg=${r[0].a}`); });
  t('aggregate', 'group-by', async (db) => {
    await seed(db);
    const r = await db.select({ active: users.active, c: count() }).from(users).groupBy(users.active);
    if (r.length !== 2) throw new BehaviorMismatch(`groupBy buckets=${r.length}`);
  });

  // ---- joins ----
  t('join', 'inner-join', async (db) => {
    const res = await db.insert(users).values({ name: 'author' });
    const aid = res[0].insertId ?? res.insertId;
    await db.insert(posts).values({ title: 'p1', authorId: Number(aid) });
    const r = await db.select({ title: posts.title, name: users.name })
      .from(posts).innerJoin(users, eq(posts.authorId, users.id));
    if (r.length !== 1 || r[0].name !== 'author') throw new BehaviorMismatch('inner join failed');
  });
  t('join', 'left-join', async (db) => {
    await db.insert(users).values({ name: 'noposts' });
    const r = await db.select({ name: users.name, title: posts.title })
      .from(users).leftJoin(posts, eq(posts.authorId, users.id));
    if (r.length !== 1 || r[0].title !== null) throw new BehaviorMismatch('left join failed');
  });

  // ---- transactions ----
  t('transaction', 'commit', async (db) => {
    await db.transaction(async (txc) => {
      await txc.insert(users).values({ name: 'tx1' });
      await txc.insert(users).values({ name: 'tx2' });
    });
    const r = await db.select().from(users);
    if (r.length !== 2) throw new BehaviorMismatch(`tx commit count=${r.length}`);
  });
  t('transaction', 'rollback', async (db) => {
    try {
      await db.transaction(async (txc) => {
        await txc.insert(users).values({ name: 'rb' });
        throw new Error('boom');
      });
    } catch (_) { /* expected */ }
    const r = await db.select().from(users);
    if (r.length !== 0) throw new BehaviorMismatch(`rollback left ${r.length}`);
  });

  // ---- JSON / decimal columns ----
  t('types', 'json-column', async (db) => {
    await db.insert(posts).values({ title: 'j', meta: { tags: [1, 2, 3], k: 'v' } });
    const r = await db.select().from(posts);
    if (!r[0].meta || r[0].meta.k !== 'v') throw new BehaviorMismatch('json column failed');
  });
  t('types', 'decimal-column', async (db) => {
    await db.insert(posts).values({ title: 'd', score: '99.95' });
    const r = await db.select().from(posts);
    if (String(r[0].score) !== '99.95') throw new BehaviorMismatch(`decimal=${r[0].score}`);
  });

  // ---- raw sql escape hatch ----
  t('raw', 'sql-template', async (db) => {
    const r = await db.execute(sql`SELECT 1 + 1 AS v`);
    const rows = Array.isArray(r) ? r[0] : r;
    if (Number(rows[0].v) !== 2) throw new BehaviorMismatch('sql template failed');
  });
  t('raw', 'sql-in-where', async (db) => {
    await seed(db);
    const r = await db.select().from(users).where(sql`${users.age} > 25`);
    if (r.length !== 2) throw new BehaviorMismatch('sql in where failed');
  });
}

async function teardown() { await teardownDrizzle(); }

module.exports = { register, teardown };
