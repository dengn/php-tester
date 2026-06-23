'use strict';

// Broad generated matrices for Sequelize: per-type CRUD operations, operator ×
// type combinations, ordering/grouping, finder option variations, validators.
// Shares the singleton sequelize instance via the main sequelize module.

const { Sequelize, DataTypes, Op } = require('sequelize');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

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
  const s = getSequelize();
  const name = uniq('SX');
  const M = s.define(name, attrs, { tableName: name, timestamps: false, ...options });
  try { await M.sync({ force: true }); return await body(M); }
  finally { try { await M.drop(); } catch (_) {} }
}

// The per-type matrix uses a curated DataTypes set with sample + boundary values.
function typeMatrix() {
  return [
    ['INTEGER', DataTypes.INTEGER, 42, 100, 'num'],
    ['TINYINT', DataTypes.TINYINT, 7, 120, 'num'],
    ['SMALLINT', DataTypes.SMALLINT, 100, 30000, 'num'],
    ['MEDIUMINT', DataTypes.MEDIUMINT, 1000, 800000, 'num'],
    ['BIGINT', DataTypes.BIGINT, '100', '9007199254740991', 'num'],
    ['FLOAT', DataTypes.FLOAT, 3.5, 9.5, 'num'],
    ['DOUBLE', DataTypes.DOUBLE, 3.14, 2.71, 'num'],
    ['DECIMAL', DataTypes.DECIMAL(12, 2), '10.50', '99.99', 'num'],
    ['STRING', DataTypes.STRING, 'alpha', 'omega', 'str'],
    ['CHAR', DataTypes.CHAR(10), 'aa', 'zz', 'str'],
    ['TEXT', DataTypes.TEXT, 'hello', 'world', 'str'],
    ['DATEONLY', DataTypes.DATEONLY, '2026-01-01', '2026-12-31', 'dt'],
    ['DATE', DataTypes.DATE, new Date('2026-01-01T00:00:00Z'), new Date('2026-12-31T00:00:00Z'), 'dt'],
    ['TIME', DataTypes.TIME, '08:00:00', '20:00:00', 'dt'],
    ['BOOLEAN', DataTypes.BOOLEAN, true, false, 'bool'],
    ['ENUM', DataTypes.ENUM('a', 'b', 'c'), 'a', 'c', 'enum'],
  ];
}

