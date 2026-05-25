"""
BlocklistSyncer — baixa as fontes ativas (index_enabled=true) e popula
blocklist_entries.

Substitui o antigo scripts/update_blacklist.php que rodava via cron e
suportava UMA fonte por vez. Agora itera sobre todas as sources marcadas
como ativas no DuckDB.

Estratégia:
- Loop: 1x/h por default. Antes de baixar, checa se já sincronizou nas
  últimas FRESH_HOURS — se sim, pula (idempotente).
- Por source: HTTP GET, parse conforme `format`, clear_source + bulk_insert,
  carimba last_sync/last_count/last_error.
- Falha em uma source NÃO derruba as outras — captura exceção e segue.

Funções públicas:
- sync_source(slug) → usável sob demanda pelo endpoint POST /sources/{slug}/sync
- Classe BlocklistSyncer (start/stop) — usada pelo lifespan do main.py
"""

from __future__ import annotations

import asyncio
import re
from datetime import datetime, timedelta, timezone

import httpx
import structlog

from app.repositories.duckdb import blocklist_sources_repo, threats_repo

log = structlog.get_logger(__name__)

SYNC_INTERVAL_SECONDS = 3600          # rechecagem horária
INITIAL_DELAY_SECONDS = 30            # pra não brigar com outros workers no startup
FRESH_HOURS = 12                      # se last_sync < FRESH_HOURS, pula
HTTP_TIMEOUT_SECONDS = 60
USER_AGENT = "unbound-dashboard/blocklist-syncer"

# Regexes pré-compilados pros parsers
_RE_LOCALZONE = re.compile(r'^\s*local-zone:\s*"([^"]+?)\.?"\s+', re.IGNORECASE)
_RE_ADBLOCK = re.compile(r"^\|\|([a-z0-9.\-_]+)\^")
_RE_DOMAIN_VALIDATE = re.compile(r"^(?=.{1,253}$)([a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$")


def _parse_hosts(text: str) -> list[str]:
    """Parser para arquivos no formato `hosts`: linhas tipo `0.0.0.0 domain.com`.

    Ignora comentários, IPs locais (localhost, broadcast) e entradas inválidas.
    """
    out: list[str] = []
    for raw in text.splitlines():
        line = raw.split("#", 1)[0].strip()
        if not line:
            continue
        parts = line.split()
        if len(parts) < 2:
            continue
        # Aceita 0.0.0.0/127.0.0.1/:: como IP de bloqueio. Skip se IP é diferente
        # (alguns hosts files trazem entradas legítimas tipo 192.168.x.x).
        ip = parts[0]
        if ip not in ("0.0.0.0", "127.0.0.1", "::", "::1"):
            continue
        domain = parts[1].lower().rstrip(".")
        if domain in ("localhost", "localhost.localdomain", "broadcasthost", "ip6-localhost", "ip6-loopback"):
            continue
        if _RE_DOMAIN_VALIDATE.match(domain):
            out.append(domain)
    return out


def _parse_domains(text: str) -> list[str]:
    """Parser para listas com um domínio por linha (formato 'domains' / 'domainswild')."""
    out: list[str] = []
    for raw in text.splitlines():
        line = raw.split("#", 1)[0].strip()
        if not line:
            continue
        # OISD usa `*.domain` no formato domainswild; ignora o prefixo.
        if line.startswith("*."):
            line = line[2:]
        domain = line.lower().rstrip(".")
        if _RE_DOMAIN_VALIDATE.match(domain):
            out.append(domain)
    return out


def _parse_unbound_localzone(text: str) -> list[str]:
    """Parser pra `local-zone: "domain." always_nxdomain` (formato Anablock)."""
    out: list[str] = []
    for raw in text.splitlines():
        m = _RE_LOCALZONE.match(raw)
        if not m:
            continue
        domain = m.group(1).lower().rstrip(".")
        if _RE_DOMAIN_VALIDATE.match(domain):
            out.append(domain)
    return out


def _parse_adblock(text: str) -> list[str]:
    """Parser pra sintaxe AdBlock: `||domain.com^`. Ignora regras complexas."""
    out: list[str] = []
    for raw in text.splitlines():
        line = raw.split("#", 1)[0].strip()
        if not line or line.startswith("!"):
            continue
        m = _RE_ADBLOCK.match(line.lower())
        if not m:
            continue
        domain = m.group(1).rstrip(".")
        if _RE_DOMAIN_VALIDATE.match(domain):
            out.append(domain)
    return out


