"""Endpoints /api/v1/webhooks — config + envio de teste."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field, HttpUrl

from app.core.deps import require_admin
from app.repositories.duckdb import settings_repo
from app.services import webhook_notifier

router = APIRouter(prefix="/api/v1/webhooks", tags=["webhooks"])

_ALLOWED_TYPES = {"slack", "discord", "teams", "generic"}
_ALLOWED_SEVERITIES = {"warning", "critical"}


class WebhookConfig(BaseModel):
    enabled: bool = False
    url: str = ""
    type: str = "generic"
    severity_min: str = "critical"
    notify_on_release: bool = False


@router.get("/config")
async def get_config(_: Annotated[dict, Depends(require_admin)]) -> WebhookConfig:
    return WebhookConfig(
        enabled=await settings_repo.get_bool("webhook_enabled", False),
        url=await settings_repo.get("webhook_url", "") or "",
        type=(await settings_repo.get("webhook_type", "generic") or "generic"),
        severity_min=(await settings_repo.get("webhook_severity_min", "critical") or "critical"),
        notify_on_release=await settings_repo.get_bool("notify_webhook_on_release", False),
    )


class WebhookUpdate(BaseModel):
    enabled: bool
    url: str = Field(default="", max_length=512)
    type: str = "generic"
    severity_min: str = "critical"
    notify_on_release: bool = False


@router.put("/config", status_code=status.HTTP_204_NO_CONTENT)
async def update_config(
    body: WebhookUpdate,
    _: Annotated[dict, Depends(require_admin)],
) -> None:
    if body.type not in _ALLOWED_TYPES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"type deve ser um de {sorted(_ALLOWED_TYPES)}",
        )
    if body.severity_min not in _ALLOWED_SEVERITIES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"severity_min deve ser um de {sorted(_ALLOWED_SEVERITIES)}",
        )
    if body.enabled and not body.url.lower().startswith(("http://", "https://")):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="url precisa começar com http:// ou https://",
        )
    await settings_repo.bulk_upsert([
        {"setting_key": "webhook_enabled", "setting_value": "true" if body.enabled else "false"},
        {"setting_key": "webhook_url", "setting_value": body.url},
        {"setting_key": "webhook_type", "setting_value": body.type},
        {"setting_key": "webhook_severity_min", "setting_value": body.severity_min},
        {"setting_key": "notify_webhook_on_release", "setting_value": "true" if body.notify_on_release else "false"},
    ])


class TestRequest(BaseModel):
    message: str | None = None


@router.post("/test")
async def send_test(
    body: TestRequest,
    _: Annotated[dict, Depends(require_admin)],
) -> dict:
    result = await webhook_notifier.send_test(body.message)
    return result
