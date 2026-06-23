'use strict';

const { Sequelize, DataTypes, Op, Model } = require('sequelize');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

let sequelize = null;
function getSequelize() {
  if (!sequelize) {
    sequelize = new Sequelize(DATABASES.sequelize, CONFIG.user, CONFIG.password, {
      host: CONFIG.host,
      port: CONFIG.port,
      dialect: 'mysql',
      dialectModule: require('mysql2'),
      logging: false,
      pool: { max: 4, min: 0, idle: 10000 },
      retry: { max: 0 },
    });
  }
  return sequelize;
}

// Define + sync a fresh model with a unique table name, run body, then drop.
async function withModel(attrs, options, body) {
  const s = getSequelize();
  const name = uniq('S');
  const M = s.define(name, attrs, { tableName: name, timestamps: false, ...options });
  try {
    await M.sync({ force: true });
    return await body(M);
  } finally {
    try { await M.drop(); } catch (_) {}
  }
}

// The Sequelize DataTypes mapping matrix: every DataTypes.* column type.
function dataTypeMatrix() {
  return [
    ['STRING', DataTypes.STRING, 'hello'],
    ['STRING(100)', DataTypes.STRING(100), 'hi'],
    ['STRING.BINARY', DataTypes.STRING.BINARY, 'bin'],
    ['TEXT', DataTypes.TEXT, 'text'],
    ['TEXT-tiny', DataTypes.TEXT('tiny'), 'tiny'],
    ['TEXT-medium', DataTypes.TEXT('medium'), 'med'],
    ['TEXT-long', DataTypes.TEXT('long'), 'long'],
    ['CHAR', DataTypes.CHAR(10), 'abc'],
    ['CITEXT', DataTypes.CITEXT, 'CiText'],
    ['BOOLEAN', DataTypes.BOOLEAN, true],
    ['TINYINT', DataTypes.TINYINT, 12],
    ['SMALLINT', DataTypes.SMALLINT, 1234],
    ['MEDIUMINT', DataTypes.MEDIUMINT, 100000],
    ['INTEGER', DataTypes.INTEGER, 42],
    ['BIGINT', DataTypes.BIGINT, '9007199254740991'],
    ['INTEGER.UNSIGNED', DataTypes.INTEGER.UNSIGNED, 42],
    ['INTEGER.ZEROFILL', DataTypes.INTEGER.ZEROFILL, 42],
    ['FLOAT', DataTypes.FLOAT, 3.5],
    ['FLOAT(11,2)', DataTypes.FLOAT(11, 2), 3.5],
    ['DOUBLE', DataTypes.DOUBLE, 3.141592653589793],
    ['DOUBLE(15,5)', DataTypes.DOUBLE(15, 5), 3.14159],
    ['DECIMAL', DataTypes.DECIMAL(18, 4), '12345.6789'],
    ['REAL', DataTypes.REAL, 2.71],
    ['DATE', DataTypes.DATE, new Date('2026-06-23T11:00:00Z')],
    ['DATEONLY', DataTypes.DATEONLY, '2026-06-23'],
    ['TIME', DataTypes.TIME, '11:30:00'],
    ['NOW', DataTypes.DATE, new Date()],
    ['BLOB', DataTypes.BLOB, Buffer.from('blob')],
    ['BLOB-tiny', DataTypes.BLOB('tiny'), Buffer.from('t')],
    ['BLOB-medium', DataTypes.BLOB('medium'), Buffer.from('m')],
    ['BLOB-long', DataTypes.BLOB('long'), Buffer.from('l')],
    ['ENUM', DataTypes.ENUM('a', 'b', 'c'), 'b'],
    ['JSON', DataTypes.JSON, { k: 'v' }],
    ['UUID', DataTypes.UUID, '6f9619ff-8b86-d011-b42d-00cf4fc964ff'],
    ['UUIDV4', DataTypes.UUID, DataTypes.UUIDV4],
  ];
}

