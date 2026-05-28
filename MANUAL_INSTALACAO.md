# 📖 Manual de Instalação — Unbound Dashboard

Este manual descreve o procedimento para gerar um pacote de instalação e implantá-lo em um servidor novo (Debian 13 ou Ubuntu 24.04+).

> **Stack atual:** PHP (frontend) + FastAPI/DuckDB/Redis (backend). MariaDB foi removido em 2026-05-04. Versão atual em [VERSION](VERSION); histórico em [CHANGELOG.md](CHANGELOG.md).

---

## 📋 Requisitos do Servidor de Destino

* **Sistema operacional**: **Debian 13 (Trixie)** ou **Ubuntu 24.04 LTS+** (Python 3.13 nativo).
  * Em **Debian 12 (Bookworm)** ou **Ubuntu 22.04** *funciona*, mas o `uv` precisará **baixar Python 3.13 standalone** (~50 MB extras + ~30s no primeiro `uv sync`), porque o `python3` default desses SOs é 3.11. Pesa pouco em prod mas vale saber.
* **Acesso**: superusuário (`root`) via `sudo`
* **Internet**: conexão ativa para `apt-get` e instalação do `uv` (+ Python 3.13 standalone se aplicável)
* **Arquitetura**: x86_64 ou ARM64
* **Recursos sugeridos**: 1 vCPU, 1 GB RAM, 5 GB de disco livre

O `install.sh` cuida da instalação automática de:

* Apache 2.4+ (com `mod_proxy`, `mod_proxy_http`, `mod_proxy_wstunnel`, `mod_proxy_fcgi`, `mod_headers`, `mod_setenvif`)
* PHP 8.1+ via PHP-FPM (handler servido pelo `mod_proxy_fcgi`)
* Python **3.13+** + `uv` (gerenciador de venv) — `pyproject.toml` exige `>=3.13`
* Redis 7+
* Unbound 1.17+
* `rsyslog`, `dnsutils`, `traceroute`, `dns-root-data`

---

## ⚡ Atalho: Instalar direto do GitHub (one-liner)

Para teste/dev rápido, sem ter que gerar pacote em outra máquina:

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo ADMIN_USERNAME=admin \
         ADMIN_EMAIL=admin@empresa.com \
         ADMIN_PASSWORD='senhaSegura123' \
    bash
```

O `install-from-git.sh` no servidor de destino:

1. Instala `git`, `rsync`, `tar`, `curl` se faltarem
2. Clona o repo (`main` por default — use `REPO_BRANCH=outra` pra mudar)
3. Roda `tools/build-package.sh` localmente
4. Extrai o pacote e executa `install.sh` end-to-end
5. Limpa `/tmp/unbound-dashboard-install` (use `KEEP_WORK_DIR=true` pra preservar)

Variáveis aceitas:

| Variável | Default | Descrição |
|---|---|---|
| `ADMIN_USERNAME` | (prompt) | Username do admin (regex `^[a-zA-Z0-9._-]+$`) |
| `ADMIN_EMAIL` | (prompt) | Email do admin (opcional) |
| `ADMIN_PASSWORD` | (prompt) | Senha (mín. 6 chars) |
| `REPO_URL` | `https://github.com/bldantas/unbound-dashboard.git` | Repo de origem |
| `REPO_BRANCH` | `main` | Branch a clonar |
| `GITHUB_TOKEN` | _(vazio)_ | PAT do GitHub pra clonar **repo privado**. Quando setado, é injetado como `https://oauth2:<TOKEN>@github.com/…` na URL do clone. |
| `WORK_DIR` | `/tmp/unbound-dashboard-install` | Diretório de trabalho |
| `KEEP_WORK_DIR` | `false` | Preserva o WORK_DIR ao final |

Para instalações de **produção versionada** (release imutável + auditoria), continue usando o fluxo do pacote `.tar.gz` abaixo.

