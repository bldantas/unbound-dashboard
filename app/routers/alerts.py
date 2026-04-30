from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, status
from pydantic import BaseModel

from app.core.deps import require_admin, require_auth
from app.domain.alert import AlertCreate, Severity
from app.repositories.duckdb.alert_repo import AlertRepository

router = APIRouter(prefix="/api/v2/alerts", tags=["alerts"])


def _get_repo() -> AlertRepository:
    return AlertRepository()


class AlertOut(BaseModel):
    id: int
    type: str
    message: str
    severity: str
    is_read: bool
    created_at: str


class CreateAlertBody(BaseModel):
    type: str
    message: str
    severity: str = "info"


@router.get("")
async def list_alerts(
    unread_only: bool = False,
    _: dict = Depends(require_auth),
    repo: AlertRepository = Depends(_get_repo),
) -> list[dict]:
    alerts = await repo.list_unread() if unread_only else await repo.list_all()
    return [
        {
            "id": a.id,
            "type": a.type,
            "message": a.message,
            "severity": a.severity.value,
            "is_read": a.is_read,
            "created_at": a.created_at.isoformat(),
        }
        for a in alerts
    ]


@router.get("/count")
async def count_unread(
    _: dict = Depends(require_auth),
    repo: AlertRepository = Depends(_get_repo),
) -> dict:
    return {"unread": await repo.count_unread()}


@router.post("/{alert_id}/read", status_code=status.HTTP_204_NO_CONTENT)
async def mark_read(
    alert_id: Annotated[int, Path(ge=1)],
    _: dict = Depends(require_auth),
    repo: AlertRepository = Depends(_get_repo),
) -> None:
    await repo.mark_read(alert_id)


@router.post("/read-all", status_code=status.HTTP_204_NO_CONTENT)
async def mark_all_read(
    _: dict = Depends(require_auth),
    repo: AlertRepository = Depends(_get_repo),
) -> None:
    await repo.mark_all_read()


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_alert(
    body: CreateAlertBody,
    _: dict = Depends(require_admin),
    repo: AlertRepository = Depends(_get_repo),
) -> dict:
    try:
        severity = Severity(body.severity)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=f"Severity inválido: {body.severity}",
        )
    alert_id = await repo.create(
        AlertCreate(type=body.type, message=body.message, severity=severity)
    )
    return {"id": alert_id}


@router.delete("/{alert_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_alert(
    alert_id: Annotated[int, Path(ge=1)],
    _: dict = Depends(require_admin),
    repo: AlertRepository = Depends(_get_repo),
) -> None:
    await repo.delete(alert_id)
