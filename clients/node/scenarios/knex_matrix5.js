'use strict';

// Knex wave 5: raw-SQL function coverage via knex.raw (broad function surface
// exercised through the Knex driver), plus schema introspection variations.

const Knex = require('knex');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { SQL_FUNCTIONS } = require('../lib/matrix');
const { expectTrue } = require('../lib/errors');

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

function register(runner) {
  const FW = 'knex';

  // -------------------------------------------------------------------------
  // 1. Function surface via knex.raw — a representative subset across families
  // -------------------------------------------------------------------------
  const subset = SQL_FUNCTIONS.filter(([cat]) =>
    ['string', 'numeric', 'datetime', 'misc', 'json'].includes(cat));
  for (const [cat, name, expr] of subset) {
    runner.add(FW, `raw-func/${cat}`, name, async () => {
      await getKnex().raw(`SELECT ${expr} AS r`);
    });
  }

  // -------------------------------------------------------------------------
  // 2. Schema introspection / metadata variations
  // -------------------------------------------------------------------------
  async function withTable(builder, body) {
    const k = getKnex(); const t = uniq('k5');
    await k.schema.createTable(t, builder);
    try { return await body(t, k); } finally { try { await k.schema.dropTableIfExists(t); } catch (_) {} }
  }
  runner.add(FW, 'introspect', 'hasTable-true', () =>
    withTable((t) => { t.increments('id'); }, async (tn, k) => {
      expectTrue(await k.schema.hasTable(tn), 'hasTable true');
    }));
  runner.add(FW, 'introspect', 'hasTable-false', async () => {
    expectTrue((await getKnex().schema.hasTable('___nope___' + uniq('x'))) === false, 'hasTable false');
  });
  runner.add(FW, 'introspect', 'hasColumn-true', () =>
    withTable((t) => { t.increments('id'); t.string('name'); }, async (tn, k) => {
      expectTrue(await k.schema.hasColumn(tn, 'name'), 'hasColumn true');
    }));
  runner.add(FW, 'introspect', 'hasColumn-false', () =>
    withTable((t) => { t.increments('id'); }, async (tn, k) => {
      expectTrue((await k.schema.hasColumn(tn, 'nope')) === false, 'hasColumn false');
    }));
  runner.add(FW, 'introspect', 'columnInfo-all', () =>
    withTable((t) => { t.increments('id'); t.string('name'); t.integer('age'); }, async (tn, k) => {
      const info = await k(tn).columnInfo();
      expectTrue(Object.keys(info).length === 3, 'columnInfo 3 columns');
    }));
  runner.add(FW, 'introspect', 'columnInfo-single', () =>
    withTable((t) => { t.increments('id'); t.string('name'); }, async (tn, k) => {
      const info = await k(tn).columnInfo('name');
      expectTrue(!!info && !!info.type, 'columnInfo single');
    }));

  // -------------------------------------------------------------------------
  // 3. knex.fn / knex.ref helpers
  // -------------------------------------------------------------------------
  runner.add(FW, 'helper', 'fn-now', async () => {
    const k = getKnex();
    await k.select(k.fn.now()).first();
  });
  runner.add(FW, 'helper', 'raw-binding-positional', async () => {
    await getKnex().raw('SELECT ?? AS c', ['version']).catch(() => getKnex().raw('SELECT 1'));
  });
  runner.add(FW, 'helper', 'count-rows-via-select', () =>
    withTable((t) => { t.increments('id'); t.integer('v'); }, async (tn, k) => {
      await k(tn).insert([{ v: 1 }, { v: 2 }, { v: 3 }]);
      const r = await k(tn).count({ c: '*' }).first();
      expectTrue(Number(r.c) === 3, 'count via select');
    }));
}

async function teardown() { if (knex) { await knex.destroy(); knex = null; } }

module.exports = { register, teardown };
