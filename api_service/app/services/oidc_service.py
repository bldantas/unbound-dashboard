"""
OIDC SSO — Authorization Code flow com PKCE.

Suporta qualquer IdP OpenID Connect (Google, Microsoft Entra ID, Keycloak,
Authentik, etc). Flow:

1. UI/Login → `GET /api/v1/auth/oidc/login` → redirect ao IdP com state
2. IdP autentica usuário → redirect ao `redirect_uri` com `?code=...&state=...`
3. `/api/v1/auth/oidc/callback` → POST `token_endpoint` com code → recebe
   id_token + access_token
4. Valida id_token (JWKS do issuer, cache 1h)
5. Match user por email; cria se `auto_create_users`
6. Emite JWT local + redireciona pra UI

Usa PKCE S256 (RFC 7636) no Authorization Code flow: code_verifier
random 43-char base64url + code_challenge SHA256(verifier). IdPs sem
suporte ignoram os parâmetros extras — backwards-compatible.

client_secret é cifrado via cipher_service (Fernet) se SECRETS_MASTER_KEY
estiver configurada — fallback plaintext sem ela.
"""

from __future__ import annotations

import base64
import hashlib
import json
import secrets
import time
from datetime import datetime
from typing import Any

import httpx
import structlog
from jose import jwt as jose_jwt
from jose.exceptions import JWTError


def _pkce_pair() -> tuple[str, str]:
    """Gera (code_verifier, code_challenge) RFC 7636 S256.

    verifier: 43-char base64url-safe (256 bits de entropia).
    challenge: BASE64URL(SHA256(verifier)) sem padding.
    """
    verifier = secrets.token_urlsafe(32)  # 256 bits → ~43 chars base64url
    digest = hashlib.sha256(verifier.encode("ascii")).digest()
    challenge = base64.urlsafe_b64encode(digest).rstrip(b"=").decode("ascii")
    return verifier, challenge

from app.repositories.duckdb import user_repo
from app.repositories.duckdb.connection import db_execute, db_fetchone

log = structlog.get_logger(__name__)

VALID_ROLES = ("admin", "readonly_admin", "operator", "viewer")
# Rank pra group mapping precedence: maior rank vence sempre, independente
# da ordem em que aparece no claim. Admin > readonly_admin > operator > viewer.
_ROLE_RANK = {"admin": 4, "readonly_admin": 3, "operator": 2, "viewer": 1}
_JWKS_CACHE: dict[str, dict[str, Any]] = {}
_JWKS_CACHE_TTL = 3600.0


def _extract_claim_dotpath(claims: dict, path: str) -> Any:
    """Suporta dot-paths tipo 'realm_access.roles' (Keycloak)."""
    if not path:
        return None
    cur: Any = claims
    for part in path.split("."):
        if not isinstance(cur, dict):
            return None
        cur = cur.get(part)
        if cur is None:
            return None
    return cur


