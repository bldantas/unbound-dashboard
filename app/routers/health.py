"""Health endpoints — /healthz (liveness) e /health (full snapshot)."""

from __future__ import annotations

from fastapi import APIRouter, Depends

from app.core.deps import require_auth
from app.services.stats_service import StatsService

router = APIRouter(tags=["health"])


def _get_service() -> StatsService:
    return StatsService()


@router.get("/healthz")
async def liveness() -> dict:
    """Liveness probe — retorna 200 se a API está rodando."""
    return {"status": "ok", "version": "2.0.0"}


@router.get("/health")
async def health(
    force: bool = False,
    _: dict = Depends(require_auth),
    svc: StatsService = Depends(_get_service),
) -> dict:
    """Snapshot completo: sistema (CPU/RAM/Disco/Rede) + status Unbound."""
    return await svc.get_health_snapshot(force=force)
