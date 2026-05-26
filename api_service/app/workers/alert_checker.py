"""
AlertChecker — substitui scripts/cron_alerts.php (PHP cron) + adiciona check
DNS-específico (no_queries via query_logs).

Categorias de alerta cobertas:
  - no_queries        — zero queries DNS nos últimos 10min (query_logs DuckDB)
  - cpu               — load1 > 4.0 (psutil)
  - memory            — RAM uso > 90% (psutil)
  - swap              — swap uso > 50% (psutil)
  - disk              — disco / uso > 90% (shutil)
  - network           — errors OR drops > 100 (psutil)
  - security          — SSH failed logins hoje > 50 (journalctl)
  - webserver         — apache2 systemd unit inativa (systemctl)

NÃO coberto (vs PHP original):
  - DB connection count (PHP usa SHOW GLOBAL STATUS no MariaDB) — MariaDB
    está em tear-down planejado, perde sentido manter check da contagem.
    O check de "MariaDB online" é coberto via systemctl is-active mariadb
    (mas só ativado se settings.check_mariadb=True; default false porque
    o plano tira MariaDB).

Padrão de cada check:
  - Se condição violada → _raise_alert(type, severity, message) com dedupe
  - Se condição OK → _resolve_alert(type) marca alertas ativos como resolvidos

Lições do audit aplicadas:
  - Erros pontuais não crasham o worker (try/except por check)
  - structlog estruturado pra cada alerta criado/resolvido
"""

from __future__ import annotations

import asyncio

import structlog

from app.core.metrics import worker_errors
from app.infrastructure import system_health
from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

CHECK_INTERVAL = 60  # segundos
QUERY_STALL_WINDOW = 600  # 10 minutos sem query DNS = alerta
NO_QUERIES_COOLDOWN_HOURS = 6

# Defaults — usados como fallback se a chave não existir em settings.
# Editáveis via UI (alerts.php → modal "Editar limiares") que faz
# PUT /api/v1/alerts/thresholds → settings_repo.bulk_upsert.
THRESHOLD_DEFAULTS = {
    "alert_threshold_cpu_load1":        4.0,
    "alert_threshold_mem_percent":      90.0,
    "alert_threshold_swap_percent":     50.0,
    "alert_threshold_disk_percent":     90.0,
    "alert_threshold_network_counters": 100.0,
    "alert_threshold_ssh_failed_day":   50.0,
}

WEBSERVER_UNIT = "apache2.service"


async def load_thresholds() -> dict[str, float]:
    """Lê os 6 thresholds de settings, com fallback nos defaults."""
    out = {}
    for key, default in THRESHOLD_DEFAULTS.items():
        out[key] = await settings_repo.get_float(key, default)
    return out


