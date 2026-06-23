# PHP ORM ⇆ MatrixOne Compatibility Report

- **Generated:** 2026-06-23 14:29:58 UTC
- **Target:** `127.0.0.1:6001`
- **Server version():** `8.0.30-MatrixOne-v4.0.0-rc3`
- **Scenarios:** 10009 total — **8854 passed**, **1155 failed**, **0 skipped**
- **Pass rate (excl. skipped):** 88.5%

## Summary by framework

| Framework | Total | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|--:|
| CakePHP | 136 | 130 | 6 | 0 | 95.6% |
| Doctrine | 237 | 221 | 16 | 0 | 93.2% |
| Eloquent | 504 | 489 | 15 | 0 | 97.0% |
| PDO | 8867 | 7759 | 1108 | 0 | 87.5% |
| RedBean | 265 | 255 | 10 | 0 | 96.2% |

## Summary by category

| Category | Total | Pass | Fail | Skip |
|---|--:|--:|--:|--:|
| aggregate2 | 14 | 14 | 0 | 0 |
| app:analytics | 189 | 187 | 2 | 0 |
| app:cms | 112 | 112 | 0 | 0 |
| app:ecommerce | 355 | 351 | 4 | 0 |
| app:geo | 39 | 36 | 3 | 0 |
| app:ledger | 95 | 95 | 0 | 0 |
| app:saas | 79 | 78 | 1 | 0 |
| app:social | 137 | 130 | 7 | 0 |
| app:timeseries | 105 | 105 | 0 | 0 |
| behavior:aggregate | 8 | 8 | 0 | 0 |
| behavior:autoincrement | 2 | 1 | 1 | 0 |
| behavior:coercion | 9 | 7 | 2 | 0 |
| behavior:collation | 12 | 3 | 9 | 0 |
| behavior:null | 6 | 5 | 1 | 0 |
| behavior:operator | 9 | 7 | 2 | 0 |
| behavior:strict | 6 | 6 | 0 | 0 |
| behavior:string | 2 | 2 | 0 | 0 |
| cast:matrix | 280 | 148 | 132 | 0 |
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
| ddlsurf:check | 15 | 11 | 4 | 0 |
| ddlsurf:event | 16 | 16 | 0 | 0 |
| ddlsurf:fk | 27 | 27 | 0 | 0 |
| ddlsurf:generated | 16 | 16 | 0 | 0 |
| ddlsurf:index | 12 | 12 | 0 | 0 |
| ddlsurf:partition | 33 | 31 | 2 | 0 |
| ddlsurf:proc | 29 | 29 | 0 | 0 |
| ddlsurf:trigger | 28 | 28 | 0 | 0 |
| ddlsurf:view | 62 | 62 | 0 | 0 |
| dml2:delete | 5 | 4 | 1 | 0 |
| dml2:insert | 9 | 8 | 1 | 0 |
| dml2:update | 5 | 5 | 0 | 0 |
| dml:delete | 4 | 3 | 1 | 0 |
| dml:insert | 7 | 7 | 0 | 0 |
| dml:update | 5 | 5 | 0 | 0 |
| eloq-app:analytics | 14 | 13 | 1 | 0 |
| eloq-app:cms | 14 | 14 | 0 | 0 |
| eloq-app:ecommerce | 30 | 29 | 1 | 0 |
| eloq-app:ledger | 13 | 12 | 1 | 0 |
| eloq-app:social | 16 | 15 | 1 | 0 |
| eloq-json | 19 | 19 | 0 | 0 |
| eloq-migrate:column | 17 | 17 | 0 | 0 |
| eloq-migrate:fk | 11 | 11 | 0 | 0 |
| eloq-migrate:up-down | 36 | 36 | 0 | 0 |
| err:constraint | 23 | 23 | 0 | 0 |
| err:deadlock | 25 | 25 | 0 | 0 |
| err:dupkey | 28 | 28 | 0 | 0 |
| err:reconnect | 14 | 14 | 0 | 0 |
| err:timeout | 15 | 15 | 0 | 0 |
| err:type | 38 | 35 | 3 | 0 |
| expression | 12 | 12 | 0 | 0 |
| feature:collation | 5 | 3 | 2 | 0 |
| feature:fulltext | 3 | 3 | 0 | 0 |
| feature:upsert | 4 | 4 | 0 | 0 |
| feature:vector | 8 | 8 | 0 | 0 |
| fn:date-format | 35 | 35 | 0 | 0 |
| fn:date-var | 155 | 152 | 3 | 0 |
| fn:json-var | 50 | 45 | 5 | 0 |
| fn:numeric-var | 224 | 196 | 28 | 0 |
| fn:string-var | 240 | 232 | 8 | 0 |
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
| grid:arith | 1536 | 1152 | 384 | 0 |
| grid:bitwise | 250 | 250 | 0 | 0 |
| grid:compare | 1792 | 1512 | 280 | 0 |
| grid:logical | 80 | 80 | 0 | 0 |
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
| redbean:crud | 44 | 44 | 0 | 0 |
| redbean:finder | 17 | 17 | 0 | 0 |
| redbean:fluid | 40 | 40 | 0 | 0 |
| redbean:frozen | 4 | 4 | 0 | 0 |
| redbean:query | 59 | 56 | 3 | 0 |
| redbean:relation | 52 | 52 | 0 | 0 |
| redbean:transaction | 13 | 8 | 5 | 0 |
| redbean:type | 36 | 34 | 2 | 0 |
| relationship | 10 | 10 | 0 | 0 |
| relationship2 | 32 | 32 | 0 | 0 |
| schema | 3 | 2 | 1 | 0 |
| schema2 | 6 | 4 | 2 | 0 |
| schema:modifier | 19 | 17 | 2 | 0 |
| schema:operation | 13 | 13 | 0 | 0 |
| schema:type | 45 | 45 | 0 | 0 |
| scope | 6 | 6 | 0 | 0 |
| sem:aggregate | 24 | 24 | 0 | 0 |
| sem:flow | 16 | 16 | 0 | 0 |
| sem:grouping | 5 | 4 | 1 | 0 |
| sem:numeric | 35 | 35 | 0 | 0 |
| sem:operator | 47 | 46 | 1 | 0 |
| sem:precedence | 10 | 10 | 0 | 0 |
| sem:predicate | 10 | 10 | 0 | 0 |
| sem:regex | 19 | 19 | 0 | 0 |
| sem:setop | 29 | 25 | 4 | 0 |
| sem:string | 41 | 41 | 0 | 0 |
| sem:subquery | 12 | 12 | 0 | 0 |
| sem:vector-fn | 50 | 47 | 3 | 0 |
| sem:vector-hybrid | 2 | 2 | 0 | 0 |
| sem:vector-index | 27 | 27 | 0 | 0 |
| sem:vector-knn | 9 | 9 | 0 | 0 |
| sem:window | 108 | 108 | 0 | 0 |
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
| typematrix:bit | 21 | 20 | 1 | 0 |
| typematrix:charset | 16 | 9 | 7 | 0 |
| typematrix:coercion | 24 | 17 | 7 | 0 |
| typematrix:collation | 20 | 11 | 9 | 0 |
| typematrix:decimal | 45 | 43 | 2 | 0 |
| typematrix:enum | 7 | 7 | 0 | 0 |
| typematrix:json-cast | 15 | 14 | 1 | 0 |
| typematrix:json-path | 41 | 29 | 12 | 0 |
| typematrix:null | 37 | 32 | 5 | 0 |
| typematrix:overflow | 27 | 25 | 2 | 0 |
| typematrix:set | 9 | 9 | 0 | 0 |
| typematrix:string | 12 | 12 | 0 | 0 |
| typematrix:temporal | 58 | 58 | 0 | 0 |
| workload:analytics | 9 | 9 | 0 | 0 |
| workload:filter | 144 | 144 | 0 | 0 |
| workload:group | 48 | 48 | 0 | 0 |
| workload:join | 18 | 18 | 0 | 0 |
| workload:multi | 40 | 40 | 0 | 0 |
| workload:sort | 48 | 48 | 0 | 0 |
| workload:subquery | 9 | 9 | 0 | 0 |
| workload:window | 24 | 24 | 0 | 0 |

## Compatibility issues (failures) grouped by error signature

