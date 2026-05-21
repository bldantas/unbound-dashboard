"""
BlockedMatcher — sabe se um domain está sendo bloqueado pelo Unbound.

Motivação: o `log_watcher` antes classificava QUALQUER NXDOMAIN como
`blocked`, mas a maioria dos domínios "blocked" do dashboard eram
NXDOMAIN upstream (domínios mortos/descontinuados), não bloqueio nosso.
Isso poluía a métrica de ameaças com sites que ninguém bloqueou.

Solução: parsear `/etc/unbound/includes/blocked_domains.conf` (a fonte
da verdade do que o Unbound realmente serve via local-zone), manter um
set em memória, e expor `matches(query_domain)` que faz match por
sufixo (porque `local-zone "evil.com"` também bloqueia `ads.evil.com`).

Cache: TTL de 5min. Reload sob demanda quando o arquivo mtime muda.
Não chama DuckDB nem invoca Unbound — read-only no arquivo, barato.
"""

from __future__ import annotations

import re
import threading
import time
from pathlib import Path

import structlog

log = structlog.get_logger(__name__)

DEFAULT_CONF_PATH = "/etc/unbound/includes/blocked_domains.conf"
DEFAULT_CACHE_TTL = 300.0  # 5min — refresh por idade do cache em memória

# Parser de `local-zone: "domain.tld[.]" <type>` — pega o domínio entre aspas.
# Aceita trailing dot (formato Unbound). Linha pode ter qualquer indentação.
_LOCAL_ZONE_RE = re.compile(r'^\s*local-zone:\s*"([^"]+?)\.?"\s*\w+')


class BlockedMatcher:
    """
    Carrega o set de domínios `local-zone` do conf do Unbound e responde
    `matches(domain)` com match por sufixo.

    Thread-safe: lock em torno do set durante o reload. Reads são naked
    porque `frozenset` é imutável — safe sem lock.

    Uso típico:
        matcher = BlockedMatcher()  # ou injete path/ttl customizado
        if matcher.matches("ads.evil.com"):
            # bloqueio nosso
    """

    def __init__(
        self,
        conf_path: str | Path = DEFAULT_CONF_PATH,
        cache_ttl: float = DEFAULT_CACHE_TTL,
    ) -> None:
        self._path = Path(conf_path)
        self._ttl = cache_ttl
        self._lock = threading.Lock()
        self._domains: frozenset[str] = frozenset()
        self._loaded_at: float = 0.0
        self._loaded_mtime: float = 0.0

    # ------------------------------------------------------------------
    # API pública
    # ------------------------------------------------------------------

    def matches(self, domain: str) -> bool:
        """
        True se `domain` (ou algum sufixo) está nas local-zones do Unbound.
        Recarrega o cache se necessário.
        """
        self._maybe_reload()
        d = domain.lower().rstrip(".")
        if not d:
            return False
        if d in self._domains:
            return True
        # Sobe por sufixos: "a.b.evil.com" → "b.evil.com" → "evil.com" → "com"
        idx = d.find(".")
        while idx != -1:
            suffix = d[idx + 1 :]
            if suffix in self._domains:
                return True
            idx = d.find(".", idx + 1)
        return False

    def size(self) -> int:
        """Quantidade de local-zones carregadas. Útil pra debug/health."""
        self._maybe_reload()
        return len(self._domains)

    def force_reload(self) -> int:
        """Força re-leitura do arquivo. Retorna nova quantidade."""
        self._reload()
        return len(self._domains)

    # ------------------------------------------------------------------
    # Internos
    # ------------------------------------------------------------------

    def _maybe_reload(self) -> None:
        now = time.time()
        # Cache vivo? Skip.
        if self._loaded_at > 0 and (now - self._loaded_at) < self._ttl:
            return
        # Recarrega se mudou mtime OU se nunca carregou
        try:
            mtime = self._path.stat().st_mtime
        except FileNotFoundError:
            with self._lock:
                self._domains = frozenset()
                self._loaded_at = now
                self._loaded_mtime = 0.0
            return
        if mtime != self._loaded_mtime or not self._domains:
            self._reload()
        else:
            # Conf não mudou — atualiza só o timestamp pra não tentar de novo
            self._loaded_at = now

    def _reload(self) -> None:
        with self._lock:
            try:
                mtime = self._path.stat().st_mtime
                with self._path.open("r", encoding="utf-8", errors="replace") as fh:
                    domains: set[str] = set()
                    for line in fh:
                        m = _LOCAL_ZONE_RE.match(line)
                        if m:
                            domains.add(m.group(1).lower())
                self._domains = frozenset(domains)
                self._loaded_at = time.time()
                self._loaded_mtime = mtime
                log.info(
                    "blocked_matcher.reloaded",
                    count=len(self._domains),
                    path=str(self._path),
                )
            except FileNotFoundError:
                self._domains = frozenset()
                self._loaded_at = time.time()
                self._loaded_mtime = 0.0
                log.warning("blocked_matcher.conf_missing", path=str(self._path))
            except Exception as exc:  # noqa: BLE001
                log.error("blocked_matcher.reload_failed", error=str(exc))


# Singleton — log_watcher injeta via parâmetro; quem não precisa
# customizar usa este.
_default: BlockedMatcher | None = None


def get_default_matcher() -> BlockedMatcher:
    global _default
    if _default is None:
        _default = BlockedMatcher()
    return _default
