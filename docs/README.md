# Documentação do Unbound Dashboard

> **Status:** parcialmente desatualizada. Esta pasta foi escrita no início do projeto (pré-v2.2) e várias seções descrevem código que foi removido na modernização MariaDB → DuckDB. As entradas legadas estão marcadas com `> ⚠️ DEPRECATED` no topo do arquivo.
>
> Para a **arquitetura atual** consulte sempre:
> - [`SISTEMA.md`](../SISTEMA.md) — visão completa do sistema
> - [`MANUAL_INSTALACAO.md`](../MANUAL_INSTALACAO.md) — instalação e troubleshooting
> - [`CHANGELOG.md`](../CHANGELOG.md) — histórico de releases

---

## Conteúdo

### Atual & vivo

| Arquivo | Descrição |
|---|---|
| [PLANO_MODERNIZACAO_V1.md](PLANO_MODERNIZACAO_V1.md) | **Canônico.** Plano da migração MariaDB → DuckDB (concluído v2.2.0). |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Problemas comuns e soluções. |
| [pages/cluster.md](pages/cluster.md) | Setup do cluster HA (shared-secret-per-link, botão 🔑, debugging). |
| [grafana/](grafana/) | Dashboards Grafana versionados. |

### Legado (marcado como DEPRECATED)

| Pasta | O que tem | Por que está estale |
|---|---|---|
| [components/](components/) | 16 classes PHP (BlocklistManager, AlertManager, Database, …) | Várias removidas em v2.2.0 quando MariaDB caiu. Lógica migrou pra `api_service/app/services/` e `app/workers/`. |
| [api/](api/) | 7 endpoints PHP (`stats.php`, `export.php`, `service_control.php`, …) | Todos migrados pra FastAPI em `/api/v1/*` desde v2.1.0. Endpoints PHP residuais são fallbacks. |
| [pages/](pages/) (parcial) | 13 páginas básicas (alerts, blocklist, history, etc) | Capturam só o estado pré-v2.32. **29 páginas novas sem doc** (cluster, sso, hosts, orgs, notifications, observability, policies, dns_security, doh_inbound, geo_blocking, performance, sessions, webhooks, …). |
| [REFACTORING_PLAN_V2.md](REFACTORING_PLAN_V2.md) | Plano da v2 "do zero" em `/opt/` | v2 está pausada. Doc mantida por histórico. |

### Auto-gerada

- **Swagger interativo** da API FastAPI: [`/api/v1/docs`](http://localhost:8001/api/v1/docs) (no servidor instalado) — 100+ endpoints atuais documentados automaticamente
- **OpenAPI JSON**: `/api/v1/openapi.json`

---

## Lista de TODOs de documentação

Áreas que merecem doc dedicada (PRs bem-vindos):

### Páginas sem doc

- `cluster.php` ✅ (feito em [pages/cluster.md](pages/cluster.md))
- `client_policies.php` — split-horizon DNS
- `dns_security.php` — DNSSEC + QNAME minimization
- `doh_inbound.php` — TLS inbound do Unbound
- `geo_blocking.php` — bloqueio por país
- `external_health.php` — probes externos
- `notifications.php` — preferências de digest
- `observability.php` — workers + Prometheus + Grafana
- `orgs.php` — multi-tenant
- `hosts.php` — multi-host gerenciado
- `sso.php` — OIDC com PKCE + group mapping
- `audit.php` — admin audit log
- `approvals.php` — workflow de aprovação
- `secrets.php` — gestão de SECRETS_MASTER_KEY
- `backups.php` — destinos S3 múltiplos + restore
- `updates.php` — self-update + histórico
- `webhooks.php`, `sessions.php`, `performance.php`, `recover.php`, `reset.php`, `compliance.php`

### Features transversais

- **Multi-tenant** — modelo, helper `resolve_viewer_org_id`, padrão `NULL = global` (exceto `blocklist_exceptions` que usa `org_id = 0`)
- **OIDC** — claim parsing (dot-path), group mapping com role rank, PKCE
- **`SECRETS_MASTER_KEY`** — formato Fernet, fallback plaintext, `secrets_migrator`
- **Workers asyncio** — supervisor, backoff, lifecycle, métricas
- **Backup multi-S3** — destinations service, cache de tarball, restore test runner
- **i18n** — `t()` PHP vs `window.t()` JS, namespace `js.*` pra toasts cross-cutting

---

## Como usar esta pasta no estado atual

1. Para arquitetura/setup: use os arquivos no diretório raiz ([SISTEMA.md](../SISTEMA.md), [MANUAL_INSTALACAO.md](../MANUAL_INSTALACAO.md)).
2. Para endpoints específicos: consulte o Swagger.
3. Para entender o histórico de evolução: [CHANGELOG.md](../CHANGELOG.md) (agrupado por dia desde 2026-05-26).
4. Os arquivos `> ⚠️ DEPRECATED` podem ainda ter valor histórico (entender de onde veio um padrão), mas não confie nos detalhes específicos de implementação.
