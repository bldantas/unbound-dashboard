# Changelog — Unbound Dashboard v1 (PHP Frontend)

Histórico de mudanças do frontend PHP (`*.php`, `src/`, `includes/`, `api/`, `scripts/`, `tools/`).
Para mudanças do backend Python/FastAPI, consulte [CHANGELOG-api.md](CHANGELOG-api.md).

## [1.4.12] - 2026-05-02
### Added
- `app/workers/json_exporter.py`: novo worker Python que exporta snapshots do DuckDB como arquivos JSON locais em `/var/lib/unbound-dashboard/export/` a cada 30 segundos (alertas não lidos, stats ao vivo 24h, últimos 500 logs bloqueados, últimos 100 query logs, metadados de timestamp).
- `app/core/config.py`: novo campo `export_path` (default `/var/lib/unbound-dashboard/export`) para configurar o diretório de exportação via variável de ambiente.
- `src/LocalDataAdapter.php`: nova classe PHP que lê os JSONs exportados pelo worker diretamente do filesystem, sem chamadas HTTP à v2. Fornece `getAlerts()`, `getLiveStats()`, `getBlockedLogs()`, `getQueryLogs()` e `isFresh()` (staleness de 2 minutos). Escrita atômica (tmp → rename) no lado Python previne leitura de arquivo parcial.

### Improved
- `api/threats_data.php`: usa `LocalDataAdapter` como fonte primária para logs bloqueados e stats ao vivo; cai para `ApiClient` apenas se o export não estiver disponível.
- `health.php`: usa `LocalDataAdapter->getLiveStats()` para `queriesWindow` quando o export está fresco; evita chamada HTTP desnecessária à v2.
- `history.php`: usa `LocalDataAdapter` para top domínios e query logs recentes; fallback transparente para `ApiClient`.
- `src/AlertManager.php`: deduplicação de alertas (`addAlert`/`resolveAlert`) lê do export local em vez de fazer GET `/api/alerts`; writes (createAlert, markAlertRead, deleteAlert) ainda vão para v2. `getActiveCount()` usa export local; `getHistory()` usa ApiClient com fallback local.
- `src/AppMetricsManager.php`: `getMariaDBStats()` verifica frescor do export para determinar se v2 está online sem chamada HTTP; cai para healthcheck HTTP apenas se o export estiver stale.


### Added
- `scripts/diagnose_alerts_threats.sh`: novo diagnóstico operacional para incidentes de Alertas/Ameaças (status do serviço, comparação do worker ativo em `/opt` vs workspace, métricas reais do DuckDB e últimos acessos de rotas no Apache).

### Improved
- `threats.php`: UX refinada para estado sem dados recentes; agora distingue claramente "sem tráfego DNS recente" e "sem bloqueios recentes" de falha de carregamento.
- `threats.php`: tabela de eventos ajustada para mensagem explícita "Sem bloqueios recentes para exibir" quando não houver linhas.
- `alerts.php`: adicionada faixa de status de telemetria para diferenciar claramente sucesso, telemetria parcial e falha de carregamento dos cards.
- `alerts.php`: adicionada mensagem contextual de "Sem pendências críticas" quando não houver alertas ativos, evitando percepção de falha na seção de ocorrências.
- `alerts.php` e `threats.php`: banners de estado padronizados (cores, espaçamento e nomenclatura) para consistência visual entre módulos.
- `tools/build-update.sh`: corrigida a declaração de `VERSION_FILE`, `VERSION_API_FILE` e `TIMESTAMP` para evitar concatenação indevida e garantir staging consistente da versão API.
- `tools/build-update-v1.sh`: novo script de build exclusivo para o frontend PHP v1 (sem API Python v2); inclui validação PHP de todos os arquivos empacotados.
- `tools/update-v1.sh`: novo script de update dedicado ao frontend v1; atualiza apenas `WEB_DIR`, cria backup rotativo (5 versões), valida PHP no destino e recarrega servidor web (Apache2/Caddy).

### Fixed
- `src/Auth.php`: `check()` agora detecta JWT expirado decodificando o claim `exp` do payload; destrói a sessão e redireciona para `login.php` (páginas) ou retorna HTTP 401 JSON (endpoints `/api/`).
- `threats.php`: bloco `catch` agora limpa os containers "Carregando top domínios..." e "Carregando top clientes..." para o estado vazio ao invés de deixá-los presos. Também zera os counters e detecta HTTP 401 para redirecionar ao login.
- `alerts.php`: JS detecta HTTP 401 na chamada a `api/alerts_metrics.php` e redireciona ao login em vez de exibir banner de falha.

