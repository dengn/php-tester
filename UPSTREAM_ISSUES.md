# Upstream bug reports for `matrixorigin/matrixone`

Ready-to-file English issue drafts for the **distinct, single-point engine bugs**
found while testing PHP / Python / Java / Node.js ORMs against MatrixOne.

- **Affected version:** `8.0.30-MatrixOne-v4.0.0-rc3` (MySQL 8.0.30 wire protocol)
- **How to use:** create one issue per section below in
  https://github.com/matrixorigin/matrixone/issues — paste the **Title** and the
  body. Each links back to the cross-language test background on
  `dengn/php-tester` (#1 report, #2 Eloquent, #3 Doctrine, #4 Python, #5 Node,
  #6 Java) and `CROSS_LANGUAGE.md`.
- **Before filing:** please search the tracker first — some of these may already
  be reported. Suggested labels are noted per issue.

Findings were reproduced independently across multiple languages/ORMs (cross-validated). Minimal repros below use raw SQL so they are driver-independent.

Issues **#16–#18** come from the **JDBC 4.2 API conformance probe** (`JDBC_COMPAT.md`,
`clients/java/jdbcapi/`): the driver-level `java.sql.*` surface tested independently
of any ORM. Each was reduced to a driver-independent SQL/protocol repro.

---

## 1. [correctness] Default collation is binary/case-sensitive; explicit `_ci` collations are ignored

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, compatibility, collation, P1

New `VARCHAR`/`TEXT` columns default to `utf8mb4_bin` (`@@collation_server = utf8mb4_bin`). MySQL 8's default is the case- and accent-**insensitive** `utf8mb4_0900_ai_ci`. Worse, requesting a `_ci` collation explicitly does **not** restore case-insensitivity.

```sql
SELECT 'abc' = 'ABC';                               -- MySQL: 1   MatrixOne: 0
SELECT 'café' = 'cafe';                             -- MySQL: 1   MatrixOne: 0
SELECT 'a ' = 'a';                                  -- MySQL: 1   MatrixOne: 0
SELECT 'abc' = 'ABC' COLLATE utf8mb4_general_ci;    -- MySQL: 1   MatrixOne: 0  (collation ignored)
CREATE TABLE t (c VARCHAR(20) COLLATE utf8mb4_general_ci);
INSERT INTO t VALUES ('abc');
SELECT COUNT(*) FROM t WHERE c = 'ABC';             -- MySQL: 1   MatrixOne: 0
```

**Impact:** silently breaks case-insensitive lookups, `UNIQUE` de-duplication, and logins for any application ported from MySQL. The usual workaround (specify a `_ci` collation) does not work; only `LOWER()`/`UPPER()` on both sides helps. This is the single biggest porting hazard and was reproduced in all four languages.

**Background:** dengn/php-tester#1, #4, #5, #6.

---

## 2. [unimplemented] `ROLLBACK TO SAVEPOINT` is not implemented → nested transactions silently leak

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, transaction, P1

`SAVEPOINT` can be created, but rolling back to it errors:

```sql
START TRANSACTION;
INSERT INTO t VALUES (1);
SAVEPOINT sp1;
INSERT INTO t VALUES (2);
ROLLBACK TO SAVEPOINT sp1;   -- 20101 internal error: savepoint has not been implemented yet
```

**Impact:** every ORM implements *nested* transactions with savepoints (Laravel/Eloquent nested `DB::transaction`, Doctrine `setNestTransactionsWithSavepoints`, SQLAlchemy `begin_nested()`, Django `atomic()`, Hibernate/JDBC `Savepoint`). An inner rollback therefore does **not** undo inner work — the outer transaction commits it, causing silent data-integrity loss.

**Background:** dengn/php-tester#2, #3, #4, #6.

---

## 3. [parser] `DOUBLE PRECISION` column type keyword is rejected

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, parser, type-system, P1

