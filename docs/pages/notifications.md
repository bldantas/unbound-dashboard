# `/notifications.php` — Notificações por usuário + digest diário

Preferências de notificação **por usuário**: severity mínima, categorias, e digest diário por email.

**Disponível desde:** v2.43+ (notification center); v2.90 (preferências per-user); v2.93 (HTML digest); v2.106 (paginação multi-email).

---

## Como cada user recebe

Cada user tem 1 row em `user_notification_prefs` (criada lazy na primeira visita). Campos:

| Campo | O que faz |
|---|---|
| `severity_min` | Só notifica se `severity >= esse_valor`. Valores: `info` < `warning` < `critical` |
| `categories` (JSON array) | Filtro de tipos. Vazio = tudo. Aceita prefixos: `alert` casa com `alert.cpu`, `alert.memory`, etc; `anomaly_` casa com `anomaly_dga`, `anomaly_tunneling`, etc |
| `digest_enabled` | Boolean. Se true, manda email diário com tudo das últimas 24h que passa nos filtros |
| `digest_hour` | Hora UTC (0..23) em que o digest é enviado |
| `last_digest_sent_at` | Timestamp do último digest. Evita duplicação se worker reiniciar na hora exata |

Default ao criar conta: severity_min=`warning`, categories=`[]` (tudo), digest_enabled=`false`.

---

## Como acionar (por user)

1. `/notifications.php` (qualquer user logado)
2. Configurar:
   - Severity mínima (radio: info / warning / critical)
   - Categorias (chips toggleável)
   - Toggle "Habilitar digest diário"
   - Se habilitado: hora UTC (com hint do timezone local)
3. Salvar → próximo tick do `DigestSender` (1x/hora) considera a config

---

## DigestSender worker (resumo)

Tick **horário** (1x/hora). Pra cada user em `due_for_digest(current_hour)`:
- Busca alerts/anomalies das últimas 24h que passam nos filtros (severity_min + categories)
- Manda email via SMTP configurado em `/smtp.php`
- Marca `last_digest_sent_at` pra não duplicar

Paginação (v2.106): se > 500 eventos, divide em chunks de 500 e manda 1 email por chunk. Subject: `"[Unbound Dashboard] Digest diário — N eventos (parte X/Y)"`. Body indica a parte + banner azul "continua na parte X+1".

HTML email (v2.93+) com badges coloridos por severidade. Plain-text fallback pra clientes que não renderizam HTML. Footer com link `/notifications.php` pra editar preferências.

---

## Webhook + SMTP — onde configurar

Notificação por email/webhook globais (sem opt-in per-user) configurada em:
- `/smtp.php` — credenciais SMTP, from address, test
- `/webhooks.php` — URL, secret, eventos disparados

Workers `email_notifier` + `webhook_notifier` mandam **na hora** que o alerta sobe (não esperam digest). Digest é opt-in adicional pra **resumo periódico** sem ser bombardeado em tempo real.

---

## Pruning automático

`NotificationPruner` worker (diário): apaga notificações com mais de N dias (default 30, configurável em settings). Mantém DuckDB enxuto.

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/notifications/prefs` | Lê as próprias prefs do user (JWT decoded) |
| `PUT /api/v1/notifications/prefs` | Atualiza as próprias prefs |
| `GET /api/v1/notifications/feed` | Feed paginado de notificações do user |
| `POST /api/v1/notifications/{id}/read` | Marca como lida |
| `POST /api/v1/notifications/read-all` | Marca tudo como lido |
| `POST /api/v1/notifications/prune-now` | Roda pruner manual (admin) |

WebSocket: `/api/v1/ws/notifications` — feed live, broker em memória.

---

## Limitações conhecidas

- **Digest hora é UTC, não timezone do user** — se user está em UTC-3 e quer receber às 09:00 local, configurar `digest_hour=12` (12 UTC = 09 BRT)
- **Sem snooze temporário** — se ficou ruidoso, edita categories ou severity_min direto
- **Sem template custom** — body do email é fixo (badges + ordem por severity)
- **Múltiplos digests no mesmo dia** não são possíveis (dedupe via `last_digest_sent_at` é por DATE)

---

## Memórias relacionadas

- [Sessão 2026-05-28 gap](../../.claude/projects/-var-www-html-unbound-dashboard/memory/sessao_2026-05-28_gap.md) — implementação do digest HTML em v2.93
