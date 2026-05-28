# `/cluster.php` — Cluster HA (peers + manual failover)

Página de administração de peers HA do Unbound Dashboard. Permite:

- Ver status agregado do cluster (KPIs + tabela)
- Adicionar/remover peers com healthcheck autenticado
- Substituir o token de um peer existente (botão 🔑) — útil pra fechar o link quando os dois lados foram criados sem coordenar
- Failover manual de role (primary ↔ secondary) — **só atualiza o registro**, não muda rede/DNS

**Disponível desde:** v2.16+ (CRUD + monitor); v2.103.0 (cluster bidirecional autenticado); v2.103.1 (botão 🔑); v2.103.2 (fix do pycache no update.sh).

---

## Conceito: shared-secret-per-link

Cada par A↔B no cluster compartilha **um único token**. Ambos os lados guardam:

- **Raw cifrado** (`api_token_raw_encrypted`) — usado pra **mandar** como `X-Api-Token` no probe
- **Bcrypt hash** (`api_token_hash`) — usado pra **verificar** quando recebe um probe

O endpoint [`GET /api/v1/cluster/peer-ping`](../../api_service/app/routers/cluster.py) exige `X-Api-Token` (ou `Authorization: Bearer`) e valida via `bcrypt.checkpw` contra **todos** os hashes locais. Retorna `{ok, version, matched_peer_label, matched_peer_role}` se autenticado, 401 caso contrário.

---

## Setup em 3 passos (cenário recomendado)

### 1. No servidor A — cria peer apontando pro B

Abra `/cluster.php` no servidor A, role até o painel **Adicionar peer**:

| Campo | Valor |
|---|---|
| Label | `SRV-B` (qualquer nome legível) |
| API URL | `https://srv-b.exemplo.com` (URL pública do dashboard de B) |
| Role | `secondary` (ou `primary`, conforme topologia) |
| Prioridade | `100` (default) |
| **Token existente** | **(deixar em branco)** |
| 🔐 Healthcheck autenticado | marcado |

Clica **Adicionar**. O backend gera um token aleatório T (`secrets.token_urlsafe(32)`), grava hash + raw cifrado em A, e exibe T no modal "Peer criado. TOKEN (apenas exibido agora)". **Copie T agora** — não dá pra recuperar depois.

### 2. No servidor B — cria peer apontando pro A com o mesmo token

Abra `/cluster.php` no servidor B:

| Campo | Valor |
|---|---|
| Label | `SRV-A` |
| API URL | `https://srv-a.exemplo.com` |
| Role | conforme topologia |
| **Token existente** | **cole o T copiado no passo 1** |

Clica **Adicionar**. B reusa o mesmo T (gera hash dele, cifra raw). Modal mostra "Peer criado reutilizando o token fornecido."

### 3. Validar

Volte em qualquer um dos dois servidores, clique **Check** no card do peer. Espera-se:

- Status: `ok`
- Latência: ~tempo de RTT entre os servidores
- Modal de detalhes: `Probe: /api/v1/cluster/peer-ping`, `Autenticado: sim`, `matched_peer_label: <o outro lado>`
- Badge 🔐 verde aparece ao lado do label

Se ambos os lados ficam `ok` 🔐, cluster autenticado bidirecional está funcionando.

---

## Cenário corretivo: já criei dos dois lados, mas tokens diferentes

Comum quando o operador foi direto sem ler o guia. Cada lado gerou seu próprio token, nenhum reconhece o do outro, **Check** vira `unauthorized`.

**Como alinhar:**

1. Escolha um dos tokens — digamos T_A (o que A gerou).
2. No **servidor B**, clique no botão `🔑` do peer "SRV-A" → modal pede o token novo.
3. Cole T_A e confirme.

Agora ambos os lados têm `hash(T_A)` + `raw(T_A)`. Próximo Check vira `ok`.

> O endpoint `PUT /api/v1/ha/peers/{id}/token` audita a substituição em `admin_audit` com `action: ha.peer.token_replaced`.

---

## Diagnóstico de status comum

A coluna "Status" da tabela mostra o último resultado do probe (atualizado a cada 30s pelo worker `HAPeerMonitor`, ou imediatamente via botão **Check**). Ícone ⓘ no status indica que há mensagem de erro no payload — passe o mouse pra ler.

