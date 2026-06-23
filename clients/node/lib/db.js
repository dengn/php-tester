'use strict';

const mysql = require('mysql2/promise');
const { CONFIG, DATABASES } = require('./config');

// Server-level connection (no database selected) used for bootstrap/teardown.
async function serverConnection() {
  return mysql.createConnection({
    host: CONFIG.host,
    port: CONFIG.port,
    user: CONFIG.user,
    password: CONFIG.password,
    multipleStatements: false,
  });
}

// Create the per-framework databases fresh (drop + create).
async function bootstrapDatabases(names) {
  const conn = await serverConnection();
  try {
    for (const key of names) {
      const db = DATABASES[key];
      if (!db) continue;
      await conn.query(`DROP DATABASE IF EXISTS \`${db}\``);
      await conn.query(`CREATE DATABASE \`${db}\``);
    }
  } finally {
    await conn.end();
  }
}

async function dropDatabases(names) {
  const conn = await serverConnection();
  try {
    for (const key of names) {
      const db = DATABASES[key];
      if (!db) continue;
      try {
        await conn.query(`DROP DATABASE IF EXISTS \`${db}\``);
      } catch (_) { /* ignore */ }
    }
  } finally {
    await conn.end();
  }
}

// A pool bound to a specific database. mysql2 pools keep connection count low.
function makePool(dbKey, opts = {}) {
  return mysql.createPool({
    host: CONFIG.host,
    port: CONFIG.port,
    user: CONFIG.user,
    password: CONFIG.password,
    database: DATABASES[dbKey],
    connectionLimit: opts.connectionLimit || 4,
    waitForConnections: true,
    queueLimit: 0,
    ...opts,
  });
}

// Monotonic unique-name source so concurrent-looking scenarios never collide.
let _seq = 0;
function uniq(prefix) {
  _seq += 1;
  return `${prefix}_${process.pid}_${_seq}`;
}

module.exports = {
  mysql,
  serverConnection,
  bootstrapDatabases,
  dropDatabases,
  makePool,
  uniq,
  CONFIG,
  DATABASES,
};
