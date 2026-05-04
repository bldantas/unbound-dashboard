# 📖 Manual de Instalação — Unbound Dashboard

Este manual descreve o procedimento para gerar um pacote de instalação e implantá-lo em um servidor novo (Debian 12/13 ou Ubuntu 22.04+).

> **Stack atual (v2.2.x):** PHP (frontend) + FastAPI/DuckDB/Redis (backend). MariaDB foi removido em 2026-05-04.

---

## 📋 Requisitos do Servidor de Destino

* **Sistema operacional**: Debian 12 (Bookworm), Debian 13 (Trixie) ou Ubuntu 22.04 LTS+
* **Acesso**: superusuário (`root`) via `sudo`
* **Internet**: conexão ativa para `apt-get` e instalação do `uv`
* **Arquitetura**: x86_64 ou ARM64
* **Recursos sugeridos**: 1 vCPU, 1 GB RAM, 5 GB de disco livre

O `install.sh` cuida da instalação automática de:

* Apache 2.4+ (com `mod_proxy`, `mod_proxy_http`, `mod_proxy_wstunnel`, `mod_headers`)
* PHP 8.1+ + módulos comuns
* Python 3.11+ + `uv` (gerenciador de venv)
* Redis 7+
* Unbound 1.17+
* `rsyslog`, `dnsutils`, `traceroute`, `dns-root-data`

---

## 🏗 Passo 1: Gerar o Pacote (Servidor de Origem)

Em uma máquina que já tem o repositório clonado:

```bash
cd /var/www/html/unbound-dashboard
sudo bash tools/build-package.sh
```

O script empacota:

* `dashboard/` — frontend PHP completo
* `api_service/` — FastAPI app + workers + migrations DuckDB (sem `.venv`)
* `system/sudoers/` — `/etc/sudoers.d/unbound-dashboard`
* `system/systemd/` — unit `unbound-dashboard-api.service`
* `system/apache/` — conf-available proxy `/api/v1/*`
* `system/etc/api-v1.env.example` — template do EnvironmentFile
* `system/bin/` — `unbound-health-fix.sh`, `setup-unbound-logs.sh`
* `system/cron/unbound-dashboard-crons` — definições de cron
* `install.sh` — instalador automatizado
* `LEIAME.txt` — instruções resumidas

O artefato é gravado em `tools/unbound-dashboard-v<X.Y.Z>.tar.gz`.

---

## 🚚 Passo 2: Transferência

```bash
scp tools/unbound-dashboard-v<X.Y.Z>.tar.gz usuario@novo-servidor:/tmp/
```

---

## ⚙️ Passo 3: Instalação no Servidor de Destino

```bash
cd /tmp
tar xzf unbound-dashboard-v<X.Y.Z>.tar.gz
cd unbound-dashboard-v<X.Y.Z>
sudo bash install.sh
```

O instalador roda 8 etapas:

1. **Detecção do SO** — valida Debian 12+/Ubuntu 22.04+
2. **Pacotes APT** — instala dependências do sistema
3. **Apache** — habilita módulos de proxy
4. **uv + venv** — instala `uv` e cria `.venv` do `api_service` via `uv sync --no-dev`
5. **Deploy** — copia `dashboard/` e `api_service/` para `/var/www/html/unbound-dashboard/`
6. **Config** — gera `JWT_SECRET` aleatório, popula `/etc/unbound-dashboard/api-v1.env`, instala systemd unit + Apache conf, sudoers, scripts em `/usr/local/bin`, crontab
7. **Permissões + serviços** — sobe Apache, Redis, Unbound, `unbound-dashboard-api`; faz smoke `/api/v1/healthz`
8. **Admin inicial** — pede username/email/senha (interativo) e cria o admin no DuckDB; marca `data/.installed`

### Modo não-interativo (CI/automação)

```bash
ADMIN_USERNAME=admin \
ADMIN_EMAIL=admin@empresa.com \
ADMIN_PASSWORD='senhaSegura123' \
sudo -E bash install.sh
```

> A flag `-E` no `sudo` é essencial para preservar as variáveis de ambiente.

---

## 🌐 Passo 4: Acesso

Após o `install.sh` finalizar, acesse:

```
http://<IP-DO-SERVIDOR>/unbound-dashboard/login.php
```

Use as credenciais do admin criado na etapa 8.

> **Observação:** o wizard PHP antigo (`setup.php`) é **pulado automaticamente** — o instalador já cria o admin e marca `data/.installed`.

---

## 🔄 Atualização (Update Incremental)

Para atualizar uma instalação existente:

