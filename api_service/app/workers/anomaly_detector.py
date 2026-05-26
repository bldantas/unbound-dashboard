"""
AnomalyDetector — checks heurísticos sobre query_logs.

6 detecções (v2.55):

  - **DGA** (B.5): Shannon entropy alta + length elevado no label esquerdo.
  - **NXDOMAIN spike** (B.5): ratio nxdomain_upstream/total alto por cliente.
  - **Novo cliente** (B.5): IP presente em 24h mas ausente em baseline.
  - **DNS tunneling** (v2.55): muitos subdomínios únicos pro mesmo
    registrable domain por cliente, com label longo e alta entropia.
    Padrão `<base32-payload>.tunnel-domain.com`.
  - **Beaconing** (v2.55): queries periódicas pro mesmo (cliente, dom raiz)
    com baixa variância no inter-arrival time. Padrão típico de C2.
  - **Suspicious TLDs** (v2.55): alto volume de queries pra TLDs com
    correlação histórica com malware (.xyz, .top, .tk, .ml, ...).

Opt-in via `anomaly_enabled` (default false). Tick 5min. Dedupe via
type composto (ex: `anomaly_dga:192.168.1.10`). Cada detector pode ser
suprimido por entradas na tabela `anomaly_whitelist` (V14).
"""

from __future__ import annotations

import asyncio
import math
from collections import Counter

import structlog

from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

CHECK_INTERVAL = 300              # 5 minutos
INITIAL_DELAY_SECONDS = 45

# Defaults — overrideable via /api/v1/analytics/anomaly/settings
DEFAULTS = {
    "anomaly_enabled":                       "0",      # opt-in (master switch)
    # DGA
    "anomaly_dga_window_seconds":            "900",    # 15min
    "anomaly_dga_entropy_min":               "3.5",    # bits/char (random ~3.7+)
    "anomaly_dga_min_length":                "12",     # chars no label esquerdo
    "anomaly_dga_min_count_per_client":      "10",     # X domínios suspeitos = alerta
    # NXDOMAIN spike
    "anomaly_nxdomain_window_seconds":       "600",    # 10min
    "anomaly_nxdomain_spike_ratio":          "0.5",    # 50% nxdomain
    "anomaly_nxdomain_spike_min_count":      "20",     # min queries de nxdomain
    # Novo cliente
    "anomaly_new_client_baseline_days":      "7",
    "anomaly_new_client_window_seconds":     "86400",  # 24h
    "anomaly_new_client_min_queries":        "10",     # filtra "ruído" 1-shot
    # DNS tunneling (v2.55)
    "anomaly_tunneling_enabled":             "1",
    "anomaly_tunneling_window_seconds":      "900",    # 15min
    "anomaly_tunneling_min_unique_subdomains": "20",   # subdom únicos pro mesmo dom raiz / cliente
    "anomaly_tunneling_min_avg_length":      "25",     # comprimento médio do label esquerdo
    "anomaly_tunneling_min_avg_entropy":     "3.3",    # entropia média no label esquerdo
    # Beaconing (v2.55)
    "anomaly_beacon_enabled":                "1",
    "anomaly_beacon_window_seconds":         "1800",   # 30min
    "anomaly_beacon_min_samples":            "8",      # min queries pra calcular
    "anomaly_beacon_max_cv":                 "0.20",   # coef var (stddev/mean) — baixo = beacon
    "anomaly_beacon_min_period_seconds":     "30",     # ignora "burstinho" rápido
    # Suspicious TLDs (v2.55)
    "anomaly_suspicious_tld_enabled":        "1",
    "anomaly_suspicious_tld_window_seconds": "3600",   # 1h
    "anomaly_suspicious_tld_min_count":      "20",     # queries non-blocked pro cliente
    "anomaly_suspicious_tld_list":           ".xyz,.top,.tk,.ml,.ga,.cf,.gq,.icu,.work,.click,.download,.stream,.country,.review,.zip,.mov,.rest",
}


def _shannon_entropy(s: str) -> float:
    """Entropia de Shannon em bits/char. String puro alfabético: ~2-3.
    Random hex: ~4. Random base32/64: ~5-6."""
    if not s:
        return 0.0
    counts = Counter(s)
    n = len(s)
    return -sum((c / n) * math.log2(c / n) for c in counts.values())


def _is_dga_label(label: str, entropy_min: float, length_min: int) -> bool:
    label = label.lower()
    if len(label) < length_min:
        return False
    if not label.replace("-", "").isalnum():
        return False
    # Skip se tem padrão claramente humano (vogal-consoante regular)
    # Heurística simples — quem é DGA real bate entropy alta
    return _shannon_entropy(label) >= entropy_min


