<?php
require_once 'src/Auth.php';
require_once 'src/StatsManager.php';

use App\Auth;

// Só redireciona para setup se a instalação ainda não foi concluída.
if (!file_exists(__DIR__ . '/data/.installed')) {
    header('Location: setup.php');
    exit;
}

Auth::check();

// Envia o <head> + loader imediatamente para o browser enquanto os dados carregam
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Dashboard - Unbound DNS Recursivo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200">
<script>
(function(){
    var el = document.getElementById('global-page-loader');
    if (!el) {
        el = document.createElement('div');
        el.id = 'global-page-loader';
        el.setAttribute('aria-live','polite');
        el.setAttribute('aria-busy','true');
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando metricas do Dashboard...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
        document.body.appendChild(el);
    }
    el.classList.add('is-visible');
})();
</script>
<?php
// Flush imediato — browser mostra o loader enquanto o PHP busca os dados
ob_end_flush();
if (function_exists('ob_flush')) ob_flush();
flush();

// Agora carrega os dados (browser já está mostrando o loader)
$statsManager = new \App\StatsManager();
$statsManager->ensureFreshCache();
$initialMetrics = $statsManager->getProcessedMetrics();
$chartBootstrap = $statsManager->getDashboardChartData();

// Extract for initial SSR
$isUnboundRunning = $initialMetrics['online'];
$qps = $initialMetrics['qps'];
$latencyAvg = $initialMetrics['latency_avg'];
$latencyRecursion = $initialMetrics['latency_recursion'] ?? $latencyAvg;
$latencyMedian = $initialMetrics['latency_median'];
$hitRatio = $initialMetrics['hit_ratio'];
$cacheHits = $initialMetrics['cache_hits'];
$cacheMiss = $initialMetrics['cache_miss'];
$reqListAvg = $initialMetrics['req_list_avg'];
$reqListMax = $initialMetrics['req_list_max'];
$dnssecRatio = $initialMetrics['dnssec_ratio'];
$dnssecSecure = $initialMetrics['dnssec_secure'];
$dnssecBogus = $initialMetrics['dnssec_bogus'];
$totalQueries = $initialMetrics['total_queries'];
$tcpTotal = $initialMetrics['tcp_total'];
$ipv4Total = $initialMetrics['ipv4_total'];
$ipv6Total = $initialMetrics['ipv6_total'];
$prefetch = $initialMetrics['prefetch'];
$rrsetMem = $initialMetrics['rrset_mem'];
$msgMem = $initialMetrics['msg_mem'];
$unwanted = $initialMetrics['unwanted'] ?? 0;
$unwantedQueries = $initialMetrics['unwanted_queries'] ?? 0;
$unwantedReplies = $initialMetrics['unwanted_replies'] ?? 0;
$adwareBlocks = $initialMetrics['blocks']['adware'] ?? 0;
$phishBlocks = $initialMetrics['blocks']['phishing'] ?? 0;
$anatelBlocks = $initialMetrics['blocks']['judicial'] ?? 0;
$isJudicialEnabled = $initialMetrics['blocks']['judicial_enabled'] ?? false;
$uptimeHuman = $initialMetrics['uptime_human'] ?? '---';

$timeLabels = $chartBootstrap['labels'];
$cHitsArr = $chartBootstrap['hits'];
$cMissArr = $chartBootstrap['misses'];
$queryTypesArr = $chartBootstrap['query_types'];

