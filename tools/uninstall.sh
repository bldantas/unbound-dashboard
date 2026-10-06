#!/bin/bash
# ============================================================
# Unbound Dashboard — Desinstalador
#
# Remove o painel (frontend PHP, api_service, DuckDB, configs, cron, sudoers,
# units) e resíduos de versões antigas (v1 em /opt, cópias em /var/www/html).
#
# PRESERVA o Unbound: pacote, /etc/unbound (config, includes, certs) e o
# serviço continuam como estão — o DNS dos clientes não para. O drop-in que
# redireciona o stderr do Unbound para /var/log/unbound/unbound.log é removido
# mas só vale no próximo restart do Unbound (não reiniciamos aqui).
#
# Também não mexe em pacotes (apache2, php-fpm, redis, uv) nem em
# /var/log/unbound (logs de queries).
#
# Uso:
#   sudo bash uninstall.sh                 # pergunta antes; arquiva dados em /root
#   sudo bash uninstall.sh --yes           # sem confirmação
#   sudo bash uninstall.sh --no-archive    # não arquiva DuckDB/env antes de apagar
#   sudo bash uninstall.sh --legacy-only   # só resíduos (v1, cópias no DocumentRoot,
#                                          #   crontab antigo do root); mantém o painel
#   ARCHIVE_DIR=/srv/arquivo sudo bash uninstall.sh
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
info() { echo -e "${CYAN}[i]${NC} $1"; }
step() { echo -e "\n${BOLD}── $1 ──${NC}"; }

[ "$EUID" -eq 0 ] || err "Execute como root: sudo bash uninstall.sh"

ASSUME_YES="false"
DO_ARCHIVE="true"
LEGACY_ONLY="false"
for arg in "$@"; do
    case "$arg" in
        --yes|-y)       ASSUME_YES="true" ;;
        --no-archive)   DO_ARCHIVE="false" ;;
        --legacy-only)  LEGACY_ONLY="true" ;;
        -h|--help)      sed -n '2,25p' "$0"; exit 0 ;;
        *)              err "Opção desconhecida: $arg (use --help)" ;;
    esac
done

INSTALL_DIR="/var/www/html/unbound-dashboard"
ETC_DIR="/etc/unbound-dashboard"
DUCKDB_DIR="/var/lib/unbound-dashboard"
BACKUP_DIR="/var/backups/unbound-dashboard"
LOG_DIR="/var/log/unbound-dashboard"
ARCHIVE_DIR="${ARCHIVE_DIR:-/root}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

LEGACY_DIR="/opt/unbound-dashboard"
LEGACY_UNITS=(unbound-api.service unbound-log-ingester.service)
LEGACY_USER="unbound-dash"

# Remove do crontab do root as entradas do dashboard: linhas com a tag
# UNBOUND-DASHBOARD e as linhas de cabeçalho que versões antigas do
# instalador duplicavam a cada instalação. Mesma lista do install.sh.
clean_root_crontab() {
    local cur new
    cur=$(crontab -l 2>/dev/null || true)
    [ -n "$cur" ] || return 0
    new=$(printf '%s\n' "$cur" | grep -v 'UNBOUND-DASHBOARD' | grep -vxF \
        -e '# Unbound Dashboard — Crontabs' \
        -e '# Instalar com: crontab -l | cat - system/cron/unbound-dashboard-crons | crontab -' \
        -e '#' \
        -e '# Os crons de agregação de estatísticas e monitoramento de alertas foram' \
        -e '# cutovered para os workers Python do api_service em 2026-04-29:' \
        -e '#   - stats_aggregator.py  (substitui aggregate_stats.php)' \
        -e '#   - alert_checker.py     (substitui cron_alerts.php)' \
        -e '#   - log_watcher.py       (substitui log_ingester.php)' \
        -e '# Eles rodam dentro do unbound-dashboard-api.service.' \
        -e '# Sincronização de blacklist principal (a cada hora)' \
        -e '# Spawned por api/service_control.php com JWT via env, mas mantido também aqui' \
        -e '# como fallback caso o admin queira agendar sincronização periódica automática.' \
        -e '# Sincronização de lista judicial ANATEL (diário às 04:30)' \
        -e '# Agregação de estatísticas (a cada minuto)' \
        -e '# Monitoramento de alertas (a cada minuto)' \
        | cat -s || true)
    if [ -z "$(printf '%s' "$new" | tr -d '[:space:]')" ]; then
        crontab -r 2>/dev/null || true
    else
        printf '%s\n' "$new" | crontab -
    fi
}