async def _setting_float(key: str, default: str) -> float:
    return float(await settings_repo.get(key, default) or default)


async def _setting_int(key: str, default: str) -> int:
    return int(await settings_repo.get(key, default) or default)


async def _setting_bool(key: str, default: str) -> bool:
    return await settings_repo.get_bool(key, default == "1")


_WHITELIST_CACHE: dict[str, list[dict]] = {"all": []}
_WHITELIST_CACHE_TS = 0.0
_WHITELIST_CACHE_TTL = 60.0  # 1min


async def _load_whitelist() -> list[dict]:
    """Carrega anomaly_whitelist cacheado por 60s pra não martelar DuckDB."""
    import time
    global _WHITELIST_CACHE_TS
    now = time.monotonic()
    if now - _WHITELIST_CACHE_TS < _WHITELIST_CACHE_TTL and _WHITELIST_CACHE.get("all"):
        return _WHITELIST_CACHE["all"]
    try:
        rows = await db_fetchall(
            "SELECT kind, client_ip, domain_pattern, detector FROM anomaly_whitelist",
        )
        _WHITELIST_CACHE["all"] = [
            {
                "kind": str(r["kind"] or ""),
                "client_ip": str(r["client_ip"] or ""),
                "domain_pattern": str(r["domain_pattern"] or "").lower(),
                "detector": str(r["detector"] or ""),
            }
            for r in rows
        ]
        _WHITELIST_CACHE_TS = now
    except Exception as exc:  # noqa: BLE001
        log.debug("anomaly.whitelist.load_failed", error=str(exc))
        _WHITELIST_CACHE["all"] = []
    return _WHITELIST_CACHE["all"]


def _whitelist_invalidate() -> None:
    """Chamado pelo router quando whitelist é alterada via PUT/DELETE."""
    global _WHITELIST_CACHE_TS
    _WHITELIST_CACHE_TS = 0.0


async def _is_whitelisted(detector: str, client_ip: str, domain: str = "") -> bool:
    """Checa se (detector, client_ip, domain) bate alguma entrada do whitelist."""
    rules = await _load_whitelist()
    dom_l = (domain or "").lower()
    cip = client_ip or ""
    for r in rules:
        if r["detector"] and r["detector"] != detector:
            continue
        kind = r["kind"]
        if kind == "client_ip":
            if r["client_ip"] and r["client_ip"] == cip:
                return True
        elif kind == "domain":
            p = r["domain_pattern"]
            if p and (p in dom_l):
                return True
        elif kind == "client_and_domain":
            if (
                r["client_ip"] and r["client_ip"] == cip
                and r["domain_pattern"] and r["domain_pattern"] in dom_l
            ):
                return True
    return False


def _registrable_domain(domain: str) -> str:
    """Heurística simples — últimos 2 labels. Imprecisa pra TLDs compostos
    (co.uk, com.br) mas suficiente pra detecção de tunneling/beaconing
    onde false positives são aceitáveis."""
    domain = (domain or "").lower().rstrip(".")
    parts = domain.split(".")
    if len(parts) < 2:
        return domain
    return ".".join(parts[-2:])


async def _raise_alert(alert_type: str, severity: str, message: str) -> None:
    """Mesmo padrão do alert_checker — dedupe + webhook best-effort."""
    existing = await db_fetchone(
        "SELECT id FROM alerts WHERE type = ? AND resolved_at IS NULL LIMIT 1",
        [alert_type],
    )
    if existing:
        return
    await db_execute(
        "INSERT INTO alerts (type, severity, message, started_at) VALUES (?, ?, ?, NOW())",
        [alert_type, severity, message],
    )
    log.warning("anomaly.detected", type=alert_type, severity=severity, message=message)
    from app.services import alerts_broker
    alerts_broker.publish({
        "event": "created", "type": alert_type, "severity": severity, "message": message,
    })
    try:
        from app.services.webhook_notifier import notify as webhook_notify
        await webhook_notify(alert_type, severity, message)
    except Exception as exc:  # noqa: BLE001
        log.warning("anomaly.webhook_failed", type=alert_type, error=str(exc))


# ============================================================
# Detectors
# ============================================================