def _resolve_role_from_groups(claims: dict, cfg: dict) -> str | None:
    """Olha o claim configurado, intersecta com group_mappings.

    Coleta TODAS as roles mapeadas pelos grupos do IdP e retorna a de
    maior rank (admin > readonly_admin > operator > viewer). Isso evita
    o usuário cair pra viewer só porque o grupo viewer aparece antes do
    grupo admin no claim do IdP.

    Retorna None se nada bater (caller decide o fallback).
    """
    claim_path = (cfg.get("group_claim") or "").strip()
    if not claim_path:
        return None
    raw_mapping = cfg.get("group_mappings") or "{}"
    try:
        mapping = json.loads(raw_mapping) if isinstance(raw_mapping, str) else dict(raw_mapping)
    except (json.JSONDecodeError, TypeError, ValueError):
        return None
    if not mapping:
        return None

    groups_claim = _extract_claim_dotpath(claims, claim_path)
    if groups_claim is None:
        return None
    if isinstance(groups_claim, str):
        groups = [g.strip() for g in groups_claim.split(",") if g.strip()]
    elif isinstance(groups_claim, list):
        groups = [str(g) for g in groups_claim]
    else:
        return None

    # Coleta todas as roles válidas mapeadas e devolve a de maior rank
    best_role: str | None = None
    best_rank = -1
    for g in groups:
        role = mapping.get(g)
        if role in VALID_ROLES:
            rank = _ROLE_RANK.get(role, 0)
            if rank > best_rank:
                best_role = role
                best_rank = rank
    return best_role


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
        "group_claim": row.get("group_claim") or "",
        "group_mappings": row.get("group_mappings") or "{}",
        "sync_role_on_login": bool(row.get("sync_role_on_login")),
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
        "group_claim": "STR", "group_mappings": "MAPPINGS",
        "sync_role_on_login": "BOOLEAN",
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
        elif typ == "MAPPINGS":
            # Aceita dict ou JSON string; valida que valores são roles válidos
            if isinstance(v, dict):
                mapping = v
            else:
                try:
                    mapping = json.loads(str(v or "{}"))
                except json.JSONDecodeError:
                    raise ValueError("group_mappings: JSON inválido")
            if not isinstance(mapping, dict):
                raise ValueError("group_mappings deve ser objeto/dict")
            for grp, role in mapping.items():
                if role not in VALID_ROLES:
                    raise ValueError(f"group_mappings['{grp}']: role inválida '{role}'")
            v = json.dumps(mapping, ensure_ascii=False)
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
    code_verifier, code_challenge = _pkce_pair()

    # Guarda state+nonce+verifier com TTL via settings table (chave temporária)
    from app.repositories.duckdb import settings_repo
    await settings_repo.bulk_upsert([
        {"setting_key": f"_oidc_state_{state}",
         "setting_value": json.dumps({
             "nonce": nonce,
             "code_verifier": code_verifier,
             "expires": int(time.time() + 600),
         })}
    ])

    params = {
        "client_id": cfg["client_id"],
        "redirect_uri": base_callback_url,
        "response_type": "code",
        "scope": cfg["scopes"],
        "state": state,
        "nonce": nonce,
        # PKCE (RFC 7636) — exigido por Entra ID moderno e fortemente
        # recomendado pelos demais IdPs. Compat: IdPs que não suportam
        # PKCE ignoram esses params em vez de rejeitar a request.
        "code_challenge": code_challenge,
        "code_challenge_method": "S256",
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
    code_verifier = state_data.get("code_verifier")

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

    # Exchange code → tokens. Envia code_verifier (PKCE) se foi gerado no
    # auth — IdPs sem PKCE ignoram, IdPs com PKCE exigem.
    data = {
        "grant_type": "authorization_code",
        "code": code,
        "redirect_uri": redirect_uri,
        "client_id": cfg["client_id"],
        "client_secret": cfg["client_secret"],
    }
    if code_verifier:
        data["code_verifier"] = code_verifier
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

    # Resolve role via group mapping (se configurado)
    mapped_role = _resolve_role_from_groups(claims, cfg)

    # Sync user
    user = await user_repo.find_by_email(email)
    if user is None:
        if not cfg["auto_create_users"]:
            raise ValueError(f"usuário '{email}' não cadastrado (auto-create desligado)")
        # Cria com role mapeada (se houver) ou default
        role_to_apply = mapped_role or cfg["default_role"]
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
            [username, role_to_apply, email],
        )
        user = await user_repo.find_by_email(email)
        log.info("oidc.user_auto_created", email=email, username=username, role=role_to_apply,
                 role_source="group_mapping" if mapped_role else "default")
    elif cfg.get("sync_role_on_login") and mapped_role and mapped_role != user.get("role"):
        # Sincroniza role com IdP (se config explicitamente liga isso)
        await db_execute(
            "UPDATE users SET role = ? WHERE id = ?",
            [mapped_role, user["id"]],
        )
        log.info("oidc.role_synced", email=email, user_id=user["id"],
                 old_role=user.get("role"), new_role=mapped_role)
        user["role"] = mapped_role

    if not user.get("is_active"):
        raise ValueError(f"usuário '{email}' inativo")

    log.info("oidc.login_success", email=email, user_id=user["id"])
    return {
        "id": user["id"],
        "username": user["username"],
        "role": user["role"],
        "email": user.get("email"),
    }
