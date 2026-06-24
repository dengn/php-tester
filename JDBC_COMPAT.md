# JDBC API conformance — MatrixOne (vs TiDB)

A driver-level test of the **`java.sql.*` (JDBC 4.2) feature surface** via
`mysql-connector-j 9.1.0`, independent of any ORM. Each feature is exercised
and classified **WORKS / UNSUPPORTED (driver throws `SQLFeatureNotSupported`) /
BROKEN (server error)**. Probe: `clients/java/jdbcapi/JdbcApiTest.java`.

| Engine | version() | WORKS | UNSUPPORTED | BROKEN |
|---|---|--:|--:|--:|
| **MatrixOne** | `8.0.30-MatrixOne-v4.0.0-rc3` | 122 | 2 | 8 |
| **TiDB** | `8.0.11-TiDB-v8.5.3` | 125 | 5 | 2 |

(132 behavioural checks + 27 capability flags. Both ≈ 92–95% conformant.)

## ⚠️ Capability flags reflect the **driver**, not the engine

`DatabaseMetaData.supportsXxx()` is reported by Connector/J (which assumes a
MySQL server) and is **identical for MatrixOne and TiDB** — e.g. it advertises:

```
savepoints = YES   storedProcedures = YES   batchUpdates = YES
getGeneratedKeys = YES   RS:CONCUR_UPDATABLE = YES   multipleResultSets = YES
RS:SCROLL_SENSITIVE = no   namedParameters = no   fullOuterJoins = no
```

So the driver **claims** savepoints / stored procedures / updatable result sets
are supported — but those operations actually **fail on MatrixOne**. The flags
are not a reliable guide to MatrixOne's real JDBC behaviour; the behavioural
checks below are.

## Feature-by-feature (the non-WORKS items)

| JDBC feature | MatrixOne | TiDB | Verdict |
|---|---|---|---|
| `getImportedKeys` (FK metadata) | ❌ BROKEN — no FK rows returned | ✅ WORKS | **MatrixOne-specific** |
| Updatable `ResultSet` `updateRow` | ❌ BROKEN — *"not updatable (references no primary keys)"* | ✅ WORKS | **MatrixOne-specific** (driver can't see the PK in MO's RS metadata) |
| Updatable `ResultSet` `insertRow` | ❌ BROKEN — same | ✅ WORKS | **MatrixOne-specific** |
| `setSavepoint(name)` / `rollback(Savepoint)` | ❌ BROKEN — *"savepoint has not been implemented yet"* | ✅ WORKS | **MatrixOne-specific** |
| `{? = call abs(?)}` function out-param | ❌ BROKEN — *"user variable …outparam_0 does not exist"* | ✅ WORKS | **MatrixOne-specific** |
| Stored procedure `{call p()}` | ❌ BROKEN — *"unclassified statement in uncommitted transaction"* | ⚠️ UNSUPPORTED — *"Unsupported type …"* | **Both lack stored procedures** |
| `ParameterMetaData.getParameterType` | ❌ BROKEN | ❌ BROKEN | **Driver default** (Connector/J needs `generateSimpleParameterMetadata`) — not engine-specific |
| `createArrayOf` | ⚠️ UNSUPPORTED | ⚠️ UNSUPPORTED | **Driver-level** (Connector/J) |
| `ResultSet.getType/getConcurrency/getHoldability` | ⚠️ UNSUPPORTED | ⚠️ UNSUPPORTED | **Driver-level** (Connector/J) |
| `setTransactionIsolation(READ_UNCOMMITTED / SERIALIZABLE)` | ✅ WORKS | ⚠️ UNSUPPORTED — needs `tidb_skip_isolation_level_check` | TiDB-specific (MatrixOne is more permissive here) |
| `setReadOnly` | ✅ WORKS | ❌ BROKEN — *"READ ONLY has only noop implementation … enable tidb_enable_noop_functions"* | TiDB-specific |

## What works on MatrixOne (the large majority)

- **Connection:** autocommit, all four `TRANSACTION_*` isolation levels, readOnly, isValid, get/setCatalog & Schema, holdability, nativeSQL, networkTimeout, clientInfo, createBlob/createClob, typeMap, warnings.
- **Statement:** execute/executeUpdate/executeLargeUpdate, multiple result sets (`getMoreResults`), `setMaxRows`, streaming `fetchSize=Integer.MIN_VALUE`, queryTimeout, maxFieldSize, closeOnCompletion, poolable, JDBC `{fn …}` escapes.
- **PreparedStatement:** all 22 `setXxx` type setters incl. `setObject(LocalDate/LocalDateTime/LocalTime)`, `setNull`, `setBinaryStream`, `setCharacterStream`; `getMetaData` before execute; prepared batch.
- **ResultSet:** all 23 `getXxx` incl. `getObject(Class)`, `getObject(LocalDate/LocalDateTime)`, streams, `wasNull`, `findColumn`; forward + **`SCROLL_INSENSITIVE`** navigation (last/first/absolute/relative/previous).
- **ResultSetMetaData:** full surface incl. correct `isAutoIncrement`.
- **Transactions:** commit, rollback, all isolation levels. **Batch** (Statement & Prepared, `executeLargeBatch`, `BatchUpdateException`). **Generated keys** (Statement & Prepared). **LOB** (Blob/Clob create + column round-trip). Exceptions/SQLState/errorCode, warnings, wrapper.

## Conclusion

MatrixOne's **driver-level JDBC compatibility is high (~92%)** — the everyday
JDBC surface (connections, statements, prepared statements & all type
binding, result sets & all getters, metadata, batch, generated keys, LOBs,
isolation levels, transactions) works. The **MatrixOne-specific JDBC gaps** are
a small, coherent set, all matching the SQL-level findings:

1. **Foreign-key metadata** not exposed (`getImportedKeys`) → also breaks
   `DatabaseMetaData`-driven tooling.
2. **Updatable result sets** fail — Connector/J can't locate the primary key in
   MatrixOne's result-set metadata.
3. **Savepoints** (`setSavepoint`/`rollback(sp)`) not implemented.
4. **Stored procedures / function out-parameters** unsupported (stored procs are
   also unsupported by TiDB).

Everything else that "fails" is either a Connector/J driver limitation (same on
TiDB/MySQL) or, for TiDB, its own stricter isolation/readOnly handling.

---

*Reproduce:*
```bash
cd clients/java/jdbcapi
JAR=$(find ~/.m2 -name 'mysql-connector-j-9.1.0.jar' | head -1)
javac JdbcApiTest.java
java -cp ".:$JAR" JdbcApiTest 127.0.0.1 6001 root 111      # MatrixOne
java -cp ".:$JAR" JdbcApiTest 127.0.0.1 4000 root 111      # TiDB
```
Full probe output: `comparison/jdbc/`.
