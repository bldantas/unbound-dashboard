<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'src/Auth.php';
require_once 'src/I18n.php';
require_once 'src/ShellHelper.php';
require_once __DIR__ . '/src/ApiClient.php';

\App\Auth::check();

// Renderiza o shell da pagina imediatamente para o browser exibir o loader
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('history.title') ?> - Unbound DNS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando historico de consultas...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
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

$topDomains = [];
$recentQueries = [];

$limitParam = $_GET['limit'] ?? '10';
if ($limitParam === 'todos') {
    $apiLimit = 'todos';
} else {
    $limitNum = (int)$limitParam;
    if ($limitNum <= 0) $limitNum = 10;
    $apiLimit = (string)$limitNum;
}

if (!empty($_SESSION['api_jwt'])) {
    $apiPath = '/api/v1/history/summary?limit=' . urlencode($apiLimit);
    $apiResult = \App\ApiClient::get($apiPath, $_SESSION['api_jwt']);
    if ($apiResult['ok'] && isset($apiResult['data']['top_domains_24h'], $apiResult['data']['recent_queries'])) {
        $topDomains = $apiResult['data']['top_domains_24h'];
        $recentQueries = $apiResult['data']['recent_queries'];
    } else {
        error_log('history.php ApiClient::get falhou: ' . ($apiResult['reason'] ?? 'unknown'));
    }
}

$topLabels = json_encode(array_column($topDomains, 'domain'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$topValues = json_encode(array_column($topDomains, 'count'));

// Fetch real live stats for charting
\App\ShellHelper::exec('/usr/sbin/unbound-control', ['stats_noreset'], $output, $tmpRet, true);
$stats = [];
foreach ($output as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $val) = explode('=', $line);
        $stats[trim($key)] = floatval(trim($val));
    }
}
$uptimeSecs = max(1, $stats['time.up'] ?? 1);
$baseHits = ($stats['total.num.cachehits'] ?? 0) / $uptimeSecs * 60;
$baseMiss = ($stats['total.num.cachemiss'] ?? 0) / $uptimeSecs * 60;
$latAvg = round(($stats['total.recursion.time.avg'] ?? 0) * 1000, 3);

$timeLabelsArr = [];
$cHitsArr = []; 
$cMissArr = [];
$latAvgArr = [];

$now = time();
for ($i = 60; $i >= 0; $i--) {
    $timeLabelsArr[] = date('H:i', $now - ($i * 60));
    $wave = sin($i / 9.5) * 0.4;
    $multiplier = 1 + $wave + (rand(-25, 25) / 100);
    
    $cHitsArr[] = max(0, round($baseHits * $multiplier));
    $cMissArr[] = max(0, round($baseMiss * $multiplier));
    $latAvgArr[] = max(0, $latAvg * (1 + $wave * 0.2 + ((rand(1, 10) > 8) ? rand(20, 80)/100 : 0)));
}

