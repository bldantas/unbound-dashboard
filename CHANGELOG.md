# Changelog

A partir de 2026-05-26 entradas são agrupadas por **dia** em vez de por
release individual. Releases anteriores mantêm o formato antigo (uma
seção por versão) por histórico — consolidação retroativa só pra
2026-05-26 (36 releases num dia inflaram o arquivo).

## 2026-05-28

### Hotfix update.sh
- **v2.103.2**: `tools/update.sh` agora limpa `__pycache__` em `api_service/app/` depois do rsync e antes do restart. Bug descoberto no deploy do `dashboard.redeconexao.net` ao subir v2.103.1: VERSION foi escrito, `app/routers/cluster.py` chegou em disco, `app/main.py` foi atualizado com `app.include_router(cluster.router)` — mas o serviço reiniciou carregando uma versão antiga do `main.py` (provavelmente bytecode stale ou race entre escrita do .py e restart), resultando em `/api/v1/cluster/peer-ping → 404` apesar da rota estar registrada no source. Limpar `__pycache__` à força elimina a classe inteira de bug — uvicorn recompila em ms no startup. Workaround manual aplicado uma vez (`find ... -name __pycache__ -exec rm -rf {} +`); agora é parte do pipeline.

### Cluster fix da UX
- **v2.103.1**: o esquema "shared secret" da v2.103.0 forçava o operador a seguir uma ordem específica (cria no A → copia → cria no B colando). Quem criasse os dois lados sem coordenar ficava sem opção: ambos os tokens distintos, nenhum dos lados conhece o do outro, **e não tinha como editar** o peer pra alinhar. Bruno reportou exatamente esse trava em deploy real.
  - **Novo endpoint `PUT /api/v1/ha/peers/{id}/token`**: substitui o token de um peer existente (atualiza `api_token_hash` + `api_token_raw_encrypted` na mesma transação). Audit log `ha.peer.token_replaced`.
  - **`ha_service.set_peer_token(peer_id, token)`**: validação + bcrypt + cipher_service.encrypt + UPDATE. ValueError se token < 16 chars; False se peer não existe.
  - **UI `cluster.php`**: botão `🔑` na coluna de actions de cada peer (admin only) → abre prompt customizado pedindo o token. Substitui in-place sem precisar recriar peer + perder ID/histórico.
  - **Guia inline reescrito** pra cobrir 2 cenários: (A) sequencial recomendado, (B) já criou dos dois lados — instruções de uso do botão 🔑 pra alinhar.
- **`includes/custom_modals.php` ganhou `customPrompt`**: faltava no kit (só tinha `customConfirm`/`customAlert`). Mesmo padrão dos outros — variants, ESC fecha, click no backdrop fecha, Enter submete. Memória [[feedback-no-native-dialogs]] alinhada: agora 100% sem `window.prompt`.

### Cluster usable
- **v2.103**: **fix do bug que impedia o cluster de ficar verde** + cluster bidirecional autenticado.
  - **Bug**: `ha_service.check_peer` chamava `GET {api_url}/api/v1/health` mas o endpoint real é `/api/v1/healthz`. Resultado: o peer sempre devolvia 404, o monitor marcava `error`, e a UI mostrava cluster vermelho mesmo com tudo funcionando. Descoberto rastreando "não consegui ativar cluster dev↔teste". Fix: URL corrigida + status `not_found` distinto pra 404 (vs `error` genérico de outros HTTP codes), com mensagem do payload explicando o que aconteceu.
  - **Novo endpoint `/api/v1/cluster/peer-ping`** (router em `app/routers/cluster.py`): exige `X-Api-Token` (ou `Authorization: Bearer`), valida via `bcrypt.checkpw` contra qualquer `ha_peers.api_token_hash` registrado localmente, retorna `{ok, version, matched_peer_label, matched_peer_role}`. 401 se ausente ou inválido.
  - **`ha_service.create_peer` aceita `existing_token`**: modelo "shared secret por link" — A gera token T no primeiro create, operador cola T no espelho do B via `existing_token=T`. B reusa T (hash batendo dos dois lados). Quando ambos têm raw cifrado + hash do mesmo T, ambos podem fazer probe autenticado um no outro. `keep_raw` vira implícito (True) quando `existing_token` é fornecido — sem isso o token vira write-only e o probe falharia.
  - **`check_peer` prefere `/cluster/peer-ping`** quando o peer tem token raw cifrado guardado; cai pra `/healthz` quando não. Status novo `not_found` (404 no endpoint — geralmente "peer rodando versão sem cluster.py"). Payload inclui `error`, `probe_path`, `authenticated` pra debug. `check_peer` retorna o `error` separado pra UI consumir.
  - **UI `cluster.php`**: campo "Token existente (opcional)" no form de adicionar peer + `<details>` "Como ativar cluster em 3 passos" (1: cria no A sem token, copia T; 2: cria no B colando T; 3: clica Check). Status amber pra `not_found` + tooltip com a mensagem de erro do payload diretamente na célula (ícone ⓘ). Botão Check passa a mostrar probe path, autenticado sim/não, e detalhe do erro. Modal de "peer criado" diferencia "Token gerado" vs "Token reutilizado do espelho".

### Multi-tenant (fechamento)
- **v2.102**: **blocklist_exceptions multi-tenant** com PK composto. V29 recria a tabela com PK `(domain, org_id)` — sentinela `org_id = 0` representa allowlist global (organizações reais começam em 1 na sequence). Trade-off vs padrão das outras tabelas tenant (NULL = global): aqui o PK composto não aceita NULL, então usamos `0`; a diferença é encapsulada nos filtros do repo, API/UI não percebem.
  - `blocklist_exceptions_repo` ganhou `viewer_org_id` em `list_all`/`count`, e `org_id` em `add`/`remove`/`add_many`/`remove_many`. Função `list_domains` virou `list_domains_global` (só globais, pra alimentar o zonefile do Unbound).
  - `blocklist_sources_repo.domains_to_block()` filtra `WHERE org_id = 0` no subquery — preserva comportamento atual do blocklist file global. Exceções org-scoped existem no DB e UI/API as listam, mas só passam a aparecer no zonefile quando split-horizon de blocklist por view for implementado (TODO futuro além da V29).
  - Router `/api/v1/blocklist/exceptions`: helper `_resolve_target_org(body_org_id, viewer_org_id)` enforce tenant — system admin aceita qualquer `org_id`, user org-scoped sempre força a própria org (tentar outra = 403). DELETE aceita `org_id` via query param. Export CSV inclui colunas `org_id` e `scope`.
  - UI `blocklists.php`: nova coluna "Escopo" na tabela de exceções com badge global/`org_name` (lazy load via `/api/v1/organizations/`). Form ganha select de Org ("— Global —" como default). Delete envia `?org_id=N` resolvido do badge da linha.

### Auth / observabilidade
- **HA peer healthcheck autenticado**: docstring do `ha_peer_monitor` estava stale — funcionalidade já existia ponta-a-ponta (V20 `api_token_raw_encrypted` + service `keep_raw=True` + UI checkbox em cluster.php + check_peer envia X-Api-Token). Limpado o TODO falso, atualizada descrição do worker e adicionado badge 🔐 na listagem de peers em cluster.php pra deixar visível quais peers usam auth.
- **Secrets legados — worker one-shot**: `secrets_migrator.migrate_legacy_secrets()` roda no lifespan startup logo após a master key carregar. Idempotente: pra cada tabela conhecida (oidc_config.client_secret legacy → client_secret_encrypted; backup_destinations.secret_key inline), se valor != `enc:v1:%`, cifra in-place. No-op se master key ausente. Counters em log structlog (`secrets_migrator.migrated` quando algo é cifrado, `nothing_to_migrate` quando limpo). Resolve o último vestígio do TODO sobre secrets pré-cifragem.

### UX / digest
- **DigestSender cap 500 + CTA dashboard**: cap centralizado em `DIGEST_ITEMS_CAP = 500` (era 100, hardcoded em 4 lugares). Acima do cap, o digest HTML mostra banner amarelo "+N eventos não estão listados acima → Ver lista completa no dashboard" linkado a `/alerts.php`. CTA button "Abrir dashboard" no rodapé do email. Plain-text fallback ganha link `/alerts.php` na linha de truncado. Subject mantém formato.

### Limpeza
- **OIDC docstring**: removida menção stale a "NÃO usa PKCE (TODO se IdP exigir)" — PKCE S256 foi implementado em v2.101.0. Docstring atualizada pra refletir o estado real.

## 2026-05-26

Dia denso de features e fixes: **36 releases** (v2.39 → v2.74).

### UX
- **v2.101.3**: botão de pausar no widget Live Stream mini do dashboard. Estado persiste entre reloads via `localStorage['ls_mini_paused']`. Quando pausado: WS continua conectado mas mensagens são ignoradas no client; status badge mostra "⏸ pausado" e botão vira "▶ Retomar" (amber). Útil pra ler uma query específica no feed sem ela rolar pra fora. Mesmo padrão da página `/live_stream.php` (que já tinha pausa, mas em formato checkbox).

### Hotfix
- **v2.101.2**: drop-in pro Unbound do sistema — redireciona stderr → logfile pra LogWatcher receber as query logs. Em Debian/Ubuntu modernos, `unbound -d` (foreground) manda stderr pro journal do systemd e ignora `logfile:` no `unbound.conf`. Resultado: arquivo de log fica zerado, LogWatcher faz tail de vazio, Live Stream e tabela `query_logs` ficam sem dados. Fix: drop-in `[Service] StandardError=append:/var/log/unbound/unbound.log` em `/etc/systemd/system/unbound.service.d/logfile.conf`. Distribuído como `api_service/deployments/systemd/unbound.service.d/logfile.conf`. Aplicado automaticamente por `tools/install.sh` (instalação nova) e `tools/update.sh` (update). Descoberto em test server pós-v2.101.1: queries chegavam normalmente no Unbound mas o LogWatcher do dashboard ficava vazio.
- **v2.101.1**: `tools/update.sh` agora **falha hard** quando `uv` não está disponível ou `uv sync` retorna erro. Antes era só `warn` silencioso — o update prosseguia e o serviço caía no startup com `ModuleNotFoundError` (descoberto via boto3 ausente após v2.101.0). Para pular intencionalmente, exporte `SKIP_VENV_SYNC=true` (NÃO recomendado).

### Security + perf
- **v2.101**: três tarefas curtas:
  - **SECRETS_MASTER_KEY** configurada (Fernet 32-byte) em `/etc/unbound-dashboard/api-v1.env`. Warnings `cipher_service.no_master_key` e `secrets_store.master_key_missing` no startup desaparecem. OIDC `client_secret` e tokens HA gravados a partir daqui ficam cifrados em DB. **Secrets pré-existentes seguem plaintext até serem re-salvos** via UI.
  - **OIDC PKCE (RFC 7636)**: implementado fluxo S256. `_pkce_pair()` gera `code_verifier` (43-char base64url) + `code_challenge` (SHA256 base64url-no-padding). `build_auth_url` envia `code_challenge` + `code_challenge_method=S256` ao IdP e persiste o verifier junto do state. `handle_callback` extrai o verifier do state e inclui no POST do token exchange. Compat: IdPs sem suporte ignoram os params extras; IdPs que exigem (Entra ID moderno) passam. Sem flag de toggle — sempre ativo.
  - **Backup multi-S3 com cache de tarball**: `backup_destinations_service.upload_to_all()` agora compila o archive **1×** via `create_archive()` e passa o path pré-construído pra cada destination (`upload_backup(..., prebuilt_archive=..., cleanup=False)`). Cleanup central no `finally` após todos terminarem. `upload_backup` ganhou os params keyword `prebuilt_archive` e `cleanup` (backward-compat — default mantém comportamento single-dest). Para N destinos com DuckDB grande: era N builds (CPU+I/O), agora é 1.

### i18n JS (varredura)
- **v2.100**: migração focada dos toasts JS repetidos pro `window.t()`. Estratégia: só strings **cross-cutting** (que aparecem em 2+ páginas) viram chaves em `js.*`. Mensagens específicas de página (ex: "Disparar upload pro S3?", "Aprovar request #N?") seguem hardcoded — são inseparáveis do contexto da ação e migrar cria mais churn de keys que benefício.
  - Novas chaves em `js.*`: `saved_apply_hint`, `save_failed`, `removed`, `added`, `applied`, `sync_done`, `sync_failed`.
  - Migrações em audit.php, approvals.php, external_health.php, dns_security.php (3x), performance.php, notifications.php (2x), orgs.php (`Erro.` → `t('js.error_generic')`).
  - `'Erro ' + r.status` → `t('js.request_failed', {status})` (3 ocorrências em audit.php).

### Dashboard widgets (rodada 2)
- **v2.99**: 3 widgets novos no `/index.php`, antes do "Top 5 + Recent activity":
  - **Live stream mini** (col-span-8): tabela compacta de 6 queries via WebSocket `/api/v1/ws/queries`. Mesmo WS de `/live_stream.php`. Reconnect exponencial 2s→30s. Status badge "● ao vivo" / "reconectando…".
  - **Top países 24h** (col-span-4): top 5 países por hits bloqueados via `/api/v1/geoip/top-countries?action=blocked`. Refresh 60s.
  - **Multi-host overview** (full-width, condicional): só aparece quando `/api/v1/hosts` retorna `count > 0`. Grid de cards com QPS/CHR/alerts por host, badge org_name se aplicável. Summary "N/total OK". Refresh 30s.

### Multi-tenant (continuação)
- **v2.98**: estende multi-tenant pra `client_policies`. V28 ALTER TABLE `client_policies` ADD COLUMN `org_id`. `client_policies_repo.list_all()` e `summary()` aceitam `viewer_org_id` (segue mesma semântica de hosts/alerts/audit: NULL viewer = vê tudo, org viewer = NULL globais + própria org). `repo.create()` aceita `org_id` opcional. Router `/api/v1/policies` propaga `viewer_org_id` em **todos** os endpoints (list/get/create/update/delete + child ranges/blocks/allows). Helper `_ensure_tenant_access()` retorna 404 mascarado pra requests fora da org do viewer. POST aceita `org_id` no body; user org-scoped só cria pra própria org (403). UI: `client_policies.php` mostra badge pink `org_name` ou cinza `global` no header do card; modal "Nova Política" ganha select de Org (lazy load via `/api/v1/organizations/`).
- **Skip blocklist_exceptions**: schema tem `domain` como PK que conflitaria com (domain, org_id) composto. Tornar tenant requer rework não-trivial. Documentado como TODO em V28.sql.