## [1.4.9] - 2026-05-02
### Fixed
- `app/workers/alert_checker.py`: reforçada a prevenção de flood no alerta `no_queries`.
- `app/workers/alert_checker.py`: quando há tráfego DNS novamente, alertas `no_queries` pendentes são auto-resolvidos.
- `app/workers/alert_checker.py`: adicionado cooldown de 6 horas para evitar recriação contínua do mesmo evento após reconhecimento manual, enquanto a condição persiste.

## [1.4.8] - 2026-05-01
### Fixed
- `deployments/systemd/unbound-api.service`: `User`/`Group` agora são parametrizados pelo instalador para casar com o `SERVICE_USER` escolhido.
- `tools/install.sh`: substitui `{{SERVICE_USER}}` no unit file antes do `daemon-reload`, evitando serviço rodando com usuário diferente do dono do banco.
- `tools/install.sh`: reforço pós-migration de ownership/permissões em `DB_PATH` e `DATA_DIR` para prevenir `Permission denied` no DuckDB.

## [1.4.7] - 2026-05-01
### Fixed
- `deployments/caddy/Caddyfile`: corrigido root do frontend para o modo PHP (`/var/www/html/unbound-dashboard`) com `php_fastcgi`; removido fallback de SPA para `index.html` que causava `404` quando `frontend/dist` não existia.
- `tools/install.sh`: detecção automática de serviço e socket do PHP-FPM e injeção no `Caddyfile` durante a instalação.
- `tools/install.sh`: PHP-FPM passa a ser habilitado/iniciado automaticamente no fluxo de instalação para evitar `404/502` ao servir páginas `.php` via Caddy.

## [1.4.6] - 2026-04-30
### Fixed
- `tools/install.sh`: normalização do domínio informado para Caddy (remove `http://`, `https://` e path), evitando configurações inválidas quando o usuário cola URL completa.
- `tools/install.sh`: validação explícita do `CADDY_SITE` e do `Caddyfile` com `caddy validate`; em erro, o instalador aborta com diagnóstico claro.
- `tools/install.sh`: verificação de conflito de portas 80/443 com Apache2 antes de subir Caddy; se houver conflito persistente, a instalação falha com instrução objetiva.
- `tools/install.sh`: após `restart caddy`, o instalador valida se o serviço ficou ativo e exibe journal em caso de falha.

## [1.4.5] - 2026-04-30
### Fixed
- `scripts/cron_alerts.php`: removido flood de `401 Unauthorized` na API v2. O cron agora autentica com `ALERTS_CRON_USER` e `ALERTS_CRON_PASS` (lidos de `/etc/unbound-dashboard.env`) antes de consultar/criar/resolver alertas.
- `scripts/cron_alerts.php`: quando as credenciais de serviço não estão configuradas, o script encerra de forma segura sem chamar endpoints protegidos de alertas.

## [1.4.4] - 2026-04-30
### Improved
- `install.sh`: adicionada seleção interativa de servidor web (`Caddy` ou `Apache2`) durante a instalação.
- `install.sh`: quando `Caddy` é escolhido, o instalador agora solicita o domínio/site e injeta automaticamente no `/etc/caddy/Caddyfile`.
- `install.sh`: alternância automática de serviços para evitar conflito em 80/443 (`stop/disable apache2` quando Caddy é selecionado; `stop/disable caddy` quando Apache2 é selecionado).
- `install.sh`: adicionada seleção interativa do usuário de serviço da aplicação (padrão `unbound-dash`).

## [1.4.3] - 2026-04-30
### Fixed
- `install.sh` Etapa 10: corrigido `AuthService.__init__()` — `UserRepository()` agora é instanciado e injetado.
- `install.sh` Etapa 10: tratamento de usuário já existente (re-instalação) — faz upsert de senha e role em vez de falhar com `ConstraintException`.

## [1.4.2] — 2026-04-30 — Build limpa sem bancos legados

### Melhorias
- **Versionamento**: arquivo `VERSION` atualizado de `1.4.0` para `1.4.2`, alinhando o pacote gerado ao cabeçalho desta release.
- **`tools/build-package.sh`**: build de instalação agora exclui artefatos de banco e runtime do diretório `data/` (`*.db`, `*.sqlite`, `*.duckdb`, `*.wal`, `*.shm`, `*.bak` e `data/tmp/*.lock`).
- **`tools/build-package.sh`**: adicionada limpeza pós-cópia no staging para remover esses artefatos de forma determinística, mesmo quando existirem variações de caminho durante o `rsync`.
- Objetivo: evitar distribuição de bases locais/legadas no pacote e reduzir risco de sobrescrever estado em novos servidores.

