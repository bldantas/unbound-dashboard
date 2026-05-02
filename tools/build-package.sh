#!/bin/bash
# ============================================================
# Unbound Dashboard v2 — Build Package Script
# Gera pacote .tar.gz para instalação em novos servidores.
#
# Uso: bash tools/build-package.sh [--skip-frontend]
# ============================================================

set -euo pipefail

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

SKIP_FRONTEND="${SKIP_FRONTEND:-false}"
for arg in "$@"; do
    [[ "$arg" == "--skip-frontend" ]] && SKIP_FRONTEND=true
done

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$PROJECT_DIR/VERSION"

[ -f "$VERSION_FILE" ] || err "Arquivo VERSION não encontrado em $PROJECT_DIR"
VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || err "Formato inválido em VERSION: '$VERSION'"

VERSION_API_FILE="$PROJECT_DIR/VERSION-api"
VERSION_API=""
[ -f "$VERSION_API_FILE" ] && VERSION_API=$(tr -d '[:space:]' < "$VERSION_API_FILE") || warn "VERSION-api não encontrado"

PACKAGE_NAME="unbound-dashboard-v${VERSION}"
BUILD_DIR="/tmp/unbound-dashboard-build-$$"
OUTPUT_DIR="$PROJECT_DIR/dist"

trap 'rm -rf "$BUILD_DIR"' EXIT

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Unbound Dashboard v2 — Package Builder v${VERSION}   ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================
# 1. Build do frontend
# ============================================================
if [ "$SKIP_FRONTEND" = "false" ]; then
    info "Construindo frontend Vue 3..."
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    [ -d "$FRONTEND_DIR" ] || err "Diretório frontend/ não encontrado."
    command -v node &>/dev/null || err "Node.js não encontrado. Instale Node 20+."
    command -v npm  &>/dev/null || err "npm não encontrado."

    pushd "$FRONTEND_DIR" > /dev/null
    npm ci --silent
    npm run build
    popd > /dev/null
    log "Frontend construído: frontend/dist/"
else
    warn "Build do frontend pulada (--skip-frontend)"
    [ -d "$PROJECT_DIR/frontend/dist" ] || err "frontend/dist/ não existe. Remova --skip-frontend."
fi

# ============================================================
# 2. Montar staging
# ============================================================
info "Montando pacote de instalação..."
STAGING="$BUILD_DIR/$PACKAGE_NAME"
mkdir -p "$STAGING"

# Aplicação Python
rsync -a \
    --exclude='.git' \
    --exclude='.venv' \
    --exclude='__pycache__' \
    --exclude='*.pyc' \
    --exclude='.pytest_cache' \
    --exclude='.mypy_cache' \
    --exclude='.ruff_cache' \
    --exclude='dist/' \
    --exclude='frontend/node_modules' \
    --exclude='frontend/src' \
    --exclude='frontend/public' \
    --exclude='frontend/tsconfig*' \
    --exclude='frontend/vite.config.ts' \
    --exclude='frontend/package*.json' \
    --exclude='frontend/index.html' \
    --exclude='tests/' \
    --exclude='.github/' \
    --exclude='tools/' \
    --exclude='unbound-dashboard-update-*' \
    --exclude='*.tar.gz' \
     --exclude='data/*.db' \
     --exclude='data/*.sqlite' \
     --exclude='data/*.duckdb' \
     --exclude='data/*.wal' \
     --exclude='data/*.shm' \
     --exclude='data/*.bak' \
     --exclude='data/tmp/*.lock' \
    "$PROJECT_DIR/" "$STAGING/app/"

# Mantém apenas o dist do frontend
log "Código Python e frontend/dist copiados"

# Garante pacote limpo sem artefatos de banco/runtime locais
find "$STAGING/app/data" -maxdepth 1 -type f \
     \( -name '*.db' -o -name '*.sqlite' -o -name '*.duckdb' -o -name '*.wal' -o -name '*.shm' -o -name '*.bak' \) \
     -delete 2>/dev/null || true
find "$STAGING/app/data/tmp" -maxdepth 1 -type f -name '*.lock' -delete 2>/dev/null || true

# Copiar arquivos de versão e changelog separados
for f in VERSION VERSION-api CHANGELOG.md CHANGELOG-api.md; do
    [ -f "$PROJECT_DIR/$f" ] && cp "$PROJECT_DIR/$f" "$STAGING/app/$f"
done

