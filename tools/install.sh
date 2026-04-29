#!/bin/bash
# ============================================================
# Unbound Dashboard — Instalador Automatizado
# Para Debian 12/13 e Ubuntu 22.04+
#
# Uso: sudo bash install.sh
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
step() { echo -e "\n${BOLD}── $1 ──${NC}"; }

# Script deve rodar como root
if [ "$EUID" -ne 0 ]; then
    err "Este script deve ser executado como root (sudo bash install.sh)"
fi

# Detectar diretório do pacote
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DASHBOARD_SRC="$SCRIPT_DIR/dashboard"
SYSTEM_SRC="$SCRIPT_DIR/system"

if [ ! -d "$DASHBOARD_SRC" ]; then
    err "Diretório 'dashboard/' não encontrado. Certifique-se de estar no diretório do pacote extraído."
fi

INSTALL_DIR="/var/www/html/unbound-dashboard"
VERSION=$(cat "$DASHBOARD_SRC/VERSION" 2>/dev/null || echo "1.0.0")

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║     Unbound Dashboard — Instalador v${VERSION}            ║${NC}"
echo -e "${BOLD}║     Para Debian 12/13 e Ubuntu 22.04+                ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================
# ETAPA 1: Detecção do Sistema Operacional
# ============================================================
step "Etapa 1/5 — Detecção do Sistema Operacional"

if [ ! -f /etc/os-release ]; then
    err "Arquivo /etc/os-release não encontrado. Sistema operacional não reconhecido."
fi

source /etc/os-release

case "$ID" in
    debian)
        if [ "${VERSION_ID:-0}" -lt 12 ]; then
            err "Debian $VERSION_ID detectado. Necessário Debian 12 ou superior."
        fi
        log "Debian $VERSION_ID ($VERSION_CODENAME) detectado"
        ;;
    ubuntu)
        MAJOR_VERSION=$(echo "${VERSION_ID:-0}" | cut -d. -f1)
        if [ "$MAJOR_VERSION" -lt 22 ]; then
            err "Ubuntu $VERSION_ID detectado. Necessário Ubuntu 22.04 ou superior."
        fi
        log "Ubuntu $VERSION_ID ($VERSION_CODENAME) detectado"
        ;;
    *)
        err "Sistema operacional '$ID' não suportado. Apenas Debian 12+ e Ubuntu 22.04+ são compatíveis."
        ;;
esac

# ============================================================
# ETAPA 2: Instalação de Dependências
# ============================================================
step "Etapa 2/5 — Instalação de Dependências"

info "Atualizando repositórios..."
apt-get update -qq

PACKAGES=(
    apache2
    libapache2-mod-php
    sudo
    php
    php-cli
    php-mysql
    php-sqlite3
    php-common
    mariadb-server
    mariadb-client
    unbound
    rsyslog
    dnsutils
    traceroute
    dns-root-data
    curl
    wget
    rsync
)

info "Instalando pacotes necessários..."
for pkg in "${PACKAGES[@]}"; do
    if dpkg -l "$pkg" &>/dev/null; then
        log "$pkg já instalado"
    else
        info "Instalando $pkg..."
        apt-get install -y -qq "$pkg" || {
            warn "Falha ao instalar $pkg — continuando..."
        }
    fi
done

# Verificar PHP versão
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0")
PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
if [ "$PHP_MAJOR" -lt 8 ]; then
    err "PHP $PHP_VERSION detectado. Necessário PHP 8.1 ou superior."
fi
log "PHP $PHP_VERSION instalado"

# ============================================================
# ETAPA 3: Deploy dos Arquivos
# ============================================================
step "Etapa 3/5 — Deploy dos Arquivos"

# Backup se já existir
if [ -d "$INSTALL_DIR" ]; then
    BACKUP_DIR="${INSTALL_DIR}.backup.$(date +%Y%m%d-%H%M%S)"
    warn "Instalação anterior detectada. Criando backup em $BACKUP_DIR"
    cp -a "$INSTALL_DIR" "$BACKUP_DIR"
fi

# Copiar código-fonte
info "Copiando código-fonte para $INSTALL_DIR..."
mkdir -p "$INSTALL_DIR"
rsync -a --delete \
    --exclude='data/.installed' \
    "$DASHBOARD_SRC/" "$INSTALL_DIR/"
log "Código-fonte implantado"

# Criar diretórios de dados se não existem
mkdir -p "$INSTALL_DIR/data/tmp"
mkdir -p "$INSTALL_DIR/src/data/tmp"

# ============================================================
# Sudoers
# ============================================================
info "Configurando sudoers..."
if [ -f "$SYSTEM_SRC/sudoers/unbound-dashboard" ]; then
    cp "$SYSTEM_SRC/sudoers/unbound-dashboard" /etc/sudoers.d/unbound-dashboard
    chmod 440 /etc/sudoers.d/unbound-dashboard
    # Validar sudoers
    if visudo -c -f /etc/sudoers.d/unbound-dashboard &>/dev/null; then
        log "Sudoers instalado e validado"
    else
        err "Arquivo sudoers inválido! Verifique manualmente."
    fi
