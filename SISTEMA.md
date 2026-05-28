# Unbound Dashboard — Documentação do Sistema

Painel de administração web para o servidor DNS **Unbound**, com observabilidade em tempo real, blocklists multi-fonte, alertas, multi-tenant, cluster HA, e ferramentas completas de administração do SO subjacente.

**Stack atual (v2.103.x):** PHP (frontend SSR via Apache+PHP-FPM) + FastAPI/DuckDB/Redis (backend Python) + Unbound 1.17+. MariaDB foi removido em 2026-05-04 (v2.2.0). Apache faz reverse proxy de `/api/v1/*` pro FastAPI em `127.0.0.1:8001`.

Para o histórico de releases consulte o [CHANGELOG.md](CHANGELOG.md). Para instalação consulte o [MANUAL_INSTALACAO.md](MANUAL_INSTALACAO.md).

---

## Estrutura de Diretórios

```
.
├── api/                 # Endpoints PHP em transição → FastAPI (resíduos)
├── api_service/         # FastAPI app (foco da modernização v1)
│   ├── app/
│   │   ├── core/        # config, security (JWT/PKCE), deps, rate_limit, metrics, rbac
│   │   ├── routers/     # 37 routers — /api/v1/* (ver lista abaixo)
│   │   ├── services/    # 33+ services (lógica de domínio)
│   │   ├── repositories/duckdb/  # acesso DuckDB serializado por executor
│   │   ├── workers/     # 18 workers asyncio supervisionados (ver lista abaixo)
│   │   ├── infrastructure/  # redis_client, shell allowlisted, system_health, unbound
│   │   └── db/          # runner idempotente de migrations
│   ├── migrations/duckdb/   # V1..V29 — schema versionado
│   ├── deployments/         # systemd unit, Apache conf, drop-in unbound, env example
│   ├── tools/               # create_admin, migrate_mariadb_to_duckdb (one-time)
│   └── tests/               # ~190+ testes (pytest + pytest-asyncio)
├── docs/                # Documentação técnica (parcialmente legada — ver docs/README.md)
├── includes/            # Partials HTML: sidebar, topbar, head, footer, custom_modals
├── scripts/             # Scripts utilitários remanescentes (ex: update_blacklist.php)
├── src/                 # Classes PHP: Auth, ApiClient, I18n, configurações
├── tools/               # build-package, install, update, build-update, release.sh
├── data/                # Cache/snapshots de runtime (gitignored)
├── lang/                # Arquivos pt-BR e en (i18n PHP + window.t() JS)
└── *.php                # 43 páginas da interface (ver Frontend abaixo)
```