| Status | Significado | Como resolver |
|---|---|---|
| `ok` 🔐 | Probe autenticado retornou 200 | — |
| `ok` (sem 🔐) | `/healthz` respondeu mas token não foi enviado | Marca "Healthcheck autenticado" + cola token |
| `unauthorized` | Peer rejeitou o `X-Api-Token` | Tokens divergentes → use botão 🔑 pra alinhar |
| `not_found` | Peer respondeu 404 em `/api/v1/cluster/peer-ping` | Peer está em versão antiga (< v2.103.0) — atualize |
| `timeout` | Sem resposta em 5s | Verifica conectividade, firewall, DNS |
| `error` | HTTP 5xx ou response inesperado | Olhe `journalctl -u unbound-dashboard-api` no peer remoto |
| `down` | Connection refused, TLS quebrado, DNS resolve falhou | Verifica se `dashboard-api` está rodando no peer |

---

## Manual failover (mudar role)

Painel rosé "Manual Failover" (admin only):

1. Selecione **Promover (→ primary)** — peer secondary que vai virar primary
2. **Demover (opcional)** — primary que vira secondary
3. Clica **Executar Failover** → confirmação modal

⚠️ **NÃO toca em rede, DNS ou IP virtual** — só atualiza o registro `role` em `ha_peers`. O operador é responsável pelo cutover real (mudar A record, mover IP virtual, ajustar keepalived/anycast).

Failover gera entrada em `admin_audit` (`action: ha.failover`) e pode exigir aprovação via [`approval_service`](../../api_service/app/services/approval_service.py) dependendo da config de aprovações pra `config.write`.

---

## Pré-requisitos

- **Ambos os servidores** em v2.103.0+ (endpoint `/cluster/peer-ping` foi adicionado nessa versão)
- **Recomendado**: ambos com `SECRETS_MASTER_KEY` configurada — sem ela, tokens HA ficam plaintext no DuckDB (cluster funciona mesmo assim, mas é gap de segurança)
- Conectividade HTTPS bidirecional entre os peers (porta 443 ou que estiver configurada no Apache)
- Certificados TLS válidos (ou aceitar self-signed — o `httpx.AsyncClient(verify=False)` no `check_peer` é tolerante)

---

## Limitações conhecidas

- **Sem replicação automática de config** — cada servidor mantém seu próprio DuckDB; o cluster atual é só pra observabilidade + failover assist
- **Sem split-brain detection** — operador precisa garantir que só 1 peer é primary ativo de fato
- **Bcrypt verify por probe** — custo ~10ms × N peers; ok pra 2-5 peers, escala mal acima de 50 (improvável)
- **`/healthz` é público** — fallback usado quando peer não tem token raw cifrado guardado. Probe anônimo só confirma "responde HTTP", não autentica

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/ha/status` | KPIs agregados + lista de peers |
| `GET /api/v1/ha/peers` | Lista detalhada de peers |
| `POST /api/v1/ha/peers` | Cria peer (body opcional `existing_token` pra modo espelho) |
| `PUT /api/v1/ha/peers/{id}` | Atualiza label, URL, role, priority, enabled |
| `PUT /api/v1/ha/peers/{id}/token` | Substitui o token in-place (audit log) |
| `DELETE /api/v1/ha/peers/{id}` | Remove peer (pode exigir aprovação) |
| `POST /api/v1/ha/peers/{id}/check` | Probe imediato (botão Check da UI) |
| `POST /api/v1/ha/failover` | Failover manual (audit log + aprovação) |
| `GET /api/v1/cluster/peer-ping` | **Endpoint que peers HA chamam mutuamente** — exige X-Api-Token |

---

## Memórias relacionadas

- [feature_cluster_v2_103.md](../../.claude/projects/-var-www-html-unbound-dashboard/memory/feature_cluster_v2_103.md) — design do shared-secret-per-link + receita de diagnóstico (bytecode stale, SECRETS_MASTER_KEY ausente)
- [unbound_stderr_logfile_quirk.md](../../.claude/projects/-var-www-html-unbound-dashboard/memory/unbound_stderr_logfile_quirk.md) — drop-in que é pré-requisito pro LogWatcher funcionar (não tem relação direta com cluster mas trava observabilidade)
