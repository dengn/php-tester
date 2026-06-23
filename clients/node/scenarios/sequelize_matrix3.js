'use strict';

// Sequelize wave 3: operator × numeric/date/string type combinations exercised
// through findAll, plus aggregate-function-per-type and association-kind matrix.

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
async function withModel(attrs, body) {
  const s = getSequelize(); const name = uniq('S3');
  const M = s.define(name, attrs, { tableName: name, timestamps: false });
  try { await M.sync({ force: true }); return await body(M); }
  finally { try { await M.drop(); } catch (_) {} }
}

function register(runner) {
  const FW = 'sequelize';

  // -------------------------------------------------------------------------
  // 1. OPERATOR × TYPE matrix (numeric / date / string columns)
  // -------------------------------------------------------------------------
  const typed = [
    ['INTEGER', DataTypes.INTEGER, [1, 5, 9], 'num'],
    ['BIGINT', DataTypes.BIGINT, ['1', '5', '9'], 'num'],
    ['DECIMAL', DataTypes.DECIMAL(10, 2), ['1.50', '5.50', '9.50'], 'num'],
    ['DOUBLE', DataTypes.DOUBLE, [1.1, 5.5, 9.9], 'num'],
    ['DATEONLY', DataTypes.DATEONLY, ['2026-01-01', '2026-06-01', '2026-12-01'], 'date'],
    ['STRING', DataTypes.STRING, ['apple', 'mango', 'zebra'], 'str'],
    ['CHAR', DataTypes.CHAR(10), ['aaa', 'mmm', 'zzz'], 'str'],
  ];
  for (const [label, type, vals, group] of typed) {
    const cat = `op-type/${label}`;
    const attrs = () => ({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, c: { type } });
    const seed = async (M) => { await M.bulkCreate(vals.map((v) => ({ c: v }))); };
    const mid = vals[1];

    const ops = [
      ['eq', { c: { [Op.eq]: mid } }],
      ['ne', { c: { [Op.ne]: mid } }],
      ['gt', { c: { [Op.gt]: vals[0] } }],
      ['gte', { c: { [Op.gte]: mid } }],
      ['lt', { c: { [Op.lt]: vals[2] } }],
      ['lte', { c: { [Op.lte]: mid } }],
      ['in', { c: { [Op.in]: vals } }],
      ['notIn', { c: { [Op.notIn]: [vals[0]] } }],
      ['between', { c: { [Op.between]: [vals[0], vals[2]] } }],
      ['notBetween', { c: { [Op.notBetween]: [vals[0], vals[2]] } }],
    ];
    if (group === 'str') {
      ops.push(['like', { c: { [Op.like]: '%a%' } }]);
      ops.push(['startsWith', { c: { [Op.startsWith]: 'a' } }]);
      ops.push(['endsWith', { c: { [Op.endsWith]: 'a' } }]);
    }
    for (const [nm, where] of ops) {
      runner.add(FW, cat, nm, () => withModel(attrs(), async (M) => { await seed(M); await M.findAll({ where }); }));
    }
    // order + aggregate per type
    runner.add(FW, cat, 'order-asc', () => withModel(attrs(), async (M) => { await seed(M); await M.findAll({ order: [['c', 'ASC']] }); }));
    runner.add(FW, cat, 'order-desc', () => withModel(attrs(), async (M) => { await seed(M); await M.findAll({ order: [['c', 'DESC']] }); }));
    runner.add(FW, cat, 'count', () => withModel(attrs(), async (M) => { await seed(M); expect(await M.count(), 3, 'count'); }));
    if (group === 'num') {
      runner.add(FW, cat, 'sum', () => withModel(attrs(), async (M) => { await seed(M); await M.sum('c'); }));
      runner.add(FW, cat, 'avg', () => withModel(attrs(), async (M) => { await seed(M); await M.aggregate('c', 'avg'); }));
    }
    runner.add(FW, cat, 'min', () => withModel(attrs(), async (M) => { await seed(M); await M.min('c'); }));
    runner.add(FW, cat, 'max', () => withModel(attrs(), async (M) => { await seed(M); await M.max('c'); }));
  }

  // -------------------------------------------------------------------------
  // 2. FUNCTION-IN-QUERY matrix (Sequelize.fn over a column)
  // -------------------------------------------------------------------------
  function numModel(body) {
    return withModel({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, v: DataTypes.DECIMAL(12, 4), s: DataTypes.STRING },
      async (M) => { await M.bulkCreate([{ v: 3.5, s: 'Hello' }, { v: -7.25, s: 'World' }]); return body(M); });
  }
  const fnCases = [
    ['abs', Sequelize.fn('ABS', Sequelize.col('v'))],
    ['round', Sequelize.fn('ROUND', Sequelize.col('v'), 1)],
    ['ceil', Sequelize.fn('CEIL', Sequelize.col('v'))],
    ['floor', Sequelize.fn('FLOOR', Sequelize.col('v'))],
    ['upper', Sequelize.fn('UPPER', Sequelize.col('s'))],
    ['lower', Sequelize.fn('LOWER', Sequelize.col('s'))],
    ['length', Sequelize.fn('LENGTH', Sequelize.col('s'))],
    ['concat', Sequelize.fn('CONCAT', Sequelize.col('s'), '!')],
    ['substring', Sequelize.fn('SUBSTRING', Sequelize.col('s'), 1, 3)],
    ['coalesce', Sequelize.fn('COALESCE', Sequelize.col('v'), 0)],
    ['count', Sequelize.fn('COUNT', Sequelize.col('id'))],
    ['sum', Sequelize.fn('SUM', Sequelize.col('v'))],
    ['avg', Sequelize.fn('AVG', Sequelize.col('v'))],
    ['max', Sequelize.fn('MAX', Sequelize.col('v'))],
    ['min', Sequelize.fn('MIN', Sequelize.col('v'))],
  ];
  for (const [nm, fn] of fnCases) {
    runner.add(FW, 'fn-query', nm, () => numModel(async (M) => {
      await M.findAll({ attributes: [[fn, 'r']] });
    }));
  }
}

async function teardown() { if (sequelize) { await sequelize.close(); sequelize = null; } }

module.exports = { register, teardown };
