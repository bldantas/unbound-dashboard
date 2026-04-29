#!/bin/bash
# ============================================================
# Unbound Dashboard v2 — Instalador Automatizado
# Debian 12/13 · Ubuntu 22.04+
#
# Uso: sudo bash install.sh
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

require_cmd() {
    local cmd="$1"
    command -v "$cmd" >/dev/null 2>&1 || err "Dependência ausente: $cmd"
}

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash install.sh"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SRC="$SCRIPT_DIR/app"
SYSTEM_SRC="$SCRIPT_DIR/system"

[ -d "$APP_SRC" ] || err "Diretório app/ não encontrado. Extraia o pacote completo antes de instalar."

VERSION=$(cat "$APP_SRC/VERSION" 2>/dev/null || echo "2.0.0")
INSTALL_DIR="/opt/unbound-dashboard"
DATA_DIR="/var/lib/unbound-dashboard"
LOG_DIR="/var/log/unbound-dashboard"
ENV_DIR="/etc/unbound-dashboard"
SERVICE_USER="unbound-dash"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Unbound Dashboard v2 — Instalador v${VERSION}          ║${NC}"
echo -e "${BOLD}║   Debian 12/13 · Ubuntu 22.04+                       ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================
# ETAPA 1 — Sistema Operacional
# ============================================================
step "Etapa 1 — Sistema Operacional"

[ -f /etc/os-release ] || err "/etc/os-release não encontrado."
# shellcheck source=/dev/null
source /etc/os-release

case "$ID" in
    debian)
        [[ "${VERSION_ID:-0}" -ge 12 ]] || err "Debian $VERSION_ID não suportado. Necessário Debian 12+."
        log "Debian $VERSION_ID detectado"
        ;;
    ubuntu)
        MAJOR=$(echo "${VERSION_ID:-0}" | cut -d. -f1)
        [[ "$MAJOR" -ge 22 ]] || err "Ubuntu $VERSION_ID não suportado. Necessário Ubuntu 22.04+."
        log "Ubuntu $VERSION_ID detectado"
        ;;
    *)
        err "SO '$ID' não suportado. Use Debian 12+ ou Ubuntu 22.04+."
        ;;
esac

# ============================================================
# ETAPA 2 — Dependências do sistema
# ============================================================
step "Etapa 2 — Dependências do sistema"

info "Atualizando repositórios..."
apt-get update -qq

PACKAGES=(
    python3
    python3-venv
    python3-pip
    python3-dev
    curl
    wget
    git
    rsync
    openssl
    ca-certificates
    gnupg
    apt-transport-https
    unbound
    redis-server
    sudo
    libssl-dev
    build-essential
)
info "Instalando pacotes base..."
apt-get install -y -qq "${PACKAGES[@]}"
log "Pacotes base instalados"

# Python 3.12+ é requisito do projeto
PYTHON_BIN=""
for py in python3.13 python3.12 python3; do
    if command -v "$py" &>/dev/null; then
        if "$py" - <<'PYEOF' >/dev/null 2>&1
import sys
raise SystemExit(0 if sys.version_info >= (3, 12) else 1)
PYEOF
        then
            PYTHON_BIN="$py"
            break
        fi
    fi
done

[ -n "$PYTHON_BIN" ] || err "Python >= 3.12 não encontrado. Instale Python 3.12+ e execute novamente."
log "Python: $($PYTHON_BIN --version)"

# Node.js 20 LTS
if ! command -v node &>/dev/null || [[ "$(node --version | cut -d. -f1 | tr -d 'v')" -lt 20 ]]; then
    info "Instalando Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null
    apt-get install -y -qq nodejs
fi
log "Node.js: $(node --version)"

# uv (gerenciador de pacotes Python)
if ! command -v uv &>/dev/null; then
    info "Instalando uv..."
    curl -LsSf https://astral.sh/uv/install.sh | sh
fi

UV_BIN="$(command -v uv || true)"
if [ -z "$UV_BIN" ] && [ -x "/root/.local/bin/uv" ]; then
    UV_BIN="/root/.local/bin/uv"