### 1. Gerar o pacote de update (na máquina build)

```bash
cd /var/www/html/unbound-dashboard
sudo bash tools/build-update.sh
# → gera dist/unbound-dashboard-update-v<X.Y.Z>-<TIMESTAMP>.tar.gz
```

> O script faz auto-bump da `VERSION` (patch). Use `AUTO_BUMP_VERSION=false` para pular ou `BUMP_TYPE=minor|major` para tipo diferente.

### 2. Transferir para o servidor

```bash
scp dist/unbound-dashboard-update-v<X.Y.Z>-<TS>.tar.gz usuario@servidor:/tmp/
```

### 3. Aplicar

```bash
# Dry-run (não aplica nada, só simula):
sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz

# Real:
sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/pacote.tar.gz
```

O `update.sh`:

1. Valida sintaxe (PHP + bash) de todos os arquivos do pacote
2. Cria 3 backups em `/var/backups/unbound-dashboard/`:
   * `dashboard-<TS>.tar.gz` — código completo
   * `duckdb-<TS>.duckdb` — snapshot do banco
   * `api-v1.env-<TS>` — env file (preserva `JWT_SECRET`)
3. Aplica `dashboard/` via rsync (preserva `data/`, `src/data/`, `Database.php`)
4. Aplica `api_service/` via rsync preservando `.venv`
5. Detecta mudanças em `pyproject.toml`/`uv.lock` e roda `uv sync --no-dev` automaticamente
6. Atualiza systemd, Apache conf, sudoers, scripts em `/usr/local/bin`, crontab
7. Reset de permissões (`www-data:www-data`)
8. Reload do Apache + restart do `unbound-dashboard-api`
9. Smoke `/api/v1/healthz`

### Variáveis aceitas pelo `update.sh`

| Variável | Default | Efeito |
|---|---|---|
| `DRY_RUN` | `false` | Simula sem aplicar |
| `AUTO_RESTART` | `true` | Reinicia api_service + Apache ao final |
| `SKIP_VENV_SYNC` | `false` | Pula `uv sync` mesmo se `pyproject.toml` mudou |
| `VERBOSE` | `false` | Saída detalhada |

### Rollback

```bash
sudo systemctl stop unbound-dashboard-api
sudo tar xzf /var/backups/unbound-dashboard/dashboard-<TS>.tar.gz -C /
sudo cp -a /var/backups/unbound-dashboard/duckdb-<TS>.duckdb /var/lib/unbound-dashboard/unbound_dash.duckdb
sudo cp -a /var/backups/unbound-dashboard/api-v1.env-<TS> /etc/unbound-dashboard/api-v1.env
sudo systemctl start unbound-dashboard-api
```

---

## 🛠 Troubleshooting

### `api_service` não sobe

```bash
sudo systemctl status unbound-dashboard-api
sudo journalctl -u unbound-dashboard-api -n 50 --no-pager
```

Causas comuns:

* `JWT_SECRET` ainda no placeholder `CHANGE_ME...` — o `pydantic-settings` falha no startup.
* Permissão de `/etc/unbound-dashboard/api-v1.env` (correto: `640 root:www-data`).
* `.venv` corrompido — rode `cd /var/www/html/unbound-dashboard/api_service && sudo /usr/local/bin/uv sync --no-dev`.

### `/api/v1/healthz` retorna 502 via Apache

* Verifique se o `api_service` está em `127.0.0.1:8001`: `ss -tlnp | grep 8001`.
* Verifique se o Apache tem os módulos: `apache2ctl -M | grep proxy`.
* Habilite a conf: `sudo a2enconf unbound-dashboard-api && sudo systemctl reload apache2`.

### Perdi a senha do admin

```bash
cd /var/www/html/unbound-dashboard/api_service
ADMIN_USERNAME=admin ADMIN_PASSWORD='novaSenha' \
sudo -u www-data \
    env "PYTHONPATH=$(pwd)" $(grep -v '^#' /etc/unbound-dashboard/api-v1.env | xargs) \
    .venv/bin/python tools/create_admin.py
```

> Se o usuário já existir, o script detecta e sai sem alterar — para resetar a senha, é necessário usar a página de recuperação ou um endpoint admin.

### Permissões quebradas no Unbound

Acesse `/health.php` no dashboard e clique em **Executar Auto-Reparo**, ou rode manualmente:

```bash
sudo /usr/local/bin/unbound-health-fix.sh
```

---

## 🏁 Conclusão

Seu Unbound Dashboard está pronto. Para o detalhamento técnico de cada componente, consulte [docs/README.md](docs/README.md).
