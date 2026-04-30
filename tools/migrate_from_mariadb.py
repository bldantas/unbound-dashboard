#!/usr/bin/env python3
"""
Migração única: MariaDB/MySQL → DuckDB.

Uso:
    MYSQL_URL=mysql+pymysql://user:pass@host/dbname python tools/migrate_from_mariadb.py

Requer:
    pip install pymysql
    (ou uv add pymysql --dev)

Tabelas migradas:
  - query_logs  → query_logs (DuckDB)
  - daily_stats → daily_stats (DuckDB)
  - alerts      → alerts (DuckDB)
  - settings    → settings (DuckDB)
  - users       → users (DuckDB)
"""

from __future__ import annotations

import asyncio
import os
import sys
import time
from typing import Any

# Verificar dependências
try:
    import pymysql  # type: ignore
    import duckdb
except ImportError as exc:
    print(f"Dependência faltando: {exc}")
    print("Execute: uv add pymysql  (ou pip install pymysql)")
    sys.exit(1)

MYSQL_URL = os.environ.get("MYSQL_URL", "")
if not MYSQL_URL:
    print("Defina a variável MYSQL_URL antes de executar este script.")
    sys.exit(1)

from app.core.config import settings
from app.db import run_migrations

CHUNK_SIZE = int(os.environ.get("CHUNK_SIZE", "100000"))


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def parse_mysql_url(url: str) -> dict[str, Any]:
    """Extrai host/port/user/password/db de uma URL mysql+pymysql://..."""
    from urllib.parse import urlparse
    parsed = urlparse(url)
    return {
        "host": parsed.hostname or "127.0.0.1",
        "port": parsed.port or 3306,
        "user": parsed.username or "root",
        "password": parsed.password or "",
        "database": (parsed.path or "/").lstrip("/"),
        "cursorclass": pymysql.cursors.DictCursor,
    }


def mysql_connect() -> pymysql.connections.Connection:
    params = parse_mysql_url(MYSQL_URL)
    return pymysql.connect(**params)


# ---------------------------------------------------------------------------
# Migration steps
# ---------------------------------------------------------------------------

def migrate_query_logs(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    offset = 0
    total = 0
    while True:
        mysql_cur.execute(
            "SELECT timestamp, client_ip, domain, query_type, action, blocked "
            "FROM query_logs ORDER BY timestamp LIMIT %s OFFSET %s",
            (CHUNK_SIZE, offset),
        )
        rows = mysql_cur.fetchall()
        if not rows:
            break
        data = [
            (
                r["timestamp"],
                r["client_ip"],
                r["domain"],
                r.get("query_type", "A"),
                r["action"],
                bool(r.get("blocked", False)),
            )
            for r in rows
        ]
        duck.executemany(
            "INSERT OR IGNORE INTO query_logs "
            "(timestamp, client_ip, domain, query_type, action, blocked) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            data,
        )
        total += len(rows)
        offset += CHUNK_SIZE
        print(f"  query_logs: {total:,} linhas migradas…", end="\r")
    print()
    return total


def migrate_daily_stats(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    mysql_cur.execute(
        "SELECT date, total, blocked, resolved, cache_hits FROM daily_stats"
    )
    rows = mysql_cur.fetchall()
    for r in rows:
        duck.execute(
            "INSERT INTO daily_stats (date, total, blocked, resolved, cache_hits) "
            "VALUES (?, ?, ?, ?, ?) ON CONFLICT (date) DO UPDATE SET "
            "total=EXCLUDED.total, blocked=EXCLUDED.blocked, "
            "resolved=EXCLUDED.resolved, cache_hits=EXCLUDED.cache_hits",
            (r["date"], r["total"], r["blocked"], r["resolved"], r.get("cache_hits", 0)),
        )
    return len(rows)


def migrate_alerts(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    mysql_cur.execute(
        "SELECT id, severity, message, read_at, created_at FROM alerts"
    )
    rows = mysql_cur.fetchall()
    for r in rows:
        ts = r["created_at"].timestamp() if r["created_at"] else time.time()
        duck.execute(
            "INSERT OR IGNORE INTO alerts (id, severity, message, is_read, created_at) "
            "VALUES (?, ?, ?, ?, ?)",
            (r["id"], r["severity"], r["message"], r["read_at"] is not None, ts),
        )
    return len(rows)


def migrate_settings(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    mysql_cur.execute("SELECT `key`, `value` FROM settings")
    rows = mysql_cur.fetchall()
    for r in rows:
        duck.execute(
            "INSERT INTO settings (key, value) VALUES (?, ?) "
            "ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value",
            (r["key"], r["value"]),
        )
    return len(rows)


def migrate_users(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    mysql_cur.execute(
        "SELECT id, username, password_hash, role, is_active, "
        "failed_logins, locked_until, created_at FROM users"
    )
    rows = mysql_cur.fetchall()
    for r in rows:
        duck.execute(
            "INSERT OR IGNORE INTO users "
            "(id, username, password_hash, role, is_active, "
            "failed_logins, locked_until, created_at) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            (
                r["id"],
                r["username"],
                r["password_hash"],
                r.get("role", "viewer"),
                r.get("is_active", True),
                r.get("failed_logins", 0),
                r.get("locked_until"),
                r.get("created_at"),
            ),
        )
    return len(rows)


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

def validate(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> None:
    tables = ["query_logs", "daily_stats", "alerts", "settings", "users"]
    print("\nValidação de contagens:")
    all_ok = True
    for table in tables:
        try:
            mysql_cur.execute(f"SELECT COUNT(*) as n FROM {table}")
            mysql_count = mysql_cur.fetchone()["n"]
        except Exception:
            continue
        try:
            duck_count = duck.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]  # type: ignore
        except Exception:
            duck_count = -1

        ok = "✓" if mysql_count == duck_count else "✗"
        print(f"  {ok} {table}: MySQL={mysql_count:,}  DuckDB={duck_count:,}")
        if mysql_count != duck_count:
            all_ok = False

    if all_ok:
        print("\nMigração validada com sucesso.")
    else:
        print("\n⚠ Discrepâncias encontradas — verifique os logs acima.")
        sys.exit(1)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

async def main() -> None:
    print(f"Destino DuckDB: {settings.db_path}")
    print("Aplicando migrations DuckDB…")
    await run_migrations()

    print("Conectando ao MySQL…")
    my = mysql_connect()
    duck = duckdb.connect(str(settings.db_path))

    try:
        cur = my.cursor()
        print(f"\nMigrando em chunks de {CHUNK_SIZE:,} linhas\n")

        steps: list[tuple[str, Any]] = [
            ("query_logs", lambda: migrate_query_logs(cur, duck)),
            ("daily_stats", lambda: migrate_daily_stats(cur, duck)),
            ("alerts",      lambda: migrate_alerts(cur, duck)),
            ("settings",    lambda: migrate_settings(cur, duck)),
            ("users",       lambda: migrate_users(cur, duck)),
        ]

        for name, fn in steps:
            t0 = time.time()
            n = fn()
            duck.execute("CHECKPOINT")
            elapsed = time.time() - t0
            print(f"  {name}: {n:,} registros em {elapsed:.1f}s")

        validate(cur, duck)

    finally:
        duck.close()
        my.close()


if __name__ == "__main__":
    asyncio.run(main())
