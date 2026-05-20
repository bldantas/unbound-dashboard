"""
Hosts gerenciados pelo master no multi-host setup.

CRUD da tabela `managed_hosts` (V7) + função `poll_host(host)` que faz
chamada HTTP pro `/api/v1/host/status` do agent usando o `api_token`.
Resultado é salvo no banco (last_polled_at, last_status, payload).

Worker `host_poller` chama `poll_all()` periodicamente (Fase 4).
UI consome `list_all()` pra renderizar `/hosts.php` (Fase 5).
"""

from __future__ import annotations

import json
import time
from typing import Any

import httpx
import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

POLL_TIMEOUT = 10.0  # 10s — agents em rede confiável devem responder rápido


class ManagedHostError(Exception):
    """Base."""


class HostNotFound(ManagedHostError):
    pass


class DuplicateHost(ManagedHostError):
    """base_url já cadastrada."""


async def list_all() -> list[dict[str, Any]]:
    """Lista todos os hosts gerenciados com último status."""
    rows = await db_fetchall(
        """
        SELECT id, label, base_url, notes, added_by, added_at,
               last_polled_at, last_status_at, last_status,
               last_status_payload, last_error
        FROM managed_hosts
        ORDER BY label ASC
        """
    )
    out = []
    for r in rows:
        payload = None
        if r.get("last_status_payload"):
            try:
                payload = json.loads(r["last_status_payload"])
            except json.JSONDecodeError:
                pass
        out.append({
            "id": int(r["id"]),
            "label": r["label"],
            "base_url": r["base_url"],
            "notes": r.get("notes") or "",
            "added_by": r.get("added_by"),
            "added_at": r["added_at"].isoformat() if r.get("added_at") else None,
            "last_polled_at": r["last_polled_at"].isoformat() if r.get("last_polled_at") else None,
            "last_status_at": r["last_status_at"].isoformat() if r.get("last_status_at") else None,
            "last_status": r.get("last_status"),
            "last_status_payload": payload,
            "last_error": r.get("last_error"),
        })
    return out


async def get(host_id: int) -> dict[str, Any] | None:
    """Retorna 1 host (com api_token incluso — só pra uso interno do poller)."""
    return await db_fetchone(
        "SELECT id, label, base_url, api_token, added_at FROM managed_hosts WHERE id = ?",
        [host_id],
    )


async def create(
    *,
    label: str,
    base_url: str,
    api_token: str,
    notes: str | None,
    added_by: int | None,
) -> int:
    """Adiciona host. Levanta DuplicateHost se base_url já existe."""
    base_url = base_url.rstrip("/")
    existing = await db_fetchone(
        "SELECT id FROM managed_hosts WHERE base_url = ?",
        [base_url],
    )
    if existing:
        raise DuplicateHost(f"base_url {base_url} já cadastrada (id={existing['id']})")

    await db_execute(
        """
        INSERT INTO managed_hosts (label, base_url, api_token, notes, added_by)
        VALUES (?, ?, ?, ?, ?)
        """,
        [label[:100], base_url[:255], api_token[:255], (notes or "")[:500], added_by],
    )
    row = await db_fetchone(
        "SELECT id FROM managed_hosts WHERE base_url = ?",
        [base_url],
    )
    return int(row["id"]) if row else 0


async def update(
    host_id: int,
    *,
    label: str | None = None,
    api_token: str | None = None,
    notes: str | None = None,
) -> bool:
    """Atualiza label/token/notes. base_url é imutável (use create+delete)."""
    existing = await db_fetchone("SELECT id FROM managed_hosts WHERE id = ?", [host_id])
    if not existing:
        return False
    sets = []
    args: list[Any] = []
    if label is not None:
        sets.append("label = ?")
        args.append(label[:100])
    if api_token is not None and api_token != "":
        sets.append("api_token = ?")
        args.append(api_token[:255])
    if notes is not None:
        sets.append("notes = ?")
        args.append(notes[:500])
    if not sets:
        return True  # nada pra mudar
    args.append(host_id)
    await db_execute(f"UPDATE managed_hosts SET {', '.join(sets)} WHERE id = ?", args)
    return True


