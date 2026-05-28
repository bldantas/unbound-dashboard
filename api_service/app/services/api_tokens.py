"""
API tokens — long-lived bearer credentials pra autenticação master → agent
e integrações externas (SDKs, scripts, dashboards de terceiros).

Diferente do JWT (token de sessão de user, curto, sliding refresh), API
tokens são gerados pelo admin na UI e vinculam-se a uma label. Master
usa esses tokens em todas chamadas pro agent. Integrações externas usam
tokens com capabilities limitadas (v2.110+).

Storage: SHA256 do token bruto. Token bruto só é mostrado uma vez (na
geração). Compromisso entre conveniência e segurança — análogo a
GitHub PATs.

Validação: agent aceita Authorization: Bearer <jwt> OU X-Api-Token: <token>.
JWT continua sendo o caminho preferido pra users humanos; api_token é
exclusivo do master e de integrações.

Capabilities (v2.110+): coluna `capabilities` JSON na V30 migration.
- NULL ou [] → admin global (backward-compat com tokens pré-v2.110)
- ["cap", ...] → token tem APENAS essas caps (sem fallback admin)
"""

from __future__ import annotations

import hashlib
import json
import secrets
import time
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

_TOKEN_BYTES = 32  # 32 bytes = 64 hex chars = 256 bits de entropia


def _hash_token(raw: str) -> str:
    """SHA256 hex — não-reversível mas determinístico pra lookup."""
    return hashlib.sha256(raw.encode()).hexdigest()


def generate_raw_token() -> str:
    """Gera novo token bruto (URL-safe base64, 256 bits)."""
    return secrets.token_urlsafe(_TOKEN_BYTES)


def _normalize_capabilities(caps: list[str] | None) -> str | None:
    """Normaliza lista de capabilities pra JSON gravado em DB.
    Vazio/None → None (= admin global, backward-compat).
    Lista de strings → JSON serializado, sem duplicatas, ordenado.
    """
    if not caps:
        return None
    valid = sorted({str(c).strip() for c in caps if str(c).strip()})
    if not valid:
        return None
    return json.dumps(valid, ensure_ascii=False)


async def create(
    label: str,
    created_by: int | None,
    capabilities: list[str] | None = None,
) -> tuple[int, str]:
    """
    Cria novo API token. Retorna `(id, raw_token)`. Raw token é
    mostrado UMA vez ao caller — depois disso só fica o hash.

    `capabilities` opcional (v2.110+):
    - None/[] → token é admin global (default, backward-compat)
    - ["cap", ...] → token tem APENAS essas caps (zero acesso a tudo mais)
    """
    raw = generate_raw_token()
    token_hash = _hash_token(raw)
    caps_json = _normalize_capabilities(capabilities)
    await db_execute(
        """
        INSERT INTO api_tokens (label, token_hash, created_by, capabilities)
        VALUES (?, ?, ?, ?)
        """,
        [label[:100], token_hash, created_by, caps_json],
    )
    row = await db_fetchone(
        "SELECT id FROM api_tokens WHERE token_hash = ?",
        [token_hash],
    )
    new_id = int(row["id"]) if row else 0
    log.info(
        "api_tokens.created", id=new_id, label=label,
        by=created_by, scoped=caps_json is not None,
    )
    return new_id, raw


def _decode_caps(raw: Any) -> list[str]:
    """Aceita JSON string, list já desserializada, ou None. Retorna list[str]."""
    if raw is None or raw == "":
        return []
    if isinstance(raw, list):
        return [str(x) for x in raw]
    try:
        parsed = json.loads(str(raw))
        if isinstance(parsed, list):
            return [str(x) for x in parsed]
    except (json.JSONDecodeError, ValueError):
        pass
    return []


async def list_active(include_revoked: bool = False) -> list[dict[str, Any]]:
    """Lista tokens (sem o hash). Inclui revogados se solicitado."""
    where = "" if include_revoked else "WHERE revoked_at IS NULL"
    rows = await db_fetchall(
        f"""
        SELECT id, label, created_by, created_at, last_used_at,
               last_used_ip, revoked_at, capabilities
        FROM api_tokens
        {where}
        ORDER BY created_at DESC
        """
    )
    return [
        {
            "id": int(r["id"]),
            "label": r["label"],
            "created_by": r.get("created_by"),
            "created_at": r["created_at"].isoformat() if r.get("created_at") else None,
            "last_used_at": r["last_used_at"].isoformat() if r.get("last_used_at") else None,
            "last_used_ip": r.get("last_used_ip"),
            "revoked_at": r["revoked_at"].isoformat() if r.get("revoked_at") else None,
            "is_active": r.get("revoked_at") is None,
            "capabilities": _decode_caps(r.get("capabilities")),
            "is_scoped": bool(_decode_caps(r.get("capabilities"))),
        }
        for r in rows
    ]


async def verify(raw_token: str, source_ip: str | None = None) -> dict[str, Any] | None:
    """
    Valida token bruto. Se válido, retorna metadata + atualiza
    last_used_at/last_used_ip. Se inválido/revogado, retorna None.

    Metadata inclui `capabilities` (lista) — vazia = admin global
    (backward-compat com tokens pré-v2.110).
    """
    if not raw_token or len(raw_token) < 20:
        return None
    token_hash = _hash_token(raw_token)
    row = await db_fetchone(
        """
        SELECT id, label, created_by, revoked_at, capabilities
        FROM api_tokens
        WHERE token_hash = ?
        """,
        [token_hash],
    )
    if row is None or row.get("revoked_at") is not None:
        return None

    # Atualiza last_used (best-effort, falha silenciosa não bloqueia auth)
    try:
        await db_execute(
            "UPDATE api_tokens SET last_used_at = NOW(), last_used_ip = ? WHERE id = ?",
            [(source_ip or "")[:64], int(row["id"])],
        )
    except Exception:  # noqa: BLE001
        pass

    return {
        "id": int(row["id"]),
        "label": row["label"],
        "created_by": row.get("created_by"),
        "capabilities": _decode_caps(row.get("capabilities")),
    }


async def revoke(token_id: int) -> bool:
    """Marca token como revogado. Retorna True se existia e foi revogado."""
    row = await db_fetchone("SELECT revoked_at FROM api_tokens WHERE id = ?", [token_id])
    if row is None:
        return False
    if row.get("revoked_at") is not None:
        return False  # já revogado
    await db_execute(
        "UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?",
        [token_id],
    )
    log.info("api_tokens.revoked", id=token_id)
    return True