# Scripts de sistema
mkdir -p "$STAGING/system/systemd"
mkdir -p "$STAGING/system/caddy"
mkdir -p "$STAGING/system/env"

cp "$PROJECT_DIR/deployments/systemd/unbound-api.service" "$STAGING/system/systemd/"
cp "$PROJECT_DIR/deployments/caddy/Caddyfile"             "$STAGING/system/caddy/"
log "Arquivos de deploy copiados"

# Template de configuração de ambiente
cat > "$STAGING/system/env/unbound-dashboard.env" << 'ENVEOF'
# Unbound Dashboard v2 — Configuração de ambiente
# Copiar para /etc/unbound-dashboard/env
# Ajuste os valores antes de iniciar o serviço

# Segredo JWT — OBRIGATÓRIO: gere com: openssl rand -hex 32
JWT_SECRET=CHANGE_ME_BEFORE_STARTING

# Banco DuckDB
DB_PATH=/var/lib/unbound-dashboard/unbound_dash.duckdb

# Redis
REDIS_URL=redis://127.0.0.1:6379/0

# API
API_HOST=127.0.0.1
API_PORT=8000
LOG_LEVEL=info

# Log do Unbound — deve coincidir com logfile: em /etc/unbound/includes/general.conf
UNBOUND_LOG=/var/log/unbound/unbound.log

# Binário do unbound-control
UNBOUND_CONTROL=/usr/sbin/unbound-control

# CORS (adicione o domínio real em produção)
# CORS_ORIGINS=["https://dashboard.example.com"]
ENVEOF

# Instalador
cp "$PROJECT_DIR/tools/install.sh" "$STAGING/install.sh"
chmod +x "$STAGING/install.sh"
cp "$PROJECT_DIR/tools/update.sh" "$STAGING/update.sh"
chmod +x "$STAGING/update.sh"
log "Instalador incluído"

# ============================================================
# 3. LEIAME
# ============================================================
cat > "$STAGING/LEIAME.txt" << EOF
============================================================
  Unbound Dashboard
     Frontend PHP v1 ${VERSION} + API Python v2 ${VERSION_API}
     Pacote de Instalação — Sistema Híbrido
============================================================

Requisitos:
  - Debian 12+ ou Ubuntu 22.04+
  - Acesso root
  - Conexão com a internet (para instalar pacotes)
  - Unbound instalado e configurado

Instalação:
  1. Copie este pacote para o servidor:
       scp ${PACKAGE_NAME}.tar.gz user@server:/tmp/

  2. No servidor, extraia e instale:
       tar xzf /tmp/${PACKAGE_NAME}.tar.gz -C /tmp/
       cd /tmp/${PACKAGE_NAME}
       sudo bash install.sh

  3. Após a instalação, valide os arquivos de ambiente:
       sudo nano /etc/unbound-dashboard/env
       sudo nano /etc/unbound-dashboard.env

  4. Verifique os serviços:
       sudo systemctl status unbound-api redis-server apache2

  5. Opcional (proxy TLS com Caddy):
       sudo nano /etc/caddy/Caddyfile
       sudo systemctl reload caddy

Conteúdo do pacote:
     app/          → Código completo (PHP v1 + API v2)
  system/       → Templates systemd, Caddy, env
  install.sh    → Instalador automatizado
     update.sh     → Script de atualização
  LEIAME.txt    → Este arquivo
============================================================
EOF

# ============================================================
# 4. Gerar tarball
# ============================================================
info "Gerando tarball..."
mkdir -p "$OUTPUT_DIR"
cd "$BUILD_DIR"
tar czf "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" "$PACKAGE_NAME/"
sha256sum "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" > "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz.sha256"

SIZE=$(du -h "$OUTPUT_DIR/${PACKAGE_NAME}.tar.gz" | cut -f1)

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║          Pacote gerado com sucesso! ✓            ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
log "Arquivo:  $OUTPUT_DIR/${PACKAGE_NAME}.tar.gz"
log "SHA256:   $OUTPUT_DIR/${PACKAGE_NAME}.tar.gz.sha256"
log "Tamanho:  $SIZE"
echo ""
info "Para instalar:"
echo -e "   ${CYAN}scp $OUTPUT_DIR/${PACKAGE_NAME}.tar.gz user@server:/tmp/${NC}"
echo -e "   ${CYAN}tar xzf /tmp/${PACKAGE_NAME}.tar.gz -C /tmp/ && sudo bash /tmp/${PACKAGE_NAME}/install.sh${NC}"
echo ""
