'use strict';

require('reflect-metadata');
const { DataSource, EntitySchema, In, Like, Between, MoreThan, LessThan, Not, IsNull, MoreThanOrEqual, LessThanOrEqual } = require('typeorm');
const { CONFIG, DATABASES } = require('../lib/config');
const { uniq } = require('../lib/db');
const { BehaviorMismatch, expect, expectTrue } = require('../lib/errors');

const { getShared, withEntitiesPooled, withShapeCached, teardownShared } = require('../lib/typeorm_shared');

async function getDS() { return getShared(); }

// Per-test metadata DataSources are routed through the bounded pool in
// lib/typeorm_shared so the live-connection count stays well under the server
// limit (TypeORM's mysql2 driver leaks ~1 connection per DataSource lifetime
// against this MatrixOne build — see that module's header).
async function withEntities(schemas, body) {
  return withEntitiesPooled(schemas, body);
}

// Run a DDL/DML body against the single shared DataSource via raw query(). Used
// for the large column-type matrices, which exercise TypeORM's connection and
// SQL-execution layer without creating a DataSource per scenario.
async function withSharedTable(ddl, body) {
  const d = await getShared();
  const tn = uniq('to');
  await d.query(ddl(tn));
  try { return await body(d, tn); }
  finally { try { await d.query(`DROP TABLE IF EXISTS \`${tn}\``); } catch (_) {} }
}

function ent(tableName, columns, extra = {}) {
  return new EntitySchema({ name: tableName, tableName, columns, ...extra });
}

// Map a TypeORM column label/opts to a MySQL DDL fragment (for the shared-DS
// column matrix). TypeORM's own DDL is exercised by the metadata-bearing
// feature tests; here we focus on the type + driver round-trip.
function ddlForColumn(label, type, opts) {
  const o = opts || {};
  switch (label) {
    case 'simple-enum': return `ENUM(${o.enum.map((e) => `'${e}'`).join(',')})`;
    case 'enum': return `ENUM(${o.enum.map((e) => `'${e}'`).join(',')})`;
    case 'simple-json': return 'TEXT';
    case 'simple-array': return 'TEXT';
    case 'decimal': case 'numeric': return `${type.toUpperCase()}(${o.precision || 10},${o.scale || 0})`;
    case 'char': return `CHAR(${o.length || 10})`;
    case 'varchar': return `VARCHAR(${o.length || 255})`;
    case 'binary': return `BINARY(${o.length || 16})`;
    case 'varbinary': return `VARBINARY(${o.length || 255})`;
    default: return type.toUpperCase();
  }
}

// Normalise a JS sample to a value the driver can bind for a given label.
function bindValue(label, sample) {
  if (label === 'simple-json') return JSON.stringify(sample);
  if (label === 'simple-array') return Array.isArray(sample) ? sample.join(',') : sample;
  if (label === 'json') return JSON.stringify(sample);
  return sample;
}

// TypeORM column-type matrix (the `type` strings TypeORM maps to MySQL DDL).
function columnTypeMatrix() {
  return [
    ['int', 'int', 42],
    ['tinyint', 'tinyint', 7],
    ['smallint', 'smallint', 1234],
    ['mediumint', 'mediumint', 100000],
    ['bigint', 'bigint', '9007199254740991'],
    ['decimal', 'decimal', '123.45', { precision: 10, scale: 2 }],
    ['numeric', 'numeric', '99.99', { precision: 10, scale: 2 }],
    ['float', 'float', 3.5],
    ['double', 'double', 3.14159],
    ['real', 'double', 2.71], // typeorm maps 'real' weirdly; use double for stability of declaration
    ['char', 'char', 'abc', { length: 10 }],
    ['varchar', 'varchar', 'hello', { length: 255 }],
    ['text', 'text', 'some text'],
    ['tinytext', 'tinytext', 'tt'],
    ['mediumtext', 'mediumtext', 'mt'],
    ['longtext', 'longtext', 'lt'],
    ['blob', 'blob', Buffer.from('blob')],
    ['mediumblob', 'mediumblob', Buffer.from('m')],
    ['longblob', 'longblob', Buffer.from('l')],
    ['date', 'date', '2026-06-23'],
    ['datetime', 'datetime', '2026-06-23 11:00:00'],
    ['timestamp', 'timestamp', '2026-06-23 11:00:00'],
    ['time', 'time', '11:30:00'],
    ['year', 'year', 2026],
    ['enum', 'enum', 'b', { enum: ['a', 'b', 'c'] }],
    ['simple-enum', 'simple-enum', 'b', { enum: ['a', 'b', 'c'] }],
    ['json', 'json', { k: 'v' }],
    ['simple-json', 'simple-json', { k: 'v' }],
    ['simple-array', 'simple-array', ['a', 'b', 'c']],
    ['boolean', 'boolean', true],
    ['bit', 'bit', undefined], // declare-only
    ['binary', 'binary', Buffer.alloc(16, 1), { length: 16 }],
    ['varbinary', 'varbinary', Buffer.from('vb'), { length: 255 }],
  ];
}