---

## [1.4.1] — 2026-04-30 — Correções na página de Ameaças

### Correções de bugs
- **`api/threats_data.php` — Domínios em Blacklist = 0**: corrigida a fonte do contador; era lido de `latest_stats.json['blocks']` (campo inexistente). Agora lê de `data/blocklist_counts.json` → soma de `adware + phishing + judicial` (440.132 domínios).
- **`api/threats_data.php` — Bloqueios Efetuados = 0**: o endpoint `getLiveStats()` usa janela de 24h (ou até 168h) e retornava 0 porque o último bloqueio registrado é anterior a essa janela. Agora usa `count($blockedLogs)` — quantidade de logs bloqueados retornados pelo banco sem filtro de tempo — como fonte de verdade para o contador.
- **`api/threats_data.php` — Taxa de Ameaças**: calculada como `blocked / total_queries_168h * 100`; zero defensivo quando não há queries na janela.
- **`api/threats_data.php` — Top Domínios/Clientes e Logs**: limite mínimo de busca aumentado de 100 para 500 registros para cobrir melhor o histórico de bloqueios disponível.
- **`src/ApiClient.php` — `getLiveStats()`**: adicionado parâmetro opcional `int $hours = 24` (máx 168) para permitir janelas customizadas por página.

---


## [2.1.20] — 2026-04-30 — Auditoria & Saúde + Build/Install para DuckDB

### Melhorias
- **`health.php`**: página de Auditoria & Saúde completamente atualizada para a arquitetura DuckDB/API v2:
  - Adicionados cards de status para **API v2** (`unbound-api`) e **Redis** (`redis-server`) via `systemctl is-active`.
  - Banner de **LogWatcher** indicando queries na janela atual; alerta visual se nenhuma query foi registrada.
  - Novo checklist de componentes inclui: `/var/log/unbound/unbound.log`, DuckDB (`/var/lib/unbound-dashboard/unbound_dash.duckdb`), `/etc/unbound-dashboard/env`.
  - Integridade das tabelas DuckDB (`users`, `query_logs`, `alerts`, `daily_stats`, `blocklist_hits`) agora inferida via conectividade da API v2 — removido o código legado de `SHOW TABLES LIKE` (MariaDB).
  - Corrigido caminho do log do daemon: era `/var/log/unbound.log`, agora `/var/log/unbound/unbound.log`.
- **`tools/build-package.sh`**: template de ambiente gerado pelo build agora inclui `UNBOUND_LOG=/var/log/unbound/unbound.log` e `UNBOUND_CONTROL=/usr/sbin/unbound-control`.
- **`tools/install.sh`**: instalador agora:
  - Cria `/var/log/unbound/` com permissões corretas para o usuário `unbound`.
  - Detecta `/etc/unbound/includes/general.conf` e injeta `logfile: "/var/log/unbound/unbound.log"` automaticamente se a diretiva ainda não existir.
  - Reinicia o Unbound após adicionar o `logfile:`.

---

## [2.1.19] — 2026-04-30 — Correção da página de Histórico (Top 10 e Logs)

### Correções de bugs
- **`history.php`**: corrigida a fonte do bloco **Top 10 Domínios** para usar `getLiveStats()` (`/api/v2/stats/live`), que é onde a API v2 expõe `top_domains`. O endpoint `/api/v2/stats/history` retorna série diária e não possui esse campo.
- **`history.php`**: separados os blocos `try/catch` de Top 10 e logs e atualizado para `catch (\Throwable)`; com isso, falha em um bloco não interrompe mais o outro.
- **`history.php`**: normalização de contagem dos domínios para aceitar `count` e `hits`, mantendo compatibilidade com payloads diferentes.
- **`src/ApiClient.php`**: `getStatsHistory()` passou a aceitar parâmetro de dias (`int $days = 30`) e enviar `?days=` para a API v2, com cache separado por janela (`stats:history:{days}`).

### Resultado
- Bloco **Top 10 Domínios** voltou a renderizar dados.
- Tabela **Logs de Consulta em Tempo Real** não é mais afetada por falhas do bloco de Top 10.

---

## [2.1.18] — 2026-04-30 — Correção do gráfico de Desempenho e LogWatcher