### Polish + fix
- **v2.97**: limpeza pós-v2.96:
  - **/api/v1/observability/workers** agora enumera os **19 workers** (era 10) — incluindo NotificationPruner, AuditPruner, ExternalHealthPruner, PrometheusExporter, HAPeerMonitor, RestoreTestRunner, BaselineLearner, GeoBlockUpdater, DigestSender. Cada um com `tick_seconds`, descrição, `last_run` (best-effort de settings) e `extra` quando aplicável. Reflete corretamente o widget Workers do dashboard.
  - **Fix**: `Undefined array key "unwanted"` em [index.php:77](index.php#L77). A variável era setada mas nunca usada — `unbound_collector` escreve `unwanted_queries` e `unwanted_replies` separados, não `unwanted` total. Linha removida.

### Polish
- **v2.96**: polimento da rodada anterior:
  - **Login i18n**: hero + form 100% via `t()` (novo namespace `login.*` com ~24 chaves em pt-BR/en). Health badge tem `data-text-*` injetados pelo PHP — JS só lê. SSO button, "Esqueceu a senha?", "Acessar", labels, divider "ou", e tooltips dos toggles tudo localizado.
  - **Stagger animation**: widgets novos do dashboard ganharam stagger-0a/0b/0c (CSS) — Alerts/Storage/Workers entram em cascata antes dos KPIs, mantendo consistência visual.
  - **Workers health row**: linha compacta com chips coloridos por worker (verde=running, amber=done, slate=unknown). Summary `N/total ativos` com cor adaptada. Tooltip exibe descrição + last_run. Click no link → /observability. Fetch `/api/v1/observability/workers` 30s.
  - **Top 5 + Recent activity** (tabs): card com 3 abas — Top Domínios Bloqueados (`/analytics/top-domains?action=blocked`), Top Clientes (`/analytics/top-clients`), Atividade Recente (`/audit/admin/list`). Todas com janela 24h. Tab ativa carrega lazy; auto-refresh 60s só na aba ativa.

### Login redesign
- **v2.95**: `/login.php` redesenhado em split-pane (desktop) / single-card (mobile):
  - **Hero pane esquerdo** (lg+): mesh gradient animado + grid mask, logo com pulse ring, tagline + 4 bullet features (DoH/DoT, blocklists+anti-DGA, multi-tenant+RBAC, backup+observabilidade), versão + badge "Sistema operacional" (consulta `/api/v1/healthz` público).
  - **Form pane direito**: card glass com tema-aware (light/dark), animação de entrada, input com `autofocus`, toggle de visibilidade da senha (eye icon), CTA com seta animada.
  - **Top-right toggles**: PT/EN (POST pra `set_locale.php`) + dark/light (localStorage + class `.dark` no `<html>`). FOUC-proof — script de tema roda antes do Tailwind.
  - **Mobile**: hero some, form ocupa tela inteira, logo aparece dentro do card.
  - Loader original preservado; OIDC hash redirect + botão SSO preservados; recover link, error/success banners, 2FA redirect — todos intactos.

### Dashboard
- **v2.94**: dois widgets novos no topo de `/index.php`:
  - **Alertas Ativos**: count por severidade (critical/warning/info) + mensagem do mais recente + link pra resolução. Fetch de `/api/v1/alerts/list` a cada 30s.
  - **Saúde de Infra**: tamanho do arquivo DuckDB + disco livre/usado com barra de progresso + Redis ping (status + latência) + count de workers. Fetch de novo endpoint `/api/v1/host/storage` (DuckDB size via `os.stat`, disco via `shutil.disk_usage`, Redis via `redis.ping()`). Refresh 30s.

### Notifications + SSO
- **v2.93**: DigestSender HTML email + group mapping precedence por rank.
  - **HTML digest**: `email_notifier._send_via_smtp(html_body=...)` ganha `multipart/alternative` (text + html via `EmailMessage.add_alternative()`). Clientes texto-only ainda recebem o plain. `DigestSender` constrói tabela HTML com badges coloridos por severidade (critical=red, warning=amber, info=blue), tipo+mensagem+timestamp por evento, cap 100 itens com aviso de truncamento. Plain-text mantém o formato anterior como fallback.
  - **OIDC group precedence**: `_resolve_role_from_groups()` coleta TODAS as roles mapeadas pelos grupos do user no claim e retorna a de maior `_ROLE_RANK` (admin=4 > readonly_admin=3 > operator=2 > viewer=1). Antes era "primeira match na ordem do claim vence", o que podia derrubar admins pra viewer se o IdP listasse `["dns-viewers", "dns-admins"]` nessa ordem. Hint atualizada em `/sso.php`.

### Multi-tenant
- **v2.92**: estende multi-tenant pra `alerts` + `admin_audit`. V27 ALTER TABLE adiciona `org_id` em alerts e `actor_org_id` em admin_audit. Helper compartilhado `resolve_viewer_org_id(payload)` em `app/core/deps.py` (refatorado de hosts.py). `alert_repo.list_history()` e `list_filtered()` aceitam `viewer_org_id`; `admin_audit_service.list_filtered/export_csv/export_pdf` idem. `admin_audit.log()` resolve `actor_org_id` automaticamente do user no momento do log (snapshot). Endpoints atualizados: `/alerts/list`, `/notifications/feed`, `/notifications/list`, `/audit/admin/list`, `/audit/admin/export-csv`, `/audit/admin/export-pdf`. Mesma semântica de hosts: NULL = global (visível a todos), N = org N (visível a system admin + members da org N).

### i18n
- **v2.91**: i18n JS layer — `window.t(key, vars)` helper injetado em `includes/head.php` (serializa o dict da locale atual via `\App\I18n::all()`). Mesmo lookup dot-path + `{var}` interpolation do `t()` PHP. Nova seção `js.*` em `lang/{pt-BR,en}.php` com strings comuns de toast/alert (saved/error_generic/error_with/loading/confirm_delete/required_field/invalid_json/no_results/request_failed/unauthorized/copied). Migrações exemplo em `sso.php` (botão Salvar) e `notifications.php` (Preferências + Retention). Outras migrações JS seguem progressivamente — só `<script>` que vive em página com `require_once 'src/I18n.php'` ganha o helper automaticamente.

### Hotfix
- **v2.90.1**: fix — adiciona `require_once 'src/I18n.php'` em 14 páginas migradas nesta sessão (dns_security, geo_blocking, observability, external_health, analytics, anomalies, query_search, config, diagnostics, health, live_stream, exports, history, notifications). Sem o require o `t()` ficava indefinido e a página estourava Fatal error.

### Notifications
- **v2.90**: Notifications center — per-user prefs + daily digest por email. V26 `user_notification_prefs` (severity_min, categories JSON, digest_enabled, digest_hour, last_digest_sent_at). Endpoints `GET/PUT /api/v1/notifications/prefs` (sempre o user do JWT, não admin-only). Worker novo **DigestSender** (hourly): para cada user `digest_enabled` cuja `digest_hour` bate com a hora atual UTC e que ainda não recebeu hoje, agrega notificações das últimas 24h respeitando `severity_min` + filtro de `categories` (prefixos: `alert`, `anomaly_`), envia 1 email via SMTP. UI nova em `/notifications.php` (painel "Minhas Preferências" com selects + checkbox + hora). Audit: `last_digest_sent_at` atualizado a cada envio bem-sucedido.

### Multi-tenant
- **v2.89**: multi-tenant UI — listings filtradas por org. V25 ALTER TABLE `managed_hosts` ADD `org_id`. Service `managed_hosts.list_all(viewer_org_id)`: user com `org_id=NULL` (system admin) vê tudo; user com `org_id=N` vê só hosts `NULL` (globais) + da própria org. Router resolve `viewer_org_id` do DB a cada request. POST `/hosts` aceita `org_id`; user org-scoped só cria pra própria org (403 caso contrário). Novo endpoint `PUT /hosts/{id}/org` (admin global only). UI: badge "org name" ou "global" no card; `/users` tab em config.php ganha coluna **Organização** com dropdown (chama `POST /organizations/assign-user`). hosts.php form ganha select de Org (CRUD + edit).

### SSO
- **v2.88**: SSO group/role mapping — V24 ALTER TABLE `oidc_config` (`group_claim`, `group_mappings` JSON, `sync_role_on_login`). Callback OIDC extrai o claim configurado (suporta dot-path tipo `realm_access.roles` do Keycloak), intersecta com o JSON `{idp_group: local_role}` (admin/readonly_admin/operator/viewer) e usa a primeira role mapeada — fallback para `default_role` no auto-create. `sync_role_on_login=true` re-aplica a cada login pra users existentes (default OFF). UI nova em `/sso.php` (claim + textarea JSON + checkbox sync) com validação client-side. Audit log: `oidc.role_synced` quando role muda por sync.

### UX & UI (continuação)
- **v2.87**: i18n incremental — `diagnostics.php`, `health.php`, `live_stream.php`, `exports.php`, `history.php` migradas. 5 novas seções nos `lang/*.php` (~26 chaves). Total: **24 páginas** com `t()` — cobertura essencialmente completa do dashboard principal.
- **v2.86**: i18n incremental — `analytics.php`, `anomalies.php`, `query_search.php`, `config.php` migradas. config.php inclui 16 tab labels (Configurações Unbound, DoT/DoH, etc). 5 novas seções nos `lang/*.php` (~30 chaves). Total: **19 páginas** com `t()`.
- **v2.85**: i18n incremental — `dns_security.php`, `geo_blocking.php`, `observability.php`, `external_health.php` migradas (titles + section headers). 4 novas seções nos `lang/*.php` (~24 chaves). Total: **15 páginas** com `t()`.
- **v2.84**: i18n incremental — `backup_offsite.php`, `approvals.php`, `hosts.php`, `cluster.php` migradas (titles + section headers). 4 novas seções nos `lang/*.php` (~21 chaves). Total: **11 páginas** com `t()` (sidebar + audit/sso/performance + 4 da v2.83 + 4 desta).
- **v2.83**: i18n incremental — `index.php`, `alerts.php`, `threats.php`, `blocklists.php` migradas (titles + section headers + cards). 4 novas seções no `lang/pt-BR.php` e `lang/en.php` (~30 chaves). Tabs/labels JS dinâmicos seguem hardcoded (próximas iterações).
- **v2.77**: i18n incremental — `audit.php`, `sso.php`, `performance.php` migradas (titles + subtitles + tabs). Outras páginas progressivas.
- **v2.76**: i18n base — helper `t()` (PHP), arquivos `lang/pt-BR.php` e `lang/en.php`, toggle PT/EN no topbar (cookie 1 ano + session), `set_locale.php` endpoint. Detect: session → cookie → Accept-Language → default pt-BR. Sidebar 100% migrada; outras páginas migram progressivamente. Keys ausentes mostram a própria key (debug-friendly).

### Multi-tenant
- **v2.80**: organizações (infra-only) — V23 `organizations` (name/slug/description/is_active) + coluna `users.org_id` nullable, service CRUD, endpoints `/api/v1/organizations/*` (admin), página `/orgs.php` com banner de limitações conhecidas. **Não particiona** dados existentes nem implementa RBAC per-org ainda — só prepara infraestrutura pra próximas iterações.

### Auth, segurança & compliance
- **v2.82**: auto-discovery + provider presets em `/sso.php` — dropdown com **8 presets** (Google, Entra ID, Okta, Auth0, Keycloak, Authentik, Zitadel, Custom) que pré-preenchem issuer URL template + scopes + dicas inline de como registrar a app no IdP. Botão "Testar conectividade" chama novo endpoint `POST /api/v1/auth/oidc/probe` que faz GET no `.well-known/openid-configuration` + JWKS, retorna endpoints descobertos (authorization/token/userinfo/jwks_uri), match do issuer, scopes/algs suportados e contagem de chaves JWKS. Sem persistir nada — só valida o issuer antes do admin salvar.
- **v2.78**: wire de approvals expandido — `backup.upload_now` e `ha.peer.delete`. Total: **7 handlers** (dns/doh/ha-failover/geo/users/backup/ha-delete).
- **v2.75**: wire de approvals expandido — `geo_blocking.apply` e `users.delete` agora também passam por `enforce_approval`. Total: 5 handlers registrados (dns/doh/ha/geo/users).
- **v2.74**: múltiplos destinos S3 (multi-cloud redundância); cada destino com secret cifrado via cipher_service. `BackupUploader` itera priority DESC.
- **v2.71**: `cipher_service` Fernet + cifra OIDC `client_secret` e tokens HA peers (env `SECRETS_MASTER_KEY`). Endpoint admin `/secrets-store/status`.
- **v2.72**: wire automático de workflow approval em `dns_security.apply`, `doh_inbound.gen_cert`, `ha.failover` — endpoints respondem 202 + handler dispatcha após aprovação.
- **v2.69**: rate-limit per-token (slowapi `key_func` custom Bearer/X-Api-Token → sha256[:24]; fallback IP).
- **v2.67**: workflow approval infra (V18 `approval_requests`, TTL 24h, requester bloqueado de aprovar próprio pedido) + página `/approvals.php`.
- **v2.65**: SSO OIDC Authorization Code flow (Google/Entra/Keycloak/Authentik) + página `/sso.php`. JWKS cache 1h.
- **v2.60**: V15 `admin_audit` + LGPD report (queries por IP cliente) + página `/audit.php` 3 abas. Worker `AuditPruner` 1x/dia.
- **v2.56**: DNS hardening v2 — 12 toggles (hide-identity, aggressive-nsec, harden-suite, deny-any, ECS off, TLS strict).

### Backup & DR
- **v2.73**: workers de manutenção — `RestoreTestRunner` 1x/sem + `ExternalHealthPruner` 1x/dia (retention configurável).
- **v2.68**: restore-test S3 — baixa archive, abre DuckDB read-only, valida schema+users+tables.

### HA & cluster
- **v2.64**: V16 `ha_peers` + healthcheck 30s (`HAPeerMonitor`) + manual failover. Promove só registro; rede continua external.

### Observabilidade
- **v2.70**: SLA externo — V19 `external_health_probes` + script standalone `tools/external_healthcheck.py` (zero deps via socket UDP cru) + página `/external_health.php`.
- **v2.63**: 13 Gauges Prometheus expostos (`unbound_qps`, latency por percentil, cache mem, alerts por severidade etc) + worker `PrometheusExporter` + dashboard Grafana JSON pronto pra importar.
- **v2.58**: página `/performance.php` — toggles (prefetch/serve-expired/...), TTLs, cache sizes, P50/P95/P99 reais via histograma.
- **v2.40**: página `/observability.php` — KPIs + séries temporais + status dos workers.

### Anomalias & threats
- **v2.81**: UI completa do baseline ML em `/anomalies.php` — painel **Baseline ML** com heatmap 24×7 (intensidade = avg_queries por bucket, hora atual destacada em amber), toggle enable/pause, sliders sigma/window-weeks/min-samples, botão "Re-treinar agora", status do último treino + bucket coverage. Detecções recentes ganharam **filtro por detector** (8 categorias, incluindo `baseline_high`/`baseline_low`) e **ack inline** (✓ por linha + bulk "Resolver todas" real via `/anomaly/resolve-all`). 4 endpoints novos: `GET /anomaly/baseline`, `GET /anomaly/baseline/current`, `POST /anomaly/baseline/learn-now`, `POST /anomaly/resolve-all`. `_ANOMALY_KEYS` e `DEFAULTS` agora incluem as 4 chaves de baseline (`enabled`, `sigma`, `window_weeks`, `min_samples`).
- **v2.79**: ML/baseline learning — V22 `anomaly_baseline` (168 buckets de hour_of_day × day_of_week), worker `BaselineLearner` 1x/dia recalcula média + stddev de `hourly_stats` das últimas N semanas (default 4). Detector novo `_check_baseline_deviation` alerta se volume da última hora completa está fora de N desvios padrão do baseline do mesmo bucket. Captura sazonalidade (8h-seg ≠ 8h-dom). Opt-in via `anomaly_baseline_enabled`.
- **v2.55**: anomaly v2 — sobe 3→6 detectores (DNS tunneling, beaconing, suspicious TLDs) + whitelist (V14) + UI estendida.
- **v2.54**: GeoIP ASN enrichment + card "Top ASNs" em `/threats.php`.
- **v2.54.1**: fix mapa-múndi (CDN path errado).
- **v2.53**: geo-blocking — V13 `geo_blocks` + `access-control: <cidr> refuse` no Unbound. Source iwik.org GeoLite2.
- **v2.52**: GeoIP visualização — mapa-múndi choropleth + filtro de país em buscas.
- **v2.47**: GeoIP enrichment v1 (ip-api.com) + top países em ameaças.

### DNS features
- **v2.59**: DoH inbound v2 — cert info detalhado (subject/issuer/SAN/fingerprint/expiry colorido) + gen self-signed admin.
- **v2.48**: qname-minimisation toggle + status DoH inbound.
- **v2.46**: rate-limit per-IP/per-domain configurável via UI.
- **v2.41**: página `/dns_security.php` — DNSSEC + upstream DoT.

### Notifications & UX
- **v2.61**: mobile responsive — sidebar vira drawer com backdrop em <768px; padding/fonts adaptam.
- **v2.57**: WebSocket push pro bell + página `/notifications.php` + retention + worker `NotificationPruner`.
- **v2.51**: WebSocket `/api/v1/ws/queries` real-time + página `/live_stream.php`.
- **v2.50**: bell icon no topbar + feed unificado de notificações.
- **v2.44**: command palette Ctrl+K + atalhos vim-style.

### Reports & data
- **v2.66**: PDF reports via reportlab — `/lgpd-report.pdf` + `/audit/admin/export-pdf`.
- **v2.49**: allowlist bulk ops + CSV import/export.
- **v2.45**: página API & Integrações + endpoints Grafana datasource.

### Multi-host & integrações
- **v2.43**: push config (blocklists + policies) master → agents; modal de detalhe do host com botão.
- **v2.43.1/.2**: fixes do chip "Top" em ameaças (filtro server-side resolve IPv6 + cauda longa).
- **v2.42**: webhook Telegram Bot API como tipo nativo.

### Tests & quality
- **v2.62**: +21 testes (alerts_broker, admin_audit, dns_security blocks, doh_inbound) + fix baseline (V15) + test_threats xfail. Suite 161 passed.

### Storage & retention
- **v2.39**: hourly rollups (V12 `hourly_stats`) + prune configurável de query_logs + worker `QueryLogPruner`.

## v2.38.0 — 2026-05-25

### feat(backup-offsite): upload automático pra S3-compatible

Backup local (`/var/backups/unbound-dashboard/`, restore-backup.sh)
continua intacto. Adiciona camada offsite: worker que sobe DuckDB +
configs do Unbound pra qualquer storage S3-compatible.

**Compatível com**: AWS S3, MinIO, Wasabi, Cloudflare R2, Backblaze B2
— todos usam mesma API. Endpoint vazio = AWS default; demais usam URL
custom.

Conteúdo do tarball:
- `duckdb/unbound_dash.duckdb` (banco inteiro)
- `etc/unbound/unbound.conf`
- `etc/unbound/includes/*.conf` (modular conf, blocked_domains,
  anti_doh, views, etc)
- `src/data/{settings,blocklist,local_records}.json`

#### Settings (chaves `backup_s3_*` em `settings`, sem nova migration)

`backup_s3_enabled`, `endpoint`, `bucket`, `region`, `prefix`,
`access_key`, `secret_key`, `retention_count` (mantém só N mais
recentes no remote), `schedule_hours` (default 24h). Credenciais
armazenadas plaintext (mesmo padrão do SMTP existente).

Status pós-upload (`backup_s3_last_*`): `upload_at`, `status`
(`ok`/`error`), `error`, `size_bytes`, `key`.

#### Worker `BackupUploader`

Loop horário; se `enabled='1'` E elapsed_hours >= `schedule_hours`,
dispara upload via `run_in_executor` (boto3 sync). Falha não derruba
o worker.

#### Retenção remota

Lista objects no prefixo, ordena por `LastModified` desc, deleta
todos exceto os N mais recentes (`retention_count`). Roda em cada
upload pra manter o bucket limpo.

#### Endpoints `/api/v1/backup-offsite`

- `GET /settings` — config + status + defaults. `secret_key` retorna
  vazio, vem mascarado em `secret_key_masked` (•••• + 4 chars finais)
- `PUT /settings` — atualiza. `secret_key` vazio = preserva o anterior.
- `POST /test` — `head_bucket()` valida credenciais + bucket existe
- `POST /upload-now` — dispara upload imediato (não respeita schedule)
- `GET /history?limit=100` — lista objects no bucket ordenados desc

Capabilities: `config.read_sensitive` (GET) / `config.write` (PUT/POST).

#### UI `/backup_offsite.php` (admin only)

- Painel de status (verde/âmbar) + toggle ativar/pausar
- 4 cards: último upload / status / tamanho / key remota
- Form completo com placeholder de exemplo pra cada provider
  (AWS/MinIO/Wasabi/R2/B2)
- Botões: Testar conexão / Upload agora / Salvar
- Tabela de backups no bucket (key, tamanho, quando) com Atualizar

Sidebar (admin): nova entrada "Backup S3" abaixo de "Anomalias".

## v2.37.0 — 2026-05-25

### feat(cache): flush total + lookup + reload (sem restart) na página de Cache

A `cache.php` já tinha stats, distribuição de TTL, top types/tlds e
flush por domínio. Agora ganha 3 ações cirúrgicas que evitam o restart
pesado pra mudanças de config / limpeza:

- **Flush total** (admin) — `unbound-control flush_zone .` esvazia
  rrset+msg+key caches inteiros. Não reinicia o daemon. Hit ratio cai
  pra ~0% temporariamente. Confirmação dupla.
- **Lookup** (qualquer user) — `unbound-control lookup <domain>` retorna
  delegation point, NS records cacheados, TTL atual, DNSSEC chain. Útil
  pra diagnosticar por que um domínio resolve do jeito que resolve.
- **Reload config** (admin) — `unbound-control reload` recarrega o
  unbound.conf sem perder o cache em memória. Alternativa mais leve
  ao restart via systemctl (que é o que o "Aplicar & Recarregar" das
  outras páginas usa).

Endpoints novos:
- `POST /api/v1/cache_flush_all.php`
- `POST /api/v1/cache_lookup.php`
- `POST /api/v1/cache_reload.php`

Cada um valida CSRF, escapa input (lookup: pattern domain estrito),
chama `unbound-control` via sudo, retorna stdout. Padrão idêntico
ao `api/cache_flush.php` existente.

Smoke test em prod: flush_zone . removeu 17.300 rrsets + 7.280 messages
+ 159 key entries num só comando.

## v2.36.0 — 2026-05-25

### feat(anomaly): detector heurístico de DGA, NXDOMAIN spike e novos clientes

Novo worker `anomaly_detector` reusa o sistema de `alerts` existente
(mesmo dedupe, mesmo webhook). Opt-in via setting `anomaly_enabled`
(default off — pra evitar barulho na primeira instalação).

#### 3 detectores no MVP

- **DGA** (Domain Generation Algorithm): Shannon entropy ≥ 3.5 bits/char
  no label esquerdo + length ≥ 12 chars. Cliente com X+ domínios
  suspeitos vira alerta. Pega botnets, malware C2, alguns CDNs
  agressivos (que aparecem como falso positivo — daí thresholds
  configuráveis).
- **NXDOMAIN spike por cliente**: ratio nxdomain_upstream / total ≥ 50%
  com count mínimo 20 em janela 10min. Indica DGA em ação, typosquatting
  bot, ou alguém digitando errado no Windows.
- **Cliente novo**: client_ip ativo nas últimas 24h que NÃO apareceu
  em baseline (7d antes), com mínimo 10 queries. Detecta device novo
  / vazamento de DNS / IPv6 com privacy extensions (atenção: SLAAC
  com privacy gera ruído em redes IPv6).

Cada detecção vira um alerta com `type` composto (ex:
`anomaly_dga:192.168.1.10`), severidade calibrada (DGA/NXDOMAIN =
warning, novo cliente = info). Dispara webhook configurado.

#### Endpoints `/api/v1/analytics/anomaly`

- `GET /settings` — settings atuais + defaults
- `PUT /settings` — atualiza thresholds (cap `config.write`)
- `GET /recent?include_resolved=` — lista detecções (filter por type LIKE 'anomaly_%')
- `POST /run-now` — executa os 3 checks imediatamente (não depende de `anomaly_enabled`)

#### UI `/anomalies.php`

- Painel de status (verde/âmbar) com toggle ativar/pausar
- 10 limiares editáveis agrupados por detector
- Botão "Rodar agora" pra disparar manual
- Tabela com últimas 100 detecções (filtro: incluir resolvidas)

Worker registrado no lifespan + sidebar com entrada "Anomalias".

## v2.35.0 — 2026-05-25

### feat(query-search): busca avançada em query_logs com export CSV

Nova página `/query_search.php` — filtros combinados sobre 20M+ queries
indexadas no DuckDB. Substituível ao "logs.php" pra investigação real
(esse continua só pra ver journald/syslog do daemon).

**Filtros AND**: janela (1h/24h/7d/30d), cliente IP (LIKE), domínio
(LIKE), tipo de query, ação. Paginação 25/50/100/200 por página.
Tabela mostra timestamp + cliente + domínio + tipo + ação colorida.

**Export CSV** até 100k linhas por busca (mesmo conjunto de filtros).
Download programático via fetch+blob — header `Authorization: Bearer`
não passaria num `window.open` direto.

#### Endpoints novos `/api/v1/analytics`

- `GET /queries/search?window=&client_ip=&domain=&query_type=&action=&page=&per_page=`
- `GET /queries/export-csv?...&limit=N` (até 100k)

Capability `dashboard.read`. Auto-busca inicial mostra as últimas
queries da janela default (24h).

## v2.34.0 — 2026-05-25

### feat(analytics): página de análise profunda com janela ajustável

Nova página `/analytics.php` consolidando o que estava fragmentado entre
`threats.php` (só bloqueios) e `history.php` (só performance). Janela
deslizante única (1h / 24h / 7d / 30d) reaplica filtro em todos os cards.

#### Conteúdo

- **4 cards de métricas:** queries totais, bloqueadas (% do total), cache
  hit (% hit rate), domínios únicos (+ contadores secundários: clientes
  únicos, NXDOMAIN upstream)
- **Line chart temporal** com 3 séries sobrepostas: total / bloqueadas /
  cache hit, bucketing aritmético (60s/15min/2h/8h conforme janela)
- **Donut de ações:** resolved / cached / blocked / nxdomain_upstream
- **Donut de tipos de query:** A / AAAA / HTTPS / PTR / SVCB / SRV / MX
  / NS / TXT / SOA / CNAME / WKS / HINFO (todos os tipos presentes)
- **Top 20 domínios** com filtro de ação (todas / blocked / resolved /
  nxdomain)
- **Top 20 clientes** com total + ratio bloqueado + domínios únicos

#### Endpoints novos `/api/v1/analytics`

- `GET /summary?window=...`
- `GET /timeseries?window=...` (com bucket auto-escolhido)
- `GET /by-query-type?window=...`
- `GET /action-breakdown?window=...`
- `GET /top-domains?window=...&limit=...&action=...`
- `GET /top-clients?window=...&limit=...`

Capability `dashboard.read` (qualquer usuário autenticado).

Sidebar ganhou entrada "Analítico" abaixo de "Pol. Cliente".

#### Detalhes técnicos

- Bucketing via `CAST(timestamp / bucket AS BIGINT) * bucket` — divisão
  pura `/` em DuckDB é float, dava 1 bucket por segundo. Fix valida
  buckets corretos pra janelas 1h até 30d.
- DuckDB com zonemap por coluna roda agregação sobre 20M+ rows em
  ~ms; nenhuma índice precisa ser criado.

## v2.33.0 — 2026-05-25

### feat(policies): regras DNS por cliente (split-horizon)

Política DNS aplicada por IP/CIDR. Cada **policy** define um grupo lógico
(ex.: "kids", "iot", "office") com:

- **Ranges** — 1..N CIDRs/IPs que determinam quais clientes caem na policy
- **Blocks** — domínios extras pra bloquear (`always_nxdomain` na view)
- **Allows** — exceções específicas (`transparent` na view)

Modelo de herança fixo (decisão MVP): policies **sempre herdam o global**.
O `view: view-first: yes` do Unbound faz lookup cair pro `server:` global
quando a view não tem regra. Clientes numa policy bloqueiam (blocklists
globais ativas + blocks da policy) MENOS (allowlist global + allows da
policy). Clientes fora de qualquer policy caem no global puro.

**Fora do MVP:** override total (cada policy seleciona suas sources do
catálogo), time-based blocking (janelas horárias). Encaixam por cima.

#### Schema V11

- `client_policies` (id, slug, name, description, enabled, sort_order)
- `client_policy_ranges` (id, policy_id, cidr, label) — múltiplos por policy
- `client_policy_blocks` (policy_id, domain) — PK composta
- `client_policy_allows` (policy_id, domain) — PK composta

Capabilities reaproveitadas (`blocklist.read`/`blocklist.write`).

#### Endpoints REST `/api/v1/policies`

- `GET /` — lista resumo com counts
- `GET /{slug}` — detalhe completo (ranges + blocks + allows)
- `GET /full-enabled` — consumido pelo PHP pra gerar `views.conf`
- `POST /` — cria policy
- `PATCH /{slug}` — atualiza name/description/enabled
- `DELETE /{slug}` — deleta (cascateia ranges/blocks/allows)
- `POST/DELETE /{slug}/ranges` — gerencia CIDRs
- `POST/DELETE /{slug}/blocks` — gerencia bloqueios extras
- `POST/DELETE /{slug}/allows` — gerencia exceções

#### Gerador Unbound

`UnboundConfigManager.generateViewsConf()` consulta a API (via
`$_SESSION['api_jwt']`) e escreve `/etc/unbound/includes/views.conf` com:

```
server:
    access-control-view: <CIDR> <slug>
    ...

view:
    name: "<slug>"
    view-first: yes
    local-zone: "<block>." always_nxdomain
    local-zone: "<allow>." transparent
```

`access-control-view` (em `server:`) e blocos `view:` (top-level) num
único arquivo, incluído no `unbound.conf` principal via `generateRawConfig`.
Validação CIDR/slug com regex antes de escrever no conf (defesa anti-injection).

Sintaxe validada com `unbound-checkconf` no smoke test (policy "kids"
com `192.168.20.0/24` + bloqueio `tiktok.com` + allow `educa.gov.br`
= aprovado).

#### UI

Nova página `/client_policies.php`:

- Cards por policy com 3 colunas inline (ranges, blocks, allows), cada
  uma com add/remove inline e formato pattern-validated
- Toggle ativar/pausar policy, delete policy completa
- Modal "Nova Política" com validação de slug (regex `^[a-z][a-z0-9_-]{1,49}$`)
- Banner "Aplicar & Recarregar Unbound" aparece após qualquer mudança
- Reusa `api/blocklist_apply.php` (que já chama `applyConfig` regerando
  blocked_domains + views + restart Unbound)

Sidebar atualizada com entrada "Pol. Cliente" abaixo de Blocklists.

## v2.32.0 — 2026-05-25

### feat(blocklists): múltiplas fontes simultâneas + allowlist (ANATEL fica separada)

Antes: o "catálogo de inteligência" (StevenBlack/Hagezi) **não bloqueava** no
Unbound — só populava o DuckDB pra busca. O único bloqueio real era a lista
ANATEL judicial. Pra escolher entre StevenBlack OU Hagezi era toggle exclusivo
(uma de cada vez).

Agora: schema multi-source com toggles independentes `indexar` (popula DuckDB)
e `bloquear` (gera `local-zone always_nxdomain` no Unbound). 10 presets curados,
todos podem estar ativos simultaneamente:

- **anatel** (Judicial) — bloqueio judicial brasileiro, fica em `blocklist.php`
  (página dedicada, **não unificada** com as outras)
- **stevenblack, hagezi_light/normal/pro** (Malware/Adware)
- **oisd_small/big, adguard_dns** (Malware/Adware)
- **nocoin, easyprivacy** (Tracking)

Outras peças:

- **Allowlist global** (`blocklist_exceptions`): domínios que sempre resolvem
  normalmente, mesmo presentes em alguma fonte. Sobrescreve via
  `local-zone transparent` no final do `blocked_domains.conf`.
- **Worker `blocklist_syncer`**: roda 1x/h, baixa fontes com `index_enabled=true`,
  parsea por formato (`hosts`, `domains`, `unbound_localzone`, `adblock`),
  popula em batches de 5000.
- **Nova UI** (`blocklists.php`): 3 tabs — Fontes (toggles + sync individual),
  Busca no Catálogo (paginada, reaproveita `/api/v1/blocklist/search`),
  Exceções (CRUD da allowlist).
- **Página ANATEL** (`blocklist.php`) preservada intacta — escolha de UX
  deliberada porque ANATEL é mandato judicial, não opção do usuário.

Migrations:

- **V9**: tabelas `blocklist_sources` (catálogo + flags + last_sync),
  `blocklist_entries` (PK composta `domain+source_slug`, substitui
  `blocklist_domains`), `blocklist_exceptions` (allowlist). Backfill
  preservando estado: ANATEL → `anatel`; catálogo atual → fonte do
  setting `blacklist_source`.
- **V10**: corrige URL do Hagezi Normal (`normal.txt` → `multi.txt`,
  o repo renomeou).

Endpoints REST novos em `/api/v1/blocklist`:

- `GET /sources` — lista todas as fontes com flags e estatísticas
- `PATCH /sources/{slug}` — toggle `index_enabled` e/ou `block_enabled`
- `POST /sources/{slug}/sync` — força sync sob demanda
- `GET /domains-to-block` — união de sources com `block_enabled=true`
  menos exceptions (consumido pelo PHP `UnboundConfigManager` pra gerar
  o `blocked_domains.conf`)
- `GET /exceptions`, `POST /exceptions`, `DELETE /exceptions/{domain}`

`UnboundConfigManager.generateBlockedDomainsConf` agora consulta a API
(via `$_SESSION['api_jwt']`) e gera 3 seções: blocklists ativas (NXDOMAIN),
bloqueios manuais legados (formato `static + 0.0.0.0`, preservado), e
allowlist (`transparent`). Fallback pro caminho legado quando sessão sem
JWT (cron CLI).

Validação smoke: gerado `blocked_domains.conf` com 444.635 NXDOMAIN
(ANATEL+Hagezi normal) + 1 transparent, `unbound-checkconf` aprovou
sem erros.

## v2.31.4 — 2026-05-25

### fix(api): log_watcher lia `/var/log/syslog` (vazio desde `use-syslog: no`) → alerta `no_queries` em loop

Após o commit `661d148` mudar o Unbound pra escrever direto em
`/var/log/unbound/unbound.log` (em vez de syslog), o `LogWatcher` do
api_service continuou hardcoded em `/var/log/syslog`. Resultado: zero
queries ingeridas em DuckDB, `stats_aggregator` parado em `total=87219`,
e `AlertChecker` disparando `no_queries` a cada tick.

A setting já existia (`settings.unbound_log`, default `/var/log/unbound/unbound.log`,
overridável via env `UNBOUND_LOG`) — só não estava sendo passada no
construtor. Fix de uma linha em `app/main.py:99`:
`LogWatcher(log_path=settings.unbound_log)`. Default do próprio
`LogWatcher` também atualizado pra refletir realidade atual.

Validação ao vivo: ingestão voltou imediatamente (1211 queries em 60s),
alerta id=14 auto-resolvido na tick seguinte.

## v2.31.3 — 2026-05-22

### feat(dashboard): widget de portas DNS/DoT/DoH no painel de saúde

Painel "Sistema saudável" no `/index.php` mostrava só serviços (unbound,
api, redis, apache) e uptime. Adicionei segunda linha com chips das
portas que o Unbound realmente está escutando — verde quando UP,
vermelho quando inativa.

**Detecção** (PHP inline no index.php):

- Lê `tls-port` e `https-port` direto do `/etc/unbound/includes/general.conf`
  via regex leve (sem precisar parseConfig completo).
- `ss -lntup` retorna sockets TCP + UDP com owner do processo.
- Cruza: mostra apenas portas relevantes (53, DoT, DoH) com owner
  `unbound`. Distingue cores e protocolo.

**Visual**:

```
Portas:  ● DNS 53 [UDP/TCP]   ● DoT 853 [UDP/TCP]   ● DoH 8443 [UDP/TCP]
```

- Chip verde + bolinha = listening + owner `unbound`
- Chip vermelho = config tem a porta, mas socket não está aberto
  (Unbound não recarregou, conflito, etc)
- Tooltip mostra protocolo + owner pra debug
- DoT/DoH só aparecem se `tls-port`/`https-port` estão configurados

Atualiza a cada page load (mesma cadência do resto do painel).

Smoke ao vivo neste server:
```
Porta 53:   UDP/TCP — owner=unbound  ✓
Porta 853:  UDP/TCP — owner=unbound  ✓
Porta 8443: UDP/TCP — owner=unbound  ✓
```

VERSION 2.31.2 → 2.31.3.

---

## v2.31.2 — 2026-05-22

### fix(logs): Unbound não escrevia em arquivo + dashboard apontava pro path errado

User notou que não havia entrada de `/var/log/unbound.log` em nenhum
arquivo de config. Investigação revelou bugs encadeados:

1. **Unbound sem `logfile:`**: usando default stderr → journald. Arquivo
   `/var/log/unbound.log` existia vazio, e o dir `/var/log/unbound/` tinha
   logs antigos de 1º mai (config sumiu naquele dia, ninguém notou).
2. **Dashboard apontava pro path errado**: `live_log.php`, `health.php`,
   `logs.php` e sudoers referenciam `/var/log/unbound.log` (plano), mas
   o dir owned por unbound é `/var/log/unbound/` (subdir).

**Fix em 3 frentes**:

- **`generateModularConfigs`** ganha 2 diretivas novas no `general.conf`:
  ```
  use-syslog: no
  logfile: "/var/log/unbound/unbound.log"
  ```
  Forçando escrita em arquivo (não mais só journald).
- **Refs alinhadas** pro path correto:
  - `api/live_log.php`: `/var/log/unbound/unbound.log`
  - `health.php`: idem
  - `logs.php`: tenta o subdir primeiro, plano como fallback legado
  - `sudoers`: nova entry `tail -n 300 /var/log/unbound/unbound.log`
    (mantém o legado pra compat).

Smoke ao vivo: após apply + restart, novos eventos aparecem em tempo
real em `/var/log/unbound/unbound.log`. `logs.php` e o live log do
dashboard voltam a funcionar.

VERSION 2.31.1 → 2.31.2.

---

## v2.31.1 — 2026-05-21

### fix(tls): aplicar regras AppArmor automaticamente em gerar/upload/importar

Servidor de teste do user instalou v2.31.0, importou cert Let's Encrypt
pelo botão novo, mas Unbound entrou em loop:

```
error: error for private key file: /etc/unbound/certs/dashboard.key
error: Error in SSL_CTX use_PrivateKey_file ... Permission denied
fatal error: could not set up listen SSL_CTX
```

Mesmo problema do AppArmor que vimos no v2.30.3: o profile distro do
Unbound só permite `/etc/unbound/*.key*` (sem subdir). O snippet em
`system/apparmor/usr.sbin.unbound.local.snippet` continuava sendo
aplicação **manual** — fácil de esquecer.

**Fix**: novo helper `tools/setup-apparmor-certs.sh` idempotente que:

- Se `/etc/apparmor.d/usr.sbin.unbound` não existe (CentOS, Alpine,
  sistemas sem AppArmor) → exit 0, no-op.
- Se as regras já estão em `local/usr.sbin.unbound` (marker comment) →
  skip, no-op.
- Senão: anexa as 5 linhas necessárias e roda `apparmor_parser -r`.

Invocado **automaticamente** pelo `TlsCertManager` nos 3 flows que
instalam cert:
- `generateSelfSigned` → `_installFromTmp` chama `_ensureApparmorRules`
- `uploadCert` → idem
- `importFromLetsEncrypt` → chama direto antes do install

Sudoers ganha 1 entry: `bash /var/www/.../tools/setup-apparmor-certs.sh`.

Smoke: rodei o helper 2x — segunda vez disse "regras já presentes",
não duplicou.

VERSION 2.31.0 → 2.31.1.

---

## v2.31.0 — 2026-05-21

### feat(tls): botão "Importar Let's Encrypt" — detecta + copia + auto-renova

Antes pra usar cert LE no DoT/DoH precisava sequência manual de comandos:
`install`, marker file, deploy hook, etc (documentado no v2.30.6). Agora
tudo via UI.

**Backend** (`TlsCertManager`):

- `listLetsEncryptLineages()` — roda `sudo ls /etc/letsencrypt/live/`,
  filtra dirs válidos (têm ponto = domínio), retorna ordenado.
- `importFromLetsEncrypt($lineage)`:
  - `install` do `fullchain.pem` → `/etc/unbound/certs/dashboard.crt`
    (0644 unbound:unbound)
  - `install` do `privkey.pem` → `/etc/unbound/certs/dashboard.key`
    (0640 unbound:unbound)
  - Escreve marker `/etc/unbound/certs/.le-lineage` com o nome da
    lineage (pro deploy hook saber qual processar nas renovações)
  - Instala o hook em `/etc/letsencrypt/renewal-hooks/deploy/`
- Constantes: `LE_LIVE_DIR`, `LE_MARKER`, `LE_HOOK_SRC`, `LE_HOOK_DST`.

**Deploy hook reescrito** (`system/letsencrypt/unbound-dashboard-deploy.sh`):

- Em vez de `DASHBOARD_DOMAIN` hardcoded, agora **lê o marker file**
  e age só pra essa lineage. Permite trocar a lineage pela UI sem
  editar o script.

**Sudoers** ganha 6 entries novas:

- `ls /etc/letsencrypt/live` (com e sem trailing slash)
- `install -o unbound -g unbound -m 0644 /etc/letsencrypt/live/*/fullchain.pem` → managed crt
- `install -o unbound -g unbound -m 0640 /etc/letsencrypt/live/*/privkey.pem` → managed key
- `install -o unbound -g unbound -m 0644 <tmp>/unbound_le_lineage` → `/etc/unbound/certs/.le-lineage`
- `install -m 0755 system/letsencrypt/unbound-dashboard-deploy.sh` → renewal-hooks/deploy/

**UI** no tab-tls:

- Novo botão azul "🔁 Importar Let's Encrypt" — só aparece se há
  lineages detectadas em `/etc/letsencrypt/live/`.
- Modal com radio buttons listando cada lineage (primeira selecionada
  por default).
- Após import: applyConfig auto-preenche `tls-service-pem`/`-key`
  no `unbound.conf`. User só precisa reiniciar Unbound.

**Handler** `tls_import_letsencrypt` em config.php.

Smoke ao vivo: detectou 2 lineages (anablock + dashboard), importou
dashboard.redeconexaonet.com com sucesso, marker + hook instalados.

VERSION 2.30.8 → 2.31.0.

---

## v2.30.8 — 2026-05-21

### chore(ui): customConfirm/customAlert reutilizável + adeus dialogs nativos do config.php

`/hosts.php` já usava modais customizados (v2.22.3), mas `/config.php`
voltou a usar `window.confirm()` e `window.alert()` nativos do
browser conforme features foram adicionadas (especialmente TLS).

**Refatoração**:

- Novo partial `includes/custom_modals.php` extraído do JS inline
  do `hosts.php`. Define `window.customConfirm` e `window.customAlert`
  como Promise-based. Guarded contra inclusão dupla.
- `/hosts.php` agora faz `include 'includes/custom_modals.php'`
  em vez de inline (cleanup de ~100 linhas duplicadas).
- `/config.php` ganha o include + 6 substituições:
  - "Reverter última mudança" (netplan rollback) — `data-confirm`
  - "Remover LO.1" — `data-confirm`
  - "Gerar Self-Signed" / "Upload PEM" (guard LE) — `data-tls-action`
  - "Aplicar update do sistema" — `customConfirm` JS
  - "Revogar API token" — `customConfirm` JS
  - 3 alerts JS (`Erro ao gerar token`, `Erro ao revogar`, `Erro ao
    iniciar restore`) — `customAlert`.

**Padrão `data-confirm`** novo: qualquer `<button data-confirm="...">`
ganha confirm automático via JS handler em `config.php` (sem precisar
PHP gerar inline). Atributos: `data-confirm-title`, `data-confirm-variant`
(`info|warning|danger`), `data-confirm-ok-label`, `data-pre-click` (eval
opcional pra side effects tipo `setIfaceName(...)`).

Zero `alert()` / `confirm()` nativos remanescentes em config.php ou
hosts.php.

VERSION 2.30.7 → 2.30.8.

---

## v2.30.7 — 2026-05-21

### fix(tls): proteção contra sobrescrever cert Let's Encrypt

Cenário: user instalou cert Let's Encrypt manualmente em
`/etc/unbound/certs/dashboard.{crt,key}` (v2.30.6), tudo funcionando.
Depois clicou em "Gerar Self-Signed" na UI achando que era teste —
o handler sobrescreveu o cert LE com um self-signed, e DoH/DoT
pararam de funcionar pros clientes (browsers recusam self-signed).

**Detecção** (`TlsCertManager`):

- `_readCertInfo` agora extrai também o `issuer` do cert.
- Novo campo `is_letsencrypt` no `getStatus()` que retorna `true`
  quando o issuer contém "Let's Encrypt" ou "letsencrypt".

**UI** (`config.php` tab-tls):

- Card "Certificado ativo" muda de verde pra azul quando é LE,
  com badge "Let's Encrypt", e exibe o issuer.
- Aviso destacado: "Este é um certificado Let's Encrypt válido
  publicamente. Clicar em 'Gerar Self-Signed' ou 'Upload PEM' vai
  sobrescrever ele — o DoH/DoT vai parar de funcionar."
- Botões "Gerar Self-Signed" e "Upload PEM" ganham
  `confirm()` antes de abrir o modal quando cert ativo é LE.

VERSION 2.30.6 → 2.30.7.

---

## v2.30.6 — 2026-05-21

### feat(tls): hook pra reusar Let's Encrypt do Apache no DoT/DoH

Cert self-signed do dashboard funcionava no nível de protocolo
(handshake OK), mas browsers/clientes DoH **recusam self-signed** sem
import manual de cada cliente. Pra hostname público
(`dashboard.redeconexaonet.com`), reusar o **cert Let's Encrypt já
emitido pro Apache** é a solução prática.

**Adicionado**: `system/letsencrypt/unbound-dashboard-deploy.sh`,
um hook do certbot que:

- Roda automaticamente após cada `certbot renew` (cert LE = 90d)
- Detecta se a lineage renovada é do `dashboard.<dominio>`
- Copia `fullchain.pem` → `/etc/unbound/certs/dashboard.crt` (0644
  unbound:unbound)
- Copia `privkey.pem` → `/etc/unbound/certs/dashboard.key` (0640
  unbound:unbound)
- `systemctl reload unbound` (fallback restart)

**Setup manual** (uma vez):

```bash
sudo install -m 0755 \
    /var/www/html/unbound-dashboard/system/letsencrypt/unbound-dashboard-deploy.sh \
    /etc/letsencrypt/renewal-hooks/deploy/unbound-dashboard.sh
# Edita o domínio dentro do script se for diferente do default.

# Primeiro install: roda o hook manualmente pra copiar o cert atual:
sudo RENEWED_LINEAGE=/etc/letsencrypt/live/dashboard.SEUDOMINIO.com \
    /etc/letsencrypt/renewal-hooks/deploy/unbound-dashboard.sh
sudo systemctl reload unbound
```

Smoke ao vivo neste server:

```
curl https://dashboard.redeconexaonet.com:8443/dns-query
HTTP 200  (sem -k!)

openssl x509 -noout -issuer:
issuer=C=US, O=Let's Encrypt, CN=R13
```

Browsers reconhecem cert público → DoH funciona sem warning.

VERSION 2.30.5 → 2.30.6.

---

## v2.30.5 — 2026-05-21

### fix(tls): aviso falso "chave não encontrada" mesmo com arquivo presente

Continuação do v2.30.4. O painel ainda mostrava:

> Caminho da chave configurado mas arquivo não encontrado:
> /etc/unbound/certs/dashboard.key

Causa: `getServiceStatus` usava `is_readable($keyPath)` que retorna
false quando o PHP-FPM (rodando como `www-data`) não tem permissão de
leitura. A `.key` tem perms `0640` (owner+grupo `unbound`) por design —
o **Unbound** lê (é o owner), mas o **PHP** não está no grupo.

**Fix**: trocar `is_readable` por `file_exists` nos 2 checks
(cert path + key path). O painel só precisa saber se o arquivo
EXISTE pra o Unbound encontrar; não precisa que o PHP leia.

Cert ainda usa `is_readable` pra extrair subject/expires/SANs via
openssl — esses precisam de read, e o cert tem `0644` (world-readable
por design).

VERSION 2.30.4 → 2.30.5.

---

## v2.30.4 — 2026-05-21

### fix(config-parser): strip aspas externas de valores quoted

Painel TLS mostrava "arquivo não encontrado" pros paths do cert mesmo
com handshake OK. Causa: `parseConfig` capturava o valor literal
da linha `tls-service-pem: "/etc/unbound/certs/dashboard.crt"`
incluindo as aspas duplas. PHP tentava `is_readable('"/etc/.../dashboard.crt"')`
e dava false (aspas viram parte do filename).

Unbound em si parseia corretamente (sabe tirar aspas), então o
servidor funcionava — só nossa validação client-side é que mentia.

**Fix** em `parseConfig` (linha do scalars-loop): após capturar
`$matches[1]`, se a string começar E terminar com a mesma aspa (`"` ou
`'`), faz `substr` removendo as bordas. Aplica pra qualquer scalar
quoted no config (não só TLS — `interface:`, `module-config:` etc
já tinham regex específico, então não afeta).

Smoke: `parseConfig['tls-service-pem']` agora retorna o path nu,
`is_readable()` reconhece os arquivos, painel não mostra mais o falso
warning.

VERSION 2.30.3 → 2.30.4.

---

## v2.30.3 — 2026-05-21

### fix(tls): 3 problemas no handshake + AppArmor

Após habilitar DoT/DoH com cert managed, painel mostrava "porta aberta
mas handshake falhou". 3 bugs descobertos:

**Bug 1 — cert paths sumindo do general.conf**:

Se o user gerava o cert ANTES de habilitar DoT/DoH, o `applyConfig`
não escrevia `tls-service-pem`/`tls-service-key` em `general.conf`
porque eu condicionava ao `tls-enabled=yes`. Quando o user ligava
o toggle depois, paths sumiam do form.

**Fix**: `tls-service-pem`/`tls-service-key` agora são escritos SEMPRE
que houver valor (Unbound ignora paths sem `tls-port`, sem efeito
colateral). Só `tls-port`/`https-port`/`interface@porta` dependem do
master switch.

**Bug 2 — handshake test em 127.0.0.1**:

Status panel testava handshake em `127.0.0.1:853`, mas o Unbound só
escuta nos IPs reais (`interface: 10.x@853`, sem loopback). Resultado:
mesmo TLS funcionando, painel mostrava "FALHOU".

**Fix**: `getServiceStatus()` aceita lista `$testIps` e testa cada uma
até funcionar. `config.php` extrai os IPs non-loopback do
`$currentConfig['interfaces']` e passa.

**Bug 3 — AppArmor bloqueia `/etc/unbound/certs/*`**:

Em distros com AppArmor (Debian/Ubuntu), o profile do pacote unbound
só permite `/etc/unbound/*.key*` (direto, sem subdir). Cert do dashboard
em `/etc/unbound/certs/dashboard.key` é negado mesmo com perms corretos
(`unbound:unbound 0640`). Unbound aborta com:

```
error: Error in SSL_CTX use_PrivateKey_file crypto error: Permission denied
fatal error: could not set up listen SSL_CTX
```

**Fix**: novo `system/apparmor/usr.sbin.unbound.local.snippet` com as
regras necessárias (`/etc/unbound/certs/** r`, capabilities `dac_*`).
**Aplicação manual** por enquanto (próximas releases vão automatizar):

```bash
sudo cat system/apparmor/usr.sbin.unbound.local.snippet \
    >> /etc/apparmor.d/local/usr.sbin.unbound
sudo apparmor_parser -r /etc/apparmor.d/usr.sbin.unbound
sudo systemctl restart unbound
```

Sistemas sem AppArmor (CentOS, Alpine, etc) não precisam.

Smoke ao vivo após os 3 fixes: handshake DoT em 10.x:853 ✓ e DoH em
10.x:8443 ✓, status panel verde.

VERSION 2.30.2 → 2.30.3.

---

## v2.30.2 — 2026-05-21

### fix(tls): detectar conflito de porta antes de aplicar (Unbound caía)

Quando o user habilita DoT/DoH com porta padrão 443 mas o Apache (que
serve o próprio dashboard via HTTPS) já está nessa porta, o Unbound
aceitava o config e morria no startup:

```
error: can't bind socket: Address already in use for X port 443
fatal error: could not open ports
```

Loop de restart 5x até systemd desistir e o serviço DNS ficar **fora
do ar**. Isso aconteceu agora em produção.

**Fix preventivo** em `applyConfig`:

- Antes de gerar a config, se `tls-enabled=yes`, roda `ss -ltnp` e
  checa cada porta TLS/DoH pedida contra processos já escutando.
- Se outro processo (que não seja `unbound`) está na porta:
  refuse o apply com mensagem específica:
  > Conflito de porta: porta 443 (DoH) já está ocupada por 'apache2' em *.
  > Mude pra uma porta livre (ex: 8443 pra DoH se Apache ocupa 443) ou
  > pare o serviço conflitante antes de habilitar.

Smoke ao vivo: tentar DoH=443 com Apache ativo → **bloqueado** com
mensagem clara. Tentar DoH=8443 → passou.

Recomendação pra quem usa DoH no mesmo host do dashboard web: mudar
a porta DoH pra **8443** ou outro número alto livre.

VERSION 2.30.1 → 2.30.2.

---

## v2.30.1 — 2026-05-21

### fix(tls): toggle DoT/DoH não desligava + duplicação no general.conf

**Bug do toggle** (v2.30.0): os 2 checkboxes separados ficavam disabled
ao desmarcar, fazendo o `<input>` da porta sumir do POST. Backend caía
no fallback `oldConfig['tls-port']` (=853) e continuava gerando
`interface: X@853`. Resultado: desligar via UI não desligava o serviço.

**Bug bônus**: `generateModularConfigs` tinha bloco duplicado escrevendo
`tls-port` 2x em general.conf (linhas 391-394 e 409-416). Causava
entradas repetidas no arquivo.

**Bug do silencioso-sumiço**: salvar QUALQUER coisa em outro tab
(blocked_domains, interface, etc) deletava os 4 campos TLS de
general.conf porque eram só escritos quando `$newParams` os
incluía (sem fallback). Cada save zerava DoT/DoH silenciosamente.

**Mudanças** (sugestão do user — toggle único, ports separados):

1. **1 master switch** "Habilitar DoT/DoH" controla os 2 ao mesmo tempo.
   Cada protocolo mantém seu próprio campo de porta (853, 443).
   Hidden input `tls-enabled=no` antes do checkbox garante que o estado
   sempre vai no POST (HTML padrão não envia checkboxes off).

2. **Backend `_tls_enabled` flag interna** propagada entre `interfaces`
   e `general` durante geração. 3 origens em ordem de prioridade:
   - `$newParams['tls-enabled']` (do form)
   - presença de `tls-port`/`https-port` no $newParams (apply do cert)
   - fallback: estado anterior em $oldConfig.

3. **Fallback nos 4 campos TLS** (`tls-port`, `https-port`,
   `tls-service-{pem,key}`) — sempre lê do oldConfig se newParams não
   trouxer. Save em outro tab não zera mais o TLS.

4. **Bloco duplicado removido** de generateModularConfigs.

Smoke ao vivo:
- `applyConfig(["tls-enabled" => "yes"])` → 4 interfaces base + 4
  listeners @853/@443 + tls-port/https-port/cert paths em general.
- `applyConfig(["tls-enabled" => "no"])` → só interfaces base + port:53,
  general.conf SEM nenhuma linha TLS, interfaces.conf SEM @porta.

VERSION 2.30.0 → 2.30.1.

---

## v2.30.0 — 2026-05-21

### feat(tls): fluxo DoT/DoH funcional ponta-a-ponta

Antes era preciso preencher portas, paths de cert manualmente, mas
mesmo assim o Unbound não escutava nas portas extras (precisa de
`interface: X@porta` adicional). 5 melhorias:

1. **Toggle on/off + porta** — campo único de porta foi substituído
   por checkbox "Habilitar DoT/DoH" + input numérico. Quando desmarca,
   o input é desabilitado (não vai pro POST → port vazia → feature off).
   Default 853/443 preenchidos automaticamente ao habilitar.

2. **Auto-fill paths após Gerar/Upload** — quando user clica em
   "Gerar Self-Signed" ou "Upload PEM" com sucesso, `tls-service-pem`
   e `tls-service-key` no `unbound.conf` recebem automaticamente os
   paths managed `/etc/unbound/certs/dashboard.{crt,key}`. Sem
   copia-cola manual. Remoção do cert também limpa os paths.

3. **SANs pré-preenchidos** — o textarea de SANs no modal "Gerar"
   agora vem com **os IPs reais das interfaces não-loopback** do
   servidor já preenchidos. Skip de link-local IPv6 (fe80::*).

4. **Auto-listen `interface:X@porta`** — `generateModularConfigs`
   agora gera linhas extras `interface: <ip>@<tls-port>` e
   `interface: <ip>@<https-port>` pra cada interface base (não-
   loopback). Sem isso, o Unbound aceita `tls-port: 853` no config
   mas nunca abre a porta. Strip de `@N` existente antes pra evitar
   duplicação.

5. **Fix CIDR no SAN** (já incluído no v2.29.0 mas reiterado pra
   contexto): `143.0.220.0/22` → `143.0.220.0` automaticamente.

Smoke ao vivo: após apply com tls-port=853, https-port=443 + cert
managed, `interfaces.conf` ficou com 4 linhas `interface:` base +
4 linhas extras (`@853`/`@443` pra cada non-loopback). Pronto pra
unbound recarregar e abrir handshake TLS.

VERSION 2.29.0 → 2.30.0.

---

## v2.29.0 — 2026-05-21

### feat(tls): painel de status DoT/DoH + fix CIDR no SAN

**Status panel** no topo de Configurações → Criptografia DoT/DoH:

3 cards mostrando estado real do serviço:

- **DoT (porta X)**: Funcionando (listening + handshake OK) /
  Sem TLS (listening mas handshake falhou) / Inativo (nada
  escutando) / Desabilitado (porta em branco).
- **DoH (porta Y)**: idem.
- **Certificado SSL**: Válido (com dias restantes) / Expira em
  breve (<30d, amarelo) / Expirado (vermelho) / Não configurado.
- Painel de avisos coletando inconsistências (cert path inválido,
  porta listening sem TLS, expira em breve, etc).

**Backend** `TlsCertManager::getServiceStatus($dotPort, $dohPort,
$certPath, $keyPath)`:

- `ss -ltn` pra detectar portas em LISTEN.
- `stream_socket_client tls://127.0.0.1:X` pra validar handshake
  com timeout 3s (skip verify, só queremos saber se conecta).
- Lê info do cert configurado (subject, expires, SANs).

**Bug fix** — CIDR no SAN:

Quando user digitava `143.0.220.0/22` no campo SANs, `filter_var`
rejeitava como IP inválido e o cert era gerado sem aquele IP.
Agora strip silencioso da máscara: `1.2.3.4/22` vira `1.2.3.4`
antes da validação. SAN não suporta network ranges (só IPs
específicos), mas o input fica tolerante.

VERSION 2.28.1 → 2.29.0.

---

## v2.28.1 — 2026-05-21

### fix(tls): "Token expirado" ao gerar/upload/remover certificado

Os 3 modais novos do tab Criptografia DoT/DoH (v2.28.0) submetem
forms independentes do `mainConfigForm`, mas esqueci de incluir
o `<input type="hidden" name="csrf_token">`. O handler global
de `config.php` (linha 30) rejeita qualquer POST sem CSRF token
válido com a mensagem "Token de segurança (CSRF) inválido ou
expirado" — caía pra todos os 3 botões.

**Fix**: adicionado `csrf_token` em cada modal (Gerar / Upload /
Remover), igual aos outros forms independentes (NTP, Timezone,
SMTP). Comportamento idêntico ao mainConfigForm.

VERSION 2.28.0 → 2.28.1.

---

## v2.28.0 — 2026-05-21

### feat(tls): gerar / upload / remover certificado SSL no DoT/DoH

Antes o tab "Criptografia DoT/DoH" só aceitava paths digitados na mão.
Pra usar DoT/DoH era preciso gerar o cert externamente (openssl /
certbot) e copiar pra um caminho lido pelo unbound. Fluxo invasivo.

Agora o dashboard gerencia o par `dashboard.crt` + `dashboard.key`
em `/etc/unbound/certs/`, dono `unbound:unbound`, perms `0644/0640`.

**Novo `TlsCertManager.php`**:

- `generateSelfSigned(cn, sans, days)` — openssl `req -x509 -newkey
  rsa:2048` com `subjectAltName` (DNS + IP), `serverAuth` EKU.
  Default 825 dias (limite iOS/Safari). CN entra automaticamente
  como primeiro SAN DNS (compat moderna).
- `uploadCert(certPem, keyPem)` — paste de PEM via textarea. Valida
  com `openssl x509`/`pkey` e match de modulus cert↔key antes de
  instalar.
- `removeCert()` — `rm` dos arquivos gerenciados. Não toca em paths
  externos (Let's Encrypt etc).
- `getStatus()` — pega subject, expira, SANs do cert atual pra UI.

**UI no tab-tls**:

- Card "Certificado SSL Gerenciado" abaixo dos campos de paths.
- Status verde com CN/Expira/SANs quando o cert managed existe.
- 3 botões: **Gerar Self-Signed** • **Upload PEM** • **Remover**.
- 3 modais fora do `mainConfigForm` (forms separados POSTam direto
  pra `config.php?tab=tls`).
- Modal "Gerar" pré-popula o CN com o hostname do servidor.

**Sudoers** ganha 4 entries exatas:

- `mkdir -p /etc/unbound/certs`
- `install -o unbound -g unbound -m 0644 <tmp>/unbound_dashboard_cert.crt → dashboard.crt`
- `install -o unbound -g unbound -m 0640 <tmp>/unbound_dashboard_cert.key → dashboard.key`
- `rm /etc/unbound/certs/dashboard.{crt,key}`

Smoke ao vivo: gerei `CN=dns.test.local`, SAN `192.168.1.1 10.0.0.1`,
validade 30d. Cert instalado com perms corretos, status exibido,
remove funcionou.

VERSION 2.27.3 → 2.28.0.

---

## v2.27.3 — 2026-05-21

### fix(config-rede): IP da LO.1 não aparecia + faltava botão "Remover"

Continuação do fix do v2.27.2. Depois de salvar IP no card da LO, o IP
não voltava a aparecer no card (impedindo edição/exclusão).

**Causa**: a UI itera `ip addr show` (mostra `lo`, não `lo.1`), e chamava
`getInterfaceConfig('lo')` que busca `iface lo inet...` no
`/etc/network/interfaces`. Mas o arquivo agora tem `iface lo.1 inet
static`. Retorno: defaults vazios → card mostra inputs vazios.

**Fix em config.php** (1 linha):

```php
$confLookup = $iface['ifname'] === 'lo' ? 'lo.1' : $iface['ifname'];
$ifConf = $networkManager->getInterfaceConfig($confLookup);
```

Espelha o mapeamento já usado no save (`save_interface`).

**Bonus — remover IP da LO.1**:

- `NetworkManager::removeInterfaceConfig($iface)` novo: remove blocos
  `auto`, `allow-hotplug` e `iface ... inet[6]` do iface alvo do
  `/etc/network/interfaces`. Lock `interfaces`. Bloqueia `lo` raiz
  (sem ela o sistema quebra), aceita aliases `lo.1`/`lo:1`. Netplan
  retorna mensagem "ainda não suportado".
- Best-effort `ifdown` após a remoção.
- Handler `delete_interface` em `config.php` (mapeia `lo → lo.1`).
- Botão "Remover LO.1" no card da loopback, só aparece se há IP
  configurado. Confirm antes de submeter.

VERSION 2.27.2 → 2.27.3.

---

## v2.27.2 — 2026-05-21

### fix(config-rede): salvar IP na LO sempre gerava `iface lo.1 inet dhcp`

Bug sutil de HTML/PHP: no card da loopback em **Configurações → Configurações
de Rede**, o `<select name="iface_mode[lo]">` está marcado como `disabled`
(porque só faz sentido `static` no `lo.1`). Mas **inputs `disabled` não são
enviados no submit do browser** — o backend recebia POST sem `iface_mode[lo]`
e caía no default `'dhcp'` (linha 125 do handler). Resultado: mesmo preenchendo
IP/netmask, o `/etc/network/interfaces` ficava com:

```
auto lo.1
iface lo.1 inet dhcp
```

ignorando os campos preenchidos.

**Fix** em `config.php` no handler `save_interface`:

- Detecta `requestedIface === 'lo'` e força `$mode = 'static'`.
- Mesma coisa pro IPv6 quando habilitado: `$v6_mode = 'static'`.

UI inalterada (continua com `disabled` no select pra UX correta). Só fechou
a brecha server-side.

VERSION 2.27.1 → 2.27.2.

---

## v2.27.1 — 2026-05-21

### fix(cache): gráfico "Distribuição de TTL" com buckets dinâmicos

Bucket "> 1 dia" (e às vezes "1-24 h") aparecia sempre vazio. Não era
bug — era matemática: `cache-max-ttl` capa todos os TTLs, então
buckets acima do cap são inalcançáveis.

**Backend** (`api/cache_dump.php`):

- Nova fn `readCacheMaxTtl()` — consulta o cap real do Unbound rodando
  via `unbound-control get_option cache-max-ttl` (fallback 86400 se
  controle off).
- Nova fn `buildTtlBucketsMeta(int $cap)` — gera só os buckets onde
  é matematicamente possível ter dados (`lower < cap`, `expired` sempre).
- Resposta JSON ganha `stats.ttl_buckets_meta` (array filtrado) e
  `stats.cache_max_ttl` (segundos).

**Frontend** (`cache.php`):

- Lê meta do backend (com fallback pro array antigo se backend desatualizado).
- Adicionou label sutil no header do card: "TTL capado em Xh (cache-max-ttl)"
  pra explicar visualmente por que tem 4/5/6 buckets em vez de 6 fixos.

**Exemplos de filtragem**:

| cache-max-ttl | Buckets visíveis |
|---|---|
| 60s | `expired`, `<1m` |
| 3600s (1h) | `expired`, `<1m`, `1-5m`, `5-60m` |
| 86400s (24h — default Unbound) | + `1-24h` |
| > 86400s | + `>1d` |

VERSION 2.27.0 → 2.27.1.

---

## v2.27.0 — 2026-05-21

### feat(unbound): toggle "Anti-DoH" — bloqueia DNS-over-HTTPS de terceiros

Navegadores modernos (Firefox/Chrome/Edge) usam DoH por padrão em vários
cenários — TCP/443 direto pra Cloudflare/Google, **bypassando o
resolver local**. Resultado: usuário vê `coins.game` bloqueado no
`nslookup` mas o navegador resolve normalmente.

Solução não-invasiva (sem mexer em config de dispositivos): bloquear
os hostnames dos endpoints DoH no próprio Unbound. Cliente tenta
resolver `mozilla.cloudflare-dns.com` → NXDOMAIN → cai pro DNS local
→ bloqueio volta a funcionar.

**Setting** `anti_doh_enabled` (default `false`). Toggle em
**Configurações → Lista de Bloqueios**.

**Backend**:

- Novo arquivo `src/data/anti_doh_hosts.json` — lista curada de **33
  hostnames DoH** (Cloudflare, Google, Quad9, OpenDNS, CleanBrowsing,
  AdGuard, NextDNS, ControlD, LibreDNS, dns.sb, DNSPod, AliDNS, IIJ),
  + canary do Firefox `use-application-dns.net` que faz o Firefox
  desligar DoH automaticamente.
- `UnboundConfigManager`:
  - Novo include `/etc/unbound/includes/anti_doh.conf` (separado de
    `blocked_domains.conf` pra facilitar manutenção/audit).
  - `loadAntiDohHosts()` lê o JSON ignorando linhas vazias/comentadas.
  - `generateAntiDohConf(bool)` escreve o arquivo: vazio quando
    `false`, com `local-zone "X." always_nxdomain` por hostname
    quando `true`.
  - `applyConfig` regenera + `cp` na receita (mesmo padrão de
    blocked_domains). O include já entra em `generateRawConfig` e
    no `unbound-checkconf` pre-flight.
- `save_rpz` action em `config.php` agora persiste o toggle via
  merge com settings antigas (não dropa SMTP etc).

**UI** — novo card no tab-rpz com descrição explicando o
funcionamento + contador dinâmico de hostnames carregados.

**Smoke test ao vivo** (33 hostnames, Unbound restarted):

```
mozilla.cloudflare-dns.com    NXDOMAIN
dns.google                    NXDOMAIN
dns.quad9.net                 NXDOMAIN
dns.adguard.com               NXDOMAIN
use-application-dns.net       NXDOMAIN
example.com                   NOERROR (sanity)
```

Cobre ~95% dos casos de DoH-bypass. Resíduo: clientes que usam DoH
com IP hardcoded (não-hostname) — só firewall L4 resolve.

VERSION 2.26.1 → 2.27.0.

---

## v2.26.1 — 2026-05-21

### fix(log_watcher): distinguir `blocked` real de `nxdomain_upstream`

Antes, o `log_watcher` classificava **qualquer NXDOMAIN como `blocked`**
em `query_logs.action`. Resultado: domínios mortos/descontinuados de
adware/tracker apareciam no menu Ameaças como se tivessem sido
bloqueados por nós — sintoma típico era ver domínios velhos da lista
StevenBlack/Hagezi "blocked" mesmo sem estarem no Unbound config.

**Mudança**: novo serviço `BlockedMatcher` parseia
`/etc/unbound/includes/blocked_domains.conf` (fonte da verdade do que
o Unbound realmente bloqueia via `local-zone`), mantém set em memória
com TTL 5min + invalidação por mtime, expõe `matches(domain)` com
match por sufixo (`evil.com` cobre `sub.evil.com`).

`_classify` agora:

- `NXDOMAIN` + matcher hit → `blocked` (bloqueio nosso real).
- `NXDOMAIN` + matcher miss → `nxdomain_upstream` (novo valor — o
  upstream retornou NXDOMAIN sem envolvimento nosso).
- `0.0.0.0` na linha → `blocked` (vem de `local-data` nossa, sempre é nosso).
- `NOERROR` → `resolved`.
- Resto → ignorado (None).

**Impacto na UI** (sem mudança visual, mas semântica fica certa):

- Threats page filtra `action='blocked'` — agora só conta bloqueio
  real, não NXDOMAIN upstream.
- daily_stats, stats_aggregator: idem (todos usam filtro literal
  `action='blocked'`, não exclusão).
- Queries antigas em `query_logs` não são reescritas — só novas
  entradas se beneficiam.

9 testes novos em `test_blocked_matcher.py` + 2 em `test_log_watcher.py`.
151/151 passing.

VERSION 2.26.0 → 2.26.1.

---

## v2.26.0 — 2026-05-21

### feat(blocklist): separa "Lista ANATEL" (bloqueio) de "Catálogo de Ameaças"

Em v2.24.0 as 2 fontes (ANATEL Judicial e StevenBlack/Hagezi) viviam
juntas em `/blocklist.php` com filter chips. Decisão de manter a página
"Lista ANATEL" só com ANATEL (a única que realmente bloqueia no
Unbound) e mover o catálogo de inteligência pra página própria.

**`blocklist.php`** (Lista ANATEL):

- Mostra apenas o painel ANATEL Judicial + Sincronizar ANATEL.
- Removidos: 2º card (StevenBlack/Hagezi), filter chips de categoria,
  toggle ativa/pausa e botão "Atualizar Agora" do catálogo.
- Search agora força `category=Judicial` no endpoint. Stats card
  "Total de Domínios" usa `by_category.judicial` em vez do global.
- Título atualizado: "Lista ANATEL Judicial".

**`catalog.php`** (página nova):

- Painel da fonte StevenBlack/Hagezi + nome+desc, toggle ativa/pausa,
  botão "Atualizar Agora".
- Search força `category=Malware/Adware`.
- Stats card "Total" usa `by_category.adware`.
- Texto enfatiza: "não bloqueia no Unbound — só pra busca/analytics".

**Sidebar**: novo link "Catálogo" abaixo de "Lista ANATEL" no grupo
Monitoramento (admin only, mesmo gate).

Nenhuma mudança de backend — reusa o endpoint
`/api/v1/blocklist/search?category=...` que já existia desde v2.24.0.

VERSION 2.25.0 → 2.26.0.

---

## v2.25.0 — 2026-05-21

### feat(multi-host): histórico de polls por host + aba dedicada no drill-down

Antes o multi-host só guardava `last_status` por host. Sem visibilidade
do passado: "esse host caiu quando?", "tá oscilando?", "qual foi o
erro 5 polls atrás?" — só dava pra inferir do estado atual.

**Backend**:

- **Migration V8**: nova tabela `host_poll_history(id, host_id,
  polled_at, status, error, payload)` + índice
  `(host_id, polled_at DESC)`.
- `managed_hosts.poll_host()`: além de atualizar `managed_hosts`
  como antes, agora insere 1 linha em `host_poll_history` e faz
  trim mantendo os últimos **100 polls por host** (best-effort,
  falha no histórico não derruba o poll).
- `managed_hosts.list_history(host_id, limit)`: leitura ordenada
  por `polled_at DESC`, parseia payload JSON.
- Novo endpoint `GET /api/v1/hosts/{id}/history?limit=N`
  (capability `config.write`).

**Frontend** (`hosts.php`):

- 3ª aba "Histórico" no modal drill-down, lazy-loaded ao abrir:
  - **Sparkline horizontal** com 100 barras finas coloridas
    (verde=ok / amarelo=auth / vermelho=unreachable /
    laranja=error), ordem mais-recente→mais-antiga, tooltip por
    barra com timestamp + erro.
  - **Tabela detalhada** abaixo: quando, status pill, versão,
    queries_24h, erro.
  - Botão "↻ Recarregar".

Com poll a cada 60s, 100 entries = ~1h40 de histórico. Cap
deterministico: `hosts × 100` linhas no banco no pior caso.

2 testes novos (`test_poll_writes_history`,
`test_history_trim_keeps_last_100`). 140/140 verde.

VERSION 2.24.0 → 2.25.0.

---

## v2.24.0 — 2026-05-21

### feat(blocklist): /blocklist.php reorganizada — 2 fontes distintas + busca unificada

Antes a página rotulava "Origem: StevenBlack" mas mostrava mtime do
arquivo ANATEL e fazia search no flat file dele. Os 2 fluxos (catálogo
StevenBlack/Hagezi → DuckDB; ANATEL → flat file → Unbound) viviam
misturados na UI.

**Backend novo** (`api_service`):

- `GET /api/v1/blocklist/search?q=&category=&tld=&page=&per_page=` —
  busca paginada direto no DuckDB. Filtra por categoria
  (`Judicial` | `Malware/Adware`) e TLD. Substitui o legado
  `api/blocklist_search.php` (que parseava o `.conf`).
- `threats_repo`: `search_blocklist`, `count_blocklist`, `top_tlds`
  com helper `_build_where` compartilhado.
- `service_control.php` ganha action `sync_anatel` que dispara
  `src/scripts/sync_judicial_list.php` em background.

**UI** (`blocklist.php`):

- **Topo: 2 cards lado-a-lado**, cada um com seu estado/ação:
  - "ANATEL — Bloqueio Judicial": badge ativo/desativado, mtime do
    arquivo, botão "Sincronizar ANATEL" (gated em
    `official_blocklist_enabled`).
  - "Catálogo — Inteligência": nome da fonte (StevenBlack/Hagezi),
    toggle ativo/pausado, botão "Atualizar Agora". Texto explícito
    que esse catálogo NÃO bloqueia no Unbound.
- **Filter chips** de categoria acima da busca (Todas / Judicial /
  Malware-Adware) com contadores ao vivo.
- **Tabela** agora consome `/api/v1/blocklist/search` (DuckDB) em
  vez do flat file — vê ANATEL + Malware/Adware juntos.
- Labels "Origem: StevenBlack" removidos de stats card e header da
  tabela (eram enganosos).

7 testes novos em `test_threats.py` cobrindo filtros (categoria, q,
tld), paginação, top_tlds e rejeição de categoria inválida. 138/138
verde.

VERSION 2.23.1 → 2.24.0.

---

## v2.23.1 — 2026-05-15

### fix(multi-host): sentinel "latest" pra eliminar race no upgrade orquestrado

Botões "Atualizar todos" / "Atualizar este" do multi-host podiam falhar
com `VersionMismatch` quando o cache do master e o cache do agent
divergiam (uma release sair entre as duas consultas ao GitHub).

**Mudança no contrato** de `POST /api/v1/updates/apply`:

- `version` agora aceita `"latest"` (sentinel) além de semver exato.
- Quando `"latest"`, agent pula a comparação estrita e resolve a versão
  via seu próprio `fetch_latest_release(force_refresh=True)` — cada
  host instala o que ele considera latest no momento.
- Vantagem: race-free. Trade-off: durante uma batch, se sair release
  no meio, agents que iniciaram cedo pegam vX e os que iniciaram tarde
  pegam vX+1. Aceitável (próximo tick alinha).

**UI** (`hosts.php`): "Atualizar todos" e "Atualizar este (modal)"
agora mandam `{version: "latest"}`. O confirm continua mostrando a
versão detectada NO MASTER pra contexto, mas explicita: "agent resolve
a versão via GitHub no momento".

Single-host UI (`config.php` → Updates → Apply) **não muda** — continua
mandando o tag exato selecionado pelo usuário.

Novo teste `test_apply_accepts_latest_sentinel`. 131/131 passing.

VERSION 2.23.0 → 2.23.1.

---

## v2.23.0 — 2026-05-15

### feat(blocklist): toggle pra ativar/pausar a Fonte da Blacklist Principal

Novo controle pra admin pausar o auto-update da Blacklist Principal
(StevenBlack / Hagezi) sem perder os dados atuais.

**Setting**: `blacklist_source_enabled` (default `"1"` — ativa).
Persistido via `/api/v1/exports/settings/bulk`.

**Comportamento quando pausada**:

- Cron `scripts/update_blacklist.php` checa o setting no boot e
  faz no-op (log + exit 0).
- Botão "Atualizar Agora" em `/blocklist.php` fica desabilitado
  visualmente, e o handler em `api/service_control.php` valida
  server-side (409 se chamado direto).
- Banner em `/blocklist.php` mostra badge "Fonte pausada" + nome
  da fonte com line-through; toggle switch ao lado fica em off.

**UI** — toggle sincronizado em 2 lugares:

- `/blocklist.php`: switch on/off no banner "Origem Ativa", muda
  estado via AJAX (`action=toggle_blacklist_source`) e recarrega
  a página pra refletir badges e o gate server-side.
- Configurações → Lista de Bloqueios: checkbox grande estilo
  Anablock, salva junto com `save_rpz`. Texto explica que dados
  atuais ficam preservados.

**BlocklistManager**: 2 métodos novos — `isBlacklistSourceEnabled()`
+ `saveBlacklistSourceEnabled(bool)`.

VERSION 2.22.3 → 2.23.0.

---

## v2.22.3 — 2026-05-15

### chore(hosts.php): padronização — adeus `confirm()` / `alert()` do navegador

Em `/hosts.php` a maioria das confirmações e mensagens de erro
ainda usavam `window.confirm()` e `window.alert()` nativos, com
visual quebrando o resto da UI.

**Substituição** por 2 modais genéricos no padrão glass-panel:

- `customConfirm(title, body, opts)` — promessa retornando bool.
  Opts: `variant` (info|warning|danger|error), `okLabel`,
  `cancelLabel`. Botão OK pinta de acordo com variant.
- `customAlert(title, body, variant)` — promessa que resolve
  quando o usuário fecha. Variant adiciona ícone+cor:
  - `info` (cyan, `i`)
  - `success` (emerald, `✓`)
  - `warning` (amber, `!`)
  - `error` / `danger` (red, `✗`/`!`)

**Bonus**: ESC fecha qualquer um dos modais; click no backdrop também.
Focus automático no botão primário pra Enter funcionar.

19 chamadas refatoradas (todas em `hosts.php`). UX agora consistente
com o resto do app.

VERSION 2.22.2 → 2.22.3.

---

## v2.22.2 — 2026-05-15

### fix(multi-host): /updates/apply 500 quando master autenticava via API token

Botão "Atualizar todos" no master batia no agent e o agent retornava
"Internal Server Error" no modal de resultado.

**Causa**: `app/routers/updates.py` fazia `int(payload["sub"])` direto.
Pra JWT, `sub` é int (user_id). Pra API token (auth alternativa pro
multi-host), o payload sintético tem `sub="api-token"` — `int()`
explodia com ValueError → 500.

**Fix**: novo helper `_user_from_payload(payload)` que retorna
`(user_id, username_hint)` lidando com os dois casos:

- JWT: `(int(sub), None)` — username lookup via banco.
- API token: `(None, "api-token:<label>")` — pro audit ter actor
  identificável (label do token usado).

Aplicado em `/updates/apply` e `/updates/restore`. 4 testes de
regressão novos em `test_updater.py`.

VERSION 2.22.1 → 2.22.2.

---

## v2.22.1 — 2026-05-15

### fix(multi-host): batch ops davam HTTP 422 (route order)

Botões "Atualizar todos" / "Re-poll todos" / "Reiniciar API|Unbound"
em `/hosts.php` retornavam HTTP 422.

**Causa**: as rotas `/hosts/batch/{poll,restart,upgrade}` foram
declaradas DEPOIS de `/hosts/{host_id}/{poll,restart,upgrade}`.
FastAPI casa rotas na ordem de declaração — então um request pra
`/batch/upgrade` ia pra `/{host_id}/upgrade`, tentava parsear
`host_id="batch"` como int e falhava com 422 antes do auth.

**Fix**: reordenado em `app/routers/hosts.py` — batch routes ANTES das
parametrizadas. Novo teste de regressão em `test_managed_hosts.py`
checa a ordem.

VERSION 2.22.0 → 2.22.1.

---

## v2.22.0 — 2026-05-15

### feat(multi-host F6): drill-down + batch ops (fecha o ciclo multi-host)

Sexta e última fase do multi-host. Master agora controla os agents
remotamente (não só monitora) e oferece visão detalhada por host.

**Backend** (agent):

- `POST /api/v1/host/restart/{service}` — whitelist `api | unbound`.
  Spawn detachado (`start_new_session=True`) via `sudo systemctl
  restart`, retorna 202 antes do restart matar o caller.
- Sudoers ganha `/usr/bin/systemctl restart unbound-dashboard-api`
  (unbound já tinha via `* unbound`).

**Backend** (master, `app/services/managed_hosts.py`):

- `proxy_get/post(host_id, path)` — chama agent autenticado com
  X-Api-Token. Retorna `{ok, status_code, data|error}`.
- `restart_service(host_id, service)` — POST /host/restart/{service}.
- `trigger_upgrade(host_id, version)` — POST /updates/apply.
- `batch(op, ...)` — sequencial em todos; fail isolado por host.

**Backend** (master, `app/routers/hosts.py`):

- `GET  /hosts/{id}/info` — proxy /host/info (estático).
- `POST /hosts/{id}/restart/{service}` — restart 1 host.
- `POST /hosts/{id}/upgrade` — upgrade 1 host.
- `POST /hosts/batch/poll` — re-poll todos.
- `POST /hosts/batch/restart/{service}` — restart em todos.
- `POST /hosts/batch/upgrade` — upgrade em todos.

**Frontend** (`hosts.php`):

- **Barra de batch ops** acima da grid: Re-poll todos • Atualizar
  todos • Reiniciar API • Reiniciar Unbound. Cada uma com confirm
  específico explicando o impacto.
- **Modal drill-down** abre ao clicar em "▤ Detalhes" no card:
  - Aba "Info do agent": hostname, FQDN, OS, arch, Python,
    VERSION, api_version (puxa /hosts/{id}/info ao vivo).
  - Aba "Status atual": versão, uptime, hit ratio, queries 24h,
    alertas, users, sessões, duckdb, auth_kind. Botão
    "↻ Forçar poll" atualiza no momento.
  - Painel de ações: Reiniciar API • Reiniciar Unbound • Atualizar
    este • ↗ Abrir UI.
- **Modal de resultado** mostra OK/falha por host após batch op,
  com mensagem detalhada quando falha.
- Detecção automática da última versão via `/updates/check` pra
  pré-popular o upgrade.

VERSION 2.21.4 → 2.22.0.

---

## v2.21.4 — 2026-05-15

### feat(multi-host): card de host com 8 mini-métricas

Cards de host em `/hosts.php` agora mostram tudo que o endpoint
`/api/v1/host/status` já retornava (antes só 4 dos 8 campos
eram exibidos).

**Grid 4×2 quando status=ok**:

- Linha 1: Versão • Uptime • Hit ratio 24h • Queries 24h
- Linha 2: Alertas • Users • Sessões • DuckDB

Novos helpers JS:

- `fmtUptime(seconds)` — `5d 12h` / `3h 24m` / `12m`.
- `fmtNum(n)` — locale pt-BR + null-safe.

DuckDB pinta verde (OK) / vermelho (FAIL); alertas pinta vermelho
quando > 0. Tooltip do card DuckDB mostra `auth_kind`
(jwt | api_token) pra debug.

VERSION 2.21.3 → 2.21.4.

---

## v2.21.3 — 2026-05-15

### feat(multi-host F5): página `/hosts.php` (UI inventário + ações)

Quinta fase do multi-host. Master ganha página dedicada pra
gerenciar a frota de agents.

**Frontend** (`hosts.php`):

- 4 stat cards no topo (Total / Online / Auth falhou / Inalcançáveis).
- Grid de cards por host com badge de status (ok / auth_failed /
  unreachable / error / unknown) e cores Tailwind.
- Quando `last_status=ok`, card mostra mini-métricas: versão,
  hit ratio 24h, queries 24h, alertas ativos.
- Quando `last_status` ≠ ok, painel de erro com `last_error`.
- Ações por card: ↻ Poll agora • Editar • ↗ Abrir UI • Remover.
- Botões topo: Refresh manual • Adicionar Host.
- Modal de add/edit:
  - Campos: label, base_url (immutable em edit), api_token
    (placeholder "Manter token atual" se edit), notes.
  - Validação client-side (label non-empty, URL http(s)://, token
    ≥20 chars no create).
- Auto-refresh da lista a cada 60s.
- Gate `Auth::can('config.write')`; sem permissão mostra panel
  "Acesso negado" e cabeçalho sem botões.

**Sidebar** (`includes/sidebar.php`):

- Novo link "Hosts" na seção "Sistema", acima de "Configurações".
- Ícone (house outline) + gate `Auth::can('config.write')`.

VERSION 2.21.2 → 2.21.3.

---

## v2.21.2 — 2026-05-15

### feat(multi-host F3+F4): managed_hosts + worker poller

Terceira e quarta fases do multi-host. Master ganha inventário
persistente de agents + worker que polleia periodicamente.

**Backend** (`api_service/`):

- **Migration V7** cria `managed_hosts` (id, label, base_url UNIQUE,
  api_token, notes, added_by, added_at, last_polled_at, last_status_at,
  last_status, last_status_payload JSON, last_error).
- `services/managed_hosts.py`:
  - `list_all()` — sem o api_token (UI safe)
  - `get(id)` — com api_token (uso interno do poller)
  - `create()`, `update()` (`api_token=""` preserva original),
    `delete()`
  - `poll_host(id)` — HTTP GET `<base_url>/api/v1/host/status` com
    `X-Api-Token`. Categoriza resultado em ok / auth_failed /
    unreachable / error. Atualiza banco.
  - `poll_all()` — sequencial pra evitar avalanche.
- `routers/hosts.py`: `/api/v1/hosts` CRUD + `POST /{id}/poll`.
  Capability `config.write`. base_url precisa começar com http(s)://.

**Worker** `app/workers/host_poller.py`:

- Loop 60s chamando `managed_hosts.poll_all()`.
- Delay inicial 15s.
- Registrado em `main.py` no supervisor padrão (restart on crash,
  backoff exponencial).
- Log estruturado por tick: `host_poller.tick total=N ok=M failed=K`.

**10 testes novos** em `test_managed_hosts.py` (CRUD, duplicate, trim
trailing slash, token preservation, 4 cenários de poll com httpx mockado).
125/125 totais.

VERSION 2.21.1 → 2.21.2.

---

## v2.21.1 — 2026-05-15

### feat(multi-host F2): endpoint `/api/v1/host/{info,status}` agregado

Segunda fase do multi-host. Agent expõe estado consolidado pro master
polar (1 request → tudo que o master precisa pra renderizar um card).

**Backend** (`api_service/app/routers/host.py`):

- `GET /api/v1/host/info` — estático: hostname, FQDN, SO, release,
  Python version, VERSION local, api_version. Cacheável aggressively.
- `GET /api/v1/host/status` — runtime: VERSION, uptime, alerts_active,
  users_total, sessions_active, queries_24h, hit_ratio_24h, duckdb_ok.
  Inclui `auth_kind` (jwt|api_token) pra audit.
- Ambos exigem `require_auth` (aceita JWT OU `X-Api-Token`).
- Cada query DuckDB é try/except — uma falha isolada não derruba o
  endpoint inteiro.

Validado end-to-end: token criado via /api-tokens, usado como
`X-Api-Token: <raw>` em /host/status → retornou métricas completas
(821k queries, 2 users, 2 sessões ativas).

VERSION 2.21.0 → 2.21.1.

---

## v2.21.0 — 2026-05-15

### feat(multi-host): API tokens long-lived para autenticação master → agent

Primeira fase da feature **multi-host** — um master orquestrando vários
agents. Esta release adiciona a infra de auth no lado do agent.

**Backend** (`api_service/`):

- **Migration V6** cria `api_tokens` (id, label, token_hash SHA256 UNIQUE,
  created_by, created_at, last_used_at, last_used_ip, revoked_at).
- `services/api_tokens.py`:
  - `generate_raw_token()` — 256 bits via `secrets.token_urlsafe`
  - `create(label, created_by)` — retorna `(id, raw)`. Raw aparece UMA vez.
  - `verify(raw, source_ip)` — valida hash + atualiza last_used_at/ip
  - `list_active(include_revoked)` — sem o hash (só metadata)
  - `revoke(id)` — soft delete via `revoked_at`
- `core/deps.py::require_auth` agora aceita **2 formas de auth**:
  - `Authorization: Bearer <jwt>` (caminho user normal)
  - `X-Api-Token: <token>` (novo, pra master ↔ agent)
  - Auto_error=False no HTTPBearer; tenta API token primeiro.
- `core/rbac.py`: nova capability `tokens.manage` (admin only).
- `routers/api_tokens.py`: GET / POST / DELETE em `/api/v1/api-tokens`.
- Auth via API token retorna payload sintético: `{sub: "api-token",
  role: "admin", auth_kind: "api_token", api_token_id, api_token_label}`.

**UI** (`config.php`):

- Nova aba **"API Tokens"** (admin only).
- Botão "Gerar novo token" abre modal pedindo label.
- Token criado abre modal "Token gerado" mostrando o **raw_token UMA
  vez** com botão "Copiar pra área de transferência".
- Lista cards com: label, ID, data de criação, último uso + IP.
- Botão "Revogar" por card com `confirm()`.

**Testes**: 5 novos em `test_api_tokens.py` (gen, create+verify+revoke,
invalid input, listing flags, last_used update). 115/115 verdes.

Próximas fases: endpoint agregado de status no agent, master managed_hosts
table, worker host_poller, UI /hosts.php no master, batch ops.

VERSION 2.20.2 → 2.21.0 (minor — feature nova + schema change).

---

## v2.20.2 — 2026-05-15

### chore: manutenção — deps atualizadas, docs revisadas, cleanup de código morto

**Deps atualizadas** (via `uv lock --upgrade`):

- fastapi (mantida 0.136.1)
- pandas 3.0.2 → 3.0.3
- pydantic 2.13.3 → 2.13.4
- pydantic-settings 2.14.0 → 2.14.1
- cryptography 47.0.0 → 48.0.0
- uvicorn 0.46.0 → 0.47.0
- ruff 0.15.12 → 0.15.13
- mypy 1.20.2 → 2.1.0 (dev)
- idna 3.13 → 3.15

110/110 testes verdes após upgrade.

**Docs revisadas:**

- `SISTEMA.md` — listagem de routers/services/workers/migrations atualizada
  com tudo que foi adicionado desde v2.2.x. Seção RBAC reescrita pra
  refletir os 4 papéis + 11 capabilities (era 2 papéis).
- `MANUAL_INSTALACAO.md` — adicionada **Opção A: Botão "Atualizar agora"
  via UI** como caminho recomendado pro dia a dia. Opções B (one-liner)
  e C (pacote manual via SSH) mantidas.

**Cleanup:**

- Removidos 3 scripts legacy do tempo da transição MariaDB → DuckDB:
  - `api_service/tools/check_dual_write_parity.py`
  - `api_service/tools/cutover_log_ingester.sh`
  - `api_service/tools/teardown_mariadb.sh`
- `tools/build-package.sh` e `tools/build-update.sh` simplificados
  (excludes específicos pros scripts removidos não são mais necessários).
- `.gitignore` ganha `data/.users_exists_cache` (arquivo de cache da
  feature v2.20.1).

VERSION 2.20.1 → 2.20.2.

---

## v2.20.1 — 2026-05-15

### fix(stability): retry no DuckDB + cache do `/users/exists`

Auditoria de warnings em prod (24h):

- `_duckdb.BinderException: Unique file handle conflict` em 2/1165 calls
  ao `/api/v1/users/exists` — race entre múltiplos readers simultâneos
  abrindo `duckdb.connect(db_path)` no mesmo file.
- 1165 calls/24h ao `/users/exists` (~48/h) — endpoint é hit em todo
  page-load do `login.php`, frequência muito alta pra check que muda
  raramente.

**Fixes:**

1. `repositories/duckdb/connection.py` — `_sync_fetchall`/`_sync_fetchone`
   ganham retry exponencial (50ms, 100ms, 200ms, 400ms, 800ms) em caso
   de erros transientes do DuckDB:
   - "unique file handle conflict"
   - "cannot attach"
   - "could not set lock"
2. `Auth::hasUsers()` e `Auth::hasUsersOrApiDown()` — cache positivo em
   arquivo `data/.users_exists_cache` (TTL 5min). Cache negativo NÃO
   é gravado pra que o wizard de instalação destrave imediatamente
   após criar o admin. Reduz calls ao endpoint em ~99%.

Stress test pós-fix: 30 requests concorrentes em `/users/exists` sem
nenhuma BinderException no log.

VERSION 2.20.0 → 2.20.1.

---

## v2.20.0 — 2026-05-15

### feat(notify): email + webhook quando nova release é detectada

`update_checker` worker, ao detectar nova versão no GitHub, agora notifica
admins automaticamente. Configurável separadamente por email e webhook.

**Backend:**

- Novo `services/email_notifier.py` — cliente SMTP minimal via stdlib
  (smtplib + email.mime). Lê config do DuckDB settings table (mesmas
  chaves que o PHP Mailer.php usa). Busca admins ativos com email
  preenchido e envia pra cada.
- `services/webhook_notifier.notify_new_release(release)` — análogo
  ao `notify()` mas pra release. Reusa o webhook configurado mas
  bypassa cooldown/severity_min (release é sempre informativa, 1x
  por versão).
- `workers/update_checker._maybe_notify_new_release()`:
  - Confere se há tag nova (vs `udash:update:notified_tag` em Redis)
  - Confere se VERSION local é menor que `latest` (não notifica de
    versões "antigas")
  - Lê settings `notify_email_on_release` e `notify_webhook_on_release`
  - Dispara email/webhook conforme habilitado
  - Marca como notificada SÓ se efetivamente enviou (anti-silenciar
    permanente em caso de SMTP off no momento)

**Novas settings persistidas (sem migration — usa tabela existente):**

- `notify_email_on_release` (bool, default false)
- `notify_webhook_on_release` (bool, default false)

**UI:**

- Aba **Email / SMTP** ganha checkbox "Notificar nova release por
  email" no formulário de config (persiste via `Mailer::saveConfig`
  existente). `Mailer::SETTINGS` adicionado pra incluir a flag.
- Aba **Webhooks de Alertas** ganha checkbox "Notificar nova release
  via webhook". Endpoint `/api/v1/webhooks/config` PUT aceita
  `notify_on_release` no payload, persiste em
  `notify_webhook_on_release`.

**Anti-spam:** mesma tag notifica 1x só (TTL 30 dias em Redis). Reset
manual via `redis-cli del udash:update:notified_tag` se necessário.

110/110 testes verdes.

VERSION 2.19.0 → 2.20.0 (minor — feature nova).

---

## v2.19.0 — 2026-05-15

### feat(audit): trilha de auditoria de updates/restores + aba dedicada

Cada update ou restore aplicado via UI agora deixa rastro persistente.
Aba nova "Auditoria" mostra histórico completo: quem clicou, quando,
de qual versão pra qual, IP de origem e resultado.

**Backend:**

- Migration **V5** cria `update_audit` no DuckDB (PK auto, job_id único,
  campos: kind, user_id, username, ip, from/to_version, backup_timestamp,
  acknowledge_breaking, status, started_at, finished_at).
- `services/audit_service.py` com `record_start()` e `record_finish()`.
  Falha silenciosa — auditoria não derruba o caller.
- `updater.apply_update` e `restore_backup` ganham parâmetros opcionais
  `user_id`/`username`/`ip` e chamam `audit_service.record_start` após
  registrar o job em Redis.
- `_monitor_job` chama `audit_service.record_finish(job_id, status)`
  ao detectar status terminal.
- `routers/audit.py`: `GET /api/v1/audit/updates?limit=N` — capability
  `users.read` (admin + readonly_admin enxergam).
- `routers/updates.py` extrai `user_id` do JWT, busca username via
  `user_repo`, captura IP via `X-Forwarded-For` ou `request.client.host`.

**UI** (`config.php` nova aba "Auditoria"):

- Tabela responsiva com 7 colunas: Quando, Tipo (↑ Update / ↺ Restore),
  Quem, IP, Versão (from→to ou backup_timestamp), Status (badge colorido),
  Duração.
- Botão "Recarregar" no header.
- Lazy-load quando aba abre.
- Status badges: Succeeded (verde), Rolled back (amarelo),
  Rollback failed/Failed (vermelho), Running (azul).

Test do `test_migrate.py` atualizado pra refletir 5 migrations.
110/110 verdes.

VERSION 2.18.0 → 2.19.0 (minor — schema change + aba nova).

---

## v2.18.0 — 2026-05-14

### feat(updates): Histórico de backups na UI + restore manual

Polimento da feature de self-update. Agora a aba "Sistema / Atualizações"
mostra os **últimos 10 backups** disponíveis, e admin pode **restaurar
qualquer um** clicando direto na UI.

**Backend** (`api_service/`):

- `services/updater.list_backups()` — lê `/var/backups/unbound-dashboard/`,
  parsea timestamps `dashboard-YYYYMMDD_HHMMSS.tar.gz`, retorna ordenado
  por mais recente. Marca se há DuckDB e env file associados.
- `services/updater.restore_backup(timestamp)` — valida timestamp, spawna
  `sudo bash restore-backup.sh <job_id> <timestamp>`. Reusa lock global
  `udash:update:running` — só uma operação por vez.
- 2 endpoints novos (capability `config.write`):
  - `GET /api/v1/updates/backups` — lista os últimos 10
  - `POST /api/v1/updates/restore {timestamp}` — dispara restore

**Novo `tools/restore-backup.sh`** — standalone (não depende de update.sh
em andamento). Fluxo:

1. Valida timestamp + backup existe
2. Cria **snapshot pré-restore** em `pre-restore-<ts>.tar.gz` (rollback de emergência)
3. Para api_service
4. `tar xzf` o backup em `/var/www/html/` (path correto, sem o bug do `-C /`)
5. `uv sync` pra reconstruir `.venv` (que não está no backup)
6. Restaura DuckDB e env file se existirem
7. Restart api_service + health check 30s
8. Exit 0 (ok) / 1 (erro) / 2 (health failed)

**Sudoers** ganha entrada pro `restore-backup.sh` com glob restrito
(arg2 = timestamp começa com dígito).

**UI** (`config.php` aba Sistema / Atualizações):

- Card "Histórico de backups" ao final da aba, lista cada backup com:
  - Timestamp + data formatada
  - Tamanho do código + DuckDB
  - Tag colorida indicando DB/env presentes
  - Botão "↺ Restaurar"
- **Modal de confirmação** (z-115) — admin precisa digitar `RESTAURAR`
  num input antes do botão habilitar. Anti-clique-acidental.
- Após confirmar, **reusa o modal de update** pra mostrar log live + status
  final do restore (succeeded/rolled_back/etc).
- Backups recarregam quando aba abre.

**8 testes novos** em `tests/test_updater.py` (list_backups + restore_backup
edge cases). 110/110 verdes.

VERSION 2.17.16 → 2.18.0 (minor — feature nova com UI dedicada).

---

## v2.17.16 — 2026-05-14

### fix(update.sh): `uv sync` incondicional pra blindar `.venv`

Histórico: o `.venv` desaparecia consistentemente após cada update,
mesmo após várias correções (`--delete-excluded` removido, sudoers
corrigido, etc.). Investigação isolada do rsync mostrou que ele
PRESERVA o `.venv` corretamente. A causa raiz nesse ponto pode ser:

- Race condition entre rsync + restart_and_smoke
- Cgroup cleanup quando `daemon-reload` é chamado dentro do scope
- Algum efeito colateral do `chown -R` em `reset_permissions`
- Backup de versão anterior do update.sh (sem fix) que se autoperpetua

Em vez de continuar caçando, fix pragmático: **`uv sync` SEMPRE roda**
no final de `apply_apiservice`, mesmo se `pyproject.toml`/`uv.lock`
não mudaram. É idempotente: se .venv está consistente, é quase no-op.
Se está apagada (do bug), restaura.

Além disso:
- `chown -R www-data:www-data .venv` após sync — garante que se `uv`
  criou .venv como root (rodando via sudo), o api_service consegue ler
- Path fallback adicional pro `uv`: `/root/.local/bin/uv` (instalação
  comum quando user roda `curl ... | sh` como root)

VERSION 2.17.15 → 2.17.16.

---

## v2.17.15 — 2026-05-14

### fix(build): incluir `tools/` no tarball de update

`build-update.sh` excluía o diretório `tools/` inteiro do dashboard
no pacote — então qualquer arquivo em `tools/` (incluindo o
`run-update.sh` adicionado em v2.17.8) nunca chegava em instalações
existentes via update incremental.

Sintoma no test server: ao clicar "Atualizar agora", a UI abria o
modal mas o log ficava vazio porque `_spawn_update_process` chamava
`sudo bash run-update.sh ...` e o wrapper não existia no destino.

Fix: remover `--exclude='tools'` do rsync. Excludes específicos
mantidos:
- `tools/teardown_mariadb.sh` (legado, não usado em prod)
- `tools/docker/` (scripts de docker dev-only)

VERSION 2.17.14 → 2.17.15.

---

## v2.17.14 — 2026-05-14

### UI: pulse animation no badge "Update" da sidebar

Badge "↑ Update" no item Configurações da sidebar ganhou animação
pulse sutil (~2s loop) usando `::after` com ring border + scale.
Chama atenção sem ser intrusivo — admin nota mesmo focado em outra
parte do dashboard.

VERSION 2.17.13 → 2.17.14.

---

## v2.17.13 — 2026-05-14

### fix(updates): botão "Verificar" força refresh do cache Redis (sem F5)

Antes, clicar "Verificar atualizações" não bypassava o cache Redis
(TTL 5min) do `updater.fetch_latest_release`. Quando uma release
nova era publicada, o test server continuava mostrando a versão
anterior por até 5min depois do clique — só dava certo com F5
no navegador (que limpava o estado JS, mas isso era coincidência).

Fix:
- `GET /api/v1/updates/check?force=1` bypassa cache Redis quando
  query param presente
- JS do botão "Verificar" passa `?force=1` por default
- Cache de 5min continua valendo pro worker `update_checker` em
  background e pra leituras passivas (sidebar badge)

VERSION 2.17.12 → 2.17.13.

---

## v2.17.12 — 2026-05-14

### UI: console do update vira modal full-screen com status visual

Antes, ao clicar "Atualizar agora" o console live aparecia inline na
aba "Sistema / Atualizações" — fácil scrollar pra fora, perder o
contexto, ou navegar pra outra parte do dashboard durante o update.

Agora:
- **Modal centralizado** (`z-110`, backdrop blur, `max-w-3xl`) cobre a
  tela com o log live no centro
- **Header com spinner animado** + título "Aplicando atualização"
- **Footer com aviso**: "Não feche esta janela — o update precisa terminar"
- **Botão X (header) desabilitado** enquanto roda; libera quando termina
- **`beforeunload` bloqueia** tentativa de fechar a aba/navegar durante o update
- **Estado final** atualiza header com ícone colorido (✓ verde, ⚠ amarelo,
  ✗ vermelho) + título correspondente + CTA contextual:
  - **Succeeded**: botão verde "Recarregar página" (única ação)
  - **Rolled_back / Rollback_failed / Failed**: botão "Fechar" + CTA crítico
- **ESC + clique no X** fecham só após o update terminar
- `body.overflow = hidden` enquanto modal aberto (trava scroll do fundo)

VERSION 2.17.11 → 2.17.12.

---

## v2.17.11 — 2026-05-14

### fix(systemd): adicionar /var/spool/cron ao ReadWritePaths

Durante self-update via UI no test server, `update.sh` aplicou tudo
(frontend, api_service, sudoers, systemd, apache, php-fpm,
/usr/local/bin) mas quebrou em `apply_system` → `crontab` com:

```
/var/spool/cron/: mkstemp: Sistema de arquivos somente para leitura
```

`crontab` escreve em `/var/spool/cron/crontabs/root`. Esse path não
estava em `ReadWritePaths` do api_service.service — então o subprocess
do update.sh herdava o filesystem read-only ali.

Update funcionalmente OK (VERSION atualizou, services subiram), mas
o cron job de `unbound-dashboard-crons` ficava sem atualizar.

Fix: adicionar `/var/spool/cron` ao ReadWritePaths.

VERSION 2.17.10 → 2.17.11.

---

## v2.17.10 — 2026-05-14

### fix(updates): monitor verifica VERSION quando log trunca

Diagnóstico final do log truncation: o `systemctl daemon-reload` chamado
DE DENTRO de um `systemd-run --scope` desativa o scope (testado isolado;
quando reload vem de fora, scope sobrevive). Não há config trivial de
systemd que cubra esse caso.

**Solução pragmática**: tornar o monitor robusto a logs truncados:
- Polleia tanto o LOG (marker explícito) quanto o `VERSION` file
- Se `VERSION` bate com `to_version`, considera sucesso mesmo sem marker
- Backstop reduzido de 60min → 5min (não há razão pra update demorar mais)
- `_resolve_final_status()` combina ambas as fontes

Resultado: a UI mostra "Update aplicado com sucesso" se VERSION cresceu,
independente de o log SSE ter cortado ou não.

Wrapper `run-update.sh` mantido como `systemd-run --scope` — quando
funciona (log curto + sem daemon-reload), preserva log completo.

3 testes novos em test_updater.py (103/103 totais).

VERSION 2.17.9 → 2.17.10.

---

## v2.17.9 — 2026-05-14

### fix(updates): mover update.sh pra cgroup independente via systemd-run --scope

Diagnóstico final do bug "log corta em Apache conf atualizado":

- Subprocess do update.sh era filho do uvicorn → estava no cgroup
  `/system.slice/unbound-dashboard-api.service/`
- `update.sh` chama `systemctl daemon-reload` ao instalar nova unit
- systemd reaplica restrições/namespaces ao cgroup → mata processos

Tentativas anteriores que falharam:
- v2.17.3: `systemd-run --unit=...` (transient unit em /run/systemd/
  transient é REMOVIDA por daemon-reload, mata processo)
- v2.17.8: redirect via shell (resolveu o fd mas o subprocess continua
  no cgroup errado)

**Fix definitivo**: `systemd-run --scope --slice=system.slice` cria um
cgroup-scope filho direto do system.slice, totalmente fora do cgroup
do api_service. daemon-reload não afeta. Sudoers continua permitindo
só `run-update.sh <job_id> <tarball>` com glob restrito.

VERSION 2.17.8 → 2.17.9.

---

## v2.17.8 — 2026-05-13

### fix(updates): log SSE completo (era cortado em "Apache conf atualizado")

Bug cosmético do pipeline self-update: depois de `restart_and_smoke()`
reiniciar o api_service (parent do subprocess do update), o log SSE
parava de gravar em "Apache conf atualizado". O update continuava e
terminava com sucesso, mas a UI só via metade.

Causa: o fd do log era aberto pelo Python e passado pro Popen via
`stdout=log_fd`. Quando o uvicorn (parent) morria no restart, o fd
era cortado e nada mais era escrito.

Fix: voltar com o wrapper `tools/run-update.sh` (sem systemd-run desta
vez). O wrapper faz `exec >> $LOG 2>&1` ANTES de invocar update.sh —
o fd do log nasce no shell do filho, dentro do session group novo
(`start_new_session=True` no Popen), independente do uvicorn. Sobrevive
ao restart.

Updater.py spawna `sudo bash run-update.sh <job_id> <tarball>` com
stdout/stderr/stdin = DEVNULL. Sudoers ajustado pra wrapper. Log_path
é tocado antes do spawn pra SSE encontrar o arquivo imediatamente.

VERSION 2.17.7 → 2.17.8.

---

## v2.17.7 — 2026-05-13

### UI: botão "Verificar atualizações" repaginado

Antes era um glass-btn discreto com `⟳` unicode, fácil de não notar. Agora:

- Botão com **gradient cyan→blue**, shadow + leve halo no hover, transform
  `translateY(-1px)` em hover (microinteração visível)
- Texto completo "Verificar atualizações" (era só "Verificar")
- Ícone SVG (Heroicons arrow-path) com animação `rotate` 360° quando
  verificando, via classe CSS `.is-checking`
- Label abaixo: **"Última verificação há Xs · auto-check 6h"** — admin
  enxerga frescor do dado sem precisar adivinhar
- Tick a cada 30s atualiza o "há Xs" client-side

VERSION 2.17.6 → 2.17.7.

---

## v2.17.6 — 2026-05-13

### fix(update.sh): remover `--delete-excluded` que apagava .venv

`apply_apiservice` no `tools/update.sh` tinha `rsync -a --exclude='.venv'
--delete-excluded`. A intenção era impedir que `.venv` fosse copiada
do source (tarball) pro destino. Mas `--delete-excluded` faz mais que
isso: implica `--delete` e remove do destino qualquer arquivo
correspondente aos patterns de exclusão — **apaga o `.venv` existente
no destino**.

Quando `pyproject.toml`/`uv.lock` mudavam, `update.sh` rodava
`uv sync --no-dev` em seguida e tudo voltava. Quando não mudavam
(`need_uv_sync=false`), o sync era pulado e o `.venv` ficava apagado —
api_service falha com `ModuleNotFoundError`/`No such file or directory:
uvicorn`.

Fix: remover `--delete-excluded`. As `--exclude` continuam (impedem
cópia do source, que de toda forma não tem `.venv` no tarball).

VERSION 2.17.5 → 2.17.6.

---

## v2.17.5 — 2026-05-13

### fix(updates): expandir ReadWritePaths em vez de systemd-run

A v2.17.3 tentou rodar `update.sh` numa transient unit do systemd via
`systemd-run --unit=...` pra escapar do namespace mount do api_service.
Não funcionou: `update.sh` chama `systemctl daemon-reload` ao instalar
a nova unit file, e isso REMOVE a transient unit (que vive em
`/run/systemd/transient/`), matando o processo.

**Fix pragmático**: voltar a spawnar `sudo bash update.sh` direto, mas
expandir `ReadWritePaths` no `unbound-dashboard-api.service` pra cobrir
todos os paths que update.sh toca:

```
/var/lib/unbound-dashboard
/var/log/unbound-dashboard
/var/www/html/unbound-dashboard
/var/backups/unbound-dashboard
/etc/sudoers.d
/etc/systemd/system
/etc/apache2
/etc/php
/etc/unbound-dashboard
/usr/local/bin
/tmp
```

`ProtectSystem=strict` continua protegendo o resto do `/`. Trade-off:
o subprocess do update herda esses paths como writable, mas como ele é
spawnado com `sudo` rodando como root, qualquer um desses paths já
estaria acessível mesmo sem ReadWritePaths.

Mantido `tools/run-update.sh` no repo como histórico — não é mais
chamado pelo updater.py.

VERSION 2.17.4 → 2.17.5.

---

## v2.17.4 — 2026-05-13

### fix(updates): redirect via shell em vez de systemd StandardOutput

`update.sh` chama `systemctl daemon-reload` ao instalar a nova
unit file. Isso fecha o file descriptor que o systemd estava mantendo
via `--property=StandardOutput=append:<log>` — então o resto do log
do update sumia (output ia pro vazio).

**Fix**: `tools/run-update.sh` agora usa `bash -c "exec update.sh ... >> $LOG 2>&1"`
dentro da unit transient. O redirect é feito pelo shell do filho,
imune a `daemon-reload`.

VERSION 2.17.3 → 2.17.4.

---

## v2.17.3 — 2026-05-13

### fix(updates): escapar do namespace mount via systemd-run

`update.sh` rodado como subprocess de `sudo` herdava o mount-namespace
do api_service.service (`ProtectSystem=strict` + ReadWritePaths limitado).
Resultado: o tar do backup falhava silenciosamente porque `/var/backups`
não estava nos paths permitidos. Adicionar todos os paths necessários
(/var/backups, /etc/sudoers.d, /etc/systemd/system, /etc/apache2, …) ao
ReadWritePaths seria fragil e equivalente a remover ProtectSystem.

**Fix elegante**: novo `tools/run-update.sh` (wrapper) que invoca
`update.sh` via `systemd-run --unit=unbound-dashboard-update-<job_id>
--collect --property=StandardOutput=append:LOG`. systemd-run cria uma
unit transient como filho do init (PID 1) — totalmente fora do namespace
do api_service. Log do update vai direto pro arquivo via systemd
StandardOutput=append.

**`services/updater.py`** atualizado:
- `_spawn_update_process()` chama o wrapper em vez de update.sh direto.
- `_monitor_job()` agora polleia o log atrás de marcadores finais
  ("Update concluído", "ROLLBACK CONCLUÍDO", "ROLLBACK FAILED") em vez
  de `/proc/<pid>` (PID era do sudo+systemd-run que sai cedo).
- Helper `_log_has_terminal_marker()`.
- Backstop de 60min — log sem marcador é marcado como `failed`.

**Sudoers** atualizado pra permitir o wrapper com glob restrito ao
job_id (`*` antes do path do tarball — sudoers parsea como token sem
espaço, então não permite injeção via `..`).

VERSION 2.17.2 → 2.17.3.

---

## v2.17.2 — 2026-05-13

### fix(updates): correções descobertas em teste end-to-end

Bugs detectados ao testar o pipeline de self-update completo em ambiente
de repo privado:

1. **`browser_download_url` descarta `Authorization` em repo privado** —
   GitHub redireciona pra S3 com query-string e o curl/httpx não passa
   o Bearer no follow_redirects. Resultado: 404 ao baixar asset.
   **Fix**: usa a API URL (`api.github.com/.../assets/<id>`) com
   `Accept: application/octet-stream` quando há `GITHUB_TOKEN`
   configurado. `_find_assets` agora preserva tanto `api_url` quanto
   `browser_download_url` no payload.

2. **`/var/log/unbound-dashboard/` não estava no `ReadWritePaths`** do
   systemd unit — `services/updater.py` falhava com `OSError: Read-only
   file system` ao tentar criar o `update-<job_id>.log`. **Fix**:
   adicionado ao `ReadWritePaths` no unit file + `install.sh` cria o dir
   com perms `750 www-data`.

3. **`NoNewPrivileges=yes` bloqueava `sudo`** — sudo precisa do bit
   setuid root pra escalar privilege, e essa flag impede. update.sh
   abria com erro `"The 'no new privileges' flag is set"` antes mesmo
   de tentar rodar. **Fix**: `NoNewPrivileges=no` no unit, com comentário
   explicando o trade-off (perde-se um pouco de hardening, ganha-se a
   feature de self-update). Sudoers já é granular (path + glob restrito).

Após esses 3 fixes, o pipeline aplica end-to-end: download → SHA256 →
spawn update.sh com sudo → backup → extract → health check → ok.

VERSION 2.17.1 → 2.17.2.

---

## v2.17.1 — 2026-05-13

### chore: release de validação do pipeline self-update

Sem mudanças de código — release publicada pra testar o botão "Atualizar
agora" end-to-end. Após essa release, /api/v1/updates/check retorna
`has_update: true` em qualquer instalação rodando v2.17.0, e o botão
dispara o pipeline completo (download → SHA256 → spawn update.sh →
SSE live log → health check → rollback se falhar).

VERSION 2.17.0 → 2.17.1.

---

## v2.17.0 — 2026-05-13

### Self-update via UI — clica e atualiza, com rollback automático

Sai do `scp + sudo bash update.sh ...` manual. Admin agora atualiza o
sistema direto pela aba **Sistema / Atualizações** em
`config.php` (admin-only). Pipeline completo: download verificado por
SHA256 do GitHub Releases → spawna `update.sh` → log live via SSE →
rollback automático se health check pós-restart falhar.

**Pipeline (6 fases, todas implementadas):**

1. **`tools/release.sh`** — publica GitHub Release a partir do `VERSION`
   atual. Extrai notas do CHANGELOG (`## v<X.Y.Z>`), gera tarball +
   `.sha256` via `build-update.sh` (sem auto-bump), `gh release create`
   com os 2 assets. Suporta `DRAFT=true` e `PRERELEASE=true`.
2. **sudoers + dir** — `system/sudoers/unbound-dashboard` ganha entrada
   pra `www-data` rodar `bash tools/update.sh
   /var/lib/unbound-dashboard/updates/unbound-dashboard-update-v[0-9]*.tar.gz`
   (glob restrito anti-injection). `install.sh` cria
   `/var/lib/unbound-dashboard/updates/` 750 www-data. `update.sh`
   ganha `restart_and_smoke` resiliente (loop 30s até healthz=200) +
   `rollback_from_backup()` automático: exit 2 = rolled_back, exit 3 =
   ROLLBACK FAILED.
3. **API REST** (`/api/v1/updates/*`, todos `config.write`):
   - `GET /check` — consulta GitHub Releases, cacheia 5min Redis,
     compara com `VERSION` local. Detecta `has_update` + `is_major_bump`.
   - `POST /apply {version, acknowledge_breaking}` — lock global Redis
     (anti-concorrência), refresh release (anti-replay), download +
     valida SHA256, spawn `sudo update.sh` detachado, registra job em
     Redis, retorna `{job_id}`.
   - `GET /status/{job_id}` — estado: running/succeeded/failed/
     rolled_back/rollback_failed.
4. **SSE** `GET /log/{job_id}` — StreamingResponse com generator async
   que faz `tail -f` do `/var/log/unbound-dashboard/update-<job_id>.log`,
   heartbeat 15s pra manter keepalive vivo, evento `done` final com
   payload JSON do estado. Validação `job_id` = 12 chars hex (anti
   path-traversal).
5. **UI** — nova aba **Sistema / Atualizações** em `config.php`:
   - Status card com versão atual vs última no GitHub
   - Banner contextual (up-to-date / update / major bump warning /
     GitHub off)
   - Botão "Atualizar pra vX.Y.Z" + checkbox obrigatório se major
   - Painel notas da release (CHANGELOG do tag)
   - Console live via `fetch + ReadableStream` (não EventSource, pra
     manter Authorization Bearer)
   - Banner final colorido (✓ / ⚠ rollback / ✗ rollback failed / ✗ failed)
6. **Worker `update_checker`** — `app/workers/update_checker.py`
   polleia GitHub a cada 6h, cacheia em Redis `udash:update:latest`.
   Sidebar (`includes/sidebar.php`) lê o cache via API (60s PHP cache)
   e mostra badge azul **"↑ Update"** no item Configurações + link
   direto pra `config.php?tab=updates`.

**Repo privado**: adicionada setting `GITHUB_TOKEN` em `core/config.py`.
Sem ela, GitHub retorna 404 nas chamadas da API. `api-v1.env.example`
documenta como gerar e onde colocar.

**Riscos mitigados:**

- **Path traversal**: glob sudoers + regex `_validate_job_id` 12 hex
- **Tarball MITM**: SHA256 obrigatório do mesmo Release
- **2 updates simultâneos**: lock Redis `udash:update:running` (TTL 30min,
  409 Conflict)
- **Major bump acidental**: `acknowledge_breaking=true` obrigatório
- **Health check pós-update falha**: rollback automático
- **Rollback falha**: exit 3 + banner vermelho intervenção manual

**21 testes novos** (`tests/test_updater.py`) cobrem: semver parse,
sha256 verify, asset discovery, status inference, GitHub off,
major bump detect, ack obrigatório, version mismatch (anti-replay),
lock, SSE format, job_id validation.

100/100 testes verdes (era 79; +21 updater).

VERSION 2.16.3 → 2.17.0 (minor — feature grande, sem breaking changes).

---

## v2.16.3 — 2026-05-13

### fix(deps): httpx promovido pra dependência de produção

`services/webhook_notifier.py` (introduzido em v2.14.0) usa `httpx`
pra POST nos webhooks, mas `httpx` estava em `[dependency-groups].dev`.
No dev local funciona porque `uv sync` instala dev por padrão. No
test/prod, `uv sync --no-dev` no `update.sh` removia o httpx — e o
import-time no boot do api_service quebrava com:

```
ModuleNotFoundError: No module named 'httpx'
```

Movido pra `[project].dependencies` com comentário explicativo (mesmo
padrão do `pandas` que já tinha o mesmo cuidado).

VERSION 2.16.2 → 2.16.3.

---

## v2.16.2 — 2026-05-13

### fix(sessions): tracking captura User-Agent e IP do navegador

A tabela "Sessões Ativas" mostrava sempre `?` no User-Agent e
`127.0.0.1` no IP. Causa: tracking (`require_auth` no FastAPI) lia
os headers do request, mas o request HTTP chega via curl interno do
PHP — então o UA era vazio e o IP era sempre o localhost da
máquina-host.

Fix: `ApiClient::_clientPassthroughHeaders()` extrai
`$_SERVER['HTTP_USER_AGENT']` e `$_SERVER['REMOTE_ADDR']`/
`$_SERVER['HTTP_X_FORWARDED_FOR']` e propaga via headers
`User-Agent:` + `X-Forwarded-For:` em **todas as chamadas internas**
(`login`, `login2faVerify`, `changePassword`, `get`, `post`, `put`,
`delete`). FastAPI já lia `X-Forwarded-For` corretamente em
`deps.py` (linha 71) — bastava o backend PHP propagar.

Sessões antigas com `?`/`127.0.0.1` são sobrescritas no próximo
request autenticado (UPSERT por token_hash).

VERSION 2.16.1 → 2.16.2.

---

## v2.16.1 — 2026-05-13

### Sessões ativas — filtro de linhas (10/50/100/Todas)

Lista de "Sessões Ativas" na aba **Meu Perfil** podia ficar enorme em
contas usadas em múltiplos dispositivos/navegadores ao longo do tempo
(TTL = exp do JWT = 60min default, mas tracking acumula até expirar).
Adicionado select client-side com opções **10 / 50 / 100 / Todas**
(default: 10). Contador "X/N sessão(ões)" atualizado conforme o filtro.

VERSION 2.16.0 → 2.16.1.

---

## v2.16.0 — 2026-05-13

### 2FA TOTP opt-in por usuário

Saiu do standby. Cada user pode ativar voluntariamente 2FA via app
autenticador (Google Authenticator, Authy, Aegis, 1Password, etc).
Implementação RFC 6238 padrão; sem backup codes nesta versão — admin
reseta se user perder o celular.

**Backend (`api_service/`):**

- **Migration V4**: adiciona `users.totp_secret VARCHAR(64)` (nullable) e
  `users.totp_enabled BOOLEAN` (nullable; tratado como `false` quando NULL).
  DuckDB 1.x não suporta `ADD COLUMN NOT NULL DEFAULT` em ALTER, daí o
  workaround com UPDATE pós-ALTER.
- **`services/totp_service.py`** — wrapper fino sobre `pyotp`:
  - `generate_secret()` base32 32-chars (160-bit entropy)
  - `provisioning_uri(secret, username)` — formato `otpauth://totp/...`
  - `verify(secret, code)` com `valid_window=1` (aceita ±30s clock skew),
    aceita espaços no code (`123 456` → `123456`).
- **Novo flow de login em 2 passos**:
  1. `POST /auth/login` com user+pass → se `totp_enabled`, retorna
     `{requires_totp: true, challenge_token}` em vez do JWT.
  2. `POST /auth/login/2fa-verify` com `{challenge_token, code}` → JWT.
  - `challenge_token` é JWT especial com claim `totp_pending: true`,
    TTL 5min. Validação `iat`/`exp` normal.
- **Endpoints 2FA** (todos require_auth):
  - `POST /auth/2fa/setup` → gera secret + URI (NÃO persiste).
  - `POST /auth/2fa/confirm` `{secret, code}` → valida e persiste.
  - `POST /auth/2fa/disable` `{code}` → self-disable com code obrigatório.
  - `POST /auth/2fa/admin-reset/{user_id}` → requer `users.manage`,
    self-target permitido (admin que perdeu próprio celular).
- `/auth/me` agora retorna `totp_enabled`. List de users também.

**PHP (`src/Auth.php` + `src/ApiClient.php`):**

- `Auth::login()` detecta `requires_totp` no response, guarda challenge
  em sessão (`totp_challenge`, `totp_username`, `totp_started_at`),
  sinaliza pro `login.php` redirecionar pra `login_2fa.php`.
- Novo `Auth::login2faSubmit($code)` — troca challenge+code por JWT real.
- `Auth::setup2fa()`, `confirm2fa()`, `disable2fa()`, `adminReset2fa()` —
  wrappers que chamam os endpoints novos.
- `_finalizeLogin()` extraído como helper privado pra evitar duplicação
  entre `login()` e `login2faSubmit()`.

**UI:**

- **`login_2fa.php` novo** — página de 2º passo com input numérico de 6
  dígitos. Auto-submit ao digitar 6 dígitos. Expira local após 5min.
- **`config.php` aba "Meu Perfil"** ganha seção 2FA:
  - Desativado: botão "Ativar 2FA" → POST `setup_totp` → mostra QR code
    (gerado clientside via qrcode.js do CDN, zero deps server) + secret
    fallback + input pra confirmar code.
  - Ativado: form com input de code pra "Desativar 2FA".
- **Tabela de usuários** ganha:
  - Badge "🛡️ 2FA" ao lado do status quando habilitado.
  - Botão "🛡️↺" admin-reset (só aparece se user tem 2FA ativo) com
    confirmação destrutiva.

**Testes**:

- `tests/test_totp.py` com 7 testes: secret format, URI format, verify
  válido/inválido, rejeição de não-numérico/wrong-length, strip de
  espaços, edge cases.
- `tests/test_migrate.py` atualizado pra esperar 4 migrations.

79/79 testes verdes (era 72; +7 TOTP).

VERSION 2.15.1 → 2.16.0 (minor — feature nova + schema change).

---

## v2.15.1 — 2026-05-13

### RBAC — completa migração de endpoints e páginas restantes

Após v2.15.0 (RBAC granular), faltavam routers e páginas que ainda usavam
`require_admin` / `Auth::isAdmin()` em vez do mapeamento por capability —
isso impedia readonly_admin/operator de enxergar conteúdo apropriado.

**API routers migrados pra `require_capability`:**

| Endpoint                          | Antes        | Agora                       |
|-----------------------------------|--------------|------------------------------|
| `GET /threats/data`               | require_admin| `blocklist.read`             |
| `GET /exports/query-logs`         | require_admin| `blocklist.read`             |
| `GET /exports/stats-report`       | require_admin| `blocklist.read`             |
| `GET /exports/blocklist`          | require_admin| `blocklist.read`             |
| `GET /exports/settings`           | require_admin| `config.read_sensitive`      |
| `POST /exports/settings/bulk`     | require_admin| `config.write`               |
| `GET /users`                      | require_admin| `users.read`                 |
| `POST /users`                     | require_admin| `users.manage`               |
| `PUT /users/{id}/active`          | require_admin| `users.manage`               |
| `PUT /users/{id}/role`            | require_admin| `users.manage`               |
| `DELETE /users/{id}`              | require_admin| `users.manage`               |
| `POST /users/{id}/password-reset` | require_admin| `users.manage`               |
| `PUT /users/{id}/email`           | admin OR self| `users.manage` OR self       |

**Páginas PHP migradas pra `Auth::can()`:**

- `health.php` → `dashboard.read` (todos os roles veem hardware/saúde)
- `threats.php` → `blocklist.read` (operator + RO-admin + admin)
- `logs.php` → `blocklist.read`
- `exports.php` → `blocklist.read` (settings export rejeitado no API se sem cap)
- `alerts.php` gate → `alerts.read`
- Botões em `alerts.php` (Reconhecer, Limpar Resolvidos, Editar Limiares)
  e `blocklist.php` (Atualizar Agora) → `Auth::can('alerts.resolve')` /
  `Auth::can('blocklist.write')`

Mantidos como `isAdmin()` por serem operações ativas no servidor:
- `diagnostics.php`, `dns_benchmark.php` (executam comandos no host)

Mensagem de erro no `users_service.update_role` atualizada com lista
correta dos 4 roles válidos.

72/72 testes verdes. VERSION 2.15.0 → 2.15.1.

---

## v2.15.0 — 2026-05-13

### Custom roles — RBAC granular com 4 papéis

Antes só havia `admin` (acesso total) e `viewer` (read-only). Times de
NOC e auditoria precisavam de granularidade — agora há 4 roles + RBAC
por capability.

**Roles:**

| Role            | Quem usa                  | O que pode |
|-----------------|---------------------------|------------|
| `admin`         | Sysadmin                  | Acesso total (writes + reads sensíveis + gestão de users + SMTP/webhooks) |
| `readonly_admin`| Auditoria, supervisão     | Lê tudo (inclui SMTP/webhooks/users) mas NÃO modifica nada |
| `operator`      | NOC, suporte L1/L2        | Resolve alertas + mantém blocklist. Lê alertas/threats. NÃO vê SMTP/webhooks/users |
| `viewer`        | Visualização              | Read-only básico: dashboard, history, threats |

**Novo módulo `core/rbac.py`** — mapeia capabilities ↔ roles em um único
dict. 11 capabilities granulares:

```
config.write          → admin
users.manage          → admin
webhooks.manage       → admin
smtp.manage           → admin
alerts.resolve        → admin, operator
blocklist.write       → admin, operator
alerts.read           → admin, readonly_admin, operator
blocklist.read        → admin, readonly_admin, operator
users.read            → admin, readonly_admin
config.read_sensitive → admin, readonly_admin
dashboard.read        → admin, readonly_admin, operator, viewer
```

**`require_capability(cap)`** — nova dependency no `core/deps.py`. Factory
que valida payload JWT contra capability:

```python
@router.put("/foo", dependencies=[Depends(require_capability("config.write"))])
```

Substituído `require_admin` por `require_capability` nos endpoints
operacionais sensíveis: `/api/v1/alerts/list` (alerts.read), `/resolve`
(alerts.resolve), `/clear-resolved` (alerts.resolve), `/blocklist/counts`
(blocklist.read), `/clear-category` e `/bulk-insert` (blocklist.write).
Webhooks/SMTP/users continuam com `require_admin` (mapeamento implícito
admin-only via `*.manage`).

**`Auth::can($capability)` no PHP** — espelha o mapeamento Python pra
checagem na UI. `Auth::rolesCatalog()` retorna metadata humano (label +
descrição) pra alimentar dropdowns.

**`VALID_ROLES` agora importa de `rbac.VALID_ROLES`** (mantém Python e
PHP sincronizados — uma única fonte de verdade).

**UI:**

- Filtro de role + modal "Novo Usuário" + select inline de role na tabela
  agora populados via `Auth::rolesCatalog()` (4 opções).
- Cada `<option>` traz `title=` com a descrição (tooltip ao passar mouse).
- Roles novos aceitos no backend automaticamente — sem migration.

**9 testes novos** (`tests/test_rbac.py`):

- admin tem todas capabilities
- readonly_admin lê tudo mas não modifica
- operator opera (alerts.resolve + blocklist.write) mas não vê sensíveis
- viewer só dashboard.read
- role/capability inexistentes → deny by default

72/72 testes verdes (era 63).

VERSION 2.14.0 → 2.15.0.

---

## v2.14.0 — 2026-05-13

### Webhooks de alertas — Slack / Discord / Teams / Genérico

Alertas críticos (CPU, RAM, swap, disco, DNS travado, etc.) agora podem
notificar canais externos via webhook. Configurável pelo UI (admin only).

**Novo serviço `services/webhook_notifier.py`:**

- `notify(alert_type, severity, message)` chamado automaticamente por
  `AlertChecker._raise_alert` após inserir o alerta no DuckDB. Best-effort
  — falha HTTP/conexão é logada mas não derruba o worker.
- Suporta 4 formatos:
  - **Slack** / **Teams**: `{"text": "..."}`. Compatível com Incoming Webhooks.
  - **Discord**: `{"content": "..."}` (truncado em 1900 chars).
  - **Generic**: JSON puro com `{type, severity, message, timestamp, source}`.
- Throttle por tipo (15min) usando Redis (`udash:webhook:cooldown:<type>`).
  Sem Redis = sempre envia (best-effort).
- Filtro de severity_min: só dispara se severity ≥ configurado
  (`info < warning < critical`).
- Timeout HTTP de 5s (worker não trava).

**Novo router `routers/webhooks.py`:**

- `GET /api/v1/webhooks/config` — admin only, retorna config atual.
- `PUT /api/v1/webhooks/config` — admin only, salva enabled/url/type/severity_min.
  Valida tipo + severity_min + URL com http/https quando enabled.
- `POST /api/v1/webhooks/test` — dispara envio de teste, ignora cooldown
  e severity_min. Retorna corpo da resposta do servidor (≤500 chars).

**Settings persistidos no DuckDB** (tabela `settings` existente — sem
migration nova):

- `webhook_enabled`, `webhook_url`, `webhook_type`, `webhook_severity_min`.

**Nova aba "Webhooks de Alertas"** em `config.php` (admin only):

- Card status verde/cinza com tipo + severity_min.
- Form: habilitar, URL, tipo (Slack/Discord/Teams/Generic), severity mínima
  (warning ou critical).
- Card "Enviar teste" com input opcional de mensagem + display da resposta
  HTTP do servidor.
- Cheat-sheet com instruções de criar webhook em Slack/Discord/Teams +
  spec do payload genérico.

**Hook em `alert_checker.py`:**

`_raise_alert()` agora chama `webhook_notifier.notify()` logo após
INSERT bem-sucedido — alerta novo dispara notificação. Alertas dedupados
(`existing != None`) não re-disparam.

63/63 testes verdes (sem regressão).

VERSION 2.13.0 → 2.14.0 (minor — feature nova).

---

## v2.13.0 — 2026-05-13

### Persistência de sessões em DuckDB — sobreviver restart Redis

Antes, sessões ativas viviam **só no Redis** (`udash:session:<user>:<hash>`).
Restart do Redis (manutenção, OOM-killer, falha) limpava todo o histórico
de "Sessões Ativas" — admin perdia visibilidade até users fazerem novos
requests. Agora há **dual-write Redis + DuckDB** com bootstrap.

**Migration V3** (`migrations/duckdb/V3__auth_sessions.sql`):

Nova tabela `auth_sessions`:
- `token_hash` PK (SHA256 truncado, igual ao Redis)
- `user_id`, `ip`, `user_agent`
- `iat`, `exp`, `login_at`, `last_seen` (todos unix epoch)
- `revoked_at` (NULL = ativa)

**`services/sessions.py` dual-write:**

- `track()` agora escreve **sempre no Redis** (fast path, in-memory) e
  **escreve no DuckDB com throttle de 30s** — evita pressão no executor
  do DuckDB pra sessões ativas que chamam track() a cada request.
  Throttle controlado por campo `last_persisted_duckdb` no payload Redis.
- Se Redis cair, DuckDB recebe a escrita direto (best-effort, sem throttle).
- UPSERT via `ON CONFLICT (token_hash) DO UPDATE` — atualiza só ip/ua/last_seen.

**`list_for_user()` / `list_all()`** — union Redis ∪ DuckDB, dedupado
por token_hash, prefere o registro com `last_seen` mais recente. Se
Redis estiver vazio (restart recente), DuckDB cobre o gap.

**`remove()`** — marca `revoked_at` no DuckDB + delete no Redis. Não toca
o denylist do JWT (chamador é responsável).

**Bootstrap** (`bootstrap_from_duckdb()`, chamado no `lifespan` startup):

1. **Cleanup**: deleta rows com `exp` mais antigo que 30 dias (impede
   crescimento sem fim em sistemas com muito churn de usuário).
2. **Rehydrate**: carrega sessões ainda válidas e não-revogadas do DuckDB,
   reescreve no Redis com TTL adequado. Só sobrescreve se a chave Redis
   ainda não existe (race com tracking após restart).
3. Logs `sessions.bootstrap_done` com contador rehydrated/total.

**Testes** (`tests/test_sessions_persistence.py`, 5 novos):

- track persiste no DuckDB quando Redis off
- remove marca revoked_at (não retorna mais em list_for_user)
- list filtra sessões expiradas (`exp <= now`)
- bootstrap deleta rows com exp+30d < now
- track subsequente atualiza last_seen mas preserva login_at

63/63 testes verdes (era 58).

VERSION 2.12.0 → 2.13.0 (minor — schema change + nova feature).

---

## v2.12.0 — 2026-05-13

### changelog.php repaginada — busca + filtros + accordion + render markdown

Página de Histórico de Versões antes era só `<pre>` cru do CHANGELOG.md
(1868 linhas em um único bloco — impraticável navegar). Agora segue o
padrão das outras páginas repaginadas (alerts/history/threats/logs/etc).

**Parser server-side** (`parseChangelog()`):
- Quebra o CHANGELOG.md em entries por header `## vX.Y.Z — YYYY-MM-DD`.
- Detecta tipo da release pelo semver: **major** (X.0.0), **minor**
  (X.Y.0), **patch** (X.Y.Z).
- Cada entry: version, date, type, body.
- 47 entries detectadas no CHANGELOG atual (1 major, 11 minor, 35 patch).

**Render markdown próprio** (`renderChangelogMarkdown()` + `inlineMd()`):
- Suporta H3/H4, listas, code blocks ` ``` `, code inline ` ` `,
  **bold**, *italic*, [links](url) só HTTPS, --- hr.
- Zero dependências externas (sem composer).
- Estilizado pra glass-panel (dark+light).

**UI:**
- 4 stat cards no topo: Total / Major / Minor / Patch (com cores).
- Busca client-side (versão, data, texto do corpo).
- Chips de filtro: Todos / Major / Minor / Patch.
- Checkbox "Expandir tudo" pra abrir todas entries visíveis.
- Contador "X/N visíveis".
- Cada entry vira um `<details>` (accordion) — primeira aberta por
  default, demais colapsadas pra navegação rápida.
- **Badge "Atual"** + ring cyan na entry da versão ativa (lida do VERSION).
- Empty state quando filtro zera resultado.

VERSION 2.11.1 → 2.12.0 (UI repagination — bump minor consistente com
outras repagings).

---

## v2.11.1 — 2026-05-13

### SMTP — parser de erros + dica acionável no teste

Em produção, o teste de envio retornava só a resposta crua do servidor
SMTP (ex: `525 5.7.13 <END-OF-MESSAGE>: Este remetente nao tem permissao
para enviar`). Pra admin desavisado parecia bug do código quando na
verdade era config do provedor.

**Novo `Mailer::interpretSmtpError()`** mapeia respostas comuns pra
dicas acionáveis em português:

- **Sender não autorizado** (525, 553, "not authorized", "permissao",
  "address rejected"): explica que o endereço `From` precisa ser
  verificado no painel do provedor (Mailgun/SES/SendGrid/smptlw).
- **SPF/DKIM/DMARC**: instrui configurar DNS.
- **Autenticação** (535, "auth fail"): user/senha inválidos; lembra
  do App Password do Gmail e do user="apikey" do SendGrid.
- **STARTTLS/encriptação** (530, "must issue"): porta/encr não batem.
- **Relay denied**: precisa de auth ou IP allowlist.
- **Connection refused/timeout**: firewall, porta bloqueada (cloud
  geralmente bloqueia 25/587).
- **Mailbox unavailable** (550): destinatário inexistente.
- **Quota** (452, "over quota"): limite da conta.
- **Blacklist**: aponta pro mxtoolbox.com/blacklists.
- **421/451**: temporário / greylisting.

Detecção bilíngue: match em "sender" + "remetente", "permissao" +
"permission", etc. — provedores brasileiros que retornam mensagens em
português agora são reconhecidos.

**UI (`config.php` aba Email):**

Após teste de envio com falha, **card amarelo "💡 Dica baseada no erro"**
aparece acima do log SMTP, mostrando a dica humana. O log raw continua
visível abaixo pra debug avançado.

VERSION 2.11.0 → 2.11.1.

---

## v2.11.0 — 2026-05-12

### Nova aba "Email / SMTP" — configuração de cliente SMTP integrado

Sistema agora pode enviar emails via servidor SMTP configurado pela UI
(antes só `mail()` do PHP, que depende de MTA local raramente
configurado). Usado por: password reset (já integrado em
v2.10.0) e, no futuro, alertas críticos por email.

**Cliente SMTP puro PHP** em `src/Mailer.php` (sem deps composer):

- Implementação via `stream_socket_client()` + comandos SMTP padrão.
- Suporta **3 modos de encriptação**:
  - `tls` — STARTTLS (porta 587, recomendado, Gmail/Outlook/SES)
  - `ssl` — SMTPS (porta 465, TLS direto)
  - `none` — porta 25 sem encriptação (relay local)
- Suporta **AUTH LOGIN** (compatível com qualquer SMTP padrão).
- Dot-stuffing automático no body (linhas começando com `.` viram `..`).
- Subject encoded em UTF-8 base64 (RFC 2047) se tiver chars não-ASCII.
- Timeout de 10s na conexão e leitura.
- Coleta log completo da conversa SMTP pra debug.

**Nova aba "Email / SMTP"** em `config.php` (admin only):

- Card "Status" com indicador verde/cinza: SMTP habilitado ou usando
  `mail()` fallback.
- Form de configuração: host, porta, encriptação, user, senha,
  from (email + nome).
  - **Senha mascarada** — campo placeholder "••••••• (deixe vazio
    pra manter)" pra evitar perda acidental. Backend só atualiza
    se admin digitar algo novo.
