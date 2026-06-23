# MatrixOne ⇆ Node.js ORM Compatibility — Summary

- **Engine:** 8.0.30-MatrixOne-v4.0.0-rc3
- **Target:** 127.0.0.1:6001
- **Run mode:** full (registered 5115, ran 5115)
- **Generated:** 2026-06-23T10:44:18.253Z

**Totals:** 5115 scenarios — 4851 PASS · 264 FAIL · 0 SKIP (94.8% pass)

## Pass rate by framework

| Framework | Pass | Fail | Skip | Pass % |
|---|--:|--:|--:|--:|
| knex | 850 | 22 | 0 | 97.5% |
| raw | 2672 | 155 | 0 | 94.5% |
| sequelize | 824 | 81 | 0 | 91.0% |
| typeorm | 505 | 6 | 0 | 98.8% |

## Pass rate by framework × category

| Framework | Category | Pass | Fail | Skip |
|---|---|--:|--:|--:|
| knex | aggregate | 13 | 0 | 0 |
| knex | chained | 15 | 0 | 0 |
| knex | col-modifier | 12 | 2 | 0 |
| knex | connection | 1 | 0 | 0 |
| knex | fulltype/bigInteger | 11 | 0 | 0 |
| knex | fulltype/binary | 7 | 0 | 0 |
| knex | fulltype/boolean | 7 | 0 | 0 |
| knex | fulltype/date | 11 | 0 | 0 |
| knex | fulltype/datetime | 11 | 0 | 0 |
| knex | fulltype/decimal | 11 | 0 | 0 |
| knex | fulltype/double | 11 | 0 | 0 |
| knex | fulltype/enum | 7 | 0 | 0 |
| knex | fulltype/float | 11 | 0 | 0 |
| knex | fulltype/integer | 11 | 0 | 0 |
| knex | fulltype/json | 7 | 0 | 0 |
| knex | fulltype/jsonb | 7 | 0 | 0 |
| knex | fulltype/string | 9 | 0 | 0 |
| knex | fulltype/text | 7 | 0 | 0 |
| knex | fulltype/text-long | 7 | 0 | 0 |
| knex | fulltype/text-medium | 7 | 0 | 0 |
| knex | fulltype/time | 11 | 0 | 0 |
| knex | fulltype/timestamp | 11 | 0 | 0 |
| knex | fulltype/tinyint | 11 | 0 | 0 |
| knex | fulltype/uuid | 9 | 0 | 0 |
| knex | helper | 3 | 0 | 0 |
| knex | introspect | 6 | 0 | 0 |
| knex | join | 8 | 0 | 0 |
| knex | mutation | 10 | 0 | 0 |
| knex | op-type/date | 15 | 0 | 0 |
| knex | op-type/datetime | 15 | 0 | 0 |
| knex | op-type/decimal | 17 | 0 | 0 |
| knex | op-type/double | 17 | 0 | 0 |
| knex | op-type/integer | 17 | 0 | 0 |
| knex | op-type/string | 16 | 0 | 0 |
| knex | pagination | 10 | 0 | 0 |
| knex | query | 39 | 2 | 0 |
| knex | raw | 2 | 0 | 0 |
| knex | raw-expr | 10 | 0 | 0 |
| knex | raw-func/datetime | 47 | 3 | 0 |
| knex | raw-func/json | 13 | 11 | 0 |
| knex | raw-func/misc | 18 | 2 | 0 |
| knex | raw-func/numeric | 32 | 0 | 0 |
| knex | raw-func/string | 48 | 2 | 0 |
| knex | schema | 4 | 0 | 0 |
| knex | schema/alter | 5 | 0 | 0 |
| knex | schema/create | 23 | 0 | 0 |
| knex | schema/insert | 22 | 0 | 0 |
| knex | schema/modifier | 7 | 0 | 0 |
| knex | schema/roundtrip | 22 | 0 | 0 |
| knex | schema/specific | 20 | 0 | 0 |
| knex | transaction | 4 | 0 | 0 |
| knex | type-op/bigInteger | 15 | 0 | 0 |
| knex | type-op/boolean | 12 | 0 | 0 |
| knex | type-op/date | 15 | 0 | 0 |
| knex | type-op/datetime | 15 | 0 | 0 |
| knex | type-op/decimal | 15 | 0 | 0 |
| knex | type-op/double | 15 | 0 | 0 |
| knex | type-op/enum | 12 | 0 | 0 |
| knex | type-op/float | 15 | 0 | 0 |
| knex | type-op/integer | 15 | 0 | 0 |
| knex | type-op/string | 13 | 0 | 0 |
| knex | type-op/text | 13 | 0 | 0 |
| knex | where-op | 20 | 0 | 0 |
| raw | aggregate-table | 19 | 1 | 0 |
| raw | alter-add/bin | 6 | 0 | 0 |
| raw | alter-add/dt | 5 | 0 | 0 |
| raw | alter-add/enum | 2 | 0 | 0 |
| raw | alter-add/int | 10 | 0 | 0 |
| raw | alter-add/json | 1 | 0 | 0 |
| raw | alter-add/num | 6 | 0 | 0 |
| raw | alter-add/str | 6 | 0 | 0 |
| raw | alter-add/vector | 2 | 0 | 0 |
| raw | alter-modify | 10 | 0 | 0 |
| raw | arith-proj/bigint | 0 | 2 | 0 |
| raw | arith-proj/bigint_u | 1 | 1 | 0 |
| raw | arith-proj/bool | 1 | 1 | 0 |
| raw | arith-proj/boolean | 1 | 1 | 0 |
| raw | arith-proj/decimal | 2 | 0 | 0 |
| raw | arith-proj/double | 2 | 0 | 0 |
| raw | arith-proj/float | 2 | 0 | 0 |
| raw | arith-proj/float_ps | 2 | 0 | 0 |
| raw | arith-proj/int | 2 | 0 | 0 |
| raw | arith-proj/int_u | 2 | 0 | 0 |
| raw | arith-proj/mediumint | 2 | 0 | 0 |
| raw | arith-proj/numeric | 2 | 0 | 0 |
| raw | arith-proj/real | 2 | 0 | 0 |
| raw | arith-proj/smallint | 2 | 0 | 0 |
| raw | arith-proj/tinyint | 2 | 0 | 0 |
| raw | arith-proj/tinyint_u | 2 | 0 | 0 |
| raw | attr/bin | 6 | 0 | 0 |
| raw | attr/dt | 5 | 0 | 0 |
| raw | attr/enum | 2 | 0 | 0 |
| raw | attr/int | 10 | 0 | 0 |
| raw | attr/json | 1 | 0 | 0 |
| raw | attr/num | 6 | 0 | 0 |
| raw | attr/str | 6 | 0 | 0 |
| raw | attr/vector | 2 | 0 | 0 |
| raw | behavior | 1 | 11 | 0 |
| raw | bit-col | 3 | 0 | 0 |
| raw | case-expr/dt | 10 | 0 | 0 |
| raw | case-expr/int | 20 | 0 | 0 |
| raw | case-expr/num | 12 | 0 | 0 |
| raw | case-expr/str | 12 | 0 | 0 |
| raw | cast-target | 17 | 1 | 0 |
| raw | coercion | 9 | 1 | 0 |
| raw | collation-behavior | 0 | 5 | 0 |
| raw | collation-ddl | 10 | 0 | 0 |
| raw | constraint | 7 | 0 | 0 |
| raw | convert-target | 14 | 4 | 0 |
| raw | date-extract | 16 | 0 | 0 |
| raw | date-format-token | 24 | 0 | 0 |
| raw | date-interval | 28 | 0 | 0 |
| raw | ddl | 17 | 1 | 0 |
| raw | ddl-alter | 10 | 1 | 0 |
| raw | ddl-default | 13 | 0 | 0 |
| raw | ddl-index | 2 | 1 | 0 |
| raw | decode/bin | 6 | 0 | 0 |
| raw | decode/dt | 5 | 0 | 0 |
| raw | decode/enum | 2 | 0 | 0 |
| raw | decode/int | 10 | 0 | 0 |
| raw | decode/json | 1 | 0 | 0 |
| raw | decode/num | 6 | 0 | 0 |
| raw | decode/str | 6 | 0 | 0 |
| raw | decode/vector | 2 | 0 | 0 |
| raw | distinct/dt | 10 | 0 | 0 |
| raw | distinct/enum | 4 | 0 | 0 |
| raw | distinct/int | 20 | 0 | 0 |
| raw | distinct/num | 12 | 0 | 0 |
| raw | distinct/str | 12 | 0 | 0 |
| raw | dml | 15 | 1 | 0 |
| raw | dml-form/bigint | 3 | 0 | 0 |
| raw | dml-form/bigint_u | 3 | 0 | 0 |
| raw | dml-form/bool | 3 | 0 | 0 |
| raw | dml-form/boolean | 3 | 0 | 0 |
| raw | dml-form/char | 3 | 0 | 0 |
| raw | dml-form/date | 3 | 0 | 0 |
| raw | dml-form/datetime | 3 | 0 | 0 |
| raw | dml-form/decimal | 3 | 0 | 0 |
| raw | dml-form/double | 3 | 0 | 0 |
| raw | dml-form/float | 3 | 0 | 0 |
| raw | dml-form/float_ps | 3 | 0 | 0 |
| raw | dml-form/int | 3 | 0 | 0 |
| raw | dml-form/int_u | 3 | 0 | 0 |
| raw | dml-form/longtext | 3 | 0 | 0 |
| raw | dml-form/mediumint | 3 | 0 | 0 |
| raw | dml-form/mediumtext | 3 | 0 | 0 |
| raw | dml-form/numeric | 3 | 0 | 0 |
| raw | dml-form/real | 3 | 0 | 0 |
| raw | dml-form/smallint | 3 | 0 | 0 |
| raw | dml-form/text | 3 | 0 | 0 |
| raw | dml-form/time | 3 | 0 | 0 |
| raw | dml-form/timestamp | 3 | 0 | 0 |
| raw | dml-form/tinyint | 3 | 0 | 0 |
| raw | dml-form/tinyint_u | 3 | 0 | 0 |
| raw | dml-form/tinytext | 3 | 0 | 0 |
| raw | dml-form/varchar | 3 | 0 | 0 |
| raw | dml-form/year | 3 | 0 | 0 |
| raw | edge/bigint | 2 | 0 | 0 |
| raw | edge/bigint_u | 2 | 0 | 0 |
| raw | edge/bool | 2 | 0 | 0 |
| raw | edge/boolean | 2 | 0 | 0 |
| raw | edge/char | 2 | 0 | 0 |
| raw | edge/date | 2 | 0 | 0 |
| raw | edge/datetime | 2 | 0 | 0 |
| raw | edge/decimal | 2 | 0 | 0 |
| raw | edge/double | 2 | 0 | 0 |
| raw | edge/float | 2 | 0 | 0 |
| raw | edge/float_ps | 2 | 0 | 0 |
| raw | edge/int | 2 | 0 | 0 |
| raw | edge/int_u | 2 | 0 | 0 |
| raw | edge/longtext | 2 | 0 | 0 |
| raw | edge/mediumint | 2 | 0 | 0 |
| raw | edge/mediumtext | 2 | 0 | 0 |
| raw | edge/numeric | 2 | 0 | 0 |
| raw | edge/real | 2 | 0 | 0 |
| raw | edge/smallint | 2 | 0 | 0 |
| raw | edge/text | 2 | 0 | 0 |
| raw | edge/time | 2 | 0 | 0 |
| raw | edge/timestamp | 2 | 0 | 0 |
| raw | edge/tinyint | 2 | 0 | 0 |
| raw | edge/tinyint_u | 2 | 0 | 0 |
| raw | edge/tinytext | 2 | 0 | 0 |
| raw | edge/varchar | 2 | 0 | 0 |
| raw | edge/year | 2 | 0 | 0 |
| raw | func-table/datetime | 47 | 3 | 0 |
| raw | func-table/numeric | 32 | 0 | 0 |
| raw | func-table/string | 48 | 2 | 0 |
| raw | func-where/aggregate | 0 | 9 | 0 |
| raw | func-where/datetime | 47 | 3 | 0 |
| raw | func-where/json | 13 | 11 | 0 |
| raw | func-where/misc | 18 | 2 | 0 |
| raw | func-where/numeric | 32 | 0 | 0 |
| raw | func-where/spatial | 1 | 5 | 0 |
| raw | func-where/string | 48 | 2 | 0 |
| raw | func/aggregate | 18 | 0 | 0 |
| raw | func/datetime | 94 | 6 | 0 |
| raw | func/json | 26 | 22 | 0 |
| raw | func/misc | 36 | 4 | 0 |
| raw | func/numeric | 64 | 0 | 0 |
| raw | func/spatial | 2 | 10 | 0 |
| raw | func/string | 96 | 4 | 0 |
| raw | groupby-modifier | 10 | 0 | 0 |
| raw | index-type/dt | 10 | 0 | 0 |
| raw | index-type/enum | 3 | 1 | 0 |
| raw | index-type/int | 20 | 0 | 0 |
| raw | index-type/num | 12 | 0 | 0 |
| raw | info-schema | 20 | 4 | 0 |
| raw | insert-form/bigint | 1 | 0 | 0 |
| raw | insert-form/bigint_u | 1 | 0 | 0 |
| raw | insert-form/bool | 1 | 0 | 0 |
| raw | insert-form/boolean | 1 | 0 | 0 |
| raw | insert-form/char | 1 | 0 | 0 |
| raw | insert-form/date | 1 | 0 | 0 |
| raw | insert-form/datetime | 1 | 0 | 0 |
| raw | insert-form/decimal | 1 | 0 | 0 |
| raw | insert-form/double | 1 | 0 | 0 |
| raw | insert-form/float | 1 | 0 | 0 |
| raw | insert-form/float_ps | 1 | 0 | 0 |
| raw | insert-form/int | 1 | 0 | 0 |
| raw | insert-form/int_u | 1 | 0 | 0 |
| raw | insert-form/longtext | 1 | 0 | 0 |
| raw | insert-form/mediumint | 1 | 0 | 0 |
| raw | insert-form/mediumtext | 1 | 0 | 0 |
| raw | insert-form/numeric | 1 | 0 | 0 |
| raw | insert-form/real | 1 | 0 | 0 |
| raw | insert-form/smallint | 1 | 0 | 0 |
| raw | insert-form/text | 1 | 0 | 0 |
| raw | insert-form/time | 1 | 0 | 0 |
| raw | insert-form/timestamp | 1 | 0 | 0 |
| raw | insert-form/tinyint | 1 | 0 | 0 |
| raw | insert-form/tinyint_u | 1 | 0 | 0 |
| raw | insert-form/tinytext | 1 | 0 | 0 |
| raw | insert-form/varchar | 1 | 0 | 0 |
| raw | insert-form/year | 1 | 0 | 0 |
| raw | join-matrix | 24 | 0 | 0 |
| raw | json-path | 19 | 8 | 0 |
| raw | like-pattern | 12 | 0 | 0 |
| raw | math-args | 39 | 1 | 0 |
| raw | mo-fulltext | 3 | 0 | 0 |
| raw | mo-vector | 15 | 1 | 0 |
| raw | multirow/bin | 6 | 0 | 0 |
| raw | multirow/dt | 5 | 0 | 0 |
| raw | multirow/enum | 2 | 0 | 0 |
| raw | multirow/int | 10 | 0 | 0 |
| raw | multirow/json | 1 | 0 | 0 |
| raw | multirow/num | 6 | 0 | 0 |
| raw | multirow/str | 6 | 0 | 0 |
| raw | multirow/vector | 2 | 0 | 0 |
| raw | op/arith | 9 | 0 | 0 |
| raw | op/bitwise | 6 | 0 | 0 |
| raw | op/cast | 9 | 0 | 0 |
| raw | op/cmp | 22 | 0 | 0 |
| raw | op/logic | 5 | 0 | 0 |
| raw | op/string | 2 | 0 | 0 |
| raw | orderby/bigint | 1 | 0 | 0 |
| raw | orderby/bigint_u | 1 | 0 | 0 |
| raw | orderby/bool | 1 | 0 | 0 |
| raw | orderby/boolean | 1 | 0 | 0 |
| raw | orderby/char | 1 | 0 | 0 |
| raw | orderby/date | 1 | 0 | 0 |
| raw | orderby/datetime | 1 | 0 | 0 |
| raw | orderby/decimal | 1 | 0 | 0 |
| raw | orderby/double | 1 | 0 | 0 |
| raw | orderby/float | 1 | 0 | 0 |
| raw | orderby/float_ps | 1 | 0 | 0 |
| raw | orderby/int | 1 | 0 | 0 |
| raw | orderby/int_u | 1 | 0 | 0 |
| raw | orderby/longtext | 1 | 0 | 0 |
| raw | orderby/mediumint | 1 | 0 | 0 |
| raw | orderby/mediumtext | 1 | 0 | 0 |
| raw | orderby/numeric | 1 | 0 | 0 |
| raw | orderby/real | 1 | 0 | 0 |
| raw | orderby/smallint | 1 | 0 | 0 |
| raw | orderby/text | 1 | 0 | 0 |
| raw | orderby/time | 1 | 0 | 0 |
| raw | orderby/timestamp | 1 | 0 | 0 |
| raw | orderby/tinyint | 1 | 0 | 0 |
| raw | orderby/tinyint_u | 1 | 0 | 0 |
| raw | orderby/tinytext | 1 | 0 | 0 |
| raw | orderby/varchar | 1 | 0 | 0 |
| raw | orderby/year | 1 | 0 | 0 |
| raw | precision | 10 | 0 | 0 |
| raw | prepared | 2 | 1 | 0 |
| raw | prepared-bind/bin | 12 | 0 | 0 |
| raw | prepared-bind/dt | 10 | 0 | 0 |
| raw | prepared-bind/enum | 4 | 0 | 0 |
| raw | prepared-bind/int | 18 | 2 | 0 |
| raw | prepared-bind/json | 2 | 0 | 0 |
| raw | prepared-bind/num | 12 | 0 | 0 |
| raw | prepared-bind/str | 12 | 0 | 0 |
| raw | prepared-bind/vector | 4 | 0 | 0 |
| raw | prepared-meta | 1 | 5 | 0 |
| raw | prepared-reuse/bin | 6 | 0 | 0 |
| raw | prepared-reuse/dt | 5 | 0 | 0 |
| raw | prepared-reuse/enum | 2 | 0 | 0 |
| raw | prepared-reuse/int | 6 | 4 | 0 |
| raw | prepared-reuse/json | 1 | 0 | 0 |
| raw | prepared-reuse/num | 6 | 0 | 0 |
| raw | prepared-reuse/str | 6 | 0 | 0 |
| raw | prepared-reuse/vector | 2 | 0 | 0 |
| raw | prepared-type | 13 | 1 | 0 |
| raw | project/bin | 12 | 0 | 0 |
| raw | project/dt | 10 | 0 | 0 |
| raw | project/enum | 4 | 0 | 0 |
| raw | project/int | 20 | 0 | 0 |
| raw | project/json | 2 | 0 | 0 |
| raw | project/num | 12 | 0 | 0 |
| raw | project/str | 12 | 0 | 0 |
| raw | project/vector | 0 | 4 | 0 |
| raw | query | 41 | 1 | 0 |
| raw | query2 | 19 | 1 | 0 |
| raw | regexp-pattern | 10 | 0 | 0 |
| raw | semantics | 8 | 2 | 0 |
| raw | set-op | 7 | 1 | 0 |
| raw | show | 11 | 0 | 0 |
| raw | spatial-col | 3 | 0 | 0 |
| raw | str-where/char | 3 | 0 | 0 |
| raw | str-where/longtext | 3 | 0 | 0 |
| raw | str-where/mediumtext | 3 | 0 | 0 |
| raw | str-where/text | 3 | 0 | 0 |
| raw | str-where/tinytext | 3 | 0 | 0 |
| raw | str-where/varchar | 3 | 0 | 0 |
| raw | string-fn-variation | 20 | 0 | 0 |
| raw | subquery-shape | 12 | 0 | 0 |
| raw | tx | 4 | 3 | 0 |
| raw | tx-isolation | 7 | 0 | 0 |
| raw | type/bigint | 10 | 0 | 0 |
| raw | type/bigint_u | 10 | 0 | 0 |
| raw | type/binary | 9 | 0 | 0 |
| raw | type/bit | 2 | 0 | 0 |
| raw | type/blob | 9 | 0 | 0 |
| raw | type/bool | 10 | 0 | 0 |
| raw | type/boolean | 10 | 0 | 0 |
| raw | type/char | 10 | 0 | 0 |
| raw | type/date | 10 | 0 | 0 |
| raw | type/datetime | 10 | 0 | 0 |
| raw | type/decimal | 10 | 0 | 0 |
| raw | type/double | 10 | 0 | 0 |
| raw | type/enum | 10 | 0 | 0 |
| raw | type/float | 10 | 0 | 0 |
| raw | type/float_ps | 10 | 0 | 0 |
| raw | type/int | 10 | 0 | 0 |
| raw | type/int_u | 10 | 0 | 0 |
| raw | type/json | 9 | 0 | 0 |
| raw | type/longblob | 9 | 0 | 0 |
| raw | type/longtext | 10 | 0 | 0 |
| raw | type/mediumblob | 9 | 0 | 0 |
| raw | type/mediumint | 10 | 0 | 0 |
| raw | type/mediumtext | 10 | 0 | 0 |
| raw | type/numeric | 10 | 0 | 0 |
| raw | type/real | 10 | 0 | 0 |
| raw | type/set | 10 | 0 | 0 |
| raw | type/smallint | 10 | 0 | 0 |
| raw | type/text | 10 | 0 | 0 |
| raw | type/time | 10 | 0 | 0 |
| raw | type/timestamp | 10 | 0 | 0 |
| raw | type/tinyblob | 9 | 0 | 0 |
| raw | type/tinyint | 10 | 0 | 0 |
| raw | type/tinyint_u | 10 | 0 | 0 |
| raw | type/tinytext | 10 | 0 | 0 |
| raw | type/uuid | 2 | 0 | 0 |
| raw | type/varbinary | 9 | 0 | 0 |
| raw | type/varchar | 10 | 0 | 0 |
| raw | type/vecf32 | 9 | 0 | 0 |
| raw | type/vecf64 | 9 | 0 | 0 |
| raw | type/year | 10 | 0 | 0 |
| raw | uuid-feature | 4 | 0 | 0 |
| raw | where/bigint | 12 | 0 | 0 |
| raw | where/bigint_u | 12 | 0 | 0 |
| raw | where/bool | 12 | 0 | 0 |
| raw | where/boolean | 12 | 0 | 0 |
| raw | where/char | 12 | 0 | 0 |
| raw | where/date | 12 | 0 | 0 |
| raw | where/datetime | 12 | 0 | 0 |
| raw | where/decimal | 12 | 0 | 0 |
| raw | where/double | 12 | 0 | 0 |
| raw | where/float | 12 | 0 | 0 |
| raw | where/float_ps | 12 | 0 | 0 |
| raw | where/int | 12 | 0 | 0 |
| raw | where/int_u | 12 | 0 | 0 |
| raw | where/longtext | 12 | 0 | 0 |
| raw | where/mediumint | 12 | 0 | 0 |
| raw | where/mediumtext | 12 | 0 | 0 |
| raw | where/numeric | 12 | 0 | 0 |
| raw | where/real | 12 | 0 | 0 |
| raw | where/smallint | 12 | 0 | 0 |
| raw | where/text | 12 | 0 | 0 |
| raw | where/time | 12 | 0 | 0 |
| raw | where/timestamp | 12 | 0 | 0 |
| raw | where/tinyint | 12 | 0 | 0 |
| raw | where/tinyint_u | 12 | 0 | 0 |
| raw | where/tinytext | 12 | 0 | 0 |
| raw | where/varchar | 12 | 0 | 0 |
| raw | where/year | 12 | 0 | 0 |
| raw | window-fn | 15 | 0 | 0 |
| raw | workload/batch-insert | 8 | 0 | 0 |
| raw | workload/bulk | 2 | 0 | 0 |
| raw | workload/json | 4 | 0 | 0 |
| raw | workload/large-fetch | 2 | 0 | 0 |
| raw | workload/long | 8 | 0 | 0 |
| raw | workload/pool | 4 | 0 | 0 |
| raw | workload/wide | 3 | 0 | 0 |
| sequelize | aggregate | 6 | 0 | 0 |
| sequelize | assoc/belongsTo | 1 | 0 | 0 |
| sequelize | assoc/belongsToMany | 3 | 0 | 0 |
| sequelize | assoc/hasMany | 4 | 0 | 0 |
| sequelize | assoc/hasOne | 2 | 0 | 0 |
| sequelize | casting | 8 | 0 | 0 |
| sequelize | connection | 2 | 0 | 0 |
| sequelize | crud | 16 | 1 | 0 |
| sequelize | datatype/insert | 29 | 5 | 0 |
| sequelize | datatype/modifier | 5 | 0 | 0 |
| sequelize | datatype/null | 29 | 6 | 0 |
| sequelize | datatype/roundtrip | 29 | 5 | 0 |
| sequelize | datatype/sync | 29 | 6 | 0 |
| sequelize | finder | 17 | 0 | 0 |
| sequelize | fn-query | 15 | 0 | 0 |
| sequelize | fulltype/BIGINT | 11 | 0 | 0 |
| sequelize | fulltype/BLOB | 7 | 0 | 0 |
| sequelize | fulltype/BLOB-long | 7 | 0 | 0 |
| sequelize | fulltype/BOOLEAN | 7 | 0 | 0 |
| sequelize | fulltype/CHAR | 9 | 0 | 0 |
| sequelize | fulltype/DATE | 11 | 0 | 0 |
| sequelize | fulltype/DATEONLY | 11 | 0 | 0 |
| sequelize | fulltype/DECIMAL | 11 | 0 | 0 |
| sequelize | fulltype/DOUBLE | 0 | 11 | 0 |
| sequelize | fulltype/ENUM | 6 | 1 | 0 |
| sequelize | fulltype/FLOAT | 11 | 0 | 0 |
| sequelize | fulltype/INTEGER | 11 | 0 | 0 |
| sequelize | fulltype/INTEGER.UNSIGNED | 10 | 1 | 0 |
| sequelize | fulltype/JSON | 8 | 0 | 0 |
| sequelize | fulltype/MEDIUMINT | 11 | 0 | 0 |
| sequelize | fulltype/REAL | 11 | 0 | 0 |
| sequelize | fulltype/SMALLINT | 11 | 0 | 0 |
| sequelize | fulltype/STRING | 9 | 0 | 0 |
| sequelize | fulltype/STRING-100 | 9 | 0 | 0 |
| sequelize | fulltype/TEXT | 7 | 0 | 0 |
| sequelize | fulltype/TEXT-long | 7 | 0 | 0 |
| sequelize | fulltype/TEXT-medium | 7 | 0 | 0 |
| sequelize | fulltype/TIME | 11 | 0 | 0 |
| sequelize | fulltype/TINYINT | 11 | 0 | 0 |
| sequelize | fulltype/UUID | 0 | 9 | 0 |
| sequelize | hook | 3 | 0 | 0 |
| sequelize | instance | 10 | 0 | 0 |
| sequelize | json | 3 | 2 | 0 |
| sequelize | model-option | 4 | 0 | 0 |
| sequelize | op-matrix | 23 | 0 | 0 |
| sequelize | op-type/BIGINT | 17 | 0 | 0 |
| sequelize | op-type/CHAR | 18 | 0 | 0 |
| sequelize | op-type/DATEONLY | 15 | 0 | 0 |
| sequelize | op-type/DECIMAL | 17 | 0 | 0 |
| sequelize | op-type/DOUBLE | 0 | 17 | 0 |
| sequelize | op-type/INTEGER | 17 | 0 | 0 |
| sequelize | op-type/STRING | 18 | 0 | 0 |
| sequelize | operator | 23 | 0 | 0 |
| sequelize | paranoid | 2 | 0 | 0 |
| sequelize | raw-query | 3 | 1 | 0 |
| sequelize | raw-typed | 8 | 0 | 0 |
| sequelize | scope | 2 | 0 | 0 |
| sequelize | transaction | 5 | 0 | 0 |
| sequelize | type-op/BIGINT | 15 | 0 | 0 |
| sequelize | type-op/BOOLEAN | 12 | 0 | 0 |
| sequelize | type-op/CHAR | 14 | 0 | 0 |
| sequelize | type-op/DATE | 15 | 0 | 0 |
| sequelize | type-op/DATEONLY | 15 | 0 | 0 |
| sequelize | type-op/DECIMAL | 15 | 0 | 0 |
| sequelize | type-op/DOUBLE | 0 | 15 | 0 |
| sequelize | type-op/ENUM | 11 | 1 | 0 |
| sequelize | type-op/FLOAT | 15 | 0 | 0 |
| sequelize | type-op/INTEGER | 15 | 0 | 0 |
| sequelize | type-op/MEDIUMINT | 15 | 0 | 0 |
| sequelize | type-op/SMALLINT | 15 | 0 | 0 |
| sequelize | type-op/STRING | 14 | 0 | 0 |
| sequelize | type-op/TEXT | 14 | 0 | 0 |
| sequelize | type-op/TIME | 15 | 0 | 0 |
| sequelize | type-op/TINYINT | 15 | 0 | 0 |
| sequelize | validation | 4 | 0 | 0 |
| sequelize | where-fn | 8 | 0 | 0 |
| typeorm | column/insert | 32 | 0 | 0 |
| typeorm | column/modifier | 5 | 0 | 0 |
| typeorm | column/null | 33 | 0 | 0 |
| typeorm | column/roundtrip | 32 | 0 | 0 |
| typeorm | column/sync | 33 | 0 | 0 |
| typeorm | connection | 2 | 0 | 0 |
| typeorm | crud | 16 | 1 | 0 |
| typeorm | find-operator | 11 | 0 | 0 |
| typeorm | find-options | 15 | 0 | 0 |
| typeorm | fulltype/bigint | 6 | 0 | 0 |
| typeorm | fulltype/blob | 6 | 0 | 0 |
| typeorm | fulltype/boolean | 6 | 0 | 0 |
| typeorm | fulltype/char | 6 | 0 | 0 |
| typeorm | fulltype/date | 6 | 0 | 0 |
| typeorm | fulltype/datetime | 6 | 0 | 0 |
| typeorm | fulltype/decimal | 6 | 0 | 0 |
| typeorm | fulltype/double | 6 | 0 | 0 |
| typeorm | fulltype/enum | 6 | 0 | 0 |
| typeorm | fulltype/float | 6 | 0 | 0 |
| typeorm | fulltype/int | 6 | 0 | 0 |
| typeorm | fulltype/json | 6 | 0 | 0 |
| typeorm | fulltype/longtext | 6 | 0 | 0 |
| typeorm | fulltype/mediumint | 6 | 0 | 0 |
| typeorm | fulltype/mediumtext | 6 | 0 | 0 |
| typeorm | fulltype/numeric | 6 | 0 | 0 |
| typeorm | fulltype/simple-array | 6 | 0 | 0 |
| typeorm | fulltype/simple-json | 6 | 0 | 0 |
| typeorm | fulltype/smallint | 6 | 0 | 0 |
| typeorm | fulltype/text | 6 | 0 | 0 |
| typeorm | fulltype/time | 6 | 0 | 0 |
| typeorm | fulltype/timestamp | 6 | 0 | 0 |
| typeorm | fulltype/tinyint | 6 | 0 | 0 |
| typeorm | fulltype/varchar | 6 | 0 | 0 |
| typeorm | fulltype/year | 6 | 0 | 0 |
| typeorm | manager | 4 | 0 | 0 |
| typeorm | qb-matrix | 19 | 1 | 0 |
| typeorm | querybuilder | 11 | 1 | 0 |
| typeorm | relation/ManyToMany | 1 | 0 | 0 |
| typeorm | relation/ManyToOne | 1 | 0 | 0 |
| typeorm | relation/OneToMany | 1 | 0 | 0 |
| typeorm | relation/OneToOne | 1 | 0 | 0 |
| typeorm | relation/cascade | 1 | 0 | 0 |
| typeorm | relation/leftJoinAndSelect | 1 | 0 | 0 |
| typeorm | schema | 2 | 1 | 0 |
| typeorm | soft-delete | 0 | 2 | 0 |
| typeorm | transaction | 4 | 0 | 0 |
| typeorm | type-op/bigint | 14 | 0 | 0 |
| typeorm | type-op/boolean | 11 | 0 | 0 |
| typeorm | type-op/date | 14 | 0 | 0 |
| typeorm | type-op/datetime | 14 | 0 | 0 |
| typeorm | type-op/decimal | 14 | 0 | 0 |
| typeorm | type-op/double | 14 | 0 | 0 |
| typeorm | type-op/enum | 11 | 0 | 0 |
| typeorm | type-op/int | 14 | 0 | 0 |
| typeorm | type-op/text | 12 | 0 | 0 |
| typeorm | type-op/varchar | 12 | 0 | 0 |

