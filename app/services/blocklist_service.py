"""
BlocklistService — sincronização e gerenciamento de blocklists.
Mantém zonas locais no Unbound para bloquear domínios.

Fontes suportadas:
- URLs HTTP/S com listas de domínios (um por linha, ou formato hosts)
- Zonas locais manuais (adicionar/remover via API)
"""

from __future__ import annotations

import asyncio
import re
import time
from pathlib import Path
from typing import Any

import structlog

from app.core.config import settings
from app.infrastructure.shell import run, CommandError
from app.infrastructure.unbound import UnboundAdapter
from app.repositories.duckdb.settings_repo import SettingsRepository

log = structlog.get_logger(__name__)

_COMMENT_RE = re.compile(r"#.*$")
_HOSTS_RE = re.compile(r"^\s*\d+\.\d+\.\d+\.\d+\s+(\S+)")
_DOMAIN_RE = re.compile(r"^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$")


class BlocklistService:
    def __init__(
        self,
        adapter: UnboundAdapter | None = None,
        settings_repo: SettingsRepository | None = None,
    ) -> None:
        self._adapter = adapter or UnboundAdapter()
        self._repo = settings_repo or SettingsRepository()

    async def get_sources(self) -> list[dict]:
        """Retorna a lista de fontes de blocklist configuradas."""
        raw = await self._repo.get("blocklist_sources")
        if not raw:
            return []
        import json
        try:
            return json.loads(raw)
        except Exception:
            return []

    async def add_source(self, name: str, url: str) -> None:
        """Adiciona uma nova fonte de blocklist."""
        import json
        sources = await self.get_sources()
        if any(s["url"] == url for s in sources):
            raise ValueError(f"Fonte já existe: {url}")
        sources.append({"name": name, "url": url, "enabled": True, "last_sync": None})
        await self._repo.set("blocklist_sources", json.dumps(sources))

    async def remove_source(self, url: str) -> None:
        import json
        sources = [s for s in await self.get_sources() if s["url"] != url]
        await self._repo.set("blocklist_sources", json.dumps(sources))

    async def sync_all(self) -> dict[str, Any]:
        """Baixa todas as fontes habilitadas e aplica ao Unbound."""
        sources = await self.get_sources()
        enabled = [s for s in sources if s.get("enabled", True)]

        if not enabled:
            return {"synced": 0, "domains": 0, "errors": []}

        results = await asyncio.gather(
            *[self._sync_source(s) for s in enabled],
            return_exceptions=True,
        )

        total_domains = 0
        errors = []
        for source, result in zip(enabled, results):
            if isinstance(result, Exception):
                errors.append({"source": source["name"], "error": str(result)})
            else:
                total_domains += result

        return {
            "synced": len(enabled) - len(errors),
            "domains": total_domains,
            "errors": errors,
        }

    async def add_domain(self, domain: str) -> None:
        """Bloqueia um domínio manualmente."""
        domain = domain.lower().rstrip(".")
        if not _DOMAIN_RE.match(domain):
            raise ValueError(f"Domínio inválido: {domain}")
        await self._adapter.add_local_zone(f"{domain}.", "refuse")
        log.info("blocklist.add_domain", domain=domain)

    async def remove_domain(self, domain: str) -> None:
        """Remove um domínio bloqueado manualmente."""
        domain = domain.lower().rstrip(".")
        await self._adapter.remove_local_zone(f"{domain}.")
        log.info("blocklist.remove_domain", domain=domain)

    async def list_blocked(self) -> list[str]:
        zones = await self._adapter.list_local_zones()
        return [z.rstrip(".") for z in zones if z]

    # ------------------------------------------------------------------ #
    # Internos                                                             #
    # ------------------------------------------------------------------ #

    async def _sync_source(self, source: dict) -> int:
        """Baixa uma fonte e aplica ao Unbound. Retorna número de domínios."""
        import urllib.request
        url = source["url"]

        loop = asyncio.get_event_loop()
        raw = await loop.run_in_executor(None, _download, url)
        domains = _parse_list(raw)

        if not domains:
            log.warning("blocklist.sync.empty", url=url)
            return 0

        # Aplica em lote via unbound-control local_zone
        for domain in domains:
            try:
                await self._adapter.add_local_zone(f"{domain}.", "refuse")
            except CommandError:
                pass  # domínio já existe — ignora

        log.info("blocklist.sync.done", url=url, count=len(domains))
        return len(domains)


def _download(url: str) -> str:
    import urllib.request
    with urllib.request.urlopen(url, timeout=30) as r:  # noqa: S310
        return r.read(10 * 1024 * 1024).decode(errors="replace")  # max 10 MB


def _parse_list(raw: str) -> list[str]:
    """Parseia lista de domínios (formato hosts ou um por linha)."""
    domains = []
    for line in raw.splitlines():
        line = _COMMENT_RE.sub("", line).strip()
        if not line:
            continue
        m = _HOSTS_RE.match(line)
        if m:
            domain = m.group(1).lower()
        else:
            domain = line.lower().rstrip(".")

        # Filtra localhost e entradas inválidas
        if domain in ("localhost", "localhost.localdomain", "broadcasthost"):
            continue
        if _DOMAIN_RE.match(domain):
            domains.append(domain)

    return list(set(domains))
