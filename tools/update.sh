#!/bin/bash
# ============================================================
# Unbound Dashboard v2 — Update Script
# Aplica um pacote de update gerado por build-update.sh.
#
# Uso:
#   sudo bash update.sh /tmp/unbound-dashboard-update-v2.1.0-*.tar.gz
#   sudo DRY_RUN=true bash update.sh /tmp/unbound-dashboard-update-*.tar.gz
#
# Variáveis de ambiente:
#   DRY_RUN=true        Simula sem fazer mudanças
#   VERBOSE=true        Modo detalhado
#   AUTO_RESTART=true   Reinicia serviço automaticamente
#   FORCE_CADDY=true    Sobrescreve Caddyfile (padrão: não)
# ============================================================

set -euo pipefail

DRY_RUN="${DRY_RUN:-false}"
VERBOSE="${VERBOSE:-false}"
AUTO_RESTART="${AUTO_RESTART:-false}"
FORCE_CADDY="${FORCE_CADDY:-false}"

INSTALL_DIR="/opt/unbound-dashboard"
DATA_DIR="/var/lib/unbound-dashboard"
BACKUP_BASE="/var/backups/unbound-dashboard"
ENV_FILE="/etc/unbound-dashboard/env"
SERVICE_NAME="unbound-api"
SERVICE_USER="unbound-dash"
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
    # Executa o comando apenas se não for dry-run
    if [ "$DRY_RUN" = "true" ]; then
        debug "DRY-RUN: $*"
    else
        "$@"
    fi
}

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash update.sh <pacote>"
[ -n "$UPDATE_SRC" ] || err "Informe o caminho do pacote: sudo bash update.sh /tmp/unbound-dashboard-update-*.tar.gz"

EXTRACT_DIR=""
trap '[[ -n "$EXTRACT_DIR" ]] && rm -rf "$EXTRACT_DIR"' EXIT

# ============================================================
# Extrair pacote (aceita .tar.gz ou diretório já extraído)
# ============================================================
resolve_package() {
    if [ -d "$UPDATE_SRC" ]; then
        EXTRACT_DIR="$UPDATE_SRC"
        info "Usando diretório: $EXTRACT_DIR"
    elif [ -f "$UPDATE_SRC" ]; then
        EXTRACT_DIR=$(mktemp -d /tmp/unbound-update-XXXXXX)
        info "Extraindo $UPDATE_SRC..."
        tar xzf "$UPDATE_SRC" -C "$EXTRACT_DIR" --strip-components=1
        log "Pacote extraído"
    else
        err "Pacote não encontrado: $UPDATE_SRC"
    fi

    [ -f "$EXTRACT_DIR/VERSION" ] || err "Pacote inválido: arquivo VERSION não encontrado em $EXTRACT_DIR"
    NEW_VERSION=$(tr -d '[:space:]' < "$EXTRACT_DIR/VERSION")
    CURRENT_VERSION=$(tr -d '[:space:]' < "$INSTALL_DIR/VERSION" 2>/dev/null || echo "desconhecida")
    info "Versão atual: $CURRENT_VERSION → Nova versão: $NEW_VERSION"
}

# ============================================================
# Backup
# ============================================================
create_backup() {
    info "Criando backup..."
    drun mkdir -p "$BACKUP_BASE"
    local backup_file="$BACKUP_BASE/dashboard-${CURRENT_VERSION}-${TIMESTAMP}.tar.gz"
    drun tar czf "$backup_file" \
        --exclude="$INSTALL_DIR/.venv" \
        --exclude="$INSTALL_DIR/frontend/node_modules" \
        --exclude="$INSTALL_DIR/__pycache__" \
        "$INSTALL_DIR" 2>/dev/null || true
    log "Backup: $backup_file"
    # Manter apenas os 5 backups mais recentes
    if [ "$DRY_RUN" = "false" ]; then
        ls -t "$BACKUP_BASE"/dashboard-*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
    fi
}

# ============================================================
# Parar serviço
# ============================================================
stop_service() {
    if systemctl is-active "$SERVICE_NAME" &>/dev/null; then
        info "Parando $SERVICE_NAME..."
        drun systemctl stop "$SERVICE_NAME"
        log "Serviço parado"
    else
        debug "$SERVICE_NAME não estava ativo"
    fi
}