### Error `20203` — 733 scenario(s)

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
| 2398 | PDO | typematrix:coercion | coerce: '10abc' + 0 |
| 2399 | PDO | typematrix:coercion | coerce: 'abc' + 0 |
| 2402 | PDO | typematrix:coercion | coerce: '3.5' * 2 |
| 2408 | PDO | typematrix:coercion | coerce: 1 = '1.0' |
| 2411 | PDO | typematrix:coercion | coerce: 0 = 'x' |
| 2412 | PDO | typematrix:coercion | coerce: TRUE + TRUE |
| 2534 | PDO | typematrix:null | 3VL: IF(NULL, 1, 2) |
| 2535 | PDO | typematrix:null | 3VL: GREATEST(1, NULL, 3) |
| 2536 | PDO | typematrix:null | 3VL: LEAST(1, NULL, 3) |
| 2538 | PDO | typematrix:null | 3VL: NULL IN (1, NULL) |
| 2543 | PDO | typematrix:null | 3VL: CASE WHEN NULL THEN 1 ELSE 2 END |
| 2997 | PDO | app:geo | nearest place to NYC via haversine |
| 2998 | PDO | app:geo | nearest-3 places to NYC list |
| 2999 | PDO | app:geo | places within 5km of NYC |
| 4365 | PDO | sem:vector-fn | vec sub |
| 4366 | PDO | sem:vector-fn | vec mul |
| 5037 | PDO | grid:arith | int0 add mixStr |
| 5038 | PDO | grid:arith | int0 add str |
| 5039 | PDO | grid:arith | int0 add date |
| 5040 | PDO | grid:arith | int0 add dt |
| 5053 | PDO | grid:arith | int1 add mixStr |
| 5054 | PDO | grid:arith | int1 add str |
| 5055 | PDO | grid:arith | int1 add date |
| 5056 | PDO | grid:arith | int1 add dt |
| 5069 | PDO | grid:arith | intNeg add mixStr |
| 5070 | PDO | grid:arith | intNeg add str |
| 5071 | PDO | grid:arith | intNeg add date |
| 5072 | PDO | grid:arith | intNeg add dt |
| 5085 | PDO | grid:arith | intBig add mixStr |
| 5086 | PDO | grid:arith | intBig add str |
| 5087 | PDO | grid:arith | intBig add date |
| 5088 | PDO | grid:arith | intBig add dt |
| 5101 | PDO | grid:arith | bigint add mixStr |
| 5102 | PDO | grid:arith | bigint add str |
| 5103 | PDO | grid:arith | bigint add date |
| 5104 | PDO | grid:arith | bigint add dt |
| 5172 | PDO | grid:arith | mixStr add int0 |
| 5173 | PDO | grid:arith | mixStr add int1 |
| 5174 | PDO | grid:arith | mixStr add intNeg |
| 5175 | PDO | grid:arith | mixStr add intBig |
| 5176 | PDO | grid:arith | mixStr add bigint |
| 5188 | PDO | grid:arith | str add int0 |
| 5189 | PDO | grid:arith | str add int1 |
| 5190 | PDO | grid:arith | str add intNeg |
| 5191 | PDO | grid:arith | str add intBig |
| 5192 | PDO | grid:arith | str add bigint |
| 5204 | PDO | grid:arith | date add int0 |
| 5205 | PDO | grid:arith | date add int1 |
| 5206 | PDO | grid:arith | date add intNeg |
| 5207 | PDO | grid:arith | date add intBig |
| 5208 | PDO | grid:arith | date add bigint |
| 5220 | PDO | grid:arith | dt add int0 |
| 5221 | PDO | grid:arith | dt add int1 |
| 5222 | PDO | grid:arith | dt add intNeg |
| 5223 | PDO | grid:arith | dt add intBig |
| 5224 | PDO | grid:arith | dt add bigint |
| 5293 | PDO | grid:arith | int0 sub mixStr |
| 5294 | PDO | grid:arith | int0 sub str |
| 5295 | PDO | grid:arith | int0 sub date |
| 5296 | PDO | grid:arith | int0 sub dt |
| 5309 | PDO | grid:arith | int1 sub mixStr |
| 5310 | PDO | grid:arith | int1 sub str |
| 5311 | PDO | grid:arith | int1 sub date |
| 5312 | PDO | grid:arith | int1 sub dt |
| 5325 | PDO | grid:arith | intNeg sub mixStr |
| 5326 | PDO | grid:arith | intNeg sub str |
| 5327 | PDO | grid:arith | intNeg sub date |
| 5328 | PDO | grid:arith | intNeg sub dt |
| 5341 | PDO | grid:arith | intBig sub mixStr |
| 5342 | PDO | grid:arith | intBig sub str |
| 5343 | PDO | grid:arith | intBig sub date |
| 5344 | PDO | grid:arith | intBig sub dt |
| 5357 | PDO | grid:arith | bigint sub mixStr |
| 5358 | PDO | grid:arith | bigint sub str |
| 5359 | PDO | grid:arith | bigint sub date |
| 5360 | PDO | grid:arith | bigint sub dt |
| 5420 | PDO | grid:arith | numStr sub numStr |
| 5421 | PDO | grid:arith | numStr sub mixStr |
| 5422 | PDO | grid:arith | numStr sub str |
| 5423 | PDO | grid:arith | numStr sub date |
| 5424 | PDO | grid:arith | numStr sub dt |
| 5426 | PDO | grid:arith | numStr sub zeroStr |
| 5428 | PDO | grid:arith | mixStr sub int0 |
| 5429 | PDO | grid:arith | mixStr sub int1 |
| 5430 | PDO | grid:arith | mixStr sub intNeg |
| 5431 | PDO | grid:arith | mixStr sub intBig |
| 5432 | PDO | grid:arith | mixStr sub bigint |
| 5436 | PDO | grid:arith | mixStr sub numStr |
| 5437 | PDO | grid:arith | mixStr sub mixStr |
| 5438 | PDO | grid:arith | mixStr sub str |
| 5439 | PDO | grid:arith | mixStr sub date |
| 5440 | PDO | grid:arith | mixStr sub dt |
| 5442 | PDO | grid:arith | mixStr sub zeroStr |
| 5444 | PDO | grid:arith | str sub int0 |
| 5445 | PDO | grid:arith | str sub int1 |
| 5446 | PDO | grid:arith | str sub intNeg |
| 5447 | PDO | grid:arith | str sub intBig |
| 5448 | PDO | grid:arith | str sub bigint |
| 5452 | PDO | grid:arith | str sub numStr |
| 5453 | PDO | grid:arith | str sub mixStr |
| 5454 | PDO | grid:arith | str sub str |
| 5455 | PDO | grid:arith | str sub date |
| 5456 | PDO | grid:arith | str sub dt |
| 5458 | PDO | grid:arith | str sub zeroStr |
| 5460 | PDO | grid:arith | date sub int0 |
| 5461 | PDO | grid:arith | date sub int1 |
| 5462 | PDO | grid:arith | date sub intNeg |
| 5463 | PDO | grid:arith | date sub intBig |
| 5464 | PDO | grid:arith | date sub bigint |
| 5468 | PDO | grid:arith | date sub numStr |
| 5469 | PDO | grid:arith | date sub mixStr |
| 5470 | PDO | grid:arith | date sub str |
| 5471 | PDO | grid:arith | date sub date |
| 5472 | PDO | grid:arith | date sub dt |
| 5474 | PDO | grid:arith | date sub zeroStr |
| 5476 | PDO | grid:arith | dt sub int0 |
| 5477 | PDO | grid:arith | dt sub int1 |
| 5478 | PDO | grid:arith | dt sub intNeg |
| 5479 | PDO | grid:arith | dt sub intBig |
| 5480 | PDO | grid:arith | dt sub bigint |
| 5484 | PDO | grid:arith | dt sub numStr |
| 5485 | PDO | grid:arith | dt sub mixStr |
| 5486 | PDO | grid:arith | dt sub str |
| 5487 | PDO | grid:arith | dt sub date |
| 5488 | PDO | grid:arith | dt sub dt |
| 5490 | PDO | grid:arith | dt sub zeroStr |
| 5516 | PDO | grid:arith | zeroStr sub numStr |
| 5517 | PDO | grid:arith | zeroStr sub mixStr |
| 5518 | PDO | grid:arith | zeroStr sub str |
| 5519 | PDO | grid:arith | zeroStr sub date |
| 5520 | PDO | grid:arith | zeroStr sub dt |
| 5522 | PDO | grid:arith | zeroStr sub zeroStr |
| 5549 | PDO | grid:arith | int0 mul mixStr |
| 5550 | PDO | grid:arith | int0 mul str |
| 5551 | PDO | grid:arith | int0 mul date |
| 5552 | PDO | grid:arith | int0 mul dt |
| 5565 | PDO | grid:arith | int1 mul mixStr |
| 5566 | PDO | grid:arith | int1 mul str |
| 5567 | PDO | grid:arith | int1 mul date |
| 5568 | PDO | grid:arith | int1 mul dt |
| 5581 | PDO | grid:arith | intNeg mul mixStr |
| 5582 | PDO | grid:arith | intNeg mul str |
| 5583 | PDO | grid:arith | intNeg mul date |
| 5584 | PDO | grid:arith | intNeg mul dt |
| 5597 | PDO | grid:arith | intBig mul mixStr |
| 5598 | PDO | grid:arith | intBig mul str |
| 5599 | PDO | grid:arith | intBig mul date |
| 5600 | PDO | grid:arith | intBig mul dt |
| 5613 | PDO | grid:arith | bigint mul mixStr |
| 5614 | PDO | grid:arith | bigint mul str |
| 5615 | PDO | grid:arith | bigint mul date |
| 5616 | PDO | grid:arith | bigint mul dt |
| 5676 | PDO | grid:arith | numStr mul numStr |
| 5677 | PDO | grid:arith | numStr mul mixStr |
| 5678 | PDO | grid:arith | numStr mul str |
| 5679 | PDO | grid:arith | numStr mul date |
| 5680 | PDO | grid:arith | numStr mul dt |
| 5682 | PDO | grid:arith | numStr mul zeroStr |
| 5684 | PDO | grid:arith | mixStr mul int0 |
| 5685 | PDO | grid:arith | mixStr mul int1 |
| 5686 | PDO | grid:arith | mixStr mul intNeg |
| 5687 | PDO | grid:arith | mixStr mul intBig |
| 5688 | PDO | grid:arith | mixStr mul bigint |
| 5692 | PDO | grid:arith | mixStr mul numStr |
| 5693 | PDO | grid:arith | mixStr mul mixStr |
| 5694 | PDO | grid:arith | mixStr mul str |
| 5695 | PDO | grid:arith | mixStr mul date |
| 5696 | PDO | grid:arith | mixStr mul dt |
| 5698 | PDO | grid:arith | mixStr mul zeroStr |
| 5700 | PDO | grid:arith | str mul int0 |
| 5701 | PDO | grid:arith | str mul int1 |
| 5702 | PDO | grid:arith | str mul intNeg |
| 5703 | PDO | grid:arith | str mul intBig |
| 5704 | PDO | grid:arith | str mul bigint |
| 5708 | PDO | grid:arith | str mul numStr |
| 5709 | PDO | grid:arith | str mul mixStr |
| 5710 | PDO | grid:arith | str mul str |
| 5711 | PDO | grid:arith | str mul date |
| 5712 | PDO | grid:arith | str mul dt |
| 5714 | PDO | grid:arith | str mul zeroStr |
| 5716 | PDO | grid:arith | date mul int0 |
| 5717 | PDO | grid:arith | date mul int1 |
| 5718 | PDO | grid:arith | date mul intNeg |
| 5719 | PDO | grid:arith | date mul intBig |
| 5720 | PDO | grid:arith | date mul bigint |
| 5724 | PDO | grid:arith | date mul numStr |
| 5725 | PDO | grid:arith | date mul mixStr |
| 5726 | PDO | grid:arith | date mul str |
| 5727 | PDO | grid:arith | date mul date |
| 5728 | PDO | grid:arith | date mul dt |
| 5730 | PDO | grid:arith | date mul zeroStr |
| 5732 | PDO | grid:arith | dt mul int0 |
| 5733 | PDO | grid:arith | dt mul int1 |
| 5734 | PDO | grid:arith | dt mul intNeg |
| 5735 | PDO | grid:arith | dt mul intBig |
| 5736 | PDO | grid:arith | dt mul bigint |
| 5740 | PDO | grid:arith | dt mul numStr |
| 5741 | PDO | grid:arith | dt mul mixStr |
| 5742 | PDO | grid:arith | dt mul str |
| 5743 | PDO | grid:arith | dt mul date |
| 5744 | PDO | grid:arith | dt mul dt |
| 5746 | PDO | grid:arith | dt mul zeroStr |
| 5772 | PDO | grid:arith | zeroStr mul numStr |
| 5773 | PDO | grid:arith | zeroStr mul mixStr |
| 5774 | PDO | grid:arith | zeroStr mul str |
| 5775 | PDO | grid:arith | zeroStr mul date |
| 5776 | PDO | grid:arith | zeroStr mul dt |
| 5778 | PDO | grid:arith | zeroStr mul zeroStr |
| 5932 | PDO | grid:arith | numStr div numStr |
| 5933 | PDO | grid:arith | numStr div mixStr |
| 5934 | PDO | grid:arith | numStr div str |
| 5935 | PDO | grid:arith | numStr div date |
| 5936 | PDO | grid:arith | numStr div dt |
| 5938 | PDO | grid:arith | numStr div zeroStr |
| 5948 | PDO | grid:arith | mixStr div numStr |
| 5949 | PDO | grid:arith | mixStr div mixStr |
| 5950 | PDO | grid:arith | mixStr div str |
| 5951 | PDO | grid:arith | mixStr div date |
| 5952 | PDO | grid:arith | mixStr div dt |
| 5954 | PDO | grid:arith | mixStr div zeroStr |
| 5964 | PDO | grid:arith | str div numStr |
| 5965 | PDO | grid:arith | str div mixStr |
| 5966 | PDO | grid:arith | str div str |
| 5967 | PDO | grid:arith | str div date |
| 5968 | PDO | grid:arith | str div dt |
| 5970 | PDO | grid:arith | str div zeroStr |
| 5980 | PDO | grid:arith | date div numStr |
| 5981 | PDO | grid:arith | date div mixStr |
| 5982 | PDO | grid:arith | date div str |
| 5983 | PDO | grid:arith | date div date |
| 5984 | PDO | grid:arith | date div dt |
| 5986 | PDO | grid:arith | date div zeroStr |
| 5996 | PDO | grid:arith | dt div numStr |
| 5997 | PDO | grid:arith | dt div mixStr |
| 5998 | PDO | grid:arith | dt div str |
| 5999 | PDO | grid:arith | dt div date |
| 6000 | PDO | grid:arith | dt div dt |
| 6002 | PDO | grid:arith | dt div zeroStr |
| 6028 | PDO | grid:arith | zeroStr div numStr |
| 6029 | PDO | grid:arith | zeroStr div mixStr |
| 6030 | PDO | grid:arith | zeroStr div str |
| 6031 | PDO | grid:arith | zeroStr div date |
| 6032 | PDO | grid:arith | zeroStr div dt |
| 6034 | PDO | grid:arith | zeroStr div zeroStr |
| 6188 | PDO | grid:arith | numStr idiv numStr |
| 6189 | PDO | grid:arith | numStr idiv mixStr |
| 6190 | PDO | grid:arith | numStr idiv str |
| 6191 | PDO | grid:arith | numStr idiv date |
| 6192 | PDO | grid:arith | numStr idiv dt |
| 6193 | PDO | grid:arith | numStr idiv nul |
| 6194 | PDO | grid:arith | numStr idiv zeroStr |
| 6204 | PDO | grid:arith | mixStr idiv numStr |
| 6205 | PDO | grid:arith | mixStr idiv mixStr |
| 6206 | PDO | grid:arith | mixStr idiv str |
| 6207 | PDO | grid:arith | mixStr idiv date |
| 6208 | PDO | grid:arith | mixStr idiv dt |
| 6209 | PDO | grid:arith | mixStr idiv nul |
| 6210 | PDO | grid:arith | mixStr idiv zeroStr |
| 6220 | PDO | grid:arith | str idiv numStr |
| 6221 | PDO | grid:arith | str idiv mixStr |
| 6222 | PDO | grid:arith | str idiv str |
| 6223 | PDO | grid:arith | str idiv date |
| 6224 | PDO | grid:arith | str idiv dt |
| 6225 | PDO | grid:arith | str idiv nul |
| 6226 | PDO | grid:arith | str idiv zeroStr |
| 6236 | PDO | grid:arith | date idiv numStr |
| 6237 | PDO | grid:arith | date idiv mixStr |
| 6238 | PDO | grid:arith | date idiv str |
| 6239 | PDO | grid:arith | date idiv date |
| 6240 | PDO | grid:arith | date idiv dt |
| 6241 | PDO | grid:arith | date idiv nul |
| 6242 | PDO | grid:arith | date idiv zeroStr |
| 6252 | PDO | grid:arith | dt idiv numStr |
| 6253 | PDO | grid:arith | dt idiv mixStr |
| 6254 | PDO | grid:arith | dt idiv str |
| 6255 | PDO | grid:arith | dt idiv date |
| 6256 | PDO | grid:arith | dt idiv dt |
| 6257 | PDO | grid:arith | dt idiv nul |
| 6258 | PDO | grid:arith | dt idiv zeroStr |
| 6268 | PDO | grid:arith | nul idiv numStr |
| 6269 | PDO | grid:arith | nul idiv mixStr |
| 6270 | PDO | grid:arith | nul idiv str |
| 6271 | PDO | grid:arith | nul idiv date |
| 6272 | PDO | grid:arith | nul idiv dt |
| 6274 | PDO | grid:arith | nul idiv zeroStr |
| 6284 | PDO | grid:arith | zeroStr idiv numStr |
| 6285 | PDO | grid:arith | zeroStr idiv mixStr |
| 6286 | PDO | grid:arith | zeroStr idiv str |
| 6287 | PDO | grid:arith | zeroStr idiv date |
| 6288 | PDO | grid:arith | zeroStr idiv dt |
| 6289 | PDO | grid:arith | zeroStr idiv nul |
| 6290 | PDO | grid:arith | zeroStr idiv zeroStr |
| 6317 | PDO | grid:arith | int0 mod mixStr |
| 6318 | PDO | grid:arith | int0 mod str |
| 6319 | PDO | grid:arith | int0 mod date |
| 6320 | PDO | grid:arith | int0 mod dt |
| 6333 | PDO | grid:arith | int1 mod mixStr |
| 6334 | PDO | grid:arith | int1 mod str |
| 6335 | PDO | grid:arith | int1 mod date |
| 6336 | PDO | grid:arith | int1 mod dt |
| 6349 | PDO | grid:arith | intNeg mod mixStr |
| 6350 | PDO | grid:arith | intNeg mod str |
| 6351 | PDO | grid:arith | intNeg mod date |
| 6352 | PDO | grid:arith | intNeg mod dt |
| 6365 | PDO | grid:arith | intBig mod mixStr |
| 6366 | PDO | grid:arith | intBig mod str |
| 6367 | PDO | grid:arith | intBig mod date |
| 6368 | PDO | grid:arith | intBig mod dt |
| 6381 | PDO | grid:arith | bigint mod mixStr |
| 6382 | PDO | grid:arith | bigint mod str |
| 6383 | PDO | grid:arith | bigint mod date |
| 6384 | PDO | grid:arith | bigint mod dt |
| 6444 | PDO | grid:arith | numStr mod numStr |
| 6445 | PDO | grid:arith | numStr mod mixStr |
| 6446 | PDO | grid:arith | numStr mod str |
| 6447 | PDO | grid:arith | numStr mod date |
| 6448 | PDO | grid:arith | numStr mod dt |
| 6450 | PDO | grid:arith | numStr mod zeroStr |
| 6452 | PDO | grid:arith | mixStr mod int0 |
| 6453 | PDO | grid:arith | mixStr mod int1 |
| 6454 | PDO | grid:arith | mixStr mod intNeg |
| 6455 | PDO | grid:arith | mixStr mod intBig |
| 6456 | PDO | grid:arith | mixStr mod bigint |
| 6460 | PDO | grid:arith | mixStr mod numStr |
| 6461 | PDO | grid:arith | mixStr mod mixStr |
| 6462 | PDO | grid:arith | mixStr mod str |
| 6463 | PDO | grid:arith | mixStr mod date |
| 6464 | PDO | grid:arith | mixStr mod dt |
| 6466 | PDO | grid:arith | mixStr mod zeroStr |
| 6468 | PDO | grid:arith | str mod int0 |
| 6469 | PDO | grid:arith | str mod int1 |
| 6470 | PDO | grid:arith | str mod intNeg |
| 6471 | PDO | grid:arith | str mod intBig |
| 6472 | PDO | grid:arith | str mod bigint |
| 6476 | PDO | grid:arith | str mod numStr |
| 6477 | PDO | grid:arith | str mod mixStr |
| 6478 | PDO | grid:arith | str mod str |
| 6479 | PDO | grid:arith | str mod date |
| 6480 | PDO | grid:arith | str mod dt |
| 6482 | PDO | grid:arith | str mod zeroStr |
| 6484 | PDO | grid:arith | date mod int0 |
| 6485 | PDO | grid:arith | date mod int1 |
| 6486 | PDO | grid:arith | date mod intNeg |
| 6487 | PDO | grid:arith | date mod intBig |
| 6488 | PDO | grid:arith | date mod bigint |
| 6492 | PDO | grid:arith | date mod numStr |
| 6493 | PDO | grid:arith | date mod mixStr |
| 6494 | PDO | grid:arith | date mod str |
| 6495 | PDO | grid:arith | date mod date |
| 6496 | PDO | grid:arith | date mod dt |
| 6498 | PDO | grid:arith | date mod zeroStr |
| 6500 | PDO | grid:arith | dt mod int0 |
| 6501 | PDO | grid:arith | dt mod int1 |
| 6502 | PDO | grid:arith | dt mod intNeg |
| 6503 | PDO | grid:arith | dt mod intBig |
| 6504 | PDO | grid:arith | dt mod bigint |
| 6508 | PDO | grid:arith | dt mod numStr |
| 6509 | PDO | grid:arith | dt mod mixStr |
| 6510 | PDO | grid:arith | dt mod str |
| 6511 | PDO | grid:arith | dt mod date |
| 6512 | PDO | grid:arith | dt mod dt |
| 6514 | PDO | grid:arith | dt mod zeroStr |
| 6540 | PDO | grid:arith | zeroStr mod numStr |
| 6541 | PDO | grid:arith | zeroStr mod mixStr |
| 6542 | PDO | grid:arith | zeroStr mod str |
| 6543 | PDO | grid:arith | zeroStr mod date |
| 6544 | PDO | grid:arith | zeroStr mod dt |
| 6546 | PDO | grid:arith | zeroStr mod zeroStr |
| 6573 | PDO | grid:compare | int0 eq mixStr |
| 6574 | PDO | grid:compare | int0 eq str |
| 6575 | PDO | grid:compare | int0 eq date |
| 6576 | PDO | grid:compare | int0 eq dt |
| 6589 | PDO | grid:compare | int1 eq mixStr |
| 6590 | PDO | grid:compare | int1 eq str |
| 6591 | PDO | grid:compare | int1 eq date |
| 6592 | PDO | grid:compare | int1 eq dt |
| 6605 | PDO | grid:compare | intNeg eq mixStr |
| 6606 | PDO | grid:compare | intNeg eq str |
| 6607 | PDO | grid:compare | intNeg eq date |
| 6608 | PDO | grid:compare | intNeg eq dt |
| 6621 | PDO | grid:compare | intBig eq mixStr |
| 6622 | PDO | grid:compare | intBig eq str |
| 6623 | PDO | grid:compare | intBig eq date |
| 6624 | PDO | grid:compare | intBig eq dt |
| 6637 | PDO | grid:compare | bigint eq mixStr |
| 6638 | PDO | grid:compare | bigint eq str |
| 6639 | PDO | grid:compare | bigint eq date |
| 6640 | PDO | grid:compare | bigint eq dt |
| 6708 | PDO | grid:compare | mixStr eq int0 |
| 6709 | PDO | grid:compare | mixStr eq int1 |
| 6710 | PDO | grid:compare | mixStr eq intNeg |
| 6711 | PDO | grid:compare | mixStr eq intBig |
| 6712 | PDO | grid:compare | mixStr eq bigint |
| 6724 | PDO | grid:compare | str eq int0 |
| 6725 | PDO | grid:compare | str eq int1 |
| 6726 | PDO | grid:compare | str eq intNeg |
| 6727 | PDO | grid:compare | str eq intBig |
| 6728 | PDO | grid:compare | str eq bigint |
| 6740 | PDO | grid:compare | date eq int0 |
| 6741 | PDO | grid:compare | date eq int1 |
| 6742 | PDO | grid:compare | date eq intNeg |
| 6743 | PDO | grid:compare | date eq intBig |
| 6744 | PDO | grid:compare | date eq bigint |
| 6756 | PDO | grid:compare | dt eq int0 |
| 6757 | PDO | grid:compare | dt eq int1 |
| 6758 | PDO | grid:compare | dt eq intNeg |
| 6759 | PDO | grid:compare | dt eq intBig |
| 6760 | PDO | grid:compare | dt eq bigint |
| 6829 | PDO | grid:compare | int0 ne mixStr |
| 6830 | PDO | grid:compare | int0 ne str |
| 6831 | PDO | grid:compare | int0 ne date |
| 6832 | PDO | grid:compare | int0 ne dt |
| 6845 | PDO | grid:compare | int1 ne mixStr |
| 6846 | PDO | grid:compare | int1 ne str |
| 6847 | PDO | grid:compare | int1 ne date |
| 6848 | PDO | grid:compare | int1 ne dt |
| 6861 | PDO | grid:compare | intNeg ne mixStr |
| 6862 | PDO | grid:compare | intNeg ne str |
| 6863 | PDO | grid:compare | intNeg ne date |
| 6864 | PDO | grid:compare | intNeg ne dt |
| 6877 | PDO | grid:compare | intBig ne mixStr |
| 6878 | PDO | grid:compare | intBig ne str |
| 6879 | PDO | grid:compare | intBig ne date |
| 6880 | PDO | grid:compare | intBig ne dt |
| 6893 | PDO | grid:compare | bigint ne mixStr |
| 6894 | PDO | grid:compare | bigint ne str |
| 6895 | PDO | grid:compare | bigint ne date |
| 6896 | PDO | grid:compare | bigint ne dt |
| 6964 | PDO | grid:compare | mixStr ne int0 |
| 6965 | PDO | grid:compare | mixStr ne int1 |
| 6966 | PDO | grid:compare | mixStr ne intNeg |
| 6967 | PDO | grid:compare | mixStr ne intBig |
| 6968 | PDO | grid:compare | mixStr ne bigint |
| 6980 | PDO | grid:compare | str ne int0 |
| 6981 | PDO | grid:compare | str ne int1 |
| 6982 | PDO | grid:compare | str ne intNeg |
| 6983 | PDO | grid:compare | str ne intBig |
| 6984 | PDO | grid:compare | str ne bigint |
| 6996 | PDO | grid:compare | date ne int0 |
| 6997 | PDO | grid:compare | date ne int1 |
| 6998 | PDO | grid:compare | date ne intNeg |
| 6999 | PDO | grid:compare | date ne intBig |
| 7000 | PDO | grid:compare | date ne bigint |
| 7012 | PDO | grid:compare | dt ne int0 |
| 7013 | PDO | grid:compare | dt ne int1 |
| 7014 | PDO | grid:compare | dt ne intNeg |
| 7015 | PDO | grid:compare | dt ne intBig |
| 7016 | PDO | grid:compare | dt ne bigint |
| 7085 | PDO | grid:compare | int0 lt mixStr |
| 7086 | PDO | grid:compare | int0 lt str |
| 7087 | PDO | grid:compare | int0 lt date |
| 7088 | PDO | grid:compare | int0 lt dt |
| 7101 | PDO | grid:compare | int1 lt mixStr |
| 7102 | PDO | grid:compare | int1 lt str |
| 7103 | PDO | grid:compare | int1 lt date |
| 7104 | PDO | grid:compare | int1 lt dt |
| 7117 | PDO | grid:compare | intNeg lt mixStr |
| 7118 | PDO | grid:compare | intNeg lt str |
| 7119 | PDO | grid:compare | intNeg lt date |
| 7120 | PDO | grid:compare | intNeg lt dt |
| 7133 | PDO | grid:compare | intBig lt mixStr |
| 7134 | PDO | grid:compare | intBig lt str |
| 7135 | PDO | grid:compare | intBig lt date |
| 7136 | PDO | grid:compare | intBig lt dt |
| 7149 | PDO | grid:compare | bigint lt mixStr |
| 7150 | PDO | grid:compare | bigint lt str |
| 7151 | PDO | grid:compare | bigint lt date |
| 7152 | PDO | grid:compare | bigint lt dt |
| 7220 | PDO | grid:compare | mixStr lt int0 |
| 7221 | PDO | grid:compare | mixStr lt int1 |
| 7222 | PDO | grid:compare | mixStr lt intNeg |
| 7223 | PDO | grid:compare | mixStr lt intBig |
| 7224 | PDO | grid:compare | mixStr lt bigint |
| 7236 | PDO | grid:compare | str lt int0 |
| 7237 | PDO | grid:compare | str lt int1 |
| 7238 | PDO | grid:compare | str lt intNeg |
| 7239 | PDO | grid:compare | str lt intBig |
| 7240 | PDO | grid:compare | str lt bigint |
| 7252 | PDO | grid:compare | date lt int0 |
| 7253 | PDO | grid:compare | date lt int1 |
| 7254 | PDO | grid:compare | date lt intNeg |
| 7255 | PDO | grid:compare | date lt intBig |
| 7256 | PDO | grid:compare | date lt bigint |
| 7268 | PDO | grid:compare | dt lt int0 |
| 7269 | PDO | grid:compare | dt lt int1 |
| 7270 | PDO | grid:compare | dt lt intNeg |
| 7271 | PDO | grid:compare | dt lt intBig |
| 7272 | PDO | grid:compare | dt lt bigint |
| 7341 | PDO | grid:compare | int0 le mixStr |
| 7342 | PDO | grid:compare | int0 le str |
| 7343 | PDO | grid:compare | int0 le date |
| 7344 | PDO | grid:compare | int0 le dt |
| 7357 | PDO | grid:compare | int1 le mixStr |
| 7358 | PDO | grid:compare | int1 le str |
| 7359 | PDO | grid:compare | int1 le date |
| 7360 | PDO | grid:compare | int1 le dt |
| 7373 | PDO | grid:compare | intNeg le mixStr |
| 7374 | PDO | grid:compare | intNeg le str |
| 7375 | PDO | grid:compare | intNeg le date |
| 7376 | PDO | grid:compare | intNeg le dt |
| 7389 | PDO | grid:compare | intBig le mixStr |
| 7390 | PDO | grid:compare | intBig le str |
| 7391 | PDO | grid:compare | intBig le date |
| 7392 | PDO | grid:compare | intBig le dt |
| 7405 | PDO | grid:compare | bigint le mixStr |
| 7406 | PDO | grid:compare | bigint le str |
| 7407 | PDO | grid:compare | bigint le date |
| 7408 | PDO | grid:compare | bigint le dt |
| 7476 | PDO | grid:compare | mixStr le int0 |
| 7477 | PDO | grid:compare | mixStr le int1 |
| 7478 | PDO | grid:compare | mixStr le intNeg |
| 7479 | PDO | grid:compare | mixStr le intBig |
| 7480 | PDO | grid:compare | mixStr le bigint |
| 7492 | PDO | grid:compare | str le int0 |
| 7493 | PDO | grid:compare | str le int1 |
| 7494 | PDO | grid:compare | str le intNeg |
| 7495 | PDO | grid:compare | str le intBig |
| 7496 | PDO | grid:compare | str le bigint |
| 7508 | PDO | grid:compare | date le int0 |
| 7509 | PDO | grid:compare | date le int1 |
| 7510 | PDO | grid:compare | date le intNeg |
| 7511 | PDO | grid:compare | date le intBig |
| 7512 | PDO | grid:compare | date le bigint |
| 7524 | PDO | grid:compare | dt le int0 |
| 7525 | PDO | grid:compare | dt le int1 |
| 7526 | PDO | grid:compare | dt le intNeg |
| 7527 | PDO | grid:compare | dt le intBig |
| 7528 | PDO | grid:compare | dt le bigint |
| 7597 | PDO | grid:compare | int0 gt mixStr |
| 7598 | PDO | grid:compare | int0 gt str |
| 7599 | PDO | grid:compare | int0 gt date |
| 7600 | PDO | grid:compare | int0 gt dt |
| 7613 | PDO | grid:compare | int1 gt mixStr |
| 7614 | PDO | grid:compare | int1 gt str |
| 7615 | PDO | grid:compare | int1 gt date |
| 7616 | PDO | grid:compare | int1 gt dt |
| 7629 | PDO | grid:compare | intNeg gt mixStr |
| 7630 | PDO | grid:compare | intNeg gt str |
| 7631 | PDO | grid:compare | intNeg gt date |
| 7632 | PDO | grid:compare | intNeg gt dt |
| 7645 | PDO | grid:compare | intBig gt mixStr |
| 7646 | PDO | grid:compare | intBig gt str |
| 7647 | PDO | grid:compare | intBig gt date |
| 7648 | PDO | grid:compare | intBig gt dt |
| 7661 | PDO | grid:compare | bigint gt mixStr |
| 7662 | PDO | grid:compare | bigint gt str |
| 7663 | PDO | grid:compare | bigint gt date |
| 7664 | PDO | grid:compare | bigint gt dt |
| 7732 | PDO | grid:compare | mixStr gt int0 |
| 7733 | PDO | grid:compare | mixStr gt int1 |
| 7734 | PDO | grid:compare | mixStr gt intNeg |
| 7735 | PDO | grid:compare | mixStr gt intBig |
| 7736 | PDO | grid:compare | mixStr gt bigint |
| 7748 | PDO | grid:compare | str gt int0 |
| 7749 | PDO | grid:compare | str gt int1 |
| 7750 | PDO | grid:compare | str gt intNeg |
| 7751 | PDO | grid:compare | str gt intBig |
| 7752 | PDO | grid:compare | str gt bigint |
| 7764 | PDO | grid:compare | date gt int0 |
| 7765 | PDO | grid:compare | date gt int1 |
| 7766 | PDO | grid:compare | date gt intNeg |
| 7767 | PDO | grid:compare | date gt intBig |
| 7768 | PDO | grid:compare | date gt bigint |
| 7780 | PDO | grid:compare | dt gt int0 |
| 7781 | PDO | grid:compare | dt gt int1 |
| 7782 | PDO | grid:compare | dt gt intNeg |
| 7783 | PDO | grid:compare | dt gt intBig |
| 7784 | PDO | grid:compare | dt gt bigint |
| 7853 | PDO | grid:compare | int0 ge mixStr |
| 7854 | PDO | grid:compare | int0 ge str |
| 7855 | PDO | grid:compare | int0 ge date |
| 7856 | PDO | grid:compare | int0 ge dt |
| 7869 | PDO | grid:compare | int1 ge mixStr |
| 7870 | PDO | grid:compare | int1 ge str |
| 7871 | PDO | grid:compare | int1 ge date |
| 7872 | PDO | grid:compare | int1 ge dt |
| 7885 | PDO | grid:compare | intNeg ge mixStr |
| 7886 | PDO | grid:compare | intNeg ge str |
| 7887 | PDO | grid:compare | intNeg ge date |
| 7888 | PDO | grid:compare | intNeg ge dt |
| 7901 | PDO | grid:compare | intBig ge mixStr |
| 7902 | PDO | grid:compare | intBig ge str |
| 7903 | PDO | grid:compare | intBig ge date |
| 7904 | PDO | grid:compare | intBig ge dt |
| 7917 | PDO | grid:compare | bigint ge mixStr |
| 7918 | PDO | grid:compare | bigint ge str |
| 7919 | PDO | grid:compare | bigint ge date |
| 7920 | PDO | grid:compare | bigint ge dt |
| 7988 | PDO | grid:compare | mixStr ge int0 |
| 7989 | PDO | grid:compare | mixStr ge int1 |
| 7990 | PDO | grid:compare | mixStr ge intNeg |
| 7991 | PDO | grid:compare | mixStr ge intBig |
| 7992 | PDO | grid:compare | mixStr ge bigint |
| 8004 | PDO | grid:compare | str ge int0 |
| 8005 | PDO | grid:compare | str ge int1 |
| 8006 | PDO | grid:compare | str ge intNeg |
| 8007 | PDO | grid:compare | str ge intBig |
| 8008 | PDO | grid:compare | str ge bigint |
| 8020 | PDO | grid:compare | date ge int0 |
| 8021 | PDO | grid:compare | date ge int1 |
| 8022 | PDO | grid:compare | date ge intNeg |
| 8023 | PDO | grid:compare | date ge intBig |
| 8024 | PDO | grid:compare | date ge bigint |
| 8036 | PDO | grid:compare | dt ge int0 |
| 8037 | PDO | grid:compare | dt ge int1 |
| 8038 | PDO | grid:compare | dt ge intNeg |
| 8039 | PDO | grid:compare | dt ge intBig |
| 8040 | PDO | grid:compare | dt ge bigint |
| 8109 | PDO | grid:compare | int0 nseq mixStr |
| 8110 | PDO | grid:compare | int0 nseq str |
| 8111 | PDO | grid:compare | int0 nseq date |
| 8112 | PDO | grid:compare | int0 nseq dt |
| 8125 | PDO | grid:compare | int1 nseq mixStr |
| 8126 | PDO | grid:compare | int1 nseq str |
| 8127 | PDO | grid:compare | int1 nseq date |
| 8128 | PDO | grid:compare | int1 nseq dt |
| 8141 | PDO | grid:compare | intNeg nseq mixStr |
| 8142 | PDO | grid:compare | intNeg nseq str |
| 8143 | PDO | grid:compare | intNeg nseq date |
| 8144 | PDO | grid:compare | intNeg nseq dt |
| 8157 | PDO | grid:compare | intBig nseq mixStr |
| 8158 | PDO | grid:compare | intBig nseq str |
| 8159 | PDO | grid:compare | intBig nseq date |
| 8160 | PDO | grid:compare | intBig nseq dt |
| 8173 | PDO | grid:compare | bigint nseq mixStr |
| 8174 | PDO | grid:compare | bigint nseq str |
| 8175 | PDO | grid:compare | bigint nseq date |
| 8176 | PDO | grid:compare | bigint nseq dt |
| 8244 | PDO | grid:compare | mixStr nseq int0 |
| 8245 | PDO | grid:compare | mixStr nseq int1 |
| 8246 | PDO | grid:compare | mixStr nseq intNeg |
| 8247 | PDO | grid:compare | mixStr nseq intBig |
| 8248 | PDO | grid:compare | mixStr nseq bigint |
| 8260 | PDO | grid:compare | str nseq int0 |
| 8261 | PDO | grid:compare | str nseq int1 |
| 8262 | PDO | grid:compare | str nseq intNeg |
| 8263 | PDO | grid:compare | str nseq intBig |
| 8264 | PDO | grid:compare | str nseq bigint |
| 8276 | PDO | grid:compare | date nseq int0 |
| 8277 | PDO | grid:compare | date nseq int1 |
| 8278 | PDO | grid:compare | date nseq intNeg |
| 8279 | PDO | grid:compare | date nseq intBig |
| 8280 | PDO | grid:compare | date nseq bigint |
| 8292 | PDO | grid:compare | dt nseq int0 |
| 8293 | PDO | grid:compare | dt nseq int1 |
| 8294 | PDO | grid:compare | dt nseq intNeg |
| 8295 | PDO | grid:compare | dt nseq intBig |
| 8296 | PDO | grid:compare | dt nseq bigint |
| 8822 | PDO | fn:string-var | SPACE #0 |
| 8823 | PDO | fn:string-var | SPACE #1 |
| 8824 | PDO | fn:string-var | SPACE #2 |
| 8825 | PDO | fn:string-var | SPACE #3 |
| 8826 | PDO | fn:string-var | SPACE #4 |
| 8827 | PDO | fn:string-var | SPACE #5 |
| 8828 | PDO | fn:string-var | SPACE #6 |
| 8829 | PDO | fn:string-var | SPACE #7 |
| 8960 | PDO | fn:numeric-var | SQRT #2 |
| 8962 | PDO | fn:numeric-var | SQRT #4 |
| 8974 | PDO | fn:numeric-var | LN #0 |
| 8976 | PDO | fn:numeric-var | LN #2 |
| 8978 | PDO | fn:numeric-var | LN #4 |
| 8982 | PDO | fn:numeric-var | LOG2 #0 |
| 8984 | PDO | fn:numeric-var | LOG2 #2 |
| 8986 | PDO | fn:numeric-var | LOG2 #4 |
| 8990 | PDO | fn:numeric-var | LOG10 #0 |
| 8992 | PDO | fn:numeric-var | LOG10 #2 |
| 8994 | PDO | fn:numeric-var | LOG10 #4 |
| 9025 | PDO | fn:numeric-var | ASIN #3 |
| 9026 | PDO | fn:numeric-var | ASIN #4 |
| 9027 | PDO | fn:numeric-var | ASIN #5 |
| 9029 | PDO | fn:numeric-var | ASIN #7 |
| 9033 | PDO | fn:numeric-var | ACOS #3 |
| 9034 | PDO | fn:numeric-var | ACOS #4 |
| 9035 | PDO | fn:numeric-var | ACOS #5 |
| 9037 | PDO | fn:numeric-var | ACOS #7 |
| 9062 | PDO | fn:numeric-var | CRC32 #0 |
| 9063 | PDO | fn:numeric-var | CRC32 #1 |
| 9064 | PDO | fn:numeric-var | CRC32 #2 |
| 9065 | PDO | fn:numeric-var | CRC32 #3 |
| 9066 | PDO | fn:numeric-var | CRC32 #4 |
| 9067 | PDO | fn:numeric-var | CRC32 #5 |
| 9068 | PDO | fn:numeric-var | CRC32 #6 |
| 9069 | PDO | fn:numeric-var | CRC32 #7 |
| 9392 | PDO | cast:matrix | CAST '-3.14' AS SIGNED |
| 9393 | PDO | cast:matrix | CONVERT '-3.14',SIGNED |
| 9394 | PDO | cast:matrix | CAST '2026-06-23' AS SIGNED |
| 9395 | PDO | cast:matrix | CONVERT '2026-06-23',SIGNED |
| 9396 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS SIGNED |
| 9397 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',SIGNED |
| 9398 | PDO | cast:matrix | CAST '10:20:30' AS SIGNED |
| 9399 | PDO | cast:matrix | CONVERT '10:20:30',SIGNED |
| 9400 | PDO | cast:matrix | CAST 'abc' AS SIGNED |
| 9401 | PDO | cast:matrix | CONVERT 'abc',SIGNED |
| 9406 | PDO | cast:matrix | CAST '[1,2,3]' AS SIGNED |
| 9407 | PDO | cast:matrix | CONVERT '[1,2,3]',SIGNED |
| 9412 | PDO | cast:matrix | CAST '-3.14' AS UNSIGNED |
| 9413 | PDO | cast:matrix | CONVERT '-3.14',UNSIGNED |
| 9414 | PDO | cast:matrix | CAST '2026-06-23' AS UNSIGNED |
| 9415 | PDO | cast:matrix | CONVERT '2026-06-23',UNSIGNED |
| 9416 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS UNSIGNED |
| 9417 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',UNSIGNED |
| 9418 | PDO | cast:matrix | CAST '10:20:30' AS UNSIGNED |
| 9419 | PDO | cast:matrix | CONVERT '10:20:30',UNSIGNED |
| 9420 | PDO | cast:matrix | CAST 'abc' AS UNSIGNED |
| 9421 | PDO | cast:matrix | CONVERT 'abc',UNSIGNED |
| 9426 | PDO | cast:matrix | CAST '[1,2,3]' AS UNSIGNED |
| 9427 | PDO | cast:matrix | CONVERT '[1,2,3]',UNSIGNED |
| 9522 | PDO | cast:matrix | CAST 255 AS DATE |
| 9523 | PDO | cast:matrix | CONVERT 255,DATE |
| 9524 | PDO | cast:matrix | CAST 3.14159 AS DATE |
| 9525 | PDO | cast:matrix | CONVERT 3.14159,DATE |
| 9542 | PDO | cast:matrix | CAST 255 AS DATETIME |
| 9543 | PDO | cast:matrix | CONVERT 255,DATETIME |
| 9544 | PDO | cast:matrix | CAST 3.14159 AS DATETIME |
| 9545 | PDO | cast:matrix | CONVERT 3.14159,DATETIME |
| 9642 | PDO | cast:matrix | CAST 255 AS JSON |
| 9644 | PDO | cast:matrix | CAST 3.14159 AS JSON |

