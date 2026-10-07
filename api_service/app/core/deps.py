"""Dependências FastAPI compartilhadas — auth (Bearer JWT ou API token) e RBAC."""

from __future__ import annotations

from typing import Annotated

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token
from app.services import sessions
from app.services.jwt_denylist import is_token_hash_revoked, is_user_revoked

# auto_error=False — temos auth alternativa (X-Api-Token) então não levantamos
# 401 imediato se o Bearer estiver ausente; tentamos o api token antes.
_bearer = HTTPBearer(auto_error=False)


async def require_auth(
    request: Request,
    credentials: Annotated[HTTPAuthorizationCredentials | None, Depends(_bearer)] = None,
) -> dict:
    """
    Aceita JWT (Authorization: Bearer ...) OU API Token (X-Api-Token: ...).
    Retorna um payload normalizado em ambos os casos:
      - JWT → payload do decode (sub, role, iat, exp, ...)
      - API token → {"sub": "api-token", "role": "admin", "auth_kind": "api_token",
                     "api_token_id": N, "api_token_label": "..."}

    API tokens são considerados "admin" pra fins de RBAC — geram acesso
    pleno ao agent. Granularidade futura pode mudar isso (capabilities
    por token).

    Validações (JWT path):
    1. Assinatura + `exp` (via decode_token)
    2. Denylist per-user: se `iat` < `revoked_at`, rejeita 401.
       Usado quando admin desativa conta — corta todas as sessões do user.
    3. Denylist por token-hash: se a sessão específica foi revogada
       (ex: user clicou "Encerrar sessão dessa máquina" no perfil).

    Side-effect: registra a sessão em Redis pra "Sessões Ativas" UI.
    """
    # === API Token path (header X-Api-Token) ===
    api_token = request.headers.get("x-api-token")
    if api_token:
        from app.services import api_tokens

        xff = request.headers.get("x-forwarded-for", "")
        source_ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else "")
        meta = await api_tokens.verify(api_token, source_ip=source_ip)
        if meta is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="API token inválido ou revogado",
            )
        # Capabilities (v2.110+): vazias = admin global (backward-compat).
        # Não-vazias = token restrito a essas caps; require_capability vai
        # validar via api_token_capabilities em vez do role.
        return {
            "sub": "api-token",
            "role": "admin",
            "auth_kind": "api_token",
            "api_token_id": meta["id"],
            "api_token_label": meta["label"],
            "api_token_capabilities": meta.get("capabilities", []),
        }

    # === JWT path (header Authorization: Bearer ...) ===
    if credentials is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authorization ausente — use Bearer JWT ou X-Api-Token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    token = credentials.credentials
    try:
        payload = decode_token(token)
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None

    # Challenge de 2FA (emitido por /login quando totp_enabled) é assinado com
    # o mesmo segredo, mas só vale em /login/2fa-verify. Aceitá-lo aqui
    # permitiria pular o segundo fator.
    if payload.get("totp_pending"):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Login incompleto — confirme o código 2FA",
            headers={"WWW-Authenticate": "Bearer"},
        )

    try:
        user_id = int(payload.get("sub", 0))
        iat = payload.get("iat")
        exp = payload.get("exp")
    except (TypeError, ValueError):
        user_id = 0
        iat = None
        exp = None

    if user_id and await is_user_revoked(user_id, iat):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token revogado — conta foi desativada ou banida",
            headers={"WWW-Authenticate": "Bearer"},
        )

    token_hash = sessions.hash_token(token)
    if await is_token_hash_revoked(token_hash):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Sessão encerrada — faça login novamente",
            headers={"WWW-Authenticate": "Bearer"},
        )

    # Tracking: best-effort, não derruba a request se Redis off
    if user_id and isinstance(iat, int) and isinstance(exp, int):
        # IP do cliente atrás do proxy (Apache passa X-Forwarded-For)
        xff = request.headers.get("x-forwarded-for", "")
        ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else "")
        ua = request.headers.get("user-agent", "")
        await sessions.track(user_id, token_hash, ip or "?", ua or "?", iat, exp)

    return payload


_WS_ROLES = frozenset({"admin", "readonly_admin", "operator", "viewer"})


async def validate_ws_token(token: str) -> dict | None:
    """Autenticação dos WebSockets (token na query string — o browser não
    manda header Authorization no handshake). Mesmas regras de
    `require_auth`: JWT válido, sem 2FA pendente, fora da denylist (sessão
    encerrada / conta revogada); ou API token, que se tiver escopo precisa de
    `dashboard.read`. Retorna o payload ou None."""
    if not token:
        return None
    try:
        payload = decode_token(token)
    except JWTError:
        payload = None
    if payload is not None:
        if payload.get("totp_pending") or payload.get("role") not in _WS_ROLES:
            return None
        try:
            user_id = int(payload.get("sub", 0))
        except (TypeError, ValueError):
            user_id = 0
        if user_id and await is_user_revoked(user_id, payload.get("iat")):
            return None
        if await is_token_hash_revoked(sessions.hash_token(token)):
            return None
        return payload

    from app.services import api_tokens

    try:
        meta = await api_tokens.verify(token)
    except Exception:  # noqa: BLE001
        return None
    if meta is None:
        return None
    caps = meta.get("capabilities") or []
    if caps and "dashboard.read" not in caps:
        return None
    return {
        "sub": "api-token",
        "role": "admin",
        "auth_kind": "api_token",
        "api_token_id": meta["id"],
        "api_token_label": meta["label"],
        "api_token_capabilities": caps,
    }


