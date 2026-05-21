#!/bin/bash
# certbot deploy hook — replica cert renovado pro path managed do Unbound.
#
# Instale em /etc/letsencrypt/renewal-hooks/deploy/ e marque executável:
#   sudo install -m 0755 system/letsencrypt/unbound-dashboard-deploy.sh \
#       /etc/letsencrypt/renewal-hooks/deploy/unbound-dashboard.sh
#
# Edite o domínio abaixo pro seu (default: dashboard.redeconexaonet.com).
# Cert Let's Encrypt dura 90 dias; certbot renova automaticamente via
# systemd timer; o hook roda após cada renovação, copia o cert novo
# pro Unbound e dá reload.
#
# Variáveis injetadas pelo certbot:
#   RENEWED_LINEAGE = /etc/letsencrypt/live/<dominio>
#   RENEWED_DOMAINS = <dominio> [<dominio2> ...]

set -euo pipefail

# Edite ESTE domínio pra bater com o do seu Apache/Dashboard:
DASHBOARD_DOMAIN="dashboard.redeconexaonet.com"

case "${RENEWED_LINEAGE:-}" in
    */"${DASHBOARD_DOMAIN}")
        install -o unbound -g unbound -m 0644 \
            "${RENEWED_LINEAGE}/fullchain.pem" \
            /etc/unbound/certs/dashboard.crt
        install -o unbound -g unbound -m 0640 \
            "${RENEWED_LINEAGE}/privkey.pem" \
            /etc/unbound/certs/dashboard.key
        systemctl reload unbound 2>/dev/null || systemctl restart unbound || true
        logger -t unbound-dashboard "Cert ${DASHBOARD_DOMAIN} renovado e instalado em /etc/unbound/certs/"
        ;;
esac