> 💡 Esse mesmo one-liner também faz **atualização** in-place quando rodado numa máquina que já tem o dashboard instalado — veja a seção [🔄 Atualização](#-atualização) abaixo.

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

## 🔐 Passo 3.5: Configurar `SECRETS_MASTER_KEY` (recomendado)

**Não é estritamente obrigatório** pra o sistema subir, mas é altamente recomendado em produção — sem ela, secrets sensíveis (OIDC `client_secret`, tokens HA, chaves S3 de backup) ficam em **plaintext** no DuckDB.

```bash
# Gera uma chave Fernet 32-byte aleatória
KEY=$(python3 -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())")

# Adiciona ao env file
echo "SECRETS_MASTER_KEY=$KEY" | sudo tee -a /etc/unbound-dashboard/api-v1.env
sudo systemctl restart unbound-dashboard-api
```

Na próxima partida, o worker `secrets_migrator` cifra automaticamente qualquer secret já gravado em plaintext (warning `cipher_service.no_master_key` some dos logs). Tem chamada **idempotente**: rodar uma segunda vez é no-op.

**Guarde a chave em local seguro** — se ela for perdida e o env file for reescrito, os secrets cifrados ficam ilegíveis. Backup recomendado: cópia da `api-v1.env` em volume separado ou cofre (Vault, 1Password, etc).

---

## 🔧 Passo 3.6: Drop-in stderr do Unbound (aplicado automaticamente)

O `install.sh` e `update.sh` aplicam automaticamente um drop-in systemd em `/etc/systemd/system/unbound.service.d/logfile.conf` com `StandardError=append:/var/log/unbound/unbound.log`.

**Por quê?** Em Debian/Ubuntu modernos, `unbound -d` (foreground) joga stderr no journal e **ignora** `logfile:` em `unbound.conf`. Sem o drop-in, o LogWatcher do dashboard faz tail de arquivo vazio — Live Stream e tabela `query_logs` ficam sem dados, mesmo com o Unbound respondendo `dig` normalmente.

Você não precisa fazer nada — só está documentado pra explicar o arquivo extra. Se precisar verificar:

```bash
ls -la /etc/systemd/system/unbound.service.d/logfile.conf
sudo systemctl cat unbound | grep StandardError
```

---

## 🌐 Passo 4: Acesso

Após o `install.sh` finalizar, acesse:

```
http://<IP-DO-SERVIDOR>/unbound-dashboard/login.php
```

Use as credenciais do admin criado na etapa 8.

> **Observação:** desde v2.2.2 o wizard PHP foi removido. O `install.sh` cria o admin diretamente no DuckDB via `api_service/tools/create_admin.py`. Caso alguém acesse o sistema antes da instalação, vê uma página `not_installed.php` retornando 503 com instruções.

---

## 🔄 Atualização

Três opções:
- **A) Botão "Atualizar agora" no dashboard** — recomendado pro dia a dia desde v2.17.0. Admin clica e o sistema faz tudo (download, SHA256, backup, apply, restart, rollback se quebrar).
- **B) One-liner do GitHub** — bom pra primeiro deploy ou recuperação manual via SSH.
- **C) Pacote de update versionado** — release imutável via `update.sh`. Útil pra ambientes que precisam aplicar offline ou auditar tarballs.

---

### Opção A — Botão "Atualizar agora" via UI (recomendado)

Aba **Configurações → Sistema / Atualizações** (admin only):

1. Sistema verifica GitHub Releases a cada 6h via worker `update_checker`. Badge "↑ Update" aparece no sidebar quando há versão nova.
2. Admin clica **"Verificar atualizações"** pra forçar refresh ou **"Atualizar pra vX.Y.Z"**.
3. Modal full-screen abre com log live do update streamado via SSE.
4. Pipeline interno:
   - Download do tarball + SHA256 do GitHub Release
   - Validação de checksum
   - Snapshot `pre-restore-*.tar.gz` (rollback de emergência)
   - Backup do código + DuckDB + env file
   - Aplica via `sudo bash tools/update.sh` (sudoers granular)
   - Restart api_service + health check de 30s
   - Rollback automático se health check falhar
5. Banner final colorido + botão "Recarregar página".

**Histórico de backups** na mesma aba — admin pode restaurar qualquer backup anterior clicando "↺ Restaurar" (precisa confirmar digitando `RESTAURAR`).

**Aba "Auditoria"** mostra trilha persistente de todos updates/restores aplicados (quem, quando, de qual versão, IP, status).

**Pré-requisitos pro botão funcionar:**
- `gh` CLI instalado na máquina build (releases publicadas via `tools/release.sh`)
- `uv` instalado no servidor (vem com o install.sh atual)
- Repo público OU `GITHUB_TOKEN` em `/etc/unbound-dashboard/api-v1.env`

**Notificação por email/webhook** quando release nova: configure em **Email / SMTP** e **Webhooks** (checkbox "Notificar nova release").

---

### Opção B — One-liner do GitHub (in-place)

