#!/bin/bash
# ============================================================
# Unbound Dashboard — Instalador v2.2.0+
# Stack: PHP (frontend) + FastAPI/DuckDB/Redis (backend) + Apache + Unbound
# Suporte: Debian 12/13 e Ubuntu 22.04+
#
# Uso:
#   sudo bash install.sh
#
# Variáveis opcionais (não-interativo):
#   ADMIN_USERNAME=admin ADMIN_PASSWORD='senha' ADMIN_EMAIL=a@b.c \
#       sudo -E bash install.sh
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

if [ "$EUID" -ne 0 ]; then
    err "Execute como root: sudo bash install.sh"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DASHBOARD_SRC="$SCRIPT_DIR/dashboard"
APISERVICE_SRC="$SCRIPT_DIR/api_service"
SYSTEM_SRC="$SCRIPT_DIR/system"

[ -d "$DASHBOARD_SRC" ]  || err "Diretório 'dashboard/' não encontrado em $SCRIPT_DIR"
[ -d "$APISERVICE_SRC" ] || err "Diretório 'api_service/' não encontrado em $SCRIPT_DIR"
[ -d "$SYSTEM_SRC" ]     || err "Diretório 'system/' não encontrado em $SCRIPT_DIR"

INSTALL_DIR="/var/www/html/unbound-dashboard"
APISERVICE_DIR="$INSTALL_DIR/api_service"
ETC_DIR="/etc/unbound-dashboard"
DUCKDB_DIR="/var/lib/unbound-dashboard"
ENV_FILE="$ETC_DIR/api-v1.env"
VERSION=$(tr -d '[:space:]' < "$DASHBOARD_SRC/VERSION" 2>/dev/null || echo "0.0.0")

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  Unbound Dashboard — Instalador v${VERSION}              ║${NC}"
echo -e "${BOLD}║  PHP + FastAPI/DuckDB/Redis (sem MariaDB)            ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================
# 1. Detecção do SO
# ============================================================
step "Etapa 1/8 — Detecção do Sistema Operacional"

[ -f /etc/os-release ] || err "/etc/os-release ausente — SO não reconhecido."
source /etc/os-release

case "$ID" in
    debian)
        if [ "${VERSION_ID:-0}" -lt 12 ]; then
            err "Debian $VERSION_ID detectado. Necessário Debian 12+."
        fi
        log "Debian $VERSION_ID ($VERSION_CODENAME) detectado"
        ;;
    ubuntu)
        MAJOR=$(echo "${VERSION_ID:-0}" | cut -d. -f1)
        if [ "$MAJOR" -lt 22 ]; then
            err "Ubuntu $VERSION_ID detectado. Necessário Ubuntu 22.04+."
        fi
        log "Ubuntu $VERSION_ID ($VERSION_CODENAME) detectado"
        ;;
    *)
        err "SO '$ID' não suportado. Apenas Debian 12+ e Ubuntu 22.04+."
        ;;
esac

# ============================================================
# 2. Pacotes APT
# ============================================================
step "Etapa 2/8 — Dependências do Sistema (apt)"

info "Atualizando índices do apt..."
apt-get update -qq

PACKAGES=(
    apache2
    libapache2-mod-php
    php
    php-cli
    php-curl
    php-mbstring
    php-xml
    php-common
    sudo
    redis-server
    python3
    python3-venv
    python3-dev
    build-essential
    unbound
    rsyslog
    dnsutils
    traceroute
    dns-root-data
    curl
    wget
    rsync
    openssl
    ca-certificates
)

info "Instalando pacotes..."
for pkg in "${PACKAGES[@]}"; do
    if dpkg -l "$pkg" &>/dev/null; then
        :  # já instalado, silencioso pra reduzir ruído
    else
        info "  → $pkg"
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$pkg" || warn "Falha em $pkg, continuando"
    fi
done
log "Pacotes APT processados"

# Validação versões mínimas
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0")
[[ "$PHP_VER" =~ ^([0-9]+)\.([0-9]+)$ ]] || err "PHP não detectado"
[ "${BASH_REMATCH[1]}" -lt 8 ] && err "PHP $PHP_VER detectado, requer 8.1+"
log "PHP $PHP_VER OK"

