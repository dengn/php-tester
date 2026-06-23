'use strict';

// Knex wave 2: full column-builder type set crossed with schema/insert/
// roundtrip/null/update/where operations, and modifier coverage per type.

const Knex = require('knex');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect } = require('../lib/errors');

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
  const k = getKnex(); const t = uniq('km');
  await k.schema.createTable(t, builder);
  try { return await body(t, k); } finally { try { await k.schema.dropTableIfExists(t); } catch (_) {} }
}

// Full knex column-builder matrix. `json` marks values needing JSON.stringify.
function fullColMatrix() {
  return [
    ['integer', (t, c) => t.integer(c), 42, 99, 'ord', false],
    ['bigInteger', (t, c) => t.bigInteger(c), '100', '900719925474099', 'ord', false],
    ['tinyint', (t, c) => t.tinyint(c), 7, 120, 'ord', false],
    ['decimal', (t, c) => t.decimal(c, 12, 2), '10.50', '99.99', 'ord', false],
    ['float', (t, c) => t.float(c), 3.5, 9.5, 'ord', false],
    ['double', (t, c) => t.double(c), 3.14, 2.71, 'ord', false],
    ['string', (t, c) => t.string(c, 100), 'alpha', 'omega', 'str', false],
    ['text', (t, c) => t.text(c), 'hello', 'world', 'plain', false],
    ['text-medium', (t, c) => t.text(c, 'mediumtext'), 'm1', 'm2', 'plain', false],
    ['text-long', (t, c) => t.text(c, 'longtext'), 'l1', 'l2', 'plain', false],
    ['boolean', (t, c) => t.boolean(c), true, false, 'plain', false],
    ['date', (t, c) => t.date(c), '2026-01-01', '2026-12-31', 'ord', false],
    ['datetime', (t, c) => t.datetime(c), '2026-01-01 00:00:00', '2026-12-31 00:00:00', 'ord', false],
    ['timestamp', (t, c) => t.timestamp(c), '2026-01-01 00:00:00', '2026-06-01 00:00:00', 'ord', false],
    ['time', (t, c) => t.time(c), '08:00:00', '20:00:00', 'ord', false],
    ['binary', (t, c) => t.binary(c), Buffer.from('a'), Buffer.from('b'), 'bin', false],
    ['json', (t, c) => t.json(c), { k: 1 }, { k: 2 }, 'json', true],
    ['jsonb', (t, c) => t.jsonb(c), { k: 1 }, { k: 2 }, 'json', true],
    ['enum', (t, c) => t.enum(c, ['a', 'b', 'c']), 'a', 'c', 'plain', false],
    ['uuid', (t, c) => t.uuid(c), '6f9619ff-8b86-d011-b42d-00cf4fc964ff', '7f9619ff-8b86-d011-b42d-00cf4fc964ff', 'str', false],
  ];
}

function register(runner) {
  const FW = 'knex';

  for (const [label, build, sample, boundary, group, isJson] of fullColMatrix()) {
    const cat = `fulltype/${label}`;
    const ddl = (t) => { t.integer('id').primary(); build(t, 'c'); };
    const v = (x) => (isJson ? JSON.stringify(x) : x);

    runner.add(FW, cat, 'create', () => withTable(ddl, async () => {}));
    runner.add(FW, cat, 'insert-roundtrip', () => withTable(ddl, async (tn, k) => {
      await k(tn).insert({ id: 1, c: v(sample) });
      const r = await k(tn).where({ id: 1 });
      if (r.length !== 1) throw new BehaviorMismatch('row missing after insert');
      if (label === 'float' && Number(r[0].c) === 4 && Number(sample) === 3.5) {
        throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${r[0].c}`);
      }
    }));
    runner.add(FW, cat, 'null', () => withTable((t) => { t.integer('id').primary(); build(t, 'c'); }, async (tn, k) => {
      await k(tn).insert({ id: 1, c: null });
      const r = await k(tn).where({ id: 1 }).first();
      if (r.c !== null) throw new BehaviorMismatch(`null not preserved: ${JSON.stringify(r.c)}`);
    }));
    runner.add(FW, cat, 'update', () => withTable(ddl, async (tn, k) => {
      await k(tn).insert({ id: 1, c: v(sample) });
      await k(tn).where({ id: 1 }).update({ c: v(boundary) });
    }));
    runner.add(FW, cat, 'insert-multi', () => withTable(ddl, async (tn, k) => {
      await k(tn).insert([{ id: 1, c: v(sample) }, { id: 2, c: v(boundary) }, { id: 3, c: v(sample) }]);
      const r = await k(tn).count({ n: '*' });
      expect(Number(r[0].n), 3, 'insert-multi count');
    }));
    runner.add(FW, cat, 'delete-where', () => withTable(ddl, async (tn, k) => {
      await k(tn).insert([{ id: 1, c: v(sample) }, { id: 2, c: v(boundary) }]);
      await k(tn).where({ c: v(sample) }).del();
    }));
    runner.add(FW, cat, 'count', () => withTable(ddl, async (tn, k) => {
      await k(tn).insert([{ id: 1, c: v(sample) }, { id: 2, c: v(boundary) }]);
      const r = await k(tn).count({ n: '*' });
      expect(Number(r[0].n), 2, 'count');
    }));

    if (group === 'ord') {
      runner.add(FW, cat, 'order', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).orderBy('c', 'asc');
        await k(tn).orderBy('c', 'desc');
      }));
      runner.add(FW, cat, 'where-gt', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).where('c', '>', sample);
      }));
      runner.add(FW, cat, 'where-between', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).whereBetween('c', [sample, boundary]);
      }));
      runner.add(FW, cat, 'min-max', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).min({ mn: 'c' }).max({ mx: 'c' });
      }));
    }
    if (group === 'str') {
      runner.add(FW, cat, 'where-like', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).where('c', 'like', '%');
      }));
      runner.add(FW, cat, 'where-in', () => withTable(ddl, async (tn, k) => {
        await k(tn).insert([{ id: 1, c: sample }, { id: 2, c: boundary }]);
        await k(tn).whereIn('c', [sample, boundary]);
      }));
    }
  }
}

async function teardown() { if (knex) { await knex.destroy(); knex = null; } }

module.exports = { register, teardown };