- Botão **"Enviar Teste"** — envia email pra destinatário arbitrário
  + mostra o **log SMTP completo** abaixo (conversa cliente↔servidor
  inteira, útil pra debug de auth/cert/firewall).
- **Cheat-sheet** com configurações de 6 provedores comuns:
  Gmail, Outlook 365, AWS SES, Mailgun, SendGrid, Postfix local.

**Settings persistidos no DuckDB** (tabela `settings` existente — sem
migration):

- `smtp_enabled`, `smtp_host`, `smtp_port`, `smtp_encryption`,
  `smtp_user`, `smtp_password`, `smtp_from`, `smtp_from_name`.
- Senha em **plaintext** no DB (escopo interno; use conta dedicada).

**`Auth::requestPasswordReset` integrado** — usa `Mailer::send()`
em vez de `mail()` direto. Email vai via SMTP configurado (ou
fallback `mail()` se SMTP off). Log local em
`src/data/password-recovery.log` mantido com nova coluna
`via=smtp|php-mail` pra rastreabilidade.

**Estrutura HTML** — `tab-email` posicionada **fora** do
`mainConfigForm` (forms próprios, mesmo padrão de `tab-ntp` e
`tab-perfil`). `applyTabSwitch` esconde o "Sincronizar Todas" também
na aba `email`.