### Correções de bugs
- **`index.php` — Gráfico de Desempenho de Resolução**: corrigido bug no `applyLiveChartDelta` que inflava o último ponto do gráfico. Entre atualizações da snapshot (~60s), o poll de 5s somava deltas acumulativamente, fazendo o último ponto crescer até ~12x acima dos outros. Implementado acumulador `liveHitsAccum/liveMissAccum` que é resetado a cada nova snapshot; o último ponto agora é **substituído** pelo maior valor (snapshot ou acumulado), não somado indefinidamente.
- **`app/workers/alert_checker.py` — Alerta "Nenhuma query DNS"**: adicionada deduplicação no `_check_query_volume_drop`. Antes, um novo alerta idêntico era criado a cada 5 minutos enquanto a condição persistisse. Agora verifica se já existe um alerta `no_queries` não lido antes de criar outro.
- **`/etc/unbound/includes/general.conf` — LogWatcher sem dados**: o Unbound não tinha `logfile:` configurado, então todos os logs iam para o journald. O `LogWatcher` Python lia `/var/log/syslog` (onde o Unbound não escrevia), resultando em zero ingestão de queries no DuckDB desde 23/04. Adicionado `logfile: "/var/log/unbound/unbound.log"`.
- **`/etc/unbound-dashboard/env` e `/opt/unbound-dashboard/.env`**: variável `UNBOUND_LOG` corrigida de `/var/log/syslog` para `/var/log/unbound/unbound.log`.
- **`/opt/unbound-dashboard/app/core/config.py`**: default `unbound_log` atualizado para `/var/log/unbound/unbound.log`.

### Resultado
- O LogWatcher agora ingere ~60-108 queries/flush (a cada 5s) no DuckDB corretamente.
- O alerta de "Nenhuma query DNS" vai parar de aparecer repetidamente após o alerta existente ser marcado como lido.
- O gráfico de Desempenho de Resolução exibe valores consistentes sem picos no último ponto.

---

## [2.1.17] — Correção de compatibilidade PHP v1 ↔ API v2

### Correções de erros críticos (500 e avisos PHP)
- **`src/SourceBalanceManager.php`** (FATAL → 500 em `config.php`): `getSettings()` agora trata corretamente a resposta da API v2, que retorna dict plano `{"key":"value"}` em vez de lista de objetos `[{key,value}]`. Remove loop `foreach` inválido.
- **`alerts.php`** (Warning → campo ausente): `started_at` → `created_at` (campo real retornado pela API v2). Status Ativo/Resolvido agora usa `is_read` em vez de `resolved_at` (inexistente na API v2).
- **`history.php`** (Warning → campo ausente): acesso a `$q['category']` agora usa `?? null` (campo não retornado pelo endpoint `/api/v2/stats/logs`).
- **`index.php`** (Warning → campo ausente): todos os campos de `$initialMetrics` que podem estar ausentes no cache (`unwanted`, `unwanted_queries`, `unwanted_replies`, `blocks.*`) agora têm fallback `?? 0 / ?? false`.
- **`scripts/aggregate_stats.php`**: conexão MariaDB tornada opcional (`?PDO $db`); script roda sem MariaDB (sistema usa API v2/DuckDB); adicionado campo `unwanted` ao cache; consultas de blocklist no DB protegidas por try/catch.
- Campo `unwanted` adicionado ao cache `data/latest_stats.json` (soma de `unwanted_queries` + `unwanted_replies`).
- **`api/threats_data.php`** (500 → página em branco): endpoint reescrito para usar `ApiClient` + API v2 em vez de `Database` (MariaDB). Totais via `/api/v2/stats/live`, logs via `/api/v2/stats/logs?action=blocked`, contagem de blocklist via cache `latest_stats.json`. Top domínios/clientes agregados client-side a partir dos logs.
- **`src/ApiClient::getQueryLogs()`**: adicionado suporte a parâmetros `$action` e `$domain` para filtrar logs diretamente no endpoint da API v2.

---

## [2.1.16] — Frontend PHP v1 consumindo API v2 (DuckDB)

