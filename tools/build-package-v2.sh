#!/bin/bash
# ============================================================
# Wrapper de build da linha v2
#
# Objetivo:
# - Permitir disparar o empacotamento da v2 a partir do repo legado v1.
# - Evitar confusão entre scripts v1 (PHP/MariaDB) e v2 (Python/DuckDB).
#
# Uso:
#   sudo bash tools/build-package-v2.sh [--skip-frontend]
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
info() { echo -e "${CYAN}[i]${NC} $1"; }

V2_DIR="${V2_DIR:-/opt/unbound-dashboard}"
V2_SCRIPT="$V2_DIR/tools/build-package.sh"

[ -d "$V2_DIR" ] || err "Diretório v2 não encontrado: $V2_DIR"
[ -x "$V2_SCRIPT" ] || err "Script de build v2 não encontrado/executável: $V2_SCRIPT"
[ -f "$V2_DIR/VERSION" ] || err "Arquivo VERSION não encontrado em $V2_DIR"

info "Chamando builder da v2 em: $V2_SCRIPT"
"$V2_SCRIPT" "$@"

LATEST_PKG=$(ls -t "$V2_DIR"/dist/unbound-dashboard-v*.tar.gz 2>/dev/null | head -1 || true)
if [ -n "$LATEST_PKG" ]; then
    log "Build v2 concluído"
    echo "Pacote: $LATEST_PKG"
else
    warn "Build executado, mas nenhum pacote encontrado em $V2_DIR/dist"
fi
