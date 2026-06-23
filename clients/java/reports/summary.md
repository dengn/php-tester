# MatrixOne Java ORM Compatibility — Summary

- **Engine:** 8.0.30-MatrixOne-v4.0.0-rc3
- **Total scenarios run:** 10361
- **Pass:** 9260 (89.4%)
- **Fail:** 1100 (10.6%)
- **Skip:** 1 (0.0%)

## Pass / fail by framework

| Framework | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|
| flyway | 7 | 2 | 0 | 77.8% |
| hibernate | 211 | 4 | 0 | 98.1% |
| jdbc | 4079 | 240 | 1 | 94.4% |
| jdbi | 24 | 0 | 0 | 100.0% |
| jooq | 574 | 13 | 0 | 97.8% |
| liquibase | 0 | 9 | 0 | 0.0% |
| mega | 3968 | 824 | 0 | 82.8% |
| mybatis | 369 | 8 | 0 | 97.9% |
| springdata | 28 | 0 | 0 | 100.0% |

## Pass / fail by category

| Category | Pass | Fail | Skip |
|---|--:|--:|--:|
| agg_type | 170 | 5 | 0 |
| aggregate | 26 | 1 | 0 |
| batch | 13 | 0 | 0 |
| binding | 35 | 0 | 0 |
| binding_typed | 40 | 0 | 0 |
| bootstrap | 1 | 0 | 0 |
| bulk_batch | 9 | 0 | 0 |
| bulk_lob | 7 | 0 | 0 |
| bulk_multi | 2 | 0 | 0 |
| bulk_stream | 2 | 1 | 0 |
| bulk_wide | 3 | 0 | 0 |
| cast:matrix | 148 | 132 | 0 |
| charset | 9 | 1 | 0 |
| check_constraint | 3 | 0 | 0 |
| collation | 2 | 4 | 0 |
| column | 243 | 9 | 0 |
| concurrency | 17 | 0 | 0 |
| constraint | 6 | 3 | 0 |
| criteria | 6 | 0 | 0 |
| criteria_type | 17 | 0 | 0 |
| crud | 18 | 0 | 0 |
| cte | 6 | 0 | 0 |
| cte_type | 34 | 1 | 0 |
| ddl | 20 | 0 | 0 |
| decimal_grid | 81 | 8 | 1 |
| derived_query | 9 | 0 | 0 |
| dml | 11 | 0 | 0 |
| dml_type | 279 | 7 | 0 |
| dynamicsql | 3 | 0 | 0 |
| embeddable | 1 | 0 | 0 |
| enumset | 9 | 1 | 0 |
| error | 13 | 0 | 0 |
| event | 0 | 2 | 0 |
| expr_arith | 46 | 2 | 0 |
| expr_cast | 17 | 2 | 0 |
| expr_cmp | 42 | 0 | 0 |
| expr_interval | 43 | 0 | 0 |
| expr_json | 12 | 0 | 0 |
| expr_string | 12 | 0 | 0 |
| fk_action | 7 | 0 | 0 |
| fluent | 10 | 0 | 0 |
| fn:date-format | 20 | 0 | 0 |
| fn:date-var | 84 | 0 | 0 |
| fn:numeric-var | 196 | 28 | 0 |
| fn:string-var | 232 | 0 | 0 |
| fulltext | 8 | 0 | 0 |
| function | 201 | 19 | 0 |
| function_aliased | 201 | 19 | 0 |
| function_cte | 201 | 19 | 0 |
| function_subquery | 201 | 19 | 0 |
| function_where | 201 | 19 | 0 |
| generated | 1 | 6 | 0 |
| generatedvalue | 4 | 0 | 0 |
| grid:arith | 1152 | 384 | 0 |
| grid:bitwise | 250 | 0 | 0 |
| grid:compare | 1512 | 280 | 0 |
| grid:logical | 80 | 0 | 0 |
| grouping | 10 | 0 | 0 |
| index | 7 | 1 | 0 |
| inheritance | 3 | 0 | 0 |
| javatime | 19 | 5 | 0 |
| join | 12 | 0 | 0 |
| join_type | 34 | 1 | 0 |
| jpql | 27 | 1 | 0 |
| jpql_type | 51 | 0 | 0 |
| json_paths | 18 | 5 | 0 |
| lifecycle | 2 | 0 | 0 |
| load | 10 | 2 | 0 |
| mapper | 7 | 0 | 0 |
| metadata | 14 | 4 | 0 |
| migration | 7 | 11 | 0 |
| modifying | 1 | 0 | 0 |
| native | 5 | 0 | 0 |
| null_logic | 22 | 0 | 0 |
| numeric_coercion | 9 | 1 | 0 |
| numeric_overflow | 5 | 0 | 0 |
| pageable | 3 | 0 | 0 |
| pagination | 2 | 0 | 0 |
| partition | 6 | 0 | 0 |
| plainsql | 4 | 0 | 0 |
| procedure | 0 | 5 | 0 |
| ptype | 293 | 7 | 0 |
| query_annotation | 5 | 0 | 0 |
| range_type | 68 | 2 | 0 |
| relationship | 7 | 0 | 0 |
| schemagen | 5 | 2 | 0 |
| semantics | 1 | 10 | 0 |
| set_type | 34 | 1 | 0 |
| setop | 10 | 0 | 0 |
| specification | 3 | 0 | 0 |
| sqlobject | 3 | 0 | 0 |
| sub_type | 34 | 1 | 0 |
| subquery | 12 | 0 | 0 |
| temporal_precision | 42 | 0 | 0 |
| transaction | 11 | 3 | 0 |
| trigger | 0 | 3 | 0 |
| type | 1937 | 47 | 0 |
| typehandler | 5 | 0 | 0 |
| uuid | 3 | 1 | 0 |
| vector | 35 | 2 | 0 |
| versioning | 2 | 0 | 0 |
| view | 4 | 3 | 0 |
| win_type | 102 | 3 | 0 |
| window | 22 | 0 | 0 |
| workload:analytics | 6 | 0 | 0 |
| workload:filter | 144 | 0 | 0 |
| workload:group | 48 | 0 | 0 |
| workload:join | 18 | 0 | 0 |
| workload:sort | 48 | 0 | 0 |
| workload:subquery | 6 | 0 | 0 |
| workload:window | 24 | 0 | 0 |
| workload_cms | 9 | 0 | 0 |
| workload_ecommerce | 15 | 0 | 0 |
| workload_geo | 6 | 5 | 0 |
| workload_ledger | 7 | 0 | 0 |
| workload_olap | 11 | 2 | 0 |
| workload_saas | 7 | 0 | 0 |
| workload_social | 10 | 0 | 0 |
| workload_timeseries | 9 | 0 | 0 |

