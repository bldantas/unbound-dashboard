#!/bin/bash
# ============================================================
# Unbound Dashboard — Build Package Script
# Gera um pacote .tar.gz auto-contido para instalação em
# servidores novos. Inclui o frontend PHP legacy + o api_service
# (FastAPI/DuckDB/Redis) que substituiu o MariaDB.
#
# Uso: sudo bash tools/build-package.sh
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

# Resolve DASHBOARD_DIR a partir do path do próprio script (parent do tools/).
# Permite rodar o build de qualquer checkout (não só /var/www/html/unbound-dashboard).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DASHBOARD_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
VERSION_FILE="$DASHBOARD_DIR/VERSION"

if [ ! -f "$VERSION_FILE" ]; then
    err "Arquivo VERSION não encontrado em $DASHBOARD_DIR."
fi

VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
PACKAGE_NAME="unbound-dashboard-v${VERSION}"
BUILD_DIR="/tmp/unbound-dashboard-build"
OUTPUT_DIR="$DASHBOARD_DIR/tools"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║    Unbound Dashboard — Package Builder v${VERSION}     ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PACKAGE_NAME"
STAGING="$BUILD_DIR/$PACKAGE_NAME"

# ============================================================
# 1. Frontend PHP — copia o dashboard legacy
# ============================================================
info "Copiando frontend PHP (dashboard/)..."

rsync -a --exclude='.git' \
         --exclude='.vscode' \
         --exclude='docs' \
         --exclude='*.md' \
         --exclude='tools' \
         --exclude='api_service' \
         --exclude='data/*.json' \
         --exclude='data/*.db' \
         --exclude='data/*.sqlite' \
         --exclude='data/.installed' \
         --exclude='data/tmp/*' \
         --exclude='src/data/tmp/*' \
         --exclude='src/data/*.json' \
         --exclude='src/data/official_blocklist.conf' \
         --exclude='test_help.php' \
         "$DASHBOARD_DIR/" "$STAGING/dashboard/"

# CHANGELOG.md é exibido em /changelog.php — força inclusão
if [ -f "$DASHBOARD_DIR/CHANGELOG.md" ]; then
    cp "$DASHBOARD_DIR/CHANGELOG.md" "$STAGING/dashboard/CHANGELOG.md"
fi
log "Frontend PHP copiado"

# ============================================================
# 2. Diretórios de dados vazios com .gitkeep
# ============================================================
info "Preparando diretórios de dados..."
mkdir -p "$STAGING/dashboard/data/tmp"
mkdir -p "$STAGING/dashboard/src/data/tmp"
touch "$STAGING/dashboard/data/.gitkeep"
touch "$STAGING/dashboard/data/tmp/.gitkeep"
touch "$STAGING/dashboard/src/data/.gitkeep"
touch "$STAGING/dashboard/src/data/tmp/.gitkeep"
log "Diretórios de dados preparados"

# ============================================================
# 3. api_service — FastAPI + DuckDB + Redis (workers)
# ============================================================
info "Copiando api_service (FastAPI/DuckDB)..."

if [ ! -d "$DASHBOARD_DIR/api_service" ]; then
    err "api_service/ não encontrado em $DASHBOARD_DIR — pacote inválido sem ele."
fi

rsync -a --exclude='.venv' \
         --exclude='__pycache__' \
         --exclude='*.pyc' \
         --exclude='.pytest_cache' \
         --exclude='.ruff_cache' \
         --exclude='.mypy_cache' \
         --exclude='*.bak' \
         --exclude='*.bak-*' \
         --exclude='tools/teardown_mariadb.sh' \
         "$DASHBOARD_DIR/api_service/" "$STAGING/api_service/"

log "api_service copiado"

# ============================================================
# 4. Configurações do sistema (sudoers, systemd, apache, cron)
# ============================================================
info "Coletando configurações do sistema..."
mkdir -p "$STAGING/system/sudoers"
mkdir -p "$STAGING/system/systemd"
mkdir -p "$STAGING/system/apache"
mkdir -p "$STAGING/system/cron"
mkdir -p "$STAGING/system/bin"
mkdir -p "$STAGING/system/etc"

