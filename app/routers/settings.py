from __future__ import annotations

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.deps import require_admin, require_auth
from app.repositories.duckdb.settings_repo import SettingsRepository

router = APIRouter(prefix="/api/v2/settings", tags=["settings"])


def _get_repo() -> SettingsRepository:
    return SettingsRepository()


class SettingItem(BaseModel):
    key: str
    value: str


@router.get("")
async def list_settings(
    _: dict = Depends(require_admin),
    repo: SettingsRepository = Depends(_get_repo),
) -> dict[str, str]:
    return await repo.all()


@router.get("/{key}")
async def get_setting(
    key: str,
    _: dict = Depends(require_admin),
    repo: SettingsRepository = Depends(_get_repo),
) -> dict:
    value = await repo.get(key)
    return {"key": key, "value": value}


@router.put("/{key}")
async def set_setting(
    key: str,
    body: SettingItem,
    _: dict = Depends(require_admin),
    repo: SettingsRepository = Depends(_get_repo),
) -> dict:
    await repo.set(key, body.value)
    return {"key": key, "value": body.value}


@router.delete("/{key}", status_code=204)
async def delete_setting(
    key: str,
    _: dict = Depends(require_admin),
    repo: SettingsRepository = Depends(_get_repo),
) -> None:
    await repo.delete(key)
