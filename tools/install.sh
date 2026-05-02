#!/bin/bash
# ============================================================
# Unbound Dashboard v2 — Instalador Automatizado
# Debian 12/13 · Ubuntu 22.04+
#
# Uso: sudo bash install.sh
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
info() { echo -e "${CYAN}[i]${NC} $1"; }
step() { echo -e "\n${BOLD}── $1 ──${NC}"; }

detect_php_fpm_service() {
    local svc
    svc=$(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null | awk '{print $1}' | head -n1 || true)
    echo "$svc"
}

detect_php_fpm_socket() {
    local svc ver sock

    for sock in /run/php/php-fpm.sock /run/php/php*-fpm.sock; do
        [ -S "$sock" ] && {
            echo "$sock"
            return
        }
    done

    svc="$(detect_php_fpm_service)"
    if [ -n "$svc" ]; then
        ver="${svc#php}"
        ver="${ver%-fpm.service}"
        echo "/run/php/php${ver}-fpm.sock"
        return
    fi

    echo "/run/php/php-fpm.sock"
}

require_cmd() {
    local cmd="$1"
    command -v "$cmd" >/dev/null 2>&1 || err "Dependência ausente: $cmd"
}

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash install.sh"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SRC="$SCRIPT_DIR/app"
SYSTEM_SRC="$SCRIPT_DIR/system"

[ -d "$APP_SRC" ] || err "Diretório app/ não encontrado. Extraia o pacote completo antes de instalar."

VERSION=$(cat "$APP_SRC/VERSION" 2>/dev/null || echo "2.0.0")
INSTALL_DIR="/opt/unbound-dashboard"
WEB_DIR="/var/www/html/unbound-dashboard"
DATA_DIR="/var/lib/unbound-dashboard"
LOG_DIR="/var/log/unbound-dashboard"
ENV_DIR="/etc/unbound-dashboard"
SERVICE_USER="${SERVICE_USER:-unbound-dash}"
WEB_SERVER="${WEB_SERVER:-caddy}"
CADDY_SITE="${CADDY_SITE:-}"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Unbound Dashboard v2 — Instalador v${VERSION}          ║${NC}"
echo -e "${BOLD}║   Debian 12/13 · Ubuntu 22.04+                       ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================
# MODO DE PUBLICAÇÃO (Caddy/Apache)
# ============================================================
if [ -t 0 ]; then
    echo -n "Usuário de serviço da aplicação (padrão: ${SERVICE_USER}): "
    read -r INPUT_SERVICE_USER
    if [ -n "${INPUT_SERVICE_USER:-}" ]; then
        SERVICE_USER="$INPUT_SERVICE_USER"
    fi

    info "Servidor web para publicar o dashboard:"
    echo "  1) Caddy (recomendado)"
    echo "  2) Apache2"
    echo -n "  Escolha [1/2] (padrão: 1): "
    read -r WEB_CHOICE

    case "${WEB_CHOICE:-1}" in
        1) WEB_SERVER="caddy" ;;
        2) WEB_SERVER="apache2" ;;
        *) warn "Opção inválida, usando Caddy"; WEB_SERVER="caddy" ;;
    esac

    if [ "$WEB_SERVER" = "caddy" ] && [ -z "$CADDY_SITE" ]; then
        echo -n "  Domínio do dashboard (ex: dashboard.seudominio.com, vazio para :80): "
        read -r CADDY_SITE
    fi
fi

if [ "$WEB_SERVER" = "caddy" ] && [ -z "$CADDY_SITE" ]; then
    CADDY_SITE=":80"
fi

