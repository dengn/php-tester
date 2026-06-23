'use strict';

require('reflect-metadata');
const { DataSource, EntitySchema, In, Like, Between, MoreThan, LessThan, Not, IsNull, MoreThanOrEqual, LessThanOrEqual, ILike } = require('typeorm');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

// To keep TypeORM affordable (each DataSource.initialize() opens a connection),
// these matrices share ONE long-lived DataSource and register entity metadata
// per-test by building a fresh DataSource only when entity schemas differ. We
// avoid that cost by using a single shared DataSource with a generic table and
// raw DDL for the type matrix, and the repository API for operations.

const { teardownShared } = require('../lib/typeorm_shared');

// Cached long-lived DataSources keyed by a caller-supplied shape key. Because a
// DataSource leaks ~1 connection for the process lifetime against this MatrixOne
// build, we create a *small fixed number* of them (one per distinct entity
// shape) and reuse them across every scenario that shares that shape. The table
// is dropped+recreated per scenario via synchronize(true), so state never bleeds.
const dsCache = new Map();
async function getCachedDS(key, E) {
  if (!dsCache.has(key)) {
    const ds = new DataSource({
      type: 'mysql', host: CONFIG.host, port: CONFIG.port,
      username: CONFIG.user, password: CONFIG.password, database: DATABASES.typeorm,
      driver: require('mysql2'), synchronize: false, logging: false, entities: [E],
      extra: { connectionLimit: 2 },
    });
    await ds.initialize();
    dsCache.set(key, { ds, E });
  }
  return dsCache.get(key);
}

// Run body against a cached DataSource for entity E (shape `key`). The entity's
// table is freshly (re)synced so each scenario starts clean.
async function withShape(key, E, body) {
  const { ds, E: cachedE } = await getCachedDS(key, E);
  await ds.synchronize(true); // drop + create just this entity's table
  return body(ds, ds.getRepository(cachedE), cachedE);
}

// Curated type matrix for operation tests.
function typeMatrix() {
  return [
    ['int', { type: 'int' }, 42, 100, 'num'],
    ['bigint', { type: 'bigint' }, '100', '9007199254740991', 'num'],
    ['decimal', { type: 'decimal', precision: 12, scale: 2 }, '10.50', '99.99', 'num'],
    ['double', { type: 'double' }, 3.14, 2.71, 'num'],
    ['varchar', { type: 'varchar', length: 50 }, 'alpha', 'omega', 'str'],
    ['text', { type: 'text' }, 'hello', 'world', 'str'],
    ['date', { type: 'date' }, '2026-01-01', '2026-12-31', 'dt'],
    ['datetime', { type: 'datetime' }, '2026-01-01 00:00:00', '2026-12-31 00:00:00', 'dt'],
    ['boolean', { type: 'boolean' }, true, false, 'bool'],
    ['enum', { type: 'enum', enum: ['a', 'b', 'c'] }, 'a', 'c', 'enum'],
  ];
}

