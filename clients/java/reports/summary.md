# MatrixOne Java ORM Compatibility — Summary

- **Engine:** 8.0.30-MatrixOne-v4.0.0-rc3
- **Total scenarios run:** 5033
- **Pass:** 4823 (95.8%)
- **Fail:** 210 (4.2%)
- **Skip:** 0 (0.0%)

## Pass / fail by framework

| Framework | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|
| hibernate | 211 | 4 | 0 | 98.1% |
| jdbc | 3669 | 185 | 0 | 95.2% |
| jooq | 574 | 13 | 0 | 97.8% |
| mybatis | 369 | 8 | 0 | 97.9% |

## Pass / fail by category

| Category | Pass | Fail | Skip |
|---|--:|--:|--:|
| agg_type | 170 | 5 | 0 |
| aggregate | 26 | 1 | 0 |
| batch | 10 | 0 | 0 |
| binding | 35 | 0 | 0 |
| binding_typed | 40 | 0 | 0 |
| column | 243 | 9 | 0 |
| constraint | 6 | 3 | 0 |
| criteria | 6 | 0 | 0 |
| criteria_type | 17 | 0 | 0 |
| crud | 13 | 0 | 0 |
| cte | 6 | 0 | 0 |
| cte_type | 34 | 1 | 0 |
| ddl | 20 | 0 | 0 |
| dml | 11 | 0 | 0 |
| dml_type | 279 | 7 | 0 |
| dynamicsql | 3 | 0 | 0 |
| embeddable | 1 | 0 | 0 |
| expr_arith | 46 | 2 | 0 |
| expr_cast | 17 | 2 | 0 |
| expr_cmp | 42 | 0 | 0 |
| expr_interval | 43 | 0 | 0 |
| expr_json | 12 | 0 | 0 |
| expr_string | 12 | 0 | 0 |
| function | 201 | 19 | 0 |
| function_aliased | 201 | 19 | 0 |
| function_cte | 201 | 19 | 0 |
| function_subquery | 201 | 19 | 0 |
| function_where | 201 | 19 | 0 |
| generatedvalue | 4 | 0 | 0 |
| grouping | 10 | 0 | 0 |
| index | 7 | 1 | 0 |
| inheritance | 3 | 0 | 0 |
| join | 12 | 0 | 0 |
| join_type | 34 | 1 | 0 |
| jpql | 27 | 1 | 0 |
| jpql_type | 51 | 0 | 0 |
| lifecycle | 1 | 0 | 0 |
| load | 10 | 2 | 0 |
| mapper | 7 | 0 | 0 |
| metadata | 14 | 4 | 0 |
| native | 5 | 0 | 0 |
| pagination | 2 | 0 | 0 |
| plainsql | 4 | 0 | 0 |
| ptype | 293 | 7 | 0 |
| range_type | 68 | 2 | 0 |
| relationship | 7 | 0 | 0 |
| schemagen | 5 | 2 | 0 |
| semantics | 1 | 10 | 0 |
| set_type | 34 | 1 | 0 |
| setop | 10 | 0 | 0 |
| sub_type | 34 | 1 | 0 |
| subquery | 12 | 0 | 0 |
| transaction | 11 | 3 | 0 |
| type | 1929 | 47 | 0 |
| typehandler | 5 | 0 | 0 |
| versioning | 2 | 0 | 0 |
| win_type | 102 | 3 | 0 |
| window | 22 | 0 | 0 |

## Failures by error signature (error_code)

| Error code | Count | Meaning |
|---|--:|---|
| 1064 | 83 | SQL parser / syntax not supported |
| 20105 | 80 | function / operator not implemented |
| 20203 | 25 | stricter argument / type validation than MySQL |
| BEHAVIOR | 11 | ran OK but result differs from MySQL semantics |
| 20101 | 5 | internal 'not implemented yet' (e.g. ROLLBACK TO SAVEPOINT) |
| 20301 | 3 |  |
| 1149 | 3 |  |

## Top distinct failure messages

