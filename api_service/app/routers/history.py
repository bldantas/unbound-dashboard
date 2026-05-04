"""Endpoint /api/v1/history/summary — substitui as 2 queries PDO em history.php."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_auth
from app.services import history_service

router = APIRouter(prefix="/api/v1/history", tags=["history"])

_ALLOWED_LIMITS = frozenset({10, 20, 50, 100})


def _parse_limit(raw: str) -> int:
    if raw == "todos":
        return 1000
    try:
        n = int(raw)
    except ValueError:
        return 10
    return n if n in _ALLOWED_LIMITS else 10


@router.get("/summary")
async def history_summary(
    _: Annotated[dict, Depends(require_auth)],
    limit: str = Query("10", description="10|20|50|100|'todos'"),
) -> dict:
    parsed_limit = _parse_limit(limit)
    return await history_service.get_summary(limit=parsed_limit, top_n=10)
