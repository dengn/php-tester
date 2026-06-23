# PHP ORM ⇆ MatrixOne Compatibility Report

- **Generated:** 2026-06-23 02:46:18 UTC
- **Target:** `127.0.0.1:6001`
- **Server version():** `8.0.30-MatrixOne-v3.0.15`
- **Scenarios:** 1002 total — **868 passed**, **134 failed**, **0 skipped**
- **Pass rate (excl. skipped):** 86.6%

## Summary by framework

| Framework | Total | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|--:|
| CakePHP | 28 | 27 | 1 | 0 | 96.4% |
| Doctrine | 66 | 60 | 6 | 0 | 90.9% |
| Eloquent | 132 | 126 | 6 | 0 | 95.5% |
| PDO | 776 | 655 | 121 | 0 | 84.4% |

## Summary by category

| Category | Total | Pass | Fail | Skip |
|---|--:|--:|--:|--:|
| behavior:aggregate | 8 | 8 | 0 | 0 |
| behavior:autoincrement | 2 | 1 | 1 | 0 |
| behavior:coercion | 9 | 7 | 2 | 0 |
| behavior:collation | 12 | 3 | 9 | 0 |
| behavior:null | 6 | 5 | 1 | 0 |
| behavior:operator | 9 | 7 | 2 | 0 |
| behavior:strict | 6 | 6 | 0 | 0 |
| behavior:string | 2 | 2 | 0 | 0 |
| connection | 2 | 2 | 0 | 0 |
| constraint:autoinc | 1 | 1 | 0 | 0 |
| constraint:check | 1 | 0 | 1 | 0 |
| constraint:default | 1 | 1 | 0 | 0 |
| constraint:fk | 2 | 2 | 0 | 0 |
| constraint:notnull | 1 | 1 | 0 | 0 |
| constraint:pk | 1 | 1 | 0 | 0 |
| constraint:unique | 1 | 1 | 0 | 0 |
| datatype:ddl | 47 | 47 | 0 | 0 |
| datatype:edge | 25 | 24 | 1 | 0 |
| datatype:roundtrip | 47 | 45 | 2 | 0 |
| dbal:information_schema | 8 | 7 | 1 | 0 |
| dbal:introspection | 9 | 7 | 2 | 0 |
| dbal:querybuilder | 12 | 12 | 0 | 0 |
| dbal:schema-ddl | 3 | 2 | 1 | 0 |
| dbal:transaction | 3 | 2 | 1 | 0 |
| dbal:type | 19 | 18 | 1 | 0 |
| ddl:alter | 18 | 17 | 1 | 0 |
| ddl:create | 19 | 17 | 2 | 0 |
| ddl:drop | 3 | 3 | 0 | 0 |
| ddl:view | 3 | 3 | 0 | 0 |
| dml:delete | 4 | 3 | 1 | 0 |
| dml:insert | 7 | 7 | 0 | 0 |
| dml:update | 5 | 5 | 0 | 0 |
| feature:collation | 5 | 3 | 2 | 0 |
| feature:fulltext | 3 | 3 | 0 | 0 |
| feature:upsert | 4 | 4 | 0 | 0 |
| feature:vector | 8 | 8 | 0 | 0 |
| function:aggregate | 20 | 17 | 3 | 0 |
| function:cast | 20 | 18 | 2 | 0 |
| function:datetime | 78 | 62 | 16 | 0 |
| function:encryption | 10 | 7 | 3 | 0 |
| function:flow | 8 | 7 | 1 | 0 |
| function:info | 12 | 10 | 2 | 0 |
| function:json | 32 | 18 | 14 | 0 |
| function:numeric | 55 | 50 | 5 | 0 |
| function:scalar | 26 | 18 | 8 | 0 |
| function:spatial | 12 | 0 | 12 | 0 |
| function:string | 86 | 75 | 11 | 0 |
| function:vector | 7 | 6 | 1 | 0 |
| function:window | 13 | 12 | 1 | 0 |
| index:create | 11 | 9 | 2 | 0 |
| index:drop | 1 | 1 | 0 | 0 |
| introspection | 26 | 21 | 5 | 0 |
| misc | 4 | 3 | 1 | 0 |
| model:crud | 11 | 11 | 0 | 0 |
| orm:association | 2 | 2 | 0 | 0 |
| orm:crud | 4 | 4 | 0 | 0 |
| orm:dql | 3 | 3 | 0 | 0 |
| orm:pagination | 1 | 1 | 0 | 0 |
| orm:repository | 1 | 1 | 0 | 0 |
| orm:schema-tool | 1 | 1 | 0 | 0 |
| prepared:metadata | 3 | 3 | 0 | 0 |
| prepared:non-preparable | 7 | 1 | 6 | 0 |
| prepared:param | 12 | 12 | 0 | 0 |
| prepared:typed-bind | 18 | 18 | 0 | 0 |
| query-builder | 37 | 37 | 0 | 0 |
| query:cte | 2 | 2 | 0 | 0 |
| query:group | 3 | 3 | 0 | 0 |
| query:join | 7 | 7 | 0 | 0 |
| query:misc | 7 | 6 | 1 | 0 |
| query:select | 7 | 6 | 1 | 0 |
| query:setop | 4 | 4 | 0 | 0 |
| query:subquery | 5 | 5 | 0 | 0 |
| relationship | 10 | 10 | 0 | 0 |
| schema | 3 | 2 | 1 | 0 |
| schema:modifier | 19 | 15 | 4 | 0 |
| schema:operation | 13 | 13 | 0 | 0 |
| schema:type | 45 | 45 | 0 | 0 |
| sql:advanced | 15 | 15 | 0 | 0 |
| transaction | 6 | 5 | 1 | 0 |
| transaction:autocommit | 1 | 1 | 0 | 0 |
| transaction:commit | 1 | 1 | 0 | 0 |
| transaction:isolation | 4 | 4 | 0 | 0 |
| transaction:lock | 1 | 0 | 1 | 0 |
| transaction:rollback | 1 | 1 | 0 | 0 |
| transaction:savepoint | 1 | 0 | 1 | 0 |
| type | 10 | 10 | 0 | 0 |

