"""
Service que monta o sumário de Unbound — espelho exato do shape em
data/latest_stats.json produzido por scripts/aggregate_stats.php.

Cache em memória com TTL 60s (alinhado com o cron PHP atual). Vai pra
Redis no futuro quando outro processo precisar do mesmo cache.
"""

from __future__ import annotations

import asyncio
import json
import time
from pathlib import Path
from typing import Any

from app.infrastructure import unbound
from app.repositories.duckdb import settings_repo, threats_repo

# Path do JSON gerenciado pelo PHP UnboundConfigManager — fonte da verdade pra
# `official_blocklist_enabled` (não está em DB, é arquivo de config local).
_PHP_SETTINGS_JSON = Path("/var/www/html/unbound-dashboard/src/data/settings.json")


def _read_php_setting(key: str, default: Any = None) -> Any:
    try:
        if _PHP_SETTINGS_JSON.exists():
            data = json.loads(_PHP_SETTINGS_JSON.read_text(encoding="utf-8"))
            return data.get(key, default)
    except (OSError, json.JSONDecodeError):
        pass
    return default


_CACHE_TTL = 60  # segundos
_cache: dict[str, Any] = {"data": None, "expires_at": 0.0}
_lock = asyncio.Lock()


def _format_bytes(b: float) -> str:
    if b < 1024:
        return f"{int(b)} B"
    if b < 1_048_576:
        return f"{round(b / 1024, 2)} KB"
    if b < 1_073_741_824:
        return f"{round(b / 1_048_576, 2)} MB"
    return f"{round(b / 1_073_741_824, 2)} GB"


def _parse_histogram(stats: dict[str, Any]) -> list[tuple[float, int]]:
    """Extrai buckets do histograma de tempo de recursão.

    Chaves do unbound têm forma `histogram.<s>.<us>.to.<s>.<us>=<count>`.
    Retorna lista ordenada por upper-bound em segundos: [(upper_sec, count), ...]
    """
    buckets: list[tuple[float, int]] = []
    for key, val in stats.items():
        if not key.startswith("histogram."):
            continue
        parts = key.split(".")
        # histogram . s_lower . us_lower . to . s_upper . us_upper
        if len(parts) != 7 or parts[3] != "to":
            continue
        try:
            s_upper = int(parts[5])
            us_upper = int(parts[6])
            upper = s_upper + us_upper / 1_000_000
            buckets.append((upper, int(val)))
        except (ValueError, TypeError):
            continue
    buckets.sort(key=lambda b: b[0])
    return buckets


def _percentile(buckets: list[tuple[float, int]], p: float) -> float:
    """Calcula percentil aproximado (upper-bound do bucket que cumpre o cap).

    Pega o primeiro bucket cujo CDF >= p. Retorna ms.
    """
    total = sum(c for _, c in buckets)
    if total == 0:
        return 0.0
    target = total * (p / 100.0)
    acc = 0
    for upper, count in buckets:
        acc += count
        if acc >= target:
            return round(upper * 1000.0, 2)
    return round(buckets[-1][0] * 1000.0, 2) if buckets else 0.0


def _format_uptime(seconds: int) -> str:
    days = seconds // 86400
    seconds -= days * 86400
    hours = seconds // 3600
    seconds -= hours * 3600
    minutes = seconds // 60
    parts = []
    if days:
        parts.append(f"{days}d")
    if hours:
        parts.append(f"{hours}h")
    if minutes:
        parts.append(f"{minutes}m")
    return " ".join(parts) or "0m"


