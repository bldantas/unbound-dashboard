"""
JWT denylist via Redis — revogação imediata de tokens.

Modelo: **per-user revocation timestamp** (não per-token).

Quando admin desativa/banir um usuário, gravamos em Redis:
    udash:revoke:user:<id>  = <unix_ts>

TTL da key = `jwt_expire_minutes` (60min default) — depois disso,
todos os tokens já expiraram naturalmente.

Em `require_auth`, depois do `decode_token` bem-sucedido, comparamos
o claim `iat` do JWT com esse timestamp:
    iat < revoked_at  →  REJEITAR (401)

Falha do Redis: **fail-open** (assume não-revogado). Razão: denylist
é defesa adicional, não única — JWT já tem `exp`. Bloquear todos os
logins porque Redis caiu é pior que perder a feature de revogação
imediata.
"""

from __future__ import annotations

import time

import structlog

from app.core.config import settings
from app.infrastructure.redis_client import get_redis

log = structlog.get_logger(__name__)

_KEY_PREFIX = "udash:revoke:user:"


def _key_for_user(user_id: int) -> str:
    return f"{_KEY_PREFIX}{user_id}"


async def revoke_user_tokens(user_id: int) -> bool:
    """
    Marca todos os tokens emitidos pra `user_id` ANTES de `now` como
    inválidos. Tokens emitidos após este momento (ex: novo login) são
    aceitos normalmente.

    Retorna True se a gravação no Redis foi OK; False se Redis estiver
    indisponível (caller geralmente loga warn — a revogação não acontece
    e o user fica logado até o JWT expirar naturalmente).
    """
    r = await get_redis()
    try:
        await r.setex(
            _key_for_user(user_id),
            settings.jwt_expire_minutes * 60,
            int(time.time()),
        )
        log.info("jwt_denylist.user_revoked", user_id=user_id)
        return True
    except Exception as exc:  # noqa: BLE001
        log.warning("jwt_denylist.revoke_failed", user_id=user_id, error=str(exc))
        return False


async def is_user_revoked(user_id: int, token_iat: int | None) -> bool:
    """
    Retorna True se o token (com claim `iat`) está revogado — ou seja,
    foi emitido antes do `revoked_at` armazenado em Redis.

    Fail-open: se Redis indisponível, retorna False (não-revogado).
    """
    if token_iat is None:
        # Tokens antigos sem `iat` claim (pré v2.8.4) — não temos como
        # comparar. Aceitar (compat com sessões em andamento).
        return False

    r = await get_redis()
    try:
        raw = await r.get(_key_for_user(user_id))
        if raw is None:
            return False
        revoked_at = int(raw)
        return token_iat < revoked_at
    except Exception as exc:  # noqa: BLE001
        # Fail-open
        log.warning("jwt_denylist.check_failed", user_id=user_id, error=str(exc))
        return False


async def clear_user_revocation(user_id: int) -> None:
    """
    Remove a marca de revogação — usado quando admin re-ativa o user
    (não obrigatório, mas evita rejeitar tokens novos por engano se o
    relógio estiver fora de sincronia).
    """
    r = await get_redis()
    try:
        await r.delete(_key_for_user(user_id))
    except Exception:  # noqa: BLE001
        pass