## Compatibility issues (failures) grouped by error signature

### Error `20105` — 60 scenario(s)

> SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'greatest'

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 112 | PDO | function:scalar | GREATEST() |
| 113 | PDO | function:scalar | LEAST() |
| 114 | PDO | function:scalar | INET_ATON() |
| 115 | PDO | function:scalar | INET_NTOA() |
| 116 | PDO | function:scalar | INET6_ATON() |
| 117 | PDO | function:scalar | BENCHMARK() |
| 130 | PDO | function:string | CHARACTER_LENGTH |
| 132 | PDO | function:string | OCTET_LENGTH |
| 153 | PDO | function:string | INSERT |
| 156 | PDO | function:string | ORD |
| 157 | PDO | function:string | CHAR |
| 162 | PDO | function:string | CONV |
| 165 | PDO | function:string | SOUNDEX |
| 211 | PDO | function:numeric | BIT_COUNT |
| 226 | PDO | function:datetime | UTC_DATE |
| 227 | PDO | function:datetime | UTC_TIME |
| 235 | PDO | function:datetime | DAYOFMONTH |
| 239 | PDO | function:datetime | MICROSECOND |
| 242 | PDO | function:datetime | WEEKOFYEAR |
| 263 | PDO | function:datetime | SEC_TO_TIME |
| 264 | PDO | function:datetime | TIME_TO_SEC |
| 266 | PDO | function:datetime | FROM_DAYS |
| 278 | PDO | function:json | JSON_CONTAINS |
| 279 | PDO | function:json | JSON_CONTAINS_PATH |
| 282 | PDO | function:json | JSON_DEPTH |
| 286 | PDO | function:json | JSON_REMOVE |
| 287 | PDO | function:json | JSON_MERGE_PATCH |
| 288 | PDO | function:json | JSON_MERGE_PRESERVE |
| 289 | PDO | function:json | JSON_ARRAY_APPEND |
| 290 | PDO | function:json | JSON_SEARCH |
| 291 | PDO | function:json | JSON_OVERLAPS |
| 301 | PDO | function:aggregate | STDDEV |
| 303 | PDO | function:aggregate | STDDEV_SAMP |
| 305 | PDO | function:aggregate | VAR_SAMP |
| 331 | PDO | function:vector | l1_distance |
| 392 | PDO | query:select | DO statement |
| 501 | PDO | behavior:null | GREATEST with NULL is NULL |
| 582 | PDO | function:string | EXPORT_SET |
| 583 | PDO | function:string | MAKE_SET |
| 584 | PDO | function:string | WEIGHT_STRING |
| 585 | PDO | function:string | CHAR USING |
| 609 | PDO | function:numeric | LEAST mixed |
| 610 | PDO | function:numeric | GREATEST strings |
| 613 | PDO | function:numeric | CONV bin to hex |
| 624 | PDO | function:datetime | PERIOD_ADD |
| 625 | PDO | function:datetime | PERIOD_DIFF |
| 644 | PDO | function:datetime | TIME_TO_SEC |
| 671 | PDO | function:info | COERCIBILITY |
| 676 | PDO | function:info | UUID_SHORT |
| 683 | PDO | function:encryption | COMPRESS round-trip |
| 684 | PDO | function:encryption | RANDOM_BYTES length |
| 687 | PDO | function:spatial | ST_GeomFromText POINT |
| 691 | PDO | function:spatial | ST_AsText linestring |
| 693 | PDO | function:spatial | ST_GeomFromText POLYGON |
| 696 | PDO | function:spatial | GeomFromText alias |
| 700 | PDO | function:json | JSON_STORAGE_SIZE |
| 706 | PDO | function:json | JSON_ARRAYAGG |
| 707 | PDO | function:json | JSON_OBJECTAGG |
| 716 | PDO | function:flow | GREATEST in CASE |
| 907 | Eloquent | misc | whereJsonContains |

