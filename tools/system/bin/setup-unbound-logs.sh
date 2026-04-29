#!/bin/bash
# ============================================================
# Setup Unbound Logging Files and Permissions
# Cria arquivo de log do Unbound com permissões corretas
# ============================================================

set -euo pipefail

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }

# Função para criar arquivo de log
setup_log_file() {
    local logfile="$1"
    local owner="$2"
    local perms="$3"
    
    if [ ! -f "$logfile" ]; then
        touch "$logfile"
        log "Arquivo $logfile criado"
    else
        log "$logfile já existe"
    fi
    
    chown "$owner" "$logfile"
    chmod "$perms" "$logfile"
    log "Permissões de $logfile configuradas ($owner, $perms)"
}

# Criar arquivo de log do Unbound
setup_log_file "/var/log/unbound.log" "unbound:unbound" "640"

# Permitir que www-data leia o arquivo (para Live Sniffer)
if command -v setfacl &> /dev/null; then
    setfacl -m u:www-data:r /var/log/unbound.log 2>/dev/null || warn "setfacl não disponível, www-data pode não conseguir ler o log"
else
    # Fallback: adicionar www-data ao grupo unbound
    if id -Gn unbound 2>/dev/null | grep -qw www-data; then
        log "www-data já está no grupo unbound"
    else
        warn "setfacl não disponível e www-data não está no grupo unbound"
        log "Adicionando www-data ao grupo unbound..."
        usermod -a -G unbound www-data 2>/dev/null || warn "Não conseguiu adicionar www-data ao grupo unbound"
    fi
fi

log "Setup de logging do Unbound concluído"
