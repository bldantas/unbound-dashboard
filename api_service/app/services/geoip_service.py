"""
GeoIP service — lookup de país por IP com cache Redis.

Estratégia self-contained: usa http://ip-api.com/json/<ip> (gratuito, 45 req/min
por IP de origem, sem chave). Resultados cached em Redis com TTL longo (30 dias)
porque IP→país muda raramente. Cache miss faz a chamada HTTP; falha silencia
pra "?" sem derrubar UI.

Privacidade: só pergunta país (não cidade). Domínios privados (10/8, 192.168/16,
172.16/12, fc00::/7, ::1, localhost) são pulados localmente — não vão pra fora.
"""

from __future__ import annotations

import ipaddress
from typing import Any

import httpx
import structlog

from app.infrastructure.redis_client import get_redis

log = structlog.get_logger(__name__)

_CACHE_PREFIX = "udash:geoip:"
_CACHE_TTL = 30 * 86400  # 30 dias
_HTTP_TIMEOUT = 3.0
_IP_API_URL = "http://ip-api.com/json/{ip}?fields=status,country,countryCode,query"

# Códigos especiais
_PRIVATE_RESULT = {"country_code": "--", "country_name": "Rede privada", "source": "local"}
_UNKNOWN_RESULT = {"country_code": "??", "country_name": "Desconhecido", "source": "fallback"}


def _is_private(ip: str) -> bool:
    try:
        obj = ipaddress.ip_address(ip)
    except (ValueError, TypeError):
        return True
    return obj.is_private or obj.is_loopback or obj.is_link_local or obj.is_multicast


async def _cache_get(ip: str) -> dict[str, Any] | None:
    try:
        r = await get_redis()
        raw = await r.get(_CACHE_PREFIX + ip)
        if not raw:
            return None
        cc, name = raw.split("|", 1)
        return {"country_code": cc, "country_name": name, "source": "cache"}
    except Exception:  # noqa: BLE001
        return None


async def _cache_set(ip: str, cc: str, name: str) -> None:
    try:
        r = await get_redis()
        await r.setex(_CACHE_PREFIX + ip, _CACHE_TTL, f"{cc}|{name}")
    except Exception as exc:  # noqa: BLE001
        log.debug("geoip.cache_set_failed", error=str(exc))


async def lookup(ip: str) -> dict[str, Any]:
    """Resolve IP → país. Lazy + cached. Nunca lança."""
    ip = (ip or "").strip()
    if not ip:
        return {"ip": ip, **_UNKNOWN_RESULT}
    if _is_private(ip):
        return {"ip": ip, **_PRIVATE_RESULT}

    cached = await _cache_get(ip)
    if cached:
        return {"ip": ip, **cached}

    try:
        async with httpx.AsyncClient(timeout=_HTTP_TIMEOUT) as client:
            resp = await client.get(_IP_API_URL.format(ip=ip))
        if resp.status_code != 200:
            return {"ip": ip, **_UNKNOWN_RESULT}
        d = resp.json()
        if d.get("status") != "success":
            return {"ip": ip, **_UNKNOWN_RESULT}
        cc = str(d.get("countryCode") or "??")
        name = str(d.get("country") or "Desconhecido")
        await _cache_set(ip, cc, name)
        return {"ip": ip, "country_code": cc, "country_name": name, "source": "api"}
    except (httpx.RequestError, ValueError) as exc:
        log.debug("geoip.lookup_failed", ip=ip, error=str(exc))
        return {"ip": ip, **_UNKNOWN_RESULT}


async def lookup_many(ips: list[str]) -> dict[str, dict[str, Any]]:
    """Lookup em paralelo de N IPs — usa cache pra evitar saturar 45 req/min."""
    import asyncio

    unique = list(dict.fromkeys(ips))[:200]  # cap em 200 pra não estourar rate-limit
    results = await asyncio.gather(*(lookup(ip) for ip in unique))
    return {r["ip"]: r for r in results}


async def top_countries_blocked(hours: int = 24, limit: int = 20) -> list[dict[str, Any]]:
    """
    Top países dos clientes blocked nas últimas N horas.

    Estratégia: pega top IPs por contagem direto do DuckDB, lookup geoip,
    agrega por country_code. Reduz chamadas externas (só top N IPs, não
    todos os bloqueados).
    """
    from datetime import UTC, datetime
    from app.repositories.duckdb.connection import db_fetchall

    cutoff = int(datetime.now(UTC).timestamp()) - (hours * 3600)
    rows = await db_fetchall(
        """
        SELECT client_ip, COUNT(*) AS hits
        FROM query_logs
        WHERE action = 'blocked' AND timestamp >= ?
        GROUP BY client_ip
        ORDER BY hits DESC
        LIMIT 200
        """,
        [cutoff],
    )
    if not rows:
        return []

    ips = [str(r["client_ip"]) for r in rows]
    geo_map = await lookup_many(ips)

    # Agrega por país
    by_country: dict[str, dict[str, Any]] = {}
    for r in rows:
        ip = str(r["client_ip"])
        hits = int(r["hits"])
        info = geo_map.get(ip, {"country_code": "??", "country_name": "Desconhecido"})
        cc = info["country_code"]
        entry = by_country.setdefault(
            cc,
            {"country_code": cc, "country_name": info["country_name"], "hits": 0, "clients": 0},
        )
        entry["hits"] += hits
        entry["clients"] += 1

    out = sorted(by_country.values(), key=lambda x: x["hits"], reverse=True)
    return out[:limit]
