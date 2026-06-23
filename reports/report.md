# PHP ORM ⇆ MatrixOne Compatibility Report

- **Generated:** 2026-06-23 07:41:55 UTC
- **Target:** `127.0.0.1:6001`
- **Server version():** `8.0.30-MatrixOne-v4.0.0-rc3`
- **Scenarios:** 2324 total — **2104 passed**, **220 failed**, **0 skipped**
- **Pass rate (excl. skipped):** 90.5%

## Summary by framework

| Framework | Total | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|--:|
| CakePHP | 136 | 130 | 6 | 0 | 95.6% |
| Doctrine | 237 | 221 | 16 | 0 | 93.2% |
| Eloquent | 334 | 323 | 11 | 0 | 96.7% |
| PDO | 1617 | 1430 | 187 | 0 | 88.4% |

## Summary by category

| Category | Total | Pass | Fail | Skip |
|---|--:|--:|--:|--:|
| aggregate2 | 14 | 14 | 0 | 0 |
| behavior:aggregate | 8 | 8 | 0 | 0 |
| behavior:autoincrement | 2 | 1 | 1 | 0 |
| behavior:coercion | 9 | 7 | 2 | 0 |
| behavior:collation | 12 | 3 | 9 | 0 |
| behavior:null | 6 | 5 | 1 | 0 |
| behavior:operator | 9 | 7 | 2 | 0 |
| behavior:strict | 6 | 6 | 0 | 0 |
| behavior:string | 2 | 2 | 0 | 0 |
| casts | 16 | 16 | 0 | 0 |
| charset | 21 | 15 | 6 | 0 |
| collation | 22 | 11 | 11 | 0 |
| connection | 2 | 2 | 0 | 0 |
| constraint:autoinc | 1 | 1 | 0 | 0 |
| constraint:check | 1 | 0 | 1 | 0 |
| constraint:default | 1 | 1 | 0 | 0 |
| constraint:fk | 2 | 2 | 0 | 0 |
| constraint:notnull | 1 | 1 | 0 | 0 |
| constraint:pk | 1 | 1 | 0 | 0 |
| constraint:unique | 1 | 1 | 0 | 0 |
| datatype:ddl | 47 | 47 | 0 | 0 |
| datatype:edge | 25 | 25 | 0 | 0 |
| datatype:edge2 | 54 | 53 | 1 | 0 |
| datatype:roundtrip | 47 | 46 | 1 | 0 |
| dbal:information_schema | 8 | 7 | 1 | 0 |
| dbal:introspection | 9 | 7 | 2 | 0 |
| dbal:introspection2 | 12 | 8 | 4 | 0 |
| dbal:platform | 12 | 7 | 5 | 0 |
| dbal:querybuilder | 12 | 12 | 0 | 0 |
| dbal:querybuilder2 | 37 | 37 | 0 | 0 |
| dbal:schema-ddl | 3 | 2 | 1 | 0 |
| dbal:transaction | 3 | 2 | 1 | 0 |
| dbal:type | 19 | 18 | 1 | 0 |
| dbal:type2 | 37 | 37 | 0 | 0 |
| ddl2:alter | 12 | 8 | 4 | 0 |
| ddl2:create | 16 | 15 | 1 | 0 |
| ddl2:drop | 3 | 3 | 0 | 0 |
| ddl2:partition | 4 | 4 | 0 | 0 |
| ddl:alter | 18 | 17 | 1 | 0 |
| ddl:create | 19 | 19 | 0 | 0 |
| ddl:drop | 3 | 3 | 0 | 0 |
| ddl:view | 3 | 3 | 0 | 0 |
| dml2:delete | 5 | 4 | 1 | 0 |
| dml2:insert | 9 | 8 | 1 | 0 |
| dml2:update | 5 | 5 | 0 | 0 |
| dml:delete | 4 | 3 | 1 | 0 |
| dml:insert | 7 | 7 | 0 | 0 |
| dml:update | 5 | 5 | 0 | 0 |
| expression | 12 | 12 | 0 | 0 |
| feature:collation | 5 | 3 | 2 | 0 |
| feature:fulltext | 3 | 3 | 0 | 0 |
| feature:upsert | 4 | 4 | 0 | 0 |
| feature:vector | 8 | 8 | 0 | 0 |
| function2 | 19 | 18 | 1 | 0 |
| function:aggregate | 46 | 46 | 0 | 0 |
| function:bit | 12 | 11 | 1 | 0 |
| function:cast | 56 | 47 | 9 | 0 |
| function:comparison | 40 | 35 | 5 | 0 |
| function:conditional | 23 | 22 | 1 | 0 |
| function:datetime | 172 | 162 | 10 | 0 |
| function:encryption | 10 | 9 | 1 | 0 |
| function:flow | 8 | 8 | 0 | 0 |
| function:info | 12 | 10 | 2 | 0 |
| function:json | 102 | 64 | 38 | 0 |
| function:math | 70 | 65 | 5 | 0 |
| function:numeric | 55 | 53 | 2 | 0 |
| function:scalar | 26 | 24 | 2 | 0 |
| function:spatial | 12 | 3 | 9 | 0 |
| function:string | 165 | 153 | 12 | 0 |
| function:vector | 7 | 6 | 1 | 0 |
| function:window | 37 | 37 | 0 | 0 |
| index:create | 11 | 9 | 2 | 0 |
| index:drop | 1 | 1 | 0 | 0 |
| introspection | 26 | 21 | 5 | 0 |
| introspection2 | 58 | 44 | 14 | 0 |
| join:agg | 2 | 2 | 0 | 0 |
| join:anti | 2 | 2 | 0 | 0 |
| join:chain | 3 | 3 | 0 | 0 |
| join:cross | 2 | 2 | 0 | 0 |
| join:expr | 4 | 4 | 0 | 0 |
| join:full | 1 | 1 | 0 | 0 |
| join:multi | 3 | 3 | 0 | 0 |
| join:natural | 1 | 1 | 0 | 0 |
| join:self | 2 | 2 | 0 | 0 |
| join:semi | 2 | 2 | 0 | 0 |
| join:straight | 1 | 1 | 0 | 0 |
| join:subq | 2 | 2 | 0 | 0 |
| join:using | 2 | 2 | 0 | 0 |
| json | 6 | 4 | 2 | 0 |
| misc | 4 | 3 | 1 | 0 |
| model2 | 32 | 32 | 0 | 0 |
| model:crud | 11 | 11 | 0 | 0 |
| orm:association | 2 | 2 | 0 | 0 |
| orm:association2 | 11 | 11 | 0 | 0 |
| orm:crud | 4 | 4 | 0 | 0 |
| orm:crud2 | 12 | 12 | 0 | 0 |
| orm:dql | 3 | 3 | 0 | 0 |
| orm:dql2 | 33 | 33 | 0 | 0 |
| orm:lifecycle | 4 | 4 | 0 | 0 |
| orm:pagination | 1 | 1 | 0 | 0 |
| orm:repository | 1 | 1 | 0 | 0 |
| orm:repository2 | 13 | 12 | 1 | 0 |
| orm:schema-tool | 1 | 1 | 0 | 0 |
| pagination2 | 8 | 8 | 0 | 0 |
| prepared:metadata | 3 | 3 | 0 | 0 |
| prepared:non-preparable | 7 | 1 | 6 | 0 |
| prepared:param | 12 | 12 | 0 | 0 |
| prepared:typed-bind | 18 | 18 | 0 | 0 |
| query-builder | 37 | 37 | 0 | 0 |
| query-builder2 | 112 | 110 | 2 | 0 |
| query:cte | 2 | 2 | 0 | 0 |
| query:group | 3 | 3 | 0 | 0 |
| query:join | 7 | 7 | 0 | 0 |
| query:misc | 7 | 7 | 0 | 0 |
| query:select | 7 | 6 | 1 | 0 |
| query:setop | 4 | 4 | 0 | 0 |
| query:subquery | 5 | 5 | 0 | 0 |
| relationship | 10 | 10 | 0 | 0 |
| relationship2 | 32 | 32 | 0 | 0 |
| schema | 3 | 2 | 1 | 0 |
| schema2 | 6 | 4 | 2 | 0 |
| schema:modifier | 19 | 17 | 2 | 0 |
| schema:operation | 13 | 13 | 0 | 0 |
| schema:type | 45 | 45 | 0 | 0 |
| scope | 6 | 6 | 0 | 0 |
| softdelete | 8 | 8 | 0 | 0 |
| sql:advanced | 15 | 15 | 0 | 0 |
| sql:cte | 18 | 18 | 0 | 0 |
| sql:grouping | 25 | 23 | 2 | 0 |
| sql:setop | 18 | 17 | 1 | 0 |
| sql:subquery | 25 | 24 | 1 | 0 |
| sql:window | 28 | 26 | 2 | 0 |
| transaction | 6 | 5 | 1 | 0 |
| transaction2 | 35 | 23 | 12 | 0 |
| transaction:autocommit | 1 | 1 | 0 | 0 |
| transaction:commit | 1 | 1 | 0 | 0 |
| transaction:isolation | 4 | 4 | 0 | 0 |
| transaction:lock | 1 | 0 | 1 | 0 |
| transaction:rollback | 1 | 1 | 0 | 0 |
| transaction:savepoint | 1 | 0 | 1 | 0 |
| type | 10 | 10 | 0 | 0 |
| type2 | 21 | 21 | 0 | 0 |