class AlertChecker:
    def __init__(self) -> None:
        self._running = False
        # Thresholds carregados a cada tick (re-ler é barato — 6 selects)
        self._thresholds: dict[str, float] = dict(THRESHOLD_DEFAULTS)

    async def start(self) -> None:
        self._running = True
        while self._running:
            try:
                self._thresholds = await load_thresholds()
            except Exception as exc:  # noqa: BLE001
                log.warning("alert_checker.thresholds_load_failed", error=str(exc))
                # Mantém o último válido (ou defaults)
            await self._run_all_checks()
            await asyncio.sleep(CHECK_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    # ----------------------------------------------------------------- #
    # Orquestração                                                         #
    # ----------------------------------------------------------------- #

    async def _run_all_checks(self) -> None:
        """
        Roda os 8 checks. Cada falha pontual loga + métrica + segue —
        NÃO crasha o worker (supervisor restart é overkill pra erro de query).
        """
        checks = [
            ("no_queries", self._check_no_queries),
            ("cpu", self._check_cpu),
            ("memory", self._check_memory),
            ("swap", self._check_swap),
            ("disk", self._check_disk),
            ("network", self._check_network),
            ("security", self._check_security),
            ("webserver", self._check_webserver),
        ]
        for name, check in checks:
            try:
                await check()
            except Exception as exc:  # noqa: BLE001
                log.error("alert_checker.check_failed", check=name, error=str(exc))
                worker_errors.labels(worker="alert_checker").inc()

    # ----------------------------------------------------------------- #
    # Helpers de raise/resolve com dedupe                                  #
    # ----------------------------------------------------------------- #

    async def _raise_alert(self, alert_type: str, severity: str, message: str) -> None:
        """Cria alerta apenas se não há um ativo do mesmo type (dedupe).
        Após criar, dispara webhook (best-effort, com cooldown próprio)."""
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
        log.warning("alert_checker.created", type=alert_type, severity=severity, message=message)
        # Publish no broker WS pra bell em tempo real
        from app.services import alerts_broker
        alerts_broker.publish({
            "event": "created", "type": alert_type, "severity": severity, "message": message,
        })
        # Webhook best-effort — não derruba o worker
        try:
            from app.services.webhook_notifier import notify as webhook_notify
            await webhook_notify(alert_type, severity, message)
        except Exception as exc:  # noqa: BLE001
            log.warning("alert_checker.webhook_failed", type=alert_type, error=str(exc))

    async def _resolve_alert(self, alert_type: str) -> None:
        """Marca alertas ativos do type como resolvidos (UPDATE WHERE resolved_at IS NULL)."""
        rows = await db_fetchall(
            "SELECT id FROM alerts WHERE type = ? AND resolved_at IS NULL",
            [alert_type],
        )
        if not rows:
            return
        await db_execute(
            "UPDATE alerts SET resolved_at = NOW() WHERE type = ? AND resolved_at IS NULL",
            [alert_type],
        )
        from app.services import alerts_broker
        for r in rows:
            alerts_broker.publish({"event": "resolved", "type": alert_type, "id": int(r["id"])})

    # ----------------------------------------------------------------- #
    # Checks individuais                                                   #
    # ----------------------------------------------------------------- #

    async def _check_no_queries(self) -> None:
        """Zero queries DNS nos últimos QUERY_STALL_WINDOW segundos = alerta DNS travado."""
        row = await db_fetchone(
            """
            SELECT COUNT(*) AS n
            FROM query_logs
            WHERE timestamp >= (epoch(now()) - ?)::INTEGER
            """,
            [QUERY_STALL_WINDOW],
        )
        recent_count = int(row["n"]) if row else 0

        if recent_count > 0:
            # Auto-resolve + dismiss (DNS voltou — não acumular ruído)
            await db_execute(
                """
                UPDATE alerts
                SET resolved_at = NOW(), is_dismissed = true
                WHERE type = 'no_queries' AND resolved_at IS NULL
                """,
                [],
            )
            return

        existing = await db_fetchone(
            "SELECT id FROM alerts WHERE type = 'no_queries' AND resolved_at IS NULL LIMIT 1",
            [],
        )
        if existing:
            return

        # Cooldown 6h pra evitar re-alerta após reconhecimento manual
        recent_alert = await db_fetchone(
            """
            SELECT id FROM alerts
            WHERE type = 'no_queries'
              AND started_at >= NOW() - (INTERVAL '1 hour' * ?)
            ORDER BY started_at DESC LIMIT 1
            """,
            [NO_QUERIES_COOLDOWN_HOURS],
        )
        if recent_alert:
            return

        msg = "Nenhuma query DNS registrada nos últimos 10 minutos."
        await db_execute(
            """
            INSERT INTO alerts (type, severity, message, started_at)
            VALUES ('no_queries', 'critical', ?, NOW())
            """,
            [msg],
        )
        log.warning("alert_checker.created", type="no_queries")
        from app.services import alerts_broker
        alerts_broker.publish({"event": "created", "type": "no_queries", "severity": "critical", "message": msg})

    async def _check_cpu(self) -> None:
        load1 = system_health.cpu_load1()
        threshold = self._thresholds["alert_threshold_cpu_load1"]
        if load1 > threshold:
            await self._raise_alert(
                "cpu", "warning", f"Sobrecarga de CPU: Load Average {load1:.2f}"
            )
        else:
            await self._resolve_alert("cpu")

    async def _check_memory(self) -> None:
        percent = system_health.memory_percent()
        threshold = self._thresholds["alert_threshold_mem_percent"]
        if percent > threshold:
            await self._raise_alert("memory", "critical", f"Falta de RAM: {percent:.1f}% em uso")
        else:
            await self._resolve_alert("memory")

    async def _check_swap(self) -> None:
        percent = system_health.swap_percent()
        threshold = self._thresholds["alert_threshold_swap_percent"]
        if percent > threshold:
            await self._raise_alert("swap", "warning", f"Uso excessivo de Swap: {percent:.1f}%")
        else:
            await self._resolve_alert("swap")

    async def _check_disk(self) -> None:
        percent = system_health.disk_percent_root()
        threshold = self._thresholds["alert_threshold_disk_percent"]
        if percent > threshold:
            await self._raise_alert(
                "disk", "critical", f"Armazenamento Crítico: {percent:.1f}% cheio"
            )
        else:
            await self._resolve_alert("disk")

    async def _check_network(self) -> None:
        net = system_health.network_errors_drops()
        threshold = self._thresholds["alert_threshold_network_counters"]
        if net["errors"] > threshold or net["drops"] > threshold:
            await self._raise_alert(
                "network",
                "warning",
                f"Instabilidade na Rede: {net['errors']} erros e {net['drops']} drops",
            )
        # Note: PHP original NÃO resolve network alerts (counters são acumulativos
        # desde boot — só sobem). Mantemos o mesmo comportamento.

    async def _check_security(self) -> None:
        failed = await system_health.ssh_failed_logins_today()
        threshold = self._thresholds["alert_threshold_ssh_failed_day"]
        if failed > threshold:
            await self._raise_alert(
                "security", "critical", f"Alto nível de falhas SSH hoje: {failed} tentativas."
            )
        else:
            await self._resolve_alert("security")

    async def _check_webserver(self) -> None:
        if not await system_health.is_service_active(WEBSERVER_UNIT):
            await self._raise_alert("webserver", "critical", "O Servidor Web não foi detectado!")
        else:
            await self._resolve_alert("webserver")