fi
[ -n "$UV_BIN" ] || err "uv não foi encontrado após instalação."
log "uv: $($UV_BIN --version)"

# Caddy
if ! command -v caddy &>/dev/null; then
    info "Instalando Caddy..."
    apt-get install -y -qq debian-keyring debian-archive-keyring apt-transport-https curl
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -qq
    apt-get install -y -qq caddy
fi
log "Caddy: $(caddy version 2>/dev/null | head -1 || echo 'instalado')"

for cmd in "$PYTHON_BIN" "$UV_BIN" unbound redis-server caddy node npm rsync; do
    require_cmd "$cmd"
done
log "Dependências de runtime validadas"

# ============================================================
# ETAPA 3 — Usuário e diretórios
# ============================================================
step "Etapa 3 — Usuário e diretórios"

if ! id "$SERVICE_USER" &>/dev/null; then
    useradd --system --no-create-home --shell /usr/sbin/nologin "$SERVICE_USER"
    log "Usuário $SERVICE_USER criado"
else
    log "Usuário $SERVICE_USER já existe"
fi

# Adicionar ao grupo unbound para acessar unbound-control
if getent group unbound &>/dev/null; then
    usermod -aG unbound "$SERVICE_USER" || true
fi

for dir in "$INSTALL_DIR" "$DATA_DIR" "$LOG_DIR" "$ENV_DIR"; do
    mkdir -p "$dir"
done

chown -R "$SERVICE_USER:$SERVICE_USER" "$DATA_DIR" "$LOG_DIR"
chmod 750 "$ENV_DIR"
log "Diretórios criados"

# ============================================================
# ETAPA 4 — Instalar código-fonte
# ============================================================
step "Etapa 4 — Instalar código-fonte"

info "Copiando aplicação para $INSTALL_DIR..."
rsync -a --delete \
    --exclude='.venv' \
    --exclude='__pycache__' \
    --exclude='*.pyc' \
    "$APP_SRC/" "$INSTALL_DIR/"

chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR"
# Pasta .venv deve poder ser escrita pelo instalador (root) — ajusta depois
log "Código instalado em $INSTALL_DIR"

# ============================================================
# ETAPA 5 — Virtualenv e dependências Python
# ============================================================
step "Etapa 5 — Dependências Python"

info "Criando virtualenv com uv..."
cd "$INSTALL_DIR"
"$UV_BIN" venv .venv --python "$PYTHON_BIN" --quiet

mapfile -t PY_DEPS < <("$PYTHON_BIN" - <<'PYEOF'
import tomllib
from pathlib import Path

data = tomllib.loads(Path("pyproject.toml").read_text(encoding="utf-8"))
for dep in data.get("project", {}).get("dependencies", []):
    print(dep)
PYEOF
)

[ "${#PY_DEPS[@]}" -gt 0 ] || err "Nenhuma dependência encontrada em pyproject.toml"
"$UV_BIN" pip install --quiet --no-cache --python .venv/bin/python "${PY_DEPS[@]}"

# Valida import essencial do banco usado pelo sistema (DuckDB)
.venv/bin/python - <<'PYEOF'
import duckdb
print(duckdb.__version__)
PYEOF

# Ajusta permissões do venv
chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR/.venv"
log "Dependências Python instaladas"

# ============================================================
# ETAPA 6 — Arquivo de ambiente
# ============================================================
step "Etapa 6 — Configuração de ambiente"

if [ ! -f "$ENV_DIR/env" ]; then
    cp "$SYSTEM_SRC/env/unbound-dashboard.env" "$ENV_DIR/env"
    chmod 640 "$ENV_DIR/env"
    chown root:"$SERVICE_USER" "$ENV_DIR/env"

    # Gera JWT_SECRET automaticamente
    JWT_SECRET=$(openssl rand -hex 32)
    sed -i "s/JWT_SECRET=CHANGE_ME_BEFORE_STARTING/JWT_SECRET=${JWT_SECRET}/" "$ENV_DIR/env"
    log "Arquivo de ambiente criado em $ENV_DIR/env (JWT_SECRET gerado)"
else
    warn "Arquivo $ENV_DIR/env já existe — não sobrescrito"
