"""Connection helpers and per-framework database namespace management."""

from __future__ import annotations

import contextlib

import pymysql

from . import config


def raw_connect(db: str | None = None):
    """Open a fresh pymysql connection, optionally selecting a database."""
    kwargs = config.base_dsn_kwargs()
    if db is not None:
        kwargs["database"] = db
    return pymysql.connect(**kwargs)


@contextlib.contextmanager
def cursor(db: str | None = None):
    conn = raw_connect(db)
    try:
        cur = conn.cursor()
        yield cur, conn
    finally:
        with contextlib.suppress(Exception):
            conn.close()


def ensure_database(db: str, drop_first: bool = False) -> None:
    """Create (optionally recreate) a database namespace."""
    with cursor() as (cur, _conn):
        if drop_first:
            cur.execute(f"DROP DATABASE IF EXISTS `{db}`")
        cur.execute(f"CREATE DATABASE IF NOT EXISTS `{db}`")


def drop_database(db: str) -> None:
    with cursor() as (cur, _conn):
        with contextlib.suppress(Exception):
            cur.execute(f"DROP DATABASE IF EXISTS `{db}`")


def server_version() -> str:
    try:
        with cursor() as (cur, _conn):
            cur.execute("SELECT version()")
            row = cur.fetchone()
            if row:
                return str(row[0])
    except Exception:
        pass
    return config.server_version()
