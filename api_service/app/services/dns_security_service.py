"""
DNS Security service — modo upstream (recursivo vs DoT) e info DNSSEC.

Escreve o `forwarders.conf` modular do Unbound a partir de settings em
DuckDB. Modo "recursive" deixa o arquivo vazio (Unbound resolve do root).
Modo "dot" emite `forward-zone "."` com `forward-tls-upstream: yes` +
presets curados (Quad9/Cloudflare/Google/AdGuard) ou custom list.

Aplicação: escreve em /var/www/html/unbound-dashboard/src/data/tmp/, valida
com unbound-checkconf, copia via sudo cp, restart unbound.
"""

from __future__ import annotations

import asyncio
import json
import shlex
from pathlib import Path
from typing import Any

import structlog

from app.repositories.duckdb import settings_repo

log = structlog.get_logger(__name__)

TMP_DIR = Path("/var/www/html/unbound-dashboard/src/data/tmp")
TMP_FORWARDERS = TMP_DIR / "unbound_forwarders.tmp"
TARGET_FORWARDERS = "/etc/unbound/includes/forwarders.conf"
TLS_BUNDLE = "/etc/ssl/certs/ca-certificates.crt"

PRESETS: dict[str, dict[str, Any]] = {
    "quad9": {
        "label": "Quad9 (filtra malware)",
        "addresses": [
            {"addr": "9.9.9.9", "port": 853, "hostname": "dns.quad9.net"},
            {"addr": "149.112.112.112", "port": 853, "hostname": "dns.quad9.net"},
        ],
    },
    "cloudflare": {
        "label": "Cloudflare 1.1.1.1",
        "addresses": [
            {"addr": "1.1.1.1", "port": 853, "hostname": "cloudflare-dns.com"},
            {"addr": "1.0.0.1", "port": 853, "hostname": "cloudflare-dns.com"},
        ],
    },
    "google": {
        "label": "Google Public DNS",
        "addresses": [
            {"addr": "8.8.8.8", "port": 853, "hostname": "dns.google"},
            {"addr": "8.8.4.4", "port": 853, "hostname": "dns.google"},
        ],
    },
    "adguard": {
        "label": "AdGuard (unfiltered)",
        "addresses": [
            {"addr": "94.140.14.140", "port": 853, "hostname": "unfiltered.adguard-dns.com"},
            {"addr": "94.140.14.141", "port": 853, "hostname": "unfiltered.adguard-dns.com"},
        ],
    },
}

KEYS = ("dns_upstream_mode", "dns_upstream_provider", "dns_upstream_custom")
DEFAULTS = {
    "dns_upstream_mode": "recursive",
    "dns_upstream_provider": "quad9",
    "dns_upstream_custom": "[]",
}

# Rate-limit settings (C.4). Defaults = off. Unbound interpreta 0 como desabilitado.
# `factor` é "1 a cada N queries passa mesmo limitado" — proteção contra cache-miss
# 100% (NXDOMAIN amplification). 10 = ~10% passa.
RATELIMIT_KEYS = (
    "dns_ratelimit_ip_enabled",
    "dns_ratelimit_ip_qps",
    "dns_ratelimit_ip_factor",
    "dns_ratelimit_domain_enabled",
    "dns_ratelimit_domain_qps",
    "dns_ratelimit_domain_factor",
)
RATELIMIT_DEFAULTS = {
    "dns_ratelimit_ip_enabled": "0",
    "dns_ratelimit_ip_qps": "0",
    "dns_ratelimit_ip_factor": "10",
    "dns_ratelimit_domain_enabled": "0",
    "dns_ratelimit_domain_qps": "0",
    "dns_ratelimit_domain_factor": "10",
}

# Privacy (D.1): qname-minimisation (RFC 7816). Modo "strict" segue o RFC ao pé da
# letra — mais privado mas quebra com alguns auths mal-configurados.
PRIVACY_KEYS = ("dns_qname_min_mode",)
PRIVACY_DEFAULTS = {"dns_qname_min_mode": "no"}  # no | yes | strict


async def get_settings() -> dict[str, Any]:
    out = {}
    for k in KEYS:
        out[k] = await settings_repo.get(k, DEFAULTS[k])
    return {
        "settings": out,
        "defaults": DEFAULTS,
        "presets": {k: v["label"] for k, v in PRESETS.items()},
    }


