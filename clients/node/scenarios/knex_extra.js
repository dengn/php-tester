'use strict';

// Broad generated matrices for Knex: per-type schema+CRUD operations, where
// operator matrix, aggregate matrix, more join/CTE patterns. Shares one knex.

const Knex = require('knex');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

let knex = null;
function getKnex() {
  if (!knex) {
    knex = Knex({
      client: 'mysql2',
      connection: { host: CONFIG.host, port: CONFIG.port, user: CONFIG.user, password: CONFIG.password, database: DATABASES.knex },
      pool: { min: 0, max: 4 },
    });
  }
  return knex;
}
async function withTable(builder, body) {
  const k = getKnex(); const t = uniq('kx');
  await k.schema.createTable(t, builder);
  try { return await body(t, k); } finally { try { await k.schema.dropTableIfExists(t); } catch (_) {} }
}

function typeMatrix() {
  return [
    ['integer', (t, c) => t.integer(c), 42, 100, 'num'],
    ['bigInteger', (t, c) => t.bigInteger(c), '100', '9007199254740991', 'num'],
    ['decimal', (t, c) => t.decimal(c, 12, 2), '10.50', '99.99', 'num'],
    ['float', (t, c) => t.float(c), 3.5, 9.5, 'num'],
    ['double', (t, c) => t.double(c), 3.14, 2.71, 'num'],
    ['string', (t, c) => t.string(c, 50), 'alpha', 'omega', 'str'],
    ['text', (t, c) => t.text(c), 'hello', 'world', 'str'],
    ['date', (t, c) => t.date(c), '2026-01-01', '2026-12-31', 'dt'],
    ['datetime', (t, c) => t.datetime(c), '2026-01-01 00:00:00', '2026-12-31 00:00:00', 'dt'],
    ['boolean', (t, c) => t.boolean(c), true, false, 'bool'],
    ['enum', (t, c) => t.enum(c, ['a', 'b', 'c']), 'a', 'c', 'enum'],
  ];
}