# ============================================================
# Sincronizar código
# ============================================================
sync_code() {
    info "Sincronizando código Python..."
    drun rsync -a --delete \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        "$EXTRACT_DIR/app/" "$INSTALL_DIR/app/"
    log "Código Python atualizado"

    info "Sincronizando migrations..."
    drun rsync -a \
        "$EXTRACT_DIR/migrations/" "$INSTALL_DIR/migrations/"
    log "Migrations sincronizadas"

    info "Sincronizando frontend/dist..."
    drun rsync -a --delete \
        "$EXTRACT_DIR/frontend/dist/" "$INSTALL_DIR/frontend/dist/"
    log "Frontend atualizado"

    # Arquivos raiz
    for f in VERSION CHANGELOG.md pyproject.toml; do
        if [ -f "$EXTRACT_DIR/$f" ]; then
            drun cp "$EXTRACT_DIR/$f" "$INSTALL_DIR/$f"
            debug "Copiado: $f"
        fi
    done
    log "Arquivos raiz atualizados"

    # systemd unit (sobrescreve sempre — não tem config local)
    if [ -f "$EXTRACT_DIR/system/systemd/unbound-api.service" ]; then
        drun cp "$EXTRACT_DIR/system/systemd/unbound-api.service" /etc/systemd/system/
        drun systemctl daemon-reload
        log "Serviço systemd atualizado"
    fi

    # Caddyfile.example — só copia; não sobrescreve o ativo
    if [ -f "$EXTRACT_DIR/system/caddy/Caddyfile.example" ]; then
        drun cp "$EXTRACT_DIR/system/caddy/Caddyfile.example" /etc/caddy/Caddyfile.example
        debug "Caddyfile.example atualizado em /etc/caddy/"
        if [ "$FORCE_CADDY" = "true" ]; then
            drun cp "$EXTRACT_DIR/system/caddy/Caddyfile.example" /etc/caddy/Caddyfile
            warn "Caddyfile substituído (FORCE_CADDY=true). Verifique antes de recarregar: sudo systemctl reload caddy"
        fi
    fi

    # Permissões
    drun chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR/app" "$INSTALL_DIR/migrations" \
        "$INSTALL_DIR/frontend/dist" "$INSTALL_DIR/VERSION" 2>/dev/null || true
}

# ============================================================
# Atualizar dependências Python
# ============================================================
update_deps() {
    if [ ! -f "$INSTALL_DIR/pyproject.toml" ]; then
        warn "pyproject.toml não encontrado — pulando atualização de deps"
        return
    fi

    info "Atualizando dependências Python..."
    if command -v uv &>/dev/null; then
        drun uv pip install --quiet --no-cache -r "$INSTALL_DIR/pyproject.toml" \
            --python "$INSTALL_DIR/.venv/bin/python"
    else
        drun "$INSTALL_DIR/.venv/bin/pip" install --quiet -r "$INSTALL_DIR/pyproject.toml"
    fi
    drun chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR/.venv"
    log "Dependências atualizadas"
}

# ============================================================
# Migrations
# ============================================================
run_migrations() {
    info "Executando migrations DuckDB..."
    [ -f "$ENV_FILE" ] && source <(grep -v '^#' "$ENV_FILE" | grep '=')
    DB_PATH="${DB_PATH:-$DATA_DIR/unbound_dash.duckdb}"

    if [ "$DRY_RUN" = "false" ]; then
        export DB_PATH
        cd "$INSTALL_DIR"
        sudo -u "$SERVICE_USER" .venv/bin/python -m app.db && log "Migrations executadas" \
            || warn "Erro nas migrations — verifique manualmente: cd $INSTALL_DIR && .venv/bin/python -m app.db"
    else
        debug "DRY-RUN: migrations não executadas"
    fi
}

# ============================================================
# Reiniciar serviço
# ============================================================
start_service() {
    if [ "$AUTO_RESTART" = "true" ] || [ "$DRY_RUN" = "false" ]; then
        info "Iniciando $SERVICE_NAME..."
        drun systemctl start "$SERVICE_NAME"

        # Aguarda até 10s para confirmar que subiu
        if [ "$DRY_RUN" = "false" ]; then
            local tries=0
            while [ $tries -lt 10 ]; do
                sleep 1
                tries=$((tries+1))
                if systemctl is-active "$SERVICE_NAME" &>/dev/null; then
                    log "Serviço $SERVICE_NAME ativo"
                    return
                fi
            done
            warn "Serviço pode não ter subido. Verifique: journalctl -u $SERVICE_NAME -n 50"
        fi
    else
        warn "Serviço não reiniciado automaticamente."
        warn "Execute: sudo systemctl start $SERVICE_NAME"
    fi
}

# ============================================================
# Relatório final
# ============================================================
print_summary() {
    local status_line
    if [ "$DRY_RUN" = "true" ]; then
        status_line="[DRY-RUN] Nenhuma modificação foi feita"
    else
        status_line="Update aplicado: $CURRENT_VERSION → $NEW_VERSION"
    fi

    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║       Unbound Dashboard v2 — Update ✓           ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    log "$status_line"
    echo ""
    echo "  Verificar status:  systemctl status $SERVICE_NAME"
    echo "  Verificar logs:    journalctl -u $SERVICE_NAME -f"
    echo "  Rollback:          tar xzf $BACKUP_BASE/dashboard-${CURRENT_VERSION}-${TIMESTAMP}.tar.gz -C /"
    echo "                     systemctl restart $SERVICE_NAME"
    echo ""
}

# ============================================================
# Main
# ============================================================
main() {
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Unbound Dashboard v2 — Update Script          ║"
    if [ "$DRY_RUN" = "true" ]; then
        echo "║   *** MODO DRY-RUN — NENHUMA MUDANÇA ***         ║"
    fi
    echo "╚══════════════════════════════════════════════════╝"
    echo ""

    resolve_package
    create_backup
    stop_service
    sync_code
    update_deps
    run_migrations
    start_service
    print_summary
}

main
