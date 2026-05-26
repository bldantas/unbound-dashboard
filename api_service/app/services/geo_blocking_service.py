"""
Geo-blocking — bloqueio de países inteiros via `access-control: <cidr> refuse`.

Os CIDRs IPv4/IPv6 são baixados de iwik.org/ipcountry/ (atualizado diariamente,
sem registro nem API key). Cada país tem dois URLs:

    https://www.iwik.org/ipcountry/{CC}.cidr    — IPv4
    https://www.iwik.org/ipcountry/{CC}.ipv6    — IPv6

Persistência fica em geo_blocks (V13). Apply gera o include
/etc/unbound/includes/geo_acl.conf e reinicia o Unbound (snapshot+rollback,
mesmo padrão de dns_security_service.apply).

Atenção: access-control aqui se aplica ao CLIENT (quem pergunta), não ao
upstream. Bloquear "US" não quebra resolução pra 8.8.8.8 — só rejeita
clientes que perguntam ao Unbound dali.
"""

from __future__ import annotations

import asyncio
import time
from pathlib import Path
from typing import Any

import httpx
import structlog

from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

TMP_DIR = Path("/var/www/html/unbound-dashboard/src/data/tmp")
TMP_GEO_ACL = TMP_DIR / "unbound_geo_acl.tmp"
TARGET_GEO_ACL = "/etc/unbound/includes/geo_acl.conf"

IWIK_BASE = "https://www.iwik.org/ipcountry"
IWIK_TIMEOUT = 20.0
IWIK_UA = "unbound-dashboard/geo-blocking"

SETTING_KEYS = ("geo_blocking_enabled", "geo_blocking_include_ipv6")
DEFAULTS = {
    "geo_blocking_enabled": "0",      # master switch (precisa apply pra valer)
    "geo_blocking_include_ipv6": "0", # IPv6 opt-in (lista bem maior)
}


# -----------------------------
# Settings
# -----------------------------


async def get_settings() -> dict[str, Any]:
    out: dict[str, str] = {}
    for k in SETTING_KEYS:
        out[k] = await settings_repo.get(k, DEFAULTS[k]) or DEFAULTS[k]
    return {"settings": out, "defaults": DEFAULTS}


async def update_settings(body: dict[str, Any]) -> int:
    entries = []
    for k, v in body.items():
        if k in SETTING_KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


# -----------------------------
# Country list
# -----------------------------


async def list_countries() -> list[dict[str, Any]]:
    """Lista todos os países cadastrados em geo_blocks."""
    rows = await db_fetchall(
        """
        SELECT country_code, country_name, blocked, ipv4_count, ipv6_count,
               updated_at, last_error
        FROM geo_blocks
        ORDER BY blocked DESC, country_name
        """,
    )
    return [
        {
            "country_code": str(r["country_code"]),
            "country_name": str(r["country_name"]),
            "blocked": bool(r["blocked"]),
            "ipv4_count": int(r["ipv4_count"] or 0),
            "ipv6_count": int(r["ipv6_count"] or 0),
            "updated_at": int(r["updated_at"] or 0),
            "last_error": str(r["last_error"] or ""),
        }
        for r in rows
    ]


async def add_country(country_code: str, country_name: str, blocked: bool = True) -> dict[str, Any]:
    """Adiciona um país. Não baixa CIDRs ainda — chamar refresh_country() pra isso."""
    cc = (country_code or "").strip().upper()
    if len(cc) != 2 or not cc.isalpha():
        return {"ok": False, "error": "country_code inválido (use ISO-2)"}
    name = (country_name or cc).strip()[:120]

    existing = await db_fetchone(
        "SELECT country_code FROM geo_blocks WHERE country_code = ?", [cc]
    )
    if existing:
        await db_execute(
            "UPDATE geo_blocks SET country_name = ?, blocked = ? WHERE country_code = ?",
            [name, bool(blocked), cc],
        )
    else:
        await db_execute(
            """
            INSERT INTO geo_blocks (country_code, country_name, blocked,
                                    cidrs_ipv4, cidrs_ipv6, ipv4_count, ipv6_count,
                                    updated_at, last_error)
            VALUES (?, ?, ?, '', '', 0, 0, 0, '')
            """,
            [cc, name, bool(blocked)],
        )
    return {"ok": True, "country_code": cc, "country_name": name}