async def delete(host_id: int) -> bool:
    """Remove host do inventário. Retorna True se existia."""
    existing = await db_fetchone("SELECT id FROM managed_hosts WHERE id = ?", [host_id])
    if not existing:
        return False
    await db_execute("DELETE FROM managed_hosts WHERE id = ?", [host_id])
    return True


async def poll_host(host_id: int) -> dict[str, Any]:
    """
    Polleia 1 host. Atualiza last_polled_at + last_status + payload no
    banco. Retorna dict com resultado.

    last_status possíveis:
      - "ok"           — HTTP 200 + JSON válido
      - "auth_failed"  — HTTP 401 (token revogado/inválido)
      - "unreachable"  — connect timeout / DNS fail / refused
      - "error"        — outros HTTP ou JSON inválido
    """
    host = await get(host_id)
    if not host:
        raise HostNotFound(f"Host {host_id} não existe")

    base_url = host["base_url"].rstrip("/")
    api_token = host["api_token"]
    url = f"{base_url}/api/v1/host/status"
    now = int(time.time())

    status_label = "error"
    payload_str = None
    err_msg = None

    try:
        async with httpx.AsyncClient(timeout=POLL_TIMEOUT, follow_redirects=True) as client:
            resp = await client.get(url, headers={"X-Api-Token": api_token})
        if resp.status_code == 200:
            try:
                data = resp.json()
                status_label = "ok"
                payload_str = json.dumps(data)
            except Exception as exc:  # noqa: BLE001
                err_msg = f"JSON inválido: {exc}"
        elif resp.status_code == 401:
            status_label = "auth_failed"
            err_msg = "Token inválido ou revogado"
        else:
            err_msg = f"HTTP {resp.status_code}: {resp.text[:200]}"
    except httpx.ConnectError as exc:
        status_label = "unreachable"
        err_msg = f"Conexão recusada: {exc}"
    except httpx.TimeoutException:
        status_label = "unreachable"
        err_msg = f"Timeout após {POLL_TIMEOUT}s"
    except Exception as exc:  # noqa: BLE001
        err_msg = f"{type(exc).__name__}: {exc}"

    # Persiste resultado
    if status_label == "ok":
        await db_execute(
            """
            UPDATE managed_hosts
            SET last_polled_at = NOW(), last_status_at = NOW(),
                last_status = ?, last_status_payload = ?, last_error = NULL
            WHERE id = ?
            """,
            [status_label, payload_str, host_id],
        )
    else:
        await db_execute(
            """
            UPDATE managed_hosts
            SET last_polled_at = NOW(),
                last_status = ?, last_error = ?
            WHERE id = ?
            """,
            [status_label, err_msg, host_id],
        )

    return {
        "id": host_id,
        "label": host["label"],
        "status": status_label,
        "polled_at": now,
        "payload": json.loads(payload_str) if payload_str else None,
        "error": err_msg,
    }


async def poll_all() -> list[dict[str, Any]]:
    """Polleia todos os hosts em paralelo. Usado pelo worker."""
    rows = await db_fetchall("SELECT id FROM managed_hosts")
    results = []
    for r in rows:
        try:
            results.append(await poll_host(int(r["id"])))
        except Exception as exc:  # noqa: BLE001
            log.warning("managed_hosts.poll_failed", id=r.get("id"), error=str(exc))
    return results


# ============================================================
# Proxy calls — master invoca endpoints específicos do agent
# ============================================================
# Diferente do poll, estes não persistem nada no banco do master:
# são pass-through pra UI mostrar drill-down ou disparar batch ops.