### Error `1064` — 30 scenario(s)

> SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 77 near " OVER (ORDER BY n) t2 FROM (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3) t) z) AS v";

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 315 | PDO | function:window | NTILE |
| 348 | PDO | ddl:create | generated column STORED |
| 349 | PDO | ddl:create | generated column VIRTUAL |
| 439 | PDO | index:create | index USING BTREE |
| 443 | PDO | index:create | spatial index |
| 454 | PDO | transaction:lock | LOCK IN SHARE MODE |
| 479 | PDO | introspection | DESCRIBE table |
| 480 | PDO | introspection | EXPLAIN SELECT |
| 565 | PDO | prepared:non-preparable | prepare DESCRIBE |
| 566 | PDO | prepared:non-preparable | prepare EXPLAIN |
| 570 | PDO | prepared:non-preparable | prepare SET (session var) |
| 688 | PDO | function:spatial | POINT constructor |
| 689 | PDO | function:spatial | ST_X |
| 690 | PDO | function:spatial | ST_Y |
| 692 | PDO | function:spatial | ST_Distance |
| 694 | PDO | function:spatial | ST_Contains |
| 695 | PDO | function:spatial | ST_SRID |
| 697 | PDO | function:spatial | ST_AsWKT |
| 698 | PDO | function:spatial | ST_GeometryType |
| 702 | PDO | function:json | MEMBER OF |
| 703 | PDO | function:json | JSON_TABLE |
| 835 | Eloquent | schema:modifier | modifier storedAs() |
| 836 | Eloquent | schema:modifier | modifier virtualAs() |
| 840 | Eloquent | schema:modifier | modifier spatialIndex |
| 910 | Doctrine | dbal:introspection | listTables (bulk) |
| 915 | Doctrine | dbal:introspection | introspectTable |
| 918 | Doctrine | dbal:information_schema | information_schema.COLLATION_CHARACTER_SET_APPLICABILITY |
| 927 | Doctrine | dbal:schema-ddl | schema diff / migrate (Comparator) |
| 935 | Doctrine | dbal:type | type float |
| 978 | CakePHP | schema | describe() table reflection |

