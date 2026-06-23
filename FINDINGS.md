# MatrixOne ⇆ PHP ORM Compatibility Findings

A compatibility study of the most popular PHP database/ORM stacks against
**MatrixOne** (MySQL-wire-compatible). 1002 scenarios were executed; this
document curates the findings by severity. The raw, machine-readable results are
in [`reports/results.json`](reports/results.json) and the auto-generated tables
in [`reports/report.md`](reports/report.md).

## Environment & method

| | |
|---|---|
| **Target engine** | MatrixOne `v3.0.15` (reports `version()` = `8.0.30-MatrixOne-v3.0.15`) |
| **Wire protocol** | MySQL 8.0.30 |
| **Frameworks** | Raw **PDO** baseline · **Laravel Eloquent 12** · **Doctrine ORM 3 / DBAL 4** · **CakePHP 5** database layer |
| **Scenarios** | 1002 total — **868 pass / 134 fail** (86.6% pass) |

> **Note on the target.** The managed endpoint in the request
> (`database.omni.ruh.cloudsigma.com:6001`) is **not reachable from this
> execution environment**: the environment's egress policy only permits ports
> 80/443, and a CONNECT tunnel to 6001 is denied (`proxy_ip_not_allowed`).
> Testing was therefore run against a **local MatrixOne `v3.0.15` container**
> (the same engine). Every connection value is overridable via environment
> variables (`MO_HOST`, `MO_PORT`, `MO_USER`, `MO_PASS`), so the identical suite
> can be re-run against the managed instance the moment egress to 6001 is
> allowed:
>
> ```bash
> MO_HOST=database.omni.ruh.cloudsigma.com MO_PORT=6001 \
> MO_USER='019db2db-5642-7112-ba4f-339ceded17e9:admin:accountadmin' \
> MO_PASS='Test@2026' php run.php
> ```

### Pass rate by framework

| Framework | Pass | Fail | Pass % |
|---|--:|--:|--:|
| Eloquent | 126 | 6 | 95.5% |
| CakePHP | 27 | 1 | 96.4% |
| Doctrine | 60 | 6 | 90.9% |
| PDO (raw SQL baseline) | 655 | 121 | 84.4% |

### Failures by signature

| Error | Count | Meaning |
|---|--:|---|
| `20105` | 60 | function / operator not implemented |
| `1064` | 30 | SQL syntax / feature not supported by the parser |
| `BEHAVIOR` | 21 | runs without error but result differs from MySQL semantics |
| `20101` | 11 | internal "not implemented yet" |
| `20203` | 6 | stricter argument/type validation than MySQL |
| protocol | 6 | driver-level errors (unknown column type, malformed packet) |

---

## 🔴 Critical — silent data-integrity / wrong-result issues

These produce **no error** (or an error only in a corner) yet change data or
results. They are the most dangerous when porting a MySQL app.

### C1. Default collation is binary/case-sensitive (`utf8mb4_bin`)
MatrixOne creates `VARCHAR`/`TEXT` columns with `utf8_bin` / `utf8mb4_bin` by
default (`@@collation_server = utf8mb4_bin`). MySQL 8's default is
`utf8mb4_0900_ai_ci` — **case- and accent-insensitive**. Consequences:

```sql
SELECT 'abc' = 'ABC';          -- MySQL: 1     MatrixOne: 0
SELECT 'café' = 'cafe';        -- MySQL: 1     MatrixOne: 0
SELECT 'a ' = 'a';             -- MySQL: 1     MatrixOne: 0   (trailing space)
'abc' LIKE 'ABC'               -- MySQL: 1     MatrixOne: 0
```

A `UNIQUE` column accepts both `'abc'` and `'ABC'`; `WHERE email = 'User@X.com'`
will **not** match a stored `user@x.com`. This silently breaks logins,
de-duplication, and any case-insensitive lookup ported from MySQL.

**The usual fix does not work:** explicitly requesting a `_ci` collation is
**ignored** —

```sql
SELECT 'abc' = 'ABC' COLLATE utf8mb4_general_ci;   -- still 0
CREATE TABLE t (c VARCHAR(20) COLLATE utf8mb4_general_ci); -- still case-sensitive
```

**Workaround that does work:** `LOWER()`/`UPPER()` on both sides of every
comparison — invasive, and unindexed unless you add functional indexes.

