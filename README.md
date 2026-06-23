# MatrixOne PHP ORM Compatibility Tester

A 1000+ scenario test harness that exercises the most popular PHP database/ORM
stacks against **[MatrixOne](https://www.matrixorigin.io/)** (a MySQL-wire-
compatible HTAP database) to surface compatibility issues.

**👉 The curated results are in [`FINDINGS.md`](FINDINGS.md).**

## What it tests

| Framework | What is exercised |
|---|---|
| **Raw PDO** (baseline) | data types, ~250 built-in functions, DDL/DML, joins/subqueries/CTEs/set-ops/window functions, constraints, indexes, transactions, introspection, MySQL-vs-MatrixOne **semantic behaviour**, the binary **prepared-statement** protocol, and MatrixOne-specific features (vector / full-text / upsert) |
| **Laravel Eloquent 12** | schema builder (Blueprint), query builder, models & casts, every relationship kind, transactions, pagination, chunking |
| **Doctrine ORM 3 / DBAL 4** | schema introspection, schema-tool DDL generation, typed round-trips, query builder, transactions, entities, DQL, associations, repositories, Paginator |
| **CakePHP 5** | query builder, schema reflection, typed binding, transactions |

Each scenario is isolated, timed, and classified as `PASS` / `FAIL` / `SKIP`.
A failure means MatrixOne **errored** or **diverged from MySQL semantics** — i.e.
a real compatibility issue.

## Latest run

```
1002 scenarios — 868 PASS, 134 FAIL  (86.6%)
Eloquent 95.5% · CakePHP 96.4% · Doctrine 90.9% · PDO baseline 84.4%
```

See [`reports/report.md`](reports/report.md) (human-readable) and
[`reports/results.json`](reports/results.json) (machine-readable).

## Running it

### Requirements
- PHP 8.2+ with `pdo_mysql`, Composer
- A reachable MatrixOne instance (default: `127.0.0.1:6001`, user `root` / `111`)

### Quick start against a local MatrixOne (Docker)
```bash
docker run -d --name matrixone -p 6001:6001 matrixorigin/matrixone:latest
# wait ~30s for it to become ready

composer install
php run.php
```

### Point it at any MatrixOne (e.g. the managed CloudSigma endpoint)
All connection settings are environment-overridable:
```bash
MO_HOST=database.omni.ruh.cloudsigma.com MO_PORT=6001 \
MO_USER='019db2db-5642-7112-ba4f-339ceded17e9:admin:accountadmin' \
MO_PASS='Test@2026' \
php run.php
```

### Useful flags
```bash
php run.php --verbose          # print every scenario, not just failures
php run.php --only=eloquent     # run a single provider (pdo-type, pdo-func,
                                # pdo-sql, pdo-behavior, pdo-prepared, pdo-extra,
                                # pdo-mo-feature, eloquent, doctrine, cake)
```

## Layout

```
run.php                       entry point: bootstrap DBs, register, run, report
src/
  Config.php                  env-overridable connection settings
  Connections.php             PDO + Eloquent/Doctrine/CakePHP connection factories
  Runner.php                  isolates, times and classifies each scenario
  Reporter.php                writes reports/report.md + results.json
  Support.php                 assertions & helpers
  Scenarios/                  the scenario providers (one per area)
  Models/                     Eloquent models (relationships)
  Entity/                     Doctrine entities (associations)
reports/                      generated report.md + results.json
FINDINGS.md                   curated, severity-ranked compatibility findings
```

## Adding scenarios

A scenario is any callable registered on the `Runner`. It returns a string
(detail) on success, throws `SkipScenario` to skip, throws `BehaviorMismatch`
for a semantic difference, or throws anything else for an error:

```php
$runner->add('Eloquent', 'category', 'name', function () {
    // ... do work against MatrixOne ...
    Support::assertEquals($expected, $actual, 'what');
    return 'ok';
});
```

> **Note:** This run targeted a local MatrixOne `v3.0.15` container because the
> execution environment's network policy blocks outbound port 6001 to the
> managed endpoint (only 80/443 are permitted). The suite is configured to run
> unchanged against the managed instance via the `MO_*` environment variables
> once that egress is allowed.