### Error `BEHAVIOR` — 21 scenario(s)

> FLOAT numeric round-trip drifted: expected 3.5 got 4.0

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 36 | PDO | datatype:roundtrip | round-trip FLOAT |
| 253 | PDO | function:datetime | ADDTIME |
| 254 | PDO | function:datetime | SUBTIME |
| 390 | PDO | dml:delete | multi-table DELETE join |
| 429 | PDO | constraint:check | CHECK rejects violation |
| 484 | PDO | behavior:operator | pipe-pipe is logical OR |
| 485 | PDO | behavior:operator | double-pipe truthy OR |
| 507 | PDO | behavior:collation | case-insensitive equality |
| 508 | PDO | behavior:collation | trailing-space padded equality |
| 509 | PDO | behavior:collation | case-insensitive LIKE |
| 513 | PDO | behavior:collation | case-insensitive IN |
| 514 | PDO | behavior:collation | accent-insensitive equality |
| 515 | PDO | behavior:collation | ORDER mixes case (ci) |
| 516 | PDO | behavior:collation | column WHERE is case-insensitive |
| 517 | PDO | behavior:collation | UNIQUE collides case-insensitively |
| 518 | PDO | behavior:collation | default column collation is *_ci |
| 526 | PDO | behavior:autoincrement | LAST_INSERT_ID after multi-row insert |
| 682 | PDO | function:encryption | TO_BASE64 round-trip |
| 757 | PDO | feature:collation | COLLATE utf8mb4_general_ci in comparison |
| 758 | PDO | feature:collation | column COLLATE utf8mb4_general_ci case-insensitive WHERE |
| 903 | Eloquent | transaction | nested transaction (savepoint) |

### Error `20101` — 11 scenario(s)

> SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '123' from BIGINT type to CHAR type. 123 is larger than Dest length 1

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 111 | PDO | function:scalar | CONVERT() |
| 365 | PDO | ddl:alter | ALTER ADD CHECK |
| 448 | PDO | transaction:savepoint | SAVEPOINT + ROLLBACK TO |
| 463 | PDO | introspection | SHOW COLLATION |
| 466 | PDO | introspection | SHOW WARNINGS |
| 567 | PDO | prepared:non-preparable | prepare SHOW COLLATION |
| 568 | PDO | prepared:non-preparable | prepare SHOW WARNINGS |
| 647 | PDO | function:cast | CAST AS CHAR(n) |
| 657 | PDO | function:cast | CONVERT AS CHAR |
| 839 | Eloquent | schema:modifier | modifier fulltext index |
| 962 | Doctrine | dbal:transaction | nested with savepoints |

### Error `ERROR` — 6 scenario(s)

> PDO::query(): Unknown type 243 sent by the server. Please send a report to the developers

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 90 | PDO | datatype:roundtrip | round-trip UUID |
| 100 | PDO | function:scalar | UUID() |
| 424 | PDO | query:misc | SELECT FOR UPDATE |
| 459 | PDO | introspection | SHOW VARIABLES |
| 571 | PDO | prepared:non-preparable | prepare SHOW VARIABLES LIKE |
| 738 | PDO | datatype:edge | UUID stored |

### Error `20203` — 6 scenario(s)

> SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 236 | PDO | function:datetime | HOUR |
| 237 | PDO | function:datetime | MINUTE |
| 238 | PDO | function:datetime | SECOND |
| 497 | PDO | behavior:coercion | trailing-text string add (warns, =15) |
| 498 | PDO | behavior:coercion | CAST non-numeric to UNSIGNED = 0 |
| 615 | PDO | function:numeric | RAND seeded |

## Detailed failures

#### #36 [PDO / datatype:roundtrip] round-trip FLOAT

- **Code:** `BEHAVIOR`
- **Message:** FLOAT numeric round-trip drifted: expected 3.5 got 4.0

#### #90 [PDO / datatype:roundtrip] round-trip UUID

- **Code:** `ERROR`
- **Message:** PDO::query(): Unknown type 243 sent by the server. Please send a report to the developers

#### #100 [PDO / function:scalar] UUID()

- **Code:** `ERROR`
- **Message:** PDO::query(): Unknown type 243 sent by the server. Please send a report to the developers

#### #111 [PDO / function:scalar] CONVERT()

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '123' from BIGINT type to CHAR type. 123 is larger than Dest length 1

#### #112 [PDO / function:scalar] GREATEST()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'greatest'

#### #113 [PDO / function:scalar] LEAST()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'least'

#### #114 [PDO / function:scalar] INET_ATON()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'inet_aton'

#### #115 [PDO / function:scalar] INET_NTOA()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'inet_ntoa'

#### #116 [PDO / function:scalar] INET6_ATON()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'inet6_aton'

#### #117 [PDO / function:scalar] BENCHMARK()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'benchmark'

#### #130 [PDO / function:string] CHARACTER_LENGTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'character_length'

#### #132 [PDO / function:string] OCTET_LENGTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'octet_length'

#### #153 [PDO / function:string] INSERT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'insert'

#### #156 [PDO / function:string] ORD

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'ord'

#### #157 [PDO / function:string] CHAR

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'char'

#### #162 [PDO / function:string] CONV

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'conv'

#### #165 [PDO / function:string] SOUNDEX

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'soundex'

#### #211 [PDO / function:numeric] BIT_COUNT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'bit_count'

#### #226 [PDO / function:datetime] UTC_DATE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'utc_date'

#### #227 [PDO / function:datetime] UTC_TIME

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'utc_time'

#### #235 [PDO / function:datetime] DAYOFMONTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'dayofmonth'

#### #236 [PDO / function:datetime] HOUR

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #237 [PDO / function:datetime] MINUTE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #238 [PDO / function:datetime] SECOND

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #239 [PDO / function:datetime] MICROSECOND

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'microsecond'

#### #242 [PDO / function:datetime] WEEKOFYEAR

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'weekofyear'

#### #253 [PDO / function:datetime] ADDTIME

- **Code:** `BEHAVIOR`
- **Message:** ADDTIME: expected '11:00:00', got '2026-06-23 11:00:00.000000'

#### #254 [PDO / function:datetime] SUBTIME

- **Code:** `BEHAVIOR`
- **Message:** SUBTIME: expected '09:00:00', got '2026-06-23 09:00:00.000000'

#### #263 [PDO / function:datetime] SEC_TO_TIME

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'sec_to_time'

#### #264 [PDO / function:datetime] TIME_TO_SEC

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'time_to_sec'

#### #266 [PDO / function:datetime] FROM_DAYS

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'from_days'

#### #278 [PDO / function:json] JSON_CONTAINS

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #279 [PDO / function:json] JSON_CONTAINS_PATH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains_path'

#### #282 [PDO / function:json] JSON_DEPTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #286 [PDO / function:json] JSON_REMOVE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_remove'

#### #287 [PDO / function:json] JSON_MERGE_PATCH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_patch'

#### #288 [PDO / function:json] JSON_MERGE_PRESERVE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_preserve'

#### #289 [PDO / function:json] JSON_ARRAY_APPEND

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_array_append'

#### #290 [PDO / function:json] JSON_SEARCH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #291 [PDO / function:json] JSON_OVERLAPS

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_overlaps'