### C2. Bare `FLOAT` corrupts data — `3.5` is stored as `4.0`
```sql
CREATE TABLE t (c FLOAT);
INSERT INTO t VALUES (3.5);
SELECT c FROM t;               -- 4.0   (the fraction is lost!)
```
Both literal and bound inserts are affected. **Workaround:** use `DOUBLE` or
`DECIMAL(p,s)`; never a precision-less `FLOAT`. (Note: Doctrine's `float` type
maps to `DOUBLE PRECISION`, which MatrixOne rejects — see H3.)

### C3. Multi-table `DELETE … JOIN` deletes **all** rows
```sql
-- d1 = {(1,1),(2,2)}, d2 = {(1)}
DELETE a FROM d1 a JOIN d2 b ON a.id = b.id;
-- MySQL: deletes only id=1, leaving 1 row.  MatrixOne: table is emptied.
```
A data-loss bug for the common "delete rows that have a match in another table"
pattern.

### C4. `CHECK` constraints are parsed but **not enforced**
```sql
CREATE TABLE t (age INT CHECK (age >= 0));
INSERT INTO t VALUES (-5);     -- accepted (MySQL 8.0.16+ rejects it)
```
Any data-validation relying on `CHECK` is a silent no-op.

### C5. Nested transactions silently lose their isolation
`SAVEPOINT` can be issued but `ROLLBACK TO SAVEPOINT` is **not implemented**
(`20101 … savepoint has not been implemented yet`). Both Eloquent and Doctrine
implement nested `transaction()` calls with savepoints, so an inner rollback
**does not undo the inner work** — the outer transaction still commits it:

```php
DB::transaction(function () {
    User::create(['name' => 'Outer']);
    try {
        DB::transaction(function () {          // SAVEPOINT
            User::create(['name' => 'Inner']);
            throw new RuntimeException();        // expected: undo "Inner"
        });
    } catch (Throwable) {}
});
// MySQL: 1 row (Outer).   MatrixOne: 2 rows (Inner leaked).
```

### C6. `LAST_INSERT_ID()` after a multi-row insert returns the **last** id
```sql
INSERT INTO t(n) VALUES (1),(2),(3);
SELECT LAST_INSERT_ID();       -- MySQL: 1 (first row)   MatrixOne: 3 (last row)
```
Code that batch-inserts and then derives the inserted id range from
`LAST_INSERT_ID()` (a very common idiom) computes the wrong range.

### C7. `||` is string concatenation, not logical OR
```sql
SELECT 1 || 0;                 -- MySQL: 1 (OR)   MatrixOne: '10' (concat)
```
MatrixOne follows ANSI/PostgreSQL semantics regardless of `sql_mode`.

---

## 🟠 High — ORM tooling broken by missing `information_schema` tables

MatrixOne ships **24** of MySQL 8's information_schema tables; several that ORM
schema tooling depends on are **absent**: `check_constraints`,
`collation_character_set_applicability`, `table_options`, `column_statistics`,
`st_geometry_columns`, `innodb_tables`.

### H1. Doctrine schema introspection & **Migrations** are broken
`AbstractSchemaManager::listTables()`, `introspectTable()` and the schema
comparator all issue a query that joins
`information_schema.collation_character_set_applicability`, which does not
exist:
```
1064 SQL parser error: table "collation_character_set_applicability" does not exist
```
This breaks Doctrine's bulk introspection and therefore **Doctrine Migrations**
and any schema-diff workflow. (Single-table `listTableColumns()` works.)

### H2. CakePHP ORM table reflection is broken
`Cake\Database\Schema\Collection::describe()` queries
`information_schema.CHECK_CONSTRAINTS` (missing) →
`1064 … table "check_constraints" does not exist`. CakePHP's ORM auto-describes
**every** table it touches, so the full ORM is unusable until this table exists.
(The lower-level query builder, which does not auto-describe, works fine.)

### H3. Doctrine `float` type emits `DOUBLE PRECISION`, which is rejected
```sql
CREATE TABLE t (c DOUBLE PRECISION);   -- 1064 (only DOUBLE is accepted)
```
Any entity with a `#[ORM\Column(type: 'float')]` fails at schema-create time.
**Workaround:** use `decimal`, or a custom type mapping to `DOUBLE`.

### H4. `UUID` columns crash the mysqlnd/PDO driver
Selecting a `UUID`-typed column (or `UUID()`/`UUID_SHORT()`) returns MySQL
column type `243`, which PHP's driver does not recognise:
```
PDO::query(): Unknown type 243 sent by the server
```
Storing UUIDs as `CHAR(36)`/`BINARY(16)` is unaffected and is the safe choice.

