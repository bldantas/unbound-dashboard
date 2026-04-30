# Changelog

## v2.1.1 — 2026-04-30

### Correções
- Documentação atualizada para separar explicitamente as linhas v1 (PHP/MariaDB) e v2 (Python/FastAPI/DuckDB), reduzindo confusão operacional entre scripts.

### Novidades
- Novo script `tools/build-package-v2.sh` (wrapper) para gerar o pacote da v2 a partir deste repositório legado, delegando para `/opt/unbound-dashboard/tools/build-package.sh`.

---

## v2.1.0 — 2026-04-29

### Correções
- Export `apiClient` corrigido em `api/client.ts` (4 views afetadas)
- Campo `Alert.type` removido; timestamp Unix corrigido
- Tipos do WebSocket live-log normalizados para `QueryLogEntry`

### Novidades
- BalanceView — gerenciamento visual de upstreams DNS com health check e latência
- ToastContainer — notificações globais para erros 429/5xx
- ChangelogView — esta página no frontend Vue
- Módulos de API dedicados: `balance.ts`, `health.ts`, `unbound.ts`, `diagnostics.ts`

### Melhorias
- Dashboard com auto-refresh a cada 30s e barra de progresso
- Sidebar com badge de alertas não-lidos (polling 60s)
- `api/client.ts`: timeout 30s, header `X-Request-ID` em cada request
- `tsconfig.app.json`: alias `@/` habilitado para vue-tsc

---

## v2.0.0 — 2026-04-29

### Backend
- Migração PHP → Python/FastAPI + DuckDB
- Workers assíncronos: LogWatcher, StatsAggregator, AlertChecker
- Rate limiting (slowapi), middlewares CORS e Request-ID
- Prometheus metrics em `/metrics`
- GitHub Actions CI/CD com cobertura de testes

### Frontend
- Vue 3 + TypeScript + Vite + Tailwind CSS
- Pinia stores: auth, alerts, ui
- WebSocket live-log via VueUse
- Chart.js para histórico de consultas
- RBAC: rotas admin protegidas

---

## v1.0.3 — Sistema legado PHP

- Backup disponível em `/var/backups/unbound-dashboard-v1-20260429_163848.tar.gz`
- Pré-requisitos instalados: Python 3.13.5, uv 0.11.8, Node 20 LTS, Redis 8 (ativo)
- Estrutura de diretórios criada: `app/{core,domain,models,repositories,services,routers,workers,infrastructure,middleware}`
- `pyproject.toml` com todas as dependências (fastapi, uvicorn, duckdb>=1.2, redis, bcrypt, python-jose, psutil, slowapi, structlog, prometheus-fastapi-instrumentator)
- `app/core/config.py` — pydantic-settings com validação de todas as variáveis de ambiente
- `app/core/security.py` — bcrypt direto (sem passlib; compatível com Python 3.13), JWT via python-jose
- `app/core/deps.py` — dependências FastAPI (require_auth, require_admin)
- `app/repositories/duckdb/connection.py` — ThreadPoolExecutor(max_workers=1) para writes serializados; leituras em threads separadas
- `migrations/duckdb/V1__initial_schema.sql` — schema completo: users, settings, alerts, query_logs, daily_stats, blocklist_hits
- `migrations/duckdb/V2__add_refresh_token.sql` — suporte a refresh token JWT
- `app/db/__init__.py` — runner de migrations com tabela de controle de versão
- `app/domain/user.py` — entidade User, Role, erros de domínio (InvalidCredentials, AccountLocked, AccountInactive)
- `app/repositories/duckdb/user_repo.py` — CRUD de usuários com DuckDB
- `app/services/auth_service.py` — login timing-safe, lockout 5 falhas/15min
- `app/routers/auth.py` — POST /api/v2/auth/login (JWT response)
- `app/repositories/redis/connection.py` — cliente aioredis singleton
- `app/main.py` — FastAPI app com lifespan (auto-migrations no startup), CORS, /healthz
- `Makefile` — targets: install, dev, test, lint, typecheck, format, migrate-up
- **Testes: 8/8 passando** — 5 unitários (AuthService) + 3 integração (UserRepository com DuckDB temporário)
- Fix: bcrypt 4.x incompatível com passlib — substituído por `bcrypt` direto
- Fix: DuckDB 1.x não permite misturar read_only e read-write no mesmo arquivo — removido `read_only=True`
- Fix: comparações de TIMESTAMP — normalização para naive UTC (DuckDB TIMESTAMP é sempre naive)
- API verificada: `GET /healthz` → `{"status":"ok","version":"2.0.0"}`

