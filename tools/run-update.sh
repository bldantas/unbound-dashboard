#!/bin/bash
# ============================================================
# Unbound Dashboard — Run Update Wrapper
#
# Wrapper invocado por services/updater.py (via sudoers). Existe pra
# resolver UM problema específico: o fd do log compartilhado com uvicorn.
#
# Sem este wrapper, updater.py passava `stdout=log_fd` direto pro Popen.
# Quando `update.sh` chamava `systemctl restart unbound-dashboard-api`,
# o uvicorn morria e seu fd duplicado do log era fechado. O fd do
# subprocess também sumia em algum ponto (provavelmente cgroup cleanup),
# e o log truncava em "Apache conf atualizado" — toda a parte de restart/
# smoke/sucesso ficava sem registro na UI SSE.
#
# Com o wrapper, é o SHELL DO FILHO que abre o log (`>> LOG 2>&1`). Esse
# fd nasce dentro do session group novo (criado por setsid no Popen do
# Python via `start_new_session=True`), totalmente independente do
# uvicorn — sobrevive ao restart.
#
# Uso (via sudoers):
#   sudo bash tools/run-update.sh <job_id> <tarball_path>
# ============================================================

set -euo pipefail

JOB_ID="${1:-}"
TARBALL="${2:-}"

if [ -z "$JOB_ID" ] || [ -z "$TARBALL" ]; then
    echo "Uso: $0 <job_id> <tarball>" >&2
    exit 2
fi

# Validação defense-in-depth (updater.py já valida, mas redundância barata)
if ! [[ "$JOB_ID" =~ ^[a-f0-9]{12}$ ]]; then
    echo "job_id inválido: $JOB_ID" >&2
    exit 2
fi

LOG="/var/log/unbound-dashboard/update-${JOB_ID}.log"

# Garante log dir antes — update.sh confia que existe
mkdir -p "$(dirname "$LOG")"

# Abre o log com `exec ... >> $LOG 2>&1`. A partir desta linha, TODO output
# do shell e dos comandos filhos vai pro arquivo, com fd próprio deste
# processo (não herdado do Python que spawnou). Sobrevive ao restart do
# api_service que update.sh dispara mais tarde.
exec >> "$LOG" 2>&1
exec /usr/bin/bash /var/www/html/unbound-dashboard/tools/update.sh "$TARBALL"