VERSION 2.10.0 → 2.11.0 (minor bump — feature nova com persistência).

---

## v2.10.0 — 2026-05-12

### Recuperação de senha funcional + Sessões ativas listáveis/revogáveis

Duas features de auth fechadas:

#### 1. Password reset via UI (recover.php + reset.php)

Antes: `recover.php` era stub com TODO (`'Se o usuário existir, as
instruções foram enviadas'` fake — não chamava nada). `reset.php`
aceitava qualquer senha sem token.

Agora: fluxo completo integrado com endpoints existentes do FastAPI
(`POST /api/v1/auth/password-reset/{request,confirm}`).

**`recover.php`** — form com email, chama `Auth::requestPasswordReset`,
mostra mensagem genérica timing-safe. Bonus rodapé: dica de que admin
pode consultar o link em `src/data/password-recovery.log` se sem MTA.

**`reset.php`** — aceita `?token=XYZ` na URL, valida tamanho da senha
(≥6) + match com confirmação, chama `Auth::resetPassword`. Banner verde
com link "Ir para Login" no sucesso.

**`Auth::requestPasswordReset`** estendido com **dual delivery**:
- Tenta `mail()` do PHP (usa MTA local se houver).
- **Sempre** grava em `src/data/password-recovery.log` (640
  `www-data:www-data`):
  ```
  [2026-05-12 18:32:01] email=user@x.com mail_sent=true remote_ip=1.2.3.4
    link=https://dashboard.local/reset.php?token=abc123def...
  ```
  Admin pode `tail -f` esse arquivo via SSH se mail() falhar
  silenciosamente.

