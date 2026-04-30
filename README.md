# Unbound Dashboard

Painel de administração web para o servidor DNS **Unbound**, com monitoramento em tempo real, gerenciamento de blocklists, diagnósticos, alertas e histórico de consultas.

## Status das versões

- Este repositório em `/var/www/html/unbound-dashboard` é a linha **legada v1** (PHP + MariaDB).
- A linha **v2** (Python/FastAPI + DuckDB + Vue 3) roda em `/opt/unbound-dashboard`.
- Para gerar build da v2 a partir deste host, use `tools/build-package-v2.sh`.

## Funcionalidades

- **Dashboard principal** — métricas em tempo real (queries, bloqueios, latência, uso de recursos)
- **Histórico DNS** — consulta e filtragem de registros de consultas
- **Blocklist** — gerenciamento de listas de bloqueio com suporte a múltiplas fontes
- **Alertas** — detecção automática de anomalias e alertas configuráveis
- **Diagnósticos** — testes de conectividade, rede e serviços
- **Benchmark DNS** — comparação de performance entre servidores DNS
- **Logs ao vivo** — visualização em tempo real dos logs do Unbound
- **Exportação** — backup e exportação de dados e configurações
- **Configuração** — interface para configurar o Unbound sem editar arquivos manualmente
- **Health check** — auditoria e auto-reparo do sistema

## Requisitos

- **SO**: Debian 12+, Debian 13 ou Ubuntu 22.04+
- **Servidor web**: Apache 2.4+
- **PHP**: 8.1+
- **Banco de dados**: MariaDB 10.6+
- **DNS**: Unbound
- **Permissões**: `sudo` para operações de sistema

> Nota: os requisitos acima são da **v1**. A v2 não usa Apache/PHP/MariaDB.

## Instalação

Consulte o [Manual de Instalação](MANUAL_INSTALACAO.md) para o procedimento completo de implantação.

```bash
# Clonar o repositório
git clone <url-do-repositorio> /var/www/html/unbound-dashboard

# Executar o instalador
cd /var/www/html/unbound-dashboard/tools
sudo bash install.sh
```

O instalador automatizado cuida de:
- Instalação do Apache, PHP, MariaDB e Unbound
- Criação do banco de dados e schema inicial
- Configuração de permissões e cron jobs
- Configuração inicial via wizard web

## Build da v2 (DuckDB)

Se você precisa gerar o pacote de instalação da v2 neste servidor:

```bash
cd /var/www/html/unbound-dashboard/tools
sudo bash build-package-v2.sh --skip-frontend
```

O script delega para `/opt/unbound-dashboard/tools/build-package.sh` e grava o artefato em `/opt/unbound-dashboard/dist/`.

## Configuração

Copie `.env.example` para `.env` e ajuste as credenciais:

```bash
cp .env.example .env
```

## Estrutura do Projeto

```
.
├── api/            # Endpoints da API interna
├── docs/           # Documentação de componentes e APIs
├── includes/       # Partials HTML (sidebar, topbar, etc.)
├── scripts/        # Scripts de manutenção e cron
├── src/            # Classes PHP da aplicação
├── tools/          # Scripts de instalação, build e atualização
├── data/           # Dados de runtime (excluídos do git)
└── *.php           # Páginas da interface web
```

## Atualização

Para atualizar o sistema em produção:

```bash
cd /var/www/html/unbound-dashboard/tools
sudo bash update.sh
```

## Documentação

A documentação completa de componentes, APIs e páginas está em [docs/](docs/README.md).

## Changelog

Consulte o [CHANGELOG.md](CHANGELOG.md) para o histórico de versões.

## Versão atual

**v1.0.3**

## Licença

Uso privado — todos os direitos reservados.
