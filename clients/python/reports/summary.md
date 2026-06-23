# Python ORM ⇆ MatrixOne Compatibility Summary

- **Engine:** `8.0.30-MatrixOne-v4.0.0-rc3`
- **Scenarios:** 10947 total — **9689 pass**, **1246 fail**, **12 skip**
- **Pass rate (excl. skip):** 88.6%

## Pass/fail by framework

| Framework | Total | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|--:|
| app | 1079 | 921 | 158 | 0 | 85.4% |
| django | 505 | 485 | 20 | 0 | 96.0% |
| mega | 4792 | 3968 | 824 | 0 | 82.8% |
| peewee | 307 | 286 | 21 | 0 | 93.2% |
| raw | 3404 | 3189 | 214 | 1 | 93.7% |
| sqlalchemy | 860 | 840 | 9 | 11 | 98.9% |

## Pass/fail by category

| Category | Total | Pass | Fail | Skip |
|---|--:|--:|--:|--:|
| aggregate | 18 | 18 | 0 | 0 |
| aggregate_distinct | 16 | 13 | 3 | 0 |
| alter_table | 20 | 17 | 3 | 0 |
| autoincrement | 7 | 7 | 0 | 0 |
| bind_insert | 17 | 17 | 0 | 0 |
| bind_update | 17 | 17 | 0 | 0 |
| bind_where | 17 | 16 | 0 | 1 |
| bit_ops | 8 | 0 | 8 | 0 |
| bit_roundtrip | 8 | 7 | 1 | 0 |
| bulk | 17 | 17 | 0 | 0 |
| bulk_executemany | 8 | 8 | 0 | 0 |
| bulk_multivalue | 8 | 8 | 0 | 0 |
| cast | 11 | 11 | 0 | 0 |
| cast:matrix | 280 | 148 | 132 | 0 |
| cast_from_col | 19 | 18 | 1 | 0 |
| charset | 6 | 5 | 1 | 0 |
| cms | 16 | 16 | 0 | 0 |
| collation | 14 | 9 | 5 | 0 |
| collation_case | 12 | 4 | 8 | 0 |
| collation_create | 12 | 11 | 1 | 0 |
| collation_orderby | 12 | 11 | 1 | 0 |
| column_modifier | 22 | 21 | 1 | 0 |
| compare_op | 224 | 210 | 14 | 0 |
| compare_pair | 20 | 20 | 0 | 0 |
| concurrency | 10 | 9 | 1 | 0 |
| conditional | 3 | 3 | 0 | 0 |
| constraint | 4 | 3 | 1 | 0 |
| convert | 11 | 8 | 3 | 0 |
| core_aggregate | 42 | 38 | 4 | 0 |
| core_dml_delete | 31 | 30 | 0 | 1 |
| core_dml_insert_more | 31 | 30 | 0 | 1 |
| core_dml_update | 31 | 30 | 0 | 1 |
| core_func | 72 | 70 | 2 | 0 |
| core_func_label | 36 | 35 | 1 | 0 |
| core_join_style | 5 | 5 | 0 | 0 |
| core_operator | 25 | 25 | 0 | 0 |
| core_query | 24 | 24 | 0 | 0 |
| core_select_variant | 20 | 19 | 1 | 0 |
| date_format_spec | 26 | 26 | 0 | 0 |
| db_function | 46 | 42 | 4 | 0 |
| ddl | 17 | 13 | 4 | 0 |
| ddl_check_constraint | 6 | 6 | 0 | 0 |
| ddl_fk_action | 8 | 5 | 3 | 0 |
| ddl_partition | 6 | 6 | 0 | 0 |
| ddl_routine | 4 | 0 | 4 | 0 |
| ddl_view | 5 | 4 | 1 | 0 |
| decimal_arith | 73 | 65 | 8 | 0 |
| decimal_declare | 73 | 73 | 0 | 0 |
| decimal_roundtrip | 73 | 66 | 7 | 0 |
| default_value | 32 | 30 | 2 | 0 |
| dialect_type_count | 31 | 31 | 0 | 0 |
| dialect_type_declare | 31 | 31 | 0 | 0 |
| dialect_type_distinct | 31 | 31 | 0 | 0 |
| dialect_type_groupby | 31 | 30 | 0 | 1 |
| dialect_type_null | 31 | 31 | 0 | 0 |
| dialect_type_orderby | 31 | 30 | 0 | 1 |
| dialect_type_roundtrip | 31 | 30 | 0 | 1 |
| dialect_type_update | 31 | 31 | 0 | 0 |
| dialect_type_where | 31 | 30 | 0 | 1 |
| dml | 13 | 11 | 2 | 0 |
| dml_delete_type | 32 | 30 | 2 | 0 |
| dml_insert_type | 32 | 30 | 2 | 0 |
| dml_update_type | 32 | 30 | 2 | 0 |
| ecommerce | 30 | 28 | 2 | 0 |
| enum_set | 5 | 4 | 1 | 0 |
| enum_set_edge | 15 | 3 | 12 | 0 |
| error_handling | 11 | 2 | 9 | 0 |
| expression | 30 | 30 | 0 | 0 |
| extract_unit | 15 | 15 | 0 | 0 |
| f_expression | 11 | 11 | 0 | 0 |
| field_agg | 72 | 67 | 5 | 0 |
| field_aggregate | 26 | 25 | 1 | 0 |
| field_count | 14 | 13 | 1 | 0 |
| field_create | 38 | 36 | 2 | 0 |
| field_crud_delete | 24 | 23 | 1 | 0 |
| field_crud_filter_exact | 24 | 23 | 1 | 0 |
| field_crud_save_modify | 24 | 23 | 1 | 0 |
| field_crud_update | 24 | 23 | 1 | 0 |
| field_delete | 14 | 13 | 1 | 0 |
| field_distinct | 26 | 25 | 1 | 0 |
| field_groupby | 14 | 13 | 1 | 0 |
| field_in | 14 | 13 | 1 | 0 |
| field_index | 26 | 23 | 3 | 0 |
| field_insert_many | 14 | 13 | 1 | 0 |
| field_limit | 14 | 13 | 1 | 0 |
| field_lookup | 110 | 110 | 0 | 0 |
| field_null | 38 | 36 | 2 | 0 |
| field_orderby | 26 | 25 | 1 | 0 |
| field_roundtrip | 38 | 36 | 2 | 0 |
| field_save | 14 | 13 | 1 | 0 |
| field_update | 14 | 13 | 1 | 0 |
| field_where | 14 | 13 | 1 | 0 |
| financial_ledger | 17 | 17 | 0 | 0 |
| fn:date-format | 20 | 20 | 0 | 0 |
| fn:date-var | 84 | 84 | 0 | 0 |
| fn:numeric-var | 224 | 196 | 28 | 0 |
| fn:string-var | 232 | 232 | 0 | 0 |
| fulltext | 12 | 11 | 1 | 0 |
| func_bare | 335 | 309 | 26 | 0 |
| func_col_date_groupby | 25 | 25 | 0 | 0 |
| func_col_date_orderby | 25 | 25 | 0 | 0 |
| func_col_date_select | 25 | 25 | 0 | 0 |
| func_col_date_where | 25 | 25 | 0 | 0 |
| func_col_math_groupby | 30 | 28 | 2 | 0 |
| func_col_math_orderby | 30 | 28 | 2 | 0 |
| func_col_math_select | 30 | 28 | 2 | 0 |
| func_col_math_where | 30 | 28 | 2 | 0 |
| func_col_str_groupby | 30 | 30 | 0 | 0 |
| func_col_str_orderby | 30 | 30 | 0 | 0 |
| func_col_str_select | 30 | 30 | 0 | 0 |
| func_col_str_where | 30 | 30 | 0 | 0 |
| func_groupby | 12 | 12 | 0 | 0 |
| func_having | 12 | 12 | 0 | 0 |
| func_orderby | 12 | 12 | 0 | 0 |
| func_select | 335 | 309 | 26 | 0 |
| func_where | 335 | 309 | 26 | 0 |
| generated_column | 10 | 10 | 0 | 0 |
| generated_column_update | 10 | 10 | 0 | 0 |
| geo | 12 | 9 | 3 | 0 |
| grid:arith | 1536 | 1152 | 384 | 0 |
| grid:bitwise | 250 | 250 | 0 | 0 |
| grid:compare | 1792 | 1512 | 280 | 0 |
| grid:logical | 80 | 80 | 0 | 0 |
| group_by_variant | 11 | 11 | 0 | 0 |
| index_variety | 7 | 7 | 0 | 0 |
| insert_variant | 6 | 6 | 0 | 0 |
| interval_unit | 14 | 14 | 0 | 0 |
| isolation_set | 4 | 4 | 0 | 0 |
| isolation_visibility | 4 | 4 | 0 | 0 |
| join_condition | 10 | 10 | 0 | 0 |
| join_matrix | 24 | 24 | 0 | 0 |
| json | 4 | 3 | 1 | 0 |
| json_arrow | 15 | 15 | 0 | 0 |
| json_op | 23 | 15 | 8 | 0 |
| json_op_deep | 22 | 13 | 9 | 0 |
| json_path_extract | 15 | 15 | 0 | 0 |
| json_where | 15 | 15 | 0 | 0 |
| large_value | 8 | 8 | 0 | 0 |
| like_pattern | 17 | 17 | 0 | 0 |
| literal | 22 | 21 | 1 | 0 |
| multitenant | 16 | 15 | 1 | 0 |
| nesting | 15 | 10 | 5 | 0 |
| null_aggregate | 32 | 30 | 2 | 0 |
| null_ordering | 32 | 30 | 2 | 0 |
| null_propagation | 16 | 14 | 2 | 0 |
| num_function | 10 | 8 | 2 | 0 |
| numeric_overflow | 22 | 10 | 12 | 0 |
| olap | 24 | 23 | 1 | 0 |
| operator | 90 | 90 | 0 | 0 |
| order_variant | 12 | 12 | 0 | 0 |
| orm | 13 | 13 | 0 | 0 |
| orm_advanced | 4 | 4 | 0 | 0 |
| orm_type_count | 16 | 16 | 0 | 0 |
| orm_type_delete | 16 | 16 | 0 | 0 |
| orm_type_distinct | 16 | 16 | 0 | 0 |
| orm_type_filter | 16 | 16 | 0 | 0 |
| orm_type_null | 16 | 16 | 0 | 0 |
| orm_type_order | 16 | 12 | 0 | 4 |
| orm_type_roundtrip | 16 | 16 | 0 | 0 |
| orm_type_update | 16 | 16 | 0 | 0 |
| pagination | 7 | 7 | 0 | 0 |
| precision | 10 | 10 | 0 | 0 |
| prepared | 6 | 6 | 0 | 0 |
| projection | 10 | 10 | 0 | 0 |
| query | 35 | 34 | 1 | 0 |
| query_pattern | 10 | 10 | 0 | 0 |
| query_shape | 20 | 20 | 0 | 0 |
| queryset | 18 | 18 | 0 | 0 |
| reflection | 8 | 8 | 0 | 0 |
| rel_loader | 12 | 12 | 0 | 0 |
| relationship | 9 | 9 | 0 | 0 |
| semantics | 11 | 3 | 8 | 0 |
| set_operation | 12 | 11 | 1 | 0 |
| show | 10 | 7 | 3 | 0 |
| social_graph | 25 | 25 | 0 | 0 |
| str_function | 18 | 18 | 0 | 0 |
| stream | 3 | 3 | 0 | 0 |
| stream_fetchmany | 4 | 4 | 0 | 0 |
| stream_sscursor | 4 | 4 | 0 | 0 |
| string_concat | 6 | 6 | 0 | 0 |
| string_fns | 6 | 6 | 0 | 0 |
| string_like | 6 | 6 | 0 | 0 |
| subquery_position | 6 | 6 | 0 | 0 |
| subquery_type | 32 | 30 | 2 | 0 |
| temporal_fracsec | 13 | 7 | 6 | 0 |
| temporal_roundtrip | 13 | 13 | 0 | 0 |
| three_valued_logic | 26 | 23 | 3 | 0 |
| timeseries | 20 | 20 | 0 | 0 |
| timezone | 10 | 10 | 0 | 0 |
| transaction | 15 | 10 | 5 | 0 |
| tx_pattern | 4 | 4 | 0 | 0 |
| type_boundary | 20 | 20 | 0 | 0 |
| type_case | 32 | 30 | 2 | 0 |
| type_coalesce | 32 | 30 | 2 | 0 |
| type_coercion | 15 | 12 | 3 | 0 |
| type_count | 32 | 30 | 2 | 0 |
| type_create | 26 | 26 | 0 | 0 |
| type_declare | 76 | 72 | 4 | 0 |
| type_distinct | 32 | 30 | 2 | 0 |
| type_groupby | 32 | 30 | 2 | 0 |
| type_in | 32 | 30 | 2 | 0 |
| type_index | 32 | 30 | 2 | 0 |
| type_insert_select | 32 | 30 | 2 | 0 |
| type_insert_set | 32 | 30 | 2 | 0 |
| type_minmax | 32 | 30 | 2 | 0 |
| type_null | 102 | 98 | 4 | 0 |
| type_orderby | 32 | 30 | 2 | 0 |
| type_param | 76 | 69 | 7 | 0 |
| type_prepared | 32 | 30 | 2 | 0 |
| type_replace | 32 | 30 | 2 | 0 |
| type_roundtrip | 102 | 98 | 4 | 0 |
| type_update | 76 | 72 | 4 | 0 |
| type_where | 32 | 30 | 2 | 0 |
| vector_aggregate | 24 | 0 | 24 | 0 |
| vector_arith | 24 | 24 | 0 | 0 |
| vector_declare | 24 | 24 | 0 | 0 |
| vector_distance | 144 | 120 | 24 | 0 |
| vector_hybrid | 24 | 24 | 0 | 0 |
| vector_ivfflat | 30 | 30 | 0 | 0 |
| where_clause | 20 | 20 | 0 | 0 |
| wide_table | 5 | 5 | 0 | 0 |
| window | 11 | 11 | 0 | 0 |
| window_clause | 56 | 56 | 0 | 0 |
| workload | 16 | 16 | 0 | 0 |
| workload:analytics | 6 | 6 | 0 | 0 |
| workload:filter | 144 | 144 | 0 | 0 |
| workload:group | 48 | 48 | 0 | 0 |
| workload:join | 18 | 18 | 0 | 0 |
| workload:sort | 48 | 48 | 0 | 0 |
| workload:subquery | 6 | 6 | 0 | 0 |
| workload:window | 24 | 24 | 0 | 0 |

