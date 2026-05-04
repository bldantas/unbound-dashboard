"""
Migração one-shot: MariaDB (v1 produção) → DuckDB (v1 modernizada).

Lê do MariaDB existente (`unbound_dash` schema) e escreve no DuckDB criado
pelas migrations em `migrations/duckdb/`. Valida COUNT por tabela pré e pós.
Cada tabela é envolvida em transação DuckDB (rollback automático em erro).

Tabelas migradas (em ordem de dependência/tamanho):
    settings, users, alerts, daily_stats, blocklist_domains, query_logs

Renomes:
    domain_blacklist (MariaDB) → blocklist_domains (DuckDB)

Uso:
    cd /var/www/html/unbound-dashboard/api_service

    # Dry-run: imprime o que faria, não escreve nada
    MARIADB_PASS=unbounddash .venv/bin/python -m tools.migrate_mariadb_to_duckdb --dry-run

    # Migração real (recusa se DuckDB não estiver vazio — use --truncate para forçar)
    MARIADB_PASS=unbounddash .venv/bin/python -m tools.migrate_mariadb_to_duckdb

    # Re-migrar (TRUNCA tabelas DuckDB primeiro)
    MARIADB_PASS=unbounddash .venv/bin/python -m tools.migrate_mariadb_to_duckdb --truncate

Variáveis de ambiente:
    MARIADB_HOST   default 127.0.0.1
    MARIADB_PORT   default 3306
    MARIADB_DB     default unbound_dash
    MARIADB_USER   default unbounddb
    MARIADB_PASS   OBRIGATÓRIO
    DB_PATH        herda de api_service settings (DuckDB target)

Lições do audit aplicadas:
    - Sequence restart após inserir users/alerts/daily_stats/query_logs
      (sem isso novos auto-increments colidem com IDs migrados)
    - Validação por COUNT pré/pós, ABORT em divergência
    - Transaction por tabela (rollback em erro)
    - Dry-run para inspeção antes de aplicar
"""

from __future__ import annotations

import argparse
import os
import sys
from dataclasses import dataclass

import duckdb
import pandas as pd
import pymysql
import pymysql.cursors
import structlog

from app.core.config import settings

log = structlog.get_logger()

CHUNK_SIZE = 100_000


@dataclass
class TableSpec:
    """Mapeamento de uma tabela MariaDB → DuckDB."""

    mariadb_name: str
    duckdb_name: str
    columns_select: str  # colunas a SELECT do MariaDB (na ordem do INSERT no DuckDB)
    duckdb_insert_cols: str  # colunas alvo no INSERT (sem PRIMARY KEY auto-incremented)
    placeholders: str  # "?, ?, ?" matching column count
    has_sequence: str | None = None  # nome da sequence DuckDB pra restart, se houver
    sequence_table_pk: str | None = None  # coluna PK pra calcular max(id)
    chunked: bool = False


SPECS: list[TableSpec] = [
    TableSpec(
        mariadb_name="settings",
        duckdb_name="settings",
        columns_select="setting_key, setting_value",
        duckdb_insert_cols="setting_key, setting_value",
        placeholders="?, ?",
    ),
    TableSpec(
        mariadb_name="users",
        duckdb_name="users",
        columns_select=(
            "id, username, password_hash, role, email, "
            "is_active, failed_logins, locked_until, reset_token, reset_expires, created_at"
        ),
        duckdb_insert_cols=(
            "id, username, password_hash, role, email, "
            "is_active, failed_logins, locked_until, reset_token, reset_expires, created_at"
        ),
        placeholders="?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?",
        has_sequence="users_id_seq",
        sequence_table_pk="id",
    ),
    TableSpec(
        mariadb_name="alerts",
        duckdb_name="alerts",
        columns_select="id, type, severity, message, started_at, resolved_at, is_dismissed",
        duckdb_insert_cols="id, type, severity, message, started_at, resolved_at, is_dismissed",
        placeholders="?, ?, ?, ?, ?, ?, ?",
        has_sequence="alerts_id_seq",
        sequence_table_pk="id",
    ),
    TableSpec(
        mariadb_name="daily_stats",
        duckdb_name="daily_stats",
        columns_select="id, stat_date, total_queries, cache_hits, cache_misses, blocked_count",
        duckdb_insert_cols="id, stat_date, total_queries, cache_hits, cache_misses, blocked_count",
        placeholders="?, ?, ?, ?, ?, ?",
        has_sequence="daily_stats_id_seq",
        sequence_table_pk="id",
    ),
    TableSpec(
        mariadb_name="domain_blacklist",
        duckdb_name="blocklist_domains",
        columns_select="domain, category, severity",
        duckdb_insert_cols="domain, category, severity",
        placeholders="?, ?, ?",
    ),
    TableSpec(
        mariadb_name="query_logs",
        duckdb_name="query_logs",
        columns_select="id, timestamp, client_ip, domain, query_type, action",
        duckdb_insert_cols="id, timestamp, client_ip, domain, query_type, action",
        placeholders="?, ?, ?, ?, ?, ?",
        has_sequence="query_logs_id_seq",
        sequence_table_pk="id",
        chunked=True,
    ),
]