```sql
CREATE TABLE t (c DOUBLE PRECISION);   -- 1064 SQL parser error
CREATE TABLE t (c DOUBLE);             -- OK
```

`DOUBLE PRECISION` is a standard SQL synonym for `DOUBLE` and is what many ORMs emit for a double/float column: Doctrine `float`, Django `FloatField`, Peewee `DoubleField`, Hibernate, MyBatis, jOOQ, Sequelize `DataTypes.DOUBLE`. As a result, double columns are broadly unusable out of the box across every language tested. **Fix:** accept `DOUBLE PRECISION` as an alias of `DOUBLE`.

**Background:** dengn/php-tester#3, #4, #5, #6.

---

## 4. [correctness] Bare `FLOAT` corrupts data — `3.5` is stored as `4.0`

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, type-system, data-integrity, P1

```sql
CREATE TABLE t (c FLOAT);
INSERT INTO t VALUES (3.5);
SELECT c FROM t;            -- MySQL: 3.5   MatrixOne: 4.0
```

A precision-less `FLOAT` column rounds values to integers (the fractional part is lost) for both literal and bound inserts. `FLOAT(7,4)` is fine. **Impact:** silent numeric data corruption for any `FLOAT` column.

**Background:** dengn/php-tester#1.

---

## 5. [correctness] Multi-table `DELETE … JOIN` deletes all rows instead of only matched rows

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, dml, data-integrity, P1

```sql
CREATE TABLE d1 (id INT, n INT); CREATE TABLE d2 (id INT);
INSERT INTO d1 VALUES (1,1),(2,2); INSERT INTO d2 VALUES (1);
DELETE a FROM d1 a JOIN d2 b ON a.id = b.id;
SELECT COUNT(*) FROM d1;    -- MySQL: 1 (only id=1 deleted)   MatrixOne: 0 (table emptied)
```

**Impact:** data loss for the common "delete rows that have a match in another table" pattern.

**Background:** dengn/php-tester#1.

---

## 6. [correctness] `CHECK` constraints are parsed but not enforced

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, constraints, P2

```sql
CREATE TABLE t (age INT CHECK (age >= 0));
INSERT INTO t VALUES (-5);   -- MySQL 8.0.16+: rejected   MatrixOne: accepted
```

`CHECK` is accepted at DDL time but never enforced on writes, so application invariants relying on it become silent no-ops.

**Background:** dengn/php-tester#1.

---

## 7. [correctness] `LAST_INSERT_ID()` after a multi-row insert returns the last id, not the first

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, compatibility, P2

```sql
CREATE TABLE t (id INT PRIMARY KEY AUTO_INCREMENT, n INT);
INSERT INTO t(n) VALUES (1),(2),(3);
SELECT LAST_INSERT_ID();     -- MySQL: 1 (first row)   MatrixOne: 3 (last row)
```

**Impact:** code that batch-inserts and derives the inserted id range from `LAST_INSERT_ID()` (a very common idiom, and what several ORMs rely on for batch `create`) computes the wrong range.

**Background:** dengn/php-tester#1.

---

## 8. [compatibility] `||` is treated as string concatenation, not logical OR

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** compatibility, operator, P2

```sql
SELECT 1 || 0;   -- MySQL (default sql_mode): 1 (logical OR)   MatrixOne: '10' (concat)
```

MatrixOne uses ANSI/PostgreSQL `||` semantics regardless of `sql_mode` (no `PIPES_AS_CONCAT` toggle observed). This diverges from MySQL and can silently change results of expressions ported from MySQL.

**Background:** dengn/php-tester#1.

---

## 9. [dml] `INSERT … ON DUPLICATE KEY UPDATE` on a primary/unique key is rejected

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, dml, upsert, P1

A single-column upsert on a non-key column works, but upserting against a PK or (composite) UNIQUE key fails:

