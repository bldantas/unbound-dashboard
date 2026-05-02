#!/usr/bin/env python3
"""Migração Big Bang: MariaDB/MySQL (v1) -> DuckDB (v2).

Uso:
    MYSQL_URL='mysql+pymysql://user:pass@host:3306/unbound_dash' \
    /opt/unbound-dashboard/.venv/bin/python tools/migrate_from_mariadb.py --truncate

Notas:
- Redis nao e migrado (cache/pubsub volatil).
- blocklist legada e exportada para CSV para reaplicacao na v2.
"""

from __future__ import annotations

import argparse
import csv
import os
import re
import subprocess
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

from app.core.config import settings
from app.db import run_migrations

CHUNK_SIZE = int(os.environ.get("CHUNK_SIZE", "50000"))
MYSQL_URL = os.environ.get("MYSQL_URL", "")

SEVERITY_MAP = {
    "info": "info",
    "warning": "warning",
    "warn": "warning",
    "critical": "critical",
    "error": "critical",
}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def parse_mysql_url(url: str) -> dict[str, Any]:
    """Extrai host/port/user/password/db de MYSQL_URL."""
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


def mysql_table_exists(cur: Any, table: str) -> bool:
    cur.execute("SHOW TABLES LIKE %s", (table,))
    return cur.fetchone() is not None


def mysql_columns(cur: Any, table: str) -> set[str]:
    cur.execute(f"SHOW COLUMNS FROM {table}")
    rows = cur.fetchall()
    return {r["Field"] for r in rows}


def normalize_role(role: Any) -> str:
    if str(role).lower() == "admin":
        return "admin"
    return "viewer"


def normalize_severity(value: Any) -> str:
    key = str(value or "warning").strip().lower()
    return SEVERITY_MAP.get(key, "warning")


def safe_resolved(total: int, blocked: int, cache_hits: int) -> int:
    v = int(total) - int(blocked) - int(cache_hits)
    return v if v > 0 else 0


# ---------------------------------------------------------------------------
# Migration steps
# ---------------------------------------------------------------------------

def migrate_query_logs(mysql_cur: Any, duck: duckdb.DuckDBPyConnection, chunk_size: int) -> int:
    if not mysql_table_exists(mysql_cur, "query_logs"):
        return 0

    mysql_cur.execute("SELECT COALESCE(MIN(id), 0) AS min_id, COALESCE(MAX(id), 0) AS max_id FROM query_logs")
    row = mysql_cur.fetchone()
    min_id = int(row["min_id"])
    max_id = int(row["max_id"])
    if max_id == 0:
        return 0

    total = 0
    cursor = min_id
    while cursor <= max_id:
        upper = cursor + chunk_size - 1
        mysql_cur.execute(
            "SELECT timestamp, client_ip, domain, query_type, action "
            "FROM query_logs WHERE id BETWEEN %s AND %s ORDER BY id",
            (cursor, upper),
        )
        rows = mysql_cur.fetchall()
        if rows:
            data = [
                (
                    int(r["timestamp"]),
                    str(r["client_ip"]),
                    str(r["domain"]),
                    str(r.get("query_type") or "A"),
                    str(r.get("action") or "resolved"),
                )
                for r in rows
            ]
            duck.executemany(
                "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) "
                "VALUES (?, ?, ?, ?, ?)",
                data,
            )
            total += len(rows)
            print(f"  query_logs: {total:,} linhas migradas...", end="\r")
        cursor = upper + 1

    print()
    return total


