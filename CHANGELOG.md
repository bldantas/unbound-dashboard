# Changelog

## v2.7.1 — 2026-05-12

### Fix: update.sh deixava Apache servindo .php cru

Servidor de teste atualizado mostrou o PHP literal no browser em vez do
HTML renderizado. Causa: `update.sh` só copiava arquivos — **não fazia
setup de PHP-FPM**. Em servidor com instalação pré-2.2.10 (que usava
`libapache2-mod-php`), o update não habilitava o novo handler nem
desabilitava o legado.

**Fix em `tools/update.sh`:**

Bloco novo de setup PHP-FPM idempotente:

- Detecta `phpX.Y-fpm.service` via `systemctl list-unit-files`. Se
  ausente, instala `php-fpm` via apt.
- Habilita módulos `proxy_fcgi setenvif proxy proxy_http`.
- Desabilita `mod_php` legado (`a2dismod phpX.Y`) se ainda ativo.
- Roda `a2enconf phpX.Y-fpm` + `systemctl enable --now` do serviço.
- Tudo silencioso quando já configurado (sem ruído em updates futuros).

**Fix em `tools/install.sh`: smoke test pós-setup**

Mesmo o install.sh já fazendo todo o setup, falhas silenciosas no
`a2enconf` ou `apt install` (cobertas com `\|\| warn`) deixavam servidor
quebrado sem aviso. Agora o install termina com smoke test real:

1. Escreve `/var/www/html/.smoke-php-$$.php` com `<?php echo TOKEN; ?>`
2. Curl localhost
3. Se body == TOKEN → log OK
4. Se body != TOKEN → warn explícito com 4 comandos pra debug e o
   `a2enconf` manual pra forçar fix

Sem isso, admin descobria só ao acessar a página pela primeira vez.

**Pra corrigir agora um servidor já quebrado**, comandos no histórico
da sessão (basicamente `apt install php-fpm + a2enmod proxy_fcgi
setenvif + a2dismod phpX.Y + a2enconf phpX.Y-fpm + systemctl reload
apache2`).

VERSION 2.7.0 → 2.7.1.

---

## v2.7.0 — 2026-05-12

### Sliding JWT session + auto-logout em expiração

Fecha a UX podre que apareceu em v2.6.0/2.6.1: JWT da sessão expirava
após 60min e admin ficava com sessão "zumbi" (sessão PHP ainda válida
mas chamadas FastAPI retornando 401 silencioso). Agora a sessão se
auto-renova enquanto ativa, e quando realmente expira o admin é
redirecionado pro login com mensagem clara.

**Backend (`api_service`):**

- Novo endpoint **`POST /api/v1/auth/refresh`** (rate-limited com
  `rate_limit_auth`, mesmo limit do login):
  - Aceita Bearer JWT no header. Decodifica **sem validar `exp`** —
    tokens expirados há ≤10min (grace window) ainda são aceitos pra
    renovação. Mais que isso, retorna 401 com mensagem explicativa.
  - Re-valida no banco: se a conta foi desativada entre login e
    refresh, recusa. Evita que JWT velho dê acesso indefinido a
    usuário banido.
  - Retorna novo JWT com TTL completo (60min default).

**Frontend (PHP):**

- **`Auth::login()`**: ao receber o JWT, decodifica o payload base64
  pra extrair o claim `exp` e salva `$_SESSION['jwt_expires_at']`
  (epoch seconds). Helper `_extractJwtExp()` faz isso sem validar
  assinatura — só lê o claim pra saber quando expira.
- **`Auth::check()`** (chamado em toda page autenticada) agora avalia
  o tempo restante:
  - `≤5min` restantes → chama `refreshJwt()` em background (sliding
    session). Falha silenciosa — próxima request tenta de novo.
  - `≤0` (já expirou) → tenta um último refresh; se falhar,
    `logoutWithReason('jwt_expired')` força redirect pro login com
    motivo na query string.
- **`Auth::refreshJwt()`** novo: chama `POST /api/v1/auth/refresh`,
  atualiza `$_SESSION['api_jwt']` + `jwt_expires_at` se OK.
- **`Auth::logoutWithReason()`** novo: limpa sessão + redirect pra
  `login.php?reason=jwt_expired`.

**`login.php`:** detecta `?reason=jwt_expired` e exibe no campo de
erro: _"Sua sessão expirou. Faça login novamente para continuar."_
(usa o mesmo render `$error` existente — zero JS novo).

**Validação:**

- 58/58 testes pytest passando após restart.
- Refresh testado em 3 cenários: válido +5min (200), expirado -3min
  (200, dentro do grace), expirado -20min (401 com msg).
- Sessões antigas continuam funcionando: páginas que faltam
  `jwt_expires_at` no session simplesmente pulam o check (assumindo
  sessão pré-2.7.0 — admin re-loga normalmente quando JWT expirar).

**Não cobre:**

- Revogação imediata (admin desativando conta de outro user que está
  online). Pra isso precisa denylist Redis com TTL = exp do JWT —
  fora de escopo aqui. Refresh re-valida conta no banco, então
  user desativado **não consegue renovar** — fica limitado ao
  resto da janela atual do JWT (max 60min).

VERSION 2.6.1 → 2.7.0 (minor bump — feature de sessão).

---

## v2.6.1 — 2026-05-12

### Fix: aba "Gestão de Usuários" não listava usuários

Dois bugs identificados após v2.6.0:

**1. Tab content sem classe `active` inicial (FOUC):**

A aba `tab-usuarios` (e `tab-perfil`) foram inseridas sem o
`<?= $activeTab === 'X' ? 'active' : '' ?>` que os outros tabs têm.
Como o CSS é `.tab-content { display: none }`, a aba só aparecia
depois que o JS `applyTabSwitch` rodasse no `DOMContentLoaded` — flash
de "tela vazia" no carregamento. Corrigido.

**2. `getAllUsers()` retornando [] silenciosamente (JWT expirado):**

`Auth::getAllUsers()` faz GET autenticado em `/api/v1/users` usando o
`$_SESSION['api_jwt']`. Quando o JWT expira (default 60min) mas a
sessão PHP continua válida (`logged_in=true`), a chamada FastAPI
retorna 401 e `getAllUsers()` retorna array vazio. Resultado: tabela
sem usuários, sem mensagem de erro — admin pensava que era bug do
código.

Agora a aba mostra um **banner amarelo explícito** quando `$allUsers`
vem vazio: avisa que o JWT provavelmente expirou + link direto pra
logout. Texto auxiliar sugere checar logs do Apache se persistir.

VERSION 2.6.0 → 2.6.1.

---

## v2.6.0 — 2026-05-12