### Error `BEHAVIOR` — 123 scenario(s)

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
| 2381 | PDO | typematrix:overflow | MEDIUMINT rejects overflow 8388608 |
| 2383 | PDO | typematrix:overflow | MEDIUMINT UNSIGNED rejects overflow 16777216 |
| 2401 | PDO | typematrix:coercion | coerce: 1 + NULL |
| 2479 | PDO | typematrix:charset | column CHARACTER SET utf8mb4 reported |
| 2481 | PDO | typematrix:charset | column CHARACTER SET ascii reported |
| 2482 | PDO | typematrix:charset | column CHARACTER SET latin1 reported |
| 2484 | PDO | typematrix:charset | column CHARACTER SET gbk reported |
| 2485 | PDO | typematrix:charset | column CHARACTER SET utf16 reported |
| 2486 | PDO | typematrix:charset | column CHARACTER SET utf32 reported |
| 2495 | PDO | typematrix:collation | 'abc'='ABC' COLLATE utf8mb4_general_ci |
| 2496 | PDO | typematrix:collation | 'ABC' LIKE 'abc' COLLATE utf8mb4_general_ci |
| 2498 | PDO | typematrix:collation | WHERE column COLLATE utf8mb4_general_ci matches |
| 2499 | PDO | typematrix:collation | 'abc'='ABC' COLLATE utf8mb4_0900_ai_ci |
| 2500 | PDO | typematrix:collation | 'ABC' LIKE 'abc' COLLATE utf8mb4_0900_ai_ci |
| 2502 | PDO | typematrix:collation | WHERE column COLLATE utf8mb4_0900_ai_ci matches |
| 2503 | PDO | typematrix:collation | 'abc'='ABC' COLLATE utf8mb4_unicode_ci |
| 2504 | PDO | typematrix:collation | 'ABC' LIKE 'abc' COLLATE utf8mb4_unicode_ci |
| 2506 | PDO | typematrix:collation | WHERE column COLLATE utf8mb4_unicode_ci matches |
| 2595 | PDO | typematrix:json-path | JSON_EXTRACT $.e |
| 2635 | PDO | typematrix:json-cast | JSON value preserves null |
| 2690 | PDO | app:ecommerce | 10% discount off 19.95 |
| 2798 | PDO | app:ecommerce | shipping fee charged for 50.00 |
| 2856 | PDO | app:analytics | GROUPING() flags grand total |
| 2872 | PDO | app:analytics | funnel conversion rate view->purchase |
| 3016 | PDO | app:ecommerce | discount sweep 3% off 34.50 |
| 3099 | PDO | app:ecommerce | discount sweep 17% off 199.50 |
| 3634 | PDO | app:saas | quota 33/99 percent |
| 3935 | PDO | ddlsurf:partition | ALTER TABLE TRUNCATE PARTITION rejected |
| 3972 | PDO | ddlsurf:check | CHECK enforcement: age >= 0 |
| 3973 | PDO | ddlsurf:check | CHECK enforcement: pct 0..100 |
| 3974 | PDO | ddlsurf:check | CHECK enforcement: n <> 0 |
| 3975 | PDO | ddlsurf:check | CHECK enforcement: named lo<hi |
| 4063 | PDO | err:type | out-of-range MEDIUMINT 8388608 surfaces error |
| 4077 | PDO | err:type | decimal int overflow surfaces error |
| 4083 | PDO | err:type | INT from hex string surfaces error |
| 4150 | Eloquent | eloq-app:ecommerce | grand revenue across all items |
| 4186 | Eloquent | eloq-app:social | mutual follows via self-join |
| 4234 | Eloquent | eloq-app:ledger | net entries for account 2 = -200 |
| 4863 | RedBean | redbean:query | find predicate #12: WHERE s LIKE ? |
| 4867 | RedBean | redbean:query | find predicate #16: WHERE n % 2 = 0 |
| 4907 | RedBean | redbean:query | agg COUNT where like |
| 4988 | RedBean | redbean:type | type round-trip: float repeating |
| 4993 | RedBean | redbean:type | type round-trip: long text 192 |
| 5016 | RedBean | redbean:transaction | begin+rollback discards |
| 5018 | RedBean | redbean:transaction | explicit tx rollback #1 |
| 5020 | RedBean | redbean:transaction | explicit tx rollback #3 |
| 5022 | RedBean | redbean:transaction | explicit tx rollback #5 |
| 5026 | RedBean | redbean:transaction | rollback preserves pre-tx data |