async def proxy_get(host_id: int, path: str) -> dict[str, Any]:
    """
    GET `<base_url>/<path>` com X-Api-Token do host. Retorna dict com
    `ok`, `status_code` e (se ok) `data` ou (se !ok) `error`.
    """
    host = await get(host_id)
    if not host:
        raise HostNotFound(f"Host {host_id} não existe")
    base_url = host["base_url"].rstrip("/")
    url = f"{base_url}/{path.lstrip('/')}"
    try:
        async with httpx.AsyncClient(timeout=POLL_TIMEOUT, follow_redirects=True) as client:
            resp = await client.get(url, headers={"X-Api-Token": host["api_token"]})
        if resp.status_code == 200:
            return {"ok": True, "status_code": 200, "data": resp.json()}
        return {
            "ok": False,
            "status_code": resp.status_code,
            "error": resp.text[:300],
        }
    except httpx.ConnectError as exc:
        return {"ok": False, "status_code": 0, "error": f"Conexão recusada: {exc}"}
    except httpx.TimeoutException:
        return {"ok": False, "status_code": 0, "error": f"Timeout após {POLL_TIMEOUT}s"}
    except Exception as exc:  # noqa: BLE001
        return {"ok": False, "status_code": 0, "error": f"{type(exc).__name__}: {exc}"}


async def proxy_post(
    host_id: int,
    path: str,
    body: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """POST `<base_url>/<path>` (com body opcional) usando X-Api-Token."""
    host = await get(host_id)
    if not host:
        raise HostNotFound(f"Host {host_id} não existe")
    base_url = host["base_url"].rstrip("/")
    url = f"{base_url}/{path.lstrip('/')}"
    try:
        async with httpx.AsyncClient(timeout=POLL_TIMEOUT, follow_redirects=True) as client:
            resp = await client.post(
                url,
                headers={
                    "X-Api-Token": host["api_token"],
                    "Content-Type": "application/json",
                },
                json=body or {},
            )
        if resp.status_code in (200, 201, 202, 204):
            try:
                data = resp.json() if resp.content else {}
            except Exception:  # noqa: BLE001
                data = {}
            return {"ok": True, "status_code": resp.status_code, "data": data}
        return {
            "ok": False,
            "status_code": resp.status_code,
            "error": resp.text[:300],
        }
    except httpx.ConnectError as exc:
        return {"ok": False, "status_code": 0, "error": f"Conexão recusada: {exc}"}
    except httpx.TimeoutException:
        return {"ok": False, "status_code": 0, "error": f"Timeout após {POLL_TIMEOUT}s"}
    except Exception as exc:  # noqa: BLE001
        return {"ok": False, "status_code": 0, "error": f"{type(exc).__name__}: {exc}"}


async def restart_service(host_id: int, service: str) -> dict[str, Any]:
    """Dispara restart de api|unbound no agent (whitelisted pelo agent)."""
    return await proxy_post(host_id, f"/api/v1/host/restart/{service}")


async def trigger_upgrade(host_id: int, version: str) -> dict[str, Any]:
    """
    Dispara self-update no agent pra `version` (sem ack de breaking).

    `version` pode ser uma versão exata ("2.23.0") OU o sentinel "latest" —
    nesse último caso, cada agent resolve via seu próprio /updates/check,
    evitando race entre o cache do master e o do agent quando uma release
    sai durante o batch loop.
    """
    return await proxy_post(
        host_id,
        "/api/v1/updates/apply",
        body={"version": version, "acknowledge_breaking": False},
    )


async def batch(
    op: str,
    *,
    service: str | None = None,
    version: str | None = None,
) -> list[dict[str, Any]]:
    """
    Aplica uma operação em todos os hosts. Sequencial pra evitar
    avalanche e pra o caller acompanhar fail-fast se quiser.

    Ops: "restart" (service obrigatório), "upgrade" (version obrigatório).
    """
    rows = await db_fetchall("SELECT id, label FROM managed_hosts")
    results: list[dict[str, Any]] = []
    for r in rows:
        host_id = int(r["id"])
        label = r["label"]
        try:
            if op == "restart":
                if not service:
                    raise ValueError("service obrigatório pra op=restart")
                res = await restart_service(host_id, service)
            elif op == "upgrade":
                if not version:
                    raise ValueError("version obrigatória pra op=upgrade")
                res = await trigger_upgrade(host_id, version)
            else:
                raise ValueError(f"op desconhecida: {op}")
            results.append({"id": host_id, "label": label, **res})
        except Exception as exc:  # noqa: BLE001
            results.append({
                "id": host_id, "label": label, "ok": False,
                "status_code": 0, "error": f"{type(exc).__name__}: {exc}",
            })
    return results
