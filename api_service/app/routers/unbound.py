"""Endpoint /api/v1/unbound/stats — espelho de data/latest_stats.json (PHP)."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends

from app.core.deps import require_auth
from app.services import unbound_stats_service

router = APIRouter(prefix="/api/v1/unbound", tags=["unbound"])


@router.get("/stats")
async def unbound_stats(_: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.

    Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
    em paralelo esperam o mesmo build (lock interno).

    Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.
    """
    return await unbound_stats_service.get_stats()
