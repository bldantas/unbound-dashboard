# Changelog — Unbound Dashboard API v2 (Python/FastAPI)

Histórico de mudanças do backend Python (`app/`), serviços, migrations, routers e workers.

---

## [2.1.14] — Paridade funcional v1 (fase 2)

### Novas funcionalidades
- **`frontend/src/views/ThreatsView.vue`**: nova tela de Ameaças com métricas de bloqueio, top domínios/clientes e lista de eventos bloqueados.
- **`frontend/src/views/DnsBenchmarkView.vue`**: nova tela de Benchmark DNS com testes em múltiplos resolvers e ranking por latência.
- **`frontend/src/views/ExportsView.vue`**: nova tela de Exportações (CSV de histórico, CSV de logs e snapshot JSON consolidado).
- **`frontend/src/router/index.ts`**: adicionadas rotas `/threats`, `/dns-benchmark` e `/exports` (admin).
- **`frontend/src/views/AppLayout.vue`**: menu atualizado para incluir os novos módulos no layout estilo v1.

---

## [2.1.13] — Layout v1 aplicado na v2

### Melhorias
- **`frontend/src/views/AppLayout.vue`**: sidebar e topbar redesenhados no estilo da v1 (seções Monitoramento/Ferramentas/Sistema, badge de alertas, perfil e botão de logout no rodapé).
- **`frontend/src/views/AppLayout.vue`**: títulos de página no topo por rota, mantendo experiência visual próxima da v1 com navegação da v2.
- **`frontend/src/style.css`** + layout novo: consolidam aparência mais consistente com o painel clássico da v1.

---

## [2.1.12] — Hardening de roteamento no Caddy

### Correções
- **`deployments/caddy/Caddyfile`**: reestruturado para usar bloco `route` com `handle /api/*`, `handle /ws/*`, `handle /healthz` e fallback SPA em `handle` final.

---

## [2.1.11] — Fix roteamento API no Caddy

### Correções
- **`deployments/caddy/Caddyfile`**: matchers ajustados de `path /api/*` para `path /api*` e de `path /ws/*` para `path /ws*`.

---

## [2.1.10] — Fix visual frontend e validação de login

### Correções
- **`frontend/src/style.css`**: removidos estilos padrão do template Vite que quebravam o layout da SPA.
- **`tools/install.sh`**: Etapa 10 passou a validar login do admin após criar/atualizar credenciais.

---

## [2.1.9] — Etapa 10 idempotente para admin

### Correções
- **`tools/install.sh`**: criação do admin ficou idempotente; se o username já existir, o instalador atualiza senha, garante role `admin`, reativa conta e limpa lock/falhas.

---

## [2.1.8] — Fix lock DuckDB na Etapa 10

### Correções
- **`tools/install.sh`**: Etapa 10 agora para temporariamente o `unbound-api` antes de criar o usuário administrador direto no banco DuckDB.

---

## [2.1.7] — Fix 404 por pacote sem frontend

### Correções
- **`tools/build-package.sh`**: corrigida regra de exclusão do rsync para não remover `app/frontend/dist` do pacote final.
- **`tools/install.sh`**: adicionada validação de `frontend/dist/index.html` após cópia dos arquivos.

---

## [2.1.6] — Fix lock DuckDB em reinstalação

### Correções
- **`tools/install.sh`**: Etapa 7 agora detecta se `unbound-api.service` já está ativo e para temporariamente antes de executar `app.db.migrate`.

---

## [2.1.5] — Setup guiado do Caddy no instalador

### Correções
- **`tools/install.sh`**: Etapa 9 solicita interativamente o domínio durante a instalação e aplica no `Caddyfile` com validação via `caddy validate`.

---

## [2.1.4] — Fix criação de admin no instalador

### Correções
- **`tools/install.sh`**: corrigida Etapa 10 para instanciar `AuthService` com `UserRepository`, compatível com a assinatura atual do serviço.

---

## [2.1.3] — Hardening do instalador

### Correções
- **`tools/install.sh`**: etapa de serviço systemd ficou resiliente a timeout transitório no `systemctl start unbound-api`.

---

## [2.1.2] — Hotfix de serviço systemd

### Correções
- **`deployments/systemd/unbound-api.service`**: alterado `Type=notify` para `Type=simple`.
- **`deployments/systemd/unbound-api.service`**: reduzido `--workers` de `2` para `1` para evitar lock concorrente no DuckDB.
- **`tools/install.sh`**: usuário `unbound-dash` adicionado ao grupo `adm` para leitura de logs.

---

## [2.1.1] — Scripts de build e instalação

### Novas funcionalidades
- **`tools/install.sh`**: Script de instalação automatizada para Debian 12+/Ubuntu 22.04+.
- **`tools/build-update.sh`**: Gera pacote `.tar.gz` de atualização com suporte a `BUMP_TYPE=patch|minor|major`.
- **`tools/update.sh`**: Aplica update com backup automático, rsync seletivo, migrations e suporte a `DRY_RUN`.

---

## [2.1.0] — Frontend Vue 3 atualizado

### Novas funcionalidades
- **`views/BalanceView.vue`**: Nova view de gerenciamento de upstreams.
- **`api/balance.ts`**, **`api/health.ts`**, **`api/unbound.ts`**, **`api/diagnostics.ts`**: Módulos de API dedicados.
- **`components/ToastContainer.vue`** + **`composables/useToast.ts`**: Sistema global de notificações toast.

### Correções
- **`api/client.ts`**: Timeout de 30s; header `X-Request-ID`; tratamento de 429/5xx.
- Múltiplos fixes de tipagem e imports nas views Vue.