### Error `1064` — 88 scenario(s)

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
| 2483 | PDO | typematrix:charset | column CHARACTER SET binary reported |
| 2830 | PDO | app:social | recursive reach from user 1 (all hops) |
| 3294 | PDO | app:social | follow-chain length 2 reach from 1 |
| 3295 | PDO | app:social | follow-chain length 3 reach from 1 |
| 3296 | PDO | app:social | follow-chain length 4 reach from 1 |
| 3297 | PDO | app:social | follow-chain length 5 reach from 1 |
| 3298 | PDO | app:social | follow-chain length 6 reach from 1 |
| 3299 | PDO | app:social | follow-chain length 8 reach from 1 |
| 3930 | PDO | ddlsurf:partition | EXPLAIN partitioned table |
| 9631 | PDO | cast:matrix | CONVERT '42',JSON |
| 9633 | PDO | cast:matrix | CONVERT '-3.14',JSON |
| 9635 | PDO | cast:matrix | CONVERT '2026-06-23',JSON |
| 9637 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',JSON |
| 9639 | PDO | cast:matrix | CONVERT '10:20:30',JSON |
| 9641 | PDO | cast:matrix | CONVERT 'abc',JSON |
| 9643 | PDO | cast:matrix | CONVERT 255,JSON |
| 9645 | PDO | cast:matrix | CONVERT 3.14159,JSON |
| 9647 | PDO | cast:matrix | CONVERT '[1,2,3]',JSON |
| 9649 | PDO | cast:matrix | CONVERT NULL,JSON |
| 9650 | PDO | cast:matrix | CAST '42' AS NCHAR |
| 9651 | PDO | cast:matrix | CONVERT '42',NCHAR |
| 9652 | PDO | cast:matrix | CAST '-3.14' AS NCHAR |
| 9653 | PDO | cast:matrix | CONVERT '-3.14',NCHAR |
| 9654 | PDO | cast:matrix | CAST '2026-06-23' AS NCHAR |
| 9655 | PDO | cast:matrix | CONVERT '2026-06-23',NCHAR |
| 9656 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS NCHAR |
| 9657 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',NCHAR |
| 9658 | PDO | cast:matrix | CAST '10:20:30' AS NCHAR |
| 9659 | PDO | cast:matrix | CONVERT '10:20:30',NCHAR |
| 9660 | PDO | cast:matrix | CAST 'abc' AS NCHAR |
| 9661 | PDO | cast:matrix | CONVERT 'abc',NCHAR |
| 9662 | PDO | cast:matrix | CAST 255 AS NCHAR |
| 9663 | PDO | cast:matrix | CONVERT 255,NCHAR |
| 9664 | PDO | cast:matrix | CAST 3.14159 AS NCHAR |
| 9665 | PDO | cast:matrix | CONVERT 3.14159,NCHAR |
| 9666 | PDO | cast:matrix | CAST '[1,2,3]' AS NCHAR |
| 9667 | PDO | cast:matrix | CONVERT '[1,2,3]',NCHAR |
| 9668 | PDO | cast:matrix | CAST NULL AS NCHAR |
| 9669 | PDO | cast:matrix | CONVERT NULL,NCHAR |

### Error `20105` — 62 scenario(s)

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
| 2607 | PDO | typematrix:json-path | JSON_DEPTH |
| 2613 | PDO | typematrix:json-path | JSON_CONTAINS |
| 2614 | PDO | typematrix:json-path | JSON_CONTAINS_PATH |
| 2615 | PDO | typematrix:json-path | JSON_SEARCH |
| 2618 | PDO | typematrix:json-path | JSON_MERGE_PATCH |
| 2619 | PDO | typematrix:json-path | JSON_MERGE_PRESERVE |
| 2623 | PDO | typematrix:json-path | JSON_REMOVE |
| 2625 | PDO | typematrix:json-path | JSON_ARRAY_APPEND |
| 2626 | PDO | typematrix:json-path | JSON_ARRAY_INSERT |
| 2628 | PDO | typematrix:json-path | JSON_STORAGE_SIZE |
| 2629 | PDO | typematrix:json-path | JSON_OVERLAPS |
| 4367 | PDO | sem:vector-fn | l1_distance (gap?) |
| 9360 | PDO | fn:json-var | JSON_DEPTH #0 |
| 9361 | PDO | fn:json-var | JSON_DEPTH #1 |
| 9362 | PDO | fn:json-var | JSON_DEPTH #2 |
| 9363 | PDO | fn:json-var | JSON_DEPTH #3 |
| 9364 | PDO | fn:json-var | JSON_DEPTH #4 |

### Error `20301` — 60 scenario(s)

> SQLSTATE[HY000]: General error: 20301 invalid input: unsupported alter option in copy mode: alter ALGORITHM not enforce

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1417 | PDO | ddl2:alter | ALTER ALGORITHM=INPLACE |
| 1418 | PDO | ddl2:alter | ALTER ALGORITHM=COPY |
| 1419 | PDO | ddl2:alter | ALTER LOCK=NONE |
| 1423 | PDO | ddl2:alter | ALTER set AUTO_INCREMENT |
| 1516 | PDO | charset | _ascii introducer |
| 2265 | CakePHP | function2 | func coalesce |
| 2352 | PDO | typematrix:decimal | DECIMAL(38,38) stores 1.5 scaled |
| 2363 | PDO | typematrix:decimal | DECIMAL(4,4) stores 1.5 scaled |
| 2588 | PDO | typematrix:bit | bitop: BIT_AND(x) |
| 5367 | PDO | grid:arith | dec sub intBig |
| 5368 | PDO | grid:arith | dec sub bigint |
| 5382 | PDO | grid:arith | decNeg sub intNeg |
| 5397 | PDO | grid:arith | frac sub int1 |
| 5399 | PDO | grid:arith | frac sub intBig |
| 5400 | PDO | grid:arith | frac sub bigint |
| 5401 | PDO | grid:arith | frac sub dec |
| 5411 | PDO | grid:arith | frac sub bigDec |
| 5619 | PDO | grid:arith | bigint mul bigDec |
| 5784 | PDO | grid:arith | bigDec mul bigint |
| 5795 | PDO | grid:arith | bigDec mul bigDec |
| 9474 | PDO | cast:matrix | CAST '2026-06-23' AS DECIMAL(10,2) |
| 9475 | PDO | cast:matrix | CONVERT '2026-06-23',DECIMAL(10,2) |
| 9476 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS DECIMAL(10,2) |
| 9477 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',DECIMAL(10,2) |
| 9478 | PDO | cast:matrix | CAST '10:20:30' AS DECIMAL(10,2) |
| 9479 | PDO | cast:matrix | CONVERT '10:20:30',DECIMAL(10,2) |
| 9480 | PDO | cast:matrix | CAST 'abc' AS DECIMAL(10,2) |
| 9481 | PDO | cast:matrix | CONVERT 'abc',DECIMAL(10,2) |
| 9486 | PDO | cast:matrix | CAST '[1,2,3]' AS DECIMAL(10,2) |
| 9487 | PDO | cast:matrix | CONVERT '[1,2,3]',DECIMAL(10,2) |
| 9494 | PDO | cast:matrix | CAST '2026-06-23' AS DECIMAL(20,4) |
| 9495 | PDO | cast:matrix | CONVERT '2026-06-23',DECIMAL(20,4) |
| 9496 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS DECIMAL(20,4) |
| 9497 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',DECIMAL(20,4) |
| 9498 | PDO | cast:matrix | CAST '10:20:30' AS DECIMAL(20,4) |
| 9499 | PDO | cast:matrix | CONVERT '10:20:30',DECIMAL(20,4) |
| 9500 | PDO | cast:matrix | CAST 'abc' AS DECIMAL(20,4) |
| 9501 | PDO | cast:matrix | CONVERT 'abc',DECIMAL(20,4) |
| 9506 | PDO | cast:matrix | CAST '[1,2,3]' AS DECIMAL(20,4) |
| 9507 | PDO | cast:matrix | CONVERT '[1,2,3]',DECIMAL(20,4) |
| 9530 | PDO | cast:matrix | CAST '42' AS DATETIME |
| 9531 | PDO | cast:matrix | CONVERT '42',DATETIME |
| 9532 | PDO | cast:matrix | CAST '-3.14' AS DATETIME |
| 9533 | PDO | cast:matrix | CONVERT '-3.14',DATETIME |
| 9538 | PDO | cast:matrix | CAST '10:20:30' AS DATETIME |
| 9539 | PDO | cast:matrix | CONVERT '10:20:30',DATETIME |
| 9540 | PDO | cast:matrix | CAST 'abc' AS DATETIME |
| 9541 | PDO | cast:matrix | CONVERT 'abc',DATETIME |
| 9546 | PDO | cast:matrix | CAST '[1,2,3]' AS DATETIME |
| 9547 | PDO | cast:matrix | CONVERT '[1,2,3]',DATETIME |
| 9554 | PDO | cast:matrix | CAST '2026-06-23' AS TIME |
| 9555 | PDO | cast:matrix | CONVERT '2026-06-23',TIME |
| 9560 | PDO | cast:matrix | CAST 'abc' AS TIME |
| 9561 | PDO | cast:matrix | CONVERT 'abc',TIME |
| 9566 | PDO | cast:matrix | CAST '[1,2,3]' AS TIME |
| 9567 | PDO | cast:matrix | CONVERT '[1,2,3]',TIME |
| 9634 | PDO | cast:matrix | CAST '2026-06-23' AS JSON |
| 9636 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS JSON |
| 9638 | PDO | cast:matrix | CAST '10:20:30' AS JSON |
| 9640 | PDO | cast:matrix | CAST 'abc' AS JSON |

### Error `20101` — 45 scenario(s)

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
| 9431 | PDO | cast:matrix | CONVERT '42',CHAR |
| 9433 | PDO | cast:matrix | CONVERT '-3.14',CHAR |
| 9435 | PDO | cast:matrix | CONVERT '2026-06-23',CHAR |
| 9437 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',CHAR |
| 9439 | PDO | cast:matrix | CONVERT '10:20:30',CHAR |
| 9441 | PDO | cast:matrix | CONVERT 'abc',CHAR |
| 9443 | PDO | cast:matrix | CONVERT 255,CHAR |
| 9445 | PDO | cast:matrix | CONVERT 3.14159,CHAR |
| 9447 | PDO | cast:matrix | CONVERT '[1,2,3]',CHAR |
| 9454 | PDO | cast:matrix | CAST '2026-06-23' AS CHAR(5) |
| 9455 | PDO | cast:matrix | CONVERT '2026-06-23',CHAR(5) |
| 9456 | PDO | cast:matrix | CAST '2026-06-23 10:20:30' AS CHAR(5) |
| 9457 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',CHAR(5) |
| 9458 | PDO | cast:matrix | CAST '10:20:30' AS CHAR(5) |
| 9459 | PDO | cast:matrix | CONVERT '10:20:30',CHAR(5) |
| 9464 | PDO | cast:matrix | CAST 3.14159 AS CHAR(5) |
| 9465 | PDO | cast:matrix | CONVERT 3.14159,CHAR(5) |
| 9466 | PDO | cast:matrix | CAST '[1,2,3]' AS CHAR(5) |
| 9467 | PDO | cast:matrix | CONVERT '[1,2,3]',CHAR(5) |
| 9611 | PDO | cast:matrix | CONVERT '42',BINARY |
| 9613 | PDO | cast:matrix | CONVERT '-3.14',BINARY |
| 9615 | PDO | cast:matrix | CONVERT '2026-06-23',BINARY |
| 9617 | PDO | cast:matrix | CONVERT '2026-06-23 10:20:30',BINARY |
| 9619 | PDO | cast:matrix | CONVERT '10:20:30',BINARY |
| 9621 | PDO | cast:matrix | CONVERT 'abc',BINARY |
| 9627 | PDO | cast:matrix | CONVERT '[1,2,3]',BINARY |

### Error `1690` — 29 scenario(s)

> SQLSTATE[HY000]: General error: 1690 data out of range: data type uint64, value '-1'

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1146 | PDO | function:cast | CAST UNSIGNED wrap |
| 1185 | PDO | function:bit | NOT then mask |
| 1481 | PDO | datatype:edge2 | BIT(8) value |
| 4529 | PDO | sem:operator | bit not |
| 5048 | PDO | grid:arith | int1 add bigint |
| 5080 | PDO | grid:arith | intBig add bigint |
| 5093 | PDO | grid:arith | bigint add int1 |
| 5095 | PDO | grid:arith | bigint add intBig |
| 5096 | PDO | grid:arith | bigint add bigint |
| 5100 | PDO | grid:arith | bigint add numStr |
| 5160 | PDO | grid:arith | numStr add bigint |
| 5320 | PDO | grid:arith | intNeg sub bigint |
| 5350 | PDO | grid:arith | bigint sub intNeg |
| 5576 | PDO | grid:arith | intNeg mul bigint |
| 5592 | PDO | grid:arith | intBig mul bigint |
| 5606 | PDO | grid:arith | bigint mul intNeg |
| 5607 | PDO | grid:arith | bigint mul intBig |
| 5608 | PDO | grid:arith | bigint mul bigint |
| 5612 | PDO | grid:arith | bigint mul numStr |
| 5672 | PDO | grid:arith | numStr mul bigint |
| 6123 | PDO | grid:arith | bigint idiv frac |
| 6293 | PDO | grid:arith | bigDec idiv int1 |
| 6294 | PDO | grid:arith | bigDec idiv intNeg |
| 6298 | PDO | grid:arith | bigDec idiv decNeg |
| 6299 | PDO | grid:arith | bigDec idiv frac |
| 8971 | PDO | fn:numeric-var | EXP #5 |
| 9230 | PDO | fn:date-var | TIME #0 |
| 9231 | PDO | fn:date-var | TIME #1 |
| 9233 | PDO | fn:date-var | TIME #3 |

### Error `20102` — 6 scenario(s)

> SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 1309 | PDO | sql:setop | EXCEPT ALL |
| 1428 | PDO | dml2:insert | INSERT with subquery value |
| 4602 | PDO | sem:setop | EXCEPT ALL 2 arms |
| 4603 | PDO | sem:setop | EXCEPT ALL 3 arms |
| 4604 | PDO | sem:setop | EXCEPT ALL overlap |
| 4605 | PDO | sem:setop | EXCEPT ALL dup arm |

### Error `ERROR` — 3 scenario(s)