## docs - 2026-04-29
- Planejamento v2: `docs/REFACTORING_PLAN_V2.md` atualizado para adotar **DuckDB-only** — MariaDB removido completamente da stack v2.
- Decisão arquitetural: todas as tabelas (users, settings, alerts, query_logs, daily_stats) consolidadas em um único arquivo DuckDB; escritas OLTP serializadas via `ThreadPoolExecutor(max_workers=1)`.
- Schema DuckDB completo documentado em `§6.3` (V1__initial_schema.sql com todas as tabelas).
- Script de migração `tools/migrate_from_mariadb.py` documentado com validação de contagem pós-migração.
- Dependências atualizadas: removidos `aiomysql` e `SQLAlchemy`; `duckdb>=1.0` cobre todos os casos de uso.
- CI/CD atualizado: serviço MariaDB removido do workflow GitHub Actions; `testcontainers` simplificado para Redis apenas.
- Ferramentas: Alembic substituído por scripts SQL versionados DuckDB (`migrations/duckdb/V*.sql`).

## v1.0.3 - 2026-04-28
- Performance: criado índice composto `idx_action_ts (action, timestamp)` em `query_logs` — elimina full scan de 16M linhas nas queries `WHERE action='blocked'`.
- Performance: adicionada coluna `blocked_count` em `daily_stats` e backfill histórico de 31 dias de dados pré-agregados.
- Performance: `api/threats_data.php` passa a usar `daily_stats` para totais (consulta em 31 linhas em vez de 16M), com fallback automático.
- Performance: `log_ingester.php` atualiza `daily_stats` a cada inserção, mantendo os totais sempre atualizados.
- Performance: `getTopDomains()` em `UnboundManager` limita consulta às últimas 24h para evitar GROUP BY em tabela inteira.
- Update: criado `scripts/migrate_db.sql` com migrações idempotentes — executado automaticamente pelo `update.sh` em cada atualização.
- Schema: `scripts/init_db.sql` atualizado com índice composto e coluna `blocked_count`.

## v1.0.2 - 2026-04-28
- DB: removido índice duplicado `idx_query_logs_domain` em `query_logs` (duplicata de `idx_domain`).
- DB: adicionados índices `idx_alerts_resolved_at` e `idx_alerts_started_at` em `alerts` para otimizar consultas por status e ordenação por data.
- Schema: `scripts/init_db.sql` atualizado com os índices corretos para novas instalações.

## v1.0.1 - 2026-04-23
- Performance: carregamento progressivo aplicado em History e Threats com flush inicial e overlay de loading.
- Threats: adicionado seletor de exibicao de linhas (10, 20, 50, 100, todos) com default em 10.
- Performance: carregamento progressivo tambem aplicado em Logs e Alerts.
- UX: corrigido hide do loader global ao finalizar render para evitar overlay preso.
- Build update: hardening para nao incluir credenciais (src/Database.php) e excluir JSONs volateis de src/data.
- Update script: sincronizacao de src preservando src/data local do servidor.
- Versionamento: build de update agora le VERSION e faz bump automatico por padrao (patch).

## v1.0.0 - 2026-04-23
- Primeira versao estavel do Unbound Dashboard.
- Monitoramento em tempo real de metricas e historico DNS.
- Modulos de seguranca, logs, alertas e diagnostico.
- Ferramentas de exportacao, benchmark e gerenciamento operacional.