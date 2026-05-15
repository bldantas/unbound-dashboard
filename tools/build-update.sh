#!/bin/bash
# ============================================================
# Unbound Dashboard — Build Update Package v2.2.0+
#
# Gera um tarball de update incremental compatível com `update.sh`.
# O pacote contém:
#   - dashboard/    → frontend PHP
#   - api_service/  → FastAPI + DuckDB + workers (sem .venv)
#   - system/       → systemd, apache, sudoers, bin, cron, etc
#   - update.sh     → script de aplicação (cópia do tools/update.sh)
#
# Uso:
#   sudo bash tools/build-update.sh
#
# Variáveis:
#   AUTO_BUMP_VERSION=false  Não dá bump automático no VERSION
#   BUMP_TYPE=patch|minor|major  Tipo de bump (default: patch)
# ============================================================

set -euo pipefail

DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="/tmp/unbound-dashboard-update-$$"
VERSION_FILE="${DASHBOARD_DIR}/VERSION"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT_DIR="${DASHBOARD_DIR}/dist"
AUTO_BUMP_VERSION="${AUTO_BUMP_VERSION:-true}"
BUMP_TYPE="${BUMP_TYPE:-patch}"
VERSION=""
ARCHIVE_PATH=""

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

log()   { printf "${GREEN}[✓]${NC} %s\n" "$1"; }
info()  { printf "${BLUE}[i]${NC} %s\n" "$1"; }
warn()  { printf "${YELLOW}[!]${NC} %s\n" "$1"; }
error() { printf "${RED}[✗]${NC} %s\n" "$1" >&2; }

cleanup() { rm -rf "$BUILD_DIR"; }
trap cleanup EXIT

# ============================================================
# VERSÃO
# ============================================================
read_version() {
    [ -f "$VERSION_FILE" ] || { error "VERSION ausente em $VERSION_FILE"; return 1; }
    VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
    [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { error "VERSION com formato inválido: $VERSION"; return 1; }
}

bump_version() {
    local major minor patch
    IFS='.' read -r major minor patch <<< "$VERSION"
    case "$BUMP_TYPE" in
        major) major=$((major + 1)); minor=0; patch=0 ;;
        minor) minor=$((minor + 1)); patch=0 ;;
        patch) patch=$((patch + 1)) ;;
        *) error "BUMP_TYPE inválido: $BUMP_TYPE"; return 1 ;;
    esac
    VERSION="${major}.${minor}.${patch}"
    printf "%s\n" "$VERSION" > "$VERSION_FILE"
    log "VERSION → $VERSION (BUMP=$BUMP_TYPE)"
}

# ============================================================
# COPIA DO PROJETO
# ============================================================
prepare() {
    info "Preparando $BUILD_DIR..."
    mkdir -p "$BUILD_DIR"
    mkdir -p "$OUTPUT_DIR"
}

copy_dashboard() {
    info "Copiando dashboard/ (frontend PHP)..."
    mkdir -p "$BUILD_DIR/dashboard"
    rsync -a \
        --exclude='.git' \
        --exclude='.vscode' \
        --exclude='docs' \
        --exclude='*.md' \
        --exclude='api_service' \
        --exclude='dist' \
        --exclude='data/.installed' \
        --exclude='data/*.json' \
        --exclude='data/tmp/*' \
        --exclude='src/data/tmp/*' \
        --exclude='src/data/*.json' \
        --exclude='src/data/official_blocklist.conf' \
        --exclude='test_help.php' \
        --exclude='tools/docker' \
        "$DASHBOARD_DIR/" "$BUILD_DIR/dashboard/"

    # Força inclusão de CHANGELOG.md (excluído pelo *.md acima)
    [ -f "$DASHBOARD_DIR/CHANGELOG.md" ] && cp "$DASHBOARD_DIR/CHANGELOG.md" "$BUILD_DIR/dashboard/CHANGELOG.md"

    # Diretórios de dados vazios com .gitkeep
    mkdir -p "$BUILD_DIR/dashboard/data/tmp" "$BUILD_DIR/dashboard/src/data/tmp"
    touch "$BUILD_DIR/dashboard/data/.gitkeep" "$BUILD_DIR/dashboard/data/tmp/.gitkeep"
    touch "$BUILD_DIR/dashboard/src/data/.gitkeep" "$BUILD_DIR/dashboard/src/data/tmp/.gitkeep"

    log "dashboard/ pronto"
}