### Consolidação: Gestão de Usuários volta pra dentro de `config.php`

Em v2.3.0 a gestão de usuários virou página dedicada `/users.php` (admin-only)
e a aba "usuarios" do `config.php` foi removida. Resultado: dois menus de
usuário desconectados — um no sidebar separado, outro escondido em
`config.php#tab-perfil` (auto-serviço de senha). Esta release **reverte
parcialmente**: tudo volta pra `config.php` como aba `usuarios`, com **as
features adicionadas em v2.3.0 preservadas** (busca, filtros, edição
inline, last_login, reset de senha por admin).

**Mudanças:**

- **`config.php`**:
  - Nova aba `Gestão de Usuários` (admin-only) no array `$tabs`,
    posicionada antes de `Meu Perfil`.
  - Handlers POST reativados: `add_user`, `toggle_user`, `delete_user`,
    `update_role`, `update_email`, `reset_password` (todos com check
    `$isAdmin`).
  - Carrega `$allUsers` via `\App\Auth::getAllUsers()` quando admin.
  - Helpers `$fmtUserDate` / `$relativeUserTime` (mesma lógica de v2.3.0).
  - Aba `tab-usuarios` com tabela completa: avatar colorido por role,
    email com inline edit, role com select auto-submit (bloqueado pra
    self), status (Ativo/Suspenso/Bloqueado), Último Login (relativo +
    absoluto), Criado, ações (🔑 reset / ⏸ suspend / ✕ delete).
  - Toolbar com busca por username/email + filtros de role/status +
    contador total/visível.
  - Modal "Novo Usuário" (admin-only) com validação HTML.
  - Banner "Senha temporária gerada" no topo da aba quando reset é
    executado, com botão copiar.
  - JS `filterUsers()` no rodapé do `<script>`.
  - Aba `tab-perfil` perdeu o painel "Ir pra Gestão de Usuários" (já
    não faz sentido — está logo ao lado).
- **`includes/sidebar.php`**: entry "Usuários" removido (não faz mais
  sentido — não é mais página dedicada).
- **`users.php`**: virou redirect 301 pra `config.php?tab=usuarios#tab-usuarios`
  pra não quebrar bookmarks/links externos antigos. Pode ser removido em
  release futura.

Backend (FastAPI) intocado — os endpoints `/api/v1/users/*` continuam os
mesmos, só o frontend foi reorganizado.

**Quando atualizar:** após `install-from-git.sh`, links/bookmarks pra
`/users.php` continuam funcionando via redirect. Admins veem a aba nova
em `config.php`. Viewer só vê `Meu Perfil`.

VERSION 2.5.2 → 2.6.0 (minor bump — reorganização de UX).

---

## v2.5.2 — 2026-05-12

### exports.php — fix CSRF (restore), toast com progresso real, snapshot, cache

Auditoria da página de Exportações encontrou 1 vuln crítica + 4 melhorias
de UX/funcionalidade. Tudo coberto.

**🔴 SECURITY FIX — CSRF token validado no restore:**

`api/export.php` aceitava POST com upload `.tar.gz` sem validar
`csrf_token` — admin autenticado podia ser CSRF-em-em em site malicioso
pra restaurar um backup com configs adversárias (sobrescrita de
`/etc/unbound/*.conf` + restart do daemon). Agora valida com
`hash_equals` antes de processar; rejeita com 403 + JSON se ausente
ou inválido. Frontend envia o token via `FormData.append('csrf_token', CSRF_TOKEN)`.

**Confirm() no restore:**

`<form id="restoreForm">` submit agora bloqueia com `confirm()` mostrando
nome do arquivo + aviso "SOBRESCREVE /etc/unbound/*.conf e reinicia o
daemon. Não há rollback automático."

**Toast com progresso real:**

