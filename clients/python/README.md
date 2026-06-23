# MatrixOne ⇆ Python ORM Compatibility Suite

A runnable compatibility test suite (>= 5000 scenarios) that exercises the most
widely used Python MySQL stacks against **MatrixOne** (MySQL-wire-compatible):

- **Raw PyMySQL** driver baseline (types, functions, DDL, DML, queries,
  transactions, prepared statements, semantics)
- **SQLAlchemy 2.x** (Core + ORM)
- **Django ORM** (standalone settings, schema generation, QuerySet API)
- **Peewee** (extra coverage)

The suite's purpose is to **surface incompatibilities**: scenario FAILs are
expected and good. Only failures caused by bugs in the harness itself would be
problems — and the harness flags those distinctly (`HARNESS-BUG`) so they can be
driven to zero.

## Setup

```bash
python -m venv .venv
.venv/bin/pip install -r requirements.txt
```

## Run

```bash
.venv/bin/python run.py                 # register + run the full suite (~4 min)
.venv/bin/python run.py --count         # just print the registered scenario count
.venv/bin/python run.py --only=sqlalchemy
.venv/bin/python run.py --only=raw,django
.venv/bin/python run.py --limit=800     # stratified subset spanning every fw/category
.venv/bin/python run.py --verbose       # print every FAIL line
.venv/bin/python run.py --list-frameworks
```

## Connection

Configured via environment (defaults shown):

```
MO_HOST=127.0.0.1  MO_PORT=6001  MO_USER=root  MO_PASS=111
```

Each framework runs in its own database namespace (`mo_py_raw`, `mo_py_sa`,
`mo_py_dj`, `mo_py_pw`), created and dropped by the harness, so runs are
isolated and repeatable.

## Output

- `reports/results.json` — machine-readable: `{engine, summary, results[]}`
  with each result `{framework, category, name, status, error_code,
  error_message}` (message truncated to ~200 chars).
- `reports/summary.md` — pass/fail by framework and category, failures by error
  signature, a harness-bug callout (must be zero), and the top distinct failure
  messages.

## Error classification

MatrixOne surfaces MySQL-style numeric codes (`1064`, `1062`, `20101`, `20105`,
`20203`, ...) which the runner extracts from driver errors. A scenario that runs
without error but returns a result that differs from MySQL semantics raises
`BehaviorMismatch` and is recorded as `BEHAVIOR`. Pure-Python exceptions in the
suite's own code (that never reached the database) are flagged `HARNESS-BUG`.

## Layout

```
harness/        runner, reporter, config, connections, matrices (generators)
scenarios/      raw_pymysql, sqlalchemy_suite, django_suite, peewee_suite
run.py          entrypoint
requirements.txt
reports/        results.json + summary.md (generated)
```