## Compatibility issues (failures) grouped by error signature

### Error `BEHAVIOR` — 75 scenario(s)

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
| 613 | PDO | function:numeric | CONV bin to hex |
| 682 | PDO | function:encryption | TO_BASE64 round-trip |
| 757 | PDO | feature:collation | COLLATE utf8mb4_general_ci in comparison |
| 758 | PDO | feature:collation | column COLLATE utf8mb4_general_ci case-insensitive WHERE |
| 777 | PDO | function:string | REGEXP_REPLACE pos |
| 780 | PDO | function:string | REGEXP_SUBSTR pos |
| 794 | PDO | function:string | FORMAT zero scale |
| 806 | PDO | function:string | ELT out of range |
| 834 | PDO | function:string | LOCATE with start |
| 853 | PDO | function:string | QUOTE null |
| 876 | PDO | function:math | POW fractional |
| 911 | PDO | function:math | CONV to base 36 |
| 956 | PDO | function:datetime | EXTRACT DAY_HOUR |
| 957 | PDO | function:datetime | EXTRACT HOUR_MINUTE |
| 958 | PDO | function:datetime | EXTRACT MINUTE_SECOND |
| 1008 | PDO | function:datetime | ADDTIME with date |
| 1009 | PDO | function:datetime | SUBTIME |
| 1100 | PDO | function:comparison | LIKE escape literal pct |
| 1101 | PDO | function:comparison | LIKE escape underscore |
| 1103 | PDO | function:comparison | LIKE case insensitive |
| 1158 | PDO | function:cast | CAST FLOAT |
| 1281 | PDO | sql:subquery | scalar in HAVING |
| 1340 | PDO | sql:window | RANK with ties |
| 1373 | PDO | sql:grouping | HAVING two conditions |
| 1389 | PDO | sql:grouping | VARIANCE aggregate |
| 1443 | PDO | dml2:delete | multi-table DELETE join |
| 1500 | PDO | charset | column CHARACTER SET utf8mb4 honored |
| 1502 | PDO | charset | column CHARACTER SET ascii honored |
| 1503 | PDO | charset | column CHARACTER SET latin1 honored |
| 1514 | PDO | charset | CHARSET after CONVERT |
| 1525 | PDO | collation | _general_ci case-insensitive |
| 1526 | PDO | collation | _0900_ai_ci case-insensitive |
| 1529 | PDO | collation | COLLATION after COLLATE |
| 1531 | PDO | collation | _general_ci ordering |
| 1534 | PDO | collation | WHERE COLLATE ci matches both |
| 1536 | PDO | collation | GROUP BY COLLATE ci buckets |
| 1538 | PDO | collation | DISTINCT COLLATE ci |
| 1539 | PDO | collation | column COLLATE utf8mb4_bin honored |
| 1540 | PDO | collation | column COLLATE utf8mb4_general_ci honored |
| 1541 | PDO | collation | column COLLATE utf8mb4_0900_ai_ci honored |
| 1542 | PDO | collation | illegal mix of collations rejected |
| 1550 | PDO | transaction2 | write in READ ONLY tx rejected |
| 1552 | PDO | transaction2 | DDL implicit commit |
| 1563 | PDO | introspection2 | COLUMNS DATA_TYPE smallint |
| 1567 | PDO | introspection2 | COLUMNS COLUMN_DEFAULT string |
| 1571 | PDO | introspection2 | COLUMNS CHAR_MAX_LENGTH text |
| 1574 | PDO | introspection2 | COLUMNS NUMERIC_PRECISION int |
| 1576 | PDO | introspection2 | COLUMNS COLUMN_TYPE int |
| 1577 | PDO | introspection2 | COLUMNS COLUMN_TYPE unsigned |
| 1582 | PDO | introspection2 | KEY_COLUMN_USAGE primary key |
| 1583 | PDO | introspection2 | KEY_COLUMN_USAGE foreign key referenced |
| 1584 | PDO | introspection2 | TABLE_CONSTRAINTS PK type |
| 1744 | Eloquent | transaction | nested transaction (savepoint) |
| 1944 | Eloquent | transaction2 | nested savepoint inner rollback |
| 2107 | Doctrine | dbal:introspection2 | column length on varchar |
| 2108 | Doctrine | dbal:introspection2 | decimal precision/scale |
| 2113 | Doctrine | dbal:introspection2 | listTableForeignKeys on FK table |
| 2185 | Doctrine | orm:repository2 | matching() Criteria in + limit |

