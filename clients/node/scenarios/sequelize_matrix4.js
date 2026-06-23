'use strict';

// Sequelize wave 4: instance-method coverage, attribute-casting matrix, and
// where-with-literal/fn matrix to round out the ORM surface.

const { Sequelize, DataTypes, Op } = require('sequelize');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { expect, expectTrue } = require('../lib/errors');

let sequelize = null;
function getSequelize() {
  if (!sequelize) {
    sequelize = new Sequelize(DATABASES.sequelize, CONFIG.user, CONFIG.password, {
      host: CONFIG.host, port: CONFIG.port, dialect: 'mysql',
      dialectModule: require('mysql2'), logging: false,
      pool: { max: 4, min: 0, idle: 10000 }, retry: { max: 0 },
    });
  }
  return sequelize;
}
async function withModel(attrs, options, body) {
  const s = getSequelize(); const name = uniq('S4');
  const M = s.define(name, attrs, { tableName: name, timestamps: false, ...options });
  try { await M.sync({ force: true }); return await body(M); }
  finally { try { await M.drop(); } catch (_) {} }
}

function register(runner) {
  const FW = 'sequelize';

  // -------------------------------------------------------------------------
  // 1. INSTANCE-METHOD matrix
  // -------------------------------------------------------------------------
  function im(name, fn) {
    runner.add(FW, 'instance', name, () => withModel({
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true },
      name: DataTypes.STRING, age: DataTypes.INTEGER,
    }, {}, fn));
  }
  im('reload', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    await M.update({ age: 99 }, { where: { id: c.id } });
    await c.reload();
    expect(c.age, 99, 'reload');
  });
  im('toJSON', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    const j = c.toJSON();
    expectTrue(j.name === 'a' && typeof j === 'object', 'toJSON');
  });
  im('get-plain', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    const p = c.get({ plain: true });
    expectTrue(p.name === 'a', 'get plain');
  });
  im('changed', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    c.age = 2;
    expectTrue(c.changed('age') === true, 'changed tracking');
  });
  im('previous', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    c.age = 2;
    expect(c.previous('age'), 1, 'previous value');
  });
  im('set-multiple', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    c.set({ name: 'b', age: 5 });
    await c.save();
    expect(c.name, 'b', 'set multiple');
  });
  im('destroy-reload-gone', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    await c.destroy();
    const found = await M.findByPk(c.id);
    expectTrue(found === null, 'destroyed not found');
  });
  im('update-method', async (M) => {
    const c = await M.create({ name: 'a', age: 1 });
    await c.update({ age: 42 });
    expect(c.age, 42, 'instance update method');
  });
  im('findCreateFind', async (M) => {
    const [c] = await M.findOrCreate({ where: { name: 'x' }, defaults: { age: 1 } });
    expectTrue(!!c, 'findOrCreate');
  });
  im('count-where', async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1 }, { name: 'b', age: 2 }]);
    expect(await M.count({ where: { age: { [Op.gt]: 1 } } }), 1, 'count where');
  });

  // -------------------------------------------------------------------------
  // 2. ATTRIBUTE-CASTING matrix (model with typed casts)
  // -------------------------------------------------------------------------
  const castCases = [
    ['boolean-cast', { flag: DataTypes.BOOLEAN }, { flag: true }, (r) => expect(r.flag, true, 'bool')],
    ['json-cast', { doc: DataTypes.JSON }, { doc: { a: [1, 2] } }, (r) => expect(r.doc.a[1], 2, 'json')],
    ['decimal-cast', { amt: DataTypes.DECIMAL(10, 2) }, { amt: '12.34' }, (r) => expectTrue(Number(r.amt) === 12.34, 'decimal')],
    ['date-cast', { d: DataTypes.DATEONLY }, { d: '2026-06-23' }, (r) => expect(String(r.d), '2026-06-23', 'date')],
    ['bigint-cast', { big: DataTypes.BIGINT }, { big: '90071992547409' }, (r) => expectTrue(String(r.big) === '90071992547409', 'bigint')],
    ['enum-cast', { e: DataTypes.ENUM('x', 'y') }, { e: 'y' }, (r) => expect(r.e, 'y', 'enum')],
    ['integer-cast', { n: DataTypes.INTEGER }, { n: 42 }, (r) => expect(r.n, 42, 'int')],
    ['text-cast', { t: DataTypes.TEXT }, { t: 'hello' }, (r) => expect(r.t, 'hello', 'text')],
  ];
  for (const [nm, extra, data, check] of castCases) {
    runner.add(FW, 'casting', nm, () => withModel({
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, ...extra,
    }, {}, async (M) => {
      const c = await M.create(data);
      const r = await M.findByPk(c.id);
      check(r);
    }));
  }

  // -------------------------------------------------------------------------
  // 3. RAW-query-with-type matrix (QueryTypes)
  // -------------------------------------------------------------------------
  const { QueryTypes } = require('sequelize');
  const rawCases = [
    ['select-type', 'SELECT 1 AS one', QueryTypes.SELECT],
    ['select-concat', "SELECT CONCAT('a','b') AS x", QueryTypes.SELECT],
    ['select-now', 'SELECT NOW() AS n', QueryTypes.SELECT],
    ['select-math', 'SELECT 2*3 AS x', QueryTypes.SELECT],
    ['select-coalesce', 'SELECT COALESCE(NULL, 5) AS x', QueryTypes.SELECT],
    ['select-cast', "SELECT CAST('7' AS SIGNED) AS x", QueryTypes.SELECT],
    ['select-case', "SELECT CASE WHEN 1>0 THEN 'y' ELSE 'n' END AS x", QueryTypes.SELECT],
    ['select-json', "SELECT JSON_OBJECT('k', 1) AS x", QueryTypes.SELECT],
  ];
  for (const [nm, sql, type] of rawCases) {
    runner.add(FW, 'raw-typed', nm, async () => {
      const r = await getSequelize().query(sql, { type });
      expectTrue(Array.isArray(r), 'raw typed returned rows');
    });
  }

  // -------------------------------------------------------------------------
  // 4. WHERE-with-literal / fn matrix
  // -------------------------------------------------------------------------
  function wmodel(body) {
    return withModel({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, name: DataTypes.STRING, age: DataTypes.INTEGER },
      {}, async (M) => { await M.bulkCreate([{ name: 'Alice', age: 10 }, { name: 'bob', age: 20 }, { name: 'CAROL', age: 30 }]); return body(M); });
  }
  const litCases = [
    ['literal-gt', { where: Sequelize.literal('age > 15') }],
    ['literal-mod', { where: Sequelize.literal('age % 10 = 0') }],
    ['fn-length', { where: Sequelize.where(Sequelize.fn('LENGTH', Sequelize.col('name')), { [Op.gt]: 3 }) }],
    ['fn-lower', { where: Sequelize.where(Sequelize.fn('LOWER', Sequelize.col('name')), 'alice') }],
    ['fn-upper', { where: Sequelize.where(Sequelize.fn('UPPER', Sequelize.col('name')), 'BOB') }],
    ['fn-abs', { where: Sequelize.where(Sequelize.fn('ABS', Sequelize.col('age')), { [Op.gte]: 10 }) }],
    ['col-arith', { where: Sequelize.literal('age + 5 > 20') }],
    ['fn-mod', { where: Sequelize.where(Sequelize.fn('MOD', Sequelize.col('age'), 20), 0) }],
  ];
  for (const [nm, opts] of litCases) {
    runner.add(FW, 'where-fn', nm, () => wmodel(async (M) => { await M.findAll(opts); }));
  }
}

async function teardown() { if (sequelize) { await sequelize.close(); sequelize = null; } }

module.exports = { register, teardown };