O `install-from-git.sh` é idempotente: rodando numa máquina que já tem o dashboard instalado, ele faz uma **atualização in-place**. O `install.sh` detecta `data/.installed` e:

- Faz **backup** automático do dir atual em `/var/www/html/unbound-dashboard.backup.<timestamp>/` (rsync source pra rollback rápido).
- **Preserva** `/etc/unbound-dashboard/api-v1.env` (com `JWT_SECRET`).
- **Preserva** o DuckDB em `/var/lib/unbound-dashboard/`.
- **Pula** a criação de admin (não precisa passar `ADMIN_*` vars).
- Re-roda `uv sync` se `pyproject.toml` mudou.
- Reaplica systemd unit + Apache conf e reinicia o `unbound-dashboard-api` (migrations DuckDB rodam no startup).

```bash
# Atualizar pra última versão do main:
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo bash

# Atualizar pra um branch específico (ex: testar feature antes do merge):
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo REPO_BRANCH=feature/x bash
```

**Rollback rápido** (Opção B) — se algo quebrar:
```bash
LAST_BACKUP=$(ls -1d /var/www/html/unbound-dashboard.backup.* | tail -1)
sudo systemctl stop unbound-dashboard-api
sudo rsync -a --delete "$LAST_BACKUP/" /var/www/html/unbound-dashboard/
sudo systemctl start unbound-dashboard-api
```

**Trade-off vs Opção A/C:** o one-liner sempre pega o `HEAD` do branch — não há `VERSION` específica imutável que você consiga "pinar" pra prod. Pra prod, prefira o botão via UI (Opção A) ou pacotes versionados (Opção C).

---

### Opção C — Pacote de update versionado (manual via SSH)

Update cirúrgico que só toca o que mudou entre versões, gera 3 backups dedicados, e produz um artefato auditável (`.tar.gz` com `VERSION` fixa).

#### 1. Gerar o pacote de update (na máquina build)

```bash
cd /var/www/html/unbound-dashboard
sudo bash tools/build-update.sh
# → gera dist/unbound-dashboard-update-v<X.Y.Z>-<TIMESTAMP>.tar.gz
```

> O script faz auto-bump da `VERSION` (patch). Use `AUTO_BUMP_VERSION=false` para pular ou `BUMP_TYPE=minor|major` para tipo diferente.

#### 2. Transferir para o servidor

```bash
scp dist/unbound-dashboard-update-v<X.Y.Z>-<TS>.tar.gz usuario@servidor:/tmp/
```

#### 3. Aplicar

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

#### Variáveis aceitas pelo `update.sh`

| Variável | Default | Efeito |
|---|---|---|
| `DRY_RUN` | `false` | Simula sem aplicar |
| `AUTO_RESTART` | `true` | Reinicia api_service + Apache ao final |
| `SKIP_VENV_SYNC` | `false` | Pula `uv sync` mesmo se `pyproject.toml` mudou |
| `VERBOSE` | `false` | Saída detalhada |

#### Rollback (Opção C)

```bash
sudo systemctl stop unbound-dashboard-api
sudo tar xzf /var/backups/unbound-dashboard/dashboard-<TS>.tar.gz -C /
sudo cp -a /var/backups/unbound-dashboard/duckdb-<TS>.duckdb /var/lib/unbound-dashboard/unbound_dash.duckdb
sudo cp -a /var/backups/unbound-dashboard/api-v1.env-<TS> /etc/unbound-dashboard/api-v1.env
sudo systemctl start unbound-dashboard-api
```

---

## 🐳 Smoke Test em Container (opcional)

Antes de instalar em produção, você pode validar o pacote em um container Debian 13 isolado:

```bash
# 1. Gerar o pacote (se ainda não existe):
sudo bash tools/build-package.sh

# 2. Em uma máquina com Docker:
sudo bash tools/docker/smoke-test.sh
```

O script:

1. Builda a imagem `unbound-dashboard-smoke:latest` a partir de `tools/docker/Dockerfile.smoke`
2. Executa `install.sh` dentro do container (com `systemctl` stubado, já que containers normais não têm systemd)
3. Valida que `.venv` foi criado, `/etc/unbound-dashboard/api-v1.env` foi populado com `JWT_SECRET` real e o admin foi criado
4. Sobe o `uvicorn` manualmente e faz smoke `/api/v1/healthz`

Útil para CI/CD ou checagem rápida antes de deploy real. Para inspecionar manualmente:

