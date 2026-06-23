'use strict';

// Central connection configuration. Everything is overridable via env vars so
// the identical suite can target a managed MatrixOne endpoint without code edits.
const CONFIG = {
  host: process.env.MO_HOST || '127.0.0.1',
  port: parseInt(process.env.MO_PORT || '6001', 10),
  user: process.env.MO_USER || 'root',
  password: process.env.MO_PASS !== undefined ? process.env.MO_PASS : '111',
};

// Dedicated database namespace per framework. The harness creates/drops these.
const DATABASES = {
  raw: 'mo_node_raw',
  sequelize: 'mo_node_seq',
  typeorm: 'mo_node_typeorm',
  knex: 'mo_node_knex',
  prisma: 'mo_node_prisma',
};

module.exports = { CONFIG, DATABASES };
