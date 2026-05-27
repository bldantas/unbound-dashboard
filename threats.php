<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';

use App\Auth;

Auth::check();
if (!\App\Auth::can('blocklist.read')) { header('Location: index.php'); exit; }

// Renderiza a estrutura inicial para exibir o loader enquanto as consultas rodam
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Ameaças - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js" defer></script>
    <style>
        /* override pra encaixar no tema escuro */
        #geoWorldMap .jvm-tooltip {
            background: rgba(15, 23, 42, 0.92);
            color: #f1f5f9;
            font-family: ui-monospace, monospace;
            font-size: 11px;
            border-radius: 8px;
            padding: 6px 10px;
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        #geoWorldMap svg { background: transparent; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">
<script>
(function(){
    var el = document.getElementById('global-page-loader');
    if (!el) {
        el = document.createElement('div');
        el.id = 'global-page-loader';
        el.setAttribute('aria-live','polite');
        el.setAttribute('aria-busy','true');
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando monitor de ameacas...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
        document.body.appendChild(el);
    }
    el.classList.add('is-visible');
})();
</script>
<?php
ob_end_flush();
if (function_exists('ob_flush')) {
    ob_flush();
}
flush();

$limitParam = $_GET['limit'] ?? '10';
if ($limitParam === 'todos') {
    $threatLimit = 1000;
} else {
    $allowedLimits = [10, 20, 50, 100];
    $parsedLimit = (int)$limitParam;
    $threatLimit = in_array($parsedLimit, $allowedLimits, true) ? $parsedLimit : 10;
}
$currentPage = 'threats.php';
?>

    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php 
        $pageTitle = t('threats.title');
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container">


            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <p class="metric-label">Domínios em Blacklist</p>
                    <div id="threatsTotalBlacklist" class="metric-value text-blue-400">--</div>
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Base de Dados Local</div>
                </div>
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <p class="metric-label">Bloqueios Efetuados</p>
                    <div id="threatsTotalThreats" class="metric-value text-red-500">--</div>
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Ameaças Interceptadas</div>
                </div>
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <p class="metric-label">Taxa de Ameaças</p>
                    <div id="threatsRatio" class="metric-value text-orange-400">--%</div>
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Relativo ao Tráfego Total</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Top Blocked Domains -->
                <div class="glass-panel flex flex-col border-slate-200 dark:border-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('threats.section_top_blocked') ?></h3>

                    <div id="threatsTopDomains" class="flex-1 space-y-3">
                        <p class="text-slate-500 text-xs italic">Carregando top domínios...</p>
                    </div>
                </div>

                <!-- Top Blocked Clients -->
                <div class="glass-panel flex flex-col border-slate-200 dark:border-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('threats.section_top_clients') ?></h3>

                    <div id="threatsTopClients" class="flex-1 space-y-3">
                        <p class="text-slate-500 text-xs italic">Carregando top clientes...</p>
                    </div>
                </div>
            </div>

            <!-- Top ASNs (provedores/redes) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('threats.section_top_asns') ?></h3>
                <div id="threatsTopAsns" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <p class="text-slate-500 text-xs italic col-span-full">Carregando ASNs...</p>
                </div>
            </div>

            <!-- Distribuição Global (mapa + top países) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-8">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-4 border-b border-slate-900/10 dark:border-white/5 pb-2">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('threats.section_global') ?></h3>
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Ação</label>
                        <select id="geoActionSelect" class="px-2 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-lg text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="blocked" selected>Bloqueios</option>
                            <option value="">Todas</option>
                            <option value="resolved">Resolvidas</option>
                            <option value="cached">Cache</option>
                            <option value="nxdomain_upstream">NXDOMAIN</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5 p-3 relative" style="min-height: 360px;">
                        <div id="geoWorldMap" style="height: 360px; width: 100%;"></div>
                        <div id="geoMapEmpty" class="hidden absolute inset-0 flex items-center justify-center pointer-events-none">
                            <p class="text-slate-500 text-xs italic">Sem dados pra esta janela.</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Top 15 países</p>
                        <div id="threatsTopCountries" class="grid grid-cols-1 gap-2 max-h-[360px] overflow-y-auto pr-1">
                            <p class="text-slate-500 text-xs italic">Carregando GeoIP...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar: busca + filtros + limit -->
            <div class="glass-panel mb-4 border-slate-200 dark:border-white/5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar (domínio ou IP)</label>
                        <input type="text" id="threatsSearch" oninput="filterThreats()" placeholder="ex: bet, casino, 192.168" class="glass-input w-full font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Categoria</label>
                        <select id="threatsCategory" onchange="filterThreats()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODAS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Severidade</label>
                        <select id="threatsSeverity" onchange="filterThreats()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODAS</option>
                            <option value="high">HIGH</option>
                            <option value="normal">NORMAL</option>
                        </select>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-3 flex items-center gap-2">
                    Total: <span id="threatsCountTotal">--</span> · Visíveis: <span id="threatsCountVisible">--</span>
                    <button type="button" id="threatsClearFilters" class="hidden ml-2 glass-btn !py-0.5 !px-2 text-[9px] uppercase tracking-widest">Limpar filtros</button>
                </p>
            </div>

            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('threats.section_live_blocks') ?></h3>
                    <form method="GET" class="flex items-center gap-2" data-loader="off">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Exibir:</label>
                        <select id="threatsLimit" name="limit" class="bg-slate-200 dark:bg-slate-900 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white text-[10px] font-black uppercase rounded-lg px-3 py-1 outline-none focus:ring-1 focus:ring-red-500">
                            <option value="10" <?= $threatLimit === 10 ? 'selected' : '' ?>>10 Linhas</option>
                            <option value="20" <?= $threatLimit === 20 ? 'selected' : '' ?>>20 Linhas</option>
                            <option value="50" <?= $threatLimit === 50 ? 'selected' : '' ?>>50 Linhas</option>
                            <option value="100" <?= $threatLimit === 100 ? 'selected' : '' ?>>100 Linhas</option>
                            <option value="todos" <?= $limitParam === 'todos' ? 'selected' : '' ?>>Todos (1000)</option>
                        </select>
                    </form>
                </div>

                <table class="glass-table">
                    <thead>
                        <tr>
                            <th class="w-32">Data / Hora</th>
                            <th>IP Solicitante</th>
                            <th>Domínio Acessado</th>
                            <th class="w-40">Categoria / Risco</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="threatsRows">
                        <tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs font-black tracking-widest uppercase">Carregando ameaças...</td></tr>
                    </tbody>
                </table>
                <p id="threatsEmptyFiltered" class="hidden text-center text-slate-500 text-sm py-8 px-4">Nenhuma linha atende aos filtros selecionados.</p>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>
    <script>
        function fmtIntBr(value) {
            return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
        }

        function escHtml(input) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(input == null ? '' : input).replace(/[&<>"']/g, function (m) { return map[m]; });
        }

        function renderTopList(containerId, items, emptyText, countClass, filterTarget) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = '<p class="text-slate-500 text-xs italic">' + escHtml(emptyText) + '</p>';
                return;
            }

            container.innerHTML = items.map(function (item) {
                const label = escHtml(item.label || '---');
                const count = fmtIntBr(item.count || 0);
                // filterTarget: 'domain' aplica filtro de busca na tabela; 'client_ip' idem.
                // Clique no chip filtra a tabela abaixo — UX cross-link interno.
                const dataAttr = filterTarget ? ' data-filter-target="' + filterTarget + '" data-filter-value="' + escHtml(item.label || '') + '"' : '';
                const interact = filterTarget ? ' cursor-pointer hover:bg-orange-500/10 transition' : '';
                return '<div class="threat-top-item flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5' + interact + '"' + dataAttr + '>'
                    + '<span class="text-xs font-mono text-slate-700 dark:text-slate-300">' + label + '</span>'
                    + '<span class="text-xs font-black ' + countClass + '">' + count + '</span>'
                    + '</div>';
            }).join('');

            // Anexa handler de clique pros chips filtráveis.
            //
            // Tops são calculados sobre TODO o histórico de blocked (sem cap), mas a
            // tabela "recent" abaixo só tem `limit` linhas (default 10). Um IP/domínio
            // do top pode não ter NENHUMA das últimas 10 linhas — clicar nele filtraria
            // pra vazio. Solução: ao clicar no chip, força limit=todos (1000) e re-fetch
            // antes de aplicar o filtro client-side. Garante que IPv6 e domínios com
            // bloqueios antigos apareçam.
            container.querySelectorAll('.threat-top-item[data-filter-target]').forEach(function (el) {
                el.addEventListener('click', async function () {
                    const searchEl = document.getElementById('threatsSearch');
                    const limitSel = document.getElementById('threatsLimit');
                    const target = el.getAttribute('data-filter-value') || '';
                    const which = el.getAttribute('data-filter-target') || '';

                    // Server-side: pede ao backend exatamente as linhas desse IP/domínio.
                    // Resolve o caso IPv6 / domínio do top que não aparecia na cauda recente.
                    __serverFilter = { client_ip: '', domain: '' };
                    if (which === 'client_ip') __serverFilter.client_ip = target;
                    else if (which === 'domain') __serverFilter.domain = target;

                    if (searchEl) searchEl.value = target;
                    if (limitSel) limitSel.value = 'todos';
                    await loadThreatsData();
                    if (searchEl) searchEl.focus();
                });
            });
        }

        // Estado de payload guardado pra refilter sem re-fetch
        let __threatsCurrentRows = [];

        function renderThreatRows(rows) {
            const tbody = document.getElementById('threatsRows');
            if (!tbody) return;
            __threatsCurrentRows = Array.isArray(rows) ? rows : [];

            if (__threatsCurrentRows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs font-black tracking-widest uppercase">Nenhuma ameaça ativa detectada.</td></tr>';
                document.getElementById('threatsCountTotal').textContent = '0';
                document.getElementById('threatsCountVisible').textContent = '0';
                return;
            }

            tbody.innerHTML = __threatsCurrentRows.map(function (t) {
                const time = escHtml(t.time || '--:--:--');
                const date = escHtml(t.date || '--/--/--');
                const ip = escHtml(t.client_ip || '---');
                const domain = escHtml(t.domain || '---');
                const category = escHtml(t.category || 'Geral');
                const severity = String(t.severity || 'normal').toLowerCase();
                const highRisk = severity === 'high';

                // data-attrs pra filtragem client-side
                return '<tr class="threat-row" data-domain="' + escHtml(String(t.domain || '').toLowerCase()) + '"'
                    + ' data-ip="' + escHtml(String(t.client_ip || '').toLowerCase()) + '"'
                    + ' data-category="' + escHtml(String(t.category || '')) + '"'
                    + ' data-severity="' + escHtml(severity) + '">'
                    + '<td class="px-6 py-4"><div class="text-[10px] font-mono text-slate-500">' + time + '</div><div class="text-[9px] text-slate-600">' + date + '</div></td>'
                    + '<td class="px-6 py-4 font-mono text-xs text-blue-500 dark:text-blue-400">' + ip + '</td>'
                    + '<td class="px-6 py-4 font-bold text-slate-900 dark:text-white text-xs">' + domain + '</td>'
                    + '<td><div class="flex flex-col gap-1">'
                    + '<span class="text-[9px] font-black text-red-400 uppercase tracking-widest px-2 py-0.5 bg-red-500/10 rounded-full w-fit border border-red-500/20">' + category + '</span>'
                    + (highRisk ? '<span class="text-[8px] font-black text-red-600 uppercase tracking-tighter ml-1">RISCO CRÍTICO</span>' : '')
                    + '</div></td>'
                    + '<td class="text-right"><span class="text-[10px] font-black text-red-500 bg-red-950/40 px-3 py-1.5 rounded-xl border border-red-500/20 uppercase tracking-widest">BLOCKED</span></td>'
                    + '</tr>';
            }).join('');

            // Atualiza dropdown de categorias com os valores distintos do payload atual
            populateCategoryDropdown(__threatsCurrentRows);

            // Reaplica filtros (mantém estado entre fetches)
            filterThreats();
        }

        function populateCategoryDropdown(rows) {
            const sel = document.getElementById('threatsCategory');
            if (!sel) return;
            const current = sel.value;
            const categories = Array.from(new Set(rows.map(r => String(r.category || '')).filter(Boolean))).sort();
            sel.innerHTML = '<option value="">TODAS</option>' +
                categories.map(c => '<option value="' + escHtml(c) + '"' + (c === current ? ' selected' : '') + '>' + escHtml(c.toUpperCase()) + '</option>').join('');
        }

        function filterThreats() {
            const q = (document.getElementById('threatsSearch').value || '').trim().toLowerCase();
            const cat = document.getElementById('threatsCategory').value;
            const sev = document.getElementById('threatsSeverity').value;
            const rows = document.querySelectorAll('.threat-row');
            let visible = 0;
            rows.forEach(function (row) {
                const matchQ = !q || row.dataset.domain.includes(q) || row.dataset.ip.includes(q);
                const matchCat = !cat || row.dataset.category === cat;
                const matchSev = !sev || row.dataset.severity === sev;
                const show = matchQ && matchCat && matchSev;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const total = rows.length;
            document.getElementById('threatsCountTotal').textContent = String(total);
            document.getElementById('threatsCountVisible').textContent = String(visible);
            document.getElementById('threatsEmptyFiltered').classList.toggle('hidden', visible !== 0 || total === 0);

            const anyFilter = !!(q || cat || sev);
            const clearBtn = document.getElementById('threatsClearFilters');
            if (clearBtn) clearBtn.classList.toggle('hidden', !anyFilter);
        }

        // Botão "Limpar filtros"
        document.addEventListener('DOMContentLoaded', function () {
            const clearBtn = document.getElementById('threatsClearFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', async function () {
                    document.getElementById('threatsSearch').value = '';
                    document.getElementById('threatsCategory').value = '';
                    document.getElementById('threatsSeverity').value = '';
                    // Se havia filtro server-side de chip, reseta e re-fetch sem filtro.
                    if (__serverFilter.client_ip || __serverFilter.domain) {
                        __serverFilter = { client_ip: '', domain: '' };
                        await loadThreatsData();
                    } else {
                        filterThreats();
                    }
                });
            }
        });

        // Filtros server-side ativos (setados ao clicar num chip do Top).
        // O backend só usa esses pra montar a tabela `recent`; o "Top" continua
        // sendo histórico cumulativo (independe do filtro).
        let __serverFilter = { client_ip: '', domain: '' };

        // Converte country code (ex: "BR") em emoji bandeira via regional indicator letters
        function ccToFlag(cc) {
            if (!cc || cc.length !== 2 || /[^A-Z]/i.test(cc)) return '🏳️';
            const codePoints = cc.toUpperCase().split('').map(c => 127397 + c.charCodeAt(0));
            return String.fromCodePoint(...codePoints);
        }

        let __geoMapInstance = null;

        function ensureWorldMap() {
            // jsvectormap pode carregar depois do DOMContentLoaded por causa do defer.
            // Idempotente — só cria uma vez. Retorna a instância (ou null se lib não pronta).
            if (__geoMapInstance) return __geoMapInstance;
            if (typeof jsVectorMap === 'undefined') return null;
            const el = document.getElementById('geoWorldMap');
            if (!el) return null;
            try {
                __geoMapInstance = new jsVectorMap({
                    selector: '#geoWorldMap',
                    map: 'world',
                    backgroundColor: 'transparent',
                    zoomOnScroll: false,
                    zoomButtons: true,
                    regionStyle: {
                        initial: { fill: 'rgba(148, 163, 184, 0.18)', stroke: 'rgba(148, 163, 184, 0.35)', strokeWidth: 0.4 },
                        hover: { fill: 'rgba(34, 211, 238, 0.55)', cursor: 'pointer' },
                    },
                    series: {
                        regions: [{
                            scale: ['#94a3b8', '#06b6d4', '#0e7490', '#7c3aed', '#db2777'],
                            normalizeFunction: 'polynomial',
                            values: {},
                        }],
                    },
                    onRegionTooltipShow(_e, tooltip, code) {
                        const v = __geoMapValues[code];
                        const name = tooltip.text();
                        if (v == null) { tooltip.text(name + ' — sem dados'); return; }
                        tooltip.text(name + ' — ' + fmtIntBr(v) + ' hits');
                    },
                });
            } catch (err) {
                console.warn('jsvectormap init failed', err);
                return null;
            }
            return __geoMapInstance;
        }

        let __geoMapValues = {};

        function applyMapValues(values, attempt) {
            const MAX_ATTEMPTS = 25;  // ~5s total (200ms × 25)
            const m = ensureWorldMap();
            if (m) {
                try {
                    m.series.regions[0].clear();
                } catch (_) { /* clear pode não existir em versões antigas */ }
                m.series.regions[0].setValues(values);
                return;
            }
            if (attempt >= MAX_ATTEMPTS) {
                const emptyEl = document.getElementById('geoMapEmpty');
                if (emptyEl) {
                    emptyEl.classList.remove('hidden');
                    emptyEl.innerHTML = '<p class="text-amber-500 text-xs italic">Falha ao carregar mapa (jsvectormap não disponível — verifique conexão com cdn.jsdelivr.net).</p>';
                }
                console.warn('[threats] jsvectormap não carregou após', MAX_ATTEMPTS, 'tentativas');
                return;
            }
            setTimeout(() => applyMapValues(values, attempt + 1), 200);
        }

        async function loadTopCountries() {
            const container = document.getElementById('threatsTopCountries');
            const emptyEl = document.getElementById('geoMapEmpty');
            if (!container) return;
            const actionSel = document.getElementById('geoActionSelect');
            const action = actionSel ? actionSel.value : 'blocked';
            try {
                const meta = document.querySelector('meta[name="api-jwt"]');
                const jwt = meta ? meta.content : '';
                if (!jwt) { container.innerHTML = '<p class="text-slate-500 text-xs italic">GeoIP requer login JWT.</p>'; return; }
                const params = new URLSearchParams({ hours: '24', limit: '15' });
                if (action) params.set('action', action);
                const r = await fetch('/api/v1/geoip/distribution?' + params.toString(), {
                    headers: { 'Authorization': 'Bearer ' + jwt },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const items = d.countries || [];

                if (!items.length) {
                    container.innerHTML = '<p class="text-slate-500 text-xs italic">Sem dados pra esta ação nas últimas 24h.</p>';
                    if (emptyEl) emptyEl.classList.remove('hidden');
                    __geoMapValues = {};
                    const m = ensureWorldMap();
                    if (m) m.series.regions[0].setValues({});
                    return;
                }
                if (emptyEl) emptyEl.classList.add('hidden');

                // Lista lateral
                container.innerHTML = items.map(c => {
                    const flag = c.country_code === '--' ? '🏠' : (c.country_code === '??' ? '❓' : ccToFlag(c.country_code));
                    return '<div class="bg-slate-900/5 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/5 p-2 flex items-center gap-2">'
                        + '<span class="text-xl">' + flag + '</span>'
                        + '<div class="min-w-0 flex-1">'
                        + '<p class="text-xs font-bold text-slate-900 dark:text-white truncate">' + escHtml(c.country_name) + '</p>'
                        + '<p class="text-[10px] text-slate-500 font-mono">' + fmtIntBr(c.hits) + ' · ' + fmtIntBr(c.clients) + ' cli</p>'
                        + '</div></div>';
                }).join('');

                // Mapa: só usa ISO-2 válidos (descarta '--' e '??')
                const values = {};
                items.forEach(c => {
                    if (c.country_code && c.country_code.length === 2 && /^[A-Z]{2}$/.test(c.country_code)) {
                        values[c.country_code] = c.hits;
                    }
                });
                __geoMapValues = values;
                applyMapValues(values, 0);
            } catch (e) {
                container.innerHTML = '<p class="text-slate-500 text-xs italic">Falha ao carregar GeoIP.</p>';
            }
        }

        async function loadTopAsns() {
            const container = document.getElementById('threatsTopAsns');
            if (!container) return;
            try {
                const meta = document.querySelector('meta[name="api-jwt"]');
                const jwt = meta ? meta.content : '';
                if (!jwt) {
                    container.innerHTML = '<p class="text-slate-500 text-xs italic col-span-full">ASNs requerem login JWT.</p>';
                    return;
                }
                const r = await fetch('/api/v1/geoip/top-asns?hours=24&limit=12&action=blocked', {
                    headers: { 'Authorization': 'Bearer ' + jwt },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const items = d.asns || [];
                if (!items.length) {
                    container.innerHTML = '<p class="text-slate-500 text-xs italic col-span-full">Sem ASNs identificados nas últimas 24h.</p>';
                    return;
                }
                container.innerHTML = items.map(a => {
                    const flag = a.country_code === '--' ? '🏠' : (a.country_code === '??' ? '❓' : ccToFlag(a.country_code));
                    const asn = (a.asn || '—').replace(/^AS/i, '');
                    return '<div class="bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5 p-3 flex items-center gap-3">'
                        + '<span class="text-2xl">' + flag + '</span>'
                        + '<div class="min-w-0 flex-1">'
                        + '<p class="text-xs font-bold text-slate-900 dark:text-white truncate" title="' + escHtml(a.asn_name) + '">' + escHtml(a.asn_name || '(sem ASN)') + '</p>'
                        + '<p class="text-[10px] text-slate-500 font-mono">AS' + escHtml(asn) + ' · ' + fmtIntBr(a.hits) + ' hits · ' + fmtIntBr(a.clients) + ' clientes</p>'
                        + '</div></div>';
                }).join('');
            } catch (e) {
                container.innerHTML = '<p class="text-slate-500 text-xs italic col-span-full">Falha ao carregar ASNs.</p>';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const sel = document.getElementById('geoActionSelect');
            if (sel) sel.addEventListener('change', loadTopCountries);
        });

        async function loadThreatsData() {
            const limitSelect = document.getElementById('threatsLimit');
            const limit = limitSelect ? limitSelect.value : '10';

            // Strangler Fig: tenta a nova FastAPI primeiro (DuckDB live), cai no PHP legado
            // se JWT ausente, expirado ou FastAPI inacessível. Permite cutover gradual sem
            // quebrar a página caso o api_service tenha qualquer hiccup.
            async function fetchThreatsData(limitVal) {
                const meta = document.querySelector('meta[name="api-jwt"]');
                const jwt = meta ? meta.content : '';
                if (jwt) {
                    try {
                        const params = new URLSearchParams({ limit: limitVal });
                        if (__serverFilter.client_ip) params.set('client_ip', __serverFilter.client_ip);
                        if (__serverFilter.domain) params.set('domain', __serverFilter.domain);
                        const r = await fetch('/api/v1/threats/data?' + params.toString(), {
                            cache: 'no-store',
                            headers: { 'Authorization': 'Bearer ' + jwt },
                        });
                        if (r.ok) return await r.json();
                        // 401 (JWT expirou) ou outro: cai no fallback legado.
                    } catch (_) {
                        // Erro de rede: fallback.
                    }
                }
                // Fallback PHP — não suporta filtros server-side, retorna tudo recente.
                const r2 = await fetch('api/threats_data.php?limit=' + encodeURIComponent(limitVal), { cache: 'no-store' });
                if (!r2.ok) throw new Error('Falha HTTP ' + r2.status);
                return await r2.json();
            }

            try {
                const payload = await fetchThreatsData(limit);
                if (!payload || payload.status !== 'success' || !payload.data) {
                    throw new Error(payload && payload.error ? payload.error : 'Resposta inválida');
                }

                const data = payload.data;
                const totals = data.totals || {};
                const top = data.top || {};

                const totalBlacklistEl = document.getElementById('threatsTotalBlacklist');
                const totalThreatsEl = document.getElementById('threatsTotalThreats');
                const ratioEl = document.getElementById('threatsRatio');

                if (totalBlacklistEl) totalBlacklistEl.textContent = fmtIntBr(totals.blacklist || 0);
                if (totalThreatsEl) totalThreatsEl.textContent = fmtIntBr(totals.threats || 0);
                if (ratioEl) ratioEl.textContent = Number(totals.ratio || 0).toFixed(2) + '%';

                renderTopList('threatsTopDomains', top.domains || [], 'Nenhum bloqueio judicial registrado recentemente.', 'text-blue-500 dark:text-blue-400', 'domain');
                renderTopList('threatsTopClients', top.clients || [], 'Nenhum cliente bloqueado.', 'text-red-500', 'client_ip');
                renderThreatRows(data.recent || []);
                loadTopCountries();
                loadTopAsns();

                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('limit', limit);
                window.history.replaceState({}, '', nextUrl.toString());
            } catch (err) {
                renderThreatRows([]);
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('Falha ao carregar métricas de ameaças.', 'warning', { title: 'Ameaças' });
                }
            } finally {
                var loader = document.getElementById('global-page-loader');
                if (loader) loader.classList.remove('is-visible');
            }
        }

        const threatsLimit = document.getElementById('threatsLimit');
        if (threatsLimit) {
            threatsLimit.addEventListener('change', function () {
                loadThreatsData();
            });
        }

        window.addEventListener('load', function () {
            loadThreatsData();
        });
    </script>
</body>
</html>