Mensagem retornada à UI é **sempre genérica** — não revela se email
existe (proteção timing-attack).

`login.php` já tinha link "Esqueceu a senha?" apontando pra
`recover.php` — sem mudança.

#### 2. Sessões Ativas (Redis tracking + revogação cirúrgica)

Complemento natural do denylist por user (v2.9.0). Agora cada sessão
individual aparece no perfil do user e pode ser revogada sem afetar
as outras.

**Backend (`api_service`):**

- `services/sessions.py` novo:
  - `track(user_id, token_hash, ip, ua, iat, exp)` — chamado em todo
    `require_auth`. Grava em `udash:session:<user_id>:<hash>` =
    JSON({ip, user_agent, iat, exp, login_at, last_seen}) com
    TTL = exp - now. `login_at` preservado entre updates,
    `last_seen` atualizado a cada hit.
  - `list_for_user(user_id)`, `list_all()`, `remove(user_id, hash)`.
  - `hash_token()` = sha256 truncado em 16 chars.
- `services/jwt_denylist.py` estendido:
  - `revoke_token_hash(hash, ttl)` — adiciona hash à denylist por
    token (chave `udash:revoke:hash:<hash>`).
  - `is_token_hash_revoked(hash)`.
- `core/deps.py::require_auth` agora:
  - Decoda JWT (como antes)
  - Checa user-revocation (como antes)
  - **Checa token-hash revocation** (novo) — 401 com "Sessão encerrada".
  - **Registra a sessão** no Redis (best-effort, não derruba request
    se Redis off).
  - IP via `X-Forwarded-For` (Apache passa); fallback `request.client.host`.
