#!/bin/bash
# certbot deploy hook — replica cert renovado pro path managed do Unbound.
#
# Instalado automaticamente pelo botão "Importar do Let's Encrypt" no
# dashboard (Configurações → Criptografia DoT/DoH), que também escreve
# o marker /etc/unbound/certs/.le-lineage com o nome do lineage importado.
#
# Pra instalar manualmente:
#   sudo install -m 0755 system/letsencrypt/unbound-dashboard-deploy.sh \
#       /etc/letsencrypt/renewal-hooks/deploy/unbound-dashboard.sh
#   echo "dashboard.SEUDOMINIO.com" | sudo tee /etc/unbound/certs/.le-lineage
#
# Variáveis injetadas pelo certbot:
#   RENEWED_LINEAGE = /etc/letsencrypt/live/<dominio>
#   RENEWED_DOMAINS = <dominio> [<dominio2> ...]

set -euo pipefail

MARKER_FILE="/etc/unbound/certs/.le-lineage"

if [[ ! -r "$MARKER_FILE" ]]; then
    # Nada importado via dashboard — nada a fazer.
    exit 0
fi

EXPECTED_LINEAGE=$(tr -d '[:space:]' < "$MARKER_FILE")
if [[ -z "$EXPECTED_LINEAGE" ]]; then
    exit 0
fi

# Só age se o lineage renovado bate com o que o dashboard marcou
case "${RENEWED_LINEAGE:-}" in
    */"${EXPECTED_LINEAGE}")
        install -o unbound -g unbound -m 0644 \
            "${RENEWED_LINEAGE}/fullchain.pem" \
            /etc/unbound/certs/dashboard.crt
        install -o unbound -g unbound -m 0640 \
            "${RENEWED_LINEAGE}/privkey.pem" \
            /etc/unbound/certs/dashboard.key
        systemctl reload unbound 2>/dev/null || systemctl restart unbound || true
        logger -t unbound-dashboard "Cert ${EXPECTED_LINEAGE} renovado e instalado em /etc/unbound/certs/"
        ;;
esac
