"""
Active sessions tracking via Redis.

Cada request autenticada (require_auth) registra/atualiza a sessão em:
    udash:session:<user_id>:<token_hash> = JSON({ip, user_agent, iat, last_seen})

TTL da key = exp do JWT (em segundos). Quando o token expira, a session
desaparece automaticamente — sem cleanup manual.

Apenas tracking, sem persistência. Se Redis cair, perdemos histórico mas
auth continua via JWT.
"""

from __future__ import annotations

import hashlib
import json
import time

import structlog

from app.core.config import settings
from app.infrastructure.redis_client import get_redis

log = structlog.get_logger(__name__)

_PREFIX = "udash:session:"


def hash_token(token: str) -> str:
    """SHA256 truncado em 16 chars — colisão desprezível pra cardinalidade real."""
    return hashlib.sha256(token.encode()).hexdigest()[:16]


def _key(user_id: int, token_hash: str) -> str:
    return f"{_PREFIX}{user_id}:{token_hash}"


def _scan_pattern(user_id: int | None) -> str:
    return f"{_PREFIX}{user_id}:*" if user_id else f"{_PREFIX}*"


async def track(
    user_id: int,
    token_hash: str,
    ip: str,
    user_agent: str,
    iat: int,
    exp: int,
) -> None:
    """
    Grava/atualiza a sessão. Idempotente — chamado a cada request
    autenticada. `last_seen` é atualizado a cada chamada.
    """
    r = await get_redis()
    now = int(time.time())
    ttl = max(60, exp - now)  # mínimo 60s pra evitar TTL negativo em corner cases
    try:
        # GET pra preservar `login_at` original (primeira vez = agora; depois mantém)
        existing_raw = await r.get(_key(user_id, token_hash))
        existing = json.loads(existing_raw) if existing_raw else {}
        login_at = existing.get("login_at", now)
        payload = {
            "user_id": user_id,
            "token_hash": token_hash,
            "ip": ip[:64],
            "user_agent": user_agent[:200],
            "iat": iat,
            "exp": exp,
            "login_at": login_at,
            "last_seen": now,
        }
        await r.setex(_key(user_id, token_hash), ttl, json.dumps(payload))
    except Exception as exc:  # noqa: BLE001
        # Tracking é best-effort — não derruba a request
        log.warning("sessions.track_failed", user_id=user_id, error=str(exc))


async def list_for_user(user_id: int) -> list[dict]:
    """Lista todas as sessões ativas do user."""
    r = await get_redis()
    sessions = []
    try:
        async for key in r.scan_iter(match=_scan_pattern(user_id), count=100):
            raw = await r.get(key)
            if raw:
                try:
                    sessions.append(json.loads(raw))
                except json.JSONDecodeError:
                    pass
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_failed", user_id=user_id, error=str(exc))
        return []
    # Mais recentes primeiro
    sessions.sort(key=lambda s: s.get("last_seen", 0), reverse=True)
    return sessions


async def list_all() -> list[dict]:
    """Todas as sessões ativas (admin-only)."""
    r = await get_redis()
    sessions = []
    try:
        async for key in r.scan_iter(match=_scan_pattern(None), count=100):
            raw = await r.get(key)
            if raw:
                try:
                    sessions.append(json.loads(raw))
                except json.JSONDecodeError:
                    pass
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_all_failed", error=str(exc))
        return []
    sessions.sort(key=lambda s: s.get("last_seen", 0), reverse=True)
    return sessions


async def remove(user_id: int, token_hash: str) -> bool:
    """Remove o tracking da sessão. Não revoga o JWT — chamador faz isso."""
    r = await get_redis()
    try:
        deleted = await r.delete(_key(user_id, token_hash))
        return bool(deleted)
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.remove_failed", error=str(exc))
        return False