function register(runner) {
  const FW = 'knex';

  // -------------------------------------------------------------------------
  // 1. TYPE × OPERATION matrix
  // -------------------------------------------------------------------------
  for (const [label, build, sample, boundary, group] of typeMatrix()) {
    const cat = `type-op/${label}`;
    const ddl = (t) => { t.integer('id').primary(); build(t, 'c'); };
    const seed = async (tn, k) => { await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]); };

    const ops = [
      ['insert', async (tn, k) => { await k(tn).insert({ id: 3, c: sample }); }],
      ['where-eq', async (tn, k) => { await seed(tn, k); await k(tn).where('c', sample); }],
      ['where-ne', async (tn, k) => { await seed(tn, k); await k(tn).whereNot('c', sample); }],
      ['where-in', async (tn, k) => { await seed(tn, k); await k(tn).whereIn('c', [sample, boundary]); }],
      ['order-asc', async (tn, k) => { await seed(tn, k); await k(tn).orderBy('c', 'asc'); }],
      ['order-desc', async (tn, k) => { await seed(tn, k); await k(tn).orderBy('c', 'desc'); }],
      ['count', async (tn, k) => { await seed(tn, k); await k(tn).count({ n: '*' }); }],
      ['distinct', async (tn, k) => { await seed(tn, k); await k(tn).distinct('c'); }],
      ['group-count', async (tn, k) => { await seed(tn, k); await k(tn).select('c').count({ n: '*' }).groupBy('c'); }],
      ['update', async (tn, k) => { await seed(tn, k); await k(tn).where('c', boundary).update({ c: sample }); }],
      ['delete', async (tn, k) => { await seed(tn, k); await k(tn).where('c', boundary).del(); }],
      ['min-max', async (tn, k) => { await seed(tn, k); await k(tn).min({ mn: 'c' }).max({ mx: 'c' }); }],
    ];
    if (group === 'num' || group === 'dt') {
      ops.push(['where-gt', async (tn, k) => { await seed(tn, k); await k(tn).where('c', '>', sample); }]);
      ops.push(['where-lt', async (tn, k) => { await seed(tn, k); await k(tn).where('c', '<', boundary); }]);
      ops.push(['where-between', async (tn, k) => { await seed(tn, k); await k(tn).whereBetween('c', [sample, boundary]); }]);
    }
    if (group === 'str') {
      ops.push(['where-like', async (tn, k) => { await seed(tn, k); await k(tn).where('c', 'like', '%'); }]);
    }
    for (const [nm, fn] of ops) {
      runner.add(FW, cat, nm, () => withTable(ddl, fn));
    }
  }

  // -------------------------------------------------------------------------
  // 2. WHERE-OPERATOR matrix
  // -------------------------------------------------------------------------
  function dataTable(body) {
    return withTable((t) => { t.increments('id'); t.string('name'); t.integer('age'); t.integer('grp'); }, async (tn, k) => {
      await k(tn).insert(Array.from({ length: 12 }, (_, i) => ({ name: `n${i}`, age: i, grp: i % 3 })));
      return body(tn, k);
    });
  }
  const whereOps = [
    ['where-eq', (q) => q.where('age', 5)],
    ['where-op-gt', (q) => q.where('age', '>', 5)],
    ['where-op-lt', (q) => q.where('age', '<', 5)],
    ['where-op-ge', (q) => q.where('age', '>=', 5)],
    ['where-op-le', (q) => q.where('age', '<=', 5)],
    ['where-op-ne', (q) => q.where('age', '<>', 5)],
    ['whereIn', (q) => q.whereIn('age', [1, 2, 3])],
    ['whereNotIn', (q) => q.whereNotIn('age', [1, 2])],
    ['whereBetween', (q) => q.whereBetween('age', [2, 8])],
    ['whereNotBetween', (q) => q.whereNotBetween('age', [2, 8])],
    ['whereNull', (q) => q.whereNull('name')],
    ['whereNotNull', (q) => q.whereNotNull('name')],
    ['whereLike', (q) => q.where('name', 'like', 'n%')],
    ['whereILike-emulated', (q) => q.whereRaw('LOWER(name) LIKE ?', ['n%'])],
    ['andWhere', (q) => q.where('grp', 1).andWhere('age', '>', 0)],
    ['orWhere', (q) => q.where('grp', 0).orWhere('grp', 2)],
    ['whereExists', (q, k, tn) => q.whereExists(k(tn).select(k.raw('1')))],
    ['whereRaw', (q) => q.whereRaw('age % 2 = 0')],
    ['where-grouped', (q) => q.where((b) => b.where('grp', 1).orWhere('grp', 2))],
    ['whereColumn', (q) => q.whereRaw('age >= grp')],
  ];
  for (const [nm, fn] of whereOps) {
    runner.add(FW, 'where-op', nm, () => dataTable(async (tn, k) => { await fn(k(tn), k, tn); }));
  }

  // -------------------------------------------------------------------------
  // 3. AGGREGATE / modifier matrix
  // -------------------------------------------------------------------------
  const aggCases = [
    ['count-star', (q) => q.count({ c: '*' })],
    ['count-distinct', (q) => q.countDistinct({ c: 'grp' })],
    ['sum', (q) => q.sum({ s: 'age' })],
    ['avg', (q) => q.avg({ a: 'age' })],
    ['min', (q) => q.min({ m: 'age' })],
    ['max', (q) => q.max({ m: 'age' })],
    ['groupBy-count', (q) => q.select('grp').count({ c: '*' }).groupBy('grp')],
    ['groupBy-sum', (q) => q.select('grp').sum({ s: 'age' }).groupBy('grp')],
    ['groupByRaw', (q) => q.select(q.client.raw('grp')).count({ c: '*' }).groupByRaw('grp')],
    ['orderByRaw', (q) => q.orderByRaw('age DESC')],
    ['limit', (q) => q.limit(5)],
    ['offset', (q) => q.offset(5).limit(5)],
    ['havingRaw', (q) => q.select('grp').count({ c: '*' }).groupBy('grp').havingRaw('count(*) > 1')],
  ];
  for (const [nm, fn] of aggCases) {
    runner.add(FW, 'aggregate', nm, () => dataTable(async (tn, k) => { await fn(k(tn)); }));
  }

  // -------------------------------------------------------------------------
  // 4. JOIN matrix
  // -------------------------------------------------------------------------
  function twoTables(body) {
    const k = getKnex(); const a = uniq('kxa'); const b = uniq('kxb');
    return (async () => {
      try {
        await k.schema.createTable(a, (t) => { t.integer('id').primary(); t.string('name'); t.integer('grp'); });
        await k.schema.createTable(b, (t) => { t.integer('id').primary(); t.integer('aid'); t.string('label'); });
        await k(a).insert([{ id: 1, name: 'x', grp: 1 }, { id: 2, name: 'y', grp: 2 }]);
        await k(b).insert([{ id: 1, aid: 1, label: 'L1' }, { id: 2, aid: 1, label: 'L2' }, { id: 3, aid: 2, label: 'L3' }]);
        return await body(a, b, k);
      } finally { await k.schema.dropTableIfExists(b); await k.schema.dropTableIfExists(a); }
    })();
  }
  const joinCases = [
    ['innerJoin', (a, b, k) => k(a).join(b, `${a}.id`, `${b}.aid`).select(`${a}.name`, `${b}.label`)],
    ['leftJoin', (a, b, k) => k(a).leftJoin(b, `${a}.id`, `${b}.aid`).select(`${a}.name`)],
    ['rightJoin', (a, b, k) => k(a).rightJoin(b, `${a}.id`, `${b}.aid`).select(`${a}.name`)],
    ['crossJoin', (a, b, k) => k(a).crossJoin(b).select(`${a}.id`)],
    ['join-and-where', (a, b, k) => k(a).join(b, `${a}.id`, `${b}.aid`).where(`${a}.grp`, 1).select('*')],
    ['join-multi-on', (a, b, k) => k(a).join(b, function () { this.on(`${a}.id`, `${b}.aid`).andOn(`${b}.id`, '>', k.raw('0')); }).select('*')],
    ['join-groupcount', (a, b, k) => k(a).leftJoin(b, `${a}.id`, `${b}.aid`).select(`${a}.id`).count({ c: `${b}.id` }).groupBy(`${a}.id`)],
    ['join-subquery', (a, b, k) => k(a).join(k(b).select('aid').as('sub'), 'sub.aid', `${a}.id`).select(`${a}.name`)],
  ];
  for (const [nm, fn] of joinCases) {
    runner.add(FW, 'join', nm, () => twoTables(async (a, b, k) => { await fn(a, b, k); }));
  }

  // -------------------------------------------------------------------------
  // 5. SCHEMA modifier matrix (more column types via specificType)
  // -------------------------------------------------------------------------
  const specificTypes = [
    'tinyint', 'smallint', 'mediumint', 'int unsigned', 'bigint unsigned',
    'char(20)', 'varchar(100)', 'tinytext', 'mediumtext', 'longtext',
    'tinyblob', 'blob', 'mediumblob', 'longblob', 'binary(16)', 'varbinary(64)',
    'year', 'bit(8)', 'numeric(10,3)', 'real',
  ];
  for (const st of specificTypes) {
    runner.add(FW, 'schema/specific', `specificType ${st}`, () =>
      withTable((t) => { t.integer('id').primary(); t.specificType('c', st); }, async () => {}));
  }
}

async function teardown() { if (knex) { await knex.destroy(); knex = null; } }

module.exports = { register, teardown };
