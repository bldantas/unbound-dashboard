# Unbound Dashboard - Documentação do Sistema

O **Unbound Dashboard** é uma aplicação web para gerenciar, monitorar e configurar o servidor DNS Unbound, com ferramentas completas para administração do sistema operacional subjacente.

A partir da v2.2.0 (2026-05-04), o sistema é híbrido: frontend PHP servindo as páginas + backend Python/FastAPI para dados, persistência (DuckDB) e workers assíncronos. MariaDB foi removido. Apache faz reverse proxy de `/api/v1/*` para o FastAPI em `127.0.0.1:8001`.

---

## 🏗 Estrutura de Diretórios

```
.
├── api/             # Endpoints PHP legados (transição → FastAPI)
├── api_service/     # FastAPI app: routers, services, repositories, workers, migrations
│   ├── app/
│   │   ├── core/        # config, security (JWT), deps, rate_limit, metrics
│   │   ├── routers/     # auth, alerts, blocklist, exports, health, history, stats, threats, unbound, users
│   │   ├── services/    # auth, history, stats, threats, unbound_stats, users
│   │   ├── repositories/duckdb/  # acesso DuckDB (connection.py + N repos)
│   │   ├── workers/     # log_watcher, stats_aggregator, alert_checker, json_exporter
│   │   ├── infrastructure/  # shell, system_health, unbound
│   │   └── db/          # runner de migrations
│   ├── migrations/duckdb/   # V*__*.sql
│   ├── deployments/         # systemd, apache, env example
│   ├── tools/               # create_admin.py, migrate_mariadb_to_duckdb.py (one-time)
│   └── tests/               # 60+ testes (pytest + pytest-asyncio)
├── docs/            # Documentação de componentes, APIs e páginas
├── includes/        # Partials HTML (sidebar, topbar, head, footer)
├── scripts/         # update_blacklist.php (chama api_service via ApiClient)
├── src/             # Classes PHP: Auth, ApiClient, Managers, Monitors
├── tools/           # build-package.sh, install.sh, update.sh, build-update.sh
├── data/            # Cache de runtime, snapshots JSON (gitignored)
└── *.php            # Páginas: index, config, history, alerts, threats, blocklist, etc.
```

**Banco DuckDB**: arquivo único em `/var/lib/unbound-dashboard/unbound_dash.duckdb` (owned by `www-data`).

**EnvironmentFile**: `/etc/unbound-dashboard/api-v1.env` (chmod 640, owner `root:www-data`) — contém `JWT_SECRET`, `DB_PATH`, `REDIS_URL`, etc.

**Backups**: `/var/backups/unbound-dashboard/` — gerados pelo `update.sh` antes de cada update.

---

## ⚙️ Componentes Principais (`/src/`)

A lógica nuclear da aplicação reside nos módulos em `/src/`:

1. **`UnboundConfigManager`** e **`UnboundManager`**:
   - Responsáveis diretos pela leitura, reescrita e aplicação de configurações nos arquivos do Unbound (ex: `unbound.conf`).
2. **`NetworkManager`**:
   - Gerenciamento de configurações sensíveis do servidor, incluindo interfaces de rede, IPs, e o sincronismo de **NTP/timezone**.
3. **`SourceBalanceManager`**:
   - Módulo responsável pelo gerenciamento de políticas de roteamento ou controle de fontes (source balance).
4. **`ServerMonitor`**, **`SecurityMonitor`**, **`AppMetricsManager`** e **`SystemCheckManager`**:
   - Monitoramento integrado contínuo da saúde do hardware, tráfego na rede, métricas do Unbound, uptime e detecção de riscos/ameaças.
   - Após o tear-down do MariaDB, `AppMetricsManager.getMariaDBStats()` retorna stub `offline` permanente; o widget correspondente em `alerts.php` será substituído por status do `api_service`/DuckDB em revisão futura.
5. **`AlertManager`**:
   - Agrega falhas, percalços nos processos ou alertas de alta prioridade gerados pelos módulos de monitoramento (ex: CPU alta, memória excedida, serviço parado).
6. **`DiagnosticsManager`**:
   - Facilita relatórios detalhados com informações e troubleshooting caso algo de errado ocorra.
7. **`BlocklistManager`**:
   - Gerencia as fontes de listas de bloqueio (ex: StevenBlack, Hagezi) e integrações com o Anablock/ANATEL.

---

## 🎨 Frontend (Telas) e Funcionalidades

As principais views disponíveis para o usuário acessar a partir dos menus do sistema:

