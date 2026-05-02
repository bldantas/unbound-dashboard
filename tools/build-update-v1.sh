#!/bin/bash
# ============================================================
# Unbound Dashboard — Build Update Package (PHP Frontend v1)
# Gera tarball com apenas os arquivos do frontend PHP v1.
# Não inclui a API Python v2 nem dependências de sistema.
#
# Uso:
#   bash tools/build-update-v1.sh
#   AUTO_BUMP_VERSION=false bash tools/build-update-v1.sh
#   BUMP_TYPE=minor bash tools/build-update-v1.sh
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { printf "${GREEN}[✓]${NC} %s\n" "$1"; }
info() { printf "${BLUE}[i]${NC} %s\n" "$1"; }
warn() { printf "${YELLOW}[!]${NC} %s\n" "$1"; }
err()  { printf "${RED}[✗]${NC} %s\n" "$1"; exit 1; }

AUTO_BUMP_VERSION="${AUTO_BUMP_VERSION:-true}"
BUMP_TYPE="${BUMP_TYPE:-patch}"

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$PROJECT_DIR/VERSION"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BUILD_DIR="/tmp/unbound-dashboard-v1-update-$$"
OUTPUT_DIR="$PROJECT_DIR/dist"
VERSION=""

trap 'rm -rf "$BUILD_DIR"' EXIT

# ============================================================
# Versão
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
# Copiar apenas arquivos do frontend PHP v1
# ============================================================
copy_files() {
    info "Copiando arquivos PHP v1 para o pacote de update..."
    mkdir -p "$BUILD_DIR/app"

    # Apenas arquivos relevantes ao frontend PHP v1
    rsync -a \
        --include='*.php' \
        --include='*.css' \
        --include='*.js' \
        --include='*.json' \
        --include='*.sql' \
        --include='*.sh' \
        --include='*.md' \
        --include='api/***' \
        --include='includes/***' \
        --include='src/***' \
        --include='scripts/***' \
        --include='data/***' \
        --include='deployments/***' \
        --exclude='.git' \
        --exclude='.venv' \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        --exclude='app/' \
        --exclude='frontend/' \
        --exclude='migrations/' \
        --exclude='tests/' \
        --exclude='tools/' \
        --exclude='dist/' \
        --exclude='unbound-dashboard-update-*' \
        --exclude='*.tar.gz' \
        --exclude='data/*.db' \
        --exclude='data/*.sqlite' \
        --exclude='data/*.duckdb' \
        --exclude='data/*.wal' \
        --exclude='data/*.shm' \
        --exclude='data/*.bak' \
        --exclude='data/tmp/*.lock' \
        "$PROJECT_DIR/" "$BUILD_DIR/app/"

    # Garantir limpeza pós-cópia de artefatos de banco
    find "$BUILD_DIR/app/data" -maxdepth 1 -type f \
        \( -name '*.db' -o -name '*.sqlite' -o -name '*.duckdb' \
           -o -name '*.wal' -o -name '*.shm' -o -name '*.bak' \) \
        -delete 2>/dev/null || true
    find "$BUILD_DIR/app/data/tmp" -maxdepth 1 -type f -name '*.lock' -delete 2>/dev/null || true

    # Arquivos raiz
    for f in VERSION CHANGELOG.md; do
        [ -f "$PROJECT_DIR/$f" ] && cp "$PROJECT_DIR/$f" "$BUILD_DIR/$f"
    done

    printf "%s\n" "$VERSION" > "$BUILD_DIR/VERSION"
    printf "%s\n" "$VERSION" > "$BUILD_DIR/app/VERSION"

    # Script de update v1
    cp "$PROJECT_DIR/tools/update-v1.sh" "$BUILD_DIR/update.sh"
    chmod +x "$BUILD_DIR/update.sh"

    log "Arquivos PHP v1 copiados"
}

# ============================================================
# Validação
# ============================================================
validate() {
    info "Validando scripts bash..."
    while IFS= read -r f; do
        bash -n "$f" || err "Erro de sintaxe em: $f"
    done < <(find "$BUILD_DIR" -name "*.sh" -type f)

    info "Validando arquivos PHP..."
    local php_errors=0
    while IFS= read -r f; do
        php -l "$f" > /dev/null 2>&1 || { warn "Erro PHP em: $f"; php_errors=$((php_errors + 1)); }
    done < <(find "$BUILD_DIR/app" -name "*.php" -type f)
    [ "$php_errors" -eq 0 ] || err "$php_errors arquivo(s) PHP com erro(s) de sintaxe."

    log "Validação concluída"
}

# ============================================================
# Tarball + checksum
# ============================================================
create_archive() {
    info "Gerando tarball..."
    mkdir -p "$OUTPUT_DIR"

    local name="unbound-dashboard-v1-update-v${VERSION}-${TIMESTAMP}.tar.gz"
    local path="$OUTPUT_DIR/$name"

    tar czf "$path" -C "$(dirname "$BUILD_DIR")" "$(basename "$BUILD_DIR")"
    sha256sum "$path" > "${path}.sha256"

    SIZE=$(du -h "$path" | cut -f1)
    log "Arquivo: $name ($SIZE)"
    echo "$path"
}

print_summary() {
    local archive="$1"
    local name
    name=$(basename "$archive")

    echo ""
    echo "╔══════════════════════════════════════════════════════╗"
    echo "║   Update v1 Package Gerado com Sucesso! ✓            ║"
    echo "╚══════════════════════════════════════════════════════╝"
    echo ""
    echo "  Arquivo:    $name"
    echo "  Local:      $OUTPUT_DIR/"
    echo "  Versão:     $VERSION"
    echo "  Data/Hora:  $(date '+%d/%m/%Y %H:%M:%S')"
    echo ""
    echo "  Próximos passos:"
    echo ""
    echo "  1. Transferir para o servidor:"
    echo "     scp $archive user@server:/tmp/"
    echo ""
    echo "  2. Dry-run:"
    echo "     sudo DRY_RUN=true bash /tmp/$(basename "$BUILD_DIR")/update.sh $archive"
    echo ""
    echo "  3. Aplicar:"
    echo "     sudo bash /tmp/$(basename "$BUILD_DIR")/update.sh $archive"
    echo ""
}

main() {
    read_version
    [ "$AUTO_BUMP_VERSION" = "true" ] && bump_version

    echo ""
    echo "╔══════════════════════════════════════════════════════╗"
    echo "║  Unbound Dashboard — Build Update PHP v1 — v${VERSION} ║"
    echo "╚══════════════════════════════════════════════════════╝"
    echo ""

    copy_files
    validate
    ARCHIVE=$(create_archive)
    print_summary "$ARCHIVE"
}

main
