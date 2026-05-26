"""Endpoints /api/v1/backup-offsite (C.7) — upload pra S3-compatible."""

from __future__ import annotations

import asyncio
from typing import Annotated

from fastapi import APIRouter, Body, Depends, HTTPException, Request

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


@router.post("/restore-test")
async def restore_test_endpoint(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict | None = None,
) -> dict:
    """Baixa um backup recente (ou key específica se passada) e valida
    integridade do DuckDB sem restaurar no DB real.

    Body opcional: `{"key": "s3-key.tar.gz"}` pra testar uma versão específica.
    """
    cfg = await svc.load_config()
    if not cfg.get("backup_s3_bucket"):
        raise HTTPException(status_code=400, detail="Configure bucket antes de testar restore")
    key = (body or {}).get("key")
    loop = asyncio.get_running_loop()
    result = await loop.run_in_executor(None, svc.restore_test, cfg, key)

    # Persiste status do último restore-test em settings (UI mostra "última verificação")
    from datetime import datetime, timezone
    from app.repositories.duckdb import settings_repo
    ts_iso = datetime.now(timezone.utc).isoformat(timespec="seconds")
    entries = [
        {"setting_key": "backup_s3_last_restore_test_at", "setting_value": ts_iso},
        {"setting_key": "backup_s3_last_restore_test_ok", "setting_value": "1" if result.get("success") else "0"},
        {"setting_key": "backup_s3_last_restore_test_error", "setting_value": str(result.get("error") or "")},
        {"setting_key": "backup_s3_last_restore_test_key", "setting_value": str(result.get("key") or "")},
    ]
    await settings_repo.bulk_upsert(entries)
    return result


@router.get("/destinations")
async def list_destinations(
    _: Annotated[dict, Depends(require_capability("config.read_sensitive"))],
) -> dict:
    from app.services import backup_destinations_service as bd
    items = await bd.list_destinations()
    return {"items": items, "count": len(items)}


@router.post("/destinations", status_code=201)
async def create_destination(
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    from app.services import backup_destinations_service as bd
    from app.services import admin_audit_service
    try:
        out = await bd.create_destination(body)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    await admin_audit_service.log(
        actor_id=user.get("user_id"), actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="backup.destination.create", category="config",
        target_type="backup_destination", target_id=str(out["id"]),
        details={"label": out["label"], "bucket": out["bucket"]},
    )
    return out


@router.put("/destinations/{dest_id}")
async def update_destination(
    dest_id: int,
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    from app.services import backup_destinations_service as bd
    from app.services import admin_audit_service
    ok = await bd.update_destination(dest_id, body)
    if not ok:
        raise HTTPException(status_code=400, detail="nenhum campo válido pra atualizar")
    await admin_audit_service.log(
        actor_id=user.get("user_id"), actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="backup.destination.update", category="config",
        target_type="backup_destination", target_id=str(dest_id),
        details={"fields": list(body.keys())},
    )
    return {"updated": True}


@router.delete("/destinations/{dest_id}", status_code=204)
async def delete_destination(
    dest_id: int,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> None:
    from app.services import backup_destinations_service as bd
    from app.services import admin_audit_service
    ok = await bd.delete_destination(dest_id)
    if not ok:
        raise HTTPException(status_code=404)
    await admin_audit_service.log(
        actor_id=user.get("user_id"), actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="backup.destination.delete", category="config",
        target_type="backup_destination", target_id=str(dest_id),
    )


@router.post("/destinations/{dest_id}/test")
async def test_destination(
    dest_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    from app.services import backup_destinations_service as bd
    return await bd.test_destination(dest_id)


@router.post("/destinations/upload-all")
async def upload_all_destinations(
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    from app.services import backup_destinations_service as bd
    from app.services import admin_audit_service
    out = await bd.upload_to_all()
    successes = sum(1 for r in out["results"] if r.get("success"))
    await admin_audit_service.log(
        actor_id=user.get("user_id"), actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="backup.upload_all", category="config",
        details={"total": out["count"], "ok": successes},
    )
    return out


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
