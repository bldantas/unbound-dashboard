<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'external_health.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('external_health.title') ?> - Unbound DNS</title>
    <meta name="description" content="SLA externo: monitor de fora do servidor reporta probes DNS contínuos.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('external_health.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-lime-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= t('external_health.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('external_health.subtitle') ?></p>
            </header>

            <div class="flex flex-wrap items-end gap-3 mb-6 text-xs">
                <label class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Janela (horas)</span>
                    <select id="fHours" class="glass-input">
                        <option value="1">1h</option>
                        <option value="6">6h</option>
                        <option value="24" selected>24h</option>
                        <option value="168">7d</option>
                        <option value="720">30d</option>
                    </select>
                </label>
                <label class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Probe source</span>
                    <select id="fSource" class="glass-input w-48"><option value="">— todos —</option></select>
                </label>
                <button type="button" id="btnRefresh" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Atualizar</button>
            </div>

            <!-- KPIs SLA -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Uptime %</p>
                    <p id="kpiUptime" class="text-3xl font-black text-emerald-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Resposta correta %</p>
                    <p id="kpiCorrect" class="text-3xl font-black text-blue-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total probes</p>
                    <p id="kpiTotal" class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Latência P95</p>
                    <p id="kpiP95" class="text-3xl font-black text-amber-500 mt-1 tabular-nums">—</p>
                </div>
            </div>

            <!-- Latency percentiles -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">P50 (ms)</p>
                    <p id="kpiP50" class="text-2xl font-black text-blue-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">P99 (ms)</p>
                    <p id="kpiP99" class="text-2xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Média (ms)</p>
                    <p id="kpiAvg" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Falhas</p>
                    <p id="kpiFail" class="text-2xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
            </div>

            <!-- Recent probes -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                <div class="px-6 py-3 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('external_health.section_probes') ?></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Quando</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Source</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Alvo</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Query</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Latência</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Resposta OK?</th>
                            </tr>
                        </thead>
                        <tbody id="tbody" class="divide-y divide-slate-200 dark:divide-white/5">
                            <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <!-- Retention -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('external_health.section_retention') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Worker <code>ExternalHealthPruner</code> apaga 1x/dia probes mais velhos que N dias. Default 90, mín 7, máx 3650.</p>
                </div>
                <div class="p-6 flex flex-wrap items-end gap-4 text-xs">
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Dias de retenção</span>
                        <input type="number" id="ehRetDays" min="7" max="3650" class="glass-input w-32 font-mono">
                    </label>
                    <button type="button" id="btnEhRetSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <div class="ml-auto text-right text-[10px] text-slate-500">
                        <p>Última: <span id="ehPrunerLastAt" class="font-mono text-slate-700 dark:text-slate-300">—</span></p>
                        <p>Apagados: <span id="ehPrunerLastDeleted" class="font-mono text-slate-700 dark:text-slate-300">—</span></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Setup helper -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('external_health.section_monitor_howto') ?></h3>
                </div>
                <div class="p-6 text-xs space-y-3">
                    <p>1. Em outra máquina (idealmente fora da rede do servidor monitorado), baixe <code>api_service/tools/external_healthcheck.py</code> deste repo.</p>
                    <p>2. Crie um <strong>API token dedicado</strong> em <code>/api_tokens.php</code> (role admin).</p>
                    <p>3. Adicione ao crontab:</p>
                    <pre class="bg-slate-100 dark:bg-slate-800 p-3 rounded-xl font-mono text-[10px] overflow-x-auto">* * * * * /usr/bin/python3 /opt/external_healthcheck.py \
  --dns-server 192.168.1.10:53 \
  --query-name google.com \
  --probe-source monitor-aws-east \
  --api-url https://&lt;seu-dashboard&gt; \
  --api-token &lt;X-Api-Token&gt; \
  --quiet</pre>
                    <p class="text-slate-500">Zero deps (só stdlib). Tempo execução típico: 50-200ms.</p>
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
    const $ = (id) => document.getElementById(id);

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function relTime(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return iso;
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return diff + 's';
        if (diff < 3600) return Math.floor(diff/60) + 'min';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        return Math.floor(diff/86400) + 'd';
    }

    function buildQuery() {
        const params = new URLSearchParams();
        params.set('hours', $('fHours').value);
        const src = $('fSource').value;
        if (src) params.set('probe_source', src);
        return params.toString();
    }

    async function loadSources() {
        const r = await fetch('/api/v1/external-health/sources?hours=168', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const cur = $('fSource').value;
        $('fSource').innerHTML = '<option value="">— todos —</option>' + (d.sources || []).map(s =>
            `<option value="${esc(s.probe_source)}">${esc(s.probe_source)} (${s.count})</option>`
        ).join('');
        $('fSource').value = cur;
    }

    async function loadSla() {
        const r = await fetch('/api/v1/external-health/sla?' + buildQuery(), { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('kpiUptime').textContent = (d.sla_uptime_pct ?? 0).toFixed(3) + '%';
        $('kpiCorrect').textContent = (d.sla_correct_pct ?? 0).toFixed(3) + '%';
        $('kpiTotal').textContent = (d.total_probes ?? 0).toLocaleString('pt-BR');
        $('kpiP95').textContent = (d.latency_p95_ms ?? 0).toFixed(0);
        $('kpiP50').textContent = (d.latency_p50_ms ?? 0).toFixed(0);
        $('kpiP99').textContent = (d.latency_p99_ms ?? 0).toFixed(0);
        $('kpiAvg').textContent = (d.latency_avg_ms ?? 0).toFixed(1);
        $('kpiFail').textContent = ((d.total_probes ?? 0) - (d.success_count ?? 0)).toLocaleString('pt-BR');
    }

    async function loadList() {
        const r = await fetch('/api/v1/external-health/list?limit=100&' + buildQuery(), { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const items = d.items || [];
        if (!items.length) {
            $('tbody').innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Nenhum probe na janela.</td></tr>';
            return;
        }
        $('tbody').innerHTML = items.map(it => {
            const stCls = it.success ? 'text-emerald-500' : 'text-red-500';
            const stText = it.success ? 'OK' : `ERR (${esc(it.error || '?')})`;
            const corCls = it.response_correct ? 'text-emerald-500' : 'text-amber-500';
            const corText = it.response_correct == null ? '—' : (it.response_correct ? '✓' : '✗');
            return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                <td class="px-3 py-2 font-mono text-[10px] text-slate-500" title="${esc(it.probed_at)}">${relTime(it.probed_at)}</td>
                <td class="px-3 py-2 font-mono">${esc(it.probe_source || '—')}</td>
                <td class="px-3 py-2 font-mono text-[10px]">${esc(it.target_host || '—')}</td>
                <td class="px-3 py-2 font-mono text-[10px]">${esc(it.query_name || '—')}</td>
                <td class="px-3 py-2 font-black ${stCls}">${stText}</td>
                <td class="px-3 py-2 font-mono">${it.latency_ms != null ? it.latency_ms + 'ms' : '—'}</td>
                <td class="px-3 py-2 font-black ${corCls}">${corText}</td>
            </tr>`;
        }).join('');
    }

    async function refresh() {
        await Promise.all([loadSources(), loadSla(), loadList()]);
    }
    $('btnRefresh').addEventListener('click', refresh);
    $('fHours').addEventListener('change', refresh);
    $('fSource').addEventListener('change', refresh);

    // Retention panel
    async function loadRetention() {
        const el = $('ehRetDays');
        if (!el) return;
        const r = await fetch('/api/v1/external-health/retention/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        el.value = d.days || 90;
        $('ehPrunerLastAt').textContent = d.last_run ? d.last_run.replace('T',' ').slice(0,19) : 'nunca';
        $('ehPrunerLastDeleted').textContent = (d.last_deleted ?? 0).toLocaleString('pt-BR');
    }
    const btnEhSave = $('btnEhRetSave');
    if (btnEhSave) {
        btnEhSave.addEventListener('click', async () => {
            const days = parseInt($('ehRetDays').value || '90', 10);
            const r = await fetch('/api/v1/external-health/retention/settings', { method: 'PUT', headers: {...H, 'Content-Type':'application/json'}, body: JSON.stringify({days}) });
            (window.customAlert || alert)(r.ok ? t('js.saved') : t('js.error_generic'));
            loadRetention();
        });
        loadRetention();
    }

    refresh();
    setInterval(refresh, 60000);
})();
</script>

</body>
</html>
