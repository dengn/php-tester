"""Connection configuration for the MatrixOne compatibility suite.

Every value is overridable through the environment so the identical suite can be
pointed at any MatrixOne (or MySQL) endpoint:

    MO_HOST  (default 127.0.0.1)
    MO_PORT  (default 6001)
    MO_USER  (default root)
    MO_PASS  (default 111)

Each framework runs inside its own database namespace (mo_py_raw, mo_py_sa,
mo_py_dj, mo_py_pw) so runs are isolated and repeatable.
"""

from __future__ import annotations

import os


def host() -> str:
    return os.environ.get("MO_HOST", "127.0.0.1")


def port() -> int:
    return int(os.environ.get("MO_PORT", "6001"))


def user() -> str:
    return os.environ.get("MO_USER", "root")


def password() -> str:
    return os.environ.get("MO_PASS", "111")


# Per-framework database namespaces.
DB_RAW = os.environ.get("MO_DB_RAW", "mo_py_raw")
DB_SA = os.environ.get("MO_DB_SA", "mo_py_sa")
DB_DJ = os.environ.get("MO_DB_DJ", "mo_py_dj")
DB_PW = os.environ.get("MO_DB_PW", "mo_py_pw")
# New scenario namespaces (real-world workloads + additional ORMs).
DB_APP = os.environ.get("MO_DB_APP", "mo_py_app")
DB_SM = os.environ.get("MO_DB_SM", "mo_py_sqlmodel")
DB_PONY = os.environ.get("MO_DB_PONY", "mo_py_pony")
DB_TORT = os.environ.get("MO_DB_TORT", "mo_py_tortoise")
DB_AL = os.environ.get("MO_DB_AL", "mo_py_alembic")

ALL_DBS = [DB_RAW, DB_SA, DB_DJ, DB_PW, DB_APP, DB_SM, DB_PONY, DB_TORT, DB_AL]


def base_dsn_kwargs() -> dict:
    """Keyword args for pymysql.connect() with no database selected."""
    return dict(
        host=host(),
        port=port(),
        user=user(),
        password=password(),
        charset="utf8mb4",
        autocommit=True,
        connect_timeout=15,
        read_timeout=60,
        write_timeout=60,
    )


def sqlalchemy_url(db: str) -> str:
    from urllib.parse import quote_plus

    return (
        f"mysql+pymysql://{quote_plus(user())}:{quote_plus(password())}"
        f"@{host()}:{port()}/{db}?charset=utf8mb4"
    )


def server_version() -> str:
    return os.environ.get("MO_SERVER_VERSION", "8.0.30-MatrixOne-v4.0.0-rc3")