### H5. Many statements cannot run via the binary prepared-statement protocol
With `PDO::ATTR_EMULATE_PREPARES = false` (a common, security-recommended
setting), `DESCRIBE`, `EXPLAIN`, `SET @v = …`, `SHOW COLLATION`,
`SHOW WARNINGS`, `SHOW VARIABLES LIKE …` all fail to prepare (they work via the
text protocol). MySQL 8 can prepare all of these.

---

## 🟡 Medium — unsupported features

### M1. Generated / computed columns
`… GENERATED ALWAYS AS (expr) STORED|VIRTUAL` → `1064`. Eloquent's `storedAs()`
/ `virtualAs()` modifiers fail accordingly.

### M2. ~49 built-in functions are not implemented (`20105`)
Grouped for convenience (the base function in each alias pair often *does* work,
e.g. `DAY`/`CHAR_LENGTH`/`LENGTH` are fine):

- **Comparison:** `LEAST`, `GREATEST` *(even with numeric args)*
- **String:** `INSERT`, `ORD`, `CHAR`, `SOUNDEX`, `CHARACTER_LENGTH`,
  `OCTET_LENGTH`, `EXPORT_SET`, `MAKE_SET`, `WEIGHT_STRING`, `CONV`
- **Date/time:** `SEC_TO_TIME`, `TIME_TO_SEC`, `PERIOD_ADD`, `PERIOD_DIFF`,
  `FROM_DAYS`, `UTC_DATE`, `UTC_TIME`, `MICROSECOND`, `WEEKOFYEAR`, `DAYOFMONTH`
- **JSON:** `JSON_CONTAINS`, `JSON_CONTAINS_PATH`, `JSON_DEPTH`, `JSON_REMOVE`,
  `JSON_MERGE_PATCH`, `JSON_MERGE_PRESERVE`, `JSON_ARRAY_APPEND`, `JSON_SEARCH`,
  `JSON_OVERLAPS`, `JSON_STORAGE_SIZE`, `JSON_ARRAYAGG`, `JSON_OBJECTAGG`,
  `JSON_TABLE`, `MEMBER OF` *(→ Eloquent `whereJsonContains` fails)*
- **Aggregate/window:** `STDDEV`, `STDDEV_SAMP`, `VAR_SAMP`, `NTILE`
- **Spatial:** `ST_GeomFromText`, `POINT`, `ST_X`, `ST_Y`, `ST_Distance`,
  `ST_Contains`, `ST_SRID`, `ST_AsText`, `ST_GeometryType` *(the `GEOMETRY`
  column type is accepted, but the functions and spatial indexes are not)*
- **Network/encoding:** `INET_ATON`, `INET_NTOA`, `INET6_ATON`
- **Misc:** `BIT_COUNT`, `UUID_SHORT`, `COERCIBILITY`, `BENCHMARK`, `COMPRESS`,
  `RANDOM_BYTES`, `l1_distance`

### M3. Other DDL / DML gaps
- `ALTER TABLE … ADD CONSTRAINT … CHECK (…)` → `20101` (cannot add CHECK by ALTER)
- `CREATE INDEX … USING BTREE` → `1064`
- `SELECT … LOCK IN SHARE MODE` → `1064`; `… FOR UPDATE` inside `CREATE TABLE AS`
  corrupts the result stream
- `CAST/CONVERT(x AS CHAR(n))` **errors** when the value is wider than `n`
  instead of truncating (`20101 … larger than Dest length`)
- **Stored procedures, triggers, `SAVEPOINT`** are unimplemented
- `FULLTEXT` indexes require a primary key (`primary key cannot be empty for
  fulltext index`)

### M4. `SHOW` surface gaps
`SHOW VARIABLES [LIKE …]`, `SHOW STATUS`, `SHOW ENGINES`, `SHOW CHARACTER SET`
return **zero rows**; `SHOW VARIABLES` can return a malformed result packet;
`SHOW COLLATION` / `SHOW WARNINGS` error with `20101`.

---

## 🔵 Low — strict-mode & formatting differences

- **`ADDTIME`/`SUBTIME`** on two `TIME` values return a full `DATETIME`
  (`'2026-06-23 11:00:00.000000'`) instead of `'11:00:00'`.