remove_unit() {
    local unit="$1"
    if systemctl list-unit-files "$unit" --no-legend 2>/dev/null | grep -q "^$unit"; then
        systemctl disable --now "$unit" >/dev/null 2>&1 || true
        rm -f "/etc/systemd/system/$unit" "/lib/systemd/system/$unit"
        log "Serviço removido: $unit"
    fi
}

# ------------------------------------------------------------
# Plano
# ------------------------------------------------------------
LEGACY_WEB_BACKUPS=$(find "$(dirname "$INSTALL_DIR")" -maxdepth 1 -type d \
    -name "$(basename "$INSTALL_DIR").backup.*" 2>/dev/null | sort || true)

echo ""
echo -e "${BOLD}Unbound Dashboard — Desinstalação${NC}"
echo ""
echo "Será removido:"
if [ "$LEGACY_ONLY" != "true" ]; then
    echo "  - serviço unbound-dashboard-api e a unit systemd"
    echo "  - $INSTALL_DIR"
    echo "  - $DUCKDB_DIR (DuckDB e pacotes de update baixados)"
    echo "  - $ETC_DIR (api-v1.env: JWT_SECRET, SECRETS_MASTER_KEY)"
    echo "  - $BACKUP_DIR, $LOG_DIR"
    echo "  - conf Apache unbound-dashboard-api, sudoers, /etc/cron.d/unbound-dashboard,"
    echo "    /etc/logrotate.d/unbound-dashboard, drop-in unbound.service.d/logfile.conf,"
    echo "    /usr/local/bin/{unbound-health-fix.sh,setup-unbound-logs.sh}"
fi
echo "  - entradas do dashboard no crontab do root"
[ -n "$LEGACY_WEB_BACKUPS" ] && printf '  - cópia antiga no DocumentRoot: %s\n' $LEGACY_WEB_BACKUPS
[ -d "$LEGACY_DIR" ] && echo "  - versão antiga (v1): $LEGACY_DIR, usuário $LEGACY_USER, ${LEGACY_UNITS[*]}"
echo ""
echo "Preservado: Unbound (pacote, serviço, /etc/unbound), /var/log/unbound, apache2, php-fpm, redis, uv."
if [ "$LEGACY_ONLY" != "true" ] && [ "$DO_ARCHIVE" = "true" ]; then
    echo "Arquivo dos dados: $ARCHIVE_DIR/unbound-dashboard-archive-$TIMESTAMP.tar.gz"
fi
echo ""

if [ "$ASSUME_YES" != "true" ]; then
    [ -t 0 ] || err "Sem terminal para confirmar — use --yes"
    read -rp "Digite 'remover' para continuar: " CONFIRM
    [ "$CONFIRM" = "remover" ] || err "Cancelado."
fi

