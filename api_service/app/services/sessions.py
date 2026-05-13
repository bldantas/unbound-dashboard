"""
Tracking de sessões ativas — Redis (fast path) + DuckDB (persistente).

Estratégia dual-write (V3 migration adicionou tabela `auth_sessions`):

- **Redis**: chave `udash:session:<user_id>:<token_hash>` com payload JSON
  e TTL = exp do JWT. Auto-expira. Reescrito a cada request.
- **DuckDB**: tabela `auth_sessions` (PK token_hash). UPSERT por request,
  mas com **throttle de 30s** — evita pressão no executor pra sessões
  ativas. Persistente entre restarts do Redis.

Bootstrap (`bootstrap_from_duckdb()`): chamado no startup, rehidrata Redis
com sessões não-expiradas + não-revogadas do DuckDB. Limpa rows
expiradas do DuckDB no mesmo passo.

Listagens (`list_for_user`, `list_all`): union Redis ∪ DuckDB, dedupado
por token_hash, prefere o registro com `last_seen` mais recente.

Revogação (`mark_revoked`): seta `revoked_at` no DuckDB + delete do Redis.
O denylist (jwt_denylist) é responsabilidade do chamador.
"""

from __future__ import annotations

import hashlib
import json
import time
from typing import Any

import structlog

from app.infrastructure.redis_client import get_redis
from app.repositories.duckdb.connection import db_execute, db_fetchall

log = structlog.get_logger(__name__)

_PREFIX = "udash:session:"

