'use strict';

// Bootstraps Prisma for the run:
//   1. Ensures the client is generated (idempotent).
//   2. Attempts `prisma db push` against MatrixOne and records the outcome as a
//      finding. This exercises Prisma's schema/migration engine, which against
//      MatrixOne 4.0.0-rc3 fails because information_schema.check_constraints is
//      absent. The captured result is consumed by scenarios/prisma.js so the
//      blocker shows up as registered scenarios.
//
// Everything here is best-effort and resilient: if Prisma is missing or errors,
// the suite continues with Prisma scenarios recording the blocker.

const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const PRISMA_DIR = path.join(__dirname, '..', 'prisma_app');
const CONFIG = path.join(PRISMA_DIR, 'prisma.config.mjs');

// Result object shared with scenarios/prisma.js.
const state = {
  generated: false,
  generateError: null,
  dbPushAttempted: false,
  dbPushOk: false,
  dbPushError: null,
  clientUsable: false,
};

function npxArgs(args) {
  return ['--yes', 'prisma', ...args, '--config', CONFIG];
}

function run(args, timeoutMs) {
  return execFileSync('npx', args, {
    cwd: PRISMA_DIR,
    timeout: timeoutMs,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    env: process.env,
  });
}

function bootstrapPrisma() {
  // 1. generate (skip if already generated to keep startup fast)
  const generatedClient = path.join(PRISMA_DIR, 'generated', 'client.js');
  try {
    if (!fs.existsSync(generatedClient)) {
      run(npxArgs(['generate']), 120000);
    }
    state.generated = fs.existsSync(generatedClient);
  } catch (err) {
    state.generateError = String(err.stderr || err.message || err).slice(0, 400);
  }

  // 2. db push (expected to FAIL — capture it).
  state.dbPushAttempted = true;
  try {
    run(npxArgs(['db', 'push', '--force-reset']), 90000);
    state.dbPushOk = true;
  } catch (err) {
    const out = String((err.stdout || '') + '\n' + (err.stderr || err.message || err));
    state.dbPushError = out.replace(/\s+/g, ' ').trim().slice(0, 500);
  }

  state.clientUsable = state.generated;
  return state;
}

module.exports = { bootstrapPrisma, prismaState: state, PRISMA_DIR };
