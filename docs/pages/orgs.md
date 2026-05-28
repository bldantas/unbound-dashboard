# `/orgs.php` — Organizations (multi-tenant)

CRUD de organizações + atribuição de usuários. Esse é o ponto de partida do modelo multi-tenant — quase todo o resto (hosts, alerts, audit, policies, blocklist exceptions) ganha filtro por `org_id` quando há orgs cadastradas.

**Disponível desde:** v2.80 (CRUD básico); v2.89 (hosts); v2.92 (alerts + admin_audit); v2.98 (client_policies); v2.102 (blocklist_exceptions com PK composto); v2.105 (split-horizon de blocklist por view).

---

## Modelo conceitual

| Conceito | Como funciona |
|---|---|
| **Org** | Entidade lógica (`organizations` table). Tem `name`, `slug` (immutable após criar, vira identificador URL-safe), `description`, `is_active`. |
| **User com `org_id = NULL`** | "System admin". Vê tudo (globais + de todas as orgs). |
| **User com `org_id = N`** | "Org-scoped". Vê dados globais + da própria org (N). |
| **Recurso com `org_id = NULL`** | Recurso "global". Visível pra todo mundo. |
| **Recurso com `org_id = N`** | Recurso da org N. Visível só pra org N + system admins. |

Helper `resolve_viewer_org_id(payload)` em [core/deps.py](../../api_service/app/core/deps.py) faz a resolução por request — query no DB por causa da `users.org_id`. Custo: 1 query extra por endpoint multi-tenant.

---

## Tabelas com `org_id` (estado v2.105)

- `users.org_id` (V23)
- `managed_hosts.org_id` (V25, v2.89)
- `alerts.org_id` (V27, v2.92)
- `admin_audit.actor_org_id` (V27, v2.92 — snapshot no momento do log)
- `client_policies.org_id` (V28, v2.98)
- `blocklist_exceptions(domain, org_id)` (V29, v2.102 — **PK composto**, sentinela `0 = global` em vez de NULL)

---

## Setup em 3 passos

1. `/orgs.php` → **Adicionar** → nome + slug (sem espaços, lowercase) + description.
2. Em `/users.php`, edite os users dessa org e atribua `org_id` via dropdown.
3. **Quando esses users logam, todos os listings já filtram automaticamente.** Não precisa configurar mais nada.

Pra criar recursos na org (ex: peer HA, policy DNS, exceção de blocklist), system admin pode escolher `Org: ...` no form. Org-scoped users sempre criam pra própria org (a UI força ou o backend retorna 403).

---

## Multi-tenant em blocklist — split-horizon (v2.105)

Diferente dos outros recursos que só **filtram listagem**, exceções de blocklist da org **alteram resolução real do DNS**:

1. Crie uma `client_policy` na org (ex: "marketing-team" com CIDR `192.168.5.0/24`)
2. Em `/blocklists.php` → aba Exceções → adicione `googleads.com` com **Org: Marketing**
3. Clica **Aplicar & Recarregar Unbound** — regenera `views.conf` + reload
4. Cliente em `192.168.5.42` faz `dig googleads.com` → resolve normalmente
5. Cliente em `10.0.0.5` (fora da view) → continua bloqueado pela blocklist global

Detalhe técnico: a `client_policies_repo.list_all_full_enabled()` retorna o campo `org_exceptions` (dedupado contra `allows`) que o `UnboundConfigManager::generateViewsConf()` emite como `local-zone "X." transparent` no view block.

---

## Limitações conhecidas (estado v2.105)

- **Usuários sem org** seguem sendo "system admins". Não há jeito de criar admin "só da org X" — RBAC per-org ainda não existe.
- **Blocklist global ainda gerada centralmente** — só as exceções escapam pra views. Pra cada org ter blocklist **realmente diferente** (com fontes próprias), seria preciso outro nível de schema.
- **Org-scoped users não podem criar orgs** — só system admin.

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/organizations/` | Lista (todos) |
| `POST /api/v1/organizations` | Cria (admin) |
| `PUT /api/v1/organizations/{id}` | Atualiza |
| `DELETE /api/v1/organizations/{id}` | Remove (cuidado: users com `org_id` apontando pra essa ficam órfãos — precisa reassign antes) |

---

## Memórias relacionadas

- [feature_cluster_v2_103.md](../../.claude/projects/-var-www-html-unbound-dashboard/memory/feature_cluster_v2_103.md) — peers HA também ganharam `org_id`
- [sessao_2026-05-28_gap.md](../../.claude/projects/-var-www-html-unbound-dashboard/memory/sessao_2026-05-28_gap.md) — histórico das v2.92→v2.102 que rolaram multi-tenant