Antes: `setTimeout(..., 3000)` fixo escondia o toast em 3s independente
do tempo real do download. Em datasets grandes (range='all') o usuário
achava que falhou. Agora: escuta `iframe.onload` (dispara quando o
response chega + browser abre save-as) e some 1.2s depois. Fallback hard
de 60s se algo travar. Texto descritivo dinâmico ("Pode levar 10-30s
— não feche a aba" para snapshot, "Dataset grande" para logs all).

**📦 Novo card "Snapshot Completo":**

Botão destacado no header empacota tudo num único `.tar.gz`:
- `dns_queries_24h.csv` — últimas 24h
- `stats.json` — métricas atuais
- `system_log.txt` — 300 linhas journalctl + syslog
- `blacklist.csv` — domínios bloqueados
- `unbound_cache_dump.txt` — dump raw (re-importável com `load_cache`)
- `unbound_configs/` — cópia recursiva de `/etc/unbound/`
- `dashboard_settings.json` (se existir)
- `README.txt` — descritivo com timestamp + hostname

Útil pra abrir chamado de suporte ou snapshot pré-update. Backend
em `api/export.php::exportSnapshot()`. Validado em produção:
**1.4 MB tarball com 7 arquivos**, gerado em segundos.

**Novo card "Cache DNS":**

Export do `unbound-control dump_cache` raw como `.txt`. Re-importável
via `unbound-control load_cache`. Útil pra warm-up de cache em
restore/migração de servidor.

VERSION 2.5.1 → 2.5.2 (patch — security fix + UX).

---

## v2.5.1 — 2026-05-12

### cache.php — paginação real + seletor "por página"

A página de Cache DNS já tinha busca + filtro de tipo, mas a tabela
renderizava até 1000 linhas de uma vez (com footer "ocultas X") —
sem navegação real. Agora alinhada com o padrão de blocklist/threats:
seletor de quantidade por página e barra de paginação navegável.

**Mudanças em `cache.php`:**

- **Seletor "Por página"** na toolbar (25 / 50 / 100 / 250 / 500,
  default 50).
- **Paginação client-side**: estado `cachePage` + `cachePerPage`,
  slice de `(page-1)*perPage` até `+perPage`.
- **Barra de paginação** abaixo da tabela:
  - Info "X–Y de Z · Página N de M" à esquerda.
  - Controles "« primeiro · ‹ anterior · [janela de 5 números] ·
    próximo › · último »" à direita.
  - Botões disabled nos extremos, página atual destacada em ciano.
  - Clicar em qualquer botão de página rola pro topo da tabela.
- **Reset automático pra página 1** ao mudar: busca, filtro de tipo,
  per-page selector, ou tab (RRset ↔ Msg).
- Removido o footer "⚠ Exibindo 1000 de N" (substituído pela barra
  de paginação que cobre todos os casos).

Backend intocado — `api/cache_dump.php` continua retornando até 5000
entries por seção.

VERSION 2.5.0 → 2.5.1.

---

## v2.5.0 — 2026-05-12

### Nova página Cache DNS (cache.php)

Inspeção visual do cache do Unbound (rrset + msg), com flush por entry,
distribuição de TTL e top types. Estava só acessível via CLI
(`unbound-control dump_cache`).

**Backend (PHP):**

- `api/cache_dump.php` — executa `unbound-control dump_cache`, parseia
  ambas seções (rrset_cache + msg_cache), agrega stats e devolve JSON.
  - Limita a 5000 entries por seção (mais que isso o filtro client-side
    engasga; flag `truncated` quando aplica).
  - Cache em arquivo `src/data/tmp/unbound_cache_dump.json` por **30s**
    pra não estressar o daemon; `?force=1` ignora cache.
  - Stats: total/shown/truncated por seção, `ttl_buckets`
    (expirado/<60s/<5min/<1h/<1d/>1d), top 10 types, top 10 TLDs,
    contagem distinta.
- `api/cache_flush.php` — POST admin-only com `csrf_token` + `domain`.
  Roda `unbound-control flush <domain>` (validação:
  `[a-zA-Z0-9._-]+`, ≤253 chars). Invalida o cache JSON pra próximo
  refresh refletir.

**UI (`cache.php`):**

- Stats cards: total RRset, total Msg, types distintos, TLDs distintos
  (com indicador "truncado" quando aplica).
- **Bar chart de distribuição de TTL** (Chart.js) por bucket
  (expirado, <1min, 1-5min, 5-60min, 1-24h, >1d), cores semáforo.
- **Top Types** lista lateral.
- **Tabs RRset Cache / Msg Cache** — alterna a tabela e o tipo de
  filtro disponível.
- **Toolbar**: busca por nome ou rdata, filtro de tipo (populado
  dinamicamente), contagem total/visível, flag de truncamento.
- **Tabela** com nome, tipo (badge colorido), valor/flags, TTL
  formatado (s/m/h/d, amarelo se ≤60s).
- **Render limit visual: 1000 linhas** (mais que isso vira footer
  "exibindo X de Y — refine os filtros").
- **Flush por linha** (admin-only): botão 🗑 com confirm + toast +
  recarga automática.
- Botão "Atualizar" no header (força ?force=1).

**Sidebar**: entry "Cache DNS" em "Ferramentas".

Sudoers já permitia `unbound-control *` — sem mudança.

VERSION 2.4.0 → 2.5.0 (minor bump — página nova).

---

## v2.4.0 — 2026-05-12

### Limiares de alerta editáveis pela UI

Os 6 thresholds que disparam alertas (CPU load1, RAM%, Swap%, Disk%,
Network counters, SSH falhas/dia) eram hardcoded como constantes
Python em `alert_checker.py`. Pra mudar precisava editar código,
commitar, reinstalar, restart do `unbound-dashboard-api`. Agora são
settings persistidos no DuckDB e editáveis pela UI em ~60s.

**Backend (`api_service`):**

- `settings_repo.get_float(key, default)` — novo helper.
- `alert_checker.THRESHOLD_DEFAULTS` — dict com as 6 keys de settings
  (`alert_threshold_cpu_load1`, `_mem_percent`, `_swap_percent`,
  `_disk_percent`, `_network_counters`, `_ssh_failed_day`).
- `alert_checker.load_thresholds()` — lê de settings com fallback nos
  defaults. Chamado no início de cada tick (60s). Falha de leitura mantém
  os últimos valores válidos (não trava o worker).
- Os 5 `_check_*` agora leem de `self._thresholds[...]` em vez de constante.

**Endpoints (`routers/alerts.py`):**

- `GET /api/v1/alerts/thresholds` (require_auth) — retorna
  `{current: {...}, defaults: {...}}`. Aberto a viewer pra que a página
  alerts mostre os números nos cards de hardware.
- `PUT /api/v1/alerts/thresholds` (require_admin) — UPSERT parcial.
  Body com qualquer subset dos 6 campos (`alert_threshold_*`). Pydantic
  valida: `ge=0` em todos, `le=100` nos percentuais. Returna o estado
  final.

**UI (`alerts.php`):**

- Cards de hardware no topo agora exibem o valor **dinâmico** vindo de
  `/api/v1/alerts/thresholds` (era hardcoded).
- Novo botão **"⚙ Editar Limiares"** no page-header.
- Modal full-screen com 6 inputs numéricos (grid 2×3), defaults
  documentados em cada label, validação `min`/`max`/`step` HTML.
- Submit faz `PUT /api/v1/alerts/thresholds` com Bearer JWT do meta tag;
  toast de sucesso/erro; page reload em 1.5s.

Como aplica:
1. Admin edita → `PUT` → `settings_repo.bulk_upsert`.
2. Próximo tick do `alert_checker` (≤ 60s) carrega novos valores.
3. Cards refletem o novo valor após o reload (lê via GET).

Sem migration nova — usa a tabela `settings` existente (V1).

VERSION 2.3.5 → 2.4.0 (minor bump — feature backend nova + UI nova).

---

## v2.3.5 — 2026-05-12

### diagnostics.php + dns_benchmark.php — auto-fill, copiar/baixar, tipo DNS, domínio editável

**`diagnostics.php`:**

- **DNS Lookup com tipo de record** selecionável (A/AAAA/MX/TXT/NS/CNAME/
  SOA/PTR). Antes era só A (`+short`). Validado contra allowlist no
  backend antes de passar pro `dig`.
- **Auto-fill de últimos inputs** via `localStorage` por ferramenta
  (ping / traceroute / dns / internet). Recarregar a página mantém
  o que você digitou da última vez. Cada `.diag-form` ganhou
  `data-tool-id` e o submit handler salva os campos automaticamente.
- **Botões "📋 Copiar" e "⬇ Baixar"** no header do output:
  - Copiar → `navigator.clipboard.writeText` com confirmação visual.
  - Baixar → gera `.txt` com nome `unbound-diag-<tool>-<timestamp>.txt`
    via Blob + ObjectURL.

**`dns_benchmark.php`:**

- **Domínio de teste agora editável** (era hardcoded em `google.com`).
  Input com `pattern="[a-zA-Z0-9._-]+"`, maxlength 253. Validação
  duplicada no PHP server-side (`preg_match` antes de passar pro
  `dig`, fallback pro default se inválido).
- **Queries por servidor agora editável** (era 5 hardcoded). Input
  numérico 1-20 com validação PHP (fora do range = default 5).
- Form reorganizado em painel com grid (domínio col-6, queries col-3,
  botão col-3). Texto auxiliar explicando os limites.
- FormData(this) do submit JS já pega ambos os campos — zero mudança
  no fluxo de 3 rounds e agregação.

Sem mudança de schema/endpoint. Tudo cirúrgico nas duas páginas.

---

## v2.3.4 — 2026-05-12

### history.php — busca + filtros + pie chart clicável

A tabela de logs de consulta tinha só seletor de limite — sem busca,
sem filtros. O pie chart de Top Domains era decorativo.

**Mudanças:**

- **Toolbar nova** acima da tabela: busca por domínio/IP, filtro por
  ação (blocked/resolved), filtro por tipo de query (A/AAAA/CNAME/…
  dropdown populado dinamicamente dos dados atuais).
- **Contagem total/visível** + botão "Limpar filtros".
- **Pie chart de Top 10 Domínios virou clicável**: clique numa fatia
  preenche a busca com o domínio e rola até a tabela. Tooltip mostra
  "— clique para filtrar a tabela".
- Linhas da tabela ganharam `data-domain`/`data-ip`/`data-type`/
  `data-action`/`data-category` pra filtragem client-side.
- Mensagem "Nenhuma linha atende aos filtros" separada do empty state.

Backend e gráficos de cache/latência intocados (continuam usando o
mesmo `/api/v1/history/summary` + dados simulados pra timeseries).

---

## v2.3.3 — 2026-05-12

### threats.php — busca + filtros + top lists clicáveis

A página de ameaças tinha 3 cards de stats, 2 top lists e uma tabela
estática só com seletor de limite. Bloqueios escalam rápido — sem busca
ou filtros virava ruído.

**Mudanças:**

- **Toolbar nova** acima da tabela:
  - **Busca** por domínio ou IP (oninput, client-side).
  - **Filtro por categoria** (dropdown populado dinamicamente com os
    valores distintos do payload — agnóstico de quais categorias o
    backend retorna).
  - **Filtro por severidade** (high / normal).
  - Contagem total/visível + botão "Limpar filtros" que aparece quando
    algum filtro está aplicado.
- **Top Domains e Top Clients agora são clicáveis**: clique num chip
  da top list aplica a busca pelo valor selecionado na tabela abaixo
  (cursor pointer + hover laranja, atalho cross-link interno).
- **Mensagem "Nenhuma linha atende aos filtros"** abaixo da tabela
  quando filtros excluem todas as linhas (separada da mensagem de
  payload vazio).
- Filtros sobrevivem ao re-fetch (auto-refresh ou mudança de limite
  preservam estado do dropdown).

Backend intocado — endpoint `/api/v1/threats/data` (FastAPI) e fallback
`api/threats_data.php` continuam iguais. Tudo client-side em cima do
payload existente.

---

## v2.3.2 — 2026-05-12

### blocklist.php — origem ativa visível + atualização sob demanda

A página da lista de bloqueio tinha título hardcoded em "Lista Judicial ANATEL —
Anablock", mas a origem real era configurável em `config.php` (StevenBlack /
Hagezi Normal / Hagezi Pro). Anablock nem é mais uma opção. Resultado:
usuário não sabia qual lista estava vendo nem quando foi atualizada.

