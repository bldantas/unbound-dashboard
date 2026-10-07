#!/bin/bash
# unbound-dashboard-priv.sh — operações privilegiadas do dashboard com
# argumentos validados.
#
# O sudoers dá a www-data este script (sem restrição de argumentos) no lugar
# de regras com curinga como `cp /…/unbound_* *` ou `ifup *`. Curinga no
# sudoers casa com espaços e opções, então aquelas regras permitiam copiar
# qualquer arquivo para qualquer lugar (ou passar `-i`/`-t` extras). Aqui
# cada subcomando aceita só origens e destinos de uma allowlist fixa.
#
# Subcomandos:
#   install-file SRC DEST       copia SRC (arquivo regular no tmp do
#                               dashboard) para DEST (allowlist abaixo),
#                               fixando dono e modo do destino
#   ifup IFACE [--force]        ifupdown, sem outras opções
#   ifdown IFACE [--force]
#   netplan-backup              salva o YAML gerenciado em BACKUP_DIR e
#                               imprime o caminho
#   netplan-restore NAME        restaura BACKUP_DIR/NAME para o YAML
#   le-install LINEAGE          instala fullchain/privkey de uma lineage do
#                               Let's Encrypt nos paths gerenciados e o
#                               deploy hook do certbot
#
# Instalado em /usr/local/bin pelo update.sh (root:root 0755).

set -euo pipefail
umask 022
export PATH=/usr/sbin:/usr/bin:/sbin:/bin

DASH_TMP="/var/www/html/unbound-dashboard/src/data/tmp"
NETPLAN_FILE="/etc/netplan/99-unbound-dashboard.yaml"
# Fora de /var/backups/unbound-dashboard de propósito: aqui o diretório é
# root:www-data 0750, então www-data lista os backups mas não planta nada.
NETPLAN_BACKUP_DIR="/var/backups/unbound-dashboard-netplan"
LE_LIVE="/etc/letsencrypt/live"
CERT_DIR="/etc/unbound/certs"
LE_HOOK_SRC="/usr/local/bin/unbound-dashboard-le-deploy-hook.sh"
LE_HOOK_DST="/etc/letsencrypt/renewal-hooks/deploy/unbound-dashboard.sh"

die() {
    echo "unbound-dashboard-priv: $*" >&2
    exit 2
}

# Destinos permitidos → "dono:grupo:modo"
dest_policy() {
    local dest="$1"
    case "$dest" in
        /etc/unbound/unbound.conf) echo "unbound:unbound:0644" ;;
        /etc/network/interfaces | /etc/hosts | /etc/resolv.conf \
            | /etc/systemd/timesyncd.conf | /etc/systemd/resolved.conf \
            | /etc/chrony/chrony.conf | /etc/chrony.conf | /etc/ntp.conf)
            echo "root:root:0644"
            ;;
        "$NETPLAN_FILE") echo "root:root:0600" ;;
        *)
            if [[ "$dest" =~ ^/etc/unbound/unbound[0-9]{1,2}\.conf$ ]] \
                || [[ "$dest" =~ ^/etc/unbound/includes/[A-Za-z0-9_-]+\.conf$ ]]; then
                echo "unbound:unbound:0644"
            else
                return 1
            fi
            ;;
    esac
}

WORK=""
cleanup() {
    if [ -n "$WORK" ]; then rm -rf -- "$WORK"; fi
}
trap cleanup EXIT

# Copia SRC para um diretório privado do root e garante que o resultado é
# arquivo regular. `cp -P` não segue symlink: se SRC for (ou virar, numa
# corrida) um symlink, a cópia também é symlink e é recusada — nada fora do
# SRC é lido como root.
stage() {
    local src="$1"
    WORK="$(mktemp -d)"
    cp -P --no-preserve=all -- "$src" "$WORK/f" 2>/dev/null || die "não foi possível ler $src"
    if [ -L "$WORK/f" ] || [ ! -f "$WORK/f" ]; then
        die "origem não é arquivo regular: $src"
    fi
    STAGED="$WORK/f"
}

install_policy() {
    local staged="$1" dest="$2" policy owner group mode
    policy="$(dest_policy "$dest")" || die "destino não permitido: $dest"
    IFS=: read -r owner group mode <<<"$policy"
    getent passwd "$owner" >/dev/null || owner=root
    getent group "$group" >/dev/null || group=root
    install -o "$owner" -g "$group" -m "$mode" -- "$staged" "$dest"
}

