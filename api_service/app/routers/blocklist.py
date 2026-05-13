"""Endpoints administrativos /api/v1/blocklist — usados pelo UnboundConfigManager."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, status

from app.core.deps import require_capability
from app.repositories.duckdb import threats_repo

router = APIRouter(prefix="/api/v1/blocklist", tags=["blocklist"])


@router.get("/counts")
async def counts(_: Annotated[dict, Depends(require_capability("blocklist.read"))]) -> dict[str, int]:
    """Retorna count por categoria (Malware/Adware, Phishing, Judicial)."""
    counts = await threats_repo.counts_by_category()
    return {
        "adware": counts.get("Malware/Adware", 0),
        "phishing": counts.get("Phishing", 0),
        "judicial": counts.get("Judicial", 0),
    }


@router.post("/clear-category", status_code=status.HTTP_200_OK)
async def clear_category(
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict,
) -> dict:
    """DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}."""
    category = str(body.get("category") or "").strip()
    if not category:
        return {"deleted": 0}
    deleted = await threats_repo.clear_category(category)
    return {"deleted": deleted}


@router.post("/bulk-insert", status_code=status.HTTP_200_OK)
async def bulk_insert(
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    entries: list[dict],
) -> dict:
    """Bulk UPSERT. Body: [{domain, category, severity}]."""
    inserted = await threats_repo.bulk_insert(entries)
    return {"inserted": inserted}
