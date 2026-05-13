#!/bin/bash
# ============================================================
# Unbound Dashboard — Run Update Wrapper
#
# Dispara `update.sh` numa unit transient do systemd, escapando do
# namespace mount restrito do api_service (ProtectSystem=strict +
# ReadWritePaths limitado). Sem isso, update.sh não consegue escrever
# em /var/backups, /etc, /usr/local/bin, etc.
#
# Uso (via sudoers, chamado pelo services/updater.py):
#   sudo bash tools/run-update.sh <job_id> <tarball_path>
#
# - job_id: 12 chars hex (validado pelo updater.py + regex aqui)
# - tarball_path: /var/lib/unbound-dashboard/updates/...tar.gz
#
# A transient unit é nomeada `unbound-dashboard-update-<job_id>.service`
# e usa --collect (auto-remove ao terminar). Stdout+stderr são
# redirecionados pelo SHELL interno (não via --property=StandardOutput),
# porque update.sh chama `systemctl daemon-reload` (ao instalar nova
# unit) — isso fecha o fd que systemd estava mantendo via
# StandardOutput=append. Redirecionando no bash do filho, esse problema
# desaparece.
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
UNIT="unbound-dashboard-update-${JOB_ID}.service"

# `--collect` libera a unit do systemd quando exit (não fica enfileirada).
# Redirect é feito por bash dentro do unit transient — não via systemd
# StandardOutput, que quebra após daemon-reload (ver doc acima).
exec /usr/bin/systemd-run \
    --unit="$UNIT" \
    --collect \
    --description="Unbound Dashboard self-update job $JOB_ID" \
    /usr/bin/bash -c "exec /usr/bin/bash /var/www/html/unbound-dashboard/tools/update.sh \"\$0\" >> \"$LOG\" 2>&1" \
    "$TARBALL"
