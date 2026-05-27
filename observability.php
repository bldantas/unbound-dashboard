<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'observability.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('observability.title') ?> - Unbound DNS</title>
    <meta name="description" content="Saúde do daemon Unbound, latência p50/avg, hit ratio, status dos workers e série temporal de queries.">
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('observability.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <?= t('observability.title') ?>
                    </h1>
                    <p class="page-subtitle"><?= t('observability.subtitle') ?></p>
                </div>
                <button type="button" id="btnRefresh" class="glass-btn !bg-purple-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Atualizar</span>
                </button>
            </header>

            <!-- KPI cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">QPS</p>
                    <p id="kpiQps" class="text-2xl font-black text-slate-900 dark:text-white mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Cache Hit %</p>
                    <p id="kpiHit" class="text-2xl font-black text-emerald-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Latência avg</p>
                    <p id="kpiLat" class="text-2xl font-black text-cyan-500 mt-1 tabular-nums">— <span class="text-xs text-slate-400">ms</span></p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Latência mediana</p>
                    <p id="kpiLatMed" class="text-2xl font-black text-cyan-500 mt-1 tabular-nums">— <span class="text-xs text-slate-400">ms</span></p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">DNSSEC %</p>
                    <p id="kpiDnssec" class="text-2xl font-black text-amber-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Uptime</p>
                    <p id="kpiUptime" class="text-lg font-black text-slate-900 dark:text-white mt-2 tabular-nums">—</p>
                </div>
            </div>

            <!-- Gráficos timeseries -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('observability.section_latency') ?></h3>
                        <p class="text-[10px] text-slate-500 mt-1">avg vs median (ms), 1 sample/min</p>
                    </div>
                    <div class="p-4 h-64"><canvas id="chartLat"></canvas></div>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('observability.section_qpm') ?></h3>
                        <p class="text-[10px] text-slate-500 mt-1">hits / miss derivados do counter</p>
                    </div>
                    <div class="p-4 h-64"><canvas id="chartQpm"></canvas></div>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 lg:col-span-2">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('observability.section_qph') ?></h3>
                        <p class="text-[10px] text-slate-500 mt-1">hourly_stats (total vs blocked)</p>
                    </div>
                    <div class="p-4 h-64"><canvas id="chartHourly"></canvas></div>
                </div>
            </div>

            <!-- Workers status -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('observability.section_workers') ?></h3>
                        <p class="text-[10px] text-slate-500 mt-1">Tasks supervisionadas no api_service (backoff exponencial em crash)</p>
                    </div>
                    <span id="workersSummary" class="text-[10px] font-black uppercase tracking-widest text-emerald-500">—</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead><tr><th>Worker</th><th>Status</th><th class="text-right">Tick</th><th>Última execução</th><th>Descrição</th></tr></thead>
                        <tbody id="workersBody"><tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};

    let chartLat, chartQpm, chartHourly;

    function fmtUptime(s) {
        if (!s) return '—';
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        const parts = [];
        if (d) parts.push(d + 'd');
        if (h) parts.push(h + 'h');
        if (m || !parts.length) parts.push(m + 'm');
        return parts.join(' ');
    }
    function fmtRelative(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return '—';
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return diff + 's atrás';
        if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
        return Math.floor(diff / 86400) + 'd atrás';
    }
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    async function loadStats() {
        const r = await fetch('/api/v1/unbound/stats', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        document.getElementById('kpiQps').textContent = (d.qps ?? 0).toFixed(2);
        document.getElementById('kpiHit').textContent = (d.hit_ratio ?? 0).toFixed(1) + '%';
        document.getElementById('kpiLat').innerHTML = (d.latency_avg ?? 0).toFixed(1) + ' <span class="text-xs text-slate-400">ms</span>';
        document.getElementById('kpiLatMed').innerHTML = (d.latency_median ?? 0).toFixed(1) + ' <span class="text-xs text-slate-400">ms</span>';
        document.getElementById('kpiDnssec').textContent = (d.dnssec_ratio ?? 0).toFixed(1) + '%';
        document.getElementById('kpiUptime').textContent = fmtUptime(d.uptime);
    }

    async function loadTimeSeries() {
        const r = await fetch('/api/v1/observability/time-series', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const samples = d.samples || [];
        const labels = samples.map(s => s.label || '');
        const latAvg = samples.map(s => s.latency_avg || 0);
        const latMed = samples.map(s => s.latency_median || 0);
        const hitsDiff = samples.map(s => s.hits_diff || 0);
        const missDiff = samples.map(s => s.miss_diff || 0);

        if (chartLat) chartLat.destroy();
        chartLat = new Chart(document.getElementById('chartLat'), {
            type: 'line',
            data: { labels, datasets: [
                { label: 'avg', data: latAvg, borderColor: 'rgb(6,182,212)', backgroundColor: 'rgba(6,182,212,0.1)', tension: 0.3, fill: true, pointRadius: 0 },
                { label: 'median', data: latMed, borderColor: 'rgb(168,85,247)', backgroundColor: 'rgba(168,85,247,0.05)', tension: 0.3, fill: false, pointRadius: 0 },
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'ms' } } }, plugins: { legend: { labels: { font: { size: 10 } } } } },
        });

        if (chartQpm) chartQpm.destroy();
        chartQpm = new Chart(document.getElementById('chartQpm'), {
            type: 'bar',
            data: { labels, datasets: [
                { label: 'hits', data: hitsDiff, backgroundColor: 'rgba(16,185,129,0.7)', stack: 's' },
                { label: 'miss', data: missDiff, backgroundColor: 'rgba(239,68,68,0.7)', stack: 's' },
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }, plugins: { legend: { labels: { font: { size: 10 } } } } },
        });
    }

    async function loadHourly() {
        const r = await fetch('/api/v1/analytics/hourly?hours=24', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const pts = d.points || [];
        const labels = pts.map(p => {
            const dt = new Date(p.hour_start * 1000);
            return dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        });
        const total = pts.map(p => p.total || 0);
        const blocked = pts.map(p => p.blocked || 0);
        if (chartHourly) chartHourly.destroy();
        chartHourly = new Chart(document.getElementById('chartHourly'), {
            type: 'line',
            data: { labels, datasets: [
                { label: 'total', data: total, borderColor: 'rgb(99,102,241)', backgroundColor: 'rgba(99,102,241,0.1)', tension: 0.3, fill: true, pointRadius: 0 },
                { label: 'blocked', data: blocked, borderColor: 'rgb(239,68,68)', backgroundColor: 'rgba(239,68,68,0.05)', tension: 0.3, fill: false, pointRadius: 0 },
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { labels: { font: { size: 10 } } } } },
        });
    }

    async function loadWorkers() {
        const r = await fetch('/api/v1/observability/workers', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const ws = d.workers || [];
        document.getElementById('workersSummary').textContent = `${d.summary.running}/${d.summary.total} ativos`;
        const tbody = document.getElementById('workersBody');
        if (!ws.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 text-xs">Nenhum worker</td></tr>';
            return;
        }
        tbody.innerHTML = ws.map(w => {
            const statusBadge = w.status === 'running'
                ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-black uppercase">● running</span>'
                : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500/10 text-red-500 text-[10px] font-black uppercase">● ${escapeHtml(w.status)}</span>`;
            const tickHuman = w.tick_seconds < 60 ? `${w.tick_seconds}s` : (w.tick_seconds < 3600 ? `${Math.round(w.tick_seconds/60)}min` : `${Math.round(w.tick_seconds/3600)}h`);
            const extra = w.extra ? Object.entries(w.extra).map(([k,v]) => `<span class="text-[10px] text-slate-500">${escapeHtml(k)}=<b>${escapeHtml(v)}</b></span>`).join(' ') : '';
            return `<tr>
                <td class="font-mono text-xs">${escapeHtml(w.name)}</td>
                <td>${statusBadge}</td>
                <td class="text-right text-[10px] font-mono text-slate-500">${tickHuman}</td>
                <td class="text-[10px] font-mono">${fmtRelative(w.last_run)}</td>
                <td class="text-[10px] text-slate-600 dark:text-slate-400">${escapeHtml(w.description)} ${extra}</td>
            </tr>`;
        }).join('');
    }

    async function refreshAll() {
        await Promise.all([loadStats(), loadTimeSeries(), loadHourly(), loadWorkers()]);
    }

    document.getElementById('btnRefresh').addEventListener('click', refreshAll);

    refreshAll();
    setInterval(refreshAll, 30000);
})();
</script>

</body>
</html>