#### #301 [PDO / function:aggregate] STDDEV

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'stddev'

#### #303 [PDO / function:aggregate] STDDEV_SAMP

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'stddev_samp'

#### #305 [PDO / function:aggregate] VAR_SAMP

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'var_samp'

#### #315 [PDO / function:window] NTILE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 77 near " OVER (ORDER BY n) t2 FROM (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3) t) z) AS v";

#### #331 [PDO / function:vector] l1_distance

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'l1_distance'

#### #348 [PDO / ddl:create] generated column STORED

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 48 near " GENERATED ALWAYS AS (a+1) STORED)";

#### #349 [PDO / ddl:create] generated column VIRTUAL

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 48 near " GENERATED ALWAYS AS (a+1) VIRTUAL)";

#### #365 [PDO / ddl:alter] ALTER ADD CHECK

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: unsupported alter option in inplace mode: check (age > 0)

#### #390 [PDO / dml:delete] multi-table DELETE join

- **Code:** `BEHAVIOR`
- **Message:** multi-table DELETE join: expected '1', got 0

#### #392 [PDO / query:select] DO statement

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: do 1

#### #424 [PDO / query:misc] SELECT FOR UPDATE

- **Code:** `ERROR`
- **Message:** PDO::exec(): Error reading result set's header

#### #429 [PDO / constraint:check] CHECK rejects violation

- **Code:** `BEHAVIOR`
- **Message:** constraint was NOT enforced (bad row accepted)

#### #439 [PDO / index:create] index USING BTREE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 40 near " USING BTREE";

#### #443 [PDO / index:create] spatial index

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 68 near " INDEX(g))";

#### #448 [PDO / transaction:savepoint] SAVEPOINT + ROLLBACK TO

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #454 [PDO / transaction:lock] LOCK IN SHARE MODE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 72 near " LOCK IN SHARE MODE";

#### #459 [PDO / introspection] SHOW VARIABLES

- **Code:** `ERROR`
- **Message:** PDOStatement::fetchAll(): Malformed server packet. Field length pointing after the end of packet

#### #463 [PDO / introspection] SHOW COLLATION

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: statement: 'show collation'

#### #466 [PDO / introspection] SHOW WARNINGS

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: statement: 'show warnings'

#### #479 [PDO / introspection] DESCRIBE table

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 36 near " DESCRIBE `i_437_1efbb`";

#### #480 [PDO / introspection] EXPLAIN SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN SELECT * FROM `i_438_6ce5b`";

#### #484 [PDO / behavior:operator] pipe-pipe is logical OR

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns '10'

#### #485 [PDO / behavior:operator] double-pipe truthy OR

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns '05'

#### #497 [PDO / behavior:coercion] trailing-text string add (warns, =15)

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #498 [PDO / behavior:coercion] CAST non-numeric to UNSIGNED = 0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value abc

#### #501 [PDO / behavior:null] GREATEST with NULL is NULL

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'greatest'

#### #507 [PDO / behavior:collation] case-insensitive equality

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns 0

#### #508 [PDO / behavior:collation] trailing-space padded equality

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns 0

#### #509 [PDO / behavior:collation] case-insensitive LIKE

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns 0

#### #513 [PDO / behavior:collation] case-insensitive IN

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns 0

#### #514 [PDO / behavior:collation] accent-insensitive equality

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns '1', MatrixOne returns 0

#### #515 [PDO / behavior:collation] ORDER mixes case (ci)

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns 'a,B', MatrixOne returns 'B,a'

#### #516 [PDO / behavior:collation] column WHERE is case-insensitive

- **Code:** `BEHAVIOR`
- **Message:** MySQL ci collation matches 'ALICE' to 'alice' (1 row); MatrixOne returned 0

#### #517 [PDO / behavior:collation] UNIQUE collides case-insensitively

