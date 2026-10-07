#!/bin/bash
# ============================================================
# Unbound Dashboard — Restore Backup
#
# Restaura um backup específico criado por update.sh em /var/backups/
# unbound-dashboard/. Wrapper standalone — não depende do contexto de
# update em andamento.
#
# Instalado em /usr/local/bin (root:root 755), fora da árvore web que
# www-data escreve. Usado pela UI (aba Sistema / Atualizações → Histórico de
# Backups) via api_service spawnando:
#   sudo /usr/local/bin/unbound-dashboard-restore-backup.sh <job_id> <ts>
#
# Args:
#   $1 = job_id (12 hex chars, valida pela API + regex aqui)
#   $2 = timestamp do backup (ex: 20260514_092609)
#
# Faz:
#   1. Para api_service
#   2. tar xzf /var/backups/unbound-dashboard/dashboard-<ts>.tar.gz -C
#      (path correto pra restaurar em /var/www/html/...)
#   3. Restaura DuckDB se duckdb-<ts>.duckdb existir
#   4. Restaura env file se api-v1.env-<ts> existir
#   5. Restart api_service + health check
#
# Exit codes:
#   0 = restore OK
#   1 = erro (backup não encontrado, falha de tar, etc)
#   2 = restore aplicado mas health check falhou
#
# Re-executa a si mesmo num scope systemd próprio: sem isso o
# `systemctl stop unbound-dashboard-api` abaixo mata este processo, que nasce
# no cgroup da API. Stdout/stderr vão para
# /var/log/unbound-dashboard-update/update-<job_id>.log (dir do root; www-data
# só lê, para o SSE).
# ============================================================

set -euo pipefail

JOB_ID="${1:-}"
TIMESTAMP="${2:-}"

BACKUP_DIR="/var/backups/unbound-dashboard"
DASHBOARD_DIR="/var/www/html/unbound-dashboard"
DUCKDB_PATH="/var/lib/unbound-dashboard/unbound_dash.duckdb"
ENV_FILE="/etc/unbound-dashboard/api-v1.env"

if [ "$#" -ne 2 ] || [ -z "$JOB_ID" ] || [ -z "$TIMESTAMP" ]; then
    echo "Uso: $0 <job_id> <timestamp>" >&2
    exit 1
fi

if ! [[ "$JOB_ID" =~ ^[a-f0-9]{12}$ ]]; then
    echo "job_id inválido: $JOB_ID" >&2
    exit 1
fi

# Timestamp seguro: 8 dígitos + _ + 6 dígitos (formato do create_backup)
if ! [[ "$TIMESTAMP" =~ ^[0-9]{8}_[0-9]{6}$ ]]; then
    echo "timestamp inválido: $TIMESTAMP (esperado: YYYYMMDD_HHMMSS)" >&2
    exit 1
fi

CODE_BACKUP="$BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz"
DB_BACKUP="$BACKUP_DIR/duckdb-$TIMESTAMP.duckdb"
ENV_BACKUP="$BACKUP_DIR/api-v1.env-$TIMESTAMP"
if [ -z "${UDASH_IN_SCOPE:-}" ]; then
    exec /usr/bin/systemd-run --scope --slice=system.slice --quiet \
        --description="Unbound Dashboard restore job $JOB_ID" \
        /usr/bin/env UDASH_IN_SCOPE=1 "$0" "$JOB_ID" "$TIMESTAMP"
fi

LOG_DIR="/var/log/unbound-dashboard-update"
install -d -o root -g www-data -m 750 "$LOG_DIR"
LOG="$LOG_DIR/update-${JOB_ID}.log"
install -o root -g www-data -m 640 /dev/null "$LOG"
exec >> "$LOG" 2>&1

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()   { printf "${GREEN}[OK]${NC} %s\n" "$1"; }
info()  { printf "[..] %s\n" "$1"; }
warn()  { printf "${YELLOW}[!!]${NC} %s\n" "$1"; }
error() { printf "${RED}[XX]${NC} %s\n" "$1" >&2; }

# ============================================================
# Validações pré-flight
# ============================================================
echo "╔════════════════════════════════════════════════════╗"
echo "║   Unbound Dashboard — Restore Backup               ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
info "Timestamp: $TIMESTAMP"
info "Job ID:    $JOB_ID"
echo ""

info "Validando backup..."
if [ ! -f "$CODE_BACKUP" ]; then
    error "Backup de código não encontrado: $CODE_BACKUP"
    echo "ROLLBACK FAILED"
    exit 1
fi
log "Backup encontrado: $CODE_BACKUP ($(du -h "$CODE_BACKUP" | cut -f1))"

[ -f "$DB_BACKUP" ] && log "DuckDB backup: $DB_BACKUP ($(du -h "$DB_BACKUP" | cut -f1))"
[ -f "$ENV_BACKUP" ] && log "Env backup:    $ENV_BACKUP"

# Snapshot pré-restore pra rollback de emergência (se restore quebrar)
SAFETY_TS=$(date +%Y%m%d_%H%M%S)
SAFETY_TAR="$BACKUP_DIR/pre-restore-$SAFETY_TS.tar.gz"
info "Snapshot do estado atual antes de restaurar ($SAFETY_TAR)..."
tar czf "$SAFETY_TAR" \
    --exclude='api_service/.venv' \
    --exclude='api_service/__pycache__' \
    --exclude='**/__pycache__' \
    --exclude='*.pyc' \
    -C "$(dirname "$DASHBOARD_DIR")" \
    "$(basename "$DASHBOARD_DIR")" 2>/dev/null || warn "Snapshot falhou — restore segue mas sem rollback fácil"
