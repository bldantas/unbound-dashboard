"""
/api/v1/hosts/* — gerência de agents pelo master no multi-host.

CRUD + poll on-demand + proxy calls + batch ops. Capability `config.write`.

Endpoints:
  GET    /api/v1/hosts                          — lista com último status
  POST   /api/v1/hosts                          — adiciona host
  PUT    /api/v1/hosts/{id}                     — atualiza label/api_token/notes
  DELETE /api/v1/hosts/{id}                     — remove
  POST   /api/v1/hosts/{id}/poll                — força poll agora
  GET    /api/v1/hosts/{id}/info                — proxy /host/info do agent
  POST   /api/v1/hosts/{id}/restart/{service}   — restart api|unbound no agent
  POST   /api/v1/hosts/{id}/upgrade             — dispara self-update no agent
  POST   /api/v1/hosts/batch/poll               — re-poll todos (sequencial)
  POST   /api/v1/hosts/batch/restart/{service}  — restart em todos
  POST   /api/v1/hosts/batch/upgrade            — upgrade em todos
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field

from app.core.deps import require_capability, resolve_viewer_org_id
from app.services import managed_hosts

router = APIRouter(prefix="/api/v1/hosts", tags=["hosts"])


# alias local pra reduzir verbosidade — wraps a função compartilhada
async def _viewer_org_id(payload: dict) -> int | None:
    return await resolve_viewer_org_id(payload)

_ALLOWED_RESTART_SERVICES = {"api", "unbound"}


def _scrub(h: dict) -> dict:
    """Remove campos sensíveis da resposta (api_token)."""
    out = dict(h)
    out.pop("api_token", None)
    return out


@router.get("")
async def list_hosts(payload: Annotated[dict, Depends(require_capability("config.write"))]) -> dict:
    viewer_org = await _viewer_org_id(payload)
    items = await managed_hosts.list_all(viewer_org_id=viewer_org)
    return {"hosts": items, "count": len(items), "viewer_org_id": viewer_org}


class HostCreate(BaseModel):
    label: str = Field(min_length=1, max_length=100)
    base_url: str = Field(min_length=8, max_length=255, description="https://host:port (sem /api/...)")
    api_token: str = Field(min_length=20, max_length=255, description="Token gerado em Settings → API Tokens do agent")
    notes: str | None = Field(default=None, max_length=500)
    org_id: int | None = Field(default=None, description="Org dona do host. None = global (visível a todos os admins).")


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_host(
    body: HostCreate,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    if not (body.base_url.startswith("http://") or body.base_url.startswith("https://")):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="base_url precisa começar com http:// ou https://",
        )
    try:
        user_id = int(payload.get("sub", 0))
    except (TypeError, ValueError):
        user_id = None
    # Quem está numa org só pode criar host pra própria org (ou global se não escolheu)
    viewer_org = await _viewer_org_id(payload)
    target_org = body.org_id
    if viewer_org is not None:
        if target_org is None:
            target_org = viewer_org  # default pra própria org
        elif target_org != viewer_org:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Não é permitido criar host em outra organização",
            )
    try:
        new_id = await managed_hosts.create(
            label=body.label,
            base_url=body.base_url,
            api_token=body.api_token,
            notes=body.notes,
            added_by=user_id,
            org_id=target_org,
        )
    except managed_hosts.DuplicateHost as exc:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail=str(exc),
        ) from None
    return {"id": new_id, "label": body.label, "org_id": target_org}


class HostSetOrg(BaseModel):
    org_id: int | None = Field(default=None)


@router.put("/{host_id}/org")
async def set_host_org(
    host_id: int,
    body: HostSetOrg,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Admin global pode mover qualquer host. User org-scoped só remaneja
    hosts da própria org (e só pra própria org ou pra global=None se quiser
    publicar — mas evitamos publicar). Aqui simplificado: só admin global."""
    viewer_org = await _viewer_org_id(payload)
    if viewer_org is not None:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Apenas admin global pode reatribuir org de hosts",
        )
    ok = await managed_hosts.set_org(host_id, body.org_id)
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host ou org não encontrados")
    return {"ok": True, "host_id": host_id, "org_id": body.org_id}


class HostUpdate(BaseModel):
    label: str | None = Field(default=None, min_length=1, max_length=100)
    api_token: str | None = Field(default=None, max_length=255, description="Vazio = manter o atual")
    notes: str | None = Field(default=None, max_length=500)


