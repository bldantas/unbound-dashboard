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
import html as html_lib
from datetime import UTC, datetime, timedelta

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import alert_repo
from app.services import email_notifier, notification_prefs_service

log = structlog.get_logger(__name__)

TICK_SECONDS = 3600  # 1x/hora
DIGEST_ITEMS_CAP = 500  # Acima disso, trunca com CTA pro dashboard

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


_HTML_SEVERITY_STYLE = {
    "critical": ("#dc2626", "#fee2e2"),
    "warning":  ("#d97706", "#fef3c7"),
    "info":     ("#2563eb", "#dbeafe"),
}


def _format_body(
    username: str,
    items: list[dict],
    window_hours: int = 24,
    part: int = 1,
    parts: int = 1,
    total_items: int | None = None,
) -> str:
    """Plain-text body — fallback pra clientes que não renderizam HTML.

    Quando paginado (parts > 1), `items` é o chunk dessa parte e
    `total_items` é o total geral pra mostrar no header.
    """
    if not items:
        return (
            f"Olá {username},\n\n"
            f"Nas últimas {window_hours}h não houve nenhum alerta ou anomalia "
            "que atenda às suas preferências.\n\n"
            "— Unbound Dashboard"
        )
    total = total_items if total_items is not None else len(items)
    part_label = f" — parte {part}/{parts}" if parts > 1 else ""
    lines = [
        f"Olá {username},",
        "",
        f"Digest das últimas {window_hours}h ({total} eventos{part_label}):",
        "",
    ]
    items_sorted = sorted(
        items,
        key=lambda r: (
            -(_SEVERITY_RANK.get(str(r.get("severity") or "info"), 0)),
            str(r.get("started_at") or ""),
        ),
        reverse=False,
    )
    # Quando paginado, items JÁ é o chunk apropriado (cap aplicado fora) —
    # não trunca aqui. Mantemos o slice por segurança defensiva.
    for it in items_sorted[:DIGEST_ITEMS_CAP]:
        sev = str(it.get("severity") or "info").upper()
        typ = str(it.get("type") or "?")
        msg = str(it.get("message") or "(sem mensagem)")[:200]
        ts = str(it.get("started_at") or "?")
        lines.append(f"[{sev}] {typ} — {ts}")
        lines.append(f"  {msg}")
        lines.append("")
    if parts > 1 and part < parts:
        lines.append(f"... continua na parte {part + 1}/{parts}.")
        lines.append("")
    elif len(items) > DIGEST_ITEMS_CAP:
        # Caso defensivo — não deveria mais acontecer com paginação
        extra = len(items) - DIGEST_ITEMS_CAP
        lines.append(f"... e mais {extra} eventos não listados acima.")
        lines.append("Veja a lista completa em /alerts.php")
        lines.append("")
    lines.append("Acesse o dashboard pra ver todos.")
    lines.append("")
    lines.append("— Unbound Dashboard")
    return "\n".join(lines)