### Error `1064` — 49 scenario(s)

> SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 40 near " USING BTREE";

| # | Framework | Category | Scenario |
|--:|---|---|---|
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
| 1075 | PDO | function:json | MEMBER OF true |
| 1076 | PDO | function:json | MEMBER OF false |
| 1159 | PDO | function:cast | CAST NCHAR |
| 1160 | PDO | function:cast | CAST CHAR charset |
| 1365 | PDO | sql:window | named WINDOW clause |
| 1504 | PDO | charset | column CHARACTER SET binary honored |
| 1556 | PDO | transaction2 | SELECT ... FOR SHARE |
| 1557 | PDO | transaction2 | LOCK IN SHARE MODE |
| 1558 | PDO | transaction2 | FOR UPDATE NOWAIT |
| 1559 | PDO | transaction2 | FOR UPDATE SKIP LOCKED |
| 1612 | PDO | introspection2 | information_schema.PLUGINS SELECT |
| 1613 | PDO | introspection2 | information_schema.CHECK_CONSTRAINTS SELECT |
| 1614 | PDO | introspection2 | information_schema.COLLATION_CHARACTER_SET_APPLICABILITY SELECT |
| 1616 | PDO | introspection2 | EXPLAIN ANALYZE |
| 1617 | PDO | introspection2 | EXPLAIN FORMAT=JSON |
| 1681 | Eloquent | schema:modifier | modifier spatialIndex |
| 1950 | Eloquent | transaction2 | sharedLock in transaction |
| 1953 | Doctrine | dbal:introspection | listTables (bulk) |
| 1958 | Doctrine | dbal:introspection | introspectTable |
| 1961 | Doctrine | dbal:information_schema | information_schema.COLLATION_CHARACTER_SET_APPLICABILITY |
| 1970 | Doctrine | dbal:schema-ddl | schema diff / migrate (Comparator) |
| 1978 | Doctrine | dbal:type | type float |
| 2094 | Doctrine | dbal:platform | getAlterTableSQL add column (Comparator) |
| 2095 | Doctrine | dbal:platform | getAlterTableSQL drop column (Comparator) |
| 2096 | Doctrine | dbal:platform | getAlterTableSQL change column type (Comparator) |
| 2097 | Doctrine | dbal:platform | getAlterTableSQL add index (Comparator) |
| 2098 | Doctrine | dbal:platform | getAlterTableSQL drop index (Comparator) |
| 2112 | Doctrine | dbal:introspection2 | introspectTable round-trip |
| 2192 | CakePHP | schema | describe() table reflection |
| 2315 | CakePHP | schema2 | describe() columns (known-broken) |
| 2316 | CakePHP | schema2 | describe() primaryKey (known-broken) |

### Error `20105` — 45 scenario(s)

> SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'benchmark'

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 117 | PDO | function:scalar | BENCHMARK() |
| 130 | PDO | function:string | CHARACTER_LENGTH |
| 278 | PDO | function:json | JSON_CONTAINS |
| 279 | PDO | function:json | JSON_CONTAINS_PATH |
| 282 | PDO | function:json | JSON_DEPTH |
| 286 | PDO | function:json | JSON_REMOVE |
| 287 | PDO | function:json | JSON_MERGE_PATCH |
| 288 | PDO | function:json | JSON_MERGE_PRESERVE |
| 289 | PDO | function:json | JSON_ARRAY_APPEND |
| 290 | PDO | function:json | JSON_SEARCH |
| 291 | PDO | function:json | JSON_OVERLAPS |
| 331 | PDO | function:vector | l1_distance |
| 392 | PDO | query:select | DO statement |
| 584 | PDO | function:string | WEIGHT_STRING |
| 671 | PDO | function:info | COERCIBILITY |
| 676 | PDO | function:info | UUID_SHORT |
| 696 | PDO | function:spatial | GeomFromText alias |
| 700 | PDO | function:json | JSON_STORAGE_SIZE |
| 1049 | PDO | function:json | JSON_REPLACE missing |
| 1050 | PDO | function:json | JSON_CONTAINS scalar |
| 1051 | PDO | function:json | JSON_CONTAINS missing |
| 1052 | PDO | function:json | JSON_CONTAINS object |
| 1053 | PDO | function:json | JSON_CONTAINS_PATH one |
| 1054 | PDO | function:json | JSON_CONTAINS_PATH all |
| 1055 | PDO | function:json | JSON_DEPTH flat |
| 1056 | PDO | function:json | JSON_DEPTH scalar |
| 1057 | PDO | function:json | JSON_DEPTH nested |
| 1058 | PDO | function:json | JSON_REMOVE key |
| 1059 | PDO | function:json | JSON_REMOVE array elem |
| 1060 | PDO | function:json | JSON_MERGE_PATCH override |
| 1061 | PDO | function:json | JSON_MERGE_PATCH combine |
| 1062 | PDO | function:json | JSON_MERGE_PRESERVE arrays |
| 1063 | PDO | function:json | JSON_MERGE_PRESERVE dup keys |
| 1064 | PDO | function:json | JSON_ARRAY_APPEND |
| 1065 | PDO | function:json | JSON_ARRAY_INSERT |
| 1066 | PDO | function:json | JSON_SEARCH one |
| 1067 | PDO | function:json | JSON_SEARCH all |
| 1068 | PDO | function:json | JSON_SEARCH not found |
| 1069 | PDO | function:json | JSON_SEARCH wildcard |
| 1070 | PDO | function:json | JSON_OVERLAPS true |
| 1071 | PDO | function:json | JSON_OVERLAPS false |
| 1250 | PDO | function:json | col JSON_CONTAINS on column |
| 1748 | Eloquent | misc | whereJsonContains |
| 1835 | Eloquent | json | whereJsonContains |
| 1838 | Eloquent | json | whereJsonDoesntContain |

### Error `20101` — 19 scenario(s)

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
| 1148 | PDO | function:cast | CAST CHAR(n) truncate |
| 1163 | PDO | function:cast | CONVERT CHAR |
| 1164 | PDO | function:cast | CONVERT CHAR(n) |
| 1395 | PDO | ddl2:create | functional index |
| 1553 | PDO | transaction2 | SAVEPOINT + ROLLBACK TO partial |
| 1680 | Eloquent | schema:modifier | modifier fulltext index |
| 1945 | Eloquent | transaction2 | transactionLevel tracking |
| 1946 | Eloquent | transaction2 | manual savepoint rollBack to level |
| 2005 | Doctrine | dbal:transaction | nested with savepoints |
| 2324 | CakePHP | transaction2 | nested rollback via savepoint |

### Error `20203` — 16 scenario(s)

> SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 236 | PDO | function:datetime | HOUR |
| 237 | PDO | function:datetime | MINUTE |
| 238 | PDO | function:datetime | SECOND |
| 497 | PDO | behavior:coercion | trailing-text string add (warns, =15) |
| 498 | PDO | behavior:coercion | CAST non-numeric to UNSIGNED = 0 |
| 501 | PDO | behavior:null | GREATEST with NULL is NULL |
| 585 | PDO | function:string | CHAR USING |
| 615 | PDO | function:numeric | RAND seeded |
| 779 | PDO | function:string | REGEXP_REPLACE ci flag |
| 830 | PDO | function:string | CHAR with using |
| 879 | PDO | function:math | SQRT negative null |
| 907 | PDO | function:math | RAND seeded deterministic |
| 925 | PDO | function:math | LEAST with null |
| 1117 | PDO | function:comparison | GREATEST with null |
| 1118 | PDO | function:comparison | LEAST with null |
| 1128 | PDO | function:conditional | IF null cond |

### Error `20301` — 6 scenario(s)

> SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in copy mode: alter ALGORITHM not enforce

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1417 | PDO | ddl2:alter | ALTER ALGORITHM=INPLACE |
| 1418 | PDO | ddl2:alter | ALTER ALGORITHM=COPY |
| 1419 | PDO | ddl2:alter | ALTER LOCK=NONE |
| 1423 | PDO | ddl2:alter | ALTER set AUTO_INCREMENT |
| 1516 | PDO | charset | _ascii introducer |
| 2265 | CakePHP | function2 | func coalesce |

### Error `1690` — 3 scenario(s)

> SQLSTATE[HY000]: General error: 1690 data out of range: data type uint64, value '-1'

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1146 | PDO | function:cast | CAST UNSIGNED wrap |
| 1185 | PDO | function:bit | NOT then mask |
| 1481 | PDO | datatype:edge2 | BIT(8) value |

### Error `ERROR` — 2 scenario(s)

> PDOStatement::fetchAll(): Malformed server packet. Field length pointing after the end of packet

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 459 | PDO | introspection | SHOW VARIABLES |
| 571 | PDO | prepared:non-preparable | prepare SHOW VARIABLES LIKE |

### Error `20102` — 2 scenario(s)

> SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1309 | PDO | sql:setop | EXCEPT ALL |
| 1428 | PDO | dml2:insert | INSERT with subquery value |

### Error `20405` — 1 scenario(s)

> SQLSTATE[HY000]: General error: 20405 file path is not found

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 854 | PDO | function:string | LOAD_FILE missing |

### Error `1062` — 1 scenario(s)

> SQLSTATE[HY000]: General error: 1062 Duplicate entry '(alice,NY)' for key '(name,city)'

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1792 | Eloquent | query-builder2 | upsert multi unique cols |

### Error `1149` — 1 scenario(s)

> SQLSTATE[HY000]: General error: 1149 SQL syntax error: column "ck2_1534_f76bd.name" must appear in the GROUP BY clause or be used in an aggregate function

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 2238 | CakePHP | query-builder2 | distinct on column |

## Detailed failures

#### #36 [PDO / datatype:roundtrip] round-trip FLOAT

- **Code:** `BEHAVIOR`
- **Message:** FLOAT numeric round-trip drifted: expected 3.5 got 4.0

#### #111 [PDO / function:scalar] CONVERT()

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '123' from BIGINT type to CHAR type. 123 is larger than Dest length 1