- **Dashboard Principal (`index.php`)**: Exibe estatísticas gerais e visão panorâmica da resolução de nomes (DNS). O card de latência exibe a **Latência Efetiva** (ponderada pela taxa de cache), com detalhes de **Recursão** (tempo bruto de ida ao upstream) e **Mediana** como subtítulos.
- **Configuração (`config.php`)**: Interface principal para ditar regras, encaminhamentos e gerir de fato os arquivos do DNS.
- **Painel de Saúde (`health.php`) e Alertas (`alerts.php`)**: Visualização limpa e premium do estado atual da máquina (CPU, RAM, Disco) e pendências de estabilidade.
- **Diagnostics (`diagnostics.php`)**, **Logs (`logs.php`)** e **History (`history.php`)**: Ferramentas cruciais para depurar requisições em tempo real e consultar o histórico de resolução/bloqueios (incluindo recursos estendidos de delimitação dinâmica e grids ajustados para visualização do fluxo em full-width). A aba **Live Sniffer** em Logs intercepta pacotes DNS em tempo real via polling do `api/live_log.php`, diferenciando visualmente consultas `[QUERY]` de respostas `[REPLY]` com cores distintas.
- **Central de Exportação (`exports.php`)**: Página dedicada para download de dados do sistema. Oferece 5 tipos de exportação: Consultas DNS (CSV), Relatório de Estatísticas (JSON), Log do Sistema (TXT), Backup de Configurações (TAR.GZ) e Lista de Bloqueios (CSV). Todos os downloads são gerados sob demanda via `api/export.php`.
- **Recover (`recover.php`)**: Redefinição de senha via token enviado por email. Configuração inicial não tem mais wizard — é feita pelo `install.sh` (que cria o admin via `api_service/tools/create_admin.py`). Acesso ao sistema antes da instalação retorna 503 em `not_installed.php`.
- **DNS Benchmark (`dns_benchmark.php`) e Threats (`threats.php`)**: Avaliação da latência global e listas de acesso/firewall ou proteção contra ameaças de malware.
- **Lista Judicial ANATEL (`blocklist.php`)**: Interface de consulta avançada para domínios bloqueados por ordem judicial (Anablock). Permite busca por palavras-chave, filtragem por TLD (top-level domain) e visualização de estatísticas da base de dados judicial.

---

## 🌓 Sistema de Temas e Interface (Modo TV)

O dashboard conta com um sistema de interface moderno e adaptável:

1. **Temas Dinâmicos**: Suporte a modos **Claro**, **Escuro** e **Padrão do Sistema**, alternáveis via ícone Sol/Lua na topbar. A preferência é persistida no `localStorage` do navegador.
2. **Sidebar Colapsável**: O menu lateral pode ser ocultado completamente para maximizar a área de visualização, ideal para exibição em TVs ou monitores de monitoramento (*NOC*). O estado é persistido via `localStorage`.
3. **Topbar com Status do Serviço**: A barra superior exibe em tempo real o status do daemon Unbound (Online/Offline) com indicador pulsante, o tempo de uptime, seletor de tema e informações do usuário logado.
4. **Design Responsivo**: Layout otimizado com Grid e Flexbox (Tailwind CSS) para diferentes resoluções.

### Arquitetura de Componentes de UI

| Componente | Arquivo | Função |
|---|---|---|
| Head | `includes/head.php` | Carrega Tailwind CDN com `darkMode: 'class'`, aplica tema antes da renderização (prevenção de FOUC), carrega fontes e CSS |
| Topbar | `includes/topbar.php` | Barra superior sticky com toggle de sidebar, status do serviço/uptime, seletor de tema e perfil do usuário |
| Sidebar | `includes/sidebar.php` | Menu lateral com navegação, badge de alertas e ação de logout |
| Footer | `includes/footer.php` | Rodapé padrão |
| CSS | `src/dashboard.css` | Variáveis CSS dinâmicas (`:root` / `html.dark`), estilos glassmorphism, tabelas, formulários e animações |

### Detalhes Técnicos do Sistema de Temas

- **Configuração do Tailwind**: O `tailwind.config` com `darkMode: 'class'` é declarado **após** o carregamento do CDN para que o Tailwind respeite a classe `.dark` no elemento `<html>` em vez de usar `prefers-color-scheme` do sistema operacional.
- **Variáveis CSS**: O arquivo `dashboard.css` define variáveis em `:root` (tema claro) e `html.dark` (tema escuro) que controlam fundos, bordas, sombras e textos globalmente.
- **Cores de texto**: Todos os textos de conteúdo usam o padrão `text-slate-900 dark:text-white` ou variantes coloridas com `dark:` prefix para garantir legibilidade em ambos os modos.
- **Gráficos (Chart.js)**: As cores de labels, grids e legendas dos gráficos são configuradas dinamicamente via `document.documentElement.classList.contains('dark')`.
- **Páginas migradas**: `index.php`, `config.php`, `history.php`, `alerts.php`, `logs.php`, `threats.php`, `diagnostics.php`, `health.php`, `dns_benchmark.php`, `exports.php`.
---

