#!/bin/bash
# Validacao pos-migracao Big Bang
# Requer MYSQL_URL e DB_PATH

set -euo pipefail

[ -n "${MYSQL_URL:-}" ] || { echo "MYSQL_URL nao definido"; exit 1; }
DB_PATH="${DB_PATH:-/var/lib/unbound-dashboard/unbound_dash.duckdb}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:8000/healthz}"
VENV_PY="${VENV_PY:-/opt/unbound-dashboard/.venv/bin/python}"

eval "$(python3 - <<'PY'
import os
from urllib.parse import urlparse

u = urlparse(os.environ['MYSQL_URL'])
host = u.hostname or '127.0.0.1'
port = u.port or 3306
user = u.username or 'root'
password = u.password or ''
database = (u.path or '/').lstrip('/')
print(f"MYSQL_HOST='{host}'")
print(f"MYSQL_PORT='{port}'")
print(f"MYSQL_USER='{user}'")
print(f"MYSQL_PASS='{password}'")
print(f"MYSQL_DB='{database}'")
PY
)"

for table in users query_logs daily_stats alerts settings; do
    exists="$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASS" -Nse "SHOW TABLES LIKE '$table'" "$MYSQL_DB" 2>/dev/null || true)"
    if [[ "$exists" != "$table" ]]; then
        continue
    fi
    n="$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASS" -Nse "SELECT COUNT(*) FROM $table" "$MYSQL_DB")"
    echo "MYSQL $table $n"
done

"$VENV_PY" - <<'PY'
import os
import duckdb

db = os.environ.get('DB_PATH', '/var/lib/unbound-dashboard/unbound_dash.duckdb')
con = duckdb.connect(db)
for table in ('users', 'query_logs', 'daily_stats', 'alerts', 'settings'):
    n = con.execute(f'SELECT COUNT(*) FROM {table}').fetchone()[0]
    print(f'DUCKDB {table} {n}')
con.close()
PY

code="$(curl -sS -o /dev/null -w '%{http_code}' "$HEALTH_URL" || true)"
if [[ "$code" != "200" ]]; then
  echo "[WARN] Health endpoint retornou HTTP $code em $HEALTH_URL"
else
  echo "[OK] Health endpoint: HTTP 200"
fi