async def remove_country(country_code: str) -> dict[str, Any]:
    cc = (country_code or "").strip().upper()
    await db_execute("DELETE FROM geo_blocks WHERE country_code = ?", [cc])
    return {"ok": True, "country_code": cc}


async def set_blocked(country_code: str, blocked: bool) -> dict[str, Any]:
    cc = (country_code or "").strip().upper()
    await db_execute(
        "UPDATE geo_blocks SET blocked = ? WHERE country_code = ?",
        [bool(blocked), cc],
    )
    return {"ok": True, "country_code": cc, "blocked": bool(blocked)}


# -----------------------------
# CIDR download
# -----------------------------


def _parse_cidr_file(text: str) -> list[str]:
    """iwik.org retorna CIDRs um por linha; pula vazias e comentários."""
    out = []
    for ln in text.splitlines():
        s = ln.strip()
        if not s or s.startswith("#") or s.startswith(";"):
            continue
        out.append(s)
    return out


async def _fetch_cidrs(country_code: str, ipv6: bool) -> list[str]:
    """Baixa CIDRs IPv4 ou IPv6 de iwik.org. Lança RuntimeError em falha."""
    cc = country_code.upper()
    url = f"{IWIK_BASE}/{cc}.ipv6" if ipv6 else f"{IWIK_BASE}/{cc}.cidr"
    async with httpx.AsyncClient(timeout=IWIK_TIMEOUT, follow_redirects=True) as client:
        resp = await client.get(url, headers={"User-Agent": IWIK_UA})
        if resp.status_code == 404:
            # iwik não tem essa combinação (típico em IPv6 de países pequenos) — OK.
            return []
        if resp.status_code != 200:
            raise RuntimeError(f"iwik {url} HTTP {resp.status_code}")
        return _parse_cidr_file(resp.text)


async def refresh_country(country_code: str) -> dict[str, Any]:
    """Re-baixa CIDRs IPv4+IPv6 do país. Atualiza geo_blocks."""
    cc = (country_code or "").strip().upper()
    if len(cc) != 2:
        return {"ok": False, "error": "country_code inválido"}

    try:
        ipv4_list = await _fetch_cidrs(cc, ipv6=False)
        ipv6_list = await _fetch_cidrs(cc, ipv6=True)
    except (httpx.RequestError, RuntimeError) as exc:
        msg = str(exc)[:240]
        await db_execute(
            "UPDATE geo_blocks SET last_error = ?, updated_at = ? WHERE country_code = ?",
            [msg, int(time.time()), cc],
        )
        return {"ok": False, "country_code": cc, "error": msg}

    await db_execute(
        """
        UPDATE geo_blocks SET
            cidrs_ipv4 = ?, cidrs_ipv6 = ?,
            ipv4_count = ?, ipv6_count = ?,
            updated_at = ?, last_error = ''
        WHERE country_code = ?
        """,
        [
            "\n".join(ipv4_list),
            "\n".join(ipv6_list),
            len(ipv4_list),
            len(ipv6_list),
            int(time.time()),
            cc,
        ],
    )
    return {
        "ok": True,
        "country_code": cc,
        "ipv4_count": len(ipv4_list),
        "ipv6_count": len(ipv6_list),
    }


async def refresh_all(only_blocked: bool = True) -> dict[str, Any]:
    """Re-baixa CIDRs de todos os países (ou só os blocked=true)."""
    where = "WHERE blocked = TRUE" if only_blocked else ""
    rows = await db_fetchall(f"SELECT country_code FROM geo_blocks {where}")
    results: list[dict[str, Any]] = []
    for r in rows:
        cc = str(r["country_code"])
        res = await refresh_country(cc)
        results.append(res)
        # pausa curta pra não martelar o iwik
        await asyncio.sleep(0.3)
    ok_count = sum(1 for r in results if r.get("ok"))
    return {"ok": True, "total": len(results), "successful": ok_count, "results": results}


# -----------------------------
# Apply (gera include + restart)
# -----------------------------