> PDOStatement::fetchAll(): Malformed server packet. Field length pointing after the end of packet

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 459 | PDO | introspection | SHOW VARIABLES |
| 571 | PDO | prepared:non-preparable | prepare SHOW VARIABLES LIKE |
| 4203 | Eloquent | eloq-app:analytics | window-ish running total via correlated subquery |

### Error `1149` — 2 scenario(s)

> SQLSTATE[HY000]: General error: 1149 SQL syntax error: column "ck2_1571_221b4.name" must appear in the GROUP BY clause or be used in an aggregate function

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 2238 | CakePHP | query-builder2 | distinct on column |
| 4684 | PDO | sem:grouping | GROUPING SETS a |

### Error `1406` — 2 scenario(s)

> SQLSTATE[HY000]: General error: 1406 data truncated: data type Signed, truncated for binary/varbinary

| # | Framework | Category | Scenario |
|--:|---|---|---|
| 9623 | PDO | cast:matrix | CONVERT 255,BINARY |
| 9625 | PDO | cast:matrix | CONVERT 3.14159,BINARY |

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
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 36 near " DESCRIBE `i_474_fb6a2`";

#### #480 [PDO / introspection] EXPLAIN SELECT

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN SELECT * FROM `i_475_9daf6`";

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
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 78 near " w s FROM sc_795_fd09c WINDOW w AS (ORDER BY id)) z)";

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
- **Message:** MySQL REFERENCED_TABLE_NAME='iskp_1167_5ca8e', MatrixOne=false

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
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN ANALYZE SELECT * FROM `ise_1175_2e2c9`";

#### #1617 [PDO / introspection2] EXPLAIN FORMAT=JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN FORMAT=JSON SELECT * FROM `ise_1176_01173`";

#### #1680 [Eloquent / schema:modifier] modifier fulltext index

```sql
alter table `el_1239_285ff` add fulltext `el_1239_285ff_c_fulltext`(`c`)
```

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: primary key cannot be empty for fulltext index

#### #1681 [Eloquent / schema:modifier] modifier spatialIndex

```sql
alter table `el_1240_cd672` add spatial index `el_1240_cd672_c_spatialindex`(`c`)
```

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 75 near " index `el_1240_cd672_c_spatialindex`(`c`)";

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
insert into `qb_1324_32444` (`age`, `city`, `name`, `score`) values (?, ?, ?, ?) on duplicate key update `age` = values(`age`)
```

- **Code:** `1062`
- **Message:** SQLSTATE[HY000]: General error: 1062 Duplicate entry '(alice,NY)' for key '(name,city)'

#### #1835 [Eloquent / json] whereJsonContains

```sql
select count(*) as aggregate from `json_1367_ea5b7` where json_contains(`doc`, ?, '$."roles"')
```

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #1838 [Eloquent / json] whereJsonDoesntContain

```sql
select count(*) as aggregate from `json_1370_f77a8` where not json_contains(`doc`, ?, '$."roles"')
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
- **Message:** SQLSTATE[HY000]: General error: 1149 SQL syntax error: column "ck2_1571_221b4.name" must appear in the GROUP BY clause or be used in an aggregate function

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

#### #2352 [PDO / typematrix:decimal] DECIMAL(38,38) stores 1.5 scaled

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 1.5 beyond the range, can't be converted to Decimal128(38,38).

#### #2363 [PDO / typematrix:decimal] DECIMAL(4,4) stores 1.5 scaled

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 1.5 beyond the range, can't be converted to Decimal64(4,4).

#### #2381 [PDO / typematrix:overflow] MEDIUMINT rejects overflow 8388608

- **Code:** `BEHAVIOR`
- **Message:** MEDIUMINT rejects overflow 8388608: value was accepted (stored 8388608) rather than rejected

#### #2383 [PDO / typematrix:overflow] MEDIUMINT UNSIGNED rejects overflow 16777216

- **Code:** `BEHAVIOR`
- **Message:** MEDIUMINT UNSIGNED rejects overflow 16777216: value was accepted (stored 16777216) rather than rejected

#### #2398 [PDO / typematrix:coercion] coerce: '10abc' + 0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #2399 [PDO / typematrix:coercion] coerce: 'abc' + 0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value abc

#### #2401 [PDO / typematrix:coercion] coerce: 1 + NULL

- **Code:** `BEHAVIOR`
- **Message:** coerce: 1 + NULL: got NULL

#### #2402 [PDO / typematrix:coercion] coerce: '3.5' * 2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 3.5

#### #2408 [PDO / typematrix:coercion] coerce: 1 = '1.0'

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 1.0

#### #2411 [PDO / typematrix:coercion] coerce: 0 = 'x'

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value x

#### #2412 [PDO / typematrix:coercion] coerce: TRUE + TRUE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator +, bad value [BOOL BOOL]

#### #2479 [PDO / typematrix:charset] column CHARACTER SET utf8mb4 reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET utf8mb4 round-trip: expected 'utf8mb4', got 'utf8'

#### #2481 [PDO / typematrix:charset] column CHARACTER SET ascii reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET ascii round-trip: expected 'ascii', got 'utf8'

#### #2482 [PDO / typematrix:charset] column CHARACTER SET latin1 reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET latin1 round-trip: expected 'latin1', got 'utf8'

#### #2483 [PDO / typematrix:charset] column CHARACTER SET binary reported

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 64 near " binary)";

#### #2484 [PDO / typematrix:charset] column CHARACTER SET gbk reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET gbk round-trip: expected 'gbk', got 'utf8'

#### #2485 [PDO / typematrix:charset] column CHARACTER SET utf16 reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET utf16 round-trip: expected 'utf16', got 'utf8'

#### #2486 [PDO / typematrix:charset] column CHARACTER SET utf32 reported

- **Code:** `BEHAVIOR`
- **Message:** CHARACTER SET utf32 round-trip: expected 'utf32', got 'utf8'

#### #2495 [PDO / typematrix:collation] 'abc'='ABC' COLLATE utf8mb4_general_ci

- **Code:** `BEHAVIOR`
- **Message:** 'abc'='ABC' COLLATE utf8mb4_general_ci: expected '1', got 0

#### #2496 [PDO / typematrix:collation] 'ABC' LIKE 'abc' COLLATE utf8mb4_general_ci

- **Code:** `BEHAVIOR`
- **Message:** 'ABC' LIKE 'abc' COLLATE utf8mb4_general_ci: expected '1', got 0

#### #2498 [PDO / typematrix:collation] WHERE column COLLATE utf8mb4_general_ci matches

- **Code:** `BEHAVIOR`
- **Message:** WHERE COLLATE utf8mb4_general_ci match count: expected '1', got 0

#### #2499 [PDO / typematrix:collation] 'abc'='ABC' COLLATE utf8mb4_0900_ai_ci

- **Code:** `BEHAVIOR`
- **Message:** 'abc'='ABC' COLLATE utf8mb4_0900_ai_ci: expected '1', got 0

#### #2500 [PDO / typematrix:collation] 'ABC' LIKE 'abc' COLLATE utf8mb4_0900_ai_ci

- **Code:** `BEHAVIOR`
- **Message:** 'ABC' LIKE 'abc' COLLATE utf8mb4_0900_ai_ci: expected '1', got 0

#### #2502 [PDO / typematrix:collation] WHERE column COLLATE utf8mb4_0900_ai_ci matches

- **Code:** `BEHAVIOR`
- **Message:** WHERE COLLATE utf8mb4_0900_ai_ci match count: expected '1', got 0

#### #2503 [PDO / typematrix:collation] 'abc'='ABC' COLLATE utf8mb4_unicode_ci

- **Code:** `BEHAVIOR`
- **Message:** 'abc'='ABC' COLLATE utf8mb4_unicode_ci: expected '1', got 0

#### #2504 [PDO / typematrix:collation] 'ABC' LIKE 'abc' COLLATE utf8mb4_unicode_ci

- **Code:** `BEHAVIOR`
- **Message:** 'ABC' LIKE 'abc' COLLATE utf8mb4_unicode_ci: expected '1', got 0

#### #2506 [PDO / typematrix:collation] WHERE column COLLATE utf8mb4_unicode_ci matches

- **Code:** `BEHAVIOR`
- **Message:** WHERE COLLATE utf8mb4_unicode_ci match count: expected '1', got 0

#### #2534 [PDO / typematrix:null] 3VL: IF(NULL, 1, 2)

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function if, bad value [ANY BIGINT BIGINT]

#### #2535 [PDO / typematrix:null] 3VL: GREATEST(1, NULL, 3)

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function greatest, bad value [BIGINT ANY BIGINT]

#### #2536 [PDO / typematrix:null] 3VL: LEAST(1, NULL, 3)

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [BIGINT ANY BIGINT]

#### #2538 [PDO / typematrix:null] 3VL: NULL IN (1, NULL)

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [ANY ANY]

#### #2543 [PDO / typematrix:null] 3VL: CASE WHEN NULL THEN 1 ELSE 2 END

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator case, bad value [ANY BIGINT BIGINT]

#### #2588 [PDO / typematrix:bit] bitop: BIT_AND(x)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: column x does not exist

#### #2595 [PDO / typematrix:json-path] JSON_EXTRACT $.e

- **Code:** `BEHAVIOR`
- **Message:** JSON_EXTRACT $.e: expected 'null', got NULL

#### #2607 [PDO / typematrix:json-path] JSON_DEPTH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #2613 [PDO / typematrix:json-path] JSON_CONTAINS

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains'

#### #2614 [PDO / typematrix:json-path] JSON_CONTAINS_PATH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_contains_path'

#### #2615 [PDO / typematrix:json-path] JSON_SEARCH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_search'

#### #2618 [PDO / typematrix:json-path] JSON_MERGE_PATCH

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_patch'

#### #2619 [PDO / typematrix:json-path] JSON_MERGE_PRESERVE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_merge_preserve'

#### #2623 [PDO / typematrix:json-path] JSON_REMOVE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_remove'

#### #2625 [PDO / typematrix:json-path] JSON_ARRAY_APPEND

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_array_append'

#### #2626 [PDO / typematrix:json-path] JSON_ARRAY_INSERT

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_array_insert'

#### #2628 [PDO / typematrix:json-path] JSON_STORAGE_SIZE

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_storage_size'

#### #2629 [PDO / typematrix:json-path] JSON_OVERLAPS

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_overlaps'

#### #2635 [PDO / typematrix:json-cast] JSON value preserves null

- **Code:** `BEHAVIOR`
- **Message:** JSON null: expected 'null', got NULL

#### #2690 [PDO / app:ecommerce] 10% discount off 19.95

- **Code:** `BEHAVIOR`
- **Message:** 10% discount off 19.95: expected '17.96', got '17.95'

#### #2798 [PDO / app:ecommerce] shipping fee charged for 50.00

- **Code:** `BEHAVIOR`
- **Message:** shipping fee charged for 50.00: expected '1', got 0

#### #2830 [PDO / app:social] recursive reach from user 1 (all hops)

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #2856 [PDO / app:analytics] GROUPING() flags grand total

- **Code:** `BEHAVIOR`
- **Message:** GROUPING() flags grand total: expected '1', got 0

#### #2872 [PDO / app:analytics] funnel conversion rate view->purchase

- **Code:** `BEHAVIOR`
- **Message:** funnel conversion rate view->purchase: expected '0.3333', got '0.3333333333'

#### #2997 [PDO / app:geo] nearest place to NYC via haversine

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [DECIMAL64 DOUBLE]

#### #2998 [PDO / app:geo] nearest-3 places to NYC list

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [DECIMAL64 DOUBLE]

#### #2999 [PDO / app:geo] places within 5km of NYC

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function least, bad value [DECIMAL64 DOUBLE]

#### #3016 [PDO / app:ecommerce] discount sweep 3% off 34.50

- **Code:** `BEHAVIOR`
- **Message:** discount sweep 3% off 34.50: expected '33.47', got '33.46'

#### #3099 [PDO / app:ecommerce] discount sweep 17% off 199.50

- **Code:** `BEHAVIOR`
- **Message:** discount sweep 17% off 199.50: expected '165.59', got '165.58'

#### #3294 [PDO / app:social] follow-chain length 2 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3295 [PDO / app:social] follow-chain length 3 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3296 [PDO / app:social] follow-chain length 4 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3297 [PDO / app:social] follow-chain length 5 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3298 [PDO / app:social] follow-chain length 6 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3299 [PDO / app:social] follow-chain length 8 reach from 1

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: In recursive query block of Recursive Common Table Expression reach, the recursive table must be referenced only once, and not in any subquery

#### #3634 [PDO / app:saas] quota 33/99 percent

- **Code:** `BEHAVIOR`
- **Message:** quota 33/99 percent: expected '33.33', got '33.33333300'

#### #3930 [PDO / ddlsurf:partition] EXPLAIN partitioned table

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 35 near " EXPLAIN SELECT * FROM `pt_3158_172dd` WHERE id=1";

#### #3935 [PDO / ddlsurf:partition] ALTER TABLE TRUNCATE PARTITION rejected

- **Code:** `BEHAVIOR`
- **Message:** ALTER TABLE TRUNCATE PARTITION rejected: statement was accepted but an error was expected

#### #3972 [PDO / ddlsurf:check] CHECK enforcement: age >= 0

- **Code:** `BEHAVIOR`
- **Message:** CHECK (age >= 0) is NOT enforced: violating value stored = -5

#### #3973 [PDO / ddlsurf:check] CHECK enforcement: pct 0..100

- **Code:** `BEHAVIOR`
- **Message:** CHECK (pct 0..100) is NOT enforced: violating value stored = 250

#### #3974 [PDO / ddlsurf:check] CHECK enforcement: n <> 0

- **Code:** `BEHAVIOR`
- **Message:** CHECK (n <> 0) is NOT enforced: violating value stored = 0

#### #3975 [PDO / ddlsurf:check] CHECK enforcement: named lo<hi

- **Code:** `BEHAVIOR`
- **Message:** CHECK (named lo<hi) is NOT enforced: violating value stored = 9

#### #4063 [PDO / err:type] out-of-range MEDIUMINT 8388608 surfaces error

- **Code:** `BEHAVIOR`
- **Message:** MEDIUMINT 8388608: out-of-range value accepted (no clamping expected)

#### #4077 [PDO / err:type] decimal int overflow surfaces error

- **Code:** `BEHAVIOR`
- **Message:** decimal int overflow: bad decimal accepted

#### #4083 [PDO / err:type] INT from hex string surfaces error

- **Code:** `BEHAVIOR`
- **Message:** INT from hex string: non-numeric value silently accepted

#### #4150 [Eloquent / eloq-app:ecommerce] grand revenue across all items

- **Code:** `BEHAVIOR`
- **Message:** grand revenue: expected 312.81, got '314.92'

#### #4186 [Eloquent / eloq-app:social] mutual follows via self-join

- **Code:** `BEHAVIOR`
- **Message:** one mutual pair: expected 1, got 2

#### #4203 [Eloquent / eloq-app:analytics] window-ish running total via correlated subquery

- **Code:** `ERROR`
- **Message:** Attempt to read property "running" on false

#### #4234 [Eloquent / eloq-app:ledger] net entries for account 2 = -200

- **Code:** `BEHAVIOR`
- **Message:** account 2 net: expected -200.0, got '200.00'

#### #4365 [PDO / sem:vector-fn] vec sub

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #4366 [PDO / sem:vector-fn] vec mul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #4367 [PDO / sem:vector-fn] l1_distance (gap?)

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'l1_distance'

#### #4529 [PDO / sem:operator] bit not

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, value '18446744073709551615'

#### #4602 [PDO / sem:setop] EXCEPT ALL 2 arms

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

#### #4603 [PDO / sem:setop] EXCEPT ALL 3 arms

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

#### #4604 [PDO / sem:setop] EXCEPT ALL overlap

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

#### #4605 [PDO / sem:setop] EXCEPT ALL dup arm

- **Code:** `20102`
- **Message:** SQLSTATE[HY000]: General error: 20102 EXCEPT/MINUS ALL clause is not yet implemented

#### #4684 [PDO / sem:grouping] GROUPING SETS a

- **Code:** `1149`
- **Message:** SQLSTATE[HY000]: General error: 1149 SQL syntax error: column "gr_3828_af5c4.b" must appear in the GROUP BY clause or be used in an aggregate function

#### #4863 [RedBean / redbean:query] find predicate #12: WHERE s LIKE ?

- **Code:** `BEHAVIOR`
- **Message:** find WHERE s LIKE ? matched: expected '2', got '3'

#### #4867 [RedBean / redbean:query] find predicate #16: WHERE n % 2 = 0

- **Code:** `BEHAVIOR`
- **Message:** find WHERE n % 2 = 0 matched: expected '3', got '4'

#### #4907 [RedBean / redbean:query] agg COUNT where like

- **Code:** `BEHAVIOR`
- **Message:** COUNT where like: expected '4', got '5'

#### #4988 [RedBean / redbean:type] type round-trip: float repeating

- **Code:** `BEHAVIOR`
- **Message:** float repeating expected col type ~'DOUBLE', got 'DECIMAL(10,2)'

#### #4993 [RedBean / redbean:type] type round-trip: long text 192

- **Code:** `BEHAVIOR`
- **Message:** long text 192 expected col type ~'TEXT', got 'VARCHAR(255)'

#### #5016 [RedBean / redbean:transaction] begin+rollback discards

- **Code:** `BEHAVIOR`
- **Message:** count after rollback: expected '1', got '2'

#### #5018 [RedBean / redbean:transaction] explicit tx rollback #1

- **Code:** `BEHAVIOR`
- **Message:** after rollback: expected '1', got '4'

#### #5020 [RedBean / redbean:transaction] explicit tx rollback #3

- **Code:** `BEHAVIOR`
- **Message:** after rollback: expected '1', got '4'

#### #5022 [RedBean / redbean:transaction] explicit tx rollback #5

- **Code:** `BEHAVIOR`
- **Message:** after rollback: expected '1', got '4'

#### #5026 [RedBean / redbean:transaction] rollback preserves pre-tx data

- **Code:** `BEHAVIOR`
- **Message:** pre-tx rows should survive rollback: expected '3', got '0'

