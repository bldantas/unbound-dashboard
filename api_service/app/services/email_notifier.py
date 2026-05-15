"""
Email notifier — cliente SMTP minimal (stdlib smtplib + email.mime).

Lê config do DuckDB `settings` table (mesma usada pelo PHP Mailer.php).
NÃO duplica config — leitura única source-of-truth pra ambos.

Usado por `update_checker` pra notificar admins de novas releases.
Não substitui o PHP Mailer.php (que cuida de password reset + envio de
teste no UI).
"""

from __future__ import annotations

import smtplib
import ssl
from email.message import EmailMessage
from typing import Any

import structlog

from app.repositories.duckdb import settings_repo, user_repo

log = structlog.get_logger(__name__)


async def _load_smtp_config() -> dict[str, Any]:
    return {
        "enabled":   await settings_repo.get_bool("smtp_enabled", False),
        "host":      await settings_repo.get("smtp_host", "") or "",
        "port":      await settings_repo.get_int("smtp_port", 587),
        "encryption": (await settings_repo.get("smtp_encryption", "tls") or "tls").lower(),
        "user":      await settings_repo.get("smtp_user", "") or "",
        "password":  await settings_repo.get("smtp_password", "") or "",
        "from_addr": await settings_repo.get("smtp_from", "") or "",
        "from_name": await settings_repo.get("smtp_from_name", "Unbound Dashboard") or "Unbound Dashboard",
    }


def _send_via_smtp(cfg: dict[str, Any], to: str, subject: str, body: str) -> tuple[bool, str]:
    """Envia 1 email. Retorna (success, message)."""
    msg = EmailMessage()
    msg["From"] = f'{cfg["from_name"]} <{cfg["from_addr"]}>' if cfg["from_name"] else cfg["from_addr"]
    msg["To"] = to
    msg["Subject"] = subject
    msg.set_content(body)

    enc = cfg["encryption"]
    host = cfg["host"]
    port = cfg["port"]

    try:
        if enc == "ssl":
            ctx = ssl.create_default_context()
            with smtplib.SMTP_SSL(host, port, timeout=10, context=ctx) as s:
                if cfg["user"]:
                    s.login(cfg["user"], cfg["password"])
                s.send_message(msg)
        else:
            with smtplib.SMTP(host, port, timeout=10) as s:
                if enc == "tls":
                    s.starttls(context=ssl.create_default_context())
                if cfg["user"]:
                    s.login(cfg["user"], cfg["password"])
                s.send_message(msg)
        return True, "ok"
    except Exception as exc:  # noqa: BLE001
        return False, f"{type(exc).__name__}: {exc}"


async def notify_new_release(release: dict[str, Any]) -> dict[str, int]:
    """
    Envia email pra todos admins ativos com `email` preenchido,
    avisando da nova versão. Retorna `{sent, failed, skipped}`.

    Best-effort: erros são logados, não levantados.
    """
    cfg = await _load_smtp_config()
    if not cfg["enabled"]:
        log.debug("email_notifier.smtp_disabled")
        return {"sent": 0, "failed": 0, "skipped": 0}
    if not cfg["host"] or not cfg["from_addr"]:
        log.warning("email_notifier.smtp_misconfigured")
        return {"sent": 0, "failed": 0, "skipped": 0}

    # Admins ativos com email não-vazio
    try:
        users = await user_repo.list_all()
    except Exception as exc:  # noqa: BLE001
        log.warning("email_notifier.list_users_failed", error=str(exc))
        return {"sent": 0, "failed": 0, "skipped": 0}

    targets = [
        str(u["email"]) for u in users
        if u.get("role") == "admin"
        and u.get("is_active")
        and u.get("email")
    ]
    if not targets:
        log.info("email_notifier.no_targets")
        return {"sent": 0, "failed": 0, "skipped": 0}

    tag = release.get("tag_name", "?")
    body = release.get("body", "") or "(sem notas de release)"
    url = release.get("html_url", "")
    published = release.get("published_at", "")

    subject = f"[Unbound Dashboard] Nova versão disponível: {tag}"
    email_body = f"""Olá,

Uma nova versão do Unbound Dashboard está disponível: {tag}

Publicada em: {published}
Notas: {url}

--- Notas da release ---
{body[:2000]}

Pra aplicar, acesse o dashboard → Configurações → Sistema / Atualizações
e clique em "Atualizar pra {tag}".

— Sistema Unbound Dashboard
"""

    sent = 0
    failed = 0
    for to_addr in targets:
        ok, reason = _send_via_smtp(cfg, to_addr, subject, email_body)
        if ok:
            sent += 1
            log.info("email_notifier.sent", to=to_addr, tag=tag)
        else:
            failed += 1
            log.warning("email_notifier.failed", to=to_addr, reason=reason)

    return {"sent": sent, "failed": failed, "skipped": 0}