cmd_install_file() {
    [ $# -eq 2 ] || die "uso: install-file SRC DEST"
    local src="$1" dest="$2" dir base
    dir="$(dirname -- "$src")"
    base="$(basename -- "$src")"
    [[ "$base" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]*$ ]] || die "nome de origem inválido: $base"
    [ "$(realpath -e -- "$dir" 2>/dev/null)" = "$(realpath -e -- "$DASH_TMP")" ] \
        || die "origem fora de $DASH_TMP: $src"
    dest_policy "$dest" >/dev/null || die "destino não permitido: $dest"
    stage "$src"
    install_policy "$STAGED" "$dest"
}

valid_iface() {
    [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9._:@-]{0,31}$ ]]
}

cmd_ifupdown() {
    local bin="$1"
    shift
    [ $# -ge 1 ] && [ $# -le 2 ] || die "uso: $bin IFACE [--force]"
    valid_iface "$1" || die "interface inválida: $1"
    if [ $# -eq 2 ]; then
        [ "$2" = "--force" ] || die "opção não permitida: $2"
        exec "/usr/sbin/$bin" --force -- "$1"
    fi
    exec "/usr/sbin/$bin" -- "$1"
}

cmd_netplan_backup() {
    [ $# -eq 0 ] || die "uso: netplan-backup"
    [ -f "$NETPLAN_FILE" ] || die "$NETPLAN_FILE não existe"
    install -d -o root -g www-data -m 0750 "$NETPLAN_BACKUP_DIR"
    local dest
    dest="$NETPLAN_BACKUP_DIR/netplan-99-$(date +%Y%m%d-%H%M%S).yaml"
    install -o root -g www-data -m 0640 -- "$NETPLAN_FILE" "$dest"
    echo "$dest"
}

cmd_netplan_restore() {
    [ $# -eq 1 ] || die "uso: netplan-restore NAME"
    local name="$1"
    [[ "$name" =~ ^netplan-99-[0-9]{8}-[0-9]{6}\.yaml$ ]] || die "nome de backup inválido: $name"
    stage "$NETPLAN_BACKUP_DIR/$name"
    install_policy "$STAGED" "$NETPLAN_FILE"
}

cmd_le_install() {
    [ $# -eq 1 ] || die "uso: le-install LINEAGE"
    local lineage="$1" src real
    [[ "$lineage" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*\.[A-Za-z]{2,}$ ]] || die "lineage inválida: $lineage"
    for src in "$LE_LIVE/$lineage/fullchain.pem" "$LE_LIVE/$lineage/privkey.pem"; do
        real="$(realpath -e -- "$src" 2>/dev/null)" || die "não encontrado: $src"
        [[ "$real" == /etc/letsencrypt/* ]] || die "fora de /etc/letsencrypt: $src"
    done
    mkdir -p "$CERT_DIR"
    install -o unbound -g unbound -m 0644 -- "$LE_LIVE/$lineage/fullchain.pem" "$CERT_DIR/dashboard.crt"
    install -o unbound -g unbound -m 0640 -- "$LE_LIVE/$lineage/privkey.pem" "$CERT_DIR/dashboard.key"
    printf '%s\n' "$lineage" >"$CERT_DIR/.le-lineage.tmp"
    chown unbound:unbound "$CERT_DIR/.le-lineage.tmp"
    chmod 0644 "$CERT_DIR/.le-lineage.tmp"
    mv -f -- "$CERT_DIR/.le-lineage.tmp" "$CERT_DIR/.le-lineage"
    if [ -f "$LE_HOOK_SRC" ]; then
        mkdir -p "$(dirname -- "$LE_HOOK_DST")"
        install -o root -g root -m 0755 -- "$LE_HOOK_SRC" "$LE_HOOK_DST"
    else
        echo "aviso: $LE_HOOK_SRC ausente — deploy hook não instalado" >&2
    fi
}

[ "$(id -u)" -eq 0 ] || die "precisa rodar como root (via sudo)"
[ $# -ge 1 ] || die "uso: $0 {install-file|ifup|ifdown|netplan-backup|netplan-restore|le-install} ..."

sub="$1"
shift
case "$sub" in
    install-file) cmd_install_file "$@" ;;
    ifup | ifdown) cmd_ifupdown "$sub" "$@" ;;
    netplan-backup) cmd_netplan_backup "$@" ;;
    netplan-restore) cmd_netplan_restore "$@" ;;
    le-install) cmd_le_install "$@" ;;
    *) die "subcomando desconhecido: $sub" ;;
esac
