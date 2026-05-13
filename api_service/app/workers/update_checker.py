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

from app.services import updater

log = structlog.get_logger(__name__)

# 6h entre checks. Releases não saem com tanta frequência — pollings mais
# agressivos só queimam rate limit do GitHub (60 req/h sem auth).
CHECK_INTERVAL_SECONDS = 6 * 3600

# Backoff curto após start pra primeiro check rodar logo (UI ter algo cacheado)
INITIAL_DELAY_SECONDS = 30


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
