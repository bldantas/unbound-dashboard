"""Endpoints de estatísticas DNS (read-only, consome DuckDB)."""

from __future__ import annotations

from fastapi import APIRouter, Query

from app.services import stats_service

router = APIRouter(prefix="/api/v1/stats", tags=["stats"])


@router.get("/summary")
async def summary(
    window_hours: int = Query(24, ge=1, le=720, description="Janela retroativa em horas (1-720)"),
    top_n: int = Query(10, ge=1, le=100, description="Quantidade de top domínios/clientes"),
) -> dict:
    """
    Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
    top domínios bloqueados e top clientes por volume.

    Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
    populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
    `log_watcher.py` estiver ativo, será atualizado em tempo real.
    """
    return await stats_service.get_summary(window_hours=window_hours, top_n=top_n)