def migrate_daily_stats(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    if not mysql_table_exists(mysql_cur, "daily_stats"):
        return 0

    cols = mysql_columns(mysql_cur, "daily_stats")
    if {"stat_date", "total_queries", "blocked_count", "cache_hits"}.issubset(cols):
        mysql_cur.execute(
            "SELECT stat_date, total_queries, blocked_count, cache_hits FROM daily_stats"
        )
        rows = mysql_cur.fetchall()
        for r in rows:
            total = int(r.get("total_queries") or 0)
            blocked = int(r.get("blocked_count") or 0)
            cache_hits = int(r.get("cache_hits") or 0)
            resolved = safe_resolved(total, blocked, cache_hits)
            duck.execute(
                "INSERT INTO daily_stats (date, total, blocked, resolved, cache_hits) "
                "VALUES (?, ?, ?, ?, ?) ON CONFLICT (date) DO UPDATE SET "
                "total=EXCLUDED.total, blocked=EXCLUDED.blocked, "
                "resolved=EXCLUDED.resolved, cache_hits=EXCLUDED.cache_hits",
                (r["stat_date"], total, blocked, resolved, cache_hits),
            )
        return len(rows)

    mysql_cur.execute("SELECT date, total, blocked, resolved, cache_hits FROM daily_stats")
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
    if not mysql_table_exists(mysql_cur, "alerts"):
        return 0

    cols = mysql_columns(mysql_cur, "alerts")
    if {"type", "severity", "message", "started_at", "resolved_at", "is_dismissed"}.issubset(cols):
        mysql_cur.execute(
            "SELECT type, severity, message, started_at, resolved_at, is_dismissed "
            "FROM alerts ORDER BY started_at"
        )
        rows = mysql_cur.fetchall()
        for r in rows:
            is_read = r.get("resolved_at") is not None or bool(r.get("is_dismissed"))
            created_at = r.get("started_at")
            if created_at is None:
                created_at = time.strftime("%Y-%m-%d %H:%M:%S")
            duck.execute(
                "INSERT INTO alerts (type, message, severity, is_read, created_at) "
                "VALUES (?, ?, ?, ?, ?)",
                (
                    str(r.get("type") or "legacy"),
                    str(r.get("message") or "Alerta migrado da v1"),
                    normalize_severity(r.get("severity")),
                    is_read,
                    created_at,
                ),
            )
        return len(rows)

    mysql_cur.execute("SELECT type, severity, message, is_read, created_at FROM alerts")
    rows = mysql_cur.fetchall()
    for r in rows:
        duck.execute(
            "INSERT INTO alerts (type, message, severity, is_read, created_at) VALUES (?, ?, ?, ?, ?)",
            (
                str(r.get("type") or "legacy"),
                str(r.get("message") or "Alerta migrado"),
                normalize_severity(r.get("severity")),
                bool(r.get("is_read")),
                r.get("created_at"),
            ),
        )
    return len(rows)


def migrate_settings(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> int:
    if not mysql_table_exists(mysql_cur, "settings"):
        return 0

    cols = mysql_columns(mysql_cur, "settings")
    if {"setting_key", "setting_value"}.issubset(cols):
        mysql_cur.execute("SELECT setting_key, setting_value FROM settings")
        rows = mysql_cur.fetchall()
        for r in rows:
            key = str(r["setting_key"])
            value = str(r["setting_value"])
            duck.execute(
                "INSERT INTO settings (key, value) VALUES (?, ?) "
                "ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value",
                (key, value),
            )
        return len(rows)

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
    if not mysql_table_exists(mysql_cur, "users"):
        return 0

    mysql_cur.execute(
        "SELECT id, username, password_hash, role, email, is_active, failed_logins, "
        "locked_until, created_at FROM users ORDER BY id"
    )
    rows = mysql_cur.fetchall()
    max_id = 0
    for r in rows:
        uid = int(r["id"])
        if uid > max_id:
            max_id = uid
        duck.execute(
            "INSERT INTO users "
            "(id, username, password_hash, role, email, is_active, failed_logins, "
            "locked_until, created_at, updated_at) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) "
            "ON CONFLICT (username) DO UPDATE SET "
            "password_hash=EXCLUDED.password_hash, role=EXCLUDED.role, email=EXCLUDED.email, "
            "is_active=EXCLUDED.is_active, failed_logins=EXCLUDED.failed_logins, "
            "locked_until=EXCLUDED.locked_until, updated_at=EXCLUDED.updated_at",
            (
                uid,
                r["username"],
                r["password_hash"],
                normalize_role(r.get("role", "viewer")),
                r.get("email"),
                r.get("is_active", True),
                r.get("failed_logins", 0),
                r.get("locked_until"),
                r.get("created_at"),
                r.get("created_at"),
            ),
        )

    if max_id > 0:
        duck.execute(f"ALTER SEQUENCE users_id_seq RESTART WITH {max_id + 1}")
    return len(rows)


def export_domain_blacklist(mysql_cur: Any, csv_path: str) -> int:
    if not mysql_table_exists(mysql_cur, "domain_blacklist"):
        return 0

    mysql_cur.execute(
        "SELECT domain, category, severity FROM domain_blacklist ORDER BY domain"
    )
    rows = mysql_cur.fetchall()
    with open(csv_path, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["domain", "category", "severity"])
        for r in rows:
            writer.writerow([
                str(r.get("domain") or ""),
                str(r.get("category") or ""),
                str(r.get("severity") or ""),
            ])
    return len(rows)


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

def validate(mysql_cur: Any, duck: duckdb.DuckDBPyConnection) -> None:
    tables = ["query_logs", "daily_stats", "alerts", "settings", "users"]
    print("\nValidação de contagens:")
    all_ok = True
    for table in tables:
        if not mysql_table_exists(mysql_cur, table):
            continue
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
        print("\nMigracao validada com sucesso.")
    else:
        print("\nDiscrepancias encontradas - verifique os logs acima.")
        sys.exit(1)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def truncate_destination(duck: duckdb.DuckDBPyConnection) -> None:
    for table in ("query_logs", "daily_stats", "alerts", "settings", "users", "blocklist_hits"):
        duck.execute(f"DELETE FROM {table}")
    duck.execute("ALTER SEQUENCE users_id_seq RESTART WITH 1")
    duck.execute("ALTER SEQUENCE alerts_id_seq RESTART WITH 1")
    duck.execute("CHECKPOINT")


def main() -> None:
    parser = argparse.ArgumentParser(description="Migracao Big Bang MariaDB/MySQL -> DuckDB")
    parser.add_argument("--chunk-size", type=int, default=CHUNK_SIZE)
    parser.add_argument("--truncate", action="store_true", help="Limpa tabelas de destino antes de migrar")
    parser.add_argument(
        "--export-blocklist",
        default="/var/backups/unbound-dashboard/v1-domain-blacklist.csv",
        help="Caminho para exportar domain_blacklist legado em CSV",
    )
    args = parser.parse_args()

    if not MYSQL_URL:
        print("Defina MYSQL_URL antes de executar este script.")
        sys.exit(1)

    print(f"Destino DuckDB: {settings.db_path}")
    print("Aplicando migrations DuckDB...")
    run_migrations()

    print("Conectando ao MySQL...")
    my = mysql_connect()
    duck = duckdb.connect(str(settings.db_path))

    try:
        if args.truncate:
            print("Limpando destino (truncate)...")
            truncate_destination(duck)

        cur = my.cursor()
        print(f"\nMigrando em chunks de {args.chunk_size:,} linhas\n")

        steps: list[tuple[str, Any]] = [
            ("query_logs", lambda: migrate_query_logs(cur, duck, args.chunk_size)),
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

        exported = export_domain_blacklist(cur, args.export_blocklist)
        print(f"  domain_blacklist exportado: {exported:,} linhas -> {args.export_blocklist}")

        validate(cur, duck)

    finally:
        duck.close()
        my.close()


if __name__ == "__main__":
    main()
