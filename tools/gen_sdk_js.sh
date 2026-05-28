#!/bin/bash
# Regenera o SDK TypeScript/JavaScript em clients/js/ a partir do OpenAPI atual.
#
# Pré-requisitos:
#   - unbound-dashboard-api rodando em 127.0.0.1:8001
#   - npm/npx (apt install npm OU node oficial)
#
# Uso:
#   sudo bash tools/gen_sdk_js.sh
#
# Variáveis:
#   API_BASE=http://127.0.0.1:8001

set -euo pipefail

DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_BASE="${API_BASE:-http://127.0.0.1:8001}"
OUT_DIR="$DASHBOARD_DIR/clients/js"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

echo "[i] Baixando openapi.json de $API_BASE..."
if ! curl -sf "$API_BASE/api/v1/openapi.json" -o "$TMP_DIR/openapi.json"; then
    echo "[✗] Falha ao baixar openapi.json. api_service está rodando?"
    exit 1
fi

echo "[i] Ajustando title pra package name limpo..."
python3 -c "
import json
with open('$TMP_DIR/openapi.json') as f: spec = json.load(f)
spec['info']['title'] = 'Unbound Dashboard'
with open('$TMP_DIR/openapi_clean.json', 'w') as f: json.dump(spec, f)
"

echo "[i] Gerando SDK via openapi-typescript-codegen..."
npx --yes openapi-typescript-codegen \
    --input "$TMP_DIR/openapi_clean.json" \
    --output "$TMP_DIR/sdk" \
    --name UnboundDashboardClient \
    --client fetch

if [ ! -f "$TMP_DIR/sdk/index.ts" ]; then
    echo "[✗] Output esperado não foi gerado. Veja o output do generator."
    exit 1
fi

echo "[i] Preservando package.json + README custom..."
PRESERVE_FILES=()
for f in package.json README.md; do
    if [ -f "$OUT_DIR/$f" ]; then
        cp "$OUT_DIR/$f" "$TMP_DIR/sdk/$f"
    fi
done

echo "[i] Copiando pra $OUT_DIR..."
rm -rf "$OUT_DIR"
cp -a "$TMP_DIR/sdk" "$OUT_DIR"

echo "[✓] SDK JS/TS regenerado em $OUT_DIR"
echo "    Pra testar: cd $OUT_DIR && npm install && npm run build"
