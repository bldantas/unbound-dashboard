#!/bin/bash
# Idempotente: adiciona regras AppArmor pro Unbound ler certs em
# /etc/unbound/certs/* (necessário pra DoT/DoH com cert gerenciado).
#
# Sem essas regras, o profile do pacote unbound (Debian/Ubuntu) bloqueia
# leitura da .key mesmo com perms corretos — gera fatal "could not set
# up listen SSL_CTX" e Unbound entra em loop de restart.
#
# Chamado automaticamente pelo TlsCertManager nos flows de gerar/upload/
# importar cert. Pode ser rodado standalone também.
#
# Exit codes:
#   0 = OK (regras presentes ou aplicadas com sucesso, OU AppArmor não
#       está em uso nesse host)
#   1 = erro irrecuperável

set -euo pipefail

LOCAL_PROFILE="/etc/apparmor.d/local/usr.sbin.unbound"
MAIN_PROFILE="/etc/apparmor.d/usr.sbin.unbound"
MARKER="# Unbound Dashboard — certificados gerenciados (DoT/DoH)"

# AppArmor não instalado/ativo aqui? Nada a fazer.
if [[ ! -f "$MAIN_PROFILE" ]]; then
    echo "apparmor: profile principal não existe, skip"
    exit 0
fi

# Garante que o arquivo local exista (geralmente já existe nos pacotes)
if [[ ! -f "$LOCAL_PROFILE" ]]; then
    touch "$LOCAL_PROFILE"
fi

# Já foi aplicado antes? Pula.
if grep -q "$MARKER" "$LOCAL_PROFILE" 2>/dev/null; then
    echo "apparmor: regras já presentes em $LOCAL_PROFILE"
    exit 0
fi

# Anexa as regras
cat >> "$LOCAL_PROFILE" <<'EOF'

# Unbound Dashboard — certificados gerenciados (DoT/DoH)
/etc/unbound/certs/ r,
/etc/unbound/certs/** r,
owner /etc/unbound/certs/*.key r,
capability dac_read_search,
capability dac_override,
EOF

# Recarrega o profile
if command -v apparmor_parser &>/dev/null; then
    apparmor_parser -r "$MAIN_PROFILE"
    echo "apparmor: regras adicionadas e profile recarregado"
else
    echo "apparmor: regras adicionadas (apparmor_parser ausente — reinicialização necessária)"
fi

exit 0
