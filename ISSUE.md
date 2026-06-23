# MatrixOne ⇆ PHP ORM compatibility report (4.0.0-rc3, 2324 scenarios, 2104 pass / 220 fail)

A compatibility study of the most popular PHP database/ORM stacks against **MatrixOne 4.0.0-rc3** (MySQL-wire-compatible). 2324 isolated scenarios were executed; this issue summarises the findings by severity.

## Environment & method

| | |
|---|---|
| **Target engine** | MatrixOne `v4.0.0-rc3` (`version()` = `8.0.30-MatrixOne-v4.0.0-rc3`) |
| **Frameworks** | Raw **PDO** baseline · **Laravel Eloquent 12** · **Doctrine ORM 3 / DBAL 4** · **CakePHP 5** |
| **Result** | 2324 scenarios — **2104 pass / 220 fail** (90.5%) |

### Pass rate by framework

| Framework | Total | Pass | Fail | Pass % |
|---|--:|--:|--:|--:|
| Eloquent | 334 | 323 | 11 | 96.7% |
| CakePHP | 136 | 130 | 6 | 95.6% |
| Doctrine | 237 | 221 | 16 | 93.2% |
| PDO (raw SQL baseline) | 1617 | 1430 | 187 | 88.4% |

### Failures by signature

| Signature | Count | Meaning |
|---|--:|---|
| `BEHAVIOR` | 75 | runs without error but result differs from MySQL semantics |
| `1064` | 49 | SQL syntax / feature not supported by the parser |
| `20105` | 45 | function / operator not implemented |
| `20101` | 19 | internal "not implemented yet" |
| `20203` | 16 | stricter argument/type validation than MySQL |
| `20301` | 6 | column/identifier resolution differences |
| `1690` | 3 | numeric out-of-range on cast |
| other | 7 | protocol errors, `1062`, `20102`, `1149`, … |

> Version 3.0.15 was 868/134 over 1002 scenarios; 4.0.0-rc3 fixed 46 of those with zero regressions (UUID `type 243` driver crash, generated columns, `LEAST`/`GREATEST`, `STDDEV`/`VAR_SAMP`, many string/date functions, `SELECT FOR UPDATE`, partial spatial). The critical correctness/semantic bugs below remain.

---

## 🔴 Critical — silent data-integrity / wrong-result issues

These produce **no error** (or only in a corner) yet change data or results — the most dangerous when porting a MySQL app.

### C1. Default collation is binary/case-sensitive (`utf8mb4_bin`)
Columns default to `utf8_bin`/`utf8mb4_bin` (`@@collation_server = utf8mb4_bin`). MySQL 8 defaults to case- and accent-**insensitive** `utf8mb4_0900_ai_ci`.

```sql
SELECT 'abc' = 'ABC';   -- MySQL: 1   MatrixOne: 0
SELECT 'café' = 'cafe'; -- MySQL: 1   MatrixOne: 0
SELECT 'a ' = 'a';      -- MySQL: 1   MatrixOne: 0  (trailing space)
'abc' LIKE 'ABC'        -- MySQL: 1   MatrixOne: 0
```
A `UNIQUE` column accepts both `'abc'` and `'ABC'`; `WHERE email = 'User@X.com'` won't match a stored `user@x.com`. **The usual fix is ignored** — `… COLLATE utf8mb4_general_ci` is still case-sensitive in comparisons, `WHERE`, `GROUP BY` and `DISTINCT`. Only `LOWER()`/`UPPER()` on both sides works.

### C2. Bare `FLOAT` corrupts data — `3.5` is stored as `4.0`
```sql
CREATE TABLE t (c FLOAT);
INSERT INTO t VALUES (3.5);
SELECT c FROM t;        -- 4.0  (the fraction is lost; FLOAT(7,4) is fine)
```
Workaround: use `DOUBLE` or `DECIMAL(p,s)`.

### C3. Multi-table `DELETE … JOIN` deletes **all** rows
```sql
-- d1 = {(1,1),(2,2)}, d2 = {(1)}
DELETE a FROM d1 a JOIN d2 b ON a.id = b.id;
-- MySQL: deletes only id=1.   MatrixOne: empties the table.
```

