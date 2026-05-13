"""
/api/v1/updates/* — self-update via UI.

Endpoints (todos exigem capability `config.write`):
  GET  /check                  → versão atual vs última no GitHub
  POST /apply                  → dispara update (retorna job_id)
  GET  /status/{job_id}        → estado do job

SSE em /log/{job_id} fica no Fase 4 (próximo commit).
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field

from app.core.deps import require_capability
from app.services import updater

router = APIRouter(prefix="/api/v1/updates", tags=["updates"])


@router.get("/check")
async def check(_: Annotated[dict, Depends(require_capability("config.write"))]) -> dict:
    """
    Consulta GitHub Releases pela última versão publicada. Resposta
    sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
    Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.
    """
    return await updater.check_for_updates()


class ApplyRequest(BaseModel):
    version: str = Field(min_length=5, max_length=20, description="Versão semver sem 'v' (ex: 2.17.0)")
    acknowledge_breaking: bool = Field(default=False, description="Obrigatório em major bumps")


@router.post("/apply", status_code=status.HTTP_202_ACCEPTED)
async def apply(
    body: ApplyRequest,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis
    """
    try:
        job_id = await updater.apply_update(
            version=body.version,
            acknowledge_breaking=body.acknowledge_breaking,
        )
    except updater.UpdateLocked as exc:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail=str(exc),
        ) from None
    except updater.VersionMismatch as exc:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(exc),
        ) from None
    except updater.MissingBreakingAck as exc:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(exc),
        ) from None
    except updater.GitHubUnavailable as exc:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=str(exc),
        ) from None
    except updater.TarballDownloadFailed as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=str(exc),
        ) from None
    except updater.ChecksumMismatch as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=str(exc),
        ) from None

    return {"job_id": job_id, "status": "running"}


@router.get("/status/{job_id}")
async def status_endpoint(
    job_id: str,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Estado atual do job.
    Statuses possíveis: running, succeeded, failed, rolled_back, rollback_failed.
    """
    state = await updater.get_job_state(job_id)
    if state is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Job não encontrado")
    return state
