#!/bin/bash
# ============================================================
# Unbound Dashboard — Run Update Wrapper
#
# Wrapper invocado por services/updater.py (via sudoers). Resolve UM
# problema fundamental: o processo do `update.sh` precisa rodar FORA
# do cgroup do `unbound-dashboard-api.service`. Caso contrário:
#
#   1. update.sh chama `systemctl daemon-reload` ao instalar nova unit
#   2. systemd reaplica restrições/namespaces ao cgroup atual
#   3. processos que estão usando os recursos antigos morrem
#   4. log SSE trunca em "Apache conf atualizado"
#
# Estratégia: usar `systemd-run --scope --slice=system.slice`. Diferente
# de `--unit` (que cria transient unit em /run/systemd/transient/
# e é REMOVIDA por daemon-reload), `--scope` cria um cgroup-scope
# herdando direto do slice especificado — totalmente OUT do
# cgroup do api_service.
#
# Redirect de stdout/stderr é feito pelo shell DESTE wrapper antes do
# exec — o fd nasce no shell do filho, sobrevive ao restart do api.
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

if ! [[ "$JOB_ID" =~ ^[a-f0-9]{12}$ ]]; then
    echo "job_id inválido: $JOB_ID" >&2
    exit 2
fi

LOG="/var/log/unbound-dashboard/update-${JOB_ID}.log"
mkdir -p "$(dirname "$LOG")"

# Redirect deste shell pro log — todos os logs subsequentes (deste script
# e do update.sh executado abaixo) caem no arquivo. fd nasce no novo
# scope criado por systemd-run, fora do cgroup do api_service.
exec >> "$LOG" 2>&1

# `--scope --slice=system.slice` move o processo pro cgroup
# `/system.slice/run-rXXX.scope`, herdando do system.slice — NÃO do
# cgroup do api_service. Daemon-reload do update.sh não afeta este scope.
# `--quiet` suprime mensagens "Running as unit:..." do próprio systemd-run.
exec /usr/bin/systemd-run \
    --scope \
    --slice=system.slice \
    --quiet \
    --description="Unbound Dashboard self-update job $JOB_ID" \
    /usr/bin/bash /var/www/html/unbound-dashboard/tools/update.sh "$TARBALL"