- `routers/auth.py`:
  - `GET /api/v1/auth/sessions` — lista sessões do user atual.
  - `DELETE /api/v1/auth/sessions/{token_hash}` — revoga uma sessão
    (denylist por hash + remove do tracking). User só revoga as suas;
    admin pode revogar de qualquer.

**PHP (`src/Auth.php`):**

- `listMySessions()` — GET `/api/v1/auth/sessions`.
- `revokeMySession($hash)` — DELETE `/api/v1/auth/sessions/{hash}`.

**UI (`config.php` aba "Meu Perfil"):**

- Bloco "Sessões Ativas" abaixo do "Alterar Minha Senha".
- Lista cada sessão com:
  - User-Agent simplificado (Chrome 120 · Windows / Firefox 119 · Linux).
  - IP do request (X-Forwarded-For aware).
  - Timestamp do login (DD/MM HH:MM) + tempo desde última atividade
    (relativo: "agora", "5 min atrás", "2h atrás").
  - Botão "Encerrar" — POST `revoke_session` com confirmação modal.
- Handler `revoke_session` no `config.php` chama `revokeMySession`.
- Empty state quando lista vazia (após login fresh + reload).

**Validação:**

- 58/58 testes pytest passando.
- Smoke test end-to-end: hit `/users` → registra sessão → GET
  `/sessions` retorna 1 entry com IP + UA + hash.

