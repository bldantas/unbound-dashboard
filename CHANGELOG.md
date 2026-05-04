# Changelog

## v2.2.5 — 2026-05-04

### Bugfix do install.sh — ownership do DuckDB

**Problema:** Em servidores que tinham instalação anterior com user
diferente (ex: `unbound-dash:unbound-dash` de uma versão experimental
v2.1.x antiga), o arquivo `/var/lib/unbound-dashboard/unbound_dash.duckdb`
ficava com ownership errado. O api_service rodando como `www-data` recebia
`Permission denied` ao tentar abrir o arquivo.

**Fix:** install.sh agora faz `chown -R www-data:www-data` no
`/var/lib/unbound-dashboard/` (não só no diretório raiz) e força
`chmod 640` em todos os arquivos. Idempotente — sem efeito em instalações
limpas.

### Hotfix em servidor já instalado:
```bash
sudo chown -R www-data:www-data /var/lib/unbound-dashboard/
sudo chmod 750 /var/lib/unbound-dashboard
sudo find /var/lib/unbound-dashboard -type f -exec chmod 640 {} \;
```

---

## v2.2.4 — 2026-05-04

### Bugfix do install.sh — Etapa 8 (admin inicial)

**Problema 1: lock do DuckDB.** A Etapa 7 sobe o `unbound-dashboard-api`,
que abre o arquivo `.duckdb` com lock exclusivo. A Etapa 8 tentava rodar
`create_admin.py` (também escritor), e o DuckDB falhava com
`IO Error: Cannot open file ... Permission denied` (lock conflict).

**Fix:** install.sh agora **para** o `unbound-dashboard-api` antes do
`create_admin.py` e religa depois (com smoke `/api/v1/healthz`). Em caso de
falha do create_admin, o serviço é religado mesmo assim para não deixar o
sistema offline.

**Problema 2: usernames com espaço/caracteres especiais** eram aceitos pelo
prompt mas quebravam ou se tornavam difíceis de logar depois.

**Fix:** install.sh e `create_admin.py` agora validam username com regex
`^[a-zA-Z0-9._-]+$`. Username inválido no prompt re-pergunta; via env var
aborta o install com mensagem clara.

---

## v2.2.3 — 2026-05-04

### Bugfix
- **`pandas` movido para `dependencies` no `pyproject.toml`** (estava em
  `dependency-groups dev`). Como `install.sh` roda `uv sync --no-dev`,
  o pandas não ia pra produção e o startup do `api_service` quebrava com
  `ModuleNotFoundError: No module named 'pandas'` ao importar
  `app.repositories.duckdb.connection` (que usa pandas em `db_append`).
- `uv.lock` regenerado.

### Hotfix em servidores já instalados (sem refazer install)
```bash
cd /var/www/html/unbound-dashboard/api_service
sudo /usr/local/bin/uv pip install --python .venv/bin/python "pandas>=2.0"
sudo systemctl restart unbound-dashboard-api
```

---

## v2.2.2 — 2026-05-04

### UI / Backend
- Widget "Banco de Dados" em `alerts.php` substituído pelo card **API + DuckDB**:
  mostra status do `unbound-dashboard-api.service`, smoke `/api/v1/healthz`,
  tamanho do arquivo DuckDB, status do `redis-server` e do webserver.
- `AppMetricsManager` ganhou métodos `getApiServiceStatus()`,
  `getDuckDBStatus()` e `getRedisStatus()`.
- `api/alerts_metrics.php` retorna novas chaves `api`, `duckdb`, `redis`
  (mantém `db` como stub fixo offline pra compat).

### Limpeza de legado MariaDB
- Removidos `scripts/{init_db.sql, migrate_db.sql, setup_database.sql,
  log_ingester.php, aggregate_stats.php, cron_alerts.php, migrate_users.php,
  force_config.php, init_system.sh}` — todos cobertos pelos workers Python
  do api_service ou tornaram-se obsoletos.
- `tools/system/cron/unbound-dashboard-crons` reduzido para apenas o que
  ainda faz sentido (update_blacklist + sync_judicial_list).
- `StatsManager::ensureFreshCache()` agora é no-op (workers Python mantêm
  os JSONs atualizados).

### Wizard PHP legado removido
- Removidos `setup.php` e `api/setup_wizard.php` (assumiam MariaDB).
- Acesso pré-instalação redireciona para nova página `not_installed.php`
  (HTTP 503) com instruções claras.
- Bootstrap do admin é exclusivo do `install.sh` via `create_admin.py`.

### Tooling
- `api_service/tools/reset_admin_password.py` adicionado: CLI idempotente
  para resetar senha de um usuário existente quando o SMTP de recuperação
  não está disponível.
- `tools/docker/{Dockerfile.smoke,smoke-test.sh}`: smoke-test do `install.sh`
  em container Debian 13 (com `systemctl` stubado), valida `.venv`, env file,
  bootstrap do admin e `/api/v1/healthz`.

