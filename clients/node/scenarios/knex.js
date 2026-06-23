'use strict';

const Knex = require('knex');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

let knex = null;
function getKnex() {
  if (!knex) {
    knex = Knex({
      client: 'mysql2',
      connection: {
        host: CONFIG.host,
        port: CONFIG.port,
        user: CONFIG.user,
        password: CONFIG.password,
        database: DATABASES.knex,
      },
      pool: { min: 0, max: 4 },
    });
  }
  return knex;
}

// create a table via the schema builder, run body, always drop.
async function withTable(builder, body) {
  const k = getKnex();
  const t = uniq('k');
  await k.schema.createTable(t, builder);
  try {
    return await body(t, k);
  } finally {
    try { await k.schema.dropTableIfExists(t); } catch (_) {}
  }
}

function register(runner) {
  const FW = 'knex';

  // -------------------------------------------------------------------------
  // 1. CONNECTION
  // -------------------------------------------------------------------------
  runner.add(FW, 'connection', 'raw-select', async () => {
    const r = await getKnex().raw('SELECT 1 AS one');
    expect(Number(r[0][0].one), 1, 'raw select');
  });

  // -------------------------------------------------------------------------
  // 2. SCHEMA BUILDER — column type matrix
  //    Knex column-builder method × {create, insert, roundtrip}
  // -------------------------------------------------------------------------
  const colTypes = [
    ['increments', (t) => t.increments('id'), null],
    ['integer', (t) => t.integer('c'), 42],
    ['bigInteger', (t) => t.bigInteger('c'), '9007199254740991'],
    ['tinyint', (t) => t.tinyint('c'), 7],
    ['smallint', (t) => t.specificType('c', 'smallint'), 1234],
    ['mediumint', (t) => t.specificType('c', 'mediumint'), 100000],
    ['decimal', (t) => t.decimal('c', 10, 2), '123.45'],
    ['float', (t) => t.float('c'), 3.5],
    ['double', (t) => t.double('c'), 3.14159],
    ['string', (t) => t.string('c', 255), 'hello'],
    ['text', (t) => t.text('c'), 'some text'],
    ['text-medium', (t) => t.text('c', 'mediumtext'), 'med'],
    ['text-long', (t) => t.text('c', 'longtext'), 'lng'],
    ['boolean', (t) => t.boolean('c'), true],
    ['date', (t) => t.date('c'), '2026-06-23'],
    ['datetime', (t) => t.datetime('c'), '2026-06-23 11:00:00'],
    ['timestamp', (t) => t.timestamp('c'), '2026-06-23 11:00:00'],
    ['time', (t) => t.time('c'), '11:30:00'],
    ['binary', (t) => t.binary('c'), Buffer.from('bin')],
    ['json', (t) => t.json('c'), { k: 'v' }],
    ['jsonb', (t) => t.jsonb('c'), { k: 'v' }],
    ['enum', (t) => t.enum('c', ['a', 'b', 'c']), 'b'],
    ['uuid', (t) => t.uuid('c'), '6f9619ff-8b86-d011-b42d-00cf4fc964ff'],
  ];
  for (const [label, build, sample] of colTypes) {
    runner.add(FW, 'schema/create', `create ${label}`, () =>
      withTable((t) => {
        if (label === 'increments') { build(t); } // increments() defines its own id pk
        else { t.integer('id').primary(); build(t); }
      }, async () => {}));

    if (sample !== null) {
      runner.add(FW, 'schema/insert', `insert ${label}`, () =>
        withTable((t) => { t.integer('pk').primary(); build(t); }, async (tn, k) => {
          const val = (typeof sample === 'object' && !Buffer.isBuffer(sample)) ? JSON.stringify(sample) : sample;
          await k(tn).insert({ pk: 1, c: val });
        }));

      runner.add(FW, 'schema/roundtrip', `roundtrip ${label}`, () =>
        withTable((t) => { t.integer('pk').primary(); build(t); }, async (tn, k) => {
          const val = (typeof sample === 'object' && !Buffer.isBuffer(sample)) ? JSON.stringify(sample) : sample;
          await k(tn).insert({ pk: 1, c: val });
          const rows = await k(tn).where({ pk: 1 });
          if (rows.length !== 1) throw new BehaviorMismatch('row missing after insert');
          if (label === 'float' && Number(rows[0].c) === 4 && Number(sample) === 3.5) {
            throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${rows[0].c}`);
          }
        }));
    }
  }

  // column modifiers
  runner.add(FW, 'schema/modifier', 'notNullable', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n').notNullable(); }, async (tn, k) => {
      await k(tn).insert({ id: 1, n: 'x' });
    }));
  runner.add(FW, 'schema/modifier', 'defaultTo', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n').defaultTo('def'); }, async (tn, k) => {
      await k(tn).insert({ id: 1 });
      const r = await k(tn).where({ id: 1 }).first();
      expect(r.n, 'def', 'defaultTo');
    }));
  runner.add(FW, 'schema/modifier', 'unique', () =>
    withTable((t) => { t.integer('id').primary(); t.string('e').unique(); }, async (tn, k) => {
      await k(tn).insert({ id: 1, e: 'a@b.com' });
    }));
  runner.add(FW, 'schema/modifier', 'index', () =>
    withTable((t) => { t.integer('id').primary(); t.integer('a'); t.index(['a']); }, async () => {}));
  runner.add(FW, 'schema/modifier', 'comment', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n').comment('a name'); }, async () => {}));
  runner.add(FW, 'schema/modifier', 'unsigned', () =>
    withTable((t) => { t.integer('id').primary(); t.integer('a').unsigned(); }, async () => {}));
  runner.add(FW, 'schema/modifier', 'timestamps', () =>
    withTable((t) => { t.integer('id').primary(); t.timestamps(true, true); }, async () => {}));

  // -------------------------------------------------------------------------
  // 3. SCHEMA ALTER / index / foreign
  // -------------------------------------------------------------------------
  runner.add(FW, 'schema/alter', 'add-column', () =>
    withTable((t) => { t.integer('id').primary(); }, async (tn, k) => {
      await k.schema.alterTable(tn, (t) => { t.string('extra'); });
    }));
  runner.add(FW, 'schema/alter', 'drop-column', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n'); }, async (tn, k) => {
      await k.schema.alterTable(tn, (t) => { t.dropColumn('n'); });
    }));
  runner.add(FW, 'schema/alter', 'add-index', () =>
    withTable((t) => { t.integer('id').primary(); t.integer('a'); }, async (tn, k) => {
      await k.schema.alterTable(tn, (t) => { t.index(['a'], 'ix_a'); });
    }));
  runner.add(FW, 'schema/alter', 'add-unique', () =>
    withTable((t) => { t.integer('id').primary(); t.string('e'); }, async (tn, k) => {
      await k.schema.alterTable(tn, (t) => { t.unique(['e'], { indexName: 'uq_e' }); });
    }));
  runner.add(FW, 'schema/alter', 'rename-column', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n'); }, async (tn, k) => {
      await k.schema.alterTable(tn, (t) => { t.renameColumn('n', 'nn'); });
    }));
  runner.add(FW, 'schema', 'foreign-key', async () => {
    const k = getKnex();
    const p = uniq('kp'); const c = uniq('kc');
    try {
      await k.schema.createTable(p, (t) => { t.integer('id').primary(); });
      await k.schema.createTable(c, (t) => { t.integer('id').primary(); t.integer('pid'); t.foreign('pid').references('id').inTable(p); });
    } finally {
      await k.schema.dropTableIfExists(c); await k.schema.dropTableIfExists(p);
    }
  });
  runner.add(FW, 'schema', 'hasTable', () =>
    withTable((t) => { t.integer('id').primary(); }, async (tn, k) => {
      const has = await k.schema.hasTable(tn);
      expectTrue(has === true, 'hasTable');
    }));
  runner.add(FW, 'schema', 'hasColumn', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n'); }, async (tn, k) => {
      const has = await k.schema.hasColumn(tn, 'n');
      expectTrue(has === true, 'hasColumn');
    }));
  runner.add(FW, 'schema', 'columnInfo', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n'); }, async (tn, k) => {
      const info = await k(tn).columnInfo();
      expectTrue(!!info.n, 'columnInfo introspection');
    }));

  // -------------------------------------------------------------------------
  // 4. QUERY BUILDER
  // -------------------------------------------------------------------------
  function dataTable(body) {
    return withTable((t) => {
      t.increments('id'); t.string('name'); t.integer('grp'); t.decimal('amt', 10, 2);
    }, async (tn, k) => {
      await k(tn).insert([
        { name: 'x', grp: 1, amt: 10.5 },
        { name: 'y', grp: 1, amt: 20.0 },
        { name: 'z', grp: 2, amt: 5.0 },
      ]);
      return body(tn, k);
    });
  }
  const qb = (name, fn) => runner.add(FW, 'query', name, () => dataTable(fn));
  qb('select-all', async (tn, k) => { const r = await k(tn).select('*'); expect(r.length, 3, 'select all'); });
  qb('select-columns', async (tn, k) => { await k(tn).select('name', 'grp'); });
  qb('where', async (tn, k) => { const r = await k(tn).where('grp', 1); expect(r.length, 2, 'where'); });
  qb('where-object', async (tn, k) => { await k(tn).where({ grp: 1, name: 'x' }); });
  qb('whereIn', async (tn, k) => { const r = await k(tn).whereIn('grp', [1, 2]); expect(r.length, 3, 'whereIn'); });
  qb('whereNotIn', async (tn, k) => { await k(tn).whereNotIn('grp', [99]); });
  qb('whereNull', async (tn, k) => { await k(tn).whereNull('name'); });
  qb('whereNotNull', async (tn, k) => { const r = await k(tn).whereNotNull('name'); expect(r.length, 3, 'whereNotNull'); });
  qb('whereBetween', async (tn, k) => { const r = await k(tn).whereBetween('amt', [0, 100]); expect(r.length, 3, 'whereBetween'); });
  qb('whereLike', async (tn, k) => { await k(tn).where('name', 'like', 'x%'); });
  qb('andWhere-orWhere', async (tn, k) => { await k(tn).where('grp', 1).orWhere('name', 'z'); });
  qb('whereRaw', async (tn, k) => { await k(tn).whereRaw('amt > ?', [1]); });
  qb('orderBy', async (tn, k) => { const r = await k(tn).orderBy('amt', 'desc'); expect(Number(r[0].amt), 20, 'orderBy'); });
  qb('limit-offset', async (tn, k) => { const r = await k(tn).orderBy('id').limit(1).offset(1); expect(r.length, 1, 'limit/offset'); });
  qb('distinct', async (tn, k) => { const r = await k(tn).distinct('grp'); expect(r.length, 2, 'distinct'); });
  qb('count', async (tn, k) => { const r = await k(tn).count({ c: '*' }); expect(Number(r[0].c), 3, 'count'); });
  qb('sum', async (tn, k) => { const r = await k(tn).sum({ s: 'amt' }); expect(Number(r[0].s), 35.5, 'sum'); });
  qb('avg', async (tn, k) => { await k(tn).avg({ a: 'amt' }); });
  qb('min-max', async (tn, k) => { await k(tn).min({ mn: 'amt' }).max({ mx: 'amt' }); });
  qb('groupBy', async (tn, k) => { const r = await k(tn).select('grp').count({ c: '*' }).groupBy('grp'); expect(r.length, 2, 'groupBy'); });
  qb('groupBy-having', async (tn, k) => {
    const r = await k(tn).select('grp').count({ c: '*' }).groupBy('grp').having(k.raw('count(*)'), '>', 1);
    expect(r.length, 1, 'having');
  });
  qb('first', async (tn, k) => { const r = await k(tn).where('grp', 1).first(); expectTrue(!!r, 'first'); });
  qb('pluck', async (tn, k) => { const r = await k(tn).pluck('name'); expect(r.length, 3, 'pluck'); });
  qb('insert', async (tn, k) => { await k(tn).insert({ name: 'w', grp: 3, amt: 1 }); expect((await k(tn)).length, 4, 'insert'); });
  qb('insert-multi', async (tn, k) => { await k(tn).insert([{ name: 'a', grp: 5, amt: 1 }, { name: 'b', grp: 5, amt: 2 }]); });
  qb('update', async (tn, k) => { await k(tn).where('grp', 1).update({ amt: 0 }); const r = await k(tn).where('grp', 1); expect(Number(r[0].amt), 0, 'update'); });
  qb('increment', async (tn, k) => { await k(tn).where('grp', 1).increment('amt', 5); });
  qb('decrement', async (tn, k) => { await k(tn).where('grp', 1).decrement('amt', 1); });
  qb('delete', async (tn, k) => { await k(tn).where('grp', 2).del(); expect((await k(tn)).length, 2, 'delete'); });
  qb('returning-insert', async (tn, k) => { await k(tn).insert({ name: 'r', grp: 9, amt: 1 }); });

  // joins, union, CTE
  runner.add(FW, 'query', 'inner-join', () => joinTables(async (a, b, k) => {
    const r = await k(a).join(b, `${a}.id`, `${b}.aid`).select(`${a}.id`);
    expectTrue(r.length >= 1, 'inner join');
  }));
  runner.add(FW, 'query', 'left-join', () => joinTables(async (a, b, k) => {
    await k(a).leftJoin(b, `${a}.id`, `${b}.aid`).select(`${a}.id`);
  }));
  runner.add(FW, 'query', 'right-join', () => joinTables(async (a, b, k) => {
    await k(a).rightJoin(b, `${a}.id`, `${b}.aid`).select(`${a}.id`);
  }));
  runner.add(FW, 'query', 'cross-join', () => joinTables(async (a, b, k) => {
    await k(a).crossJoin(b).select(`${a}.id`);
  }));
  runner.add(FW, 'query', 'union', () => dataTable(async (tn, k) => {
    await k.union([k(tn).select('id'), k(tn).select('id')]);
  }));
  runner.add(FW, 'query', 'unionAll', () => dataTable(async (tn, k) => {
    await k(tn).select('id').unionAll(k(tn).select('id'));
  }));
  runner.add(FW, 'query', 'cte-with', () => dataTable(async (tn, k) => {
    const r = await k.with('c', k(tn).where('grp', 1)).select('*').from('c');
    expect(r.length, 2, 'CTE');
  }));
  runner.add(FW, 'query', 'cte-recursive', async () => {
    const k = getKnex();
    await k.withRecursive('seq', ['n'], (qb2) => {
      qb2.select(k.raw('1')).unionAll((q) => q.select(k.raw('n+1')).from('seq').where('n', '<', 5));
    }).select('*').from('seq');
  });
  runner.add(FW, 'query', 'subquery-where-in', () => dataTable(async (tn, k) => {
    await k(tn).whereIn('grp', k(tn).select('grp').where('amt', '>', 5));
  }));
  runner.add(FW, 'query', 'onConflict-merge', async () => {
    const k = getKnex();
    const tn = uniq('k');
    await k.schema.createTable(tn, (t) => { t.integer('id').primary(); t.integer('v'); });
    try {
      await k(tn).insert({ id: 1, v: 1 });
      await k(tn).insert({ id: 1, v: 2 }).onConflict('id').merge();
      const r = await k(tn).where('id', 1).first();
      expect(Number(r.v), 2, 'onConflict merge');
    } finally { await k.schema.dropTableIfExists(tn); }
  });
  runner.add(FW, 'query', 'onConflict-ignore', async () => {
    const k = getKnex();
    const tn = uniq('k');
    await k.schema.createTable(tn, (t) => { t.integer('id').primary(); t.integer('v'); });
    try {
      await k(tn).insert({ id: 1, v: 1 });
      await k(tn).insert({ id: 1, v: 2 }).onConflict('id').ignore();
      const r = await k(tn).where('id', 1).first();
      expect(Number(r.v), 1, 'onConflict ignore');
    } finally { await k.schema.dropTableIfExists(tn); }
  });

  // -------------------------------------------------------------------------
  // 5. RAW
  // -------------------------------------------------------------------------
  runner.add(FW, 'raw', 'raw-bindings', async () => {
    const r = await getKnex().raw('SELECT ? + ? AS s', [2, 3]);
    expect(Number(r[0][0].s), 5, 'raw bindings');
  });
  runner.add(FW, 'raw', 'raw-named-bindings', async () => {
    const r = await getKnex().raw('SELECT :a + :b AS s', { a: 2, b: 3 });
    expect(Number(r[0][0].s), 5, 'raw named bindings');
  });

  // -------------------------------------------------------------------------
  // 6. TRANSACTIONS + savepoints
  // -------------------------------------------------------------------------
  runner.add(FW, 'transaction', 'commit', () =>
    withTable((t) => { t.integer('id').primary(); }, async (tn, k) => {
      await k.transaction(async (trx) => { await trx(tn).insert({ id: 1 }); });
      expect((await k(tn)).length, 1, 'tx commit');
    }));
  runner.add(FW, 'transaction', 'rollback', () =>
    withTable((t) => { t.integer('id').primary(); }, async (tn, k) => {
      try {
        await k.transaction(async (trx) => { await trx(tn).insert({ id: 1 }); throw new Error('boom'); });
      } catch (_) {}
      expect((await k(tn)).length, 0, 'tx rollback');
    }));
  runner.add(FW, 'transaction', 'explicit-commit', () =>
    withTable((t) => { t.integer('id').primary(); }, async (tn, k) => {
      const trx = await k.transaction();
      await trx(tn).insert({ id: 1 });
      await trx.commit();
      expect((await k(tn)).length, 1, 'explicit commit');
    }));
  // nested transaction -> savepoint -> EXPECTED FAILURE (FINDINGS C5)
  runner.add(FW, 'transaction', 'nested-savepoint', () =>
    withTable((t) => { t.integer('id').primary(); t.string('n'); }, async (tn, k) => {
      await k.transaction(async (trx) => {
        await trx(tn).insert({ id: 1, n: 'Outer' });
        try {
          await trx.transaction(async (trx2) => {
            await trx2(tn).insert({ id: 2, n: 'Inner' });
            throw new Error('inner boom');
          });
        } catch (_) {}
      });
      const rows = await k(tn);
      if (rows.length !== 1) throw new BehaviorMismatch(`nested savepoint: expected 1 got ${rows.length} (Inner leaked)`);
    }));
}

async function joinTables(body) {
  const k = getKnex();
  const a = uniq('ka'); const b = uniq('kb');
  try {
    await k.schema.createTable(a, (t) => { t.integer('id').primary(); t.string('name'); });
    await k.schema.createTable(b, (t) => { t.integer('id').primary(); t.integer('aid'); });
    await k(a).insert([{ id: 1, name: 'x' }, { id: 2, name: 'y' }]);
    await k(b).insert([{ id: 1, aid: 1 }, { id: 2, aid: 1 }]);
    return await body(a, b, k);
  } finally {
    await k.schema.dropTableIfExists(b); await k.schema.dropTableIfExists(a);
  }
}

async function teardown() {
  if (knex) { await knex.destroy(); knex = null; }
}

module.exports = { register, teardown };
