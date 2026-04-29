#!/bin/bash
# ============================================================
# Unbound Dashboard — Build Package Script
# Gera um pacote .tar.gz auto-contido para instalação
# em novos servidores.
#
# Uso: sudo bash tools/build-package.sh
# ============================================================

set -euo pipefail

# Cores
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

DASHBOARD_DIR="/var/www/html/unbound-dashboard"
VERSION_FILE="$DASHBOARD_DIR/VERSION"

# Verificar se estamos no diretório correto
if [ ! -f "$VERSION_FILE" ]; then
    err "Arquivo VERSION não encontrado em $DASHBOARD_DIR. Execute a partir do diretório raiz do projeto."
fi

VERSION=$(cat "$VERSION_FILE" | tr -d '[:space:]')
PACKAGE_NAME="unbound-dashboard-v${VERSION}"
BUILD_DIR="/tmp/unbound-dashboard-build"
OUTPUT_DIR="$DASHBOARD_DIR/tools"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║    Unbound Dashboard — Package Builder v${VERSION}     ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# Limpar builds anteriores
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PACKAGE_NAME"

STAGING="$BUILD_DIR/$PACKAGE_NAME"

# ============================================================
# 1. Copiar código-fonte da aplicação
# ============================================================
info "Copiando código-fonte da aplicação..."

# Copiar apenas o que é necessário para rodar o dashboard
rsync -a --exclude='.git' \
         --exclude='.vscode' \
         --exclude='docs' \
         --exclude='*.md' \
         --exclude='MANUAL_INSTALACAO.md' \
         --exclude='SISTEMA.md' \
         --exclude='tools' \
         --exclude='data/*.json' \
         --exclude='data/*.db' \
         --exclude='data/*.sqlite' \
         --exclude='data/.installed' \
         --exclude='data/tmp/*' \
         --exclude='src/data/tmp/*' \
         --exclude='src/data/*.json' \
         --exclude='src/data/official_blocklist.conf' \
         --exclude='tools/*.tar.gz' \
         --exclude='test_help.php' \
         "$DASHBOARD_DIR/" "$STAGING/dashboard/"

# Inclui CHANGELOG.md explicitamente para exibicao no sistema
if [ -f "$DASHBOARD_DIR/CHANGELOG.md" ]; then
    cp "$DASHBOARD_DIR/CHANGELOG.md" "$STAGING/dashboard/CHANGELOG.md"
fi

log "Código-fonte copiado"

# ============================================================
# 2. Limpar credenciais do Database.php
# ============================================================
info "Sanitizando credenciais..."

# Substituir credenciais no Database.php
DB_PHP="$STAGING/dashboard/src/Database.php"
if [ -f "$DB_PHP" ]; then
    sed -i "s/\$host = '.*'/\$host = '127.0.0.1'/" "$DB_PHP"
    sed -i "s/\$db   = '.*'/\$db   = 'unbound_dash'/" "$DB_PHP"
    sed -i "s/\$user = '.*'/\$user = 'CONFIGURE_VIA_WIZARD'/" "$DB_PHP"
    sed -i "s/\$pass = '.*'/\$pass = 'CONFIGURE_VIA_WIZARD'/" "$DB_PHP"
fi
log "Credenciais sanitizadas"

# ============================================================
# 3. Criar diretórios de dados vazios com placeholder
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
# 4. Coletar configurações do sistema
# ============================================================
info "Coletando configurações do sistema..."
mkdir -p "$STAGING/system/sudoers"
mkdir -p "$STAGING/system/systemd"
mkdir -p "$STAGING/system/cron"
mkdir -p "$STAGING/system/bin"
mkdir -p "$STAGING/system/db"

# Sudoers
if [ -f "/etc/sudoers.d/unbound-dashboard" ]; then
    cp /etc/sudoers.d/unbound-dashboard "$STAGING/system/sudoers/"
    log "Sudoers copiado"
else
    warn "Sudoers não encontrado — será criado na instalação"
    # Criar template padrão
    cat > "$STAGING/system/sudoers/unbound-dashboard" << 'SUDOERS_EOF'
www-data ALL=(ALL) NOPASSWD: /usr/sbin/unbound-control *, /usr/bin/cp /var/www/html/unbound-dashboard/src/data/tmp/unbound_* *, /usr/sbin/unbound-checkconf *, /usr/sbin/service unbound *, /usr/bin/systemctl * unbound, /usr/sbin/ifdown *, /usr/sbin/ifup *, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/interfaces_new /etc/network/interfaces, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/timesyncd.conf.new /etc/systemd/timesyncd.conf, /usr/bin/mv /var/www/html/unbound-dashboard/src/data/tmp/resolv_conf_new /etc/resolv.conf, /usr/bin/systemctl restart systemd-timesyncd, /usr/bin/timedatectl set-timezone *, /usr/bin/hostnamectl set-hostname *, /usr/bin/tail -n 300 /var/log/syslog, /usr/bin/tail -n 300 /var/log/unbound.log, /usr/bin/journalctl -u unbound -n 300 --no-pager, /usr/local/bin/unbound-health-fix.sh
SUDOERS_EOF
fi

