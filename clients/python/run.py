#!/usr/bin/env python
"""MatrixOne Python ORM compatibility suite — entrypoint.

Usage:
    python run.py                       # register + run full suite
    python run.py --only=sqlalchemy     # only one framework (raw|sqlalchemy|django|peewee)
    python run.py --only=raw,django     # multiple frameworks
    python run.py --limit=800           # run a stratified subset of N scenarios
    python run.py --count               # register only, print the count, don't run
    python run.py --verbose             # print every FAIL line
    python run.py --list-frameworks     # print available frameworks

Connection comes from MO_HOST/MO_PORT/MO_USER/MO_PASS (defaults
127.0.0.1/6001/root/111). Each framework runs in its own database namespace
(mo_py_raw / mo_py_sa / mo_py_dj / mo_py_pw), created and dropped by the harness.
"""

from __future__ import annotations

import argparse
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
if HERE not in sys.path:
    sys.path.insert(0, HERE)

from harness import Reporter, Runner  # noqa: E402
from harness import config  # noqa: E402
from harness import connections  # noqa: E402

# Map of framework key -> (module path, register fn name, db namespace)
FRAMEWORKS = {
    "raw": ("scenarios.raw_pymysql", config.DB_RAW),
    "sqlalchemy": ("scenarios.sqlalchemy_suite", config.DB_SA),
    "django": ("scenarios.django_suite", config.DB_DJ),
    "peewee": ("scenarios.peewee_suite", config.DB_PW),
}


def parse_args(argv):
    p = argparse.ArgumentParser(description="MatrixOne Python ORM compatibility suite")
    p.add_argument("--only", default=None,
                   help="comma list of frameworks: raw,sqlalchemy,django,peewee")
    p.add_argument("--limit", type=int, default=None,
                   help="run only N scenarios (stratified across fw/category)")
    p.add_argument("--count", action="store_true",
                   help="register scenarios, print the count, do not run")
    p.add_argument("--verbose", action="store_true")
    p.add_argument("--fail-fast", action="store_true",
                   help="stop on first harness-bug failure")
    p.add_argument("--list-frameworks", action="store_true")
    p.add_argument("--reports", default=os.path.join(HERE, "reports"))
    return p.parse_args(argv)


def main(argv=None):
    args = parse_args(argv or sys.argv[1:])

    if args.list_frameworks:
        print("Available frameworks:", ", ".join(FRAMEWORKS))
        return 0

    only = None
    if args.only:
        only = {x.strip() for x in args.only.split(",") if x.strip()}
        unknown = only - set(FRAMEWORKS)
        if unknown:
            print(f"Unknown frameworks: {unknown}. Choose from {set(FRAMEWORKS)}")
            return 2

    selected_keys = [k for k in FRAMEWORKS if (only is None or k in only)]

    runner = Runner(verbose=args.verbose, fail_fast=args.fail_fast)

    # Register scenarios. Each framework module exposes register(runner).
    print(f"Registering frameworks: {', '.join(selected_keys)}")
    for key in selected_keys:
        modpath, _db = FRAMEWORKS[key]
        try:
            mod = __import__(modpath, fromlist=["register"])
        except Exception as e:  # noqa: BLE001
            print(f"  ! failed to import {modpath}: {e}")
            raise
        before = runner.count()
        mod.register(runner)
        print(f"  {key:12s} +{runner.count() - before} scenarios")

    print(f"\nTotal registered scenarios: {runner.count()}")

    if args.count:
        return 0

    # Prepare databases (recreate the namespaces we'll use).
    print("Preparing database namespaces...")
    for key in selected_keys:
        _modpath, db = FRAMEWORKS[key]
        connections.ensure_database(db, drop_first=True)
        print(f"  {db} ready")

    # Build the selection (subset support) and run.
    selection = runner.select(only=only, limit=args.limit, stratified=True)
    print(f"\nRunning {len(selection)} scenarios "
          f"(of {runner.count()} registered)...\n")
    start = time.time()
    runner.run(selection)
    elapsed = time.time() - start
    print(f"\n\nFinished in {elapsed:.1f}s")

    # Report.
    engine = connections.server_version()
    reporter = Reporter(runner.results, args.reports, engine)
    summary = reporter.write()
    print(f"\nEngine: {engine}")
    print(f"Summary: total={summary['total']} pass={summary['pass']} "
          f"fail={summary['fail']} skip={summary['skip']}")

    # Harness-bug callout.
    bugs = [r for r in runner.results
            if r.status == "FAIL" and r.error_code == "HARNESS-BUG"]
    if bugs:
        print(f"\n!!! {len(bugs)} HARNESS-BUG failures (must be fixed):")
        for r in bugs[:30]:
            print(f"  #{r.id} {r.framework}/{r.category}/{r.name}: "
                  f"{(r.error_message or '')[:160]}")
    else:
        print("\n0 harness-bug failures — all FAILs are DB-driven incompatibilities.")

    print(f"\nReports written to {args.reports}/results.json and summary.md")
    return 0


if __name__ == "__main__":
    sys.exit(main())
