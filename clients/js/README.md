# unbound-dashboard-client (TypeScript / JavaScript)

Cliente JS/TS pra API do **Unbound Dashboard**. Auto-gerado a partir do OpenAPI 3.1 — não edite manualmente.

## Instalação

```bash
# Clone o repo OU baixe o tarball da release
git clone https://github.com/bldantas/unbound-dashboard.git
cd unbound-dashboard/clients/js

# Pra build TypeScript:
npm install
npm run build
```

Requer **Node 18+** ou bundler moderno (Vite, esbuild, webpack, etc).

## Uso

```typescript
import { OpenAPI, AnalyticsService, BlocklistService } from "./clients/js";

OpenAPI.BASE = "https://seu-dashboard.exemplo.com";

// Auth via API token (recomendado em produção)
OpenAPI.HEADERS = {
  "X-Api-Token": "...",
};

// Ou via Bearer JWT (login humano)
// OpenAPI.TOKEN = "<jwt>";

// Snapshot de métricas
const summary = await AnalyticsService.getSummaryApiV1AnalyticsSummaryGet({ window: "24h" });
console.log(`QPS: ${summary.totals.qps}, hit_ratio: ${summary.totals.hit_ratio}`);

// Listar exceptions
const exceptions = await BlocklistService.listExceptionsApiV1BlocklistExceptionsGet();
console.log(`Allowlist: ${exceptions.count} domínios`);

// Adicionar exception
await BlocklistService.addExceptionApiV1BlocklistExceptionsPost({
  requestBody: { domain: "googletagmanager.com", reason: "Marketing precisa" },
});
```

## Autenticação JWT (login)

```typescript
import { AuthService, OpenAPI } from "./clients/js";

const tokens = await AuthService.loginApiV1AuthLoginPost({
  requestBody: { username: "admin", password: "..." },
});

OpenAPI.TOKEN = tokens.access_token;
```

## Cancellable promises

Cada chamada retorna uma `CancelablePromise`:

```typescript
const promise = AnalyticsService.getSummaryApiV1AnalyticsSummaryGet();
setTimeout(() => promise.cancel(), 1000);
try {
  const summary = await promise;
} catch (err) {
  if (err.isCancelled) console.log("Cancelado");
}
```

## Estrutura

```
clients/js/
├── core/                       # Cliente base (OpenAPI config, request, cancel)
├── models/                     # Tipos TS pra request/response
├── services/                   # Um arquivo por tag (Alerts, Analytics, etc)
├── UnboundDashboardClient.ts   # Cliente "client object" (alternativa ao static)
└── index.ts                    # Re-exports
```

## Re-gerar

Após mudanças no schema da API:

```bash
sudo bash tools/gen_sdk_js.sh
```

## Limitações

- Sem retry/backoff built-in — use middleware externo
- Não publicado no npm; sempre importado direto do diretório do repo
- TypeScript opcional — pode usar em projeto JS puro (sem tipos)
