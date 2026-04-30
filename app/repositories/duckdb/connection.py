"""
Conexão DuckDB — banco único para OLTP (users/settings/alerts) e OLAP (query_logs/daily_stats).

Regra crítica de thread safety:
  - ESCRITA: sempre via _writer (ThreadPoolExecutor max_workers=1) — serializa todos os writes
  - LEITURA: pode usar conexões read_only independentes — DuckDB permite N leitores simultâneos
"""

from __future__ import annotations

import asyncio
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from typing import Any

import duckdb

from app.core.config import settings

# Executor dedicado — serializa TODAS as escritas no DuckDB
_writer = ThreadPoolExecutor(max_workers=1, thread_name_prefix="duckdb-writer")


def _ensure_data_dir() -> str:
    path = Path(settings.db_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    return str(path)


def _write_conn() -> duckdb.DuckDBPyConnection:
    """Conexão de escrita — usar apenas dentro do _writer executor."""
    return duckdb.connect(_ensure_data_dir())


def _read_conn() -> duckdb.DuckDBPyConnection:
    """Conexão de leitura — DuckDB 1.x não permite misturar read_only e read-write
    no mesmo arquivo. Usamos conexão normal (MVCC garante consistência de leitura)."""
    return duckdb.connect(_ensure_data_dir())


async def db_execute(sql: str, params: list[Any] | None = None) -> None:
    """Executa DDL/DML de escrita de forma assíncrona e serializada."""
    loop = asyncio.get_event_loop()
    await loop.run_in_executor(_writer, _sync_execute, sql, params or [])


async def db_executemany(sql: str, rows: list[list[Any]]) -> None:
    """Insere múltiplas linhas de uma vez (bulk insert)."""
    loop = asyncio.get_event_loop()
    await loop.run_in_executor(_writer, _sync_executemany, sql, rows)


async def db_fetchall(sql: str, params: list[Any] | None = None) -> list[dict[str, Any]]:
    """Executa query de leitura e retorna lista de dicts."""
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, _sync_fetchall, sql, params or [])


async def db_fetchone(sql: str, params: list[Any] | None = None) -> dict[str, Any] | None:
    """Executa query de leitura e retorna primeiro resultado ou None."""
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, _sync_fetchone, sql, params or [])


# --- Funções síncronas (rodam em thread pool) ---

def _sync_execute(sql: str, params: list[Any]) -> None:
    with _write_conn() as conn:
        conn.execute(sql, params)


def _sync_executemany(sql: str, rows: list[list[Any]]) -> None:
    with _write_conn() as conn:
        conn.executemany(sql, rows)


def _sync_fetchall(sql: str, params: list[Any]) -> list[dict[str, Any]]:
    with _read_conn() as conn:
        rel = conn.execute(sql, params)
        cols = [d[0] for d in rel.description]
        return [dict(zip(cols, row)) for row in rel.fetchall()]


def _sync_fetchone(sql: str, params: list[Any]) -> dict[str, Any] | None:
    with _read_conn() as conn:
        rel = conn.execute(sql, params)
        row = rel.fetchone()
        if row is None:
            return None
        cols = [d[0] for d in rel.description]
        return dict(zip(cols, row))


# Dependência FastAPI (usado em Depends)
async def get_db() -> None:
    """Placeholder para injeção de dependência — a conexão é gerenciada internamente."""
    return None