else
    warn "Arquivo sudoers não encontrado no pacote"
fi

# ============================================================
# Systemd units
# ============================================================
info "Configurando serviços systemd..."
if [ -d "$SYSTEM_SRC/systemd" ]; then
    for unit_file in "$SYSTEM_SRC/systemd"/*.service; do
        [ -f "$unit_file" ] || continue
        unit_name=$(basename "$unit_file")
        cp "$unit_file" "/etc/systemd/system/$unit_name"
        log "Systemd unit $unit_name instalada"
    done
    systemctl daemon-reload
fi

# ============================================================
# Health-fix script
# ============================================================
if [ -f "$SYSTEM_SRC/bin/unbound-health-fix.sh" ]; then
    cp "$SYSTEM_SRC/bin/unbound-health-fix.sh" /usr/local/bin/
    chmod +x /usr/local/bin/unbound-health-fix.sh
    log "Health-fix script instalado"
fi

# ============================================================
# Fix MariaDB script
# ============================================================
if [ -f "$SYSTEM_SRC/bin/fix-mariadb.sh" ]; then
    cp "$SYSTEM_SRC/bin/fix-mariadb.sh" /usr/local/bin/
    chmod +x /usr/local/bin/fix-mariadb.sh
    log "Fix-MariaDB script instalado"
fi

# ============================================================
# Setup Unbound Logs script
# ============================================================
if [ -f "$SYSTEM_SRC/bin/setup-unbound-logs.sh" ]; then
    cp "$SYSTEM_SRC/bin/setup-unbound-logs.sh" /usr/local/bin/
    chmod +x /usr/local/bin/setup-unbound-logs.sh
    log "Setup-unbound-logs script instalado"
fi

# ============================================================
# Crontabs
# ============================================================
info "Configurando crontabs..."
CRON_FILE="$SYSTEM_SRC/cron/unbound-dashboard-crons"
if [ -f "$CRON_FILE" ]; then
    # Remover crons antigos do dashboard (se existirem)
    crontab -l 2>/dev/null | grep -v 'UNBOUND-DASHBOARD' > /tmp/cron_clean 2>/dev/null || true
    
    # Adicionar os novos
    cat "$CRON_FILE" >> /tmp/cron_clean
    crontab /tmp/cron_clean
    rm -f /tmp/cron_clean
    log "Crontabs configurados"
else
    warn "Arquivo de crons não encontrado"
fi

# ============================================================
# ETAPA 4: Permissões
# ============================================================
step "Etapa 4/5 — Permissões"

info "Ajustando proprietário dos arquivos..."
chown -R www-data:www-data "$INSTALL_DIR"
log "Proprietário: www-data:www-data"

info "Ajustando permissões de diretórios de dados..."
chmod 750 "$INSTALL_DIR/data"
chmod 750 "$INSTALL_DIR/src/data"
chmod 770 "$INSTALL_DIR/data/tmp"
chmod 770 "$INSTALL_DIR/src/data/tmp"

# Garantir que o PHP pode escrever o Database.php (para o wizard)
chmod 660 "$INSTALL_DIR/src/Database.php"
log "Permissões configuradas"

# ============================================================
# ETAPA 5: Ativação de Serviços
# ============================================================
step "Etapa 5/5 — Ativação de Serviços"

# Apache
info "Habilitando Apache..."
systemctl enable apache2 &>/dev/null || true
systemctl start apache2 &>/dev/null || true
if systemctl is-active apache2 &>/dev/null; then
    log "Apache ativo"
else
    warn "Apache não iniciou. Verifique com: systemctl status apache2"
fi

# MariaDB
info "Habilitando MariaDB..."
DO_DB_SETUP=0
systemctl enable mariadb &>/dev/null || true
systemctl start mariadb &>/dev/null || true
if systemctl is-active mariadb &>/dev/null; then
    log "MariaDB ativa"
    DO_DB_SETUP=1
else
    # Tentar mysql como fallback
    systemctl enable mysql &>/dev/null || true
    systemctl start mysql &>/dev/null || true
    if systemctl is-active mysql &>/dev/null; then
        log "MySQL ativo"
        DO_DB_SETUP=1
    else
        warn "MariaDB/MySQL não iniciou. Verifique manualmente."
    fi
fi

if [ "$DO_DB_SETUP" = "1" ]; then
    info "Provisionando banco de dados, usuário e importando schema..."

    if [ -x "/usr/local/bin/fix-mariadb.sh" ]; then
        info "Executando fix-mariadb.sh para corrigir autenticação do MariaDB..."
        /usr/local/bin/fix-mariadb.sh || warn "fix-mariadb.sh retornou erro, continuando com a configuração padrão"
    fi

    mysql -e "CREATE DATABASE IF NOT EXISTS unbound_dash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -e "DROP USER IF EXISTS 'unbounddb'@'localhost';" 2>/dev/null || true
    mysql -e "DROP USER IF EXISTS 'unbounddb'@'127.0.0.1';" 2>/dev/null || true
    mysql -e "CREATE USER 'unbounddb'@'localhost' IDENTIFIED BY 'unbounddash';" 2>/dev/null || true
    mysql -e "CREATE USER 'unbounddb'@'127.0.0.1' IDENTIFIED BY 'unbounddash';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON unbound_dash.* TO 'unbounddb'@'localhost';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON unbound_dash.* TO 'unbounddb'@'127.0.0.1';" 2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    if [ -f "$INSTALL_DIR/scripts/init_db.sql" ]; then
        # Remover CREATE DATABASE do schema local se existir para não dar erro
        grep -v -E '^(CREATE DATABASE|USE )' "$INSTALL_DIR/scripts/init_db.sql" | mysql unbound_dash 2>/dev/null || true
    fi

    cat > "$INSTALL_DIR/src/Database.php" << 'EOF'
<?php
namespace App;
use PDO;
use PDOException;
$sysTz = '';
if (file_exists('/etc/timezone')) { $sysTz = trim(file_get_contents('/etc/timezone')); }
else if (is_link('/etc/localtime')) { $t = readlink('/etc/localtime'); if (preg_match('/zoneinfo\/(.*)$/', $t, $m)) { $sysTz = trim($m[1]); } }
if (!empty($sysTz) && in_array($sysTz, timezone_identifiers_list())) { date_default_timezone_set($sysTz); }
class Database {
    private static ?PDO $instance = null;
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try { self::$instance = new PDO("mysql:host=127.0.0.1;dbname=unbound_dash;charset=utf8mb4", 'unbounddb', 'unbounddash', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]); }
            catch (PDOException $e) { die("Database connection failed: " . $e->getMessage()); }
        }
        return self::$instance;
    }
}
EOF
    chown www-data:www-data "$INSTALL_DIR/src/Database.php"
    chmod 660 "$INSTALL_DIR/src/Database.php"
    log "Banco de dados provisionado (Tabelas e Database.php configurados)"
fi

# rsyslog
info "Habilitando rsyslog..."
systemctl enable rsyslog &>/dev/null || true
systemctl start rsyslog &>/dev/null || true
if systemctl is-active rsyslog &>/dev/null; then
    log "rsyslog ativo"
else
    warn "rsyslog não iniciou. Verifique com: systemctl status rsyslog"
fi

# Provisionar arquivo de log do Unbound com permissões corretas
info "Provisionando arquivo de log do Unbound..."
if [ -f /usr/local/bin/setup-unbound-logs.sh ]; then
    /usr/local/bin/setup-unbound-logs.sh
else
    # Fallback: criar manualmente se script não está disponível
    if [ ! -f /var/log/unbound.log ]; then
        touch /var/log/unbound.log
        log "Arquivo /var/log/unbound.log criado"
    else
        log "/var/log/unbound.log já existe"
    fi
    chown unbound:unbound /var/log/unbound.log
    chmod 640 /var/log/unbound.log
    log "Permissões do arquivo de log configuradas (unbound:unbound, 640)"
fi

# Unbound
info "Habilitando Unbound..."
systemctl enable unbound &>/dev/null || true
systemctl start unbound &>/dev/null || true
if systemctl is-active unbound &>/dev/null; then
    log "Unbound ativo"
else
    warn "Unbound não iniciou. Pode ser necessário ajustar /etc/unbound/unbound.conf"
fi

# Log Ingester (não iniciar agora — precisa do banco configurado)
systemctl enable unbound-log-ingester &>/dev/null || true
info "Log Ingester habilitado (será iniciado após configuração do banco)"

# ============================================================
# Detectar IP do servidor
# ============================================================
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="IP-DO-SERVIDOR"
fi

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         Instalação Base Concluída! ✓                 ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
log "Arquivos implantados em: $INSTALL_DIR"
log "Serviços habilitados: Apache, MariaDB, Unbound"
echo ""
echo -e "${BOLD}Próximo passo:${NC} Complete a configuração pelo navegador:"
echo ""
echo -e "   ${CYAN}http://${SERVER_IP}/unbound-dashboard/setup.php${NC}"
echo ""
echo -e "O wizard vai guiá-lo na configuração do banco de dados,"
echo -e "detecção do Unbound e criação do primeiro administrador."
echo ""
info "Após concluir o wizard, inicie o Log Ingester:"
echo -e "   ${CYAN}sudo systemctl start unbound-log-ingester${NC}"
echo ""