---

## v2.2.1 — 2026-05-04

### Tooling
- `tools/build-package.sh` reescrito: empacota `dashboard/` + `api_service/`
  + `system/{sudoers, systemd, apache, etc, bin, cron}` (sem MariaDB).
- `tools/install.sh` reescrito (8 etapas): instala redis-server, python3 3.11+,
  uv, módulos Apache (proxy, headers), gera JWT_SECRET, popula
  `/etc/unbound-dashboard/api-v1.env`, sobe systemd unit + Apache conf, faz smoke
  `/api/v1/healthz` e cria admin via `create_admin.py` (interativo ou env vars).
- `tools/update.sh` reescrito: backup automático (código + DuckDB + env),
  rsync incremental do dashboard e api_service preservando `.venv`, detecta mudança
  em `pyproject.toml`/`uv.lock` e roda `uv sync` quando necessário, restart do
  api_service + reload Apache + smoke `/api/v1/healthz`.
- `tools/build-update.sh` reescrito: empacota api_service + system completos.
- `api_service/tools/create_admin.py`: bootstrap idempotente do primeiro admin
  no DuckDB (usado pelo install.sh).
- Removidos: `tools/{build-package-v2.sh, fix-mariadb.sh, tune-mariadb.sh}`.
- `unbound-dashboard-api.service`: removido `After=mariadb.service`.

---

## v2.2.0 — 2026-05-04

### Marco
- Tear-down completo do MariaDB. Sistema agora roda 100% em DuckDB.

### Backend
- Migração de todos os managers PHP (`Blocklist`, `SourceBalance`, `Alert`,
  `Unbound`, `UnboundConfig`, `SystemCheck`) para o cliente FastAPI.
- `scripts/update_blacklist.php` reescrito sobre `ApiClient`.
- `Database.php` neutralizado (stub que lança em vez de matar o script).
- `AppMetricsManager` desacoplado do PDO.

### UI
- Página de Histórico agora consome `/api/v1/history/summary` direto.
- Página de Saúde & Auditoria atualizada: removido check de MariaDB, adicionados
  status systemd (FastAPI, Redis, Apache, Unbound) e novos componentes
  (DuckDB, env do api_service, diretório de backups).
- Benchmark DNS executa 3 rounds e o modal mostra "Teste X de 3" em tempo real.

---

## v2.1.0 — 2026-04-29

### Backend (modernização in-place)
- FastAPI/DuckDB em paralelo ao PHP+MariaDB legado (Strangler Fig).
- Workers assíncronos: LogWatcher, StatsAggregator, AlertChecker.
- Cache/queue Redis para snapshots e progresso de jobs.
- Rate limiting (slowapi), middlewares CORS e X-Request-ID.
- Métricas Prometheus em `/metrics`.
- JWT (HS256) compartilhado entre PHP e FastAPI via sessão.

### Auth & API bridge
- `src/ApiClient.php` (cURL) com `get/post/put/delete/login/changePassword`.
- Login do PHP delega para `/api/v1/auth/login` e guarda `api_jwt` na sessão.

### Páginas migradas para FastAPI
- Dashboard, Threats, History, Alerts, Blocklist, Config, Diagnostics,
  Service Control, Export.

---

## v1.0.3 — 2026-04-23

### Performance
- Índice composto `idx_action_ts (action, timestamp)` em `query_logs`.
- Coluna `blocked_count` em `daily_stats` + backfill de 31 dias.
- `api/threats_data.php` usa `daily_stats` para totais (31 linhas vs 16M).
- `log_ingester.php` atualiza `daily_stats` a cada inserção.
- `getTopDomains()` em `UnboundManager` limitado às últimas 24h.

### Update
- `scripts/migrate_db.sql` com migrações idempotentes pelo `update.sh`.
- `scripts/init_db.sql` atualizado com índice composto e nova coluna.

---

## v1.0.2 — 2026-04-28

- Removido índice duplicado `idx_query_logs_domain` em `query_logs`.
- Adicionados `idx_alerts_resolved_at` e `idx_alerts_started_at` em `alerts`.

---

## v1.0.1 — 2026-04-23

- Carregamento progressivo em History/Threats/Logs/Alerts (flush + loader).
- Seletor de linhas (10/20/50/100/todos) em Threats, default 10.
- UX: hide do loader global ao finalizar render.
- Build hardening: exclui credenciais e JSONs voláteis.
- `update.sh` preserva `src/data` local do servidor.
- Build de update lê `VERSION` e faz bump automático (patch).

---

## v1.0.0 — 2026-04-23

- Primeira versão estável do Unbound Dashboard (PHP + MariaDB).
- Monitoramento em tempo real, histórico DNS, alertas, diagnósticos.
- Exportação, benchmark e ferramentas operacionais.