- **`HOUR`/`MINUTE`/`SECOND`** reject a bare `'HH:MM:SS'` string (`20203`); they
  require a `TIME`/`DATETIME`-typed argument. MySQL accepts the string.
- **`FROM_BASE64`** returns a value with a trailing `NUL` byte.
- **Stricter coercion in SELECT:** `'10abc' + 5` and `CAST('abc' AS UNSIGNED)`
  raise errors (`20203`) where MySQL returns `15` / `0` with a warning.
- **`RAND(seed)`** (seeded form) is unsupported.
- Decimal/temporal **formatting** differs in places (e.g. `AVG` returns scale 1
  vs MySQL's scale 4; `EXTRACT(MONTH …)` is zero-padded). These are numerically
  equivalent and were **not** counted as failures.

---

## ✅ What works well

The compatible surface is large — most applications will run with targeted
changes rather than a rewrite.

- **Eloquent (126/132):** schema builder for every column type & most modifiers,
  the full query builder (joins, unions, subqueries, aggregates, pagination,
  chunking, `upsert`, JSON `->` access), Eloquent models with array/json/
  boolean/decimal casts and timestamps, and **every relationship kind**
  (`hasOne`/`hasMany`/`belongsTo`/`belongsToMany`/`morph*`, eager loading,
  `withCount`, `whereHas`).
- **Doctrine ORM (60/66):** `SchemaTool::createSchema` from entities,
  `persist`/`flush`/`find`/`remove`, `OneToMany`/`ManyToOne`/`ManyToMany`,
  repositories, DQL (where/join/aggregate) and the ORM `Paginator`; DBAL query
  builder, typed value round-trips and single-table column introspection.
- **CakePHP (27/28):** the database query builder, schema creation via
  `TableSchema`, typed binding and transactions.
- **Core SQL:** DDL for ~47 data types, DML (incl. `INSERT … ON DUPLICATE KEY`,
  `REPLACE`, `INSERT IGNORE`), all join kinds, scalar/IN/EXISTS/correlated
  subqueries, derived tables, **CTEs incl. recursive**, set operations
  (`UNION`/`INTERSECT`/`EXCEPT`), **window functions**, `PK`/`FK`/`UNIQUE`/
  `NOT NULL`/`DEFAULT` enforcement, commit/rollback, and prepared-statement
  **typed parameter binding for every type** including `LIMIT ?` placeholders.
- **MatrixOne extras:** vector columns (`VECF32`/`VECF64`), `IVFFLAT` index and
  KNN search (`l2_distance`/`cosine_distance`), and full-text `MATCH … AGAINST`
  (natural-language & boolean mode).

---

## Recommendations

**Choosing an ORM**
- **Eloquent** is the smoothest fit today (95.5%). Avoid generated columns,
  `whereJsonContains`, and rely-on-rollback nested transactions; expect
  case-sensitive matching.
- **Doctrine ORM** runtime works well, but **Doctrine Migrations / schema-diff
  is blocked** by H1, and the `float` type by H3 — manage schema with raw SQL and
  map floats to `decimal`/`double`.
- **CakePHP ORM** is **blocked** by H2 (`describe()`); use the query-builder
  layer directly until `information_schema.check_constraints` exists.

**Application-level rules of thumb**
1. Never use bare `FLOAT` — use `DOUBLE`/`DECIMAL` (C2).
2. Treat string comparisons as case-sensitive; normalise with `LOWER()` and add
   functional indexes (C1).
3. Don't rely on `CHECK` constraints (C4) or nested-transaction rollback (C5).
4. Avoid multi-table `DELETE … JOIN` (C3); delete by sub-select instead.
5. Don't derive id ranges from `LAST_INSERT_ID()` after batch inserts (C6).
6. Store UUIDs as `CHAR(36)`/`BINARY(16)`, not the `UUID` type (H4).
7. Replace unsupported functions (M2) with supported equivalents or app-side
   logic.

**For the MatrixOne team — highest-impact fixes**
1. Honour `_ci` collations (or change the default) — C1 is the single
   biggest porting hazard.
2. Implement `ROLLBACK TO SAVEPOINT` — unblocks nested transactions in every ORM.
3. Add `information_schema.collation_character_set_applicability` and
   `check_constraints` — unblocks Doctrine Migrations and the CakePHP ORM.
4. Fix bare-`FLOAT` rounding and multi-table `DELETE … JOIN`.
5. Enforce `CHECK` constraints.
