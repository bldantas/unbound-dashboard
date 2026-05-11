#!/bin/bash
# ============================================================
# Unbound Dashboard — Install From Git (one-liner)
#
# Clona o repo, gera o pacote local e roda o install.sh — tudo num único
# comando. Pra servidores Debian 12+/Ubuntu 22.04+ a partir do GitHub.
#
# Uso direto (one-liner):
#   curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
#       | sudo ADMIN_USERNAME=admin ADMIN_EMAIL=a@b.c ADMIN_PASSWORD='senha' bash
#
# Variáveis aceitas:
#   ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_PASSWORD   passadas pro install.sh
#   REPO_URL       (default: https://github.com/bldantas/unbound-dashboard.git)
#   REPO_BRANCH    (default: main)
#   GITHUB_TOKEN   PAT pra repos privados — injetado na URL do clone
#   WORK_DIR       (default: /tmp/unbound-dashboard-install)
#   KEEP_WORK_DIR=true   não remove $WORK_DIR ao final (debug)
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
step() { echo -e "\n${BOLD}── $1 ──${NC}"; }

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash $0  (ou via curl | sudo bash)"

REPO_URL="${REPO_URL:-https://github.com/bldantas/unbound-dashboard.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"
WORK_DIR="${WORK_DIR:-/tmp/unbound-dashboard-install}"
KEEP_WORK_DIR="${KEEP_WORK_DIR:-false}"

# Se GITHUB_TOKEN estiver setado e a URL for HTTPS GitHub, injeta o token
# (formato suportado por GitHub: https://oauth2:TOKEN@github.com/...)
CLONE_URL="$REPO_URL"
if [ -n "$GITHUB_TOKEN" ] && [[ "$REPO_URL" =~ ^https://github\.com/ ]]; then
    CLONE_URL="${REPO_URL/https:\/\/github.com\//https://oauth2:${GITHUB_TOKEN}@github.com/}"
fi

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  Unbound Dashboard — Install From Git                ║${NC}"
echo -e "${BOLD}║  Repo: ${REPO_URL}  ║${NC}"
echo -e "${BOLD}║  Branch: ${REPO_BRANCH}                                         ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"

# ============================================================
# 1. Dependências mínimas pra fazer o clone + build
# ============================================================
step "Etapa 1/4 — Dependências do build"

NEED_PKGS=()
command -v git    >/dev/null 2>&1 || NEED_PKGS+=("git")
command -v rsync  >/dev/null 2>&1 || NEED_PKGS+=("rsync")
command -v tar    >/dev/null 2>&1 || NEED_PKGS+=("tar")
command -v curl   >/dev/null 2>&1 || NEED_PKGS+=("curl")

if [ ${#NEED_PKGS[@]} -gt 0 ]; then
    info "Instalando: ${NEED_PKGS[*]}"
    DEBIAN_FRONTEND=noninteractive apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "${NEED_PKGS[@]}"
fi
log "Build deps OK"

# ============================================================
# 2. Clone (ou pull) do repo
# ============================================================
step "Etapa 2/4 — Clone do repositório"

if [ -d "$WORK_DIR/.git" ]; then
    info "Repo já existe em $WORK_DIR — atualizando..."
    # Re-aponta o remote pro CLONE_URL atual (pode incluir token novo)
    git -C "$WORK_DIR" remote set-url origin "$CLONE_URL"
    git -C "$WORK_DIR" fetch --quiet origin "$REPO_BRANCH"
    git -C "$WORK_DIR" reset --hard "origin/$REPO_BRANCH" --quiet
    git -C "$WORK_DIR" clean -fd --quiet
else
    rm -rf "$WORK_DIR"
    info "Clonando $REPO_URL (branch: $REPO_BRANCH)${GITHUB_TOKEN:+ — auth via GITHUB_TOKEN}..."
    git clone --depth 1 --branch "$REPO_BRANCH" --quiet "$CLONE_URL" "$WORK_DIR"
fi

# Garante que token não fica no remote dentro do work dir após o clone
if [ -n "$GITHUB_TOKEN" ]; then
    git -C "$WORK_DIR" remote set-url origin "$REPO_URL"
fi

VERSION=$(tr -d '[:space:]' < "$WORK_DIR/VERSION" 2>/dev/null || echo "unknown")
log "Repo em $WORK_DIR (versão $VERSION)"

# ============================================================
# 3. Build do pacote
# ============================================================
step "Etapa 3/4 — Build do pacote"

cd "$WORK_DIR"
bash tools/build-package.sh

PACKAGE="$WORK_DIR/tools/unbound-dashboard-v${VERSION}.tar.gz"
[ -f "$PACKAGE" ] || err "Pacote não foi gerado em $PACKAGE"
log "Pacote: $PACKAGE"

# Extrai pra dir adjacente
EXTRACT_DIR="$WORK_DIR/extracted"
rm -rf "$EXTRACT_DIR"
mkdir -p "$EXTRACT_DIR"
tar xzf "$PACKAGE" -C "$EXTRACT_DIR"
EXTRACTED="$EXTRACT_DIR/unbound-dashboard-v${VERSION}"
[ -d "$EXTRACTED" ] || err "Falha ao extrair pacote em $EXTRACT_DIR"
log "Pacote extraído em $EXTRACTED"

# ============================================================
# 4. Executa o install.sh do pacote
# ============================================================
step "Etapa 4/4 — Executando install.sh"

cd "$EXTRACTED"
# Preserva env vars do admin (sudo strip ENV por default; aqui já estamos como root).
ADMIN_USERNAME="${ADMIN_USERNAME:-}" \
ADMIN_EMAIL="${ADMIN_EMAIL:-}" \
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}" \
    bash install.sh

# ============================================================
# Cleanup
# ============================================================
if [ "$KEEP_WORK_DIR" = "true" ]; then
    warn "KEEP_WORK_DIR=true — preservando $WORK_DIR"
else
    rm -rf "$WORK_DIR"
fi

echo ""
log "Instalação concluída a partir de $REPO_URL@$REPO_BRANCH"