def _open_mariadb() -> pymysql.connections.Connection:
    password = os.environ.get("MARIADB_PASS")
    if not password:
        log.error("missing_env", var="MARIADB_PASS")
        sys.exit(2)
    return pymysql.connect(
        host=os.environ.get("MARIADB_HOST", "127.0.0.1"),
        port=int(os.environ.get("MARIADB_PORT", "3306")),
        user=os.environ.get("MARIADB_USER", "unbounddb"),
        password=password,
        database=os.environ.get("MARIADB_DB", "unbound_dash"),
        cursorclass=pymysql.cursors.DictCursor,  # buffered; chunked tables usam keyset pagination
        charset="utf8mb4",
    )


def _check_duckdb_ready(conn: duckdb.DuckDBPyConnection) -> None:
    """Garante que as tabelas alvo existem (migrations já aplicadas)."""
    found = {
        row[0]
        for row in conn.execute(
            "SELECT table_name FROM information_schema.tables WHERE table_schema='main'"
        ).fetchall()
    }
    needed = {s.duckdb_name for s in SPECS}
    missing = needed - found
    if missing:
        log.error("duckdb_missing_tables", missing=sorted(missing))
        log.error("hint", message="rode primeiro `python -m app.db.migrate`")
        sys.exit(2)


def _rowcounts(conn: duckdb.DuckDBPyConnection) -> dict[str, int]:
    return {
        s.duckdb_name: conn.execute(f"SELECT COUNT(*) FROM {s.duckdb_name}").fetchone()[0]
        for s in SPECS
    }


def _restart_sequence(
    conn: duckdb.DuckDBPyConnection,
    sequence: str,
    table: str,
    pk_column: str,
    next_value: int,
) -> None:
    """
    DuckDB 1.5 não suporta `ALTER SEQUENCE ... RESTART WITH N`.
    Workaround: derrubar o DEFAULT da coluna que referencia a sequence,
    drop+create da sequence com novo START, re-adicionar DEFAULT.
    """
    conn.execute(f"ALTER TABLE {table} ALTER COLUMN {pk_column} DROP DEFAULT")
    conn.execute(f"DROP SEQUENCE {sequence}")
    conn.execute(f"CREATE SEQUENCE {sequence} START {next_value}")
    conn.execute(
        f"ALTER TABLE {table} ALTER COLUMN {pk_column} SET DEFAULT nextval('{sequence}')"
    )


def _truncate_targets(conn: duckdb.DuckDBPyConnection) -> None:
    for s in SPECS:
        conn.execute(f"DELETE FROM {s.duckdb_name}")
        if s.has_sequence and s.sequence_table_pk:
            _restart_sequence(conn, s.has_sequence, s.duckdb_name, s.sequence_table_pk, 1)
    conn.execute("CHECKPOINT")


def _migrate_buffered(
    spec: TableSpec,
    mysql_conn: pymysql.connections.Connection,
    duck: duckdb.DuckDBPyConnection,
) -> int:
    """Lê tabela inteira (DictCursor buffered) e insere via DataFrame+append."""
    select_sql = f"SELECT {spec.columns_select} FROM {spec.mariadb_name}"
    with mysql_conn.cursor() as c:
        c.execute(select_sql)
        rows = c.fetchall()
    if not rows:
        return 0
    df = pd.DataFrame(rows)
    duck.append(spec.duckdb_name, df)
    return len(df)


def _migrate_chunked(
    spec: TableSpec,
    mysql_conn: pymysql.connections.Connection,
    duck: duckdb.DuckDBPyConnection,
    total_rows: int,
) -> int:
    """Keyset pagination — WHERE pk > last ORDER BY pk LIMIT N. Cada chunk vira DataFrame."""
    pk = spec.sequence_table_pk
    inserted = 0
    last_key: int | None = None
    base_select = f"SELECT {spec.columns_select} FROM {spec.mariadb_name}"
    while True:
        with mysql_conn.cursor() as c:
            if last_key is None:
                c.execute(f"{base_select} ORDER BY {pk} LIMIT %s", (CHUNK_SIZE,))
            else:
                c.execute(
                    f"{base_select} WHERE {pk} > %s ORDER BY {pk} LIMIT %s",
                    (last_key, CHUNK_SIZE),
                )
            rows = c.fetchall()
        if not rows:
            break
        df = pd.DataFrame(rows)
        duck.append(spec.duckdb_name, df)
        inserted += len(df)
        last_key = rows[-1][pk]
        log.info("table.chunk", table=spec.mariadb_name, inserted=inserted, total=total_rows)
    return inserted


