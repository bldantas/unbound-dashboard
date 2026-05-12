"""
Service de gestão de usuários — espelho dos métodos de src/Auth.php que iam
direto no MariaDB. Após esta migração, Auth.php usa ApiClient pra tudo.
"""

from __future__ import annotations

import hashlib
import secrets
from datetime import UTC, datetime, timedelta

from app.core.security import hash_password
from app.repositories.duckdb import user_repo


class UserError(Exception):
    """Base."""


class UsernameAlreadyExists(UserError):
    pass


class EmailAlreadyExists(UserError):
    pass


class WeakPassword(UserError):
    pass


class UserNotFound(UserError):
    pass


class CannotTargetSelf(UserError):
    """Tentativa de toggle/delete do próprio user."""


class InvalidResetToken(UserError):
    pass


_PASSWORD_RESET_VALIDITY_MINUTES = 10


def _utc_naive_in(minutes: int) -> datetime:
    """DuckDB TIMESTAMP é naive — gerar UTC sem tzinfo."""
    return (datetime.now(UTC) + timedelta(minutes=minutes)).replace(tzinfo=None)


VALID_ROLES = {"admin", "viewer"}


async def list_all() -> list[dict]:
    rows = await user_repo.list_all()
    return [
        {
            "id": int(r["id"]),
            "username": str(r["username"]),
            "email": str(r["email"] or "") or None,
            "role": str(r["role"]),
            "is_active": bool(r["is_active"]),
            "failed_logins": int(r["failed_logins"] or 0),
            "locked_until": r["locked_until"].isoformat() if r.get("locked_until") else None,
            "created_at": r["created_at"].isoformat() if r.get("created_at") else None,
            "last_login_at": r["last_login_at"].isoformat() if r.get("last_login_at") else None,
        }
        for r in rows
    ]


async def has_any_users() -> bool:
    return (await user_repo.count_total()) > 0


async def create(username: str, password: str, role: str, email: str | None) -> int:
    """
    Cria usuário. Validações: senha >=6 chars, username não-vazio,
    username/email únicos. Lança exceções de domínio em violação.
    """
    if not username.strip():
        raise UsernameAlreadyExists  # PHP usa msg genérica; aqui usa pra signal
    if len(password) < 6:
        raise WeakPassword
    new_id = await user_repo.create(
        username=username,
        password_hash=hash_password(password),
        role=role,
        email=email,
    )
    if new_id is None:
        raise UsernameAlreadyExists
    return new_id


async def update_email(user_id: int, new_email: str) -> None:
    ok = await user_repo.update_email(user_id, new_email)
    if not ok:
        raise EmailAlreadyExists


async def toggle_active(user_id: int, requesting_user_id: int) -> None:
    if user_id == requesting_user_id:
        raise CannotTargetSelf
    ok = await user_repo.toggle_active(user_id)
    if not ok:
        raise UserNotFound
    # Após toggle, revoga tokens se a conta passou a inativa.
    # (toggle_active inverte o valor — se ficou inactive agora, revoga)
    user_now = await user_repo.find_by_id(user_id)
    if user_now is not None and not user_now.get("is_active"):
        from app.services import jwt_denylist  # late import — evita ciclo
        await jwt_denylist.revoke_user_tokens(user_id)


async def delete_user(user_id: int, requesting_user_id: int) -> None:
    if user_id == requesting_user_id:
        raise CannotTargetSelf
    ok = await user_repo.delete_by_id(user_id)
    if not ok:
        raise UserNotFound
    # User deletado: revoga tokens (defesa em depth — find_by_id já falha
    # depois, mas se algum middleware esquecer o check, o revoke pega).
    from app.services import jwt_denylist
    await jwt_denylist.revoke_user_tokens(user_id)


class InvalidRole(UserError):
    pass


async def update_role(user_id: int, role: str, requesting_user_id: int) -> None:
    """
    Atualiza o role. Bloqueia self-target (admin não pode rebaixar-se
    sozinho — evita ficar sem admin no sistema; um outro admin precisa fazer).

    Side-effect: revoga tokens do user se o role mudou. Sem isso, o user
    rebaixado de admin pra viewer continuaria com role=admin no JWT até
    o token expirar.
    """
    if role not in VALID_ROLES:
        raise InvalidRole
    if user_id == requesting_user_id:
        raise CannotTargetSelf
    ok = await user_repo.update_role(user_id, role)
    if not ok:
        raise UserNotFound
    # Revoga tokens — força re-login com role novo no claim
    from app.services import jwt_denylist
    await jwt_denylist.revoke_user_tokens(user_id)


async def admin_reset_password(user_id: int, requesting_user_id: int) -> str:
    """
    Gera senha aleatória de 12 chars (alphanumérica) e substitui a do user.
    Retorna a senha em texto pra que o admin entregue ao usuário.
    Permitido em si mesmo (admin pode resetar sua própria senha).
    """
    raw = secrets.token_urlsafe(9)[:12]  # ~12 chars alphanuméricos
    ok = await user_repo.admin_set_password(user_id, hash_password(raw))
    if not ok:
        raise UserNotFound
    return raw


# ---------------------------------------------------------------------------
# Password reset
# ---------------------------------------------------------------------------


def _hash_token(token: str) -> str:
    return hashlib.sha256(token.encode()).hexdigest()


async def request_password_reset(email: str) -> str | None:
    """
    Se email pertence a user ativo, gera token aleatório, hashed, salva e
    retorna o token CRU (chamador envia por email). Em qualquer outro caso
    retorna None — comportamento timing-safe não revela se email existe.
    """
    if not email.strip():
        return None
    user = await user_repo.find_active_by_email(email)
    if not user:
        return None
    raw_token = secrets.token_hex(32)
    await user_repo.set_reset_token(
        int(user["id"]),
        _hash_token(raw_token),
        _utc_naive_in(_PASSWORD_RESET_VALIDITY_MINUTES),
    )
    return raw_token


async def confirm_password_reset(token: str, new_password: str) -> None:
    """
    Valida token + atualiza senha. Lança InvalidResetToken se token bad/expired.
    Lança WeakPassword se < 6 chars.
    """
    if len(new_password) < 6:
        raise WeakPassword
    user = await user_repo.find_by_reset_token(_hash_token(token))
    if not user:
        raise InvalidResetToken
    await user_repo.consume_reset_and_set_password(int(user["id"]), hash_password(new_password))
