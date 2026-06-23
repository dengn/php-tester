'use strict';

// Knex wave 4: schema-builder modifier matrix per type, and CRUD-with-returning
// / chained-clause coverage to round out the query-builder surface.

const Knex = require('knex');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { expect } = require('../lib/errors');

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
  const k = getKnex(); const t = uniq('k4');
  await k.schema.createTable(t, builder);
  try { return await body(t, k); } finally { try { await k.schema.dropTableIfExists(t); } catch (_) {} }
}

function register(runner) {
  const FW = 'knex';

  // -------------------------------------------------------------------------
  // 1. COLUMN-MODIFIER matrix: each modifier on a representative column
  // -------------------------------------------------------------------------
  const modifiers = [
    ['notNullable', (t) => t.string('c').notNullable()],
    ['nullable', (t) => t.string('c').nullable()],
    ['defaultTo-str', (t) => t.string('c').defaultTo('x')],
    ['defaultTo-num', (t) => t.integer('c').defaultTo(5)],
    ['defaultTo-bool', (t) => t.boolean('c').defaultTo(true)],
    ['defaultTo-now', (t) => t.datetime('c').defaultTo(getKnex().fn.now())],
    ['unsigned', (t) => t.integer('c').unsigned()],
    ['unique', (t) => t.string('c').unique()],
    ['index', (t) => t.integer('c').index()],
    ['primary', (t) => t.string('c').primary()],
    ['comment', (t) => t.string('c').comment('a column')],
    ['collate-bin', (t) => t.specificType('c', 'varchar(50) collate utf8mb4_bin')],
    ['after', (t) => { t.integer('first'); t.string('c').after('first'); }],
    ['first', (t) => { t.integer('other'); t.string('c').first(); }],
  ];
  for (const [nm, build] of modifiers) {
    runner.add(FW, 'col-modifier', nm, () =>
      withTable((t) => {
        if (nm !== 'primary') t.increments('id');
        build(t);
      }, async () => {}));
  }

  // -------------------------------------------------------------------------
  // 2. CHAINED query-clause matrix
  // -------------------------------------------------------------------------
  function dataTable(body) {
    return withTable((t) => { t.increments('id'); t.string('name'); t.integer('age'); t.integer('grp'); }, async (tn, k) => {
      const rows = []; for (let i = 0; i < 20; i++) rows.push({ name: `n${i}`, age: i, grp: i % 4 });
      await k(tn).insert(rows);
      return body(tn, k);
    });
  }
  const chains = [
    ['where-orderBy-limit', (q) => q.where('grp', 1).orderBy('age', 'desc').limit(3)],
    ['where-where-offset', (q) => q.where('age', '>', 2).where('age', '<', 18).offset(2).limit(5)],
    ['select-where-groupBy', (q) => q.select('grp').count({ c: '*' }).where('age', '>', 0).groupBy('grp')],
    ['groupBy-having-orderBy', (q) => q.select('grp').count({ c: '*' }).groupBy('grp').having(getKnex().raw('count(*)'), '>', 1).orderBy('grp')],
    ['distinct-where', (q) => q.distinct('grp').where('age', '>', 5)],
    ['whereIn-orderBy', (q) => q.whereIn('grp', [0, 1, 2]).orderBy('age')],
    ['orWhere-andWhere', (q) => q.where('grp', 0).orWhere((b) => b.where('grp', 1).andWhere('age', '>', 4))],
    ['whereNull-orWhereNotNull', (q) => q.whereNull('name').orWhereNotNull('name')],
    ['select-count-first', (q) => q.where('grp', 1).count({ c: '*' }).first()],
    ['min-max-where', (q) => q.where('grp', 2).min({ mn: 'age' }).max({ mx: 'age' })],
    ['sum-groupBy-having', (q) => q.select('grp').sum({ s: 'age' }).groupBy('grp').havingRaw('sum(age) > 10')],
    ['orderByRaw-limit', (q) => q.orderByRaw('age % 3, age').limit(5)],
    ['whereBetween-orderBy', (q) => q.whereBetween('age', [5, 15]).orderBy('age', 'desc')],
    ['select-alias', (q) => q.select('name as nm', 'age as a').limit(3)],
    ['column-method', (q) => q.column('name', 'age').limit(2)],
  ];
  for (const [nm, fn] of chains) {
    runner.add(FW, 'chained', nm, () => dataTable(async (tn, k) => { await fn(k(tn)); }));
  }

  // -------------------------------------------------------------------------
  // 3. UPDATE / DELETE variation matrix
  // -------------------------------------------------------------------------
  const mutations = [
    ['update-multi-col', async (tn, k) => { await k(tn).where('grp', 1).update({ name: 'x', age: 0 }); }],
    ['update-increment', async (tn, k) => { await k(tn).where('grp', 1).increment('age', 10); }],
    ['update-decrement', async (tn, k) => { await k(tn).where('grp', 1).decrement('age', 1); }],
    ['update-raw', async (tn, k) => { await k(tn).where('grp', 1).update({ age: k.raw('age + 100') }); }],
    ['update-where-in', async (tn, k) => { await k(tn).whereIn('grp', [0, 1]).update({ name: 'z' }); }],
    ['delete-where', async (tn, k) => { await k(tn).where('grp', 3).del(); }],
    ['delete-where-in', async (tn, k) => { await k(tn).whereIn('grp', [2, 3]).del(); }],
    ['delete-where-lt', async (tn, k) => { await k(tn).where('age', '<', 5).del(); }],
    ['truncate', async (tn, k) => { await k(tn).truncate(); }],
    ['update-then-count', async (tn, k) => {
      await k(tn).where('grp', 0).update({ age: 999 });
      const r = await k(tn).where('age', 999).count({ c: '*' });
      expect(Number(r[0].c) >= 1, true, 'updated rows present');
    }],
  ];
  for (const [nm, fn] of mutations) {
    runner.add(FW, 'mutation', nm, () => dataTable(fn));
  }
}

async function teardown() { if (knex) { await knex.destroy(); knex = null; } }

module.exports = { register, teardown };
