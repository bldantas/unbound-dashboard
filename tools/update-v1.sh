#!/bin/bash
# ============================================================
# Unbound Dashboard — Update Script (PHP Frontend v1 only)
# Aplica pacote de update gerado por build-update-v1.sh.
# Atualiza apenas o WEB_DIR (/var/www/html/unbound-dashboard).
# NÃO toca na API Python v2 nem no serviço systemd.
#
# Uso:
#   sudo bash update-v1.sh /tmp/unbound-dashboard-v1-update-*.tar.gz
#   sudo DRY_RUN=true bash update-v1.sh /tmp/unbound-dashboard-v1-update-*.tar.gz
#
# Variáveis de ambiente:
#   DRY_RUN=true     Simula sem fazer mudanças
#   VERBOSE=true     Modo detalhado
# ============================================================

set -euo pipefail

DRY_RUN="${DRY_RUN:-false}"
VERBOSE="${VERBOSE:-false}"

WEB_DIR="/var/www/html/unbound-dashboard"
BACKUP_BASE="/var/backups/unbound-dashboard"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
UPDATE_SRC="${1:-}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
info()  { echo -e "${BLUE}[i]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
err()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
debug() { [ "$VERBOSE" = "true" ] && echo -e "[?] $1" || true; }
drun()  {
    if [ "$DRY_RUN" = "true" ]; then
        debug "DRY-RUN: $*"
    else
        "$@"
    fi
}

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash update-v1.sh <pacote>"
[ -n "$UPDATE_SRC" ] || err "Informe o caminho do pacote: sudo bash update-v1.sh /tmp/unbound-dashboard-v1-update-*.tar.gz"

EXTRACT_DIR=""
trap '[[ -n "$EXTRACT_DIR" ]] && rm -rf "$EXTRACT_DIR"' EXIT

# ============================================================
# Extrair pacote
# ============================================================
resolve_package() {
    if [ -d "$UPDATE_SRC" ]; then
        EXTRACT_DIR="$UPDATE_SRC"
        info "Usando diretório: $EXTRACT_DIR"
    elif [ -f "$UPDATE_SRC" ]; then
        EXTRACT_DIR=$(mktemp -d /tmp/unbound-v1-update-XXXXXX)
        info "Extraindo $UPDATE_SRC..."
        tar xzf "$UPDATE_SRC" -C "$EXTRACT_DIR" --strip-components=1
        log "Pacote extraído"
    else
        err "Pacote não encontrado: $UPDATE_SRC"
    fi

    [ -f "$EXTRACT_DIR/VERSION" ] || err "Pacote inválido: arquivo VERSION não encontrado"
    NEW_VERSION=$(tr -d '[:space:]' < "$EXTRACT_DIR/VERSION")
    CURRENT_VERSION=$(tr -d '[:space:]' < "$WEB_DIR/VERSION" 2>/dev/null || echo "desconhecida")

    echo ""
    echo "╔══════════════════════════════════════════════════════╗"
    echo "║   Unbound Dashboard — Update PHP v1                  ║"
    echo "╚══════════════════════════════════════════════════════╝"
    echo ""
    info "Versão atual: $CURRENT_VERSION → Nova versão: $NEW_VERSION"
    [ "$DRY_RUN" = "true" ] && warn "MODO DRY-RUN: nenhuma alteração será feita."
    echo ""
}

# ============================================================
# Backup do WEB_DIR
# ============================================================
create_backup() {
    info "Criando backup de $WEB_DIR..."
    drun mkdir -p "$BACKUP_BASE"
    local backup_file="$BACKUP_BASE/v1-${CURRENT_VERSION}-${TIMESTAMP}.tar.gz"
    drun tar czf "$backup_file" \
        --exclude="$WEB_DIR/.git" \
        --exclude="$WEB_DIR/.venv" \
        "$WEB_DIR" 2>/dev/null || true
    log "Backup: $backup_file"

    # Manter apenas os 5 mais recentes
    if [ "$DRY_RUN" = "false" ]; then
        ls -t "$BACKUP_BASE"/v1-*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
    fi
}

# ============================================================
# Sincronizar apenas arquivos PHP v1
# ============================================================
sync_code() {
    info "Sincronizando frontend PHP v1 em $WEB_DIR..."

    drun rsync -a --delete \
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
        --exclude='data/*.db' \
        --exclude='data/*.duckdb' \
        --exclude='data/*.wal' \
        --exclude='data/*.shm' \
        --exclude='data/*.bak' \
        --exclude='data/tmp/*.lock' \
        "$EXTRACT_DIR/app/" "$WEB_DIR/"

    # Copia VERSION + CHANGELOG separadamente
    for f in VERSION CHANGELOG.md; do
        if [ -f "$EXTRACT_DIR/$f" ]; then
            drun cp "$EXTRACT_DIR/$f" "$WEB_DIR/$f"
            debug "Copiado: $f"
        fi
    done

    drun chown -R www-data:www-data "$WEB_DIR" 2>/dev/null || true
    log "Frontend PHP v1 atualizado"
}

# ============================================================
# Validação pós-update
# ============================================================
validate_update() {
    [ "$DRY_RUN" = "true" ] && return

    info "Validando arquivos PHP no destino..."
    local php_errors=0
    while IFS= read -r f; do
        php -l "$f" > /dev/null 2>&1 || { warn "Erro PHP: $f"; php_errors=$((php_errors + 1)); }
    done < <(find "$WEB_DIR" -maxdepth 2 -name "*.php" -type f)
    [ "$php_errors" -eq 0 ] && log "Todos os arquivos PHP válidos" \
        || warn "$php_errors arquivo(s) com problema(s) — verifique manualmente"
}

# ============================================================
# Reiniciar apenas o servidor web (Apache/Caddy)
# ============================================================
restart_web() {
    info "Recarregando servidor web..."
    if systemctl is-active apache2 &>/dev/null; then
        drun systemctl reload apache2 && log "Apache2 recarregado"
    elif systemctl is-active caddy &>/dev/null; then
        drun systemctl reload caddy && log "Caddy recarregado"
    else
        warn "Nenhum servidor web ativo detectado (apache2/caddy). Reinicie manualmente."
    fi
}

# ============================================================
# Resumo
# ============================================================
print_summary() {
    echo ""
    if [ "$DRY_RUN" = "true" ]; then
        echo "╔══════════════════════════════════════════════════════╗"
        echo "║  DRY-RUN concluído — nenhuma alteração foi feita     ║"
        echo "╚══════════════════════════════════════════════════════╝"
    else
        echo "╔══════════════════════════════════════════════════════╗"
        echo "║  Update PHP v1 aplicado com sucesso! ✓               ║"
        echo "╚══════════════════════════════════════════════════════╝"
        echo ""
        echo "  Versão instalada: $NEW_VERSION"
        echo "  Diretório:        $WEB_DIR"
        echo "  Data/Hora:        $(date '+%d/%m/%Y %H:%M:%S')"
    fi
    echo ""
}

main() {
    resolve_package
    create_backup
    sync_code
    validate_update
    restart_web
    print_summary
}

main
