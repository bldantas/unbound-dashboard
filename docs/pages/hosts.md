# `/hosts.php` — Multi-host (servidores Unbound gerenciados)

Adiciona servidores Unbound **secundários** ao painel pra monitoramento agregado. Diferente do **cluster HA** (que é par espelho), multi-host é hub-and-spoke: 1 dashboard "master" pull-a métricas de N "agents".

**Disponível desde:** v2.21-v2.22 (poller + UI + drill-down + batch ops); v2.89 (multi-tenant).

---

## Quando usar vs cluster HA

| Cenário | Multi-host (`/hosts.php`) | Cluster HA (`/cluster.php`) |
|---|---|---|
| Vários Unbound em locais diferentes, 1 painel central | ✅ | ❌ |
| 2 Unbound idênticos pra alta disponibilidade | ❌ | ✅ |
| Probe autenticado bidirecional | ❌ (master → agent só) | ✅ |
| Failover (mudar role primary/secondary) | ❌ | ✅ |
| Visão agregada de QPS/CHR/alerts | ✅ | parcial |

Os dois **coexistem**. Mesmo servidor pode ser agent de um master e peer HA de outro.

---

## Setup em 5 passos

### No servidor **agent** (o secundário, vai ser monitorado):

1. `/users.php` → **API Tokens** → "Gerar token de agent" → copia o token raw (exibido 1x).
   - Esse token é hash-bcryptado e fica em `api_tokens`. O raw nunca volta a aparecer.

### No servidor **master** (dashboard central):

2. `/hosts.php` → **Adicionar host** → label, API URL (do agent), cola o token raw.
3. Atribua `Org` se for multi-tenant (campo opcional). Sem org = global.
4. **HostPoller** worker pega o host na próxima tick (configurável, default ~60s) — faz `GET /api/v1/stats/summary` no agent com `X-Api-Token: <raw>`.
5. Card aparece no `/hosts.php` com QPS, cache hit ratio, alerts ativos. Atualiza periodicamente.

---

## O que aparece no master

| Métrica | Fonte no agent | Refresh |
|---|---|---|
| QPS | `/stats/summary` | tick do HostPoller |
| Cache hit ratio | idem | idem |
| Alerts ativos | `/alerts/list` | idem |
| Versão do agent | `/healthz` | idem |
| Disco / RAM / CPU | `/host/storage` | idem |
| Histórico de polls | `host_poll_history` (DuckDB local do master) | log persistente |

Drill-down: clica no card → modal com gráficos do agent específico + último N polls + status detalhado.

---

## Batch ops

`/hosts.php` tem batch operations no header pra agir em N hosts de uma vez:

- **Forçar reload do Unbound** em todos (admin only) — manda `POST /api/v1/unbound/reload` em paralelo
- **Sync blocklist** em todos — útil quando você atualizou blocklist no master e quer propagar pros agents

Reuso de SSE no modal: log live agregado de N hosts, 1 linha por host.

---

## Multi-tenant (v2.89+)

`managed_hosts.org_id` (V25). Filtro padrão:
- System admin (`org_id NULL`): vê todos os hosts
- User org-scoped (`org_id N`): vê hosts globais + da própria org

Pra criar host na org via UI, system admin escolhe Org no dropdown; user org-scoped só pode pra própria (403 se tentar outra).

---

## Troubleshooting

| Sintoma | Causa provável |
|---|---|
| Card mostra "down" | Token errado / firewall bloqueando porta 443 ou 8001 do agent / agent caiu |
| Card vazio mas status ok | HostPoller ainda não rodou o primeiro tick — espera ~1min |
| Token rejected (401) | Token foi revogado ou o hash no agent não bate (recria token + edita host) |
| Pollings duplicados no log | Mais de 1 `HostPoller` rodando — checa se o supervisor não respawnou após crash |

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/hosts/` | Lista hosts (com filtro tenant) |
| `POST /api/v1/hosts/` | Cria host gerenciado |
| `PUT /api/v1/hosts/{id}` | Atualiza |
| `PUT /api/v1/hosts/{id}/org` | Move pra outra org (system admin) |
| `DELETE /api/v1/hosts/{id}` | Remove |
| `POST /api/v1/hosts/batch/reload` | Reload Unbound em N hosts |
| `POST /api/v1/hosts/batch/sync-blocklist` | Sync blocklist em N |

No **agent**, o que precisa estar acessível é:
- `GET /api/v1/healthz` (público)
- `GET /api/v1/stats/summary` (X-Api-Token)
- `GET /api/v1/alerts/list` (X-Api-Token)
- `GET /api/v1/host/storage` (X-Api-Token)

---

## Limitações conhecidas

- Master não pode aplicar config remotamente no agent (sem write API — só read)
- Agents não fazem push (modelo é pull-only do master)
- Cada master vê os agents do seu cadastro — sem federação entre masters