#### #5037 [PDO / grid:arith] int0 add mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5038 [PDO / grid:arith] int0 add str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5039 [PDO / grid:arith] int0 add date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5040 [PDO / grid:arith] int0 add dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5048 [PDO / grid:arith] int1 add bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (1 + 9223372036854775807)

#### #5053 [PDO / grid:arith] int1 add mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5054 [PDO / grid:arith] int1 add str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5055 [PDO / grid:arith] int1 add date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5056 [PDO / grid:arith] int1 add dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5069 [PDO / grid:arith] intNeg add mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5070 [PDO / grid:arith] intNeg add str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5071 [PDO / grid:arith] intNeg add date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5072 [PDO / grid:arith] intNeg add dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5080 [PDO / grid:arith] intBig add bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (2147483647 + 9223372036854775807)

#### #5085 [PDO / grid:arith] intBig add mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5086 [PDO / grid:arith] intBig add str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5087 [PDO / grid:arith] intBig add date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5088 [PDO / grid:arith] intBig add dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5093 [PDO / grid:arith] bigint add int1

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 + 1)

#### #5095 [PDO / grid:arith] bigint add intBig

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 + 2147483647)

#### #5096 [PDO / grid:arith] bigint add bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 + 9223372036854775807)

#### #5100 [PDO / grid:arith] bigint add numStr

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 + 42)

#### #5101 [PDO / grid:arith] bigint add mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5102 [PDO / grid:arith] bigint add str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5103 [PDO / grid:arith] bigint add date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5104 [PDO / grid:arith] bigint add dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5160 [PDO / grid:arith] numStr add bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (42 + 9223372036854775807)

#### #5172 [PDO / grid:arith] mixStr add int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5173 [PDO / grid:arith] mixStr add int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5174 [PDO / grid:arith] mixStr add intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5175 [PDO / grid:arith] mixStr add intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5176 [PDO / grid:arith] mixStr add bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5188 [PDO / grid:arith] str add int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5189 [PDO / grid:arith] str add int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5190 [PDO / grid:arith] str add intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5191 [PDO / grid:arith] str add intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5192 [PDO / grid:arith] str add bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5204 [PDO / grid:arith] date add int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5205 [PDO / grid:arith] date add int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5206 [PDO / grid:arith] date add intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5207 [PDO / grid:arith] date add intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5208 [PDO / grid:arith] date add bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5220 [PDO / grid:arith] dt add int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5221 [PDO / grid:arith] dt add int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5222 [PDO / grid:arith] dt add intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5223 [PDO / grid:arith] dt add intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5224 [PDO / grid:arith] dt add bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5293 [PDO / grid:arith] int0 sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5294 [PDO / grid:arith] int0 sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5295 [PDO / grid:arith] int0 sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5296 [PDO / grid:arith] int0 sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5309 [PDO / grid:arith] int1 sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5310 [PDO / grid:arith] int1 sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5311 [PDO / grid:arith] int1 sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5312 [PDO / grid:arith] int1 sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5320 [PDO / grid:arith] intNeg sub bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (-7 - 9223372036854775807)

#### #5325 [PDO / grid:arith] intNeg sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5326 [PDO / grid:arith] intNeg sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5327 [PDO / grid:arith] intNeg sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5328 [PDO / grid:arith] intNeg sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5341 [PDO / grid:arith] intBig sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5342 [PDO / grid:arith] intBig sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5343 [PDO / grid:arith] intBig sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5344 [PDO / grid:arith] intBig sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5350 [PDO / grid:arith] bigint sub intNeg

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 - -7)

#### #5357 [PDO / grid:arith] bigint sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5358 [PDO / grid:arith] bigint sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5359 [PDO / grid:arith] bigint sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5360 [PDO / grid:arith] bigint sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5367 [PDO / grid:arith] dec sub intBig

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 12.50-2147483647

#### #5368 [PDO / grid:arith] dec sub bigint

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 12.50-9223372036854775807

#### #5382 [PDO / grid:arith] decNeg sub intNeg

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: -3.14--7

#### #5397 [PDO / grid:arith] frac sub int1

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 0.001-1

#### #5399 [PDO / grid:arith] frac sub intBig

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 0.001-2147483647

#### #5400 [PDO / grid:arith] frac sub bigint

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 0.001-9223372036854775807

#### #5401 [PDO / grid:arith] frac sub dec

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal64 Sub overflow: 0.001-12.50

#### #5411 [PDO / grid:arith] frac sub bigDec

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Sub overflow: 0.001-99999999999999999999.99

#### #5420 [PDO / grid:arith] numStr sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5421 [PDO / grid:arith] numStr sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5422 [PDO / grid:arith] numStr sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5423 [PDO / grid:arith] numStr sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5424 [PDO / grid:arith] numStr sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5426 [PDO / grid:arith] numStr sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5428 [PDO / grid:arith] mixStr sub int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5429 [PDO / grid:arith] mixStr sub int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5430 [PDO / grid:arith] mixStr sub intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5431 [PDO / grid:arith] mixStr sub intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5432 [PDO / grid:arith] mixStr sub bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5436 [PDO / grid:arith] mixStr sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5437 [PDO / grid:arith] mixStr sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5438 [PDO / grid:arith] mixStr sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5439 [PDO / grid:arith] mixStr sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5440 [PDO / grid:arith] mixStr sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5442 [PDO / grid:arith] mixStr sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5444 [PDO / grid:arith] str sub int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5445 [PDO / grid:arith] str sub int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5446 [PDO / grid:arith] str sub intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5447 [PDO / grid:arith] str sub intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5448 [PDO / grid:arith] str sub bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5452 [PDO / grid:arith] str sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5453 [PDO / grid:arith] str sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5454 [PDO / grid:arith] str sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5455 [PDO / grid:arith] str sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5456 [PDO / grid:arith] str sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5458 [PDO / grid:arith] str sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5460 [PDO / grid:arith] date sub int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5461 [PDO / grid:arith] date sub int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5462 [PDO / grid:arith] date sub intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5463 [PDO / grid:arith] date sub intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5464 [PDO / grid:arith] date sub bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5468 [PDO / grid:arith] date sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5469 [PDO / grid:arith] date sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5470 [PDO / grid:arith] date sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5471 [PDO / grid:arith] date sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5472 [PDO / grid:arith] date sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5474 [PDO / grid:arith] date sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5476 [PDO / grid:arith] dt sub int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5477 [PDO / grid:arith] dt sub int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5478 [PDO / grid:arith] dt sub intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5479 [PDO / grid:arith] dt sub intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5480 [PDO / grid:arith] dt sub bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5484 [PDO / grid:arith] dt sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5485 [PDO / grid:arith] dt sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5486 [PDO / grid:arith] dt sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5487 [PDO / grid:arith] dt sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5488 [PDO / grid:arith] dt sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5490 [PDO / grid:arith] dt sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5516 [PDO / grid:arith] zeroStr sub numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5517 [PDO / grid:arith] zeroStr sub mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5518 [PDO / grid:arith] zeroStr sub str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5519 [PDO / grid:arith] zeroStr sub date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5520 [PDO / grid:arith] zeroStr sub dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5522 [PDO / grid:arith] zeroStr sub zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator -, bad value [VARCHAR VARCHAR]

#### #5549 [PDO / grid:arith] int0 mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5550 [PDO / grid:arith] int0 mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5551 [PDO / grid:arith] int0 mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5552 [PDO / grid:arith] int0 mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5565 [PDO / grid:arith] int1 mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5566 [PDO / grid:arith] int1 mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5567 [PDO / grid:arith] int1 mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5568 [PDO / grid:arith] int1 mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5576 [PDO / grid:arith] intNeg mul bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (-7 * 9223372036854775807)

#### #5581 [PDO / grid:arith] intNeg mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5582 [PDO / grid:arith] intNeg mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5583 [PDO / grid:arith] intNeg mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5584 [PDO / grid:arith] intNeg mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5592 [PDO / grid:arith] intBig mul bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (2147483647 * 9223372036854775807)

#### #5597 [PDO / grid:arith] intBig mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5598 [PDO / grid:arith] intBig mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5599 [PDO / grid:arith] intBig mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5600 [PDO / grid:arith] intBig mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5606 [PDO / grid:arith] bigint mul intNeg

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 * -7)

#### #5607 [PDO / grid:arith] bigint mul intBig

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 * 2147483647)

#### #5608 [PDO / grid:arith] bigint mul bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 * 9223372036854775807)

#### #5612 [PDO / grid:arith] bigint mul numStr

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (9223372036854775807 * 42)

#### #5613 [PDO / grid:arith] bigint mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5614 [PDO / grid:arith] bigint mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5615 [PDO / grid:arith] bigint mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5616 [PDO / grid:arith] bigint mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5619 [PDO / grid:arith] bigint mul bigDec

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Mul overflow: 9223372036854775807*99999999999999999999.99

#### #5672 [PDO / grid:arith] numStr mul bigint

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type int64, (42 * 9223372036854775807)

#### #5676 [PDO / grid:arith] numStr mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5677 [PDO / grid:arith] numStr mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5678 [PDO / grid:arith] numStr mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5679 [PDO / grid:arith] numStr mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5680 [PDO / grid:arith] numStr mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5682 [PDO / grid:arith] numStr mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5684 [PDO / grid:arith] mixStr mul int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5685 [PDO / grid:arith] mixStr mul int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5686 [PDO / grid:arith] mixStr mul intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5687 [PDO / grid:arith] mixStr mul intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5688 [PDO / grid:arith] mixStr mul bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #5692 [PDO / grid:arith] mixStr mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5693 [PDO / grid:arith] mixStr mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5694 [PDO / grid:arith] mixStr mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5695 [PDO / grid:arith] mixStr mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5696 [PDO / grid:arith] mixStr mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5698 [PDO / grid:arith] mixStr mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5700 [PDO / grid:arith] str mul int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5701 [PDO / grid:arith] str mul int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5702 [PDO / grid:arith] str mul intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5703 [PDO / grid:arith] str mul intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5704 [PDO / grid:arith] str mul bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #5708 [PDO / grid:arith] str mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5709 [PDO / grid:arith] str mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5710 [PDO / grid:arith] str mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5711 [PDO / grid:arith] str mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5712 [PDO / grid:arith] str mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5714 [PDO / grid:arith] str mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5716 [PDO / grid:arith] date mul int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5717 [PDO / grid:arith] date mul int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5718 [PDO / grid:arith] date mul intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5719 [PDO / grid:arith] date mul intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5720 [PDO / grid:arith] date mul bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #5724 [PDO / grid:arith] date mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5725 [PDO / grid:arith] date mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5726 [PDO / grid:arith] date mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5727 [PDO / grid:arith] date mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5728 [PDO / grid:arith] date mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5730 [PDO / grid:arith] date mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5732 [PDO / grid:arith] dt mul int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5733 [PDO / grid:arith] dt mul int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5734 [PDO / grid:arith] dt mul intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5735 [PDO / grid:arith] dt mul intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5736 [PDO / grid:arith] dt mul bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #5740 [PDO / grid:arith] dt mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5741 [PDO / grid:arith] dt mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5742 [PDO / grid:arith] dt mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5743 [PDO / grid:arith] dt mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5744 [PDO / grid:arith] dt mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5746 [PDO / grid:arith] dt mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5772 [PDO / grid:arith] zeroStr mul numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5773 [PDO / grid:arith] zeroStr mul mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5774 [PDO / grid:arith] zeroStr mul str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5775 [PDO / grid:arith] zeroStr mul date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5776 [PDO / grid:arith] zeroStr mul dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5778 [PDO / grid:arith] zeroStr mul zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator *, bad value [VARCHAR VARCHAR]

#### #5784 [PDO / grid:arith] bigDec mul bigint

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Mul overflow: 99999999999999999999.99*9223372036854775807

#### #5795 [PDO / grid:arith] bigDec mul bigDec

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: Decimal128 Mul overflow: 99999999999999999999.99*99999999999999999999.99

#### #5932 [PDO / grid:arith] numStr div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5933 [PDO / grid:arith] numStr div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5934 [PDO / grid:arith] numStr div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5935 [PDO / grid:arith] numStr div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5936 [PDO / grid:arith] numStr div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5938 [PDO / grid:arith] numStr div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5948 [PDO / grid:arith] mixStr div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5949 [PDO / grid:arith] mixStr div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5950 [PDO / grid:arith] mixStr div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5951 [PDO / grid:arith] mixStr div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5952 [PDO / grid:arith] mixStr div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5954 [PDO / grid:arith] mixStr div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5964 [PDO / grid:arith] str div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5965 [PDO / grid:arith] str div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5966 [PDO / grid:arith] str div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5967 [PDO / grid:arith] str div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5968 [PDO / grid:arith] str div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5970 [PDO / grid:arith] str div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5980 [PDO / grid:arith] date div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5981 [PDO / grid:arith] date div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5982 [PDO / grid:arith] date div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5983 [PDO / grid:arith] date div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5984 [PDO / grid:arith] date div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5986 [PDO / grid:arith] date div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5996 [PDO / grid:arith] dt div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5997 [PDO / grid:arith] dt div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5998 [PDO / grid:arith] dt div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #5999 [PDO / grid:arith] dt div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6000 [PDO / grid:arith] dt div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6002 [PDO / grid:arith] dt div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6028 [PDO / grid:arith] zeroStr div numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6029 [PDO / grid:arith] zeroStr div mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6030 [PDO / grid:arith] zeroStr div str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6031 [PDO / grid:arith] zeroStr div date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6032 [PDO / grid:arith] zeroStr div dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6034 [PDO / grid:arith] zeroStr div zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator /, bad value [VARCHAR VARCHAR]

#### #6123 [PDO / grid:arith] bigint idiv frac

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type BIGINT,

#### #6188 [PDO / grid:arith] numStr idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6189 [PDO / grid:arith] numStr idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6190 [PDO / grid:arith] numStr idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6191 [PDO / grid:arith] numStr idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6192 [PDO / grid:arith] numStr idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6193 [PDO / grid:arith] numStr idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6194 [PDO / grid:arith] numStr idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6204 [PDO / grid:arith] mixStr idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6205 [PDO / grid:arith] mixStr idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6206 [PDO / grid:arith] mixStr idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6207 [PDO / grid:arith] mixStr idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6208 [PDO / grid:arith] mixStr idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6209 [PDO / grid:arith] mixStr idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6210 [PDO / grid:arith] mixStr idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6220 [PDO / grid:arith] str idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6221 [PDO / grid:arith] str idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6222 [PDO / grid:arith] str idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6223 [PDO / grid:arith] str idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6224 [PDO / grid:arith] str idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6225 [PDO / grid:arith] str idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6226 [PDO / grid:arith] str idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6236 [PDO / grid:arith] date idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6237 [PDO / grid:arith] date idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6238 [PDO / grid:arith] date idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6239 [PDO / grid:arith] date idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6240 [PDO / grid:arith] date idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6241 [PDO / grid:arith] date idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6242 [PDO / grid:arith] date idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6252 [PDO / grid:arith] dt idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6253 [PDO / grid:arith] dt idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6254 [PDO / grid:arith] dt idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6255 [PDO / grid:arith] dt idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6256 [PDO / grid:arith] dt idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6257 [PDO / grid:arith] dt idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6258 [PDO / grid:arith] dt idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6268 [PDO / grid:arith] nul idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6269 [PDO / grid:arith] nul idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6270 [PDO / grid:arith] nul idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6271 [PDO / grid:arith] nul idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6272 [PDO / grid:arith] nul idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6274 [PDO / grid:arith] nul idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [ANY VARCHAR]

#### #6284 [PDO / grid:arith] zeroStr idiv numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6285 [PDO / grid:arith] zeroStr idiv mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6286 [PDO / grid:arith] zeroStr idiv str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6287 [PDO / grid:arith] zeroStr idiv date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6288 [PDO / grid:arith] zeroStr idiv dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6289 [PDO / grid:arith] zeroStr idiv nul

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR ANY]

#### #6290 [PDO / grid:arith] zeroStr idiv zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator div, bad value [VARCHAR VARCHAR]

#### #6293 [PDO / grid:arith] bigDec idiv int1

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type BIGINT,

#### #6294 [PDO / grid:arith] bigDec idiv intNeg

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type BIGINT,

#### #6298 [PDO / grid:arith] bigDec idiv decNeg

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type BIGINT,

#### #6299 [PDO / grid:arith] bigDec idiv frac

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type BIGINT,