async def update_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


async def get_ratelimit_settings() -> dict[str, Any]:
    out = {}
    for k in RATELIMIT_KEYS:
        out[k] = await settings_repo.get(k, RATELIMIT_DEFAULTS[k])
    return {"settings": out, "defaults": RATELIMIT_DEFAULTS}


async def update_ratelimit_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in RATELIMIT_KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


async def get_privacy_settings() -> dict[str, Any]:
    out = {}
    for k in PRIVACY_KEYS:
        out[k] = await settings_repo.get(k, PRIVACY_DEFAULTS[k])
    return {"settings": out, "defaults": PRIVACY_DEFAULTS}


async def update_privacy_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in PRIVACY_KEYS:
            val = str(v).lower()
            if k == "dns_qname_min_mode" and val not in ("no", "yes", "strict"):
                val = "no"
            entries.append({"setting_key": k, "setting_value": val})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


def _build_privacy_block(qname_min_mode: str) -> str:
    """server: block com qname-minimisation. Vazio se modo=no."""
    if qname_min_mode == "yes":
        return "server:\n    qname-minimisation: yes\n    qname-minimisation-strict: no\n"
    if qname_min_mode == "strict":
        return "server:\n    qname-minimisation: yes\n    qname-minimisation-strict: yes\n"
    return ""


def _build_ratelimit_block(
    *,
    ip_enabled: bool,
    ip_qps: int,
    ip_factor: int,
    dom_enabled: bool,
    dom_qps: int,
    dom_factor: int,
) -> str:
    """Bloco `server:` com diretivas ratelimit. Vazio se ambos disabled."""
    if not ip_enabled and not dom_enabled:
        return ""
    lines = ["server:"]
    if ip_enabled and ip_qps > 0:
        lines.append(f"    ip-ratelimit: {ip_qps}")
        lines.append(f"    ip-ratelimit-factor: {max(0, ip_factor)}")
    if dom_enabled and dom_qps > 0:
        lines.append(f"    ratelimit: {dom_qps}")
        lines.append(f"    ratelimit-factor: {max(0, dom_factor)}")
    if len(lines) == 1:
        return ""
    return "\n".join(lines) + "\n"


def _build_forwarders_conf(
    mode: str,
    provider: str,
    custom_json: str,
    ratelimit_block: str = "",
    privacy_block: str = "",
) -> str:
    """Gera conteúdo do forwarders.conf conforme settings.

    Os blocos extras (ratelimit, privacy) são concatenados ao final — unbound
    aceita múltiplos `server:` blocks (são merged), então coexistem com o
    `server:` do tls-cert-bundle.
    """
    def _append_extras(body: str) -> str:
        for extra in (ratelimit_block, privacy_block):
            if extra:
                body += "\n" + extra
        return body

    header = "# Gerado por dns_security_service — NÃO edite à mão (será sobrescrito).\n"
    if mode == "recursive":
        body = header + "# Modo recursivo — sem forward-zone, Unbound resolve do root.\n"
        return _append_extras(body)

    if provider == "custom":
        try:
            addresses = json.loads(custom_json) or []
        except json.JSONDecodeError:
            addresses = []
    else:
        preset = PRESETS.get(provider) or PRESETS["quad9"]
        addresses = preset["addresses"]

    if not addresses:
        body = header + "# Lista vazia — fallback recursivo.\n"
        return _append_extras(body)

    lines = [
        header,
        "server:",
        f'    tls-cert-bundle: "{TLS_BUNDLE}"',
        "",
        "forward-zone:",
        '    name: "."',
        "    forward-tls-upstream: yes",
    ]
    for a in addresses:
        addr = a.get("addr", "").strip()
        port = int(a.get("port", 853))
        host = a.get("hostname", "").strip()
        if not addr or not host:
            continue
        lines.append(f"    forward-addr: {addr}@{port}#{host}")
    body = "\n".join(lines) + "\n"
    return _append_extras(body)