**Mudanças:**

- **Título**: "Lista Judicial ANATEL" → "Lista de Bloqueio Ativa"
  (HTML title, topbar title, meta description).
- **Novo painel "Origem Ativa"** no topo:
  - Mostra a fonte ativa (StevenBlack / Hagezi Normal / Hagezi Pro) com
    descrição curta lendo `blacklist_source` setting via
    `BlocklistManager::getBlocklistSource()` (que usa FastAPI/DuckDB).
  - Badge "Última atualização do arquivo: N min/h/d atrás (DD/MM HH:MM)"
    calculado de `filemtime(src/data/official_blocklist.conf)`.
  - Link "Configurações → Lista de Bloqueios" pra trocar a origem.
- **Botão "Atualizar Agora"** (admin-only):
  - Dispara `POST api/service_control.php action=update_blacklist` (já
    existia — re-baixa a fonte ativa em background via
    `scripts/update_blacklist.php`).
  - Animação de spinner no ícone + label muda pra "Atualizando...".
  - Toast de sucesso/erro, page reload depois de 5s pra refletir o mtime
    novo.
- **Card "Total de Domínios"** e **header da tabela** agora dizem
  "Origem: <Source>" em vez do hardcoded "Anablock".

Sem mudança de backend — `BlocklistManager` e `api/service_control.php`
já existiam. Endpoint de leitura da lista (`api/blocklist_search.php`)
continua igual (lê do arquivo flat, cache JSON local).

---

## v2.3.1 — 2026-05-12

### alerts.php — repaginação da tabela de histórico + thresholds visíveis

A tabela de "Ocorrências Críticas" ganhou filtros, busca e visibilidade
do campo `severity` (que já existia no schema mas era ignorado na UI).
Cards de hardware no topo agora mostram o threshold que dispara cada
categoria de alerta — dá contexto entre "métrica atual" e "alerta
histórico".

**Cards de hardware (topo) — thresholds inline:**

- CPU: `⚠ Alerta se load1 > 4.0`
- RAM: `⚠ Alerta se uso > 90% · swap > 50%`
- Armazenamento: `⚠ Alerta se uso > 90%`
- Network: `⚠ Alerta se errors ou drops > 100`
- SSH: `⚠ Alerta se falhas hoje > 50`

Valores hardcoded espelhando `api_service/app/workers/alert_checker.py:48-53`
(comentário no PHP avisa pra manter em sync — promovido a settings dinâmico
quando justificar overhead).

**Tabela de alertas — features novas:**

- **Severity coluna**: badge colorido (critical=vermelho, warning=âmbar,
  info=azul) lendo o campo `severity` que vinha sendo descartado.
- **Coluna duração**: lê `duration_secs` do endpoint pra resolvidos; pra
  ativos, calcula em tempo real (now - started_at). Formatação humanizada
  (s/min/h/d).
- **Busca por mensagem ou tipo** (client-side, oninput).
- **Filtros**: status (ativo/resolvido), severidade (critical/warning/info).
- **Chips por tipo** acima da toolbar: contagem `ativos/total` por tipo,
  bolinha vermelha pulsante se há ativos, clique aplica filtro de tipo.
  Lista os tipos conhecidos: cpu, memory, swap, disk, network, security,
  webserver, no_queries (oculta tipos sem ocorrências).
- **Contagem total/visível** na toolbar.
- **Confirmação modal** no "Limpar Resolvidos" via `data-confirm-message`.

Backend não mudou — endpoints `/api/v1/alerts/{list,resolve,clear-resolved}`
já retornavam `severity` e `duration_secs`. Página continua usando Strangler
Fig (FastAPI primário, AlertManager PHP/DuckDB fallback).

---

## v2.3.0 — 2026-05-11

