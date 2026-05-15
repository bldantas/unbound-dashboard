"""
Webhook notifier — envia alertas críticos para Slack / Discord / Teams /
endpoint genérico.

Configuração persistida em `settings` (DuckDB):
  - webhook_enabled       : "true"/"false"
  - webhook_url           : URL completa do webhook
  - webhook_type          : "slack" | "discord" | "teams" | "generic"
  - webhook_severity_min  : "warning" | "critical"  (default: critical)

Cada tipo gera o payload no formato esperado pela plataforma:
  - Slack/Teams: `{"text": "..."}` (compatível com Incoming Webhooks).
  - Discord:     `{"content": "..."}`.
  - Generic:     `{"type", "severity", "message", "timestamp"}` — JSON puro.

Throttle: mesma combinação (type+severity) não é re-notificada por
WEBHOOK_COOLDOWN_SECONDS (padrão 900s = 15min). Estado mantido em
Redis (`udash:webhook:cooldown:<type>`) com TTL = cooldown. Se Redis
off, fallback "sempre envia" — best-effort.

Best-effort: erros HTTP/conexão logam mas NÃO derrubam o caller
(alert_checker). Timeout curto (5s) garante que worker não trava.
"""

from __future__ import annotations

import json
import time
from typing import Any

import httpx
import structlog

from app.infrastructure.redis_client import get_redis
from app.repositories.duckdb import settings_repo

log = structlog.get_logger(__name__)

WEBHOOK_TIMEOUT = 5.0
WEBHOOK_COOLDOWN_SECONDS = 900  # 15min — mesmo alert_type não re-dispara

_COOLDOWN_PREFIX = "udash:webhook:cooldown:"

# Hierarquia de severity — usada pra comparar com webhook_severity_min
_SEVERITY_ORDER = {"info": 1, "warning": 2, "critical": 3}


SEVERITY_EMOJI = {
    "info":     "ℹ️",
    "warning":  "⚠️",
    "critical": "🚨",
}


async def _load_config() -> dict[str, Any]:
    """Carrega config do webhook do DuckDB."""
    enabled = await settings_repo.get_bool("webhook_enabled", False)
    return {
        "enabled":      enabled,
        "url":          await settings_repo.get("webhook_url", "") or "",
        "type":         (await settings_repo.get("webhook_type", "generic") or "generic").lower(),
        "severity_min": (await settings_repo.get("webhook_severity_min", "critical") or "critical").lower(),
    }


def _passes_severity(severity: str, severity_min: str) -> bool:
    return _SEVERITY_ORDER.get(severity, 0) >= _SEVERITY_ORDER.get(severity_min, 3)


def _build_payload(webhook_type: str, alert_type: str, severity: str, message: str) -> dict[str, Any]:
    """Monta o body JSON conforme o tipo de webhook."""
    emoji = SEVERITY_EMOJI.get(severity, "🔔")
    text = f"{emoji} *[Unbound Dashboard]* `{alert_type}` ({severity}): {message}"
    if webhook_type == "slack":
        return {"text": text}
    if webhook_type == "teams":
        return {"text": text}
    if webhook_type == "discord":
        # Discord não respeita *bold* mas aceita markdown; também tem limite 2000 chars
        content = f"{emoji} **[Unbound Dashboard]** `{alert_type}` ({severity}): {message}"
        return {"content": content[:1900]}
    # generic
    return {
        "type":      alert_type,
        "severity":  severity,
        "message":   message,
        "timestamp": int(time.time()),
        "source":    "unbound-dashboard",
    }


async def _under_cooldown(alert_type: str) -> bool:
    """Verifica se o tipo tem cooldown ativo (best-effort com Redis)."""
    try:
        r = await get_redis()
        return bool(await r.exists(_COOLDOWN_PREFIX + alert_type))
    except Exception:  # noqa: BLE001
        return False  # Redis off — não bloqueia o envio


async def _set_cooldown(alert_type: str) -> None:
    try:
        r = await get_redis()
        await r.setex(_COOLDOWN_PREFIX + alert_type, WEBHOOK_COOLDOWN_SECONDS, "1")
    except Exception as exc:  # noqa: BLE001
        log.debug("webhook.cooldown_set_failed", error=str(exc))