if [ "$WEB_SERVER" = "caddy" ]; then
    # Normaliza entrada comum do usuário: remove esquema e path.
    CADDY_SITE="${CADDY_SITE#http://}"
    CADDY_SITE="${CADDY_SITE#https://}"
    CADDY_SITE="${CADDY_SITE%%/*}"

    if [ -z "$CADDY_SITE" ]; then
        CADDY_SITE=":80"
    fi

    if ! echo "$CADDY_SITE" | grep -Eq '^(:[0-9]{2,5}|localhost|\*\.[A-Za-z0-9.-]+|[A-Za-z0-9.-]+)$'; then
        err "Site Caddy inválido: '$CADDY_SITE' (use domínio, localhost, *.dominio ou :80)"
    fi
fi

if ! echo "$SERVICE_USER" | grep -Eq '^[a-z_][a-z0-9_-]*[$]?$'; then
    err "Usuário de serviço inválido: '$SERVICE_USER'"
fi

log "Servidor web selecionado: $WEB_SERVER"
log "Usuário de serviço: $SERVICE_USER"
if [ "$WEB_SERVER" = "caddy" ]; then
    log "Site Caddy: $CADDY_SITE"
fi

# ============================================================
# ETAPA 1 — Sistema Operacional
# ============================================================
step "Etapa 1 — Sistema Operacional"

[ -f /etc/os-release ] || err "/etc/os-release não encontrado."
# shellcheck source=/dev/null
source /etc/os-release

case "$ID" in
    debian)
        [[ "${VERSION_ID:-0}" -ge 12 ]] || err "Debian $VERSION_ID não suportado. Necessário Debian 12+."
        log "Debian $VERSION_ID detectado"
        ;;
    ubuntu)
        MAJOR=$(echo "${VERSION_ID:-0}" | cut -d. -f1)
        [[ "$MAJOR" -ge 22 ]] || err "Ubuntu $VERSION_ID não suportado. Necessário Ubuntu 22.04+."
        log "Ubuntu $VERSION_ID detectado"
        ;;
    *)
        err "SO '$ID' não suportado. Use Debian 12+ ou Ubuntu 22.04+."
        ;;
esac

# ============================================================
# ETAPA 2 — Dependências do sistema
# ============================================================
step "Etapa 2 — Dependências do sistema"

info "Atualizando repositórios..."
apt-get update -qq

PACKAGES=(
    python3
    python3-venv
    python3-pip
    python3-dev
    php
    php-cli
    php-curl
    php-redis
    php-mbstring
    php-xml
    php-fpm
    apache2
    curl
    wget
    git
    rsync
    openssl
    ca-certificates
    gnupg
    apt-transport-https
    unbound
    redis-server
    sudo
    libssl-dev
    build-essential
)
info "Instalando pacotes base..."
apt-get install -y -qq "${PACKAGES[@]}"
log "Pacotes base instalados"

# Python 3.12+ é requisito do projeto
PYTHON_BIN=""
for py in python3.13 python3.12 python3; do
    if command -v "$py" &>/dev/null; then
        if "$py" - <<'PYEOF' >/dev/null 2>&1
import sys
raise SystemExit(0 if sys.version_info >= (3, 12) else 1)
PYEOF
        then
            PYTHON_BIN="$py"
            break
        fi
    fi
done

[ -n "$PYTHON_BIN" ] || err "Python >= 3.12 não encontrado. Instale Python 3.12+ e execute novamente."
log "Python: $($PYTHON_BIN --version)"

# Node.js 20 LTS
if ! command -v node &>/dev/null || [[ "$(node --version | cut -d. -f1 | tr -d 'v')" -lt 20 ]]; then
    info "Instalando Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null
    apt-get install -y -qq nodejs
fi
log "Node.js: $(node --version)"

# uv (gerenciador de pacotes Python)
if ! command -v uv &>/dev/null; then
    info "Instalando uv..."
    curl -LsSf https://astral.sh/uv/install.sh | sh
fi

UV_BIN="$(command -v uv || true)"
if [ -z "$UV_BIN" ] && [ -x "/root/.local/bin/uv" ]; then
    UV_BIN="/root/.local/bin/uv"
fi
[ -n "$UV_BIN" ] || err "uv não foi encontrado após instalação."
log "uv: $($UV_BIN --version)"

