# unbound-dashboard-client (Python)

Cliente Python pra API do **Unbound Dashboard** (FastAPI/DuckDB). Auto-gerado a partir do OpenAPI 3.1 — não edite manualmente.

## Instalação

```bash
# Clone o repo OU baixe o tarball da release
git clone https://github.com/bldantas/unbound-dashboard.git
cd unbound-dashboard/clients/python
pip install -e .
```

Requer **Python 3.10+** e `httpx`.

## Quick start

```python
from unbound_dashboard_client import AuthenticatedClient
from unbound_dashboard_client.api.analytics import get_summary_api_v1_analytics_summary_get
from unbound_dashboard_client.api.blocklist import list_exceptions_api_v1_blocklist_exceptions_get

# Crie o token em Configurações → API Tokens (recomendado: escopado)
TOKEN = "..."

client = AuthenticatedClient(
    base_url="https://seu-dashboard.exemplo.com",
    token=TOKEN,
    prefix="",                  # sem prefix "Bearer "
    auth_header_name="X-Api-Token",
)

# Snapshot de métricas
summary = get_summary_api_v1_analytics_summary_get.sync(client=client, window="24h")
print(f"Total queries 24h: {summary.totals.total_queries}")

# Allowlist atual
exceptions = list_exceptions_api_v1_blocklist_exceptions_get.sync(client=client)
print(f"Allowlist tem {exceptions.count} domínios")
```

## Autenticação JWT

Pra login interativo:

```python
from unbound_dashboard_client import Client
from unbound_dashboard_client.api.auth import login_api_v1_auth_login_post
from unbound_dashboard_client.models import LoginRequest

client = Client(base_url="https://seu-dashboard.exemplo.com")
resp = login_api_v1_auth_login_post.sync(client=client, body=LoginRequest(
    username="admin",
    password="...",
))
jwt = resp.access_token

# Reusar o cliente com o JWT
auth_client = AuthenticatedClient(
    base_url="https://seu-dashboard.exemplo.com",
    token=jwt,
    prefix="Bearer",
)
```

## Async

Cada função tem versão `sync` e `asyncio`:

```python
import asyncio
from unbound_dashboard_client.api.analytics import get_summary_api_v1_analytics_summary_get

async def main():
    result = await get_summary_api_v1_analytics_summary_get.asyncio(client=client)
    print(result.totals.total_queries)

asyncio.run(main())
```

## Estrutura

```
unbound_dashboard_client/
├── api/             # Módulos por tag (alerts, analytics, blocklist, etc)
├── models/          # Pydantic-like models pra request/response
├── client.py        # Client + AuthenticatedClient
└── types.py         # Tipos auxiliares (Response, UNSET, etc)
```

## Re-gerar

Após mudanças no schema da API:

```bash
sudo bash tools/gen_sdk_python.sh
```

## Limitações

- `Response` errors: 4xx/5xx **não** levantam exception por padrão. Sempre cheque `result.parsed` is None ou use `_detailed` variants.
- Sem retry built-in — use `httpx` middleware ou wrappers externos.
- Não publicado no PyPI; sempre instalado do repo (`pip install -e .`).
