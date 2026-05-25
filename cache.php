<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'cache.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Cache DNS - Unbound DNS</title>
    <meta name="description" content="Inspeção do cache do Unbound: rrset records resolvidos e msg cache de queries recentes.">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Cache DNS";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                        Cache DNS
                    </h1>
                    <p class="page-subtitle">RRset records resolvidos e msg cache de queries recentes (snapshot do <code>unbound-control dump_cache</code>).</p>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <button type="button" id="btnLookup" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2" title="Lookup detalhado de um domínio (TTL atual, delegation point, DNSSEC)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>Lookup</span>
                    </button>
                    <?php if ($isAdmin): ?>
                        <button type="button" id="btnReloadUnbound" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2" title="Recarrega config sem reiniciar (preserva cache)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Reload config</span>
                        </button>
                        <button type="button" id="btnFlushAll" class="glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black flex items-center gap-2" title="Esvazia todo o cache do Unbound (não reinicia)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path></svg>
                            <span>Flush total</span>
                        </button>
                    <?php endif; ?>
                    <button type="button" id="btnRefreshCache" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black flex items-center gap-2" title="Re-executa dump_cache (ignora cache de 30s)">
                        <svg id="iconRefreshCache" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span id="btnRefreshLabel">Atualizar</span>
                    </button>
                </div>
            </header>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">RRset entries</p>
                    <p class="text-2xl font-black text-cyan-500" id="statRrsetTotal">--</p>
                    <p class="text-[10px] text-slate-500 mt-1" id="statRrsetSub">Records resolvidos</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Msg entries</p>
                    <p class="text-2xl font-black text-purple-500" id="statMsgTotal">--</p>
                    <p class="text-[10px] text-slate-500 mt-1" id="statMsgSub">Queries cacheadas</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Tipos distintos</p>
                    <p class="text-2xl font-black text-emerald-500" id="statTypes">--</p>
                    <p class="text-[10px] text-slate-500 mt-1">A · AAAA · CNAME · MX · …</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">TLDs distintos</p>
                    <p class="text-2xl font-black text-orange-500" id="statTlds">--</p>
                    <p class="text-[10px] text-slate-500 mt-1">Extensões resolvidas</p>
                </div>
            </div>

            <!-- TTL distribution chart + top types -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 lg:col-span-2">
                    <div class="flex items-baseline justify-between gap-2 mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Distribuição de TTL</h3>
                        <span id="ttlCapLabel" class="text-[10px] font-medium text-slate-500 italic" title="Buckets acima do cap não aparecem porque seriam matematicamente vazios"></span>
                    </div>
                    <div class="h-48"><canvas id="ttlChart"></canvas></div>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2">Top Types</h3>
                    <div id="topTypesList" class="space-y-1 text-xs">
                        <p class="text-slate-500 italic">Carregando...</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 mb-4 border-b border-slate-900/10 dark:border-white/5">
                <button type="button" data-tab="rrset" class="cache-tab-btn cache-tab-active px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 border-cyan-500 text-cyan-600 dark:text-cyan-400 -mb-px">
                    RRset Cache
                </button>
                <button type="button" data-tab="msg" class="cache-tab-btn px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white">
                    Msg Cache
                </button>
            </div>

            <!-- Toolbar -->
            <div class="glass-panel mb-4 border-slate-200 dark:border-white/5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar (nome ou rdata)</label>
                        <input type="text" id="cacheSearch" oninput="onCacheFilterChange()" placeholder="ex: google, .net, 1.1.1.1" class="glass-input w-full font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Tipo</label>
                        <select id="cacheTypeFilter" onchange="onCacheFilterChange()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODOS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Por página</label>
                        <select id="cachePerPage" onchange="onCachePerPageChange()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-3 flex items-center gap-2">
                    Total: <span id="cacheCountTotal">--</span> · Visíveis: <span id="cacheCountVisible">--</span>
                    <span id="cacheTruncatedFlag" class="hidden ml-2 text-amber-500 font-black">⚠ truncado em 5000</span>
                </p>
            </div>

            <!-- Table -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead id="cacheTableHead"></thead>
                        <tbody id="cacheTableBody">
                            <tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs font-black uppercase tracking-widest">Carregando cache...</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="cacheEmpty" class="hidden text-center text-slate-500 text-sm py-8 px-4">Nenhuma entrada atende aos filtros.</p>

                <!-- Paginação -->
                <div id="cachePagination" class="hidden px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest" id="cachePaginationInfo">—</div>
                    <div class="flex items-center gap-2" id="cachePaginationControls">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

        let cacheData = { rrset: [], msg: [], stats: null };
        let currentTab = 'rrset';
        let ttlChartInstance = null;

        // Paginação client-side
        let cachePage = 1;
        let cachePerPage = 50;

        // Fallback caso o backend (versão antiga) não envie ttl_buckets_meta.
        const TTL_BUCKETS_META_FALLBACK = [
            { key: 'expired',   label: 'expirado',   color: '#ef4444' },
            { key: 'lt_60',     label: '< 1 min',    color: '#f59e0b' },
            { key: 'lt_300',    label: '1-5 min',    color: '#eab308' },
            { key: 'lt_3600',   label: '5-60 min',   color: '#10b981' },
            { key: 'lt_86400',  label: '1-24 h',     color: '#06b6d4' },
            { key: 'gte_86400', label: '> 1 dia',    color: '#8b5cf6' },
        ];

        function escHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        }

        function fmtTtl(secs) {
            if (secs <= 0) return 'expirado';
            if (secs < 60) return secs + 's';
            if (secs < 3600) return Math.floor(secs / 60) + 'm';
            if (secs < 86400) return Math.floor(secs / 3600) + 'h';
            return Math.floor(secs / 86400) + 'd';
        }

        async function loadCache(force = false) {
            const btn = document.getElementById('btnRefreshCache');
            const label = document.getElementById('btnRefreshLabel');
            const icon = document.getElementById('iconRefreshCache');
            btn.disabled = true;
            label.textContent = 'Carregando...';
            icon && icon.classList.add('animate-spin');
            try {
                const url = 'api/cache_dump.php' + (force ? '?force=1' : '');
                const res = await fetch(url, { cache: 'no-store' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                cacheData = await res.json();
                renderStats();
                renderTtlChart();
                renderTopTypes();
                populateTypeFilter();
                renderCacheTable();
            } catch (err) {
                console.error('cache load fail', err);
                if (window.AppUI && window.AppUI.toast) {
                    window.AppUI.toast('Falha ao carregar cache: ' + err.message, 'error');
                }
            } finally {
                btn.disabled = false;
                label.textContent = 'Atualizar';
                icon && icon.classList.remove('animate-spin');
            }
        }

        function renderStats() {
            const s = cacheData.stats || {};
            document.getElementById('statRrsetTotal').textContent = (s.rrset_total ?? 0).toLocaleString('pt-BR');
            document.getElementById('statMsgTotal').textContent = (s.msg_total ?? 0).toLocaleString('pt-BR');
            document.getElementById('statTypes').textContent = (s.distinct_types ?? 0).toLocaleString('pt-BR');
            document.getElementById('statTlds').textContent = (s.distinct_tlds ?? 0).toLocaleString('pt-BR');
            document.getElementById('statRrsetSub').textContent = s.rrset_truncated
                ? 'Exibindo ' + s.rrset_shown.toLocaleString('pt-BR') + ' (truncado)'
                : 'Records resolvidos';
            document.getElementById('statMsgSub').textContent = s.msg_truncated
                ? 'Exibindo ' + s.msg_shown.toLocaleString('pt-BR') + ' (truncado)'
                : 'Queries cacheadas';
        }

        function renderTtlChart() {
            const buckets = (cacheData.stats && cacheData.stats.ttl_buckets) || {};
            // Backend (v2.27.1+) manda meta dinâmica filtrada pelo cache-max-ttl
            // atual; versão antiga usa o fallback completo.
            const meta = (cacheData.stats && Array.isArray(cacheData.stats.ttl_buckets_meta))
                ? cacheData.stats.ttl_buckets_meta
                : TTL_BUCKETS_META_FALLBACK;
            const data = meta.map(m => buckets[m.key] || 0);

            // Label: indica o cap atual pra usuário entender por que algum bucket sumiu
            const capSecs = (cacheData.stats && cacheData.stats.cache_max_ttl) || null;
            const capLabel = document.getElementById('ttlCapLabel');
            if (capLabel) {
                capLabel.textContent = capSecs
                    ? 'TTL capado em ' + fmtTtl(capSecs) + ' (cache-max-ttl)'
                    : '';
            }

            if (ttlChartInstance) ttlChartInstance.destroy();
            ttlChartInstance = new Chart(document.getElementById('ttlChart'), {
                type: 'bar',
                data: {
                    labels: meta.map(m => m.label),
                    datasets: [{
                        data,
                        backgroundColor: meta.map(m => m.color),
                        borderRadius: 6,
                        borderWidth: 0,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.1)' } },
                        x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                    },
                },
            });
        }

        function renderTopTypes() {
            const tt = (cacheData.stats && cacheData.stats.top_types) || {};
            const container = document.getElementById('topTypesList');
            const entries = Object.entries(tt);
            if (entries.length === 0) {
                container.innerHTML = '<p class="text-slate-500 italic">Sem dados</p>';
                return;
            }
            container.innerHTML = entries.map(([type, count]) =>
                '<div class="flex items-center justify-between p-2 bg-slate-900/5 dark:bg-white/5 rounded-xl">'
                + '<span class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">' + escHtml(type) + '</span>'
                + '<span class="font-mono font-bold text-cyan-500">' + count.toLocaleString('pt-BR') + '</span>'
                + '</div>'
            ).join('');
        }

        function populateTypeFilter() {
            const sel = document.getElementById('cacheTypeFilter');
            const tt = (cacheData.stats && cacheData.stats.top_types) || {};
            const types = Object.keys(tt).sort();
            const current = sel.value;
            sel.innerHTML = '<option value="">TODOS</option>' + types.map(t =>
                '<option value="' + escHtml(t) + '"' + (t === current ? ' selected' : '') + '>' + escHtml(t) + '</option>'
            ).join('');
        }

        // -- Handlers: filtros/busca/per-page resetam pra página 1 --
        function onCacheFilterChange() {
            cachePage = 1;
            renderCacheTable();
        }
        function onCachePerPageChange() {
            cachePerPage = parseInt(document.getElementById('cachePerPage').value, 10) || 50;
            cachePage = 1;
            renderCacheTable();
        }

        function getFilteredEntries() {
            const tab = currentTab;
            const q = (document.getElementById('cacheSearch').value || '').trim().toLowerCase();
            const typeFilter = document.getElementById('cacheTypeFilter').value;
            const entries = tab === 'rrset' ? (cacheData.rrset || []) : (cacheData.msg || []);
            return entries.filter(e => {
                const name = (tab === 'rrset' ? e.owner : e.qname).toLowerCase();
                const t = (tab === 'rrset' ? e.type : e.qtype);
                const rdata = (tab === 'rrset' ? (e.rdata || '') : '').toLowerCase();
                const matchQ = !q || name.includes(q) || rdata.includes(q);
                const matchT = !typeFilter || t === typeFilter;
                return matchQ && matchT;
            });
        }

        function renderCacheTable() {
            const tab = currentTab;
            const head = document.getElementById('cacheTableHead');
            const body = document.getElementById('cacheTableBody');
            const truncFlag = document.getElementById('cacheTruncatedFlag');
            const stats = cacheData.stats || {};

            // Headers por tab
            if (tab === 'rrset') {
                head.innerHTML = '<tr>'
                    + '<th>Nome</th>'
                    + '<th class="w-20">Tipo</th>'
                    + '<th>Valor</th>'
                    + '<th class="w-20 text-right">TTL</th>'
                    + (IS_ADMIN ? '<th class="w-20 text-right">Ação</th>' : '')
                    + '</tr>';
                truncFlag.classList.toggle('hidden', !stats.rrset_truncated);
            } else {
                head.innerHTML = '<tr>'
                    + '<th>Nome consultado</th>'
                    + '<th class="w-20">Tipo</th>'
                    + '<th class="w-24 text-right">Flags</th>'
                    + '<th class="w-20 text-right">TTL</th>'
                    + (IS_ADMIN ? '<th class="w-20 text-right">Ação</th>' : '')
                    + '</tr>';
                truncFlag.classList.toggle('hidden', !stats.msg_truncated);
            }

            const entries = tab === 'rrset' ? (cacheData.rrset || []) : (cacheData.msg || []);
            const filtered = getFilteredEntries();

            document.getElementById('cacheCountTotal').textContent = entries.length.toLocaleString('pt-BR');
            document.getElementById('cacheCountVisible').textContent = filtered.length.toLocaleString('pt-BR');
            document.getElementById('cacheEmpty').classList.toggle('hidden', filtered.length !== 0 || entries.length === 0);

            // Paginação: clampa página atual ao range válido
            const totalPages = Math.max(1, Math.ceil(filtered.length / cachePerPage));
            if (cachePage > totalPages) cachePage = totalPages;
            if (cachePage < 1) cachePage = 1;
            const startIdx = (cachePage - 1) * cachePerPage;
            const slice = filtered.slice(startIdx, startIdx + cachePerPage);

            if (slice.length === 0) {
                body.innerHTML = '<tr><td colspan="' + (IS_ADMIN ? 5 : 4) + '" class="px-6 py-12 text-center text-slate-500 text-xs italic">Nenhum resultado.</td></tr>';
                renderCachePagination(filtered.length, totalPages, startIdx, 0);
                return;
            }

            const html = slice.map(e => {
                if (tab === 'rrset') {
                    const flushBtn = IS_ADMIN
                        ? '<td class="text-right"><button type="button" class="flush-btn glass-btn !py-1 !px-2 text-[9px] uppercase font-black bg-red-500/10 text-red-500" data-domain="' + escHtml(e.owner) + '" title="Flush ' + escHtml(e.owner) + '">🗑</button></td>'
                        : '';
                    return '<tr>'
                        + '<td class="font-mono text-xs text-slate-900 dark:text-white">' + escHtml(e.owner) + '</td>'
                        + '<td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-500 border border-cyan-500/20">' + escHtml(e.type) + '</span></td>'
                        + '<td class="font-mono text-[11px] text-slate-600 dark:text-slate-400 break-all max-w-md">' + escHtml(e.rdata) + '</td>'
                        + '<td class="text-right text-xs font-mono ' + (e.ttl <= 60 ? 'text-amber-500' : 'text-slate-500') + '">' + fmtTtl(e.ttl) + '</td>'
                        + flushBtn
                        + '</tr>';
                } else {
                    const flushBtn = IS_ADMIN
                        ? '<td class="text-right"><button type="button" class="flush-btn glass-btn !py-1 !px-2 text-[9px] uppercase font-black bg-red-500/10 text-red-500" data-domain="' + escHtml(e.qname) + '" title="Flush ' + escHtml(e.qname) + '">🗑</button></td>'
                        : '';
                    return '<tr>'
                        + '<td class="font-mono text-xs text-slate-900 dark:text-white">' + escHtml(e.qname) + '</td>'
                        + '<td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded bg-purple-500/10 text-purple-500 border border-purple-500/20">' + escHtml(e.qtype) + '</span></td>'
                        + '<td class="text-right text-[10px] font-mono text-slate-500">' + e.flags + '</td>'
                        + '<td class="text-right text-xs font-mono ' + (e.ttl <= 60 ? 'text-amber-500' : 'text-slate-500') + '">' + fmtTtl(e.ttl) + '</td>'
                        + flushBtn
                        + '</tr>';
                }
            }).join('');
            body.innerHTML = html;

            renderCachePagination(filtered.length, totalPages, startIdx, slice.length);

            // Anexa handlers de flush
            if (IS_ADMIN) {
                body.querySelectorAll('.flush-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const domain = btn.dataset.domain;
                        if (!confirm('Esvaziar cache de "' + domain + '"?')) return;
                        btn.disabled = true;
                        try {
                            const fd = new FormData();
                            fd.append('csrf_token', CSRF_TOKEN);
                            fd.append('domain', domain);
                            const res = await fetch('api/cache_flush.php', { method: 'POST', body: fd });
                            const json = await res.json();
                            if (window.AppUI && window.AppUI.toast) {
                                window.AppUI.toast(json.message || 'Flush executado.', json.success ? 'success' : 'error');
                            }
                            if (json.success) {
                                // Recarrega cache pra refletir
                                setTimeout(() => loadCache(true), 500);
                            }
                        } catch (err) {
                            if (window.AppUI && window.AppUI.toast) {
                                window.AppUI.toast('Falha ao flush: ' + err.message, 'error');
                            }
                            btn.disabled = false;
                        }
                    });
                });
            }
        }

        function renderCachePagination(totalFiltered, totalPages, startIdx, sliceLen) {
            const wrapper = document.getElementById('cachePagination');
            const info = document.getElementById('cachePaginationInfo');
            const controls = document.getElementById('cachePaginationControls');

            if (totalFiltered === 0) {
                wrapper.classList.add('hidden');
                return;
            }
            wrapper.classList.remove('hidden');

            const from = sliceLen > 0 ? (startIdx + 1) : 0;
            const to = startIdx + sliceLen;
            info.textContent = `${from.toLocaleString('pt-BR')}–${to.toLocaleString('pt-BR')} de ${totalFiltered.toLocaleString('pt-BR')} · Página ${cachePage} de ${totalPages}`;

            // Botões: primeira | anterior | [janela de 5 números] | próximo | última
            const btn = (label, page, disabled, active) => {
                const cls = active
                    ? 'glass-btn !py-1 !px-3 text-[10px] font-black bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 border-cyan-500/30'
                    : 'glass-btn !py-1 !px-3 text-[10px] font-black' + (disabled ? ' opacity-30 cursor-not-allowed' : '');
                return `<button type="button" class="${cls}" ${disabled ? 'disabled' : ''} data-page="${page}">${label}</button>`;
            };

            const pages = [];
            // Janela de até 5 números centrada na página atual
            const windowSize = 5;
            let start = Math.max(1, cachePage - Math.floor(windowSize / 2));
            let end = Math.min(totalPages, start + windowSize - 1);
            if (end - start + 1 < windowSize) {
                start = Math.max(1, end - windowSize + 1);
            }
            for (let p = start; p <= end; p++) pages.push(p);

            const html =
                btn('« primeiro', 1, cachePage === 1, false) +
                btn('‹ anterior', cachePage - 1, cachePage === 1, false) +
                pages.map(p => btn(String(p), p, false, p === cachePage)).join('') +
                btn('próximo ›', cachePage + 1, cachePage === totalPages, false) +
                btn('último »', totalPages, cachePage === totalPages, false);
            controls.innerHTML = html;

            controls.querySelectorAll('button').forEach(b => {
                b.addEventListener('click', () => {
                    const p = parseInt(b.dataset.page, 10);
                    if (!isNaN(p) && p !== cachePage) {
                        cachePage = p;
                        renderCacheTable();
                        // Rola pro topo da tabela quando trocar de página
                        document.querySelector('.glass-table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        }

        // Tabs
        document.querySelectorAll('.cache-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentTab = btn.dataset.tab;
                cachePage = 1; // reseta paginação ao trocar de tab
                document.querySelectorAll('.cache-tab-btn').forEach(b => {
                    b.classList.remove('cache-tab-active', 'border-cyan-500', 'text-cyan-600', 'dark:text-cyan-400');
                    b.classList.add('border-transparent', 'text-slate-500');
                });
                btn.classList.add('cache-tab-active', 'border-cyan-500', 'text-cyan-600', 'dark:text-cyan-400');
                btn.classList.remove('border-transparent', 'text-slate-500');
                renderCacheTable();
            });
        });

        // Refresh button
        document.getElementById('btnRefreshCache').addEventListener('click', () => loadCache(true));

        // Lookup (qualquer usuário)
        document.getElementById('btnLookup').addEventListener('click', async () => {
            const domain = window.customPrompt
                ? await window.customPrompt('Domínio pra consultar (ex: google.com)', 'Lookup no cache')
                : prompt('Domínio pra consultar:');
            if (!domain) return;
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('domain', domain.trim());
            try {
                const res = await fetch('api/cache_lookup.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const text = data.output || '(sem dados — domínio não está no cache)';
                    if (window.customAlert) await window.customAlert('<pre class="text-xs font-mono whitespace-pre-wrap break-all">' + escHtml(text) + '</pre>', 'Lookup: ' + domain, 'info');
                    else alert(text);
                } else {
                    if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + (data.message || 'erro'), 'error');
                }
            } catch (err) {
                if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
            }
        });

        // Reload config (admin)
        document.getElementById('btnReloadUnbound')?.addEventListener('click', async () => {
            const ok = window.customConfirm
                ? await window.customConfirm('Recarregar config do Unbound? <b>Preserva o cache</b> em memória.', 'Reload config')
                : confirm('Recarregar config?');
            if (!ok) return;
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('api/cache_reload.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success && window.AppUI?.toast) window.AppUI.toast(data.message, 'success');
                else if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + (data.message || 'erro'), 'error');
            } catch (err) {
                if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
            }
        });

        // Flush total (admin) — destrutivo, dois passos de confirmação
        document.getElementById('btnFlushAll')?.addEventListener('click', async () => {
            const ok = window.customConfirm
                ? await window.customConfirm('<b>Esvaziar TODO o cache</b> do Unbound? Hit ratio cai pra ~0% temporariamente até reaquecer.', 'Flush total?')
                : confirm('Esvaziar TODO o cache?');
            if (!ok) return;
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('api/cache_flush_all.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    if (window.AppUI?.toast) window.AppUI.toast(data.message + ' ' + (data.output || ''), 'success');
                    setTimeout(() => loadCache(true), 500);
                } else if (window.AppUI?.toast) {
                    window.AppUI.toast('Falha: ' + (data.message || 'erro'), 'error');
                }
            } catch (err) {
                if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
            }
        });

        // Initial load
        loadCache(false);
    </script>

</body>
</html>
