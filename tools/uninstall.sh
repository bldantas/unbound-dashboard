#!/bin/bash
# ============================================================
# Unbound Dashboard — Remoção Completa
#
# Remove completamente o sistema do servidor (v1 PHP + v2 Python).
# Faz backup dos arquivos de configuração antes de apagar.
#
# Uso:
#   sudo bash uninstall.sh
#   sudo FORCE=true bash uninstall.sh   # sem confirmação
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
info() { echo -e "${BLUE}[i]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step() { echo -e "\n${BOLD}── $1 ──${NC}"; }

FORCE="${FORCE:-false}"

INSTALL_DIR="/opt/unbound-dashboard"
WEB_DIR="/var/www/html/unbound-dashboard"
DATA_DIR="/var/lib/unbound-dashboard"
LOG_DIR="/var/log/unbound-dashboard"
ENV_DIR="/etc/unbound-dashboard"
PHP_ENV_FILE="/etc/unbound-dashboard.env"
SERVICE_NAME="unbound-api"
SERVICE_USER="unbound-dash"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
BACKUP_DIR="/var/backups/unbound-dashboard"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_ARCHIVE=""

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash uninstall.sh"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Unbound Dashboard — Remoção Completa               ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
warn "Este script irá REMOVER permanentemente:"
echo "  • $INSTALL_DIR        (API Python v2)"
echo "  • $WEB_DIR  (Frontend PHP v1)"
echo "  • $DATA_DIR    (banco DuckDB — TODOS os dados)"
echo "  • $LOG_DIR"
echo "  • $ENV_DIR      (configuração de ambiente)"
echo "  • $PHP_ENV_FILE"
echo "  • $SERVICE_FILE"
echo "  • Usuário de serviço: $SERVICE_USER"
echo ""

if [ "$FORCE" != "true" ]; then
    echo -n "  Confirma a remoção? [sim/não]: "
    read -r CONFIRM
    [[ "$CONFIRM" == "sim" ]] || { info "Operação cancelada."; exit 0; }
fi

# ============================================================
# FASE 1 — Parar serviços
# ============================================================
step "Fase 1 — Parar serviços"

if systemctl is-active --quiet "$SERVICE_NAME" 2>/dev/null; then
    systemctl stop "$SERVICE_NAME" && log "$SERVICE_NAME parado" || warn "Falha ao parar $SERVICE_NAME"
else
    info "$SERVICE_NAME já estava inativo"
fi
systemctl disable "$SERVICE_NAME" 2>/dev/null || true

# PHP-FPM é mantido — pode estar servindo outros sites
PHP_FPM=$(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null | awk '{print $1}' | head -1 || true)
[ -n "$PHP_FPM" ] && info "PHP-FPM ($PHP_FPM) mantido — pode estar em uso por outros sites"

# ============================================================
# FASE 2 — Backup dos arquivos de configuração
# ============================================================
step "Fase 2 — Backup de configuração"

mkdir -p "$BACKUP_DIR"
BACKUP_ARCHIVE="$BACKUP_DIR/uninstall-${TIMESTAMP}.tar.gz"

BACKUP_ITEMS=()
[ -d "$ENV_DIR" ]      && BACKUP_ITEMS+=("$ENV_DIR")
[ -f "$PHP_ENV_FILE" ] && BACKUP_ITEMS+=("$PHP_ENV_FILE")

if [ "${#BACKUP_ITEMS[@]}" -gt 0 ]; then
    tar czf "$BACKUP_ARCHIVE" "${BACKUP_ITEMS[@]}" 2>/dev/null && \
        log "Backup salvo em: $BACKUP_ARCHIVE" || \
        warn "Falha ao criar backup (continuando)"
else
    info "Nenhum arquivo de configuração encontrado para backup"
fi

# ============================================================
# FASE 3 — Remover
# ============================================================
step "Fase 3 — Remover arquivos e diretórios"

_rm_dir()  { [ -d "$1" ] && { rm -rf "$1"; log "Removido: $1"; } || info "Não existe (pulado): $1"; }
_rm_file() { [ -f "$1" ] && { rm -f  "$1"; log "Removido: $1"; } || info "Não existe (pulado): $1"; }

_rm_dir  "$INSTALL_DIR"
_rm_dir  "$DATA_DIR"
_rm_dir  "$LOG_DIR"
_rm_dir  "$ENV_DIR"
_rm_dir  "$WEB_DIR"
_rm_file "$PHP_ENV_FILE"
_rm_file "$SERVICE_FILE"

if id "$SERVICE_USER" &>/dev/null; then
    userdel "$SERVICE_USER" 2>/dev/null && log "Usuário $SERVICE_USER removido" || warn "Falha ao remover usuário $SERVICE_USER"
else
    info "Usuário $SERVICE_USER não existe (pulado)"
fi

systemctl daemon-reload
log "systemd recarregado"

# ============================================================
# RESUMO
# ============================================================
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Remoção concluída com sucesso!                     ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
[ -n "$BACKUP_ARCHIVE" ] && [ -f "$BACKUP_ARCHIVE" ] && \
    log "Backup de configuração: $BACKUP_ARCHIVE"
echo ""
