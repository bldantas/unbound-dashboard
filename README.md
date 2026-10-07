# Unbound Dashboard

[![CI](https://github.com/bldantas/unbound-dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/bldantas/unbound-dashboard/actions/workflows/ci.yml)
[![Smoke](https://github.com/bldantas/unbound-dashboard/actions/workflows/smoke.yml/badge.svg)](https://github.com/bldantas/unbound-dashboard/actions/workflows/smoke.yml)

Painel de administração web para o servidor DNS **Unbound**, com monitoramento em tempo real, gerenciamento de blocklists, diagnósticos, alertas e histórico de consultas.

## Início rápido

Em um servidor Debian 12+/Ubuntu 22.04+ limpo:

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo ADMIN_USERNAME=admin ADMIN_EMAIL=admin@empresa.com ADMIN_PASSWORD='senhaSegura123' bash
```

Depois acesse `http://<servidor>/unbound-dashboard/login.php` e entre com o admin criado. Para conferir se a API subiu:

```bash
curl -s http://127.0.0.1:8001/api/v1/healthz
systemctl status unbound-dashboard-api
```

Detalhes, variações e atualização nas seções [Instalação](#instalação) e [Atualização](#atualização).

## Arquitetura

| Camada | Tecnologia |
|---|---|
| Frontend | PHP 8.1+ (Apache + PHP-FPM via mod_proxy_fcgi), Tailwind CSS, Vanilla JS, Chart.js |
| API | FastAPI (Python 3.13+) servida por uvicorn em `127.0.0.1:8001` — 37 routers `/api/v1/*` |
| Banco | DuckDB (arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb`) |
| Cache / Queue / Pub-Sub | Redis 7+ |
| Resolver | Unbound 1.17+ |
| Workers | 19 asyncio supervisionados (LogWatcher, StatsAggregator, AlertChecker, UnboundCollector, UpdateChecker, HostPoller, BlocklistSyncer, AnomalyDetector, BackupUploader, QueryLogPruner, NotificationPruner, AuditPruner, PrometheusExporter, HAPeerMonitor, ExternalHealthPruner, RestoreTestRunner, BaselineLearner, GeoBlockUpdater, DigestSender) |

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

### API pública + SDKs
- **API tokens com capabilities granulares** (v2.110+) — tokens podem ser restritos a um subconjunto de capabilities em vez de admin global. Configurações → API Tokens → "🔒 Restringir capabilities".
- **SDK Python** em [`clients/python/`](clients/python/) — gerado via `openapi-python-client`, instalável via `pip install -e .` direto do repo. 190 endpoints, sync + asyncio.
- **SDK TypeScript/JS** em [`clients/js/`](clients/js/) — gerado via `openapi-typescript-codegen`, fetch-based, CancelablePromise. Importa direto do diretório.
- **Re-geradores**: [`tools/gen_sdk_python.sh`](tools/gen_sdk_python.sh) + [`tools/gen_sdk_js.sh`](tools/gen_sdk_js.sh).
- **Portal interativo** em `/api_docs.php` (no servidor instalado) com Swagger, ReDoc, Prometheus/Grafana setup, e exemplos curl + Python.

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

## Configuração

A API lê `/etc/unbound-dashboard/api-v1.env` (chmod 640, `root:www-data`), carregado pelo systemd unit `unbound-dashboard-api.service`. O instalador gera esse arquivo; o template completo está em [api_service/deployments/api-v1.env.example](api_service/deployments/api-v1.env.example).

| Variável | Para quê |
|---|---|
| `JWT_SECRET` | **Obrigatória.** Gerada pelo instalador (`openssl rand -hex 32`). A API não sobe com o valor `CHANGE_ME`. |
| `DB_PATH` | Arquivo DuckDB (default `/var/lib/unbound-dashboard/unbound_dash.duckdb`). |
| `REDIS_URL` | Default `redis://127.0.0.1:6379/0`. |
| `SECRETS_MASTER_KEY` | Chave Fernet que cifra secrets no banco (SMTP, destinos S3, OIDC, peers do cluster). Gerada pelo instalador só em instalação nova; instalações antigas podem não ter — sem ela os secrets ficam em texto plano (warning no log). Para adicionar: `openssl rand -base64 32 \| tr '+/' '-_'`, gravar no env e reiniciar a API (OIDC e destinos S3 já gravados são cifrados no startup; os demais quando forem salvos de novo). **Faça backup:** sem ela os secrets cifrados não são recuperáveis. |
| `UNBOUND_CONTROL`, `UNBOUND_LOG` | Caminhos do `unbound-control` e do log de queries. |
| `GITHUB_TOKEN` | Opcional (repo público). Usado pelo checador de updates. |
| `RATE_LIMIT_DEFAULT`, `RATE_LIMIT_AUTH`, `CORS_ORIGINS`, `LOG_LEVEL` | Ajustes finos. |

Após editar: `sudo systemctl restart unbound-dashboard-api`.

> O `.env.example` na raiz é legado da era MariaDB e não é mais lido.

## Desenvolvimento

### API (FastAPI)

```bash
cd api_service
uv sync                                    # cria .venv com deps de dev

export JWT_SECRET=dev-only-secret DB_PATH=/tmp/dev.duckdb REDIS_URL=redis://127.0.0.1:6379/0
.venv/bin/uvicorn app.main:app --reload --host 127.0.0.1 --port 8001
# Swagger: http://127.0.0.1:8001/api/v1/docs
```

As migrations em [api_service/migrations/duckdb/](api_service/migrations/duckdb/) (`V1`..`V30`) rodam automaticamente no startup. Para uma mudança de schema, crie o próximo `V<N>__descricao.sql` — nunca edite uma migration já aplicada (o runner valida checksum) — e atualize `EXPECTED_VERSIONS` em `tests/test_migrate.py`.

### Testes e lint (o mesmo que o CI roda)

```bash
cd api_service
.venv/bin/ruff check app tests
.venv/bin/ruff format --check app tests
.venv/bin/python -m pytest -q              # precisa de Redis local

# Sintaxe PHP (na raiz do repo)
find . -path ./.git -prune -o -name '*.php' -print | xargs -I {} php -l {} | grep -v 'No syntax errors'
```

O workflow [ci.yml](.github/workflows/ci.yml) roda isso em todo push/PR para `main`. O [smoke.yml](.github/workflows/smoke.yml) executa o `install.sh` num container Debian 13 (semanal e quando os scripts de instalação mudam); localmente:

```bash
sudo bash tools/build-package.sh && sudo bash tools/docker/smoke-test.sh
```

### Frontend (PHP)

As páginas `*.php` da raiz são renderizadas pelo Apache + PHP-FPM e chamam a API via [src/ApiClient.php](src/ApiClient.php) (server-side) ou `fetch('/api/v1/...')` (client-side). Partials compartilhados ficam em [includes/](includes/) e textos traduzíveis em [lang/](lang/) — use `t('chave')` no PHP e `window.t('js.chave')` no JS.

### SDKs

Após mudar schemas/rotas da API, regenere os clients com a API rodando localmente:

```bash
bash tools/gen_sdk_python.sh
bash tools/gen_sdk_js.sh
```

## Release

1. Atualize [VERSION](VERSION) e adicione a entrada no [CHANGELOG.md](CHANGELOG.md) — agrupada sob o dia (`## AAAA-MM-DD` → `### Título` → `- **vX.Y.Z**: ...`).
2. Commit + push para `main`.
3. `bash tools/release.sh` — builda o pacote de update, **assina** o tarball, extrai as notas do CHANGELOG e cria a release no GitHub (`gh` autenticado) com `.tar.gz`, `.sha256` e `.sig`. Use `DRAFT=true` para rascunho.

Servidores instalados detectam a release nova pelo worker `UpdateChecker` e podem aplicar pela UI.

### Assinatura dos pacotes

O self-update só aplica pacotes assinados. O script root `/usr/local/bin/unbound-dashboard-run-update.sh` verifica a assinatura Ed25519 do tarball com a chave pública embutida nele ([tools/system/bin/unbound-dashboard-run-update.sh](tools/system/bin/unbound-dashboard-run-update.sh)) e só então executa o `update.sh` **do pacote**.

- A chave privada fica na máquina de release, por padrão em `~/.config/unbound-dashboard/release-signing.key` (ou em `RELEASE_SIGNING_KEY`). **Guarde um backup fora dessa máquina**: sem ela não é possível publicar updates que os servidores aceitem.
- O `release.sh` confere que a chave corresponde à pública embutida antes de publicar.
- Trocar a chave: gere um par novo (`openssl genpkey -algorithm ed25519 -out release-signing.key`), atualize a pública no script, publique uma release assinada com a chave **antiga**; as seguintes usam a nova.
- Aplicar um pacote manualmente (SSH): `tar xzf <pacote>.tar.gz -C /tmp/u && sudo bash /tmp/u/update.sh /tmp/u`.

## Estrutura do Projeto

```
.
├── api/             # Endpoints PHP (AJAX/Fetch) residuais — em transição para FastAPI
├── api_service/     # FastAPI app
│   ├── app/         #   routers, services, repositories/duckdb, workers, core (auth/RBAC)
│   ├── migrations/  #   schema DuckDB versionado (V1..V30)
│   ├── deployments/ #   systemd unit, conf Apache, template do api-v1.env
│   └── tests/       #   pytest
├── clients/         # SDKs gerados (python/, js/)
├── docs/            # Documentação de páginas e componentes (parte legada — ver docs/README.md)
├── includes/        # Partials HTML (sidebar, topbar, head, command palette…)
├── lang/            # Traduções pt-BR e en
├── scripts/         # Scripts utilitários (update de blacklist)
├── src/             # Classes PHP (Auth, ApiClient, I18n…)
├── system/          # Arquivos de sistema instalados (sudoers, AppArmor, Let's Encrypt)
├── tools/           # install, update, build-package, build-update, release, gen_sdk_*, docker/
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

## Problemas comuns

- **API não sobe** — `journalctl -u unbound-dashboard-api -n 100`. Causas frequentes: `JWT_SECRET` ainda `CHANGE_ME`, Redis parado, permissão do arquivo DuckDB (owner deve ser `www-data`).
- **Painel abre mas sem dados** — confira se o Unbound grava log de queries em `UNBOUND_LOG`; o instalador aplica o drop-in `unbound.service.d/logfile.conf` para isso.
- **DuckDB não abre com `Failure while replaying WAL file`** — bug do DuckDB 1.5.x ao reproduzir `ALTER TABLE ... ADD COLUMN`. A API faz `CHECKPOINT` após cada migration e no shutdown, e o `install.sh` move um WAL quebrado para `*.wal.broken-<timestamp>` numa instalação nova. Em instalação com dados, restaure do backup em `/var/backups/unbound-dashboard/`.

Mais casos em [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md).

## Versão e changelog

Versão atual em [VERSION](VERSION); histórico completo em [CHANGELOG.md](CHANGELOG.md).

## Licença

Uso privado — todos os direitos reservados.