```bash
sudo docker run --rm -it unbound-dashboard-smoke:latest bash
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

### LogWatcher não recebe queries (Live Stream vazio)

Sintoma: o resolver responde `dig` normalmente, mas a página Live Stream e a tabela `query_logs` ficam vazias. Provável: drop-in `unbound.service.d/logfile.conf` não foi aplicado.

```bash
ls -la /etc/systemd/system/unbound.service.d/logfile.conf
# Se não existir:
sudo cp /var/www/html/unbound-dashboard/api_service/deployments/systemd/unbound.service.d/logfile.conf \
        /etc/systemd/system/unbound.service.d/
sudo systemctl daemon-reload && sudo systemctl restart unbound
# Confirma:
ls -la /var/log/unbound/unbound.log  # tamanho > 0
```

### `/api/v1/cluster/peer-ping` retorna 404 após update

Significa que o serviço subiu carregando bytecode antigo do `main.py`. Resolvido em **v2.103.2** (`update.sh` limpa `__pycache__` automaticamente). Workaround manual em versões anteriores:

```bash
sudo find /var/www/html/unbound-dashboard/api_service/app -name __pycache__ -exec rm -rf {} +
sudo systemctl restart unbound-dashboard-api
```

---

## 🧩 Integração via API + SDKs (opcional)

Pra automatizar tarefas via scripts externos, dashboards de terceiros ou bots, gere um **API token escopado** e use o SDK Python ou JS distribuído com o repo.

### 1. Criar token escopado

Em `/config.php` → aba **API Tokens** → "Gerar novo token":
- Label clara (ex: `bot-allowlist`, `monitoring-readonly`)
- ✅ Marque **"🔒 Restringir capabilities"**
- Selecione apenas as caps que o bot precisa (ver receitas comuns em `/api_docs.php`)
- Copie o `raw_token` exibido — **só aparece uma vez**

### 2. Usar o SDK Python

```bash
cd /var/www/html/unbound-dashboard/clients/python
pip install -e .
```

```python
from unbound_dashboard_client import AuthenticatedClient
from unbound_dashboard_client.api.blocklist import (
    list_exceptions_api_v1_blocklist_exceptions_get,
)

client = AuthenticatedClient(
    base_url="https://seu-dashboard.exemplo.com",
    token="<TOKEN>",
    prefix="",
    auth_header_name="X-Api-Token",
)
result = list_exceptions_api_v1_blocklist_exceptions_get.sync(client=client)
print(f"Allowlist tem {result.count} domínios")
```

### 3. Usar o SDK TypeScript/JS

```typescript
import { OpenAPI, BlocklistService } from "./clients/js";

OpenAPI.BASE = "https://seu-dashboard.exemplo.com";
OpenAPI.HEADERS = { "X-Api-Token": "..." };

const result = await BlocklistService.listExceptionsApiV1BlocklistExceptionsGet();
```

Detalhes em [clients/README.md](clients/README.md), [clients/python/README.md](clients/python/README.md) e [clients/js/README.md](clients/js/README.md).

Para re-gerar os SDKs após mudanças no schema da API:

```bash
sudo bash /var/www/html/unbound-dashboard/tools/gen_sdk_python.sh
sudo bash /var/www/html/unbound-dashboard/tools/gen_sdk_js.sh
```

> O `gen_sdk_python.sh` precisa de `uv` (já vem com o install.sh). O `gen_sdk_js.sh` precisa de `npm/npx` — instale com `sudo apt install npm` se ausente.

---

## 🌐 Setup do Cluster HA (opcional)

Para alta disponibilidade entre dois ou mais servidores Unbound Dashboard, configure peers HA — ver guia completo em [docs/pages/cluster.md](docs/pages/cluster.md). Resumo:

1. Ambos servidores precisam estar na mesma versão (v2.103.0+ pro cluster autenticado)
2. Ambos com `SECRETS_MASTER_KEY` configurada (Passo 3.5 acima)
3. **No servidor A**: `/cluster.php` → Adicionar peer "SRV-B" com Token existente em branco → copia o token T do popup
4. **No servidor B**: `/cluster.php` → Adicionar peer "SRV-A" colando T em Token existente
5. Clica "Check" em ambos os lados — deve virar `ok` 🔐

Cenário corretivo (se já criou os dois lados sem coordenar): use o botão 🔑 em qualquer um dos peers pra substituir o token e alinhar.

---

## 🏁 Conclusão

Seu Unbound Dashboard está pronto. Para o detalhamento técnico de cada componente, consulte [docs/README.md](docs/README.md).