function register(runner) {
  const FW = 'sequelize';

  // -------------------------------------------------------------------------
  // 1. AUTHENTICATE
  // -------------------------------------------------------------------------
  runner.add(FW, 'connection', 'authenticate', async () => {
    await getSequelize().authenticate();
  });
  runner.add(FW, 'connection', 'query-version', async () => {
    const [r] = await getSequelize().query('SELECT version() AS v');
    expectTrue(/MatrixOne/i.test(r[0].v), 'version mentions MatrixOne');
  });

  // -------------------------------------------------------------------------
  // 2. DATATYPES.* × {sync, insert, roundtrip, null}  (~35 types × 4)
  // -------------------------------------------------------------------------
  for (const [label, type, sample] of dataTypeMatrix()) {
    runner.add(FW, 'datatype/sync', `sync ${label}`, () =>
      withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, c: { type } }, {}, async () => {}));

    if (typeof sample !== 'function') {
      runner.add(FW, 'datatype/insert', `insert ${label}`, () =>
        withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, c: { type } }, {}, async (M) => {
          await M.create({ id: 1, c: sample });
        }));

      runner.add(FW, 'datatype/roundtrip', `roundtrip ${label}`, () =>
        withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, c: { type } }, {}, async (M) => {
          await M.create({ id: 1, c: sample });
          const row = await M.findByPk(1);
          if (!row) throw new BehaviorMismatch('row missing after create');
          if (label === 'FLOAT' && Number(row.c) === 4 && Number(sample) === 3.5) {
            throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${row.c}`);
          }
        }));
    }

    runner.add(FW, 'datatype/null', `null ${label}`, () =>
      withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, c: { type, allowNull: true } }, {}, async (M) => {
        await M.create({ id: 1, c: null });
        const row = await M.findByPk(1);
        if (row.c !== null) throw new BehaviorMismatch(`null not preserved: ${JSON.stringify(row.c)}`);
      }));
  }

  // column modifiers
  runner.add(FW, 'datatype/modifier', 'autoIncrement', () =>
    withModel({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING }, {}, async (M) => {
      const r = await M.create({ n: 'x' });
      expectTrue(r.id >= 1, 'autoIncrement produced id');
    }));
  runner.add(FW, 'datatype/modifier', 'defaultValue', () =>
    withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, n: { type: DataTypes.STRING, defaultValue: 'def' } }, {}, async (M) => {
      await M.create({ id: 1 });
      const r = await M.findByPk(1);
      expect(r.n, 'def', 'default value applied');
    }));
  runner.add(FW, 'datatype/modifier', 'unique', () =>
    withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, e: { type: DataTypes.STRING, unique: true } }, {}, async (M) => {
      await M.create({ id: 1, e: 'a@b.com' });
    }));
  runner.add(FW, 'datatype/modifier', 'comment', () =>
    withModel({ id: { type: DataTypes.INTEGER, primaryKey: true, comment: 'pk' } }, {}, async () => {}));
  runner.add(FW, 'datatype/modifier', 'validate-allowNull-false', () =>
    withModel({ id: { type: DataTypes.INTEGER, primaryKey: true }, n: { type: DataTypes.STRING, allowNull: false } }, {}, async (M) => {
      await M.create({ id: 1, n: 'x' });
    }));

  // -------------------------------------------------------------------------
  // 3. CRUD scenarios
  // -------------------------------------------------------------------------
  function crudModel(body) {
    return withModel({
      id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true },
      name: DataTypes.STRING,
      age: DataTypes.INTEGER,
      grp: DataTypes.INTEGER,
    }, {}, body);
  }
  runner.add(FW, 'crud', 'create', () => crudModel(async (M) => {
    const r = await M.create({ name: 'Alice', age: 30, grp: 1 });
    expectTrue(r.id >= 1, 'created with id');
  }));
  runner.add(FW, 'crud', 'findAll', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    const rows = await M.findAll();
    expect(rows.length, 2, 'findAll count');
  }));
  runner.add(FW, 'crud', 'findOne', () => crudModel(async (M) => {
    await M.create({ name: 'a', age: 1, grp: 1 });
    const r = await M.findOne({ where: { name: 'a' } });
    expectTrue(!!r, 'findOne found');
  }));
  runner.add(FW, 'crud', 'findByPk', () => crudModel(async (M) => {
    const c = await M.create({ name: 'a', age: 1, grp: 1 });
    const r = await M.findByPk(c.id);
    expectTrue(!!r, 'findByPk found');
  }));
  runner.add(FW, 'crud', 'findOrCreate', () => crudModel(async (M) => {
    const [r, created] = await M.findOrCreate({ where: { name: 'z' }, defaults: { age: 5, grp: 1 } });
    expectTrue(created, 'findOrCreate created');
  }));
  runner.add(FW, 'crud', 'update-instance', () => crudModel(async (M) => {
    const c = await M.create({ name: 'a', age: 1, grp: 1 });
    c.age = 99; await c.save();
    const r = await M.findByPk(c.id);
    expect(r.age, 99, 'instance update');
  }));
  runner.add(FW, 'crud', 'update-static', () => crudModel(async (M) => {
    await M.create({ name: 'a', age: 1, grp: 1 });
    await M.update({ age: 50 }, { where: { name: 'a' } });
    const r = await M.findOne({ where: { name: 'a' } });
    expect(r.age, 50, 'static update');
  }));
  runner.add(FW, 'crud', 'increment', () => crudModel(async (M) => {
    const c = await M.create({ name: 'a', age: 1, grp: 1 });
    await c.increment('age', { by: 5 });
    const r = await M.findByPk(c.id);
    expect(r.age, 6, 'increment');
  }));
  runner.add(FW, 'crud', 'decrement', () => crudModel(async (M) => {
    const c = await M.create({ name: 'a', age: 10, grp: 1 });
    await c.decrement('age', { by: 3 });
    const r = await M.findByPk(c.id);
    expect(r.age, 7, 'decrement');
  }));
  runner.add(FW, 'crud', 'destroy-instance', () => crudModel(async (M) => {
    const c = await M.create({ name: 'a', age: 1, grp: 1 });
    await c.destroy();
    expect(await M.count(), 0, 'destroyed');
  }));
  runner.add(FW, 'crud', 'destroy-static', () => crudModel(async (M) => {
    await M.create({ name: 'a', age: 1, grp: 1 });
    await M.destroy({ where: { name: 'a' } });
    expect(await M.count(), 0, 'static destroy');
  }));
  runner.add(FW, 'crud', 'bulkCreate', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
    expect(await M.count(), 3, 'bulkCreate count');
  }));
  runner.add(FW, 'crud', 'bulkCreate-returning', () => crudModel(async (M) => {
    const rows = await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expectTrue(rows.length === 2, 'bulkCreate returned rows');
  }));
  runner.add(FW, 'crud', 'upsert', () => crudModel(async (M) => {
    await M.upsert({ id: 1, name: 'a', age: 1, grp: 1 });
    await M.upsert({ id: 1, name: 'a', age: 2, grp: 1 });
    const r = await M.findByPk(1);
    expect(r.age, 2, 'upsert updated');
  }));
  runner.add(FW, 'crud', 'count', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expect(await M.count(), 2, 'count');
  }));
  runner.add(FW, 'crud', 'findAndCountAll', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
    const r = await M.findAndCountAll({ where: { grp: 1 }, limit: 1 });
    expect(r.count, 2, 'findAndCountAll count');
    expect(r.rows.length, 1, 'findAndCountAll rows limited');
  }));
  runner.add(FW, 'crud', 'pagination', () => crudModel(async (M) => {
    await M.bulkCreate(Array.from({ length: 20 }, (_, i) => ({ name: `n${i}`, age: i, grp: 1 })));
    const page = await M.findAll({ limit: 5, offset: 10, order: [['id', 'ASC']] });
    expect(page.length, 5, 'pagination page size');
  }));

  // -------------------------------------------------------------------------
  // 4. Op.* operator matrix
  // -------------------------------------------------------------------------
  const opCases = [
    ['eq', { age: { [Op.eq]: 2 } }],
    ['ne', { age: { [Op.ne]: 2 } }],
    ['gt', { age: { [Op.gt]: 1 } }],
    ['gte', { age: { [Op.gte]: 2 } }],
    ['lt', { age: { [Op.lt]: 3 } }],
    ['lte', { age: { [Op.lte]: 2 } }],
    ['in', { age: { [Op.in]: [1, 2] } }],
    ['notIn', { age: { [Op.notIn]: [3] } }],
    ['like', { name: { [Op.like]: 'a%' } }],
    ['notLike', { name: { [Op.notLike]: 'z%' } }],
    ['startsWith', { name: { [Op.startsWith]: 'a' } }],
    ['endsWith', { name: { [Op.endsWith]: 'a' } }],
    ['substring', { name: { [Op.substring]: 'a' } }],
    ['between', { age: { [Op.between]: [0, 5] } }],
    ['notBetween', { age: { [Op.notBetween]: [10, 20] } }],
    ['is-null', { name: { [Op.is]: null } }],
    ['not-null', { name: { [Op.not]: null } }],
    ['and', { [Op.and]: [{ age: { [Op.gt]: 0 } }, { grp: 1 }] }],
    ['or', { [Op.or]: [{ age: 1 }, { age: 2 }] }],
    ['not', { [Op.not]: { age: 99 } }],
    ['gt-and-lt', { age: { [Op.gt]: 0, [Op.lt]: 10 } }],
    ['regexp', { name: { [Op.regexp]: '^a' } }],
    ['notRegexp', { name: { [Op.notRegexp]: '^z' } }],
  ];
  for (const [nm, where] of opCases) {
    runner.add(FW, 'operator', nm, () => crudModel(async (M) => {
      await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
      await M.findAll({ where });
    }));
  }

  // -------------------------------------------------------------------------
  // 5. AGGREGATES / group / having
  // -------------------------------------------------------------------------
  runner.add(FW, 'aggregate', 'count-fn', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expect(await M.count(), 2, 'aggregate count');
  }));
  runner.add(FW, 'aggregate', 'sum', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expect(Number(await M.sum('age')), 3, 'sum');
  }));
  runner.add(FW, 'aggregate', 'max', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 5, grp: 1 }]);
    expect(Number(await M.max('age')), 5, 'max');
  }));
  runner.add(FW, 'aggregate', 'min', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 5, grp: 1 }]);
    expect(Number(await M.min('age')), 1, 'min');
  }));
  runner.add(FW, 'aggregate', 'group-by', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
    const rows = await M.findAll({
      attributes: ['grp', [Sequelize.fn('COUNT', Sequelize.col('id')), 'n']],
      group: ['grp'],
    });
    expect(rows.length, 2, 'group by produced 2 groups');
  }));
  runner.add(FW, 'aggregate', 'group-having', () => crudModel(async (M) => {
    await M.bulkCreate([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
    const rows = await M.findAll({
      attributes: ['grp', [Sequelize.fn('COUNT', Sequelize.col('id')), 'n']],
      group: ['grp'],
      having: Sequelize.where(Sequelize.fn('COUNT', Sequelize.col('id')), { [Op.gt]: 1 }),
    });
    expect(rows.length, 1, 'group having filtered');
  }));

  // -------------------------------------------------------------------------
  // 6. RAW queries
  // -------------------------------------------------------------------------
  runner.add(FW, 'raw-query', 'select', async () => {
    const [r] = await getSequelize().query('SELECT 1 AS one');
    expect(Number(r[0].one), 1, 'raw select');
  });
  runner.add(FW, 'raw-query', 'select-replacements', async () => {
    const [r] = await getSequelize().query('SELECT :a + :b AS s', { replacements: { a: 2, b: 3 } });
    expect(Number(r[0].s), 5, 'raw replacements');
  });
  runner.add(FW, 'raw-query', 'select-bind', async () => {
    const [r] = await getSequelize().query('SELECT $1 + $2 AS s', { bind: [2, 3] });
    expect(Number(r[0].s), 5, 'raw bind');
  });
  runner.add(FW, 'raw-query', 'select-mapped-model', () => crudModel(async (M) => {
    await M.create({ name: 'a', age: 1, grp: 1 });
    const rows = await getSequelize().query(`SELECT * FROM \`${M.tableName}\``, { model: M, mapToModel: true });
    expectTrue(rows.length === 1, 'mapToModel');
  }));

  // -------------------------------------------------------------------------
  // 7. TRANSACTIONS
  // -------------------------------------------------------------------------
  runner.add(FW, 'transaction', 'managed-commit', () => crudModel(async (M) => {
    await getSequelize().transaction(async (t) => {
      await M.create({ name: 'a', age: 1, grp: 1 }, { transaction: t });
    });
    expect(await M.count(), 1, 'managed commit');
  }));
  runner.add(FW, 'transaction', 'managed-rollback', () => crudModel(async (M) => {
    try {
      await getSequelize().transaction(async (t) => {
        await M.create({ name: 'a', age: 1, grp: 1 }, { transaction: t });
        throw new Error('boom');
      });
    } catch (_) {}
    expect(await M.count(), 0, 'managed rollback undid insert');
  }));
  runner.add(FW, 'transaction', 'unmanaged-commit', () => crudModel(async (M) => {
    const t = await getSequelize().transaction();
    await M.create({ name: 'a', age: 1, grp: 1 }, { transaction: t });
    await t.commit();
    expect(await M.count(), 1, 'unmanaged commit');
  }));
  runner.add(FW, 'transaction', 'unmanaged-rollback', () => crudModel(async (M) => {
    const t = await getSequelize().transaction();
    await M.create({ name: 'a', age: 1, grp: 1 }, { transaction: t });
    await t.rollback();
    expect(await M.count(), 0, 'unmanaged rollback');
  }));
  // Nested transactions -> savepoints -> EXPECTED FAILURE (FINDINGS C5)
  runner.add(FW, 'transaction', 'nested-savepoint-rollback', () => crudModel(async (M) => {
    await getSequelize().transaction(async (t1) => {
      await M.create({ name: 'Outer', age: 1, grp: 1 }, { transaction: t1 });
      try {
        await getSequelize().transaction({ transaction: t1 }, async (t2) => {
          await M.create({ name: 'Inner', age: 2, grp: 1 }, { transaction: t2 });
          throw new Error('inner boom');
        });
      } catch (_) {}
    });
    const cnt = await M.count();
    if (cnt !== 1) throw new BehaviorMismatch(`nested savepoint: expected 1 (Outer) got ${cnt} (Inner leaked)`);
  }));

  // -------------------------------------------------------------------------
  // 8. SCOPES
  // -------------------------------------------------------------------------
  runner.add(FW, 'scope', 'default-scope', async () => {
    const s = getSequelize();
    const name = uniq('Scoped');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, active: DataTypes.BOOLEAN },
      { tableName: name, timestamps: false, defaultScope: { where: { active: true } } });
    try {
      await M.sync({ force: true });
      await M.create({ active: true }); await M.create({ active: false });
      const rows = await M.findAll();
      expect(rows.length, 1, 'default scope filters inactive');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'scope', 'named-scope', async () => {
    const s = getSequelize();
    const name = uniq('Scoped');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, age: DataTypes.INTEGER },
      { tableName: name, timestamps: false, scopes: { adults: { where: { age: { [Op.gte]: 18 } } } } });
    try {
      await M.sync({ force: true });
      await M.create({ age: 10 }); await M.create({ age: 20 });
      const rows = await M.scope('adults').findAll();
      expect(rows.length, 1, 'named scope');
    } finally { try { await M.drop(); } catch (_) {} }
  });

  // -------------------------------------------------------------------------
  // 9. PARANOID (soft delete)
  // -------------------------------------------------------------------------
  runner.add(FW, 'paranoid', 'soft-delete', async () => {
    const s = getSequelize();
    const name = uniq('Para');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING },
      { tableName: name, timestamps: true, paranoid: true });
    try {
      await M.sync({ force: true });
      const c = await M.create({ n: 'a' });
      await c.destroy();
      expect(await M.count(), 0, 'soft-deleted excluded from count');
      expect(await M.count({ paranoid: false }), 1, 'soft-deleted still present');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'paranoid', 'restore', async () => {
    const s = getSequelize();
    const name = uniq('Para');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING },
      { tableName: name, timestamps: true, paranoid: true });
    try {
      await M.sync({ force: true });
      const c = await M.create({ n: 'a' });
      await c.destroy();
      await c.restore();
      expect(await M.count(), 1, 'restored');
    } finally { try { await M.drop(); } catch (_) {} }
  });

  // -------------------------------------------------------------------------
  // 10. HOOKS
  // -------------------------------------------------------------------------
  runner.add(FW, 'hook', 'beforeCreate', async () => {
    const s = getSequelize();
    const name = uniq('Hook');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING },
      { tableName: name, timestamps: false });
    M.beforeCreate((inst) => { inst.n = (inst.n || '') + '!'; });
    try {
      await M.sync({ force: true });
      const c = await M.create({ n: 'a' });
      expect(c.n, 'a!', 'beforeCreate ran');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'hook', 'afterCreate', async () => {
    const s = getSequelize();
    const name = uniq('Hook');
    let fired = false;
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING },
      { tableName: name, timestamps: false });
    M.afterCreate(() => { fired = true; });
    try {
      await M.sync({ force: true });
      await M.create({ n: 'a' });
      expectTrue(fired, 'afterCreate fired');
    } finally { try { await M.drop(); } catch (_) {} }
  });
  runner.add(FW, 'hook', 'beforeUpdate', async () => {
    const s = getSequelize();
    const name = uniq('Hook');
    const M = s.define(name, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, n: DataTypes.STRING },
      { tableName: name, timestamps: false });
    M.beforeUpdate((inst) => { inst.n = 'updated'; });
    try {
      await M.sync({ force: true });
      const c = await M.create({ n: 'a' });
      c.n = 'b'; await c.save();
      expect(c.n, 'updated', 'beforeUpdate ran');
    } finally { try { await M.drop(); } catch (_) {} }
  });

  // -------------------------------------------------------------------------
  // 11. JSON column queries (expect gaps)
  // -------------------------------------------------------------------------
  function jsonModel(body) {
    return withModel({ id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, doc: DataTypes.JSON }, {}, body);
  }
  runner.add(FW, 'json', 'store-and-read', () => jsonModel(async (M) => {
    await M.create({ doc: { a: 1, b: [1, 2, 3] } });
    const r = await M.findOne();
    expect(r.doc.a, 1, 'json stored');
  }));
  runner.add(FW, 'json', 'where-nested-path', () => jsonModel(async (M) => {
    await M.create({ doc: { a: 1 } });
    await M.create({ doc: { a: 2 } });
    const rows = await M.findAll({ where: { 'doc.a': 2 } });
    expect(rows.length, 1, 'where on json path');
  }));
  runner.add(FW, 'json', 'where-op-on-path', () => jsonModel(async (M) => {
    await M.create({ doc: { a: 1 } });
    await M.create({ doc: { a: 5 } });
    const rows = await M.findAll({ where: { 'doc.a': { [Op.gt]: 3 } } });
    expect(rows.length, 1, 'json path op');
  }));
  runner.add(FW, 'json', 'json-contains', () => jsonModel(async (M) => {
    await M.create({ doc: { tags: ['x', 'y'] } });
    const rows = await M.findAll({ where: Sequelize.where(Sequelize.fn('JSON_CONTAINS', Sequelize.col('doc'), '"x"', '$.tags'), 1) });
    expectTrue(rows.length >= 0, 'json_contains ran');
  }));
  runner.add(FW, 'json', 'json-extract-attr', () => jsonModel(async (M) => {
    await M.create({ doc: { a: 7 } });
    const rows = await M.findAll({ attributes: [[Sequelize.fn('JSON_EXTRACT', Sequelize.col('doc'), '$.a'), 'av']] });
    expectTrue(rows.length === 1, 'json_extract attr');
  }));

  // -------------------------------------------------------------------------
  // 12. ASSOCIATIONS
  // -------------------------------------------------------------------------
  registerAssociations(runner, FW);
}