# Caddy
if ! command -v caddy &>/dev/null; then
    info "Instalando Caddy..."
    apt-get install -y -qq debian-keyring debian-archive-keyring apt-transport-https curl
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -qq
    apt-get install -y -qq caddy
fi
log "Caddy: $(caddy version 2>/dev/null | head -1 || echo 'instalado')"

for cmd in "$PYTHON_BIN" "$UV_BIN" unbound redis-server caddy node npm rsync php; do
    require_cmd "$cmd"
done
log "Dependências de runtime validadas"

# ============================================================
# ETAPA 3 — Usuário e diretórios
# ============================================================
step "Etapa 3 — Usuário e diretórios"

if ! id "$SERVICE_USER" &>/dev/null; then
    useradd --system --no-create-home --shell /usr/sbin/nologin "$SERVICE_USER"
    log "Usuário $SERVICE_USER criado"
else
    log "Usuário $SERVICE_USER já existe"
fi

# Adicionar ao grupo unbound para acessar unbound-control
if getent group unbound &>/dev/null; then
    usermod -aG unbound "$SERVICE_USER" || true
fi

for dir in "$INSTALL_DIR" "$WEB_DIR" "$DATA_DIR" "$LOG_DIR" "$ENV_DIR"; do
    mkdir -p "$dir"
done

chown -R "$SERVICE_USER:$SERVICE_USER" "$DATA_DIR" "$LOG_DIR"
chmod 750 "$ENV_DIR"
log "Diretórios criados"

# Diretório de log do Unbound (necessário para logfile: em general.conf)
mkdir -p /var/log/unbound
chown unbound:unbound /var/log/unbound 2>/dev/null || chown root:root /var/log/unbound
chmod 755 /var/log/unbound
if [ ! -f /var/log/unbound/unbound.log ]; then
    touch /var/log/unbound/unbound.log
    chown unbound:unbound /var/log/unbound/unbound.log 2>/dev/null || true
fi
log "Diretório /var/log/unbound configurado"

# Configura logfile: no Unbound se general.conf existir e ainda não tiver a diretiva
UNBOUND_GENERAL="/etc/unbound/includes/general.conf"
if [ -f "$UNBOUND_GENERAL" ] && ! grep -q 'logfile:' "$UNBOUND_GENERAL"; then
    # Insere após log-time-ascii: yes (se existir) ou no final do arquivo
    if grep -q 'log-time-ascii:' "$UNBOUND_GENERAL"; then
        sed -i '/log-time-ascii:/a\    logfile: "/var/log/unbound/unbound.log"' "$UNBOUND_GENERAL"
    else
        echo '    logfile: "/var/log/unbound/unbound.log"' >> "$UNBOUND_GENERAL"
    fi
    log "logfile: adicionado em $UNBOUND_GENERAL"
    systemctl restart unbound 2>/dev/null || true
elif [ ! -f "$UNBOUND_GENERAL" ]; then
    warn "$UNBOUND_GENERAL não encontrado — configure manualmente: logfile: \"/var/log/unbound/unbound.log\""
fi

# ============================================================
# ETAPA 4 — Instalar código-fonte
# ============================================================
step "Etapa 4 — Instalar código-fonte"

info "Copiando aplicação para $INSTALL_DIR..."
rsync -a --delete \
    --exclude='.venv' \
    --exclude='__pycache__' \
    --exclude='*.pyc' \
    "$APP_SRC/" "$INSTALL_DIR/"

chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR"
# Pasta .venv deve poder ser escrita pelo instalador (root) — ajusta depois
log "Código instalado em $INSTALL_DIR"

info "Publicando frontend PHP v1 em $WEB_DIR..."
rsync -a --delete \
    --exclude='.git' \
    --exclude='.venv' \
    --exclude='app/' \
    --exclude='frontend/' \
    --exclude='migrations/' \
    --exclude='tests/' \
    --exclude='deployments/' \
    --exclude='tools/' \
    --exclude='dist/' \
    --exclude='*.tar.gz' \
    --exclude='uv.lock' \
    --exclude='pyproject.toml' \
    "$APP_SRC/" "$WEB_DIR/"
