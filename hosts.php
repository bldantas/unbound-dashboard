<?php
require_once 'src/Auth.php';

use App\Auth;

Auth::check();
// Capability config.write — admin only (mesma de updates/webhooks/SMTP)
if (!\App\Auth::can('config.write')) {
    header('Location: index.php');
    exit;
}

$currentPage = 'hosts.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Hosts gerenciados - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Hosts Gerenciados";
        include 'includes/topbar.php';
        ?>

        <div class="page-container">
            <header class="page-header mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z"/></svg>
                        Hosts Gerenciados
                    </h1>
                    <p class="page-subtitle">Master multi-host — inventário de agents pollados via API token.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="hosts-refresh-btn" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2" title="Recarregar lista">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        Recarregar
                    </button>
                    <button type="button" id="hosts-add-btn" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Adicionar host
                    </button>
                </div>
            </header>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Total</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-total">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Online</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-ok">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Auth/Erro</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-warn">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest">Inalcançáveis</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-down">…</p>
                </div>
            </div>

            <!-- Batch ops toolbar -->
            <div class="glass-panel !p-3 mb-5 flex flex-wrap items-center gap-2" id="hosts-batch-toolbar">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mr-2">Ações em lote:</span>
                <button type="button" data-batch="poll" class="batch-btn glass-btn text-[10px] uppercase font-black flex items-center gap-1" title="Re-pollar todos os hosts agora">
                    ↻ Re-poll todos
                </button>
                <button type="button" data-batch="upgrade" class="batch-btn glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-1" title="Disparar self-update em todos">
                    ↑ Atualizar todos
                </button>
                <button type="button" data-batch="restart-api" class="batch-btn glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black flex items-center gap-1" title="Reiniciar o api_service de todos os agents">
                    ⟲ Reiniciar API
                </button>
                <button type="button" data-batch="restart-unbound" class="batch-btn glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black flex items-center gap-1" title="Reiniciar o daemon Unbound de todos os agents">
                    ⟲ Reiniciar Unbound
                </button>
                <span class="text-[10px] text-slate-500 ml-auto" id="batch-status"></span>
            </div>

            <!-- Lista de hosts -->
            <div id="hosts-list" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="glass-panel text-center py-10 col-span-full">
                    <p class="text-sm text-slate-500 italic">Carregando…</p>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <?php include 'includes/custom_modals.php'; ?>

    <!-- Modal: drill-down de 1 host (info + status) -->
    <div id="host-detail-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="glass-panel max-w-3xl w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="min-w-0">
                    <h3 id="host-detail-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Detalhes do host</h3>
                    <p class="text-[11px] text-slate-500 mt-1" id="host-detail-subtitle">…</p>
                </div>
                <button type="button" id="host-detail-close" class="glass-btn text-[10px] uppercase font-black">Fechar</button>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 border-b border-slate-200 dark:border-white/10 mb-4">
                <button type="button" data-tab="info" class="host-tab-btn px-3 py-2 text-[10px] uppercase font-black tracking-widest border-b-2 border-cyan-500 text-cyan-600 dark:text-cyan-400">Info do agent</button>
                <button type="button" data-tab="status" class="host-tab-btn px-3 py-2 text-[10px] uppercase font-black tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Status atual</button>
                <button type="button" data-tab="history" class="host-tab-btn px-3 py-2 text-[10px] uppercase font-black tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Histórico</button>
            </div>

            <!-- Tab content: info -->
            <div id="host-tab-info" class="host-tab-pane">
                <p class="text-[11px] text-slate-500 italic" id="host-tab-info-loader">Carregando informações estáticas do agent…</p>
                <dl id="host-tab-info-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]"></dl>
            </div>

            <!-- Tab content: status -->
            <div id="host-tab-status" class="host-tab-pane hidden">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[11px] text-slate-500" id="host-tab-status-meta">Último poll: —</p>
                    <button type="button" id="host-tab-status-refresh" class="glass-btn text-[10px] uppercase font-black">↻ Forçar poll</button>
                </div>
                <dl id="host-tab-status-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]"></dl>
                <p id="host-tab-status-err" class="hidden mt-3 text-[11px] text-red-500"></p>
            </div>

            <!-- Tab content: history -->
            <div id="host-tab-history" class="host-tab-pane hidden">
                <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                    <p class="text-[11px] text-slate-500" id="host-tab-history-meta">Carregando histórico…</p>
                    <button type="button" id="host-tab-history-refresh" class="glass-btn text-[10px] uppercase font-black">↻ Recarregar</button>
                </div>

                <!-- Sparkline de status: cada poll vira uma barrinha colorida -->
                <div class="mb-4">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Linha do tempo (mais recente → mais antigo)</p>
                    <div id="host-tab-history-sparkline" class="flex flex-row-reverse gap-[2px] items-end h-10 bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg overflow-hidden">
                        <span class="text-[10px] text-slate-500 italic">…</span>
                    </div>
                    <div class="flex items-center gap-3 text-[9px] mt-1 text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-500"></span>ok</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-500"></span>auth</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-red-500"></span>unreachable</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-orange-500"></span>error</span>
                    </div>
                </div>

                <!-- Tabela detalhada -->
                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-white/10">
                    <table class="w-full text-[11px]">
                        <thead class="bg-slate-900/5 dark:bg-white/5 text-[10px] uppercase tracking-widest text-slate-500">
                            <tr>
                                <th class="text-left px-3 py-2 font-black">Quando</th>
                                <th class="text-left px-3 py-2 font-black">Status</th>
                                <th class="text-left px-3 py-2 font-black">Versão</th>
                                <th class="text-right px-3 py-2 font-black">Queries 24h</th>
                                <th class="text-left px-3 py-2 font-black">Erro</th>
                            </tr>
                        </thead>
                        <tbody id="host-tab-history-rows" class="divide-y divide-slate-200 dark:divide-white/10"></tbody>
                    </table>
                </div>
            </div>

            <!-- Ações individuais -->
            <div class="mt-6 pt-4 border-t border-slate-200 dark:border-white/10 flex flex-wrap gap-2">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mr-2 self-center">Ações:</span>
                <button type="button" id="host-detail-restart-api" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">⟲ Reiniciar API</button>
                <button type="button" id="host-detail-restart-unbound" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">⟲ Reiniciar Unbound</button>
                <button type="button" id="host-detail-upgrade" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">↑ Atualizar este</button>
                <a id="host-detail-open-ui" href="#" target="_blank" rel="noopener" class="glass-btn text-[10px] uppercase font-black ml-auto">↗ Abrir UI</a>
            </div>
        </div>
    </div>

    <!-- Modal: resultado de batch op -->
    <div id="batch-result-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="glass-panel max-w-2xl w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl max-h-[80vh] overflow-y-auto">
            <h3 id="batch-result-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3">Resultado</h3>
            <div id="batch-result-list" class="space-y-2 text-[11px]"></div>
            <div class="flex justify-end mt-4">
                <button type="button" id="batch-result-close" class="glass-btn text-[10px] uppercase font-black">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal: adicionar/editar host -->
    <div id="host-form-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="glass-panel max-w-lg w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
            <h3 id="host-form-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Adicionar host</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Label *</label>
                    <input type="text" id="host-form-label" autocomplete="off" maxlength="100" class="glass-input w-full" placeholder="ex: Recursor-SP1">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Base URL *</label>
                    <input type="url" id="host-form-url" autocomplete="off" maxlength="255" class="glass-input w-full font-mono text-xs" placeholder="https://dns1.exemplo.com">
                    <p class="text-[10px] text-slate-500 mt-1">URL completa do agent (sem `/api/...` no fim). Imutável após criar.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">API token *</label>
                    <input type="text" id="host-form-token" autocomplete="off" maxlength="255" class="glass-input w-full font-mono text-xs" placeholder="cole o token gerado no agent">
                    <p class="text-[10px] text-slate-500 mt-1" id="host-form-token-hint">Gere em Configurações → API Tokens no agent. Mostrado UMA vez lá.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Notas (opcional)</label>
                    <textarea id="host-form-notes" maxlength="500" rows="2" class="glass-input w-full text-xs" placeholder="ex: recursor primary, edge SP1"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" id="host-form-cancel" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                <button type="button" id="host-form-submit" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black" disabled>Salvar</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const jwtMeta = document.querySelector('meta[name="api-jwt"]');
            const JWT = jwtMeta ? jwtMeta.content : '';
            const HEADERS = JWT ? { 'Authorization': 'Bearer ' + JWT, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' };

            const el = {
                list:        document.getElementById('hosts-list'),
                refreshBtn:  document.getElementById('hosts-refresh-btn'),
                addBtn:      document.getElementById('hosts-add-btn'),
                statTotal:   document.getElementById('hosts-stat-total'),
                statOk:      document.getElementById('hosts-stat-ok'),
                statWarn:    document.getElementById('hosts-stat-warn'),
                statDown:    document.getElementById('hosts-stat-down'),
                modal:       document.getElementById('host-form-modal'),
                modalTitle:  document.getElementById('host-form-title'),
                fLabel:      document.getElementById('host-form-label'),
                fUrl:        document.getElementById('host-form-url'),
                fToken:      document.getElementById('host-form-token'),
                fTokenHint:  document.getElementById('host-form-token-hint'),
                fNotes:      document.getElementById('host-form-notes'),
                fCancel:     document.getElementById('host-form-cancel'),
                fSubmit:     document.getElementById('host-form-submit'),
            };

            let editingId = null;  // null=create, int=edit

            // customConfirm / customAlert vêm de includes/custom_modals.php (window.*)

            const STATUS_BADGE = {
                ok:          { label: '● Online',       color: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30' },
                auth_failed: { label: '🔒 Auth falhou',  color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                unreachable: { label: '⚠ Inalcançável',  color: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
                error:       { label: '⚠ Erro',          color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                null:        { label: '— Não pollado',   color: 'bg-slate-500/15 text-slate-500 border-slate-500/30' },
            };

            function fmtDate(iso) {
                if (!iso) return '—';
                try { return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); }
                catch (_) { return iso; }
            }
            function fmtRelative(iso) {
                if (!iso) return 'nunca';
                try {
                    const diff = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
                    if (diff < 60) return 'agora';
                    if (diff < 3600) return `há ${Math.floor(diff/60)} min`;
                    if (diff < 86400) return `há ${Math.floor(diff/3600)}h`;
                    return `há ${Math.floor(diff/86400)}d`;
                } catch (_) { return iso; }
            }
            function fmtUptime(seconds) {
                if (seconds === null || seconds === undefined) return '—';
                const s = Math.max(0, Math.floor(Number(seconds) || 0));
                const d = Math.floor(s / 86400);
                const h = Math.floor((s % 86400) / 3600);
                const m = Math.floor((s % 3600) / 60);
                if (d > 0) return `${d}d ${h}h`;
                if (h > 0) return `${h}h ${m}m`;
                return `${m}m`;
            }
            function fmtNum(n) {
                if (n === null || n === undefined) return '—';
                try { return Number(n).toLocaleString('pt-BR'); }
                catch (_) { return String(n); }
            }

            async function load() {
                el.refreshBtn.disabled = true;
                try {
                    const resp = await fetch('/api/v1/hosts', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    render(data.hosts || []);
                } catch (err) {
                    el.list.innerHTML = `<div class="glass-panel col-span-full text-center py-6"><p class="text-sm text-red-500">Erro: ${err.message}</p></div>`;
                } finally {
                    el.refreshBtn.disabled = false;
                }
            }

            function render(hosts) {
                el.statTotal.textContent = hosts.length;
                el.statOk.textContent    = hosts.filter(h => h.last_status === 'ok').length;
                el.statWarn.textContent  = hosts.filter(h => h.last_status === 'auth_failed' || h.last_status === 'error').length;
                el.statDown.textContent  = hosts.filter(h => h.last_status === 'unreachable').length;

                if (!hosts.length) {
                    el.list.innerHTML = `
                        <div class="glass-panel col-span-full text-center py-10">
                            <p class="text-sm text-slate-500 italic">Nenhum host gerenciado ainda.</p>
                            <p class="text-[11px] text-slate-500 mt-2">Clique em "Adicionar host" pra começar.</p>
                        </div>
                    `;
                    return;
                }
                el.list.innerHTML = hosts.map(h => {
                    const badge = STATUS_BADGE[h.last_status] || STATUS_BADGE.null;
                    const p = h.last_status_payload || {};
                    const ratio = (p.hit_ratio_24h !== null && p.hit_ratio_24h !== undefined) ? Number(p.hit_ratio_24h).toFixed(1) + '%' : '—';
                    const queries = fmtNum(p.queries_24h);
                    const version = p.version || '?';
                    const alerts = (p.alerts_active !== null && p.alerts_active !== undefined) ? p.alerts_active : '?';
                    const uptime = fmtUptime(p.uptime_seconds);
                    const users = fmtNum(p.users_total);
                    const sessions = fmtNum(p.sessions_active);
                    const duckdbOk = p.duckdb_ok === true;
                    const duckdbLabel = (p.duckdb_ok === true) ? 'OK' : (p.duckdb_ok === false ? 'FAIL' : '—');
                    const authKind = p.auth_kind || '—';
                    return `
                        <div class="glass-panel">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-black text-slate-900 dark:text-white truncate">${escapeHtml(h.label)}</h3>
                                    <a href="${escapeAttr(h.base_url)}" target="_blank" rel="noopener" class="text-[10px] font-mono text-cyan-600 dark:text-cyan-400 hover:underline truncate block">${escapeHtml(h.base_url)}</a>
                                </div>
                                <span class="shrink-0 inline-block px-2 py-1 rounded-md border text-[10px] font-black uppercase tracking-widest ${badge.color}">${badge.label}</span>
                            </div>

                            ${h.last_status === 'ok' ? `
                                <div class="grid grid-cols-4 gap-2 mb-2 text-[10px]">
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Versão</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white truncate">v${escapeHtml(version)}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Uptime</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${uptime}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Hit ratio</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${ratio}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Queries 24h</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${queries}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-2 mb-3 text-[10px]">
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Alertas</p>
                                        <p class="font-mono font-bold ${alerts > 0 ? 'text-red-500' : 'text-slate-900 dark:text-white'}">${alerts}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Users</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${users}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Sessões</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${sessions}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg" title="Auth: ${escapeAttr(authKind)}">
                                        <p class="text-slate-500 uppercase font-bold">DuckDB</p>
                                        <p class="font-mono font-bold ${duckdbOk ? 'text-emerald-500' : 'text-red-500'}">${duckdbLabel}</p>
                                    </div>
                                </div>
                            ` : `
                                <div class="bg-red-500/5 border border-red-500/20 text-red-700 dark:text-red-300 text-[11px] p-3 rounded-lg mb-3">
                                    ${h.last_error ? escapeHtml(h.last_error) : 'Sem detalhes.'}
                                </div>
                            `}

                            ${h.notes ? `<p class="text-[10px] text-slate-500 mb-3">${escapeHtml(h.notes)}</p>` : ''}

                            <div class="flex items-center justify-between text-[10px] text-slate-500 mb-3">
                                <span>Pollado ${fmtRelative(h.last_polled_at)}</span>
                                <span>Adicionado ${fmtDate(h.added_at)}</span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-action="details" data-id="${h.id}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black !bg-cyan-600 !text-white">▤ Detalhes</button>
                                <button type="button" data-action="poll" data-id="${h.id}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black">↻ Poll</button>
                                <button type="button" data-action="edit" data-id="${h.id}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black">Editar</button>
                                <a href="${escapeAttr(h.base_url)}" target="_blank" rel="noopener" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black">↗ UI</a>
                                <button type="button" data-action="delete" data-id="${h.id}" data-label="${escapeAttr(h.label)}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black bg-red-500/15 text-red-600 dark:text-red-400 ml-auto">Remover</button>
                            </div>
                        </div>
                    `;
                }).join('');
                el.list.querySelectorAll('.host-action').forEach(btn => {
                    btn.addEventListener('click', () => handleAction(btn));
                });
            }

            function escapeHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function escapeAttr(s) {
                return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            async function handleAction(btn) {
                const action = btn.getAttribute('data-action');
                const id = btn.getAttribute('data-id');
                if (action === 'details') {
                    openDetailModal(id);
                } else if (action === 'poll') {
                    btn.disabled = true;
                    btn.textContent = '⟳…';
                    try {
                        const resp = await fetch(`/api/v1/hosts/${id}/poll`, { method: 'POST', headers: HEADERS });
                        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                        await load();
                    } catch (err) {
                        await customAlert('Erro ao polar', err.message, 'error');
                        btn.disabled = false;
                        btn.textContent = '↻ Poll';
                    }
                } else if (action === 'edit') {
                    openEditModal(id);
                } else if (action === 'delete') {
                    const label = btn.getAttribute('data-label');
                    const ok = await customConfirm(
                        'Remover host',
                        `Remover "${label}" do inventário? Master para de polar — o agent em si não é afetado.`,
                        { variant: 'danger', okLabel: 'Remover' }
                    );
                    if (!ok) return;
                    try {
                        const resp = await fetch(`/api/v1/hosts/${id}`, { method: 'DELETE', headers: HEADERS });
                        if (!resp.ok && resp.status !== 204) throw new Error(`HTTP ${resp.status}`);
                        await load();
                    } catch (err) {
                        await customAlert('Erro ao remover', err.message, 'error');
                    }
                }
            }

            function openCreateModal() {
                editingId = null;
                el.modalTitle.textContent = 'Adicionar host';
                el.fLabel.value = '';
                el.fUrl.value = '';
                el.fUrl.disabled = false;
                el.fToken.value = '';
                el.fTokenHint.textContent = 'Gere em Configurações → API Tokens no agent. Mostrado UMA vez lá.';
                el.fNotes.value = '';
                el.fSubmit.textContent = 'Salvar';
                el.fSubmit.disabled = true;
                el.modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                el.fLabel.focus();
                validateForm();
            }

            async function openEditModal(id) {
                // Pega dados atuais do host
                try {
                    const resp = await fetch('/api/v1/hosts', { headers: HEADERS });
                    const data = await resp.json();
                    const h = (data.hosts || []).find(x => String(x.id) === String(id));
                    if (!h) throw new Error('Host não encontrado');

                    editingId = parseInt(id);
                    el.modalTitle.textContent = `Editar host #${id}`;
                    el.fLabel.value = h.label;
                    el.fUrl.value = h.base_url;
                    el.fUrl.disabled = true;  // base_url imutável
                    el.fToken.value = '';
                    el.fTokenHint.textContent = 'Deixe vazio pra preservar o atual. Cole um novo só se for trocar.';
                    el.fNotes.value = h.notes || '';
                    el.fSubmit.textContent = 'Atualizar';
                    el.modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    validateForm();
                } catch (err) {
                    await customAlert('Erro ao carregar dados', err.message, 'error');
                }
            }

            function closeModal() {
                el.modal.classList.add('hidden');
                document.body.style.overflow = '';
                editingId = null;
            }

            function validateForm() {
                const labelOk = el.fLabel.value.trim().length > 0;
                const urlOk = editingId !== null || (el.fUrl.value.trim().length > 7 && /^https?:\/\//.test(el.fUrl.value.trim()));
                const tokenOk = editingId !== null || el.fToken.value.trim().length >= 20;
                el.fSubmit.disabled = !(labelOk && urlOk && tokenOk);
            }

            [el.fLabel, el.fUrl, el.fToken].forEach(input => input.addEventListener('input', validateForm));

            el.fCancel.addEventListener('click', closeModal);
            el.fSubmit.addEventListener('click', async () => {
                el.fSubmit.disabled = true;
                el.fCancel.disabled = true;
                try {
                    let resp;
                    if (editingId === null) {
                        // CREATE
                        const body = {
                            label: el.fLabel.value.trim(),
                            base_url: el.fUrl.value.trim(),
                            api_token: el.fToken.value.trim(),
                            notes: el.fNotes.value.trim() || null,
                        };
                        resp = await fetch('/api/v1/hosts', { method: 'POST', headers: HEADERS, body: JSON.stringify(body) });
                    } else {
                        // UPDATE — só envia campos preenchidos
                        const body = {
                            label: el.fLabel.value.trim(),
                            notes: el.fNotes.value.trim(),
                        };
                        const newTok = el.fToken.value.trim();
                        if (newTok) body.api_token = newTok;
                        resp = await fetch(`/api/v1/hosts/${editingId}`, { method: 'PUT', headers: HEADERS, body: JSON.stringify(body) });
                    }
                    if (!resp.ok && resp.status !== 201 && resp.status !== 204) {
                        const data = await resp.json().catch(() => ({}));
                        throw new Error(data.detail || `HTTP ${resp.status}`);
                    }
                    closeModal();
                    await load();
                } catch (err) {
                    await customAlert('Erro ao salvar', err.message, 'error');
                } finally {
                    el.fSubmit.disabled = false;
                    el.fCancel.disabled = false;
                }
            });

            el.addBtn.addEventListener('click', openCreateModal);
            el.refreshBtn.addEventListener('click', load);

            // ============================================================
            // Drill-down modal (Detalhes)
            // ============================================================
            const detailEl = {
                modal:        document.getElementById('host-detail-modal'),
                close:        document.getElementById('host-detail-close'),
                title:        document.getElementById('host-detail-title'),
                subtitle:     document.getElementById('host-detail-subtitle'),
                tabBtns:      document.querySelectorAll('.host-tab-btn'),
                paneInfo:     document.getElementById('host-tab-info'),
                paneStatus:   document.getElementById('host-tab-status'),
                paneHistory:  document.getElementById('host-tab-history'),
                infoLoader:   document.getElementById('host-tab-info-loader'),
                infoGrid:     document.getElementById('host-tab-info-grid'),
                statusMeta:   document.getElementById('host-tab-status-meta'),
                statusGrid:   document.getElementById('host-tab-status-grid'),
                statusErr:    document.getElementById('host-tab-status-err'),
                statusReload: document.getElementById('host-tab-status-refresh'),
                historyMeta:  document.getElementById('host-tab-history-meta'),
                historyBars:  document.getElementById('host-tab-history-sparkline'),
                historyRows:  document.getElementById('host-tab-history-rows'),
                historyReload:document.getElementById('host-tab-history-refresh'),
                btnRestartApi:     document.getElementById('host-detail-restart-api'),
                btnRestartUnbound: document.getElementById('host-detail-restart-unbound'),
                btnUpgrade:        document.getElementById('host-detail-upgrade'),
                openUi:            document.getElementById('host-detail-open-ui'),
            };
            let currentDetailHost = null;

            function infoRow(k, v) {
                return `
                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">${escapeHtml(k)}</p>
                        <p class="font-mono text-[11px] font-bold text-slate-900 dark:text-white break-all">${escapeHtml(v === null || v === undefined ? '—' : v)}</p>
                    </div>
                `;
            }

            async function openDetailModal(id) {
                const resp = await fetch('/api/v1/hosts', { headers: HEADERS });
                const data = await resp.json();
                const h = (data.hosts || []).find(x => String(x.id) === String(id));
                if (!h) { await customAlert('Não encontrado', 'Host não está mais no inventário.', 'warning'); return; }

                currentDetailHost = h;
                detailEl.title.textContent = h.label;
                detailEl.subtitle.textContent = h.base_url + (h.notes ? ' — ' + h.notes : '');
                detailEl.openUi.href = h.base_url;
                switchTab('info');
                detailEl.modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';

                renderStatusTab(h);
                await loadInfoTab(h.id);
            }

            function closeDetailModal() {
                detailEl.modal.classList.add('hidden');
                document.body.style.overflow = '';
                currentDetailHost = null;
            }

            function switchTab(which) {
                detailEl.tabBtns.forEach(b => {
                    const active = b.getAttribute('data-tab') === which;
                    b.classList.toggle('border-cyan-500', active);
                    b.classList.toggle('text-cyan-600', active);
                    b.classList.toggle('dark:text-cyan-400', active);
                    b.classList.toggle('border-transparent', !active);
                    b.classList.toggle('text-slate-500', !active);
                });
                detailEl.paneInfo.classList.toggle('hidden', which !== 'info');
                detailEl.paneStatus.classList.toggle('hidden', which !== 'status');
                detailEl.paneHistory.classList.toggle('hidden', which !== 'history');
                // Lazy-load do histórico só quando a aba abre
                if (which === 'history' && currentDetailHost) loadHistoryTab(currentDetailHost.id);
            }

            async function loadInfoTab(id) {
                detailEl.infoLoader.classList.remove('hidden');
                detailEl.infoGrid.classList.add('hidden');
                detailEl.infoGrid.innerHTML = '';
                try {
                    const resp = await fetch(`/api/v1/hosts/${id}/info`, { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const body = await resp.json();
                    if (!body.ok) {
                        detailEl.infoLoader.textContent = 'Falha ao consultar /host/info: ' + (body.error || 'erro desconhecido');
                        return;
                    }
                    const d = body.data || {};
                    detailEl.infoGrid.innerHTML = [
                        infoRow('Hostname', d.hostname),
                        infoRow('FQDN', d.fqdn),
                        infoRow('Sistema', `${d.system || '?'} ${d.release || ''}`.trim()),
                        infoRow('Arquitetura', d.machine),
                        infoRow('Python', d.python_version),
                        infoRow('VERSION', d.version),
                        infoRow('API version', d.api_version),
                    ].join('');
                    detailEl.infoLoader.classList.add('hidden');
                    detailEl.infoGrid.classList.remove('hidden');
                } catch (err) {
                    detailEl.infoLoader.textContent = 'Erro: ' + err.message;
                }
            }

            // Cores das barras do sparkline + cor da pill na tabela
            const HISTORY_STATUS_COLOR = {
                ok:          { bar: 'bg-emerald-500', pill: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30' },
                auth_failed: { bar: 'bg-amber-500',   pill: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                unreachable: { bar: 'bg-red-500',     pill: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
                error:       { bar: 'bg-orange-500',  pill: 'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30' },
            };
            const HISTORY_DEFAULT_COLOR = { bar: 'bg-slate-400', pill: 'bg-slate-500/15 text-slate-500 border-slate-500/30' };

            async function loadHistoryTab(id) {
                detailEl.historyMeta.textContent = 'Carregando histórico…';
                detailEl.historyBars.innerHTML = '<span class="text-[10px] text-slate-500 italic">carregando…</span>';
                detailEl.historyRows.innerHTML = '';
                detailEl.historyReload.disabled = true;
                try {
                    const resp = await fetch(`/api/v1/hosts/${id}/history?limit=100`, { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const body = await resp.json();
                    const items = body.history || [];
                    detailEl.historyMeta.textContent = items.length
                        ? `${items.length} polls registrados (mais recente: ${fmtRelative(items[0].polled_at)})`
                        : 'Sem histórico ainda — aguarde o próximo tick do poller (60s).';
                    // Sparkline: barras finas com cor por status, ordem mais-recente-primeiro
                    detailEl.historyBars.innerHTML = items.map(it => {
                        const c = HISTORY_STATUS_COLOR[it.status] || HISTORY_DEFAULT_COLOR;
                        const tip = `${fmtDate(it.polled_at)} • ${it.status}${it.error ? ' • ' + it.error.replace(/"/g, "'") : ''}`;
                        return `<span class="w-[3px] flex-1 ${c.bar} rounded-sm hover:scale-y-110 transition-transform" style="height:100%" title="${escapeAttr(tip)}"></span>`;
                    }).join('') || '<span class="text-[10px] text-slate-500 italic">sem polls ainda</span>';
                    // Tabela: timestamp, status pill, versão, queries_24h, erro
                    detailEl.historyRows.innerHTML = items.map(it => {
                        const c = HISTORY_STATUS_COLOR[it.status] || HISTORY_DEFAULT_COLOR;
                        const p = it.payload || {};
                        return `
                            <tr>
                                <td class="px-3 py-2 font-mono text-[10px] text-slate-600 dark:text-slate-400 whitespace-nowrap" title="${escapeAttr(it.polled_at || '')}">${fmtRelative(it.polled_at)}</td>
                                <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-md border text-[9px] font-black uppercase tracking-widest ${c.pill}">${escapeHtml(it.status || '?')}</span></td>
                                <td class="px-3 py-2 font-mono">${p.version ? 'v' + escapeHtml(p.version) : '—'}</td>
                                <td class="px-3 py-2 text-right font-mono">${fmtNum(p.queries_24h)}</td>
                                <td class="px-3 py-2 text-[10px] text-slate-500 max-w-xs truncate" title="${escapeAttr(it.error || '')}">${it.error ? escapeHtml(it.error) : ''}</td>
                            </tr>
                        `;
                    }).join('') || `<tr><td colspan="5" class="text-center text-[11px] text-slate-500 italic py-4">Sem polls ainda</td></tr>`;
                } catch (err) {
                    detailEl.historyMeta.textContent = 'Erro: ' + err.message;
                } finally {
                    detailEl.historyReload.disabled = false;
                }
            }

            function renderStatusTab(h) {
                const p = h.last_status_payload || {};
                detailEl.statusMeta.textContent = `Último poll: ${fmtRelative(h.last_polled_at)} • Estado: ${h.last_status || 'unknown'}`;
                detailEl.statusGrid.innerHTML = [
                    infoRow('Versão', p.version ? 'v' + p.version : '—'),
                    infoRow('Uptime', fmtUptime(p.uptime_seconds)),
                    infoRow('Hit ratio 24h', p.hit_ratio_24h !== undefined && p.hit_ratio_24h !== null ? Number(p.hit_ratio_24h).toFixed(1) + '%' : '—'),
                    infoRow('Queries 24h', fmtNum(p.queries_24h)),
                    infoRow('Alertas', p.alerts_active),
                    infoRow('Users', fmtNum(p.users_total)),
                    infoRow('Sessões', fmtNum(p.sessions_active)),
                    infoRow('DuckDB', p.duckdb_ok === true ? 'OK' : (p.duckdb_ok === false ? 'FAIL' : '—')),
                    infoRow('Auth', p.auth_kind),
                ].join('');
                if (h.last_status !== 'ok' && h.last_error) {
                    detailEl.statusErr.textContent = h.last_error;
                    detailEl.statusErr.classList.remove('hidden');
                } else {
                    detailEl.statusErr.classList.add('hidden');
                }
            }

            detailEl.tabBtns.forEach(b => b.addEventListener('click', () => switchTab(b.getAttribute('data-tab'))));
            detailEl.historyReload.addEventListener('click', () => {
                if (currentDetailHost) loadHistoryTab(currentDetailHost.id);
            });
            detailEl.close.addEventListener('click', closeDetailModal);
            detailEl.modal.addEventListener('click', (e) => { if (e.target === detailEl.modal) closeDetailModal(); });
            detailEl.statusReload.addEventListener('click', async () => {
                if (!currentDetailHost) return;
                detailEl.statusReload.disabled = true;
                detailEl.statusReload.textContent = '⟳ Pollando…';
                try {
                    const resp = await fetch(`/api/v1/hosts/${currentDetailHost.id}/poll`, { method: 'POST', headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    await load();
                    // Atualiza referência local com novo estado
                    const r2 = await fetch('/api/v1/hosts', { headers: HEADERS });
                    const data = await r2.json();
                    const h = (data.hosts || []).find(x => x.id === currentDetailHost.id);
                    if (h) { currentDetailHost = h; renderStatusTab(h); }
                } catch (err) {
                    await customAlert('Erro ao polar', err.message, 'error');
                } finally {
                    detailEl.statusReload.disabled = false;
                    detailEl.statusReload.textContent = '↻ Forçar poll';
                }
            });

            detailEl.btnRestartApi.addEventListener('click', async () => {
                if (!currentDetailHost) return;
                const ok = await customConfirm(
                    'Reiniciar api_service',
                    `Reiniciar api_service em "${currentDetailHost.label}"? Polling do master vai falhar por ~5s.`,
                    { variant: 'warning', okLabel: 'Reiniciar' }
                );
                if (!ok) return;
                await singleHostAction(currentDetailHost.id, 'restart/api', 'API reiniciada');
            });
            detailEl.btnRestartUnbound.addEventListener('click', async () => {
                if (!currentDetailHost) return;
                const ok = await customConfirm(
                    'Reiniciar Unbound',
                    `Reiniciar Unbound em "${currentDetailHost.label}"? DNS pode parar por ~1s.`,
                    { variant: 'warning', okLabel: 'Reiniciar' }
                );
                if (!ok) return;
                await singleHostAction(currentDetailHost.id, 'restart/unbound', 'Unbound reiniciado');
            });
            detailEl.btnUpgrade.addEventListener('click', async () => {
                if (!currentDetailHost) return;
                // Mostra a versão detectada NO MASTER pra contexto, mas manda "latest"
                // pro agent resolver via /updates/check próprio (race-free).
                const v = await detectLatestVersion();
                const versionHint = v ? `v${v}` : 'última disponível';
                const ok = await customConfirm(
                    'Atualizar host',
                    `Atualizar "${currentDetailHost.label}" pra ${versionHint}? O agent resolve a versão via GitHub no momento e reinicia.`,
                    { variant: 'warning', okLabel: 'Atualizar' }
                );
                if (!ok) return;
                try {
                    const resp = await fetch(`/api/v1/hosts/${currentDetailHost.id}/upgrade`, {
                        method: 'POST', headers: HEADERS,
                        body: JSON.stringify({ version: 'latest' }),
                    });
                    const data = await resp.json();
                    if (!resp.ok) throw new Error(data.detail || `HTTP ${resp.status}`);
                    if (data.ok) await customAlert('Update disparado', `Self-update rodando em "${currentDetailHost.label}".`, 'success');
                    else await customAlert('Falha no upgrade', data.error || 'erro desconhecido', 'error');
                } catch (err) {
                    await customAlert('Erro', err.message, 'error');
                }
            });

            async function singleHostAction(id, path, successMsg) {
                try {
                    const resp = await fetch(`/api/v1/hosts/${id}/${path}`, { method: 'POST', headers: HEADERS });
                    const data = await resp.json();
                    if (!resp.ok) throw new Error(data.detail || `HTTP ${resp.status}`);
                    if (data.ok) await customAlert('Pronto', successMsg + '.', 'success');
                    else await customAlert('Falha', data.error || 'erro desconhecido', 'error');
                } catch (err) {
                    await customAlert('Erro', err.message, 'error');
                }
            }

            // ============================================================
            // Batch ops
            // ============================================================
            const batchStatusEl = document.getElementById('batch-status');
            const batchResultModal = document.getElementById('batch-result-modal');
            const batchResultTitle = document.getElementById('batch-result-title');
            const batchResultList  = document.getElementById('batch-result-list');
            document.getElementById('batch-result-close').addEventListener('click', () => {
                batchResultModal.classList.add('hidden');
                document.body.style.overflow = '';
            });

            async function detectLatestVersion() {
                try {
                    const resp = await fetch('/api/v1/updates/check', { headers: HEADERS });
                    if (!resp.ok) return null;
                    const data = await resp.json();
                    return data.latest_version || data.latest || null;
                } catch (_) {
                    return null;
                }
            }

            function setBatchBusy(busy, msg) {
                document.querySelectorAll('.batch-btn').forEach(b => b.disabled = busy);
                batchStatusEl.textContent = busy ? (msg || 'Processando…') : '';
            }

            function showBatchResults(title, results) {
                batchResultTitle.textContent = title;
                batchResultList.innerHTML = results.map(r => {
                    const cls = r.ok
                        ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
                        : 'bg-red-500/10 border-red-500/30 text-red-700 dark:text-red-300';
                    const icon = r.ok ? '✓' : '✗';
                    const detail = r.ok ? (r.status_code ? `HTTP ${r.status_code}` : 'OK') : (r.error || `HTTP ${r.status_code}`);
                    return `
                        <div class="${cls} border rounded-md p-2 flex items-start gap-2">
                            <span class="font-mono font-black">${icon}</span>
                            <div class="min-w-0">
                                <p class="font-bold">${escapeHtml(r.label || ('host #' + r.id))}</p>
                                <p class="text-[10px] opacity-80 break-all">${escapeHtml(String(detail))}</p>
                            </div>
                        </div>
                    `;
                }).join('') || '<p class="text-slate-500 italic text-[11px]">Nenhum host gerenciado.</p>';
                batchResultModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            async function runBatch(op) {
                let url, body = null, confirmTitle, confirmMsg, title, okLabel = 'Confirmar', variant = 'warning';
                if (op === 'poll') {
                    url = '/api/v1/hosts/batch/poll';
                    confirmTitle = 'Re-pollar todos';
                    confirmMsg = 'Re-pollar todos os hosts agora?';
                    title = 'Re-poll em lote';
                    okLabel = 'Re-polar';
                    variant = 'info';
                } else if (op === 'restart-api') {
                    url = '/api/v1/hosts/batch/restart/api';
                    confirmTitle = 'Reiniciar API em todos';
                    confirmMsg = 'Reiniciar api_service em TODOS os agents? Polling do master vai falhar por ~5s em cada.';
                    title = 'Restart API em lote';
                    okLabel = 'Reiniciar todos';
                } else if (op === 'restart-unbound') {
                    url = '/api/v1/hosts/batch/restart/unbound';
                    confirmTitle = 'Reiniciar Unbound em todos';
                    confirmMsg = 'Reiniciar Unbound em TODOS os agents? DNS de cada agent pode parar por ~1s.';
                    title = 'Restart Unbound em lote';
                    okLabel = 'Reiniciar todos';
                } else if (op === 'upgrade') {
                    // Detecta a versão NO MASTER só pra mostrar contexto no confirm.
                    // Em fio, manda "latest" — cada agent resolve via /updates/check próprio
                    // (evita race entre cache de master e de cada agent).
                    const v = await detectLatestVersion();
                    const versionHint = v ? `v${v}` : 'última versão disponível';
                    url = '/api/v1/hosts/batch/upgrade';
                    body = JSON.stringify({ version: 'latest' });
                    confirmTitle = 'Atualizar todos';
                    confirmMsg = `Atualizar TODOS os hosts pra ${versionHint}? Cada agent resolve a versão via GitHub no momento e reinicia.`;
                    title = `Upgrade em lote → ${versionHint}`;
                    okLabel = v ? `Atualizar pra v${v}` : 'Atualizar todos';
                } else {
                    return;
                }
                const ok = await customConfirm(confirmTitle, confirmMsg, { variant, okLabel });
                if (!ok) return;
                setBatchBusy(true, 'Disparando…');
                try {
                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: HEADERS,
                        body,
                    });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    if (op === 'poll') {
                        const results = (data.results || []).map(r => ({
                            id: r.id, label: r.label, ok: r.status === 'ok',
                            status_code: r.status === 'ok' ? 200 : 0,
                            error: r.error || r.status,
                        }));
                        showBatchResults(title, results);
                        await load();
                    } else {
                        showBatchResults(title, data.results || []);
                    }
                } catch (err) {
                    await customAlert('Erro', err.message, 'error');
                } finally {
                    setBatchBusy(false);
                }
            }

            document.querySelectorAll('.batch-btn').forEach(b => {
                b.addEventListener('click', () => runBatch(b.getAttribute('data-batch')));
            });

            // Auto-refresh a cada 60s (matching o intervalo do worker)
            setInterval(load, 60000);

            // Carrega ao abrir
            load();

            // Loader hide
            window.addEventListener('load', function () {
                var loader = document.getElementById('global-page-loader');
                if (loader) loader.classList.remove('is-visible');
            });
        })();
    </script>
</body>
</html>