# --- Sudoers
if [ -f "/etc/sudoers.d/unbound-dashboard" ]; then
    cp /etc/sudoers.d/unbound-dashboard "$STAGING/system/sudoers/"
    log "Sudoers copiado"
else
    warn "Sudoers não encontrado — gerando template padrão"
    cat > "$STAGING/system/sudoers/unbound-dashboard" << 'SUDOERS_EOF'
www-data ALL=(ALL) NOPASSWD: /usr/sbin/unbound-control *, /usr/bin/cp /var/www/html/unbound-dashboard/src/data/tmp/unbound_* *, /usr/sbin/unbound-checkconf *, /usr/sbin/service unbound *, /usr/bin/systemctl * unbound, /usr/sbin/ifdown *, /usr/sbin/ifup *, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/interfaces_new /etc/network/interfaces, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/timesyncd.conf.new /etc/systemd/timesyncd.conf, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/resolv_conf_new /etc/resolv.conf, /usr/bin/systemctl restart systemd-timesyncd, /usr/bin/timedatectl set-timezone *, /usr/bin/hostnamectl set-hostname *, /usr/bin/tail -n 300 /var/log/syslog, /usr/bin/tail -n 300 /var/log/unbound.log, /usr/bin/journalctl -u unbound -n 300 --no-pager, /usr/local/bin/unbound-health-fix.sh
SUDOERS_EOF
fi

# --- Systemd unit do api_service (FastAPI)
SRC_API_UNIT="$DASHBOARD_DIR/api_service/deployments/systemd/unbound-dashboard-api.service"
if [ -f "$SRC_API_UNIT" ]; then
    cp "$SRC_API_UNIT" "$STAGING/system/systemd/unbound-dashboard-api.service"
    log "Systemd unit do FastAPI copiada"
else
    err "Template systemd do api_service ausente: $SRC_API_UNIT"
fi

# --- Apache reverse-proxy /api/v1 → FastAPI
SRC_APACHE_CONF="$DASHBOARD_DIR/api_service/deployments/apache/unbound-dashboard-api.conf"
if [ -f "$SRC_APACHE_CONF" ]; then
    cp "$SRC_APACHE_CONF" "$STAGING/system/apache/unbound-dashboard-api.conf"
    log "Apache conf-available do api_service copiado"
else
    err "Template Apache do api_service ausente: $SRC_APACHE_CONF"
fi

# --- EnvironmentFile template (api-v1.env.example)
SRC_ENV_EXAMPLE="$DASHBOARD_DIR/api_service/deployments/api-v1.env.example"
if [ -f "$SRC_ENV_EXAMPLE" ]; then
    cp "$SRC_ENV_EXAMPLE" "$STAGING/system/etc/api-v1.env.example"
    log "Template api-v1.env.example incluído"
else
    err "Template api-v1.env.example ausente: $SRC_ENV_EXAMPLE"
fi

# --- Health-fix script (continua relevante: permissões Unbound + dashboard data/)
if [ -f "/usr/local/bin/unbound-health-fix.sh" ]; then
    cp /usr/local/bin/unbound-health-fix.sh "$STAGING/system/bin/"
    log "Health-fix script copiado"
else
    warn "Health-fix script não encontrado em /usr/local/bin/"
fi

# --- Setup-unbound-logs script
SRC_LOGS_SH="$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh"
if [ -f "$SRC_LOGS_SH" ]; then
    cp "$SRC_LOGS_SH" "$STAGING/system/bin/setup-unbound-logs.sh"
    chmod +x "$STAGING/system/bin/setup-unbound-logs.sh"
    log "Setup-unbound-logs script copiado"
else
    warn "Setup-unbound-logs script não encontrado em tools/system/bin/"
fi