```sql
-- composite UNIQUE (name, city):
INSERT INTO t (name, city, age) VALUES ('alice','NY',31)
  ON DUPLICATE KEY UPDATE age = VALUES(age);
-- MatrixOne: 1062 Duplicate entry '(alice,NY)' for key '(name,city)'  (expected: row updated)

-- via key:
... ON DUPLICATE KEY UPDATE id = ... -> 20101 internal error:
    do not support update primary key/unique key for on duplicate
```

**Impact:** breaks `upsert()` / `updateOrInsert()` / `onConflict().merge()` in Eloquent, Sequelize, TypeORM, Knex, etc.

**Background:** dengn/php-tester#2, #5.

---

## 10. [introspection] `information_schema` gaps break schema-migration tooling

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, information_schema, migrations, P1

Two related problems block every schema-introspection / migration tool tested:

1. **Missing tables:** `information_schema.collation_character_set_applicability` and `information_schema.check_constraints` do not exist.
   ```
   -- Doctrine DBAL listTables()/introspectTable(), CakePHP describe():
   1064 SQL parser error: table "collation_character_set_applicability" does not exist
   1064 SQL parser error: table "check_constraints" does not exist
   ```
2. **`ONLY_FULL_GROUP_BY` rejection on a `tables` introspection query** that the MySQL planner accepts:
   ```
   -- Hibernate hbm2ddl=update/validate, Flyway, Liquibase:
   1149 SQL syntax error: column "tables.TABLE_TYPE" must appear in the GROUP BY clause
   ```

Single-table column metadata is also inaccurate: varchar `CHARACTER_MAXIMUM_LENGTH` and decimal `NUMERIC_PRECISION`/`SCALE` come back `NULL`, `DATA_TYPE` is upper-cased, TEXT `CHARACTER_MAXIMUM_LENGTH = 0`, and foreign keys are not exposed via JDBC `getImportedKeys` / DBAL `listTableForeignKeys`.

**Impact:** **Doctrine Migrations, CakePHP ORM reflection, Hibernate auto-DDL, Flyway and Liquibase all fail** — i.e. the standard migration path for Symfony, Laravel(CakePHP), and Spring Boot apps. Liquibase is 0/9 in the suite.

**Background:** dengn/php-tester#3, #6 (and CakePHP in #1).

---

## 11. [functions] JSON manipulation/containment function family is unimplemented

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, functions, json, P2

```sql
SELECT JSON_CONTAINS('[1,2,3]', '2');   -- 20105 not supported: function or operator 'json_contains'
```

Unimplemented: `JSON_CONTAINS`, `JSON_CONTAINS_PATH`, `JSON_DEPTH`, `JSON_REMOVE`, `JSON_MERGE_PATCH`, `JSON_MERGE_PRESERVE`, `JSON_ARRAY_APPEND`, `JSON_ARRAY_INSERT`, `JSON_SEARCH`, `JSON_OVERLAPS`, `JSON_STORAGE_SIZE`. (`JSON_EXTRACT` / `->` / `->>` work.)

**Impact:** breaks JSON `where`/containment queries in every ORM (e.g. Eloquent `whereJsonContains`, Django `JSONField` containment lookups).

**Background:** dengn/php-tester#2, #4, #6.

---

## 12. [crash] `UPDATE` of an `ENUM` column triggers a server-side panic (nil pointer)

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, crash, enum, P0

Updating a row that has an `ENUM` column crashes the server with error `1105` (nil-pointer panic), observed via Sequelize-generated `UPDATE`s. A client statement must never panic the server (crash/DoS-class).

Repro sketch:
```sql
CREATE TABLE t (id INT PRIMARY KEY, status ENUM('new','paid','shipped'));
INSERT INTO t VALUES (1,'new');
UPDATE t SET status='paid' WHERE id=1;   -- observed: 1105 server panic
```
(Please confirm the exact trigger; in the suite it surfaced under Sequelize ENUM updates.)

**Background:** dengn/php-tester#5.

---

