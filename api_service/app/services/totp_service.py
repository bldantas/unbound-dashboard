"""
TOTP (RFC 6238) — 2FA opt-in por usuário.

API:
  - generate_secret() : str base32 32-chars  (NÃO persiste — caller decide)
  - provisioning_uri(secret, username) : str otpauth:// (alimenta QR no UI)
  - verify(secret, code) : bool — valid_window=1 (aceita ±30s clock skew)

Storage: coluna `users.totp_secret` (plaintext) + `users.totp_enabled` (bool).
Vide migration V4__totp.sql.

Setup flow:
  1. /auth/2fa/setup → gera secret novo, devolve {secret, uri}; NÃO persiste.
  2. UI mostra QR + pede ao user pra digitar o code do app.
  3. /auth/2fa/confirm com {secret, code} → verifica e persiste.

Login flow:
  1. /auth/login → se totp_enabled, retorna {requires_totp, challenge_token}.
  2. /auth/login/2fa-verify com {challenge_token, code} → JWT.
  challenge_token = JWT especial com TTL 5min, claim `totp_pending: true`.

Reset por admin: zera totp_secret/enabled via users_service.
"""

from __future__ import annotations

import pyotp

# Issuer aparece no app autenticador (Google Authenticator, Authy, etc).
# Não usar hostname dinâmico — ele entra na URL otpauth e quebra a UX
# se o user trocar de máquina/VPN.
_ISSUER = "Unbound Dashboard"


def generate_secret() -> str:
    """Gera secret base32 32-chars — entropia 160 bits."""
    return pyotp.random_base32()


def provisioning_uri(secret: str, username: str) -> str:
    """
    URI otpauth:// que o app autenticador interpreta via QR.
    Formato: otpauth://totp/<issuer>:<account>?secret=<...>&issuer=<...>
    """
    totp = pyotp.TOTP(secret)
    return totp.provisioning_uri(name=username, issuer_name=_ISSUER)


def verify(secret: str, code: str) -> bool:
    """
    Verifica o code de 6 dígitos. `valid_window=1` aceita o code anterior
    e o próximo (cobre ±30s de skew de relógio entre user e servidor).
    """
    if not secret or not code:
        return False
    code = code.strip().replace(" ", "")
    if not code.isdigit() or len(code) != 6:
        return False
    try:
        totp = pyotp.TOTP(secret)
        return totp.verify(code, valid_window=1)
    except Exception:  # noqa: BLE001
        return False
