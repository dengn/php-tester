# MatrixOne ⇆ Java ORM Compatibility Suite

A runnable compatibility test suite that exercises **MatrixOne** (MySQL-wire
compatible) through the four most-used Java database stacks:

- **Raw JDBC** baseline (`com.mysql:mysql-connector-j`)
- **Hibernate ORM 6 / JPA** (Jakarta Persistence) — programmatic bootstrap
- **MyBatis** — annotation mappers + `SqlSessionFactory`
- **jOOQ** — DSL used *without* code generation (`DSL.using(connection)`)

The suite registers **5000+ scenarios** generated from data-type × operation
matrices, function/expression matrices, query-pattern matrices, JDBC-binding
matrices, per-type ORM mapping matrices, and hand-written ORM-feature tests.

Scenario **FAILs are expected and good** — they are MatrixOne compatibility
findings, not test bugs. The harness only treats Java/integration errors in its
own code as bugs (there are none).

## Requirements

- JDK 21, Maven
- A running MatrixOne reachable via the MySQL protocol

## Connection config (env, with defaults)

| Var | Default |
|---|---|
| `MO_HOST` | `127.0.0.1` |
| `MO_PORT` | `6001` |
| `MO_USER` | `root` |
| `MO_PASS` | `111` |

JDBC URL params used: `allowPublicKeyRetrieval=true&useSSL=false&allowMultiQueries=true`.
Each framework gets its own database namespace (`mo_java_jdbc`,
`mo_java_hibernate`, `mo_java_mybatis`, `mo_java_jooq`), created and dropped by
the harness.

## Run

Full suite (all frameworks, ~5 minutes):

```bash
mvn -q compile exec:java
```

Filter by framework and/or category:

```bash
mvn -q compile exec:java -Dexec.args="--only=hibernate"
mvn -q compile exec:java -Dexec.args="--only=jdbc,jooq"
mvn -q compile exec:java -Dexec.args="--category=type,function"
```

Representative subset (stride-sampled per framework, spans every category):

```bash
mvn -q compile exec:java -Dexec.args="--limit-per-framework=220"
```

Just print the registered scenario count and exit:

```bash
mvn -q compile exec:java -Dexec.args="--list"
```

## Output

- `reports/results.json` — `{engine, summary{total,pass,fail,skip}, results[...]}`
- `reports/summary.md` — pass/fail by framework & category, failures grouped by
  error signature, top distinct failure messages, and the BEHAVIOR-mismatch table.

## Error classification

The harness parses MatrixOne's numeric codes from `SQLException` messages
(`1064` parser, `20105` function/operator unimplemented, `20101`/`20301`
internal, `20203` strict arg validation, `1062` duplicate, `1690` overflow,
`1149` group-by), captures `getSQLState()`/`getErrorCode()`, and uses a dedicated
`BehaviorMismatch` exception (`error_code = "BEHAVIOR"`) for statements that run
OK but diverge from MySQL semantics (case-insensitive collation, `CHECK` no-op,
multi-table `DELETE..JOIN`, `LAST_INSERT_ID` after multi-row insert, `||` as
concat, etc.).