## Failures by error signature

| Error code | Count | Sample message |
|---|--:|---|
| `20203` | 793 | (20203, 'invalid argument function rand, bad value [BIGINT]') |
| `1064` | 162 | (1064, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 38 near "  |
| `20105` | 98 | (20105, "not supported: function or operator 'character_length'") |
| `20301` | 80 | (20301, 'invalid input: data too long') |
| `20101` | 44 | (20101, 'internal error: distinct bit operations are not supported: \n\ngithub.com/matrixorigin/matrixone/pkg/sql/colexec/aggexec.makeBitOpExec\n\t/go/src/github.com/matrixorigin/matrixone/pkg/sql/col |
| `1690` | 34 | (1690, "data out of range: data type int8, value '128'") |
| `BEHAVIOR` | 28 | utf8mb4_general_ci: 'abc' and 'ABC' both accepted (collation ignored) |
| `ERROR` | 3 | (2013, 'Lost connection to MySQL server during query (timed out)') \| timed out |
| `1062` | 2 | (1062, "Duplicate entry '1' for key 'id'") |
| `1105` | 1 | (1105, 'internal error: not supported type INTERVAL') |
| `20102` | 1 | (20102, 'EXCEPT/MINUS ALL clause is not yet implemented') |

## Harness-bug failures (must be zero)

**0 harness-bug failures.** All FAILs are DB-driven incompatibilities.

## Top distinct failure messages

| Count | Message |
|--:|---|
| 113 | (<n>, 'invalid argument cast to int, bad value <n>-06-23') |
| 112 | (<n>, 'invalid argument cast to int, bad value <n>-06-23 10:20:30') |
| 111 | (<n>, 'invalid argument cast to int, bad value 10abc') |
| 110 | (<n>, 'invalid argument cast to int, bad value hello') |
| 36 | (<n>, 'invalid argument operator -, bad value [VARCHAR VARCHAR]') |
| 36 | (<n>, 'invalid argument operator *, bad value [VARCHAR VARCHAR]') |
| 36 | (<n>, 'invalid argument operator /, bad value [VARCHAR VARCHAR]') |
| 36 | (<n>, 'invalid argument operator div, bad value [VARCHAR VARCHAR]') |
| 36 | (<n>, 'invalid argument operator %, bad value [VARCHAR VARCHAR]') |
| 27 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 27 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 24 | (<n>, "not supported: function or operator 'negative_inner<id>'") |
| 21 | (<n>, "not supported: function or operator 'json<id>'") |
| 16 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 12 | (<n>, 'invalid argument aggregate function sum, bad value [VECF32]') |
| 12 | (<n>, 'invalid argument aggregate function sum, bad value [VECF64]') |
| 9 | (<n>, 'invalid argument parse timestamp, bad value 11:22:33') |
| 9 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 8 | (<n>, "not supported: function or operator 'json_array<id>'") |
| 8 | (<n>, 'invalid argument function crc32, bad value [BIGINT]') |
| 8 | (<n>, 'invalid argument operator unary_tilde, bad value [BIT]') |
| 6 | (<n>, 'invalid input: invalid datetime value 11:22:33.<n>') |
| 6 | (<n>, 'invalid argument operator div, bad value [VARCHAR ANY]') |
| 6 | (<n>, 'invalid argument operator div, bad value [ANY VARCHAR]') |
| 5 | (<n>, "not supported: function or operator 'json_contains_path'") |
| 5 | (<n>, "not supported: function or operator 'json_depth'") |
| 5 | (<n>, "not supported: function or operator 'json_merge_patch'") |
| 5 | (<n>, 'data out of range: data type BIGINT, ') |
| 4 | (<n>, "not supported: function or operator 'json_storage_size'") |
| 4 | (<n>, 'invalid argument cast to int, bad value a') |