async def resolve_viewer_org_id(payload: dict) -> int | None:
    """Resolve a org_id do caller pra filtros multi-tenant.

    - API token → None (sempre global, age como system admin).
    - User com `org_id` NULL no DB → None (system admin, vê tudo).
    - User com `org_id = N` → N (vê globais + da própria org).
    """
    if payload.get("auth_kind") == "api_token":
        return None
    try:
        user_id = int(payload.get("sub", 0))
    except (TypeError, ValueError):
        return None
    if user_id < 1:
        return None
    from app.repositories.duckdb.connection import db_fetchone
    row = await db_fetchone("SELECT org_id FROM users WHERE id = ?", [user_id])
    if not row or row.get("org_id") is None:
        return None
    return int(row["org_id"])


async def require_admin(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """Exige role = 'admin' no payload do JWT.

    NOTE: não diferencia admin global de admin org-scoped (v2.109+).
    Pra endpoints que devem ser exclusivos do admin global (infra, SMTP,
    webhooks, OIDC, cluster, organizations CRUD, backup destinations,
    secrets, API tokens), use `require_global_admin` abaixo.
    """
    if payload.get("role") != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: requer privilégios de administrador",
        )
    _deny_scoped_api_token(payload)
    return payload


def _deny_scoped_api_token(payload: dict) -> None:
    """API token com capabilities (v2.110+) recebe role=admin no payload, mas só
    pode o que as capabilities dele dizem (ver `require_capability`). Rotas
    protegidas só por role admin não declaram capability, então ficam fora do
    escopo do token. Tokens sem capabilities continuam admin global (compat).
    """
    if payload.get("auth_kind") == "api_token" and payload.get("api_token_capabilities"):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: API token com escopo restrito não acessa "
                   "rotas exclusivas de administrador",
        )


async def require_global_admin(
    payload: Annotated[dict, Depends(require_auth)],
) -> dict:
    """Exige admin com `org_id` NULL (ou autenticação via API token).

    "Admin global" = pode mexer em infra/configs que afetam o sistema todo
    ou outras orgs. Admin org-scoped (role=admin, org_id=N) é negado aqui
    com 403.

    Usar em endpoints que NÃO devem ser acessíveis a admins de uma org:
    - Webhooks, SMTP, OIDC config, API tokens
    - Organizations CRUD
    - Cluster HA peers + failover
    - Backup destinations
    - Secrets management
    - Unbound config global (split-horizon de policies é OK pra admin
      org-scoped via outros endpoints, mas mudar config.conf do daemon
      todo é infra-level)

    API tokens são tratados como admin global por design (multi-host).
    """
    if payload.get("role") != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: requer privilégios de administrador global",
        )
    # API token sem escopo passa (infra-level); com escopo é negado
    if payload.get("auth_kind") == "api_token":
        _deny_scoped_api_token(payload)
        return payload
    # JWT path: olha org_id do user no DB
    viewer_org = await resolve_viewer_org_id(payload)
    if viewer_org is not None:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: este recurso é exclusivo do admin global "
                   "(sem org_id). Admin org-scoped não tem permissão.",
        )
    return payload


def require_capability(capability: str):
    """
    Factory de dependency que valida uma capability RBAC.

    Uso em endpoints:
        @router.put("/foo", dependencies=[Depends(require_capability("config.write"))])

    Ou injetando payload:
        async def foo(payload: dict = Depends(require_capability("alerts.resolve"))):

    Capability inexistente = 403 (deny by default).

    Lógica de avaliação (v2.110+):
    - JWT path → checa via role (CAPABILITIES dict no rbac.py)
    - API token sem capabilities (= [] ou ausente) → role=admin, passa tudo
      (backward-compat com tokens pré-v2.110)
    - API token com capabilities → SÓ a cap requerida estar na lista do
      token, role=admin no payload é ignorado pra essa decisão. Princípio
      do menor privilégio pra integrações externas.
    """
    from app.core.rbac import can

    async def _dep(payload: Annotated[dict, Depends(require_auth)]) -> dict:
        # API token com capabilities restritas: bypassa o role check
        if payload.get("auth_kind") == "api_token":
            token_caps = payload.get("api_token_capabilities") or []
            if token_caps:
                # Token escopado: só passa se cap está nas caps do token
                if capability not in token_caps:
                    raise HTTPException(
                        status_code=status.HTTP_403_FORBIDDEN,
                        detail=f"Acesso negado: API token sem capability '{capability}' "
                               f"(scopes: {sorted(token_caps)})",
                    )
                return payload
            # Token sem capabilities = admin global (backward-compat)

        # JWT path ou api_token admin global: avalia por role
        role = payload.get("role")
        if not can(role, capability):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Acesso negado: requer permissão '{capability}'",
            )
        return payload

    return _dep