#### #6317 [PDO / grid:arith] int0 mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6318 [PDO / grid:arith] int0 mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6319 [PDO / grid:arith] int0 mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6320 [PDO / grid:arith] int0 mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6333 [PDO / grid:arith] int1 mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6334 [PDO / grid:arith] int1 mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6335 [PDO / grid:arith] int1 mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6336 [PDO / grid:arith] int1 mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6349 [PDO / grid:arith] intNeg mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6350 [PDO / grid:arith] intNeg mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6351 [PDO / grid:arith] intNeg mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6352 [PDO / grid:arith] intNeg mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6365 [PDO / grid:arith] intBig mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6366 [PDO / grid:arith] intBig mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6367 [PDO / grid:arith] intBig mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6368 [PDO / grid:arith] intBig mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6381 [PDO / grid:arith] bigint mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6382 [PDO / grid:arith] bigint mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6383 [PDO / grid:arith] bigint mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6384 [PDO / grid:arith] bigint mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6444 [PDO / grid:arith] numStr mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6445 [PDO / grid:arith] numStr mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6446 [PDO / grid:arith] numStr mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6447 [PDO / grid:arith] numStr mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6448 [PDO / grid:arith] numStr mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6450 [PDO / grid:arith] numStr mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6452 [PDO / grid:arith] mixStr mod int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6453 [PDO / grid:arith] mixStr mod int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6454 [PDO / grid:arith] mixStr mod intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6455 [PDO / grid:arith] mixStr mod intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6456 [PDO / grid:arith] mixStr mod bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6460 [PDO / grid:arith] mixStr mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6461 [PDO / grid:arith] mixStr mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6462 [PDO / grid:arith] mixStr mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6463 [PDO / grid:arith] mixStr mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6464 [PDO / grid:arith] mixStr mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6466 [PDO / grid:arith] mixStr mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6468 [PDO / grid:arith] str mod int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6469 [PDO / grid:arith] str mod int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6470 [PDO / grid:arith] str mod intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6471 [PDO / grid:arith] str mod intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6472 [PDO / grid:arith] str mod bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6476 [PDO / grid:arith] str mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6477 [PDO / grid:arith] str mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6478 [PDO / grid:arith] str mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6479 [PDO / grid:arith] str mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6480 [PDO / grid:arith] str mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6482 [PDO / grid:arith] str mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6484 [PDO / grid:arith] date mod int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6485 [PDO / grid:arith] date mod int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6486 [PDO / grid:arith] date mod intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6487 [PDO / grid:arith] date mod intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6488 [PDO / grid:arith] date mod bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6492 [PDO / grid:arith] date mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6493 [PDO / grid:arith] date mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6494 [PDO / grid:arith] date mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6495 [PDO / grid:arith] date mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6496 [PDO / grid:arith] date mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6498 [PDO / grid:arith] date mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6500 [PDO / grid:arith] dt mod int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6501 [PDO / grid:arith] dt mod int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6502 [PDO / grid:arith] dt mod intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6503 [PDO / grid:arith] dt mod intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6504 [PDO / grid:arith] dt mod bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6508 [PDO / grid:arith] dt mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6509 [PDO / grid:arith] dt mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6510 [PDO / grid:arith] dt mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6511 [PDO / grid:arith] dt mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6512 [PDO / grid:arith] dt mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6514 [PDO / grid:arith] dt mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6540 [PDO / grid:arith] zeroStr mod numStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6541 [PDO / grid:arith] zeroStr mod mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6542 [PDO / grid:arith] zeroStr mod str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6543 [PDO / grid:arith] zeroStr mod date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6544 [PDO / grid:arith] zeroStr mod dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6546 [PDO / grid:arith] zeroStr mod zeroStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator %, bad value [VARCHAR VARCHAR]

#### #6573 [PDO / grid:compare] int0 eq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6574 [PDO / grid:compare] int0 eq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6575 [PDO / grid:compare] int0 eq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6576 [PDO / grid:compare] int0 eq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6589 [PDO / grid:compare] int1 eq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6590 [PDO / grid:compare] int1 eq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6591 [PDO / grid:compare] int1 eq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6592 [PDO / grid:compare] int1 eq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6605 [PDO / grid:compare] intNeg eq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6606 [PDO / grid:compare] intNeg eq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6607 [PDO / grid:compare] intNeg eq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6608 [PDO / grid:compare] intNeg eq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6621 [PDO / grid:compare] intBig eq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6622 [PDO / grid:compare] intBig eq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6623 [PDO / grid:compare] intBig eq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6624 [PDO / grid:compare] intBig eq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6637 [PDO / grid:compare] bigint eq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6638 [PDO / grid:compare] bigint eq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6639 [PDO / grid:compare] bigint eq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6640 [PDO / grid:compare] bigint eq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6708 [PDO / grid:compare] mixStr eq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6709 [PDO / grid:compare] mixStr eq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6710 [PDO / grid:compare] mixStr eq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6711 [PDO / grid:compare] mixStr eq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6712 [PDO / grid:compare] mixStr eq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6724 [PDO / grid:compare] str eq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6725 [PDO / grid:compare] str eq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6726 [PDO / grid:compare] str eq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6727 [PDO / grid:compare] str eq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6728 [PDO / grid:compare] str eq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6740 [PDO / grid:compare] date eq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6741 [PDO / grid:compare] date eq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6742 [PDO / grid:compare] date eq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6743 [PDO / grid:compare] date eq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6744 [PDO / grid:compare] date eq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6756 [PDO / grid:compare] dt eq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6757 [PDO / grid:compare] dt eq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6758 [PDO / grid:compare] dt eq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6759 [PDO / grid:compare] dt eq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6760 [PDO / grid:compare] dt eq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6829 [PDO / grid:compare] int0 ne mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6830 [PDO / grid:compare] int0 ne str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6831 [PDO / grid:compare] int0 ne date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6832 [PDO / grid:compare] int0 ne dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6845 [PDO / grid:compare] int1 ne mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6846 [PDO / grid:compare] int1 ne str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6847 [PDO / grid:compare] int1 ne date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6848 [PDO / grid:compare] int1 ne dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6861 [PDO / grid:compare] intNeg ne mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6862 [PDO / grid:compare] intNeg ne str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6863 [PDO / grid:compare] intNeg ne date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6864 [PDO / grid:compare] intNeg ne dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6877 [PDO / grid:compare] intBig ne mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6878 [PDO / grid:compare] intBig ne str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6879 [PDO / grid:compare] intBig ne date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6880 [PDO / grid:compare] intBig ne dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6893 [PDO / grid:compare] bigint ne mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6894 [PDO / grid:compare] bigint ne str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6895 [PDO / grid:compare] bigint ne date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6896 [PDO / grid:compare] bigint ne dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #6964 [PDO / grid:compare] mixStr ne int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6965 [PDO / grid:compare] mixStr ne int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6966 [PDO / grid:compare] mixStr ne intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6967 [PDO / grid:compare] mixStr ne intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6968 [PDO / grid:compare] mixStr ne bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #6980 [PDO / grid:compare] str ne int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6981 [PDO / grid:compare] str ne int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6982 [PDO / grid:compare] str ne intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6983 [PDO / grid:compare] str ne intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6984 [PDO / grid:compare] str ne bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #6996 [PDO / grid:compare] date ne int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6997 [PDO / grid:compare] date ne int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6998 [PDO / grid:compare] date ne intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #6999 [PDO / grid:compare] date ne intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7000 [PDO / grid:compare] date ne bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7012 [PDO / grid:compare] dt ne int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7013 [PDO / grid:compare] dt ne int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7014 [PDO / grid:compare] dt ne intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7015 [PDO / grid:compare] dt ne intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7016 [PDO / grid:compare] dt ne bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7085 [PDO / grid:compare] int0 lt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7086 [PDO / grid:compare] int0 lt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7087 [PDO / grid:compare] int0 lt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7088 [PDO / grid:compare] int0 lt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7101 [PDO / grid:compare] int1 lt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7102 [PDO / grid:compare] int1 lt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7103 [PDO / grid:compare] int1 lt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7104 [PDO / grid:compare] int1 lt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7117 [PDO / grid:compare] intNeg lt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7118 [PDO / grid:compare] intNeg lt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7119 [PDO / grid:compare] intNeg lt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7120 [PDO / grid:compare] intNeg lt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7133 [PDO / grid:compare] intBig lt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7134 [PDO / grid:compare] intBig lt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7135 [PDO / grid:compare] intBig lt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7136 [PDO / grid:compare] intBig lt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7149 [PDO / grid:compare] bigint lt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7150 [PDO / grid:compare] bigint lt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7151 [PDO / grid:compare] bigint lt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7152 [PDO / grid:compare] bigint lt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7220 [PDO / grid:compare] mixStr lt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7221 [PDO / grid:compare] mixStr lt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7222 [PDO / grid:compare] mixStr lt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7223 [PDO / grid:compare] mixStr lt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7224 [PDO / grid:compare] mixStr lt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7236 [PDO / grid:compare] str lt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7237 [PDO / grid:compare] str lt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7238 [PDO / grid:compare] str lt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7239 [PDO / grid:compare] str lt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7240 [PDO / grid:compare] str lt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7252 [PDO / grid:compare] date lt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7253 [PDO / grid:compare] date lt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7254 [PDO / grid:compare] date lt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7255 [PDO / grid:compare] date lt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7256 [PDO / grid:compare] date lt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7268 [PDO / grid:compare] dt lt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7269 [PDO / grid:compare] dt lt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7270 [PDO / grid:compare] dt lt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7271 [PDO / grid:compare] dt lt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7272 [PDO / grid:compare] dt lt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7341 [PDO / grid:compare] int0 le mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7342 [PDO / grid:compare] int0 le str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7343 [PDO / grid:compare] int0 le date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7344 [PDO / grid:compare] int0 le dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7357 [PDO / grid:compare] int1 le mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7358 [PDO / grid:compare] int1 le str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7359 [PDO / grid:compare] int1 le date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7360 [PDO / grid:compare] int1 le dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7373 [PDO / grid:compare] intNeg le mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7374 [PDO / grid:compare] intNeg le str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7375 [PDO / grid:compare] intNeg le date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7376 [PDO / grid:compare] intNeg le dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7389 [PDO / grid:compare] intBig le mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7390 [PDO / grid:compare] intBig le str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7391 [PDO / grid:compare] intBig le date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7392 [PDO / grid:compare] intBig le dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7405 [PDO / grid:compare] bigint le mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7406 [PDO / grid:compare] bigint le str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7407 [PDO / grid:compare] bigint le date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7408 [PDO / grid:compare] bigint le dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7476 [PDO / grid:compare] mixStr le int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7477 [PDO / grid:compare] mixStr le int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7478 [PDO / grid:compare] mixStr le intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7479 [PDO / grid:compare] mixStr le intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7480 [PDO / grid:compare] mixStr le bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7492 [PDO / grid:compare] str le int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7493 [PDO / grid:compare] str le int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7494 [PDO / grid:compare] str le intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7495 [PDO / grid:compare] str le intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7496 [PDO / grid:compare] str le bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7508 [PDO / grid:compare] date le int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7509 [PDO / grid:compare] date le int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7510 [PDO / grid:compare] date le intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7511 [PDO / grid:compare] date le intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7512 [PDO / grid:compare] date le bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7524 [PDO / grid:compare] dt le int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7525 [PDO / grid:compare] dt le int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7526 [PDO / grid:compare] dt le intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7527 [PDO / grid:compare] dt le intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7528 [PDO / grid:compare] dt le bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7597 [PDO / grid:compare] int0 gt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7598 [PDO / grid:compare] int0 gt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7599 [PDO / grid:compare] int0 gt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7600 [PDO / grid:compare] int0 gt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7613 [PDO / grid:compare] int1 gt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7614 [PDO / grid:compare] int1 gt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7615 [PDO / grid:compare] int1 gt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7616 [PDO / grid:compare] int1 gt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7629 [PDO / grid:compare] intNeg gt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7630 [PDO / grid:compare] intNeg gt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7631 [PDO / grid:compare] intNeg gt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7632 [PDO / grid:compare] intNeg gt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7645 [PDO / grid:compare] intBig gt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7646 [PDO / grid:compare] intBig gt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7647 [PDO / grid:compare] intBig gt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7648 [PDO / grid:compare] intBig gt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7661 [PDO / grid:compare] bigint gt mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7662 [PDO / grid:compare] bigint gt str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7663 [PDO / grid:compare] bigint gt date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7664 [PDO / grid:compare] bigint gt dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7732 [PDO / grid:compare] mixStr gt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7733 [PDO / grid:compare] mixStr gt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7734 [PDO / grid:compare] mixStr gt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7735 [PDO / grid:compare] mixStr gt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7736 [PDO / grid:compare] mixStr gt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7748 [PDO / grid:compare] str gt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7749 [PDO / grid:compare] str gt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7750 [PDO / grid:compare] str gt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7751 [PDO / grid:compare] str gt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7752 [PDO / grid:compare] str gt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7764 [PDO / grid:compare] date gt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7765 [PDO / grid:compare] date gt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7766 [PDO / grid:compare] date gt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7767 [PDO / grid:compare] date gt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7768 [PDO / grid:compare] date gt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7780 [PDO / grid:compare] dt gt int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7781 [PDO / grid:compare] dt gt int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7782 [PDO / grid:compare] dt gt intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7783 [PDO / grid:compare] dt gt intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7784 [PDO / grid:compare] dt gt bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7853 [PDO / grid:compare] int0 ge mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7854 [PDO / grid:compare] int0 ge str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7855 [PDO / grid:compare] int0 ge date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7856 [PDO / grid:compare] int0 ge dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7869 [PDO / grid:compare] int1 ge mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7870 [PDO / grid:compare] int1 ge str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7871 [PDO / grid:compare] int1 ge date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7872 [PDO / grid:compare] int1 ge dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7885 [PDO / grid:compare] intNeg ge mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7886 [PDO / grid:compare] intNeg ge str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7887 [PDO / grid:compare] intNeg ge date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7888 [PDO / grid:compare] intNeg ge dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7901 [PDO / grid:compare] intBig ge mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7902 [PDO / grid:compare] intBig ge str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7903 [PDO / grid:compare] intBig ge date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7904 [PDO / grid:compare] intBig ge dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7917 [PDO / grid:compare] bigint ge mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7918 [PDO / grid:compare] bigint ge str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #7919 [PDO / grid:compare] bigint ge date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #7920 [PDO / grid:compare] bigint ge dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #7988 [PDO / grid:compare] mixStr ge int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7989 [PDO / grid:compare] mixStr ge int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7990 [PDO / grid:compare] mixStr ge intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7991 [PDO / grid:compare] mixStr ge intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #7992 [PDO / grid:compare] mixStr ge bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8004 [PDO / grid:compare] str ge int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8005 [PDO / grid:compare] str ge int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8006 [PDO / grid:compare] str ge intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8007 [PDO / grid:compare] str ge intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8008 [PDO / grid:compare] str ge bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8020 [PDO / grid:compare] date ge int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8021 [PDO / grid:compare] date ge int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8022 [PDO / grid:compare] date ge intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8023 [PDO / grid:compare] date ge intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8024 [PDO / grid:compare] date ge bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8036 [PDO / grid:compare] dt ge int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8037 [PDO / grid:compare] dt ge int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8038 [PDO / grid:compare] dt ge intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8039 [PDO / grid:compare] dt ge intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8040 [PDO / grid:compare] dt ge bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8109 [PDO / grid:compare] int0 nseq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8110 [PDO / grid:compare] int0 nseq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8111 [PDO / grid:compare] int0 nseq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8112 [PDO / grid:compare] int0 nseq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8125 [PDO / grid:compare] int1 nseq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8126 [PDO / grid:compare] int1 nseq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8127 [PDO / grid:compare] int1 nseq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8128 [PDO / grid:compare] int1 nseq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8141 [PDO / grid:compare] intNeg nseq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8142 [PDO / grid:compare] intNeg nseq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8143 [PDO / grid:compare] intNeg nseq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8144 [PDO / grid:compare] intNeg nseq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8157 [PDO / grid:compare] intBig nseq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8158 [PDO / grid:compare] intBig nseq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8159 [PDO / grid:compare] intBig nseq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8160 [PDO / grid:compare] intBig nseq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8173 [PDO / grid:compare] bigint nseq mixStr

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8174 [PDO / grid:compare] bigint nseq str

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8175 [PDO / grid:compare] bigint nseq date

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8176 [PDO / grid:compare] bigint nseq dt

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8244 [PDO / grid:compare] mixStr nseq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8245 [PDO / grid:compare] mixStr nseq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8246 [PDO / grid:compare] mixStr nseq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8247 [PDO / grid:compare] mixStr nseq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8248 [PDO / grid:compare] mixStr nseq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10abc

#### #8260 [PDO / grid:compare] str nseq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8261 [PDO / grid:compare] str nseq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8262 [PDO / grid:compare] str nseq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8263 [PDO / grid:compare] str nseq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8264 [PDO / grid:compare] str nseq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value hello

#### #8276 [PDO / grid:compare] date nseq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8277 [PDO / grid:compare] date nseq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8278 [PDO / grid:compare] date nseq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8279 [PDO / grid:compare] date nseq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8280 [PDO / grid:compare] date nseq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8292 [PDO / grid:compare] dt nseq int0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8293 [PDO / grid:compare] dt nseq int1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8294 [PDO / grid:compare] dt nseq intNeg

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8295 [PDO / grid:compare] dt nseq intBig

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8296 [PDO / grid:compare] dt nseq bigint

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #8822 [PDO / fn:string-var] SPACE #0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value abc

#### #8823 [PDO / fn:string-var] SPACE #1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value

#### #8824 [PDO / fn:string-var] SPACE #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value Héllo Wörld

#### #8825 [PDO / fn:string-var] SPACE #3

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value pad

#### #8826 [PDO / fn:string-var] SPACE #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value a,b,c

#### #8827 [PDO / fn:string-var] SPACE #5

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #8828 [PDO / fn:string-var] SPACE #6

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 123.45

#### #8829 [PDO / fn:string-var] SPACE #7

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 😀emoji

#### #8960 [PDO / fn:numeric-var] SQRT #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument Sqrt, bad value -1

#### #8962 [PDO / fn:numeric-var] SQRT #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument Sqrt, bad value -2.5

#### #8971 [PDO / fn:numeric-var] EXP #5

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type float64, DOUBLE value is out of range in 'exp(1e+06)'

#### #8974 [PDO / fn:numeric-var] LN #0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument ln, bad value 0

#### #8976 [PDO / fn:numeric-var] LN #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument ln, bad value -1

#### #8978 [PDO / fn:numeric-var] LN #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument ln, bad value -2.5

#### #8982 [PDO / fn:numeric-var] LOG2 #0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log2, bad value 0

#### #8984 [PDO / fn:numeric-var] LOG2 #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log2, bad value -1

#### #8986 [PDO / fn:numeric-var] LOG2 #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log2, bad value -2.5

#### #8990 [PDO / fn:numeric-var] LOG10 #0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log10, bad value 0

#### #8992 [PDO / fn:numeric-var] LOG10 #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log10, bad value -1

#### #8994 [PDO / fn:numeric-var] LOG10 #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument log10, bad value -2.5

#### #9025 [PDO / fn:numeric-var] ASIN #3

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument asin, bad value 3.14159

#### #9026 [PDO / fn:numeric-var] ASIN #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument asin, bad value -2.5

#### #9027 [PDO / fn:numeric-var] ASIN #5

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument asin, bad value 1e+06

#### #9029 [PDO / fn:numeric-var] ASIN #7

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument asin, bad value 255

#### #9033 [PDO / fn:numeric-var] ACOS #3

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument acos, bad value 3.14159

