#!/bin/bash
# Big Bang migration runner: v1 MariaDB -> v2 DuckDB
# Uso:
#   MYSQL_URL='mysql+pymysql://user:pass@127.0.0.1:3306/unbound_dash' \
#   sudo bash tools/run-bigbang-migration.sh

set -euo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/unbound-dashboard}"
DB_PATH="${DB_PATH:-/var/lib/unbound-dashboard/unbound_dash.duckdb}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/unbound-dashboard}"
SERVICE="${SERVICE:-unbound-api}"
CHUNK_SIZE="${CHUNK_SIZE:-50000}"

[ "${EUID}" -eq 0 ] || { echo "Execute como root"; exit 1; }
[ -n "${MYSQL_URL:-}" ] || { echo "MYSQL_URL nao definido"; exit 1; }
[ -f "$INSTALL_DIR/.venv/bin/python" ] || { echo "Python venv nao encontrado em $INSTALL_DIR/.venv"; exit 1; }

mkdir -p "$BACKUP_DIR"
TS="$(date +%Y%m%d_%H%M%S)"
DB_BKP="$BACKUP_DIR/unbound_dash_pre_bigbang_${TS}.duckdb"
BL_CSV="$BACKUP_DIR/v1-domain-blacklist-${TS}.csv"

if [ -f "$DB_PATH" ]; then
  cp "$DB_PATH" "$DB_BKP"
  echo "[OK] Backup DuckDB: $DB_BKP"
else
  echo "[WARN] DuckDB ainda nao existe em $DB_PATH"
fi

echo "[i] Parando servico $SERVICE"
systemctl stop "$SERVICE" || true

echo "[i] Executando migracao Big Bang"
cd "$INSTALL_DIR"
CHUNK_SIZE="$CHUNK_SIZE" MYSQL_URL="$MYSQL_URL" \
  "$INSTALL_DIR/.venv/bin/python" tools/migrate_from_mariadb.py \
  --truncate \
  --chunk-size "$CHUNK_SIZE" \
  --export-blocklist "$BL_CSV"

echo "[i] Subindo servico $SERVICE"
systemctl start "$SERVICE"
systemctl --no-pager --full status "$SERVICE" || true

echo "[i] Validando contagens e health"
MYSQL_URL="$MYSQL_URL" DB_PATH="$DB_PATH" bash tools/validate-bigbang-migration.sh

echo "[OK] Migracao finalizada"
echo "[i] CSV blocklist legado: $BL_CSV"
echo "[i] Para reaplicar blocklist estatico na v2 use o CSV exportado"
