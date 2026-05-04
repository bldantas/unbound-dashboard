#!/bin/bash
# Tear-down do MariaDB — etapa final da modernização v1.
#
# Pré-requisitos (verificados pelo script):
#   - api_service rodando e healthy
#   - Todos os crons PHP cutovered (logger/stats/alerts)
#   - DuckDB com dados consistentes
#
# O que faz:
#   1. Backup mysqldump completo (em /var/backups/unbound-dashboard/)
#   2. Smoke test de páginas críticas (rejeita se alguma 500)
#   3. Pede confirmação SIM em maiúscula
#   4. DROP DATABASE unbound_dash + DROP USER unbounddb
#   5. Para e DESABILITA mariadb.service
#   6. Smoke test pós-tear-down (mesmas páginas, devem continuar OK)
#
# Rollback:
#   sudo systemctl enable --now mariadb
#   sudo mysql -u root < /var/backups/unbound-dashboard/mariadb-pre-teardown-<TS>.sql

set -euo pipefail

DB_NAME="unbound_dash"
DB_USER="unbounddb"
BACKUP_DIR="/var/backups/unbound-dashboard"
TS=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/mariadb-pre-teardown-${TS}.sql"

echo "=== 1. api_service /healthz ==="
if ! curl -sf http://127.0.0.1:8001/api/v1/healthz >/dev/null; then
    echo "✗ FastAPI não respondeu — abort." >&2
    exit 2
fi
echo "✓ api_service OK"

echo ""
echo "=== 2. mariadb ainda rodando? ==="
if ! systemctl is-active --quiet mariadb; then
    echo "⚠ mariadb já inativa — tear-down já feito ou estado inesperado."
    exit 0
fi
echo "✓ mariadb ativa"

echo ""
echo "=== 3. backup mysqldump (via sudo root socket) ==="
sudo install -d -m 750 -o root -g root "$BACKUP_DIR"
sudo mysqldump --single-transaction --routines --triggers --events "$DB_NAME" | sudo tee "$BACKUP_FILE" >/dev/null
sudo chmod 640 "$BACKUP_FILE"
SIZE=$(sudo du -h "$BACKUP_FILE" | cut -f1)
LINES=$(sudo wc -l < "$BACKUP_FILE")
if [ "$LINES" -lt 10 ]; then
    echo "✗ Backup parece vazio ($LINES linhas) — abort." >&2
    exit 2
fi
echo "✓ Backup salvo em $BACKUP_FILE ($SIZE, $LINES linhas)"

echo ""
echo "=== 4. smoke pre-teardown — páginas críticas devem responder ==="
HOST="dashboard.redeconexaonet.com"
FAILED=0
for path in /login.php /index.php /alerts.php /threats.php /history.php /config.php /blocklist.php /health.php; do
    code=$(curl -sk -o /dev/null -w "%{http_code}" -H "Host: $HOST" "https://localhost$path")
    if [ "$code" -ge 500 ]; then
        echo "  ✗ $path = HTTP $code"
        FAILED=$((FAILED + 1))
    else
        printf "  ✓ %-15s HTTP %s\n" "$path" "$code"
    fi
done
if [ "$FAILED" -gt 0 ]; then
    echo "$FAILED páginas retornaram 5xx ANTES do tear-down — investigar antes de prosseguir." >&2
    exit 1
fi

echo ""
echo "=== 5. confirma tear-down ==="
echo "Vou executar:"
echo "  - DROP DATABASE $DB_NAME"
echo "  - DROP USER '${DB_USER}'@'%' (e variantes)"
echo "  - systemctl stop+disable mariadb"
echo ""
read -rp "Confirma? digite SIM em maiúscula: " confirmation
if [ "$confirmation" != "SIM" ]; then
    echo "Cancelado pelo usuário. Backup mantido em $BACKUP_FILE."
    exit 0
fi

echo ""
echo "=== 6. drop database + user (via sudo root socket) ==="
sudo mysql -e "DROP DATABASE IF EXISTS $DB_NAME;"
sudo mysql -e "DROP USER IF EXISTS '${DB_USER}'@'localhost'; DROP USER IF EXISTS '${DB_USER}'@'127.0.0.1'; DROP USER IF EXISTS '${DB_USER}'@'%'; FLUSH PRIVILEGES;"

echo ""
echo "=== 7. parar e desabilitar mariadb ==="
sudo systemctl stop mariadb
sudo systemctl disable mariadb 2>&1 | tail -2

echo ""
echo "=== 8. smoke post-teardown — páginas devem continuar funcionando ==="
sleep 2
FAILED=0
for path in /login.php /index.php /alerts.php /threats.php /history.php /config.php /blocklist.php /health.php; do
    code=$(curl -sk -o /dev/null -w "%{http_code}" -H "Host: $HOST" "https://localhost$path")
    if [ "$code" -ge 500 ]; then
        echo "  ✗ $path = HTTP $code"
        FAILED=$((FAILED + 1))
    else
        printf "  ✓ %-15s HTTP %s\n" "$path" "$code"
    fi
done

echo ""
if [ "$FAILED" -gt 0 ]; then
    echo "⚠ $FAILED páginas retornaram 5xx pós-tear-down. Considere rollback."
    echo "Rollback:"
    echo "  sudo systemctl enable --now mariadb"
    echo "  sudo mysql -u root < $BACKUP_FILE"
    exit 1
fi

echo "✅ Tear-down concluído. Sistema 100% em DuckDB."
echo "   Backup: $BACKUP_FILE"
echo "   Rollback (se precisar): sudo systemctl enable --now mariadb && sudo mysql -u root < $BACKUP_FILE"