#### #117 [PDO / function:scalar] BENCHMARK()

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'benchmark'

#### #130 [PDO / function:string] CHARACTER_LENGTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'character_length'

#### #236 [PDO / function:datetime] HOUR

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #237 [PDO / function:datetime] MINUTE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #238 [PDO / function:datetime] SECOND

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument parse timestamp, bad value 10:20:30

#### #253 [PDO / function:datetime] ADDTIME

- **Code:** `BEHAVIOR`
- **Message:** ADDTIME: expected '11:00:00', got '2026-06-23 11:00:00.000000'

#### #254 [PDO / function:datetime] SUBTIME

- **Code:** `BEHAVIOR`
- **Message:** SUBTIME: expected '09:00:00', got '2026-06-23 09:00:00.000000'

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

#### #331 [PDO / function:vector] l1_distance

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'l1_distance'

#### #365 [PDO / ddl:alter] ALTER ADD CHECK

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: unsupported alter option in inplace mode: check (age > 0)

#### #390 [PDO / dml:delete] multi-table DELETE join

- **Code:** `BEHAVIOR`
- **Message:** multi-table DELETE join: expected '1', got 0

#### #392 [PDO / query:select] DO statement

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: do 1

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
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 36 near " DESCRIBE `i_437_14cb0`";

#### #480 [PDO / introspection] EXPLAIN SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN SELECT * FROM `i_438_ed2bc`";

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

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function greatest, bad value [BIGINT ANY]

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

#### #584 [PDO / function:string] WEIGHT_STRING

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'weight_string'

#### #585 [PDO / function:string] CHAR USING

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value utf8mb4

#### #613 [PDO / function:numeric] CONV bin to hex

- **Code:** `BEHAVIOR`
- **Message:** CONV('1010', 2, 16): expected 'A', got 'a'

#### #615 [PDO / function:numeric] RAND seeded

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function rand, bad value [BIGINT]

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

#### #688 [PDO / function:spatial] POINT constructor

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 51 near "(3, 4)) AS v";

#### #689 [PDO / function:spatial] ST_X

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near "(3, 4)) AS v";

#### #690 [PDO / function:spatial] ST_Y

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near "(3, 4)) AS v";

#### #692 [PDO / function:spatial] ST_Distance

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near "(0,0), POINT(3,4)) AS v";

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

#### #757 [PDO / feature:collation] COLLATE utf8mb4_general_ci in comparison

- **Code:** `BEHAVIOR`
- **Message:** explicit _ci collation should give case-insensitive equality (1); MatrixOne returned 0

#### #758 [PDO / feature:collation] column COLLATE utf8mb4_general_ci case-insensitive WHERE

- **Code:** `BEHAVIOR`
- **Message:** explicit _ci column collation should match 'ALICE'=='alice'; got 0 rows

#### #777 [PDO / function:string] REGEXP_REPLACE pos

- **Code:** `BEHAVIOR`
- **Message:** REGEXP_REPLACE('a1b2c3','[0-9]','#',2): expected 'a1b#c#', got 'a#b#c#'

#### #779 [PDO / function:string] REGEXP_REPLACE ci flag

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function regexp_replace, bad value [VARCHAR VARCHAR VARCHAR BIGINT BIGINT VARCHAR]

#### #780 [PDO / function:string] REGEXP_SUBSTR pos

- **Code:** `BEHAVIOR`
- **Message:** REGEXP_SUBSTR('a1b22c333','[0-9]+',2): expected '22', got '1'

#### #794 [PDO / function:string] FORMAT zero scale

- **Code:** `BEHAVIOR`
- **Message:** FORMAT(1234.567, 0): expected '1,235', got '1,234'

#### #806 [PDO / function:string] ELT out of range

- **Code:** `BEHAVIOR`
- **Message:** returned NULL/false

#### #830 [PDO / function:string] CHAR with using

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value utf8mb4

#### #834 [PDO / function:string] LOCATE with start

- **Code:** `BEHAVIOR`
- **Message:** LOCATE('a', 'banana', 2): expected '4', got 2

#### #853 [PDO / function:string] QUOTE null

- **Code:** `BEHAVIOR`
- **Message:** returned NULL/false

#### #854 [PDO / function:string] LOAD_FILE missing

- **Code:** `20405`
- **Message:** SQLSTATE[HY000]: General error: 20405 file path is not found

#### #876 [PDO / function:math] POW fractional

- **Code:** `BEHAVIOR`
- **Message:** POW(27, 1.0/3.0): expected '3', got 2.9999996704163316

#### #879 [PDO / function:math] SQRT negative null

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument Sqrt, bad value -1

#### #907 [PDO / function:math] RAND seeded deterministic

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function rand, bad value [BIGINT]

#### #911 [PDO / function:math] CONV to base 36

- **Code:** `BEHAVIOR`
- **Message:** CONV(35, 10, 36): expected 'Z', got 'z'

#### #925 [PDO / function:math] LEAST with null

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [BIGINT ANY BIGINT]

#### #956 [PDO / function:datetime] EXTRACT DAY_HOUR

- **Code:** `BEHAVIOR`
- **Message:** EXTRACT(DAY_HOUR FROM '2026-06-23 14:25:36'): expected '2314', got '23 14'

#### #957 [PDO / function:datetime] EXTRACT HOUR_MINUTE

- **Code:** `BEHAVIOR`
- **Message:** EXTRACT(HOUR_MINUTE FROM '2026-06-23 14:25:36'): expected '1425', got '14:25'

#### #958 [PDO / function:datetime] EXTRACT MINUTE_SECOND

- **Code:** `BEHAVIOR`
- **Message:** EXTRACT(MINUTE_SECOND FROM '2026-06-23 14:25:36'): expected '2536', got '25:36'

#### #1008 [PDO / function:datetime] ADDTIME with date

- **Code:** `BEHAVIOR`
- **Message:** ADDTIME('2026-01-01 23:59:59', '00:00:02'): expected '2026-01-02 00:00:01', got '2026-01-02 00:00:01.000000'