**Banco DuckDB**: arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb` (owner `www-data`).
**EnvironmentFile**: `/etc/unbound-dashboard/api-v1.env` (chmod 640, root:www-data) — `JWT_SECRET`, `DB_PATH`, `REDIS_URL`, **`SECRETS_MASTER_KEY`** (Fernet 32-byte), `GITHUB_TOKEN` (opcional).
**Backups**: `/var/backups/unbound-dashboard/` — gerados pelo `update.sh` antes de cada update.

---

## Backend FastAPI (`api_service/app/`)

### Routers (37) — `/api/v1/*`

| Domínio | Routers |
|---|---|
| Auth & Identidade | `auth`, `users`, `api_tokens`, `oidc`, `organizations`, `secrets` |
| DNS & Blocklists | `blocklist`, `policies` (split-horizon), `dns_security` (DNSSEC/QNAME min), `doh_inbound`, `geo_blocking`, `geoip` |
| Operação | `unbound`, `stats`, `threats`, `history`, `analytics`, `observability`, `health`, `host` |
| Alertas & Notificações | `alerts`, `notifications`, `webhooks`, `external_health`, `approvals` |
| Cluster & Multi-host | `cluster` (peer-ping autenticado), `ha`, `hosts` |
| Backup & Compliance | `backup_offsite`, `compliance`, `audit`, `updates`, `exports` |
| Misc | `grafana`, `rate_limits`, `ws_notifications`, `ws_queries` |

Swagger auto-gerado em [/api/v1/docs](http://localhost:8001/api/v1/docs) (também via Apache em prod).

### Services (33+) — lógica de domínio

`auth`, `audit`, `admin_audit`, `email_notifier`, `webhook_notifier`, `history`, `jwt_denylist`, `sessions`, `stats`, `threats`, `totp`, `unbound_stats`, `updater`, `users`, `oidc`, `cipher` (Fernet), **`secrets_migrator`** (one-shot na partida), `ha`, `managed_hosts`, `multi_host_sync`, `notification_prefs`, `organizations`, `pdf_report`, `query_broker`, `alerts_broker`, `geoip`, `geo_blocking`, `dns_security`, `doh_inbound`, `external_health`, `backup_offsite`, `backup_destinations`, `blocked_matcher`, `approval`, `api_tokens`.

### Workers (18) — asyncio supervisionados

Todos rodam dentro do uvicorn, com backoff exponencial (1s→60s) em crash. Listados em [api_service/app/main.py](api_service/app/main.py):

| Worker | Tick | Função |
|---|---|---|
| `LogWatcher` | 5s flush | Tail `/var/log/unbound/unbound.log` → batch insert em `query_logs` |
| `StatsAggregator` | 60s | UPSERT em `daily_stats`, `hourly_stats` |
| `UnboundCollector` | 60s | `unbound-control stats_noreset` → JSON cache + delta |
| `AlertChecker` | 60s | 8 categorias (no_queries, cpu, mem, swap, disk, network, ssh_failed, webserver) com dedupe + auto-resolve |
| `UpdateChecker` | 6h | Polla GitHub Releases, popula Redis cache; notifica email/webhook |
| `HostPoller` | configurável | Multi-host: agrega métricas de hosts gerenciados via api_tokens |
| `BlocklistSyncer` | horário | Re-baixa blocklists das fontes habilitadas (StevenBlack, Hagezi, OISD, AdGuard, NoCoin, EasyPrivacy, etc) |
| `AnomalyDetector` | configurável | Detecção de spikes em `query_logs` baseado em baseline aprendido |
| `BackupUploader` | diário | Multi-S3 (AWS/MinIO/Wasabi/R2/B2) com cache de tarball compartilhado |
| `QueryLogPruner` | diário | Retenção rolling de `query_logs` |
| `NotificationPruner` | diário | Retenção de notificações |
| `AuditPruner` | diário | Retenção de `admin_audit` |
| `PrometheusExporter` | 10s | Refresh de métricas custom (`unbound_*`, `worker_*`) em `/metrics` |
| `HAPeerMonitor` | 30s | Probe autenticado `/api/v1/cluster/peer-ping` em todos os peers HA |
| `ExternalHealthPruner` | diário | Retenção de `external_health_probes` |
| `RestoreTestRunner` | configurável | Testa restore de backup S3 em DuckDB temporário (smoke periódico) |
| `BaselineLearner` | configurável | Aprende baseline horário pra `AnomalyDetector` |
| `GeoBlockUpdater` | diário | Atualiza CIDRs de países bloqueados em `geo_blocks` |
| `DigestSender` | horário | Digest diário de notificações por email (HTML multipart com badges) |

Endpoint enumerável em [/api/v1/observability/workers](http://localhost:8001/api/v1/observability/workers).

### Migrations DuckDB (V1..V29)

29 migrations idempotentes em [api_service/migrations/duckdb/](api_service/migrations/duckdb/) — desde o schema inicial (V1) até multi-tenant em blocklist_exceptions (V29). Runner aplica em ordem e grava sha256 em `schema_migrations`.

---

## Frontend (43 páginas)

Páginas SSR PHP + Tailwind + Vanilla JS. JWT injetado via `<meta name="api-jwt">` pra chamadas AJAX ao FastAPI. Todas suportam tema claro/escuro + i18n (pt-BR/en via `t()` server-side e `window.t()` client-side).

### Operação (visão geral, NOC)

- `index.php` — dashboard principal com widgets: Alertas Ativos, Saúde de Infra, Workers, Live stream mini, Top países 24h, Multi-host overview, Top 5 + Recent (tabbed)
- `logs.php` — live sniffer (WS `/api/v1/ws/queries`)
- `history.php` — histórico DNS com filtros
- `live_stream.php` — feed contínuo de queries via WebSocket
- `threats.php`, `blocklist.php` (Judicial ANATEL), `blocklists.php` (multi-source não-judicial + allowlist)
- `diagnostics.php`, `dns_benchmark.php` (3 rounds, 8 resolvers)
- `health.php` (DuckDB/Redis/Apache/Unbound status + auto-reparo)

### Configuração & gestão

- `config.php` — configuração principal do Unbound (16 abas: forward zones, DoH/DoT, DNSSEC, performance, etc.)
- `client_policies.php` — split-horizon DNS por CIDR/IP via `access-control-view` + views
- `dns_security.php` — DNSSEC + QNAME minimization + harden options
- `doh_inbound.php` — TLS inbound + cert management
- `geo_blocking.php` — bloqueio por país via CIDRs MaxMind/geofeeds
- `external_health.php` — probes externos (CDN, DoH público, etc.)

### Multi-tenant & cluster

- `orgs.php` — gestão de organizações (multi-tenant)
- `hosts.php` — multi-host managed (poller + drill-down)
- `cluster.php` — peers HA com healthcheck autenticado (`/api/v1/cluster/peer-ping`) — ver [docs/pages/cluster.md](docs/pages/cluster.md)

### Segurança & auditoria

- `users.php`, `sessions.php`, `sso.php` (OIDC com PKCE + group mapping)
- `audit.php` (admin audit log), `approvals.php` (workflows de aprovação)
- `secrets.php` — gestão de SECRETS_MASTER_KEY + status do cipher_service
- `webhooks.php`, `smtp.php`

### Backup & manutenção

- `backups.php` — destinos S3 múltiplos + restore + smoke
- `updates.php` — self-update via UI (botão + histórico + auditoria)
- `notifications.php` — preferências de digest + retenção

### Misc

- `compliance.php`, `performance.php`, `observability.php`, `recover.php`, `reset.php`, `login.php`, `profile.php`, `changelog.php`, `release.php`

---

## RBAC

Centralizado em [api_service/app/core/rbac.py](api_service/app/core/rbac.py) (Python) com espelho em [src/Auth.php::can()](src/Auth.php).

### Papéis (4)

| Papel | Descrição |
|---|---|
| **admin** | Acesso total |
| **readonly_admin** | Vê tudo (inclui SMTP/webhooks/users) mas não modifica |
| **operator** | NOC: resolve alertas, modifica blocklist, vê threats |
| **viewer** | Read-only básico |

Custom roles podem ser criadas em runtime (v2.15+) — combinações arbitrárias de capabilities.

### Capabilities (12+)

`config.write`, `config.read_sensitive`, `users.manage`, `users.read`, `webhooks.manage`, `smtp.manage`, `alerts.resolve`, `alerts.read`, `blocklist.write`, `blocklist.read`, `dashboard.read`. Ver `_DEFAULT_CAPS` em [rbac.py](api_service/app/core/rbac.py) pro mapping atual.

### Multi-tenant

Tabelas com `org_id`: `users`, `managed_hosts`, `alerts`, `admin_audit`, `client_policies`, `blocklist_exceptions` (V29: PK composto `(domain, org_id)`, sentinela `0 = global`). Filtro padrão: viewer global vê tudo; viewer org-scoped vê globais + da própria org. Helper `resolve_viewer_org_id(payload)` em [core/deps.py](api_service/app/core/deps.py).

---

## Segurança

### Autenticação

- **JWT HS256** com 60min expiração + sliding refresh
- **Denylist Redis** pra revogar tokens antes da expiração
- **Lockout** 5 falhas → 15min de bloqueio
- **Rate limit** via slowapi (10/min em `/login`, 200/min default)
- **Timing-safe** bcrypt dummy quando user não existe
- **2FA TOTP** opt-in por user; admin pode resetar
- **OIDC SSO** com **PKCE S256** (RFC 7636) + group mapping com role rank (admin > readonly_admin > operator > viewer)

### Secrets em DB

- **`SECRETS_MASTER_KEY`** (Fernet 32-byte em env) cifra `oidc_config.client_secret`, `ha_peers.api_token_raw_encrypted`, `backup_destinations.secret_key`
- Sem master key: fallback plaintext com warning no startup (NÃO recomendado em prod)
- `secrets_migrator` cifra automaticamente secrets pré-existentes na primeira partida após master key ser configurada — idempotente

### CSRF + sudoers

- CSRF token dinâmico por sessão (`Auth::csrfToken()`) validado em todo POST
- `/etc/sudoers.d/unbound-dashboard` com comandos exatos (`tail -n 300`, `journalctl -n 300 --no-pager`, etc) — qualquer variação é rejeitada silenciosamente
- Shell allowlist em `infrastructure/shell.py` no api_service (só `/usr/sbin/unbound-control`, `/usr/bin/systemctl`, `/usr/bin/journalctl`)

### Cluster HA (shared-secret-per-link)

Cada par A↔B compartilha um token raw. Ambos os lados guardam: raw cifrado (pra mandar como `X-Api-Token`) + bcrypt hash (pra verificar). Endpoint `/api/v1/cluster/peer-ping` valida bcrypt contra todos hashes locais. Ver [docs/pages/cluster.md](docs/pages/cluster.md) pro setup em 3 passos.

---

## Observabilidade

- **Prometheus** em `/metrics` (sem auth) — métricas custom: `unbound_queries_ingested_total{action}`, `unbound_worker_queue_size`, `unbound_worker_errors_total{worker}` + métricas FastAPI default
- **Grafana** dashboards versionados em [docs/grafana/](docs/grafana/)
- **Structlog JSON** em todos os workers e services
- **`X-Request-ID`** injetado em todas requisições FastAPI
- **Audit log persistente** em `admin_audit` (DuckDB) — todas as ações críticas registradas com snapshot do org

---

## Cálculo de Métricas

### Latência Efetiva (Ponderada)

```
latência_efetiva = latência_recursão × taxa_de_miss
```

Card no dashboard exibe: valor principal (efetiva), subtítulo 1 (recursão bruta em âmbar), subtítulo 2 (mediana em verde).

### Live Sniffer

Hoje via WebSocket `/api/v1/ws/queries` (broker em memória). Endpoint `api/live_log.php` legado mantido como fallback histórico.

---

## Histórico de Marcos

| Marco | Versão | Data |
|---|---|---|
| Modernização v1 in-place (Strangler Fig: FastAPI/DuckDB/Redis em paralelo) | v2.1.0 | 2026-04-29 |
| Tear-down do MariaDB (sistema 100% DuckDB) | v2.2.0 | 2026-05-04 |
| Multi-host gerenciado | v2.21-v2.22 | 2026-05-09 |
| Self-update via UI | v2.17.0 | 2026-05-15 |
| Multi-source blocklist + 10 presets curados | v2.32.0 | 2026-05-25 |
| Client policies split-horizon | v2.33.0 | 2026-05-25 |
| RBAC + custom roles + 2FA TOTP | v2.16.3 | 2026-05-13 |
| SECRETS_MASTER_KEY + PKCE + backup multi-S3 cache | v2.101.0 | 2026-05-28 |
| Multi-tenant (orgs) — hosts/alerts/audit/policies/blocklist | v2.92-v2.102 | 2026-05-28 |
| Cluster HA bidirecional autenticado | v2.103.0+ | 2026-05-28 |

Detalhes em [CHANGELOG.md](CHANGELOG.md).

---

## Para entender o código

- **Backend Python**: comece por [api_service/app/main.py](api_service/app/main.py) (lifespan + workers + include_routers).
- **Frontend PHP**: comece por [includes/head.php](includes/head.php) (theme, i18n, JWT meta) e [includes/sidebar.php](includes/sidebar.php) (mapa do menu).
- **DB**: [api_service/migrations/duckdb/V*.sql](api_service/migrations/duckdb/) em ordem cronológica conta a história.
- **Operação**: [MANUAL_INSTALACAO.md](MANUAL_INSTALACAO.md) + [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md).
