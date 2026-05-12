"""
JWT (encode/decode) e bcrypt (verify/hash) — usado por AuthService e deps.

Lições do audit aplicadas (ver memory/audit_findings_v2_integration.md):
  - JWT_SECRET é validado no startup (config.py field_validator) — rejeita placeholder.
  - bcrypt rounds=12 (custo seguro hoje, ~250ms de hash).
  - decode_token explicitamente passa `algorithms=[settings.jwt_algorithm]` —
    previne `alg` confusion attack (ex: atacante muda alg pra "none" ou "HS256"
    quando esperávamos "RS256").
"""

from __future__ import annotations

from datetime import UTC, datetime, timedelta

import bcrypt
from jose import JWTError, jwt

from app.core.config import settings


def hash_password(plain: str) -> str:
    return bcrypt.hashpw(plain.encode(), bcrypt.gensalt(rounds=12)).decode()


def verify_password(plain: str, hashed: str) -> bool:
    try:
        return bcrypt.checkpw(plain.encode(), hashed.encode())
    except (ValueError, TypeError):
        return False


def verify_password_dummy() -> None:
    """
    Roda bcrypt.checkpw com payload fake quando o usuário NÃO existe.
    Mantém o tempo total de resposta constante — sem isso, login seria
    timing-sensitive (mais rápido pra usuário inexistente, atacante consegue
    enumerar usernames pelo tempo de resposta).
    """
    bcrypt.checkpw(b"dummy", bcrypt.hashpw(b"dummy", bcrypt.gensalt(rounds=12)))


def create_access_token(payload: dict, expires_delta: timedelta | None = None) -> str:
    now = datetime.now(UTC)
    expire = now + (expires_delta or timedelta(minutes=settings.jwt_expire_minutes))
    # `iat` (issued at) é usado pelo denylist Redis: quando admin
    # desativa um user, gravamos `user:<id>:revoked_at = now`. Tokens
    # com `iat < revoked_at` são rejeitados em require_auth.
    return jwt.encode(
        {**payload, "iat": int(now.timestamp()), "exp": expire},
        settings.jwt_secret.get_secret_value(),
        algorithm=settings.jwt_algorithm,
    )


def decode_token(token: str) -> dict:
    """Decodifica e valida JWT. Lança JWTError em caso de token inválido/expirado."""
    return jwt.decode(
        token,
        settings.jwt_secret.get_secret_value(),
        algorithms=[settings.jwt_algorithm],
    )


__all__ = [
    "JWTError",
    "create_access_token",
    "decode_token",
    "hash_password",
    "verify_password",
    "verify_password_dummy",
]
