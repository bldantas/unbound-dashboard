<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'performance.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Performance & Cache - Unbound DNS</title>
    <meta name="description" content="Tuning fino do Unbound: prefetch, serve-expired, TTLs, cache sizes, e KPIs P50/P95/P99.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Performance & Cache";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <?= t('performance.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('performance.subtitle') ?></p>
            </header>

            <!-- KPIs latência -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">QPS</p>
                    <p id="kpiQps" class="text-3xl font-black text-violet-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Cache hit %</p>
                    <p id="kpiHit" class="text-3xl font-black text-emerald-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Prefetch counter</p>
                    <p id="kpiPrefetch" class="text-3xl font-black text-cyan-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Uptime</p>
                    <p id="kpiUptime" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-2">—</p>
                </div>
            </div>

            <!-- KPIs P50/P95/P99 latência de recursão -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lat. P50 (ms)</p>
                    <p id="kpiP50" class="text-3xl font-black text-blue-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lat. P95 (ms)</p>
                    <p id="kpiP95" class="text-3xl font-black text-amber-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lat. P99 (ms)</p>
                    <p id="kpiP99" class="text-3xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lat. média/avg</p>
                    <p id="kpiAvg" class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
            </div>

            <!-- KPIs cache & request list -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">RRset cache</p>
                    <p id="kpiRrsetMem" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 font-mono">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Msg cache</p>
                    <p id="kpiMsgMem" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 font-mono">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Req list avg</p>
                    <p id="kpiReqAvg" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Req list max</p>
                    <p id="kpiReqMax" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
            </div>

            <!-- Toggles -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Otimizações</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Toggles que ativam/desativam funcionalidades do Unbound. Sobrepõem <code>optimization.conf</code>.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="pPrefetch" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Prefetch</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Atualiza popular records ~10% antes do TTL expirar — keeps cache warm.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="pPrefetchKey" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Prefetch DNSKEY</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Mesmo pra DNSKEY records (acelera validação DNSSEC).</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/5 bg-emerald-50/40 dark:bg-emerald-500/5">
                        <input type="checkbox" id="pServeExpired" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px] text-emerald-700 dark:text-emerald-300">Serve Expired (RFC 8767)</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Serve resposta stale se upstream demorar — UX sem cair durante outages.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="pMinimalResponses" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Minimal Responses</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Omite Authority/Additional não-essenciais. Menos bandwidth.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="pRoundRobin" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">RRset Round-Robin</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Embaralha ordem dos A records — load balance básico.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Serve-expired tuning + TTLs -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Tuning de TTLs</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Controla tempo de vida de respostas no cache. Defaults do Unbound: cache-max-ttl=86400 (1d), cache-min-ttl=0.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">cache-min-ttl (s)</label>
                        <input type="number" id="pCacheMinTtl" min="0" max="2592000" class="glass-input w-full mt-1 font-mono">
                        <p class="text-[10px] text-slate-500 mt-1">0 = honra TTL do servidor.</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">cache-max-ttl (s)</label>
                        <input type="number" id="pCacheMaxTtl" min="0" max="2592000" class="glass-input w-full mt-1 font-mono">
                        <p class="text-[10px] text-slate-500 mt-1">Default 86400 (1d).</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">serve-expired-ttl (s)</label>
                        <input type="number" id="pServeExpiredTtl" min="0" max="2592000" class="glass-input w-full mt-1 font-mono">
                        <p class="text-[10px] text-slate-500 mt-1">Janela que stale ainda vale.</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">client-timeout (ms)</label>
                        <input type="number" id="pServeExpiredTimeout" min="0" max="30000" class="glass-input w-full mt-1 font-mono">
                        <p class="text-[10px] text-slate-500 mt-1">Default 1800ms. Após N ms, serve stale.</p>
                    </div>
                </div>
            </div>

            <!-- Cache sizes -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Cache Sizes</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Defaults: msg-cache 50MB, rrset-cache 100MB. Aumente em volumes altos (>500 QPS).</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">msg-cache-size (MB)</label>
                        <input type="number" id="pMsgCacheMb" min="4" max="4096" class="glass-input w-full mt-1 font-mono">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">rrset-cache-size (MB)</label>
                        <input type="number" id="pRrsetCacheMb" min="8" max="8192" class="glass-input w-full mt-1 font-mono">
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <button type="button" id="btnSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar Performance</button>
                    <button type="button" id="btnApply" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Aplicar + Restart Unbound</button>
                </div>
                <?php endif; ?>
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
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    const $ = (id) => document.getElementById(id);

    async function loadMetrics() {
        const r = await fetch('/api/v1/dns-security/performance/metrics', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('kpiQps').textContent = (d.qps ?? 0).toFixed(1);
        $('kpiHit').textContent = (d.hit_ratio ?? 0).toFixed(1) + '%';
        $('kpiPrefetch').textContent = (d.prefetch ?? 0).toLocaleString('pt-BR');
        $('kpiUptime').textContent = d.uptime_human || '—';
        $('kpiP50').textContent = (d.latency_p50 ?? 0).toFixed(1);
        $('kpiP95').textContent = (d.latency_p95 ?? 0).toFixed(1);
        $('kpiP99').textContent = (d.latency_p99 ?? 0).toFixed(1);
        $('kpiAvg').textContent = (d.latency_avg ?? 0).toFixed(1);
        $('kpiRrsetMem').textContent = d.rrset_mem || '—';
        $('kpiMsgMem').textContent = d.msg_mem || '—';
        $('kpiReqAvg').textContent = (d.req_list_avg ?? 0).toFixed(2);
        $('kpiReqMax').textContent = (d.req_list_max ?? 0).toLocaleString('pt-BR');
    }

    const BOOL_MAP = [
        ['pPrefetch', 'unbound_perf_prefetch'],
        ['pPrefetchKey', 'unbound_perf_prefetch_key'],
        ['pServeExpired', 'unbound_perf_serve_expired'],
        ['pMinimalResponses', 'unbound_perf_minimal_responses'],
        ['pRoundRobin', 'unbound_perf_rrset_roundrobin'],
    ];
    const INT_MAP = [
        ['pCacheMinTtl', 'unbound_perf_cache_min_ttl'],
        ['pCacheMaxTtl', 'unbound_perf_cache_max_ttl'],
        ['pServeExpiredTtl', 'unbound_perf_serve_expired_ttl'],
        ['pServeExpiredTimeout', 'unbound_perf_serve_expired_client_timeout'],
        ['pMsgCacheMb', 'unbound_perf_msg_cache_size_mb'],
        ['pRrsetCacheMb', 'unbound_perf_rrset_cache_size_mb'],
    ];

    async function loadSettings() {
        const r = await fetch('/api/v1/dns-security/performance/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const s = d.settings || {};
        BOOL_MAP.forEach(([el, key]) => { const c = $(el); if (c) c.checked = String(s[key]) === '1'; });
        INT_MAP.forEach(([el, key]) => { const i = $(el); if (i) i.value = parseInt(s[key] || '0', 10); });
    }

    if (IS_ADMIN) {
        $('btnSave')?.addEventListener('click', async () => {
            const body = {};
            BOOL_MAP.forEach(([el, key]) => { body[key] = $(el)?.checked ? '1' : '0'; });
            INT_MAP.forEach(([el, key]) => { body[key] = String(Math.max(0, parseInt($(el)?.value || '0', 10))); });
            const r = await fetch('/api/v1/dns-security/performance/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            (window.customAlert || alert)(r.ok ? t('js.saved_apply_hint') : t('js.save_failed'));
        });
        $('btnApply')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Aplicar tuning de performance + restart Unbound? ~2s de interrupção.') : Promise.resolve(confirm('Confirma?')));
            if (!ok) return;
            const r = await fetch('/api/v1/dns-security/apply', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok && d.ok ? 'Aplicado.' : `Falha em "${d.stage || '?'}": ${d.error || r.statusText}`);
            loadMetrics();
        });
    }

    loadMetrics();
    loadSettings();
    setInterval(loadMetrics, 30000);
})();
</script>

</body>
</html>
