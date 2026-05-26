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
_IP_API_URL = "http://ip-api.com/json/{ip}?fields=status,country,countryCode,as,query"

# Códigos especiais
_PRIVATE_RESULT = {
    "country_code": "--", "country_name": "Rede privada",
    "asn": "", "asn_name": "", "source": "local",
}
_UNKNOWN_RESULT = {
    "country_code": "??", "country_name": "Desconhecido",
    "asn": "", "asn_name": "", "source": "fallback",
}


def _is_private(ip: str) -> bool:
    try:
        obj = ipaddress.ip_address(ip)
    except (ValueError, TypeError):
        return True
    return obj.is_private or obj.is_loopback or obj.is_link_local or obj.is_multicast


async def _cache_get(ip: str) -> dict[str, Any] | None:
    # Formato cacheado: "cc|name" (legacy) ou "cc|name|asn|asn_name" (v2.54+).
    # Aceita ambos; legacy resulta em ASN vazio (lookup novo preencherá).
    try:
        r = await get_redis()
        raw = await r.get(_CACHE_PREFIX + ip)
        if not raw:
            return None
        parts = raw.split("|", 3)
        if len(parts) == 2:
            cc, name = parts
            return {"country_code": cc, "country_name": name, "asn": "", "asn_name": "", "source": "cache"}
        cc, name, asn, asn_name = (parts + ["", "", "", ""])[:4]
        return {
            "country_code": cc, "country_name": name,
            "asn": asn, "asn_name": asn_name, "source": "cache",
        }
    except Exception:  # noqa: BLE001
        return None


async def _cache_set(ip: str, cc: str, name: str, asn: str = "", asn_name: str = "") -> None:
    try:
        r = await get_redis()
        await r.setex(_CACHE_PREFIX + ip, _CACHE_TTL, f"{cc}|{name}|{asn}|{asn_name}")
    except Exception as exc:  # noqa: BLE001
        log.debug("geoip.cache_set_failed", error=str(exc))


def _split_asn_field(raw: str) -> tuple[str, str]:
    """ip-api.com retorna 'as' como 'AS15169 Google LLC'. Separa em (asn, name)."""
    s = (raw or "").strip()
    if not s:
        return "", ""
    parts = s.split(" ", 1)
    asn = parts[0].strip()  # 'AS15169' ou número
    asn_name = parts[1].strip() if len(parts) > 1 else ""
    return asn, asn_name


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
        asn, asn_name = _split_asn_field(str(d.get("as") or ""))
        await _cache_set(ip, cc, name, asn, asn_name)
        return {
            "ip": ip, "country_code": cc, "country_name": name,
            "asn": asn, "asn_name": asn_name, "source": "api",
        }
    except (httpx.RequestError, ValueError) as exc:
        log.debug("geoip.lookup_failed", ip=ip, error=str(exc))
        return {"ip": ip, **_UNKNOWN_RESULT}


async def lookup_many(ips: list[str]) -> dict[str, dict[str, Any]]:
    """Lookup em paralelo de N IPs — usa cache pra evitar saturar 45 req/min."""
    import asyncio

    unique = list(dict.fromkeys(ips))[:200]  # cap em 200 pra não estourar rate-limit
    results = await asyncio.gather(*(lookup(ip) for ip in unique))
    return {r["ip"]: r for r in results}


async def top_countries(
    hours: int = 24,
    limit: int = 20,
    action: str | None = "blocked",
) -> list[dict[str, Any]]:
    """
    Top países dos clientes nas últimas N horas, opcionalmente filtrado por action.

    Estratégia: pega top IPs por contagem direto do DuckDB (cap 500 cobre cauda
    longa em ambientes médios), lookup geoip em lote, agrega por country_code.

    Args:
        hours: janela em horas (1–720).
        limit: top N países no retorno.
        action: 'blocked' | 'resolved' | 'cached' | 'nxdomain_upstream' | None
                (None = todas as ações).
    """
    from datetime import UTC, datetime
    from app.repositories.duckdb.connection import db_fetchall

    cutoff = int(datetime.now(UTC).timestamp()) - (hours * 3600)
    if action:
        sql = """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs
            WHERE action = ? AND timestamp >= ?
            GROUP BY client_ip
            ORDER BY hits DESC
            LIMIT 500
        """
        params: list[Any] = [action, cutoff]
    else:
        sql = """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs
            WHERE timestamp >= ?
            GROUP BY client_ip
            ORDER BY hits DESC
            LIMIT 500
        """
        params = [cutoff]
    rows = await db_fetchall(sql, params)
    if not rows:
        return []

    ips = [str(r["client_ip"]) for r in rows]
    geo_map = await lookup_many(ips)

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


async def top_countries_blocked(hours: int = 24, limit: int = 20) -> list[dict[str, Any]]:
    """Alias retrocompat — top países dos blocked. Use top_countries(action='blocked')."""
    return await top_countries(hours=hours, limit=limit, action="blocked")


async def top_asns(
    hours: int = 24,
    limit: int = 20,
    action: str | None = "blocked",
) -> list[dict[str, Any]]:
    """Top ASNs (provedores/redes) dos clientes nas últimas N horas.

    Mesma estratégia de top_countries — cap 500 IPs, lookup_many, agrega
    por ASN. Útil pra ver "quais ISPs estão originando o tráfego ruim".
    """
    from datetime import UTC, datetime
    from app.repositories.duckdb.connection import db_fetchall

    cutoff = int(datetime.now(UTC).timestamp()) - (hours * 3600)
    if action:
        sql = """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs
            WHERE action = ? AND timestamp >= ?
            GROUP BY client_ip
            ORDER BY hits DESC
            LIMIT 500
        """
        params: list[Any] = [action, cutoff]
    else:
        sql = """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs
            WHERE timestamp >= ?
            GROUP BY client_ip
            ORDER BY hits DESC
            LIMIT 500
        """
        params = [cutoff]
    rows = await db_fetchall(sql, params)
    if not rows:
        return []

    ips = [str(r["client_ip"]) for r in rows]
    geo_map = await lookup_many(ips)

    by_asn: dict[str, dict[str, Any]] = {}
    for r in rows:
        ip = str(r["client_ip"])
        hits = int(r["hits"])
        info = geo_map.get(ip, {})
        asn = (info.get("asn") or "").strip() or "—"
        asn_name = (info.get("asn_name") or "").strip() or "(sem ASN)"
        cc = info.get("country_code") or "??"
        entry = by_asn.setdefault(
            asn,
            {
                "asn": asn,
                "asn_name": asn_name,
                "country_code": cc,
                "hits": 0,
                "clients": 0,
            },
        )
        entry["hits"] += hits
        entry["clients"] += 1

    out = sorted(by_asn.values(), key=lambda x: x["hits"], reverse=True)
    return out[:limit]