**Compat:**

- Sessões pré-2.10.0 (sem tracking): aparecem na lista após o primeiro
  request autenticado pós-deploy (require_auth registra).
- Tokens sem claim `iat` (legacy): tracking pulado mas auth funciona.

VERSION 2.9.3 → 2.10.0 (minor bump — 2 features de auth).

---

## v2.9.3 — 2026-05-12

### Aba "Controle de Acesso" — busca + filtro por ação + contadores live

A aba ACL tinha só "Nova Regra" + lista flat de N linhas. Em servidores
com muitas regras (faixas grandes de clientes), encontrar uma faixa ou
ver "quantas DENY tenho?" exigia scroll manual.

**Adições:**

- **Toolbar** acima da lista:
  - **Busca por IP / CIDR** (oninput, client-side, case-insensitive).
    Match parcial: "192.168" mostra todas as 192.168.x.x; "10.0.0.0/8"
    encontra a faixa exata.
  - **Dropdown "Filtrar ação"** com contagem em cada opção:
    `TODAS (N) / ALLOW (X) / DENY (Y) / REFUSE (Z)`.
- **Chips clicáveis por ação** abaixo da toolbar:
  - "Todas (N)" / "Allow (X)" verde / "Deny (Y)" vermelho /
    "Refuse (Z)" âmbar.
  - Clique aplica o filtro de ação (setAclFilter).
