"""
AnomalyDetector — checks heurísticos sobre query_logs (B.5).

3 detecções no MVP (cada uma reusa o sistema de alerts existente via
`alerts` table + dedupe por type composto):

  - **DGA** (Domain Generation Algorithm): Shannon entropy alta + length
    elevado no label esquerdo. Algoritmos de geração de domínio (botnets,
    malware C2) tipicamente geram strings tipo `q1n5wzkx9j.com` ou
    `kjhgfdsa-mnbvcxz.net`. Filtra clientes top com domínios suspeitos.

  - **NXDOMAIN spike**: ratio nxdomain_upstream / total num cliente >
    threshold (default 50%) com count mínimo. Indica DGA em ação,
    typosquatting bot, ou cache poisoning attempt.

  - **Novo cliente**: client_ip apareceu nas últimas 24h mas NÃO existe
    em baseline (7d antes). Útil pra detectar device novo na rede, ou
    vazamento de DNS resolver pra fora.

Opt-in via setting `anomaly_enabled` (default false). Worker roda a
cada 5min. Pra evitar fadiga de alerta, cada detecção tem type
composto (ex: `anomaly_dga:192.168.1.10`) e dedupe via _raise_alert.
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
    "anomaly_enabled":                       "0",      # opt-in
    "anomaly_dga_window_seconds":            "900",    # 15min
    "anomaly_dga_entropy_min":               "3.5",    # bits/char (random ~3.7+)
    "anomaly_dga_min_length":                "12",     # chars no label esquerdo
    "anomaly_dga_min_count_per_client":      "10",     # X domínios suspeitos = alerta
    "anomaly_nxdomain_window_seconds":       "600",    # 10min
    "anomaly_nxdomain_spike_ratio":          "0.5",    # 50% nxdomain
    "anomaly_nxdomain_spike_min_count":      "20",     # min queries de nxdomain
    "anomaly_new_client_baseline_days":      "7",
    "anomaly_new_client_window_seconds":     "86400",  # 24h
    "anomaly_new_client_min_queries":        "10",     # filtra "ruído" 1-shot
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
        await _raise_alert(
            f"anomaly_new_client:{client_ip}",
            "info",
            f"Cliente novo detectado: {client_ip} ({n} queries em 24h, ausente em {baseline_days}d antes)",
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
                    if dga or nxd or new:
                        log.info("anomaly_detector.tick", dga=dga, nxdomain_spike=nxd, new_clients=new)
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
    """
    dga = await _check_dga()
    nxd = await _check_nxdomain_spike()
    new = await _check_new_clients()
    return {"dga": dga, "nxdomain_spike": nxd, "new_clients": new}
