'use strict';

// High-volume generated coverage (raw mysql2 driver), mirroring the PHP/Python
// MegaMatrix providers: operator/operand grids, function-input variations, a
// cast matrix, and parametric query-workload shapes over a shared seeded
// fixture. Most expression cases assert only that MatrixOne executes them (a
// divergence => a finding); workload cases assert a plausible scalar.

const { makePool } = require('../lib/db');
const { BehaviorMismatch } = require('../lib/errors');

const FW = 'mega';
let pool = null;
function getPool() {
  if (!pool) pool = makePool('mega', { connectionLimit: 4 });
  return pool;
}
async function scalar(sql) {
  const [rows] = await getPool().query(sql);
  const r = rows[0];
  if (!r) return undefined;
  return Object.values(r)[0];
}

function addExpr(runner, cat, name, expr, expect) {
  runner.add(FW, cat, name, async () => {
    const got = await scalar(`SELECT ${expr} AS v`);
    if (expect !== undefined) {
      if (String(expect) !== String(got)) {
        const a = Number(got);
        const b = Number(expect);
        if (!(Number.isFinite(a) && Number.isFinite(b) && Math.abs(a - b) < 1e-9)) {
          throw new BehaviorMismatch(`${expr}: expected ${expect} got ${got}`);
        }
      }
    }
    return 'ran';
  });
}

const OPERANDS = {
  int0: '0', int1: '1', intNeg: '-7', intBig: '2147483647',
  bigint: '9223372036854775807', dec: '12.50', decNeg: '-3.14', frac: '0.001',
  numStr: "'42'", mixStr: "'10abc'", str: "'hello'", date: "'2026-06-23'",
  dt: "'2026-06-23 10:20:30'", nul: 'NULL', zeroStr: "'0'",
  bigDec: '99999999999999999999.99', hugeNeg: '-2147483648',
};

function grids(runner) {
  const arith = { '+': 'add', '-': 'sub', '*': 'mul', '/': 'div', DIV: 'idiv', '%': 'mod' };
  for (const [op, n] of Object.entries(arith)) {
    for (const [la, a] of Object.entries(OPERANDS)) {
      for (const [lb, b] of Object.entries(OPERANDS)) {
        addExpr(runner, 'grid:arith', `${la} ${n} ${lb}`, `${a} ${op} ${b}`);
      }
    }
  }
  const cmp = { '=': 'eq', '<>': 'ne', '<': 'lt', '<=': 'le', '>': 'gt', '>=': 'ge', '<=>': 'nseq' };
  for (const [op, n] of Object.entries(cmp)) {
    for (const [la, a] of Object.entries(OPERANDS)) {
      for (const [lb, b] of Object.entries(OPERANDS)) {
        addExpr(runner, 'grid:compare', `${la} ${n} ${lb}`, `${a} ${op} ${b}`);
      }
    }
  }
  const ints = ['0', '1', '5', '255', '-1', '1024', '9223372036854775807'];
  for (const [op, n] of Object.entries({ '&': 'and', '|': 'or', '^': 'xor', '<<': 'shl', '>>': 'shr' })) {
    for (const a of ints) for (const b of ints) addExpr(runner, 'grid:bitwise', `${a} ${n} ${b}`, `${a} ${op} ${b}`);
  }
  const bools = ['0', '1', 'NULL', '5', '-1'];
  for (const [op, n] of Object.entries({ AND: 'and', OR: 'or', XOR: 'xor' })) {
    for (const a of bools) for (const b of bools) addExpr(runner, 'grid:logical', `${a} ${n} ${b}`, `${a} ${op} ${b}`);
  }
  for (const a of bools) {
    addExpr(runner, 'grid:logical', `NOT ${a}`, `NOT ${a}`);
    addExpr(runner, 'grid:bitwise', `~ ${a}`, `~ ${a}`);
  }
}