async def _check_dga() -> int:
    """Detecta clientes consultando domínios com entropy alta no label.

    Estratégia: pega DISTINCT (client, domain) das últimas N min, computa
    entropy do primeiro label, e conta por cliente quantos passam o critério.
    Cliente com count >= threshold → alerta.
    """
    window = await _setting_int("anomaly_dga_window_seconds", DEFAULTS["anomaly_dga_window_seconds"])
    entropy_min = await _setting_float("anomaly_dga_entropy_min", DEFAULTS["anomaly_dga_entropy_min"])
    length_min = await _setting_int("anomaly_dga_min_length", DEFAULTS["anomaly_dga_min_length"])
    count_min = await _setting_int("anomaly_dga_min_count_per_client", DEFAULTS["anomaly_dga_min_count_per_client"])

    rows = await db_fetchall(
        """
        SELECT DISTINCT client_ip, domain
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
          AND domain LIKE '%.%'
        """,
        [window],
    )

    per_client: dict[str, list[str]] = {}
    for r in rows:
        domain = str(r["domain"] or "").lower().rstrip(".")
        if not domain:
            continue
        # Pega o label mais à esquerda (subdomínio mais profundo)
        left = domain.split(".")[0]
        if _is_dga_label(left, entropy_min, length_min):
            per_client.setdefault(str(r["client_ip"] or ""), []).append(domain)

    raised = 0
    for client_ip, domains in per_client.items():
        if len(domains) < count_min:
            continue
        if await _is_whitelisted("dga", client_ip, domains[0] if domains else ""):
            continue
        sample = ", ".join(domains[:3]) + ("..." if len(domains) > 3 else "")
        await _raise_alert(
            f"anomaly_dga:{client_ip}",
            "warning",
            f"Cliente {client_ip} consultou {len(domains)} domínios com padrão DGA ({sample})",
        )
        raised += 1
    return raised


async def _check_nxdomain_spike() -> int:
    window = await _setting_int("anomaly_nxdomain_window_seconds", DEFAULTS["anomaly_nxdomain_window_seconds"])
    ratio_min = await _setting_float("anomaly_nxdomain_spike_ratio", DEFAULTS["anomaly_nxdomain_spike_ratio"])
    count_min = await _setting_int("anomaly_nxdomain_spike_min_count", DEFAULTS["anomaly_nxdomain_spike_min_count"])

    rows = await db_fetchall(
        """
        SELECT
            client_ip,
            COUNT(*)                                          AS total,
            COUNT(*) FILTER (WHERE action='nxdomain_upstream') AS nxd
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        GROUP BY client_ip
        HAVING nxd >= ?
        """,
        [window, count_min],
    )

    raised = 0
    for r in rows:
        total = int(r["total"] or 0)
        nxd = int(r["nxd"] or 0)
        if total == 0:
            continue
        ratio = nxd / total
        if ratio >= ratio_min:
            client_ip = str(r["client_ip"] or "")
            if await _is_whitelisted("nxdomain", client_ip):
                continue
            await _raise_alert(
                f"anomaly_nxdomain_spike:{client_ip}",
                "warning",
                f"Spike NXDOMAIN: cliente {client_ip} com {nxd}/{total} ({ratio*100:.0f}%) NXDOMAIN em {window//60}min",
            )
            raised += 1
    return raised


async def _check_new_clients() -> int:
    """Clientes vistos nas últimas 24h que NÃO apareceram em baseline (7d antes)."""
    window = await _setting_int("anomaly_new_client_window_seconds", DEFAULTS["anomaly_new_client_window_seconds"])
    baseline_days = await _setting_int("anomaly_new_client_baseline_days", DEFAULTS["anomaly_new_client_baseline_days"])
    min_queries = await _setting_int("anomaly_new_client_min_queries", DEFAULTS["anomaly_new_client_min_queries"])
    baseline_secs = baseline_days * 86400

    rows = await db_fetchall(
        """
        WITH recent AS (
            SELECT client_ip, COUNT(*) AS n
            FROM query_logs
            WHERE timestamp >= epoch(NOW()) - ?
              AND client_ip IS NOT NULL AND client_ip <> ''
            GROUP BY client_ip
            HAVING n >= ?
        ),
        baseline AS (
            SELECT DISTINCT client_ip
            FROM query_logs
            WHERE timestamp >= epoch(NOW()) - ? - ?
              AND timestamp <  epoch(NOW()) - ?
        )
        SELECT r.client_ip, r.n
        FROM recent r
        WHERE r.client_ip NOT IN (SELECT client_ip FROM baseline)
        ORDER BY r.n DESC
        LIMIT 50
        """,
        [window, min_queries, baseline_secs, window, window],
    )

    raised = 0
    for r in rows:
        client_ip = str(r["client_ip"] or "")
        n = int(r["n"] or 0)
        if await _is_whitelisted("new_client", client_ip):
            continue
        await _raise_alert(
            f"anomaly_new_client:{client_ip}",
            "info",
            f"Cliente novo detectado: {client_ip} ({n} queries em 24h, ausente em {baseline_days}d antes)",
        )
        raised += 1
    return raised