## 👥 Controle de Acesso por Roles (RBAC)

O sistema implementa controle de acesso baseado em papéis (Role-Based Access Control) com dois perfis:

### Papéis Disponíveis

| Papel | Descrição |
|---|---|
| **admin** | Acesso total a todas as funcionalidades, configurações e ferramentas do sistema |
| **viewer** | Acesso limitado somente a visualização do Dashboard e Histórico |

### Matriz de Permissões por Página

| Página | Admin | Viewer | Proteção Backend |
|---|---|---|---|
| `index.php` (Dashboard) | ✅ | ✅ | `Auth::check()` |
| `history.php` (Histórico) | ✅ | ✅ | `Auth::check()` |
| `logs.php` (Logs) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `alerts.php` (Alertas) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `threats.php` (Ameaças) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `config.php` (Configurações) | ✅ | ❌* | `Auth::isAdmin()` (exceto tab perfil) |
| `diagnostics.php` (Diagnóstico) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `health.php` (Saúde) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `dns_benchmark.php` (Benchmark) | ✅ | ❌ | `Auth::isAdmin()` com redirect |
| `exports.php` (Exportações) | ✅ | ❌ | `Auth::isAdmin()` com redirect |

> \* Viewers podem acessar `config.php` apenas na aba **Perfil** para alterar email e senha.

### Implementação

- **Sidebar (`includes/sidebar.php`)**: O menu lateral é renderizado condicionalmente via `$sidebarIsAdmin = \App\Auth::isAdmin()`. Para viewers, apenas os links de **Dashboard** e **Histórico** são exibidos. As seções **Ferramentas** e **Sistema** ficam completamente ocultas.
- **Proteção Backend**: Cada página restrita verifica `Auth::isAdmin()` no topo do script PHP e redireciona visitantes não-autorizados para `index.php`.
- **Classe `Auth`** (`src/Auth.php`): Gerencia login, sessão, roles, recuperação de senha e CRUD de usuários. O papel é armazenado em `$_SESSION['role']`.

---

## 🔒 Segurança e Práticas do Sistema

Muitas das funções do *Unbound Dashboard* exigem privilégios elevados (`root`). Para contornar e prover acesso seguro ao servidor da web (`www-data` no Apache), o sistema faz forte uso de:
- Regras minuciosas de escalonamento registradas em `/etc/sudoers.d/unbound-dashboard` para que o painel consiga executar reinícios de serviços e ajustes de permissão de maneira isolada.
- Operações de `I/O` em arquivos de configuração que recorrem à pasta `/var/www/html/unbound-dashboard/data/` em substituição ao uso direto da partição `/tmp/`, para evitar bloqueios de diretórios isolados decorrentes da política padrão do `PrivateTmp=true` vigente no Apache.
- **Proteção Antifraude e CSRF**: Implementação de barreira estrutural para bloqueio de vulnerabilidades CSRF em rotas administrativas (ex: `config.php`). A classe `Auth` forja e exige tokens dinâmicos encriptados por sessão para prever acessos indevidos e injeção de reconfigurações advindas de abas terceiras, validando todo envio `POST` antes de acionar executáveis do sistema.
- **Sudoers e comandos exatos**: Os comandos `exec()` no PHP devem usar parâmetros **idênticos** aos registrados em `/etc/sudoers.d/unbound-dashboard`. Por exemplo, `tail -n 300` e `journalctl -n 300 --no-pager` são as formas autorizadas — qualquer variação (como `-n 40`) será rejeitada silenciosamente pelo `sudo`.

---

## 📊 Cálculo de Métricas do Dashboard

### Latência Efetiva (Ponderada)

O Unbound reporta `total.recursion.time.avg` como o tempo médio de resolução **apenas para consultas recursivas** (cache miss). Consultas servidas do cache têm latência ≈ 0ms e não entram nessa estatística.

Para evitar exibir um valor inflado, o sistema calcula a **latência efetiva ponderada**:

```
latência_efetiva = latência_recursão × taxa_de_miss
```

**Exemplo real**: Com latência de recursão de 177ms e cache hit de 55%:
- Exibição antiga (incorreta): `177.3 ms`
- Exibição atual (correta): `177.3 × 0.45 = 80.0 ms`