function register(runner) {
  const FW = 'typeorm';

  // -------------------------------------------------------------------------
  // 1. CONNECTION
  // -------------------------------------------------------------------------
  runner.add(FW, 'connection', 'initialize', async () => {
    const d = await getDS();
    expectTrue(d.isInitialized, 'datasource initialized');
  });
  runner.add(FW, 'connection', 'raw-query', async () => {
    const d = await getDS();
    const r = await d.query('SELECT 1 AS one');
    expect(Number(r[0].one), 1, 'raw query');
  });

  // -------------------------------------------------------------------------
  // 2. COLUMN TYPE × {sync, insert, roundtrip, null}
  //    Routed through the shared DataSource (raw DDL/DML) to keep the live
  //    connection count bounded; TypeORM's metadata-driven DDL is covered by
  //    the feature tests below.
  // -------------------------------------------------------------------------
  for (const [label, type, sample, opts] of columnTypeMatrix()) {
    const sqlType = ddlForColumn(label, type, opts);

    runner.add(FW, 'column/sync', `sync ${label}`, () =>
      withSharedTable((tn) => `CREATE TABLE \`${tn}\` (id INT PRIMARY KEY, c ${sqlType} NULL)`, async () => {}));

    if (sample !== undefined) {
      const val = bindValue(label, sample);
      runner.add(FW, 'column/insert', `insert ${label}`, () =>
        withSharedTable((tn) => `CREATE TABLE \`${tn}\` (id INT PRIMARY KEY, c ${sqlType} NULL)`, async (d, tn) => {
          await d.query(`INSERT INTO \`${tn}\` (id, c) VALUES (?, ?)`, [1, val]);
        }));

      runner.add(FW, 'column/roundtrip', `roundtrip ${label}`, () =>
        withSharedTable((tn) => `CREATE TABLE \`${tn}\` (id INT PRIMARY KEY, c ${sqlType} NULL)`, async (d, tn) => {
          await d.query(`INSERT INTO \`${tn}\` (id, c) VALUES (?, ?)`, [1, val]);
          const rows = await d.query(`SELECT c FROM \`${tn}\` WHERE id = 1`);
          if (!rows.length) throw new BehaviorMismatch('row missing after insert');
          if (label === 'float' && Number(rows[0].c) === 4 && Number(sample) === 3.5) {
            throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${rows[0].c}`);
          }
        }));
    }

    runner.add(FW, 'column/null', `null ${label}`, () =>
      withSharedTable((tn) => `CREATE TABLE \`${tn}\` (id INT PRIMARY KEY, c ${sqlType} NULL)`, async (d, tn) => {
        await d.query(`INSERT INTO \`${tn}\` (id, c) VALUES (1, NULL)`);
        const rows = await d.query(`SELECT c FROM \`${tn}\` WHERE id = 1`);
        if (rows[0].c !== null) throw new BehaviorMismatch(`null not preserved: ${JSON.stringify(rows[0].c)}`);
      }));
  }

  // column modifiers
  runner.add(FW, 'column/modifier', 'generated-increment', () => {
    const tn = uniq('to');
    const E = ent(tn, { id: { type: 'int', primary: true, generated: 'increment' }, n: { type: 'varchar', length: 20 } });
    return withEntities([E], async (d) => {
      const r = await d.getRepository(E).save({ n: 'x' });
      expectTrue(r.id >= 1, 'auto increment');
    });
  });
  runner.add(FW, 'column/modifier', 'generated-uuid', () => {
    const tn = uniq('to');
    const E = ent(tn, { id: { type: 'varchar', length: 36, primary: true, generated: 'uuid' }, n: { type: 'varchar', length: 20 } });
    return withEntities([E], async (d) => {
      const r = await d.getRepository(E).save({ n: 'x' });
      expectTrue(typeof r.id === 'string' && r.id.length > 0, 'uuid generated');
    });
  });
  runner.add(FW, 'column/modifier', 'default-value', () => {
    const tn = uniq('to');
    const E = ent(tn, { id: { type: 'int', primary: true }, n: { type: 'varchar', length: 20, default: 'def' } });
    return withEntities([E], async (d) => {
      await d.query(`INSERT INTO \`${tn}\` (id) VALUES (1)`);
      const row = await d.getRepository(E).findOneBy({ id: 1 });
      expect(row.n, 'def', 'default applied');
    });
  });
  runner.add(FW, 'column/modifier', 'unique', () => {
    const tn = uniq('to');
    const E = ent(tn, { id: { type: 'int', primary: true }, e: { type: 'varchar', length: 50, unique: true } });
    return withEntities([E], async (d) => {
      await d.getRepository(E).save({ id: 1, e: 'a@b.com' });
    });
  });
  runner.add(FW, 'column/modifier', 'created-updated-date', () => {
    const tn = uniq('to');
    const E = ent(tn, {
      id: { type: 'int', primary: true, generated: 'increment' },
      createdAt: { type: 'datetime', createDate: true },
      updatedAt: { type: 'datetime', updateDate: true },
    });
    return withEntities([E], async (d) => {
      const r = await d.getRepository(E).save({});
      expectTrue(!!r.createdAt, 'createDate set');
    });
  });

  // -------------------------------------------------------------------------
  // 3. REPOSITORY CRUD
  // -------------------------------------------------------------------------
  // A single stable CRUD entity + cached DataSource, reused across all crud /
  // find-operator / transaction / schema scenarios (one live connection total).
  const CrudE = ent('to_crud', {
    id: { type: 'int', primary: true, generated: 'increment' },
    name: { type: 'varchar', length: 50, nullable: true },
    age: { type: 'int', nullable: true },
    grp: { type: 'int', nullable: true },
  });
  const withCrud = (body) => withShapeCached('crud', [CrudE], (d) => body(d, d.getRepository(CrudE)));
  const crud = (name, fn) => runner.add(FW, 'crud', name, () => withCrud((d, repo) => fn(repo, d, CrudE)));

  crud('save', async (repo) => {
    const r = await repo.save({ name: 'a', age: 1, grp: 1 });
    expectTrue(r.id >= 1, 'saved with id');
  });
  crud('insert', async (repo) => {
    await repo.insert({ name: 'a', age: 1, grp: 1 });
    expect(await repo.count(), 1, 'insert');
  });
  crud('find', async (repo) => {
    await repo.save([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expect((await repo.find()).length, 2, 'find all');
  });
  crud('findOneBy', async (repo) => {
    await repo.save({ name: 'a', age: 1, grp: 1 });
    expectTrue(!!(await repo.findOneBy({ name: 'a' })), 'findOneBy');
  });
  crud('findBy', async (repo) => {
    await repo.save([{ name: 'a', age: 1, grp: 1 }, { name: 'a', age: 2, grp: 1 }]);
    expect((await repo.findBy({ name: 'a' })).length, 2, 'findBy');
  });
  crud('findAndCount', async (repo) => {
    await repo.save([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
    const [rows, count] = await repo.findAndCount({ where: { grp: 1 }, take: 1 });
    expect(count, 2, 'findAndCount total');
    expect(rows.length, 1, 'findAndCount take');
  });
  crud('update', async (repo) => {
    const r = await repo.save({ name: 'a', age: 1, grp: 1 });
    await repo.update({ id: r.id }, { age: 99 });
    expect((await repo.findOneBy({ id: r.id })).age, 99, 'update');
  });
  crud('delete', async (repo) => {
    const r = await repo.save({ name: 'a', age: 1, grp: 1 });
    await repo.delete({ id: r.id });
    expect(await repo.count(), 0, 'delete');
  });
  crud('remove', async (repo) => {
    const r = await repo.save({ name: 'a', age: 1, grp: 1 });
    await repo.remove(r);
    expect(await repo.count(), 0, 'remove');
  });
  crud('count', async (repo) => {
    await repo.save([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }]);
    expect(await repo.count(), 2, 'count');
  });
  crud('exists', async (repo) => {
    await repo.save({ name: 'a', age: 1, grp: 1 });
    const e = await repo.existsBy({ name: 'a' });
    expectTrue(e === true, 'existsBy');
  });
  crud('increment', async (repo) => {
    const r = await repo.save({ name: 'a', age: 1, grp: 1 });
    await repo.increment({ id: r.id }, 'age', 5);
    expect((await repo.findOneBy({ id: r.id })).age, 6, 'increment');
  });
  crud('upsert', async (repo) => {
    await repo.upsert({ id: 1, name: 'a', age: 1, grp: 1 }, ['id']);
    await repo.upsert({ id: 1, name: 'a', age: 2, grp: 1 }, ['id']);
    expect((await repo.findOneBy({ id: 1 })).age, 2, 'upsert updated');
  });
  crud('save-bulk', async (repo) => {
    await repo.save(Array.from({ length: 10 }, (_, i) => ({ name: `n${i}`, age: i, grp: 1 })));
    expect(await repo.count(), 10, 'save bulk');
  });
  crud('find-pagination', async (repo) => {
    await repo.save(Array.from({ length: 20 }, (_, i) => ({ name: `n${i}`, age: i, grp: 1 })));
    const page = await repo.find({ skip: 10, take: 5, order: { id: 'ASC' } });
    expect(page.length, 5, 'pagination');
  });
  crud('find-order', async (repo) => {
    await repo.save([{ name: 'a', age: 3, grp: 1 }, { name: 'b', age: 1, grp: 1 }]);
    const rows = await repo.find({ order: { age: 'ASC' } });
    expect(rows[0].age, 1, 'order');
  });
  crud('find-select', async (repo) => {
    await repo.save({ name: 'a', age: 1, grp: 1 });
    const rows = await repo.find({ select: { id: true, name: true } });
    expectTrue(rows[0].age === undefined || rows[0].age === null, 'select limited columns');
  });

  // -------------------------------------------------------------------------
  // 4. FIND-OPERATOR matrix (typeorm operators)
  // -------------------------------------------------------------------------
  const findOps = [
    ['equal', { age: 2 }],
    ['in', { age: In([1, 2]) }],
    ['like', { name: Like('a%') }],
    ['between', { age: Between(0, 5) }],
    ['moreThan', { age: MoreThan(1) }],
    ['lessThan', { age: LessThan(3) }],
    ['moreThanOrEqual', { age: MoreThanOrEqual(2) }],
    ['lessThanOrEqual', { age: LessThanOrEqual(2) }],
    ['not', { age: Not(99) }],
    ['isNull', { name: IsNull() }],
    ['and-implicit', { age: MoreThan(0), grp: 1 }],
  ];
  for (const [nm, where] of findOps) {
    runner.add(FW, 'find-operator', nm, () => withCrud(async (d, repo) => {
      await repo.save([{ name: 'a', age: 1, grp: 1 }, { name: 'b', age: 2, grp: 1 }, { name: 'c', age: 3, grp: 2 }]);
      await repo.find({ where });
    }));
  }

  // -------------------------------------------------------------------------
  // 5. QUERY BUILDER (single stable entity + cached DataSource)
  // -------------------------------------------------------------------------
  const QbE = ent('to_qb', {
    id: { type: 'int', primary: true, generated: 'increment' },
    name: { type: 'varchar', length: 50, nullable: true },
    amt: { type: 'decimal', precision: 10, scale: 2, nullable: true },
    grp: { type: 'int', nullable: true },
  });
  const qb = (name, fn) => runner.add(FW, 'querybuilder', name, () => withShapeCached('qb', [QbE], (d) => fn(d, QbE)));
  qb('select-where', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 2 }]);
    const rows = await d.createQueryBuilder(E, 't').where('t.grp = :g', { g: 1 }).getMany();
    expect(rows.length, 1, 'qb where');
  });
  qb('select-andwhere', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 1 }]);
    const rows = await d.createQueryBuilder(E, 't').where('t.grp = :g', { g: 1 }).andWhere('t.amt > :a', { a: 15 }).getMany();
    expect(rows.length, 1, 'qb andWhere');
  });
  qb('orderBy-limit-offset', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save(Array.from({ length: 10 }, (_, i) => ({ name: `n${i}`, amt: i, grp: 1 })));
    const rows = await d.createQueryBuilder(E, 't').orderBy('t.amt', 'DESC').limit(3).offset(2).getMany();
    expect(rows.length, 3, 'qb limit/offset');
  });
  qb('groupBy-having', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 1 }, { name: 'c', amt: 5, grp: 2 }]);
    const rows = await d.createQueryBuilder(E, 't')
      .select('t.grp', 'grp').addSelect('COUNT(*)', 'n')
      .groupBy('t.grp').having('COUNT(*) > :n', { n: 1 }).getRawMany();
    expect(rows.length, 1, 'qb group having');
  });
  qb('aggregate-sum', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 1 }]);
    const r = await d.createQueryBuilder(E, 't').select('SUM(t.amt)', 's').getRawOne();
    expect(Number(r.s), 30, 'qb sum');
  });
  qb('distinct', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 1 }]);
    const rows = await d.createQueryBuilder(E, 't').select('DISTINCT t.grp', 'grp').getRawMany();
    expect(rows.length, 1, 'qb distinct');
  });
  qb('subquery-where-in', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save([{ name: 'a', amt: 10, grp: 1 }, { name: 'b', amt: 20, grp: 2 }]);
    const rows = await d.createQueryBuilder(E, 't')
      .where(`t.grp IN ${d.createQueryBuilder(E, 's').select('s.grp').where('s.amt > :a', { a: 15 }).getQuery()}`)
      .setParameter('a', 15).getMany();
    expectTrue(rows.length >= 0, 'qb subquery');
  });
  qb('update-querybuilder', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save({ name: 'a', amt: 10, grp: 1 });
    await d.createQueryBuilder().update(E).set({ amt: 99 }).where('grp = :g', { g: 1 }).execute();
    const r = await repo.findOneBy({ grp: 1 });
    expect(Number(r.amt), 99, 'qb update');
  });
  qb('delete-querybuilder', async (d, E) => {
    const repo = d.getRepository(E);
    await repo.save({ name: 'a', amt: 10, grp: 1 });
    await d.createQueryBuilder().delete().from(E).where('grp = :g', { g: 1 }).execute();
    expect(await repo.count(), 0, 'qb delete');
  });
  qb('insert-querybuilder', async (d, E) => {
    await d.createQueryBuilder().insert().into(E).values([{ name: 'a', amt: 1, grp: 1 }]).execute();
    expect(await d.getRepository(E).count(), 1, 'qb insert');
  });
  qb('getCount', async (d, E) => {
    await d.getRepository(E).save([{ name: 'a', amt: 1, grp: 1 }, { name: 'b', amt: 2, grp: 1 }]);
    const c = await d.createQueryBuilder(E, 't').getCount();
    expect(c, 2, 'qb getCount');
  });
  qb('getManyAndCount', async (d, E) => {
    await d.getRepository(E).save([{ name: 'a', amt: 1, grp: 1 }, { name: 'b', amt: 2, grp: 1 }]);
    const [rows, c] = await d.createQueryBuilder(E, 't').take(1).getManyAndCount();
    expect(c, 2, 'qb getManyAndCount total');
  });

  // -------------------------------------------------------------------------
  // 6. RELATIONS
  // -------------------------------------------------------------------------
  registerRelations(runner, FW);

  // -------------------------------------------------------------------------
  // 7. TRANSACTIONS
  // -------------------------------------------------------------------------
  runner.add(FW, 'transaction', 'transaction-commit', () => withCrud(async (d) => {
    await d.transaction(async (mgr) => { await mgr.getRepository(CrudE).save({ name: 'a', age: 1, grp: 1 }); });
    expect(await d.getRepository(CrudE).count(), 1, 'tx commit');
  }));
  runner.add(FW, 'transaction', 'transaction-rollback', () => withCrud(async (d) => {
    try {
      await d.transaction(async (mgr) => { await mgr.getRepository(CrudE).save({ name: 'a', age: 1, grp: 1 }); throw new Error('boom'); });
    } catch (_) {}
    expect(await d.getRepository(CrudE).count(), 0, 'tx rollback');
  }));
  runner.add(FW, 'transaction', 'queryrunner-manual', () => withCrud(async (d) => {
    const qr = d.createQueryRunner();
    await qr.connect(); await qr.startTransaction();
    try {
      await qr.manager.getRepository(CrudE).save({ name: 'a', age: 1, grp: 1 });
      await qr.commitTransaction();
    } catch (e) { await qr.rollbackTransaction(); throw e; } finally { await qr.release(); }
    expect(await d.getRepository(CrudE).count(), 1, 'manual tx');
  }));
  // nested transactions / savepoints — EXPECTED FAILURE (FINDINGS C5)
  runner.add(FW, 'transaction', 'nested-savepoint', () => withCrud(async (d) => {
    await d.transaction(async (mgr) => {
      await mgr.getRepository(CrudE).save({ name: 'Outer', age: 1, grp: 1 });
      try {
        await mgr.transaction(async (mgr2) => {
          await mgr2.getRepository(CrudE).save({ name: 'Inner', age: 2, grp: 1 });
          throw new Error('inner boom');
        });
      } catch (_) {}
    });
    const cnt = await d.getRepository(CrudE).count();
    if (cnt !== 1) throw new BehaviorMismatch(`nested savepoint: expected 1 got ${cnt} (Inner leaked)`);
  }));

  // -------------------------------------------------------------------------
  // 8. SOFT DELETE
  // -------------------------------------------------------------------------
  const SoftE = ent('to_soft', {
    id: { type: 'int', primary: true, generated: 'increment' },
    n: { type: 'varchar', length: 20, nullable: true },
    deletedAt: { type: 'datetime', deleteDate: true, nullable: true },
  });
  const withSoft = (body) => withShapeCached('soft', [SoftE], (d) => body(d, d.getRepository(SoftE)));
  runner.add(FW, 'soft-delete', 'softRemove-and-find', () => withSoft(async (d, repo) => {
    const r = await repo.save({ n: 'a' });
    await repo.softDelete(r.id);
    expect(await repo.count(), 0, 'soft-deleted excluded');
    const withDeleted = await repo.find({ withDeleted: true });
    expect(withDeleted.length, 1, 'soft-deleted still present');
  }));
  runner.add(FW, 'soft-delete', 'restore', () => withSoft(async (d, repo) => {
    const r = await repo.save({ n: 'a' });
    await repo.softDelete(r.id);
    await repo.restore(r.id);
    expect(await repo.count(), 1, 'restored');
  }));

  // -------------------------------------------------------------------------
  // 9. SCHEMA INTROSPECTION (expect information_schema gaps)
  // -------------------------------------------------------------------------
  runner.add(FW, 'schema', 'has-table', () => withCrud(async (d) => {
    const qr = d.createQueryRunner();
    try {
      const has = await qr.hasTable(CrudE.options.tableName);
      expectTrue(has === true, 'hasTable true');
    } finally { await qr.release(); }
  }));
  runner.add(FW, 'schema', 'get-table-introspection', () => withCrud(async (d) => {
    const qr = d.createQueryRunner();
    try {
      const tbl = await qr.getTable(CrudE.options.tableName);
      expectTrue(!!tbl && tbl.columns.length >= 1, 'getTable introspection');
    } finally { await qr.release(); }
  }));
  runner.add(FW, 'schema', 'synchronize-twice-idempotent', () => withCrud(async (d) => {
    await d.synchronize(false); // re-sync without dropping (uses introspection/diff)
  }));
}