copy_apiservice() {
    info "Copiando api_service/..."
    [ -d "$DASHBOARD_DIR/api_service" ] || { error "api_service/ ausente — pacote inválido"; exit 1; }

    rsync -a \
        --exclude='.venv' \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        --exclude='.pytest_cache' \
        --exclude='.ruff_cache' \
        --exclude='.mypy_cache' \
        --exclude='*.bak' \
        --exclude='*.bak-*' \
        "$DASHBOARD_DIR/api_service/" "$BUILD_DIR/api_service/"

    log "api_service/ pronto"
}

copy_system() {
    info "Copiando configurações do sistema..."
    mkdir -p "$BUILD_DIR/system/sudoers" \
             "$BUILD_DIR/system/systemd" \
             "$BUILD_DIR/system/apache" \
             "$BUILD_DIR/system/etc" \
             "$BUILD_DIR/system/bin" \
             "$BUILD_DIR/system/cron"

    # Sudoers (do sistema vivo, fonte de verdade)
    if [ -f /etc/sudoers.d/unbound-dashboard ]; then
        cp /etc/sudoers.d/unbound-dashboard "$BUILD_DIR/system/sudoers/"
    fi

    # Systemd unit do api_service (template versionado em api_service/deployments/)
    local src_unit="$DASHBOARD_DIR/api_service/deployments/systemd/unbound-dashboard-api.service"
    [ -f "$src_unit" ] && cp "$src_unit" "$BUILD_DIR/system/systemd/"

    # Apache conf-available
    local src_conf="$DASHBOARD_DIR/api_service/deployments/apache/unbound-dashboard-api.conf"
    [ -f "$src_conf" ] && cp "$src_conf" "$BUILD_DIR/system/apache/"

    # Env file template
    local src_env="$DASHBOARD_DIR/api_service/deployments/api-v1.env.example"
    [ -f "$src_env" ] && cp "$src_env" "$BUILD_DIR/system/etc/api-v1.env.example"

    # Health-fix script
    [ -f /usr/local/bin/unbound-health-fix.sh ] && cp /usr/local/bin/unbound-health-fix.sh "$BUILD_DIR/system/bin/"

    # Setup-unbound-logs script
    [ -f "$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh" ] && \
        cp "$DASHBOARD_DIR/tools/system/bin/setup-unbound-logs.sh" "$BUILD_DIR/system/bin/"

    # Crons
    [ -f "$DASHBOARD_DIR/tools/system/cron/unbound-dashboard-crons" ] && \
        cp "$DASHBOARD_DIR/tools/system/cron/unbound-dashboard-crons" "$BUILD_DIR/system/cron/"

    log "system/ pronto"
}

copy_update_script() {
    cp "$DASHBOARD_DIR/tools/update.sh" "$BUILD_DIR/update.sh"
    chmod +x "$BUILD_DIR/update.sh"
    log "update.sh incluído"
}