O card de latência no dashboard exibe:
- **Valor principal**: Latência Efetiva (ponderada)
- **Subtítulo 1**: Recursão (tempo bruto do upstream, em âmbar)
- **Subtítulo 2**: Mediana (mediana da recursão, em verde)

### Live Sniffer (`api/live_log.php`)

O Live Sniffer captura pacotes DNS em tempo real do Unbound via `journalctl` ou `/var/log/unbound.log`. As regex de parsing diferenciam dois tipos de linha:

| Tipo | Formato Unbound | Regex |
|---|---|---|
| **Query** | `info: IP DOMAIN QTYPE IN` (linha termina após IN) | `/info:\s+IP\s+DOMAIN\s+QTYPE\s+IN\s*$/` |
| **Reply** | `info: IP DOMAIN QTYPE IN RCODE TIME FLAGS TTL` | `/info:\s+IP\s+DOMAIN\s+QTYPE\s+IN\s+RCODE\s+TIME/` |

> **Nota**: O Unbound **não** usa a palavra `reply` nas linhas de log (diferente do dnsmasq). A distinção é feita pela presença de campos extras (RCODE, tempo) após `IN`.

---

## 📝 Histórico de Correções

### [2026-05-04] v2.2.0 — Tear-down do MariaDB

**Marco**: sistema migrado para 100% DuckDB. Modernização v1 in-place concluída.

**Alterações**:
- Todos os 6 Managers PHP (`Blocklist`, `SourceBalance`, `Alert`, `Unbound`, `UnboundConfig`, `SystemCheck`) migrados para `App\ApiClient` (cURL → FastAPI).
- `src/Database.php` neutralizado (stub que lança `PDOException` em vez de `die()`).
- `AppMetricsManager` desacoplado do PDO; `getMariaDBStats()` retorna `offline` fixo.
- `scripts/update_blacklist.php` reescrito sobre `ApiClient` + JWT via env var.
- `health.php`: card MariaDB removido, novos checks de status systemd (`unbound-dashboard-api`, Redis, Apache, Unbound) e novos componentes (DuckDB, env file, backups dir).
- `history.php`: removida dependência incondicional de `Database::getInstance()` que travava o loader.
- `dns_benchmark.php`: 3 rounds com modal "Teste X de 3" e agregação cliente-side.
- `tools/build-package.sh`, `install.sh`, `update.sh`, `build-update.sh` reescritos para a stack DuckDB+FastAPI+Redis.
- `api_service/tools/create_admin.py` adicionado para bootstrap idempotente do admin no install.

### [2026-04-29] v2.1.0 — Modernização in-place (Strangler Fig)

**Backend**: `api_service/` (FastAPI/DuckDB/Redis) deployado em paralelo ao PHP+MariaDB. Workers asyncio (LogWatcher, StatsAggregator, AlertChecker, JsonExporter), métricas Prometheus em `/metrics`, rate limiting (slowapi), CORS, JWT (HS256) compartilhado entre PHP e FastAPI via sessão.

**Auth**: `src/ApiClient.php` (cURL) com `get/post/put/delete/login/changePassword`. Login PHP delega para `/api/v1/auth/login` e guarda `api_jwt` em `$_SESSION`.

**Páginas migradas**: Dashboard, Threats, History, Alerts, Blocklist, Config, Diagnostics, Service Control, Export.

### [2026-04-04] Live Sniffer — Correção de Regex e Permissões

**Problema**: O Live Sniffer ficava travado em "Inicializando interceptador de pacotes..." sem exibir dados.

**Causas identificadas**:
1. **Sudoers**: A API usava `tail -n 40` e `journalctl -n 40`, mas o sudoers só autoriza `-n 300`. O `sudo` rejeitava silenciosamente.
2. **Regex incorreta**: A regex de reply procurava `info: reply ...` (formato dnsmasq), mas o Unbound não usa essa palavra. A regex de query casava com todas as linhas (queries e replies), gerando dados duplicados.

**Correções aplicadas**:
- `api/live_log.php`: Parâmetros alterados para `-n 300` (alinhado ao sudoers). Regex reescritas para formato real do Unbound.
- `logs.php`: Template de reply atualizado para incluir IP do cliente.

### [2026-04-04] Latência Média — Cálculo Ponderado

**Problema**: O dashboard exibia ~177ms como "Latência Média", dando a impressão de resolução lenta, quando na verdade esse é o tempo apenas de consultas recursivas (cache miss).

**Correções aplicadas**:
- `scripts/aggregate_stats.php`: Novo cálculo de latência efetiva ponderada pela taxa de cache miss. Campo `latency_recursion` adicionado ao JSON de cache.
- `index.php`: Card renomeado para "Latência Efetiva" com subtítulos de Recursão e Mediana.
- `src/StatsManager.php`: Default do novo campo `latency_recursion` adicionado.

