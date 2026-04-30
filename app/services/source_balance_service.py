"""
SourceBalanceManager — gerencia múltiplas instâncias Unbound (upstream balanceadas).

Versão simplificada para v2: persiste a lista de upstreams em settings DuckDB,
verifica a saúde de cada um via `unbound-control` e expõe métricas agregadas.

Uso principal: ambientes com mais de um servidor Unbound (ex: primário + secundário).
Caso não existam múltiplas instâncias, funciona normalmente com a instância única.
"""

from __future__ import annotations

import asyncio
import json
from dataclasses import dataclass, field
from typing import Any

import structlog

from app.infrastructure.shell import run, CommandError
from app.repositories.duckdb.settings_repo import SettingsRepository

log = structlog.get_logger(__name__)

SETTINGS_KEY = "source_balance_upstreams"


@dataclass
class UpstreamStatus:
    host: str
    port: int
    healthy: bool
    latency_ms: float | None = None
    error: str | None = None
    stats: dict[str, Any] = field(default_factory=dict)


@dataclass
class UpstreamConfig:
    host: str
    port: int = 8953
    label: str = ""
    enabled: bool = True

    def to_dict(self) -> dict:
        return {
            "host": self.host,
            "port": self.port,
            "label": self.label,
            "enabled": self.enabled,
        }

    @classmethod
    def from_dict(cls, d: dict) -> "UpstreamConfig":
        return cls(
            host=d["host"],
            port=int(d.get("port", 8953)),
            label=d.get("label", ""),
            enabled=bool(d.get("enabled", True)),
        )


class SourceBalanceManager:
    """
    Gerencia N instâncias Unbound como upstreams.

    Persiste configuração em `settings` DuckDB — chave `source_balance_upstreams`.
    Verifica saúde em paralelo via `unbound-control -s host:port status`.
    """

    def __init__(self, repo: SettingsRepository | None = None) -> None:
        self._repo = repo or SettingsRepository()

    # ------------------------------------------------------------------ #
    # Configuração de upstreams                                            #
    # ------------------------------------------------------------------ #

    async def list_upstreams(self) -> list[UpstreamConfig]:
        raw = await self._repo.get(SETTINGS_KEY)
        if not raw:
            return []
        try:
            items = json.loads(raw)
            return [UpstreamConfig.from_dict(i) for i in items]
        except (json.JSONDecodeError, KeyError):
            return []

    async def add_upstream(self, host: str, port: int = 8953, label: str = "") -> None:
        upstreams = await self.list_upstreams()
        if any(u.host == host and u.port == port for u in upstreams):
            raise ValueError(f"Upstream {host}:{port} já existe")
        upstreams.append(UpstreamConfig(host=host, port=port, label=label))
        await self._save(upstreams)

    async def remove_upstream(self, host: str, port: int = 8953) -> None:
        upstreams = await self.list_upstreams()
        upstreams = [u for u in upstreams if not (u.host == host and u.port == port)]
        await self._save(upstreams)

    async def set_enabled(self, host: str, port: int, enabled: bool) -> None:
        upstreams = await self.list_upstreams()
        for u in upstreams:
            if u.host == host and u.port == port:
                u.enabled = enabled
        await self._save(upstreams)

    # ------------------------------------------------------------------ #
    # Health check paralelo                                                #
    # ------------------------------------------------------------------ #

    async def check_all(self) -> list[UpstreamStatus]:
        upstreams = await self.list_upstreams()
        if not upstreams:
            return []

        results = await asyncio.gather(
            *[self._check_one(u) for u in upstreams if u.enabled],
            return_exceptions=True,
        )

        statuses: list[UpstreamStatus] = []
        for u, r in zip([x for x in upstreams if x.enabled], results):
            if isinstance(r, Exception):
                statuses.append(UpstreamStatus(host=u.host, port=u.port, healthy=False, error=str(r)))
            else:
                statuses.append(r)  # type: ignore[arg-type]

        return statuses

    async def healthy_count(self) -> int:
        statuses = await self.check_all()
        return sum(1 for s in statuses if s.healthy)

    # ------------------------------------------------------------------ #
    # Estatísticas agregadas de todos os upstreams saudáveis              #
    # ------------------------------------------------------------------ #

    async def aggregate_stats(self) -> dict[str, Any]:
        """
        Retorna a soma das métricas `num.queries` e `num.cache_hits`
        de todos os upstreams saudáveis.
        """
        statuses = await self.check_all()
        total_queries = 0
        total_cache = 0
        up = 0

        for s in statuses:
            if not s.healthy:
                continue
            up += 1
            raw = s.stats.get("raw", "")
            for line in raw.splitlines():
                if line.startswith("total.num.queries="):
                    try:
                        total_queries += int(line.split("=")[1])
                    except ValueError:
                        pass
                elif line.startswith("total.num.cache_hits="):
                    try:
                        total_cache += int(line.split("=")[1])
                    except ValueError:
                        pass

        return {
            "upstreams_total": len(statuses),
            "upstreams_healthy": up,
            "total_queries": total_queries,
            "total_cache_hits": total_cache,
        }

    # ------------------------------------------------------------------ #
    # Internos                                                             #
    # ------------------------------------------------------------------ #

    async def _check_one(self, u: UpstreamConfig) -> UpstreamStatus:
        import time
        from app.core.config import settings

        control_bin = settings.unbound_control
        t0 = time.monotonic()
        try:
            out = await run(control_bin, "-s", f"{u.host}@{u.port}", "stats_noreset", timeout=5.0)
            latency = (time.monotonic() - t0) * 1000
            return UpstreamStatus(
                host=u.host,
                port=u.port,
                healthy=True,
                latency_ms=round(latency, 2),
                stats={"raw": out},
            )
        except (CommandError, TimeoutError) as exc:
            return UpstreamStatus(
                host=u.host,
                port=u.port,
                healthy=False,
                latency_ms=None,
                error=str(exc),
            )

    async def _save(self, upstreams: list[UpstreamConfig]) -> None:
        await self._repo.set(SETTINGS_KEY, json.dumps([u.to_dict() for u in upstreams]))
