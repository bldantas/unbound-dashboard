# Unbound Dashboard

Painel de administração web para o servidor DNS **Unbound**, com monitoramento em tempo real, gerenciamento de blocklists, diagnósticos, alertas e histórico de consultas.

## Arquitetura

| Camada | Tecnologia |
|---|---|
| Frontend | PHP 8.1+ (Apache + PHP-FPM via mod_proxy_fcgi), Tailwind CSS, Vanilla JS, Chart.js |
| API | FastAPI (Python 3.11+) servida por uvicorn em `127.0.0.1:8001` |
| Banco | DuckDB (arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb`) |
| Cache / Queue | Redis 7+ |
| Resolver | Unbound 1.17+ |
| Workers | asyncio: `LogWatcher`, `StatsAggregator`, `AlertChecker`, `JsonExporter` |

O Apache faz reverse proxy de `/api/v1/*` para o FastAPI; o restante das rotas (páginas PHP, AJAX legado) é servido por PHP-FPM via `mod_proxy_fcgi`. JWT (HS256) é compartilhado entre PHP e FastAPI via sessão.

> **MariaDB foi removido em 2026-05-04** (v2.2.0). Sistema agora roda 100% em DuckDB.

## Funcionalidades

- **Dashboard principal** — métricas em tempo real (queries, bloqueios, latência, uso de recursos)
- **Histórico DNS** — consulta e filtragem de registros de consultas
- **Blocklist** — gerenciamento de listas de bloqueio (StevenBlack, Hagezi, ANATEL/Anablock judicial)
- **Alertas** — detecção automática de anomalias e alertas configuráveis
- **Diagnósticos** — testes de conectividade, rede e serviços
- **Benchmark DNS** — comparação de performance entre 8 resolvers (3 rounds)
- **Logs ao vivo** — visualização em tempo real dos logs do Unbound
- **Exportação** — backup e exportação de dados e configurações
- **Configuração** — interface para configurar o Unbound sem editar arquivos manualmente
- **Saúde & Auditoria** — verificação de integridade de componentes, status systemd e auto-reparo

## Requisitos

- **SO**: Debian 12+ (Bookworm/Trixie) ou Ubuntu 22.04 LTS+
- **Servidor web**: Apache 2.4+ com `proxy`, `proxy_http`, `proxy_wstunnel`, `proxy_fcgi`, `setenvif`, `headers`
- **PHP**: 8.1+ via PHP-FPM (`php-fpm` no apt) — `libapache2-mod-php` não é mais usado a partir de 2.2.10
- **Python**: 3.11+ (com `uv` para gerenciar venv)
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

1. Detecta SO e instala dependências (Apache, PHP 8+, Redis, Python 3.11+, Unbound)
2. Instala `uv` em `/usr/local/bin/uv`
3. Habilita módulos Apache (`proxy`, `proxy_http`, `proxy_wstunnel`, `proxy_fcgi`, `setenvif`, `headers`) e o conf do PHP-FPM detectado
4. Sincroniza venv do `api_service` via `uv sync`
5. Gera `JWT_SECRET` aleatório (`openssl rand -hex 32`) em `/etc/unbound-dashboard/api-v1.env`
6. Habilita systemd unit `unbound-dashboard-api.service` e Apache `conf-available`
7. Faz smoke `/api/v1/healthz`
8. Cria o admin inicial no DuckDB e marca `data/.installed`

## Atualização

```bash
# Em uma máquina build:
sudo bash tools/build-update.sh
# → gera dist/unbound-dashboard-update-v<X.Y.Z>-<TIMESTAMP>.tar.gz

# No servidor:
sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz   # dry-run
sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz                # aplicar
```

Cada update faz **3 backups automáticos** em `/var/backups/unbound-dashboard/`: tarball do código, snapshot do `.duckdb` e cópia do `api-v1.env` (preservando `JWT_SECRET`).

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

A documentação completa de componentes, APIs e páginas está em [docs/](docs/README.md).

- [Plano de Modernização v1](docs/PLANO_MODERNIZACAO_V1.md) — doc canônica da migração MariaDB → DuckDB
- [Troubleshooting](docs/TROUBLESHOOTING.md) — problemas comuns e soluções
- [Manual de Instalação](MANUAL_INSTALACAO.md) — guia passo a passo

## Changelog

Consulte [CHANGELOG.md](CHANGELOG.md) para o histórico completo de versões.

## Versão atual

Veja o arquivo [VERSION](VERSION).

## Licença

Uso privado — todos os direitos reservados.