# Systemd units
SYSTEMD_FOUND=false
for svc_name in unbound-log-ingester unbound-logger; do
    if [ -f "/etc/systemd/system/${svc_name}.service" ]; then
        cp "/etc/systemd/system/${svc_name}.service" "$STAGING/system/systemd/unbound-log-ingester.service"
        SYSTEMD_FOUND=true
        log "Systemd unit copiada (${svc_name}.service)"
        break
    fi
done
if [ "$SYSTEMD_FOUND" = false ]; then
    warn "Systemd unit não encontrada — será criada na instalação"
    cat > "$STAGING/system/systemd/unbound-log-ingester.service" << 'SYSTEMD_EOF'
[Unit]
Description=Unbound Dashboard Log Ingester
After=unbound.service mysql.service mariadb.service

[Service]
Type=simple
User=root
ExecStart=/usr/bin/php /var/www/html/unbound-dashboard/scripts/log_ingester.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SYSTEMD_EOF
fi

# Health fix script
if [ -f "/usr/local/bin/unbound-health-fix.sh" ]; then
    cp /usr/local/bin/unbound-health-fix.sh "$STAGING/system/bin/"
    log "Health-fix script copiado"
else
    warn "Health-fix script não encontrado"
fi

# Fix MariaDB script
if [ -f "$DASHBOARD_DIR/tools/fix-mariadb.sh" ]; then
    cp "$DASHBOARD_DIR/tools/fix-mariadb.sh" "$STAGING/system/bin/"
    chmod +x "$STAGING/system/bin/fix-mariadb.sh"
    log "Fix-MariaDB script copiado"
else
    warn "Fix-MariaDB script não encontrado"
fi

# Setup Unbound Logs script
if [ -f "$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh" ]; then
    chmod +x "$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh"
    cp "$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh" "$STAGING/system/bin/"
    chmod +x "$STAGING/system/bin/setup-unbound-logs.sh"
    log "Setup-unbound-logs script copiado"
else
    warn "Setup-unbound-logs script não encontrado"
fi

# Cron definitions
if [ -f "$DASHBOARD_DIR/tools/system/cron/unbound-dashboard-crons" ]; then
    cp "$DASHBOARD_DIR/tools/system/cron/unbound-dashboard-crons" "$STAGING/system/cron/"
    log "Definição de crons copiada"
else
    warn "Arquivo de crons não encontrado em tools/system/cron/"
fi

# Schema do banco de dados
cp "$STAGING/dashboard/scripts/init_db.sql" "$STAGING/system/db/schema.sql"
log "Schema do banco incluído"

# ============================================================
# 5. Copiar o install.sh para a raiz do pacote
# ============================================================
info "Incluindo instalador..."
if [ -f "$DASHBOARD_DIR/tools/install.sh" ]; then
    cp "$DASHBOARD_DIR/tools/install.sh" "$STAGING/install.sh"
    chmod +x "$STAGING/install.sh"
    log "Instalador incluído"
else
    warn "install.sh não encontrado em tools/ — adicione manualmente"
fi

# ============================================================
# 6. Criar arquivo de metadados
# ============================================================
cat > "$STAGING/LEIAME.txt" << EOF
============================================================
  Unbound Dashboard v${VERSION}
  Pacote de Instalação
============================================================

Para instalar em um novo servidor (Debian 12/13 ou Ubuntu 22.04+):

  1. Copie este pacote para o servidor destino
  2. Extraia: tar xzf ${PACKAGE_NAME}.tar.gz
  3. Execute: cd ${PACKAGE_NAME} && sudo bash install.sh
  4. Acesse o wizard: http://IP-DO-SERVIDOR/unbound-dashboard/setup.php

Requisitos:
  - Debian 12+ ou Ubuntu 22.04+
  - Acesso root
  - Conexão com a internet (para instalar pacotes)

Conteúdo do pacote:
  - dashboard/     → Código-fonte da aplicação
  - system/        → Configurações do sistema
  - install.sh     → Script de instalação automatizada
  - LEIAME.txt     → Este arquivo

============================================================
EOF

# ============================================================
# 7. Gerar o tarball
# ============================================================
info "Gerando pacote..."
mkdir -p "$OUTPUT_DIR"
cd "$BUILD_DIR"
tar czf "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" "$PACKAGE_NAME/"

# Tamanho do pacote
SIZE=$(du -h "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" | cut -f1)

# Limpar
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
