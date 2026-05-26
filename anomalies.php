<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'anomalies.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Detector de Anomalias - Unbound DNS</title>
    <meta name="description" content="Detector heurístico (DGA, NXDOMAIN spike, novo cliente, DNS tunneling, beaconing, suspicious TLDs) + whitelist por cliente/domínio.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Detector de Anomalias";
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
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Limiares</h3>
                    <?php if (\App\Auth::isAdmin()): ?>
                        <button id="btnSaveSettings" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="thresholdsGrid"></div>
            </div>

            <!-- Detecções recentes -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">
                        Detecções (<span id="totalCount" class="text-indigo-500">—</span>)
                    </h3>
                    <div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-widest">
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
                                <th class="w-20">Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentBody">
                            <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>
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
        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center"><div class="w-6 h-6 mx-auto border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div></td></tr>`;
        try {
            const res = await fetch(`/api/v1/analytics/anomaly/recent?limit=100&include_resolved=${include}`, { headers: H });
            const data = await res.json();
            renderRecent(data.items || []);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</td></tr>`;
        }
    }

    const CATEGORY_INFO = {
        'anomaly_dga':              { label: 'DGA',              color: 'red' },
        'anomaly_nxdomain_spike':   { label: 'NXDOMAIN spike',   color: 'orange' },
        'anomaly_new_client':       { label: 'Novo cliente',     color: 'blue' },
        'anomaly_tunneling':        { label: 'Tunneling',        color: 'rose' },
        'anomaly_beacon':           { label: 'Beaconing',        color: 'purple' },
        'anomaly_suspicious_tld':   { label: 'Suspicious TLD',   color: 'amber' },
    };

    function renderRecent(items) {
        const tbody = document.getElementById('recentBody');
        document.getElementById('totalCount').textContent = items.length;
        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhuma detecção ${document.getElementById('includeResolved').checked ? '' : 'ativa'}</td></tr>`;
            return;
        }
        tbody.innerHTML = items.map(it => {
            const cat = CATEGORY_INFO[it.category] || { label: it.category, color: 'slate' };
            const d = it.started_at ? new Date(it.started_at) : null;
            const when = d ? (d.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'}) + ' ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit', second:'2-digit'})) : '—';
            const status = it.resolved_at
                ? `<span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Resolvida</span>`
                : `<span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${cat.color}-500/15 text-${cat.color}-600 dark:text-${cat.color}-400 border border-${cat.color}-500/30">Ativa</span>`;
            return `
            <tr>
                <td class="text-[11px] font-mono text-slate-500">${when}</td>
                <td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${cat.color}-500/15 text-${cat.color}-600 dark:text-${cat.color}-400 border border-${cat.color}-500/30">${cat.label}</span></td>
                <td class="text-xs">${escapeHtml(it.message)}</td>
                <td>${status}</td>
            </tr>`;
        }).join('');
    }

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
        // Não temos endpoint pra bulk-resolve; faço via individual update via alerts.php API se houver, ou chamo direto via DB. Simplificando — só recarrega após hint.
        toast('Use o painel de Alertas pra resolver individualmente, ou aguarde auto-resolução.', 'info');
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

    loadSettings();
    loadRecent();
    loadWhitelist();
})();
</script>

</body>
</html>
