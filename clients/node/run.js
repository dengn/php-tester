'use strict';

require('reflect-metadata'); // needed before any TypeORM import

const path = require('path');
const { Runner } = require('./lib/runner');
const { writeReports } = require('./lib/reporter');
const { bootstrapDatabases, dropDatabases, CONFIG, DATABASES } = require('./lib/db');

// ---- framework registry ----
const FRAMEWORKS = {
  raw: { dbKey: 'raw', modules: ['./scenarios/raw', './scenarios/raw_extra', './scenarios/raw_matrix2', './scenarios/raw_matrix3', './scenarios/raw_matrix4', './scenarios/raw_matrix5', './scenarios/raw_matrix6', './scenarios/raw_matrix7', './scenarios/workload'] },
  sequelize: { dbKey: 'sequelize', modules: ['./scenarios/sequelize', './scenarios/sequelize_extra', './scenarios/sequelize_matrix2', './scenarios/sequelize_matrix3', './scenarios/sequelize_matrix4'] },
  typeorm: { dbKey: 'typeorm', modules: ['./scenarios/typeorm', './scenarios/typeorm_extra', './scenarios/typeorm_matrix2'] },
  knex: { dbKey: 'knex', modules: ['./scenarios/knex', './scenarios/knex_extra', './scenarios/knex_matrix2', './scenarios/knex_matrix3', './scenarios/knex_matrix4', './scenarios/knex_matrix5'] },
};

function parseArgs(argv) {
  const opts = { only: null, subset: null, verbose: false, list: false, dropOnly: false };
  for (const a of argv.slice(2)) {
    if (a.startsWith('--only=')) opts.only = a.slice(7).split(',').map((s) => s.trim()).filter(Boolean);
    else if (a.startsWith('--subset=')) opts.subset = parseInt(a.slice(9), 10);
    else if (a === '--verbose' || a === '-v') opts.verbose = true;
    else if (a === '--list') opts.list = true;
    else if (a === '--drop') opts.dropOnly = true;
  }
  return opts;
}

async function main() {
  const opts = parseArgs(process.argv);

  const selected = opts.only
    ? Object.keys(FRAMEWORKS).filter((k) => opts.only.includes(k))
    : Object.keys(FRAMEWORKS);

  if (selected.length === 0) {
    console.error(`No matching frameworks for --only=${opts.only}. Available: ${Object.keys(FRAMEWORKS).join(', ')}`);
    process.exit(2);
  }

  console.log('MatrixOne ⇆ Node.js ORM compatibility suite');
  console.log('='.repeat(60));
  console.log(`Target: ${CONFIG.host}:${CONFIG.port} (user ${CONFIG.user})`);
  console.log(`Frameworks: ${selected.join(', ')}`);

  if (opts.dropOnly) {
    await dropDatabases(selected.map((k) => FRAMEWORKS[k].dbKey));
    console.log('Dropped databases. Exiting.');
    return;
  }

  // ---- bootstrap databases ----
  console.log('Bootstrapping databases...');
  await bootstrapDatabases(selected.map((k) => FRAMEWORKS[k].dbKey));

  // ---- register scenarios ----
  const runner = new Runner({ verbose: opts.verbose });
  const teardowns = [];
  for (const fw of selected) {
    for (const modPath of FRAMEWORKS[fw].modules) {
      const mod = require(modPath);
      mod.register(runner);
      if (mod.teardown) teardowns.push(mod.teardown);
    }
  }

  const registered = runner.count();
  console.log(`Registered ${registered} scenarios.`);

  if (opts.list) {
    // print category breakdown then exit
    const byCat = {};
    for (const s of runner.scenarios) {
      const k = `${s.framework}/${s.category}`;
      byCat[k] = (byCat[k] || 0) + 1;
    }
    for (const k of Object.keys(byCat).sort()) console.log(`  ${k}: ${byCat[k]}`);
    console.log(`TOTAL registered: ${registered}`);
    return;
  }

  // ---- optional subset sampling (deterministic, spread across all cats) ----
  let mode = 'full';
  if (opts.subset && opts.subset < registered) {
    mode = 'subset';
    const all = runner.scenarios;
    // bucket by framework/category, round-robin pick to spread coverage
    const buckets = new Map();
    for (const s of all) {
      const k = `${s.framework}/${s.category}`;
      if (!buckets.has(k)) buckets.set(k, []);
      buckets.get(k).push(s);
    }
    const keys = [...buckets.keys()];
    const picked = [];
    const seen = new Set();
    let idx = 0;
    while (picked.length < opts.subset) {
      let advanced = false;
      for (const k of keys) {
        const arr = buckets.get(k);
        if (idx < arr.length) {
          picked.push(arr[idx]);
          seen.add(arr[idx]);
          advanced = true;
          if (picked.length >= opts.subset) break;
        }
      }
      if (!advanced) break;
      idx++;
    }
    runner.scenarios = picked;
    console.log(`Subset mode: running ${picked.length} of ${registered} (spread across ${keys.length} categories).`);
  }

  // ---- run ----
  console.log(`Running ${runner.count()} scenarios sequentially...`);
  const t0 = Date.now();
  await runner.run();
  const elapsed = ((Date.now() - t0) / 1000).toFixed(1);

  // ---- teardown (close pools/connections) ----
  for (const td of teardowns) {
    try { await td(); } catch (_) {}
  }

  const summary = runner.summary();
  const outDir = path.join(__dirname, 'reports');
  writeReports(runner.results, summary, outDir, {
    engine: '8.0.30-MatrixOne-v4.0.0-rc3',
    target: `${CONFIG.host}:${CONFIG.port}`,
    mode,
    registered,
  });

  console.log('\n' + '='.repeat(60));
  console.log(`DONE in ${elapsed}s — ${summary.total} ran: ${summary.pass} PASS, ${summary.fail} FAIL, ${summary.skip} SKIP`);
  console.log(`Mode: ${mode} (registered ${registered}).`);
  console.log('Reports: reports/results.json and reports/summary.md');
}

// Surface unhandled rejections loudly so harness bugs cannot hide.
process.on('unhandledRejection', (err) => {
  console.error('\n[UNHANDLED REJECTION — harness bug]', err);
  process.exit(1);
});

main().then(() => process.exit(0)).catch((err) => {
  console.error('FATAL', err);
  process.exit(1);
});