function functions(runner) {
  const strIn = ["'abc'", "''", "'Hello World'", "'  pad  '", "'a,b,c'", "'2026-06-23'", "'123.45'", "'xyz'"];
  const strFns = ['UPPER', 'LOWER', 'LENGTH', 'CHAR_LENGTH', 'REVERSE', 'TRIM', 'LTRIM', 'RTRIM',
    'HEX', 'TO_BASE64', 'MD5', 'SHA1', 'SOUNDEX', 'ORD', 'ASCII', 'BIT_LENGTH', 'QUOTE'];
  strFns.forEach((fn) => strIn.forEach((inp, i) => addExpr(runner, 'fn:string-var', `${fn} #${i}`, `${fn}(${inp})`)));
  ['LEFT', 'RIGHT', 'REPEAT'].forEach((fn) => strIn.forEach((inp, i) => ['0', '1', '3', '50'].forEach((k) => addExpr(runner, 'fn:string-var', `${fn} #${i},${k}`, `${fn}(${inp}, ${k})`))));
  const numIn = ['0', '1', '-1', '3.14159', '-2.5', '1000000', '0.0001', '255'];
  const numFns = ['ABS', 'CEIL', 'FLOOR', 'SIGN', 'SQRT', 'EXP', 'LN', 'LOG2', 'LOG10', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'DEGREES', 'RADIANS', 'CRC32', 'BIN', 'OCT'];
  numFns.forEach((fn) => numIn.forEach((inp, i) => addExpr(runner, 'fn:numeric-var', `${fn} #${i}`, `${fn}(${inp})`)));
  ['ROUND', 'TRUNCATE'].forEach((fn) => numIn.forEach((inp, i) => ['-2', '0', '2', '4'].forEach((d) => addExpr(runner, 'fn:numeric-var', `${fn} #${i},${d}`, `${fn}(${inp}, ${d})`))));
  const dateIn = ["'2026-06-23'", "'2024-02-29'", "'2026-12-31 23:59:59'", "'2000-01-01'"];
  const dateFns = ['YEAR', 'MONTH', 'DAY', 'HOUR', 'MINUTE', 'SECOND', 'QUARTER', 'WEEK', 'DAYOFWEEK', 'DAYOFYEAR', 'DAYNAME', 'MONTHNAME', 'LAST_DAY', 'TO_DAYS', 'WEEKDAY'];
  dateFns.forEach((fn) => dateIn.forEach((inp, i) => addExpr(runner, 'fn:date-var', `${fn} #${i}`, `${fn}(${inp})`)));
  ['DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR', 'HOUR'].forEach((u) => dateIn.forEach((inp, i) => addExpr(runner, 'fn:date-var', `ADD ${u} #${i}`, `DATE_ADD(${inp}, INTERVAL 3 ${u})`)));
  ['%Y-%m-%d', '%H:%i:%s', '%W %M %Y', '%j', '%p %r'].forEach((f) => dateIn.forEach((inp, i) => addExpr(runner, 'fn:date-format', `fmt ${f} #${i}`, `DATE_FORMAT(${inp}, '${f}')`)));
}

function casts(runner) {
  const targets = ['SIGNED', 'UNSIGNED', 'CHAR', 'CHAR(5)', 'DECIMAL(10,2)', 'DECIMAL(20,4)', 'DATE', 'DATETIME', 'TIME', 'DOUBLE', 'FLOAT', 'BINARY', 'JSON', 'NCHAR'];
  const sources = ["'42'", "'-3.14'", "'2026-06-23'", "'2026-06-23 10:20:30'", "'10:20:30'", "'abc'", '255', '3.14159', "'[1,2,3]'", 'NULL'];
  for (const t of targets) {
    for (const s of sources) {
      addExpr(runner, 'cast:matrix', `CAST ${s} AS ${t}`, `CAST(${s} AS ${t})`);
      addExpr(runner, 'cast:matrix', `CONVERT ${s},${t}`, `CONVERT(${s}, ${t})`);
    }
  }
}

let fixtureReady = false;
async function ensureFixture() {
  if (fixtureReady) return;
  const p = getPool();
  await p.query('DROP TABLE IF EXISTS qw_orders');
  await p.query('DROP TABLE IF EXISTS qw_users');
  await p.query('CREATE TABLE qw_users (id INT PRIMARY KEY, name VARCHAR(40), region VARCHAR(10), tier INT)');
  await p.query('CREATE TABLE qw_orders (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, product VARCHAR(20), qty INT, price DECIMAL(10,2), status VARCHAR(12), region VARCHAR(10), created DATETIME)');
  const regions = ['NA', 'EU', 'APAC', 'LATAM'];
  const status = ['new', 'paid', 'shipped', 'cancelled', 'refunded'];
  const prods = ['widget', 'gadget', 'gizmo', 'doohickey', 'thingamajig'];
  const uvals = [];
  for (let i = 1; i <= 200; i++) uvals.push(`(${i},'user${i}','${regions[i % 4]}',${(i % 3) + 1})`);
  await p.query('INSERT INTO qw_users VALUES ' + uvals.join(','));
  for (let b = 0; b < 15; b++) {
    const rows = [];
    for (let i = 1; i <= 100; i++) {
      const n = b * 100 + i;
      const mo = String((n % 12) + 1).padStart(2, '0');
      const da = String((n % 27) + 1).padStart(2, '0');
      const hh = String(n % 24).padStart(2, '0');
      rows.push(`(${(n % 200) + 1},'${prods[n % 5]}',${(n % 10) + 1},${((n % 1000) + 0.99).toFixed(2)},'${status[n % 5]}','${regions[n % 4]}','2026-${mo}-${da} ${hh}:00:00')`);
    }
    await p.query('INSERT INTO qw_orders(user_id,product,qty,price,status,region,created) VALUES ' + rows.join(','));
  }
  fixtureReady = true;
}