function register(runner) {
  const FW = 'typeorm';

  // -------------------------------------------------------------------------
  // 1. TYPE × OPERATION (repository) matrix
  // -------------------------------------------------------------------------
  for (const [label, colDef, sample, boundary, group] of typeMatrix()) {
    const cat = `type-op/${label}`;
    // One stable entity (and one cached DataSource) per type.
    const tname = `to_op_${label.replace(/[^a-z0-9]/gi, '')}`;
    const E = new EntitySchema({ name: tname, tableName: tname, columns: { id: { type: 'int', primary: true, generated: 'increment' }, c: { ...colDef, nullable: true } } });
    const run = (body) => withShape(`type-op/${label}`, E, async (d, repo, Ent) => {
      await repo.save([{ c: sample }, { c: boundary }]);
      return body(d, repo, Ent);
    });

    const ops = [
      ['find-eq', async (d, repo) => { await repo.findBy({ c: sample }); }],
      ['find-not', async (d, repo) => { await repo.findBy({ c: Not(sample) }); }],
      ['find-in', async (d, repo) => { await repo.findBy({ c: In([sample, boundary]) }); }],
      ['order-asc', async (d, repo) => { await repo.find({ order: { c: 'ASC' } }); }],
      ['order-desc', async (d, repo) => { await repo.find({ order: { c: 'DESC' } }); }],
      ['count', async (d, repo) => { await repo.count(); }],
      ['update', async (d, repo) => { await repo.update({ c: boundary }, { c: sample }); }],
      ['delete', async (d, repo) => { await repo.delete({ c: boundary }); }],
      ['qb-where', async (d, repo, Ent) => { await d.createQueryBuilder(Ent, 't').where('t.c = :v', { v: sample }).getMany(); }],
      ['qb-orderby', async (d, repo, Ent) => { await d.createQueryBuilder(Ent, 't').orderBy('t.c', 'DESC').getMany(); }],
      ['qb-groupcount', async (d, repo, Ent) => { await d.createQueryBuilder(Ent, 't').select('t.c', 'c').addSelect('COUNT(*)', 'n').groupBy('t.c').getRawMany(); }],
    ];
    if (group === 'num' || group === 'dt') {
      ops.push(['find-gt', async (d, repo) => { await repo.findBy({ c: MoreThan(sample) }); }]);
      ops.push(['find-lt', async (d, repo) => { await repo.findBy({ c: LessThan(boundary) }); }]);
      ops.push(['find-between', async (d, repo) => { await repo.findBy({ c: Between(sample, boundary) }); }]);
    }
    if (group === 'str') {
      ops.push(['find-like', async (d, repo) => { await repo.findBy({ c: Like('%') }); }]);
    }

    for (const [nm, fn] of ops) {
      runner.add(FW, cat, nm, () => run(fn));
    }
  }

  // -------------------------------------------------------------------------
  // 2. FIND-OPTIONS matrix
  // -------------------------------------------------------------------------
  const seededE = new EntitySchema({
    name: 'to_seeded', tableName: 'to_seeded',
    columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 30, nullable: true }, age: { type: 'int', nullable: true }, grp: { type: 'int', nullable: true } },
  });
  function withSeeded(body) {
    return withShape('seeded', seededE, async (d, repo, E) => {
      await repo.save(Array.from({ length: 12 }, (_, i) => ({ name: `n${i}`, age: i, grp: i % 3 })));
      return body(d, repo, E);
    });
  }
  const findOptCases = [
    ['select', { select: { id: true, name: true } }],
    ['order-asc', { order: { age: 'ASC' } }],
    ['order-multi', { order: { grp: 'ASC', age: 'DESC' } }],
    ['take', { take: 5 }],
    ['skip', { skip: 3, take: 100 }], // TypeORM requires a limit alongside offset
    ['skip-take', { skip: 4, take: 4 }],
    ['where-eq', { where: { grp: 1 } }],
    ['where-in', { where: { age: In([1, 2, 3]) } }],
    ['where-gt', { where: { age: MoreThan(5) } }],
    ['where-between', { where: { age: Between(2, 8) } }],
    ['where-not', { where: { grp: Not(0) } }],
    ['where-like', { where: { name: Like('n%') } }],
    ['where-or-array', { where: [{ grp: 0 }, { grp: 2 }] }],
    ['where-and-implicit', { where: { grp: 1, age: MoreThan(0) } }],
    ['cache-false', { cache: false }],
  ];
  for (const [nm, opts] of findOptCases) {
    runner.add(FW, 'find-options', nm, () => withSeeded(async (d, repo) => { await repo.find(opts); }));
  }

  // -------------------------------------------------------------------------
  // 3. QUERY-BUILDER feature matrix
  // -------------------------------------------------------------------------
  const qbCases = [
    ['select-addSelect', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('t.id').addSelect('t.name').getMany(); }],
    ['where-param', async (d, repo, E) => { await d.createQueryBuilder(E, 't').where('t.grp = :g', { g: 1 }).getMany(); }],
    ['where-in-param', async (d, repo, E) => { await d.createQueryBuilder(E, 't').where('t.age IN (:...ids)', { ids: [1, 2, 3] }).getMany(); }],
    ['andWhere-orWhere', async (d, repo, E) => { await d.createQueryBuilder(E, 't').where('t.grp = :g', { g: 1 }).orWhere('t.age > :a', { a: 9 }).getMany(); }],
    ['orderBy-addOrderBy', async (d, repo, E) => { await d.createQueryBuilder(E, 't').orderBy('t.grp').addOrderBy('t.age', 'DESC').getMany(); }],
    ['limit-offset', async (d, repo, E) => { await d.createQueryBuilder(E, 't').limit(3).offset(2).getMany(); }],
    ['take-skip', async (d, repo, E) => { await d.createQueryBuilder(E, 't').take(3).skip(2).getMany(); }],
    ['groupBy-count', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('t.grp', 'grp').addSelect('COUNT(*)', 'n').groupBy('t.grp').getRawMany(); }],
    ['having', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('t.grp', 'grp').addSelect('COUNT(*)', 'n').groupBy('t.grp').having('COUNT(*) > :n', { n: 1 }).getRawMany(); }],
    ['distinct', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('DISTINCT t.grp', 'g').getRawMany(); }],
    ['getRawOne', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('COUNT(*)', 'c').getRawOne(); }],
    ['getCount', async (d, repo, E) => { await d.createQueryBuilder(E, 't').getCount(); }],
    ['getManyAndCount', async (d, repo, E) => { await d.createQueryBuilder(E, 't').take(3).getManyAndCount(); }],
    ['agg-sum', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('SUM(t.age)', 's').getRawOne(); }],
    ['agg-avg', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('AVG(t.age)', 'a').getRawOne(); }],
    ['agg-min-max', async (d, repo, E) => { await d.createQueryBuilder(E, 't').select('MIN(t.age)', 'mn').addSelect('MAX(t.age)', 'mx').getRawOne(); }],
    ['where-subquery', async (d, repo, E) => {
      const sub = d.createQueryBuilder(E, 's').select('s.grp').where('s.age > :a').getQuery();
      await d.createQueryBuilder(E, 't').where(`t.grp IN ${sub}`).setParameter('a', 5).getMany();
    }],
    ['update-set', async (d, repo, E) => { await d.createQueryBuilder().update(E).set({ age: 0 }).where('grp = :g', { g: 1 }).execute(); }],
    ['delete-where', async (d, repo, E) => { await d.createQueryBuilder().delete().from(E).where('grp = :g', { g: 2 }).execute(); }],
    ['insert-values', async (d, repo, E) => { await d.createQueryBuilder().insert().into(E).values([{ name: 'zz', age: 99, grp: 9 }]).execute(); }],
  ];
  for (const [nm, fn] of qbCases) {
    runner.add(FW, 'qb-matrix', nm, () => withSeeded(fn));
  }

  // -------------------------------------------------------------------------
  // 4. RAW + entityManager matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'manager', 'em-save-find', () => withSeeded(async (d, repo, E) => {
    const found = await d.manager.find(E);
    expectTrue(found.length === 12, 'manager.find');
  }));
  runner.add(FW, 'manager', 'em-count', () => withSeeded(async (d, repo, E) => {
    expect(await d.manager.count(E), 12, 'manager.count');
  }));
  runner.add(FW, 'manager', 'em-query-raw', () => withSeeded(async (d, repo, E) => {
    const r = await d.manager.query(`SELECT COUNT(*) c FROM \`${E.options.tableName}\``);
    expect(Number(r[0].c), 12, 'manager.query');
  }));
  runner.add(FW, 'manager', 'em-transaction', () => withSeeded(async (d, repo, E) => {
    await d.manager.transaction(async (m) => { await m.delete(E, { grp: 0 }); });
    expectTrue((await repo.count()) < 12, 'em transaction deleted');
  }));
}

async function teardown() {
  for (const { ds } of dsCache.values()) { try { await ds.destroy(); } catch (_) {} }
  dsCache.clear();
  await teardownShared();
}

module.exports = { register, teardown };
