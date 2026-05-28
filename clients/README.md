# SDKs — Unbound Dashboard

Clientes auto-gerados a partir do OpenAPI 3.1 do `api_service` pra facilitar integrações externas.

## O que tem aqui

| Diretório | Linguagem | Gerador | Versão da API |
|---|---|---|---|
| [python/](python/) | Python 3.10+ | [openapi-python-client](https://github.com/openapi-generators/openapi-python-client) | 0.1.0 |
| [js/](js/) | TypeScript / JavaScript | [openapi-typescript-codegen](https://github.com/ferdikoomen/openapi-typescript-codegen) | 0.1.0 |

Ambos são **distribuídos com o repo** — não publicamos no PyPI/npm (por enquanto). Use clonando o repo ou baixando o tarball da release.

## Quando re-gerar

Sempre que o schema da API muda (novo endpoint, novo campo numa resposta, etc). Scripts em [`../tools/`](../tools/):

```bash
# Pré-requisitos: uv, npm
sudo bash tools/gen_sdk_python.sh   # regenera clients/python/
sudo bash tools/gen_sdk_js.sh        # regenera clients/js/
```

Os scripts:
1. Baixam `/api/v1/openapi.json` do `unbound-dashboard-api` local (port 8001)
2. Ajustam o `info.title` pra ficar limpo
3. Rodam o gerador (`uvx openapi-python-client` ou `npx openapi-typescript-codegen`)
4. Copiam o resultado pra `clients/{python,js}/` sobrescrevendo

## Autenticação

Ambos os SDKs aceitam:
- **`Authorization: Bearer <JWT>`** — login humano (`POST /api/v1/auth/login`)
- **`X-Api-Token: <raw>`** — token long-lived (criado em Configurações → API Tokens)

Tokens **escopados** (v2.110+) restringem o que o cliente pode chamar — recomendado pra integrações em produção. Ver [api_docs.php](../api_docs.php) seção "Tokens escopados".

## Exemplos rápidos

Veja os READMEs específicos:
- [python/README.md](python/README.md)
- [js/README.md](js/README.md)