#### #1009 [PDO / function:datetime] SUBTIME

- **Code:** `BEHAVIOR`
- **Message:** SUBTIME('2026-01-01 00:00:01', '00:00:02'): expected '2025-12-31 23:59:59', got '2025-12-31 23:59:59.000000'

#### #1049 [PDO / function:json] JSON_REPLACE missing

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains_path'

#### #1050 [PDO / function:json] JSON_CONTAINS scalar

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1051 [PDO / function:json] JSON_CONTAINS missing

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1052 [PDO / function:json] JSON_CONTAINS object

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1053 [PDO / function:json] JSON_CONTAINS_PATH one

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains_path'

#### #1054 [PDO / function:json] JSON_CONTAINS_PATH all

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains_path'

#### #1055 [PDO / function:json] JSON_DEPTH flat

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #1056 [PDO / function:json] JSON_DEPTH scalar

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #1057 [PDO / function:json] JSON_DEPTH nested

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #1058 [PDO / function:json] JSON_REMOVE key

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_remove'

#### #1059 [PDO / function:json] JSON_REMOVE array elem

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_remove'

#### #1060 [PDO / function:json] JSON_MERGE_PATCH override

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_patch'

#### #1061 [PDO / function:json] JSON_MERGE_PATCH combine

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_patch'

#### #1062 [PDO / function:json] JSON_MERGE_PRESERVE arrays

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_preserve'

#### #1063 [PDO / function:json] JSON_MERGE_PRESERVE dup keys

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_preserve'

#### #1064 [PDO / function:json] JSON_ARRAY_APPEND

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_array_append'

#### #1065 [PDO / function:json] JSON_ARRAY_INSERT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_array_insert'

#### #1066 [PDO / function:json] JSON_SEARCH one

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #1067 [PDO / function:json] JSON_SEARCH all

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #1068 [PDO / function:json] JSON_SEARCH not found

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #1069 [PDO / function:json] JSON_SEARCH wildcard

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #1070 [PDO / function:json] JSON_OVERLAPS true

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_overlaps'

#### #1071 [PDO / function:json] JSON_OVERLAPS false

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_overlaps'

#### #1075 [PDO / function:json] MEMBER OF true

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near " OF('[1,2,3]') AS v";

#### #1076 [PDO / function:json] MEMBER OF false

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 46 near " OF('[1,2,3]') AS v";

#### #1100 [PDO / function:comparison] LIKE escape literal pct

- **Code:** `BEHAVIOR`
- **Message:** '50%' LIKE '50!%' ESCAPE '!': expected '1', got 0

#### #1101 [PDO / function:comparison] LIKE escape underscore

- **Code:** `BEHAVIOR`
- **Message:** 'a_b' LIKE 'a!_b' ESCAPE '!': expected '1', got 0

#### #1103 [PDO / function:comparison] LIKE case insensitive

- **Code:** `BEHAVIOR`
- **Message:** 'ABC' LIKE 'abc': expected '1', got 0

#### #1117 [PDO / function:comparison] GREATEST with null

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function greatest, bad value [BIGINT ANY BIGINT]

#### #1118 [PDO / function:comparison] LEAST with null

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [BIGINT ANY BIGINT]

#### #1128 [PDO / function:conditional] IF null cond

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function if, bad value [ANY VARCHAR VARCHAR]

#### #1146 [PDO / function:cast] CAST UNSIGNED wrap

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type uint64, value '-1'

#### #1148 [PDO / function:cast] CAST CHAR(n) truncate

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '123456' from BIGINT type to CHAR type. 123456 is larger than Dest length 3

#### #1158 [PDO / function:cast] CAST FLOAT

- **Code:** `BEHAVIOR`
- **Message:** CAST('1.25' AS FLOAT): expected '1.25', got 1.0

#### #1159 [PDO / function:cast] CAST NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 54 near " NCHAR) AS v";

#### #1160 [PDO / function:cast] CAST CHAR charset

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 63 near " CHARACTER SET utf8mb4) AS v";

#### #1163 [PDO / function:cast] CONVERT CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '456' from BIGINT type to CHAR type. 456 is larger than Dest length 1

#### #1164 [PDO / function:cast] CONVERT CHAR(n)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '123456' from BIGINT type to CHAR type. 123456 is larger than Dest length 4

#### #1185 [PDO / function:bit] NOT then mask

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, value '18446744073709551615'

#### #1250 [PDO / function:json] col JSON_CONTAINS on column

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1281 [PDO / sql:subquery] scalar in HAVING

- **Code:** `BEHAVIOR`
- **Message:** scalar in HAVING: expected 'a', got 'b'

#### #1309 [PDO / sql:setop] EXCEPT ALL

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

#### #1340 [PDO / sql:window] RANK with ties

- **Code:** `BEHAVIOR`
- **Message:** RANK with ties: expected '4', got 5

#### #1365 [PDO / sql:window] named WINDOW clause

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 78 near " w s FROM sc_758_17191 WINDOW w AS (ORDER BY id)) z)";

#### #1373 [PDO / sql:grouping] HAVING two conditions

- **Code:** `BEHAVIOR`
- **Message:** HAVING two conditions: expected '2', got 1

#### #1389 [PDO / sql:grouping] VARIANCE aggregate

- **Code:** `BEHAVIOR`
- **Message:** VARIANCE aggregate: expected '171', got 164.0

#### #1395 [PDO / ddl2:create] functional index

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: unsupported index which using expression as keypart

#### #1417 [PDO / ddl2:alter] ALTER ALGORITHM=INPLACE

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in copy mode: alter ALGORITHM not enforce

#### #1418 [PDO / ddl2:alter] ALTER ALGORITHM=COPY

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in copy mode: alter ALGORITHM not enforce

#### #1419 [PDO / ddl2:alter] ALTER LOCK=NONE

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in copy mode: charset = LOCK

#### #1423 [PDO / ddl2:alter] ALTER set AUTO_INCREMENT

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in inplace mode: auto_increment = 500