# ------------------------------------------------------------
# 1. Arquivo dos dados (antes de apagar qualquer coisa)
# ------------------------------------------------------------
if [ "$LEGACY_ONLY" != "true" ] && [ "$DO_ARCHIVE" = "true" ]; then
    step "Arquivando dados"
    ARCHIVE_SRC=()
    for p in "$DUCKDB_DIR" "$ETC_DIR" "$INSTALL_DIR/data" "$INSTALL_DIR/src/data"; do
        [ -e "$p" ] && ARCHIVE_SRC+=("$p")
    done
    if [ ${#ARCHIVE_SRC[@]} -gt 0 ]; then
        NEED=$(du -sbc --exclude="$DUCKDB_DIR/updates" "${ARCHIVE_SRC[@]}" | tail -1 | cut -f1)
        AVAIL=$(df -B1 --output=avail "$ARCHIVE_DIR" | tail -1 | tr -d ' ')
        if [ "$AVAIL" -lt "$NEED" ]; then
            err "Espaço insuficiente em $ARCHIVE_DIR para o arquivo: livre $((AVAIL / 1048576))MB, dados $((NEED / 1048576))MB.
    Libere espaço, use ARCHIVE_DIR=<outro disco> ou --no-archive. Nada foi removido."
        fi
        # Só depois da checagem de espaço: se abortar, o painel segue no ar.
        # Parar a API dá um snapshot consistente do DuckDB (CHECKPOINT no shutdown).
        systemctl stop unbound-dashboard-api 2>/dev/null || true
        ARCHIVE="$ARCHIVE_DIR/unbound-dashboard-archive-$TIMESTAMP.tar.gz"
        # Caminhos relativos a / (sem o aviso "Removing leading /" e sem esconder erros)
        tar czf "$ARCHIVE" -C / --exclude="${DUCKDB_DIR#/}/updates" "${ARCHIVE_SRC[@]#/}"
        chmod 600 "$ARCHIVE"
        log "Dados arquivados em $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
    fi
fi

# ------------------------------------------------------------
# 2. Resíduos (sempre)
# ------------------------------------------------------------
step "Resíduos de instalações anteriores"

clean_root_crontab
log "Crontab do root limpo"

for d in $LEGACY_WEB_BACKUPS; do
    rm -rf --one-file-system -- "$d"
    log "Removido: $d"
done

if [ -d "$LEGACY_DIR" ] || id "$LEGACY_USER" >/dev/null 2>&1; then
    for unit in "${LEGACY_UNITS[@]}"; do
        remove_unit "$unit"
    done
    [ -d "$LEGACY_DIR" ] && rm -rf --one-file-system -- "$LEGACY_DIR" && log "Removido: $LEGACY_DIR"
    rm -f "$ETC_DIR/env"
    if id "$LEGACY_USER" >/dev/null 2>&1; then
        userdel "$LEGACY_USER" 2>/dev/null || warn "userdel $LEGACY_USER falhou"
        getent group "$LEGACY_USER" >/dev/null && groupdel "$LEGACY_USER" 2>/dev/null || true
        log "Usuário $LEGACY_USER removido"
    fi
fi

if [ "$LEGACY_ONLY" = "true" ]; then
    systemctl daemon-reload
    log "Resíduos removidos (painel mantido)"
    exit 0
fi

# ------------------------------------------------------------
# 3. Painel
# ------------------------------------------------------------
step "Removendo o painel"

remove_unit unbound-dashboard-api.service

if [ -f /etc/apache2/conf-available/unbound-dashboard-api.conf ]; then
    a2disconf unbound-dashboard-api >/dev/null 2>&1 || true
    rm -f /etc/apache2/conf-available/unbound-dashboard-api.conf
    log "Conf Apache removida"
fi

rm -f /etc/sudoers.d/unbound-dashboard \
      /etc/cron.d/unbound-dashboard \
      /etc/logrotate.d/unbound-dashboard \
      /usr/local/bin/unbound-health-fix.sh \
      /usr/local/bin/setup-unbound-logs.sh
log "Sudoers, cron, logrotate e scripts removidos"

if [ -f /etc/systemd/system/unbound.service.d/logfile.conf ]; then
    rm -f /etc/systemd/system/unbound.service.d/logfile.conf
    rmdir /etc/systemd/system/unbound.service.d 2>/dev/null || true
    log "Drop-in do Unbound removido (vale no próximo restart do Unbound)"
fi

for d in "$INSTALL_DIR" "$DUCKDB_DIR" "$ETC_DIR" "$BACKUP_DIR" "$LOG_DIR"; do
    if [ -e "$d" ]; then
        rm -rf --one-file-system -- "$d"
        log "Removido: $d"
    fi
done

systemctl daemon-reload
if systemctl is-active --quiet apache2; then
    systemctl reload apache2 || warn "reload do Apache falhou — rode apache2ctl configtest"
fi

echo ""
log "Desinstalação concluída. Unbound segue ativo: $(systemctl is-active unbound 2>/dev/null || echo '?')"
if [ -n "${ARCHIVE:-}" ]; then
    info "Arquivo dos dados: $ARCHIVE"
fi
info "Site Apache próprio (sites-enabled) que aponte para $INSTALL_DIR não foi alterado."