# --- Crons (limpa MariaDB do conteúdo se ainda existir referência)
SRC_CRONS="$DASHBOARD_DIR/tools/system/cron/unbound-dashboard-crons"
if [ -f "$SRC_CRONS" ]; then
    cp "$SRC_CRONS" "$STAGING/system/cron/unbound-dashboard-crons"
    log "Definição de crons copiada"
else
    warn "Arquivo de crons não encontrado em tools/system/cron/"
fi

# ============================================================
# 5. Instalador (mantém install.sh atual; revisão é tarefa separada)
# ============================================================
info "Incluindo instalador..."
if [ -f "$DASHBOARD_DIR/tools/install.sh" ]; then
    cp "$DASHBOARD_DIR/tools/install.sh" "$STAGING/install.sh"
    chmod +x "$STAGING/install.sh"
    log "Instalador incluído (install.sh)"
else
    warn "install.sh não encontrado — pacote sai sem instalador"
fi

# ============================================================
# 6. LEIAME.txt (atualizado pra v2.2.0+)
# ============================================================
cat > "$STAGING/LEIAME.txt" << EOF
============================================================
  Unbound Dashboard v${VERSION}
  Pacote de Instalação
============================================================

Stack:
  - Frontend  : PHP 8.x  (Apache + mod_php / php-fpm)
  - API       : FastAPI 0.110+ via uvicorn  (Python 3.11+)
  - Banco     : DuckDB 1.x (arquivo único, sem servidor)
  - Cache/Q   : Redis 7+
  - Resolver  : Unbound 1.17+

Requisitos do servidor:
  - Debian 12+ ou Ubuntu 22.04+
  - Acesso root
  - Conexão com a internet (apt + pip + uv)
  - Python 3.11+ + uv (https://docs.astral.sh/uv/)
  - Apache com módulos: proxy, proxy_http, proxy_wstunnel, headers
  - Redis (redis-server) ativo

Instalação:
  1. Copie este pacote para o servidor destino
  2. Extraia: tar xzf ${PACKAGE_NAME}.tar.gz
  3. Execute: cd ${PACKAGE_NAME} && sudo bash install.sh
  4. Acesse o wizard: http://IP-DO-SERVIDOR/unbound-dashboard/setup.php

Conteúdo do pacote:
  dashboard/                       → Frontend PHP
  api_service/                     → FastAPI + DuckDB + workers
  system/sudoers/                  → /etc/sudoers.d/unbound-dashboard
  system/systemd/                  → unit do api_service (FastAPI)
  system/apache/                   → conf-available proxy /api/v1
  system/etc/api-v1.env.example    → template do EnvironmentFile
  system/bin/                      → unbound-health-fix.sh, setup-unbound-logs.sh
  system/cron/                     → definições cron do dashboard
  install.sh                       → Instalação automatizada
  LEIAME.txt                       → Este arquivo

Pós-instalação:
  - Gere JWT_SECRET: openssl rand -hex 32
  - Edite /etc/unbound-dashboard/api-v1.env (chmod 640 root:www-data)
  - Habilite o serviço: sudo systemctl enable --now unbound-dashboard-api
  - Habilite proxy Apache: sudo a2enconf unbound-dashboard-api && sudo systemctl reload apache2

============================================================
EOF

# ============================================================
# 7. Tarball
# ============================================================
info "Gerando pacote..."
mkdir -p "$OUTPUT_DIR"
cd "$BUILD_DIR"
tar czf "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" "$PACKAGE_NAME/"

SIZE=$(du -h "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" | cut -f1)
rm -rf "$BUILD_DIR"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║          Pacote gerado com sucesso! ✓            ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
log "Arquivo: $OUTPUT_DIR/${PACKAGE_NAME}.tar.gz"
log "Tamanho: $SIZE"
echo ""
info "Copie o pacote para o servidor destino e execute:"
echo -e "   ${CYAN}tar xzf ${PACKAGE_NAME}.tar.gz${NC}"
echo -e "   ${CYAN}cd ${PACKAGE_NAME} && sudo bash install.sh${NC}"
echo ""
