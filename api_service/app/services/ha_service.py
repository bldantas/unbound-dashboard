"""
HA service — observabilidade do cluster Unbound + manual failover assist.

NÃO implementa VRRP/keepalived automático. Foco em:
1. Manter registro de peers (primary/secondary) e healthchecks 30s.
2. Mostrar status agregado pra operador.
3. Manual failover: promove secondary → primary (só na tabela; rede continua
   sendo gerenciada externamente por DNS round-robin, keepalived, ou
   anycast — dashboard registra a intenção).

Token de cada peer fica em hash bcrypt (igual api_tokens V6); o valor raw
só é mostrado uma vez na criação.
"""

from __future__ import annotations

import json
import secrets
import time
from datetime import datetime
from typing import Any

import bcrypt
import httpx
import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

_HEALTH_TIMEOUT = 5.0
VALID_ROLES = ("primary", "secondary")
VALID_STATUSES = ("ok", "down", "unauthorized", "timeout", "error")


def _to_iso(v: Any) -> str | None:
    if isinstance(v, datetime):
        return v.isoformat()
    return str(v) if v else None


def _row_to_dict(r: dict, include_token_hash: bool = False) -> dict:
    payload = r.get("last_check_payload")
    if isinstance(payload, str):
        try:
            payload = json.loads(payload)
        except Exception:  # noqa: BLE001
            payload = None
    out = {
        "id": int(r["id"]),
        "label": r["label"],
        "api_url": r["api_url"],
        "role": r["role"],
        "priority": int(r.get("priority", 100)),
        "enabled": bool(r["enabled"]),
        "last_check_at": _to_iso(r.get("last_check_at")),
        "last_check_status": r.get("last_check_status"),
        "last_check_latency_ms": r.get("last_check_latency_ms"),
        "last_check_payload": payload,
        "created_at": _to_iso(r.get("created_at")),
        "updated_at": _to_iso(r.get("updated_at")),
    }
    if include_token_hash:
        out["api_token_hash"] = r.get("api_token_hash")
    return out


# ---------- CRUD ----------

async def list_peers() -> list[dict]:
    rows = await db_fetchall(
        "SELECT * FROM ha_peers ORDER BY priority DESC, label ASC",
        [],
    )
    return [_row_to_dict(r) for r in rows]


async def create_peer(label: str, api_url: str, role: str, priority: int = 100) -> dict:
    """Gera token raw + salva hash. Retorna o raw 1x (depois nunca mais)."""
    label = label.strip()
    api_url = api_url.strip().rstrip("/")
    if role not in VALID_ROLES:
        raise ValueError(f"role inválido: {role}")
    if not label or not api_url:
        raise ValueError("label e api_url obrigatórios")

    raw_token = secrets.token_urlsafe(32)
    token_hash = bcrypt.hashpw(raw_token.encode(), bcrypt.gensalt()).decode()

    await db_execute(
        """
        INSERT INTO ha_peers (label, api_url, api_token_hash, role, priority)
        VALUES (?, ?, ?, ?, ?)
        """,
        [label, api_url, token_hash, role, int(priority)],
    )
    row = await db_fetchone("SELECT * FROM ha_peers WHERE label = ?", [label])
    out = _row_to_dict(row)
    out["api_token"] = raw_token  # 1x — UI exibe pra copiar
    return out


async def update_peer(peer_id: int, fields: dict) -> bool:
    sets = []
    params: list = []
    for k in ("label", "api_url", "role", "priority", "enabled"):
        if k in fields:
            v = fields[k]
            if k == "role" and v not in VALID_ROLES:
                raise ValueError(f"role inválido: {v}")
            if k == "api_url":
                v = str(v).strip().rstrip("/")
            if k == "label":
                v = str(v).strip()
            if k == "priority":
                v = max(0, min(1000, int(v)))
            if k == "enabled":
                v = bool(v)
            sets.append(f"{k} = ?")
            params.append(v)
    if not sets:
        return False
    sets.append("updated_at = NOW()")
    params.append(int(peer_id))
    await db_execute(f"UPDATE ha_peers SET {', '.join(sets)} WHERE id = ?", params)
    return True


async def delete_peer(peer_id: int) -> bool:
    row = await db_fetchone("SELECT id FROM ha_peers WHERE id = ?", [int(peer_id)])
    if not row:
        return False
    await db_execute("DELETE FROM ha_peers WHERE id = ?", [int(peer_id)])
    return True


# ---------- Healthcheck ----------