async def _run(cmd: list[str]) -> tuple[int, str, str]:
    proc = await asyncio.create_subprocess_exec(
        *cmd,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    out, err = await proc.communicate()
    return (
        proc.returncode or 0,
        out.decode("utf-8", errors="replace"),
        err.decode("utf-8", errors="replace"),
    )


async def _build_geo_acl_content() -> tuple[str, int, int, int]:
    """Constrói o conteúdo do geo_acl.conf a partir de geo_blocks blocked=true.

    Retorna (content, total_cidrs, ipv4_cidrs, ipv6_cidrs).
    """
    enabled = (
        await settings_repo.get("geo_blocking_enabled", DEFAULTS["geo_blocking_enabled"])
    ) == "1"
    include_v6 = (
        await settings_repo.get(
            "geo_blocking_include_ipv6", DEFAULTS["geo_blocking_include_ipv6"]
        )
    ) == "1"

    header = [
        "# Auto-gerado por geo_blocking_service. NÃO EDITAR MANUALMENTE.",
        "# Regenerar via /api/v1/geo-blocking/apply ou /geo_blocking.php.",
        "server:",
    ]

    if not enabled:
        return (
            "\n".join(header + ["    # geo-blocking desabilitado", ""]),
            0,
            0,
            0,
        )

    rows = await db_fetchall(
        """
        SELECT country_code, country_name, cidrs_ipv4, cidrs_ipv6
        FROM geo_blocks WHERE blocked = TRUE
        ORDER BY country_code
        """,
    )
    body: list[str] = []
    total_v4 = 0
    total_v6 = 0
    for r in rows:
        cc = str(r["country_code"])
        name = str(r["country_name"] or cc)
        v4s = [c for c in str(r["cidrs_ipv4"] or "").splitlines() if c.strip()]
        v6s = [c for c in str(r["cidrs_ipv6"] or "").splitlines() if c.strip()]
        if not v4s and not v6s:
            continue
        body.append(f"    # {cc} — {name}")
        for cidr in v4s:
            body.append(f"    access-control: {cidr} refuse")
        if include_v6:
            for cidr in v6s:
                body.append(f"    access-control: {cidr} refuse")
        total_v4 += len(v4s)
        total_v6 += len(v6s) if include_v6 else 0

    content = "\n".join(header + body + [""])
    return content, total_v4 + total_v6, total_v4, total_v6


async def preview() -> dict[str, Any]:
    """Retorna o conteúdo que apply() iria escrever, sem aplicar."""
    content, total, v4, v6 = await _build_geo_acl_content()
    return {
        "content": content,
        "total_cidrs": total,
        "ipv4_count": v4,
        "ipv6_count": v6,
        "target_path": TARGET_GEO_ACL,
    }


async def apply() -> dict[str, Any]:
    """
    Gera o include, copia via sudo, reinicia Unbound. Em falha, rollback
    pro conteúdo anterior + restart de novo. Mesma estratégia de
    dns_security_service.apply().
    """
    content, total, v4, v6 = await _build_geo_acl_content()

    target = Path(TARGET_GEO_ACL)
    previous = target.read_text(encoding="utf-8") if target.exists() else ""

    TMP_DIR.mkdir(parents=True, exist_ok=True)
    TMP_GEO_ACL.write_text(content, encoding="utf-8")
    TMP_GEO_ACL.chmod(0o644)

    rc, out, err = await _run(["sudo", "/usr/bin/cp", str(TMP_GEO_ACL), TARGET_GEO_ACL])
    if rc != 0:
        log.error("geo_blocking.apply.cp_failed", rc=rc, err=err)
        return {"ok": False, "stage": "cp", "error": err or out}

    rc, out, err = await _run(["sudo", "/usr/bin/systemctl", "restart", "unbound"])
    if rc != 0:
        log.error("geo_blocking.apply.restart_failed", rc=rc, err=err)
        # rollback
        TMP_GEO_ACL.write_text(previous, encoding="utf-8")
        rb_rc, _, rb_err = await _run(
            ["sudo", "/usr/bin/cp", str(TMP_GEO_ACL), TARGET_GEO_ACL]
        )
        rs_rc, _, rs_err = await _run(
            ["sudo", "/usr/bin/systemctl", "restart", "unbound"]
        )
        return {
            "ok": False,
            "stage": "restart",
            "error": err or out,
            "rollback": "ok" if (rb_rc == 0 and rs_rc == 0) else f"failed ({rb_err}|{rs_err})",
            "content_preview": content[:500],
        }

    log.info("geo_blocking.apply.ok", total_cidrs=total, ipv4=v4, ipv6=v6)
    return {
        "ok": True,
        "total_cidrs": total,
        "ipv4_count": v4,
        "ipv6_count": v6,
        "target_path": TARGET_GEO_ACL,
    }