#### #1428 [PDO / dml2:insert] INSERT with subquery value

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 subquery in JOIN condition is not yet implemented

#### #1443 [PDO / dml2:delete] multi-table DELETE join

- **Code:** `BEHAVIOR`
- **Message:** multi-table DELETE join: expected '1', got 0

#### #1481 [PDO / datatype:edge2] BIT(8) value

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type bit(8), value 255

#### #1500 [PDO / charset] column CHARACTER SET utf8mb4 honored

- **Code:** `BEHAVIOR`
- **Message:** declared CHARACTER SET=utf8mb4, information_schema reports='utf8'

#### #1502 [PDO / charset] column CHARACTER SET ascii honored

- **Code:** `BEHAVIOR`
- **Message:** declared CHARACTER SET=ascii, information_schema reports='utf8'

#### #1503 [PDO / charset] column CHARACTER SET latin1 honored

- **Code:** `BEHAVIOR`
- **Message:** declared CHARACTER SET=latin1, information_schema reports='utf8'

#### #1504 [PDO / charset] column CHARACTER SET binary honored

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 64 near " binary)";

#### #1514 [PDO / charset] CHARSET after CONVERT

- **Code:** `BEHAVIOR`
- **Message:** CHARSET after CONVERT: expected 'ascii', got 'utf8mb4'

#### #1516 [PDO / charset] _ascii introducer

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: column _ascii does not exist

#### #1525 [PDO / collation] _general_ci case-insensitive

- **Code:** `BEHAVIOR`
- **Message:** _general_ci case-insensitive: expected '1', got 0

#### #1526 [PDO / collation] _0900_ai_ci case-insensitive

- **Code:** `BEHAVIOR`
- **Message:** _0900_ai_ci case-insensitive: expected '1', got 0

#### #1529 [PDO / collation] COLLATION after COLLATE

- **Code:** `BEHAVIOR`
- **Message:** COLLATION after COLLATE: expected 'utf8mb4_bin', got 'utf8mb4_general_ci'

#### #1531 [PDO / collation] _general_ci ordering

- **Code:** `BEHAVIOR`
- **Message:** _general_ci ordering: expected '0', got 1

#### #1534 [PDO / collation] WHERE COLLATE ci matches both

- **Code:** `BEHAVIOR`
- **Message:** WHERE COLLATE ci matches both: expected '2', got 1

#### #1536 [PDO / collation] GROUP BY COLLATE ci buckets

- **Code:** `BEHAVIOR`
- **Message:** GROUP BY COLLATE ci buckets: expected '2', got 4

#### #1538 [PDO / collation] DISTINCT COLLATE ci

- **Code:** `BEHAVIOR`
- **Message:** DISTINCT COLLATE ci: expected '2', got 4

#### #1539 [PDO / collation] column COLLATE utf8mb4_bin honored

- **Code:** `BEHAVIOR`
- **Message:** declared COLLATE=utf8mb4_bin, information_schema reports='utf8_bin'

#### #1540 [PDO / collation] column COLLATE utf8mb4_general_ci honored

- **Code:** `BEHAVIOR`
- **Message:** declared COLLATE=utf8mb4_general_ci, information_schema reports='utf8_bin'

#### #1541 [PDO / collation] column COLLATE utf8mb4_0900_ai_ci honored

- **Code:** `BEHAVIOR`
- **Message:** declared COLLATE=utf8mb4_0900_ai_ci, information_schema reports='utf8_bin'

#### #1542 [PDO / collation] illegal mix of collations rejected

- **Code:** `BEHAVIOR`
- **Message:** statement was NOT rejected (accepted unexpectedly)

#### #1550 [PDO / transaction2] write in READ ONLY tx rejected

- **Code:** `BEHAVIOR`
- **Message:** write was allowed inside READ ONLY transaction

#### #1552 [PDO / transaction2] DDL implicit commit

- **Code:** `BEHAVIOR`
- **Message:** MySQL: DDL implicitly commits prior INSERT (count=1); MatrixOne count=0

#### #1553 [PDO / transaction2] SAVEPOINT + ROLLBACK TO partial

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #1556 [PDO / transaction2] SELECT ... FOR SHARE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 79 near " SHARE";

#### #1557 [PDO / transaction2] LOCK IN SHARE MODE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 74 near " LOCK IN SHARE MODE";

#### #1558 [PDO / transaction2] FOR UPDATE NOWAIT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 87 near " NOWAIT";

#### #1559 [PDO / transaction2] FOR UPDATE SKIP LOCKED

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 74 near " SKIP LOCKED";

#### #1563 [PDO / introspection2] COLUMNS DATA_TYPE smallint

- **Code:** `BEHAVIOR`
- **Message:** MySQL=DATA_TYPE 'smallint', MatrixOne='SMALLINT UNSIGNED'

#### #1567 [PDO / introspection2] COLUMNS COLUMN_DEFAULT string

- **Code:** `BEHAVIOR`
- **Message:** MySQL=COLUMN_DEFAULT 'anon', MatrixOne='\'anon\''

#### #1571 [PDO / introspection2] COLUMNS CHAR_MAX_LENGTH text

- **Code:** `BEHAVIOR`
- **Message:** MySQL=CHARACTER_MAXIMUM_LENGTH '65535', MatrixOne=0

#### #1574 [PDO / introspection2] COLUMNS NUMERIC_PRECISION int

- **Code:** `BEHAVIOR`
- **Message:** MySQL=NUMERIC_PRECISION '10', MatrixOne=NULL

#### #1576 [PDO / introspection2] COLUMNS COLUMN_TYPE int

- **Code:** `BEHAVIOR`
- **Message:** MySQL=COLUMN_TYPE 'int', MatrixOne='INT(32)'

#### #1577 [PDO / introspection2] COLUMNS COLUMN_TYPE unsigned

- **Code:** `BEHAVIOR`
- **Message:** MySQL=COLUMN_TYPE 'smallint unsigned', MatrixOne='SMALLINT UNSIGNED(16)'

