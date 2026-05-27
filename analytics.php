<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'analytics.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('analytics.title') ?> - Unbound DNS</title>
    <meta name="description" content="Análise profunda de queries DNS: métricas, distribuições, top domínios e clientes, com janela ajustável.">
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('analytics.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <!-- Seletor de período -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Janela de análise</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Período atual: <span id="windowLabel" class="text-purple-500">24 horas</span></p>
                    </div>
                    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl p-1 border border-slate-200 dark:border-white/10">
                        <button class="window-btn px-4 py-2 rounded-xl text-[10px] uppercase font-black tracking-widest transition-all" data-window="1h">1h</button>
                        <button class="window-btn px-4 py-2 rounded-xl text-[10px] uppercase font-black tracking-widest transition-all active" data-window="24h">24h</button>
                        <button class="window-btn px-4 py-2 rounded-xl text-[10px] uppercase font-black tracking-widest transition-all" data-window="7d">7 dias</button>
                        <button class="window-btn px-4 py-2 rounded-xl text-[10px] uppercase font-black tracking-widest transition-all" data-window="30d">30 dias</button>
                    </div>
                </div>
            </div>

            <!-- Cards de métricas -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-panel glow-blue group relative overflow-hidden border-slate-200 dark:border-white/5">
                    <p class="metric-label">Queries totais</p>
                    <p class="metric-value text-blue-500" id="mTotal">—</p>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2"><span id="mUniqClients">—</span> clientes únicos</p>
                </div>
                <div class="glass-panel glow-red group relative overflow-hidden border-slate-200 dark:border-white/5">
                    <p class="metric-label">Bloqueadas</p>
                    <p class="metric-value text-red-500" id="mBlocked">—</p>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2"><span id="mBlockedRatio">—</span>% do total</p>
                </div>
                <div class="glass-panel glow-emerald group relative overflow-hidden border-slate-200 dark:border-white/5">
                    <p class="metric-label">Cache hit</p>
                    <p class="metric-value text-emerald-500" id="mCached">—</p>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2"><span id="mCacheRatio">—</span>% hit rate</p>
                </div>
                <div class="glass-panel glow-purple group relative overflow-hidden border-slate-200 dark:border-white/5">
                    <p class="metric-label">Domínios únicos</p>
                    <p class="metric-value text-purple-500" id="mUniqDomains">—</p>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2"><span id="mNxdomain">—</span> NXDOMAIN upstream</p>
                </div>
            </div>

            <!-- Timeseries -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('analytics.section_queries_time') ?></h3>
                <div style="height:280px"><canvas id="timeseriesChart"></canvas></div>
            </div>

            <!-- Donuts: actions + query types -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('analytics.section_dist_action') ?></h3>
                    <div style="height:240px"><canvas id="actionsChart"></canvas></div>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('analytics.section_dist_type') ?></h3>
                    <div style="height:240px"><canvas id="typesChart"></canvas></div>
                </div>
            </div>

            <!-- Top tables -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="glass-table-container border-slate-200 dark:border-white/5">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('analytics.section_top_domains') ?></h3>
                        <select id="topDomainsAction" class="text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-lg px-2 py-1 cursor-pointer">
                            <option value="">Todas as ações</option>
                            <option value="blocked">Só bloqueadas</option>
                            <option value="resolved">Só resolvidas</option>
                            <option value="nxdomain_upstream">Só NXDOMAIN</option>
                        </select>
                    </div>
                    <table class="glass-table">
                        <thead><tr><th class="w-10">#</th><th>Domínio</th><th class="w-20 text-right">Total</th><th class="w-20 text-right">Bloq</th></tr></thead>
                        <tbody id="topDomainsBody"><tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr></tbody>
                    </table>
                </div>
                <div class="glass-table-container border-slate-200 dark:border-white/5">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('analytics.section_top_clients') ?></h3>
                    </div>
                    <table class="glass-table">
                        <thead><tr><th class="w-10">#</th><th>IP</th><th class="w-20 text-right">Total</th><th class="w-16 text-right">Bloq%</th><th class="w-20 text-right">Domínios</th></tr></thead>
                        <tbody id="topClientsBody"><tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>

            <!-- Retenção & Rollups (admin only) -->
            <?php if (\App\Auth::isAdmin()): ?>
            <div id="retentionPanel" class="glass-panel mt-6 border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('analytics.section_retention') ?></h3>
                        <p class="text-[10px] text-slate-500 mt-1">Pruner roda 1x/h. Mín 7 dias.</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="retEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 rounded-full peer-checked:bg-emerald-500 transition-colors relative">
                            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">Ativo</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-6">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Reter (dias)</label>
                        <input type="number" id="retDays" min="7" max="3650" class="glass-input w-full mt-1 font-mono">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Linhas totais</label>
                        <div id="retTotalRows" class="text-2xl font-black text-slate-900 dark:text-white mt-1 tabular-nums">—</div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Linha mais antiga</label>
                        <div id="retOldest" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-1">—</div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Última execução</label>
                        <div id="retLastRun" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-1">—</div>
                        <div id="retLastDeleted" class="text-[10px] text-slate-500 mt-0.5">—</div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <button type="button" id="btnRetSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <button type="button" id="btnRetPruneNow" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Executar agora</button>
                </div>
            </div>
            <?php endif; ?>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    let currentWindow = '24h';
    let currentDomainAction = '';
    let chartTs, chartActions, chartTypes;
    const WINDOW_LABELS = { '1h': 'última hora', '24h': '24 horas', '7d': '7 dias', '30d': '30 dias' };

    // ============ Seletor de janela ============
    document.querySelectorAll('.window-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.window-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentWindow = btn.dataset.window;
            document.getElementById('windowLabel').textContent = WINDOW_LABELS[currentWindow];
            refreshAll();
        });
    });

    document.getElementById('topDomainsAction').addEventListener('change', (e) => {
        currentDomainAction = e.target.value;
        loadTopDomains();
    });

    // ============ Loaders ============
    async function fetchJson(path) {
        const res = await fetch(path, { headers: H });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function loadSummary() {
        try {
            const d = await fetchJson(`/api/v1/analytics/summary?window=${currentWindow}`);
            setNum('mTotal', d.total);
            setNum('mBlocked', d.blocked);
            setNum('mCached', d.cached);
            setNum('mUniqDomains', d.unique_domains);
            document.getElementById('mUniqClients').textContent = d.unique_clients.toLocaleString('pt-BR');
            document.getElementById('mBlockedRatio').textContent = d.blocked_ratio;
            document.getElementById('mCacheRatio').textContent = d.cache_ratio;
            document.getElementById('mNxdomain').textContent = d.nxdomain_upstream.toLocaleString('pt-BR');
        } catch (err) { console.error('summary', err); }
    }

    async function loadTimeseries() {
        try {
            const d = await fetchJson(`/api/v1/analytics/timeseries?window=${currentWindow}`);
            renderTimeseries(d);
        } catch (err) { console.error('timeseries', err); }
    }

    async function loadActions() {
        try {
            const d = await fetchJson(`/api/v1/analytics/action-breakdown?window=${currentWindow}`);
            renderActions(d.items);
        } catch (err) { console.error('actions', err); }
    }

    async function loadTypes() {
        try {
            const d = await fetchJson(`/api/v1/analytics/by-query-type?window=${currentWindow}`);
            renderTypes(d.items);
        } catch (err) { console.error('types', err); }
    }

    async function loadTopDomains() {
        const tbody = document.getElementById('topDomainsBody');
        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>`;
        try {
            const qs = new URLSearchParams({ window: currentWindow, limit: '20' });
            if (currentDomainAction) qs.set('action', currentDomainAction);
            const d = await fetchJson(`/api/v1/analytics/top-domains?${qs}`);
            renderTopDomains(d.items);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">${err.message}</td></tr>`;
        }
    }

    async function loadTopClients() {
        const tbody = document.getElementById('topClientsBody');
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>`;
        try {
            const d = await fetchJson(`/api/v1/analytics/top-clients?window=${currentWindow}&limit=20`);
            renderTopClients(d.items);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">${err.message}</td></tr>`;
        }
    }

    function refreshAll() {
        loadSummary();
        loadTimeseries();
        loadActions();
        loadTypes();
        loadTopDomains();
        loadTopClients();
    }

    // ============ Renderers ============
    function setNum(id, v) {
        document.getElementById(id).textContent = (v || 0).toLocaleString('pt-BR');
    }

    function renderTimeseries(data) {
        const labels = data.points.map(p => fmtTs(p.ts, data.bucket_seconds));
        const total = data.points.map(p => p.total);
        const blocked = data.points.map(p => p.blocked);
        const cached = data.points.map(p => p.cached);
        if (chartTs) chartTs.destroy();
        chartTs = new Chart(document.getElementById('timeseriesChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Total',   data: total,   borderColor: 'rgb(59,130,246)', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.3, fill: true, pointRadius: 0 },
                    { label: 'Bloqueadas', data: blocked, borderColor: 'rgb(239,68,68)', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.3, fill: false, pointRadius: 0 },
                    { label: 'Cache hit',  data: cached,  borderColor: 'rgb(16,185,129)', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.3, fill: false, pointRadius: 0 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { font: { size: 10, weight: 700 } } } },
                scales: {
                    x: { ticks: { maxTicksLimit: 12, font: { size: 9 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 9 } }, grid: { color: 'rgba(148,163,184,0.1)' } },
                },
            },
        });
    }

    const ACTION_COLORS = { 'resolved': 'rgb(16,185,129)', 'cached': 'rgb(59,130,246)', 'blocked': 'rgb(239,68,68)', 'nxdomain_upstream': 'rgb(249,115,22)' };

    function renderActions(items) {
        const labels = items.map(i => i.action);
        const data = items.map(i => i.count);
        const colors = items.map(i => ACTION_COLORS[i.action] || 'rgb(148,163,184)');
        if (chartActions) chartActions.destroy();
        chartActions = new Chart(document.getElementById('actionsChart'), {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: 'rgba(15,23,42,0.5)' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { size: 10, weight: 700 } } } } },
        });
    }

    function renderTypes(items) {
        const labels = items.map(i => i.type);
        const data = items.map(i => i.count);
        const colors = ['rgb(59,130,246)','rgb(16,185,129)','rgb(249,115,22)','rgb(168,85,247)','rgb(236,72,153)','rgb(245,158,11)','rgb(20,184,166)','rgb(239,68,68)','rgb(148,163,184)','rgb(99,102,241)','rgb(34,197,94)','rgb(217,70,239)','rgb(14,165,233)'];
        if (chartTypes) chartTypes.destroy();
        chartTypes = new Chart(document.getElementById('typesChart'), {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors.slice(0, labels.length), borderWidth: 2, borderColor: 'rgba(15,23,42,0.5)' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { size: 10, weight: 700 } } } } },
        });
    }

    function renderTopDomains(items) {
        const tbody = document.getElementById('topDomainsBody');
        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Sem dados</td></tr>`;
            return;
        }
        tbody.innerHTML = items.map((d, i) => `
            <tr>
                <td class="text-slate-500 font-mono text-xs">${i+1}</td>
                <td class="font-mono text-sm">${escapeHtml(d.domain)}</td>
                <td class="text-right font-mono text-sm font-bold">${d.total.toLocaleString('pt-BR')}</td>
                <td class="text-right font-mono text-xs ${d.blocked > 0 ? 'text-red-500 font-bold' : 'text-slate-400'}">${d.blocked > 0 ? d.blocked.toLocaleString('pt-BR') : '—'}</td>
            </tr>
        `).join('');
    }

    function renderTopClients(items) {
        const tbody = document.getElementById('topClientsBody');
        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Sem dados</td></tr>`;
            return;
        }
        tbody.innerHTML = items.map((c, i) => `
            <tr>
                <td class="text-slate-500 font-mono text-xs">${i+1}</td>
                <td class="font-mono text-sm">${escapeHtml(c.client_ip)}</td>
                <td class="text-right font-mono text-sm font-bold">${c.total.toLocaleString('pt-BR')}</td>
                <td class="text-right font-mono text-xs ${c.blocked_ratio > 1 ? 'text-red-500 font-bold' : 'text-slate-400'}">${c.blocked_ratio}%</td>
                <td class="text-right font-mono text-xs text-slate-500">${c.unique_domains.toLocaleString('pt-BR')}</td>
            </tr>
        `).join('');
    }

    // ============ util ============
    function fmtTs(ts, bucket) {
        const d = new Date(ts * 1000);
        if (bucket >= 86400) return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        if (bucket >= 3600)  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    refreshAll();

    // === Retenção (admin only) ===
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const retEl = document.getElementById('retentionPanel');
    if (retEl) {
        const $ = (id) => document.getElementById(id);
        const fmtEpoch = (e) => e ? new Date(e * 1000).toLocaleString('pt-BR') : '—';

        async function loadRetention() {
            try {
                const res = await fetch('/api/v1/analytics/retention/settings', { headers: H });
                if (!res.ok) return;
                const d = await res.json();
                $('retEnabled').checked = String(d.settings.query_log_retention_enabled) === '1';
                $('retDays').value = parseInt(d.settings.query_log_retention_days || '90', 10);
                $('retTotalRows').textContent = (d.current.total_rows || 0).toLocaleString('pt-BR');
                $('retOldest').textContent = fmtEpoch(d.current.oldest_epoch);
                $('retLastRun').textContent = d.last_run.last_run ? new Date(d.last_run.last_run).toLocaleString('pt-BR') : 'nunca';
                $('retLastDeleted').textContent = d.last_run.last_deleted ? `${parseInt(d.last_run.last_deleted, 10).toLocaleString('pt-BR')} linhas removidas` : '';
            } catch (e) { console.error('retention.load', e); }
        }

        $('btnRetSave').addEventListener('click', async () => {
            const body = {
                query_log_retention_enabled: $('retEnabled').checked ? '1' : '0',
                query_log_retention_days: String(Math.max(7, parseInt($('retDays').value || '90', 10))),
            };
            const res = await fetch('/api/v1/analytics/retention/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            if (res.ok) {
                window.customAlert ? customAlert('Salvo.') : alert('Salvo.');
                loadRetention();
            } else {
                window.customAlert ? customAlert('Erro ao salvar.') : alert('Erro ao salvar.');
            }
        });

        $('btnRetPruneNow').addEventListener('click', async () => {
            const ok = window.customConfirm ? await customConfirm('Executar prune agora?') : confirm('Executar prune agora?');
            if (!ok) return;
            const res = await fetch('/api/v1/analytics/retention/prune-now', { method: 'POST', headers: H });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.started) {
                window.customAlert ? customAlert(`Removidas: ${(d.deleted || 0).toLocaleString('pt-BR')} linhas.`) : alert(`Removidas: ${d.deleted || 0}`);
                loadRetention();
            } else {
                window.customAlert ? customAlert('Erro: ' + (d.error || res.statusText)) : alert('Erro');
            }
        });

        loadRetention();
    }
})();
</script>

<style>
    .window-btn { color: rgb(148,163,184); }
    .window-btn:not(.active):hover { color: rgb(168,85,247); background: rgba(168,85,247,0.08); }
    .window-btn.active { color: white; background: linear-gradient(135deg, rgb(168,85,247), rgb(126,34,206)); box-shadow: 0 4px 12px rgba(168,85,247,0.3); }
</style>

</body>
</html>
