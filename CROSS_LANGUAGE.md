# MatrixOne ⇆ Multi-language ORM Compatibility — Consolidated Report

Compatibility of the most-used MySQL ORMs in **PHP, Python, Java and Node.js**
against **MatrixOne 4.0.0-rc3** (`8.0.30-MatrixOne-v4.0.0-rc3`).

## Headline

**17,548 scenarios · 16,578 pass · 958 fail · 12 skip — 94.5% pass.**

| Language | Suite | ORMs / drivers | Scenarios | Pass | Fail | Pass % |
|---|---|---|--:|--:|--:|--:|
| PHP | `/` | PDO, Eloquent 12, Doctrine 3/DBAL 4, CakePHP 5 | 2324 | 2104 | 220 | 90.5% |
| Python | `clients/python` | PyMySQL, SQLAlchemy 2, Django, Peewee | 5076 | 4800 | 264 | 94.8% |
| Java | `clients/java` | JDBC, Hibernate 6/JPA, MyBatis, jOOQ | 5033 | 4823 | 210 | 95.8% |
| Node.js | `clients/node` | mysql2, Sequelize 6, TypeORM, Knex | 5115 | 4851 | 264 | 94.8% |

### Per-ORM pass rate (highest-fit ORM per language in **bold**)

| ORM | Pass % | | ORM | Pass % |
|---|--:|---|---|--:|
| TypeORM (Node) | 98.8% | | **Hibernate (Java)** | 98.1% |
| MyBatis (Java) | 97.9% | | jOOQ (Java) | 97.8% |
| **SQLAlchemy (Python)** | 97.7% | | Knex (Node) | 97.5% |
| **Eloquent (PHP)** | 96.7% | | Django (Python) | 96.0% |
| CakePHP (PHP) | 95.6% | | Doctrine (PHP) | 93.2% |
| Peewee (Python) | 93.2% | | Sequelize (Node) | 91.0% |

### Failures by error signature (all languages)

| Signature | Count | Meaning |
|---|--:|---|
| `1064` | 355 | SQL syntax / feature not supported by the parser |
| `20105` | 259 | function / operator not implemented |
| `20203` | 127 | stricter argument/type validation than MySQL |
| `BEHAVIOR` | 123 | runs without error but result differs from MySQL semantics |
| `20101` | 46 | internal "not implemented yet" |
| `20301` | 15 | column/identifier resolution differences |
| `1149` | 13 | aggregate in WHERE rejected |
| `1690` | 6 | numeric out-of-range on cast |
| `20102` | 4 | `EXCEPT ALL` / set-op-all unimplemented |
| `1105` | 3 | **server panic (nil pointer)** |
| `TIMEOUT` | 2 | scenario hung |
| other | 5 | `1062`, `1068`, `20405`, driver `ERROR` |

---

## Universal findings — reproduced in **every** language and ORM

These appeared independently in PHP, Python, Java **and** Node — strong, cross-validated signals. They are the priority list.

### U1. 🔴 Default collation is binary/case-sensitive (`utf8mb4_bin`); `_ci` collations ignored
`'abc' = 'ABC'` → 0, `'café' = 'cafe'` → 0, `'a ' = 'a'` → 0 in all four languages; `UNIQUE` accepts `'abc'` and `'ABC'`; an explicit `COLLATE utf8mb4_general_ci` does **not** restore case-insensitivity. Silently breaks logins / lookups / dedup ported from MySQL. **Biggest porting hazard.**

### U2. 🔴 `ROLLBACK TO SAVEPOINT` unimplemented → nested transactions silently leak
Hit by Eloquent/Doctrine, SQLAlchemy `begin_nested()` / Django `atomic()`, Hibernate + raw JDBC `Savepoint`, and all Node ORMs. Inner rollback does not undo inner work; the outer transaction commits it.

### U3. 🔴 Bare `FLOAT` corrupts data; `DOUBLE PRECISION` keyword rejected
`FLOAT` stores `3.5` as `4.0`. Worse, the canonical mapping of a double column in **Doctrine, Django `FloatField`, Peewee `DoubleField`, Hibernate, and Sequelize `DataTypes.DOUBLE`** emits `DOUBLE PRECISION`, which MatrixOne rejects (`1064`) — so double columns are broadly unusable out of the box. (Use `DOUBLE`.)

### U4. 🔴 `CHECK` constraints parsed but not enforced; multi-table `DELETE … JOIN` deletes all rows; `LAST_INSERT_ID()` returns the **last** id after a multi-row insert; `||` is string concat not logical OR. All reproduced in every language.