// Association scenarios get their own define/sync lifecycle per test.
function registerAssociations(runner, FW) {
  // hasOne / belongsTo (User hasOne Profile, aliased so mixin names are stable)
  runner.add(FW, 'assoc/hasOne', 'create-and-fetch', () => assocHasOne(async (User, Profile, u) => {
    const p = await Profile.create({ bio: 'hi', userId: u.id });
    const fetched = await u.getProfile();
    expectTrue(fetched && fetched.id === p.id, 'hasOne getter');
  }));
  runner.add(FW, 'assoc/hasOne', 'eager-include', () => assocHasOne(async (User, Profile, u) => {
    await Profile.create({ bio: 'hi', userId: u.id });
    const full = await User.findByPk(u.id, { include: { model: Profile, as: 'Profile' } });
    expectTrue(full.Profile && full.Profile.bio === 'hi', 'hasOne eager include');
  }));
  runner.add(FW, 'assoc/belongsTo', 'fetch-parent', () => assocHasOne(async (User, Profile, u) => {
    const p = await Profile.create({ bio: 'hi', userId: u.id });
    const owner = await p.getUser();
    expectTrue(owner && owner.id === u.id, 'belongsTo getter');
  }));

  // hasMany / belongsTo (User hasMany Post)
  runner.add(FW, 'assoc/hasMany', 'create-and-count', () => assocHasMany(async (User, Post, u) => {
    await Post.bulkCreate([{ title: 'p1', userId: u.id }, { title: 'p2', userId: u.id }]);
    const posts = await u.getPosts();
    expect(posts.length, 2, 'hasMany getter');
  }));
  runner.add(FW, 'assoc/hasMany', 'eager-include', () => assocHasMany(async (User, Post, u) => {
    await Post.bulkCreate([{ title: 'p1', userId: u.id }, { title: 'p2', userId: u.id }]);
    const full = await User.findByPk(u.id, { include: { model: Post, as: 'Posts' } });
    expect(full.Posts.length, 2, 'hasMany eager include');
  }));
  runner.add(FW, 'assoc/hasMany', 'nested-where-include', () => assocHasMany(async (User, Post, u) => {
    await Post.bulkCreate([{ title: 'p1', userId: u.id }, { title: 'zz', userId: u.id }]);
    const full = await User.findByPk(u.id, { include: [{ model: Post, as: 'Posts', where: { title: { [Op.like]: 'p%' } }, required: false }] });
    expectTrue(!!full, 'nested where include ran');
  }));
  runner.add(FW, 'assoc/hasMany', 'count-via-include', () => assocHasMany(async (User, Post, u) => {
    await Post.bulkCreate([{ title: 'p1', userId: u.id }]);
    const rows = await User.findAll({
      attributes: ['id', [Sequelize.fn('COUNT', Sequelize.col('Posts.id')), 'n']],
      include: [{ model: Post, as: 'Posts', attributes: [] }],
      group: [`${User.name}.id`],
    });
    expectTrue(rows.length >= 1, 'count via include');
  }));

  // belongsToMany (User <-> Role through UserRole)
  runner.add(FW, 'assoc/belongsToMany', 'add-and-fetch', () => assocM2M(async (User, Role, UserRole, u) => {
    const r = await Role.create({ name: 'admin' });
    await u.addRole(r);
    const roles = await u.getRoles();
    expect(roles.length, 1, 'm2m add+get');
  }));
  runner.add(FW, 'assoc/belongsToMany', 'eager-include', () => assocM2M(async (User, Role, UserRole, u) => {
    const r = await Role.create({ name: 'admin' });
    await u.addRole(r);
    const full = await User.findByPk(u.id, { include: { model: Role, as: 'Roles' } });
    expect(full.Roles.length, 1, 'm2m eager include');
  }));
  runner.add(FW, 'assoc/belongsToMany', 'remove', () => assocM2M(async (User, Role, UserRole, u) => {
    const r = await Role.create({ name: 'admin' });
    await u.addRole(r);
    await u.removeRole(r);
    const roles = await u.getRoles();
    expect(roles.length, 0, 'm2m remove');
  }));
}

