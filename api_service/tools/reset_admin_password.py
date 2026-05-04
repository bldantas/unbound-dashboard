"""
Reseta a senha de um usuário diretamente no DuckDB.

Diferente de `create_admin.py`, este script ALTERA a senha de um usuário
existente — útil quando o admin perde o acesso e o servidor SMTP de
recuperação não está configurado.

Uso:
    .venv/bin/python tools/reset_admin_password.py \
        --username admin --password 'novaSenha'

Ou via env:
    USERNAME=admin NEW_PASSWORD='novaSenha' \
        .venv/bin/python tools/reset_admin_password.py

Variáveis de ambiente do api_service (JWT_SECRET, DB_PATH, etc) devem estar
carregadas — geralmente via `set -a; source /etc/unbound-dashboard/api-v1.env`.
"""

from __future__ import annotations

import argparse
import asyncio
import os
import sys

from app.core.security import hash_password
from app.repositories.duckdb import user_repo


async def _reset(username: str, new_password: str) -> int:
    user = await user_repo.find_by_username(username)
    if user is None:
        print(f"[✗] Usuário '{username}' não encontrado.", file=sys.stderr)
        return 1

    await user_repo.update_password_hash(int(user["id"]), hash_password(new_password))
    print(f"[✓] Senha de '{username}' (id={user['id']}, role={user['role']}) atualizada.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Reset de senha de um usuário existente.")
    parser.add_argument("--username", default=os.environ.get("USERNAME"))
    parser.add_argument("--password", default=os.environ.get("NEW_PASSWORD"))
    args = parser.parse_args()

    if not args.username:
        print("[✗] --username (ou USERNAME) é obrigatório.", file=sys.stderr)
        return 2
    if not args.password:
        print("[✗] --password (ou NEW_PASSWORD) é obrigatório.", file=sys.stderr)
        return 2
    if len(args.password) < 6:
        print("[✗] Senha precisa ter ao menos 6 caracteres.", file=sys.stderr)
        return 2

    try:
        return asyncio.run(_reset(args.username, args.password))
    except Exception as exc:
        print(f"[✗] Falha ao resetar senha: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
