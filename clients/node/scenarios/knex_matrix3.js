'use strict';

// Knex wave 3: operator × type matrix, raw-expression matrix, modifier coverage,
// and pagination/aggregate variations to broaden coverage.

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
  const k = getKnex(); const t = uniq('k3');
  await k.schema.createTable(t, builder);
  try { return await body(t, k); } finally { try { await k.schema.dropTableIfExists(t); } catch (_) {} }
}

function register(runner) {
  const FW = 'knex';

  // -------------------------------------------------------------------------
  // 1. OPERATOR × TYPE matrix
  // -------------------------------------------------------------------------
  const typed = [
    ['integer', (t, c) => t.integer(c), [1, 5, 9], 'num'],
    ['decimal', (t, c) => t.decimal(c, 10, 2), ['1.50', '5.50', '9.50'], 'num'],
    ['double', (t, c) => t.double(c), [1.1, 5.5, 9.9], 'num'],
    ['string', (t, c) => t.string(c, 50), ['apple', 'mango', 'zebra'], 'str'],
    ['date', (t, c) => t.date(c), ['2026-01-01', '2026-06-01', '2026-12-01'], 'date'],
    ['datetime', (t, c) => t.datetime(c), ['2026-01-01 00:00:00', '2026-06-01 00:00:00', '2026-12-01 00:00:00'], 'date'],
  ];
  for (const [label, build, vals, group] of typed) {
    const cat = `op-type/${label}`;
    const ddl = (t) => { t.increments('id'); build(t, 'c'); };
    const seed = async (tn, k) => { await k(tn).insert(vals.map((v) => ({ c: v }))); };
    const mid = vals[1];

    const ops = [
      ['where-eq', (q) => q.where('c', mid)],
      ['where-ne', (q) => q.whereNot('c', mid)],
      ['where-gt', (q) => q.where('c', '>', vals[0])],
      ['where-gte', (q) => q.where('c', '>=', mid)],
      ['where-lt', (q) => q.where('c', '<', vals[2])],
      ['where-lte', (q) => q.where('c', '<=', mid)],
      ['whereIn', (q) => q.whereIn('c', vals)],
      ['whereNotIn', (q) => q.whereNotIn('c', [vals[0]])],
      ['whereBetween', (q) => q.whereBetween('c', [vals[0], vals[2]])],
      ['whereNotBetween', (q) => q.whereNotBetween('c', [vals[0], vals[2]])],
      ['orderBy-asc', (q) => q.orderBy('c', 'asc')],
      ['orderBy-desc', (q) => q.orderBy('c', 'desc')],
      ['count', (q) => q.count({ n: '*' })],
      ['distinct', (q) => q.distinct('c')],
      ['min-max', (q) => q.min({ mn: 'c' }).max({ mx: 'c' })],
    ];
    if (group === 'str') {
      ops.push(['where-like', (q) => q.where('c', 'like', '%a%')]);
    }
    if (group === 'num') {
      ops.push(['sum', (q) => q.sum({ s: 'c' })]);
      ops.push(['avg', (q) => q.avg({ a: 'c' })]);
    }
    for (const [nm, fn] of ops) {
      runner.add(FW, cat, nm, () => withTable(ddl, async (tn, k) => { await seed(tn, k); await fn(k(tn)); }));
    }
  }

  // -------------------------------------------------------------------------
  // 2. RAW-expression matrix
  // -------------------------------------------------------------------------
  const rawExprs = [
    ['select-literal', "SELECT 1 AS one"],
    ['select-concat', "SELECT CONCAT('a','b') AS x"],
    ['select-now', "SELECT NOW() AS n"],
    ['select-cast', "SELECT CAST('5' AS SIGNED) AS x"],
    ['select-coalesce', "SELECT COALESCE(NULL, 3) AS x"],
    ['select-case', "SELECT CASE WHEN 1>0 THEN 'y' ELSE 'n' END AS x"],
    ['select-if', "SELECT IF(1>0, 'y', 'n') AS x"],
    ['select-json-extract', "SELECT JSON_EXTRACT('{\"a\":1}', '$.a') AS x"],
    ['select-date-format', "SELECT DATE_FORMAT('2026-06-23', '%Y') AS x"],
    ['select-round', "SELECT ROUND(3.567, 2) AS x"],
  ];
  for (const [nm, sql] of rawExprs) {
    runner.add(FW, 'raw-expr', nm, async () => { await getKnex().raw(sql); });
  }

  // -------------------------------------------------------------------------
  // 3. PAGINATION / modifier variations
  // -------------------------------------------------------------------------
  function bigTable(body) {
    return withTable((t) => { t.increments('id'); t.integer('v'); }, async (tn, k) => {
      const rows = []; for (let i = 0; i < 50; i++) rows.push({ v: i });
      await k(tn).insert(rows);
      return body(tn, k);
    });
  }
  const pageCases = [
    ['limit-10', (q) => q.limit(10)],
    ['offset-20', (q) => q.offset(20).limit(10)],
    ['orderBy-limit', (q) => q.orderBy('v', 'desc').limit(5)],
    ['first', (q) => q.where('v', 25).first()],
    ['pluck', (q) => q.pluck('v')],
    ['distinct-count', (q) => q.countDistinct({ c: 'v' })],
    ['select-raw-col', (q) => q.select(getKnex().raw('v * 2 AS dbl'))],
    ['groupByRaw', (q) => q.select(getKnex().raw('v % 2 AS parity')).count({ c: '*' }).groupByRaw('v % 2')],
    ['havingBetween', (q) => q.select('v').count({ c: '*' }).groupBy('v').havingRaw('count(*) >= 1')],
    ['whereRaw-mod', (q) => q.whereRaw('v % 5 = 0')],
  ];
  for (const [nm, fn] of pageCases) {
    runner.add(FW, 'pagination', nm, () => bigTable(async (tn, k) => { await fn(k(tn)); }));
  }
}

async function teardown() { if (knex) { await knex.destroy(); knex = null; } }

module.exports = { register, teardown };
