'use strict';

require('reflect-metadata');
const { DataSource, EntitySchema } = require('typeorm');
const { CONFIG, DATABASES } = require('../lib/config');
const { BehaviorMismatch, expect } = require('../lib/errors');

// TypeORM wave 2: the full column-type set crossed with declare/insert/
// roundtrip/null/update/bulk operations. To bound the live-connection count
// (TypeORM's mysql2 driver leaks ~1 connection per DataSource lifetime against
// this MatrixOne build), we cache one long-lived DataSource per *distinct entity
// shape* (i.e. per column type) and reuse it across that type's scenarios, with
// synchronize(true) recreating the table so each scenario starts clean.
const dsCache = new Map();
async function withShape(key, E, body) {
  if (!dsCache.has(key)) {
    const ds = new DataSource({
      type: 'mysql', host: CONFIG.host, port: CONFIG.port,
      username: CONFIG.user, password: CONFIG.password, database: DATABASES.typeorm,
      driver: require('mysql2'), synchronize: false, logging: false, entities: [E],
      extra: { connectionLimit: 2 },
    });
    await ds.initialize();
    dsCache.set(key, ds);
  }
  const ds = dsCache.get(key);
  await ds.synchronize(true);
  return body(ds, ds.getRepository(E));
}

function fullColMatrix() {
  return [
    ['int', { type: 'int' }, 42, 99],
    ['tinyint', { type: 'tinyint' }, 7, 120],
    ['smallint', { type: 'smallint' }, 100, 30000],
    ['mediumint', { type: 'mediumint' }, 1000, 800000],
    ['bigint', { type: 'bigint' }, '100', '900719925474099'],
    ['decimal', { type: 'decimal', precision: 12, scale: 2 }, '10.50', '99.99'],
    ['numeric', { type: 'numeric', precision: 10, scale: 2 }, '1.50', '9.99'],
    ['float', { type: 'float' }, 3.5, 9.5],
    ['double', { type: 'double' }, 3.14, 2.71],
    ['char', { type: 'char', length: 10 }, 'aa', 'zz'],
    ['varchar', { type: 'varchar', length: 100 }, 'alpha', 'omega'],
    ['text', { type: 'text' }, 'hello', 'world'],
    ['mediumtext', { type: 'mediumtext' }, 'm1', 'm2'],
    ['longtext', { type: 'longtext' }, 'l1', 'l2'],
    ['date', { type: 'date' }, '2026-01-01', '2026-12-31'],
    ['datetime', { type: 'datetime' }, '2026-01-01 00:00:00', '2026-12-31 00:00:00'],
    ['timestamp', { type: 'timestamp' }, '2026-01-01 00:00:00', '2026-06-01 00:00:00'],
    ['time', { type: 'time' }, '08:00:00', '20:00:00'],
    ['year', { type: 'year' }, 2026, 2030],
    ['boolean', { type: 'boolean' }, true, false],
    ['enum', { type: 'enum', enum: ['a', 'b', 'c'] }, 'a', 'c'],
    ['simple-array', { type: 'simple-array' }, ['a', 'b'], ['c', 'd']],
    ['simple-json', { type: 'simple-json' }, { k: 1 }, { k: 2 }],
    ['json', { type: 'json' }, { k: 1 }, { k: 2 }],
    ['blob', { type: 'blob' }, Buffer.from('a'), Buffer.from('b')],
  ];
}

function register(runner) {
  const FW = 'typeorm';

  for (const [label, colDef, sample, boundary] of fullColMatrix()) {
    const cat = `fulltype/${label}`;
    const tname = `to_full_${label.replace(/[^a-z0-9]/gi, '')}`;
    const E = new EntitySchema({ name: tname, tableName: tname, columns: { id: { type: 'int', primary: true, generated: 'increment' }, c: { ...colDef, nullable: true } } });
    const run = (body) => withShape(cat, E, body);

    runner.add(FW, cat, 'declare', () => run(async () => {}));
    runner.add(FW, cat, 'insert-roundtrip', () => run(async (d, repo) => {
      const r = await repo.save({ c: sample });
      const found = await repo.findOneBy({ id: r.id });
      if (!found) throw new BehaviorMismatch('row missing after save');
      if (label === 'float' && Number(found.c) === 4 && Number(sample) === 3.5) {
        throw new BehaviorMismatch(`FLOAT corrupted 3.5 -> ${found.c}`);
      }
    }));
    runner.add(FW, cat, 'null', () => run(async (d, repo) => {
      const r = await repo.save({ c: null });
      const found = await repo.findOneBy({ id: r.id });
      if (found.c !== null) throw new BehaviorMismatch(`null not preserved: ${JSON.stringify(found.c)}`);
    }));
    runner.add(FW, cat, 'update', () => run(async (d, repo) => {
      const r = await repo.save({ c: sample });
      await repo.update({ id: r.id }, { c: boundary });
    }));
    runner.add(FW, cat, 'bulk-insert', () => run(async (d, repo) => {
      await repo.save([{ c: sample }, { c: boundary }, { c: sample }]);
      expect(await repo.count(), 3, 'bulk count');
    }));
    runner.add(FW, cat, 'count', () => run(async (d, repo) => {
      await repo.save([{ c: sample }, { c: boundary }]);
      expect(await repo.count(), 2, 'count');
    }));
  }
}

async function teardown() {
  for (const ds of dsCache.values()) { try { await ds.destroy(); } catch (_) {} }
  dsCache.clear();
}

module.exports = { register, teardown };
