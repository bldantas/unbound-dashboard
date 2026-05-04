#!/bin/bash
# Cutover seguro do unbound-logger.service (PHP log_ingester.php).
#
# Uso (qualquer das opções):
#   sudo /var/www/html/unbound-dashboard/api_service/tools/cutover_log_ingester.sh
#       (vai pedir MARIADB_PASS interativamente)
#
#   MARIADB_PASS=unbounddash sudo -E ./cutover_log_ingester.sh
#       (-E preserva env do shell pai)
#
# Pré-requisitos (verificados pelo script):
#   - api_service rodando e healthy
#   - log_watcher.py em paridade dual-write (drift <5%)
#
# Em caso de problema pós-cutover, rollback:
#   sudo systemctl enable --now unbound-logger.service
#
# Depois do cutover, sessões PHP antigas (sem api_jwt) verão dados estagnados
# em history.php até re-login. Considere comunicar pra usuários re-logarem.

set -euo pipefail

API_HEALTH_URL="http://127.0.0.1:8001/api/v1/healthz"
SERVICE="unbound-logger.service"
PARITY_THRESHOLD=0.05

cd "$(dirname "$0")/.."

echo "=== 1. api_service healthy? ==="
if ! curl -sf "$API_HEALTH_URL" >/dev/null; then
    echo "✗ FastAPI api_service não está respondendo em $API_HEALTH_URL — abort." >&2
    exit 2
fi
echo "✓ api_service OK"

echo ""
echo "=== 2. unbound-logger.service ativo? ==="
if ! systemctl is-active --quiet "$SERVICE"; then
    echo "⚠ $SERVICE já não está ativo — cutover pode já ter sido feito."
    exit 0
fi
echo "✓ $SERVICE ativo"

echo ""
echo "=== 3. paridade dual-write ==="
# `sudo` strips env vars por default. Aceita via env (sudo -E) ou prompt.
if [ -z "${MARIADB_PASS:-}" ]; then
    echo "MARIADB_PASS não disponível neste ambiente."
    echo "Forneça agora (input não ecoa):"
    read -rs MARIADB_PASS
    echo ""
    if [ -z "$MARIADB_PASS" ]; then
        echo "✗ Senha vazia — abort." >&2
        exit 2
    fi
    export MARIADB_PASS
fi
DRIFT_THRESHOLD="$PARITY_THRESHOLD" sudo -u www-data -E bash -c "
    set -a
    source /etc/unbound-dashboard/api-v1.env
    set +a
    cd /var/www/html/unbound-dashboard/api_service
    .venv/bin/python -m tools.check_dual_write_parity
" || {
    echo "✗ Drift fora do threshold — abort." >&2
    exit 1
}

echo ""
echo "=== 4. backup snapshot do MariaDB.query_logs (último timestamp) ==="
LAST_TS=$(mysql -h 127.0.0.1 -u unbounddb -p"$MARIADB_PASS" unbound_dash \
    -Nse "SELECT MAX(timestamp) FROM query_logs" 2>/dev/null || echo "?")
echo "MariaDB.query_logs último timestamp antes cutover: $LAST_TS"

echo ""
echo "=== 5. parando $SERVICE ==="
read -rp "Confirma cutover? digite SIM em maiúscula: " confirmation
if [ "$confirmation" != "SIM" ]; then
    echo "Cancelado pelo usuário."
    exit 0
fi

sudo systemctl stop "$SERVICE"
sudo systemctl disable "$SERVICE"
echo "✓ $SERVICE parado e desabilitado"

echo ""
echo "=== 6. validação 60s pós-cutover ==="
sleep 60
echo "Estado pós-cutover:"
sudo -u www-data /var/www/html/unbound-dashboard/api_service/.venv/bin/python -c "
import duckdb
conn = duckdb.connect('/var/lib/unbound-dashboard/unbound_dash.duckdb')
last = conn.execute('SELECT MAX(timestamp) FROM query_logs').fetchone()[0]
last_60s = conn.execute('SELECT COUNT(*) FROM query_logs WHERE timestamp > epoch(now()) - 60').fetchone()[0]
print(f'  DuckDB MAX(timestamp): {last}')
print(f'  DuckDB rows últimos 60s: {last_60s}')
"

echo ""
echo "✓ Cutover concluído. Para rollback:"
echo "   sudo systemctl enable --now $SERVICE"