### C4. `CHECK` constraints are parsed but **not enforced**
```sql
CREATE TABLE t (age INT CHECK (age >= 0));
INSERT INTO t VALUES (-5);   -- accepted (MySQL 8.0.16+ rejects)
```

### C5. Nested transactions silently lose isolation
`SAVEPOINT` can be issued but `ROLLBACK TO SAVEPOINT` is **not implemented** (`20101`). Eloquent and Doctrine implement nested `transaction()` with savepoints, so an inner rollback does **not** undo inner work — the outer transaction commits it.

### C6. `LAST_INSERT_ID()` after a multi-row insert returns the **last** id
```sql
INSERT INTO t(n) VALUES (1),(2),(3);
SELECT LAST_INSERT_ID();     -- MySQL: 1 (first row)   MatrixOne: 3 (last row)
```

### C7. `||` is string concatenation, not logical OR
```sql
SELECT 1 || 0;               -- MySQL: 1 (OR)   MatrixOne: '10' (concat)
```

### C8. `ON DUPLICATE KEY UPDATE` on a composite UNIQUE throws `1062` instead of updating
The upsert raises `Duplicate entry` rather than performing the update — breaks Eloquent/Doctrine upserts against composite unique keys.

---

## 🟠 High — ORM tooling broken

MatrixOne ships 24 of MySQL 8's information_schema tables; several relied on by ORM tooling are **absent** (`check_constraints`, `collation_character_set_applicability`, `table_options`, `column_statistics`, …).

- **H1 — Doctrine introspection & Migrations broken:** `listTables()` / `introspectTable()` / schema-comparator join `information_schema.collation_character_set_applicability` → `1064 … does not exist`. Breaks Doctrine Migrations and schema-diff.
- **H2 — CakePHP ORM reflection broken:** `Collection::describe()` queries `information_schema.CHECK_CONSTRAINTS` (missing) → `1064`. CakePHP auto-describes every table, so the ORM is unusable until this exists (the query-builder layer is fine).
- **H3 — Doctrine `float` emits `DOUBLE PRECISION`**, rejected by MatrixOne; only `DOUBLE` is accepted.
- **H4 — Many statements can't run via the binary prepared-statement protocol** (`ATTR_EMULATE_PREPARES=false`): `DESCRIBE`, `EXPLAIN`, `SET @v=…`, `SHOW COLLATION/WARNINGS`, `SHOW VARIABLES LIKE …` (all preparable in MySQL 8; work via the text protocol).
- **H5 — Eloquent JSON queries fail:** the JSON manipulation/query function family is unimplemented (see M1), so `whereJsonContains`/`whereJsonDoesntContain`/`whereJsonLength` paths error.
- **H6 — Single-table introspection metadata is inaccurate:** `information_schema.COLUMNS` returns `NULL` for varchar `CHARACTER_MAXIMUM_LENGTH` and decimal `NUMERIC_PRECISION`/`SCALE`; `DATA_TYPE` is upper-cased; TEXT `CHARACTER_MAXIMUM_LENGTH = 0`; `listTableForeignKeys` returns no FKs for a table created with one. Any ORM that maps types from introspection gets wrong metadata.

---

## 🟡 Medium — unsupported features

### M1. ~18 built-in functions still not implemented (`20105`)
`JSON_CONTAINS`, `JSON_CONTAINS_PATH`, `JSON_DEPTH`, `JSON_REMOVE`, `JSON_MERGE_PATCH`, `JSON_MERGE_PRESERVE`, `JSON_ARRAY_APPEND`, `JSON_ARRAY_INSERT`, `JSON_SEARCH`, `JSON_OVERLAPS`, `JSON_STORAGE_SIZE`, `CHARACTER_LENGTH`, `COERCIBILITY`, `BENCHMARK`, `WEIGHT_STRING`, `UUID_SHORT`, `GEOMFROMTEXT` (alias), `l1_distance`.

