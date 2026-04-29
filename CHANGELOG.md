# Changelog — Unbound Dashboard v2

## [2.1.1] — Scripts de build e instalação

### Novas funcionalidades
- **`tools/install.sh`**: Script de instalação automatizada para Debian 12+/Ubuntu 22.04+. Instala dependências (Python 3.12+, uv, Node 20, Redis, Caddy, Unbound), cria usuário sistema `unbound-dash`, cria diretórios, instala venv, roda migrations, configura systemd e Caddy, cria usuário admin interativo.
- **`tools/build-update.sh`**: Gera pacote `.tar.gz` de atualização com apenas arquivos atualizáveis (`app/`, `frontend/dist/`, `migrations/`, `deployments/`, `VERSION`, `CHANGELOG.md`). Suporte a `BUMP_TYPE=patch|minor|major`, `SKIP_FRONTEND`, `AUTO_BUMP_VERSION`.
- **`tools/update.sh`**: Aplica update em instalação existente. Backup automático antes do update (mantém 5 mais recentes em `/var/backups/unbound-dashboard/`). Rsync seletivo (não sobrescreve `.venv`, configs locais). Atualiza dependências Python via `uv`. Roda migrations novas. Suporte a `DRY_RUN=true`, `VERBOSE=true`, `AUTO_RESTART`, `FORCE_CADDY`.

---

## [2.1.0] — Frontend atualizado

### Correções (Bug Fixes)
- **`api/client.ts`**: Adicionado `export { api as apiClient }` — corrige 4 views (`BlocklistView`, `ConfigView`, `DiagnosticsView`, `HealthView`) que importavam o nome antigo
- **`api/alerts.ts`**: Removido campo `type` inexistente; timestamp corrigido para Unix (`number`) em vez de string ISO
- **`views/LogsView.vue`**: Eliminados operadores `??` com campos de tipos incompatíveis; useLiveLog agora normaliza entrada WebSocket para `QueryLogEntry`
- **`views/HistoryView.vue`**: Removido import `watch` não utilizado
- **`views/SettingsView.vue`**: Corrigida variável `value` no `v-for` não utilizada

### Novas funcionalidades
- **`api/balance.ts`**: Módulo de API dedicado para upstreams (`listUpstreams`, `addUpstream`, `removeUpstream`, `setEnabled`, `checkHealth`, `getStats`)
- **`api/health.ts`**: Módulo de API dedicado para saúde do sistema
- **`api/unbound.ts`**: Módulo de API dedicado para controle do Unbound
- **`api/diagnostics.ts`**: Módulo de API dedicado para diagnósticos
- **`views/BalanceView.vue`**: Nova view de gerenciamento de upstreams — tabela com toggle ativo/inativo, health badge, latência, formulário de adição, cards de estatísticas agregadas
- **`router/index.ts`**: Adicionada rota `/balance` (adminOnly)
- **`views/AppLayout.vue`**: Adicionado item "⚖️ Balanceamento" na sidebar; badge de alertas não-lidos com polling a cada 60s; exibe role do usuário
- **`components/ToastContainer.vue`**: Sistema global de notificações toast (info/success/warning/error) com auto-dismiss
- **`composables/useToast.ts`**: Composable reativo para emitir toasts
- **`stores/ui.ts`**: Store Pinia para estado da UI (sidebar collapsed)
- **`App.vue`**: Integrado listener `api:error` para disparar toasts em 429 e 5xx globalmente

### Melhorias
- **`views/DashboardView.vue`**: Auto-refresh a cada 30s via `useIntervalFn`; barra de progresso da taxa de bloqueio; estado de erro visível; timestamp da última atualização
- **`views/AlertsView.vue`**: Badge de severidade colorido; botão de exclusão individual; timestamp Unix corrigido
- **`api/client.ts`**: Timeout de 30s; header `X-Request-ID` em cada request; tratamento de 429 e 5xx com evento global
- **`composables/useLiveLog.ts`**: Normaliza entradas WebSocket para tipo `QueryLogEntry`
- **`tsconfig.app.json`**: Adicionado `baseUrl` e `paths` para alias `@/`
- **`tsconfig.node.json`**: Adicionado `types: ["node"]` para suporte a `node:url`
- Instalado `@types/node` como devDependency