chown -R www-data:www-data "$WEB_DIR"
log "Frontend PHP publicado"

# ============================================================
# ETAPA 5 — Virtualenv e dependências Python
# ============================================================
step "Etapa 5 — Dependências Python"

info "Criando virtualenv com uv..."
cd "$INSTALL_DIR"
"$UV_BIN" venv .venv --python "$PYTHON_BIN" --quiet

mapfile -t PY_DEPS < <("$PYTHON_BIN" - <<'PYEOF'
import tomllib
from pathlib import Path

data = tomllib.loads(Path("pyproject.toml").read_text(encoding="utf-8"))
for dep in data.get("project", {}).get("dependencies", []):
    print(dep)
PYEOF
)

[ "${#PY_DEPS[@]}" -gt 0 ] || err "Nenhuma dependência encontrada em pyproject.toml"
"$UV_BIN" pip install --quiet --no-cache --python .venv/bin/python "${PY_DEPS[@]}"

# Valida import essencial do banco usado pelo sistema (DuckDB)
.venv/bin/python - <<'PYEOF'
import duckdb
print(duckdb.__version__)
PYEOF

# Ajusta permissões do venv
chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR/.venv"
log "Dependências Python instaladas"

# ============================================================
# ETAPA 6 — Arquivo de ambiente
# ============================================================
step "Etapa 6 — Configuração de ambiente"

if [ ! -f "$ENV_DIR/env" ]; then
    cp "$SYSTEM_SRC/env/unbound-dashboard.env" "$ENV_DIR/env"
    chmod 640 "$ENV_DIR/env"
    chown root:"$SERVICE_USER" "$ENV_DIR/env"

    # Gera JWT_SECRET automaticamente
    JWT_SECRET=$(openssl rand -hex 32)
    sed -i "s/JWT_SECRET=CHANGE_ME_BEFORE_STARTING/JWT_SECRET=${JWT_SECRET}/" "$ENV_DIR/env"
    log "Arquivo de ambiente criado em $ENV_DIR/env (JWT_SECRET gerado)"
else
    warn "Arquivo $ENV_DIR/env já existe — não sobrescrito"
fi

if [ ! -f /etc/unbound-dashboard.env ]; then
    cat > /etc/unbound-dashboard.env << 'ENVPHP'
# Ambiente do frontend PHP v1 -> backend v2 (FastAPI/DuckDB)
API_V2_URL=http://127.0.0.1:8000
API_V2_TIMEOUT=5
REDIS_URL=redis://127.0.0.1:6379/0
ENVPHP
    chmod 640 /etc/unbound-dashboard.env
    chown root:www-data /etc/unbound-dashboard.env
    log "Arquivo de ambiente PHP criado em /etc/unbound-dashboard.env"
else
    grep -q '^API_V2_URL=' /etc/unbound-dashboard.env || echo 'API_V2_URL=http://127.0.0.1:8000' >> /etc/unbound-dashboard.env
    grep -q '^API_V2_TIMEOUT=' /etc/unbound-dashboard.env || echo 'API_V2_TIMEOUT=5' >> /etc/unbound-dashboard.env
    grep -q '^REDIS_URL=' /etc/unbound-dashboard.env || echo 'REDIS_URL=redis://127.0.0.1:6379/0' >> /etc/unbound-dashboard.env
    log "Arquivo /etc/unbound-dashboard.env atualizado"
fi

# ============================================================
# ETAPA 7 — Migrations DuckDB
# ============================================================
step "Etapa 7 — Migrations do banco de dados"

info "Executando migrations DuckDB..."
DB_PATH="$DATA_DIR/unbound_dash.duckdb"
export DB_PATH
export REDIS_URL="redis://127.0.0.1:6379/0"
export JWT_SECRET=$(grep JWT_SECRET "$ENV_DIR/env" | cut -d= -f2)