### Correções
- **Loop de redirecionamento (`ERR_TOO_MANY_REDIRECTS`)**: corrigido fluxo entre `login.php` e `setup.php` quando `data/.installed` já existe.
- `login.php` não redireciona mais para `setup.php` com base em `Auth::hasUsers()` (evita loop em falhas temporárias da API v2).
- `index.php` agora só redireciona para `setup.php` se a instalação realmente não foi concluída (`data/.installed` ausente).
- **Login com erro "Serviço indisponível"**: restaurado backend `unbound-api` no host de teste, com correção de unit systemd (`Type=simple`) e execução em `--workers 1` para evitar lock concorrente no DuckDB durante startup.
- **Autenticação v2 após migração**: sincronizados usuários e configurações do MariaDB para DuckDB, restabelecendo contas da aplicação e removendo falso positivo de indisponibilidade no formulário de login.

### Migração da camada de dados (PHP v1)
- **`src/ApiClient.php`**: cliente HTTP central para comunicação do PHP com o backend v2 (`/api/v2/*`), incluindo cache Redis opcional.
- **`src/Auth.php`**: autenticação e gestão de usuários migradas de PDO/MySQL para API v2 (JWT em sessão PHP).
- **`src/AlertManager.php`**: criação/resolução de alertas migradas para API v2.
- **`src/BlocklistManager.php`**: leitura/escrita de `blacklist_source` via API v2.
- **`src/SourceBalanceManager.php`**: leitura e persistência de configurações via API v2.
- **`src/AppMetricsManager.php`**: métricas de saúde passam a usar `/api/v2/health` (compatível com assinatura legada).
- **`history.php`**: consultas históricas migradas para endpoints da API v2.

### Compatibilidade e fallback
- **`src/Database.php`**: convertido para stub de compatibilidade; mantém conexão MariaDB apenas quando variáveis `DB_*` estiverem definidas (útil para scripts legados/migração).

### Scripts de instalação/build/update
- **`tools/install.sh`**:
	- instala runtime PHP (`php`, `php-curl`, `php-redis`, `php-fpm`, `apache2`);
	- publica frontend PHP em `/var/www/html/unbound-dashboard`;
	- cria/atualiza `/etc/unbound-dashboard.env` com `API_V2_URL`, `API_V2_TIMEOUT`, `REDIS_URL`.
- **`tools/build-package.sh`**: pacote e LEIAME ajustados para cenário híbrido (PHP v1 + API v2) e inclusão de `update.sh` no pacote.
- **`tools/build-update.sh`**: update passa a empacotar projeto completo (incluindo frontend PHP v1) em `app/`.
- **`tools/update.sh`**: sincroniza código v2 para `/opt/unbound-dashboard` e frontend PHP para `/var/www/html/unbound-dashboard`; aplica ownership apropriado e reinicia `apache2`.

### Observações operacionais
- Frontend PHP v1 agora depende do backend v2 ativo em `API_V2_URL`.
- Caddy mantido como opcional no instalador quando Apache2 estiver ativo, para evitar conflito de portas por padrão.

## [2.1.15] — Toolkit de migração Big Bang v1 -> v2

### Novas funcionalidades
- **`tools/migrate_from_mariadb.py`**: reescrito para o cenário real da v1 (`settings.setting_key/value`, `alerts` com `resolved_at/is_dismissed`, `daily_stats` com `stat_date/total_queries/blocked_count`) e schema da v2 (DuckDB).
- **`tools/run-bigbang-migration.sh`**: orquestra a janela Big Bang (backup do `.duckdb`, stop/start de serviço, execução da migração com `--truncate`, export da blocklist legada e validação final).
- **`tools/validate-bigbang-migration.sh`**: validação pós-cutover com contagens MySQL x DuckDB e health-check da API.

### Melhorias
- **`tools/migrate_from_mariadb.py`**: migração em lotes por faixa de `id` para `query_logs` (mais estável que `OFFSET` em bases grandes).
- **`tools/migrate_from_mariadb.py`**: normalização de `role` (`admin/viewer`) e `severity` (`info/warning/critical`) durante a carga.
- **`tools/migrate_from_mariadb.py`**: exporta `domain_blacklist` para CSV para reaplicação na v2.

### Observações operacionais
- Redis permanece sem migração de dados (cache/pubsub volátil por design).

## [2.1.1] — Scripts de build e instalação