## Failures by error signature

| Error code | Count | Likely meaning |
|---|--:|---|
| `1064` | 109 | SQL syntax / feature not supported by the parser |
| `20105` | 72 | function / operator not implemented |
| `20203` | 37 | stricter argument / type validation than MySQL |
| `BEHAVIOR` | 16 | ran OK but result differs from MySQL semantics |
| `20101` | 11 | internal "not implemented yet" (e.g. savepoint rollback) |
| `1149` | 9 | — |
| `1690` | 3 | numeric value out of range |
| `1105` | 2 | — |
| `TIMEOUT` | 2 | operation did not return within the scenario time cap |
| `1068` | 1 | multiple primary key defined |
| `20102` | 1 | — |
| `20301` | 1 | — |

## Top distinct failures

| Count | Code | Message (normalized) |
|--:|---|---|
| 56 | `20105` | not supported: function or operator '…' |
| 43 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 9 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 8 | `20203` | invalid argument parse timestamp, bad value 11:30:00 |
| 6 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 4 | `20203` | invalid argument parse timestamp, bad value 11:30:45 |
| 4 | `20203` | invalid argument cast to int, bad value 9.007199254740991e+15 |
| 4 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 4 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 4 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 4 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 2 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 2 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 2 | `20101` | internal error: unclassified statement appears in uncommitted transaction |
| 2 | `20203` | invalid argument operator +, bad value [TEXT TEXT] |
| 2 | `20101` | internal error: statement: '…' |
| 2 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 2 | `20203` | invalid argument cast to int, bad value |
| 2 | `20203` | invalid argument operator -, bad value [BOOL BOOL] |
| 2 | `20101` | internal error: do not support update primary key/unique key for on duplicate key update |
| 2 | `1105` | internal error: panic runtime error: invalid memory address or nil pointer dereference: runtime.panicmem /usr/local/go/src/runtime/panic.go:… |
| 2 | `TIMEOUT` | scenario exceeded 30000ms wall-clock cap (likely a non-returning MatrixOne operation) |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `20101` | internal error: unsupported alter option in inplace mode: check (id >= 0) |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `BEHAVIOR` | DELETE..JOIN left 0 rows; MySQL leaves 1 |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `20101` | internal error: savepoint has not been implemented yet. please rollback the transaction. |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `1064` | SQL parser error: You have an error in your SQL syntax; check the manual that corresponds to your MatrixOne server version for the right syn… |
| 1 | `BEHAVIOR` | '…'='…' returned 0 (utf8mb4_bin); MySQL returns 1 |
| 1 | `BEHAVIOR` | '…'='…' returned 0; MySQL ai_ci returns 1 |
| 1 | `BEHAVIOR` | '…'='…' returned 0; MySQL returns 1 |