### U5. 🟠 Missing `information_schema` tables break schema tooling
`collation_character_set_applicability` and `check_constraints` are absent → Doctrine Migrations/diff, CakePHP `describe()`, **Hibernate `hbm2ddl=update`/`validate`**, and ORM introspection across languages fail. Column metadata is also inaccurate (varchar length / decimal precision return `NULL`; FKs not exposed via JDBC `getImportedKeys` / `listTableForeignKeys`).

### U6. 🟠 JSON manipulation function family unimplemented
`JSON_CONTAINS`, `JSON_CONTAINS_PATH`, `JSON_DEPTH`, `JSON_REMOVE`, `JSON_MERGE_PATCH/PRESERVE`, `JSON_SEARCH`, `JSON_OVERLAPS`, `JSON_ARRAY_APPEND/INSERT`, `JSON_STORAGE_SIZE` → breaks JSON `where`/containment helpers in every ORM (Eloquent `whereJsonContains`, Django JSONField lookups, etc.). `JSON_EXTRACT`/`->`/`->>` work.

### U7. 🟡 Misc unsupported surface (consistent across languages)
`CHARACTER_LENGTH`, `WEIGHT_STRING`, `UUID_SHORT`, `COERCIBILITY`, `BENCHMARK`, some `INET*`/spatial `ST_*`; `CREATE INDEX … USING BTREE`; `LOCK IN SHARE MODE` / `FOR SHARE` / `SKIP LOCKED`; generated-column edge cases; `EXCEPT ALL` / `INTERSECT ALL`; `CAST … AS CHAR(n)` truncation errors; stricter coercion (`'10abc'+5`, seeded `RAND`, bare time-string to `HOUR/MINUTE`).

---

## Severe stack-specific findings (beyond the universal set)

- 🔴 **Server panic (`1105`, nil pointer):** Sequelize **ENUM column `UPDATE`** crashes MatrixOne (3 occurrences). A client statement should never panic the server.
- 🔴 **Hangs (`TIMEOUT`):** TypeORM `softDelete`/`restore` stall under cumulative load.
- 🟠 **Connection leak:** TypeORM's mysql2 driver leaks ~1 connection per DataSource against this build, exhausting the 151-connection limit (worked around in the suite via shared DataSources). Worth investigating server-side connection accounting.
- 🟠 **ENUM/SET/JSON in PK or UNIQUE** rejected (Java/jOOQ findings).
- 🟠 **Aggregate in `WHERE`** rejected with `1149` (Node/Java) where MySQL gives a clearer error path; **Decimal128 strict-subtract overflow** (`20301`).
- 🟡 **PHP `UUID` column** previously crashed the mysqlnd driver (`Unknown type 243`) — fixed in 4.0.0-rc3.

---

## What works well everywhere

Across all four languages the **ORM core is solid**: entity/model mapping for the common types, CRUD, the query builders (joins, subqueries, **CTEs incl. recursive**, **window functions**, set ops, group/having, aggregates), associations (1:1 / 1:N / N:M / self-ref / eager+lazy / cascade), pagination, batch/bulk inserts, prepared-statement typed binding, and commit/rollback. MatrixOne's own extras — **vector** (`VECF32/64`, `IVFFLAT`, KNN) and **full-text** (`MATCH … AGAINST`) — also work. The 94.5% aggregate pass rate reflects this.

---

## Recommended fix priority for MatrixOne (ranked by cross-language impact)

1. **Honour `_ci` collations / change the default** (U1) — the #1 silent-correctness hazard, every language.
2. **Implement `ROLLBACK TO SAVEPOINT`** (U2) — unblocks nested transactions in all 14 ORMs.
3. **Accept `DOUBLE PRECISION` + fix bare-`FLOAT` rounding** (U3) — double columns are broadly broken today.
4. **Add `collation_character_set_applicability` + `check_constraints` and accurate column/FK metadata** (U5) — unblocks Hibernate/Doctrine/CakePHP schema tooling.
5. **Enforce `CHECK`; fix multi-table `DELETE … JOIN`; fix `LAST_INSERT_ID` for batch inserts** (U4).
6. **Implement the JSON manipulation function family** (U6).
7. **Fix the ENUM-`UPDATE` server panic and the TypeORM hang/connection-leak** (severe, stability).

---

## Reproduce

```bash
# PHP   (2324):  cd /home/user/php-tester        && php run.php
# Python(5076):  cd clients/python               && .venv/bin/python run.py
# Java  (5033):  cd clients/java                 && mvn -q compile exec:java
# Node  (5115):  cd clients/node                 && node run.js
```
All suites read `MO_HOST/MO_PORT/MO_USER/MO_PASS` (defaults `127.0.0.1:6001` root/`111`) and write `reports/results.json` + `reports/summary.md`.