## 13. [type-system] `ENUM`/`SET` cannot be part of a PRIMARY KEY/UNIQUE; `JSON` columns cannot have a `DEFAULT`

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, type-system, P3

```sql
CREATE TABLE t (c ENUM('a','b') PRIMARY KEY);   -- 20105 ENUM column 'c' cannot be in primary key
CREATE TABLE t (c JSON DEFAULT (JSON_ARRAY()));  -- 20105 JSON column 'c' cannot have default value
SELECT COALESCE(json_col, '{}');                 -- 20203 invalid argument function coalesce, bad value [JSON VARCHAR]
```

These are valid in MySQL 8 and are emitted by common entity mappings (JDBC/Hibernate generated DDL).

**Background:** dengn/php-tester#6.

---

## 14. [compatibility] Stricter implicit type coercion than MySQL in arithmetic/comparison

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** compatibility, type-coercion, P3

MatrixOne rejects many mixed-type expressions that MySQL silently coerces:

```sql
SELECT '10abc' + 5;                 -- MySQL: 15 (+warning)   MatrixOne: 20203 invalid argument
SELECT CAST('abc' AS UNSIGNED);     -- MySQL: 0  (+warning)   MatrixOne: 20203 / 1690
SELECT COUNT(*) / COUNT(*) FROM t;  -- MySQL: 1.0             MatrixOne: 20203 invalid argument operator /, bad value [BIGINT UNSIGNED ...]
```

The last case (a `rate = a/b` over two unsigned/aggregate counts) is common in analytics queries. This was the single largest source of failures in the expanded grids (3,064 occurrences) and is consistent across all four languages — likely one root cause in the coercion rules. **Suggestion:** align implicit numeric coercion (esp. division over unsigned/aggregate operands and string→number) with MySQL.

**Background:** dengn/php-tester#1, #4, #5, #6 (and `CROSS_LANGUAGE.md`).

---

## 15. [functions/sql] Assorted unimplemented functions & locking/set-op syntax

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** enhancement, functions, P3

Group of smaller MySQL-compat gaps (each verified):

- **Functions (`20105`):** `CHARACTER_LENGTH` (alias of `CHAR_LENGTH`), `OCTET_LENGTH`*, `UUID_SHORT`, `COERCIBILITY`, `WEIGHT_STRING`, `BENCHMARK`, `INET6_ATON`, `l1_distance`, spatial `ST_*` / `POINT` constructors.
- **Locking reads (`1064`):** `LOCK IN SHARE MODE`, `FOR SHARE`, `SKIP LOCKED`, `NOWAIT`.
- **Set ops (`20102`):** `EXCEPT ALL` / `INTERSECT ALL`.
- **DDL (`1064`/`20101`):** `CREATE INDEX … USING BTREE`, `ALTER TABLE … ADD … CHECK`, named `WINDOW` clause.
- **Cast (`20101`/`1064`):** `CAST/CONVERT(x AS CHAR(n))` errors on truncation instead of truncating; `CAST … AS NCHAR` / `… CHARACTER SET` rejected.
- **Date arg strictness (`20203`):** `HOUR('11:30:00')` / `MINUTE`/`SECOND` reject a bare time string (require a `TIME`/`DATETIME` typed arg).

(\* some of these may already be implemented in newer builds — please verify against current `main`.)

**Background:** dengn/php-tester#1, #5, #6.

---

## 16. [protocol] Result-set column definitions omit column flags (no `PRI_KEY`/`NOT_NULL`) → JDBC updatable `ResultSet` cannot find the primary key

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, protocol, metadata, P2

In the column-definition packets MatrixOne sends for a result set, the per-column
`flags` field is empty — even for a `PRIMARY KEY` / `NOT NULL` column. MySQL sets
`PRI_KEY_FLAG` and `NOT_NULL_FLAG` there.

