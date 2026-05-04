#!/bin/bash
# Smoke test do install.sh em container Debian 13.
#
# Builda a imagem (que executa install.sh durante o build) e roda o
# /api/v1/healthz via uvicorn manual. Não cobre systemd — o stub no
# Dockerfile.smoke aceita is-active/is-enabled como sucesso.
#
# Uso:
#   sudo bash tools/docker/smoke-test.sh
#
# Variáveis:
#   IMAGE_TAG         (default: unbound-dashboard-smoke:latest)
#   PACKAGE_TARBALL   (auto: tools/unbound-dashboard-v<VERSION>.tar.gz)

set -euo pipefail

DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE_TAG="${IMAGE_TAG:-unbound-dashboard-smoke:latest}"

if [ "${PACKAGE_TARBALL:-}" = "" ]; then
    VERSION=$(tr -d '[:space:]' < "$DASHBOARD_DIR/VERSION")
    PACKAGE_TARBALL="tools/unbound-dashboard-v${VERSION}.tar.gz"
fi

if [ ! -f "$DASHBOARD_DIR/$PACKAGE_TARBALL" ]; then
    echo "[✗] Pacote não encontrado: $DASHBOARD_DIR/$PACKAGE_TARBALL"
    echo "    Gere primeiro com: sudo bash tools/build-package.sh"
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "[✗] docker não instalado neste host."
    echo "    Você pode rodar o smoke num host com docker via:"
    echo "    sudo apt-get install docker.io  # ou docker-ce"
    exit 1
fi

cd "$DASHBOARD_DIR"

echo "[i] Build da imagem $IMAGE_TAG (pacote: $PACKAGE_TARBALL)..."
docker build \
    -f tools/docker/Dockerfile.smoke \
    --build-arg PACKAGE_TARBALL="$PACKAGE_TARBALL" \
    -t "$IMAGE_TAG" \
    . 2>&1 | tail -30

echo ""
echo "[i] Executando smoke runtime (uvicorn + /api/v1/healthz)..."
docker run --rm "$IMAGE_TAG"

echo ""
echo "[✓] Smoke test concluído com sucesso"
echo "    Para inspecionar dentro: sudo docker run --rm -it $IMAGE_TAG bash"
