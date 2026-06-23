'use strict';

require('reflect-metadata');
const { DataSource } = require('typeorm');
const { CONFIG, DATABASES } = require('./config');

// ---------------------------------------------------------------------------
// TypeORM connection management.
//
// IMPORTANT: against this MatrixOne build, TypeORM's mysql2 driver leaks one
// server connection per DataSource lifetime even after destroy() (verified:
// SHOW PROCESSLIST grows by 1 per create/destroy cycle, and MatrixOne only
// reaps them when the client *process* exits). With 500+ scenarios that would
// blow past max_connections (151) and the server starts refusing/handing new
// connections, manifesting as hangs.
//
// To stay well under the limit we keep ONE long-lived shared DataSource (no
// entities) for the large generated matrices, exercising TypeORM's connection,
// query-runner and SQL layers via dataSource.query()/createQueryRunner(). The
// hand-written feature tests that genuinely need entity metadata
// (repositories, relations, find-options, query-builder) use a *bounded,
// reused* DataSource registry keyed by their entity set so the number of live
// DataSources stays small and constant.
// ---------------------------------------------------------------------------

let shared = null;
async function getShared() {
  if (!shared) {
    shared = new DataSource({
      type: 'mysql', host: CONFIG.host, port: CONFIG.port,
      username: CONFIG.user, password: CONFIG.password, database: DATABASES.typeorm,
      driver: require('mysql2'), synchronize: false, logging: false, entities: [],
      extra: { connectionLimit: 4 },
    });
    await shared.initialize();
  }
  return shared;
}

// A bounded ring of metadata-bearing DataSources. We cap the number of distinct
// live DataSources; when the cap is hit the oldest is destroyed and replaced.
// Because each scenario fully drops its tables, reusing the *same* DataSource
// for different entity sets is not possible (metadata is bound at init), so the
// ring trades a small, bounded connection footprint for correctness.
const RING_CAP = 30; // stays far below the 151 server limit
const ring = [];

async function withEntitiesPooled(schemas, body) {
  const ds = new DataSource({
    type: 'mysql', host: CONFIG.host, port: CONFIG.port,
    username: CONFIG.user, password: CONFIG.password, database: DATABASES.typeorm,
    driver: require('mysql2'), synchronize: false, logging: false, entities: schemas,
    extra: { connectionLimit: 1 },
  });
  await ds.initialize();
  ring.push(ds);
  // Evict oldest if over cap (bounds the leaked-connection count to ~RING_CAP).
  while (ring.length > RING_CAP) {
    const old = ring.shift();
    try { await old.destroy(); } catch (_) {}
  }
  try {
    await ds.synchronize(true);
    return await body(ds);
  } finally {
    try {
      const qr = ds.createQueryRunner();
      for (const s of [...schemas].reverse()) {
        const tn = s.options.tableName || s.options.name;
        try { await qr.query(`DROP TABLE IF EXISTS \`${tn}\``); } catch (_) {}
      }
      await qr.release();
    } catch (_) {}
    // NOTE: do not destroy here; the ring evicts. Destroying immediately would
    // still leak (see header) but more importantly the ring keeps the live
    // count bounded and constant.
  }
}

// Cache of long-lived DataSources keyed by a caller-supplied shape key. Reusing
// one DataSource for every scenario that shares an entity set keeps the live
// (leaked) connection count to one-per-distinct-shape instead of one-per-
// scenario. synchronize(true) recreates the tables so state never bleeds.
const shapeCache = new Map();
async function withShapeCached(key, schemas, body) {
  if (!shapeCache.has(key)) {
    const ds = new DataSource({
      type: 'mysql', host: CONFIG.host, port: CONFIG.port,
      username: CONFIG.user, password: CONFIG.password, database: DATABASES.typeorm,
      driver: require('mysql2'), synchronize: false, logging: false, entities: schemas,
      extra: { connectionLimit: 2 },
    });
    await ds.initialize();
    shapeCache.set(key, ds);
  }
  const ds = shapeCache.get(key);
  await ds.synchronize(true);
  try {
    return await body(ds);
  } finally {
    // leave tables in place; next synchronize(true) drops+recreates them
  }
}

async function teardownShared() {
  for (const ds of ring.splice(0)) { try { await ds.destroy(); } catch (_) {} }
  for (const ds of shapeCache.values()) { try { await ds.destroy(); } catch (_) {} }
  shapeCache.clear();
  if (shared) { try { await shared.destroy(); } catch (_) {} shared = null; }
}

module.exports = { getShared, withEntitiesPooled, withShapeCached, teardownShared };
