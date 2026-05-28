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
        "has_raw_token": bool(r.get("api_token_raw_encrypted")),
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


async def create_peer(
    label: str,
    api_url: str,
    role: str,
    priority: int = 100,
    keep_raw: bool = False,
    existing_token: str | None = None,
) -> dict:
    """Gera (ou aceita) token + salva hash. Retorna o raw 1x.

    Modelo "shared secret por link": o token é o mesmo em ambos os lados do
    par A↔B. Quem cria primeiro deixa `existing_token=None` (gera); quem
    cria o lado espelho cola o token via `existing_token=<T>`.

    Se `keep_raw=True` (ou se `existing_token` foi fornecido — implícito),
    o raw é cifrado via cipher_service e guardado em
    `api_token_raw_encrypted` pra que o HAPeerMonitor possa mandar como
    X-Api-Token no probe `/api/v1/cluster/peer-ping`.

    Sem `keep_raw` e sem `existing_token`: só hash é guardado; o operador
    precisa anotar o raw retornado (no campo `api_token`) pra usar no
    espelho. Probe será anônimo (vai falhar em /peer-ping).
    """
    from app.services import cipher_service

    label = label.strip()
    api_url = api_url.strip().rstrip("/")
    if role not in VALID_ROLES:
        raise ValueError(f"role inválido: {role}")
    if not label or not api_url:
        raise ValueError("label e api_url obrigatórios")

    existing_token = (existing_token or "").strip() or None
    if existing_token:
        if len(existing_token) < 16:
            raise ValueError("existing_token muito curto (mín 16 chars)")
        raw_token = existing_token
        # Sempre cifra raw quando o operador cola um token (precisa pra
        # outbound probe; sem isso o token vira write-only)
        keep_raw = True
    else:
        raw_token = secrets.token_urlsafe(32)

    token_hash = bcrypt.hashpw(raw_token.encode(), bcrypt.gensalt()).decode()
    encrypted = cipher_service.encrypt(raw_token) if keep_raw else None

    await db_execute(
        """
        INSERT INTO ha_peers (label, api_url, api_token_hash, api_token_raw_encrypted, role, priority)
        VALUES (?, ?, ?, ?, ?, ?)
        """,
        [label, api_url, token_hash, encrypted, role, int(priority)],
    )
    row = await db_fetchone("SELECT * FROM ha_peers WHERE label = ?", [label])
    out = _row_to_dict(row)
    out["api_token"] = raw_token  # 1x — UI exibe pra copiar (ou repete o existing)
    out["keep_raw"] = bool(keep_raw)
    out["reused_token"] = existing_token is not None
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


async def set_peer_token(peer_id: int, token: str) -> bool:
    """Substitui o token de um peer existente.

    Substitui tanto `api_token_hash` (bcrypt do raw) quanto
    `api_token_raw_encrypted` (cipher_service do raw) — assim o peer fica
    coerente nas duas direções: este lado manda o novo token no probe E
    aceita esse mesmo token vindo do outro lado.

    Use-case típico: você criou peer dos dois lados sem coordenar, cada
    um gerou um token diferente. Anota o token de UM dos lados (digamos
    T_A) e cola via este endpoint no OUTRO lado. Agora ambos os lados
    têm hash(T_A) + raw(T_A) — probe bidirecional autenticado funciona.
    """
    from app.services import cipher_service

    token = (token or "").strip()
    if len(token) < 16:
        raise ValueError("token muito curto (mín 16 chars)")
    row = await db_fetchone("SELECT id FROM ha_peers WHERE id = ?", [int(peer_id)])
    if not row:
        return False
    token_hash = bcrypt.hashpw(token.encode(), bcrypt.gensalt()).decode()
    encrypted = cipher_service.encrypt(token)
    await db_execute(
        """
        UPDATE ha_peers
        SET api_token_hash = ?,
            api_token_raw_encrypted = ?,
            updated_at = NOW()
        WHERE id = ?
        """,
        [token_hash, encrypted, int(peer_id)],
    )
    return True


