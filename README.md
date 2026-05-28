# Unbound Dashboard

Painel de administração web para o servidor DNS **Unbound**, com monitoramento em tempo real, gerenciamento de blocklists, diagnósticos, alertas e histórico de consultas.

## Arquitetura

| Camada | Tecnologia |
|---|---|
| Frontend | PHP 8.1+ (Apache + PHP-FPM via mod_proxy_fcgi), Tailwind CSS, Vanilla JS, Chart.js |
| API | FastAPI (Python 3.13+) servida por uvicorn em `127.0.0.1:8001` — 37 routers `/api/v1/*` |
| Banco | DuckDB (arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb`) |
| Cache / Queue / Pub-Sub | Redis 7+ |
| Resolver | Unbound 1.17+ |
| Workers | 18 asyncio supervisionados (LogWatcher, StatsAggregator, AlertChecker, UnboundCollector, UpdateChecker, HostPoller, BlocklistSyncer, AnomalyDetector, BackupUploader, QueryLogPruner, NotificationPruner, AuditPruner, PrometheusExporter, HAPeerMonitor, ExternalHealthPruner, RestoreTestRunner, BaselineLearner, GeoBlockUpdater, DigestSender) |

O Apache faz reverse proxy de `/api/v1/*` para o FastAPI; o restante das rotas (páginas PHP, AJAX legado) é servido por PHP-FPM via `mod_proxy_fcgi`. JWT (HS256) é compartilhado entre PHP e FastAPI via sessão.

> **MariaDB foi removido em 2026-05-04** (v2.2.0). Sistema agora roda 100% em DuckDB.

## Funcionalidades

### Operação
- **Dashboard principal** — widgets: Alertas Ativos, Saúde de Infra, Workers, Live stream mini (WS), Top países 24h, Multi-host overview, Top 5 + Recent activity (tabbed)
- **Live stream** — feed contínuo de queries via WebSocket
- **Histórico DNS** — consulta e filtragem de registros
- **Diagnósticos** + **Benchmark DNS** (3 rounds, 8 resolvers)
- **Saúde & Auto-reparo** — DuckDB/Redis/Apache/Unbound status

### DNS + Blocklists
- **Blocklists multi-fonte** — 10 presets curados (StevenBlack, Hagezi, OISD, AdGuard, NoCoin, EasyPrivacy…) com toggle indexar/bloquear independentes + allowlist global ou por org
- **ANATEL/Anablock** — busca dedicada na base judicial em `/blocklist.php`
- **Client policies** — split-horizon DNS por CIDR/IP via `access-control-view` + views Unbound
- **DNS Security** — DNSSEC, QNAME minimization, harden options
- **Geo blocking** — bloqueio por país via CIDRs MaxMind
- **DoH inbound** — TLS terminado no Unbound

### Multi-tenant + Cluster
- **Organizations** — filtros por org em hosts, alerts, audit, policies, blocklist
- **Multi-host gerenciado** — poller agrega métricas de hosts secundários via api_tokens
- **Cluster HA** — peers com healthcheck autenticado (`/api/v1/cluster/peer-ping` + shared-secret-per-link). Ver [docs/pages/cluster.md](docs/pages/cluster.md)

### Segurança
- **RBAC** com 4 papéis + custom roles + 12 capabilities
- **2FA TOTP** opt-in por usuário
- **OIDC SSO** com PKCE S256 + group mapping (role rank)
- **JWT denylist** em Redis (revogação imediata)
- **`SECRETS_MASTER_KEY`** (Fernet) cifra secrets em DB; `secrets_migrator` corrige plaintext legacy na partida
- **Admin audit** persistente (DuckDB)

### Notificações & Backup
- **Email/SMTP** + **Webhooks** + **Digest diário HTML** com preferências por user
- **Backup multi-S3** (AWS/MinIO/Wasabi/R2/B2) com cache de tarball compartilhado
- **Restore test runner** smoke periódico do backup

### Atualização
- **Self-update via UI** — botão + histórico + auditoria + rollback automático em falha
- **Notificação por email/webhook** quando release nova
- **Aplicação automática** do drop-in `unbound.service.d/logfile.conf` (resolve bug do stderr→journal em Debian/Ubuntu modernos)

### Internacionalização
- **i18n pt-BR + en** server-side (`t()`) e client-side (`window.t()`) — ~24 páginas migradas, namespace `js.*` pra toasts cross-cutting

## Requisitos

- **SO**: Debian 12+ (Bookworm/Trixie) ou Ubuntu 22.04 LTS+
- **Servidor web**: Apache 2.4+ com `proxy`, `proxy_http`, `proxy_wstunnel`, `proxy_fcgi`, `setenvif`, `headers`
- **PHP**: 8.1+ via PHP-FPM (`php-fpm` no apt) — `libapache2-mod-php` não é mais usado a partir de 2.2.10
- **Python**: 3.13+ (com `uv` para gerenciar venv) — `pyproject.toml` exige `>=3.13`. Em Debian 12/Ubuntu 22.04 o `uv` baixa 3.13 standalone automaticamente.
- **Redis**: 7+
- **DNS**: Unbound 1.17+
- **Permissões**: acesso `sudo` para operações de sistema

## Instalação

Consulte o [Manual de Instalação](MANUAL_INSTALACAO.md) para detalhes.

### Direto do GitHub (recomendado pra teste/dev)

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo ADMIN_USERNAME=admin ADMIN_EMAIL=admin@empresa.com ADMIN_PASSWORD='senhaSegura123' bash
```

Faz tudo: instala `git`/`rsync` se faltar, clona o repo, builda o pacote local, e executa o `install.sh`. Aceita também `REPO_BRANCH=feature/x` pra testar branches.

### Via pacote `.tar.gz` (recomendado pra prod versionada)

```bash
# Em uma máquina build:
cd /var/www/html/unbound-dashboard
sudo bash tools/build-package.sh
# → gera tools/unbound-dashboard-v<X.Y.Z>.tar.gz

# No servidor de destino:
tar xzf unbound-dashboard-v<X.Y.Z>.tar.gz
cd unbound-dashboard-v<X.Y.Z>

# Modo interativo (pede username/senha):
sudo bash install.sh

# OU modo não-interativo:
ADMIN_USERNAME=admin ADMIN_EMAIL=admin@empresa.com ADMIN_PASSWORD='senhaSegura123' \
    sudo -E bash install.sh
```

O instalador:

1. Detecta SO e instala dependências (Apache, PHP 8.1+, Redis, Python 3.13+, Unbound)
2. Instala `uv` em `/usr/local/bin/uv`
3. Habilita módulos Apache (`proxy`, `proxy_http`, `proxy_wstunnel`, `proxy_fcgi`, `setenvif`, `headers`) e o conf do PHP-FPM detectado
4. Sincroniza venv do `api_service` via `uv sync`
5. Gera `JWT_SECRET` aleatório (`openssl rand -hex 32`) em `/etc/unbound-dashboard/api-v1.env`
6. Habilita systemd unit `unbound-dashboard-api.service` e Apache `conf-available`
7. Faz smoke `/api/v1/healthz`
8. Cria o admin inicial no DuckDB e marca `data/.installed`

## Atualização

### Direto do GitHub (recomendado pra teste/dev)

Re-executa o mesmo one-liner da instalação inicial. É idempotente: detecta
`data/.installed`, pula a criação de admin, preserva `api-v1.env` (com o
`JWT_SECRET`) e o DuckDB, faz **backup** do dir atual em
`/var/www/html/unbound-dashboard.backup.<timestamp>/` antes de sobrescrever.

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo bash
```

Para testar um branch específico:
```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo REPO_BRANCH=feature/x bash
```

O `install.sh` recopia todos os arquivos, re-roda `uv sync` (instala novas
deps Python se `pyproject.toml` mudou), re-aplica systemd unit + Apache conf
e reinicia o `unbound-dashboard-api` — as migrations DuckDB rodam no startup.

**Rollback** (se necessário):
```bash
LAST_BACKUP=$(ls -1d /var/www/html/unbound-dashboard.backup.* | tail -1)
sudo systemctl stop unbound-dashboard-api
sudo rsync -a --delete "$LAST_BACKUP/" /var/www/html/unbound-dashboard/
sudo systemctl start unbound-dashboard-api
```

### Via pacote de update `.tar.gz` (recomendado pra prod versionada)

```bash
# Em uma máquina build:
sudo bash tools/build-update.sh
# → gera dist/unbound-dashboard-update-v<X.Y.Z>-<TIMESTAMP>.tar.gz

# No servidor:
sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz   # dry-run
sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz                # aplicar
```

Mais cirúrgico que o one-liner: o `update.sh` só toca o que mudou entre
versões. Cada update faz **3 backups automáticos** em
`/var/backups/unbound-dashboard/`: tarball do código, snapshot do `.duckdb`
e cópia do `api-v1.env`.

## Estrutura do Projeto

```
.
├── api/             # Endpoints PHP (AJAX/Fetch) — em transição para FastAPI
├── api_service/     # FastAPI app: workers, repositories, routers, migrations DuckDB
├── docs/            # Documentação de componentes, APIs e páginas
├── includes/        # Partials HTML (sidebar, topbar, etc.)
├── scripts/         # Scripts utilitários (cron, blacklist update, etc.)
├── src/             # Classes PHP da aplicação
├── tools/           # build-package.sh, install.sh, update.sh, build-update.sh
├── data/            # Dados de runtime (gitignored)
└── *.php            # Páginas da interface web
```

## Documentação

- [SISTEMA.md](SISTEMA.md) — arquitetura completa, lista de routers/services/workers, RBAC, segurança, observabilidade
- [MANUAL_INSTALACAO.md](MANUAL_INSTALACAO.md) — instalação passo a passo (one-liner GitHub ou pacote versionado)
- [CHANGELOG.md](CHANGELOG.md) — histórico completo de releases (formato agrupado por dia desde v2.39)
- [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) — problemas comuns e soluções
- [docs/pages/cluster.md](docs/pages/cluster.md) — guia de setup do cluster HA
- [docs/PLANO_MODERNIZACAO_V1.md](docs/PLANO_MODERNIZACAO_V1.md) — doc canônica da migração MariaDB → DuckDB (concluída em v2.2.0)
- Swagger interativo da API: `/api/v1/docs` (no servidor instalado)

> Os arquivos em [docs/components/](docs/components/) e [docs/api/](docs/api/) descrevem código pré-modernização v2.2 (classes PHP/endpoints PHP que foram removidos ou migrados). Mantidos por histórico; ver os headers `[DEPRECATED]`.

## Changelog

Consulte [CHANGELOG.md](CHANGELOG.md) para o histórico completo de versões.

## Versão atual

Veja o arquivo [VERSION](VERSION).

## Licença

Uso privado — todos os direitos reservados.
