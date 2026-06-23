# Python ORM ⇆ MatrixOne Compatibility Summary

- **Engine:** `8.0.30-MatrixOne-v4.0.0-rc3`
- **Scenarios:** 5076 total — **4800 pass**, **264 fail**, **12 skip**
- **Pass rate (excl. skip):** 94.8%

## Pass/fail by framework

| Framework | Total | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|--:|
| django | 505 | 485 | 20 | 0 | 96.0% |
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
| bulk | 17 | 17 | 0 | 0 |
| cast | 11 | 11 | 0 | 0 |
| cast_from_col | 19 | 18 | 1 | 0 |
| collation | 14 | 9 | 5 | 0 |
| column_modifier | 22 | 21 | 1 | 0 |
| compare_op | 224 | 210 | 14 | 0 |
| compare_pair | 20 | 20 | 0 | 0 |
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
| enum_set | 5 | 4 | 1 | 0 |
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
| group_by_variant | 11 | 11 | 0 | 0 |
| index_variety | 7 | 7 | 0 | 0 |
| insert_variant | 6 | 6 | 0 | 0 |
| interval_unit | 14 | 14 | 0 | 0 |
| join_condition | 10 | 10 | 0 | 0 |
| join_matrix | 24 | 24 | 0 | 0 |
| json | 4 | 3 | 1 | 0 |
| json_op | 23 | 15 | 8 | 0 |
| like_pattern | 17 | 17 | 0 | 0 |
| literal | 22 | 21 | 1 | 0 |
| nesting | 15 | 10 | 5 | 0 |
| null_propagation | 16 | 14 | 2 | 0 |
| num_function | 10 | 8 | 2 | 0 |
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
| str_function | 18 | 18 | 0 | 0 |
| stream | 3 | 3 | 0 | 0 |
| string_concat | 6 | 6 | 0 | 0 |
| string_fns | 6 | 6 | 0 | 0 |
| string_like | 6 | 6 | 0 | 0 |
| subquery_position | 6 | 6 | 0 | 0 |
| subquery_type | 32 | 30 | 2 | 0 |
| transaction | 15 | 10 | 5 | 0 |
| tx_pattern | 4 | 4 | 0 | 0 |
| type_boundary | 20 | 20 | 0 | 0 |
| type_case | 32 | 30 | 2 | 0 |
| type_coalesce | 32 | 30 | 2 | 0 |
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
| where_clause | 20 | 20 | 0 | 0 |
| window | 11 | 11 | 0 | 0 |
| window_clause | 56 | 56 | 0 | 0 |
| workload | 16 | 16 | 0 | 0 |

## Failures by error signature

| Error code | Count | Sample message |
|---|--:|---|
| `1064` | 114 | (1064, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line 1 column 38 near "  |
| `20105` | 62 | (20105, "not supported: function or operator 'character_length'") |
| `20203` | 49 | (20203, 'invalid argument function rand, bad value [BIGINT]') |
| `BEHAVIOR` | 21 | utf8mb4_general_ci: 'abc' and 'ABC' both accepted (collation ignored) |
| `20101` | 11 | (20101, 'internal error: distinct bit operations are not supported: \n\ngithub.com/matrixorigin/matrixone/pkg/sql/colexec/aggexec.makeBitOpExec\n\t/go/src/github.com/matrixorigin/matrixone/pkg/sql/col |
| `20301` | 5 | (20301, 'invalid input: data too long') |
| `1105` | 1 | (1105, 'internal error: not supported type INTERVAL') |
| `20102` | 1 | (20102, 'EXCEPT/MINUS ALL clause is not yet implemented') |

## Harness-bug failures (must be zero)

**0 harness-bug failures.** All FAILs are DB-driven incompatibilities.

## Top distinct failure messages

| Count | Message |
|--:|---|
| 27 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 27 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 17 | (<n>, "not supported: function or operator 'json<id>'") |
| 16 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 9 | (<n>, 'invalid argument parse timestamp, bad value 11:22:33') |
| 9 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 7 | (<n>, "not supported: function or operator 'json_array<id>'") |
| 4 | (<n>, "not supported: function or operator 'json_contains_path'") |
| 4 | (<n>, "not supported: function or operator 'json_depth'") |
| 4 | (<n>, "not supported: function or operator 'json_merge_patch'") |
| 4 | (<n>, 'invalid argument function greatest, bad value [DOUBLE BIGINT]') |
| 4 | (<n>, 'invalid argument function least, bad value [DOUBLE BIGINT]') |
| 3 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 3 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 3 | (<n>, "not supported: function or operator 'character<id>'") |
| 3 | (<n>, 'invalid argument function rand, bad value [BIGINT]') |
| 3 | (<n>, "not supported: function or operator 'json_merge<id>'") |
| 3 | (<n>, "not supported: function or operator 'json_storage_size'") |
| 3 | (<n>, "not supported: function or operator 'uuid_short'") |
| 3 | (<n>, "not supported: function or operator 'benchmark'") |
| 3 | (<n>, "not supported: function or operator 'weight<id>'") |
| 3 | (<n>, 'invalid argument cast to int, bad value utf8mb4') |
| 3 | (<n>, 'invalid argument function crc32, bad value [BIGINT]') |
| 3 | (<n>, "not supported: function or operator 'json_merge'") |
| 3 | (<n>, 'invalid argument cast to int, bad value x') |
| 3 | (<n>, 'invalid argument cast to int, bad value a') |
| 3 | (<n>, 'invalid argument function greatest, bad value [INT BIGINT]') |
| 2 | (<n>, 'invalid input: data too long') |
| 2 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |
| 2 | (<n>, 'SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syntax to use. syntax error at line  |

