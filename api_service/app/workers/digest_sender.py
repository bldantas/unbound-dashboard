"""
DigestSender — envia digest diário de notificações por email pra users que
optaram.

Tick: hourly. Pra cada user com `digest_enabled = true` cuja `digest_hour`
bate com a hora atual (UTC do servidor) e que ainda não recebeu hoje,
agrega notificações das últimas 24h respeitando `severity_min` e
`categories`, e envia 1 email.

Não persiste log estruturado dos envios — só `last_digest_sent_at` no
próprio user_notification_prefs. Falhas viram log + worker_errors metric.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime, timedelta

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import alert_repo
from app.services import email_notifier, notification_prefs_service

log = structlog.get_logger(__name__)

TICK_SECONDS = 3600  # 1x/hora

_SEVERITY_RANK = {"critical": 3, "warning": 2, "info": 1}


def _passes_filter(item: dict, severity_min: str, categories: list[str]) -> bool:
    item_rank = _SEVERITY_RANK.get(str(item.get("severity") or "info"), 1)
    min_rank = _SEVERITY_RANK.get(severity_min, 2)
    if item_rank < min_rank:
        return False
    if categories:
        t = str(item.get("type") or "")
        if not any(t.startswith(prefix) for prefix in categories):
            return False
    return True


def _format_body(username: str, items: list[dict], window_hours: int = 24) -> str:
    if not items:
        return (
            f"Olá {username},\n\n"
            f"Nas últimas {window_hours}h não houve nenhum alerta ou anomalia "
            "que atenda às suas preferências.\n\n"
            "— Unbound Dashboard"
        )
    lines = [
        f"Olá {username},",
        "",
        f"Digest das últimas {window_hours}h ({len(items)} eventos):",
        "",
    ]
    # Ordena por severidade desc, depois por started_at desc
    items_sorted = sorted(
        items,
        key=lambda r: (
            -(_SEVERITY_RANK.get(str(r.get("severity") or "info"), 0)),
            str(r.get("started_at") or ""),
        ),
        reverse=False,
    )
    for it in items_sorted[:100]:  # cap em 100 pra não estourar email
        sev = str(it.get("severity") or "info").upper()
        typ = str(it.get("type") or "?")
        msg = str(it.get("message") or "(sem mensagem)")[:200]
        ts = str(it.get("started_at") or "?")
        lines.append(f"[{sev}] {typ} — {ts}")
        lines.append(f"  {msg}")
        lines.append("")
    if len(items) > 100:
        lines.append(f"... e mais {len(items) - 100} eventos truncados.")
        lines.append("")
    lines.append("Acesse o dashboard pra ver todos.")
    lines.append("")
    lines.append("— Unbound Dashboard")
    return "\n".join(lines)


class DigestSender:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Pequeno offset pra evitar correr exatamente junto com outros workers
        await asyncio.sleep(45)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("digest_sender.cycle_failed", error=str(exc))
                worker_errors.labels(worker="digest_sender").inc()
            await asyncio.sleep(TICK_SECONDS)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        current_hour = datetime.now(UTC).hour
        due = await notification_prefs_service.list_due_for_digest(current_hour)
        if not due:
            log.debug("digest_sender.no_due_users", hour=current_hour)
            return {"sent": 0, "skipped": 0}

        # Pre-carrega config SMTP só 1x
        cfg = await email_notifier._load_smtp_config()  # noqa: SLF001
        if not cfg["enabled"] or not cfg["host"] or not cfg["from_addr"]:
            log.warning("digest_sender.smtp_unavailable", due_count=len(due))
            return {"sent": 0, "skipped": len(due)}

        # Carrega as últimas 24h só 1x (filtra por user depois em memória)
        since = datetime.now(UTC) - timedelta(hours=24)
        rows = await alert_repo.list_filtered(
            limit=500, offset=0,
        )
        # alert_repo.list_filtered não aceita time filter — filtramos aqui
        all_items = []
        for r in rows.get("items", []):
            started = r.get("started_at")
            if isinstance(started, datetime) and started >= since:
                all_items.append({
                    "id": r["id"],
                    "type": r.get("type"),
                    "severity": r.get("severity"),
                    "message": r.get("message"),
                    "started_at": started.isoformat() if isinstance(started, datetime) else started,
                })

        sent = 0
        failed = 0
        for u in due:
            user_id = u["user_id"]
            email = u["email"]
            sev_min = u["severity_min"]
            cats = u["categories"]
            personalized = [it for it in all_items if _passes_filter(it, sev_min, cats)]
            subject = f"[Unbound Dashboard] Digest diário — {len(personalized)} eventos"
            body = _format_body(u.get("username") or email, personalized)
            ok, reason = email_notifier._send_via_smtp(cfg, email, subject, body)  # noqa: SLF001
            if ok:
                sent += 1
                await notification_prefs_service.mark_digest_sent(user_id)
                log.info("digest_sender.sent", to=email, user_id=user_id, items=len(personalized))
            else:
                failed += 1
                log.warning("digest_sender.failed", to=email, reason=reason)

        return {"sent": sent, "failed": failed, "due": len(due)}

    async def run_now(self) -> dict:
        return await self._run_once()