function register(runner) {
  const FW = 'sequelize';

  // -------------------------------------------------------------------------
  // 1. TYPE × OPERATION matrix
  //    operations: where-eq, where-gt, where-lt, in, between, order-asc,
  //    order-desc, group-count, distinct, update, bulk, min-max  (~12 × 16)
  // -------------------------------------------------------------------------
  for (const [label, type, sample, boundary, group] of typeMatrix()) {
    const cat = `type-op/${label}`;
    const attrs = () => ({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, c: { type, allowNull: true } });
    const seed = async (M) => { await M.bulkCreate([{ c: sample }, { c: boundary }]); };

    const ops = [
      ['where-eq', async (M) => { await M.findAll({ where: { c: sample } }); }],
      ['where-ne', async (M) => { await M.findAll({ where: { c: { [Op.ne]: sample } } }); }],
      ['where-in', async (M) => { await M.findAll({ where: { c: { [Op.in]: [sample, boundary] } } }); }],
      ['where-not-null', async (M) => { await M.findAll({ where: { c: { [Op.not]: null } } }); }],
      ['order-asc', async (M) => { await M.findAll({ order: [['c', 'ASC']] }); }],
      ['order-desc', async (M) => { await M.findAll({ order: [['c', 'DESC']] }); }],
      ['count', async (M) => { await M.count(); }],
      ['group-count', async (M) => { await M.findAll({ attributes: ['c', [Sequelize.fn('COUNT', Sequelize.col('id')), 'n']], group: ['c'] }); }],
      ['distinct', async (M) => { await M.aggregate('c', 'count', { distinct: true }); }],
      ['update', async (M) => { await M.update({ c: sample }, { where: { c: boundary } }); }],
      ['max', async (M) => { await M.max('c'); }],
      ['min', async (M) => { await M.min('c'); }],
    ];
    // numeric/date types also get gt/lt/between
    if (group === 'num' || group === 'dt') {
      ops.push(['where-gt', async (M) => { await M.findAll({ where: { c: { [Op.gt]: sample } } }); }]);
      ops.push(['where-lt', async (M) => { await M.findAll({ where: { c: { [Op.lt]: boundary } } }); }]);
      ops.push(['where-between', async (M) => { await M.findAll({ where: { c: { [Op.between]: [sample, boundary] } } }); }]);
    }
    if (group === 'str') {
      ops.push(['where-like', async (M) => { await M.findAll({ where: { c: { [Op.like]: '%' } } }); }]);
      ops.push(['where-substring', async (M) => { await M.findAll({ where: { c: { [Op.substring]: 'a' } } }); }]);
    }

    for (const [nm, fn] of ops) {
      runner.add(FW, cat, nm, () => withModel(attrs(), {}, async (M) => { await seed(M); await fn(M); }));
    }
  }

  // -------------------------------------------------------------------------
  // 2. FINDER-OPTION matrix on a single model
  // -------------------------------------------------------------------------
  function fmodel(body) {
    return withModel({
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true },
      name: DataTypes.STRING, age: DataTypes.INTEGER, grp: DataTypes.INTEGER, active: DataTypes.BOOLEAN,
    }, {}, async (M) => {
      await M.bulkCreate(Array.from({ length: 12 }, (_, i) => ({ name: `n${i}`, age: i, grp: i % 3, active: i % 2 === 0 })));
      return body(M);
    });
  }
  const finderOpts = [
    ['attributes-subset', { attributes: ['id', 'name'] }],
    ['attributes-exclude', { attributes: { exclude: ['age'] } }],
    ['order-multi', { order: [['grp', 'ASC'], ['age', 'DESC']] }],
    ['limit', { limit: 5 }],
    ['offset', { offset: 5 }],
    ['limit-offset', { limit: 3, offset: 6 }],
    ['where-and', { where: { [Op.and]: [{ grp: 1 }, { active: true }] } }],
    ['where-or', { where: { [Op.or]: [{ grp: 0 }, { grp: 2 }] } }],
    ['where-gt-lt', { where: { age: { [Op.gt]: 2, [Op.lt]: 9 } } }],
    ['group', { attributes: ['grp', [Sequelize.fn('COUNT', Sequelize.col('id')), 'n']], group: ['grp'] }],
    ['having', { attributes: ['grp', [Sequelize.fn('COUNT', Sequelize.col('id')), 'n']], group: ['grp'], having: Sequelize.literal('COUNT(id) > 1') }],
    ['distinct-col', { attributes: [[Sequelize.fn('DISTINCT', Sequelize.col('grp')), 'g']] }],
    ['raw-true', { raw: true }],
    ['subquery-false', { subQuery: false, limit: 2 }],
    ['order-by-fn', { order: [[Sequelize.fn('ABS', Sequelize.col('age')), 'ASC']] }],
    ['where-literal', { where: Sequelize.literal('age > 5') }],
    ['nested-attributes', { attributes: ['name', [Sequelize.literal('age * 2'), 'double_age']] }],
  ];
  for (const [nm, opts] of finderOpts) {
    runner.add(FW, 'finder', nm, () => fmodel(async (M) => { await M.findAll(opts); }));
  }

  // -------------------------------------------------------------------------
  // 3. OPERATOR exhaustive matrix (each Op against age + name)
  // -------------------------------------------------------------------------
  const opMatrix = [
    ['eq', { age: { [Op.eq]: 5 } }], ['ne', { age: { [Op.ne]: 5 } }],
    ['gt', { age: { [Op.gt]: 5 } }], ['gte', { age: { [Op.gte]: 5 } }],
    ['lt', { age: { [Op.lt]: 5 } }], ['lte', { age: { [Op.lte]: 5 } }],
    ['in', { age: { [Op.in]: [1, 2, 3] } }], ['notIn', { age: { [Op.notIn]: [1, 2] } }],
    ['between', { age: { [Op.between]: [2, 8] } }], ['notBetween', { age: { [Op.notBetween]: [2, 8] } }],
    ['like', { name: { [Op.like]: 'n%' } }], ['notLike', { name: { [Op.notLike]: 'z%' } }],
    ['startsWith', { name: { [Op.startsWith]: 'n' } }], ['endsWith', { name: { [Op.endsWith]: '1' } }],
    ['substring', { name: { [Op.substring]: '1' } }],
    ['is-null', { name: { [Op.is]: null } }], ['not-null', { name: { [Op.not]: null } }],
    ['or-list', { age: { [Op.or]: [1, 2, 3] } }],
    ['and-merge', { age: { [Op.gte]: 2, [Op.lte]: 8 } }],
    ['regexp', { name: { [Op.regexp]: '^n' } }], ['notRegexp', { name: { [Op.notRegexp]: '^z' } }],
    ['col-compare', { age: { [Op.gt]: Sequelize.col('grp') } }],
    ['any', { age: { [Op.in]: Sequelize.literal('(1,2,3)') } }],
  ];
  for (const [nm, where] of opMatrix) {
    runner.add(FW, 'op-matrix', nm, () => fmodel(async (M) => { await M.findAll({ where }); }));
  }

  // -------------------------------------------------------------------------
  // 4. VALIDATOR / setter / getter matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'validation', 'isEmail', async () => {
    const s = getSequelize(); const name = uniq('SXV');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, email: { type: DataTypes.STRING, validate: { isEmail: true } } }, { tableName: name, timestamps: false });
    try {
      await M.sync({ force: true });
      try { await M.create({ email: 'not-an-email' }); }
      catch (e) { return; } // validation error expected (client-side)
      throw new BehaviorMismatch('isEmail validation did not reject');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'validation', 'len-range', async () => {
    const s = getSequelize(); const name = uniq('SXV');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: { type: DataTypes.STRING, validate: { len: [2, 5] } } }, { tableName: name, timestamps: false });
    try {
      await M.sync({ force: true });
      try { await M.create({ n: 'x' }); } catch (e) { return; }
      throw new BehaviorMismatch('len validation did not reject');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'validation', 'getter-setter', async () => {
    const s = getSequelize(); const name = uniq('SXV');
    const M = s.define(name, {
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true },
      n: { type: DataTypes.STRING, set(v) { this.setDataValue('n', String(v).toUpperCase()); } },
    }, { tableName: name, timestamps: false });
    try {
      await M.sync({ force: true });
      const r = await M.create({ n: 'abc' });
      expect(r.n, 'ABC', 'setter applied');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'validation', 'virtual-field', async () => {
    const s = getSequelize(); const name = uniq('SXV');
    const M = s.define(name, {
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true },
      first: DataTypes.STRING, last: DataTypes.STRING,
      full: { type: DataTypes.VIRTUAL, get() { return `${this.first} ${this.last}`; } },
    }, { tableName: name, timestamps: false });
    try {
      await M.sync({ force: true });
      const r = await M.create({ first: 'a', last: 'b' });
      expect(r.full, 'a b', 'virtual field');
    } finally { try { await M.drop(); } catch (_) {} }
  });

  // -------------------------------------------------------------------------
  // 5. TIMESTAMPS / model-options matrix
  // -------------------------------------------------------------------------
  runner.add(FW, 'model-option', 'timestamps-default', async () => {
    const s = getSequelize(); const name = uniq('SXT');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING }, { tableName: name, timestamps: true });
    try {
      await M.sync({ force: true });
      const r = await M.create({ n: 'a' });
      expectTrue(!!r.createdAt && !!r.updatedAt, 'timestamps set');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'model-option', 'underscored', async () => {
    const s = getSequelize(); const name = uniq('SXT');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, firstName: DataTypes.STRING }, { tableName: name, timestamps: true, underscored: true });
    try {
      await M.sync({ force: true });
      await M.create({ firstName: 'a' });
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'model-option', 'composite-pk', async () => {
    const s = getSequelize(); const name = uniq('SXT');
    const M = s.define(name, { a: { type: DataTypes.INTEGER, primaryKey: true }, b: { type: DataTypes.INTEGER, primaryKey: true }, v: DataTypes.INTEGER }, { tableName: name, timestamps: false });
    try {
      await M.sync({ force: true });
      await M.create({ a: 1, b: 1, v: 10 });
      const r = await M.findOne({ where: { a: 1, b: 1 } });
      expect(r.v, 10, 'composite pk roundtrip');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'model-option', 'index-definition', async () => {
    const s = getSequelize(); const name = uniq('SXT');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, a: DataTypes.INTEGER, b: DataTypes.INTEGER },
      { tableName: name, timestamps: false, indexes: [{ fields: ['a'] }, { unique: true, fields: ['b'] }] });
    try { await M.sync({ force: true }); } finally { try { await M.drop(); } catch (_) {} }
  });
}

async function teardown() { if (sequelize) { await sequelize.close(); sequelize = null; } }

module.exports = { register, teardown };