# Cada sessão só persiste no DuckDB se passou >= 30s desde o último persist.
# Reduz writes drasticamente sem perder fidelidade (last_seen Redis sempre
# atualizado; DuckDB fica ≤30s atrás).
_DUCKDB_THROTTLE_SECONDS = 30


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
    Grava/atualiza sessão. Idempotente — chamado por require_auth a cada
    request. Redis sempre. DuckDB com throttle.
    """
    now = int(time.time())
    ip = ip[:64]
    user_agent = user_agent[:200]

    # --- Redis (fast path, sempre) ---
    redis_ok = False
    last_persisted = 0
    login_at = now
    try:
        r = await get_redis()
        ttl = max(60, exp - now)
        existing_raw = await r.get(_key(user_id, token_hash))
        existing = json.loads(existing_raw) if existing_raw else {}
        login_at = existing.get("login_at", now)
        last_persisted = existing.get("last_persisted_duckdb", 0)
        payload = {
            "user_id": user_id,
            "token_hash": token_hash,
            "ip": ip,
            "user_agent": user_agent,
            "iat": iat,
            "exp": exp,
            "login_at": login_at,
            "last_seen": now,
            "last_persisted_duckdb": last_persisted,  # atualizado abaixo se persistir
        }
        # Persiste no DuckDB se passou throttle OU é primeira gravação dessa sessão
        if now - last_persisted >= _DUCKDB_THROTTLE_SECONDS:
            payload["last_persisted_duckdb"] = now
        await r.setex(_key(user_id, token_hash), ttl, json.dumps(payload))
        redis_ok = True
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.track_redis_failed", user_id=user_id, error=str(exc))

    # --- DuckDB (throttled, best-effort) ---
    # Sem Redis pra ler `last_persisted`, persistimos sempre (Redis off é raro).
    # Com Redis: respeita throttle.
    should_persist = (not redis_ok) or (now - last_persisted >= _DUCKDB_THROTTLE_SECONDS)
    if should_persist:
        try:
            await db_execute(
                """
                INSERT INTO auth_sessions
                    (token_hash, user_id, ip, user_agent, iat, exp, login_at, last_seen, revoked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
                ON CONFLICT (token_hash) DO UPDATE SET
                    ip         = excluded.ip,
                    user_agent = excluded.user_agent,
                    last_seen  = excluded.last_seen
                """,
                [token_hash, user_id, ip, user_agent, iat, exp, login_at, now],
            )
        except Exception as exc:  # noqa: BLE001
            log.warning("sessions.track_duckdb_failed", user_id=user_id, error=str(exc))


async def list_for_user(user_id: int) -> list[dict[str, Any]]:
    """Sessões ativas do user — union Redis ∪ DuckDB."""
    by_hash: dict[str, dict[str, Any]] = {}
    now = int(time.time())

    # Redis primeiro (mais fresco)
    try:
        r = await get_redis()
        async for key in r.scan_iter(match=_scan_pattern(user_id), count=100):
            raw = await r.get(key)
            if raw:
                try:
                    s = json.loads(raw)
                    s.pop("last_persisted_duckdb", None)
                    by_hash[s.get("token_hash", "")] = s
                except json.JSONDecodeError:
                    pass
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_redis_failed", user_id=user_id, error=str(exc))

    # DuckDB complementa (sessões persistidas que o Redis perdeu)
    try:
        rows = await db_fetchall(
            "SELECT token_hash, user_id, ip, user_agent, iat, exp, login_at, last_seen "
            "FROM auth_sessions "
            "WHERE user_id = ? AND revoked_at IS NULL AND exp > ?",
            [user_id, now],
        )
        for row in rows:
            th = row["token_hash"]
            existing = by_hash.get(th)
            if existing is None or row.get("last_seen", 0) > existing.get("last_seen", 0):
                by_hash[th] = row
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_duckdb_failed", user_id=user_id, error=str(exc))

    sessions = list(by_hash.values())
    sessions.sort(key=lambda s: s.get("last_seen", 0), reverse=True)
    return sessions


async def list_all() -> list[dict[str, Any]]:
    """Todas sessões ativas — union Redis ∪ DuckDB. Admin-only."""
    by_hash: dict[str, dict[str, Any]] = {}
    now = int(time.time())

    try:
        r = await get_redis()
        async for key in r.scan_iter(match=_scan_pattern(None), count=100):
            raw = await r.get(key)
            if raw:
                try:
                    s = json.loads(raw)
                    s.pop("last_persisted_duckdb", None)
                    by_hash[s.get("token_hash", "")] = s
                except json.JSONDecodeError:
                    pass
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_all_redis_failed", error=str(exc))

    try:
        rows = await db_fetchall(
            "SELECT token_hash, user_id, ip, user_agent, iat, exp, login_at, last_seen "
            "FROM auth_sessions "
            "WHERE revoked_at IS NULL AND exp > ?",
            [now],
        )
        for row in rows:
            th = row["token_hash"]
            existing = by_hash.get(th)
            if existing is None or row.get("last_seen", 0) > existing.get("last_seen", 0):
                by_hash[th] = row
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.list_all_duckdb_failed", error=str(exc))

    sessions = list(by_hash.values())
    sessions.sort(key=lambda s: s.get("last_seen", 0), reverse=True)
    return sessions


async def remove(user_id: int, token_hash: str) -> bool:
    """
    Marca sessão como revogada no DuckDB + remove tracking no Redis.
    Não toca o denylist do JWT — chamador é responsável.
    """
    now = int(time.time())
    deleted = False
    try:
        r = await get_redis()
        deleted = bool(await r.delete(_key(user_id, token_hash)))
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.remove_redis_failed", error=str(exc))

    try:
        await db_execute(
            "UPDATE auth_sessions SET revoked_at = ? WHERE token_hash = ?",
            [now, token_hash],
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.remove_duckdb_failed", error=str(exc))

    return deleted


async def bootstrap_from_duckdb() -> int:
    """
    Rehidrata Redis a partir do DuckDB no startup do serviço. Útil quando
    Redis acabou de reiniciar e perdeu o estado em memória.

    Retorna: número de sessões rehidratadas.
    Também limpa rows do DuckDB com `exp` muito antigo (>30 dias) pra
    impedir crescimento sem fim.
    """
    now = int(time.time())
    rehydrated = 0

    # 1) Cleanup: deleta rows muito velhas (exp + 30 dias < agora)
    try:
        cutoff = now - (30 * 86400)
        await db_execute("DELETE FROM auth_sessions WHERE exp < ?", [cutoff])
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.bootstrap_cleanup_failed", error=str(exc))

    # 2) Rehydrate: pega sessões ainda válidas e não-revogadas
    try:
        rows = await db_fetchall(
            "SELECT token_hash, user_id, ip, user_agent, iat, exp, login_at, last_seen "
            "FROM auth_sessions "
            "WHERE revoked_at IS NULL AND exp > ?",
            [now],
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.bootstrap_load_failed", error=str(exc))
        return 0

    try:
        r = await get_redis()
        for row in rows:
            user_id = int(row["user_id"])
            token_hash = row["token_hash"]
            exp = int(row["exp"])
            ttl = max(60, exp - now)
            payload = {
                "user_id": user_id,
                "token_hash": token_hash,
                "ip": row.get("ip") or "?",
                "user_agent": row.get("user_agent") or "?",
                "iat": int(row.get("iat") or 0),
                "exp": exp,
                "login_at": int(row.get("login_at") or now),
                "last_seen": int(row.get("last_seen") or now),
                "last_persisted_duckdb": now,
            }
            # Não sobrescreve se Redis já tem a chave (sessão tracked após restart)
            if not await r.exists(_key(user_id, token_hash)):
                await r.setex(_key(user_id, token_hash), ttl, json.dumps(payload))
                rehydrated += 1
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.bootstrap_rehydrate_failed", error=str(exc))

    if rehydrated > 0:
        log.info("sessions.bootstrap_done", rehydrated=rehydrated, total_active=len(rows))
    return rehydrated