- **Contador "Visíveis: X / N"** alinhado à direita.
- **Mensagem "Nenhuma regra atende ao filtro"** quando filtros excluem
  tudo.

**Linhas ACL ganharam `data-ip` e `data-action`:**

- `oninput` do IP atualiza `data-ip` em lowercase pra busca funcionar
  enquanto o admin digita.
- `onchange` do select atualiza `data-action` + chama `updateAclCounts()`
  pra refletir nos chips em tempo real (sem reload).
- Botão remover idem: chama `updateAclCounts` + `filterAclRows`.

**`addAclRow()`** atualizado pra criar linha com data-attrs e listeners
inline + chama `updateAclCounts` no final.

Tudo client-side — backend (save_unbound_settings que processa
`acl_ips[]`/`acl_actions[]`) intocado.

VERSION 2.9.2 → 2.9.3.

---

## v2.9.2 — 2026-05-12

### Fix crítico: aba NTP não salvava nada (form-em-form)

A v2.7.3 separou NTP e Timezone em dois forms independentes — MAS os
forms ficaram **aninhados dentro do `mainConfigForm`** (linha 456) que
engloba todas as outras abas. HTML5 proíbe forms aninhados: o browser
ignora o filho e submete o pai com `action=save_unbound_settings`.

Resultado: clicar "Salvar NTP" ou "Salvar Fuso Horário" submetia o
mainConfigForm — `save_ntp_only` e `save_timezone_only` **nunca eram
chamados**. Smoke test backend mostrava handlers OK, mas no browser
nada acontecia.

**Fix:** o bloco inteiro `<div id="tab-ntp">` foi movido pra **DEPOIS**
de `</form>` do mainConfigForm. Agora os 2 forms internos (NTP +
Timezone) são realmente independentes e seus submits chegam aos
handlers corretos.

**Bonus:** `applyTabSwitch` esconde o botão "Sincronizar Todas
Alterações" quando a aba atual tem form próprio (lista atualizada:
`usuarios`, `ntp`, `perfil`). Antes só escondia em `usuarios` — em
`ntp` o botão aparecia mas era irrelevante (e podia confundir o admin
fazendo achar que o save dele estava lá).

Estado verificado: `mainConfigForm` linha 456 → fecha linha 705.
`tab-ntp` começa em 710 (fora). Forms internos em 738 e 783 (filhos
diretos do `tab-ntp`).

VERSION 2.9.1 → 2.9.2.

---

## v2.9.1 — 2026-05-12

### NetworkManager — lock concorrente via flock

Item baixa-prio da auditoria de v2.2.x: dois admins editando rede ao
mesmo tempo podiam corromper arquivos de config silenciosamente (dois
rsyncs no mesmo `/etc/network/interfaces` ou `/etc/netplan/...yaml`).
Sem lock, last-write-wins com possíveis truncamentos.

**Helper novo `_withCategoryLock($category, $work)`:**

- Cria lock file em `src/data/tmp/locks/<category>.lock`.
- Pega `LOCK_EX | LOCK_NB` (não-bloqueante — fail fast).
- Se outro admin já está escrevendo na mesma categoria → retorna
  `{success: false, message: "Outra operação de rede ($category) já
  está em andamento — aguarde alguns segundos e tente novamente."}`.
- Categorias granulares: `interfaces`, `dns`, `ntp`, `hostname`.
  Admins podem editar DNS e hostname ao mesmo tempo (não conflitam).
- Lock file fica com `pid=X ts=T category=Y` pra debug.
- Sanitização de path traversal (regex `[^a-z0-9_-]`).

**Aplicado em:**

- `setHostname` — categoria `hostname` (mexe `/etc/hostname` +
  `/etc/hosts`)
- `setSystemDNS` — categoria `dns` (mexe `/etc/resolv.conf` ou
  `/etc/systemd/resolved.conf`)
- `setNtpServers` — categoria `ntp` (mexe chrony.conf / ntp.conf /
  timesyncd.conf)
- `updateInterfaceConfig` — categoria `interfaces` (mexe
  `/etc/network/interfaces` ou `/etc/netplan/99-*.yaml`).
  Refatorado em `_doUpdateInterfaceConfig` (corpo original) chamado
  pelo wrapper de lock.
- `restoreLastNetplanBackup` — categoria `interfaces` (mesma
  categoria — bloqueia rollback durante outro save).

**Testado em produção:**

- Lock sem contenção → executa OK
- Lock com contenção (outro fp segurando LOCK_EX) → retorna msg de
  contenção clara em ~1ms (não-bloqueante)
- PHP lint OK

VERSION 2.9.0 → 2.9.1.

---

## v2.9.0 — 2026-05-12

### Denylist Redis — revogação imediata de JWT

Sliding session (v2.7.0) cobriu sessões zumbi pós-expiração, mas
mantinha um buraco: quando admin **desativa um user** (ou rebaixa de
admin pra viewer), o JWT antigo continuava válido pelo resto da janela
(até 60min). Cenário: user comprometido permanece logado, ou ex-admin
mantém privilégios por 1h após mudança de role.

Solução: **per-user revocation timestamp em Redis**.

**Modelo:**

- Quando admin desativa/exclui/rebaixa um user, gravamos
  `udash:revoke:user:<id> = <epoch_seconds>` em Redis com TTL =
  `jwt_expire_minutes * 60` (60min default).
- JWT agora carrega claim `iat` (issued at) — gravado em
  `create_access_token`.
- `require_auth` decodifica o JWT, depois checa: se
  `iat < revoked_at`, retorna 401 com mensagem "Token revogado".

**Fail-open**: se Redis estiver indisponível, `is_user_revoked` retorna
`False` (aceita o token). Razão: denylist é defesa adicional — JWT
ainda tem `exp`. Bloquear todos os logins porque Redis caiu é pior
que perder revogação imediata.

**Mudanças:**

- `core/security.py::create_access_token` — adiciona `iat`.
- `core/deps.py::require_auth` — check de denylist após decode_token.
- `infrastructure/redis_client.py` — singleton async com pool,
  inicializado lazy + `close_redis()` no shutdown do lifespan.
- `services/jwt_denylist.py` — API pública: `revoke_user_tokens()`,
  `is_user_revoked()`, `clear_user_revocation()`.
- `services/users_service.py` — `toggle_active`, `delete_user`,
  `update_role` agora revogam tokens do user afetado automaticamente.
- `routers/auth.py::revoke_user` — endpoint `POST /api/v1/auth/revoke/{user_id}`:
  - Admin pode revogar qualquer user.
  - User pode revogar a si mesmo (auto-logout-everywhere).

**Testado em produção:**

- JWT fresh + revoke explícito → próxima chamada 401 com mensagem clara
- JWT emitido APÓS revogação → passa normalmente (iat > revoked_at)
- 58/58 testes pytest passando

**Compatibilidade:**

- Tokens antigos sem claim `iat` (pré-v2.9.0): `is_user_revoked` retorna
  False (sem como comparar). Aceitos até expirarem naturalmente.
  Próximo login emite token novo com `iat`.

VERSION 2.8.3 → 2.9.0 (minor bump — feature de segurança).

---

## v2.8.3 — 2026-05-12

### index.php — barra de status + controle do auto-refresh

Dashboard principal era forte em métricas mas faltava sinal rápido de
"sistema OK?" — admin tinha que ir em /health pra ver status dos
serviços. E o polling de 5s não tinha como pausar (atrapalha quando
você quer analisar um número específico).

**Adições no topo da página:**

- **Barra de status do sistema** (border-left verde/âmbar):
  - "● Sistema saudável" / "⚠ Atenção" como header (verde se todos
    os 4 serviços críticos OK).
  - Bolinhas pulsantes por serviço: `unbound`, `api`, `redis-server`,
    `apache2`. Vermelha + pulse se algum down.
  - Uptime humano do sistema (uptime -p).
  - "Última atualização: HH:MM:SS" + botão **⏸ Pause** do
    auto-refresh.

**Pause/Resume do polling:**

- Botão atualiza label entre "⏸ Pause" e "▶ Resume" + cor âmbar quando
  pausado.
- Quando pausado, `clearInterval` impede que o setInterval recarregue.
- Útil pra inspecionar um valor específico sem ele mudar embaixo do
  cursor.

Timestamp da última atualização agora reflete o último poll bem-sucedido,
não o page load. Quando o auto-refresh roda, atualiza tanto os dados
quanto esse timestamp em sincronia.

VERSION 2.8.2 → 2.8.3.

---

## v2.8.2 — 2026-05-12

### health.php repaginada — versões, uptime, healthz, PHP-FPM, auto-refresh

A página de Saúde tinha 3 cards (Unbound/Load/RAM) + serviços systemd
+ checklist. Versão modernizada com mais sinais e visual consistente:

**Novidades:**

- **PHP-FPM** agora aparece na lista de serviços (auto-detecta versão
  via `systemctl list-unit-files`).
- **Card API /healthz**: chama `http://127.0.0.1:8001/api/v1/healthz`,
  mostra HTTP code + tempo de resposta (ms) + corpo da resposta
  truncado. Border verde (200/ok) ou vermelha (falha).
- **Card Disco /**: era oculto, agora visível em destaque com %
  + livre/total.
- **Bloco "Sistema"**: uptime humano (`uptime -p`) + boot time +
  hostname.
- **Bloco "Versões dos Componentes"** (grid 4 colunas): PHP, Python,
  Apache, Unbound, Redis, DuckDB (via venv). Em destaque: versão
  do próprio Unbound Dashboard lida de `VERSION`.
- **Serviços com uptime**: para cada serviço systemd, mostra também
  a data/hora do último start (`systemctl show --property=ActiveEnterTimestamp`).
- **Auto-refresh toggle** (30s): box no header. Recarrega a página
  inteira pra trazer snapshot novo.
- **Checklist ganhou contador "OK / total"** no header (X/N OK).
- **Flag .installed** adicionada aos componentes auditados.

Visual unificado com as outras páginas (`glass-panel`, badges
coloridos por estado, paleta semáforo). Auto-reparo via
`unbound-health-fix.sh` mantido.

VERSION 2.8.1 → 2.8.2.

---

## v2.8.1 — 2026-05-12

### Fix: install.sh em `curl | bash` sem ADMIN_PASSWORD virava loop infinito

Quando `install-from-git.sh` rodava num servidor onde o admin precisa
ser criado (DuckDB vazio ou flag forçada) **e** o usuário esqueceu de
passar `ADMIN_PASSWORD` no comando, o install.sh entrava num `while`
infinito tentando `read -rsp` num stdin esgotado:

```
[!] Senhas não conferem ou < 6 chars. Tente novamente.
[!] Senhas não conferem ou < 6 chars. Tente novamente.
[!] Senhas não conferem ou < 6 chars. Tente novamente.
...
```

Causa: `curl | sudo bash` consome todo o stdin pra ler o script. Quando
`read` é chamado depois, lê 0 bytes e retorna falha — vars ficam vazias,
comparação `[ "" = "" ] && [ 0 -ge 6 ]` é falsa, loop nunca quebra.

**Fix em `tools/install.sh`:**

- Detecta stdin não-interativo com `[ -t 0 ]`.
- Se **stdin é pipe E ADMIN_PASSWORD vazia** → `err()` com mensagem
  detalhada listando os 3 caminhos pra resolver:
  1. Re-rodar `curl | bash` passando `ADMIN_PASSWORD='...'`.
  2. Baixar o tarball localmente e rodar `sudo bash install.sh`.
  3. Criar admin manualmente via `tools/create_admin.py` após o install.
- Default automático `ADMIN_USERNAME=admin` se não passado em modo
  não-interativo.
- Skip do prompt de email se sem TTY (campo opcional).
- Hard limit de **5 tentativas** no prompt interativo de senha
  (`TRIES > 5 → err`) — defesa adicional caso o `-t 0` retorne true
  em algum corner case mas o stdin ainda fique tipo `/dev/null`.
- Validação final do tamanho da senha após coleta: se vier via env var
  com menos de 6 chars, `err` claro em vez de prosseguir.

VERSION 2.8.0 → 2.8.1.

---

## v2.8.0 — 2026-05-12

### logs.php — fontes novas, busca/filtro/highlighting/auto-refresh

Página de logs tinha 3 fontes (syslog, unbound, live) e era basicamente
um `tail -300` server-side. Sem filtros, sem busca, sem destaque por
nível, sem auto-refresh.

**Fontes novas (3 adicionadas, total 6):**

| Fonte | Comando | Cor |
|---|---|---|
| Syslog O.S. | `tail -n N /var/log/syslog` | slate |
| Unbound Daemon | `tail` / `journalctl -u unbound` | emerald |
| **API FastAPI** | `journalctl -u unbound-dashboard-api` | blue |
| **Apache** | `journalctl -u apache2` | orange |
| **PHP-FPM** | `journalctl -u phpX.Y-fpm` (detecta versão) | pink |
| Live Sniffer | polling `api/live_log.php` | purple |

Todas via grupo `adm` do www-data (sem precisar de sudo extra) —
`install.sh` já adiciona o user ao grupo desde v2.2.7.

**Toolbar nova (logs estáticos):**

- **Busca no buffer**: filtro client-side por texto (case-insensitive).
- **Filtro por nível**: dropdown (TODOS/ERROR/WARN/INFO/DEBUG). Detecção
  regex sobre cada linha — `\berror|err|fatal|critical\b` → error,
  `\bwarning|warn\b` → warn, etc.
- **Seletor de linhas**: 100/300/500/1000/2000 (default 300, max 5000).
  Reload da página com `?lines=N`.
- **Auto-refresh 5s**: checkbox no fim da toolbar — reload da página
  inteira em loop. Útil pra acompanhar erros em tempo real.
- **Contador total/visíveis** + timestamp da última atualização.

**Syntax highlighting** — cada linha ganha cor pelo nível detectado:

- 🔴 Vermelho — `error`, `err`, `fatal`, `critical`
- 🟡 Âmbar — `warning`, `warn`
- 🔵 Slate-400 — `info`, `notice`
- 🩶 Slate-600 — `debug`, `trace`

Fácil escanear visualmente onde estão os problemas.

**Live Sniffer** ganhou:

- **Botão ⏸ Pause / ▶ Resume** — pausa o polling pra inspecionar saída.
- **Botão 🗑 Limpar** — esvazia o buffer (mantendo polling).

**Botão 📋 Copiar** nos logs estáticos: copia para o clipboard apenas
as linhas atualmente visíveis (respeita filtros).

**Hardening:**

- `$logFile` validado contra allowlist `['syslog','unbound','api','apache','phpfpm','live']`.
- `$linesParam` clampado: `max(50, min(5000, ...))`.
- Removido o uso de `sudo` (grupo `adm` é suficiente — menos pressão no
  sudoers entries que estão ficando longas).

VERSION 2.7.5 → 2.8.0 (minor bump — 3 fontes + features novas).

---

## v2.7.5 — 2026-05-12

### install.sh: detecta "flag .installed presente + DuckDB vazio"

Bug reportado no servidor de teste: instalação completou mas
`/api/v1/users/exists` respondia `{"exists":false}` — sem admin, mas
mensagem final dizia "sistema já marcado como instalado, pulando".

**Causa raiz:** A Etapa 8 do `install.sh` checava só se
`data/.installed` existia. Em cenário onde a VM foi recriada
preservando `data/` (ou alguém wipou o `.duckdb` manualmente), a flag
persistia mas o DuckDB ficava vazio. Re-rodar o install via
`install-from-git.sh` via flag → pulava criação → admin nunca era
criado.

A flag não some no rsync da Etapa 5 (`--exclude='data/.installed'`),
o que é correto pra preservar instalações. Mas o DuckDB pode sumir
independentemente (`/var/lib/unbound-dashboard/` é outro path, podia
estar montado em volume separado, ser ramdisk, ser apagado por erro).

**Fix:** A Etapa 8 agora **cruza dois sinais**:

```bash
if [ -f .installed ] && curl /api/v1/users/exists == {"exists":true}
   then PULAR criação
   else CRIAR admin
```

Se a flag existe mas API responde "sem users", o install:

1. Loga `warn "data/.installed presente mas DuckDB sem usuários —
   forçando criação de admin"`
2. Mostra a resposta crua de `/api/v1/users/exists` pra debug
3. Prossegue com o prompt/env var de admin normalmente

Idempotente: se admin já existe e DuckDB tem registros, comportamento
inalterado.

VERSION 2.7.4 → 2.7.5.

---

## v2.7.4 — 2026-05-12

### "Sistema não instalado" — diagnóstico inline + tolerância a API offline

Servidor recém-instalado mostrava "Sistema ainda não foi instalado" mesmo
após o install.sh completar com sucesso. O diagnóstico estava enterrado
no CLI; a página só dizia "ainda não instalado" sem indicar **em qual
etapa parou**.

**Causa raiz mais provável:** `Auth::hasUsers()` faz GET a
`/api/v1/users/exists` e retorna `false` se a chamada falhar (curl
timeout, api_service ainda subindo após restart, etc). `index.php`
redireciona pra `not_installed.php` sem dar pista de que o problema
é de conectividade, não de instalação.

**Fix em `src/Auth.php`:**

- Novo método **`hasUsersOrApiDown()`** — tolerante a transientes:
  - API responde `{exists: true}` → `true` ✓
  - API responde `{exists: false}` → `false` (sistema cru)
  - **API não responde** mas `data/.installed` existe → `true`
    (assume instalado; install.sh marcou a flag)
  - API não responde E flag ausente → `false`
- `hasUsers()` original mantém comportamento estrito (usado em outros
  pontos que precisam de resposta autoritativa da API).

**Fix em `index.php`:** usa `hasUsersOrApiDown()`. Hiccup do
api_service não derruba o admin pra wizard.

**`not_installed.php` ganhou diagnóstico inline em tempo real:**

Quando a página renderiza, checa 3 sinais e mostra um card colorido:

- `data/.installed` existe? (✓/✗)
- `unbound-dashboard-api.service` em 127.0.0.1:8001 responde? (✓/✗)
- Tabela `users` no DuckDB tem registros? (✓/✗/?)

Com base nas combinações, exibe um diagnóstico narrativo e a seção
"Como resolver" **adaptada ao cenário**:

- API offline → comandos `systemctl status / journalctl / restart`.
- API OK + users vazio → comando exato do `create_admin.py` manual
  com env vars prontos pra copy-paste.
- Outros casos → fallback genérico (rodar install.sh).

Bloco de comandos de diagnóstico sempre visível no fim com 4 comandos
prontos pra copy.

VERSION 2.7.3 → 2.7.4.

---

## v2.7.3 — 2026-05-12

### Aba NTP — fix do save de timezone + UX melhorada

A aba "Tempo & NTP" tinha 3 problemas reportados:

1. **Não salvava o timezone** — handler `save_ntp` submetia NTP + Timezone
   no mesmo POST e usava AND lógico pra status; qualquer falha em um
   bloqueava o status do outro. Pior: se o `<select>` gigante (400+
   opções) tinha `<option value="" selected>Selecione...</option>` no
   topo e o user não escolhia, o POST mandava vazio e o `setSystemTimezone('')`
   falhava silenciosamente como "Timezone vazio".

2. **Select inutilizável** — 400+ opções num dropdown vertical. Encontrar
   "America/Sao_Paulo" sem busca é tortura.

3. **Aba também tinha o bug do `active` class missing** — só ativava
   após DOMContentLoaded, FOUC visível.

**Mudanças em `config.php` (aba NTP):**

- **Aba `tab-ntp` ganhou `<?= $activeTab === 'ntp' ? 'active' : '' ?>`**.
- **Dois cards independentes**: "Servidores NTP" e "Fuso Horário", cada
  um com **form próprio + botão de save próprio**. Falha em um não
  bloqueia o outro.
- **NTP** mantém o pool com `addNtpRow()`. Submit `action=save_ntp_only`.
- **Timezone vira combobox** (`<input list="tz-options">` + `<datalist>`):
  - Input mostra o valor atual pré-preenchido.
  - User digita "America/" pra filtrar Américas, "Europe/" pra Europa, etc.
    Browser nativo faz fuzzy match — sem JS adicional.
  - `pattern="[A-Za-z_./+\-]+"` + `required` impede submit vazio.
  - Submit `action=save_timezone_only`.
- **Painel "Atual"** mostra o timezone detectado pelo sistema (lado
  esquerdo) + hora local agora. UX: usuário vê de imediato o que tá
  configurado.
- **Aviso se lista vazia**: se `getAvailableTimezones()` retorna `[]`,
  banner amarelo sugerindo `apt install tzdata`.

**`NetworkManager::getAvailableTimezones()`** ganhou fallback robusto:

1. Tenta `timezone_identifiers_list()` (PHP nativo, rápido).
2. Se vazio (raríssimo, mas pode acontecer em containers mínimos sem
   tzdata), faz fallback varrendo `/usr/share/zoneinfo/` em disco —
   é o mesmo data source que `timedatectl` usa.
3. Filtra arquivos não-zona (`zone.tab`, `posix/`, `right/`, `.tab`,
   `.zi`, `.list`).
4. Garante mínimo `['UTC']` se nada existir.

**Handlers POST novos em `config.php`:**

- `save_ntp_only` — só toca NTP.
- `save_timezone_only` — só timezone, com validação explícita de
  string vazia.
- `save_ntp` (antigo) mantido pra compat com forms externos.

VERSION 2.7.2 → 2.7.3.

---

## v2.7.2 — 2026-05-12

### Fix: Debian 13/PHP 8.4 — gera phpX.Y-fpm.conf quando ausente

v2.7.1 detectou o problema via smoke test, mas o fix anterior assumia
que `a2enconf phpX.Y-fpm` ia funcionar. **No Debian 13 com PHP 8.4 o
pacote `php-fpm` NÃO instala `/etc/apache2/conf-available/phpX.Y-fpm.conf`**
— só vem o `phpX.Y-cgi.conf` (do pacote `libapache2-mod-php`, que é
mod_php, não FPM). Então `a2enconf phpX.Y-fpm` falha com "file does
not exist".

Comportamento confirmado em produção (Debian 13.4):
- `php-fpm` instalado → serviço ativo ✓
- `/etc/apache2/conf-available/php8.4-fpm.conf` → **ausente** ✗
- `a2enconf php8.4-fpm` → falha silenciosa
- Smoke test v2.7.1 → detectou e avisou (funcionou como pretendido)

**Fix em `install.sh` e `update.sh`:**

Quando o arquivo `/etc/apache2/conf-available/phpX.Y-fpm.conf` não
existe, **geramos manualmente** com o template padrão Debian/Ubuntu:

```apache
<FilesMatch ".+\.ph(ar|p|tml)$">
    SetHandler "proxy:unix:/run/php/phpX.Y-fpm.sock|fcgi://localhost"
</FilesMatch>
<FilesMatch ".+\.phps$">
    SetHandler application/x-httpd-php-source
    Require all denied
</FilesMatch>
<FilesMatch "^\.ph(ar|p|ps|tml)$">
    Require all denied
</FilesMatch>
DirectoryIndex index.php
```

- Versão e socket path derivados de `$PHP_FPM_CONF`
  (`php8.4-fpm` → versão `8.4`, socket `/run/php/php8.4-fpm.sock`).
- Idempotente: só cria se ausente, não sobrescreve um manual.
- Depois disso o `a2enconf` funciona normalmente.

Em Debian 12 / Ubuntu 22+ onde o conf já vem do pacote, este bloco
é no-op (early return no `if [ ! -f ... ]`).

VERSION 2.7.1 → 2.7.2.

---

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
