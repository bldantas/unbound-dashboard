"""
Cria o primeiro usuário admin no DuckDB.

Usado pelo install.sh durante o setup inicial. Aceita argumentos via flags
ou variáveis de ambiente (ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_PASSWORD).

Idempotência: se o usuário com `--username` já existir, sai com código 0
sem criar nada.

Uso:
    .venv/bin/python tools/create_admin.py \
        --username admin --email admin@x.com --password 'changeme'

Ou via env:
    ADMIN_USERNAME=admin ADMIN_PASSWORD=... ADMIN_EMAIL=... \
        .venv/bin/python tools/create_admin.py
"""

from __future__ import annotations

import argparse
import asyncio
import os
import re
import sys

from app.repositories.duckdb import user_repo

_USERNAME_RE = re.compile(r"^[a-zA-Z0-9._-]+$")
from app.repositories.duckdb.connection import db_fetchone
from app.services import users_service


async def _admin_already_exists(username: str) -> bool:
    row = await db_fetchone("SELECT id FROM users WHERE username = ?", [username])
    return row is not None


async def _bootstrap_admin(username: str, email: str | None, password: str) -> int:
    if await _admin_already_exists(username):
        print(f"[i] Usuário '{username}' já existe — nenhum admin criado.")
        existing = await user_repo.find_by_username(username)
        return int(existing["id"]) if existing else -1

    new_id = await users_service.create(
        username=username,
        password=password,
        role="admin",
        email=email,
    )
    print(f"[✓] Admin '{username}' criado (id={new_id}, role=admin)")
    return new_id


def main() -> int:
    parser = argparse.ArgumentParser(description="Bootstrap do admin inicial.")
    parser.add_argument("--username", default=os.environ.get("ADMIN_USERNAME"))
    parser.add_argument("--email", default=os.environ.get("ADMIN_EMAIL"))
    parser.add_argument("--password", default=os.environ.get("ADMIN_PASSWORD"))
    args = parser.parse_args()

    if not args.username:
        print("[✗] --username (ou ADMIN_USERNAME) é obrigatório.", file=sys.stderr)
        return 2
    if not _USERNAME_RE.match(args.username):
        print(
            f"[✗] Username inválido: '{args.username}'. Use apenas letras, números, _ . -",
            file=sys.stderr,
        )
        return 2
    if not args.password:
        print("[✗] --password (ou ADMIN_PASSWORD) é obrigatório.", file=sys.stderr)
        return 2
    if len(args.password) < 6:
        print("[✗] Senha precisa ter ao menos 6 caracteres.", file=sys.stderr)
        return 2

    try:
        asyncio.run(_bootstrap_admin(args.username, args.email, args.password))
    except Exception as exc:
        print(f"[✗] Falha ao criar admin: {exc}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
