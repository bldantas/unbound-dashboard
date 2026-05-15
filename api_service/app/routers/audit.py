"""Endpoints /api/v1/audit/* — trilha de auditoria de updates/restores."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.services import audit_service

router = APIRouter(prefix="/api/v1/audit", tags=["audit"])


@router.get("/updates")
async def list_update_audit(
    _: Annotated[dict, Depends(require_capability("users.read"))],
    limit: int = Query(50, ge=1, le=500),
) -> dict:
    """
    Lista o histórico de updates/restores aplicados via UI.
    Capability `users.read` — readonly_admin enxerga, operator/viewer não.
    """
    items = await audit_service.list_recent(limit=limit)
    return {"audit": items, "count": len(items)}