- **Code:** `BEHAVIOR`
- **Message:** MySQL default ci collation rejects 'ABC' as duplicate of 'abc'; MatrixOne accepted both (case-sensitive unique)

#### #518 [PDO / behavior:collation] default column collation is *_ci

- **Code:** `BEHAVIOR`
- **Message:** MySQL default column collation is case-insensitive (*_ci); MatrixOne used 'utf8_bin'

#### #526 [PDO / behavior:autoincrement] LAST_INSERT_ID after multi-row insert

- **Code:** `BEHAVIOR`
- **Message:** MySQL returns first row's id (1) for multi-row insert; got 3

#### #565 [PDO / prepared:non-preparable] prepare DESCRIBE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 36 near " DESCRIBE information_schema.tables";

#### #566 [PDO / prepared:non-preparable] prepare EXPLAIN

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN SELECT 1";

#### #567 [PDO / prepared:non-preparable] prepare SHOW COLLATION

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: statement: 'show collation'

#### #568 [PDO / prepared:non-preparable] prepare SHOW WARNINGS

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: statement: 'show warnings'

#### #570 [PDO / prepared:non-preparable] prepare SET (session var)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 31 near " SET @x = 1";

#### #571 [PDO / prepared:non-preparable] prepare SHOW VARIABLES LIKE

- **Code:** `ERROR`
- **Message:** PDOStatement::fetchAll(): Malformed server packet. Field length pointing after the end of packet

#### #582 [PDO / function:string] EXPORT_SET

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'export_set'

#### #583 [PDO / function:string] MAKE_SET

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'make_set'

#### #584 [PDO / function:string] WEIGHT_STRING

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'weight_string'

#### #585 [PDO / function:string] CHAR USING

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'char'

#### #609 [PDO / function:numeric] LEAST mixed

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'least'

#### #610 [PDO / function:numeric] GREATEST strings

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'greatest'

#### #613 [PDO / function:numeric] CONV bin to hex

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'conv'

#### #615 [PDO / function:numeric] RAND seeded

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function rand, bad value [BIGINT]

#### #624 [PDO / function:datetime] PERIOD_ADD

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'period_add'

#### #625 [PDO / function:datetime] PERIOD_DIFF

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'period_diff'

#### #644 [PDO / function:datetime] TIME_TO_SEC

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'time_to_sec'

#### #647 [PDO / function:cast] CAST AS CHAR(n)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '12345' from BIGINT type to CHAR type. 12345 is larger than Dest length 3

#### #657 [PDO / function:cast] CONVERT AS CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '99' from BIGINT type to CHAR type. 99 is larger than Dest length 1

#### #671 [PDO / function:info] COERCIBILITY

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'coercibility'

#### #676 [PDO / function:info] UUID_SHORT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'uuid_short'

#### #682 [PDO / function:encryption] TO_BASE64 round-trip

- **Code:** `BEHAVIOR`
- **Message:** FROM_BASE64(TO_BASE64('hello')): expected 'hello', got 'hello' . "\0" . ''

#### #683 [PDO / function:encryption] COMPRESS round-trip

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'compress'

#### #684 [PDO / function:encryption] RANDOM_BYTES length

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'random_bytes'

#### #687 [PDO / function:spatial] ST_GeomFromText POINT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'st_geomfromtext'

#### #688 [PDO / function:spatial] POINT constructor

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 51 near "(3, 4)) AS v";

#### #689 [PDO / function:spatial] ST_X

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near "(3, 4)) AS v";

#### #690 [PDO / function:spatial] ST_Y

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near "(3, 4)) AS v";

#### #691 [PDO / function:spatial] ST_AsText linestring

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'st_geomfromtext'

#### #692 [PDO / function:spatial] ST_Distance

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near "(0,0), POINT(3,4)) AS v";

#### #693 [PDO / function:spatial] ST_GeomFromText POLYGON

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'st_geomfromtext'