// ---- association fixtures ----
async function assocHasOne(body) {
  const s = getSequelize();
  const un = uniq('U'); const pn = uniq('P');
  const User = s.define(un, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, name: DataTypes.STRING }, { tableName: un, timestamps: false });
  const Profile = s.define(pn, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, bio: DataTypes.STRING, userId: DataTypes.INTEGER }, { tableName: pn, timestamps: false });
  User.hasOne(Profile, { foreignKey: 'userId', as: 'Profile' });
  Profile.belongsTo(User, { foreignKey: 'userId', as: 'User' });
  try {
    await User.sync({ force: true }); await Profile.sync({ force: true });
    const u = await User.create({ name: 'u1' });
    return await body(User, Profile, u);
  } finally {
    try { await Profile.drop(); } catch (_) {}
    try { await User.drop(); } catch (_) {}
  }
}
async function assocHasMany(body) {
  const s = getSequelize();
  const un = uniq('U'); const pn = uniq('Post');
  const User = s.define(un, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, name: DataTypes.STRING }, { tableName: un, timestamps: false });
  const Post = s.define(pn, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, title: DataTypes.STRING, userId: DataTypes.INTEGER }, { tableName: pn, timestamps: false });
  User.hasMany(Post, { foreignKey: 'userId', as: 'Posts' });
  Post.belongsTo(User, { foreignKey: 'userId', as: 'User' });
  try {
    await User.sync({ force: true }); await Post.sync({ force: true });
    const u = await User.create({ name: 'u1' });
    return await body(User, Post, u);
  } finally {
    try { await Post.drop(); } catch (_) {}
    try { await User.drop(); } catch (_) {}
  }
}
async function assocM2M(body) {
  const s = getSequelize();
  const un = uniq('U'); const rn = uniq('R'); const jn = uniq('UR');
  const User = s.define(un, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, name: DataTypes.STRING }, { tableName: un, timestamps: false });
  const Role = s.define(rn, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true }, name: DataTypes.STRING }, { tableName: rn, timestamps: false });
  const UserRole = s.define(jn, { id: { type: DataTypes.INTEGER, primaryKey: true, autoIncrement: true } }, { tableName: jn, timestamps: false });
  User.belongsToMany(Role, { through: UserRole, foreignKey: 'userId', otherKey: 'roleId', as: 'Roles' });
  Role.belongsToMany(User, { through: UserRole, foreignKey: 'roleId', otherKey: 'userId', as: 'Users' });
  try {
    await User.sync({ force: true }); await Role.sync({ force: true }); await UserRole.sync({ force: true });
    const u = await User.create({ name: 'u1' });
    return await body(User, Role, UserRole, u);
  } finally {
    try { await UserRole.drop(); } catch (_) {}
    try { await Role.drop(); } catch (_) {}
    try { await User.drop(); } catch (_) {}
  }
}

async function teardown() {
  if (sequelize) { await sequelize.close(); sequelize = null; }
}

module.exports = { register, teardown };
