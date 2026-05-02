#!/usr/bin/env bash
set -euo pipefail

SERVICE_NAME="${SERVICE_NAME:-unbound-api}"
DB_PATH="${DB_PATH:-/var/lib/unbound-dashboard/unbound_dash.duckdb}"
WORKSPACE_ROOT="${WORKSPACE_ROOT:-/var/www/html/unbound-dashboard}"
INSTALL_ROOT="${INSTALL_ROOT:-/opt/unbound-dashboard}"
VENV_PY="${VENV_PY:-${INSTALL_ROOT}/.venv/bin/python}"

echo "=== Diagnostico Alertas/Ameacas ==="
date
echo

echo "[1] Servico ${SERVICE_NAME}"
if systemctl is-active --quiet "${SERVICE_NAME}"; then
  echo "status=active"
else
  echo "status=inactive"
fi
systemctl status "${SERVICE_NAME}" --no-pager -l | sed -n '1,20p'
echo

echo "[2] Processo uvicorn"
ps -ef | grep -E "uvicorn app.main:app|${SERVICE_NAME}" | grep -v grep || true
echo

echo "[3] Versao ativa do worker alert_checker"
ACTIVE_WORKER="${INSTALL_ROOT}/app/workers/alert_checker.py"
WS_WORKER="${WORKSPACE_ROOT}/app/workers/alert_checker.py"

if [[ -f "${ACTIVE_WORKER}" ]]; then
  echo "active_worker=${ACTIVE_WORKER}"
  if grep -q "NO_QUERIES_COOLDOWN_HOURS" "${ACTIVE_WORKER}"; then
    echo "active_has_cooldown=yes"
  else
    echo "active_has_cooldown=no"
  fi
else
  echo "active_worker=missing"
fi

if [[ -f "${WS_WORKER}" && -f "${ACTIVE_WORKER}" ]]; then
  if cmp -s "${ACTIVE_WORKER}" "${WS_WORKER}"; then
    echo "active_vs_workspace=equal"
  else
    echo "active_vs_workspace=different"
  fi
fi
echo

echo "[4] Estado de dados no DuckDB"
if [[ ! -f "${VENV_PY}" ]]; then
  echo "python_venv_not_found=${VENV_PY}"
  exit 1
fi

"${VENV_PY}" - <<'PY'
import time
import duckdb

db_path = "/var/lib/unbound-dashboard/unbound_dash.duckdb"
con = duckdb.connect(db_path, read_only=True)
now = int(time.time())

q10m = con.execute("select count(*) from query_logs where timestamp >= ?", [now - 600]).fetchone()[0]
q24h = con.execute("select count(*) from query_logs where timestamp >= ?", [now - 86400]).fetchone()[0]
latest_q = con.execute("select max(timestamp) from query_logs").fetchone()[0]

noq_total = con.execute("select count(*) from alerts where type='no_queries'").fetchone()[0]
noq_unread = con.execute("select count(*) from alerts where type='no_queries' and is_read=false").fetchone()[0]

blocked_total = con.execute("select count(*) from query_logs where action='blocked'").fetchone()[0]
latest_block = con.execute("select max(timestamp) from query_logs where action='blocked'").fetchone()[0]

print(f"db_path={db_path}")
print(f"queries_10m={q10m}")
print(f"queries_24h={q24h}")
print(f"latest_query_ts={latest_q}")
if latest_q:
    print(f"seconds_since_last_query={now - int(latest_q)}")
print(f"no_queries_total={noq_total}")
print(f"no_queries_unread={noq_unread}")
print(f"blocked_total={blocked_total}")
print(f"latest_block_ts={latest_block}")
if latest_block:
    print(f"seconds_since_last_block={now - int(latest_block)}")

print("recent_no_queries=")
for row in con.execute(
    "select id, is_read, created_at from alerts where type='no_queries' order by id desc limit 5"
).fetchall():
    print(row)

con.close()
PY
echo

echo "[5] Rotas PHP de ameacas (ultimo acesso no Apache)"
if [[ -f /var/log/apache2/access.log ]]; then
  grep -E "threats_data\.php|threats\.php|alerts\.php" /var/log/apache2/access.log | tail -n 20 || true
else
  echo "access_log_not_found=/var/log/apache2/access.log"
fi

echo
echo "=== Fim do diagnostico ==="