write_readme() {
    cat > "$BUILD_DIR/README-UPDATE.md" << EOF
# Unbound Dashboard — Pacote de Update v${VERSION}

## Aplicar update

\`\`\`bash
# Dry-run (não aplica nada, só simula):
sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/unbound-dashboard-update-v${VERSION}-${TIMESTAMP}.tar.gz

# Real:
sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/unbound-dashboard-update-v${VERSION}-${TIMESTAMP}.tar.gz
\`\`\`

## Variáveis aceitas

| Variável | Default | Efeito |
|---|---|---|
| \`DRY_RUN\` | false | Simula sem aplicar |
| \`AUTO_RESTART\` | true | Reinicia api_service + Apache ao final |
| \`SKIP_VENV_SYNC\` | false | Pula uv sync mesmo se pyproject mudou |
| \`VERBOSE\` | false | Saída detalhada |

## Backup automático antes do update

- \`/var/backups/unbound-dashboard/dashboard-<TS>.tar.gz\`
- \`/var/backups/unbound-dashboard/duckdb-<TS>.duckdb\`
- \`/var/backups/unbound-dashboard/api-v1.env-<TS>\`

## Rollback

\`\`\`bash
sudo systemctl stop unbound-dashboard-api
sudo tar xzf /var/backups/unbound-dashboard/dashboard-<TS>.tar.gz -C /
sudo cp -a /var/backups/unbound-dashboard/duckdb-<TS>.duckdb /var/lib/unbound-dashboard/unbound_dash.duckdb
sudo cp -a /var/backups/unbound-dashboard/api-v1.env-<TS> /etc/unbound-dashboard/api-v1.env
sudo systemctl start unbound-dashboard-api
\`\`\`

## NÃO sobrescritos

- \`/etc/unbound-dashboard/api-v1.env\` (preserva JWT_SECRET local)
- \`/var/lib/unbound-dashboard/unbound_dash.duckdb\` (banco vivo)
- \`/etc/unbound/unbound.conf\` (config principal do resolver)
- \`src/Database.php\` (stub local, mantido pra evitar conflitos)
EOF
    log "README-UPDATE.md gerado"
}

# ============================================================
# VALIDAÇÃO
# ============================================================
validate() {
    info "Validando sintaxe dos arquivos..."
    local fail=0
    while IFS= read -r f; do
        php -l "$f" >/dev/null 2>&1 || { error "PHP inválido: $f"; fail=1; }
    done < <(find "$BUILD_DIR" -name "*.php" -type f)

    while IFS= read -r f; do
        bash -n "$f" 2>/dev/null || { error "Bash inválido: $f"; fail=1; }
    done < <(find "$BUILD_DIR" -name "*.sh" -type f)

    [ "$fail" -eq 0 ] || { error "Validação falhou — abortando"; exit 1; }
    log "Sintaxe validada"
}

# ============================================================
# TARBALL + CHECKSUM
# ============================================================
create_archive() {
    info "Empacotando tarball..."
    local name="unbound-dashboard-update-v${VERSION}-${TIMESTAMP}.tar.gz"
    ARCHIVE_PATH="$OUTPUT_DIR/$name"

    # tar do conteúdo do BUILD_DIR (sem o nome aleatório do tmp)
    tar czf "$ARCHIVE_PATH" -C "$BUILD_DIR" \
        dashboard api_service system update.sh README-UPDATE.md

    [ -f "$ARCHIVE_PATH" ] || { error "Falha ao gerar tarball"; exit 1; }
    log "$name ($(du -h "$ARCHIVE_PATH" | cut -f1))"

    sha256sum "$ARCHIVE_PATH" > "${ARCHIVE_PATH}.sha256"
    log "Checksum: $(basename "${ARCHIVE_PATH}").sha256"
}

# ============================================================
# RELATÓRIO
# ============================================================
print_summary() {
    local name; name=$(basename "$ARCHIVE_PATH")
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Update Package gerado ✓                        ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    echo "Arquivo:  $ARCHIVE_PATH"
    echo "Versão:   $VERSION"
    echo "Data:     $(date '+%d/%m/%Y %H:%M:%S')"
    echo ""
    echo "Próximos passos:"
    echo "  1. scp $ARCHIVE_PATH user@server:/tmp/"
    echo "  2. sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/$name"
    echo "  3. sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/$name"
    echo ""
}

# ============================================================
# MAIN
# ============================================================
main() {
    read_version || exit 1
    [ "$AUTO_BUMP_VERSION" = "true" ] && bump_version

    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Build Update Package v${VERSION}                  ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""

    prepare
    copy_dashboard
    copy_apiservice
    copy_system
    copy_update_script
    write_readme
    validate
    create_archive
    print_summary
}

main