# ============================================================
# v2.55 — Tunneling, Beaconing, Suspicious TLDs
# ============================================================


async def _check_tunneling() -> int:
    """Detecta DNS tunneling: muitos subdomínios únicos pro mesmo dom raiz
    pelo mesmo cliente, com label longo e alta entropia (base32/base64-like).
    """
    if not await _setting_bool("anomaly_tunneling_enabled", DEFAULTS["anomaly_tunneling_enabled"]):
        return 0
    window = await _setting_int("anomaly_tunneling_window_seconds", DEFAULTS["anomaly_tunneling_window_seconds"])
    min_unique = await _setting_int("anomaly_tunneling_min_unique_subdomains", DEFAULTS["anomaly_tunneling_min_unique_subdomains"])
    min_len = await _setting_float("anomaly_tunneling_min_avg_length", DEFAULTS["anomaly_tunneling_min_avg_length"])
    min_ent = await _setting_float("anomaly_tunneling_min_avg_entropy", DEFAULTS["anomaly_tunneling_min_avg_entropy"])

    rows = await db_fetchall(
        """
        SELECT DISTINCT client_ip, domain
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
          AND domain LIKE '%.%.%'
        """,
        [window],
    )

    # Agrega: (client_ip, registrable_dom) → list[label_esquerdo]
    grouped: dict[tuple[str, str], list[str]] = {}
    for r in rows:
        dom = str(r["domain"] or "").lower().rstrip(".")
        if not dom:
            continue
        parts = dom.split(".")
        if len(parts) < 3:
            continue
        left = parts[0]
        if not left.replace("-", "").isalnum():
            continue
        reg = ".".join(parts[-2:])
        cip = str(r["client_ip"] or "")
        grouped.setdefault((cip, reg), []).append(left)

    raised = 0
    for (cip, reg), labels in grouped.items():
        if len(labels) < min_unique:
            continue
        avg_len = sum(len(x) for x in labels) / len(labels)
        if avg_len < min_len:
            continue
        avg_ent = sum(_shannon_entropy(x) for x in labels) / len(labels)
        if avg_ent < min_ent:
            continue
        if await _is_whitelisted("tunneling", cip, reg):
            continue
        await _raise_alert(
            f"anomaly_tunneling:{cip}:{reg}",
            "critical",
            f"DNS tunneling suspeito: {cip} consultou {len(labels)} subdomínios únicos em {reg} "
            f"(avg_len={avg_len:.0f}, avg_entropy={avg_ent:.2f})",
        )
        raised += 1
    return raised


async def _check_beaconing() -> int:
    """Detecta queries periódicas (C2 beacons) — baixa variância em
    inter-arrival times pro mesmo (cliente, dom raiz).

    Coeficiente de variação (cv = stddev / mean) < threshold → beacon.
    """
    if not await _setting_bool("anomaly_beacon_enabled", DEFAULTS["anomaly_beacon_enabled"]):
        return 0
    window = await _setting_int("anomaly_beacon_window_seconds", DEFAULTS["anomaly_beacon_window_seconds"])
    min_samples = await _setting_int("anomaly_beacon_min_samples", DEFAULTS["anomaly_beacon_min_samples"])
    max_cv = await _setting_float("anomaly_beacon_max_cv", DEFAULTS["anomaly_beacon_max_cv"])
    min_period = await _setting_int("anomaly_beacon_min_period_seconds", DEFAULTS["anomaly_beacon_min_period_seconds"])

    # Pega timestamps ordenados, group by (client_ip, registrable_domain)
    rows = await db_fetchall(
        """
        SELECT client_ip, domain, timestamp
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
          AND domain LIKE '%.%'
        ORDER BY client_ip, domain, timestamp
        """,
        [window],
    )

    grouped: dict[tuple[str, str], list[int]] = {}
    for r in rows:
        dom = str(r["domain"] or "").lower().rstrip(".")
        if not dom:
            continue
        reg = _registrable_domain(dom)
        cip = str(r["client_ip"] or "")
        grouped.setdefault((cip, reg), []).append(int(r["timestamp"] or 0))

    raised = 0
    for (cip, reg), ts_list in grouped.items():
        if len(ts_list) < min_samples:
            continue
        # Inter-arrival times
        deltas = [b - a for a, b in zip(ts_list[:-1], ts_list[1:]) if b - a >= 0]
        if len(deltas) < min_samples - 1:
            continue
        n = len(deltas)
        mean = sum(deltas) / n
        if mean < min_period:
            continue
        var = sum((d - mean) ** 2 for d in deltas) / n
        std = math.sqrt(var)
        cv = std / mean if mean > 0 else 0.0
        if cv > max_cv:
            continue
        if await _is_whitelisted("beaconing", cip, reg):
            continue
        await _raise_alert(
            f"anomaly_beacon:{cip}:{reg}",
            "warning",
            f"Beaconing suspeito: {cip} → {reg} a cada ~{mean:.0f}s "
            f"({n + 1} amostras, cv={cv:.2f})",
        )
        raised += 1
    return raised