function addQuery(runner, cat, name, sql) {
  runner.add(FW, cat, name, async () => {
    await ensureFixture();
    const v = await scalar(sql);
    if (v === undefined || v === null) throw new BehaviorMismatch('query returned no row');
    return `= ${v}`;
  });
}

function workload(runner) {
  const cols = ['qty', 'price', 'status', 'region', 'product', 'user_id'];
  const ops = ['=', '<>', '<', '<=', '>', '>=', 'LIKE', 'IN'];
  const vals = {
    qty: ['5', '0', '11'], price: ['100.00', '0.99', '500'],
    status: ["'paid'", "'PAID'", "'unknown'"], region: ["'EU'", "'eu'", "'NA'"],
    product: ["'widget'", "'WIDGET'", "'w%'"], user_id: ['1', '100', '999'],
  };
  for (const c of cols) {
    for (const op of ops) {
      for (const v of vals[c]) {
        let expr;
        if (op === 'IN') expr = `${c} IN (${v}, ${v})`;
        else if (op === 'LIKE') expr = `${c} LIKE ${v}`;
        else expr = `${c} ${op} ${v}`;
        addQuery(runner, 'workload:filter', `${c} ${op} ${v}`, `SELECT COUNT(*) FROM qw_orders WHERE ${expr}`);
      }
    }
  }
  for (const c of cols) {
    for (const d of ['ASC', 'DESC']) {
      for (const pg of ['LIMIT 10', 'LIMIT 10 OFFSET 50', 'LIMIT 1 OFFSET 999', 'LIMIT 100 OFFSET 1400']) {
        addQuery(runner, 'workload:sort', `${c} ${d} ${pg}`, `SELECT COUNT(*) FROM (SELECT id FROM qw_orders ORDER BY ${c} ${d} ${pg}) z`);
      }
    }
  }
  const aggs = ['COUNT(*)', 'SUM(price)', 'AVG(qty)', 'MIN(price)', 'MAX(qty)', 'COUNT(DISTINCT user_id)'];
  for (const g of ['region', 'status', 'product', 'qty']) {
    for (const a of aggs) {
      addQuery(runner, 'workload:group', `${g} ${a}`, `SELECT COUNT(*) FROM (SELECT ${g}, ${a} m FROM qw_orders GROUP BY ${g}) z`);
      addQuery(runner, 'workload:group', `${g} ${a} having`, `SELECT COUNT(*) FROM (SELECT ${g}, ${a} m FROM qw_orders GROUP BY ${g} HAVING ${a} > 0) z`);
    }
  }
  for (const w of ['ROW_NUMBER()', 'RANK()', 'DENSE_RANK()', 'SUM(price)', 'AVG(qty)', 'LAG(price)', 'LEAD(qty)', 'NTILE(4)']) {
    for (const part of ['region', 'status', 'product']) {
      addQuery(runner, 'workload:window', `${w} over ${part}`, `SELECT COUNT(*) FROM (SELECT ${w} OVER (PARTITION BY ${part} ORDER BY id) wv FROM qw_orders) z`);
    }
  }
  for (const jt of ['JOIN', 'LEFT JOIN', 'RIGHT JOIN']) {
    for (const on of ['o.user_id=u.id', 'o.region=u.region']) {
      for (const w of ['', "WHERE u.tier=1", "WHERE o.status='paid'"]) {
        addQuery(runner, 'workload:join', `${jt} ${on} ${w}`, `SELECT COUNT(*) FROM qw_orders o ${jt} qw_users u ON ${on} ${w}`);
      }
    }
  }
  for (const g of ['region', 'status', 'product']) {
    addQuery(runner, 'workload:analytics', `rollup ${g}`, `SELECT COUNT(*) FROM (SELECT ${g}, SUM(price) FROM qw_orders GROUP BY ${g} WITH ROLLUP) z`);
    addQuery(runner, 'workload:analytics', `month ${g}`, `SELECT COUNT(*) FROM (SELECT MONTH(created) m, ${g}, SUM(price) FROM qw_orders GROUP BY MONTH(created), ${g}) z`);
    addQuery(runner, 'workload:subquery', `in ${g}`, `SELECT COUNT(*) FROM qw_orders WHERE ${g} IN (SELECT ${g} FROM qw_orders WHERE price > 100)`);
    addQuery(runner, 'workload:subquery', `scalar ${g}`, `SELECT COUNT(*) FROM qw_orders WHERE price > (SELECT AVG(price) FROM qw_orders)`);
  }
}

function register(runner) {
  grids(runner);
  functions(runner);
  casts(runner);
  workload(runner);
}

async function teardown() {
  if (pool) { try { await pool.end(); } catch (_) { /* noop */ } pool = null; }
}

module.exports = { register, teardown };
