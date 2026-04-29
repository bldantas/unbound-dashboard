#!/bin/bash
# ============================================================
# Unbound Dashboard — Build Update Package
# Gera tarball com apenas os arquivos alterados
# ============================================================

set -euo pipefail

# ============================================================
# CONFIGURAÇÃO
# ============================================================
DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="/tmp/unbound-dashboard-update-$$"
VERSION_FILE="${DASHBOARD_DIR}/VERSION"
VERSION=""
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT_DIR="${DASHBOARD_DIR}/dist"
AUTO_BUMP_VERSION="${AUTO_BUMP_VERSION:-true}"
BUMP_TYPE="${BUMP_TYPE:-patch}"
ARCHIVE_PATH=""  # Será definido por create_archive()

# ============================================================
# CORES E FUNÇÕES
# ============================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { printf "${GREEN}[✓]${NC} %s\n" "$1"; }
info()  { printf "${BLUE}[i]${NC} %s\n" "$1"; }
warn()  { printf "${YELLOW}[!]${NC} %s\n" "$1"; }
error() { printf "${RED}[✗]${NC} %s\n" "$1"; }

cleanup() {
    rm -rf "$BUILD_DIR"
}

trap cleanup EXIT

# ============================================================
# CONTROLE DE VERSÃO
# ============================================================
read_version() {
    if [ ! -f "$VERSION_FILE" ]; then
        error "Arquivo VERSION não encontrado em: $VERSION_FILE"
        return 1
    fi

    local current
    current=$(tr -d '[:space:]' < "$VERSION_FILE")
    if ! [[ "$current" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        error "Formato inválido de versão em VERSION: '$current' (esperado: X.Y.Z)"
        return 1
    fi

    VERSION="$current"
    return 0
}

bump_version() {
    local major minor patch
    IFS='.' read -r major minor patch <<< "$VERSION"

    case "$BUMP_TYPE" in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
        *)
            error "BUMP_TYPE inválido: '$BUMP_TYPE' (use: patch|minor|major)"
            return 1
            ;;
    esac

    VERSION="${major}.${minor}.${patch}"
    printf "%s\n" "$VERSION" > "$VERSION_FILE"
    log "VERSION atualizado para ${VERSION} (BUMP_TYPE=${BUMP_TYPE})"
    return 0
}

# ============================================================
# PREPARAÇÃO
# ============================================================
prepare_build() {
    info "Preparando diretório de build..."
    mkdir -p "$BUILD_DIR"
    mkdir -p "$OUTPUT_DIR"
    log "Diretório de build criado"
}

# ============================================================
# COPIAR ARQUIVOS ATUALIZÁVEIS
# ============================================================
copy_source_files() {
    info "Copiando arquivos de origem..."
    local dir
    local file
    
    # Estrutura de diretórios da aplicação
    local dirs=(
        "src"
        "api"
        "includes"
        "scripts"
    )
    
    for dir in "${dirs[@]}"; do
        if [ -d "$DASHBOARD_DIR/$dir" ]; then
            mkdir -p "$BUILD_DIR/$dir"
            if [ "$dir" = "src" ]; then
                rsync -a "$DASHBOARD_DIR/$dir/" "$BUILD_DIR/$dir/" \
                    --exclude='Database.php' \
                    --exclude='data/*.json' \
                    --exclude='data/tmp/*.json' \
                    --exclude='data/tmp/*.tmp' \
                    --exclude='.git' \
                    --exclude='node_modules' \
                    2>/dev/null || true
            else
                cp -a "$DASHBOARD_DIR/$dir/." "$BUILD_DIR/$dir/" 2>/dev/null || true
            fi
        fi
    done

    mkdir -p "$BUILD_DIR/system/bin"
    mkdir -p "$BUILD_DIR/system/cron"
    mkdir -p "$BUILD_DIR/system/sudoers"
    mkdir -p "$BUILD_DIR/system/systemd"

    if [ -f "$DASHBOARD_DIR/tools/fix-mariadb.sh" ]; then
        cp "$DASHBOARD_DIR/tools/fix-mariadb.sh" "$BUILD_DIR/system/bin/"
    fi

    if [ -d "$DASHBOARD_DIR/tools/system/bin" ]; then
        cp -a "$DASHBOARD_DIR/tools/system/bin/." "$BUILD_DIR/system/bin/" 2>/dev/null || true
    fi

    if [ -d "$DASHBOARD_DIR/tools/system/cron" ]; then
        cp -a "$DASHBOARD_DIR/tools/system/cron/." "$BUILD_DIR/system/cron/" 2>/dev/null || true
    fi

    if [ -d "$DASHBOARD_DIR/tools/system/sudoers" ]; then
        cp -a "$DASHBOARD_DIR/tools/system/sudoers/." "$BUILD_DIR/system/sudoers/" 2>/dev/null || true
    fi

    if [ -d "$DASHBOARD_DIR/tools/system/systemd" ]; then
        cp -a "$DASHBOARD_DIR/tools/system/systemd/." "$BUILD_DIR/system/systemd/" 2>/dev/null || true
    fi
    
    # Arquivos raiz
    local root_files=(
        "VERSION"
        "CHANGELOG.md"
        "index.php"
        "login.php"
        "logout.php"
        "config.php"
        "health.php"
        "changelog.php"
        "logs.php"
        "diagnostics.php"
        "dns_benchmark.php"
        "exports.php"
        "threats.php"
        "alerts.php"
        "blocklist.php"
        "history.php"
        "setup.php"
        "reset.php"
        "recover.php"
    )
    
    for file in "${root_files[@]}"; do
        if [ -f "$DASHBOARD_DIR/$file" ]; then
            cp "$DASHBOARD_DIR/$file" "$BUILD_DIR/"
        fi
    done
    
    # Scripts de update/build
    cp "$DASHBOARD_DIR/tools/update.sh" "$BUILD_DIR/"
    
    # README com instruções
    cat > "$BUILD_DIR/README-UPDATE.md" << 'EOF'
# Unbound Dashboard — Pacote de Update

## Forma mais simples (recomendada)

O script de update aceita o .tar.gz diretamente — não é necessário extrair antes.

### 1. Transferir para o servidor
```bash
scp unbound-dashboard-update-*.tar.gz user@server:/tmp/
```

### 2. No servidor, rodar o update passando o .tar.gz
```bash
# Testar antes (dry-run — nenhuma mudança é feita)
sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/unbound-dashboard-update-*.tar.gz

# Aplicar o update de verdade
sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/unbound-dashboard-update-*.tar.gz
```

## Forma alternativa (sem o update.sh instalado no servidor)

Use quando o servidor de destino ainda não tem o dashboard instalado.

```bash
cd /tmp
tar xzf unbound-dashboard-update-*.tar.gz
cd unbound-dashboard-update-*

# Dry-run
sudo DRY_RUN=true bash update.sh .

# Update real
sudo bash update.sh .
```

## Opções disponíveis

| Variável            | Efeito                                      |
|---------------------|---------------------------------------------|
| `DRY_RUN=true`      | Simula sem fazer mudanças                   |
| `AUTO_RESTART=true` | Reinicia apache2 e unbound automaticamente  |
| `VERBOSE=true`      | Modo detalhado (debug)                      |
| `AUTO_PREPARE_DB=false` | Pula provisionamento automático do banco|

## Rollback

Backup automático é criado em `/var/backups/unbound-dashboard/`

```bash
sudo tar xzf /var/backups/unbound-dashboard/dashboard-TIMESTAMP.tar.gz -C /
sudo systemctl restart apache2 unbound
```

## Arquivos atualizados

- Código PHP: `src/`, `api/`, `includes/`, `scripts/`, arquivos raiz
- Templates de configuração do Unbound
- Scripts do sistema em `/usr/local/bin/`

**Nunca sobrescritos:**
- `src/Database.php` (credenciais do banco)
- `/etc/unbound/unbound.conf` (configuração principal)
EOF
    
    log "Arquivos copiados"
}

# ============================================================
# VALIDAR ARQUIVOS
# ============================================================
validate_files() {
    info "Validando arquivos de update..."
    
    # Validar PHP
    while IFS= read -r php_file; do
        if ! php -l "$php_file" &>/dev/null; then
            error "Erro de sintaxe em: $php_file"
            return 1
        fi
    done < <(find "$BUILD_DIR" -name "*.php" -type f)
    
    # Validar bash scripts
    while IFS= read -r bash_file; do
        if ! bash -n "$bash_file" 2>/dev/null; then
            error "Erro de sintaxe em: $bash_file"
            return 1
        fi
    done < <(find "$BUILD_DIR" -name "*.sh" -type f)
    
    log "Validação concluída com sucesso"
}

# ============================================================
# GERAR TARBALL
# ============================================================
create_archive() {
    info "Gerando arquivo de update..."
    
    local archive_name="unbound-dashboard-update-v${VERSION}-${TIMESTAMP}.tar.gz"
    ARCHIVE_PATH="$OUTPUT_DIR/$archive_name"
    
    # Criar tarball
    tar czf "$ARCHIVE_PATH" -C "$(dirname "$BUILD_DIR")" "$(basename "$BUILD_DIR")" 2>/dev/null
    
    # Verificar se foi criado
    if [ ! -f "$ARCHIVE_PATH" ]; then
        error "Erro ao criar arquivo de update"
        return 1
    fi
    
    local size=$(du -h "$ARCHIVE_PATH" | cut -f1)
    log "Arquivo gerado: $archive_name (${size})"
}

# ============================================================
# GERAR CHECKSUM
# ============================================================
create_checksum() {
    local archive_path="$1"
    local checksum_file="${archive_path}.sha256"
    
    info "Gerando checksum..."
    sha256sum "$archive_path" > "$checksum_file"
    log "Checksum salvo: $(basename "$checksum_file")"
}

# ============================================================
# RELATÓRIO FINAL
# ============================================================
print_summary() {
    local archive_path="$1"
    local archive_name=$(basename "$archive_path")
    
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Update Package Gerado com Sucesso! ✓           ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    echo "📦 Arquivo:     $archive_name"
    echo "📍 Localização: $OUTPUT_DIR/"
    echo "📅 Data/Hora:   $(date '+%d/%m/%Y %H:%M:%S')"
    echo "🔖 Versão:      $VERSION"
    echo ""
    echo "📋 Próximos passos:"
    echo ""
    echo "1. Transferir para servidor:"
    echo "   scp $OUTPUT_DIR/$archive_name user@server:/tmp/"
    echo ""
    echo "2. No servidor, testar antes (dry-run):"
    echo "   sudo DRY_RUN=true bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/$archive_name"
    echo ""
    echo "3. Aplicar o update:"
    echo "   sudo bash /var/www/html/unbound-dashboard/tools/update.sh /tmp/$archive_name"
    echo ""
    echo "✅ Fazer backup antes de atualizar!"
    echo ""
}

# ============================================================
# MAIN
# ============================================================
main() {
    read_version || exit 1

    if [ "$AUTO_BUMP_VERSION" = "true" ]; then
        bump_version || exit 1
    fi

    echo "╔══════════════════════════════════════════════════╗"
    echo "║  Unbound Dashboard — Build Update Package v${VERSION}   ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    
    prepare_build
    copy_source_files
    validate_files
    create_archive
    create_checksum "$ARCHIVE_PATH"
    print_summary "$ARCHIVE_PATH"
}

# ============================================================
# EXECUÇÃO
# ============================================================
main