| Count | Code | Message (normalized) |
|--:|---|---|
| 76 | 20105 | not supported: function or operator '_' |
| 41 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 89 near " PRECISIO... |
| 22 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 87 near " PRECISIO... |
| 15 | 20203 | invalid argument parse timestamp, bad value 11:22:33 |
| 8 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 64 near " PRECISIO... |
| 5 | 20203 | invalid argument function rand, bad value [BIGINT] |
| 4 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 78 near " PRECISIO... |
| 3 | 1149 | SQL syntax error: column "tables.TABLE_TYPE" must appear in the GROUP BY clause or be used in an aggregate function |
| 2 | 20101 | internal error: savepoint has not been implemented yet. please rollback the transaction. |
| 2 | BEHAVIOR | '_' = '_' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| 1 | 20203 | invalid argument function coalesce, bad value [JSON VARCHAR] |
| 1 | 20105 | not supported: JSON column '_' cannot have default value |
| 1 | 20203 | invalid argument operator cast, bad value [VECF32 VARCHAR] |
| 1 | 20203 | invalid argument operator cast, bad value [VECF64 VARCHAR] |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 70 near " PRECISIO... |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 79 near " PRECISIO... |
| 1 | 20105 | not supported: ENUM column '_' cannot be in primary key |
| 1 | 20105 | not supported: SET column '_' cannot be in primary key |
| 1 | 20105 | not supported: SET column '_' cannot be in unique index |
| 1 | 20301 | invalid input: Decimal128 Sub overflow: 2.5-10 |
| 1 | 20301 | invalid input: Decimal128 Sub overflow: 0.1-# |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 23 near " NCHAR)"; |
| 1 | 20101 | internal error: Can'_'#' from BIGINT type to CHAR type. # is larger than Dest length 1 |
| 1 | 20203 | invalid argument aggregate function json_objectagg, bad value [INT INT] |
| 1 | BEHAVIOR | UNIQUE case-sensitivity \| MySQL expects [duplicate rejected (MySQL _ci)] but MatrixOne returned [both rows accepted (MatrixOne _bin)] |
| 1 | BEHAVIOR | CHECK constraint enforcement \| MySQL expects [reject -5 (MySQL)] but MatrixOne returned [accepted -5 (MatrixOne CHECK is a no-op)] |
| 1 | 20101 | internal error: unsupported alter option in inplace mode: check (age >= 0) |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 80 near " USING BT... |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 51 near " LOCK IN ... |
| 1 | BEHAVIOR | DatabaseMetaData.getImportedKeys \| MySQL expects [>=1 FK row (MySQL)] but MatrixOne returned [0 rows (MatrixOne exposes no FK metadata)] |
| 1 | 1064 | SQL parser error: table "check_constraints" does not exist |
| 1 | 1064 | SQL parser error: table "collation_character_set_applicability" does not exist |
| 1 | 20301 | invalid input: unsupported Prepare flag '_' |
| 1 | BEHAVIOR | LAST_INSERT_ID after multi-row insert \| MySQL expects [1 (first id, MySQL)] but MatrixOne returned [3 (last id, MatrixOne)] |
| 1 | BEHAVIOR | '_' = '_' \| MySQL expects [1 (MySQL PAD SPACE)] but MatrixOne returned [0] |
| 1 | BEHAVIOR | '_' LIKE '_' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| 1 | BEHAVIOR | explicit COLLATE _ci \| MySQL expects [1 (honored)] but MatrixOne returned [0 (ignored)] |
| 1 | BEHAVIOR | multi-table DELETE..JOIN remaining rows \| MySQL expects [1 (MySQL deletes only matched)] but MatrixOne returned [0 (MatrixOne emptied table)] |
| 1 | BEHAVIOR | 1 \|\| 0 \| MySQL expects [1 (MySQL logical OR)] but MatrixOne returned [10 (MatrixOne concat)] |
| 1 | 1064 | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 52 near " PRECISIO... |

## BEHAVIOR mismatches (ran OK but differs from MySQL)

| Framework | Name | Detail |
|---|---|---|
| jdbc | check_constraint_enforced | CHECK constraint enforcement \| MySQL expects [reject -5 (MySQL)] but MatrixOne returned [accepted -5 (MatrixOne CHECK is a no-op)] |
| jdbc | collate_ci_override_honored | explicit COLLATE _ci \| MySQL expects [1 (honored)] but MatrixOne returned [0 (ignored)] |
| jdbc | getImportedKeys | DatabaseMetaData.getImportedKeys \| MySQL expects [>=1 FK row (MySQL)] but MatrixOne returned [0 rows (MatrixOne exposes no FK metadata)] |
| jdbc | last_insert_id_multirow | LAST_INSERT_ID after multi-row insert \| MySQL expects [1 (first id, MySQL)] but MatrixOne returned [3 (last id, MatrixOne)] |
| jdbc | like_case_insensitive | 'abc' LIKE 'ABC' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | multi_table_delete_join | multi-table DELETE..JOIN remaining rows \| MySQL expects [1 (MySQL deletes only matched)] but MatrixOne returned [0 (MatrixOne emptied table)] |
| jdbc | pipe_pipe_is_or | 1 \|\| 0 \| MySQL expects [1 (MySQL logical OR)] but MatrixOne returned [10 (MatrixOne concat)] |
| jdbc | string_equality_accent_insensitive | 'café' = 'cafe' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | string_equality_case_insensitive | 'abc' = 'ABC' \| MySQL expects [1 (MySQL _ci)] but MatrixOne returned [0] |
| jdbc | trailing_space_equality | 'a ' = 'a' \| MySQL expects [1 (MySQL PAD SPACE)] but MatrixOne returned [0] |
| jdbc | unique_case_sensitive_collation | UNIQUE case-sensitivity \| MySQL expects [duplicate rejected (MySQL _ci)] but MatrixOne returned [both rows accepted (MatrixOne _bin)] |