### Página dedicada de Gestão de Usuários (`users.php`)

A gestão de outros usuários sai da aba do `config.php` e ganha página
própria com features mais completas. Tudo backed por endpoints existentes
e dois novos no `api_service`.

**Novo: `users.php`** (admin-only, link no sidebar em "Sistema"):

- **Tabela** com colunas: avatar (iniciais coloridas por role), username,
  email (inline edit), role (select inline), status (Ativo/Suspenso/Bloqueado),
  last_login (relativo + absoluto), created_at, ações.
- **Busca client-side** por username ou email + filtros de role e status.
  Contador "Total: N · Visíveis: M".
- **Edição inline:**
  - Role: select onchange auto-submit (admin/viewer). Bloqueado pra self
    (admin não pode rebaixar a si mesmo — outro admin precisa).
  - Email: input + botão ✓ pra salvar.
- **Reset de senha por admin:** botão 🔑 gera senha temporária aleatória
  (12 chars urlsafe), exibida UMA VEZ em banner amarelo com botão "Copiar".
- **Modal "Novo Usuário"** com validação HTML (regex username, email type,
  minlength senha).
- **Confirmações** via modal genérico do `footer.php` (data-confirm-message)
  pra exclusão e reset.
- **Coluna last_login** com timestamp relativo ("agora", "5 min atrás",
  "2d atrás") + absoluto pra contas dormentes.

**Backend: 2 endpoints novos em `api_service/app/routers/users.py`:**

- `PUT /api/v1/users/{id}/role` (require_admin) — atualiza role; bloqueia
  self-target e roles inválidos.
- `POST /api/v1/users/{id}/password-reset` (require_admin) — gera senha
  aleatória `secrets.token_urlsafe(9)[:12]`, hasheia com bcrypt, limpa
  failed_logins/locked_until, retorna a senha em texto na resposta.

