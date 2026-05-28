#!/bin/bash
# Regenera o SDK Python em clients/python/ a partir do OpenAPI atual.
#
# Pré-requisitos:
#   - unbound-dashboard-api rodando em 127.0.0.1:8001
#   - uv instalado (curl -fsSL https://astral.sh/uv/install.sh | sh)
#
# Uso:
#   sudo bash tools/gen_sdk_python.sh
#
# Variáveis:
#   API_BASE=http://127.0.0.1:8001  (URL do api_service local)

set -euo pipefail

DASHBOARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_BASE="${API_BASE:-http://127.0.0.1:8001}"
OUT_DIR="$DASHBOARD_DIR/clients/python"
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

echo "[i] Gerando SDK via openapi-python-client..."
cd "$TMP_DIR"
uvx --from openapi-python-client openapi-python-client generate \
    --path "$TMP_DIR/openapi_clean.json" \
    --output-path "$TMP_DIR/sdk" \
    --overwrite

if [ ! -d "$TMP_DIR/sdk/unbound_dashboard_client" ]; then
    echo "[✗] Package esperado não foi gerado. Veja o output do generator."
    exit 1
fi

echo "[i] Copiando pra $OUT_DIR..."
# Preserva READMEs custom do projeto (não os gerados)
README_BACKUP=""
if [ -f "$OUT_DIR/README.md" ]; then
    README_BACKUP="$(mktemp)"
    cp "$OUT_DIR/README.md" "$README_BACKUP"
fi

rm -rf "$OUT_DIR"
cp -a "$TMP_DIR/sdk" "$OUT_DIR"

# Restaura README custom (gerado é genérico)
if [ -n "$README_BACKUP" ]; then
    cp "$README_BACKUP" "$OUT_DIR/README.md"
    rm "$README_BACKUP"
fi

echo "[✓] SDK Python regenerado em $OUT_DIR"
echo "    Pra testar: cd $OUT_DIR && pip install -e ."
