#!/bin/bash
# ============================================================
# Unbound Dashboard — Release Script
#
# Publica uma release no GitHub a partir da VERSION atual:
#   1. Roda tools/build-update.sh (gera tarball + .sha256 em dist/)
#   2. Extrai notas do CHANGELOG.md (primeira seção `## vX.Y.Z — …`)
#   3. `gh release create vX.Y.Z` com tarball+sha256 como assets
#   4. Notifica o usuário com URL da release
#
# Uso:
#   bash tools/release.sh             # release a partir da VERSION atual
#   DRAFT=true bash tools/release.sh  # cria como rascunho (não publica)
#   PRERELEASE=true bash tools/release.sh
#
# Requisitos:
#   - `gh` CLI autenticado com escopo `repo` (gh auth login)
#   - Working tree limpo OU commits pendentes já pushed (avisamos se sujo)
#   - Tag vX.Y.Z não pode existir ainda (refuse, exit 1)
# ============================================================

set -euo pipefail

DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="${DASHBOARD_DIR}/VERSION"
CHANGELOG_FILE="${DASHBOARD_DIR}/CHANGELOG.md"
DIST_DIR="${DASHBOARD_DIR}/dist"
DRAFT="${DRAFT:-false}"
PRERELEASE="${PRERELEASE:-false}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

log()   { printf "${GREEN}[✓]${NC} %s\n" "$1"; }
info()  { printf "${BLUE}[i]${NC} %s\n" "$1"; }
warn()  { printf "${YELLOW}[!]${NC} %s\n" "$1"; }
error() { printf "${RED}[✗]${NC} %s\n" "$1" >&2; }
step()  { printf "\n${BOLD}── %s ──${NC}\n" "$1"; }

# ============================================================
# PRE-FLIGHT
# ============================================================
step "Pré-voo"

# 1. gh CLI presente + autenticado
command -v gh >/dev/null 2>&1 || { error "gh CLI não encontrado. Instale: https://cli.github.com/"; exit 1; }
gh auth status >/dev/null 2>&1 || { error "gh CLI não autenticado. Rode: gh auth login"; exit 1; }
log "gh CLI autenticado"

# 2. VERSION válida
[ -f "$VERSION_FILE" ] || { error "VERSION ausente em $VERSION_FILE"; exit 1; }
VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { error "VERSION com formato inválido: $VERSION"; exit 1; }
TAG="v${VERSION}"
log "Versão: $VERSION → tag $TAG"

# 3. Tag não existe ainda (no remote)
if gh release view "$TAG" >/dev/null 2>&1; then
    error "Release $TAG já existe no GitHub. Bump VERSION e CHANGELOG primeiro."
    exit 1
fi
log "Tag $TAG ainda não existe (ok pra criar)"

# 4. Working tree limpo (warn, não bloqueia — pode estar buildando local)
if [ -n "$(git -C "$DASHBOARD_DIR" status --porcelain 2>/dev/null | grep -v '^?? \.claude/' || true)" ]; then
    warn "Working tree tem mudanças não commitadas — release pode divergir do código publicado"
    warn "Continuar mesmo assim? Aguardando 3s (Ctrl+C pra abortar)..."
    sleep 3
fi

# 5. Commits locais não pushed?
LOCAL_HEAD=$(git -C "$DASHBOARD_DIR" rev-parse HEAD 2>/dev/null || echo "")
REMOTE_HEAD=$(git -C "$DASHBOARD_DIR" rev-parse origin/main 2>/dev/null || echo "")
if [ -n "$LOCAL_HEAD" ] && [ -n "$REMOTE_HEAD" ] && [ "$LOCAL_HEAD" != "$REMOTE_HEAD" ]; then
    warn "HEAD local ($LOCAL_HEAD) ≠ origin/main ($REMOTE_HEAD)"
    warn "Faça git push antes de criar release pra evitar divergência"
    warn "Aguardando 3s (Ctrl+C pra abortar)..."
    sleep 3
fi

# ============================================================
# EXTRAI NOTAS DO CHANGELOG
# ============================================================
step "Extraindo notas da release"

[ -f "$CHANGELOG_FILE" ] || { error "CHANGELOG.md ausente"; exit 1; }

# Pega o bloco entre `## v<VERSION>` e o próximo `## v` ou `---`
NOTES=$(awk -v version="$VERSION" '
    BEGIN { found=0; emit=0 }
    /^## v/ {
        if (emit) exit
        if ($0 ~ "^## v" version "([[:space:]]|$|—)") { found=1; emit=1; next }
    }
    /^---[[:space:]]*$/ {
        if (emit) exit
    }
    emit { print }
' "$CHANGELOG_FILE" | sed '/./,$!d' | awk 'BEGIN{RS=""} {print; exit}')
# Trim leading/trailing blank lines
NOTES=$(printf "%s" "$NOTES" | awk 'NF{found=1} found' | tac | awk 'NF{found=1} found' | tac)

if [ -z "$NOTES" ]; then
    warn "Não encontrei seção '## v$VERSION' no CHANGELOG.md — release sem notas"
    NOTES="Release $TAG. Consulte CHANGELOG.md no repositório."
fi

NOTES_PREVIEW=$(printf "%s" "$NOTES" | head -8)
info "Notas extraídas ($(printf "%s" "$NOTES" | wc -l) linhas):"
printf "  ${YELLOW}┃${NC} %s\n" $(echo "$NOTES_PREVIEW" | head -5 | sed 's/^/| /' | head -c 400)
echo

# ============================================================
# BUILD DO PACOTE
# ============================================================
step "Buildando tarball"

# Não auto-bump aqui — já estamos no commit certo
mkdir -p "$DIST_DIR"
AUTO_BUMP_VERSION=false bash "${DASHBOARD_DIR}/tools/build-update.sh"

# build-update.sh deixa o arquivo mais recente em dist/
TARBALL=$(ls -t "$DIST_DIR"/unbound-dashboard-update-v${VERSION}-*.tar.gz 2>/dev/null | head -1)
SHA256_FILE="${TARBALL}.sha256"

[ -f "$TARBALL" ] || { error "Tarball não encontrado em $DIST_DIR"; exit 1; }
[ -f "$SHA256_FILE" ] || { error "Checksum $SHA256_FILE ausente"; exit 1; }

TARBALL_SIZE=$(du -h "$TARBALL" | cut -f1)
log "Tarball: $(basename "$TARBALL") ($TARBALL_SIZE)"
log "SHA256:  $(basename "$SHA256_FILE")"

# ============================================================
# CRIA A RELEASE
# ============================================================
step "Criando release no GitHub"

GH_ARGS=("release" "create" "$TAG"
    "$TARBALL"
    "$SHA256_FILE"
    --title "$TAG"
    --notes "$NOTES"
    --target main)

[ "$DRAFT" = "true" ] && GH_ARGS+=(--draft)
[ "$PRERELEASE" = "true" ] && GH_ARGS+=(--prerelease)

cd "$DASHBOARD_DIR"
RELEASE_URL=$(gh "${GH_ARGS[@]}")

log "Release criada"
echo
printf "${BOLD}🎉 Release publicada${NC}\n"
printf "   URL:     %s\n" "$RELEASE_URL"
printf "   Tag:     %s\n" "$TAG"
printf "   Tarball: %s (%s)\n" "$(basename "$TARBALL")" "$TARBALL_SIZE"
[ "$DRAFT" = "true" ] && printf "   ${YELLOW}Status:  DRAFT (não publicada)${NC}\n"
[ "$PRERELEASE" = "true" ] && printf "   ${YELLOW}Status:  PRE-RELEASE${NC}\n"
echo
