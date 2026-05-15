"""
RBAC granular — mapeia roles a capabilities.

Roles disponíveis:
  - admin           : acesso total (writes + reads sensíveis + gestão de users)
  - readonly_admin  : lê tudo (inclui SMTP/webhooks/users) mas não modifica
  - operator        : NOC/suporte — resolve alertas, modifica blocklist,
                      lê alertas/threats. NÃO vê SMTP/webhooks/users.
  - viewer          : read-only básico (dashboards, history, threats)

Em vez de checar `role == "admin"` espalhado no código, endpoints usam
`require_capability("foo.bar")`. Pra um novo recurso, basta adicionar
a capability ao dict + decorar o endpoint.

Convenção de nomes: `<recurso>.<acao>`.
"""

from __future__ import annotations

VALID_ROLES = frozenset({"admin", "readonly_admin", "operator", "viewer"})

# Cada capability mapeia para o conjunto de roles autorizados.
# Mudar uma capability afeta TODOS os endpoints que a usam.
CAPABILITIES: dict[str, frozenset[str]] = {
    # Writes/admin
    "config.write":         frozenset({"admin"}),
    "users.manage":         frozenset({"admin"}),
    "webhooks.manage":      frozenset({"admin"}),
    "smtp.manage":          frozenset({"admin"}),
    "tokens.manage":        frozenset({"admin"}),  # API tokens p/ master multi-host

    # Operações de NOC — operator + admin
    "alerts.resolve":       frozenset({"admin", "operator"}),
    "blocklist.write":      frozenset({"admin", "operator"}),

    # Leituras administrativas (não-sensíveis) — operator + readonly_admin + admin
    "alerts.read":          frozenset({"admin", "readonly_admin", "operator"}),
    "blocklist.read":       frozenset({"admin", "readonly_admin", "operator"}),

    # Leituras sensíveis (SMTP, webhooks, users) — readonly_admin + admin
    "users.read":           frozenset({"admin", "readonly_admin"}),
    "config.read_sensitive": frozenset({"admin", "readonly_admin"}),

    # Reads gerais (qualquer user autenticado)
    "dashboard.read":       frozenset({"admin", "readonly_admin", "operator", "viewer"}),
}


def can(role: str | None, capability: str) -> bool:
    """True se `role` tem `capability`. Capability inexistente → False (deny by default)."""
    if not role:
        return False
    allowed = CAPABILITIES.get(capability)
    if allowed is None:
        return False
    return role in allowed