def _migrate_table(
    spec: TableSpec,
    mysql_conn: pymysql.connections.Connection,
    duck: duckdb.DuckDBPyConnection,
    dry_run: bool,
) -> tuple[int, int]:
    """
    Retorna (mariadb_count, duckdb_inserted_count).
    Wrap em transação DuckDB; rollback em qualquer erro.
    """
    # COUNT pré-migração no MariaDB
    with mysql_conn.cursor() as c:
        c.execute(f"SELECT COUNT(*) AS n FROM {spec.mariadb_name}")
        mariadb_count = int(c.fetchone()["n"])

    log.info(
        "table.starting",
        table=spec.mariadb_name,
        target=spec.duckdb_name,
        rows=mariadb_count,
        chunked=spec.chunked,
    )

    if dry_run:
        log.info("table.dry_run", table=spec.mariadb_name, would_insert=mariadb_count)
        return mariadb_count, 0

    duck.execute("BEGIN")
    inserted = 0
    try:
        if spec.chunked and spec.sequence_table_pk:
            # Keyset pagination: WHERE pk > last_seen ORDER BY pk LIMIT N.
            # Evita SSCursor (que estourava net_read_timeout) e OFFSET (lento).
            inserted = _migrate_chunked(spec, mysql_conn, duck, mariadb_count)
        else:
            inserted = _migrate_buffered(spec, mysql_conn, duck)

        if spec.has_sequence and spec.sequence_table_pk:
            max_id = duck.execute(
                f"SELECT COALESCE(MAX({spec.sequence_table_pk}), 0) FROM {spec.duckdb_name}"
            ).fetchone()[0]
            if max_id > 0:
                next_value = max_id + 1
                _restart_sequence(
                    duck, spec.has_sequence, spec.duckdb_name, spec.sequence_table_pk, next_value
                )
                log.info("sequence.restarted", sequence=spec.has_sequence, next_value=next_value)

        duck.execute("COMMIT")
    except Exception as exc:
        duck.execute("ROLLBACK")
        log.error("table.failed", table=spec.mariadb_name, inserted=inserted, error=str(exc))
        raise

    log.info("table.done", table=spec.mariadb_name, inserted=inserted)
    return mariadb_count, inserted


def main() -> int:
    parser = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Não escreve no DuckDB; só conta e reporta.",
    )
    parser.add_argument(
        "--truncate",
        action="store_true",
        help="TRUNCA as tabelas DuckDB antes de migrar (use pra re-migração).",
    )
    args = parser.parse_args()

    log.info("migration.starting", dry_run=args.dry_run, db_path=settings.db_path)

    mysql_conn = _open_mariadb()
    log.info("mariadb.connected", host=os.environ.get("MARIADB_HOST", "127.0.0.1"))

    with duckdb.connect(settings.db_path) as duck:
        _check_duckdb_ready(duck)

        existing = _rowcounts(duck)
        non_empty = {t: n for t, n in existing.items() if n > 0}
        if non_empty and not args.truncate and not args.dry_run:
            log.error(
                "duckdb_not_empty",
                rows=non_empty,
                hint=(
                    "use --truncate pra forçar (apaga dados existentes) "
                    "ou --dry-run pra inspecionar"
                ),
            )
            return 2

        if args.truncate and not args.dry_run:
            log.warning("truncating_targets", tables=list(existing.keys()))
            _truncate_targets(duck)

        results: list[tuple[str, int, int]] = []
        for spec in SPECS:
            mc, di = _migrate_table(spec, mysql_conn, duck, args.dry_run)
            results.append((spec.duckdb_name, mc, di))

    mysql_conn.close()

    log.info("migration.summary", results=results)
    print("\n=== Resumo ===")
    print(f"{'tabela':<22} {'MariaDB':>10} {'DuckDB':>10} {'status':>10}")
    print("-" * 56)
    failures = 0
    for name, mc, di in results:
        if args.dry_run:
            status = "DRY"
        elif mc == di:
            status = "OK"
        else:
            status = "MISMATCH"
            failures += 1
        print(f"{name:<22} {mc:>10} {di:>10} {status:>10}")

    if failures:
        log.error("validation.failed", mismatches=failures)
        return 1

    log.info("migration.complete")
    return 0


if __name__ == "__main__":
    sys.exit(main())