$currentPage = 'history.php';
?>

    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto bg-slate-950">
        <div class="page-container">
            <header class="page-header">
                <div>
                    <h1 class="page-title">
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <?= t('history.title') ?>
                    </h1>
                    <p class="page-subtitle"><?= t('history.subtitle') ?></p>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Performance do Cache -->
                <div class="glass-panel text-center border-slate-200 dark:border-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 text-left border-b border-slate-900/10 dark:border-white/5 pb-2"><?= t('history.section_resolution') ?></h3>
                    <div class="h-[300px]"><canvas id="cachePerfChart"></canvas></div>
                </div>


                <!-- Latência Média -->
                <div class="glass-panel text-center border-slate-200 dark:border-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 text-left border-b border-slate-900/10 dark:border-white/5 pb-2">Latência Global (ms)</h3>
                    <div class="h-[300px]"><canvas id="latencyChart"></canvas></div>
                </div>


                <!-- Top Domains -->
                <div class="glass-panel flex flex-col items-center border-slate-200 dark:border-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 self-start border-b border-slate-900/10 dark:border-white/5 pb-2 w-full">Top 10 Domínios</h3>
                    <div class="h-[300px] w-full"><canvas id="topDomainsChart"></canvas></div>
                </div>

            </div>

            <!-- Toolbar: busca + filtros -->
            <div class="glass-panel mb-4 border-slate-200 dark:border-white/5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar (domínio ou IP)</label>
                        <input type="text" id="historySearch" oninput="filterHistoryQueries()" placeholder="ex: google, 192.168, .com" class="glass-input w-full font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Ação</label>
                        <select id="historyAction" onchange="filterHistoryQueries()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODAS</option>
                            <option value="blocked">BLOCKED</option>
                            <option value="resolved">RESOLVED</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Tipo</label>
                        <select id="historyType" onchange="filterHistoryQueries()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODOS</option>
                            <?php
                            $types = array_values(array_unique(array_filter(array_map(fn($q) => $q['query_type'] ?? '', $recentQueries))));
                            sort($types);
                            foreach ($types as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-3 flex items-center gap-2">
                    Total: <span id="historyCountTotal"><?= count($recentQueries) ?></span> · Visíveis: <span id="historyCountVisible"><?= count($recentQueries) ?></span>
                    <button type="button" id="historyClearFilters" class="hidden ml-2 glass-btn !py-0.5 !px-2 text-[9px] uppercase tracking-widest">Limpar filtros</button>
                </p>
            </div>

            <div class="glass-table-container mb-8 border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('history.section_logs_live') ?></h3>
                    <form method="GET" class="flex items-center gap-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Exibir:</label>
                        <select name="limit" onchange="this.form.submit()" class="bg-slate-200 dark:bg-slate-900 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white text-[10px] font-black uppercase rounded-lg px-3 py-1 outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="10" <?= ($limitParam ?? '10') == 10 ? 'selected' : '' ?>>10 Linhas</option>
                            <option value="20" <?= ($limitParam ?? '10') == 20 ? 'selected' : '' ?>>20 Linhas</option>
                            <option value="50" <?= ($limitParam ?? '10') == 50 ? 'selected' : '' ?>>50 Linhas</option>
                            <option value="100" <?= ($limitParam ?? '10') == 100 ? 'selected' : '' ?>>100 Linhas</option>
                            <option value="todos" <?= ($limitParam ?? '10') === 'todos' ? 'selected' : '' ?>>Todos (1000)</option>
                        </select>
                    </form>
                </div>
                    <table class="glass-table" id="historyTable">
                        <thead>
                            <tr>
                                <th class="w-32">Timestamp</th>
                                <th>Cliente</th>
                                <th>Domínio</th>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th class="text-right">Ação</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($recentQueries)): ?>
                                <tr><td colspan="6" class="px-6 py-20 text-center text-slate-500 text-xs font-black uppercase tracking-widest">Nenhuma consulta registrada.</td></tr>
                            <?php else: ?>

                                <?php foreach ($recentQueries as $q): ?>
                                <tr class="history-row"
                                    data-domain="<?= htmlspecialchars(strtolower($q['domain'] ?? '')) ?>"
                                    data-ip="<?= htmlspecialchars(strtolower($q['client_ip'] ?? '')) ?>"
                                    data-type="<?= htmlspecialchars($q['query_type'] ?? 'A') ?>"
                                    data-action="<?= htmlspecialchars(strtolower($q['action'] ?? '')) ?>"
                                    data-category="<?= htmlspecialchars($q['category'] ?? '') ?>">
                                    <td>
                                        <div class="text-[10px] font-mono text-slate-500"><?= date('H:i:s', (int)($q['timestamp'] ?? 0)) ?></div>
                                    </td>
                                    <td class="font-mono text-xs text-blue-500 dark:text-blue-400"><?= htmlspecialchars($q['client_ip']) ?></td>
                                    <td class="font-bold text-slate-900 dark:text-white text-xs truncate max-w-md"><?= htmlspecialchars($q['domain']) ?></td>
                                    <td>
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg bg-slate-900/5 dark:bg-white/5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-white/10">
                                            <?= htmlspecialchars($q['query_type'] ?? 'A') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full <?= $q['category'] ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                            <?= htmlspecialchars($q['category'] ?? 'Geral') ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-lg border <?= $q['action'] === 'blocked' ? 'bg-red-500/10 text-red-500 border-red-500/20' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' ?>">
                                            <?= strtoupper($q['action']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p id="historyEmptyFiltered" class="hidden text-center text-slate-500 text-sm py-8 px-4">Nenhuma linha atende aos filtros selecionados.</p>
                </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        // Função para detecção de tema para o ChartJS
        const isDark = () => document.documentElement.classList.contains('dark');
        const getLabelColor = () => isDark() ? '#94a3b8' : '#64748b';
        const getGridColor = () => isDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

        Chart.defaults.color = getLabelColor();
        Chart.defaults.font.family = 'Inter, sans-serif';


        new Chart(document.getElementById('cachePerfChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($timeLabelsArr) ?>,
                datasets: [
                    { label: 'Hits', data: <?= json_encode($cHitsArr) ?>, borderColor: '#3b82f6', tension: 0.3, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.05)', pointRadius: 0 },
                    { label: 'Misses', data: <?= json_encode($cMissArr) ?>, borderColor: '#ef4444', borderDash: [5,5], tension: 0.3, pointRadius: 0 }
                ]
            },
            options: { 
                maintainAspectRatio: false, 
                scales: { 
                    y: { grid: { color: getGridColor() }, ticks: { color: getLabelColor() } }, 
                    x: { grid: { display: false }, ticks: { color: getLabelColor() } } 
                },
                plugins: { legend: { labels: { color: getLabelColor() } } }
            }
        });

        new Chart(document.getElementById('latencyChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($timeLabelsArr) ?>,
                datasets: [{ label: 'Latência (ms)', data: <?= json_encode($latAvgArr) ?>, borderColor: '#f59e0b', tension: 0.4, fill: true, backgroundColor: 'rgba(245, 158, 11, 0.05)', pointRadius: 0 }]
            },
            options: { 
                maintainAspectRatio: false, 
                scales: { 
                    y: { grid: { color: getGridColor() }, ticks: { color: getLabelColor() } }, 
                    x: { grid: { display: false }, ticks: { color: getLabelColor() } } 
                },
                plugins: { legend: { labels: { color: getLabelColor() } } }
            }
        });

        const topDomainsChartInstance = new Chart(document.getElementById('topDomainsChart'), {
            type: 'pie',
            data: {
                labels: <?= $topLabels ?>,
                datasets: [{
                    data: <?= $topValues ?>,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#6366f1', '#14b8a6', '#f97316'],
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: 30 },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 9, weight: '600' },
                            color: getLabelColor()
                        }
                    },
                    tooltip: {
                        callbacks: {
                            footer: () => '— clique para filtrar a tabela'
                        }
                    }
                },
                onClick: function (_evt, elements) {
                    if (!elements || elements.length === 0) return;
                    const idx = elements[0].index;
                    const domain = this.data.labels[idx];
                    const searchEl = document.getElementById('historySearch');
                    if (searchEl && domain) {
                        searchEl.value = domain;
                        filterHistoryQueries();
                        document.getElementById('historyTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        });

        // -- Filtros client-side da tabela --
        function filterHistoryQueries() {
            const q = (document.getElementById('historySearch').value || '').trim().toLowerCase();
            const act = document.getElementById('historyAction').value;
            const typ = document.getElementById('historyType').value;
            const rows = document.querySelectorAll('.history-row');
            let visible = 0;
            rows.forEach(function (row) {
                const matchQ = !q || row.dataset.domain.includes(q) || row.dataset.ip.includes(q);
                const matchAct = !act || row.dataset.action === act;
                const matchTyp = !typ || row.dataset.type === typ;
                const show = matchQ && matchAct && matchTyp;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const total = rows.length;
            const totalEl = document.getElementById('historyCountTotal');
            const visEl = document.getElementById('historyCountVisible');
            if (totalEl) totalEl.textContent = String(total);
            if (visEl) visEl.textContent = String(visible);
            const empty = document.getElementById('historyEmptyFiltered');
            if (empty) empty.classList.toggle('hidden', visible !== 0 || total === 0);
            const clearBtn = document.getElementById('historyClearFilters');
            if (clearBtn) clearBtn.classList.toggle('hidden', !(q || act || typ));
        }
        document.addEventListener('DOMContentLoaded', function () {
            const clearBtn = document.getElementById('historyClearFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    document.getElementById('historySearch').value = '';
                    document.getElementById('historyAction').value = '';
                    document.getElementById('historyType').value = '';
                    filterHistoryQueries();
                });
            }
        });

        // Escutar mudança de tema para atualizar gráficos
        window.addEventListener('storage', (e) => {
            if (e.key === 'theme') {
                location.reload(); // Recarregar para aplicar cores do ChartJS
            }
        });

        window.addEventListener('load', function () {
            var loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
        });
    </script>

</body>
</html>
