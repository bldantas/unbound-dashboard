"""
OIDC SSO — Authorization Code flow simples.

Suporta qualquer IdP OpenID Connect (Google, Microsoft Entra ID, Keycloak,
Authentik, etc). Flow:

1. UI/Login → `GET /api/v1/auth/oidc/login` → redirect ao IdP com state
2. IdP autentica usuário → redirect ao `redirect_uri` com `?code=...&state=...`
3. `/api/v1/auth/oidc/callback` → POST `token_endpoint` com code → recebe
   id_token + access_token
4. Valida id_token (JWKS do issuer, cache 1h)
5. Match user por email; cria se `auto_create_users`
6. Emite JWT local + redireciona pra UI

NÃO usa PKCE (TODO se IdP exigir).
client_secret é cifrado via cipher_service (Fernet) se SECRETS_MASTER_KEY
estiver configurada — fallback plaintext sem ela.
"""

from __future__ import annotations

import json
import secrets
import time
from datetime import datetime
from typing import Any

import httpx
import structlog
from jose import jwt as jose_jwt
from jose.exceptions import JWTError

from app.repositories.duckdb import user_repo
from app.repositories.duckdb.connection import db_execute, db_fetchone

log = structlog.get_logger(__name__)

VALID_ROLES = ("admin", "readonly_admin", "operator", "viewer")
_JWKS_CACHE: dict[str, dict[str, Any]] = {}
_JWKS_CACHE_TTL = 3600.0


# ---------- Config ----------

async def get_config(include_secret: bool = False) -> dict:
    from app.services import cipher_service
    row = await db_fetchone("SELECT * FROM oidc_config WHERE id = 1", [])
    if not row:
        return {"enabled": False}
    # Prioriza encrypted; fallback legacy plaintext
    encrypted = row.get("client_secret_encrypted") or ""
    legacy = row.get("client_secret") or ""
    has_secret = bool(encrypted or legacy)
    out = {
        "enabled": bool(row.get("enabled")),
        "issuer_url": row.get("issuer_url") or "",
        "client_id": row.get("client_id") or "",
        "scopes": row.get("scopes") or "openid email profile",
        "allowed_email_domains": row.get("allowed_email_domains") or "",
        "auto_create_users": bool(row.get("auto_create_users")),
        "default_role": row.get("default_role") or "viewer",
        "has_secret": has_secret,
        "secret_encrypted": bool(encrypted),
        "updated_at": row["updated_at"].isoformat() if isinstance(row.get("updated_at"), datetime) else None,
    }
    if include_secret:
        if encrypted:
            out["client_secret"] = cipher_service.decrypt(encrypted)
        else:
            out["client_secret"] = legacy
    return out


async def update_config(body: dict) -> dict:
    from app.services import cipher_service
    fields = []
    params: list = []
    allowed = {
        "enabled": "BOOLEAN", "issuer_url": "STR", "client_id": "STR",
        "client_secret": "SECRET", "scopes": "STR",
        "allowed_email_domains": "STR", "auto_create_users": "BOOLEAN",
        "default_role": "ROLE",
    }
    for k, typ in allowed.items():
        if k not in body:
            continue
        v = body[k]
        if typ == "BOOLEAN":
            v = bool(v)
        elif typ == "ROLE":
            if v not in VALID_ROLES:
                raise ValueError(f"default_role inválido: {v}")
        else:
            v = str(v or "").strip()
            if k == "issuer_url":
                v = v.rstrip("/")
        # Não sobrescrever client_secret com string vazia (preserva o atual)
        if typ == "SECRET":
            if v == "":
                continue
            # Cifra e grava na coluna _encrypted; zera a legacy
            fields.append("client_secret_encrypted = ?")
            params.append(cipher_service.encrypt(v))
            fields.append("client_secret = ?")
            params.append("")
            continue
        fields.append(f"{k} = ?")
        params.append(v)
    if not fields:
        return await get_config()
    fields.append("updated_at = NOW()")
    await db_execute(f"UPDATE oidc_config SET {', '.join(fields)} WHERE id = 1", params)
    return await get_config()


# ---------- Discovery + JWKS ----------

async def _discover(issuer_url: str) -> dict:
    """GET <issuer>/.well-known/openid-configuration."""
    url = issuer_url.rstrip("/") + "/.well-known/openid-configuration"
    async with httpx.AsyncClient(timeout=10.0) as client:
        r = await client.get(url)
        r.raise_for_status()
        return r.json()


async def _get_jwks(issuer_url: str) -> dict:
    """Cache JWKS por 1h (chaves rodam raramente)."""
    now = time.time()
    cached = _JWKS_CACHE.get(issuer_url)
    if cached and cached["expires_at"] > now:
        return cached["jwks"]

    disc = await _discover(issuer_url)
    jwks_uri = disc.get("jwks_uri")
    if not jwks_uri:
        raise ValueError("issuer não expõe jwks_uri")

    async with httpx.AsyncClient(timeout=10.0) as client:
        r = await client.get(jwks_uri)
        r.raise_for_status()
        jwks = r.json()

    _JWKS_CACHE[issuer_url] = {"jwks": jwks, "expires_at": now + _JWKS_CACHE_TTL}
    return jwks


# ---------- Auth URL build + callback ----------