**Schema DuckDB — migration V2 (`add_last_login`):**

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP;
```

`auth_service.login_user()` agora chama `user_repo.touch_last_login()`
após sucesso. `users_service.list_all()` expõe o campo no JSON.

**`src/Auth.php` (PHP) — métodos novos:**

- `updateRole(int $id, string $newRole)` — PUT /role via ApiClient.
- `adminResetPassword(int $id)` — POST /password-reset, retorna a
  temporary_password no array de resposta pra UI exibir.

**Removido de `config.php`:**

- Aba "Gestão de Usuários" inteira (substituída pela página).
- Handlers `add_user`, `toggle_user`, `delete_user` (agora vivem em
  `users.php`).
- Aba "Meu Perfil" passou a aparecer pra admins também (eles agora
  perdem o "Alterar Senha" que estava enterrado na aba de usuários).

**Migrações:** após atualizar via `install-from-git.sh`, o
`unbound-dashboard-api.service` aplica V2 automaticamente no startup
(idempotente, `ADD COLUMN IF NOT EXISTS`). Testes `test_migrate.py`
atualizados pra esperar `[1, 2]`. 58/58 testes passando.

---

## v2.2.12 — 2026-05-11

### NetworkManager — DNS via systemd-resolved, /etc/hosts no setHostname, NTP chrony/ntpd

Fecha os 3 problemas restantes da auditoria da aba **Configurações de Rede**
(os mesmos sintomas do netplan: falha silenciosa em sistemas modernos).

**DNS — `detectDnsBackend()` + setSystemDNSResolved():**

- Detecta `systemd-resolved` ativo + `/etc/systemd/resolved.conf` presente.
- Em vez do `mv /tmp/resolv_conf_new /etc/resolv.conf` (que era revertido
  pelo daemon em segundos quando o arquivo é symlink stub), edita a chave
  `DNS=` no `[Resolve]` do `/etc/systemd/resolved.conf` e faz
  `systemctl restart systemd-resolved`. Persistente, oficial.
- `getSystemDNS()` lê da mesma fonte (não de `/etc/resolv.conf` que pode
  mostrar só `127.0.0.53` em sistemas com resolved).
- Backend `file` (caminho legacy) preservado pra sistemas sem resolved.

**Hostname — agora atualiza `/etc/hosts`:**

- `setHostname()` continua chamando `hostnamectl set-hostname`, mas
  depois disso reescreve a linha `127.0.1.1` (convenção Debian/Ubuntu)
  pra apontar pro novo nome. Se a linha não existir, adiciona.
- Sem isso, apps legados que resolvem o hostname local pelo `/etc/hosts`
  (Apache `ServerName`, scripts cron, MTA local) viam o nome antigo até
  rebooting.

**NTP — `detectNtpBackend()` + despacho chrony/ntpd/timesyncd:**

- Detecta o daemon ativo (ordem de preferência: chrony → ntpd/ntpsec →
  systemd-timesyncd → none).
- `getNtpServers()` e `setNtpServers()` agora despacham:
  - **chrony**: edita `/etc/chrony/chrony.conf` ou `/etc/chrony.conf`,
    substitui linhas `server`/`pool`, restart de `chrony`/`chronyd`.
  - **ntpd/ntpsec**: edita `/etc/ntp.conf`, restart de `ntp`/`ntpd`/`ntpsec`.
  - **timesyncd**: caminho original (edita `/etc/systemd/timesyncd.conf`).
  - **none**: retorna erro pedindo pra instalar/habilitar um daemon.

**Sudoers (`system/sudoers/unbound-dashboard`) — entradas novas:**

```
/usr/bin/mv /var/.../tmp/resolved.conf.new /etc/systemd/resolved.conf
/usr/bin/systemctl restart systemd-resolved
/usr/bin/mv /var/.../tmp/hosts.new /etc/hosts
/usr/bin/mv /var/.../tmp/chrony.conf.new /etc/chrony/chrony.conf
/usr/bin/mv /var/.../tmp/chrony.conf.new /etc/chrony.conf
/usr/bin/mv /var/.../tmp/ntp.conf.new /etc/ntp.conf
/usr/bin/systemctl restart chrony | chronyd | ntp | ntpd | ntpsec
```

Cada entrada tem path fixo no destino e wildcard só onde necessário —
mantém a postura granular do sudoers.

**Sintomas que isto corrige:**

- "Mudei DNS na UI mas resolvconf voltou pros stubs em 5 segundos" —
  agora persiste via `[Resolve]` do resolved.
- "Mudei hostname mas Apache ainda loga o antigo no `ServerName`" —
  `/etc/hosts` sincronizado.
- "Configurei NTP mas o time drift continua" — chrony agora é editado se
  for o daemon ativo (era padrão silencioso pra timesyncd inexistente).

Sem regressão pra sistemas legacy — todos os caminhos antigos (file
DNS, hostnamectl-only, timesyncd) ainda funcionam quando detectados.

---

## v2.2.11 — 2026-05-11

### NetworkManager — suporte a netplan (Debian/Ubuntu modernos)

A aba **Configurações de Rede** agora detecta o backend em uso e despacha
entre `netplan` (Debian 12+/Ubuntu 18+) e `ifupdown` legacy automaticamente.
Antes era hardcoded em `ifdown/ifup + /etc/network/interfaces`, o que falhava
silenciosamente em qualquer instalação cloud moderna (`netplan` é o default
desde Ubuntu 18.04).

**Novo em `src/NetworkManager.php`:**

- `detectBackend()` — retorna `'netplan'` se `/usr/sbin/netplan` existe e há
  YAML em `/etc/netplan/`; senão `'ifupdown'`. Cacheado por request.
- `detectNetplanRenderer()` — escolhe `NetworkManager` se ativo, senão
  `networkd` (default Debian server).
- `getInterfaceConfig()` e `updateInterfaceConfig()` agora despacham por
  backend. Para netplan:
  - Lê e escreve apenas `/etc/netplan/99-unbound-dashboard.yaml` (não toca
    nos YAMLs do cloud-init).
  - Antes de sobrescrever, copia o YAML atual pra
    `/var/backups/unbound-dashboard/netplan-99-<timestamp>.yaml`.
  - Aplica via `netplan apply` (sem `netplan try` — não dá pra interagir
    com TTY do PHP-FPM).
- `restoreLastNetplanBackup()` — restaura o backup mais recente e re-aplica.
  Permite admin desfazer a última mudança via UI (precisa de console local
  se a sessão SSH caiu).
- `applyInterfaceChanges()` é no-op no netplan (o apply acontece dentro do
  update — não há fluxo de duas etapas).
- Suporte a `yaml_emit`/`yaml_parse_file` (ext/yaml) com fallback de parser
  textual mínimo pra ambientes sem a extensão.

**Sudoers — agora versionado no repo (`system/sudoers/unbound-dashboard`):**

Source of truth virou um arquivo commitado, não mais o heredoc no
`build-package.sh`. Mudanças de sudoers passam por code review como o resto.
Entradas novas (granulares, com paths fixos):

- `/usr/sbin/netplan apply`, `/usr/sbin/netplan generate`
- `/usr/bin/mv /tmp/unbound-dashboard-netplan.yaml /etc/netplan/99-unbound-dashboard.yaml`
- `/usr/bin/cp` pra backup/restore em `/var/backups/unbound-dashboard/netplan-99-*.yaml`

`build-package.sh` agora prefere `system/sudoers/unbound-dashboard` do repo
sobre `/etc/sudoers.d/` instalado, e valida com `visudo -c` antes de incluir
no pacote — build aborta se sintaxe quebra.

**UI da aba `config_rede`:**

- Banner no topo mostra o backend detectado (`netplan` em azul, `ifupdown`
  em âmbar) com aviso de que mudanças de IP podem cortar SSH.
- Botão **"↩ Reverter última mudança"** aparece quando há backup netplan
  disponível. Confirma via prompt antes de restaurar.

**Não muda em ifupdown legacy:** instalações antigas continuam usando
`/etc/network/interfaces` + `ifdown/ifup` como antes — sem regressão.

**Sintomas que isto corrige:**

- "Salvei a interface em Ubuntu 22.04 server, mas o IP não mudou" — `netplan`
  ignorava o `/etc/network/interfaces` editado pelo PHP.
- "ifup falhou: interface não existe na conf" — netplan rendia configs em
  runtime que o ifupdown não reconhecia.

---

## v2.2.10 — 2026-05-11

### install.sh — migração de mod_php → PHP-FPM + pacotes críticos com fail-fast

Validado em VM limpa via `install-from-git.sh`: o pacote
`libapache2-mod-php` falhava silenciosamente em alguns ambientes (o loop
de instalação apenas logava `[!] Falha em libapache2-mod-php, continuando`
por causa do `|| warn`), deixando o Apache sem handler pra `.php` —
páginas apareciam como download/texto puro.

**Fix:**

1. **Substituído `libapache2-mod-php` por `php-fpm`** na lista de pacotes.
   PHP-FPM é o padrão moderno em Debian/Ubuntu, isolado do Apache, e
   instala de forma confiável.

2. **Detecção dinâmica da versão do PHP-FPM:** após o `apt install`, o
   script lê `systemctl list-unit-files` pra encontrar `phpX.Y-fpm.service`
   (8.2 em Debian 12, 8.3 em Debian 13, etc.) e usa essa versão pra
   `a2enconf phpX.Y-fpm` + `systemctl enable --now`.

3. **Apache modules ampliados:** Etapa 3 agora habilita também
   `proxy_fcgi` e `setenvif` (exigidos pelo drop-in do PHP-FPM) além do
   conjunto antigo de proxy do api_service. Idempotente.

4. **`a2dismod phpX.Y` legado:** se houver instalação `<=2.2.9` com
   `mod_php` ainda habilitado, o install desabilita silenciosamente antes
   de ativar o FPM — evita conflito de handler.

5. **Pacotes críticos com `|| err`:** lista de pacotes dividida em
   `CORE_PACKAGES` (apache2, php-fpm, php-*, python3*, redis-server,
   unbound) e `EXTRA_PACKAGES` (sudo, curl, wget, etc.). Falha em
   crítico aborta a instalação com mensagem clara; auxiliares mantêm o
   comportamento antigo de `|| warn`.

**Compatibilidade:** instalações existentes em produção continuam
funcionando — quando o `install.sh` rodar de novo numa máquina que já
tem `libapache2-mod-php`, o `a2dismod` legado vai retirar mod_php e o
FPM assume. Sem necessidade de remover o pacote manualmente.

**Docs atualizadas:** `README.md` (Arquitetura, Requisitos, lista de
módulos Apache) e `build-package.sh` (LEIAME.txt do pacote).

---

## v2.2.9 — 2026-05-11

### Bugfix do build-package.sh — DASHBOARD_DIR hardcoded

`build-package.sh` tinha `DASHBOARD_DIR="/var/www/html/unbound-dashboard"`
hardcoded. Quando chamado pelo `install-from-git.sh` (que clona em
`/tmp/unbound-dashboard-install/`), o build ia até `/var/www/html/...`
e usava artefatos errados (ou inexistentes). Resultado: pacote gerado com
versão antiga e arquivos faltando.

**Fix:** `DASHBOARD_DIR` agora é derivado do path do próprio script:
`SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"` →
`DASHBOARD_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"`.

Permite rodar o build a partir de qualquer checkout — alinhando com
`build-update.sh` que já fazia isso.

