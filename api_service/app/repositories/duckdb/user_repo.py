"""Repository de users — leitura/escrita na tabela `users` do DuckDB."""

from __future__ import annotations

from datetime import datetime

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


async def find_by_username(username: str) -> dict | None:
    return await db_fetchone(
        """
        SELECT id, username, password_hash, role, email, is_active,
               failed_logins, locked_until, created_at
        FROM users
        WHERE username = ?
        """,
        [username],
    )


async def find_by_id(user_id: int) -> dict | None:
    return await db_fetchone(
        """
        SELECT id, username, role, email, is_active, created_at
        FROM users
        WHERE id = ?
        """,
        [user_id],
    )


async def find_by_username_with_hash(user_id: int) -> dict | None:
    """Variante de find_by_id que inclui password_hash — usado em change_password."""
    return await db_fetchone(
        "SELECT id, username, password_hash, is_active FROM users WHERE id = ?",
        [user_id],
    )


async def update_failed_logins(user_id: int, count: int, locked_until: datetime | None) -> None:
    await db_execute(
        "UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?",
        [count, locked_until, user_id],
    )


async def reset_failed_logins(user_id: int) -> None:
    await db_execute(
        "UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?",
        [user_id],
    )


async def touch_last_login(user_id: int) -> None:
    """Marca last_login_at = now() após login bem-sucedido."""
    await db_execute(
        "UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?",
        [user_id],
    )


async def update_password_hash(user_id: int, new_hash: str) -> None:
    await db_execute(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [new_hash, user_id],
    )


# ---------------------------------------------------------------------------
# CRUD (espelha métodos de src/Auth.php que vão pra FastAPI)
# ---------------------------------------------------------------------------


async def list_all() -> list[dict]:
    """Lista de users sem password_hash. Espelho de Auth::getAllUsers()."""
    return await db_fetchall(
        """
        SELECT id, username, email, role, is_active,
               failed_logins, locked_until, created_at, last_login_at
        FROM users
        ORDER BY id ASC
        """
    )


async def find_by_email(email: str) -> dict | None:
    return await db_fetchone(
        "SELECT id, username, is_active FROM users WHERE email = ?",
        [email],
    )


async def count_total() -> int:
    row = await db_fetchone("SELECT COUNT(*) AS n FROM users")
    return int(row["n"]) if row else 0


async def create(username: str, password_hash: str, role: str, email: str | None) -> int | None:
    """Cria usuário. Retorna o id gerado, ou None se username/email já existem."""
    existing = await db_fetchone("SELECT id FROM users WHERE username = ?", [username])
    if existing:
        return None
    if email:
        same_email = await db_fetchone("SELECT id FROM users WHERE email = ?", [email])
        if same_email:
            return None
    await db_execute(
        "INSERT INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)",
        [username, password_hash, role, email],
    )
    row = await db_fetchone("SELECT id FROM users WHERE username = ?", [username])
    return int(row["id"]) if row else None


async def update_email(user_id: int, new_email: str) -> bool:
    """
    Atualiza email se NÃO conflita com outro user. Retorna True se alterou,
    False se email já está em uso por outro user.
    """
    conflict = await db_fetchone(
        "SELECT id FROM users WHERE email = ? AND id != ?",
        [new_email, user_id],
    )
    if conflict:
        return False
    await db_execute(
        "UPDATE users SET email = ? WHERE id = ?",
        [new_email, user_id],
    )
    return True


async def toggle_active(user_id: int) -> bool:
    """Inverte is_active. Retorna True se row existe (alteração feita)."""
    row = await db_fetchone("SELECT id FROM users WHERE id = ?", [user_id])
    if not row:
        return False
    await db_execute("UPDATE users SET is_active = NOT is_active WHERE id = ?", [user_id])
    return True


async def update_role(user_id: int, role: str) -> bool:
    """Atualiza o role do user. Retorna True se row existe."""
    row = await db_fetchone("SELECT id FROM users WHERE id = ?", [user_id])
    if not row:
        return False
    await db_execute("UPDATE users SET role = ? WHERE id = ?", [role, user_id])
    return True


async def admin_set_password(user_id: int, new_hash: str) -> bool:
    """
    Reset de senha por admin — sem token, sem old_pass. Também limpa lockout
    pra liberar o user imediatamente.
    """
    row = await db_fetchone("SELECT id FROM users WHERE id = ?", [user_id])
    if not row:
        return False
    await db_execute(
        "UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?",
        [new_hash, user_id],
    )
    return True


async def delete_by_id(user_id: int) -> bool:
    row = await db_fetchone("SELECT id FROM users WHERE id = ?", [user_id])
    if not row:
        return False
    await db_execute("DELETE FROM users WHERE id = ?", [user_id])
    return True


# ---------------------------------------------------------------------------
# Password reset (token-based)
# ---------------------------------------------------------------------------


async def find_active_by_email(email: str) -> dict | None:
    return await db_fetchone(
        "SELECT id FROM users WHERE email = ? AND is_active = true",
        [email],
    )


async def set_reset_token(user_id: int, token_hash: str, expires_at: datetime) -> None:
    await db_execute(
        "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
        [token_hash, expires_at, user_id],
    )


async def find_by_reset_token(token_hash: str) -> dict | None:
    """Retorna user pra um token VÁLIDO (não expirado, ativo)."""
    return await db_fetchone(
        """
        SELECT id FROM users
        WHERE reset_token = ?
          AND reset_expires > NOW()
          AND is_active = true
        """,
        [token_hash],
    )


async def consume_reset_and_set_password(user_id: int, new_password_hash: str) -> None:
    """Limpa token + zera lockout + grava novo hash em uma operação."""
    await db_execute(
        """
        UPDATE users
        SET password_hash = ?,
            reset_token = NULL,
            reset_expires = NULL,
            failed_logins = 0,
            locked_until = NULL
        WHERE id = ?
        """,
        [new_password_hash, user_id],
    )