async def build_auth_url(base_callback_url: str) -> dict:
    """Constrói URL de autorização do IdP + state pra CSRF.

    `base_callback_url` é o callback completo (ex:
    https://dashboard.local/api/v1/auth/oidc/callback). State é guardado
    em settings table com TTL (cleanup manual via callback).
    """
    cfg = await get_config(include_secret=False)
    if not cfg["enabled"]:
        raise ValueError("OIDC desabilitado")
    if not cfg["issuer_url"] or not cfg["client_id"]:
        raise ValueError("issuer_url e client_id obrigatórios")

    disc = await _discover(cfg["issuer_url"])
    authorize = disc.get("authorization_endpoint")
    if not authorize:
        raise ValueError("issuer não expõe authorization_endpoint")

    state = secrets.token_urlsafe(24)
    nonce = secrets.token_urlsafe(24)

    # Guarda state+nonce com TTL via settings table (chave temporária)
    from app.repositories.duckdb import settings_repo
    await settings_repo.bulk_upsert([
        {"setting_key": f"_oidc_state_{state}",
         "setting_value": json.dumps({"nonce": nonce, "expires": int(time.time() + 600)})}
    ])

    params = {
        "client_id": cfg["client_id"],
        "redirect_uri": base_callback_url,
        "response_type": "code",
        "scope": cfg["scopes"],
        "state": state,
        "nonce": nonce,
    }
    import urllib.parse
    return {
        "url": authorize + "?" + urllib.parse.urlencode(params),
        "state": state,
    }


async def handle_callback(code: str, state: str, redirect_uri: str) -> dict:
    """Troca code por tokens, valida id_token, sync/create user, retorna user dict.

    Caller (router) é responsável por gerar o JWT local e setar cookie.
    """
    from app.repositories.duckdb import settings_repo

    # Valida state
    state_key = f"_oidc_state_{state}"
    state_raw = await settings_repo.get(state_key, "")
    if not state_raw:
        raise ValueError("state inválido ou expirado")
    try:
        state_data = json.loads(state_raw)
    except json.JSONDecodeError:
        raise ValueError("state corrompido")
    if state_data.get("expires", 0) < int(time.time()):
        raise ValueError("state expirado")
    nonce = state_data.get("nonce")

    # Cleanup state após validar (one-shot)
    try:
        await db_execute("DELETE FROM settings WHERE setting_key = ?", [state_key])
    except Exception:  # noqa: BLE001
        pass

    cfg = await get_config(include_secret=True)
    disc = await _discover(cfg["issuer_url"])
    token_endpoint = disc.get("token_endpoint")
    if not token_endpoint:
        raise ValueError("issuer não expõe token_endpoint")

    # Exchange code → tokens
    data = {
        "grant_type": "authorization_code",
        "code": code,
        "redirect_uri": redirect_uri,
        "client_id": cfg["client_id"],
        "client_secret": cfg["client_secret"],
    }
    async with httpx.AsyncClient(timeout=15.0) as client:
        r = await client.post(token_endpoint, data=data)
        if r.status_code != 200:
            log.warning("oidc.token_exchange_failed", status=r.status_code, body=r.text[:300])
            raise ValueError(f"token exchange falhou: {r.status_code}")
        tokens = r.json()

    id_token = tokens.get("id_token")
    if not id_token:
        raise ValueError("token endpoint não retornou id_token")

    # Valida id_token
    jwks = await _get_jwks(cfg["issuer_url"])
    try:
        claims = jose_jwt.decode(
            id_token,
            jwks,
            algorithms=["RS256", "RS384", "RS512", "ES256"],
            audience=cfg["client_id"],
            issuer=cfg["issuer_url"],
            options={"verify_at_hash": False},
        )
    except JWTError as exc:
        raise ValueError(f"id_token inválido: {exc}") from exc

    if claims.get("nonce") != nonce:
        raise ValueError("nonce mismatch")

    email = claims.get("email", "").lower().strip()
    if not email:
        raise ValueError("id_token sem email — habilite scope 'email' no IdP")

    # Allowed domains
    if cfg["allowed_email_domains"]:
        allowed = [d.strip().lower() for d in cfg["allowed_email_domains"].split(",") if d.strip()]
        domain = email.split("@", 1)[-1]
        if allowed and domain not in allowed:
            raise ValueError(f"domínio '{domain}' não permitido")

    # Sync user
    user = await user_repo.find_by_email(email)
    if user is None:
        if not cfg["auto_create_users"]:
            raise ValueError(f"usuário '{email}' não cadastrado (auto-create desligado)")
        # Cria com role default
        username = email.split("@", 1)[0]
        # Garante unicidade do username
        existing_by_name = await user_repo.find_by_username(username)
        if existing_by_name:
            username = f"{username}-{secrets.token_hex(3)}"
        await db_execute(
            """
            INSERT INTO users (username, password_hash, role, email, is_active, created_at)
            VALUES (?, '', ?, ?, true, NOW())
            """,
            [username, cfg["default_role"], email],
        )
        user = await user_repo.find_by_email(email)
        log.info("oidc.user_auto_created", email=email, username=username, role=cfg["default_role"])

    if not user.get("is_active"):
        raise ValueError(f"usuário '{email}' inativo")

    log.info("oidc.login_success", email=email, user_id=user["id"])
    return {
        "id": user["id"],
        "username": user["username"],
        "role": user["role"],
        "email": user.get("email"),
    }
