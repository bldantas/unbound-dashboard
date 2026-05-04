"""
Compara contagens de query_logs em MariaDB vs DuckDB em janelas temporais
recentes. Usado pra confirmar que o `log_watcher` está em paridade com o
`unbound-logger.service` (PHP log_ingester) antes do cutover.

Uso:
    cd /var/www/html/unbound-dashboard/api_service
    MARIADB_PASS=unbounddash .venv/bin/python -m tools.check_dual_write_parity

Saída: tabela por janela + exit code 0 (paridade) ou 1 (drift > threshold).

Variáveis:
    MARIADB_HOST     default 127.0.0.1
    MARIADB_PORT     default 3306
    MARIADB_DB       default unbound_dash
    MARIADB_USER     default unbounddb
    MARIADB_PASS     OBRIGATÓRIO
    DRIFT_THRESHOLD  default 0.05 (5%)
    DB_PATH          herda do api_service settings
"""

from __future__ import annotations

import os
import sys
import time

import duckdb
import pymysql
import pymysql.cursors

from app.core.config import settings


def _open_mariadb() -> pymysql.connections.Connection:
    password = os.environ.get("MARIADB_PASS")
    if not password:
        print("ERRO: MARIADB_PASS não definido", file=sys.stderr)
        sys.exit(2)
    return pymysql.connect(
        host=os.environ.get("MARIADB_HOST", "127.0.0.1"),
        port=int(os.environ.get("MARIADB_PORT", "3306")),
        user=os.environ.get("MARIADB_USER", "unbounddb"),
        password=password,
        database=os.environ.get("MARIADB_DB", "unbound_dash"),
        cursorclass=pymysql.cursors.DictCursor,
        charset="utf8mb4",
    )


def _count_mariadb(conn, since_ts: int) -> int:
    with conn.cursor() as c:
        c.execute(
            "SELECT COUNT(*) AS n FROM query_logs WHERE timestamp >= %s", (since_ts,)
        )
        return int(c.fetchone()["n"])


def _count_duckdb(db_path: str, since_ts: int) -> int:
    with duckdb.connect(db_path) as conn:
        return int(
            conn.execute(
                "SELECT COUNT(*) FROM query_logs WHERE timestamp >= ?", [since_ts]
            ).fetchone()[0]
        )


def main() -> int:
    threshold = float(os.environ.get("DRIFT_THRESHOLD", "0.05"))
    windows_minutes = [5, 30, 60]

    mysql_conn = _open_mariadb()
    now = int(time.time())

    header = (
        f"\n{'janela':<10} {'MariaDB':>10} {'DuckDB':>10}"
        f" {'diff':>10} {'drift%':>8} {'status':>8}"
    )
    print(header)
    print("-" * 60)
    drifts: list[float] = []
    for minutes in windows_minutes:
        since = now - (minutes * 60)
        m = _count_mariadb(mysql_conn, since)
        d = _count_duckdb(settings.db_path, since)
        diff = abs(m - d)
        # Usa o maior como denominador (lado mais "verdadeiro" estatisticamente)
        denom = max(m, d, 1)
        drift = diff / denom
        status = "OK" if drift <= threshold else "DRIFT"
        drifts.append(drift)
        print(
            f"{minutes:>4}min   {m:>10,} {d:>10,}"
            f" {diff:>10,} {drift * 100:>7.2f}% {status:>8}"
        )

    mysql_conn.close()

    max_drift = max(drifts) if drifts else 0.0
    print(f"\nMáximo drift: {max_drift * 100:.2f}% (threshold: {threshold * 100:.0f}%)")
    if max_drift > threshold:
        print("⚠️  DRIFT excessivo — investigar antes de cutover do unbound-logger.service")
        return 1
    print("✓ Paridade dentro do threshold — seguro para cutover")
    return 0


if __name__ == "__main__":
    sys.exit(main())
