# Changelog

## v2.2.10 — 2026-05-11

### install.sh — migração de mod_php → PHP-FPM + pacotes críticos com fail-fast

Validado em VM limpa via `install-from-git.sh`: o pacote
`libapache2-mod-php` falhava silenciosamente em alguns ambientes (o loop
de instalação apenas logava `[!] Falha em libapache2-mod-php, continuando`
por causa do `|| warn`), deixando o Apache sem handler pra `.php` —
páginas apareciam como download/texto puro.

**Fix:**

1. **Substituído `libapache2-mod-php` por `php-fpm`** na lista de pacotes.
   PHP-FPM é o padrão moderno em Debian/Ubuntu, isolado do Apache, e
   instala de forma confiável.

2. **Detecção dinâmica da versão do PHP-FPM:** após o `apt install`, o
   script lê `systemctl list-unit-files` pra encontrar `phpX.Y-fpm.service`
   (8.2 em Debian 12, 8.3 em Debian 13, etc.) e usa essa versão pra
   `a2enconf phpX.Y-fpm` + `systemctl enable --now`.

3. **Apache modules ampliados:** Etapa 3 agora habilita também
   `proxy_fcgi` e `setenvif` (exigidos pelo drop-in do PHP-FPM) além do
   conjunto antigo de proxy do api_service. Idempotente.

4. **`a2dismod phpX.Y` legado:** se houver instalação `<=2.2.9` com
   `mod_php` ainda habilitado, o install desabilita silenciosamente antes
   de ativar o FPM — evita conflito de handler.

5. **Pacotes críticos com `|| err`:** lista de pacotes dividida em
   `CORE_PACKAGES` (apache2, php-fpm, php-*, python3*, redis-server,
   unbound) e `EXTRA_PACKAGES` (sudo, curl, wget, etc.). Falha em
   crítico aborta a instalação com mensagem clara; auxiliares mantêm o
   comportamento antigo de `|| warn`.

**Compatibilidade:** instalações existentes em produção continuam
funcionando — quando o `install.sh` rodar de novo numa máquina que já
tem `libapache2-mod-php`, o `a2dismod` legado vai retirar mod_php e o
FPM assume. Sem necessidade de remover o pacote manualmente.

**Docs atualizadas:** `README.md` (Arquitetura, Requisitos, lista de
módulos Apache) e `build-package.sh` (LEIAME.txt do pacote).

---

## v2.2.9 — 2026-05-11

### Bugfix do build-package.sh — DASHBOARD_DIR hardcoded

`build-package.sh` tinha `DASHBOARD_DIR="/var/www/html/unbound-dashboard"`
hardcoded. Quando chamado pelo `install-from-git.sh` (que clona em
`/tmp/unbound-dashboard-install/`), o build ia até `/var/www/html/...`
e usava artefatos errados (ou inexistentes). Resultado: pacote gerado com
versão antiga e arquivos faltando.

**Fix:** `DASHBOARD_DIR` agora é derivado do path do próprio script:
`SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"` →
`DASHBOARD_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"`.

Permite rodar o build a partir de qualquer checkout — alinhando com
`build-update.sh` que já fazia isso.

---

## v2.2.8 — 2026-05-04

### Feature: `install-from-git.sh` (one-liner do GitHub)

Novo `tools/install-from-git.sh`: clona o repo, builda o pacote local e
executa `install.sh` em um único comando. Pra rodar do servidor de destino
sem precisar empacotar `.tar.gz` em outra máquina.

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo ADMIN_USERNAME=admin ADMIN_EMAIL=a@b.c ADMIN_PASSWORD='senha' bash
```

Aceita `REPO_BRANCH=outra` para testar branches. Instala git/rsync/curl/tar
se faltarem, faz idempotente (`git pull` se o repo já existe), e limpa o
work dir ao final (a menos que `KEEP_WORK_DIR=true`).

README e MANUAL_INSTALACAO atualizados com o atalho one-liner.

---

## v2.2.7 — 2026-05-04

### install.sh — adiciona www-data ao grupo `adm`

O worker `log_watcher` lê `/var/log/syslog`, `/var/log/auth.log` e
`/var/log/unbound.log` continuamente. Em Debian/Ubuntu esses arquivos têm
mode `640 root:adm`. Sem isso, o worker crasha em loop com:

```
worker.crashed name=log_watcher error="[Errno 13] Permission denied: '/var/log/syslog'"
```

`install.sh` agora faz `usermod -aG adm www-data` (idempotente).

### docs/TROUBLESHOOTING

- §8 novo: schema drift do DuckDB legado (de instalação experimental v2.1.x
  antiga) e procedimento de wipe + recreate.
- §9 novo: `log_watcher` Permission denied em `/var/log/syslog` e fix.
- §10 (antigo §8): reset de senha do admin.

---

## v2.2.6 — 2026-05-04

### Bugfix do migrations runner — schema_migrations legado

**Problema:** Em servidores com instalação experimental v2.1.x antiga,
a tabela `schema_migrations` no DuckDB foi criada com schema diferente
(`(version, filename)`) do que o api_service atual espera
(`(version, name, checksum, applied_at)`). O `CREATE TABLE IF NOT EXISTS`
do startup virava no-op (tabela já existia) e o `SELECT version, checksum`
seguinte falhava com `BinderException: Referenced column "checksum" not found`.

**Fix:** `app/db/migrate.py::_ensure_schema_migrations` agora detecta colunas
ausentes via `information_schema.columns` e faz `ALTER TABLE ADD COLUMN`
para `checksum`, `name` e `applied_at` quando faltam:
- Se há coluna legada `filename`, popula `name` extraindo o basename sem `.sql`
- `checksum` legado fica vazio (`''`); o runner pula a validação de drift
  para entradas com checksum vazio (assume migration já aplicada)
- `applied_at` recebe `NOW()` retroativo

Tornado `_ensure_schema_migrations` idempotente e tolerante a schema drift.

### Hotfix em servidor já travado:
Reaplique o pacote v2.2.6 (rebuild do install.sh) ou faça hotfix manual:
```bash
sudo -u www-data bash -c '
    set -a; source /etc/unbound-dashboard/api-v1.env; set +a
    /var/www/html/unbound-dashboard/api_service/.venv/bin/python -c "
import duckdb
with duckdb.connect(\"/var/lib/unbound-dashboard/unbound_dash.duckdb\") as c:
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS checksum VARCHAR(64) DEFAULT \\\"\\\"\")
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP DEFAULT NOW()\")
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS name VARCHAR(255)\")
    c.execute(\"UPDATE schema_migrations SET name = regexp_replace(filename, \\\"\\\\.sql$\\\", \\\"\\\") WHERE name IS NULL\")
"'
sudo systemctl restart unbound-dashboard-api
```

---

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
