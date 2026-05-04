"""Endpoints /api/v1/alerts — espelho do AlertManager (PHP) consumindo DuckDB."""

from __future__ import annotations

from datetime import datetime
from typing import Annotated

from fastapi import APIRouter, Depends, Path

from app.core.deps import require_admin
from app.repositories.duckdb import alert_repo

router = APIRouter(prefix="/api/v1/alerts", tags=["alerts"])


def _format_alert_row(row: dict) -> dict:
    """Normaliza datetime → ISO string e is_dismissed → bool."""

    def to_iso(v: datetime | None) -> str | None:
        return v.isoformat() if isinstance(v, datetime) else (str(v) if v else None)

    return {
        "id": int(row["id"]),
        "type": str(row["type"]),
        "severity": str(row["severity"]),
        "message": str(row["message"] or ""),
        "started_at": to_iso(row.get("started_at")),
        "resolved_at": to_iso(row.get("resolved_at")),
        "is_dismissed": bool(row.get("is_dismissed", False)),
        "duration_secs": int(row.get("duration_secs") or 0),
    }


@router.get("/list")
async def list_alerts(_: Annotated[dict, Depends(require_admin)]) -> dict:
    history = await alert_repo.list_history(limit=100)
    active = await alert_repo.count_active()
    return {
        "alerts": [_format_alert_row(r) for r in history],
        "active_count": active,
    }


@router.post("/{alert_id}/resolve", status_code=204)
async def resolve_alert(
    _: Annotated[dict, Depends(require_admin)],
    alert_id: Annotated[int, Path(ge=1)],
) -> None:
    await alert_repo.resolve_by_id(alert_id)


@router.post("/clear-resolved")
async def clear_resolved(_: Annotated[dict, Depends(require_admin)]) -> dict:
    deleted = await alert_repo.clear_resolved()
    return {"deleted": deleted}
