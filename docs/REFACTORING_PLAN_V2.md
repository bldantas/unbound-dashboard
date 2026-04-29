# Plano de Refatoração — Unbound Dashboard v2.0

> **Status**: Proposta técnica — aprovação necessária antes de implementação  
> **Data de elaboração**: 29 de abril de 2026  
> **Versão atual**: 1.x (PHP 8.1 + MariaDB + Apache)  
> **Versão alvo**: 2.0 (Python/FastAPI + Vue 3/TypeScript + DuckDB)
---

## Índice

1. [Análise do Sistema Atual](#1-análise-do-sistema-atual)
2. [Stack Tecnológica Recomendada](#2-stack-tecnológica-recomendada)
3. [Arquitetura v2.0](#3-arquitetura-v20)
4. [Estrutura de Pastas](#4-estrutura-de-pastas)
5. [Exemplos de Código Refatorado](#5-exemplos-de-código-refatorado)
6. [Estratégia de Migração](#6-estratégia-de-migração)
7. [Plano de Testes e CI/CD](#7-plano-de-testes-e-cicd)
8. [Monitoramento e Manutenção](#8-monitoramento-e-manutenção)
9. [Cronograma Sugerido](#9-cronograma-sugerido)
10. [Riscos e Mitigações](#10-riscos-e-mitigações)

---

## 1. Análise do Sistema Atual

### 1.1 Inventário de Componentes

| Camada | Componente | Arquivo(s) | Responsabilidade |
|---|---|---|---|
| Banco de dados | PDO/MariaDB | `src/Database.php` | Singleton de conexão |
| Autenticação | RBAC (admin/viewer) | `src/Auth.php` | Login, lockout, CSRF |
| DNS | UnboundManager | `src/UnboundManager.php` | Stats, versão, controle do daemon |
| DNS | UnboundConfigManager | `src/UnboundConfigManager.php` | Leitura/escrita de `unbound.conf` |
| Rede | NetworkManager | `src/NetworkManager.php` | Interfaces, IPv4/IPv6, NTP |
| Monitoramento | ServerMonitor | `src/ServerMonitor.php` | CPU, RAM, Disco |
| Monitoramento | AppMetricsManager | `src/AppMetricsManager.php` | Métricas da aplicação |
| Segurança | SecurityMonitor | `src/SecurityMonitor.php` | SSH brute-force, ameaças |
| Alertas | AlertManager | `src/AlertManager.php` | Detecção e persistência |
| Blocklists | BlocklistManager | `src/BlocklistManager.php` | Fontes e aplicação |
| Diagnósticos | DiagnosticsManager | `src/DiagnosticsManager.php` | Testes de conectividade |
| Balance | SourceBalanceManager | `src/SourceBalanceManager.php` | Múltiplas instâncias Unbound |
| Stats | StatsManager | `src/StatsManager.php` | Cache de métricas JSON |
| Shell | ShellHelper | `src/ShellHelper.php` | Execução segura de comandos |
| Ingestão | log_ingester.php | `scripts/log_ingester.php` | Parsing de syslog → DB |
| Agregação | aggregate_stats.php | `scripts/aggregate_stats.php` | Cron de resumo |
| API | stats, threats, alerts… | `api/*.php` | Endpoints JSON (AJAX) |
| Frontend | Tailwind CDN + Vanilla JS | `*.php` + `src/dashboard.css` | UI SSR com islands AJAX |

### 1.2 Pontos Fortes do Sistema Atual

- Autenticação com lockout e CSRF token bem implementados
- Queries otimizadas com índices compostos (`idx_action_ts`)
- Cache de métricas via arquivo JSON (evita pressão no banco)
- RBAC funcional (admin/viewer)
- Camada Shell protegida via `ShellHelper` com `escapeshellarg`

### 1.3 Limitações e Débitos Técnicos

| # | Problema | Impacto |
|---|---|---|
| 1 | PHP SSR com AJAX híbrido — sem separação clara de frontend/backend | Manutenibilidade baixa |
| 2 | `StatsManager` usa lock de arquivo (`flock`) para evitar concorrência | Frágil sob carga; não escala horizontalmente |
| 3 | `log_ingester.php` usa `popen(tail | grep)` — processo PHP de longa duração | Reinicialização manual necessária se cair |
| 4 | Cache JSON em disco (`latest_stats.json`, `time_series.json`) sem invalidação TTL confiável | Dados obsoletos sem processos cron rodando |
| 5 | Ausência de WebSockets — "live log" usa polling HTTP | Latência desnecessária e carga no servidor |
| 6 | Ausência de testes automatizados (zero cobertura) | Risco alto em cada mudança |
| 7 | Dependência de `sudo` sem escopo granular de permissões | Superfície de ataque ampla |
| 8 | Frontend usa Tailwind via CDN e Vanilla JS sem bundler | Sem tree-shaking, JS difícil de manter |
| 9 | `Environment::get()` lê `.env` sem validação de schema | Erros silenciosos em runtime |
| 10 | Sem rate limiting nas APIs | Vulnerável a enumeração de dados |

---

## 2. Stack Tecnológica Recomendada

### 2.1 Decisão Final: Python/FastAPI + DuckDB + Vue 3

Após avaliar Go, Node.js e Python/FastAPI contra as necessidades específicas deste projeto, a recomendação é:

```
Backend API:      Python 3.12 + FastAPI + asyncio
Banco de dados:   DuckDB 1.x   (banco único — OLTP serializado + OLAP nativo)
Frontend:         Vue 3 + TypeScript + Vite + Tailwind CSS v4
Cache:            Redis 7       (TTL de métricas, pub/sub para live-log)
Reverse Proxy:    Caddy 2       (substitui Apache — TLS automático, HTTP/2)
Workers:          asyncio tasks + systemd units (substituem cron PHP)
```

### 2.2 Comparativo de Stacks para este Projeto

| Critério | PHP (atual) | Go | Node.js | **Python/FastAPI** |
|---|---|---|---|---|
| Performance REST (req/s) | ~2 k | ~50 k | ~15 k | **~8–12 k** |
| Uso de memória | Alto | Muito baixo | Médio | Médio |
| Tipagem + validação | Parcial | Nativa | Com TS | **Pydantic v2 (melhor da categoria)** |
| Async I/O nativo | ✗ | Goroutines | Event loop | **asyncio nativo** |
| Subprocess seguro (sem shell) | Parcial | `exec.CommandContext` | `child_process` | **`asyncio.create_subprocess_exec`** |
| OpenAPI auto-gerado | ✗ | Manual/swaggo | Manual | **✓ (nativo no FastAPI)** |
| Libs de monitoramento de sistema | Limitado | Bom | Fraco | **Excelente (psutil, py-systemd)** |
| Libs de rede/DNS | Fraco | Bom | Fraco | **Excelente (dnspython, scapy)** |
| Banco analítico embutido | ✗ | ✗ | ✗ | **DuckDB (game changer p/ DNS logs)** |
| WebSocket | Polling | Nativo | Nativo | **Nativo (Starlette)** |
| Curva de aprendizado | Baixa | Alta | Baixa | **Baixa** |
| Ecossistema de testes | Regular | Bom | Bom | **Excelente (pytest, hypothesis)** |

Python/FastAPI com DuckDB é a escolha mais adequada **para este projeto específico** pelas razões detalhadas nas seções a seguir.

---

### 2.3 Por que FastAPI + asyncio

**Resolução direta dos débitos técnicos atuais:**

| Problema atual (PHP) | Solução FastAPI |
|---|---|
| `popen(tail\|grep)` — processo frágil | `asyncio.create_subprocess_exec` não-bloqueante, reinicia automaticamente pelo systemd |
| `flock` para evitar concorrência em cache | `asyncio.Lock` — concorrência cooperativa, sem race conditions |
| Validação manual de parâmetros em cada endpoint | Pydantic v2 — modelos declarativos com tipos, validação automática e mensagens de erro precisas |
| Ausência de documentação de API | FastAPI gera `/docs` (Swagger) e `/redoc` automaticamente a partir dos modelos Pydantic |
| Sessões PHP com CSRF manual | JWT stateless com `python-jose`; sem estado de sessão no servidor |
| `.env` sem validação de schema | `pydantic-settings` — variáveis de ambiente com tipagem, valores padrão e erros claros no startup |

**asyncio para workers de background:**
```
O log_ingester.php atual usa popen() em loop infinito — cai silenciosamente.

asyncio permite múltiplos workers no mesmo processo:
  ├── LogWatcherTask   → lê syslog via asyncio.create_subprocess_exec
  ├── StatsAggregator  → ticker a cada 60 s
  ├── AlertChecker     → ticker a cada 30 s
  └── BlocklistSyncer  → ticker diário

Todos supervisionados pelo systemd, com restart automático e journal logging.
```

---

### 2.4 Por que DuckDB — O Argumento Central

> Este é o ponto mais relevante da stack proposta para o problema de performance desta aplicação.

**O problema atual:**
```sql
-- Esta query faz full scan em 16 milhões de linhas (628 MB)
-- Mesmo com idx_action_ts, ainda examina ~241k linhas por execução
SELECT domain, COUNT(*) as total
FROM query_logs
WHERE action = 'blocked'
  AND timestamp > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
GROUP BY domain
ORDER BY total DESC
LIMIT 20;
```

**Com DuckDB (banco analítico colunar):**
```sql
-- Mesma query. DuckDB armazena por coluna, não por linha.
-- Lê APENAS as colunas 'action', 'timestamp', 'domain' — ignora o resto.
-- Vectorized execution: processa 1024 linhas por ciclo de CPU.
-- Resultado: 10–100x mais rápido que MariaDB para este padrão.
SELECT domain, COUNT(*) AS total
FROM query_logs
WHERE action = 'blocked'
  AND timestamp > epoch(now()) - 86400
GROUP BY domain
ORDER BY total DESC
LIMIT 20;
```

**Por que DuckDB é o fit ideal para `query_logs`:**

| Característica | MariaDB (atual) | DuckDB |
|---|---|---|
| Modelo de armazenamento | Row-oriented | **Columnar** |
| Padrão de leitura | OLTP (por linha) | **OLAP (por coluna/agregação)** |
| COUNT/GROUP BY em 16M linhas | Lento mesmo com índice | **Nativo e rápido** |
| JOIN entre tabelas grandes | Necessita índices cuidadosos | **Hash join vetorizado** |
| Export direto para Parquet/CSV | ✗ | **✓ (1 linha de SQL)** |
| Query sobre arquivos JSON/Parquet | ✗ | **✓ (sem importação)** |
| Servidor separado | Sim | **Não — embutido na aplicação** |
| Concurrent writes (alta frequência) | ✓ | Limitado |

**DuckDB como banco único (a decisão final):**

```
┌──────────────────────────────────────────────────────────────┐
│  DuckDB 1.x  (banco único — OLTP serializado + OLAP nativo)  │
│                                                              │
│  Tabelas OLTP (escritas serializadas via ThreadPoolExecutor) │
│  ├── users          (auth, lockout, roles — < 100 registros) │
│  ├── settings       (chave-valor — < 50 registros)           │
│  └── alerts         (alertas — baixa frequência)             │
│                                                              │
│  Tabelas OLAP (analytics vetorizadas)                        │
│  ├── query_logs     (16M+ linhas, batch a cada 5 s)          │
│  ├── daily_stats    (resumos agregados)                      │
│  └── blocklist_hits (analytics de blocklist)                 │
└──────────────────────────────────────────────────────────────┘
```

**Por que DuckDB tolera OLTP neste contexto:**
- Volume negligenciável: `users`/`settings`/`alerts` têm < 200 registros e < 10 escritas/minuto
- Toda escrita passa por `ThreadPoolExecutor(max_workers=1)` — serializada, sem concorrência de escrita
- DuckDB tem suporte completo a ACID, UPDATE, DELETE e transações
- Elimina a necessidade de manter um segundo serviço (MariaDB) em produção
- Backup trivial: `cp unbound_dash.duckdb unbound_dash.duckdb.bak`
- JOIN entre `users`, `alerts` e `query_logs` em uma única query SQL — impossível com dois bancos

**Ingestão de logs com DuckDB (batch write):**
DuckDB não é ideal para writes individuais de alta frequência. A solução:
```
syslog → LogWatcherTask (asyncio)
             ↓
       asyncio.Queue (buffer em memória)
             ↓
    FlushTask (a cada 5 segundos)
             ↓
    DuckDB bulk INSERT (centenas de linhas de uma vez)
```
Isso é mais eficiente que o `INSERT` linha a linha do `log_ingester.php` atual.

---

### 2.5 Por que Vue 3 + Vite + Tailwind

**Vue 3 vs React para este projeto:**

| Critério | React + TanStack | **Vue 3 + VueUse** |
|---|---|---|
| Curva de aprendizado | Média | **Baixa** |
| Composable para WebSocket | Biblioteca externa | **`useWebSocket` nativo no VueUse** |
| Estado global | Zustand/Redux | **Pinia (mais simples e com TS nativo)** |
| Bundle size (base) | ~130 KB | **~60 KB** |
| Integração com Tailwind | Boa | **Boa (idêntica)** |
| `<script setup>` (DX) | JSX | **Single-file components — mais legível** |
| Ferramentas de dev | React DevTools | **Vue DevTools + Vite plugin** |

**VueUse é o diferencial prático:**
- `useWebSocket` — live log com reconexão automática, sem código extra
- `useDark` — tema claro/escuro com localStorage (substitui o código atual)
- `useEventSource` — SSE para métricas em tempo real
- `useLocalStorage` — estado de sidebar/preferências persistido
- `useIntervalFn` — polling de métricas com cleanup automático

**Vite + Tailwind v4:**
- Identico ao planejado para React — sem mudanças
- `@tailwindcss/vite` plugin (v4) — zero config, CSS-only, sem `tailwind.config.js`

---

### 2.6 Por que systemd (e não Docker ou supervisor)

O projeto já usa systemd para o Unbound e scripts de sistema. Manter systemd para os workers Python é a escolha natural:

```ini
# /etc/systemd/system/unbound-api.service
[Unit]
Description=Unbound Dashboard API
After=network.target redis.service

[Service]
Type=exec
User=www-data
WorkingDirectory=/opt/unbound-dashboard
ExecStart=/opt/unbound-dashboard/.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8000
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Vantagens sobre Docker neste contexto:
- Acesso direto a `/etc/unbound/`, `/var/log/syslog`, `systemctl`, `unbound-control`
- Sem overhead de containerização em hardware já limitado (servidor DNS)
- Logs unificados no `journalctl` junto com o Unbound
- Restart automático em falha — resolve o problema do `log_ingester.php` que caia silenciosamente

---

### 2.7 Alternativa: Go (para referência)

Go continua sendo uma opção válida se houver preferência por binário estático e footprint mínimo de memória. Os trade-offs em relação a Python para **este projeto específico**:

| Aspecto | Go | Python/FastAPI |
|---|---|---|
| DuckDB | Binding C incompleto (go-duckdb, experimental) | **Binding oficial e maduro (`duckdb` PyPI)** |
| psutil equivalente | `github.com/shirou/gopsutil` (port) | **`psutil` — original, completo, testado** |
| DNS parsing | `miekg/dns` (excelente) | `dnspython` (excelente) |
| OpenAPI | Manual (swaggo) | **Automático (FastAPI nativo)** |
| Curva de aprendizado | Alta | **Baixa** |
| Footprint de memória | ~15 MB | ~80 MB |

A diferença de footprint é irrelevante para um painel web; o binding DuckDB maduro em Python é o fator decisivo.

---

## 3. Arquitetura v2.0

### 3.1 Visão Geral

```
┌─────────────────────────────────────────────────────────┐
│                    Navegador / Cliente                   │
│        Vue 3 SPA (TypeScript + Vite + VueUse)           │
└─────────────────┬───────────────────────────────────────┘
                  │  HTTPS / WebSocket
┌─────────────────▼───────────────────────────────────────┐
│                   Caddy (Reverse Proxy)                  │
│         TLS automático + compressão + rate limit         │
└────────┬────────────────────────────────────────────────┘
         │  HTTP/1.1 + HTTP/2
┌────────▼────────────────────────────────────────────────┐
│           API FastAPI (uvicorn/gunicorn)                 │
│   ┌──────────┐ ┌──────────┐ ┌────────────────────────┐  │
│   │  /auth   │ │  /stats  │ │  /ws/live-log (WS)     │  │
│   │  /users  │ │  /alerts │ │  asyncio + Redis        │  │
│   │  /config │ │  /health │ │  pub/sub fan-out        │  │
│   │  /blocks │ │  /diag   │ │                        │  │
│   └──────────┘ └──────────┘ └────────────────────────┘  │
│                                                          │
│   ┌──────────────────────────────────────────────────┐  │
│   │     Camada de Serviços + Workers asyncio          │  │
│   │  UnboundSvc │ AlertSvc │ BlocklistSvc              │  │
│   │  NetworkSvc │ StatsSvc │ LogWatcher (task)         │  │
│   └──────────────────────────────────────────────────┘  │
└────────┬───────────────────────────────┬───────────────┘
         │                               │
┌────────▼───────────────────────┐  ┌───▼───────────────┐
│  DuckDB 1.x  (banco único)     │  │  Redis 7           │
│  users, settings, alerts       │  │  cache + pub/sub   │
│  query_logs, daily_stats       │  │                    │
│  (OLTP serializado + OLAP)     │  │                    │
└────────────────────────────────┘  └───────────────────┘
         │
┌────────▼──────────────────────────────────────────────┐
│               Sistema Operacional                      │
│  unbound-control │ systemctl │ ip │ journalctl         │
└────────────────────────────────────────────────────────┘
```

### 3.2 Padrão Arquitetural: Layered Architecture

```
app/
  main.py            ← Entry point FastAPI + lifespan tasks
  core/
    config.py        ← pydantic-settings (substitui .env sem validação)
    security.py      ← JWT, bcrypt, dependências de auth
    deps.py          ← Dependências injetadas (DB, Redis, DuckDB)
  models/            ← Pydantic v2 schemas (request/response)
  domain/            ← Entidades e erros de domínio
  services/          ← Lógica de negócio (UnboundService, AlertService…)
  repositories/      ← Acesso a dados (duckdb/, redis/ subdirs)
  routers/           ← Rotas FastAPI (auth, stats, alerts, config…)
  workers/           ← Tasks asyncio (log_watcher, aggregator, checker)
  infrastructure/
    shell.py         ← Executor de subprocessos seguros (allowlist)
    unbound.py       ← Adapter unbound-control
    system.py        ← psutil wrapper (CPU, RAM, Disco, Rede)
web/                 ← Frontend Vue 3 (build Vite)
```

### 3.3 Fluxo de Dados — Live Log (WebSocket)

```
syslog / unbound log
        │
        ▼
asyncio.create_subprocess_exec("tail", "-F", logfile)
  ├── leitura de stdout linha a linha (não-bloqueante)
  └── parse → LogEntry (Pydantic model)
        │
        ▼
asyncio.Queue (buffer em memória, max 1000 entradas)
  ├── FlushTask: a cada 5s → bulk INSERT no DuckDB
  └── PublishTask: publica no Redis pub/sub ("live-log")
        │
        ▼
FastAPI WebSocket endpoint
  ├── Subscreve no Redis pub/sub (aioredis)
  └── Fan-out para clientes conectados
        │
        ▼
Vue 3 (useWebSocket do VueUse) → atualiza reatividade
```

### 3.4 Workers como asyncio Lifespan Tasks

```python
# app/main.py
from contextlib import asynccontextmanager
import asyncio
from fastapi import FastAPI
from app.workers import log_watcher, stats_aggregator, alert_checker

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Inicia todos os workers ao subir a aplicação
    task = asyncio.gather(
        log_watcher.start(),      # tail -F syslog → DuckDB + Redis
        stats_aggregator.start(), # ticker 60 s
        alert_checker.start(),    # ticker 30 s
    )
    yield
    task.cancel()  # Shutdown limpo

app = FastAPI(lifespan=lifespan)
```

Todos os workers vivem no mesmo processo `uvicorn`, supervisionado pelo systemd.

---

## 4. Estrutura de Pastas

```
unbound-dashboard-v2/
├── app/                              # Backend Python (FastAPI)
│   ├── main.py                       # Entry point + lifespan tasks
│   ├── core/
│   │   ├── config.py                 # pydantic-settings (DB, Redis, JWT…)
│   │   ├── security.py               # JWT (python-jose), bcrypt (passlib)
│   │   └── deps.py                   # Dependências injetáveis (get_db, get_duck…)
│   ├── domain/
│   │   ├── user.py                   # Entidade User, erros de domínio
│   │   ├── alert.py                  # Entidade Alert
│   │   ├── dns_query.py              # Entidade DNSQuery
│   │   └── errors.py                 # Exceções de domínio tipadas
│   ├── models/                       # Pydantic v2 (request/response schemas)
│   │   ├── auth.py
│   │   ├── stats.py
│   │   ├── alerts.py
│   │   └── logs.py
│   ├── repositories/
│   │   ├── duckdb/
│   │   │   ├── connection.py         # Conexão DuckDB (ThreadPoolExecutor, max_workers=1)
│   │   │   ├── user_repo.py          # users — leitura/escrita serializada
│   │   │   ├── alert_repo.py         # alerts — persistência
│   │   │   ├── settings_repo.py      # settings — chave/valor
│   │   │   ├── query_log_repo.py     # query_logs — bulk insert + analytics
│   │   │   └── stats_repo.py         # daily_stats — agregações
│   │   └── redis/
│   │       ├── connection.py         # aioredis
│   │       └── stats_cache.py        # Cache com TTL
│   ├── services/
│   │   ├── unbound_service.py        # stats_noreset, controle do daemon
│   │   ├── alert_service.py          # Detecção e persistência de alertas
│   │   ├── blocklist_service.py      # Sincronização de blocklists
│   │   ├── network_service.py        # Interfaces, IPv4/IPv6, NTP
│   │   ├── stats_service.py          # Métricas com cache-first
│   │   └── diagnostics_service.py   # Testes de conectividade
│   ├── routers/
│   │   ├── auth.py                   # POST /auth/login, /auth/refresh
│   │   ├── stats.py                  # GET /stats/metrics, /stats/history
│   │   ├── alerts.py                 # GET/POST /alerts
│   │   ├── blocklist.py              # GET/POST /blocklist
│   │   ├── config.py                 # GET/PUT /config/unbound
│   │   ├── health.py                 # GET /health, /healthz
│   │   ├── diagnostics.py            # POST /diagnostics/run
│   │   ├── network.py                # GET/PUT /network/interfaces
│   │   └── ws_logs.py                # WebSocket /ws/live-log
│   ├── workers/
│   │   ├── log_watcher.py            # tail -F syslog → Queue → DuckDB + Redis
│   │   ├── stats_aggregator.py       # Ticker 60 s — agrega métricas
│   │   └── alert_checker.py          # Ticker 30 s — verifica thresholds
│   ├── infrastructure/
│   │   ├── shell.py                  # Executor seguro (allowlist, sem shell=True)
│   │   ├── unbound.py                # Adapter unbound-control
│   │   └── system.py                 # psutil (CPU, RAM, Disco, Rede)
│   └── middleware/
│       ├── rate_limit.py             # slowapi (rate limiting por IP)
│       ├── request_id.py             # X-Request-ID em cada resposta
│       └── cors.py                   # CORS configurável
│
├── web/                              # Frontend Vue 3 (Vite)
│   ├── src/
│   │   ├── api/
│   │   │   ├── client.ts             # fetch wrapper com auth header
│   │   │   ├── useStats.ts           # composable — métricas do dashboard
│   │   │   ├── useAlerts.ts          # composable — alertas
│   │   │   └── useLiveLog.ts         # composable — WebSocket (VueUse)
│   │   ├── components/
│   │   │   ├── ui/                   # BaseButton, BaseCard, BaseBadge…
│   │   │   ├── charts/               # Wrappers Chart.js (LineChart, BarChart…)
│   │   │   └── layout/               # AppSidebar, AppTopbar, AppFooter
│   │   ├── pages/
│   │   │   ├── Dashboard.vue
│   │   │   ├── History.vue
│   │   │   ├── Alerts.vue
│   │   │   ├── Blocklist.vue
│   │   │   ├── Config.vue
│   │   │   ├── Diagnostics.vue
│   │   │   ├── Health.vue
│   │   │   ├── Logs.vue              # Live Sniffer via WebSocket
│   │   │   ├── Login.vue
│   │   │   └── Changelog.vue
│   │   ├── stores/
│   │   │   ├── auth.ts               # Pinia — JWT, role, logout
│   │   │   └── ui.ts                 # Pinia — tema, sidebar state
│   │   ├── router/
│   │   │   └── index.ts              # Vue Router + navigation guards
│   │   └── types/                    # Interfaces TypeScript da API
│   ├── public/
│   ├── package.json
│   ├── vite.config.ts
│   └── tsconfig.json
│
├── migrations/                       # SQL versionado (scripts DuckDB)
│   └── duckdb/
│       ├── V1__initial_schema.sql    # users, settings, alerts, query_logs, daily_stats
│       └── V2__add_refresh_token.sql  # refresh_token_hash, refresh_expires
│
├── scripts/                          # Scripts .sh auxiliares (mantidos)
│   ├── update_blocklist.sh
│   └── health_fix.sh
│
├── deployments/
│   ├── caddy/
│   │   └── Caddyfile
│   └── systemd/
│       ├── unbound-api.service       # uvicorn (API + workers asyncio)
│       └── unbound-api.socket        # socket activation opcional
│
├── tests/
│   ├── unit/                         # pytest — services com mocks
│   ├── integration/                  # pytest + testcontainers
│   └── e2e/                          # Playwright
│
├── docs/
│   └── api/                          # OpenAPI 3.0 (auto-gerado em /docs)
│
├── tools/
│   ├── install.sh
│   └── update.sh
│
├── pyproject.toml                    # Dependências (uv ou Poetry)
├── Makefile
└── README.md
```

---

## 5. Exemplos de Código Refatorado

### 5.1 Configuração — `app/core/config.py`

```python
# pydantic-settings valida todas as variáveis na inicialização
# Erros claros no startup, não em runtime (diferente do .env atual)
from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import AnyUrl, SecretStr

class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8")

    # DuckDB (banco único — OLTP + OLAP)
    duckdb_path: str = "/var/lib/unbound-dashboard/unbound_dash.duckdb"

    # Redis
    redis_url: AnyUrl = AnyUrl("redis://127.0.0.1:6379/0")

    # JWT
    jwt_secret: SecretStr
    jwt_algorithm: str = "HS256"
    jwt_expire_minutes: int = 60

    # Unbound
    unbound_control: str = "/usr/sbin/unbound-control"
    unbound_log: str = "/var/log/syslog"

settings = Settings()  # falha imediatamente se algum campo obrigatório faltar
```

### 5.2 Domínio — `app/domain/user.py`

```python
from __future__ import annotations
from dataclasses import dataclass, field
from datetime import datetime
from enum import StrEnum
from typing import Optional


class Role(StrEnum):
    ADMIN  = "admin"
    VIEWER = "viewer"


class DomainError(Exception):
    """Exceção base de domínio."""


class InvalidCredentials(DomainError):
    pass

class AccountLocked(DomainError):
    pass

class AccountInactive(DomainError):
    pass


@dataclass
class User:
    id: int
    username: str
    password_hash: str
    role: Role
    is_active: bool
    failed_logins: int
    locked_until: Optional[datetime] = None
    email: Optional[str] = None
    created_at: datetime = field(default_factory=datetime.utcnow)

    def is_locked(self) -> bool:
        return self.locked_until is not None and self.locked_until > datetime.utcnow()
```

### 5.3 Serviço — `app/services/auth_service.py`

```python
import asyncio
from datetime import datetime, timedelta
from passlib.context import CryptContext
from app.domain.user import User, InvalidCredentials, AccountLocked, AccountInactive
from app.repositories.duckdb.user_repo import UserRepository
from app.core.security import create_access_token

_pwd = CryptContext(schemes=["bcrypt"], deprecated="auto")

# Hash dummy para manter tempo constante e não revelar existência do usuário
_DUMMY_HASH = _pwd.hash("dummy-timing-safe")


class AuthService:
    def __init__(self, repo: UserRepository) -> None:
        self._repo = repo

    async def login(self, username: str, password: str) -> dict:
        user = await self._repo.find_by_username(username)

        if user is None:
            # Timing-safe: mesmo custo de verificação mesmo quando usuário não existe
            _pwd.verify(password, _DUMMY_HASH)
            raise InvalidCredentials

        if not user.is_active:
            raise AccountInactive

        if user.is_locked():
            raise AccountLocked

        if not _pwd.verify(password, user.password_hash):
            count = user.failed_logins + 1
            lock_until = datetime.utcnow() + timedelta(minutes=15) if count >= 5 else None
            await self._repo.update_failed_logins(user.id, count, lock_until)
            raise InvalidCredentials

        await self._repo.reset_failed_logins(user.id)
        return {
            "access_token": create_access_token({"sub": str(user.id), "role": user.role}),
            "token_type": "bearer",
            "role": user.role,
        }
```

### 5.4 Executor Seguro — `app/infrastructure/shell.py`

```python
import asyncio
from typing import Sequence

# Allowlist estrita: apenas estes binários podem ser chamados
ALLOWED = frozenset({
    "/usr/sbin/unbound-control",
    "/usr/bin/systemctl",
    "/usr/sbin/ip",
    "/usr/bin/journalctl",
    "/usr/sbin/unbound-checkconf",
})


class CommandNotAllowed(Exception):
    pass


async def run(binary: str, *args: str, timeout: float = 10.0) -> str:
    """
    Executa um binário da allowlist de forma assíncrona e segura.
    Nunca usa shell=True — cada argumento é passado diretamente ao OS.
    Equivalente ao ShellHelper::exec() do PHP, mas não-bloqueante.
    """
    if binary not in ALLOWED:
        raise CommandNotAllowed(f"binário não permitido: {binary}")

    proc = await asyncio.create_subprocess_exec(
        binary, *args,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    try:
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
    except TimeoutError:
        proc.kill()
        raise TimeoutError(f"{binary} excedeu {timeout}s")

    if proc.returncode != 0:
        raise RuntimeError(f"{binary} retornou {proc.returncode}: {stderr.decode().strip()}")

    return stdout.decode().strip()
```

### 5.5 Worker — `app/workers/log_watcher.py`

```python
import asyncio
import re
from datetime import datetime
from app.infrastructure.redis import get_pubsub_publisher
from app.repositories.duckdb.query_log_repo import QueryLogRepository

_PATTERN = re.compile(
    r"info:\s+(?P<ip>[\da-fA-F:.]+)\s+(?P<domain>[^\s]+)\.\s+"
    r"(?P<qtype>[A-Z0-9]+)\s+IN(?:\s+(?P<status>[A-Z]+))?"
)
_BATCH_INTERVAL = 5.0  # segundos entre bulk inserts no DuckDB


async def start(log_path: str = "/var/log/syslog") -> None:
    queue: asyncio.Queue = asyncio.Queue(maxsize=2000)
    repo = QueryLogRepository()
    publisher = await get_pubsub_publisher()

    async def _reader():
        proc = await asyncio.create_subprocess_exec(
            "tail", "-n", "0", "-F", log_path,
            stdout=asyncio.subprocess.PIPE,
        )
        assert proc.stdout
        async for raw_line in proc.stdout:
            line = raw_line.decode(errors="replace")
            if "unbound" not in line or "info:" not in line:
                continue
            if (entry := _parse(line)) is not None:
                await queue.put(entry)
                # Publica imediatamente para o WebSocket (Redis pub/sub)
                await publisher.publish("live-log", entry.model_dump_json())

    async def _flusher():
        while True:
            await asyncio.sleep(_BATCH_INTERVAL)
            batch = []
            while not queue.empty():
                batch.append(queue.get_nowait())
            if batch:
                await repo.bulk_insert(batch)  # 1 INSERT com N linhas → DuckDB

    await asyncio.gather(_reader(), _flusher())


def _parse(line: str):
    m = _PATTERN.search(line)
    if not m:
        return None
    status = m.group("status") or ""
    if not status:
        return None  # log de query; só processa replies com status
    from app.domain.dns_query import DNSQuery
    action = "blocked" if status in ("NXDOMAIN",) or "0.0.0.0" in line else "resolved"
    return DNSQuery(
        timestamp=int(datetime.utcnow().timestamp()),
        client_ip=m.group("ip"),
        domain=m.group("domain").lower(),
        query_type=m.group("qtype"),
        action=action,
    )
```

### 5.6 Router FastAPI — `app/routers/stats.py`

```python
from fastapi import APIRouter, Depends
from app.services.stats_service import StatsService
from app.models.stats import DashboardMetricsResponse
from app.core.deps import get_stats_service, require_auth

router = APIRouter(prefix="/api/v2/stats", tags=["stats"])


@router.get("/metrics", response_model=DashboardMetricsResponse)
async def get_metrics(
    service: StatsService = Depends(get_stats_service),
    _: dict = Depends(require_auth),  # qualquer role autenticado
) -> DashboardMetricsResponse:
    return await service.get_metrics()
# FastAPI gera automaticamente o schema OpenAPI a partir do response_model.
# Disponível em GET /docs (Swagger UI) e GET /redoc.
```

### 5.7 WebSocket — Live Log (`app/routers/ws_logs.py`)

```python
from fastapi import APIRouter, WebSocket, WebSocketDisconnect
from app.infrastructure.redis import get_redis

router = APIRouter()


@router.websocket("/ws/live-log")
async def live_log(websocket: WebSocket) -> None:
    await websocket.accept()
    redis = await get_redis()
    pubsub = redis.pubsub()
    await pubsub.subscribe("live-log")

    try:
        async for message in pubsub.listen():
            if message["type"] == "message":
                await websocket.send_text(message["data"])
    except WebSocketDisconnect:
        pass
    finally:
        await pubsub.unsubscribe("live-log")
```

### 5.8 DuckDB — Analytics de DNS (`app/repositories/duckdb/query_log_repo.py`)

```python
import asyncio
import duckdb
from typing import Sequence
from app.domain.dns_query import DNSQuery
from app.core.config import settings

# DuckDB não é thread-safe; usa executor dedicado para não bloquear o event loop
_executor = None  # ThreadPoolExecutor(max_workers=1) inicializado no startup


def _get_conn() -> duckdb.DuckDBPyConnection:
    return duckdb.connect(settings.duckdb_path)


class QueryLogRepository:

    async def bulk_insert(self, entries: Sequence[DNSQuery]) -> None:
        """Insere centenas de entradas de uma vez — muito mais eficiente que 1-por-1."""
        rows = [(e.timestamp, e.client_ip, e.domain, e.query_type, e.action) for e in entries]
        loop = asyncio.get_event_loop()
        await loop.run_in_executor(_executor, self._bulk_insert_sync, rows)

    def _bulk_insert_sync(self, rows):
        with _get_conn() as conn:
            conn.executemany(
                "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) VALUES (?, ?, ?, ?, ?)",
                rows
            )

    async def get_top_blocked_domains(self, hours: int = 24, limit: int = 20) -> list[dict]:
        """
        Query analítica que DuckDB executa em milissegundos mesmo com 16M+ linhas.
        Em MariaDB equivalente precisaria de índice composto e ainda seria lento.
        """
        cutoff = int(__import__("time").time()) - hours * 3600
        loop = asyncio.get_event_loop()
        return await loop.run_in_executor(_executor, self._top_blocked_sync, cutoff, limit)

    def _top_blocked_sync(self, cutoff: int, limit: int) -> list[dict]:
        with _get_conn() as conn:
            return conn.execute("""
                SELECT domain, COUNT(*) AS total
                FROM query_logs
                WHERE action = 'blocked' AND timestamp > ?
                GROUP BY domain
                ORDER BY total DESC
                LIMIT ?
            """, [cutoff, limit]).fetchdf().to_dict(orient="records")
```

### 5.9 Frontend Vue 3 — Composable de métricas (`web/src/api/useStats.ts`)

```typescript
import { ref, onMounted, onUnmounted } from 'vue'
import { useIntervalFn } from '@vueuse/core'
import type { DashboardMetrics } from '@/types/stats'

export function useStats() {
  const metrics = ref<DashboardMetrics | null>(null)
  const loading = ref(true)
  const error = ref<string | null>(null)

  async function fetchMetrics() {
    try {
      const res = await fetch('/api/v2/stats/metrics', {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
      })
      if (!res.ok) throw new Error(await res.text())
      metrics.value = await res.json()
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  const { pause, resume } = useIntervalFn(fetchMetrics, 10_000)
  onMounted(fetchMetrics)
  onUnmounted(pause)

  return { metrics, loading, error, refresh: fetchMetrics }
}
```

### 5.10 Frontend Vue 3 — Live Log com VueUse (`web/src/api/useLiveLog.ts`)

```typescript
import { computed } from 'vue'
import { useWebSocket } from '@vueuse/core'
import type { LogEntry } from '@/types/logs'

export function useLiveLog(maxLines = 200) {
  const wsUrl = `${location.origin.replace(/^http/, 'ws')}/ws/live-log`

  // useWebSocket inclui: reconexão automática, heartbeat, estado de conexão
  const { data, status } = useWebSocket<string>(wsUrl, {
    autoReconnect: { retries: 10, delay: 2000 },
    heartbeat: { message: 'ping', interval: 30_000 },
  })

  const entries = computed<LogEntry[]>(() => {
    if (!data.value) return []
    try {
      return [JSON.parse(data.value)]
    } catch {
      return []
    }
  })

  return { entries, status }
}
```

### 5.11 systemd unit — `deployments/systemd/unbound-api.service`

```ini
[Unit]
Description=Unbound Dashboard API (FastAPI)
Documentation=https://github.com/seu-org/unbound-dashboard
After=network.target redis.service
Wants=redis.service

[Service]
Type=exec
User=www-data
Group=www-data
WorkingDirectory=/opt/unbound-dashboard
EnvironmentFile=/opt/unbound-dashboard/.env
ExecStart=/opt/unbound-dashboard/.venv/bin/uvicorn app.main:app \
    --host 127.0.0.1 \
    --port 8000 \
    --workers 2 \
    --log-config /opt/unbound-dashboard/logging.json
ExecReload=/bin/kill -HUP $MAINPID
Restart=on-failure
RestartSec=5
# Hardening
NoNewPrivileges=yes
ProtectSystem=strict
ReadWritePaths=/var/lib/unbound-dashboard /opt/unbound-dashboard/data
PrivateTmp=yes

[Install]
WantedBy=multi-user.target
```

---

## 6. Estratégia de Migração

### 6.1 Princípio: Strangler Fig Pattern

Não reescrever tudo de uma vez. Substituir módulo por módulo enquanto o sistema v1 continua em produção.

```
Fase 1: Paralelo (v1 e v2 lado a lado)
  └─ v2 API em porta diferente (ex: :8080)
  └─ v1 PHP continua servindo em /var/www/html
  └─ Migrar módulos por prioridade (stateless primeiro)

Fase 2: Proxy gradual
  └─ Caddy roteia /api/v2/* → Python/FastAPI
  └─ Caddy roteia /* → PHP (fallback)
  └─ Frontend v2 consome API Python/FastAPI

Fase 3: Cutover
  └─ PHP desativado
  └─ Frontend Vue 3 servido como SPA pelo Caddy
  └─ v1 mantido em backup por 30 dias
```

### 6.2 Prioridade de Migração de Módulos

| Prioridade | Módulo PHP | Equivalente Python | Esforço |
|---|---|---|---|
| 1 | `Auth` + CSRF | `AuthService` + JWT (`python-jose`) | Médio |
| 2 | `StatsManager` (read-only) | `StatsService` + cache Redis | Baixo |
| 3 | `UnboundManager` (stats) | `unbound_service.py` | Médio |
| 4 | `log_ingester.php` | `workers/log_watcher.py` (asyncio task) | Médio |
| 5 | `AlertManager` | `workers/alert_checker.py` (ticker asyncio) | Médio |
| 6 | `BlocklistManager` | `blocklist_service.py` | Baixo |
| 7 | `NetworkManager` | `network_service.py` | Alto |
| 8 | `UnboundConfigManager` | `unbound_service.py` + parser | Alto |
| 9 | `DiagnosticsManager` | `diagnostics_service.py` | Alto |
| 10 | `SourceBalanceManager` | `source_balance_service.py` | Alto |

### 6.3 Migração do Banco de Dados

**Todo o dado migra para DuckDB.** MariaDB é desativado após a migração.

**Schema DuckDB único** (todas as tabelas no mesmo arquivo):

```sql
-- migrations/duckdb/V1__initial_schema.sql

-- Tabelas OLTP (escritas serializadas via ThreadPoolExecutor)
CREATE SEQUENCE IF NOT EXISTS users_id_seq START 1;
CREATE TABLE IF NOT EXISTS users (
    id                  INTEGER   PRIMARY KEY DEFAULT nextval('users_id_seq'),
    username            VARCHAR(64)  NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    role                VARCHAR(10)  NOT NULL DEFAULT 'viewer',
    email               VARCHAR(255),
    is_active           BOOLEAN   NOT NULL DEFAULT true,
    failed_logins       INTEGER   NOT NULL DEFAULT 0,
    locked_until        TIMESTAMP,
    created_at          TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS settings (
    key   VARCHAR(128) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE SEQUENCE IF NOT EXISTS alerts_id_seq START 1;
CREATE TABLE IF NOT EXISTS alerts (
    id         INTEGER   PRIMARY KEY DEFAULT nextval('alerts_id_seq'),
    type       VARCHAR(50)  NOT NULL,
    message    TEXT         NOT NULL,
    severity   VARCHAR(10)  NOT NULL DEFAULT 'info',
    is_read    BOOLEAN      NOT NULL DEFAULT false,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- Tabelas OLAP (analytics vetorizadas)
CREATE TABLE IF NOT EXISTS query_logs (
    timestamp  INTEGER      NOT NULL,
    client_ip  VARCHAR(45)  NOT NULL,
    domain     VARCHAR(255) NOT NULL,
    query_type VARCHAR(10)  NOT NULL,
    action     VARCHAR(20)  NOT NULL
);
-- DuckDB cria zonemaps automáticos por coluna — sem INDEX explícito necessário

CREATE TABLE IF NOT EXISTS daily_stats (
    date       DATE    NOT NULL PRIMARY KEY,
    total      BIGINT  NOT NULL DEFAULT 0,
    blocked    BIGINT  NOT NULL DEFAULT 0,
    resolved   BIGINT  NOT NULL DEFAULT 0,
    cache_hits BIGINT  NOT NULL DEFAULT 0
);
```

**Estratégia de escrita (fundamental):**
```python
# app/repositories/duckdb/connection.py
import duckdb
from concurrent.futures import ThreadPoolExecutor
import asyncio
from app.core.config import settings

# Executor único global — serializa TODAS as escritas
_writer = ThreadPoolExecutor(max_workers=1)
_duck_path = settings.duckdb_path

async def db_write(sql: str, params: list | None = None) -> None:
    loop = asyncio.get_event_loop()
    await loop.run_in_executor(_writer, _sync_exec, sql, params or [])

async def db_read(sql: str, params: list | None = None) -> list[dict]:
    # Leituras podem usar conexões independentes (DuckDB suporta leituras concorrentes)
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, _sync_query, sql, params or [])

def _sync_exec(sql: str, params: list) -> None:
    with duckdb.connect(_duck_path) as conn:
        conn.execute(sql, params)

def _sync_query(sql: str, params: list) -> list[dict]:
    with duckdb.connect(_duck_path, read_only=True) as conn:
        return conn.execute(sql, params).fetchdf().to_dict(orient="records")
```

**Script de migração única** para mover dados históricos do MariaDB → DuckDB:

```python
# tools/migrate_from_mariadb.py
"""
Executar UMA VEZ antes do cutover.
Pré-requisito: MariaDB rodando com dados da v1.
"""
import duckdb
import MySQLdb  # pip install mysqlclient

mysql = MySQLdb.connect(host="127.0.0.1", user="unbounddb", passwd="...", db="unbound_dash")
duck  = duckdb.connect("/var/lib/unbound-dashboard/unbound_dash.duckdb")

# Migra users
users = mysql.cursor()
users.execute("SELECT id, username, password_hash, role, email, is_active, "
              "failed_logins, locked_until, created_at FROM users")
for row in users.fetchall():
    duck.execute("INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", list(row))

# Migra settings
cursor = mysql.cursor()
cursor.execute("SELECT key, value FROM settings")
duck.executemany("INSERT OR REPLACE INTO settings VALUES (?, ?)", cursor.fetchall())

# Migra alerts
cursor.execute("SELECT id, type, message, severity, is_read, created_at FROM alerts")
duck.executemany("INSERT INTO alerts VALUES (?, ?, ?, ?, ?, ?)", cursor.fetchall())

# Migra query_logs em chunks de 100 k (evita estouro de memória com 16M+ linhas)
offset = 0
while True:
    cursor.execute("SELECT timestamp, client_ip, domain, query_type, action "
                   "FROM query_logs LIMIT 100000 OFFSET %s", [offset])
    rows = cursor.fetchall()
    if not rows:
        break
    duck.executemany("INSERT INTO query_logs VALUES (?, ?, ?, ?, ?)", rows)
    print(f"  migrado até offset {offset + len(rows)}")
    offset += 100000

# Validação pós-migração
mysql_count = mysql.cursor()
mysql_count.execute("SELECT COUNT(*) FROM query_logs")
duck_count = duck.execute("SELECT COUNT(*) FROM query_logs").fetchone()[0]
assert mysql_count.fetchone()[0] == duck_count, "CONTAGEM DIVERGENTE — não prosseguir com cutover!"
print(f"\n✓ {duck_count} linhas migradas com sucesso para DuckDB.")
```

### 6.4 Migração dos Arquivos JSON de Cache

| Arquivo atual | Substituto v2 | TTL |
|---|---|---|
| `data/latest_stats.json` | Redis key `stats:latest` | 60 s |
| `src/data/time_series.json` | Redis key `stats:timeseries` | 5 min |
| `data/stats_history.json` | Redis key `stats:history` | 30 min |
| `data/blocklist_counts.json` | Redis key `blocklist:counts` | 1 h |

### 6.5 Migração dos Shell Scripts

| Script atual | v2 equivalente | Abordagem |
|---|---|---|
| `scripts/log_ingester.php` | `app/workers/log_watcher.py` | asyncio task (sem processo separado) |
| `scripts/aggregate_stats.php` | `app/workers/stats_aggregator.py` | ticker asyncio a cada 60 s |
| `scripts/cron_alerts.php` | `app/workers/alert_checker.py` | ticker asyncio a cada 30 s |
| `api/scripts/internet-test.sh` | `app/infrastructure/system.py` | `asyncio.create_subprocess_exec` |
| `api/scripts/update_root_hints.sh` | Mantido como `.sh` | chamado via `shell.run()` |
| `tools/install.sh` | `tools/install.sh` (atualizado para Python) | Mantido como `.sh` |
| `tools/update.sh` | `tools/update.sh` (atualizado) | Mantido como `.sh` |

---

## 7. Plano de Testes e CI/CD

### 7.1 Pirâmide de Testes

```
           /\
          /  \
         / E2E \       Playwright — fluxos críticos (login, dashboard, blocklist)
        /--------\
       /Integration\   pytest + testcontainers-python (Redis real; DuckDB usa arquivo temporário)
      /------------  \
     /   Unit Tests   \ pytest — services com mocks (unittest.mock / pytest-mock)
    /------------------\
```

### 7.2 Testes Unitários — Exemplo Python

```python
# tests/unit/test_auth_service.py
import pytest
from unittest.mock import AsyncMock
from passlib.context import CryptContext
from app.services.auth_service import AuthService
from app.domain.user import User, Role, InvalidCredentials

_pwd = CryptContext(schemes=["bcrypt"])


@pytest.fixture
def user_repo():
    return AsyncMock()


@pytest.mark.asyncio
async def test_login_wrong_password_increments_failed_count(user_repo):
    user_repo.find_by_username.return_value = User(
        id=1, username="admin",
        password_hash=_pwd.hash("correct"),
        role=Role.ADMIN, is_active=True, failed_logins=0,
    )
    svc = AuthService(user_repo)

    with pytest.raises(InvalidCredentials):
        await svc.login("admin", "wrong-password")

    user_repo.update_failed_logins.assert_called_once_with(1, 1, None)


@pytest.mark.asyncio
async def test_login_5_failures_locks_account(user_repo):
    user_repo.find_by_username.return_value = User(
        id=1, username="admin",
        password_hash=_pwd.hash("correct"),
        role=Role.ADMIN, is_active=True, failed_logins=4,  # já falhou 4 vezes
    )
    svc = AuthService(user_repo)

    with pytest.raises(InvalidCredentials):
        await svc.login("admin", "wrong")

    _, _, lock_until = user_repo.update_failed_logins.call_args.args
    assert lock_until is not None  # conta deve ser bloqueada
```

### 7.3 Testes de Integração — Exemplo Python

```python
# tests/integration/test_query_log_repo.py
import pytest
import duckdb
from app.repositories.duckdb.query_log_repo import QueryLogRepository
from app.domain.dns_query import DNSQuery


@pytest.fixture
def duck_conn(tmp_path):
    """Cria um DuckDB temporário para cada teste."""
    conn = duckdb.connect(str(tmp_path / "test.duckdb"))
    conn.execute("""
        CREATE TABLE query_logs (
            timestamp INTEGER, client_ip VARCHAR, domain VARCHAR,
            query_type VARCHAR, action VARCHAR
        )
    """)
    return conn


@pytest.mark.asyncio
async def test_bulk_insert_and_query(duck_conn):
    repo = QueryLogRepository(duck_conn)
    entries = [
        DNSQuery(timestamp=1000, client_ip="10.0.0.1", domain="ads.example.com",
                 query_type="A", action="blocked"),
        DNSQuery(timestamp=1001, client_ip="10.0.0.1", domain="safe.example.com",
                 query_type="A", action="resolved"),
    ]
    await repo.bulk_insert(entries)

    top = await repo.get_top_blocked_domains(hours=24)
    assert top[0]["domain"] == "ads.example.com"
    assert top[0]["total"] == 1
```

### 7.4 Testes E2E — Playwright

```typescript
// tests/e2e/login.spec.ts
test('login com credenciais válidas redireciona para dashboard', async ({ page }) => {
  await page.goto('/login');
  await page.fill('[name=username]', 'admin');
  await page.fill('[name=password]', process.env.TEST_PASSWORD!);
  await page.click('[type=submit]');
  await expect(page).toHaveURL('/dashboard');
  await expect(page.getByTestId('stats-card-qps')).toBeVisible();
});

test('5 tentativas inválidas bloqueiam a conta', async ({ page }) => {
  for (let i = 0; i < 5; i++) {
    await loginAttempt(page, 'admin', 'wrong');
  }
  await expect(page.getByRole('alert')).toContainText('bloqueada');
});
```

### 7.5 Pipeline CI/CD (GitHub Actions)

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    services:
      redis:
        image: redis:7-alpine
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with: { python-version: '3.12' }
      - name: Install uv
        run: pip install uv
      - name: Install deps
        run: uv sync --dev
      - name: Lint
        run: uv run ruff check app tests
      - name: Type check
        run: uv run mypy app
      - name: Tests
        run: uv run pytest --cov=app --cov-report=term-missing
        env:
          JWT_SECRET: ci-secret-not-for-prod

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm' }
      - run: npm ci
        working-directory: web
      - run: npm run typecheck
        working-directory: web
      - run: npm run test
        working-directory: web
      - run: npm run build
        working-directory: web

  e2e:
    needs: [backend, frontend]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker compose -f deployments/docker-compose.test.yml up -d
      - run: npx playwright install --with-deps chromium
      - run: npx playwright test

  release:
    needs: [e2e]
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Build frontend
        run: make build-web
      - name: Build package
        run: make build-package
      - uses: softprops/action-gh-release@v2
        with: { files: 'dist/*.tar.gz' }
```

### 7.6 Makefile de Desenvolvimento

```makefile
.PHONY: dev test lint typecheck build-web migrate-up

dev:           ## Sobe API com hot reload (uvicorn --reload) + frontend Vite
	@uv run uvicorn app.main:app --reload --port 8000 &
	@cd web && npm run dev

test:          ## Executa todos os testes (unit + integration)
	uv run pytest --cov=app --cov-report=term-missing
	cd web && npm run test

lint:          ## Linting Python (ruff) + TypeScript (eslint)
	uv run ruff check app tests
	cd web && npm run lint

typecheck:     ## Checagem de tipos
	uv run mypy app
	cd web && npm run typecheck

build-web:     ## Build de produção do frontend
	cd web && npm run build

migrate-up:    ## Executa migrations DuckDB pendentes
	uv run python -m app.db.migrate
```

---

## 8. Monitoramento e Manutenção

### 8.1 Observabilidade da Aplicação

| Camada | Ferramenta | O que monitora |
|---|---|---|
| Métricas | **Prometheus** + `prometheus-fastapi-instrumentator` | Req/s, latência P99, erros, workers ativos |
| Dashboards | **Grafana** (conecta ao Prometheus) | Painéis de saúde do sistema e da API |
| Logs estruturados | **structlog** → **Loki** | JSON logs pesquisáveis |
| Rastreamento | **OpenTelemetry** → **Jaeger** (opcional) | Trace de requests lentos |
| Alertas | **Alertmanager** (Prometheus) | CPU, memória, serviço down |
| Uptime externo | **UptimeKuma** (auto-hospedado) | Disponibilidade da API |

### 8.2 Métricas Expostas pela API Python

```python
# Adicionado automaticamente pelo prometheus-fastapi-instrumentator
# Métricas customizadas:
from prometheus_client import Counter, Gauge

dns_queries_ingested = Counter(
    "unbound_queries_ingested_total",
    "Total de queries DNS ingeridas pelo log watcher"
)

cache_hit_ratio = Gauge(
    "unbound_cache_hit_ratio",
    "Proporção de cache hits do Unbound (0.0–1.0)"
)
```

### 8.3 Health Endpoint (FastAPI nativo)

```
GET /healthz
→ 200 { "status": "ok",       "db": "ok",    "redis": "ok", "unbound": "ok"    }
→ 503 { "status": "degraded", "db": "ok",    "redis": "error", "unbound": "ok" }
```

### 8.4 Dependências Python (`pyproject.toml`)

```toml
[project]
name = "unbound-dashboard"
requires-python = ">=3.12"
dependencies = [
    "fastapi>=0.111",           # Framework web + WebSocket
    "uvicorn[standard]>=0.29",  # ASGI server
    "pydantic>=2.7",            # Validação e serialização
    "pydantic-settings>=2.2",   # Config via env/.env
    "duckdb>=1.0",              # Banco único — OLTP serializado + OLAP analytics
    "redis[hiredis]>=5.0",      # Cliente Redis async
    "python-jose[cryptography]>=3.3", # JWT
    "passlib[bcrypt]>=1.7",     # Hash de senhas
    "psutil>=5.9",              # CPU, RAM, Disco, Rede
    "slowapi>=0.1",             # Rate limiting
    "structlog>=24.1",          # Logging estruturado
    "prometheus-fastapi-instrumentator>=7.0", # Métricas Prometheus
]

[dependency-groups]
dev = [
    "pytest>=8",
    "pytest-asyncio>=0.23",
    "pytest-cov>=5",
    "pytest-mock>=3.14",
    "httpx>=0.27",              # Cliente HTTP para testes FastAPI
    "ruff>=0.4",                # Linter + formatter
    "mypy>=1.10",               # Type checker
    "testcontainers[redis]>=4",
]
```

### 8.5 Ferramentas de Desenvolvimento

| Ferramenta | Uso |
|---|---|
| `uv` | Gerenciador de pacotes Python moderno e rápido |
| `ruff` | Linter + formatter (substitui flake8 + black + isort) |
| `mypy` | Checagem de tipos estática |
| `pytest-asyncio` | Testes de código asyncio |
| `testcontainers-python` | Redis real em testes de integração (DuckDB usa arquivo temporário) |
| `Playwright` | Testes E2E do frontend |
| Scripts SQL versionados | Migrations DuckDB (`migrations/duckdb/V*.sql`) |
| `structlog` | Logs JSON estruturados, integra com journald |

---

## 9. Cronograma Sugerido

```
Semana 1-2:  Fundação
  ├─ Setup do repositório, CI, Makefile
  ├─ pydantic-settings (config validada)
  ├─ Conexão DuckDB + Redis
  ├─ Migrations iniciais (scripts SQL DuckDB — todas as tabelas)
  └─ Auth (login, JWT, RBAC dependency)

Semana 3-4:  Core API (read-only)
  ├─ StatsService + cache Redis
  ├─ UnboundService (stats, versão)
  ├─ AlertsRouter (listagem)
  └─ Frontend base (Vite + Vue Router + layout)

Semana 5-6:  Streaming + Workers asyncio
  ├─ LogWatcher task + WebSocket hub (Redis pub/sub)
  ├─ StatsAggregator ticker
  ├─ AlertChecker ticker
  └─ Dashboard Vue com métricas reais

Semana 7-8:  Operações de escrita
  ├─ Parser/writer de unbound.conf
  ├─ NetworkService (interfaces, IPv6)
  ├─ BlocklistService (sync)
  └─ HealthCheck + DiagnosticsService

Semana 9-10: Testes + Migração de dados
  ├─ Cobertura de testes ≥ 70%
  ├─ Script migrate_to_duckdb.py
  ├─ Testes E2E Playwright
  └─ Deploy paralelo (v1 + v2 na porta 8000)

Semana 11-12: Cutover + Documentação
  ├─ Cutover (PHP + Apache desativados; Caddy assume)
  ├─ OpenAPI 3.0 publicado em /docs
  └─ Documentação para desenvolvedores
```

---

## 10. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| DuckDB com writes concorrentes (se log rate > 10k/s) | Baixa | Médio | Buffer na asyncio.Queue + batch insert a cada 5 s; monitorar tamanho da queue |
| DuckDB como banco único (SPOF em caso de corrupção) | Muito baixa | Alto | WAL habilitado; backup diário automático por `cp`; manter backup do MariaDB por 30 dias após cutover |
| Parser de `unbound.conf` com edge cases | Alta | Alto | Testes com corpus de configs reais; fallback para arquivo original em erro |
| Perda de dados históricos na migração | Baixa | Crítico | Backup completo do MariaDB antes; validação por checksum de contagem; não desativar MariaDB antes de confirmar paridade |
| Regressão no `NetworkManager` (IPv6) | Média | Alto | VM isolada para testes; mocks de sistema; review obrigatório |
| Incompatibilidade de WebSocket com proxy | Baixa | Médio | Caddy suporta WS nativo; testar com `wscat` antes do deploy |
| Redis como SPOF | Baixa | Médio | Fallback para consulta direta ao DuckDB se Redis cair; Redis com AOF |
| GIL Python em processamento intensivo | Muito baixa | Baixo | Workers são I/O-bound (asyncio os contorna); DuckDB roda em thread pool separado |

---

## Próximos Passos

Antes de iniciar a implementação:

1. **Aprovação da stack** — confirmar Python/FastAPI + **DuckDB-only** + Vue 3
2. **Ambiente de desenvolvimento** — Python 3.12 + uv + Node 20 disponíveis no servidor de dev
3. **Priorização de módulos** — confirmar a ordem na seção 6.2
4. **Decisão sobre SourceBalanceManager** — módulo complexo; avaliar se entra na v2 ou é simplificado
5. **Orçamento de tempo** — 12 semanas para 1 desenvolvedor dedicado

> Após aprovação, o próximo entregável será o repositório com estrutura base, CI funcionando, Auth implementada e DuckDB schema criado (todas as tabelas).