### [2026-04-04] Central de Exportação & Backup — Nova Funcionalidade

**Objetivo**: Permitir que administradores exportem dados do sistema para análise externa ou backup.

**Arquivos criados**:
- `exports.php`: Página visual com 5 cards de exportação + modal de restauração (design glassmorphism com cores únicas por tipo)
- `api/export.php`: API backend que gera e transmite os arquivos sob demanda (GET) e processa restauração de backup (POST)

**Tipos de exportação disponíveis**:

| Tipo | Formato | Fonte | Descrição |
|---|---|---|---|
| Consultas DNS | CSV (`;`) | `query_logs` (DuckDB via `/api/v1/exports/query-logs`) | Histórico com IP, domínio, tipo e ação. Filtro por período (24h/7d/30d/tudo) |
| Estatísticas | JSON | `latest_stats.json` + `daily_stats` (DuckDB via `/api/v1/exports/stats-report`) | Métricas atuais, histórico diário, top domínios e top clientes |
| Log do Sistema | TXT | `journalctl` + `syslog` | Últimas 300 linhas do daemon Unbound e do syslog |
| Backup Config | TAR.GZ | `/etc/unbound/` | Configs modulares + instâncias multicore + settings do dashboard |
| Blacklist | CSV (`;`) | `blocklist_domains` (DuckDB via `/api/v1/exports/blocklist`) | Lista completa de domínios bloqueados com categoria |

**Restauração de Backup (Backup Config)**:
- O card de Backup inclui dois botões: **Exportar** e **Restaurar**
- O botão Restaurar abre um modal com drag & drop para upload do `.tar.gz`
- Aviso de confirmação explícito sobre sobrescrita de configurações
- Fluxo de restauração:
  1. Valida formato (`.tar.gz`, máx. 5MB) e conteúdo (apenas `.conf` e `.json`)
  2. Extrai em diretório temporário (`src/data/tmp/`)
  3. Copia cada `.conf` para `/etc/unbound/` via `sudo cp` (staging area)
  4. Restaura settings do dashboard no DuckDB via `/api/v1/exports/settings/bulk`
  5. Valida config com `unbound-checkconf`
  6. Se válido, reinicia o Unbound automaticamente
  7. Limpa todos os arquivos temporários
- Três estados de retorno: **success** (verde), **warning** (validação falhou, âmbar), **error** (vermelho)

**Detalhes técnicos**:
- CSV usa BOM UTF-8 para compatibilidade com Excel
- Backup exclui `blocked_domains.conf` (auto-gerado, 2.3MB) e certificados/chaves TLS
- Downloads são via streaming direto (`php://output`), sem criar arquivos temporários (exceto TAR.GZ)
- Link adicionado na sidebar sob seção **Ferramentas**

### [2026-04-09] Busca Judicial ANATEL & Refatoração de Gestão

**Objetivo**: Implementar consulta local à base de dados Anablock/ANATEL e corrigir redundâncias na UI.

**Melhorias aplicadas**:
- **`blocklist.php`**: Nova interface premium com busca em tempo real (debounce de 350ms), filtros dinâmicos de extensões (.bet, .com, etc) e paginação assíncrona via `api/blocklist_search.php`.
- **Gestão de Usuários**: Consolidação da interface de criação e edição de usuários. A seção de gerenciamento agora é exclusiva da aba "Gestão de Usuários" para administradores, evitando a poluição visual em outras sub-abas de configuração.
- **`config.php`**: Adicionado suporte para troca dinâmica de fontes de blacklist (StevenBlack vs Hagezi).

### [2026-04-09] Upgrade do Script de Auto-Reparo (Health Fix)

**Objetivo**: Tornar o script `/usr/local/bin/unbound-health-fix.sh` capaz de realizar uma auditoria completa e proativa no sistema.

**Funcionalidades adicionadas**:
- **Auditoria de Permissões**: Verificação recursiva e correção automática de `owner` e `chmod` nos diretórios `/etc/unbound` e `/data`.
- **Integridade DNSSEC**: Verificação e regeneração automática de chaves DNSSEC caso detectada corrupção.
- **Validação de TLS**: Checagem de validade e existência de certificados TLS configurados para DoT/DoH.
- **Logs de Auditoria**: Registro detalhado de todas as ações de reparo em `/var/log/unbound-health-audit.log`.
- **Integração com Dashboard**: O script agora pode ser acionado via API de diagnóstico para reparos rápidos em um clique.