PY_VER=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")' 2>/dev/null || echo "0")
[[ "$PY_VER" =~ ^([0-9]+)\.([0-9]+)$ ]] || err "Python3 não detectado"
PY_MAJ=${BASH_REMATCH[1]}
PY_MIN=${BASH_REMATCH[2]}
if [ "$PY_MAJ" -lt 3 ] || ([ "$PY_MAJ" -eq 3 ] && [ "$PY_MIN" -lt 11 ]); then
    err "Python $PY_VER detectado, requer 3.11+"
fi
log "Python $PY_VER OK"

# ============================================================
# 3. Apache: módulos do reverse proxy
# ============================================================
step "Etapa 3/8 — Apache: habilitar módulos de proxy"

a2enmod proxy proxy_http proxy_wstunnel headers rewrite >/dev/null 2>&1 || warn "a2enmod retornou erro"
log "Módulos: proxy, proxy_http, proxy_wstunnel, headers, rewrite"

# ============================================================
# 4. uv (gerenciador Python) + venv do api_service
# ============================================================
step "Etapa 4/8 — uv + venv do api_service"

UV_BIN="/usr/local/bin/uv"
if [ ! -x "$UV_BIN" ]; then
    info "Instalando uv (https://docs.astral.sh/uv/)..."
    curl -fsSL https://astral.sh/uv/install.sh -o /tmp/uv-install.sh
    UV_INSTALL_DIR=/usr/local/bin sh /tmp/uv-install.sh -q || {
        # fallback se o instalador não respeitar UV_INSTALL_DIR — copia do default
        if [ -x "/root/.local/bin/uv" ]; then
            cp /root/.local/bin/uv "$UV_BIN"
            chmod +x "$UV_BIN"
        fi
    }
    rm -f /tmp/uv-install.sh
fi
[ -x "$UV_BIN" ] || err "uv não foi instalado em $UV_BIN"
log "uv $(uv --version 2>/dev/null | awk '{print $2}') instalado"

# ============================================================
# 5. Deploy: dashboard PHP + api_service + venv
# ============================================================
step "Etapa 5/8 — Deploy (dashboard + api_service)"

# Backup defensivo se já existir
if [ -d "$INSTALL_DIR" ]; then
    BACKUP="${INSTALL_DIR}.backup.$(date +%Y%m%d-%H%M%S)"
    info "Backup do install anterior em $BACKUP"
    cp -a "$INSTALL_DIR" "$BACKUP"
fi

mkdir -p "$INSTALL_DIR"

info "Copiando dashboard PHP..."
rsync -a --delete \
    --exclude='data/.installed' \
    --exclude='api_service' \
    "$DASHBOARD_SRC/" "$INSTALL_DIR/"
log "Frontend PHP em $INSTALL_DIR"

info "Copiando api_service..."
mkdir -p "$APISERVICE_DIR"
rsync -a --delete \
    --exclude='.venv' \
    "$APISERVICE_SRC/" "$APISERVICE_DIR/"
log "api_service em $APISERVICE_DIR"

# Diretórios mutáveis
mkdir -p "$INSTALL_DIR/data/tmp" "$INSTALL_DIR/src/data/tmp"

# DuckDB path
mkdir -p "$DUCKDB_DIR"
chown www-data:www-data "$DUCKDB_DIR"
chmod 750 "$DUCKDB_DIR"
log "DuckDB dir: $DUCKDB_DIR (www-data:www-data, 750)"

# Backups dir
mkdir -p /var/backups/unbound-dashboard
chown root:root /var/backups/unbound-dashboard
chmod 750 /var/backups/unbound-dashboard

# Sync deps Python via uv (cria .venv dentro de api_service/)
info "Criando venv + instalando deps via uv sync..."
(
    cd "$APISERVICE_DIR"
    UV_PYTHON="$(command -v python3)" "$UV_BIN" sync --no-dev --quiet
)
[ -x "$APISERVICE_DIR/.venv/bin/uvicorn" ] || err ".venv do api_service não foi criado corretamente"
log "venv pronto em $APISERVICE_DIR/.venv"

# ============================================================
# 6. Configuração: env file + JWT + systemd + apache conf
# ============================================================
step "Etapa 6/8 — Config (env, systemd, apache)"

# /etc/unbound-dashboard/api-v1.env
mkdir -p "$ETC_DIR"
chmod 750 "$ETC_DIR"
chown root:www-data "$ETC_DIR"