```sql
CREATE TABLE upd (id INT PRIMARY KEY, v INT);
INSERT INTO upd VALUES (1, 10);
SELECT id, v FROM upd;
```

Inspecting the result-set column metadata (driver-independent — PHP PDO
`getColumnMeta()`, but the same empty flags are seen by Connector/J):

```
id => flags=[]      -- MatrixOne     (MySQL/TiDB: flags=[primary_key, not_null])
v  => flags=[]
```

**Impact:** any client that relies on the result-set column flags to identify key
columns breaks. Concretely, JDBC `CONCUR_UPDATABLE` result sets fail:

```
ResultSet.updateRow()/insertRow()
  -> "Result Set not updatable (references no primary keys)."
```

even though the underlying table has a primary key that is present in the
`SELECT` list. The same operations succeed on TiDB and MySQL. This also weakens
`ResultSetMetaData` (`isAutoIncrement` aside, key/nullability flags are lost) for
ORM/tooling that introspects result columns.

**Fix:** populate the `flags` field of the column-definition (text/binary
protocol) with at least `PRI_KEY_FLAG`, `NOT_NULL_FLAG`, `UNIQUE_KEY_FLAG`,
`AUTO_INCREMENT_FLAG` to match MySQL.

**Background:** dengn/php-tester JDBC conformance probe (`JDBC_COMPAT.md`).

---

## 17. [compatibility] Reading an undefined user variable `@x` errors (`20101`) instead of returning NULL; `SELECT … INTO @var` rejected (`1064`)

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** bug, compatibility, user-variables, P2

Two related user-defined-variable gaps. Basic assignment/readback **works**
(`SET @x = 5; SELECT @x;` → `5`), but:

```sql
SELECT @never_set;          -- MySQL: NULL
                            -- MatrixOne: 20101 internal error: the user variable never_set does not exist

SELECT abs(-5) INTO @o;     -- MySQL: OK (sets @o = 5)
                            -- MatrixOne: 1064 SQL parser error near "@o"
```

In MySQL, referencing a user variable that has never been assigned yields `NULL`
(not an error), and `SELECT expr INTO @var` is standard syntax.

**Impact:** breaks JDBC `CallableStatement` function out-parameters. Connector/J
implements `{? = call f(?)}` by capturing the result into a session variable
named `@com_mysql_jdbc_outparam_0` and reading it back; on MatrixOne this fails
with *"the user variable com_mysql_jdbc_outparam_0 does not exist"*. More
generally, any application using `@var` accumulators or `SELECT … INTO @var`
(a common MySQL idiom) is affected.

**Fix:** return `NULL` when reading an unassigned user variable (MySQL
semantics), and support `SELECT … INTO @var`.

**Background:** dengn/php-tester JDBC conformance probe (`JDBC_COMPAT.md`).

---

## 18. [error-handling] `CALL`/stored-procedure raises a misleading "unclassified statement appears in uncommitted transaction" internal error

**Version:** 8.0.30-MatrixOne-v4.0.0-rc3 · **Labels:** enhancement, error-handling, stored-procedures, P3

Stored procedures are not supported (this is also the case on TiDB, which is a
reasonable limitation). However, MatrixOne reports it via a confusing internal
error rather than a clean "feature not supported":

```sql
CREATE PROCEDURE p1() BEGIN SELECT 1; END;   -- accepted
CALL p1();
  -- MatrixOne: internal error: unclassified statement appears in uncommitted transaction
  -- (TiDB:     a clearer "Unsupported ..." message)
```

**Impact:** minor, but the `internal error` / "unclassified statement" wording
misleads users (and JDBC `CallableStatement.execute()` callers) into thinking it
is a transaction-state problem rather than an unimplemented feature.

**Fix:** return a clear `not supported: stored procedures` style error (ideally a
MySQL-compatible error code), or implement `CREATE PROCEDURE`/`CALL`.

**Background:** dengn/php-tester JDBC conformance probe (`JDBC_COMPAT.md`).