async def check_peer(peer_id: int) -> dict:
    """GET <api_url>/api/v1/health com X-Api-Token. Atualiza last_check_*.

    Como guardamos só o hash, NÃO podemos chamar o peer aqui — o caller
    precisa passar o token raw. Pra healthcheck via worker, exportamos
    tokens raws de um secret store futuro (ou skip e só roda manual).

    Por enquanto: assume token raw vem do peer registrado externamente.
    Sem token raw, marca status='unauthorized' e segue.
    """
    row = await db_fetchone("SELECT * FROM ha_peers WHERE id = ?", [int(peer_id)])
    if not row:
        return {"ok": False, "error": "peer not found"}

    api_url = row["api_url"]
    started = time.time()
    status = "down"
    payload = None
    latency_ms = None

    # Não temos token raw aqui — fazemos check anônimo (vai dar 401 se a API
    # requer auth, ou 200 se /health for público). Healthcheck mais sofisticado
    # exigiria um secrets table separado.
    try:
        async with httpx.AsyncClient(timeout=_HEALTH_TIMEOUT, verify=False) as client:
            r = await client.get(f"{api_url}/api/v1/health")
            latency_ms = int((time.time() - started) * 1000)
            if r.status_code == 200:
                status = "ok"
                payload = r.json() if r.headers.get("content-type", "").startswith("application/json") else None
            elif r.status_code in (401, 403):
                status = "unauthorized"
            else:
                status = "error"
    except httpx.TimeoutException:
        status = "timeout"
        latency_ms = int(_HEALTH_TIMEOUT * 1000)
    except Exception as exc:  # noqa: BLE001
        log.warning("ha.check_peer.failed", peer_id=peer_id, error=str(exc))
        status = "down"

    await db_execute(
        """
        UPDATE ha_peers
        SET last_check_at = NOW(),
            last_check_status = ?,
            last_check_latency_ms = ?,
            last_check_payload = ?,
            updated_at = NOW()
        WHERE id = ?
        """,
        [status, latency_ms, json.dumps(payload) if payload else None, int(peer_id)],
    )
    return {"ok": True, "status": status, "latency_ms": latency_ms, "payload": payload}


async def check_all_enabled() -> list[dict]:
    rows = await db_fetchall(
        "SELECT id FROM ha_peers WHERE enabled = true",
        [],
    )
    results = []
    for r in rows:
        out = await check_peer(int(r["id"]))
        results.append({"peer_id": int(r["id"]), **out})
    return results


# ---------- Manual failover ----------

async def manual_failover(promote_peer_id: int, demote_peer_id: int | None = None) -> dict:
    """Promove peer secondary→primary; opcionalmente demove um primary→secondary.

    NÃO toca em rede/DNS — só registra. Operador faz o cutover real (mudar
    A record, IP virtual, etc) e dashboard reflete o estado do registro.
    """
    promote = await db_fetchone("SELECT * FROM ha_peers WHERE id = ?", [int(promote_peer_id)])
    if not promote:
        return {"ok": False, "error": "promote peer not found"}
    if promote["role"] == "primary":
        return {"ok": False, "error": "peer já é primary"}

    await db_execute(
        "UPDATE ha_peers SET role = 'primary', updated_at = NOW() WHERE id = ?",
        [int(promote_peer_id)],
    )
    if demote_peer_id is not None:
        await db_execute(
            "UPDATE ha_peers SET role = 'secondary', updated_at = NOW() WHERE id = ?",
            [int(demote_peer_id)],
        )

    return {
        "ok": True,
        "promoted": promote["label"],
        "demoted_id": demote_peer_id,
        "note": "Apenas o registro foi atualizado. Configure cutover real (DNS/IP virtual) manualmente.",
    }


# ---------- Status agregado ----------

async def cluster_status() -> dict:
    """Snapshot pro KPI da página /cluster.php."""
    peers = await list_peers()
    primary = [p for p in peers if p["role"] == "primary"]
    secondary = [p for p in peers if p["role"] == "secondary"]
    ok_count = sum(1 for p in peers if p["last_check_status"] == "ok")
    down_count = sum(1 for p in peers if p["last_check_status"] in ("down", "timeout", "error"))
    return {
        "total": len(peers),
        "primary_count": len(primary),
        "secondary_count": len(secondary),
        "ok_count": ok_count,
        "down_count": down_count,
        "has_primary_ok": any(p["role"] == "primary" and p["last_check_status"] == "ok" for p in peers),
        "peers": peers,
    }
