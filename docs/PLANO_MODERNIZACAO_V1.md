# Plano de Modernização — Unbound Dashboard v1

> **Data de elaboração:** 4 de maio de 2026
> **Escopo:** Modernização incremental da v1 (PHP + MariaDB) in-place
> **Status atual:** ✅ **CONCLUÍDA** (2026-05-04, v2.2.0). MariaDB removido. Sistema 100% DuckDB.
> **Status da v2:** Isolada e pausada em `/opt/unbound-dashboard/` — não é o foco.

---

## ✅ Marcos concluídos (resumo)

| Data | Marco | Versão |
|---|---|---|
| 2026-04-29 | api_service (FastAPI/DuckDB/Redis) deployado em paralelo via Strangler Fig. JWT compartilhado, ApiClient PHP, workers asyncio (LogWatcher, StatsAggregator, AlertChecker, JsonExporter), cutover dos 3 crons PHP. | v2.1.0 |
| 2026-05-04 | Migração dos 6 Managers PHP (Blocklist, SourceBalance, Alert, Unbound, UnboundConfig, SystemCheck) para ApiClient. `update_blacklist.php` reescrito. Tear-down do MariaDB executado: backup mysqldump, DROP DATABASE, DROP USER, systemctl stop+disable mariadb. Smoke 8/8 páginas críticas OK. | v2.2.0 |
| 2026-05-04 | Tooling de build/install/update reescrito (sem MariaDB). `create_admin.py` idempotente. Documentação atualizada. | v2.2.1 |

Ver detalhes em [`CHANGELOG.md`](../CHANGELOG.md). O conteúdo abaixo é o **plano original** mantido como histórico.

---

## Contexto

O projeto v1 (`/var/www/html/unbound-dashboard/`) continua sendo o sistema em produção.
Em paralelo, existe um projeto v2 completamente reescrito em Python/FastAPI/Vue 3 localizado em `/opt/unbound-dashboard/`, mas ele está **isolado e pausado** — não será trabalhado por enquanto.

A estratégia adotada é **evoluir a v1 in-place**, adicionando componentes modernos de forma incremental, sem reescrever tudo de uma vez. O frontend PHP (SSR + Tailwind + Vanilla JS) é mantido; as melhorias são feitas na camada de backend, banco de dados, cache e ingestão de logs.

---

## O que será adicionado à v1

### 1. Banco de dados — MariaDB → DuckDB

**Estado atual:** MariaDB via PDO (`src/Database.php`), queries manuais, schema gerenciado por `scripts/init_db.sql` e `scripts/migrate_db.sql`.

**Mudança planejada:** Substituir MariaDB por DuckDB como banco principal.

- Arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb`
- API DuckDB PHP nativa (`duckdb` extension ou via FFI) ou acesso via processo Python intermediário
- Sem servidor de banco separado — zero dependência de rede
- Performance OLAP nativa para queries analíticas sobre `query_logs` (tabela com milhões de linhas)
- Migrations SQL versionados em `migrations/duckdb/`
- Writes serializados (DuckDB não suporta múltiplos writers simultâneos)

**Tabelas a migrar:**

| Tabela (MariaDB) | Tabela (DuckDB) | Observações |
|---|---|---|
| `users` | `users` | Hash bcrypt preservado |
| `settings` | `settings` | Pares key/value preservados |
| `alerts` | `alerts` | Schema equivalente |
| `query_logs` | `query_logs` | TIMESTAMP → naive UTC |
| `daily_stats` | `daily_stats` | `blocked_count` preservado |
| `domain_blacklist` | `blocklist_domains` | Normalizado |

---

### 2. Camada de API — endpoints PHP ad-hoc → FastAPI

**Estado atual:** `api/*.php` — endpoints JSON servidos pelo Apache, sem validação de schema, sem rate limiting, sem documentação.

**Mudança planejada:** Adicionar um processo FastAPI (Python) rodando em paralelo ao Apache, servindo os endpoints JSON da aplicação.

- FastAPI em `api_service/` rodando via Uvicorn (porta separada, ex: 8001)
- Apache proxia `/api/*` para o FastAPI via `mod_proxy` ou `mod_rewrite`
- O frontend PHP continua servindo o HTML — apenas as chamadas AJAX passam pelo FastAPI
- Validação automática de payloads com Pydantic v2
- Rate limiting com `slowapi`
- Swagger UI disponível em `/api/docs` para desenvolvimento
- Endpoints migrados progressivamente — os `.php` antigos podem coexistir durante a transição

**Benefícios imediatos:**
- Workers assíncronos nativos (sem cron externo para ingestão e alertas)
- WebSocket nativo para live-log (elimina polling HTTP)
- Testes automatizados com pytest

---

### 3. Cache e pub/sub — arquivos JSON → Redis

**Estado atual:** Cache via arquivos JSON em disco (`data/latest_stats.json`, `data/stats_history.json`), com `flock()` para evitar escrita concorrente. Live-log via polling HTTP.

**Mudança planejada:** Adicionar Redis 8 como camada de cache e pub/sub.

- Redis local (porta 6379)
- Stats calculadas armazenadas com TTL confiável — sem dependência de cron rodando
- Canal pub/sub `live:logs` — `LogWatcher` publica cada linha parseada; frontend consome via WebSocket sem polling
- `StatsManager.php` passa a ler do Redis em vez de arquivo JSON
- Os arquivos `data/*.json` tornam-se opcionais / fallback

---

### 4. Ingestão de logs — `log_ingester.php` → worker Python

**Estado atual:** `scripts/log_ingester.php` — loop `popen(tail | grep)` em processo PHP de longa duração. Sem supervisão; reinicialização manual se cair.

**Mudança planejada:** Substituir por worker Python asyncio gerenciado pelo FastAPI (lifespan) ou por systemd unit dedicado.

- `app/workers/log_watcher.py` — corrotina asyncio lendo `/var/log/syslog`
- Publica no canal Redis `live:logs` + batch insert no DuckDB
- Reiniciado automaticamente pelo Uvicorn/systemd em caso de falha
- Métricas Prometheus: `queries_ingested_total`, `queries_blocked_total`, `worker_errors_total`

---

### 5. Alertas — `cron_alerts.php` → worker Python

**Estado atual:** `scripts/cron_alerts.php` agendado via cron do sistema. Sem auto-resolução, sem cooldown.

**Mudança planejada:** Task asyncio periódica dentro do processo FastAPI.

- Auto-resolve alertas `no_queries` quando tráfego retorna
- Cooldown de 6h para evitar recreação após reconhecimento manual
- Erros logados com structlog; sem falha silenciosa

---

### 6. Agregação de stats — `aggregate_stats.php` → worker Python

**Estado atual:** `scripts/aggregate_stats.php` agendado via cron. Falha silenciosa se cron parar.

**Mudança planejada:** Task asyncio periódica dentro do processo FastAPI.

- Executa de forma contínua enquanto o processo FastAPI estiver ativo
- Erros registrados com métricas Prometheus

---

### 7. Observabilidade — `error_log()` → structlog + Prometheus

**Estado atual:** `error_log()` PHP sem formato estruturado. Sem métricas expostas.

**Mudança planejada:**
- `structlog` com output JSON para todos os logs do backend Python
- Endpoint `GET /metrics` com métricas Prometheus
- `X-Request-ID` injetado em todas as requisições passando pelo FastAPI

---

## O que NÃO muda por enquanto

| Componente | Mantido |
|---|---|
| Frontend | PHP SSR + Tailwind CDN + Vanilla JS |
| Web server | Apache 2 |
| Autenticação | `src/Auth.php` (sessão PHP + CSRF) |
| Páginas | Todos os `.php` da raiz |
| Includes | `includes/` (sidebar, topbar, head, footer) |
| Classes PHP | `src/*.php` — refatoradas gradualmente conforme necessário |

---

## Estratégia de transição

A adição é **incremental e não-destrutiva**:

1. MariaDB e DuckDB coexistem durante a migração de dados
2. `api/*.php` e os novos endpoints FastAPI coexistem — migração endpoint por endpoint
3. Cache JSON em disco e Redis coexistem — Redis assume quando disponível, JSON é fallback
4. `log_ingester.php` e o worker Python não rodam simultâneos (evitar duplicação)
5. Crons PHP são desativados somente após workers Python estarem estáveis

---

## Localização dos componentes

| Item | Caminho |
|---|---|
| Projeto v1 (em produção) | `/var/www/html/unbound-dashboard/` |
| Projeto v2 (pausado) | `/opt/unbound-dashboard/` |
| DuckDB (planejado) | `/var/lib/unbound-dashboard/unbound_dash.duckdb` |
| Redis | `localhost:6379` (já instalado) |
| FastAPI service (planejado) | `/var/www/html/unbound-dashboard/api_service/` |

---

## Dependências a instalar

```bash
# Python e gerenciador de pacotes
python3.13 já disponível no sistema
uv já disponível

# Pacotes Python
fastapi uvicorn[standard] duckdb redis aioredis pydantic-settings
bcrypt python-jose slowapi structlog prometheus-fastapi-instrumentator psutil

# Redis (já instalado e ativo via systemd)
redis-server 8.x
```
