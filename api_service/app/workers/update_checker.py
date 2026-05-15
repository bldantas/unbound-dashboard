"""
UpdateChecker — worker que polleia GitHub Releases a cada 6h.

Resultado fica em `udash:update:latest` (mesmo Redis key que o cache do
`updater.fetch_latest_release`). Endpoint /api/v1/updates/check lê desse
cache; UI consulta o endpoint.

Lifecycle igual aos outros workers — start/stop, supervisionado em
main.py com backoff exponencial.

Por que worker dedicado em vez de cron? FastAPI já está rodando como
serviço systemd — adicionar 1 task asyncio é zero overhead. Cron exigiria
shell + curl + parse JSON + escrever no Redis a partir de fora do app.
"""

from __future__ import annotations

import asyncio

import structlog

from app.infrastructure.redis_client import get_redis
from app.repositories.duckdb import settings_repo
from app.services import updater

log = structlog.get_logger(__name__)

# 6h entre checks. Releases não saem com tanta frequência — pollings mais
# agressivos só queimam rate limit do GitHub (60 req/h sem auth).
CHECK_INTERVAL_SECONDS = 6 * 3600

# Backoff curto após start pra primeiro check rodar logo (UI ter algo cacheado)
INITIAL_DELAY_SECONDS = 30

# Anti-spam: armazena última tag pra qual notificamos. Se vier de novo a
# mesma, nenhum notify dispara. Reset quando user explicitamente quer.
REDIS_NOTIFIED_TAG = "udash:update:notified_tag"


async def _maybe_notify_new_release(rel: dict) -> None:
    """
    Dispara email/webhook se:
      - Há tag nova (diferente da última pra qual notificamos)
      - notify_email_on_release ou notify_webhook_on_release está ativo

    Idempotente — mesma tag chama uma vez só.
    """
    tag = rel.get("tag_name", "")
    if not tag:
        return

    # Anti-spam: já notificamos essa versão?
    try:
        r = await get_redis()
        last_notified = await r.get(REDIS_NOTIFIED_TAG)
        if last_notified == tag:
            return  # já notificado, skip
    except Exception:  # noqa: BLE001
        # Sem Redis, follow-through — risco mínimo (1 email duplicado em pior caso)
        last_notified = None

    # Confere se a release é mais nova que a VERSION local
    local = updater._read_local_version()
    latest = tag.lstrip("v")
    if not updater._is_newer(latest, local):
        return  # não é update, é mesma ou mais antiga — não notifica

    notify_email = await settings_repo.get_bool("notify_email_on_release", False)
    notify_webhook = await settings_repo.get_bool("notify_webhook_on_release", False)

    sent_any = False

    if notify_email:
        try:
            from app.services import email_notifier
            result = await email_notifier.notify_new_release(rel)
            sent_any = sent_any or (result.get("sent", 0) > 0)
            log.info("update_checker.email_notify", tag=tag, result=result)
        except Exception as exc:  # noqa: BLE001
            log.warning("update_checker.email_notify_failed", tag=tag, error=str(exc))

    if notify_webhook:
        try:
            from app.services import webhook_notifier
            result = await webhook_notifier.notify_new_release(rel)
            sent_any = sent_any or result.get("sent", False)
            log.info("update_checker.webhook_notify", tag=tag, result=result)
        except Exception as exc:  # noqa: BLE001
            log.warning("update_checker.webhook_notify_failed", tag=tag, error=str(exc))

    # Marca como notificada SÓ se efetivamente conseguiu enviar (evita
    # silenciar pra sempre se SMTP/webhook estava off no momento)
    if sent_any:
        try:
            r = await get_redis()
            # TTL longo — 30 dias. Se a mesma versão "renascer" depois,
            # paciência (não deve acontecer com semver).
            await r.setex(REDIS_NOTIFIED_TAG, 30 * 86400, tag)
        except Exception:  # noqa: BLE001
            pass


class UpdateChecker:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Delay inicial pra não competir com migrations/redis no boot
        await asyncio.sleep(INITIAL_DELAY_SECONDS)

        while self._running:
            try:
                # force_refresh=True garante que o worker realmente bate no GitHub
                # (senão a cada loop ele leria o próprio cache de 5min e nunca atualizaria)
                rel = await updater.fetch_latest_release(force_refresh=True)
                log.info(
                    "update_checker.refreshed",
                    tag=rel.get("tag_name"),
                    published_at=rel.get("published_at"),
                )
                # Notifica admins se houver update + notify habilitado
                await _maybe_notify_new_release(rel)
            except updater.GitHubUnavailable as exc:
                log.warning("update_checker.github_unavailable", error=str(exc))
            except Exception as exc:  # noqa: BLE001
                log.warning("update_checker.unexpected_error", error=str(exc))

            # Sleep interruptível: chunks de 30s pra reagir a stop() rápido
            slept = 0
            while self._running and slept < CHECK_INTERVAL_SECONDS:
                await asyncio.sleep(30)
                slept += 30

    async def stop(self) -> None:
        self._running = False
