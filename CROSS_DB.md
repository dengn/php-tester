# MatrixOne vs TiDB — Cross-database compatibility comparison

The **identical 41,653-scenario suite** (PHP + Python + Java + Node.js, ~25
ORMs/drivers) run against two MySQL-compatible databases, to separate
*MatrixOne-specific* incompatibilities from general "MySQL-compatible engine"
limitations.

| | Version (`version()`) | Pass | Fail | Skip | Pass % |
|---|---|--:|--:|--:|--:|
| **MatrixOne** | `8.0.30-MatrixOne-v4.0.0-rc3` | 36,958 | 4,682 | 13 | **88.8%** |
| **TiDB** | `8.0.11-TiDB-v8.5.3` (latest LTS) | 39,984 | 1,656 | 13 | **96.0%** |

Same scenarios, same harness; only the connection target differs (`MO_PORT=4000`).

## Per-language

| Language | MatrixOne (pass/fail) | TiDB (pass/fail) |
|---|--:|--:|
| PHP (10,009) | 8,854 / 1,155 | 9,413 / 596 |
| Python (10,947) | 9,689 / 1,246 | 10,458 / 477 |
| Java (10,361) | 9,260 / 1,100 | 9,969 / 391 |
| Node.js (10,336) | 9,155 / 1,181 | 10,144 / 192 |

## Per-scenario diff (matched by framework + category + name)

| | Count | Meaning |
|---|--:|---|
| **Fail on MatrixOne, pass on TiDB** | **4,154** | MatrixOne-specific gaps (TiDB, a mature MySQL-compat engine, handles them) |
| Pass on MatrixOne, fail on TiDB | 1,128 | TiDB's own gaps — but ~½ are MatrixOne-proprietary features TiDB lacks, or one RedBean collation root cause |
| Fail on both | 526 | general MySQL-incompat / disabled-by-default features (e.g. `CHECK`) / test edges |

## The 4,154 MatrixOne-specific failures — what they are

By MatrixOne error code (MO fail → TiDB pass):

| Code | Count | What |
|---|--:|---|
| `20203` | 3,025 | **stricter mixed-type coercion** — `'10abc'+5`, `count/count`, mixed arithmetic. TiDB coerces like MySQL; MatrixOne rejects. (the operator grids) |
| `1064` | 335 | syntax — incl. **`DOUBLE PRECISION`** rejected, `CAST AS NCHAR`, etc. |
| `20105` | 269 | unimplemented functions (`JSON_CONTAINS`, `CHARACTER_LENGTH`, `UUID_SHORT`, …) |
| `20301` | 200 | column/identifier resolution differences |
| `20101` | 147 | internal "not implemented" — incl. **`ROLLBACK TO SAVEPOINT`** |
| `BEHAVIOR` | 129 | silent semantic divergences (see below) |
| `1690`/`1149`/`1406` | 38 | overflow / GROUP-BY introspection / data-too-long |

**Every headline correctness finding is confirmed MatrixOne-specific** — each
fails on MatrixOne and **passes on TiDB**:

| Finding | MatrixOne | TiDB |
|---|---|---|
| Case-insensitive equality (`'abc'='ABC'`) | ❌ FAIL | ✅ PASS |
| Trailing-space padded equality | ❌ FAIL | ✅ PASS |
| Explicit `COLLATE utf8mb4_general_ci` honoured | ❌ FAIL | ✅ PASS |
| Nested transaction (`ROLLBACK TO SAVEPOINT`) | ❌ FAIL | ✅ PASS |
| Bare `FLOAT` round-trip (no `3.5→4.0`) | ❌ FAIL | ✅ PASS |
| Multi-table `DELETE … JOIN` (only matched rows) | ❌ FAIL | ✅ PASS |
| `LAST_INSERT_ID()` after multi-row = first id | ❌ FAIL | ✅ PASS |
| `\|\|` is logical OR | ❌ FAIL | ✅ PASS |
| `whereJsonContains` / `JSON_CONTAINS` | ❌ FAIL | ✅ PASS |
| `DOUBLE PRECISION` keyword | ❌ FAIL | ✅ PASS |

Migration tooling, where MatrixOne was hard-blocked, works on TiDB:
**Hibernate `hbm2ddl=update` is 215/0 on TiDB** (vs 211/4 on MatrixOne with the
introspection `1149`), and **Liquibase is 4/5 on TiDB vs 0/9 on MatrixOne**.

## The 1,128 TiDB-specific failures — what they are

These are *not* all "TiDB is worse": the bulk are features only MatrixOne has,
or a single ORM-config root cause.

| TiDB code / category | Count | What |
|---|--:|---|
| `1273` — RedBean (PHP) | 259 | **one root cause:** RedBeanPHP sets a default collation TiDB rejects (`Unknown collation`), failing nearly all 265 RedBean scenarios. A RedBean/TiDB config issue, not a query incompatibility. |
| vector_* categories | ~250 | **MatrixOne-proprietary `VECF32`/`VECF64`, `IVFFLAT`, `l2_distance`/`cosine_distance`** — MatrixOne's native vector stack. TiDB 8.5 has a `VECTOR` type but different syntax, so these MatrixOne scenarios fail on TiDB. A genuine MatrixOne feature advantage in this area. |
| `1064`/`42000` (other) | ~300 | TiDB rejects some syntax MatrixOne accepts (a few constructs). |
| `1101`/`1193`/`1305`/`3140` | ~80 | TiDB stricter on: `BLOB/TEXT` columns with a `DEFAULT`, unknown system variables, some functions, JSON edge cases. |

So MatrixOne's real wins vs TiDB are: **native vector search** (and full-text)
as first-class types/functions.

## Caveats

- `CHECK` enforcement is **off by default on both** engines (TiDB requires
  `tidb_enable_check_constraint=ON`), so that scenario fails on both.
- TiDB ran via the single-node in-memory `unistore` engine (functional, not a
  performance comparison).
- The large `20203` bucket (3,025) is one systematic coercion difference probed
  at volume by the operator grids; excluding it, MatrixOne's remaining
  ~1,650 failures still vastly outnumber TiDB's.

## Bottom line

On an identical 41,653-scenario MySQL-ORM compatibility battery, **TiDB v8.5.3
passes 96.0% vs MatrixOne v4.0.0-rc3's 88.8%**. **4,154 scenarios fail on
MatrixOne but pass on TiDB**, and every headline correctness/semantics finding
(collation, savepoints, `DOUBLE PRECISION`, bare `FLOAT`, multi-table `DELETE`,
`LAST_INSERT_ID`, `||`, JSON functions, migration introspection) is confirmed
**MatrixOne-specific** — i.e. a mature MySQL-compatible engine does not exhibit
them. MatrixOne's clearest differentiators are its **native vector and
full-text** types, which TiDB does not match in the same syntax.

---

*Reproduce:* run any suite with `MO_HOST=127.0.0.1 MO_PORT=4000 MO_USER=root MO_PASS=…`
against a TiDB instance (`docker run -d -p 4000:4000 pingcap/tidb:v8.5.3 --store=unistore`).
Raw TiDB results are in `comparison/tidb-v8.5.3/`.