if [ -f "$ENV_FILE" ]; then
    info "Env file existente preservado em $ENV_FILE"
else
    if [ -f "$SYSTEM_SRC/etc/api-v1.env.example" ]; then
        cp "$SYSTEM_SRC/etc/api-v1.env.example" "$ENV_FILE"
    else
        err "Template api-v1.env.example não encontrado em $SYSTEM_SRC/etc/"
    fi
    JWT_SECRET=$(openssl rand -hex 32)
    sed -i "s|JWT_SECRET=.*|JWT_SECRET=${JWT_SECRET}|" "$ENV_FILE"
    chown root:www-data "$ENV_FILE"
    chmod 640 "$ENV_FILE"
    log "Env file criado em $ENV_FILE com JWT_SECRET aleatório"
fi

# Sudoers
if [ -f "$SYSTEM_SRC/sudoers/unbound-dashboard" ]; then
    cp "$SYSTEM_SRC/sudoers/unbound-dashboard" /etc/sudoers.d/unbound-dashboard
    chmod 440 /etc/sudoers.d/unbound-dashboard
    visudo -c -f /etc/sudoers.d/unbound-dashboard >/dev/null || err "Sudoers inválido"
    log "Sudoers instalado e validado"
fi

# Systemd unit
if [ -f "$SYSTEM_SRC/systemd/unbound-dashboard-api.service" ]; then
    cp "$SYSTEM_SRC/systemd/unbound-dashboard-api.service" /etc/systemd/system/
    systemctl daemon-reload
    log "Systemd unit unbound-dashboard-api instalada"
else
    err "Systemd unit ausente em $SYSTEM_SRC/systemd/"
fi

# Apache conf-available + a2enconf
if [ -f "$SYSTEM_SRC/apache/unbound-dashboard-api.conf" ]; then
    cp "$SYSTEM_SRC/apache/unbound-dashboard-api.conf" /etc/apache2/conf-available/
    a2enconf unbound-dashboard-api >/dev/null
    log "Apache conf habilitado: /api/v1/* → 127.0.0.1:8001"
else
    err "Apache conf ausente em $SYSTEM_SRC/apache/"
fi

# Health-fix + setup-unbound-logs
for sh in unbound-health-fix.sh setup-unbound-logs.sh; do
    if [ -f "$SYSTEM_SRC/bin/$sh" ]; then
        cp "$SYSTEM_SRC/bin/$sh" /usr/local/bin/
        chmod +x "/usr/local/bin/$sh"
        log "$sh instalado em /usr/local/bin/"
    fi
done

# Crons
if [ -f "$SYSTEM_SRC/cron/unbound-dashboard-crons" ]; then
    crontab -l 2>/dev/null | grep -v 'UNBOUND-DASHBOARD' > /tmp/cron_clean 2>/dev/null || true
    cat "$SYSTEM_SRC/cron/unbound-dashboard-crons" >> /tmp/cron_clean
    crontab /tmp/cron_clean
    rm -f /tmp/cron_clean
    log "Crontabs configurados"
fi

# ============================================================
# 7. Permissões finais + serviços
# ============================================================
step "Etapa 7/8 — Permissões e ativação de serviços"

chown -R www-data:www-data "$INSTALL_DIR"
chmod 750 "$INSTALL_DIR/data" "$INSTALL_DIR/src/data"
chmod 770 "$INSTALL_DIR/data/tmp" "$INSTALL_DIR/src/data/tmp"
log "Permissões aplicadas"

# Apache reload
systemctl reload apache2 2>/dev/null || systemctl restart apache2
systemctl is-active --quiet apache2 && log "Apache ativo" || warn "Apache não está ativo"

# Redis
systemctl enable --now redis-server >/dev/null 2>&1 || warn "Não foi possível habilitar redis-server"
systemctl is-active --quiet redis-server && log "Redis ativo" || warn "Redis não está ativo"

# Unbound
systemctl enable unbound >/dev/null 2>&1 || true
systemctl start unbound 2>/dev/null || true
if [ -x /usr/local/bin/setup-unbound-logs.sh ]; then
    /usr/local/bin/setup-unbound-logs.sh >/dev/null 2>&1 || true
fi
systemctl is-active --quiet unbound && log "Unbound ativo" || warn "Unbound não iniciou — checar /etc/unbound/unbound.conf"