async def notify(alert_type: str, severity: str, message: str) -> dict[str, Any]:
    """
    Envia o alerta para o webhook configurado. Retorna dict com result:
        {"sent": bool, "reason": str, "http_status": int|None}

    Chamado pelo alert_checker._raise_alert depois de criar o alerta no DB.
    Idempotente quanto a throttling — cooldown evita spam.
    """
    cfg = await _load_config()
    if not cfg["enabled"]:
        return {"sent": False, "reason": "disabled", "http_status": None}
    if not cfg["url"]:
        return {"sent": False, "reason": "no_url", "http_status": None}
    if not _passes_severity(severity, cfg["severity_min"]):
        return {"sent": False, "reason": "below_min_severity", "http_status": None}
    if await _under_cooldown(alert_type):
        return {"sent": False, "reason": "cooldown", "http_status": None}

    payload = _build_payload(cfg["type"], alert_type, severity, message)
    try:
        async with httpx.AsyncClient(timeout=WEBHOOK_TIMEOUT) as client:
            resp = await client.post(cfg["url"], json=payload)
        ok = 200 <= resp.status_code < 300
        if ok:
            await _set_cooldown(alert_type)
            log.info(
                "webhook.sent",
                type=alert_type,
                severity=severity,
                webhook_type=cfg["type"],
                http_status=resp.status_code,
            )
            return {"sent": True, "reason": "ok", "http_status": resp.status_code}
        log.warning(
            "webhook.http_error",
            type=alert_type,
            http_status=resp.status_code,
            body=resp.text[:200],
        )
        return {"sent": False, "reason": f"http_{resp.status_code}", "http_status": resp.status_code}
    except httpx.RequestError as exc:
        log.warning("webhook.network_error", type=alert_type, error=str(exc))
        return {"sent": False, "reason": "network_error", "http_status": None}
    except Exception as exc:  # noqa: BLE001
        log.warning("webhook.unexpected_error", type=alert_type, error=str(exc))
        return {"sent": False, "reason": "exception", "http_status": None}


async def notify_new_release(release: dict[str, Any]) -> dict[str, Any]:
    """
    Notifica via webhook que uma nova release foi detectada pelo
    `update_checker`. Distinto de `notify()` (alertas críticos):
    severity_min e cooldown de alertas NÃO se aplicam aqui.

    Bypassa enabled/url separados? Não — reusa a mesma config de webhook.
    Se webhook_enabled=false, nada acontece.
    """
    cfg = await _load_config()
    if not cfg["enabled"]:
        return {"sent": False, "reason": "disabled"}
    if not cfg["url"]:
        return {"sent": False, "reason": "no_url"}

    tag = release.get("tag_name", "?")
    url = release.get("html_url", "")
    message = (
        f"📦 Nova versão disponível: {tag}\n"
        f"Aplicar via UI → Configurações → Sistema / Atualizações\n"
        f"{url}"
    )

    # Payload formatado por tipo de webhook (Slack/Discord/Teams/Generic)
    if cfg["type"] == "slack":
        payload = {"text": message}
    elif cfg["type"] == "teams":
        payload = {"text": message}
    elif cfg["type"] == "discord":
        payload = {"content": message[:1900]}
    else:
        payload = {
            "type": "release_available",
            "tag": tag,
            "html_url": url,
            "body": release.get("body", "")[:1000],
            "published_at": release.get("published_at", ""),
            "source": "unbound-dashboard",
        }

    try:
        async with httpx.AsyncClient(timeout=WEBHOOK_TIMEOUT) as client:
            resp = await client.post(cfg["url"], json=payload)
        ok = 200 <= resp.status_code < 300
        if ok:
            log.info("webhook.release_notified", tag=tag, webhook_type=cfg["type"])
            return {"sent": True, "reason": "ok"}
        log.warning("webhook.release_notify_failed", tag=tag, http_status=resp.status_code)
        return {"sent": False, "reason": f"http_{resp.status_code}"}
    except Exception as exc:  # noqa: BLE001
        log.warning("webhook.release_notify_exception", tag=tag, error=str(exc))
        return {"sent": False, "reason": "exception", "error": str(exc)}


async def send_test(custom_message: str | None = None) -> dict[str, Any]:
    """
    Dispara um envio de teste — usado pelo UI ("Enviar teste").
    Ignora cooldown e severity_min, mas respeita enabled/url.
    """
    cfg = await _load_config()
    if not cfg["url"]:
        return {"sent": False, "reason": "no_url", "http_status": None}

    msg = custom_message or "Teste de webhook — se você está lendo isso, a integração está funcionando."
    payload = _build_payload(cfg["type"], "test", "info", msg)
    try:
        async with httpx.AsyncClient(timeout=WEBHOOK_TIMEOUT) as client:
            resp = await client.post(cfg["url"], json=payload)
        ok = 200 <= resp.status_code < 300
        return {
            "sent": ok,
            "reason": "ok" if ok else f"http_{resp.status_code}",
            "http_status": resp.status_code,
            "body": resp.text[:500],
        }
    except httpx.RequestError as exc:
        return {"sent": False, "reason": "network_error", "http_status": None, "error": str(exc)}
    except Exception as exc:  # noqa: BLE001
        return {"sent": False, "reason": "exception", "http_status": None, "error": str(exc)}