async def _build_payload() -> dict[str, Any]:
    multicore_enabled = await settings_repo.get_bool("source_balance_enabled", False)
    instances = (
        await settings_repo.get_int("source_balance_instances", 4) if multicore_enabled else 1
    )
    # `official_blocklist_enabled` mora em src/data/settings.json (gerido pelo
    # PHP UnboundConfigManager), NÃO em DB. Ler direto do arquivo.
    is_judicial_enabled = bool(_read_php_setting("official_blocklist_enabled", False))

    try:
        stats = await unbound.stats_aggregated(instances)
        is_online = bool(stats)
    except Exception:
        # unbound-control falhou (daemon down, sudo errado, etc) — retornar payload "offline"
        stats = {}
        is_online = False

    total_queries = stats.get("total.num.queries", 0)
    uptime_secs = max(1, int(stats.get("time.up", 1)))
    cache_hits = int(stats.get("total.num.cachehits", 0))
    cache_miss = int(stats.get("total.num.cachemiss", 0))

    qps = round(total_queries / uptime_secs, 2)

    latency_recursion = round(stats.get("total.recursion.time.avg", 0) * 1000, 2)
    latency_median = round(stats.get("total.recursion.time.median", 0) * 1000, 2)

    # Histograma → P50/P95/P99 reais (em ms). Vazio se unbound não emitiu buckets.
    hist = _parse_histogram(stats)
    latency_p50 = _percentile(hist, 50.0) if hist else latency_median
    latency_p95 = _percentile(hist, 95.0) if hist else 0.0
    latency_p99 = _percentile(hist, 99.0) if hist else 0.0

    hit_ratio = (
        round((cache_hits / (cache_hits + cache_miss)) * 100, 2)
        if (cache_hits + cache_miss) > 0
        else 0
    )
    miss_ratio = (cache_miss / (cache_hits + cache_miss)) if (cache_hits + cache_miss) > 0 else 1
    latency_avg = round(latency_recursion * miss_ratio, 2)

    dnssec_secure = int(stats.get("num.answer.secure", 0))
    dnssec_bogus = int(stats.get("num.answer.bogus", 0))
    dnssec_total = dnssec_secure + dnssec_bogus
    dnssec_ratio = round((dnssec_secure / dnssec_total) * 100, 2) if dnssec_total > 0 else 0

    # Blocklist counts via DuckDB (categorias do blocklist_domains)
    adware = await threats_repo.db_count_category("Malware/Adware")
    phishing = await threats_repo.db_count_category("Phishing")
    judicial_raw = await threats_repo.db_count_category("Judicial")
    judicial = judicial_raw if is_judicial_enabled else 0

    return {
        "online": is_online,
        "qps": qps,
        "latency_avg": latency_avg,
        "latency_recursion": latency_recursion,
        "latency_median": latency_median,
        "latency_p50": latency_p50,
        "latency_p95": latency_p95,
        "latency_p99": latency_p99,
        "hit_ratio": hit_ratio,
        "dnssec_ratio": dnssec_ratio,
        "dnssec_secure": dnssec_secure,
        "dnssec_bogus": dnssec_bogus,
        "total_queries": int(total_queries),
        "cache_hits": cache_hits,
        "cache_miss": cache_miss,
        "req_list_avg": round(stats.get("total.requestlist.avg", 0), 2),
        "req_list_max": int(stats.get("total.requestlist.max", 0)),
        "tcp_total": int(stats.get("num.query.tcp", 0)),
        "ipv6_total": int(stats.get("num.query.ipv6", 0)),
        "ipv4_total": max(0, int(total_queries) - int(stats.get("num.query.ipv6", 0))),
        "prefetch": int(stats.get("total.num.prefetch", 0)),
        "rrset_mem": _format_bytes(stats.get("mem.cache.rrset", 0)),
        "msg_mem": _format_bytes(stats.get("mem.cache.message", 0)),
        "unwanted_queries": int(stats.get("unwanted.queries", 0)),
        "unwanted_replies": int(stats.get("unwanted.replies", 0)),
        "blocks": {
            "adware": adware,
            "phishing": phishing,
            "judicial": judicial,
            "judicial_enabled": is_judicial_enabled,
        },
        "uptime": uptime_secs,
        "uptime_human": _format_uptime(uptime_secs),
        "timestamp": int(time.time()),
    }


async def get_stats(force_refresh: bool = False) -> dict[str, Any]:
    """
    Retorna o payload do dashboard. Cache em memória 60s — múltiplas requests
    concorrentes esperam o mesmo build (sem thundering herd).
    """
    now = time.monotonic()
    if not force_refresh and _cache["data"] is not None and now < _cache["expires_at"]:
        return _cache["data"]

    async with _lock:
        # Re-check após adquirir lock (outra task pode ter atualizado)
        now = time.monotonic()
        if not force_refresh and _cache["data"] is not None and now < _cache["expires_at"]:
            return _cache["data"]
        payload = await _build_payload()
        _cache["data"] = payload
        _cache["expires_at"] = time.monotonic() + _CACHE_TTL
        return payload