fi

# ============================================================
# ETAPA 7 — Migrations DuckDB
# ============================================================
step "Etapa 7 — Migrations do banco de dados"

info "Executando migrations DuckDB..."
DB_PATH="$DATA_DIR/unbound_dash.duckdb"
export DB_PATH
export REDIS_URL="redis://127.0.0.1:6379/0"
export JWT_SECRET=$(grep JWT_SECRET "$ENV_DIR/env" | cut -d= -f2)

cd "$INSTALL_DIR"
sudo -u "$SERVICE_USER" .venv/bin/python -m app.db.migrate || {
    # Fallback: rodar como root para a primeira execução
    .venv/bin/python -m app.db.migrate
    chown "$SERVICE_USER:$SERVICE_USER" "$DB_PATH" 2>/dev/null || true
}
log "Migrations executadas"

if grep -q '^MYSQL_URL=' "$ENV_DIR/env" 2>/dev/null; then
    warn "MYSQL_URL detectado em $ENV_DIR/env, mas o v2 usa exclusivamente DuckDB (DB_PATH)."
fi

# ============================================================
# ETAPA 8 — Serviço systemd
# ============================================================
step "Etapa 8 — Serviço systemd"

cp "$SYSTEM_SRC/systemd/unbound-api.service" /etc/systemd/system/
systemctl daemon-reload
systemctl enable unbound-api
systemctl enable redis-server
systemctl start redis-server
systemctl start unbound-api
log "Serviço unbound-api instalado e iniciado"

# ============================================================
# ETAPA 9 — Caddy
# ============================================================
step "Etapa 9 — Caddy (reverse proxy)"

if [ ! -f /etc/caddy/Caddyfile ] || grep -q 'dashboard.example.com' /etc/caddy/Caddyfile 2>/dev/null; then
    cp "$SYSTEM_SRC/caddy/Caddyfile" /etc/caddy/Caddyfile
    warn "Caddyfile instalado em /etc/caddy/Caddyfile"
    warn "ATENÇÃO: Edite /etc/caddy/Caddyfile e substitua 'dashboard.example.com' pelo seu domínio."
    warn "Depois execute: sudo systemctl reload caddy"
else
    warn "Caddyfile já existe em /etc/caddy/Caddyfile — não sobrescrito"
fi

systemctl enable caddy
log "Caddy configurado"

# ============================================================
# ETAPA 10 — Criar usuário admin inicial
# ============================================================
step "Etapa 10 — Usuário administrador"

echo ""
info "Crie o primeiro usuário administrador:"
echo -n "  Username: "
read -r ADMIN_USER
echo -n "  Senha: "
read -rs ADMIN_PASS
echo ""

if [ -n "$ADMIN_USER" ] && [ -n "$ADMIN_PASS" ]; then
    cd "$INSTALL_DIR"
    export DB_PATH JWT_SECRET
    .venv/bin/python - <<PYEOF
import asyncio, sys
sys.path.insert(0, '.')
from app.services.auth_service import AuthService
from app.domain.user import Role

async def create():
    svc = AuthService()
    await svc.create_user('$ADMIN_USER', '$ADMIN_PASS', Role.ADMIN)
    print('Usuário admin criado com sucesso.')

asyncio.run(create())
PYEOF
    log "Usuário '$ADMIN_USER' criado"
else
    warn "Nenhum usuário criado. Use a API para criar: POST /api/v2/auth/register"
fi

# ============================================================
# RESUMO
# ============================================================
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         Instalação concluída com sucesso! ✓          ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
log "Versão instalada: $VERSION"
log "API:     http://127.0.0.1:8000  (acesso interno)"
log "Docs:    http://127.0.0.1:8000/docs"
log "Status:  systemctl status unbound-api"
log "Logs:    journalctl -u unbound-api -f"
echo ""
warn "Próximos passos:"
echo "  1. Edite /etc/caddy/Caddyfile com seu domínio"
echo "  2. Execute: sudo systemctl reload caddy"
echo "  3. Verifique: sudo systemctl status unbound-api caddy redis-server"
echo ""
