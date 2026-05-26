"""
cipher_service — Fernet-based symmetric encryption pra secrets em DB.

Resolve o problema de OIDC client_secret + HA peers tokens em texto plano
no DuckDB. Quem ler o DB direto agora vê só ciphertext; só quem tiver
`SECRETS_MASTER_KEY` (env var, formato Fernet 32-byte urlsafe base64)
consegue decifrar.

Formato do output: `"enc:v1:<fernet-token>"`. Prefix identifica que é
cifrado — `decrypt()` retorna inalterado se não tem prefix (compat com
valores legacy plaintext durante migração).

Sem `SECRETS_MASTER_KEY` configurada:
- Log warning no startup
- `encrypt()` retorna plaintext (com warning log)
- `decrypt()` continua funcionando pra plaintext

Geração da chave: `python -c 'from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())'`
"""

from __future__ import annotations

import os

import structlog
from cryptography.fernet import Fernet, InvalidToken

log = structlog.get_logger(__name__)

_PREFIX = "enc:v1:"
_cipher: Fernet | None = None
_key_loaded = False


def _load_key() -> Fernet | None:
    """Lê SECRETS_MASTER_KEY do env. Cacheia o objeto Fernet."""
    global _cipher, _key_loaded
    if _key_loaded:
        return _cipher
    _key_loaded = True
    key = os.environ.get("SECRETS_MASTER_KEY", "").strip()
    if not key:
        log.warning(
            "cipher_service.no_master_key",
            hint="Defina SECRETS_MASTER_KEY pra cifrar secrets em DB. "
                 "Gere com: python -c 'from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())'",
        )
        return None
    try:
        _cipher = Fernet(key.encode() if isinstance(key, str) else key)
        log.info("cipher_service.master_key_loaded")
    except Exception as exc:  # noqa: BLE001
        log.error("cipher_service.invalid_master_key", error=str(exc))
        _cipher = None
    return _cipher


def is_available() -> bool:
    """True se master key está configurada e válida."""
    return _load_key() is not None


def is_encrypted(value: str | None) -> bool:
    return bool(value) and isinstance(value, str) and value.startswith(_PREFIX)


def encrypt(plaintext: str) -> str:
    """Cifra com Fernet. Sem master key, retorna plaintext (com warning)."""
    if not plaintext:
        return plaintext
    cipher = _load_key()
    if cipher is None:
        log.warning("cipher_service.encrypt_fallback_plaintext")
        return plaintext
    token = cipher.encrypt(plaintext.encode("utf-8")).decode("utf-8")
    return _PREFIX + token


def decrypt(value: str | None) -> str:
    """Decifra se prefixado; senão retorna como-está (legacy plaintext)."""
    if not value or not isinstance(value, str):
        return value or ""
    if not value.startswith(_PREFIX):
        return value  # legacy plaintext — sem prefix
    cipher = _load_key()
    if cipher is None:
        log.error("cipher_service.decrypt_no_key", value_prefix="enc:v1:...")
        return ""
    token = value[len(_PREFIX):]
    try:
        return cipher.decrypt(token.encode("utf-8")).decode("utf-8")
    except InvalidToken:
        log.error("cipher_service.decrypt_invalid_token")
        return ""


def status() -> dict:
    """Snapshot pra endpoint admin."""
    return {
        "available": is_available(),
        "prefix": _PREFIX,
        "algorithm": "Fernet (AES-128-CBC + HMAC-SHA256)",
        "key_source": "env SECRETS_MASTER_KEY" if is_available() else "not configured",
    }