def _format_html_body(
    username: str,
    items: list[dict],
    window_hours: int = 24,
    part: int = 1,
    parts: int = 1,
    total_items: int | None = None,
) -> str:
    """HTML body — tabela com badges coloridos por severidade.

    Quando paginado (parts > 1), `items` é o chunk dessa parte e
    `total_items` é o total geral pra mostrar no header.
    """
    esc = html_lib.escape
    user_safe = esc(username)
    if not items:
        return (
            "<html><body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;\">"
            f"<p>Olá <strong>{user_safe}</strong>,</p>"
            f"<p>Nas últimas {window_hours}h não houve nenhum alerta ou anomalia "
            "que atenda às suas preferências.</p>"
            "<p style=\"color:#64748b;font-size:12px;\">— Unbound Dashboard</p>"
            "</body></html>"
        )
    items_sorted = sorted(
        items,
        key=lambda r: (
            -(_SEVERITY_RANK.get(str(r.get("severity") or "info"), 0)),
            str(r.get("started_at") or ""),
        ),
    )
    rows: list[str] = []
    for it in items_sorted[:DIGEST_ITEMS_CAP]:
        sev = str(it.get("severity") or "info").lower()
        if sev not in _HTML_SEVERITY_STYLE:
            sev = "info"
        fg, bg = _HTML_SEVERITY_STYLE[sev]
        typ = esc(str(it.get("type") or "?"))
        msg = esc(str(it.get("message") or "(sem mensagem)")[:300])
        ts = esc(str(it.get("started_at") or "?"))
        badge = (
            f"<span style=\"display:inline-block;padding:2px 8px;border-radius:8px;"
            f"background:{bg};color:{fg};font-size:10px;font-weight:700;"
            f"text-transform:uppercase;letter-spacing:0.5px;\">{sev}</span>"
        )
        rows.append(
            "<tr>"
            f"<td style=\"padding:8px;border-bottom:1px solid #e2e8f0;vertical-align:top;width:90px;\">{badge}</td>"
            f"<td style=\"padding:8px;border-bottom:1px solid #e2e8f0;vertical-align:top;\">"
            f"<div style=\"font-family:ui-monospace,'SF Mono',monospace;font-size:11px;color:#475569;\">{typ}</div>"
            f"<div style=\"color:#0f172a;font-size:14px;margin-top:2px;\">{msg}</div>"
            f"<div style=\"color:#94a3b8;font-size:11px;margin-top:4px;\">{ts}</div>"
            "</td></tr>"
        )
    truncated = ""
    if parts > 1 and part < parts:
        truncated = (
            "<div style=\"margin-top:16px;padding:12px 16px;background:#dbeafe;"
            "border-left:3px solid #2563eb;border-radius:6px;\">"
            f"<p style=\"margin:0;color:#1e3a8a;font-size:13px;\">"
            f"Continua na <strong>parte {part + 1} de {parts}</strong> "
            "(próximo email)."
            "</p></div>"
        )
    elif len(items) > DIGEST_ITEMS_CAP:
        # Caso defensivo — não deveria mais acontecer com paginação
        extra = len(items) - DIGEST_ITEMS_CAP
        truncated = (
            "<div style=\"margin-top:16px;padding:12px 16px;background:#fef3c7;"
            "border-left:3px solid #d97706;border-radius:6px;\">"
            f"<p style=\"margin:0;color:#78350f;font-size:13px;\">"
            f"+ <strong>{extra}</strong> eventos não estão listados acima. "
            f"<a href=\"/alerts.php\" style=\"color:#92400e;text-decoration:underline;font-weight:600;\">"
            "Ver lista completa no dashboard →</a>"
            "</p></div>"
        )
    cta = (
        "<div style=\"margin-top:20px;text-align:center;\">"
        "<a href=\"/alerts.php\" style=\"display:inline-block;padding:10px 24px;"
        "background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;"
        "font-weight:600;font-size:13px;\">Abrir dashboard</a>"
        "</div>"
    )
    return (
        "<html><body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;"
        "color:#0f172a;background:#f8fafc;margin:0;padding:24px;\">"
        f"<div style=\"max-width:680px;margin:0 auto;background:#fff;border-radius:12px;"
        "padding:24px;border:1px solid #e2e8f0;\">"
        f"<h2 style=\"margin:0 0 8px;font-size:20px;\">Olá, <strong>{user_safe}</strong></h2>"
        f"<p style=\"color:#475569;margin:0 0 16px;\">Digest das últimas {window_hours}h "
        f"(<strong>{total_items if total_items is not None else len(items)}</strong> eventos"
        + (f" — parte {part}/{parts}" if parts > 1 else "")
        + "):</p>"
        "<table style=\"width:100%;border-collapse:collapse;\">"
        f"<tbody>{''.join(rows)}</tbody>"
        "</table>"
        f"{truncated}"
        f"{cta}"
        "<p style=\"color:#94a3b8;font-size:11px;margin-top:24px;border-top:1px solid #e2e8f0;padding-top:12px;\">"
        "— Unbound Dashboard — Para alterar suas preferências, acesse /notifications.php"
        "</p></div></body></html>"
    )


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
        parts_sent = 0
        for u in due:
            user_id = u["user_id"]
            email = u["email"]
            sev_min = u["severity_min"]
            cats = u["categories"]
            personalized = [it for it in all_items if _passes_filter(it, sev_min, cats)]
            total = len(personalized)
            # Mantém ordenação igual a _format_body/_html_body
            personalized_sorted = sorted(
                personalized,
                key=lambda r: (
                    -(_SEVERITY_RANK.get(str(r.get("severity") or "info"), 0)),
                    str(r.get("started_at") or ""),
                ),
            )
            # Paginação: divide em chunks de DIGEST_ITEMS_CAP
            if total == 0:
                chunks: list[list[dict]] = [[]]
            else:
                chunks = [
                    personalized_sorted[i : i + DIGEST_ITEMS_CAP]
                    for i in range(0, total, DIGEST_ITEMS_CAP)
                ]
            parts = len(chunks)
            uname = u.get("username") or email
            user_ok = True
            for idx, chunk in enumerate(chunks, start=1):
                part_suffix = f" (parte {idx}/{parts})" if parts > 1 else ""
                subject = (
                    f"[Unbound Dashboard] Digest diário — {total} eventos{part_suffix}"
                )
                body = _format_body(
                    uname, chunk, part=idx, parts=parts, total_items=total,
                )
                html_body = _format_html_body(
                    uname, chunk, part=idx, parts=parts, total_items=total,
                )
                ok, reason = email_notifier._send_via_smtp(  # noqa: SLF001
                    cfg, email, subject, body, html_body=html_body,
                )
                if ok:
                    parts_sent += 1
                    log.info(
                        "digest_sender.sent",
                        to=email, user_id=user_id,
                        part=idx, parts=parts, items_in_part=len(chunk),
                    )
                else:
                    user_ok = False
                    log.warning(
                        "digest_sender.failed",
                        to=email, part=idx, parts=parts, reason=reason,
                    )
                    break  # não envia partes seguintes se uma falhou
            if user_ok:
                sent += 1
                await notification_prefs_service.mark_digest_sent(user_id)
            else:
                failed += 1

        return {
            "sent": sent, "failed": failed,
            "due": len(due), "parts_sent": parts_sent,
        }

    async def run_now(self) -> dict:
        return await self._run_once()
