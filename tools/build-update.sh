#!/bin/bash
# ============================================================
# Unbound Dashboard v2 — Build Update Package
# Gera tarball com os arquivos atualizáveis do sistema.
#
# Uso:
#   bash tools/build-update.sh
#   BUMP_TYPE=minor bash tools/build-update.sh
#   AUTO_BUMP_VERSION=false bash tools/build-update.sh
#   SKIP_FRONTEND=true bash tools/build-update.sh
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { printf "${GREEN}[✓]${NC} %s\n" "$1"; }
info()  { printf "${BLUE}[i]${NC} %s\n" "$1"; }
warn()  { printf "${YELLOW}[!]${NC} %s\n" "$1"; }
err()   { printf "${RED}[✗]${NC} %s\n" "$1"; exit 1; }

AUTO_BUMP_VERSION="${AUTO_BUMP_VERSION:-true}"
BUMP_TYPE="${BUMP_TYPE:-patch}"
SKIP_FRONTEND="${SKIP_FRONTEND:-false}"

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$PROJECT_DIR/VERSION"
VERSION_API_FILE="$PROJECT_DIR/VERSION-api"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BUILD_DIR="/tmp/unbound-dashboard-update-$$"
OUTPUT_DIR="$PROJECT_DIR/dist"
VERSION=""

trap 'rm -rf "$BUILD_DIR"' EXIT

# ============================================================
# Controle de versão
# ============================================================
read_version() {
    [ -f "$VERSION_FILE" ] || err "Arquivo VERSION não encontrado: $VERSION_FILE"
    local v
    v=$(tr -d '[:space:]' < "$VERSION_FILE")
    [[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || err "Formato inválido em VERSION: '$v'"
    VERSION="$v"
}

bump_version() {
    local major minor patch
    IFS='.' read -r major minor patch <<< "$VERSION"
    case "$BUMP_TYPE" in
        major) major=$((major+1)); minor=0; patch=0 ;;
        minor) minor=$((minor+1)); patch=0 ;;
        patch) patch=$((patch+1)) ;;
        *) err "BUMP_TYPE inválido: '$BUMP_TYPE' (use patch|minor|major)" ;;
    esac
    VERSION="${major}.${minor}.${patch}"
    printf "%s\n" "$VERSION" > "$VERSION_FILE"
    log "VERSION → $VERSION (bump: $BUMP_TYPE)"
}

# ============================================================
# Build do frontend
# ============================================================
build_frontend() {
    if [ "$SKIP_FRONTEND" = "true" ]; then
        warn "Build do frontend pulada (SKIP_FRONTEND=true)"
        [ -d "$PROJECT_DIR/frontend/dist" ] || err "frontend/dist/ não existe."
        return
    fi

    info "Construindo frontend Vue 3..."
    command -v node &>/dev/null || err "Node.js não encontrado."
    command -v npm  &>/dev/null || err "npm não encontrado."
    pushd "$PROJECT_DIR/frontend" > /dev/null
    npm ci --silent
    npm run build
    popd > /dev/null
    log "Frontend construído"
}

# ============================================================
# Copiar arquivos atualizáveis
# ============================================================
copy_files() {
    info "Copiando arquivos para o pacote de update..."
    mkdir -p "$BUILD_DIR"

    # Projeto completo (API v2 + frontend PHP v1)
    rsync -a \
        --exclude='.git' \
        --exclude='.venv' \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        --exclude='.pytest_cache' \
        --exclude='.mypy_cache' \
        --exclude='.ruff_cache' \
        --exclude='dist/' \
        --exclude='tools/' \
        --exclude='tests/' \
        --exclude='unbound-dashboard-update-*' \
        --exclude='*.tar.gz' \
        "$PROJECT_DIR/" "$BUILD_DIR/app/"

    # Arquivos raiz
    for f in VERSION VERSION-api CHANGELOG.md CHANGELOG-api.md pyproject.toml; do
        [ -f "$PROJECT_DIR/$f" ] && cp "$PROJECT_DIR/$f" "$BUILD_DIR/$f"
    done

    # Templates de sistema (só sobrescreve se explicitamente pedido no update.sh)
    mkdir -p "$BUILD_DIR/system/systemd" "$BUILD_DIR/system/caddy" "$BUILD_DIR/system/env"
    cp "$PROJECT_DIR/deployments/systemd/unbound-api.service" "$BUILD_DIR/system/systemd/"
    cp "$PROJECT_DIR/deployments/caddy/Caddyfile.example"     "$BUILD_DIR/system/caddy/" 2>/dev/null || \
        cp "$PROJECT_DIR/deployments/caddy/Caddyfile"         "$BUILD_DIR/system/caddy/Caddyfile.example"

    # Script de update para o servidor
    cp "$PROJECT_DIR/tools/update.sh" "$BUILD_DIR/update.sh"
    chmod +x "$BUILD_DIR/update.sh"

    # Atualiza VERSION no staging com a versão pós-bump
    printf "%s\n" "$VERSION" > "$BUILD_DIR/VERSION"
    # Copia VERSION-api (sem bump automático)
    [ -f "$VERSION_API_FILE" ] && cp "$VERSION_API_FILE" "$BUILD_DIR/VERSION-api"

    log "Arquivos copiados"
}

# ============================================================
# Validação básica
# ============================================================
validate() {
    info "Validando scripts bash..."
    while IFS= read -r f; do
        bash -n "$f" || err "Erro de sintaxe em: $f"
    done < <(find "$BUILD_DIR" -name "*.sh" -type f)
    log "Validação concluída"
}

# ============================================================
# Gerar tarball + checksum
# ============================================================
create_archive() {
    info "Gerando tarball..."
    mkdir -p "$OUTPUT_DIR"

    local name="unbound-dashboard-update-v${VERSION}-${TIMESTAMP}.tar.gz"
    local path="$OUTPUT_DIR/$name"

    tar czf "$path" -C "$(dirname "$BUILD_DIR")" "$(basename "$BUILD_DIR")"
    sha256sum "$path" > "${path}.sha256"

    SIZE=$(du -h "$path" | cut -f1)
    log "Arquivo: $name ($SIZE)"
    echo "$path"
}

# ============================================================
# Resumo
# ============================================================
print_summary() {
    local archive="$1"
    local name
    name=$(basename "$archive")

    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Update Package Gerado com Sucesso! ✓           ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    echo "  Arquivo:    $name"
    echo "  Localização: $OUTPUT_DIR/"
    echo "  Versão:     $VERSION"
    echo "  Data/Hora:  $(date '+%d/%m/%Y %H:%M:%S')"
    echo ""
    echo "  Próximos passos:"
    echo ""
    echo "  1. Transferir para o servidor:"
    echo "     scp $archive user@server:/tmp/"
    echo ""
    echo "  2. Dry-run (sem modificações):"
    echo "     sudo DRY_RUN=true bash /tmp/unbound-dashboard-update-*/update.sh /tmp/$name"
    echo ""
    echo "  3. Aplicar o update:"
    echo "     sudo bash /tmp/unbound-dashboard-update-*/update.sh /tmp/$name"
    echo ""
}

# ============================================================
# Main
# ============================================================
main() {
    read_version

    [ "$AUTO_BUMP_VERSION" = "true" ] && bump_version

    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║  Unbound Dashboard v2 — Build Update v${VERSION}    ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""

    build_frontend
    copy_files
    validate
    ARCHIVE=$(create_archive)
    print_summary "$ARCHIVE"
}

main
