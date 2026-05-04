"""Lógica de negócio de estatísticas — combina queries do repo em respostas úteis."""

from __future__ import annotations

import asyncio

from app.repositories.duckdb import stats_repo


async def get_summary(window_hours: int = 24, top_n: int = 10) -> dict:
    """
    Sumário de DNS para janela retroativa de `window_hours`. Roda 3 queries
    DuckDB em paralelo (asyncio.gather) — total ~ tempo da query mais lenta.
    """
    window_seconds = window_hours * 3600
    totals, top_blocked, top_clients = await asyncio.gather(
        stats_repo.totals_window(window_seconds),
        stats_repo.top_blocked_domains(window_seconds, top_n),
        stats_repo.top_clients(window_seconds, top_n),
    )

    total = totals["total"]
    blocked = totals["blocked"]
    block_rate = blocked / total if total else 0.0

    return {
        "window_hours": window_hours,
        "totals": {
            **totals,
            "block_rate": round(block_rate, 6),
        },
        "top_blocked_domains": top_blocked,
        "top_clients": top_clients,
    }