#### #694 [PDO / function:spatial] ST_Contains

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 104 near "(1,1)) AS v";

#### #695 [PDO / function:spatial] ST_SRID

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 49 near "(1,1)) AS v";

#### #696 [PDO / function:spatial] GeomFromText alias

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'geomfromtext'

#### #697 [PDO / function:spatial] ST_AsWKT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 50 near "(1,1)) AS v";

#### #698 [PDO / function:spatial] ST_GeometryType

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 57 near "(1,1)) AS v";

#### #700 [PDO / function:json] JSON_STORAGE_SIZE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_storage_size'

#### #702 [PDO / function:json] MEMBER OF

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near " OF('[1,2,3]') AS v";

#### #703 [PDO / function:json] JSON_TABLE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 93 near " COLUMNS(v INT PATH '$')) jt) AS v";

#### #706 [PDO / function:json] JSON_ARRAYAGG

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_arrayagg'

#### #707 [PDO / function:json] JSON_OBJECTAGG

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_objectagg'

#### #716 [PDO / function:flow] GREATEST in CASE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'greatest'

#### #738 [PDO / datatype:edge] UUID stored

- **Code:** `ERROR`
- **Message:** PDO::query(): Unknown type 243 sent by the server. Please send a report to the developers

#### #757 [PDO / feature:collation] COLLATE utf8mb4_general_ci in comparison

- **Code:** `BEHAVIOR`
- **Message:** explicit _ci collation should give case-insensitive equality (1); MatrixOne returned 0

#### #758 [PDO / feature:collation] column COLLATE utf8mb4_general_ci case-insensitive WHERE

- **Code:** `BEHAVIOR`
- **Message:** explicit _ci column collation should match 'ALICE'=='alice'; got 0 rows

#### #835 [Eloquent / schema:modifier] modifier storedAs()

```sql
create table `el_606_bfd37` (`anchor` int not null, `c` int as (anchor + 1) stored) default character set utf8mb4 collate 'utf8mb4_general_ci'
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 92 near " as (anchor + 1) stored) default character set utf8mb4 collate 'utf8mb4_general_ci'";

#### #836 [Eloquent / schema:modifier] modifier virtualAs()

```sql
create table `el_607_238fe` (`anchor` int not null, `c` int as (anchor + 1)) default character set utf8mb4 collate 'utf8mb4_general_ci'
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 92 near " as (anchor + 1)) default character set utf8mb4 collate 'utf8mb4_general_ci'";

#### #839 [Eloquent / schema:modifier] modifier fulltext index

```sql
alter table `el_610_304c5` add fulltext `el_610_304c5_c_fulltext`(`c`)
```

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: primary key cannot be empty for fulltext index

#### #840 [Eloquent / schema:modifier] modifier spatialIndex

```sql
alter table `el_611_bfea9` add spatial index `el_611_bfea9_c_spatialindex`(`c`)
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 74 near " index `el_611_bfea9_c_spatialindex`(`c`)";

#### #903 [Eloquent / transaction] nested transaction (savepoint)

- **Code:** `BEHAVIOR`
- **Message:** nested savepoint semantics: expected 1, got 2

#### #907 [Eloquent / misc] whereJsonContains

```sql
select count(*) as aggregate from `mo_users` where json_contains(`meta`, ?, '$."roles"')
```

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #910 [Doctrine / dbal:introspection] listTables (bulk)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #915 [Doctrine / dbal:introspection] introspectTable

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #918 [Doctrine / dbal:information_schema] information_schema.COLLATION_CHARACTER_SET_APPLICABILITY

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #927 [Doctrine / dbal:schema-ddl] schema diff / migrate (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #935 [Doctrine / dbal:type] type float

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 62 near " PRECISION DEFAULT NULL, PRIMARY KEY (id))";

#### #962 [Doctrine / dbal:transaction] nested with savepoints

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #978 [CakePHP / schema] describe() table reflection

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "check_constraints" does not exist

