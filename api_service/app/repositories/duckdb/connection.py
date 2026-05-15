"""
Conexão DuckDB para reads concorrentes e writes serializados.

DuckDB não é thread-safe por conexão, mas suporta múltiplos processos/conexões
em modo read-only simultâneos via MVCC. Writes precisam ser serializados.

Estratégia (per docs/PLANO_MODERNIZACAO_V1.md seção 6.3):
  - Reads:  conexão temporária por chamada (modo read-write porque DuckDB 1.x
            NÃO permite misturar conns read-only + read-write no mesmo file
            simultaneamente — testado em prod, falha com "Can't open a
            connection to same database file with a different configuration").
            Reads são SELECTs apenas; MVCC garante consistência sem lock.
  - Writes: executor dedicado max_workers=1 (`_writer_executor`). Garante
            serialização entre todos os workers e endpoints que escrevem.

API async wrappers (`db_fetchall`, `db_fetchone`, `db_append`) não bloqueiam
o event loop do FastAPI mesmo em queries longas.
"""

from __future__ import annotations

import asyncio
import time
from concurrent.futures import ThreadPoolExecutor
from typing import Any

import duckdb
import pandas as pd

from app.core.config import settings

# Single-thread executor: serializa todas as escritas no DuckDB. Usado por
# log_watcher, alert_checker, stats_aggregator etc. Sem isso há risco de
# corrupção (DuckDB não permite writers concorrentes no mesmo arquivo).
_writer_executor = ThreadPoolExecutor(max_workers=1, thread_name_prefix="duckdb-writer")


# Retry curto pra conflitos de file handle no DuckDB (race condition entre
# múltiplos readers simultâneos). Acontece raramente — vimos ~0.2% das
# requests em endpoints muito chamados (/users/exists). Backoff exponencial
# pra dar tempo de outro reader terminar.
_RETRY_MAX = 5
_RETRY_BASE_SLEEP = 0.05  # 50ms inicial, dobra a cada tentativa


def _is_retriable_duckdb_error(exc: Exception) -> bool:
    """True pra erros transientes que tendem a se resolver com retry curto."""
    msg = str(exc).lower()
    return (
        "unique file handle conflict" in msg
        or "cannot attach" in msg
        or "could not set lock" in msg
    )


def _with_retry(fn, *args):
    """Roda fn(*args) com retry exponencial pra conflitos transientes do DuckDB."""
    last_exc = None
    for attempt in range(_RETRY_MAX):
        try:
            return fn(*args)
        except Exception as exc:  # noqa: BLE001
            if not _is_retriable_duckdb_error(exc):
                raise
            last_exc = exc
            time.sleep(_RETRY_BASE_SLEEP * (2 ** attempt))
    # Esgotamos retries — levanta o último erro
    if last_exc is not None:
        raise last_exc
    return None  # unreachable; placeholder pro type-checker


def _sync_fetchall(sql: str, params: list[Any]) -> list[dict[str, Any]]:
    def _run():
        with duckdb.connect(settings.db_path) as conn:
            result = conn.execute(sql, params)
            cols = [d[0] for d in result.description]
            return [dict(zip(cols, row, strict=True)) for row in result.fetchall()]
    return _with_retry(_run)


def _sync_fetchone(sql: str, params: list[Any]) -> dict[str, Any] | None:
    def _run():
        with duckdb.connect(settings.db_path) as conn:
            result = conn.execute(sql, params)
            cols = [d[0] for d in result.description]
            row = result.fetchone()
            return dict(zip(cols, row, strict=True)) if row else None
    return _with_retry(_run)


async def db_fetchall(sql: str, params: list[Any] | None = None) -> list[dict[str, Any]]:
    return await asyncio.get_running_loop().run_in_executor(None, _sync_fetchall, sql, params or [])


async def db_fetchone(sql: str, params: list[Any] | None = None) -> dict[str, Any] | None:
    return await asyncio.get_running_loop().run_in_executor(None, _sync_fetchone, sql, params or [])


def _sync_append(table: str, df: pd.DataFrame) -> None:
    # `INSERT INTO table (cols) SELECT * FROM batch_view` em vez de `conn.append()`
    # porque queremos preservar DEFAULT (sequences) para colunas ausentes no df —
    # ex: id auto-incremented em query_logs. `conn.append` exige todas as colunas.
    cols = ", ".join(df.columns)
    with duckdb.connect(settings.db_path) as conn:
        conn.register("batch_view", df)
        try:
            conn.execute(f"INSERT INTO {table} ({cols}) SELECT * FROM batch_view")
        finally:
            conn.unregister("batch_view")


async def db_append(table: str, df: pd.DataFrame) -> None:
    """
    Bulk-insere um DataFrame em `table`. Colunas ausentes no DataFrame recebem
    DEFAULT (útil para `id` auto-incremented). Serializado pelo writer executor
    de thread única — múltiplas chamadas concorrentes ficam em fila.
    """
    if df.empty:
        return
    await asyncio.get_running_loop().run_in_executor(_writer_executor, _sync_append, table, df)


def _sync_execute(sql: str, params: list[Any]) -> None:
    with duckdb.connect(settings.db_path) as conn:
        conn.execute(sql, params)


async def db_execute(sql: str, params: list[Any] | None = None) -> None:
    """
    Executa um statement de escrita (INSERT/UPDATE/DELETE/UPSERT) no DuckDB.
    Serializado via writer executor — para queries que NÃO se beneficiam de
    bulk via DataFrame (ex: UPSERT de uma única linha, UPDATE de status).
    """
    await asyncio.get_running_loop().run_in_executor(
        _writer_executor, _sync_execute, sql, params or []
    )
