#!/bin/bash
# ============================================================
# Unbound Dashboard — aplicação de update (executado como root via sudoers)
#
# Instalado em /usr/local/bin (root:root 755). Fica FORA da árvore web de
# propósito: www-data escreve em /var/www/html/unbound-dashboard, e um script
# root ali dentro daria root a quem controlasse www-data.
#
# Fluxo:
#   1. Re-executa a si mesmo num scope systemd próprio (fora do cgroup do
#      unbound-dashboard-api), senão o restart da API no fim do update mata
#      este processo.
#   2. Copia pacote + assinatura para um diretório de trabalho do root (o
#      diretório de downloads é do www-data).
#   3. Verifica a assinatura Ed25519 do pacote com a chave pública abaixo.
#      Sem assinatura válida, nada é executado.
#   4. Extrai e roda o update.sh DO PACOTE (não o instalado), apontando para
#      o diretório extraído.
#
# Log: /var/log/unbound-dashboard-update/update-<job_id>.log (dir root:www-data
# 750 — www-data lê para o SSE, mas não cria nem troca arquivos).
#
# Uso (via sudoers, chamado por services/updater.py):
#   sudo /usr/local/bin/unbound-dashboard-run-update.sh <job_id> <tarball>
#   (assinatura esperada em <tarball>.sig)
# ============================================================

set -euo pipefail

# Chave pública de assinatura das releases (par em ~/.config/unbound-dashboard/
# na máquina de release; ver tools/release.sh). Trocar a chave = nova versão
# deste script.
RELEASE_PUBKEY='-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAhAq6M/MaZaOJrRVhKp9U8T+G4DNStflIv7YCTSPNHHc=
-----END PUBLIC KEY-----'

UPDATES_DIR="/var/lib/unbound-dashboard/updates"
LOG_DIR="/var/log/unbound-dashboard-update"

JOB_ID="${1:-}"
TARBALL="${2:-}"

if [ "$#" -ne 2 ]; then
    echo "Uso: $0 <job_id> <tarball>" >&2
    exit 2
fi
if ! [[ "$JOB_ID" =~ ^[a-f0-9]{12}$ ]]; then
    echo "job_id inválido: $JOB_ID" >&2
    exit 2
fi
if ! [[ "$TARBALL" =~ ^${UPDATES_DIR}/unbound-dashboard-update-v[0-9][0-9A-Za-z._-]*\.tar\.gz$ ]] \
   || [[ "$TARBALL" == *..* ]]; then
    echo "tarball fora de $UPDATES_DIR ou com nome inválido: $TARBALL" >&2
    exit 2
fi

# 1. Scope próprio (uma vez)
if [ -z "${UDASH_IN_SCOPE:-}" ]; then
    exec /usr/bin/systemd-run --scope --slice=system.slice --quiet \
        --description="Unbound Dashboard self-update job $JOB_ID" \
        /usr/bin/env UDASH_IN_SCOPE=1 "$0" "$JOB_ID" "$TARBALL"
fi

# Ignora TERM/HUP/INT: quem chamou foi `sudo` dentro do cgroup da API, e
# quando o update/restore para a API o systemd manda SIGTERM ao sudo, que o
# repassa a este processo. Sem isso o script morria no meio (e o trap de
# saída apagava o pacote extraído enquanto o update.sh ainda rodava). A
# disposição "ignorado" é herdada pelos filhos.
trap '' TERM HUP INT

install -d -o root -g www-data -m 750 "$LOG_DIR"
LOG="$LOG_DIR/update-${JOB_ID}.log"
# Diretório do root: www-data não consegue plantar symlink aqui.
install -o root -g www-data -m 640 /dev/null "$LOG"
exec >> "$LOG" 2>&1

echo "[..] Job $JOB_ID — pacote $(basename "$TARBALL")"

# Fora de /tmp e /var/tmp: chamado pela API, este processo herda o
# PrivateTmp dela, e o systemd apaga esse /tmp privado quando o update.sh
# para a API — o pacote extraído sumia no meio do update.
# /var/backups/unbound-dashboard é do root (750) e está no ReadWritePaths.
install -d -o root -g root -m 750 /var/backups/unbound-dashboard
WORK=$(mktemp -d /var/backups/unbound-dashboard/.update-work.XXXXXX)
trap 'rm -rf -- "$WORK"' EXIT

# 2. Cópia para área do root (evita troca do arquivo entre verificar e usar)
cp -- "$TARBALL" "$WORK/pkg.tar.gz"
if ! cp -- "${TARBALL}.sig" "$WORK/pkg.tar.gz.sig" 2>/dev/null; then
    echo "[XX] Assinatura ausente: ${TARBALL}.sig"
    echo "ROLLBACK FAILED — update não aplicado (pacote sem assinatura)"
    exit 1
fi

# 3. Assinatura
printf '%s\n' "$RELEASE_PUBKEY" > "$WORK/release.pub"
if ! openssl pkeyutl -verify -pubin -inkey "$WORK/release.pub" -rawin \
        -in "$WORK/pkg.tar.gz" -sigfile "$WORK/pkg.tar.gz.sig" >/dev/null 2>&1; then
    echo "[XX] Assinatura do pacote INVÁLIDA — update recusado"
    echo "ROLLBACK FAILED — update não aplicado (assinatura inválida)"
    exit 1
fi
echo "[OK] Assinatura do pacote verificada"

# 4. Extrai e roda o update.sh do próprio pacote
mkdir "$WORK/pkg"
tar xzf "$WORK/pkg.tar.gz" -C "$WORK/pkg" --no-same-owner
PKG_DIR="$WORK/pkg"
inner=$(find "$PKG_DIR" -mindepth 1 -maxdepth 1)
if [ "$(printf '%s\n' "$inner" | wc -l)" = "1" ] && [ -d "$inner" ]; then
    PKG_DIR="$inner"
fi
if [ ! -f "$PKG_DIR/update.sh" ]; then
    echo "[XX] update.sh não encontrado no pacote"
    echo "ROLLBACK FAILED — update não aplicado (pacote malformado)"
    exit 1
fi

# Sem exec: o trap limpa o diretório de trabalho no fim.
/usr/bin/bash "$PKG_DIR/update.sh" "$PKG_DIR"