### M2. Other DDL / DML / query gaps
- `EXCEPT ALL` / `INTERSECT ALL` unimplemented (`20102`); `SAVEPOINT … ROLLBACK TO` unimplemented (`20101`).
- Locking reads `FOR SHARE`, `LOCK IN SHARE MODE`, `SKIP LOCKED`, `NOWAIT` → `1064`.
- Named `WINDOW` clause → `1064`; `RANK()` tie semantics differ.
- `CREATE INDEX … USING BTREE`, `ALTER … ADD … CHECK`, spatial functions/indexes, generated-column edge cases → parser/internal errors.
- `CAST/CONVERT(x AS CHAR(n))` errors on truncation; `CAST AS NCHAR` / `… CHARACTER SET` → `1064`; `CAST(-1 AS UNSIGNED)` / `~0` → `1690` out of range.
- DDL does **not** trigger an implicit commit; `START TRANSACTION READ ONLY` is not enforced.
- `MEMBER OF`, `JSON_TABLE` → parser errors; **stored procedures / triggers** unimplemented; `FULLTEXT` requires a primary key.
- `SHOW VARIABLES`/`STATUS`/`ENGINES`/`CHARACTER SET` return 0 rows; `SHOW VARIABLES` can return a malformed packet.
- Doctrine `Criteria::setMaxResults()` via `matching()` omits the `LIMIT` clause on this platform.

---

## ✅ What works well

- **Eloquent (323/334):** schema builder for all column types & modifiers (incl. generated columns now), full query builder (joins/unions/subqueries/aggregates/pagination/chunking/`upsert`), models with casts/accessors/scopes/soft-deletes, and **every relationship kind** incl. `hasManyThrough`/`morphToMany`, relationship aggregates, eager loading.
- **Doctrine ORM (221/237):** entities, DQL (where/join/aggregate/functions/`NEW` DTO/`UPDATE`/`DELETE`), associations incl. `OneToOne`, lifecycle callbacks, repository + most `Criteria`, DBAL query-builder full `expr()` surface, typed round-trips, platform DDL generation.
- **CakePHP (130/136):** query builder, expressions/`func()`, typed binding, `TableSchema` DDL, transactions.
- **Core SQL:** DDL for ~47 types, DML incl. `ON DUPLICATE KEY`/`REPLACE`/`INSERT IGNORE`, all join kinds, subqueries, **CTEs incl. recursive**, `UNION`/`INTERSECT`/`EXCEPT`, **window functions** (frames/partitions), constraint enforcement (PK/FK/UNIQUE/NOT NULL/DEFAULT), commit/rollback, prepared-statement **typed binding for every type** incl. `LIMIT ?`.
- **MatrixOne extras:** vector columns (`VECF32`/`VECF64`), `IVFFLAT` + KNN (`l2_distance`/`cosine_distance`), full-text `MATCH … AGAINST`.

---

## Recommendations

**Highest-impact fixes for MatrixOne**
1. Honour `_ci` collations (or change the default) — C1 is the single biggest porting hazard.
2. Implement `ROLLBACK TO SAVEPOINT` — unblocks nested transactions in every ORM.
3. Add `information_schema.collation_character_set_applicability` and `check_constraints`, and return accurate column metadata (H1/H2/H6) — unblocks Doctrine Migrations and the CakePHP ORM.
4. Fix bare-`FLOAT` rounding (C2), multi-table `DELETE … JOIN` data loss (C3), and `ON DUPLICATE KEY` on composite unique (C8).
5. Enforce `CHECK` constraints (C4).
6. Implement the JSON manipulation function family (M1) — unblocks Eloquent JSON queries.

**Application rules of thumb:** never use bare `FLOAT`; normalise case with `LOWER()` (+ functional indexes); don't rely on `CHECK` or nested-transaction rollback; avoid multi-table `DELETE … JOIN`; don't derive id ranges from `LAST_INSERT_ID()` after batch inserts.

---

*Generated by a 2324-scenario compatibility suite (raw PDO + Eloquent 12 + Doctrine ORM 3/DBAL 4 + CakePHP 5) run against MatrixOne 4.0.0-rc3.*
