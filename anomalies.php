<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'anomalies.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('anomalies.title') ?> - Unbound DNS</title>
    <meta name="description" content="Detector heurístico (DGA, NXDOMAIN spike, novo cliente, DNS tunneling, beaconing, suspicious TLDs) + whitelist por cliente/domínio.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('anomalies.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <div class="glass-panel border-l-4 mb-6 border-slate-200 dark:border-white/5" id="statusPanel">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest mb-1" id="statusLabel">Detector — Status</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white" id="statusText">Carregando...</p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            6 detectores heurísticos rodando a cada 5min sobre query_logs:
                            <b>DGA</b>, <b>NXDOMAIN spike</b>, <b>Cliente novo</b>,
                            <b>DNS tunneling</b> (subdomínios longos+entropia alta), <b>Beaconing</b>
                            (queries periódicas, baixo CV), <b>Suspicious TLDs</b>. Cada um pode ser ligado/desligado
                            individualmente. Detecções viram alertas em
                            <a href="alerts.php" class="text-orange-500 hover:underline font-bold">Alertas</a> +
                            webhooks. Whitelist no fim da página suprime falso-positivos conhecidos.
                        </p>
                    </div>
                    <?php if (\App\Auth::isAdmin()): ?>
                        <div class="flex flex-col items-end gap-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <span id="enabledLabel" class="text-[10px] font-black uppercase tracking-widest text-slate-500">Pausado</span>
                                <span class="relative inline-block w-11 h-6">
                                    <input type="checkbox" id="toggleEnabled" class="peer sr-only">
                                    <span class="block w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-colors"></span>
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <button id="btnRunNow" class="glass-btn !bg-indigo-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Rodar agora</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thresholds -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('anomalies.section_thresholds') ?></h3>
                    <?php if (\App\Auth::isAdmin()): ?>
                        <button id="btnSaveSettings" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="thresholdsGrid"></div>
            </div>

            <!-- Baseline ML (v2.79) -->
            <div class="glass-panel border-l-4 border-slate-200 dark:border-white/5 mb-6" id="baselinePanel">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest mb-1 text-indigo-500">Baseline ML — sazonalidade aprendida</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white" id="baselineStatusText">Carregando...</p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            BaselineLearner roda 1×/dia, agrega <code class="font-mono">hourly_stats</code> em
                            <b>168 buckets</b> (24h × 7d). Detector <b>baseline_deviation</b> compara última hora
                            completa contra o baseline do mesmo bucket e alerta se está fora de ±Nσ.
                            Captura padrões sazonais (ex: "8h de segunda" ≠ "8h de domingo").
                        </p>
                    </div>
                    <?php if (\App\Auth::isAdmin()): ?>
                        <div class="flex flex-col items-end gap-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <span id="baselineEnabledLabel" class="text-[10px] font-black uppercase tracking-widest text-slate-500">Pausado</span>
                                <span class="relative inline-block w-11 h-6">
                                    <input type="checkbox" id="toggleBaselineEnabled" class="peer sr-only">
                                    <span class="block w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-colors"></span>
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <button id="btnLearnNow" class="glass-btn !bg-indigo-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>Re-treinar agora</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Sensibilidade (σ)</label>
                        <input type="number" step="0.1" min="1" max="6" id="baselineSigma" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/40" <?= \App\Auth::isAdmin() ? '' : 'disabled' ?>>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">desvios padrão antes de alertar (3.0 = ~99.7%)</span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Janela treino</label>
                        <input type="number" min="1" max="52" id="baselineWeeks" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/40" <?= \App\Auth::isAdmin() ? '' : 'disabled' ?>>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">semanas históricas (default 4)</span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Mín amostras / bucket</label>
                        <input type="number" min="2" max="50" id="baselineMinSamples" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/40" <?= \App\Auth::isAdmin() ? '' : 'disabled' ?>>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">skipa buckets com menos amostras</span>
                    </div>
                    <div class="flex items-end">
                        <?php if (\App\Auth::isAdmin()): ?>
                            <button id="btnSaveBaseline" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black w-full">Salvar baseline</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Heatmap 24h × 7d -->
                <div class="overflow-x-auto">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Volume médio por bucket — intensidade da cor = avg_queries (clique em uma célula pra detalhes)
                    </div>
                    <div id="baselineHeatmap" class="text-[10px] font-mono inline-block">
                        <div class="px-4 py-6 text-center text-slate-500 italic">Carregando heatmap...</div>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-3 flex items-center gap-3 flex-wrap">
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-slate-200 dark:bg-slate-800"></span> sem amostras</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-indigo-300/60"></span> baixo</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-indigo-500"></span> médio</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-indigo-800"></span> alto</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm ring-2 ring-amber-500"></span> hora atual</span>
                    </p>
                </div>
            </div>

            <!-- Detecções recentes -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">
                        Detecções (<span id="totalCount" class="text-indigo-500">—</span>)
                    </h3>
                    <div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-widest flex-wrap">
                        <select id="filterCategory" class="glass-input text-[10px] !py-1">
                            <option value="">Todos os detectores</option>
                            <option value="anomaly_dga">DGA</option>
                            <option value="anomaly_nxdomain_spike">NXDOMAIN spike</option>
                            <option value="anomaly_new_client">Cliente novo</option>
                            <option value="anomaly_tunneling">Tunneling</option>
                            <option value="anomaly_beacon">Beaconing</option>
                            <option value="anomaly_suspicious_tld">Suspicious TLD</option>
                            <option value="anomaly_baseline_high">Baseline ↑</option>
                            <option value="anomaly_baseline_low">Baseline ↓</option>
                        </select>
                        <label class="inline-flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" id="includeResolved" class="rounded text-indigo-500">
                            <span class="text-slate-500">Incluir resolvidas</span>
                        </label>
                        <?php if (\App\Auth::isAdmin()): ?>
                            <button id="btnDismissAll" class="text-red-500 hover:text-red-700 uppercase font-black">Resolver todas</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th class="w-40">Quando</th>
                                <th class="w-32">Tipo</th>
                                <th>Detecção</th>
                                <th class="w-24">Status</th>
                                <th class="w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="recentBody">
                            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Whitelist (v2.55) -->
            <div class="glass-table-container border-slate-200 dark:border-white/5 mt-8">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">
                        Whitelist (<span id="whitelistCount" class="text-emerald-500">—</span>)
                    </h3>
                    <p class="text-[11px] text-slate-500">Suprime detecções por client_ip, domain (substring match) ou ambos. Detector vazio = todos.</p>
                </div>
                <?php if (\App\Auth::isAdmin()): ?>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-2 border-b border-slate-900/10 dark:border-white/5">
                    <select id="wlKind" class="glass-input text-xs">
                        <option value="client_ip">client_ip</option>
                        <option value="domain">domain</option>
                        <option value="client_and_domain">client_and_domain</option>
                    </select>
                    <input type="text" id="wlClient" placeholder="client_ip (ex: 192.168.1.10)" class="glass-input text-xs font-mono">
                    <input type="text" id="wlDomain" placeholder="domain pattern (substring, lowercase)" class="glass-input text-xs font-mono">
                    <select id="wlDetector" class="glass-input text-xs">
                        <option value="">todos os detectores</option>
                        <option value="dga">dga</option>
                        <option value="nxdomain">nxdomain</option>
                        <option value="new_client">new_client</option>
                        <option value="tunneling">tunneling</option>
                        <option value="beaconing">beaconing</option>
                        <option value="suspicious_tld">suspicious_tld</option>
                    </select>
                    <input type="text" id="wlNote" placeholder="nota (opcional)" class="glass-input text-xs">
                    <button id="btnAddWhitelist" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">+ Adicionar</button>
                </div>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th class="w-44">Kind</th>
                                <th class="w-40">Client IP</th>
                                <th>Domain pattern</th>
                                <th class="w-32">Detector</th>
                                <th>Nota</th>
                                <th class="w-20"></th>
                            </tr>
                        </thead>
                        <tbody id="whitelistBody">
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 text-xs italic">Carregando...</td></tr>
                        </tbody>
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
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const IS_ADMIN = <?= \App\Auth::isAdmin() ? 'true' : 'false' ?>;

    const THRESHOLD_LABELS = {
        'anomaly_dga_window_seconds':           { label: 'Janela',                       unit: 'segundos',         group: 'DGA' },
        'anomaly_dga_entropy_min':              { label: 'Entropia mín',                 unit: 'bits/char (3.5+)', group: 'DGA' },
        'anomaly_dga_min_length':               { label: 'Comprimento mín',              unit: 'chars',            group: 'DGA' },
        'anomaly_dga_min_count_per_client':     { label: 'Domínios mín por cliente',     unit: 'count',            group: 'DGA' },

        'anomaly_nxdomain_window_seconds':      { label: 'Janela',                       unit: 'segundos',         group: 'NXDOMAIN spike' },
        'anomaly_nxdomain_spike_ratio':         { label: 'Ratio (0.0-1.0)',              unit: 'fração',           group: 'NXDOMAIN spike' },
        'anomaly_nxdomain_spike_min_count':     { label: 'Mín count',                    unit: 'queries',          group: 'NXDOMAIN spike' },

        'anomaly_new_client_baseline_days':     { label: 'Baseline',                     unit: 'dias',             group: 'Cliente novo' },
        'anomaly_new_client_window_seconds':    { label: 'Janela',                       unit: 'segundos',         group: 'Cliente novo' },
        'anomaly_new_client_min_queries':       { label: 'Queries mín',                  unit: 'count',            group: 'Cliente novo' },

        'anomaly_tunneling_enabled':            { label: 'Ativo (0/1)',                  unit: 'bool',             group: 'DNS tunneling' },
        'anomaly_tunneling_window_seconds':     { label: 'Janela',                       unit: 'segundos',         group: 'DNS tunneling' },
        'anomaly_tunneling_min_unique_subdomains': { label: 'Subdomínios únicos mín',    unit: 'count por (cli, dom raiz)', group: 'DNS tunneling' },
        'anomaly_tunneling_min_avg_length':     { label: 'Comprimento médio mín',        unit: 'chars no label esquerdo',   group: 'DNS tunneling' },
        'anomaly_tunneling_min_avg_entropy':    { label: 'Entropia média mín',           unit: 'bits/char',        group: 'DNS tunneling' },

        'anomaly_beacon_enabled':               { label: 'Ativo (0/1)',                  unit: 'bool',             group: 'Beaconing' },
        'anomaly_beacon_window_seconds':        { label: 'Janela',                       unit: 'segundos',         group: 'Beaconing' },
        'anomaly_beacon_min_samples':           { label: 'Mín amostras',                 unit: 'queries por (cli, dom raiz)', group: 'Beaconing' },
        'anomaly_beacon_max_cv':                { label: 'CV máx (stddev/mean)',         unit: 'fração (0.20 ≈ regular)',     group: 'Beaconing' },
        'anomaly_beacon_min_period_seconds':    { label: 'Período mín',                  unit: 'segundos (ignora burst)',     group: 'Beaconing' },

        'anomaly_suspicious_tld_enabled':       { label: 'Ativo (0/1)',                  unit: 'bool',             group: 'Suspicious TLDs' },
        'anomaly_suspicious_tld_window_seconds':{ label: 'Janela',                       unit: 'segundos',         group: 'Suspicious TLDs' },
        'anomaly_suspicious_tld_min_count':     { label: 'Queries mín',                  unit: 'non-blocked por cliente', group: 'Suspicious TLDs' },
        'anomaly_suspicious_tld_list':          { label: 'Lista de TLDs',                unit: 'CSV (ex: .xyz,.top,.tk)', group: 'Suspicious TLDs' },
    };

    async function loadSettings() {
        try {
            const res = await fetch('/api/v1/analytics/anomaly/settings', { headers: H });
            const data = await res.json();
            const enabled = (data.settings.anomaly_enabled || '0') === '1';
            document.getElementById('toggleEnabled').checked = enabled;
            updateStatusPanel(enabled);
            renderThresholds(data.settings);
        } catch (err) { console.error('settings', err); }
    }

    function updateStatusPanel(enabled) {
        const panel = document.getElementById('statusPanel');
        const label = document.getElementById('statusLabel');
        const text = document.getElementById('statusText');
        const enabledLabel = document.getElementById('enabledLabel');
        if (enabled) {
            panel.classList.remove('border-amber-500');
            panel.classList.add('border-emerald-500');
            label.classList.remove('text-amber-600', 'dark:text-amber-400');
            label.classList.add('text-emerald-600', 'dark:text-emerald-400');
            label.textContent = 'Detector — ATIVO';
            text.textContent = 'Worker roda a cada 5 minutos.';
            if (enabledLabel) {
                enabledLabel.textContent = 'Ativo';
                enabledLabel.classList.remove('text-slate-500');
                enabledLabel.classList.add('text-emerald-600', 'dark:text-emerald-400');
            }
        } else {
            panel.classList.remove('border-emerald-500');
            panel.classList.add('border-amber-500');
            label.classList.remove('text-emerald-600', 'dark:text-emerald-400');
            label.classList.add('text-amber-600', 'dark:text-amber-400');
            label.textContent = 'Detector — PAUSADO';
            text.textContent = 'Ative pra começar a detectar (worker fica idle).';
            if (enabledLabel) {
                enabledLabel.textContent = 'Pausado';
                enabledLabel.classList.add('text-slate-500');
                enabledLabel.classList.remove('text-emerald-600', 'dark:text-emerald-400');
            }
        }
    }

    function renderThresholds(settings) {
        const grid = document.getElementById('thresholdsGrid');
        const groups = {};
        for (const [k, info] of Object.entries(THRESHOLD_LABELS)) {
            (groups[info.group] = groups[info.group] || []).push({ key: k, ...info });
        }
        let html = '';
        for (const [groupName, items] of Object.entries(groups)) {
            html += `<div class="space-y-2">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500">${groupName}</h4>`;
            items.forEach(it => {
                const v = settings[it.key] ?? '';
                html += `
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">${it.label}</label>
                    <input type="text" data-key="${it.key}" value="${escapeHtml(v)}" ${IS_ADMIN ? '' : 'disabled'}
                           class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">${it.unit}</span>
                </div>`;
            });
            html += `</div>`;
        }
        grid.innerHTML = html;
    }

    async function loadRecent() {
        const include = document.getElementById('includeResolved').checked;
        const tbody = document.getElementById('recentBody');
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center"><div class="w-6 h-6 mx-auto border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div></td></tr>`;
        try {
            const res = await fetch(`/api/v1/analytics/anomaly/recent?limit=100&include_resolved=${include}`, { headers: H });
            const data = await res.json();
            renderRecent(data.items || []);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</td></tr>`;
        }
    }

    const CATEGORY_INFO = {
        'anomaly_dga':              { label: 'DGA',              color: 'red' },
        'anomaly_nxdomain_spike':   { label: 'NXDOMAIN spike',   color: 'orange' },
        'anomaly_new_client':       { label: 'Novo cliente',     color: 'blue' },
        'anomaly_tunneling':        { label: 'Tunneling',        color: 'rose' },
        'anomaly_beacon':           { label: 'Beaconing',        color: 'purple' },
        'anomaly_suspicious_tld':   { label: 'Suspicious TLD',   color: 'amber' },
        'anomaly_baseline_high':    { label: 'Baseline ↑',       color: 'fuchsia' },
        'anomaly_baseline_low':     { label: 'Baseline ↓',       color: 'cyan' },
    };

    let _RECENT_ITEMS = [];

    function renderRecent(items) {
        _RECENT_ITEMS = items;
        const tbody = document.getElementById('recentBody');
        const filter = document.getElementById('filterCategory').value;
        const filtered = filter ? items.filter(it => it.category === filter) : items;
        document.getElementById('totalCount').textContent = filtered.length + (filter && items.length !== filtered.length ? ` / ${items.length}` : '');
        if (!filtered.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhuma detecção ${document.getElementById('includeResolved').checked ? '' : 'ativa'}${filter ? ' (filtro aplicado)' : ''}</td></tr>`;
            return;
        }
        tbody.innerHTML = filtered.map(it => {
            const cat = CATEGORY_INFO[it.category] || { label: it.category, color: 'slate' };
            const d = it.started_at ? new Date(it.started_at) : null;
            const when = d ? (d.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'}) + ' ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit', second:'2-digit'})) : '—';
            const status = it.resolved_at
                ? `<span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Resolvida</span>`
                : `<span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${cat.color}-500/15 text-${cat.color}-600 dark:text-${cat.color}-400 border border-${cat.color}-500/30">Ativa</span>`;
            const ackBtn = (IS_ADMIN && !it.resolved_at)
                ? `<button class="btn-ack text-emerald-500 hover:text-emerald-700 text-[10px] uppercase font-black tracking-widest" data-id="${it.id}" title="Marcar como resolvida">✓ Ack</button>`
                : '';
            return `
            <tr>
                <td class="text-[11px] font-mono text-slate-500">${when}</td>
                <td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${cat.color}-500/15 text-${cat.color}-600 dark:text-${cat.color}-400 border border-${cat.color}-500/30">${cat.label}</span></td>
                <td class="text-xs">${escapeHtml(it.message)}</td>
                <td>${status}</td>
                <td>${ackBtn}</td>
            </tr>`;
        }).join('');
        tbody.querySelectorAll('.btn-ack').forEach(b => {
            b.addEventListener('click', async (e) => {
                const id = e.target.dataset.id;
                e.target.disabled = true;
                try {
                    const r = await fetch('/api/v1/alerts/' + id + '/resolve', { method: 'POST', headers: H });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    toast('Detecção resolvida', 'success');
                    loadRecent();
                } catch (err) {
                    e.target.disabled = false;
                    toast('Falha: ' + err.message, 'error');
                }
            });
        });
    }

    document.getElementById('filterCategory').addEventListener('change', () => renderRecent(_RECENT_ITEMS));

    // ============ Handlers ============
    document.getElementById('toggleEnabled').addEventListener('change', async (e) => {
        try {
            await fetch('/api/v1/analytics/anomaly/settings', { method: 'PUT', headers: HJ, body: JSON.stringify({ anomaly_enabled: e.target.checked ? '1' : '0' }) });
            updateStatusPanel(e.target.checked);
            toast(`Detector ${e.target.checked ? 'ativado' : 'pausado'}`, 'success');
        } catch (err) {
            e.target.checked = !e.target.checked;
            toast('Falha: ' + err.message, 'error');
        }
    });

    document.getElementById('btnSaveSettings')?.addEventListener('click', async () => {
        const body = {};
        document.querySelectorAll('#thresholdsGrid input[data-key]').forEach(inp => {
            body[inp.dataset.key] = inp.value.trim();
        });
        try {
            const res = await fetch('/api/v1/analytics/anomaly/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            const data = await res.json();
            toast(`${data.updated} setting(s) atualizado(s)`, 'success');
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    document.getElementById('btnRunNow')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnRunNow');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Rodando...';
        try {
            const res = await fetch('/api/v1/analytics/anomaly/run-now', { method: 'POST', headers: H });
            const data = await res.json();
            toast(
                `${data.dga||0} DGA · ${data.nxdomain_spike||0} NXspike · ${data.new_clients||0} novos · `
                + `${data.tunneling||0} tunnel · ${data.beaconing||0} beacon · ${data.suspicious_tld||0} TLD`,
                'info'
            );
            await loadRecent();
            await loadWhitelist();  // re-renderiza caso whitelist tenha sido alterada externamente
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally { btn.disabled = false; span.textContent = orig; }
    });

    document.getElementById('includeResolved').addEventListener('change', loadRecent);

    document.getElementById('btnDismissAll')?.addEventListener('click', async () => {
        const ok = await customConfirm('Marcar todas detecções ativas como resolvidas?', 'Resolver todas?');
        if (!ok) return;
        try {
            const r = await fetch('/api/v1/analytics/anomaly/resolve-all', { method: 'POST', headers: H });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            toast(`${d.resolved || 0} detecção(ões) resolvidas`, 'success');
            loadRecent();
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function toast(msg, type = 'info') {
        if (window.AppUI?.toast) window.AppUI.toast(msg, type);
        else console.log('[' + type + ']', msg);
    }

    // ============ Whitelist (v2.55) ============
    async function loadWhitelist() {
        const tbody = document.getElementById('whitelistBody');
        try {
            const res = await fetch('/api/v1/analytics/anomaly/whitelist', { headers: H });
            const data = await res.json();
            const items = data.items || [];
            document.getElementById('whitelistCount').textContent = items.length;
            if (!items.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 text-xs italic">Sem entradas. Adicione acima pra suprimir false-positives.</td></tr>`;
                return;
            }
            tbody.innerHTML = items.map(it => `
                <tr>
                    <td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-slate-500/15 text-slate-600 dark:text-slate-400 border border-slate-500/30">${escapeHtml(it.kind)}</span></td>
                    <td class="font-mono text-xs">${escapeHtml(it.client_ip || '—')}</td>
                    <td class="font-mono text-xs">${escapeHtml(it.domain_pattern || '—')}</td>
                    <td class="text-xs">${escapeHtml(it.detector || 'todos')}</td>
                    <td class="text-xs text-slate-500">${escapeHtml(it.note || '')}</td>
                    <td>${IS_ADMIN ? `<button class="glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black btn-wl-del" data-id="${it.id}">×</button>` : ''}</td>
                </tr>
            `).join('');
            tbody.querySelectorAll('.btn-wl-del').forEach(b => {
                b.addEventListener('click', async (e) => {
                    const id = e.target.dataset.id;
                    const ok = await customConfirm('Remover essa entrada do whitelist?', 'Confirmar');
                    if (!ok) return;
                    try {
                        const r = await fetch('/api/v1/analytics/anomaly/whitelist/' + id, { method: 'DELETE', headers: H });
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        toast('Entrada removida', 'success');
                        loadWhitelist();
                    } catch (err) { toast('Falha: ' + err.message, 'error'); }
                });
            });
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-red-500 text-xs">Falha: ${err.message}</td></tr>`;
        }
    }

    document.getElementById('btnAddWhitelist')?.addEventListener('click', async () => {
        const body = {
            kind: document.getElementById('wlKind').value,
            client_ip: document.getElementById('wlClient').value.trim(),
            domain_pattern: document.getElementById('wlDomain').value.trim().toLowerCase(),
            detector: document.getElementById('wlDetector').value,
            note: document.getElementById('wlNote').value.trim(),
        };
        try {
            const r = await fetch('/api/v1/analytics/anomaly/whitelist', { method: 'POST', headers: HJ, body: JSON.stringify(body) });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error(d.error || 'HTTP ' + r.status);
            ['wlClient','wlDomain','wlNote'].forEach(id => document.getElementById(id).value = '');
            toast('Entrada adicionada', 'success');
            loadWhitelist();
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    // ============ Baseline ML (v2.79) ============
    const DOW_LABELS = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB']; // DuckDB DOW: 0=Sun..6=Sat

    function colorForAvg(avg, maxAvg) {
        if (!avg || maxAvg <= 0) return 'background-color: rgb(226 232 240 / 0.5)';
        const ratio = Math.min(1, avg / maxAvg);
        // gradiente indigo: claro → escuro
        const r = Math.round(199 - 120 * ratio);   // 199 → 79
        const g = Math.round(210 - 140 * ratio);   // 210 → 70
        const b = Math.round(254 - 25  * ratio);   // 254 → 229
        return `background-color: rgb(${r} ${g} ${b}); color: ${ratio > 0.5 ? 'white' : '#475569'};`;
    }

    async function loadBaseline() {
        try {
            const [bRes, cRes, sRes] = await Promise.all([
                fetch('/api/v1/analytics/anomaly/baseline', { headers: H }),
                fetch('/api/v1/analytics/anomaly/baseline/current', { headers: H }),
                fetch('/api/v1/analytics/anomaly/settings', { headers: H }),
            ]);
            const b = await bRes.json();
            const c = await cRes.json();
            const s = await sRes.json();

            // Inputs / toggle
            const enabled = (s.settings.anomaly_baseline_enabled || '0') === '1';
            document.getElementById('toggleBaselineEnabled').checked = enabled;
            updateBaselinePanel(enabled);
            document.getElementById('baselineSigma').value = s.settings.anomaly_baseline_sigma || '3.0';
            document.getElementById('baselineWeeks').value = s.settings.anomaly_baseline_window_weeks || '4';
            document.getElementById('baselineMinSamples').value = s.settings.anomaly_baseline_min_samples || '3';

            // Status text
            const lastRun = b.last_run ? new Date(b.last_run).toLocaleString('pt-BR') : 'nunca';
            const learnedCount = b.buckets_learned || (b.buckets || []).length;
            const txt = document.getElementById('baselineStatusText');
            if (learnedCount === 0) {
                txt.innerHTML = `<span class="text-amber-500">Sem baseline aprendido ainda.</span> Treina 1×/dia ou clique em "Re-treinar agora" se já tem dados.`;
            } else {
                txt.innerHTML = `Último treino: <b>${escapeHtml(lastRun)}</b> · <b>${learnedCount}/168</b> buckets com baseline`;
                if (c.current && c.baseline && c.deviation_sigma !== null) {
                    const dev = c.deviation_sigma.toFixed(2);
                    const sign = c.deviation_sigma >= 0 ? '+' : '';
                    const cls = Math.abs(c.deviation_sigma) >= c.sigma_threshold ? 'text-red-500' : 'text-emerald-500';
                    txt.innerHTML += ` · Hora atual: <span class="${cls} font-mono">${sign}${dev}σ</span> do baseline`;
                }
            }

            // Heatmap
            renderHeatmap(b.buckets || [], c);
        } catch (err) {
            console.error('baseline', err);
            document.getElementById('baselineStatusText').textContent = 'Falha: ' + err.message;
        }
    }

    function updateBaselinePanel(enabled) {
        const panel = document.getElementById('baselinePanel');
        const label = document.getElementById('baselineEnabledLabel');
        if (enabled) {
            panel.classList.remove('border-amber-500');
            panel.classList.add('border-emerald-500');
            if (label) { label.textContent = 'Ativo'; label.classList.remove('text-slate-500'); label.classList.add('text-emerald-600', 'dark:text-emerald-400'); }
        } else {
            panel.classList.remove('border-emerald-500');
            panel.classList.add('border-amber-500');
            if (label) { label.textContent = 'Pausado'; label.classList.add('text-slate-500'); label.classList.remove('text-emerald-600', 'dark:text-emerald-400'); }
        }
    }

    function renderHeatmap(buckets, current) {
        const container = document.getElementById('baselineHeatmap');
        // Mapa (dow, hod) → bucket
        const map = {};
        let maxAvg = 0;
        buckets.forEach(b => {
            map[b.day_of_week + ':' + b.hour_of_day] = b;
            if (b.avg_queries > maxAvg) maxAvg = b.avg_queries;
        });
        const curHod = current?.current?.hour_of_day;
        const curDow = current?.current?.day_of_week;

        let html = '<table class="border-collapse"><thead><tr><th class="px-1 py-1"></th>';
        for (let h = 0; h < 24; h++) {
            html += `<th class="px-1 py-1 text-[9px] font-bold text-slate-400">${String(h).padStart(2,'0')}</th>`;
        }
        html += '</tr></thead><tbody>';
        for (let d = 0; d < 7; d++) {
            html += `<tr><td class="px-2 py-1 text-[9px] font-black text-slate-400">${DOW_LABELS[d]}</td>`;
            for (let h = 0; h < 24; h++) {
                const b = map[d + ':' + h];
                const isCurrent = (d === curDow && h === curHod);
                const ring = isCurrent ? 'box-shadow: inset 0 0 0 2px rgb(245 158 11);' : '';
                if (b) {
                    const style = colorForAvg(b.avg_queries, maxAvg);
                    const tip = `${DOW_LABELS[d]} ${String(h).padStart(2,'0')}h · avg=${Math.round(b.avg_queries).toLocaleString('pt-BR')} qph · σ=${Math.round(b.stddev_queries)} · n=${b.sample_count}`;
                    html += `<td class="w-6 h-6 text-center cursor-pointer hover:opacity-80" style="${style}${ring}" title="${escapeHtml(tip)}">${Math.round(b.avg_queries/1000)>=1?Math.round(b.avg_queries/1000)+'k':Math.round(b.avg_queries)}</td>`;
                } else {
                    html += `<td class="w-6 h-6 bg-slate-200 dark:bg-slate-800/40" style="${ring}" title="${DOW_LABELS[d]} ${String(h).padStart(2,'0')}h · sem amostras">·</td>`;
                }
            }
            html += '</tr>';
        }
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    document.getElementById('toggleBaselineEnabled')?.addEventListener('change', async (e) => {
        try {
            await fetch('/api/v1/analytics/anomaly/settings', { method: 'PUT', headers: HJ, body: JSON.stringify({ anomaly_baseline_enabled: e.target.checked ? '1' : '0' }) });
            updateBaselinePanel(e.target.checked);
            toast(`Baseline ${e.target.checked ? 'ativado' : 'pausado'}`, 'success');
        } catch (err) {
            e.target.checked = !e.target.checked;
            toast('Falha: ' + err.message, 'error');
        }
    });

    document.getElementById('btnSaveBaseline')?.addEventListener('click', async () => {
        const body = {
            anomaly_baseline_sigma: document.getElementById('baselineSigma').value.trim(),
            anomaly_baseline_window_weeks: document.getElementById('baselineWeeks').value.trim(),
            anomaly_baseline_min_samples: document.getElementById('baselineMinSamples').value.trim(),
        };
        try {
            const res = await fetch('/api/v1/analytics/anomaly/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            const data = await res.json();
            toast(`${data.updated} setting(s) baseline atualizado(s)`, 'success');
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    document.getElementById('btnLearnNow')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnLearnNow');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Treinando...';
        try {
            const res = await fetch('/api/v1/analytics/anomaly/baseline/learn-now', { method: 'POST', headers: H });
            const data = await res.json();
            if (!res.ok || data.ok === false) throw new Error(data.error || ('HTTP ' + res.status));
            toast(`Baseline aprendido: ${data.learned || 0} buckets (${data.weeks || '?'}w)`, 'success');
            await loadBaseline();
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally { btn.disabled = false; span.textContent = orig; }
    });

    loadSettings();
    loadRecent();
    loadWhitelist();
    loadBaseline();
})();
</script>

</body>
</html>
