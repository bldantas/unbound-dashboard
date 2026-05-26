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

# Hardening v2 (D.2). Cada chave é "0"/"1". Override semântico: as diretivas que
# colocamos aqui *sobrepõem* o que está em /etc/unbound/includes/security.conf
# (Unbound faz merge de múltiplos blocos `server:` e a última diretiva vence).
HARDENING_KEYS = (
    "dns_hide_identity",
    "dns_hide_version",
    "dns_aggressive_nsec",
    "dns_use_caps_for_id",
    "dns_harden_glue",
    "dns_harden_dnssec_stripped",
    "dns_harden_below_nxdomain",
    "dns_harden_referral_path",
    "dns_harden_algo_downgrade",
    "dns_deny_any",
    "dns_ecs_off",
    "dns_tls_strict_verify",
)
HARDENING_DEFAULTS = {k: "0" for k in HARDENING_KEYS}

# Performance & Cache v2 (D.4). Mistura booleans (toggles) e ints (TTLs, sizes).
# Override semântico igual hardening: o bloco extra sobrepõe optimization.conf
# e performance.conf via merge do Unbound (última diretiva vence).
PERFORMANCE_BOOL_KEYS = (
    "unbound_perf_prefetch",
    "unbound_perf_prefetch_key",
    "unbound_perf_serve_expired",
    "unbound_perf_minimal_responses",
    "unbound_perf_rrset_roundrobin",
)
PERFORMANCE_INT_KEYS = (
    "unbound_perf_serve_expired_ttl",          # seg, default 86400
    "unbound_perf_serve_expired_client_timeout",  # ms, default 1800
    "unbound_perf_cache_min_ttl",              # seg, default 0
    "unbound_perf_cache_max_ttl",              # seg, default 86400
    "unbound_perf_msg_cache_size_mb",          # MB, default 50
    "unbound_perf_rrset_cache_size_mb",        # MB, default 100
)
PERFORMANCE_DEFAULTS = {
    "unbound_perf_prefetch": "0",
    "unbound_perf_prefetch_key": "0",
    "unbound_perf_serve_expired": "0",
    "unbound_perf_minimal_responses": "0",
    "unbound_perf_rrset_roundrobin": "0",
    "unbound_perf_serve_expired_ttl": "86400",
    "unbound_perf_serve_expired_client_timeout": "1800",
    "unbound_perf_cache_min_ttl": "0",
    "unbound_perf_cache_max_ttl": "86400",
    "unbound_perf_msg_cache_size_mb": "50",
    "unbound_perf_rrset_cache_size_mb": "100",
}


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


async def get_hardening_settings() -> dict[str, Any]:
    out = {}
    for k in HARDENING_KEYS:
        out[k] = await settings_repo.get(k, HARDENING_DEFAULTS[k])
    return {"settings": out, "defaults": HARDENING_DEFAULTS}


async def update_hardening_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in HARDENING_KEYS:
            val = "1" if str(v) in ("1", "true", "True", "on", "yes") else "0"
            entries.append({"setting_key": k, "setting_value": val})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


async def get_performance_settings() -> dict[str, Any]:
    out = {}
    for k in PERFORMANCE_BOOL_KEYS + PERFORMANCE_INT_KEYS:
        out[k] = await settings_repo.get(k, PERFORMANCE_DEFAULTS[k])
    return {"settings": out, "defaults": PERFORMANCE_DEFAULTS}


async def update_performance_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in PERFORMANCE_BOOL_KEYS:
            val = "1" if str(v) in ("1", "true", "True", "on", "yes") else "0"
            entries.append({"setting_key": k, "setting_value": val})
        elif k in PERFORMANCE_INT_KEYS:
            try:
                n = int(v)
            except (TypeError, ValueError):
                continue
            n = max(0, n)
            if k == "unbound_perf_msg_cache_size_mb":
                n = max(4, min(4096, n))
            elif k == "unbound_perf_rrset_cache_size_mb":
                n = max(8, min(8192, n))
            elif k.endswith("_ttl") or k.endswith("_timeout"):
                n = min(2592000, n)  # cap em 30 dias / 30000s
            entries.append({"setting_key": k, "setting_value": str(n)})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