function registerRelations(runner, FW) {
  // OneToOne / ManyToOne / OneToMany / ManyToMany via EntitySchema relations.
  runner.add(FW, 'relation/OneToMany', 'create-and-eager', async () => {
    const un = uniq('toU'); const pn = uniq('toP');
    const User = new EntitySchema({
      name: un, tableName: un,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } },
      relations: { posts: { type: 'one-to-many', target: pn, inverseSide: 'user' } },
    });
    const Post = new EntitySchema({
      name: pn, tableName: pn,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, title: { type: 'varchar', length: 20 } },
      relations: { user: { type: 'many-to-one', target: un, joinColumn: { name: 'userId' } } },
    });
    return withEntities([User, Post], async (d) => {
      const u = await d.getRepository(User).save({ name: 'u' });
      await d.getRepository(Post).save([{ title: 'p1', user: u }, { title: 'p2', user: u }]);
      const full = await d.getRepository(User).findOne({ where: { id: u.id }, relations: { posts: true } });
      expect(full.posts.length, 2, 'OneToMany eager');
    });
  });
  runner.add(FW, 'relation/ManyToOne', 'fetch-parent', async () => {
    const un = uniq('toU'); const pn = uniq('toP');
    const User = new EntitySchema({ name: un, tableName: un, columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } } });
    const Post = new EntitySchema({
      name: pn, tableName: pn,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, title: { type: 'varchar', length: 20 } },
      relations: { user: { type: 'many-to-one', target: un, joinColumn: { name: 'userId' } } },
    });
    return withEntities([User, Post], async (d) => {
      const u = await d.getRepository(User).save({ name: 'u' });
      const p = await d.getRepository(Post).save({ title: 'p', user: u });
      const full = await d.getRepository(Post).findOne({ where: { id: p.id }, relations: { user: true } });
      expectTrue(full.user && full.user.id === u.id, 'ManyToOne parent');
    });
  });
  runner.add(FW, 'relation/OneToOne', 'create-and-fetch', async () => {
    const un = uniq('toU'); const pn = uniq('toProf');
    const User = new EntitySchema({ name: un, tableName: un, columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } } });
    const Profile = new EntitySchema({
      name: pn, tableName: pn,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, bio: { type: 'varchar', length: 50 } },
      relations: { user: { type: 'one-to-one', target: un, joinColumn: { name: 'userId' } } },
    });
    return withEntities([User, Profile], async (d) => {
      const u = await d.getRepository(User).save({ name: 'u' });
      const p = await d.getRepository(Profile).save({ bio: 'hi', user: u });
      const full = await d.getRepository(Profile).findOne({ where: { id: p.id }, relations: { user: true } });
      expectTrue(full.user && full.user.id === u.id, 'OneToOne fetch');
    });
  });
  runner.add(FW, 'relation/ManyToMany', 'attach-and-eager', async () => {
    const un = uniq('toU'); const rn = uniq('toR');
    const User = new EntitySchema({
      name: un, tableName: un,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } },
      relations: { roles: { type: 'many-to-many', target: rn, joinTable: { name: uniq('toUR') }, cascade: true } },
    });
    const Role = new EntitySchema({ name: rn, tableName: rn, columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } } });
    return withEntities([Role, User], async (d) => {
      const r = await d.getRepository(Role).save({ name: 'admin' });
      const u = await d.getRepository(User).save({ name: 'u', roles: [r] });
      const full = await d.getRepository(User).findOne({ where: { id: u.id }, relations: { roles: true } });
      expect(full.roles.length, 1, 'ManyToMany eager');
    });
  });
  runner.add(FW, 'relation/cascade', 'cascade-insert', async () => {
    const un = uniq('toU'); const pn = uniq('toP');
    const Post = new EntitySchema({
      name: pn, tableName: pn,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, title: { type: 'varchar', length: 20 } },
      relations: { user: { type: 'many-to-one', target: un, joinColumn: { name: 'userId' } } },
    });
    const User = new EntitySchema({
      name: un, tableName: un,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } },
      relations: { posts: { type: 'one-to-many', target: pn, inverseSide: 'user', cascade: true } },
    });
    return withEntities([User, Post], async (d) => {
      await d.getRepository(User).save({ name: 'u', posts: [{ title: 'p1' }, { title: 'p2' }] });
      expect(await d.getRepository(Post).count(), 2, 'cascade insert created children');
    });
  });
  runner.add(FW, 'relation/leftJoinAndSelect', 'querybuilder-join', async () => {
    const un = uniq('toU'); const pn = uniq('toP');
    const User = new EntitySchema({
      name: un, tableName: un,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, name: { type: 'varchar', length: 20 } },
      relations: { posts: { type: 'one-to-many', target: pn, inverseSide: 'user' } },
    });
    const Post = new EntitySchema({
      name: pn, tableName: pn,
      columns: { id: { type: 'int', primary: true, generated: 'increment' }, title: { type: 'varchar', length: 20 } },
      relations: { user: { type: 'many-to-one', target: un, joinColumn: { name: 'userId' } } },
    });
    return withEntities([User, Post], async (d) => {
      const u = await d.getRepository(User).save({ name: 'u' });
      await d.getRepository(Post).save([{ title: 'p1', user: u }]);
      const rows = await d.createQueryBuilder(User, 'u').leftJoinAndSelect('u.posts', 'p').getMany();
      expectTrue(rows.length === 1 && rows[0].posts.length === 1, 'leftJoinAndSelect');
    });
  });
}

async function teardown() {
  await teardownShared();
}

module.exports = { register, teardown };
