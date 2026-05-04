# Documentação do Unbound Dashboard

Esta pasta contém documentação de componentes e APIs principais do projeto.

Estrutura:

- `components/` — documentação das classes principais da aplicação PHP.
- `api/` — documentação das rotas PHP em `api/*.php` (legacy, em migração para FastAPI).
- `pages/` — documentação das páginas principais de interface.
- `PLANO_MODERNIZACAO_V1.md` — doc canônica da migração MariaDB → DuckDB (concluída 2026-05-04).
- `TROUBLESHOOTING.md` — problemas comuns e soluções.

Para a documentação técnica do **api_service** (FastAPI/DuckDB/Redis), consulte:

- `../api_service/deployments/README.md` — deployment systemd + Apache.
- `../api_service/app/routers/*.py` — endpoints REST `/api/v1/*` (auth, alerts, blocklist, exports, health, history, stats, threats, unbound, users).
- `../api_service/migrations/duckdb/V*.sql` — schema versionado.
- `../api_service/tests/` — 60+ testes (pytest + pytest-asyncio).

Use estes arquivos para entender o propósito, responsabilidades e dependências de cada módulo.

## Índice de Documentação

### Componentes
- `components/AlertManager.md` — gerencia geração e resolução de alertas.
- `components/AppMetricsManager.md` — monitora status do webserver (MariaDB removido em 2026-05-04, retorna stub `offline`).
- `components/Auth.md` — autenticação, sessão e autorização de usuários.
- `components/BlocklistManager.md` — gerencia listas de domínios bloqueados.
- `components/Database.md` — **DEPRECATED** (stub que lança `PDOException`). MariaDB removido em 2026-05-04; use `App\ApiClient` para acessar dados via FastAPI/DuckDB.
- `components/DiagnosticsManager.md` — executa verificações de rede e DNS.
- `components/Environment.md` — lê variáveis de ambiente e `.env`.
- `components/NetworkManager.md` — gerencia hostname, DNS e interfaces de rede.
- `components/SecurityMonitor.md` — coleta métricas de segurança e portas.
- `components/ServerMonitor.md` — coleta métricas de hardware e uso de recursos.
- `components/ShellHelper.md` — executa comandos shell de forma segura.
- `components/SourceBalanceManager.md` — gerencia múltiplas instâncias Unbound.
- `components/StatsManager.md` — agrega estatísticas e séries temporais.
- `components/SystemCheckManager.md` — valida serviços e configuração do Unbound.
- `components/UnboundConfigManager.md` — monta e aplica configuração do Unbound.
- `components/UnboundManager.md` — verifica estado e métricas do Unbound.

### APIs
- `api/blocklist_search.md` — busca na blacklist para o painel.
- `api/export.md` — exporta e importa dados e logs do sistema.
- `api/fix_health.md` — executa rotina de auto-reparo do Unbound.
- `api/live_log.md` — fornece logs em tempo real do Unbound.
- `api/service_control.md` — controla start/stop/restart do serviço Unbound.
- `api/setup_wizard.md` — valida ambiente na instalação/configuração.
- `api/stats.md` — retorna estatísticas agregadas para o painel.

### Páginas
- `pages/alerts.md` — explica a página de alertas e métricas do painel.
- `pages/blocklist.md` — descreve a página de gestão de bloqueios.
- `pages/config.md` — documenta a página de configuração do Unbound.
- `pages/dns_benchmark.md` — explica a página de benchmark DNS.
- `pages/exports.md` — documenta exportação e backup de dados.
- `pages/health.md` — descreve auditoria e reparo de saúde do sistema.
- `pages/history.md` — explica o histórico de consultas DNS.
- `pages/index.md` — resume a página principal do painel.
- `pages/logs.md` — documenta a página de visualização de logs.
- `pages/recover.md` — descreve a recuperação de senha.
- `pages/reset.md` — documenta a redefinição de senha com token.
- `pages/setup.md` — explica a página de instalação inicial.
- `pages/threats.md` — descreve a página de ameaças e blacklist.