def _build_performance_block(
    bools: dict[str, bool], ints: dict[str, int]
) -> str:
    """Bloco `server:` com diretivas de performance/cache. Vazio se tudo default.

    Só emite linhas que diferem do default — pra evitar override desnecessário
    e deixar `optimization.conf`/`performance.conf` em controle quando o user
    não tocou em nada na UI.
    """
    lines: list[str] = []
    if bools.get("unbound_perf_prefetch"):
        lines.append("    prefetch: yes")
    if bools.get("unbound_perf_prefetch_key"):
        lines.append("    prefetch-key: yes")
    if bools.get("unbound_perf_serve_expired"):
        lines.append("    serve-expired: yes")
        ttl = ints.get("unbound_perf_serve_expired_ttl", 86400)
        timeout = ints.get("unbound_perf_serve_expired_client_timeout", 1800)
        if ttl > 0:
            lines.append(f"    serve-expired-ttl: {ttl}")
        if timeout > 0:
            lines.append(f"    serve-expired-client-timeout: {timeout}")
    if bools.get("unbound_perf_minimal_responses"):
        lines.append("    minimal-responses: yes")
    if bools.get("unbound_perf_rrset_roundrobin"):
        lines.append("    rrset-roundrobin: yes")

    cmin = ints.get("unbound_perf_cache_min_ttl", 0)
    cmax = ints.get("unbound_perf_cache_max_ttl", 86400)
    msg_mb = ints.get("unbound_perf_msg_cache_size_mb", 50)
    rrset_mb = ints.get("unbound_perf_rrset_cache_size_mb", 100)

    if cmin > 0:
        lines.append(f"    cache-min-ttl: {cmin}")
    # cache-max-ttl: só emite se diferente do default (86400 = 1 dia)
    if cmax != 86400:
        lines.append(f"    cache-max-ttl: {cmax}")
    # Cache sizes: só emite se diferente do default (50m msg / 100m rrset)
    if msg_mb != 50:
        lines.append(f"    msg-cache-size: {msg_mb}m")
    if rrset_mb != 100:
        lines.append(f"    rrset-cache-size: {rrset_mb}m")

    if not lines:
        return ""
    return "server:\n" + "\n".join(lines) + "\n"


def _build_hardening_block(flags: dict[str, bool]) -> str:
    """Bloco `server:` com diretivas de hardening + privacy v2.

    Vazio se nenhum toggle estiver ativo. A semântica de cada flag está
    documentada em HARDENING_KEYS — todas mapeiam 1:1 pra uma diretiva
    `<key>: yes` (algumas têm forma especial, tratadas abaixo).

    ECS-off: emite `send-client-subnet: 0.0.0.0/0` apagando a allowlist
    + `client-subnet-always-forward: no`. Isso evita propagar a subnet
    do cliente pros auths upstream.

    TLS strict: emite `tls-system-cert: yes` — Unbound exige cert válido
    do upstream DoT contra o trust store do sistema (já temos
    `tls-cert-bundle` mas sem essa flag a validação de hostname pode
    ser permissiva em alguns builds).
    """
    if not any(flags.values()):
        return ""
    lines = ["server:"]
    if flags.get("dns_hide_identity"):
        lines.append("    hide-identity: yes")
    if flags.get("dns_hide_version"):
        lines.append("    hide-version: yes")
    if flags.get("dns_aggressive_nsec"):
        lines.append("    aggressive-nsec: yes")
    if flags.get("dns_use_caps_for_id"):
        lines.append("    use-caps-for-id: yes")
    if flags.get("dns_harden_glue"):
        lines.append("    harden-glue: yes")
    if flags.get("dns_harden_dnssec_stripped"):
        lines.append("    harden-dnssec-stripped: yes")
    if flags.get("dns_harden_below_nxdomain"):
        lines.append("    harden-below-nxdomain: yes")
    if flags.get("dns_harden_referral_path"):
        lines.append("    harden-referral-path: yes")
    if flags.get("dns_harden_algo_downgrade"):
        lines.append("    harden-algo-downgrade: yes")
    if flags.get("dns_deny_any"):
        lines.append("    deny-any: yes")
    if flags.get("dns_ecs_off"):
        lines.append("    client-subnet-always-forward: no")
    if flags.get("dns_tls_strict_verify"):
        lines.append("    tls-system-cert: yes")
    if len(lines) == 1:
        return ""
    return "\n".join(lines) + "\n"


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
    hardening_block: str = "",
    performance_block: str = "",
) -> str:
    """Gera conteúdo do forwarders.conf conforme settings.

    Os blocos extras (ratelimit, privacy, hardening, performance) são
    concatenados ao final — unbound aceita múltiplos `server:` blocks
    (são merged), então coexistem com o `server:` do tls-cert-bundle.
    """
    def _append_extras(body: str) -> str:
        for extra in (ratelimit_block, privacy_block, hardening_block, performance_block):
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

    hardening_flags = {}
    for k in HARDENING_KEYS:
        hardening_flags[k] = await settings_repo.get_bool(k, False)
    hardening_block = _build_hardening_block(hardening_flags)

    perf_bools = {}
    for k in PERFORMANCE_BOOL_KEYS:
        perf_bools[k] = await settings_repo.get_bool(k, False)
    perf_ints = {}
    for k in PERFORMANCE_INT_KEYS:
        perf_ints[k] = await settings_repo.get_int(k, int(PERFORMANCE_DEFAULTS[k]))
    performance_block = _build_performance_block(perf_bools, perf_ints)

    content = _build_forwarders_conf(
        mode, provider, custom, ratelimit_block, privacy_block,
        hardening_block, performance_block,
    )

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