# api_service (FastAPI) — só inicia depois das migrations
systemctl enable unbound-dashboard-api >/dev/null 2>&1 || true
info "Iniciando api_service..."
systemctl restart unbound-dashboard-api
sleep 3
if systemctl is-active --quiet unbound-dashboard-api; then
    log "api_service (FastAPI) ativo em 127.0.0.1:8001"
else
    journalctl -u unbound-dashboard-api -n 20 --no-pager
    err "api_service não iniciou — veja log acima"
fi

# Smoke /healthz
if curl -sf http://127.0.0.1:8001/api/v1/healthz >/dev/null; then
    log "/api/v1/healthz responde OK"
else
    warn "/api/v1/healthz não respondeu — verifique systemctl status unbound-dashboard-api"
fi

# ============================================================
# 8. Bootstrap do admin inicial + marca .installed
# ============================================================
step "Etapa 8/8 — Admin inicial"

if [ -f "$INSTALL_DIR/data/.installed" ]; then
    log "Sistema já marcado como instalado — pulando criação de admin"
else
    ADMIN_USERNAME="${ADMIN_USERNAME:-}"
    ADMIN_EMAIL="${ADMIN_EMAIL:-}"
    ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

    if [ -z "$ADMIN_USERNAME" ]; then
        read -rp "Username do admin [admin]: " ADMIN_USERNAME
        ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
    fi
    if [ -z "$ADMIN_EMAIL" ]; then
        read -rp "Email do admin (opcional): " ADMIN_EMAIL
    fi
    if [ -z "$ADMIN_PASSWORD" ]; then
        while :; do
            read -rsp "Senha (mín. 6 chars): " ADMIN_PASSWORD; echo
            read -rsp "Confirme: " ADMIN_PASSWORD2; echo
            [ "$ADMIN_PASSWORD" = "$ADMIN_PASSWORD2" ] && [ ${#ADMIN_PASSWORD} -ge 6 ] && break
            warn "Senhas não conferem ou < 6 chars. Tente novamente."
        done
    fi

    info "Criando admin '$ADMIN_USERNAME'..."
    # Roda como www-data com o env file do api_service carregado (JWT_SECRET, DB_PATH, etc).
    (
        cd "$APISERVICE_DIR"
        # Exporta tudo do api-v1.env + as creds do admin
        set -a
        # shellcheck disable=SC1090
        source "$ENV_FILE"
        ADMIN_USERNAME="$ADMIN_USERNAME"
        ADMIN_EMAIL="$ADMIN_EMAIL"
        ADMIN_PASSWORD="$ADMIN_PASSWORD"
        set +a
        sudo -u www-data --preserve-env=ADMIN_USERNAME,ADMIN_EMAIL,ADMIN_PASSWORD,JWT_SECRET,JWT_ALGORITHM,JWT_EXPIRE_MINUTES,DB_PATH,REDIS_URL,UNBOUND_CONTROL,UNBOUND_LOG,LOG_LEVEL,DEBUG \
            env "PATH=/usr/local/bin:/usr/bin:/bin" "PYTHONPATH=$APISERVICE_DIR" \
            "$APISERVICE_DIR/.venv/bin/python" "$APISERVICE_DIR/tools/create_admin.py"
    ) || err "Falha ao criar admin — veja log acima"

    install -d -o www-data -g www-data "$INSTALL_DIR/data"
    sudo -u www-data touch "$INSTALL_DIR/data/.installed"
    log "Sistema marcado como instalado (data/.installed)"
fi

# ============================================================
# Resumo final
# ============================================================
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
[ -z "$SERVER_IP" ] && SERVER_IP="IP-DO-SERVIDOR"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         Instalação concluída ✓                        ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
log "Acesse: ${CYAN}http://${SERVER_IP}/unbound-dashboard/login.php${NC}"
log "Env:    $ENV_FILE"
log "DuckDB: $DUCKDB_DIR/unbound_dash.duckdb"
log "API:    http://127.0.0.1:8001/api/v1/healthz"
echo ""
info "Logs:"
echo -e "   ${CYAN}journalctl -u unbound-dashboard-api -f${NC}"
echo -e "   ${CYAN}tail -f /var/log/apache2/error.log${NC}"
echo ""