async def _run(cmd: list[str]) -> tuple[int, str, str]:
    proc = await asyncio.create_subprocess_exec(
        *cmd,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    out, err = await proc.communicate()
    return proc.returncode or 0, out.decode("utf-8", errors="replace"), err.decode("utf-8", errors="replace")


async def apply() -> dict[str, Any]:
    """
    Gera, aplica forwarders.conf e restart unbound. Se restart falhar,
    reverte ao conteúdo anterior e tenta restart de novo (rollback).
    `unbound-checkconf` não roda dentro do sandbox systemd (precisa AF_NETLINK
    pra getifaddrs); a validação fica por conta do próprio restart.
    """
    mode = await settings_repo.get("dns_upstream_mode", DEFAULTS["dns_upstream_mode"]) or DEFAULTS["dns_upstream_mode"]
    provider = await settings_repo.get("dns_upstream_provider", DEFAULTS["dns_upstream_provider"]) or DEFAULTS["dns_upstream_provider"]
    custom = await settings_repo.get("dns_upstream_custom", DEFAULTS["dns_upstream_custom"]) or DEFAULTS["dns_upstream_custom"]

    ratelimit_block = _build_ratelimit_block(
        ip_enabled=await settings_repo.get_bool("dns_ratelimit_ip_enabled", False),
        ip_qps=await settings_repo.get_int("dns_ratelimit_ip_qps", 0),
        ip_factor=await settings_repo.get_int("dns_ratelimit_ip_factor", 10),
        dom_enabled=await settings_repo.get_bool("dns_ratelimit_domain_enabled", False),
        dom_qps=await settings_repo.get_int("dns_ratelimit_domain_qps", 0),
        dom_factor=await settings_repo.get_int("dns_ratelimit_domain_factor", 10),
    )

    qname_mode = (await settings_repo.get("dns_qname_min_mode", "no") or "no").lower()
    privacy_block = _build_privacy_block(qname_mode)

    content = _build_forwarders_conf(mode, provider, custom, ratelimit_block, privacy_block)

    # Snapshot do conteúdo atual pra rollback
    target = Path(TARGET_FORWARDERS)
    previous = target.read_text(encoding="utf-8") if target.exists() else ""

    TMP_DIR.mkdir(parents=True, exist_ok=True)
    TMP_FORWARDERS.write_text(content, encoding="utf-8")
    TMP_FORWARDERS.chmod(0o644)

    rc, out, err = await _run(["sudo", "/usr/bin/cp", str(TMP_FORWARDERS), TARGET_FORWARDERS])
    if rc != 0:
        log.error("dns_security.apply.cp_failed", rc=rc, err=err)
        return {"ok": False, "stage": "cp", "error": err or out}

    rc, out, err = await _run(["sudo", "/usr/bin/systemctl", "restart", "unbound"])
    if rc != 0:
        log.error("dns_security.apply.restart_failed", rc=rc, err=err)
        # Rollback: restaura conteúdo anterior e tenta restart
        TMP_FORWARDERS.write_text(previous, encoding="utf-8")
        rb_rc, _, rb_err = await _run(["sudo", "/usr/bin/cp", str(TMP_FORWARDERS), TARGET_FORWARDERS])
        rs_rc, _, rs_err = await _run(["sudo", "/usr/bin/systemctl", "restart", "unbound"])
        return {
            "ok": False,
            "stage": "restart",
            "error": err or out,
            "rollback": "ok" if (rb_rc == 0 and rs_rc == 0) else f"failed ({rb_err}|{rs_err})",
            "content_preview": content[:500],
        }

    log.info("dns_security.apply.ok", mode=mode, provider=provider)
    return {"ok": True, "mode": mode, "provider": provider, "addresses_written": content.count("forward-addr:")}


async def info() -> dict[str, Any]:
    """DNSSEC counters do daemon + presença do trust anchor."""
    from app.services import unbound_stats_service

    stats = await unbound_stats_service.get_stats()
    trust_anchor_path = Path("/var/lib/unbound/root.key")
    return {
        "dnssec_ratio": stats.get("dnssec_ratio", 0),
        "dnssec_secure": stats.get("dnssec_secure", 0),
        "dnssec_bogus": stats.get("dnssec_bogus", 0),
        "trust_anchor_present": trust_anchor_path.exists(),
        "trust_anchor_path": str(trust_anchor_path),
        "trust_anchor_size": trust_anchor_path.stat().st_size if trust_anchor_path.exists() else 0,
        "tls_bundle": TLS_BUNDLE,
        "tls_bundle_present": Path(TLS_BUNDLE).exists(),
    }