---

## v2.2.8 — 2026-05-04

### Feature: `install-from-git.sh` (one-liner do GitHub)

Novo `tools/install-from-git.sh`: clona o repo, builda o pacote local e
executa `install.sh` em um único comando. Pra rodar do servidor de destino
sem precisar empacotar `.tar.gz` em outra máquina.

```bash
curl -fsSL https://raw.githubusercontent.com/bldantas/unbound-dashboard/main/tools/install-from-git.sh \
  | sudo ADMIN_USERNAME=admin ADMIN_EMAIL=a@b.c ADMIN_PASSWORD='senha' bash
```

Aceita `REPO_BRANCH=outra` para testar branches. Instala git/rsync/curl/tar
se faltarem, faz idempotente (`git pull` se o repo já existe), e limpa o
work dir ao final (a menos que `KEEP_WORK_DIR=true`).

README e MANUAL_INSTALACAO atualizados com o atalho one-liner.

---

## v2.2.7 — 2026-05-04

### install.sh — adiciona www-data ao grupo `adm`

O worker `log_watcher` lê `/var/log/syslog`, `/var/log/auth.log` e
`/var/log/unbound.log` continuamente. Em Debian/Ubuntu esses arquivos têm
mode `640 root:adm`. Sem isso, o worker crasha em loop com:

```
worker.crashed name=log_watcher error="[Errno 13] Permission denied: '/var/log/syslog'"
```

`install.sh` agora faz `usermod -aG adm www-data` (idempotente).

### docs/TROUBLESHOOTING

- §8 novo: schema drift do DuckDB legado (de instalação experimental v2.1.x
  antiga) e procedimento de wipe + recreate.
- §9 novo: `log_watcher` Permission denied em `/var/log/syslog` e fix.
- §10 (antigo §8): reset de senha do admin.

---

## v2.2.6 — 2026-05-04

### Bugfix do migrations runner — schema_migrations legado

**Problema:** Em servidores com instalação experimental v2.1.x antiga,
a tabela `schema_migrations` no DuckDB foi criada com schema diferente
(`(version, filename)`) do que o api_service atual espera
(`(version, name, checksum, applied_at)`). O `CREATE TABLE IF NOT EXISTS`
do startup virava no-op (tabela já existia) e o `SELECT version, checksum`
seguinte falhava com `BinderException: Referenced column "checksum" not found`.

**Fix:** `app/db/migrate.py::_ensure_schema_migrations` agora detecta colunas
ausentes via `information_schema.columns` e faz `ALTER TABLE ADD COLUMN`
para `checksum`, `name` e `applied_at` quando faltam:
- Se há coluna legada `filename`, popula `name` extraindo o basename sem `.sql`
- `checksum` legado fica vazio (`''`); o runner pula a validação de drift
  para entradas com checksum vazio (assume migration já aplicada)
- `applied_at` recebe `NOW()` retroativo

Tornado `_ensure_schema_migrations` idempotente e tolerante a schema drift.

### Hotfix em servidor já travado:
Reaplique o pacote v2.2.6 (rebuild do install.sh) ou faça hotfix manual:
```bash
sudo -u www-data bash -c '
    set -a; source /etc/unbound-dashboard/api-v1.env; set +a
    /var/www/html/unbound-dashboard/api_service/.venv/bin/python -c "
import duckdb
with duckdb.connect(\"/var/lib/unbound-dashboard/unbound_dash.duckdb\") as c:
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS checksum VARCHAR(64) DEFAULT \\\"\\\"\")
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP DEFAULT NOW()\")
    c.execute(\"ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS name VARCHAR(255)\")
    c.execute(\"UPDATE schema_migrations SET name = regexp_replace(filename, \\\"\\\\.sql$\\\", \\\"\\\") WHERE name IS NULL\")
"'
sudo systemctl restart unbound-dashboard-api
```

---

## v2.2.5 — 2026-05-04

### Bugfix do install.sh — ownership do DuckDB

**Problema:** Em servidores que tinham instalação anterior com user
diferente (ex: `unbound-dash:unbound-dash` de uma versão experimental
v2.1.x antiga), o arquivo `/var/lib/unbound-dashboard/unbound_dash.duckdb`
ficava com ownership errado. O api_service rodando como `www-data` recebia
`Permission denied` ao tentar abrir o arquivo.

**Fix:** install.sh agora faz `chown -R www-data:www-data` no
`/var/lib/unbound-dashboard/` (não só no diretório raiz) e força
`chmod 640` em todos os arquivos. Idempotente — sem efeito em instalações
limpas.

### Hotfix em servidor já instalado:
```bash
sudo chown -R www-data:www-data /var/lib/unbound-dashboard/
sudo chmod 750 /var/lib/unbound-dashboard
sudo find /var/lib/unbound-dashboard -type f -exec chmod 640 {} \;
```

---

## v2.2.4 — 2026-05-04

### Bugfix do install.sh — Etapa 8 (admin inicial)

**Problema 1: lock do DuckDB.** A Etapa 7 sobe o `unbound-dashboard-api`,
que abre o arquivo `.duckdb` com lock exclusivo. A Etapa 8 tentava rodar
`create_admin.py` (também escritor), e o DuckDB falhava com
`IO Error: Cannot open file ... Permission denied` (lock conflict).

**Fix:** install.sh agora **para** o `unbound-dashboard-api` antes do
`create_admin.py` e religa depois (com smoke `/api/v1/healthz`). Em caso de
falha do create_admin, o serviço é religado mesmo assim para não deixar o
sistema offline.

**Problema 2: usernames com espaço/caracteres especiais** eram aceitos pelo
prompt mas quebravam ou se tornavam difíceis de logar depois.

**Fix:** install.sh e `create_admin.py` agora validam username com regex
`^[a-zA-Z0-9._-]+$`. Username inválido no prompt re-pergunta; via env var
aborta o install com mensagem clara.

---

## v2.2.3 — 2026-05-04

### Bugfix
- **`pandas` movido para `dependencies` no `pyproject.toml`** (estava em
  `dependency-groups dev`). Como `install.sh` roda `uv sync --no-dev`,
  o pandas não ia pra produção e o startup do `api_service` quebrava com
  `ModuleNotFoundError: No module named 'pandas'` ao importar
  `app.repositories.duckdb.connection` (que usa pandas em `db_append`).
- `uv.lock` regenerado.

### Hotfix em servidores já instalados (sem refazer install)
```bash
cd /var/www/html/unbound-dashboard/api_service
sudo /usr/local/bin/uv pip install --python .venv/bin/python "pandas>=2.0"
sudo systemctl restart unbound-dashboard-api
```

---

## v2.2.2 — 2026-05-04

