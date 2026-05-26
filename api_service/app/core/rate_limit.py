"""Rate limiter compartilhado (slowapi).

Key derivation: tenta extrair token primeiro (Authorization Bearer ou
X-Api-Token), fallback IP cliente. Permite limitar uso por
credencial independente do IP — útil pra detectar token comprometido
fazendo abuso de N IPs.

`rate_limit_per_token` em settings DuckDB sobrepõe `rate_limit_default`
quando a key veio de um token; cache 30s pra não bater no DB a cada
request.
"""

from __future__ import annotations

import hashlib
import time

from slowapi import Limiter
from starlette.requests import Request

from app.core.config import settings


def _token_or_ip_key(request: Request) -> str:
    """Prefere o token (hash truncado pra evitar logar plaintext); fallback IP."""
    auth = request.headers.get("authorization", "")
    if auth.startswith("Bearer "):
        tok = auth[7:].strip()
        if tok:
            return "tok:" + hashlib.sha256(tok.encode()).hexdigest()[:24]
    api_tok = request.headers.get("x-api-token", "").strip()
    if api_tok:
        return "tok:" + hashlib.sha256(api_tok.encode()).hexdigest()[:24]
    # Fallback IP — respeita X-Forwarded-For (Apache reverso)
    fwd = request.headers.get("x-forwarded-for", "")
    if fwd:
        return "ip:" + fwd.split(",")[0].strip()
    return "ip:" + (request.client.host if request.client else "unknown")


limiter = Limiter(
    key_func=_token_or_ip_key,
    default_limits=[settings.rate_limit_default],
    enabled=settings.rate_limit_enabled,
)