async def delete_peer(peer_id: int) -> bool:
    row = await db_fetchone("SELECT id FROM ha_peers WHERE id = ?", [int(peer_id)])
    if not row:
        return False
    await db_execute("DELETE FROM ha_peers WHERE id = ?", [int(peer_id)])
    return True


# ---------- Healthcheck ----------

async def check_peer(peer_id: int) -> dict:
    """Faz GET <api_url>/api/v1/cluster/peer-ping (autenticado) ou /healthz
    (anônimo) como fallback. Atualiza last_check_*.

    Se o peer tem token raw cifrado guardado (`keep_raw=True` na criação),
    decifra e manda via X-Api-Token contra `/peer-ping`. Caso contrário,
    cai pra `/healthz` (público, sempre 200 se o peer estiver vivo) —
    útil pra ver apenas se está respondendo, sem garantia de auth.

    Status possíveis:
    - `ok`: 200 no endpoint escolhido (autenticado se token presente)
    - `unauthorized`: token configurado mas peer rejeitou (401/403)
    - `not_found`: 404 (endpoint do dashboard ausente — peer rodando versão antiga?)
    - `error`: outras respostas HTTP (5xx etc)
    - `timeout`: sem resposta dentro de `_HEALTH_TIMEOUT`
    - `down`: connection refused / DNS / TLS / etc
    """
    from app.services import cipher_service

    row = await db_fetchone("SELECT * FROM ha_peers WHERE id = ?", [int(peer_id)])
    if not row:
        return {"ok": False, "error": "peer not found"}

    api_url = row["api_url"]
    encrypted_token = row.get("api_token_raw_encrypted")
    headers: dict[str, str] = {}
    raw_token = None
    if encrypted_token:
        raw_token = cipher_service.decrypt(encrypted_token)
        if raw_token:
            headers["X-Api-Token"] = raw_token
            headers["Authorization"] = f"Bearer {raw_token}"

    probe_path = "/api/v1/cluster/peer-ping" if raw_token else "/api/v1/healthz"

    started = time.time()
    status = "down"
    payload: dict | None = None
    latency_ms = None
    error_msg: str | None = None

    try:
        async with httpx.AsyncClient(timeout=_HEALTH_TIMEOUT, verify=False) as client:
            r = await client.get(f"{api_url}{probe_path}", headers=headers)
            latency_ms = int((time.time() - started) * 1000)
            if r.status_code == 200:
                status = "ok"
                if r.headers.get("content-type", "").startswith("application/json"):
                    try:
                        payload = r.json()
                    except Exception:  # noqa: BLE001
                        payload = None
            elif r.status_code in (401, 403):
                status = "unauthorized"
                error_msg = "Peer rejeitou X-Api-Token (não cadastrado no espelho?)"
            elif r.status_code == 404:
                status = "not_found"
                error_msg = f"Peer respondeu 404 em {probe_path} — versão sem o endpoint? (precisa v2.103+)"
            else:
                status = "error"
                error_msg = f"HTTP {r.status_code}"
    except httpx.TimeoutException:
        status = "timeout"
        latency_ms = int(_HEALTH_TIMEOUT * 1000)
        error_msg = f"Sem resposta em {_HEALTH_TIMEOUT}s"
    except Exception as exc:  # noqa: BLE001
        log.warning("ha.check_peer.failed", peer_id=peer_id, error=str(exc))
        status = "down"
        error_msg = str(exc)[:200]

    payload_to_store = dict(payload) if payload else {}
    if error_msg:
        payload_to_store["error"] = error_msg
    payload_to_store["probe_path"] = probe_path
    payload_to_store["authenticated"] = bool(raw_token)

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
        [status, latency_ms, json.dumps(payload_to_store), int(peer_id)],
    )
    return {
        "ok": True,
        "status": status,
        "latency_ms": latency_ms,
        "payload": payload_to_store,
        "error": error_msg,
    }


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