@router.put("/{host_id}", status_code=status.HTTP_204_NO_CONTENT)
async def update_host(
    host_id: int,
    body: HostUpdate,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> None:
    ok = await managed_hosts.update(
        host_id,
        label=body.label,
        api_token=body.api_token,
        notes=body.notes,
    )
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado")


@router.delete("/{host_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_host(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> None:
    ok = await managed_hosts.delete(host_id)
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado")


class UpgradeRequest(BaseModel):
    version: str = Field(
        min_length=5, max_length=20,
        description="Semver sem 'v' (ex: 2.21.4) OU sentinel 'latest' (cada agent resolve via seu próprio /updates/check — evita race entre caches de master/agent).",
    )


# ============================================================
# Batch ops — aplica em todos os hosts (sequencial).
# IMPORTANTE: estas rotas DEVEM ficar ANTES das `/{host_id}/...`
# porque FastAPI casa na ordem de declaração — `/batch/upgrade`
# tentaria parsear `host_id="batch"` (int) e retornaria 422.
# ============================================================


@router.post("/batch/poll", status_code=status.HTTP_200_OK)
async def batch_poll(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Força poll imediato em todos os hosts. Atualiza banco."""
    results = await managed_hosts.poll_all()
    return {"results": results, "count": len(results)}


@router.post("/batch/restart/{service}", status_code=status.HTTP_202_ACCEPTED)
async def batch_restart(
    service: str,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Restart em todos os hosts. Sequencial — fail isolado por host."""
    if service not in _ALLOWED_RESTART_SERVICES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Serviço inválido: {service}. Permitidos: {sorted(_ALLOWED_RESTART_SERVICES)}",
        )
    results = await managed_hosts.batch("restart", service=service)
    return {"results": results, "count": len(results), "service": service}


@router.post("/batch/upgrade", status_code=status.HTTP_202_ACCEPTED)
async def batch_upgrade(
    body: UpgradeRequest,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Upgrade em todos os hosts pra `version`. Sequencial."""
    results = await managed_hosts.batch("upgrade", version=body.version)
    return {"results": results, "count": len(results), "version": body.version}


# ============================================================
# Operações por host (parametrizadas)
# ============================================================


@router.post("/{host_id}/poll")
async def poll_now(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Força poll imediato do host. Retorna resultado."""
    try:
        result = await managed_hosts.poll_host(host_id)
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None
    return result


@router.get("/{host_id}/info")
async def host_info(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Proxy: GET /api/v1/host/info do agent (estático: hostname, OS, etc)."""
    try:
        return await managed_hosts.proxy_get(host_id, "/api/v1/host/info")
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None


@router.get("/{host_id}/history")
async def host_history(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
    limit: int = 100,
) -> dict:
    """Últimos polls registrados pelo poller. Retenção: HISTORY_RETENTION (100)."""
    if limit < 1 or limit > 500:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="limit deve ser 1..500")
    items = await managed_hosts.list_history(host_id, limit=limit)
    return {"history": items, "count": len(items)}


@router.post("/{host_id}/restart/{service}", status_code=status.HTTP_202_ACCEPTED)
async def restart_host_service(
    host_id: int,
    service: str,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Reinicia api ou unbound no agent específico."""
    if service not in _ALLOWED_RESTART_SERVICES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Serviço inválido: {service}. Permitidos: {sorted(_ALLOWED_RESTART_SERVICES)}",
        )
    try:
        return await managed_hosts.restart_service(host_id, service)
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None


@router.post("/{host_id}/upgrade", status_code=status.HTTP_202_ACCEPTED)
async def upgrade_host(
    host_id: int,
    body: UpgradeRequest,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Dispara self-update no agent pra versão informada."""
    try:
        return await managed_hosts.trigger_upgrade(host_id, body.version)
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None


# ============================================================
# B.4 multi-host sync — push config to agent
# ============================================================


class PushConfigRequest(BaseModel):
    include_blocklists: bool = True
    include_policies: bool = True


@router.post("/{host_id}/push-config")
async def push_config(
    host_id: int,
    body: PushConfigRequest,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Empacota config local (blocklist flags + policies completas) e
    posta no `/api/v1/host/apply-config` do agent. Retorna o resultado
    bruto do agent.
    """
    from app.services import multi_host_sync

    payload: dict = {}
    if body.include_blocklists:
        payload["blocklists"] = await multi_host_sync.build_blocklists_payload()
    if body.include_policies:
        payload["policies"] = await multi_host_sync.build_policies_payload()
    if not payload:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Nada selecionado para sincronizar",
        )

    try:
        result = await managed_hosts.proxy_post(host_id, "/api/v1/host/apply-config", payload)
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None
    return {"sent": payload_summary(payload), "result": result}


def payload_summary(payload: dict) -> dict:
    return {
        "blocklists_count": len(payload.get("blocklists", [])),
        "policies_count": len(payload.get("policies", [])),
    }


@router.post("/batch/push-config")
async def batch_push_config(
    body: PushConfigRequest,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Push config pra todos os hosts. Sequencial, falhas isoladas."""
    from app.services import multi_host_sync

    payload: dict = {}
    if body.include_blocklists:
        payload["blocklists"] = await multi_host_sync.build_blocklists_payload()
    if body.include_policies:
        payload["policies"] = await multi_host_sync.build_policies_payload()
    if not payload:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Nada selecionado para sincronizar",
        )

    hosts = await managed_hosts.list_all()
    results = []
    for h in hosts:
        try:
            r = await managed_hosts.proxy_post(int(h["id"]), "/api/v1/host/apply-config", payload)
            results.append({"host_id": h["id"], "label": h["label"], **r})
        except Exception as exc:  # noqa: BLE001
            results.append({"host_id": h["id"], "label": h["label"], "ok": False, "error": str(exc)})
    return {"sent": payload_summary(payload), "results": results, "count": len(results)}