cd "$INSTALL_DIR"
sudo -u "$SERVICE_USER" .venv/bin/python -m app.db.migrate || {
    # Fallback: rodar como root para a primeira execução
    .venv/bin/python -m app.db.migrate
    chown "$SERVICE_USER:$SERVICE_USER" "$DB_PATH" 2>/dev/null || true
}
chown "$SERVICE_USER:$SERVICE_USER" "$DATA_DIR" "$DB_PATH" 2>/dev/null || true
chmod 750 "$DATA_DIR" 2>/dev/null || true
chmod 660 "$DB_PATH" 2>/dev/null || true
log "Migrations executadas"

if grep -q '^MYSQL_URL=' "$ENV_DIR/env" 2>/dev/null; then
    warn "MYSQL_URL detectado em $ENV_DIR/env, mas o v2 usa exclusivamente DuckDB (DB_PATH)."
fi

# ============================================================
# ETAPA 8 — Serviço systemd
# ============================================================
step "Etapa 8 — Serviço systemd"

cp "$SYSTEM_SRC/systemd/unbound-api.service" /etc/systemd/system/
SAFE_SERVICE_USER="${SERVICE_USER//&/\\&}"
sed -i "s|{{SERVICE_USER}}|${SAFE_SERVICE_USER}|g" /etc/systemd/system/unbound-api.service
systemctl daemon-reload
systemctl enable unbound-api
systemctl enable redis-server
systemctl start redis-server
systemctl start unbound-api

PHP_FPM_SERVICE="$(detect_php_fpm_service)"
if [ -n "$PHP_FPM_SERVICE" ]; then
    systemctl enable "$PHP_FPM_SERVICE" >/dev/null 2>&1 || true
    systemctl start "$PHP_FPM_SERVICE" >/dev/null 2>&1 || true
    log "PHP-FPM ativo: $PHP_FPM_SERVICE"
else
    warn "Serviço PHP-FPM não detectado automaticamente; verifique o backend PHP manualmente."
fi

if [ "$WEB_SERVER" = "apache2" ]; then
    systemctl enable apache2
    systemctl start apache2
else
    systemctl disable apache2 >/dev/null 2>&1 || true
    systemctl stop apache2 >/dev/null 2>&1 || true
fi

log "Serviço unbound-api instalado e iniciado"

# ============================================================
# ETAPA 9 — Caddy
# ============================================================
step "Etapa 9 — Caddy (reverse proxy)"

if [ "$WEB_SERVER" = "caddy" ]; then
    cp "$SYSTEM_SRC/caddy/Caddyfile" /etc/caddy/Caddyfile
    SAFE_CADDY_SITE="${CADDY_SITE//&/\\&}"
    sed -i "s|dashboard.example.com|${SAFE_CADDY_SITE}|g" /etc/caddy/Caddyfile

    PHP_FPM_SOCK="$(detect_php_fpm_socket)"
    SAFE_PHP_FPM_SOCK="${PHP_FPM_SOCK//&/\\&}"
    sed -i "s|{{PHP_FPM_SOCK}}|${SAFE_PHP_FPM_SOCK}|g" /etc/caddy/Caddyfile
    log "Socket PHP-FPM configurado no Caddy: $PHP_FPM_SOCK"

    if ss -ltnp '( sport = :80 or sport = :443 )' 2>/dev/null | grep -q apache2; then
        warn "Apache2 ainda está ocupando porta 80/443. Tentando parar novamente..."
        systemctl stop apache2 >/dev/null 2>&1 || true
    fi

    if ss -ltnp '( sport = :80 or sport = :443 )' 2>/dev/null | grep -q apache2; then
        err "Portas 80/443 continuam em uso pelo Apache2. Pare o Apache2 e execute novamente."
    fi

    if ! caddy validate --config /etc/caddy/Caddyfile >/tmp/caddy-validate.log 2>&1; then
        warn "Falha na validação do Caddyfile:"
        cat /tmp/caddy-validate.log || true
        err "Caddyfile inválido em /etc/caddy/Caddyfile"
    fi

    systemctl enable caddy
    if ! systemctl restart caddy; then
        warn "Caddy não iniciou corretamente. Últimas linhas do journal:"
        journalctl -u caddy -n 40 --no-pager || true
        err "Falha ao iniciar Caddy"
    fi

    if ! systemctl is-active --quiet caddy; then
        warn "Caddy não está ativo após restart."
        journalctl -u caddy -n 40 --no-pager || true
        err "Caddy inativo"
    fi

    log "Caddy habilitado e iniciado em 80/443"
    log "Caddyfile configurado para: $CADDY_SITE"
    warn "Se usar domínio público, aponte DNS para este servidor para emissão TLS automática."