$currentPage = 'index.php';
?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto w-full bg-slate-50 dark:bg-slate-950 transition-colors duration-300 dot-matrix-bg">
        <?php 
        $pageTitle = "Painel Geral";
        include 'includes/topbar.php'; 
        ?>


        <div class="p-8 space-y-8 max-w-[1600px] mx-auto animate-fade-in">
            
            <!-- GRID DE METRICAS PRINCIPAIS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- QPS -->
                <div class="glass-panel glow-blue group relative overflow-hidden stagger-item stagger-1">
                    <div class="absolute -right-4 -bottom-4 text-blue-500/10 group-hover:scale-110 transition-transform duration-500"><svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                    <p class="metric-label">Requisições / seg</p>
                    <div class="metric-value id-qps text-blue-600 dark:text-white" data-countup="<?= number_format($qps, 2, '.', '') ?>" data-decimals="2"><?= number_format($qps, 2) ?></div>

                    <div class="flex items-center gap-2 text-[10px] font-bold text-blue-400 uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                        Carga Atual
                    </div>
                </div>

                <!-- LATENCY -->
                <div class="glass-panel glow-emerald group relative overflow-hidden stagger-item stagger-2">
                    <div class="absolute -right-4 -bottom-4 text-emerald-500/10 group-hover:scale-110 transition-transform duration-500"><svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <p class="metric-label">Latência Efetiva</p>
                    <div class="metric-value id-latency-avg text-emerald-600 dark:text-emerald-400" data-countup="<?= number_format($latencyAvg, 1, '.', '') ?>" data-decimals="1" data-suffix=" ms"><?= number_format($latencyAvg, 1) ?> <span class="text-sm font-normal text-slate-500">ms</span></div>
                    <div class="flex items-center gap-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                        <span>Recursão: <span class="text-amber-500 id-latency-recursion"><?= number_format($latencyRecursion, 1) ?></span>ms</span>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <span>Mediana: <span class="text-emerald-500 id-latency-median"><?= number_format($latencyMedian, 1) ?></span>ms</span>
                    </div>
                </div>

                <!-- HIT RATIO -->
                <div class="glass-panel glow-orange group relative overflow-hidden stagger-item stagger-3">
                    <div class="absolute -right-4 -bottom-4 text-orange-500/10 group-hover:scale-110 transition-transform duration-500"><svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                    <p class="metric-label">Taxa de Cache (CHR)</p>
                    <div class="metric-value id-hit-ratio text-orange-600 dark:text-white" data-countup="<?= number_format($hitRatio, 1, '.', '') ?>" data-decimals="1" data-suffix="%"><?= number_format($hitRatio, 1) ?>%</div>
                    <div class="w-full bg-slate-200 dark:bg-white/5 h-1.5 rounded-full mt-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-500 to-yellow-400 h-full rounded-full transition-all duration-1000 progress-shimmer" id="progress-hit-ratio" style="width: <?= $hitRatio ?>%"></div>
                    </div>
                </div>

                <!-- DNSSEC -->
                <div class="glass-panel glow-purple group relative overflow-hidden stagger-item stagger-4">
                    <div class="absolute -right-4 -bottom-4 text-purple-500/10 group-hover:scale-110 transition-transform duration-500"><svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
                    <p class="metric-label">Validação DNSSEC</p>
                    <div class="metric-value id-dnssec-ratio text-purple-600 dark:text-purple-400" data-countup="<?= number_format($dnssecRatio, 1, '.', '') ?>" data-decimals="1" data-suffix="%"><?= number_format($dnssecRatio, 1) ?>%</div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                        Seguros: <span class="text-purple-600 dark:text-purple-300 id-dnssec-secure"><?= $dnssecSecure ?></span>
                    </p>
                </div>
            </div>

            <!-- FILA DO MEIO: GRAFICOS REAIS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
                <!-- PERFORMANCE TEMPORAL -->
                <div class="lg:col-span-8 glass-panel !p-0 overflow-hidden flex flex-col h-full border-slate-900/10 dark:border-white/5 stagger-item stagger-5">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest">Desempenho de Resolução</h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase mt-1">Histórico de Fluxo (Hits x Misses)</p>
                        </div>

                        <div class="flex gap-4">
                            <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span><span class="text-[9px] font-black uppercase text-slate-400">Hits</span></div>
                            <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-slate-500"></span><span class="text-[9px] font-black uppercase text-slate-400">Misses</span></div>
                        </div>
                    </div>
                    <div class="p-6 flex-1 min-h-[350px]">
                        <canvas id="chartPerformance"></canvas>
                    </div>
                </div>

                <!-- DISTRIBUICAO DE TIPOS -->
                <div class="lg:col-span-4 glass-panel !p-0 overflow-hidden flex flex-col h-full border-slate-900/10 dark:border-white/5 stagger-item stagger-6">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest">Distribuição RR</h3>
                        <p class="text-[10px] text-slate-500 font-bold uppercase mt-1">Tipos de Registros DNS</p>
                    </div>

                    <div class="flex-1 flex items-center justify-center p-6 min-h-[300px]">
                        <canvas id="chartQueryTypes"></canvas>
                    </div>
                </div>
            </div>

            <!-- FILA INFERIOR: SEGURANCIA & SISTEMA -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                <!-- SEGURANÇA -->
                <div class="glass-panel glow-red border-l-4 border-red-500/40 flex flex-col h-full border-slate-900/10 dark:border-white/5 stagger-item stagger-7">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest mb-6 flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Segurança & Filtros
                    </h3>

                    <div class="space-y-4 flex-1">
                        <div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl">
                            <span class="text-[10px] text-slate-500 font-bold uppercase">Adware / Trackers</span>
                            <span class="text-sm font-black text-red-600 dark:text-red-400 id-block-adware"><?= number_format($adwareBlocks) ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl">
                            <span class="text-[10px] text-slate-500 font-bold uppercase">Malicious / Phish</span>
                            <span class="text-sm font-black text-red-600 dark:text-red-400 id-block-phish"><?= number_format($phishBlocks) ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-orange-500/10">
                            <span class="text-[10px] text-slate-500 font-bold uppercase">Restrições Judiciais</span>
                            <span class="text-sm font-black text-orange-600 dark:text-orange-400 id-block-judicial"><?= number_format($anatelBlocks) ?></span>
                        </div>

                    </div>
                </div>

                <!-- CONSUMO RAM -->
                <div class="glass-panel flex flex-col h-full border-slate-900/10 dark:border-white/5 stagger-item stagger-8">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest mb-6 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        Cache Interno
                    </h3>

                    <div class="space-y-6 flex-1">
                        <div>
                            <div class="flex justify-between text-[10px] font-black text-slate-500 uppercase mb-2">
                                <span>RRSET Memory</span>
                                <span class="id-mem-rrset"><?= $rrsetMem ?></span>
                            </div>
                            <div class="w-full bg-slate-900/5 dark:bg-white/5 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full rounded-full progress-shimmer" style="width: 45%"></div>
                            </div>

                        </div>
                        <div>
                            <div class="flex justify-between text-[10px] font-black text-slate-500 uppercase mb-2">
                                <span>Message Cache</span>
                                <span class="id-mem-msg"><?= $msgMem ?></span>
                            </div>
                            <div class="w-full bg-slate-900/5 dark:bg-white/5 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-purple-600 h-full rounded-full progress-shimmer" style="width: 30%"></div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ALERTA ANOMALIA -->
                <div class="glass-panel glow-orange group cursor-pointer hover:bg-orange-500/10 transition-all border border-slate-900/10 dark:border-transparent hover:border-orange-500/20 flex flex-col h-full stagger-item stagger-9">
                     <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Anomalias de Rede
                     </h3>

                    <div class="flex-1 flex flex-col justify-center space-y-4">
                        <div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-orange-500/20 flex items-center justify-center text-orange-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase">Queries Recusadas</span>
                                    <p class="text-[9px] text-slate-400 leading-tight">IPs fora das ACLs</p>
                                </div>
                            </div>
                            <span class="text-lg font-black text-orange-600 dark:text-orange-400 id-unwanted-queries"><?= number_format($unwantedQueries) ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-red-500/20 flex items-center justify-center text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase">Replies Indesejadas</span>
                                    <p class="text-[9px] text-slate-400 leading-tight">Respostas não solicitadas</p>
                                </div>
                            </div>
                            <span class="text-lg font-black text-red-600 dark:text-red-400 id-unwanted-replies"><?= number_format($unwantedReplies) ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        // Charts Configuration
        Chart.defaults.color = '#64748b';
        Chart.defaults.font.family = 'Inter, sans-serif';

        // [MELHORIA 7] Tooltip Premium — glassmorphism style
        Chart.defaults.plugins.tooltip.backgroundColor = document.documentElement.classList.contains('dark') 
            ? 'rgba(15, 23, 42, 0.85)' : 'rgba(255, 255, 255, 0.92)';
        Chart.defaults.plugins.tooltip.titleColor = document.documentElement.classList.contains('dark') 
            ? '#f8fafc' : '#0f172a';
        Chart.defaults.plugins.tooltip.bodyColor = document.documentElement.classList.contains('dark') 
            ? '#94a3b8' : '#64748b';
        Chart.defaults.plugins.tooltip.borderColor = document.documentElement.classList.contains('dark') 
            ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.cornerRadius = 12;
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '800', family: 'Inter, sans-serif' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 11, weight: '600', family: 'Inter, sans-serif' };
        Chart.defaults.plugins.tooltip.displayColors = true;
        Chart.defaults.plugins.tooltip.boxPadding = 4;

        // [MELHORIA 8] Plugin para label central no Doughnut
        const doughnutCenterLabel = {
            id: 'doughnutCenterLabel',
            afterDraw(chart) {
                if (chart.config.type !== 'doughnut') return;
                const { ctx, chartArea } = chart;
                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                if (!total || !chartArea) return;
                // Centro real do gráfico (descontando a legenda)
                const centerX = (chartArea.left + chartArea.right) / 2;
                const centerY = (chartArea.top + chartArea.bottom) / 2;
                ctx.save();
                const isDark = document.documentElement.classList.contains('dark');
                // Total value
                ctx.font = '800 24px Inter, sans-serif';
                ctx.fillStyle = isDark ? '#f8fafc' : '#0f172a';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(total.toLocaleString('pt-BR'), centerX, centerY - 8);
                // Label
                ctx.font = '700 10px Inter, sans-serif';
                ctx.fillStyle = isDark ? '#94a3b8' : '#64748b';
                ctx.letterSpacing = '0.1em';
                ctx.fillText('TOTAL', centerX, centerY + 14);
                ctx.restore();
            }
        };
        Chart.register(doughnutCenterLabel);

        const ctxPerf = document.getElementById('chartPerformance').getContext('2d');
        const ctxTypes = document.getElementById('chartQueryTypes').getContext('2d');
        const isDarkMode = document.documentElement.classList.contains('dark');

        // [CHART+] Gradient Fill Programático com resize handler
        function createPerfGradient(ctx) {
            const h = ctx.canvas.clientHeight || 350;
            const grad = ctx.createLinearGradient(0, 0, 0, h);
            grad.addColorStop(0, 'rgba(59, 130, 246, 0.28)');
            grad.addColorStop(0.4, 'rgba(59, 130, 246, 0.10)');
            grad.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
            return grad;
        }
        function createMissGradient(ctx) {
            const h = ctx.canvas.clientHeight || 350;
            const grad = ctx.createLinearGradient(0, 0, 0, h);
            grad.addColorStop(0, isDarkMode ? 'rgba(148, 163, 184, 0.08)' : 'rgba(148, 163, 184, 0.12)');
            grad.addColorStop(1, 'rgba(148, 163, 184, 0.0)');
            return grad;
        }

        // [CHART+] Crosshair vertical plugin
        const crosshairPlugin = {
            id: 'crosshair',
            afterDraw(chart) {
                if (chart.tooltip?._active?.length) {
                    const x = chart.tooltip._active[0].element.x;
                    const yAxis = chart.scales.y;
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(x, yAxis.top);
                    ctx.lineTo(x, yAxis.bottom);
                    ctx.lineWidth = 1;
                    ctx.strokeStyle = isDarkMode ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)';
                    ctx.setLineDash([4, 4]);
                    ctx.stroke();
                    ctx.restore();
                }
            }
        };

        const chartPerf = new Chart(ctxPerf, {
            type: 'line',
            plugins: [crosshairPlugin],
            data: {
                labels: <?= json_encode($timeLabels) ?>,
                datasets: [
                    {
                        label: 'Hits',
                        data: <?= json_encode($cHitsArr) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: createPerfGradient(ctxPerf),
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#3b82f6',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3,
                        pointHitRadius: 20
                    },
                    {
                        label: 'Misses',
                        data: <?= json_encode($cMissArr) ?>,
                        borderColor: isDarkMode ? 'rgba(148, 163, 184, 0.25)' : 'rgba(148, 163, 184, 0.4)',
                        backgroundColor: createMissGradient(ctxPerf),
                        borderWidth: 1.5,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#94a3b8',
                        pointHoverBorderColor: isDarkMode ? '#1e293b' : '#fff',
                        pointHoverBorderWidth: 2,
                        pointHitRadius: 20
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                animation: {
                    duration: 800,
                    easing: 'easeInOutCubic'
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(ctx) {
                                return '  ' + ctx.dataset.label + ': ' + (ctx.parsed.y || 0).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        display: true,
                        beginAtZero: true,
                        border: { display: false },
                        grid: {
                            color: isDarkMode ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)',
                            drawTicks: false
                        },
                        ticks: {
                            color: isDarkMode ? '#475569' : '#94a3b8',
                            font: { size: 9, weight: '600' },
                            padding: 8,
                            maxTicksLimit: 5,
                            callback: function(value) {
                                if (value >= 1000) return (value/1000).toFixed(1) + 'k';
                                return value;
                            }
                        }
                    },
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: { 
                            color: isDarkMode ? '#94a3b8' : '#475569', 
                            font: { size: 10, weight: '700' }, 
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                }
            }
        });

        // [CHART+] Doughnut com segmentos arredondados, espaçamento e sombra
        const doughnutColors = [
            { bg: '#3b82f6', glow: 'rgba(59,130,246,0.3)' },
            { bg: '#8b5cf6', glow: 'rgba(139,92,246,0.3)' },
            { bg: '#10b981', glow: 'rgba(16,185,129,0.3)' },
            { bg: '#f59e0b', glow: 'rgba(245,158,11,0.3)' },
            { bg: '#ef4444', glow: 'rgba(239,68,68,0.3)' }
        ];

        // Plugin para glow sutil atrás do doughnut
        const doughnutGlowPlugin = {
            id: 'doughnutGlow',
            beforeDraw(chart) {
                if (chart.config.type !== 'doughnut') return;
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta.data.length) return;
                ctx.save();
                meta.data.forEach((arc, i) => {
                    const color = doughnutColors[i % doughnutColors.length];
                    ctx.shadowColor = color.glow;
                    ctx.shadowBlur = 12;
                    ctx.shadowOffsetX = 0;
                    ctx.shadowOffsetY = 0;
                    arc.draw(ctx);
                });
                ctx.restore();
            }
        };

        const chartTypes = new Chart(ctxTypes, {
            type: 'doughnut',
            plugins: [doughnutGlowPlugin],
            data: {
                labels: <?= json_encode(array_keys($queryTypesArr)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($queryTypesArr)) ?>,
                    backgroundColor: doughnutColors.map(c => c.bg),
                    borderWidth: 0,
                    hoverOffset: 8,
                    borderRadius: 6,
                    spacing: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                layout: {
                    padding: { top: 4, bottom: 4, left: 4, right: 4 }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeInOutCubic'
                },
                plugins: {
                    tooltip: {
                        backgroundColor: isDarkMode ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDarkMode ? '#f1f5f9' : '#0f172a',
                        bodyColor: isDarkMode ? '#e2e8f0' : '#334155',
                        borderColor: isDarkMode ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.08)',
                        borderWidth: 1,
                        cornerRadius: 12,
                        padding: 14,
                        titleFont: { size: 13, weight: '800', family: 'Inter, sans-serif' },
                        bodyFont: { size: 12, weight: '600', family: 'Inter, sans-serif' },
                        displayColors: true,
                        boxPadding: 6,
                        callbacks: {
                            title: function(tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return '  ' + ctx.parsed.toLocaleString('pt-BR') + '  (' + pct + '%)';
                            }
                        }
                    },
                    legend: {
                        position: 'right',
                        labels: {
                            color: isDarkMode ? '#e2e8f0' : '#334155',
                            font: { size: 12, weight: '700', family: 'Inter, sans-serif' },
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 18,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    const pct = total ? ((value / total) * 100).toFixed(1) : 0;
                                    return {
                                        text: label + '  ' + pct + '%',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: 'transparent',
                                        fontColor: isDarkMode ? '#e2e8f0' : '#334155',
                                        lineWidth: 0,
                                        hidden: false,
                                        index: i,
                                        pointStyle: 'rectRounded'
                                    };
                                });
                            }
                        }
                    }
                }
            }
        });

        // Live Updating with delta calculation
        let lastStats = {
            total_queries: <?= $totalQueries ?>,
            cache_hits: <?= $cacheHits ?>,
            cache_miss: <?= $cacheMiss ?>,
            timestamp: Date.now()
        };

        const initialChartPayload = {
            labels: <?= json_encode($timeLabels) ?>,
            hits: <?= json_encode($cHitsArr) ?>,
            misses: <?= json_encode($cMissArr) ?>,
            query_types: <?= json_encode($queryTypesArr) ?>
        };
        let lastChartSignature = null;
        // Acumuladores de delta para o minuto atual (resetados a cada nova snapshot)
        let liveHitsAccum = 0;
        let liveMissAccum = 0;

        function getChartSignature(chartPayload) {
            if (!chartPayload || !Array.isArray(chartPayload.labels) || !Array.isArray(chartPayload.hits) || !Array.isArray(chartPayload.misses)) {
                return null;
            }

            return JSON.stringify({
                lastLabel: chartPayload.labels[chartPayload.labels.length - 1] || '',
                lastHit: chartPayload.hits[chartPayload.hits.length - 1] || 0,
                lastMiss: chartPayload.misses[chartPayload.misses.length - 1] || 0,
                queryTypes: chartPayload.query_types || {}
            });
        }

        function applyLiveChartDelta(deltaHits, deltaMisses) {
            if (!Number.isFinite(deltaHits) || !Number.isFinite(deltaMisses)) return;
            if (deltaHits === 0 && deltaMisses === 0) return;

            const hitsData = chartPerf.data.datasets[0].data;
            const missesData = chartPerf.data.datasets[1].data;
            if (!hitsData.length || !missesData.length) return;

            // Acumula o delta do minuto atual e SUBSTITUI (não soma) o último ponto,
            // evitando que o ponto cresça indefinidamente entre snapshots (~12 polls/min)
            liveHitsAccum += deltaHits;
            liveMissAccum += deltaMisses;

            const lastIndex = hitsData.length - 1;
            const baseHit = Number(hitsData[lastIndex] || 0);
            const baseMiss = Number(missesData[lastIndex] || 0);
            // Usa o maior entre o valor da snapshot e o acumulado ao vivo
            hitsData[lastIndex] = Math.max(baseHit, liveHitsAccum);
            missesData[lastIndex] = Math.max(baseMiss, liveMissAccum);
            chartPerf.update('none');
        }

        function updateChartData(chartPayload, deltaHits = 0, deltaMisses = 0) {
            if (!chartPayload) return;

            const nextSignature = getChartSignature(chartPayload);
            const hasFreshSnapshot = nextSignature && nextSignature !== lastChartSignature;

            if (hasFreshSnapshot && Array.isArray(chartPayload.labels) && Array.isArray(chartPayload.hits) && Array.isArray(chartPayload.misses)) {
                chartPerf.data.labels = chartPayload.labels;
                chartPerf.data.datasets[0].data = chartPayload.hits;
                chartPerf.data.datasets[1].data = chartPayload.misses;
                chartPerf.update('none');
                lastChartSignature = nextSignature;
                // Reseta acumuladores ao receber nova snapshot
                liveHitsAccum = 0;
                liveMissAccum = 0;
            } else if (deltaHits > 0 || deltaMisses > 0) {
                applyLiveChartDelta(deltaHits, deltaMisses);
            }

            if (hasFreshSnapshot && chartPayload.query_types && typeof chartPayload.query_types === 'object') {
                chartTypes.data.labels = Object.keys(chartPayload.query_types);
                chartTypes.data.datasets[0].data = Object.values(chartPayload.query_types);
                chartTypes.update('none');
            }
        }

        lastChartSignature = getChartSignature(initialChartPayload);

        async function updateDashboard() {
            try {
                const res = await fetch('api/stats.php', {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) {
                    throw new Error('Falha HTTP ' + res.status + ' ao carregar métricas da dashboard');
                }
                const payload = await res.json();
                const data = payload.metrics || payload;
                const now = new Date();

                if (!data || payload.error) {
                    throw new Error(payload.error || 'Falha ao carregar métricas da dashboard');
                }
                
                // Update Numeric metrics
                document.querySelector('.id-qps').innerText = parseFloat(data.qps).toFixed(2);
                document.querySelector('.id-latency-avg').innerHTML = (data.latency_avg || 0).toFixed(1) + ' <span class="text-sm font-normal text-slate-500">ms</span>';
                document.querySelector('.id-latency-recursion').innerText = (data.latency_recursion || 0).toFixed(1);
                document.querySelector('.id-latency-median').innerText = (data.latency_median || 0).toFixed(1);
                document.querySelector('.id-hit-ratio').innerText = data.hit_ratio + '%';
                document.getElementById('progress-hit-ratio').style.width = data.hit_ratio + '%';
                document.querySelector('.id-dnssec-ratio').innerText = data.dnssec_ratio + '%';
                document.querySelector('.id-dnssec-secure').innerText = data.dnssec_secure;
                document.querySelector('.id-block-adware').innerText = (data.blocks.adware || 0).toLocaleString();
                document.querySelector('.id-block-phish').innerText = (data.blocks.phishing || 0).toLocaleString();
                document.querySelector('.id-block-judicial').innerText = (data.blocks.judicial || 0).toLocaleString();
                document.querySelector('.id-mem-rrset').innerText = data.rrset_mem_human || data.rrset_mem;
                document.querySelector('.id-mem-msg').innerText = data.msg_mem_human || data.msg_mem;
                document.querySelector('.id-unwanted-queries').innerText = (data.unwanted_queries || 0).toLocaleString();
                document.querySelector('.id-unwanted-replies').innerText = (data.unwanted_replies || 0).toLocaleString();

                const deltaHits = lastStats ? Math.max(0, (data.cache_hits || 0) - (lastStats.cache_hits || 0)) : 0;
                const deltaMisses = lastStats ? Math.max(0, (data.cache_miss || 0) - (lastStats.cache_miss || 0)) : 0;

                updateChartData(payload.charts, deltaHits, deltaMisses);
                
                lastStats = {
                    total_queries: data.total_queries,
                    cache_hits: data.cache_hits,
                    cache_miss: data.cache_miss,
                    timestamp: now.getTime()
                };

            } catch(e) { console.error("Poll error", e); }
        }

        setInterval(updateDashboard, 5000);

        // [MELHORIA 4] Counters Numéricos Animados — incremento suave de 0 ao valor real
        function animateCounters() {
            document.querySelectorAll('[data-countup]').forEach(el => {
                const target = parseFloat(el.dataset.countup);
                const decimals = parseInt(el.dataset.decimals || '0');
                const suffix = el.dataset.suffix || '';
                const duration = 1200;
                const startTime = performance.now();
                
                function easeOutExpo(t) {
                    return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
                }
                
                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easedProgress = easeOutExpo(progress);
                    const current = target * easedProgress;
                    
                    if (suffix === ' ms') {
                        el.innerHTML = current.toFixed(decimals) + ' <span class="text-sm font-normal text-slate-500">ms</span>';
                    } else {
                        el.textContent = current.toFixed(decimals) + suffix;
                    }
                    
                    if (progress < 1) {
                        requestAnimationFrame(update);
                    }
                }
                requestAnimationFrame(update);
            });
        }
        // Execute after stagger animation completes
        setTimeout(animateCounters, 100);

        // Esconde o loader agora que o conteúdo renderizou
        (function() {
            var loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
        })();
    </script>
</body>
</html>