## Failing scenarios by framework / category

| Framework / category | Fails | Error codes |
|---|--:|---|
| raw / func/json | 22 | 20105×22 |
| sequelize / op-type/DOUBLE | 17 | 1064×17 |
| sequelize / type-op/DOUBLE | 15 | 1064×15 |
| raw / behavior | 11 | BEHAVIOR×9, 20203×2 |
| raw / func-where/json | 11 | 20105×11 |
| sequelize / fulltype/DOUBLE | 11 | 1064×11 |
| knex / raw-func/json | 11 | 20105×11 |
| raw / func/spatial | 10 | 1064×10 |
| raw / func-where/aggregate | 9 | 1149×9 |
| sequelize / fulltype/UUID | 9 | 1064×9 |
| raw / json-path | 8 | 20105×8 |
| raw / func/datetime | 6 | 20203×6 |
| sequelize / datatype/sync | 6 | 1064×6 |
| sequelize / datatype/null | 6 | 1064×6 |
| raw / prepared-meta | 5 | 1064×3, 20101×2 |
| raw / func-where/spatial | 5 | 1064×5 |
| raw / collation-behavior | 5 | BEHAVIOR×5 |
| sequelize / datatype/insert | 5 | 1064×5 |
| sequelize / datatype/roundtrip | 5 | 1064×5 |
| raw / func/string | 4 | 20105×4 |
| raw / func/misc | 4 | 20105×4 |
| raw / info-schema | 4 | 1064×4 |
| raw / convert-target | 4 | 1064×2, 20101×2 |
| raw / project/vector | 4 | 20203×4 |
| raw / prepared-reuse/int | 4 | 20203×4 |
| raw / tx | 3 | 20101×3 |
| raw / func-where/datetime | 3 | 20203×3 |
| raw / func-table/datetime | 3 | 20203×3 |
| knex / raw-func/datetime | 3 | 20203×3 |
| raw / prepared-bind/int | 2 | 20203×2 |
| raw / func-where/string | 2 | 20105×2 |
| raw / func-where/misc | 2 | 20105×2 |
| raw / semantics | 2 | 20203×1, BEHAVIOR×1 |
| raw / func-table/string | 2 | 20105×2 |
| raw / arith-proj/bigint | 2 | 1690×2 |
| sequelize / json | 2 | 20105×1, 20301×1 |
| typeorm / soft-delete | 2 | TIMEOUT×2 |
| knex / query | 2 | 1064×1, 20101×1 |
| knex / col-modifier | 2 | 1064×2 |
| knex / raw-func/string | 2 | 20105×2 |
| knex / raw-func/misc | 2 | 20105×2 |
| raw / ddl | 1 | 1064×1 |
| raw / ddl-alter | 1 | 20101×1 |
| raw / ddl-index | 1 | 1064×1 |
| raw / dml | 1 | BEHAVIOR×1 |
| raw / query | 1 | 1064×1 |
| raw / prepared | 1 | 20203×1 |
| raw / prepared-type | 1 | 20203×1 |
| raw / query2 | 1 | 1064×1 |
| raw / cast-target | 1 | 1064×1 |
| raw / aggregate-table | 1 | 20203×1 |
| raw / coercion | 1 | 20203×1 |
| raw / set-op | 1 | 20102×1 |
| raw / math-args | 1 | 20203×1 |
| raw / index-type/enum | 1 | 20105×1 |
| raw / arith-proj/bigint_u | 1 | 1690×1 |
| raw / arith-proj/bool | 1 | 20203×1 |
| raw / arith-proj/boolean | 1 | 20203×1 |
| raw / mo-vector | 1 | 1064×1 |
| sequelize / crud | 1 | 20101×1 |
| sequelize / raw-query | 1 | 20203×1 |
| sequelize / type-op/ENUM | 1 | 1105×1 |
| sequelize / fulltype/INTEGER.UNSIGNED | 1 | 20203×1 |
| sequelize / fulltype/ENUM | 1 | 1105×1 |
| typeorm / crud | 1 | 20101×1 |
| typeorm / querybuilder | 1 | 1064×1 |
| typeorm / schema | 1 | 1068×1 |
| typeorm / qb-matrix | 1 | 1064×1 |

## Error signature × framework

| Code | knex | raw | sequelize | typeorm |
|---|--:|--:|--:|--:|
| 1064 | 3 | 30 | 74 | 2 |
| 20105 | 15 | 56 | 1 |  |
| 20203 | 3 | 32 | 2 |  |
| BEHAVIOR |  | 16 |  |  |
| 20101 | 1 | 8 | 1 | 1 |
| 1149 |  | 9 |  |  |
| 1690 |  | 3 |  |  |
| 1105 |  |  | 2 |  |
| TIMEOUT |  |  |  | 2 |
| 1068 |  |  |  | 1 |
| 20102 |  | 1 |  |  |
| 20301 |  |  | 1 |  |