else
    if [ ! -f /etc/caddy/Caddyfile ] || grep -q 'dashboard.example.com' /etc/caddy/Caddyfile 2>/dev/null; then
        cp "$SYSTEM_SRC/caddy/Caddyfile" /etc/caddy/Caddyfile
        warn "Caddyfile instalado em /etc/caddy/Caddyfile (opcional)"
    else
        warn "Caddyfile já existe em /etc/caddy/Caddyfile — não sobrescrito"
    fi
    systemctl disable caddy >/dev/null 2>&1 || true
    systemctl stop caddy >/dev/null 2>&1 || true
    log "Caddy desabilitado (Apache2 em uso)"
fi

# ============================================================
# ETAPA 10 — Criar usuário admin inicial
# ============================================================
step "Etapa 10 — Usuário administrador"

echo ""
info "Crie o primeiro usuário administrador:"
echo -n "  Username: "
read -r ADMIN_USER
echo -n "  Senha: "
read -rs ADMIN_PASS
echo ""

if [ -n "$ADMIN_USER" ] && [ -n "$ADMIN_PASS" ]; then
    cd "$INSTALL_DIR"
    export DB_PATH JWT_SECRET
    .venv/bin/python - <<PYEOF
import asyncio, sys
sys.path.insert(0, '.')
from app.services.auth_service import AuthService
from app.repositories.duckdb.user_repo import UserRepository
from app.core.security import hash_password
from app.repositories.duckdb.connection import db_execute
from app.domain.user import Role

async def create():
    repo = UserRepository()
    existing = await repo.find_by_username('$ADMIN_USER')
    if existing:
        pw_hash = hash_password('$ADMIN_PASS')
        await db_execute(
            "UPDATE users SET password_hash = ?, role = ?, is_active = TRUE, "
            "failed_logins = 0, locked_until = NULL, updated_at = NOW() WHERE username = ?",
            [pw_hash, Role.ADMIN.value, '$ADMIN_USER'],
        )
        print('Usuário já existe — senha e role atualizados.')
    else:
        svc = AuthService(repo)
        await svc.create_user('$ADMIN_USER', '$ADMIN_PASS', Role.ADMIN)
        print('Usuário admin criado com sucesso.')

asyncio.run(create())
PYEOF
    log "Usuário '$ADMIN_USER' criado"
else
    warn "Nenhum usuário criado. Use a API para criar: POST /api/v2/auth/register"
fi

# ============================================================
# RESUMO
# ============================================================
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         Instalação concluída com sucesso! ✓          ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
log "Versão instalada: $VERSION"
log "API:     http://127.0.0.1:8000  (acesso interno)"
log "Docs:    http://127.0.0.1:8000/docs"
log "Status:  systemctl status unbound-api"
log "Logs:    journalctl -u unbound-api -f"
echo ""
warn "Próximos passos:"
echo "  1. Edite /etc/caddy/Caddyfile com seu domínio"
echo "  2. Execute: sudo systemctl reload caddy"
echo "  3. Verifique: sudo systemctl status unbound-api caddy redis-server"
echo ""
