'use strict';

// ---------------------------------------------------------------------------
// Shared connection helpers for the NEW ORMs (Objection/knex, MikroORM,
// Drizzle, Prisma). Each ORM gets a single long-lived connection/pool kept
// small (per harness rules: the MatrixOne node is shared, run sequentially,
// keep pools small). Every helper exposes a teardown() so run.js can close
// pools cleanly at the end.
//
// A local "withTimeout" wrapper is provided so an individual ORM operation
// that stalls against MatrixOne is surfaced as a normal rejection rather than
// stalling the suite; the runner's per-scenario 30s cap is the backstop.
// ---------------------------------------------------------------------------

const { CONFIG, DATABASES } = require('./config');

// Reject a promise if it does not settle within ms. Used to defend against the
// occasional MatrixOne operation that neither returns nor errors via an ORM.
function withTimeout(promise, ms, label) {
  let timer = null;
  const t = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error(`ORM op timed out after ${ms}ms${label ? ' (' + label + ')' : ''}`)), ms);
  });
  return Promise.race([Promise.resolve(promise), t]).finally(() => { if (timer) clearTimeout(timer); });
}

// ---- knex (used directly by Objection) ---------------------------------
const Knex = require('knex');
let _objKnex = null;
function objectionKnex() {
  if (!_objKnex) {
    _objKnex = Knex({
      client: 'mysql2',
      connection: {
        host: CONFIG.host, port: CONFIG.port, user: CONFIG.user,
        password: CONFIG.password, database: DATABASES.objection,
      },
      pool: { min: 0, max: 3 },
    });
  }
  return _objKnex;
}

// ---- MikroORM ----------------------------------------------------------
let _mikro = null;
async function mikroOrm(entities) {
  // MikroORM binds entity metadata at init. We keep ONE long-lived ORM with a
  // superset of entities passed on first call; subsequent calls reuse it. The
  // caller is responsible for passing a stable entity set.
  if (!_mikro) {
    const { MikroORM } = require('@mikro-orm/mysql');
    _mikro = await MikroORM.init({
      entities,
      host: CONFIG.host, port: CONFIG.port, user: CONFIG.user,
      password: CONFIG.password, dbName: DATABASES.mikro,
      pool: { min: 0, max: 3 },
      allowGlobalContext: true,
      debug: false,
      // Skip MikroORM's own schema/connection discovery features that probe
      // information_schema tables MatrixOne lacks.
      discovery: { warnWhenNoEntities: false },
    });
  }
  return _mikro;
}

// ---- Drizzle -----------------------------------------------------------
const mysql2 = require('mysql2/promise');
let _drizzlePool = null;
let _drizzleDb = null;
function drizzle() {
  if (!_drizzleDb) {
    const { drizzle: drizzleFn } = require('drizzle-orm/mysql2');
    _drizzlePool = mysql2.createPool({
      host: CONFIG.host, port: CONFIG.port, user: CONFIG.user,
      password: CONFIG.password, database: DATABASES.drizzle,
      connectionLimit: 3, waitForConnections: true, queueLimit: 0,
    });
    _drizzleDb = drizzleFn(_drizzlePool, { mode: 'default' });
  }
  return _drizzleDb;
}
// Raw pool access for Drizzle DDL (drizzle-kit migrations are out of scope; we
// create tables with raw SQL through the same pool).
function drizzlePool() { drizzle(); return _drizzlePool; }

// ---- Prisma ------------------------------------------------------------
// Prisma 7 uses a driver-adapter model. `prisma db push` FAILS against
// MatrixOne (information_schema.check_constraints is absent), so the client is
// generated ahead of the run (run.js bootstrap) and tables are created with
// raw SQL. The runtime client itself works for CRUD/raw via the mariadb
// adapter. We keep ONE shared client.
let _prisma = null;
let _prismaRawPool = null;
function prismaAvailable() {
  try {
    require.resolve('./../prisma_app/generated/client.js');
    require.resolve('@prisma/adapter-mariadb');
    return true;
  } catch (_) { return false; }
}
async function prismaClient() {
  if (!_prisma) {
    const { PrismaClient } = require('../prisma_app/generated/client.js');
    const { PrismaMariaDb } = require('@prisma/adapter-mariadb');
    const adapter = new PrismaMariaDb({
      host: CONFIG.host, port: CONFIG.port, user: CONFIG.user,
      password: CONFIG.password, database: DATABASES.prisma,
    });
    _prisma = new PrismaClient({ adapter });
  }
  return _prisma;
}
// Raw pool to create Prisma's tables (db push is unavailable) and for cleanup.
function prismaRawPool() {
  if (!_prismaRawPool) {
    _prismaRawPool = mysql2.createPool({
      host: CONFIG.host, port: CONFIG.port, user: CONFIG.user,
      password: CONFIG.password, database: DATABASES.prisma,
      connectionLimit: 2, waitForConnections: true, queueLimit: 0,
    });
  }
  return _prismaRawPool;
}

async function teardownObjection() { if (_objKnex) { await _objKnex.destroy(); _objKnex = null; } }
async function teardownMikro() { if (_mikro) { try { await _mikro.close(true); } catch (_) {} _mikro = null; } }
async function teardownDrizzle() { if (_drizzlePool) { await _drizzlePool.end(); _drizzlePool = null; _drizzleDb = null; } }
async function teardownPrisma() {
  if (_prisma) { try { await _prisma.$disconnect(); } catch (_) {} _prisma = null; }
  if (_prismaRawPool) { try { await _prismaRawPool.end(); } catch (_) {} _prismaRawPool = null; }
}

module.exports = {
  withTimeout,
  objectionKnex, teardownObjection,
  mikroOrm, teardownMikro,
  drizzle, drizzlePool, teardownDrizzle,
  prismaAvailable, prismaClient, prismaRawPool, teardownPrisma,
};
