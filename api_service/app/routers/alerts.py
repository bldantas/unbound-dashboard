"""Endpoints /api/v1/alerts — espelho do AlertManager (PHP) consumindo DuckDB."""

from __future__ import annotations

from datetime import datetime
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, status
from pydantic import BaseModel, Field

from app.core.deps import require_admin, require_auth, require_capability, resolve_viewer_org_id
from app.repositories.duckdb import alert_repo, settings_repo
from app.workers.alert_checker import THRESHOLD_DEFAULTS

router = APIRouter(prefix="/api/v1/alerts", tags=["alerts"])


class ThresholdsUpdateRequest(BaseModel):
    """
    Aceita apenas os 6 thresholds conhecidos. Valores precisam ser >= 0.
    Campos omitidos não são alterados (PATCH-style).
    """

    alert_threshold_cpu_load1:        float | None = Field(default=None, ge=0)
    alert_threshold_mem_percent:      float | None = Field(default=None, ge=0, le=100)
    alert_threshold_swap_percent:     float | None = Field(default=None, ge=0, le=100)
    alert_threshold_disk_percent:     float | None = Field(default=None, ge=0, le=100)
    alert_threshold_network_counters: float | None = Field(default=None, ge=0)
    alert_threshold_ssh_failed_day:   float | None = Field(default=None, ge=0)


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
async def list_alerts(payload: Annotated[dict, Depends(require_capability("alerts.read"))]) -> dict:
    viewer_org = await resolve_viewer_org_id(payload)
    history = await alert_repo.list_history(limit=100, viewer_org_id=viewer_org)
    active = await alert_repo.count_active()
    return {
        "alerts": [_format_alert_row(r) for r in history],
        "active_count": active,
    }


@router.post("/{alert_id}/resolve", status_code=204)
async def resolve_alert(
    _: Annotated[dict, Depends(require_capability("alerts.resolve"))],
    alert_id: Annotated[int, Path(ge=1)],
) -> None:
    await alert_repo.resolve_by_id(alert_id)


@router.post("/clear-resolved")
async def clear_resolved(_: Annotated[dict, Depends(require_capability("alerts.resolve"))]) -> dict:
    deleted = await alert_repo.clear_resolved()
    return {"deleted": deleted}


@router.get("/thresholds")
async def get_thresholds(_: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
    autenticado (a alerts.php precisa pra exibir os números nos cards de
    hardware mesmo pra viewer).
    """
    current = {}
    for key, default in THRESHOLD_DEFAULTS.items():
        current[key] = await settings_repo.get_float(key, default)
    return {"current": current, "defaults": THRESHOLD_DEFAULTS}


@router.put("/thresholds", status_code=status.HTTP_200_OK)
async def update_thresholds(
    body: ThresholdsUpdateRequest,
    _: Annotated[dict, Depends(require_admin)],
) -> dict:
    """
    UPSERT dos thresholds editáveis. Aceita parcial — só campos
    presentes no body são gravados. Retorna o estado final atualizado.
    """
    payload = body.model_dump(exclude_none=True)
    if not payload:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Nenhum threshold informado.",
        )
    entries = [
        {"setting_key": k, "setting_value": str(v)} for k, v in payload.items()
    ]
    upserted = await settings_repo.bulk_upsert(entries)
    new_state = {}
    for key, default in THRESHOLD_DEFAULTS.items():
        new_state[key] = await settings_repo.get_float(key, default)
    return {"upserted": upserted, "current": new_state}