log "Snapshot criado: $SAFETY_TAR"

# ============================================================
# Stop api_service
# ============================================================
info "Parando api_service..."
systemctl stop unbound-dashboard-api 2>/dev/null || true
log "api_service parado"

# ============================================================
# Restore código
# ============================================================
# O tar foi criado com -C "$(dirname $DASHBOARD_DIR)" (/var/www/html),
# então o path interno é "unbound-dashboard/..." e precisamos extrair
# em /var/www/html/.
TARGET_DIR="$(dirname "$DASHBOARD_DIR")"
info "Restaurando código em $TARGET_DIR..."
if tar xzf "$CODE_BACKUP" -C "$TARGET_DIR"; then
    log "Código restaurado"
else
    error "Falha ao restaurar código"
    echo "ROLLBACK FAILED"
    exit 1
fi

# Re-sync .venv pelo uv (o tar exclui .venv do backup, então após
# restore o .venv pode ficar ausente/desatualizado)
info "Sincronizando .venv via uv sync..."
# Cache/Pythons do uv fora de /root (ProtectHome no sandbox da API).
export UV_CACHE_DIR=/var/cache/unbound-dashboard/uv
export UV_PYTHON_INSTALL_DIR=/usr/local/lib/unbound-dashboard/python
install -d -o root -g root -m 755 "$UV_CACHE_DIR" "$UV_PYTHON_INSTALL_DIR" 2>/dev/null || true
uv_bin="$(command -v uv || echo /root/.local/bin/uv)"
[ -x "$uv_bin" ] || uv_bin="/usr/local/bin/uv"
if [ -x "$uv_bin" ]; then
    (cd "$DASHBOARD_DIR/api_service" && "$uv_bin" sync --no-dev --quiet) \
        || warn "uv sync falhou — venv pode estar inconsistente"
    chown -R www-data:www-data "$DASHBOARD_DIR/api_service/.venv" 2>/dev/null || true
    log ".venv sincronizado"
else
    warn "uv não disponível — venv não foi reconstruído"
fi

# ============================================================
# Restore DuckDB (opcional)
# ============================================================
if [ -f "$DB_BACKUP" ]; then
    info "Restaurando DuckDB..."
    # O .wal atual pertence ao banco que está sendo substituído: reproduzido
    # sobre o snapshot corrompe ou impede a abertura. Restaura o WAL do
    # snapshot (se houver) ou tira o atual do caminho.
    if [ -f "${DUCKDB_PATH}.wal" ]; then
        mv -f "${DUCKDB_PATH}.wal" "${DUCKDB_PATH}.wal.pre-restore-$SAFETY_TS" || true
    fi
    if [ -f "${DB_BACKUP}.wal" ]; then
        cp -a "${DB_BACKUP}.wal" "${DUCKDB_PATH}.wal" || warn "Falha ao restaurar o WAL do backup"
    fi
    if cp -a "$DB_BACKUP" "$DUCKDB_PATH"; then
        log "DuckDB restaurado"
    else
        warn "Falha ao restaurar DuckDB — continuando mesmo assim"
    fi
fi

# ============================================================
# Restore env file (opcional)
# ============================================================
if [ -f "$ENV_BACKUP" ]; then
    info "Restaurando $ENV_FILE..."
    cp -a "$ENV_BACKUP" "$ENV_FILE" || warn "Falha ao restaurar env"
    log "Env file restaurado"
fi

# ============================================================
# Start + Health check
# ============================================================
info "Reiniciando api_service..."
systemctl daemon-reload
systemctl start unbound-dashboard-api

sleep 5
health_ok=0
max_wait=30
waited=5
info "Health check (timeout ${max_wait}s)..."
while [ $waited -lt $max_wait ]; do
    if systemctl is-active --quiet unbound-dashboard-api \
       && curl -sf --max-time 3 http://127.0.0.1:8001/api/v1/healthz >/dev/null 2>&1; then
        health_ok=1
        log "api_service saudável após ${waited}s"
        break
    fi
    sleep 3
    waited=$((waited + 3))
done

VERSION_FINAL="?"
[ -f "$DASHBOARD_DIR/VERSION" ] && VERSION_FINAL=$(tr -d '[:space:]' < "$DASHBOARD_DIR/VERSION")

if [ $health_ok -eq 1 ]; then
    echo ""
    echo "╔════════════════════════════════════════════════════╗"
    echo "║   Restore concluído                                ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    echo "Versão atual: $VERSION_FINAL"
    echo "Backup pré-restore: $SAFETY_TAR (caso queira reverter)"
    # Marker reconhecido pelo monitor (mesmo do update.sh)
    echo "Update concluído"
    exit 0
else
    error "api_service não voltou após restore — health check falhou"
    journalctl -u unbound-dashboard-api -n 20 --no-pager || true
    echo "ROLLBACK FAILED — estado inconsistente; SSH manual necessário"
    exit 2
fi