async def _check_suspicious_tlds() -> int:
    """Detecta clientes com alto volume de queries pra TLDs problemáticos."""
    if not await _setting_bool("anomaly_suspicious_tld_enabled", DEFAULTS["anomaly_suspicious_tld_enabled"]):
        return 0
    window = await _setting_int("anomaly_suspicious_tld_window_seconds", DEFAULTS["anomaly_suspicious_tld_window_seconds"])
    min_count = await _setting_int("anomaly_suspicious_tld_min_count", DEFAULTS["anomaly_suspicious_tld_min_count"])
    tld_list_raw = await settings_repo.get(
        "anomaly_suspicious_tld_list", DEFAULTS["anomaly_suspicious_tld_list"]
    )
    tlds = [t.strip().lower() for t in str(tld_list_raw or "").split(",") if t.strip()]
    if not tlds:
        return 0

    # Constrói cláusula OR de LIKE — DuckDB lida com isso bem
    like_clauses = " OR ".join(["LOWER(domain) LIKE ?"] * len(tlds))
    params: list = [window]
    for t in tlds:
        params.append(f"%{t}")  # match suffix

    rows = await db_fetchall(
        f"""
        SELECT client_ip, COUNT(*) AS n, MIN(domain) AS sample
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
          AND action <> 'blocked'
          AND ({like_clauses})
        GROUP BY client_ip
        HAVING n >= ?
        """,
        params + [min_count],
    )

    raised = 0
    for r in rows:
        cip = str(r["client_ip"] or "")
        n = int(r["n"] or 0)
        sample = str(r["sample"] or "")
        if await _is_whitelisted("suspicious_tld", cip, sample):
            continue
        await _raise_alert(
            f"anomaly_suspicious_tld:{cip}",
            "info",
            f"Suspicious TLDs: {cip} fez {n} queries pra TLDs problemáticos em "
            f"{window // 60}min (ex: {sample})",
        )
        raised += 1
    return raised


# ============================================================
# Worker
# ============================================================


class AnomalyDetector:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)

        while self._running:
            try:
                enabled = await _setting_bool("anomaly_enabled", DEFAULTS["anomaly_enabled"])
                if enabled:
                    dga = await _check_dga()
                    nxd = await _check_nxdomain_spike()
                    new = await _check_new_clients()
                    tun = await _check_tunneling()
                    bea = await _check_beaconing()
                    tld = await _check_suspicious_tlds()
                    if dga or nxd or new or tun or bea or tld:
                        log.info(
                            "anomaly_detector.tick",
                            dga=dga, nxdomain_spike=nxd, new_clients=new,
                            tunneling=tun, beaconing=bea, suspicious_tld=tld,
                        )
            except Exception as exc:  # noqa: BLE001
                log.warning("anomaly_detector.unexpected_error", error=str(exc))

            slept = 0
            while self._running and slept < CHECK_INTERVAL:
                await asyncio.sleep(10)
                slept += 10

    async def stop(self) -> None:
        self._running = False


async def run_once() -> dict:
    """Executa todos os checks uma vez (consumido pelo endpoint POST /anomaly/run-now).

    Não respeita `anomaly_enabled` — chamado manualmente, sempre roda.
    Cada detector ainda respeita seu próprio toggle (ex: anomaly_tunneling_enabled).
    """
    dga = await _check_dga()
    nxd = await _check_nxdomain_spike()
    new = await _check_new_clients()
    tun = await _check_tunneling()
    bea = await _check_beaconing()
    tld = await _check_suspicious_tlds()
    return {
        "dga": dga,
        "nxdomain_spike": nxd,
        "new_clients": new,
        "tunneling": tun,
        "beaconing": bea,
        "suspicious_tld": tld,
    }