_PARSERS = {
    "hosts": _parse_hosts,
    "domains": _parse_domains,
    "unbound_localzone": _parse_unbound_localzone,
    "adblock": _parse_adblock,
}


async def _download(url: str) -> str:
    headers = {"User-Agent": USER_AGENT, "Accept": "text/plain, */*"}
    async with httpx.AsyncClient(timeout=HTTP_TIMEOUT_SECONDS, follow_redirects=True) as client:
        resp = await client.get(url, headers=headers)
        resp.raise_for_status()
        return resp.text


async def sync_source(slug: str, *, force: bool = False) -> dict:
    """Sync de UMA source. Retorna {status, count, error}.

    Se `force=False` e last_sync < FRESH_HOURS atrás, pula sem baixar.
    Se a source não tem index_enabled, sync é no-op (skipped).
    """
    src = await blocklist_sources_repo.get(slug)
    if not src:
        return {"status": "not_found", "count": 0, "error": "source não existe"}

    if not src["index_enabled"] and not force:
        return {"status": "disabled", "count": int(src["last_count"] or 0), "error": None}

    if not force and src.get("last_sync"):
        # last_sync é TIMESTAMP — duckdb retorna datetime sem timezone (UTC nominal)
        last = src["last_sync"]
        if isinstance(last, datetime):
            last_utc = last.replace(tzinfo=timezone.utc) if last.tzinfo is None else last
            if datetime.now(timezone.utc) - last_utc < timedelta(hours=FRESH_HOURS):
                return {"status": "fresh", "count": int(src["last_count"] or 0), "error": None}

    fmt = str(src["format"])
    parser = _PARSERS.get(fmt)
    if not parser:
        err = f"formato '{fmt}' não suportado"
        await blocklist_sources_repo.mark_synced(slug, 0, err)
        return {"status": "error", "count": 0, "error": err}

    url = str(src["url"])
    log.info("blocklist_syncer.fetch", slug=slug, url=url, format=fmt)
    try:
        text = await _download(url)
    except Exception as exc:  # noqa: BLE001
        err = f"download falhou: {exc}"
        await blocklist_sources_repo.mark_synced(slug, int(src.get("last_count") or 0), err)
        log.warning("blocklist_syncer.download_failed", slug=slug, error=str(exc))
        return {"status": "error", "count": int(src.get("last_count") or 0), "error": err}

    domains = parser(text)
    # Dedup interno da própria fonte (algumas listas trazem duplicatas).
    domains = sorted(set(domains))

    if not domains:
        err = "parser não extraiu nenhum domínio"
        await blocklist_sources_repo.mark_synced(slug, 0, err)
        return {"status": "error", "count": 0, "error": err}

    await threats_repo.clear_source(slug)
    inserted = await threats_repo.bulk_insert_for_source(slug, domains)
    await blocklist_sources_repo.mark_synced(slug, inserted, None)
    log.info("blocklist_syncer.synced", slug=slug, count=inserted)
    return {"status": "ok", "count": inserted, "error": None}


async def sync_all(*, force: bool = False) -> list[dict]:
    """Sync todas as sources com index_enabled=true. Falhas isoladas."""
    sources = await blocklist_sources_repo.list_all()
    results = []
    for s in sources:
        if not s["index_enabled"]:
            continue
        try:
            r = await sync_source(str(s["slug"]), force=force)
            results.append({"slug": s["slug"], **r})
        except Exception as exc:  # noqa: BLE001
            log.warning("blocklist_syncer.unexpected", slug=s["slug"], error=str(exc))
            results.append({"slug": s["slug"], "status": "error", "count": 0, "error": str(exc)})
    return results


class BlocklistSyncer:
    """Worker que dispara sync_all() periodicamente."""

    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)

        while self._running:
            try:
                results = await sync_all(force=False)
                if results:
                    ok = sum(1 for r in results if r["status"] == "ok")
                    log.info(
                        "blocklist_syncer.tick",
                        total=len(results),
                        ok=ok,
                        skipped=len(results) - ok,
                    )
            except Exception as exc:  # noqa: BLE001
                log.warning("blocklist_syncer.unexpected_error", error=str(exc))

            slept = 0
            while self._running and slept < SYNC_INTERVAL_SECONDS:
                await asyncio.sleep(10)
                slept += 10

    async def stop(self) -> None:
        self._running = False
