'use strict';

const { makePool, uniq } = require('../lib/db');
const { SQL_TYPES, SQL_FUNCTIONS } = require('../lib/matrix');
const { BehaviorMismatch, SkipScenario, expect, expectTrue } = require('../lib/errors');

// One shared pool for the raw framework (kept small per harness rules).
let pool = null;
function getPool() {
  if (!pool) pool = makePool('raw', { connectionLimit: 4 });
  return pool;
}

async function q(sql, params) {
  const [rows] = await getPool().query(sql, params);
  return rows;
}
async function exec(sql, params) {
  // prepared-statement path (binary protocol)
  const [rows] = await getPool().execute(sql, params);
  return rows;
}

// helper: create a throwaway table, run a body, always drop.
async function withTable(ddl, body) {
  const t = uniq('t');
  await q(ddl(t));
  try {
    return await body(t);
  } finally {
    try { await q(`DROP TABLE IF EXISTS \`${t}\``); } catch (_) {}
  }
}

function register(runner) {
  const FW = 'raw';

  // -------------------------------------------------------------------------
  // 1. DATA TYPE × OPERATION MATRIX
  //    operations: declare, insert, roundtrip, null, boundary, update, where,
  //    order, group, index  => ~10 per type × 40 types = ~400 scenarios
  // -------------------------------------------------------------------------
  for (const ty of SQL_TYPES) {
    const base = `type/${ty.name}`;

    runner.add(FW, base, `declare ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async () => {}));

    if (!ty.skipValue) {
      runner.add(FW, base, `insert ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
        }));

      runner.add(FW, base, `roundtrip ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
          const rows = await q(`SELECT c FROM \`${t}\` WHERE id=1`);
          if (rows.length !== 1) throw new BehaviorMismatch('row not found after insert');
          // Special-case FLOAT corruption check from FINDINGS C2.
          if (ty.name === 'float' && Number(rows[0].c) === 4 && Number(ty.sample) === 3.5) {
            throw new BehaviorMismatch(`bare FLOAT corrupted 3.5 -> ${rows[0].c}`);
          }
        }));

      runner.add(FW, base, `boundary ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.boundary]);
        }));

      runner.add(FW, base, `update ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
          await q(`UPDATE \`${t}\` SET c=? WHERE id=1`, [ty.boundary]);
        }));

      runner.add(FW, base, `where ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?)`, [ty.sample]);
          await q(`SELECT * FROM \`${t}\` WHERE c=?`, [ty.sample]);
        }));

      runner.add(FW, base, `order ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.boundary]);
          await q(`SELECT * FROM \`${t}\` ORDER BY c ASC`);
        }));

      runner.add(FW, base, `group ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
          await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,?),(2,?)`, [ty.sample, ty.sample]);
          await q(`SELECT c, COUNT(*) n FROM \`${t}\` GROUP BY c`);
        }));
    }

    runner.add(FW, base, `null ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql} NULL)`, async (t) => {
        await q(`INSERT INTO \`${t}\` (id,c) VALUES (1,NULL)`);
        const rows = await q(`SELECT c FROM \`${t}\` WHERE c IS NULL`);
        if (rows.length !== 1) throw new BehaviorMismatch('NULL not retrievable via IS NULL');
      }));

    // index: types that can't be directly indexed (TEXT/BLOB/JSON/GEOMETRY/VEC)
    // need a prefix or are expected to fail — still a valid finding.
    const idxGroups = ['int', 'num', 'str', 'dt', 'enum'];
    if (idxGroups.includes(ty.group)) {
      const idxCol = ty.group === 'str' ? 'c(10)' : 'c';
      runner.add(FW, base, `index ${ty.sql}`, () =>
        withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql}, INDEX ix (${idxCol}))`, async () => {}));
    }
  }

  // -------------------------------------------------------------------------
  // 2. FUNCTION MATRIX  (~190 functions × {select, in-where} = ~380)
  // -------------------------------------------------------------------------
  for (const [cat, name, expr] of SQL_FUNCTIONS) {
    runner.add(FW, `func/${cat}`, `${name} select`, async () => {
      await q(`SELECT ${expr} AS r`);
    });
    runner.add(FW, `func/${cat}`, `${name} in-projection`, async () => {
      await q(`SELECT (${expr}) AS r, 1 AS one`);
    });
  }

  // -------------------------------------------------------------------------
  // 3. DDL scenarios
  // -------------------------------------------------------------------------
  const ddlCases = [
    ['create-pk-auto', (t) => `CREATE TABLE \`${t}\` (id INT AUTO_INCREMENT PRIMARY KEY, n VARCHAR(20))`],
    ['create-composite-pk', (t) => `CREATE TABLE \`${t}\` (a INT, b INT, PRIMARY KEY(a,b))`],
    ['create-unique', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, e VARCHAR(50) UNIQUE)`],
    ['create-default', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n VARCHAR(10) DEFAULT 'x', k INT DEFAULT 5)`],
    ['create-not-null', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n VARCHAR(10) NOT NULL)`],
    ['create-comment', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY COMMENT 'pk') COMMENT='tbl'`],
    ['create-charset', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n VARCHAR(10)) DEFAULT CHARSET=utf8mb4`],
    ['create-multi-index', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, a INT, b INT, INDEX(a), INDEX(b))`],
    ['create-check', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, age INT CHECK (age>=0))`],
    ['create-generated-stored', (t) => `CREATE TABLE \`${t}\` (a INT, b INT GENERATED ALWAYS AS (a+1) STORED)`],
    ['create-generated-virtual', (t) => `CREATE TABLE \`${t}\` (a INT, b INT GENERATED ALWAYS AS (a+1) VIRTUAL)`],
    ['create-fk', null], // handled below specially
    ['create-temp', (t) => `CREATE TEMPORARY TABLE \`${t}\` (id INT PRIMARY KEY)`],
    ['create-as-select', (t) => `CREATE TABLE \`${t}\` AS SELECT 1 AS id, 'a' AS n`],
    ['create-if-not-exists', (t) => `CREATE TABLE IF NOT EXISTS \`${t}\` (id INT PRIMARY KEY)`],
    ['create-fulltext', (t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, body TEXT, FULLTEXT(body))`],
    ['create-serial', (t) => `CREATE TABLE \`${t}\` (id SERIAL, n VARCHAR(10))`],
    ['create-auto-increment-start', (t) => `CREATE TABLE \`${t}\` (id INT AUTO_INCREMENT PRIMARY KEY) AUTO_INCREMENT=100`],
  ];
  for (const [nm, ddl] of ddlCases) {
    if (!ddl) continue;
    runner.add(FW, 'ddl', nm, () => withTable(ddl, async () => {}));
  }
  // foreign key (needs two tables)
  runner.add(FW, 'ddl', 'create-fk', async () => {
    const p = uniq('parent'); const c = uniq('child');
    try {
      await q(`CREATE TABLE \`${p}\` (id INT PRIMARY KEY)`);
      await q(`CREATE TABLE \`${c}\` (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES \`${p}\`(id))`);
    } finally {
      await q(`DROP TABLE IF EXISTS \`${c}\``); await q(`DROP TABLE IF EXISTS \`${p}\``);
    }
  });

  // ALTER TABLE matrix
  const alterCases = [
    ['add-column', (t) => `ALTER TABLE \`${t}\` ADD COLUMN extra INT`],
    ['add-column-default', (t) => `ALTER TABLE \`${t}\` ADD COLUMN extra INT DEFAULT 9`],
    ['drop-column', (t) => `ALTER TABLE \`${t}\` DROP COLUMN n`],
    ['modify-column', (t) => `ALTER TABLE \`${t}\` MODIFY COLUMN n VARCHAR(100)`],
    ['change-column', (t) => `ALTER TABLE \`${t}\` CHANGE COLUMN n nn VARCHAR(50)`],
    ['add-index', (t) => `ALTER TABLE \`${t}\` ADD INDEX ix_n (n)`],
    ['add-unique', (t) => `ALTER TABLE \`${t}\` ADD UNIQUE uq_n (n)`],
    ['rename-table', (t) => `ALTER TABLE \`${t}\` RENAME TO \`${t}_r\``],
    ['add-check', (t) => `ALTER TABLE \`${t}\` ADD CONSTRAINT chk CHECK (id>=0)`],
    ['add-column-after', (t) => `ALTER TABLE \`${t}\` ADD COLUMN extra INT AFTER id`],
    ['add-column-first', (t) => `ALTER TABLE \`${t}\` ADD COLUMN extra INT FIRST`],
  ];
  for (const [nm, alter] of alterCases) {
    runner.add(FW, 'ddl-alter', nm, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, n VARCHAR(20))`, async (t) => {
        await q(alter(t));
        if (nm === 'rename-table') { try { await q(`DROP TABLE IF EXISTS \`${t}_r\``); } catch (_) {} }
      }));
  }
  // index DDL variants
  runner.add(FW, 'ddl-index', 'create-index-using-btree', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, a INT)`, async (t) => {
      await q(`CREATE INDEX ix ON \`${t}\` (a) USING BTREE`);
    }));
  runner.add(FW, 'ddl-index', 'create-index-plain', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, a INT)`, async (t) => {
      await q(`CREATE INDEX ix ON \`${t}\` (a)`);
    }));
  runner.add(FW, 'ddl-index', 'drop-index', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, a INT, INDEX ix(a))`, async (t) => {
      await q(`DROP INDEX ix ON \`${t}\``);
    }));

  // -------------------------------------------------------------------------
  // 4. DML scenarios
  // -------------------------------------------------------------------------
  const dml = (name, fn) => runner.add(FW, 'dml', name, fn);
  function dmlTable(body) {
    return withTable((t) => `CREATE TABLE \`${t}\` (id INT AUTO_INCREMENT PRIMARY KEY, n VARCHAR(50), v INT)`, body);
  }
  dml('insert-single', () => dmlTable(async (t) => { await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`); }));
  dml('insert-multi', () => dmlTable(async (t) => { await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1),('b',2),('c',3)`); }));
  dml('insert-select', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`INSERT INTO \`${t}\` (n,v) SELECT n,v FROM \`${t}\``);
  }));
  dml('insert-set', () => dmlTable(async (t) => { await q(`INSERT INTO \`${t}\` SET n='a', v=1`); }));
  dml('insert-ignore', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (id,n,v) VALUES (1,'a',1)`);
    await q(`INSERT IGNORE INTO \`${t}\` (id,n,v) VALUES (1,'b',2)`);
  }));
  dml('insert-on-dup-single', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (id,n,v) VALUES (1,'a',1)`);
    await q(`INSERT INTO \`${t}\` (id,n,v) VALUES (1,'b',2) ON DUPLICATE KEY UPDATE v=v+1`);
  }));
  dml('insert-on-dup-composite', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (a INT, b INT, v INT, UNIQUE KEY uq(a,b))`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,1,1)`);
      await q(`INSERT INTO \`${t}\` VALUES (1,1,2) ON DUPLICATE KEY UPDATE v=v+1`);
    }));
  dml('replace', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (id,n,v) VALUES (1,'a',1)`);
    await q(`REPLACE INTO \`${t}\` (id,n,v) VALUES (1,'b',2)`);
  }));
  dml('update-simple', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`UPDATE \`${t}\` SET v=2 WHERE n='a'`);
  }));
  dml('update-expr', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`UPDATE \`${t}\` SET v=v+10`);
  }));
  dml('update-order-limit', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1),('b',2)`);
    await q(`UPDATE \`${t}\` SET v=0 ORDER BY id LIMIT 1`);
  }));
  dml('delete-simple', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`DELETE FROM \`${t}\` WHERE n='a'`);
  }));
  dml('delete-order-limit', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1),('b',2)`);
    await q(`DELETE FROM \`${t}\` ORDER BY id LIMIT 1`);
  }));
  dml('delete-all', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`DELETE FROM \`${t}\``);
  }));
  dml('truncate', () => dmlTable(async (t) => {
    await q(`INSERT INTO \`${t}\` (n,v) VALUES ('a',1)`);
    await q(`TRUNCATE TABLE \`${t}\``);
  }));
  // BEHAVIOR: multi-table DELETE..JOIN deletes all rows (FINDINGS C3)
  dml('delete-join-behavior', async () => {
    const a = uniq('da'); const b = uniq('db');
    try {
      await q(`CREATE TABLE \`${a}\` (id INT PRIMARY KEY)`);
      await q(`CREATE TABLE \`${b}\` (id INT PRIMARY KEY)`);
      await q(`INSERT INTO \`${a}\` VALUES (1),(2)`);
      await q(`INSERT INTO \`${b}\` VALUES (1)`);
      await q(`DELETE x FROM \`${a}\` x JOIN \`${b}\` y ON x.id=y.id`);
      const rows = await q(`SELECT COUNT(*) c FROM \`${a}\``);
      if (Number(rows[0].c) !== 1) {
        throw new BehaviorMismatch(`DELETE..JOIN left ${rows[0].c} rows; MySQL leaves 1`);
      }
    } finally {
      await q(`DROP TABLE IF EXISTS \`${a}\``); await q(`DROP TABLE IF EXISTS \`${b}\``);
    }
  });

  // -------------------------------------------------------------------------
  // 5. QUERY pattern scenarios
  // -------------------------------------------------------------------------
  function q2Tables(body) {
    const a = uniq('qa'); const b = uniq('qb');
    return (async () => {
      try {
        await q(`CREATE TABLE \`${a}\` (id INT PRIMARY KEY, n VARCHAR(20), grp INT, amt DECIMAL(10,2))`);
        await q(`CREATE TABLE \`${b}\` (id INT PRIMARY KEY, aid INT, label VARCHAR(20))`);
        await q(`INSERT INTO \`${a}\` VALUES (1,'x',1,10.5),(2,'y',1,20.0),(3,'z',2,5.0)`);
        await q(`INSERT INTO \`${b}\` VALUES (1,1,'L1'),(2,1,'L2'),(3,2,'L3')`);
        return await body(a, b);
      } finally {
        await q(`DROP TABLE IF EXISTS \`${b}\``); await q(`DROP TABLE IF EXISTS \`${a}\``);
      }
    })();
  }
  const queries = [
    ['select-all', (a) => `SELECT * FROM \`${a}\``],
    ['select-where', (a) => `SELECT * FROM \`${a}\` WHERE grp=1`],
    ['select-distinct', (a) => `SELECT DISTINCT grp FROM \`${a}\``],
    ['select-limit', (a) => `SELECT * FROM \`${a}\` LIMIT 2`],
    ['select-limit-offset', (a) => `SELECT * FROM \`${a}\` LIMIT 1 OFFSET 1`],
    ['select-order', (a) => `SELECT * FROM \`${a}\` ORDER BY amt DESC`],
    ['select-count', (a) => `SELECT COUNT(*) c FROM \`${a}\``],
    ['select-sum', (a) => `SELECT SUM(amt) s FROM \`${a}\``],
    ['select-avg', (a) => `SELECT AVG(amt) s FROM \`${a}\``],
    ['select-min-max', (a) => `SELECT MIN(amt) mn, MAX(amt) mx FROM \`${a}\``],
    ['group-by', (a) => `SELECT grp, COUNT(*) c FROM \`${a}\` GROUP BY grp`],
    ['group-having', (a) => `SELECT grp, COUNT(*) c FROM \`${a}\` GROUP BY grp HAVING c>1`],
    ['group-concat', (a) => `SELECT grp, GROUP_CONCAT(n) g FROM \`${a}\` GROUP BY grp`],
    ['inner-join', (a, b) => `SELECT * FROM \`${a}\` x JOIN \`${b}\` y ON x.id=y.aid`],
    ['left-join', (a, b) => `SELECT * FROM \`${a}\` x LEFT JOIN \`${b}\` y ON x.id=y.aid`],
    ['right-join', (a, b) => `SELECT * FROM \`${a}\` x RIGHT JOIN \`${b}\` y ON x.id=y.aid`],
    ['cross-join', (a, b) => `SELECT * FROM \`${a}\` x CROSS JOIN \`${b}\` y`],
    ['self-join', (a) => `SELECT x.id FROM \`${a}\` x JOIN \`${a}\` y ON x.grp=y.grp`],
    ['subquery-scalar', (a) => `SELECT (SELECT MAX(amt) FROM \`${a}\`) m`],
    ['subquery-in', (a, b) => `SELECT * FROM \`${a}\` WHERE id IN (SELECT aid FROM \`${b}\`)`],
    ['subquery-exists', (a, b) => `SELECT * FROM \`${a}\` x WHERE EXISTS (SELECT 1 FROM \`${b}\` y WHERE y.aid=x.id)`],
    ['subquery-correlated', (a, b) => `SELECT x.*, (SELECT COUNT(*) FROM \`${b}\` y WHERE y.aid=x.id) c FROM \`${a}\` x`],
    ['derived-table', (a) => `SELECT * FROM (SELECT grp, COUNT(*) c FROM \`${a}\` GROUP BY grp) d WHERE d.c>0`],
    ['union', (a) => `SELECT id FROM \`${a}\` UNION SELECT id FROM \`${a}\``],
    ['union-all', (a) => `SELECT id FROM \`${a}\` UNION ALL SELECT id FROM \`${a}\``],
    ['intersect', (a) => `SELECT id FROM \`${a}\` INTERSECT SELECT id FROM \`${a}\``],
    ['except', (a) => `SELECT id FROM \`${a}\` EXCEPT SELECT id FROM \`${a}\``],
    ['cte', (a) => `WITH c AS (SELECT * FROM \`${a}\` WHERE grp=1) SELECT * FROM c`],
    ['cte-recursive', () => `WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n<5) SELECT * FROM seq`],
    ['window-rownum', (a) => `SELECT id, ROW_NUMBER() OVER (ORDER BY amt) rn FROM \`${a}\``],
    ['window-rank', (a) => `SELECT id, RANK() OVER (PARTITION BY grp ORDER BY amt) rk FROM \`${a}\``],
    ['window-sum', (a) => `SELECT id, SUM(amt) OVER (PARTITION BY grp) s FROM \`${a}\``],
    ['window-lag', (a) => `SELECT id, LAG(amt) OVER (ORDER BY id) p FROM \`${a}\``],
    ['window-ntile', (a) => `SELECT id, NTILE(2) OVER (ORDER BY amt) nt FROM \`${a}\``],
    ['case-when', (a) => `SELECT id, CASE WHEN amt>10 THEN 'hi' ELSE 'lo' END b FROM \`${a}\``],
    ['where-like', (a) => `SELECT * FROM \`${a}\` WHERE n LIKE 'x%'`],
    ['where-in', (a) => `SELECT * FROM \`${a}\` WHERE grp IN (1,2)`],
    ['where-between', (a) => `SELECT * FROM \`${a}\` WHERE amt BETWEEN 0 AND 100`],
    ['where-is-null', (a) => `SELECT * FROM \`${a}\` WHERE n IS NOT NULL`],
    ['order-multi', (a) => `SELECT * FROM \`${a}\` ORDER BY grp ASC, amt DESC`],
    ['lock-for-update', (a) => `SELECT * FROM \`${a}\` WHERE id=1 FOR UPDATE`],
    ['lock-share-mode', (a) => `SELECT * FROM \`${a}\` WHERE id=1 LOCK IN SHARE MODE`],
  ];
  for (const [nm, build] of queries) {
    runner.add(FW, 'query', nm, () => q2Tables(async (a, b) => { await q(build(a, b)); }));
  }

  // -------------------------------------------------------------------------
  // 6. TRANSACTION scenarios (use dedicated connections, not the pool query)
  // -------------------------------------------------------------------------
  async function withConn(body) {
    const conn = await getPool().getConnection();
    try { return await body(conn); } finally { conn.release(); }
  }
  runner.add(FW, 'tx', 'commit', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, (t) => withConn(async (c) => {
      await c.beginTransaction();
      await c.query(`INSERT INTO \`${t}\` VALUES (1)`);
      await c.commit();
      const [r] = await c.query(`SELECT COUNT(*) c FROM \`${t}\``);
      expect(Number(r[0].c), 1, 'committed row count');
    })));
  runner.add(FW, 'tx', 'rollback', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, (t) => withConn(async (c) => {
      await c.beginTransaction();
      await c.query(`INSERT INTO \`${t}\` VALUES (1)`);
      await c.rollback();
      const [r] = await c.query(`SELECT COUNT(*) c FROM \`${t}\``);
      expect(Number(r[0].c), 0, 'rolled-back row count');
    })));
  runner.add(FW, 'tx', 'savepoint-create', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, (t) => withConn(async (c) => {
      await c.beginTransaction();
      await c.query(`INSERT INTO \`${t}\` VALUES (1)`);
      await c.query('SAVEPOINT sp1');
      await c.query('RELEASE SAVEPOINT sp1');
      await c.commit();
    })));
  runner.add(FW, 'tx', 'rollback-to-savepoint', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, (t) => withConn(async (c) => {
      await c.beginTransaction();
      await c.query(`INSERT INTO \`${t}\` VALUES (1)`);
      await c.query('SAVEPOINT sp1');
      await c.query(`INSERT INTO \`${t}\` VALUES (2)`);
      await c.query('ROLLBACK TO SAVEPOINT sp1'); // FINDINGS C5: unimplemented
      await c.commit();
    })));
  runner.add(FW, 'tx', 'set-isolation-rr', () => withConn(async (c) => {
    await c.query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  }));
  runner.add(FW, 'tx', 'set-isolation-serializable', () => withConn(async (c) => {
    await c.query('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
  }));
  runner.add(FW, 'tx', 'autocommit-off-on', () => withConn(async (c) => {
    await c.query('SET autocommit=0'); await c.query('SET autocommit=1');
  }));

  // -------------------------------------------------------------------------
  // 7. PREPARED STATEMENT (binary protocol) scenarios
  // -------------------------------------------------------------------------
  runner.add(FW, 'prepared', 'execute-select-param', async () => {
    const rows = await exec('SELECT ? + ? AS s', [2, 3]);
    expect(Number(rows[0].s), 5, 'prepared add');
  });
  runner.add(FW, 'prepared', 'execute-limit-param', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1),(2),(3)`);
      const rows = await exec(`SELECT * FROM \`${t}\` ORDER BY id LIMIT ?`, [2]);
      expect(rows.length, 2, 'prepared limit');
    }));
  runner.add(FW, 'prepared', 'execute-in-clause', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1),(2),(3)`);
      await exec(`SELECT * FROM \`${t}\` WHERE id IN (?,?)`, [1, 2]);
    }));
  // prepared typed roundtrip for a subset of types
  for (const ty of SQL_TYPES.filter((t) => !t.skipValue && ['int', 'num', 'str', 'dt'].includes(t.group)).slice(0, 14)) {
    runner.add(FW, 'prepared-type', `execute ${ty.sql}`, () =>
      withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, c ${ty.sql})`, async (t) => {
        await exec(`INSERT INTO \`${t}\` (id,c) VALUES (?,?)`, [1, ty.sample]);
        await exec(`SELECT c FROM \`${t}\` WHERE id=?`, [1]);
      }));
  }
  // statements known to be hard on binary protocol (FINDINGS H5)
  for (const stmt of ['DESCRIBE information_schema.tables', 'EXPLAIN SELECT 1', 'SHOW VARIABLES LIKE "version"', 'SHOW COLLATION', 'SHOW WARNINGS', 'SET @v := 1']) {
    runner.add(FW, 'prepared-meta', `prepare ${stmt.split(' ')[0]}-${stmt.length}`, async () => {
      await exec(stmt);
    });
  }

  // -------------------------------------------------------------------------
  // 8. BEHAVIOR / SEMANTICS scenarios (ran-ok-but-wrong-vs-MySQL)
  // -------------------------------------------------------------------------
  const beh = (name, fn) => runner.add(FW, 'behavior', name, fn);
  beh('case-insensitive-eq', async () => {
    const r = await q("SELECT ('abc'='ABC') AS x");
    if (Number(r[0].x) !== 1) throw new BehaviorMismatch("'abc'='ABC' returned 0 (utf8mb4_bin); MySQL returns 1");
  });
  beh('accent-insensitive-eq', async () => {
    const r = await q("SELECT ('café'='cafe') AS x");
    if (Number(r[0].x) !== 1) throw new BehaviorMismatch("'café'='cafe' returned 0; MySQL ai_ci returns 1");
  });
  beh('trailing-space-eq', async () => {
    const r = await q("SELECT ('a '='a') AS x");
    if (Number(r[0].x) !== 1) throw new BehaviorMismatch("'a '='a' returned 0; MySQL returns 1");
  });
  beh('like-case-insensitive', async () => {
    const r = await q("SELECT ('abc' LIKE 'ABC') AS x");
    if (Number(r[0].x) !== 1) throw new BehaviorMismatch("'abc' LIKE 'ABC' returned 0; MySQL returns 1");
  });
  beh('collate-ci-ignored', async () => {
    const r = await q("SELECT ('abc'='ABC' COLLATE utf8mb4_general_ci) AS x");
    if (Number(r[0].x) !== 1) throw new BehaviorMismatch('explicit _ci collation ignored; still case-sensitive');
  });
  beh('pipe-is-concat', async () => {
    const r = await q('SELECT (1||0) AS x');
    if (String(r[0].x) !== '1') throw new BehaviorMismatch(`1||0 => ${JSON.stringify(r[0].x)} (concat); MySQL OR => 1`);
  });
  beh('last-insert-id-multirow', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT AUTO_INCREMENT PRIMARY KEY, n INT)`, async (t) => {
      await q(`INSERT INTO \`${t}\` (n) VALUES (1),(2),(3)`);
      const r = await q('SELECT LAST_INSERT_ID() x');
      if (Number(r[0].x) !== 1) throw new BehaviorMismatch(`LAST_INSERT_ID()=${r[0].x} after multi-row; MySQL=1 (first)`);
    }));
  beh('check-constraint-enforced', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, age INT CHECK (age>=0))`, async (t) => {
      try {
        await q(`INSERT INTO \`${t}\` VALUES (1,-5)`);
      } catch (e) {
        return; // good: rejected like MySQL
      }
      throw new BehaviorMismatch('CHECK (age>=0) accepted -5; MySQL 8 rejects');
    }));
  beh('unique-case-sensitivity', () =>
    withTable((t) => `CREATE TABLE \`${t}\` (id INT PRIMARY KEY, e VARCHAR(50) UNIQUE)`, async (t) => {
      await q(`INSERT INTO \`${t}\` VALUES (1,'user@x.com')`);
      try {
        await q(`INSERT INTO \`${t}\` VALUES (2,'USER@X.COM')`);
      } catch (e) {
        throw new BehaviorMismatch('UNIQUE rejected case-variant; MatrixOne should accept (case-sensitive)');
      }
      // both rows accepted => case-sensitive unique (a MatrixOne behavior)
      throw new BehaviorMismatch("UNIQUE accepted both 'user@x.com' and 'USER@X.COM' (case-sensitive)");
    }));
  beh('string-arith-coercion', async () => {
    const r = await q("SELECT ('10abc'+5) AS x");
    if (Number(r[0].x) !== 15) throw new BehaviorMismatch(`'10abc'+5 => ${r[0].x}; MySQL warns and returns 15`);
  });
  beh('cast-bad-unsigned', async () => {
    const r = await q("SELECT CAST('abc' AS UNSIGNED) AS x");
    if (Number(r[0].x) !== 0) throw new BehaviorMismatch(`CAST('abc' AS UNSIGNED) => ${r[0].x}; MySQL warns and returns 0`);
  });
  beh('division-by-zero', async () => {
    const r = await q('SELECT (1/0) AS x');
    if (r[0].x !== null) throw new BehaviorMismatch(`1/0 => ${JSON.stringify(r[0].x)}; MySQL returns NULL`);
  });
}

async function teardown() {
  if (pool) { await pool.end(); pool = null; }
}

module.exports = { register, teardown };