## Failures by error signature (error_code)

| Error code | Count | Meaning |
|---|--:|---|
| 20203 | 720 | stricter argument / type validation than MySQL |
| 1064 | 134 | SQL parser / syntax not supported |
| 20105 | 88 | function / operator not implemented |
| 20301 | 62 |  |
| 20101 | 31 | internal 'not implemented yet' (e.g. ROLLBACK TO SAVEPOINT) |
| BEHAVIOR | 24 | ran OK but result differs from MySQL semantics |
| 1690 | 22 | out-of-range / overflow |
| 1149 | 12 |  |
| S1009 | 5 |  |
| 1406 | 2 |  |

## Top distinct failure messages

| Count | Code | Message (normalized) |
|--:|---|---|
| 112 | 20203 | invalid argument cast to int, bad value #-06-23 |
| 112 | 20203 | invalid argument cast to int, bad value #-06-23 10:20:30 |
| 111 | 20203 | invalid argument cast to int, bad value 10abc |
| 110 | 20203 | invalid argument cast to int, bad value hello |
| 84 | 20105 | not supported: function or operator '_' |
| 41 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 89 near " PRECISIO... |
| 36 | 20203 | invalid argument operator -, bad value [VARCHAR VARCHAR] |
| 36 | 20203 | invalid argument operator *, bad value [VARCHAR VARCHAR] |
| 36 | 20203 | invalid argument operator /, bad value [VARCHAR VARCHAR] |
| 36 | 20203 | invalid argument operator div, bad value [VARCHAR VARCHAR] |
| 36 | 20203 | invalid argument operator %, bad value [VARCHAR VARCHAR] |
| 22 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 87 near " PRECISIO... |
| 15 | 20203 | invalid argument parse timestamp, bad value 11:22:33 |
| 12 | 1149 | SQL syntax error: column "tables.TABLE_TYPE" must appear in the GROUP BY clause or be used in an aggregate function |
| 8 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 64 near " PRECISIO... |
| 6 | 20203 | invalid argument operator div, bad value [VARCHAR ANY] |
| 6 | 20203 | invalid argument operator div, bad value [ANY VARCHAR] |
| 5 | 20203 | invalid argument function rand, bad value [BIGINT] |
| 5 | S1009 | Conversion not supported for type java.time.Instant |
| 5 | 20301 | invalid input: ambiguous column reference '_' |
| 5 | 1690 | Data truncation: data out of range: data type BIGINT, |
| 5 | 20203 | invalid argument function crc32, bad value [BIGINT] |
| 4 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 78 near " PRECISIO... |
| 3 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 14 near " TRIGGER ... |
| 3 | 1690 | Data truncation: data out of range: data type int64, (# + #) |
| 3 | 1690 | Data truncation: data out of range: data type int64, (# * #) |
| 3 | 20203 | invalid argument function crc32, bad value [DECIMAL64] |
| 3 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 25 near " NCHAR) A... |
| 3 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 26 near " NCHAR) A... |
| 2 | 20101 | internal error: Can'_'#' from BIGINT type to CHAR type. # is larger than Dest length 1 |
| 2 | 20101 | internal error: savepoint has not been implemented yet. please rollback the transaction. |
| 2 | 20301 | invalid input: unsupported Prepare flag '_' |
| 2 | BEHAVIOR | '_' = '_' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| 2 | 20301 | invalid input: cannot insert/update/delete from view |
| 2 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 38 near " BEGIN SE... |
| 2 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 64 near " BEGIN SE... |
| 2 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 12 near " EVENT ev... |
| 2 | 20301 | invalid input: Decimal128 Sub overflow: 12.50-# |
| 2 | 20301 | invalid input: Decimal128 Sub overflow: 0.#-# |
| 2 | 20203 | invalid argument cast to int, bad value -3.14 |

## BEHAVIOR mismatches (ran OK but differs from MySQL)

| Framework | Name | Detail |
|---|---|---|
| jdbc | check_constraint_enforced | CHECK constraint enforcement \| MySQL expects [reject -5 (MySQL)] but MatrixOne returned [accepted -5 (MatrixOne CHECK is a no-op)] |
| jdbc | collate_ci_override_honored | explicit COLLATE _ci \| MySQL expects [1 (honored)] but MatrixOne returned [0 (ignored)] |
| jdbc | column_collation/latin1_swedish_ci | case-insensitive collation latin1_swedish_ci \| MySQL expects [2 rows match 'hello' (MySQL _ci)] but MatrixOne returned [1 (MatrixOne ignores _ci)] |
| jdbc | column_collation/utf8mb4_0900_ai_ci | case-insensitive collation utf8mb4_0900_ai_ci \| MySQL expects [2 rows match 'hello' (MySQL _ci)] but MatrixOne returned [1 (MatrixOne ignores _ci)] |
| jdbc | column_collation/utf8mb4_general_ci | case-insensitive collation utf8mb4_general_ci \| MySQL expects [2 rows match 'hello' (MySQL _ci)] but MatrixOne returned [1 (MatrixOne ignores _ci)] |
| jdbc | column_collation/utf8mb4_unicode_ci | case-insensitive collation utf8mb4_unicode_ci \| MySQL expects [2 rows match 'hello' (MySQL _ci)] but MatrixOne returned [1 (MatrixOne ignores _ci)] |
| jdbc | getImportedKeys | DatabaseMetaData.getImportedKeys \| MySQL expects [>=1 FK row (MySQL)] but MatrixOne returned [0 rows (MatrixOne exposes no FK metadata)] |
| jdbc | last_insert_id_multirow | LAST_INSERT_ID after multi-row insert \| MySQL expects [1 (first id, MySQL)] but MatrixOne returned [3 (last id, MatrixOne)] |
| jdbc | like_case_insensitive | 'abc' LIKE 'ABC' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | multi_table_delete_join | multi-table DELETE..JOIN remaining rows \| MySQL expects [1 (MySQL deletes only matched)] but MatrixOne returned [0 (MatrixOne emptied table)] |
| jdbc | overflow_boundary/d10_2 | out-of-range DECIMAL(10,2) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 999999999 (MatrixOne)] |
| jdbc | overflow_boundary/d10_4 | out-of-range DECIMAL(10,4) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 9999999 (MatrixOne)] |
| jdbc | overflow_boundary/d18_0 | out-of-range DECIMAL(18,0) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 9999999999999999999 (MatrixOne)] |
| jdbc | overflow_boundary/d18_2 | out-of-range DECIMAL(18,2) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 99999999999999999 (MatrixOne)] |
| jdbc | overflow_boundary/d18_6 | out-of-range DECIMAL(18,6) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 9999999999999 (MatrixOne)] |
| jdbc | overflow_boundary/d20_2 | out-of-range DECIMAL(20,2) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 9999999999999999999 (MatrixOne)] |
| jdbc | overflow_boundary/d5_0 | out-of-range DECIMAL(5,0) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 999999 (MatrixOne)] |
| jdbc | overflow_boundary/d5_2 | out-of-range DECIMAL(5,2) \| MySQL expects [reject overflow (MySQL)] but MatrixOne returned [accepted 9999 (MatrixOne)] |
| jdbc | pipe_pipe_is_or | 1 \|\| 0 \| MySQL expects [1 (MySQL logical OR)] but MatrixOne returned [10 (MatrixOne concat)] |
| jdbc | rollup_alias_resolution_finding | GROUP BY alias WITH ROLLUP \| MySQL expects [alias 'g' resolvable in GROUP BY (MySQL)] but MatrixOne returned [column g does not exist (MatrixOne)] |
| jdbc | string_equality_accent_insensitive | 'café' = 'cafe' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | string_equality_case_insensitive | 'abc' = 'ABC' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | trailing_space_equality | 'a ' = 'a' \| MySQL expects [1 (MySQL PAD SPACE)] but MatrixOne returned [0] |
| jdbc | unique_case_sensitive_collation | UNIQUE case-sensitivity \| MySQL expects [duplicate rejected (MySQL _ci)] but MatrixOne returned [both rows accepted (MatrixOne _bin)] |