#### #1582 [PDO / introspection2] KEY_COLUMN_USAGE primary key

- **Code:** `BEHAVIOR`
- **Message:** PK columns in KEY_COLUMN_USAGE: expected '2', got 0

#### #1583 [PDO / introspection2] KEY_COLUMN_USAGE foreign key referenced

- **Code:** `BEHAVIOR`
- **Message:** MySQL REFERENCED_TABLE_NAME='iskp_1130_9d177', MatrixOne=false

#### #1584 [PDO / introspection2] TABLE_CONSTRAINTS PK type

- **Code:** `BEHAVIOR`
- **Message:** PK constraint type: expected 'PRIMARY KEY', got 'PRIMARY'

#### #1612 [PDO / introspection2] information_schema.PLUGINS SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "plugins" does not exist

#### #1613 [PDO / introspection2] information_schema.CHECK_CONSTRAINTS SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "check_constraints" does not exist

#### #1614 [PDO / introspection2] information_schema.COLLATION_CHARACTER_SET_APPLICABILITY SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #1616 [PDO / introspection2] EXPLAIN ANALYZE

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN ANALYZE SELECT * FROM `ise_1138_47aab`";

#### #1617 [PDO / introspection2] EXPLAIN FORMAT=JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN FORMAT=JSON SELECT * FROM `ise_1139_89a55`";

#### #1680 [Eloquent / schema:modifier] modifier fulltext index

```sql
alter table `el_1202_c48d3` add fulltext `el_1202_c48d3_c_fulltext`(`c`)
```

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: primary key cannot be empty for fulltext index

#### #1681 [Eloquent / schema:modifier] modifier spatialIndex

```sql
alter table `el_1203_fc109` add spatial index `el_1203_fc109_c_spatialindex`(`c`)
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 75 near " index `el_1203_fc109_c_spatialindex`(`c`)";

#### #1744 [Eloquent / transaction] nested transaction (savepoint)

- **Code:** `BEHAVIOR`
- **Message:** nested savepoint semantics: expected 1, got 2

#### #1748 [Eloquent / misc] whereJsonContains

```sql
select count(*) as aggregate from `mo_users` where json_contains(`meta`, ?, '$."roles"')
```

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1792 [Eloquent / query-builder2] upsert multi unique cols

```sql
insert into `qb_1287_9793a` (`age`, `city`, `name`, `score`) values (?, ?, ?, ?) on duplicate key update `age` = values(`age`)
```

- **Code:** `1062`
- **Message:** SQLSTATE[HY000]: General error: 1062 Duplicate entry '(alice,NY)' for key '(name,city)'

#### #1835 [Eloquent / json] whereJsonContains

```sql
select count(*) as aggregate from `json_1330_505f7` where json_contains(`doc`, ?, '$."roles"')
```

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1838 [Eloquent / json] whereJsonDoesntContain

```sql
select count(*) as aggregate from `json_1333_8cdf7` where not json_contains(`doc`, ?, '$."roles"')
```

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1944 [Eloquent / transaction2] nested savepoint inner rollback

- **Code:** `BEHAVIOR`
- **Message:** nested savepoint: only outer persists: expected 1, got 2

#### #1945 [Eloquent / transaction2] transactionLevel tracking

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #1946 [Eloquent / transaction2] manual savepoint rollBack to level

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #1950 [Eloquent / transaction2] sharedLock in transaction

```sql
select * from `mo_users` where `name` = ? lock in share mode
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 77 near " lock in share mode";

#### #1953 [Doctrine / dbal:introspection] listTables (bulk)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #1958 [Doctrine / dbal:introspection] introspectTable

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #1961 [Doctrine / dbal:information_schema] information_schema.COLLATION_CHARACTER_SET_APPLICABILITY

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #1970 [Doctrine / dbal:schema-ddl] schema diff / migrate (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #1978 [Doctrine / dbal:type] type float

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 63 near " PRECISION DEFAULT NULL, PRIMARY KEY (id))";

#### #2005 [Doctrine / dbal:transaction] nested with savepoints

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

#### #2094 [Doctrine / dbal:platform] getAlterTableSQL add column (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2095 [Doctrine / dbal:platform] getAlterTableSQL drop column (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2096 [Doctrine / dbal:platform] getAlterTableSQL change column type (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2097 [Doctrine / dbal:platform] getAlterTableSQL add index (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2098 [Doctrine / dbal:platform] getAlterTableSQL drop index (Comparator)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2107 [Doctrine / dbal:introspection2] column length on varchar

- **Code:** `BEHAVIOR`
- **Message:** varchar length: expected 20, got NULL

#### #2108 [Doctrine / dbal:introspection2] decimal precision/scale

- **Code:** `BEHAVIOR`
- **Message:** precision: expected 12, got NULL

#### #2112 [Doctrine / dbal:introspection2] introspectTable round-trip

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "collation_character_set_applicability" does not exist

#### #2113 [Doctrine / dbal:introspection2] listTableForeignKeys on FK table

- **Code:** `BEHAVIOR`
- **Message:** no foreign keys found

#### #2185 [Doctrine / orm:repository2] matching() Criteria in + limit

- **Code:** `BEHAVIOR`
- **Message:** matching in+limit: expected 2, got 3

#### #2192 [CakePHP / schema] describe() table reflection

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "check_constraints" does not exist

#### #2238 [CakePHP / query-builder2] distinct on column

- **Code:** `1149`
- **Message:** SQLSTATE[HY000]: General error: 1149 SQL syntax error: column "ck2_1534_f76bd.name" must appear in the GROUP BY clause or be used in an aggregate function

#### #2265 [CakePHP / function2] func coalesce

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: column fallback does not exist

#### #2315 [CakePHP / schema2] describe() columns (known-broken)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "check_constraints" does not exist

#### #2316 [CakePHP / schema2] describe() primaryKey (known-broken)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: table "check_constraints" does not exist

#### #2324 [CakePHP / transaction2] nested rollback via savepoint

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: savepoint has not been implemented yet. please rollback the transaction.

