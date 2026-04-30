"""
Adapter para unbound-control — wrapper assíncrono e seguro.

Todos os comandos passam pelo shell.py (allowlist).
Parsing de `stats_noreset` entrega um dict tipado.
"""

from __future__ import annotations

import re
from typing import Any

from app.core.config import settings
from app.infrastructure.shell import run, CommandError

_BINARY = settings.unbound_control

# Campos numéricos que queremos expor como inteiros
_INT_FIELDS = frozenset({
    "total.num.queries",
    "total.num.cachehits",
    "total.num.cachemiss",
    "total.num.recursivereplies",
    "total.requestlist.avg",
    "total.requestlist.max",
    "total.num.zero_ttl",
    "num.query.flags.QR",
    "num.query.flags.AA",
    "num.query.flags.TC",
    "num.query.flags.RD",
    "num.query.flags.RA",
    "num.query.flags.CD",
    "num.query.flags.AD",
    "mem.total.sbrk",
    "mem.cache.rrset",
    "mem.cache.message",
    "mem.mod.iterator",
    "mem.mod.validator",
})


class UnboundAdapter:
    async def stats(self) -> dict[str, Any]:
        """Executa `unbound-control stats_noreset` e retorna dict de métricas."""
        try:
            output = await run(_BINARY, "stats_noreset", timeout=5.0)
        except CommandError:
            return {}
        return _parse_stats(output)

    async def status(self) -> dict[str, str]:
        """Executa `unbound-control status` e retorna dict de status."""
        try:
            output = await run(_BINARY, "status", timeout=5.0)
        except CommandError:
            return {"status": "offline"}
        return _parse_status(output)

    async def reload(self) -> None:
        """Recarga a configuração do Unbound sem perder cache."""
        await run(_BINARY, "reload", timeout=30.0)

    async def flush(self, domain: str) -> None:
        """Expurga um domínio específico do cache."""
        await run(_BINARY, "flush", domain, timeout=10.0)

    async def flush_all(self) -> None:
        """Limpa todo o cache do Unbound."""
        await run(_BINARY, "flush_all", timeout=30.0)

    async def list_local_zones(self) -> list[str]:
        """Lista zonas locais configuradas."""
        try:
            output = await run(_BINARY, "list_local_zones", timeout=5.0)
            return [line.strip() for line in output.splitlines() if line.strip()]
        except CommandError:
            return []

    async def add_local_zone(self, zone: str, zone_type: str = "redirect") -> None:
        await run(_BINARY, "local_zone", zone, zone_type, timeout=10.0)

    async def remove_local_zone(self, zone: str) -> None:
        await run(_BINARY, "local_zone_remove", zone, timeout=10.0)

    async def version(self) -> str:
        try:
            output = await run(_BINARY, "status", timeout=5.0)
            m = re.search(r"version:\s+(\S+)", output)
            return m.group(1) if m else "unknown"
        except CommandError:
            return "unknown"


def _parse_stats(output: str) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for line in output.splitlines():
        if "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        val = val.strip()
        if key in _INT_FIELDS:
            try:
                result[key] = int(float(val))
            except ValueError:
                result[key] = val
        else:
            try:
                result[key] = float(val)
            except ValueError:
                result[key] = val
    return result


def _parse_status(output: str) -> dict[str, str]:
    result: dict[str, str] = {}
    for line in output.splitlines():
        if ":" in line:
            key, _, val = line.partition(":")
            result[key.strip().lower().replace(" ", "_")] = val.strip()
    return result