### UI / Backend
- Widget "Banco de Dados" em `alerts.php` substituído pelo card **API + DuckDB**:
  mostra status do `unbound-dashboard-api.service`, smoke `/api/v1/healthz`,
  tamanho do arquivo DuckDB, status do `redis-server` e do webserver.
- `AppMetricsManager` ganhou métodos `getApiServiceStatus()`,
  `getDuckDBStatus()` e `getRedisStatus()`.
- `api/alerts_metrics.php` retorna novas chaves `api`, `duckdb`, `redis`
  (mantém `db` como stub fixo offline pra compat).

### Limpeza de legado MariaDB
- Removidos `scripts/{init_db.sql, migrate_db.sql, setup_database.sql,
  log_ingester.php, aggregate_stats.php, cron_alerts.php, migrate_users.php,
  force_config.php, init_system.sh}` — todos cobertos pelos workers Python
  do api_service ou tornaram-se obsoletos.
- `tools/system/cron/unbound-dashboard-crons` reduzido para apenas o que
  ainda faz sentido (update_blacklist + sync_judicial_list).
- `StatsManager::ensureFreshCache()` agora é no-op (workers Python mantêm
  os JSONs atualizados).

### Wizard PHP legado removido
- Removidos `setup.php` e `api/setup_wizard.php` (assumiam MariaDB).
- Acesso pré-instalação redireciona para nova página `not_installed.php`
  (HTTP 503) com instruções claras.
- Bootstrap do admin é exclusivo do `install.sh` via `create_admin.py`.

### Tooling
- `api_service/tools/reset_admin_password.py` adicionado: CLI idempotente
  para resetar senha de um usuário existente quando o SMTP de recuperação
  não está disponível.
- `tools/docker/{Dockerfile.smoke,smoke-test.sh}`: smoke-test do `install.sh`
  em container Debian 13 (com `systemctl` stubado), valida `.venv`, env file,
  bootstrap do admin e `/api/v1/healthz`.

---

## v2.2.1 — 2026-05-04

### Tooling
- `tools/build-package.sh` reescrito: empacota `dashboard/` + `api_service/`
  + `system/{sudoers, systemd, apache, etc, bin, cron}` (sem MariaDB).
- `tools/install.sh` reescrito (8 etapas): instala redis-server, python3 3.11+,
  uv, módulos Apache (proxy, headers), gera JWT_SECRET, popula
  `/etc/unbound-dashboard/api-v1.env`, sobe systemd unit + Apache conf, faz smoke
  `/api/v1/healthz` e cria admin via `create_admin.py` (interativo ou env vars).
- `tools/update.sh` reescrito: backup automático (código + DuckDB + env),
  rsync incremental do dashboard e api_service preservando `.venv`, detecta mudança
  em `pyproject.toml`/`uv.lock` e roda `uv sync` quando necessário, restart do
  api_service + reload Apache + smoke `/api/v1/healthz`.
- `tools/build-update.sh` reescrito: empacota api_service + system completos.
- `api_service/tools/create_admin.py`: bootstrap idempotente do primeiro admin
  no DuckDB (usado pelo install.sh).
- Removidos: `tools/{build-package-v2.sh, fix-mariadb.sh, tune-mariadb.sh}`.
- `unbound-dashboard-api.service`: removido `After=mariadb.service`.

---

## v2.2.0 — 2026-05-04

### Marco
- Tear-down completo do MariaDB. Sistema agora roda 100% em DuckDB.

### Backend
- Migração de todos os managers PHP (`Blocklist`, `SourceBalance`, `Alert`,
  `Unbound`, `UnboundConfig`, `SystemCheck`) para o cliente FastAPI.
- `scripts/update_blacklist.php` reescrito sobre `ApiClient`.
- `Database.php` neutralizado (stub que lança em vez de matar o script).
- `AppMetricsManager` desacoplado do PDO.

### UI
- Página de Histórico agora consome `/api/v1/history/summary` direto.
- Página de Saúde & Auditoria atualizada: removido check de MariaDB, adicionados
  status systemd (FastAPI, Redis, Apache, Unbound) e novos componentes
  (DuckDB, env do api_service, diretório de backups).
- Benchmark DNS executa 3 rounds e o modal mostra "Teste X de 3" em tempo real.

---

## v2.1.0 — 2026-04-29

### Backend (modernização in-place)
- FastAPI/DuckDB em paralelo ao PHP+MariaDB legado (Strangler Fig).
- Workers assíncronos: LogWatcher, StatsAggregator, AlertChecker.
- Cache/queue Redis para snapshots e progresso de jobs.
- Rate limiting (slowapi), middlewares CORS e X-Request-ID.
- Métricas Prometheus em `/metrics`.
- JWT (HS256) compartilhado entre PHP e FastAPI via sessão.

### Auth & API bridge
- `src/ApiClient.php` (cURL) com `get/post/put/delete/login/changePassword`.
- Login do PHP delega para `/api/v1/auth/login` e guarda `api_jwt` na sessão.

### Páginas migradas para FastAPI
- Dashboard, Threats, History, Alerts, Blocklist, Config, Diagnostics,
  Service Control, Export.

---

## v1.0.3 — 2026-04-23

### Performance
- Índice composto `idx_action_ts (action, timestamp)` em `query_logs`.
- Coluna `blocked_count` em `daily_stats` + backfill de 31 dias.
- `api/threats_data.php` usa `daily_stats` para totais (31 linhas vs 16M).
- `log_ingester.php` atualiza `daily_stats` a cada inserção.
- `getTopDomains()` em `UnboundManager` limitado às últimas 24h.

### Update
- `scripts/migrate_db.sql` com migrações idempotentes pelo `update.sh`.
- `scripts/init_db.sql` atualizado com índice composto e nova coluna.

---

## v1.0.2 — 2026-04-28

- Removido índice duplicado `idx_query_logs_domain` em `query_logs`.
- Adicionados `idx_alerts_resolved_at` e `idx_alerts_started_at` em `alerts`.

---

## v1.0.1 — 2026-04-23

- Carregamento progressivo em History/Threats/Logs/Alerts (flush + loader).
- Seletor de linhas (10/20/50/100/todos) em Threats, default 10.
- UX: hide do loader global ao finalizar render.
- Build hardening: exclui credenciais e JSONs voláteis.
- `update.sh` preserva `src/data` local do servidor.
- Build de update lê `VERSION` e faz bump automático (patch).

---

## v1.0.0 — 2026-04-23

- Primeira versão estável do Unbound Dashboard (PHP + MariaDB).
- Monitoramento em tempo real, histórico DNS, alertas, diagnósticos.
- Exportação, benchmark e ferramentas operacionais.
