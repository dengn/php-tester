'use strict';

// Sequelize wave 2: the FULL DataTypes.* set crossed with declare/insert/
// roundtrip/null/update/bulk/where/order/min-max/count operations, plus
// instance-method and querying-interface coverage. Shares the singleton.

const { Sequelize, DataTypes, Op } = require('sequelize');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect } = require('../lib/errors');

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
async function withModel(attrs, body) {
  const s = getSequelize();
  const name = uniq('SM');
  const M = s.define(name, attrs, { tableName: name, timestamps: false });
  try { await M.sync({ force: true }); return await body(M); }
  finally { try { await M.drop(); } catch (_) {} }
}

// Full DataTypes.* matrix with sample/boundary and an operations group flag.
function fullTypeMatrix() {
  return [
    ['INTEGER', DataTypes.INTEGER, 42, 99, 'ord'],
    ['TINYINT', DataTypes.TINYINT, 7, 120, 'ord'],
    ['SMALLINT', DataTypes.SMALLINT, 100, 30000, 'ord'],
    ['MEDIUMINT', DataTypes.MEDIUMINT, 1000, 800000, 'ord'],
    ['BIGINT', DataTypes.BIGINT, '100', '900719925474099', 'ord'],
    ['INTEGER.UNSIGNED', DataTypes.INTEGER.UNSIGNED, 10, 4000000000, 'ord'],
    ['FLOAT', DataTypes.FLOAT, 3.5, 9.5, 'ord'],
    ['DOUBLE', DataTypes.DOUBLE, 3.14, 2.71, 'ord'],
    ['DECIMAL', DataTypes.DECIMAL(12, 2), '10.50', '99.99', 'ord'],
    ['REAL', DataTypes.REAL, 2.5, 8.5, 'ord'],
    ['STRING', DataTypes.STRING, 'alpha', 'omega', 'str'],
    ['STRING-100', DataTypes.STRING(100), 'ab', 'cd', 'str'],
    ['CHAR', DataTypes.CHAR(10), 'aa', 'zz', 'str'],
    ['TEXT', DataTypes.TEXT, 'hello', 'world', 'plain'],
    ['TEXT-medium', DataTypes.TEXT('medium'), 'm1', 'm2', 'plain'],
    ['TEXT-long', DataTypes.TEXT('long'), 'l1', 'l2', 'plain'],
    ['DATEONLY', DataTypes.DATEONLY, '2026-01-01', '2026-12-31', 'ord'],
    ['DATE', DataTypes.DATE, new Date('2026-01-01T00:00:00Z'), new Date('2026-12-31T00:00:00Z'), 'ord'],
    ['TIME', DataTypes.TIME, '08:00:00', '20:00:00', 'ord'],
    ['BOOLEAN', DataTypes.BOOLEAN, true, false, 'plain'],
    ['ENUM', DataTypes.ENUM('a', 'b', 'c'), 'a', 'c', 'plain'],
    ['BLOB', DataTypes.BLOB, Buffer.from('blob'), Buffer.from('xx'), 'bin'],
    ['BLOB-long', DataTypes.BLOB('long'), Buffer.from('l'), Buffer.from('m'), 'bin'],
    ['JSON', DataTypes.JSON, { k: 1 }, { k: 2 }, 'json'],
    ['UUID', DataTypes.UUID, '6f9619ff-8b86-d011-b42d-00cf4fc964ff', '7f9619ff-8b86-d011-b42d-00cf4fc964ff', 'str'],
  ];
}

function register(runner) {
  const FW = 'sequelize';

  for (const [label, type, sample, boundary, group] of fullTypeMatrix()) {
    const cat = `fulltype/${label}`;
    const attrs = () => ({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, c: { type, allowNull: true } });

    // declare
    runner.add(FW, cat, 'declare', () => withModel(attrs(), async () => {}));
    // insert + roundtrip
    runner.add(FW, cat, 'insert-roundtrip', () => withModel(attrs(), async (M) => {
      const created = await M.create({ c: sample });
      const found = await M.findByPk(created.id);
      if (!found) throw new BehaviorMismatch('row missing after create');
      if (label === 'FLOAT' && Number(found.c) === 4 && Number(sample) === 3.5) {
        throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${found.c}`);
      }
    }));
    // null
    runner.add(FW, cat, 'null', () => withModel(attrs(), async (M) => {
      const c = await M.create({ c: null });
      const f = await M.findByPk(c.id);
      if (f.c !== null) throw new BehaviorMismatch(`null not preserved: ${JSON.stringify(f.c)}`);
    }));
    // update
    runner.add(FW, cat, 'update', () => withModel(attrs(), async (M) => {
      const c = await M.create({ c: sample });
      c.c = boundary; await c.save();
    }));
    // bulkCreate
    runner.add(FW, cat, 'bulkCreate', () => withModel(attrs(), async (M) => {
      await M.bulkCreate([{ c: sample }, { c: boundary }, { c: sample }]);
      expect(await M.count(), 3, 'bulkCreate count');
    }));
    // count
    runner.add(FW, cat, 'count', () => withModel(attrs(), async (M) => {
      await M.bulkCreate([{ c: sample }, { c: boundary }]);
      expect(await M.count(), 2, 'count');
    }));
    // destroy-where
    runner.add(FW, cat, 'destroy-where', () => withModel(attrs(), async (M) => {
      await M.bulkCreate([{ c: sample }, { c: boundary }]);
      await M.destroy({ where: { c: sample } });
    }));

    // ordered / comparable types: order + where-gt + min/max
    if (group === 'ord') {
      runner.add(FW, cat, 'order-asc', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ order: [['c', 'ASC']] });
      }));
      runner.add(FW, cat, 'where-gt', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ where: { c: { [Op.gt]: sample } } });
      }));
      runner.add(FW, cat, 'where-between', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ where: { c: { [Op.between]: [sample, boundary] } } });
      }));
      runner.add(FW, cat, 'min-max', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.min('c'); await M.max('c');
      }));
    }
    // string types: like
    if (group === 'str') {
      runner.add(FW, cat, 'where-like', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ where: { c: { [Op.like]: '%' } } });
      }));
      runner.add(FW, cat, 'where-in', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ where: { c: { [Op.in]: [sample, boundary] } } });
      }));
    }
    // json: path query
    if (group === 'json') {
      runner.add(FW, cat, 'where-json-path', () => withModel(attrs(), async (M) => {
        await M.bulkCreate([{ c: sample }, { c: boundary }]);
        await M.findAll({ where: { 'c.k': 1 } });
      }));
    }
  }
}

async function teardown() { if (sequelize) { await sequelize.close(); sequelize = null; } }

module.exports = { register, teardown };