#### #9034 [PDO / fn:numeric-var] ACOS #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument acos, bad value -2.5

#### #9035 [PDO / fn:numeric-var] ACOS #5

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument acos, bad value 1e+06

#### #9037 [PDO / fn:numeric-var] ACOS #7

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument acos, bad value 255

#### #9062 [PDO / fn:numeric-var] CRC32 #0

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [BIGINT]

#### #9063 [PDO / fn:numeric-var] CRC32 #1

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [BIGINT]

#### #9064 [PDO / fn:numeric-var] CRC32 #2

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [BIGINT]

#### #9065 [PDO / fn:numeric-var] CRC32 #3

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [DECIMAL64]

#### #9066 [PDO / fn:numeric-var] CRC32 #4

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [DECIMAL64]

#### #9067 [PDO / fn:numeric-var] CRC32 #5

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [BIGINT]

#### #9068 [PDO / fn:numeric-var] CRC32 #6

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [DECIMAL64]

#### #9069 [PDO / fn:numeric-var] CRC32 #7

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument function crc32, bad value [BIGINT]

#### #9230 [PDO / fn:date-var] TIME #0

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type time, '2026-06-23'

#### #9231 [PDO / fn:date-var] TIME #1

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type time, '2024-02-29'

#### #9233 [PDO / fn:date-var] TIME #3

- **Code:** `1690`
- **Message:** SQLSTATE[HY000]: General error: 1690 data out of range: data type time, '2000-01-01'

#### #9360 [PDO / fn:json-var] JSON_DEPTH #0

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #9361 [PDO / fn:json-var] JSON_DEPTH #1

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #9362 [PDO / fn:json-var] JSON_DEPTH #2

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #9363 [PDO / fn:json-var] JSON_DEPTH #3

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #9364 [PDO / fn:json-var] JSON_DEPTH #4

- **Code:** `20105`
- **Message:** SQLSTATE[HY000]: General error: 20105 not supported: function or operator 'json_depth'

#### #9392 [PDO / cast:matrix] CAST '-3.14' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value -3.14

#### #9393 [PDO / cast:matrix] CONVERT '-3.14',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value -3.14

#### #9394 [PDO / cast:matrix] CAST '2026-06-23' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #9395 [PDO / cast:matrix] CONVERT '2026-06-23',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23

#### #9396 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #9397 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 2026-06-23 10:20:30

#### #9398 [PDO / cast:matrix] CAST '10:20:30' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10:20:30

#### #9399 [PDO / cast:matrix] CONVERT '10:20:30',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value 10:20:30

#### #9400 [PDO / cast:matrix] CAST 'abc' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value abc

#### #9401 [PDO / cast:matrix] CONVERT 'abc',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value abc

#### #9406 [PDO / cast:matrix] CAST '[1,2,3]' AS SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value [1,2,3]

#### #9407 [PDO / cast:matrix] CONVERT '[1,2,3]',SIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to int, bad value [1,2,3]

#### #9412 [PDO / cast:matrix] CAST '-3.14' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value -3.14

#### #9413 [PDO / cast:matrix] CONVERT '-3.14',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value -3.14

#### #9414 [PDO / cast:matrix] CAST '2026-06-23' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 2026-06-23

#### #9415 [PDO / cast:matrix] CONVERT '2026-06-23',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 2026-06-23

#### #9416 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 2026-06-23 10:20:30

#### #9417 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 2026-06-23 10:20:30

#### #9418 [PDO / cast:matrix] CAST '10:20:30' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 10:20:30

#### #9419 [PDO / cast:matrix] CONVERT '10:20:30',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value 10:20:30

#### #9420 [PDO / cast:matrix] CAST 'abc' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value abc

#### #9421 [PDO / cast:matrix] CONVERT 'abc',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value abc

#### #9426 [PDO / cast:matrix] CAST '[1,2,3]' AS UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value [1,2,3]

#### #9427 [PDO / cast:matrix] CONVERT '[1,2,3]',UNSIGNED

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument cast to uint64, bad value [1,2,3]

#### #9431 [PDO / cast:matrix] CONVERT '42',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '42' from VARCHAR type to CHAR type. Src length 2 is larger than Dest length 1

#### #9433 [PDO / cast:matrix] CONVERT '-3.14',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '-3.14' from VARCHAR type to CHAR type. Src length 5 is larger than Dest length 1

#### #9435 [PDO / cast:matrix] CONVERT '2026-06-23',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23' from VARCHAR type to CHAR type. Src length 10 is larger than Dest length 1

#### #9437 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23 10:20:30' from VARCHAR type to CHAR type. Src length 19 is larger than Dest length 1

#### #9439 [PDO / cast:matrix] CONVERT '10:20:30',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '10:20:30' from VARCHAR type to CHAR type. Src length 8 is larger than Dest length 1

#### #9441 [PDO / cast:matrix] CONVERT 'abc',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast 'abc' from VARCHAR type to CHAR type. Src length 3 is larger than Dest length 1

#### #9443 [PDO / cast:matrix] CONVERT 255,CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '255' from BIGINT type to CHAR type. 255 is larger than Dest length 1

#### #9445 [PDO / cast:matrix] CONVERT 3.14159,CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '314159' from DECIMAL64 type to CHAR type. 3.14159 is larger than Dest length 1

#### #9447 [PDO / cast:matrix] CONVERT '[1,2,3]',CHAR

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '1,2,3' from VARCHAR type to CHAR type. Src length 7 is larger than Dest length 1

#### #9454 [PDO / cast:matrix] CAST '2026-06-23' AS CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23' from VARCHAR type to CHAR type. Src length 10 is larger than Dest length 5

#### #9455 [PDO / cast:matrix] CONVERT '2026-06-23',CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23' from VARCHAR type to CHAR type. Src length 10 is larger than Dest length 5

#### #9456 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23 10:20:30' from VARCHAR type to CHAR type. Src length 19 is larger than Dest length 5

#### #9457 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23 10:20:30' from VARCHAR type to CHAR type. Src length 19 is larger than Dest length 5

#### #9458 [PDO / cast:matrix] CAST '10:20:30' AS CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '10:20:30' from VARCHAR type to CHAR type. Src length 8 is larger than Dest length 5

#### #9459 [PDO / cast:matrix] CONVERT '10:20:30',CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '10:20:30' from VARCHAR type to CHAR type. Src length 8 is larger than Dest length 5

#### #9464 [PDO / cast:matrix] CAST 3.14159 AS CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '314159' from DECIMAL64 type to CHAR type. 3.14159 is larger than Dest length 5

#### #9465 [PDO / cast:matrix] CONVERT 3.14159,CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '314159' from DECIMAL64 type to CHAR type. 3.14159 is larger than Dest length 5

#### #9466 [PDO / cast:matrix] CAST '[1,2,3]' AS CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '1,2,3' from VARCHAR type to CHAR type. Src length 7 is larger than Dest length 5

#### #9467 [PDO / cast:matrix] CONVERT '[1,2,3]',CHAR(5)

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '1,2,3' from VARCHAR type to CHAR type. Src length 7 is larger than Dest length 5

#### #9474 [PDO / cast:matrix] CAST '2026-06-23' AS DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 beyond the range, can't be converted to Decimal64(10,2).

#### #9475 [PDO / cast:matrix] CONVERT '2026-06-23',DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 beyond the range, can't be converted to Decimal64(10,2).

#### #9476 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 10:20:30 beyond the range, can't be converted to Decimal64(10,2).

#### #9477 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 10:20:30 beyond the range, can't be converted to Decimal64(10,2).

#### #9478 [PDO / cast:matrix] CAST '10:20:30' AS DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 10:20:30 beyond the range, can't be converted to Decimal64(10,2).

#### #9479 [PDO / cast:matrix] CONVERT '10:20:30',DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 10:20:30 beyond the range, can't be converted to Decimal64(10,2).

#### #9480 [PDO / cast:matrix] CAST 'abc' AS DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: abc beyond the range, can't be converted to Decimal64(10,2).

#### #9481 [PDO / cast:matrix] CONVERT 'abc',DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: abc beyond the range, can't be converted to Decimal64(10,2).

#### #9486 [PDO / cast:matrix] CAST '[1,2,3]' AS DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: [1,2,3] beyond the range, can't be converted to Decimal64(10,2).

#### #9487 [PDO / cast:matrix] CONVERT '[1,2,3]',DECIMAL(10,2)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: [1,2,3] beyond the range, can't be converted to Decimal64(10,2).

#### #9494 [PDO / cast:matrix] CAST '2026-06-23' AS DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 beyond the range, can't be converted to Decimal128(20,4).

#### #9495 [PDO / cast:matrix] CONVERT '2026-06-23',DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 beyond the range, can't be converted to Decimal128(20,4).

#### #9496 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 10:20:30 beyond the range, can't be converted to Decimal128(20,4).

#### #9497 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 2026-06-23 10:20:30 beyond the range, can't be converted to Decimal128(20,4).

#### #9498 [PDO / cast:matrix] CAST '10:20:30' AS DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 10:20:30 beyond the range, can't be converted to Decimal128(20,4).

#### #9499 [PDO / cast:matrix] CONVERT '10:20:30',DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: 10:20:30 beyond the range, can't be converted to Decimal128(20,4).

#### #9500 [PDO / cast:matrix] CAST 'abc' AS DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: abc beyond the range, can't be converted to Decimal128(20,4).

#### #9501 [PDO / cast:matrix] CONVERT 'abc',DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: abc beyond the range, can't be converted to Decimal128(20,4).

#### #9506 [PDO / cast:matrix] CAST '[1,2,3]' AS DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: [1,2,3] beyond the range, can't be converted to Decimal128(20,4).

#### #9507 [PDO / cast:matrix] CONVERT '[1,2,3]',DECIMAL(20,4)

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: [1,2,3] beyond the range, can't be converted to Decimal128(20,4).

#### #9522 [PDO / cast:matrix] CAST 255 AS DATE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [BIGINT DATE]

#### #9523 [PDO / cast:matrix] CONVERT 255,DATE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [BIGINT DATE]

#### #9524 [PDO / cast:matrix] CAST 3.14159 AS DATE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [DECIMAL64 DATE]

#### #9525 [PDO / cast:matrix] CONVERT 3.14159,DATE

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [DECIMAL64 DATE]

#### #9530 [PDO / cast:matrix] CAST '42' AS DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value 42

#### #9531 [PDO / cast:matrix] CONVERT '42',DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value 42

#### #9532 [PDO / cast:matrix] CAST '-3.14' AS DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value -3.14

#### #9533 [PDO / cast:matrix] CONVERT '-3.14',DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value -3.14

#### #9538 [PDO / cast:matrix] CAST '10:20:30' AS DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value 10:20:30

#### #9539 [PDO / cast:matrix] CONVERT '10:20:30',DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value 10:20:30

#### #9540 [PDO / cast:matrix] CAST 'abc' AS DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value abc

#### #9541 [PDO / cast:matrix] CONVERT 'abc',DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value abc

#### #9542 [PDO / cast:matrix] CAST 255 AS DATETIME

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [BIGINT DATETIME]

#### #9543 [PDO / cast:matrix] CONVERT 255,DATETIME

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [BIGINT DATETIME]

#### #9544 [PDO / cast:matrix] CAST 3.14159 AS DATETIME

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [DECIMAL64 DATETIME]

#### #9545 [PDO / cast:matrix] CONVERT 3.14159,DATETIME

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [DECIMAL64 DATETIME]

#### #9546 [PDO / cast:matrix] CAST '[1,2,3]' AS DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value [1,2,3]

#### #9547 [PDO / cast:matrix] CONVERT '[1,2,3]',DATETIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid datetime value [1,2,3]

#### #9554 [PDO / cast:matrix] CAST '2026-06-23' AS TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value 2026-06-23

#### #9555 [PDO / cast:matrix] CONVERT '2026-06-23',TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value 2026-06-23

#### #9560 [PDO / cast:matrix] CAST 'abc' AS TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value abc

#### #9561 [PDO / cast:matrix] CONVERT 'abc',TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value abc

#### #9566 [PDO / cast:matrix] CAST '[1,2,3]' AS TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value [1,2,3]

#### #9567 [PDO / cast:matrix] CONVERT '[1,2,3]',TIME

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: invalid time value [1,2,3]

#### #9611 [PDO / cast:matrix] CONVERT '42',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '42' from VARCHAR type to BINARY type. Src length 2 is larger than Dest length 1

#### #9613 [PDO / cast:matrix] CONVERT '-3.14',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '-3.14' from VARCHAR type to BINARY type. Src length 5 is larger than Dest length 1

#### #9615 [PDO / cast:matrix] CONVERT '2026-06-23',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23' from VARCHAR type to BINARY type. Src length 10 is larger than Dest length 1

#### #9617 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '2026-06-23 10:20:30' from VARCHAR type to BINARY type. Src length 19 is larger than Dest length 1

#### #9619 [PDO / cast:matrix] CONVERT '10:20:30',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '10:20:30' from VARCHAR type to BINARY type. Src length 8 is larger than Dest length 1

#### #9621 [PDO / cast:matrix] CONVERT 'abc',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast 'abc' from VARCHAR type to BINARY type. Src length 3 is larger than Dest length 1

#### #9623 [PDO / cast:matrix] CONVERT 255,BINARY

- **Code:** `1406`
- **Message:** SQLSTATE[HY000]: General error: 1406 data truncated: data type Signed, truncated for binary/varbinary

#### #9625 [PDO / cast:matrix] CONVERT 3.14159,BINARY

- **Code:** `1406`
- **Message:** SQLSTATE[HY000]: General error: 1406 data truncated: data type Decimal64, truncated for binary/varbinary

#### #9627 [PDO / cast:matrix] CONVERT '[1,2,3]',BINARY

- **Code:** `20101`
- **Message:** SQLSTATE[HY000]: General error: 20101 internal error: Can't cast '1,2,3' from VARCHAR type to BINARY type. Src length 7 is larger than Dest length 1

#### #9631 [PDO / cast:matrix] CONVERT '42',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near " JSON) AS v";

#### #9633 [PDO / cast:matrix] CONVERT '-3.14',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 56 near " JSON) AS v";

#### #9634 [PDO / cast:matrix] CAST '2026-06-23' AS JSON

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: json text 2026-06-23

#### #9635 [PDO / cast:matrix] CONVERT '2026-06-23',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 61 near " JSON) AS v";

#### #9636 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS JSON

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: json text 2026-06-23 10:20:30

#### #9637 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 70 near " JSON) AS v";

#### #9638 [PDO / cast:matrix] CAST '10:20:30' AS JSON

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: json text 10:20:30

#### #9639 [PDO / cast:matrix] CONVERT '10:20:30',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 59 near " JSON) AS v";

#### #9640 [PDO / cast:matrix] CAST 'abc' AS JSON

- **Code:** `20301`
- **Message:** SQLSTATE[HY000]: General error: 20301 invalid input: json text abc

#### #9641 [PDO / cast:matrix] CONVERT 'abc',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 54 near " JSON) AS v";

#### #9642 [PDO / cast:matrix] CAST 255 AS JSON

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [BIGINT JSON]

#### #9643 [PDO / cast:matrix] CONVERT 255,JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 52 near " JSON) AS v";

#### #9644 [PDO / cast:matrix] CAST 3.14159 AS JSON

- **Code:** `20203`
- **Message:** SQLSTATE[HY000]: General error: 20203 invalid argument operator cast, bad value [DECIMAL64 JSON]

#### #9645 [PDO / cast:matrix] CONVERT 3.14159,JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 56 near " JSON) AS v";

#### #9647 [PDO / cast:matrix] CONVERT '[1,2,3]',JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 58 near " JSON) AS v";

#### #9649 [PDO / cast:matrix] CONVERT NULL,JSON

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near " JSON) AS v";

#### #9650 [PDO / cast:matrix] CAST '42' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near " NCHAR) AS v";

#### #9651 [PDO / cast:matrix] CONVERT '42',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 54 near " NCHAR) AS v";

#### #9652 [PDO / cast:matrix] CAST '-3.14' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 56 near " NCHAR) AS v";

#### #9653 [PDO / cast:matrix] CONVERT '-3.14',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 57 near " NCHAR) AS v";

#### #9654 [PDO / cast:matrix] CAST '2026-06-23' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 61 near " NCHAR) AS v";

#### #9655 [PDO / cast:matrix] CONVERT '2026-06-23',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 62 near " NCHAR) AS v";

#### #9656 [PDO / cast:matrix] CAST '2026-06-23 10:20:30' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 70 near " NCHAR) AS v";

#### #9657 [PDO / cast:matrix] CONVERT '2026-06-23 10:20:30',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 71 near " NCHAR) AS v";

#### #9658 [PDO / cast:matrix] CAST '10:20:30' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 59 near " NCHAR) AS v";

#### #9659 [PDO / cast:matrix] CONVERT '10:20:30',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 60 near " NCHAR) AS v";

#### #9660 [PDO / cast:matrix] CAST 'abc' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 54 near " NCHAR) AS v";

#### #9661 [PDO / cast:matrix] CONVERT 'abc',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 55 near " NCHAR) AS v";

#### #9662 [PDO / cast:matrix] CAST 255 AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 52 near " NCHAR) AS v";

#### #9663 [PDO / cast:matrix] CONVERT 255,NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near " NCHAR) AS v";

#### #9664 [PDO / cast:matrix] CAST 3.14159 AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 56 near " NCHAR) AS v";

#### #9665 [PDO / cast:matrix] CONVERT 3.14159,NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 57 near " NCHAR) AS v";

#### #9666 [PDO / cast:matrix] CAST '[1,2,3]' AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 58 near " NCHAR) AS v";

#### #9667 [PDO / cast:matrix] CONVERT '[1,2,3]',NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 59 near " NCHAR) AS v";

#### #9668 [PDO / cast:matrix] CAST NULL AS NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 53 near " NCHAR) AS v";

#### #9669 [PDO / cast:matrix] CONVERT NULL,NCHAR

- **Code:** `1064`
- **Message:** SQLSTATE[HY000]: General error: 1064 SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 54 near " NCHAR) AS v";

