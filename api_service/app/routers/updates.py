"""
/api/v1/updates/* — self-update via UI.

Endpoints (todos exigem capability `config.write`):
  GET  /check                  → versão atual vs última no GitHub
  POST /apply                  → dispara update (retorna job_id)
  GET  /status/{job_id}        → estado do job
  GET  /log/{job_id}           → SSE com tail live do log + evento final
"""

from __future__ import annotations

import asyncio
import json
from pathlib import Path
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status
from fastapi.responses import StreamingResponse
from pydantic import BaseModel, Field

from app.core.deps import require_capability
from app.services import updater

router = APIRouter(prefix="/api/v1/updates", tags=["updates"])


async def _username_from_payload(user_id: int | None) -> str | None:
    """Busca username pelo user_id pra registrar no audit (JWT só tem sub)."""
    if not user_id:
        return None
    try:
        from app.repositories.duckdb import user_repo
        user = await user_repo.find_by_id(user_id)
        return str(user["username"]) if user else None
    except Exception:  # noqa: BLE001
        return None


def _user_from_payload(payload: dict) -> tuple[int | None, str | None]:
    """
    Extrai (user_id, username_hint) de qualquer payload aceito por require_auth.
    - JWT: sub é int (user_id), username vem do banco.
    - API token: sub="api-token", retorna user_id=None e username="api-token:<label>"
      pro audit ter um actor identificável.
    """
    if payload.get("auth_kind") == "api_token":
        label = payload.get("api_token_label") or "?"
        return None, f"api-token:{label}"
    sub = payload.get("sub", 0)
    try:
        user_id = int(sub)
    except (TypeError, ValueError):
        return None, None
    return (user_id or None), None


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
    request: Request,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis + audit trail no DuckDB
    """
    user_id, username_hint = _user_from_payload(payload)
    username = username_hint or await _username_from_payload(user_id)
    xff = request.headers.get("x-forwarded-for", "")
    ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else None)
    try:
        job_id = await updater.apply_update(
            version=body.version,
            acknowledge_breaking=body.acknowledge_breaking,
            user_id=user_id,
            username=username,
            ip=ip,
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


# ============================================================
# SSE — tail do log live
# ============================================================
# Validação: job_id é hex de 12 chars (uuid4().hex[:12]). Sem regex
# restritiva, atacante poderia tentar ler /etc/passwd via path traversal.
_JOB_ID_LEN = 12


def _validate_job_id(job_id: str) -> None:
    if len(job_id) != _JOB_ID_LEN or not all(c in "0123456789abcdef" for c in job_id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="job_id inválido",
        )


def _sse(data: str, event: str | None = None) -> bytes:
    """Formata uma mensagem SSE conforme spec."""
    out = ""
    if event:
        out += f"event: {event}\n"
    # Cada linha do `data` precisa ter o prefixo `data: `
    for line in data.splitlines() or [""]:
        out += f"data: {line}\n"
    out += "\n"
    return out.encode()


async def _tail_log_generator(request: Request, job_id: str):
    """
    Async generator que streama linhas do log enquanto o job roda.
    Termina quando o status sai de `running` (succeeded/failed/etc).

    Comportamento:
      1. Lê o histórico inteiro do log de uma vez (catch-up)
      2. Depois polleia o arquivo a cada 1s lendo só o que apareceu
      3. Detecta término via Redis (state.status != 'running')
      4. Envia evento `done` final com status + exit, depois fecha

    Heartbeat: comentário `:` a cada 15s pra manter conexão Apache/proxy viva.
    """
    state = await updater.get_job_state(job_id)
    if state is None:
        yield _sse(json.dumps({"error": "Job não encontrado"}), event="error")
        return

    log_path = Path(state.get("log_path", ""))
    if not log_path or not log_path.exists():
        # Job acabou de iniciar — espera o arquivo aparecer (até 5s)
        for _ in range(50):
            if log_path.exists():
                break
            await asyncio.sleep(0.1)

    last_size = 0
    last_heartbeat = asyncio.get_event_loop().time()
    HEARTBEAT_INTERVAL = 15.0
    POLL_INTERVAL = 1.0

    while True:
        # Cliente fechou a conexão? Para de gerar.
        if await request.is_disconnected():
            return

        # Lê só o delta do arquivo
        try:
            if log_path.exists():
                size = log_path.stat().st_size
                if size > last_size:
                    with log_path.open("rb") as f:
                        f.seek(last_size)
                        chunk = f.read(size - last_size)
                    last_size = size
                    # Pode ter linhas parciais no fim — emite mesmo assim,
                    # cliente concatena no front
                    yield _sse(chunk.decode("utf-8", errors="replace"))
        except Exception:  # noqa: BLE001
            pass

        # Heartbeat — comentário SSE (linha que começa com `:`)
        now = asyncio.get_event_loop().time()
        if now - last_heartbeat >= HEARTBEAT_INTERVAL:
            yield b": heartbeat\n\n"
            last_heartbeat = now

        # Status atual — termina o stream se finalizou
        state = await updater.get_job_state(job_id)
        if state and state.get("status") != "running":
            # Drena qualquer última linha que veio entre as 2 leituras
            if log_path.exists():
                size = log_path.stat().st_size
                if size > last_size:
                    with log_path.open("rb") as f:
                        f.seek(last_size)
                        chunk = f.read(size - last_size)
                    yield _sse(chunk.decode("utf-8", errors="replace"))
            yield _sse(json.dumps(state), event="done")
            return

        await asyncio.sleep(POLL_INTERVAL)


@router.get("/backups")
async def list_backups(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Lista os últimos backups disponíveis pra restore manual."""
    items = updater.list_backups()
    return {"backups": items, "count": len(items)}


class RestoreRequest(BaseModel):
    timestamp: str = Field(min_length=15, max_length=15, description="YYYYMMDD_HHMMSS")


@router.post("/restore", status_code=status.HTTP_202_ACCEPTED)
async def restore_backup(
    body: RestoreRequest,
    request: Request,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Dispara restore de um backup específico (criado por update.sh anterior).
    Reusa lock global — só uma operação por vez. Job_id retornado pode
    ser usado pra acompanhar status/log via endpoints existentes.
    """
    user_id, username_hint = _user_from_payload(payload)
    username = username_hint or await _username_from_payload(user_id)
    xff = request.headers.get("x-forwarded-for", "")
    ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else None)
    try:
        job_id = await updater.restore_backup(body.timestamp, user_id=user_id, username=username, ip=ip)
    except updater.InvalidTimestamp as exc:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(exc)) from None
    except updater.BackupNotFound as exc:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=str(exc)) from None
    except updater.UpdateLocked as exc:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail=str(exc)) from None
    return {"job_id": job_id, "status": "running"}


@router.get("/log/{job_id}")
async def log_stream(
    job_id: str,
    request: Request,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> StreamingResponse:
    """
    SSE stream do log do update em tempo real.

    Cliente:
        const es = new EventSource('/api/v1/updates/log/<job_id>');
        es.onmessage = (e) => appendLine(e.data);  // linhas do log
        es.addEventListener('done', (e) => {       // termino
            const final = JSON.parse(e.data);  // {status, exit_code, ...}
            es.close();
        });
    """
    _validate_job_id(job_id)
    return StreamingResponse(
        _tail_log_generator(request, job_id),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",  # nginx/apache: não bufferiza
            "Connection": "keep-alive",
        },
    )
