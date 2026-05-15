"""
API tokens — long-lived bearer credentials pra autenticação master → agent.

Diferente do JWT (token de sessão de user, curto, sliding refresh), API
tokens são gerados pelo admin na UI e vinculam-se a uma label. Master
usa esses tokens em todas chamadas pro agent.

Storage: SHA256 do token bruto. Token bruto só é mostrado uma vez (na
geração). Compromisso entre conveniência e segurança — análogo a
GitHub PATs.

Validação: agent aceita Authorization: Bearer <jwt> OU X-Api-Token: <token>.
JWT continua sendo o caminho preferido pra users humanos; api_token é
exclusivo do master.
"""

from __future__ import annotations

import hashlib
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


async def create(label: str, created_by: int | None) -> tuple[int, str]:
    """
    Cria novo API token. Retorna `(id, raw_token)`. Raw token é
    mostrado UMA vez ao caller — depois disso só fica o hash.
    """
    raw = generate_raw_token()
    token_hash = _hash_token(raw)
    await db_execute(
        """
        INSERT INTO api_tokens (label, token_hash, created_by)
        VALUES (?, ?, ?)
        """,
        [label[:100], token_hash, created_by],
    )
    row = await db_fetchone(
        "SELECT id FROM api_tokens WHERE token_hash = ?",
        [token_hash],
    )
    new_id = int(row["id"]) if row else 0
    log.info("api_tokens.created", id=new_id, label=label, by=created_by)
    return new_id, raw


async def list_active(include_revoked: bool = False) -> list[dict[str, Any]]:
    """Lista tokens (sem o hash). Inclui revogados se solicitado."""
    where = "" if include_revoked else "WHERE revoked_at IS NULL"
    rows = await db_fetchall(
        f"""
        SELECT id, label, created_by, created_at, last_used_at,
               last_used_ip, revoked_at
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
        }
        for r in rows
    ]


async def verify(raw_token: str, source_ip: str | None = None) -> dict[str, Any] | None:
    """
    Valida token bruto. Se válido, retorna metadata + atualiza
    last_used_at/last_used_ip. Se inválido/revogado, retorna None.
    """
    if not raw_token or len(raw_token) < 20:
        return None
    token_hash = _hash_token(raw_token)
    row = await db_fetchone(
        """
        SELECT id, label, created_by, revoked_at
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