### Novas funcionalidades
- **`tools/install.sh`**: Script de instalação automatizada para Debian 12+/Ubuntu 22.04+. Instala dependências (Python 3.12+, uv, Node 20, Redis, Caddy, Unbound), cria usuário sistema `unbound-dash`, cria diretórios, instala venv, roda migrations, configura systemd e Caddy, cria usuário admin interativo.
- **`tools/build-update.sh`**: Gera pacote `.tar.gz` de atualização com apenas arquivos atualizáveis (`app/`, `frontend/dist/`, `migrations/`, `deployments/`, `VERSION`, `CHANGELOG.md`). Suporte a `BUMP_TYPE=patch|minor|major`, `SKIP_FRONTEND`, `AUTO_BUMP_VERSION`.
- **`tools/update.sh`**: Aplica update em instalação existente. Backup automático antes do update (mantém 5 mais recentes em `/var/backups/unbound-dashboard/`). Rsync seletivo (não sobrescreve `.venv`, configs locais). Atualiza dependências Python via `uv`. Roda migrations novas. Suporte a `DRY_RUN=true`, `VERBOSE=true`, `AUTO_RESTART`, `FORCE_CADDY`.

### Correções
- **`tools/install.sh`**: Corrigida instalação de dependências Python para ler `project.dependencies` do `pyproject.toml` (em vez de tratar o arquivo como requirements).
- **`tools/install.sh`**: Validação explícita de Python >= 3.12, binários essenciais e import de `duckdb` após instalação.
- **`tools/install.sh`**: Corrigida execução de migrations para `python -m app.db.migrate`.
- **`tools/update.sh`**: Corrigida atualização de dependências para ler `project.dependencies` do `pyproject.toml` e validação de `duckdb` no venv.
- **`tools/update.sh`**: Corrigida execução de migrations para `python -m app.db.migrate`.
- **`tools/build-update.sh`**: Corrigido staging do frontend no pacote de update (`mkdir -p frontend/dist` antes do `rsync`), evitando falha `No such file or directory`.

---

## [2.1.0] — Frontend atualizado

### Correções (Bug Fixes)
- **`api/client.ts`**: Adicionado `export { api as apiClient }` — corrige 4 views (`BlocklistView`, `ConfigView`, `DiagnosticsView`, `HealthView`) que importavam o nome antigo
- **`api/alerts.ts`**: Removido campo `type` inexistente; timestamp corrigido para Unix (`number`) em vez de string ISO
- **`views/LogsView.vue`**: Eliminados operadores `??` com campos de tipos incompatíveis; useLiveLog agora normaliza entrada WebSocket para `QueryLogEntry`
- **`views/HistoryView.vue`**: Removido import `watch` não utilizado
- **`views/SettingsView.vue`**: Corrigida variável `value` no `v-for` não utilizada

### Novas funcionalidades
- **`api/balance.ts`**: Módulo de API dedicado para upstreams (`listUpstreams`, `addUpstream`, `removeUpstream`, `setEnabled`, `checkHealth`, `getStats`)
- **`api/health.ts`**: Módulo de API dedicado para saúde do sistema
- **`api/unbound.ts`**: Módulo de API dedicado para controle do Unbound
- **`api/diagnostics.ts`**: Módulo de API dedicado para diagnósticos
- **`views/BalanceView.vue`**: Nova view de gerenciamento de upstreams — tabela com toggle ativo/inativo, health badge, latência, formulário de adição, cards de estatísticas agregadas
- **`router/index.ts`**: Adicionada rota `/balance` (adminOnly)
- **`views/AppLayout.vue`**: Adicionado item "⚖️ Balanceamento" na sidebar; badge de alertas não-lidos com polling a cada 60s; exibe role do usuário
- **`components/ToastContainer.vue`**: Sistema global de notificações toast (info/success/warning/error) com auto-dismiss
- **`composables/useToast.ts`**: Composable reativo para emitir toasts
- **`stores/ui.ts`**: Store Pinia para estado da UI (sidebar collapsed)
- **`App.vue`**: Integrado listener `api:error` para disparar toasts em 429 e 5xx globalmente

### Melhorias
- **`views/DashboardView.vue`**: Auto-refresh a cada 30s via `useIntervalFn`; barra de progresso da taxa de bloqueio; estado de erro visível; timestamp da última atualização
- **`views/AlertsView.vue`**: Badge de severidade colorido; botão de exclusão individual; timestamp Unix corrigido
- **`api/client.ts`**: Timeout de 30s; header `X-Request-ID` em cada request; tratamento de 429 e 5xx com evento global
- **`composables/useLiveLog.ts`**: Normaliza entradas WebSocket para tipo `QueryLogEntry`
- **`tsconfig.app.json`**: Adicionado `baseUrl` e `paths` para alias `@/`
- **`tsconfig.node.json`**: Adicionado `types: ["node"]` para suporte a `node:url`
- Instalado `@types/node` como devDependency
