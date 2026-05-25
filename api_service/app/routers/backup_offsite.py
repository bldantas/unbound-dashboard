"""Endpoints /api/v1/backup-offsite (C.7) — upload pra S3-compatible."""

from __future__ import annotations

import asyncio
from typing import Annotated

from fastapi import APIRouter, Body, Depends, HTTPException

from app.core.deps import require_capability
from app.repositories.duckdb import settings_repo
from app.services import backup_offsite_service as svc

router = APIRouter(prefix="/api/v1/backup-offsite", tags=["backup-offsite"])


def _mask(s: str | None) -> str:
    """Mascara secret_key: mostra só últimos 4 chars."""
    if not s:
        return ""
    s = s.strip()
    if len(s) <= 6:
        return "•" * len(s)
    return "•" * (len(s) - 4) + s[-4:]


@router.get("/settings")
async def get_settings(
    _: Annotated[dict, Depends(require_capability("config.read_sensitive"))],
) -> dict:
    cfg = await svc.load_config()
    # Mascara secret_key pro frontend
    cfg_safe = {**cfg, "backup_s3_secret_key_masked": _mask(cfg.get("backup_s3_secret_key", ""))}
    cfg_safe["backup_s3_secret_key"] = "" if cfg.get("backup_s3_secret_key") else ""

    # Status
    status = {}
    for k in svc.STATUS_KEYS:
        status[k] = await settings_repo.get(k, "") or ""

    return {
        "settings": cfg_safe,
        "status": status,
        "defaults": svc.DEFAULTS,
    }


@router.put("/settings")
async def update_settings(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict = Body(...),
) -> dict:
    # Se secret_key vier vazio, preserva o anterior — UI evita re-tipar.
    if "backup_s3_secret_key" in body and not str(body["backup_s3_secret_key"]).strip():
        body.pop("backup_s3_secret_key")
    n = await svc.save_config(body)
    return {"updated": n}


@router.post("/test")
async def test_connection(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    cfg = await svc.load_config()
    # Roda síncrono via executor
    loop = asyncio.get_running_loop()
    result = await loop.run_in_executor(None, svc.test_connection, cfg)
    return result


@router.post("/upload-now")
async def upload_now(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    cfg = await svc.load_config()
    if not cfg.get("backup_s3_bucket"):
        raise HTTPException(status_code=400, detail="Configure bucket antes de fazer upload")
    loop = asyncio.get_running_loop()
    result = await loop.run_in_executor(None, svc.upload_backup, cfg)
    if result.get("success"):
        await svc.save_status(
            status="ok",
            error=None,
            size=result.get("size_bytes"),
            key=result.get("key"),
        )
    else:
        await svc.save_status(status="error", error=str(result.get("error") or ""))
    return result


@router.get("/history")
async def history(
    _: Annotated[dict, Depends(require_capability("config.read_sensitive"))],
    limit: int = 100,
) -> dict:
    cfg = await svc.load_config()
    if not cfg.get("backup_s3_bucket"):
        return {"items": []}
    loop = asyncio.get_running_loop()
    try:
        items = await loop.run_in_executor(None, svc.list_remote, cfg, limit)
        return {"items": items}
    except Exception as e:  # noqa: BLE001
        raise HTTPException(status_code=502, detail=f"Falha ao listar bucket: {e}")
