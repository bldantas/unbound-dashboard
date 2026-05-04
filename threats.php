<?php
require_once 'src/Auth.php';

use App\Auth;

Auth::check();
if (!\App\Auth::isAdmin()) { header('Location: index.php'); exit; }

// Renderiza a estrutura inicial para exibir o loader enquanto as consultas rodam
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Ameaças - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
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
        $pageTitle = "Segurança & Ameaças";
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
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-2">Top Domínios Bloqueados (Judicial)</h3>

                    <div id="threatsTopDomains" class="flex-1 space-y-3">
                        <p class="text-slate-500 text-xs italic">Carregando top domínios...</p>
                    </div>
                </div>

                <!-- Top Blocked Clients -->
                <div class="glass-panel flex flex-col border-slate-200 dark:border-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-2">Clientes Mais Afetados</h3>

                    <div id="threatsTopClients" class="flex-1 space-y-3">
                        <p class="text-slate-500 text-xs italic">Carregando top clientes...</p>
                    </div>
                </div>
            </div>

            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Logs de Bloqueio em Tempo Real</h3>
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

        function renderTopList(containerId, items, emptyText, countClass) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = '<p class="text-slate-500 text-xs italic">' + escHtml(emptyText) + '</p>';
                return;
            }

            container.innerHTML = items.map(function (item) {
                const label = escHtml(item.label || '---');
                const count = fmtIntBr(item.count || 0);
                return '<div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">'
                    + '<span class="text-xs font-mono text-slate-700 dark:text-slate-300">' + label + '</span>'
                    + '<span class="text-xs font-black ' + countClass + '">' + count + '</span>'
                    + '</div>';
            }).join('');
        }

        function renderThreatRows(rows) {
            const tbody = document.getElementById('threatsRows');
            if (!tbody) return;

            if (!Array.isArray(rows) || rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs font-black tracking-widest uppercase">Nenhuma ameaça ativa detectada.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(function (t) {
                const time = escHtml(t.time || '--:--:--');
                const date = escHtml(t.date || '--/--/--');
                const ip = escHtml(t.client_ip || '---');
                const domain = escHtml(t.domain || '---');
                const category = escHtml(t.category || 'Geral');
                const highRisk = t.severity === 'high';

                return '<tr>'
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
        }

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
                        const r = await fetch('/api/v1/threats/data?limit=' + encodeURIComponent(limitVal), {
                            cache: 'no-store',
                            headers: { 'Authorization': 'Bearer ' + jwt },
                        });
                        if (r.ok) return await r.json();
                        // 401 (JWT expirou) ou outro: cai no fallback legado.
                    } catch (_) {
                        // Erro de rede: fallback.
                    }
                }
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

                renderTopList('threatsTopDomains', top.domains || [], 'Nenhum bloqueio judicial registrado recentemente.', 'text-blue-500 dark:text-blue-400');
                renderTopList('threatsTopClients', top.clients || [], 'Nenhum cliente bloqueado.', 'text-red-500');
                renderThreatRows(data.recent || []);

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
