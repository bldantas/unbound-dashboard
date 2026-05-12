<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) { header('Location: index.php'); exit; }

$results = [];

// Inputs configuráveis pela UI (com defaults seguros).
$defaultDomain = 'google.com';
$defaultQueries = 5;
$benchmark_domain = trim($_POST['benchmark_domain'] ?? $defaultDomain);
// Hostname-like validation: a-z0-9.-_ — bloqueia injection no shell
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $benchmark_domain) || strlen($benchmark_domain) > 253) {
    $benchmark_domain = $defaultDomain;
}
$num_queries = (int) ($_POST['num_queries'] ?? $defaultQueries);
if ($num_queries < 1 || $num_queries > 20) $num_queries = $defaultQueries;

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_benchmark') {
    $servers = [
        'Local (Unbound)' => '127.0.0.1',
        'Cloudflare' => '1.1.1.1',
        'Google DNS' => '8.8.8.8',
        'Quad9' => '9.9.9.9',
        'OpenDNS' => '208.67.222.222',
        'Cisco OpenDNS' => '208.67.220.220',
        'AdGuard DNS' => '94.140.14.14',
        'Level3' => '4.2.2.1'
    ];
    
    foreach ($servers as $name => $ip) {
        $latencies = [];
        $failures = 0;
        
        for ($i = 0; $i < $num_queries; $i++) {
            \App\ShellHelper::exec('/usr/bin/dig', ['@'.$ip, $benchmark_domain, 'A', '+noall', '+stats', '+time=2', '+tries=1'], $out, $ret, false);
            
            $query_time = null;
            foreach ($out as $line) {
                if (preg_match('/;; Query time: (\d+) msec/', $line, $matches)) {
                    $query_time = (float)$matches[1];
                    break;
                }
            }
            
            if ($query_time !== null) {
                $latencies[] = $query_time;
            } else {
                $failures++;
            }
            unset($out);
        }
        
        if (count($latencies) > 0) {
            $avg = array_sum($latencies) / count($latencies);
            $results[$name] = [
                'ip' => $ip, 
                'avg' => round($avg, 2), 
                'min' => round(min($latencies), 2), 
                'max' => round(max($latencies), 2), 
                'status' => 'ok'
            ];
        } else {
            $results[$name] = ['ip' => $ip, 'avg' => 0, 'status' => 'fail'];
        }
    }
    
    uasort($results, function($a, $b) {
        if ($a['status'] === 'fail') return 1;
        if ($b['status'] === 'fail') return -1;
        return $a['avg'] <=> $b['avg'];
    });

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => $results]);
        exit;
    }
}

$currentPage = 'dns_benchmark.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Benchmark DNS - Unbound DNS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include 'includes/head.php'; ?>
    <style>
        .benchmark-loader-card {
            width: min(92vw, 520px);
            min-height: 340px;
            border-radius: 1.25rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: linear-gradient(165deg, rgba(15, 23, 42, 0.92), rgba(30, 41, 59, 0.86));
            box-shadow: 0 20px 55px rgba(2, 6, 23, 0.45);
            padding: 2rem 2rem 2rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .benchmark-loader-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 15% 15%, rgba(16, 185, 129, 0.16), transparent 42%);
            pointer-events: none;
        }

        .benchmark-loader-visual {
            width: 84px;
            height: 84px;
            margin: 0 auto 1rem auto;
            position: relative;
        }

        .benchmark-loader-ring {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            border: 3px solid rgba(16, 185, 129, 0.2);
            border-top-color: rgba(16, 185, 129, 1);
            border-right-color: rgba(52, 211, 153, 0.8);
            animation: benchmark-spin 1.15s linear infinite;
        }

        .benchmark-loader-core {
            position: absolute;
            inset: 17px;
            border-radius: 999px;
            background: radial-gradient(circle at 35% 35%, rgba(52, 211, 153, 0.95), rgba(16, 185, 129, 0.45));
            box-shadow: 0 0 28px rgba(16, 185, 129, 0.45);
            animation: benchmark-pulse 1.8s ease-in-out infinite;
        }

        .benchmark-loader-steps {
            margin-top: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .benchmark-loader-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.85);
            opacity: 0.35;
            animation: benchmark-dot 1.2s ease-in-out infinite;
        }

        .benchmark-loader-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .benchmark-loader-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        .benchmark-loader-progress-track {
            margin-top: 1.1rem;
            width: 100%;
            height: 5px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(51, 65, 85, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .benchmark-loader-progress-bar {
            height: 100%;
            width: 35%;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.25), rgba(16, 185, 129, 0.95), rgba(16, 185, 129, 0.25));
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.45);
            animation: benchmark-progress 1.35s ease-in-out infinite;
            transform: translateX(-120%);
        }

        @keyframes benchmark-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes benchmark-pulse {
            0%, 100% { transform: scale(0.92); }
            50% { transform: scale(1); }
        }

        @keyframes benchmark-progress {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(320%); }
        }

        @keyframes benchmark-dot {
            0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
            40% { opacity: 1; transform: translateY(-2px); }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto">
        <?php 
        $pageTitle = "Benchmark de Resolvers";
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container relative">
            
            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="hidden fixed inset-0 z-[80] bg-slate-100/70 dark:bg-slate-950/80 backdrop-blur-sm flex flex-col items-center justify-center animate-fade-in p-4">
                <div class="benchmark-loader-card">
                    <div class="benchmark-loader-visual" aria-hidden="true">
                        <div class="benchmark-loader-ring"></div>
                        <div class="benchmark-loader-core"></div>
                    </div>
                    <h3 id="benchmarkLoaderTitle" class="text-xl font-black text-white uppercase tracking-widest text-center">Executando Benchmark DNS</h3>
                    <p id="benchmarkLoaderSubtitle" class="text-sm text-slate-300 font-medium text-center mt-2">Medindo latencia e estabilidade entre resolvers. Isso pode levar alguns segundos.</p>
                    <p id="benchmarkLoaderRound" class="text-xs font-bold text-emerald-300 uppercase tracking-widest text-center mt-3">Preparando...</p>
                    <div class="benchmark-loader-steps" aria-hidden="true">
                        <span class="benchmark-loader-dot"></span>
                        <span class="benchmark-loader-dot"></span>
                        <span class="benchmark-loader-dot"></span>
                    </div>
                    <div class="benchmark-loader-progress-track" aria-hidden="true">
                        <div class="benchmark-loader-progress-bar"></div>
                    </div>
                </div>
            </div>

            <!-- Parâmetros do benchmark -->
            <div class="glass-panel mb-6 border-slate-200 dark:border-white/5">
                <form id="benchmarkForm" data-loader="off" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <input type="hidden" name="action" value="run_benchmark">
                    <div class="md:col-span-6">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Domínio de teste</label>
                        <input type="text" name="benchmark_domain" id="benchmark_domain" value="<?= htmlspecialchars($benchmark_domain) ?>"
                               pattern="[a-zA-Z0-9._-]+" maxlength="253"
                               placeholder="google.com" required
                               class="glass-input w-full font-mono">
                        <p class="text-[9px] text-slate-500 mt-1">Cada servidor resolverá este nome. Validação: a-z 0-9 . - _</p>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Queries por servidor</label>
                        <input type="number" name="num_queries" id="num_queries" value="<?= (int)$num_queries ?>"
                               min="1" max="20" required
                               class="glass-input w-full font-mono">
                        <p class="text-[9px] text-slate-500 mt-1">Entre 1 e 20 (default 5).</p>
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" id="btnStart" class="glass-btn bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-500/20 px-8 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Iniciar Diagnóstico</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Section (Hidden initially) -->
            <div id="resultsContainer" class="hidden animate-fade-in">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <div class="lg:col-span-2 glass-panel">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6">Gráfico de Latência Média (ms)</h3>
                        <div class="h-64"><canvas id="benchmarkChart"></canvas></div>
                    </div>
                    
                    <div class="glass-panel flex flex-col items-center justify-center text-center">
                        <div class="w-20 h-20 bg-emerald-500/10 text-emerald-500 rounded-3xl flex items-center justify-center mb-6 border border-emerald-500/20 shadow-xl shadow-emerald-500/10">
                            <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                        </div>
                        <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Melhor Performance</h4>
                        <p class="text-2xl font-black text-slate-900 dark:text-white mt-2" id="bestName">-</p>
                        <div class="text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-2"><span id="bestAvg">0</span><span class="text-sm">ms</span></div>
                        <p class="text-[9px] text-slate-600 mt-4 leading-relaxed max-w-[150px]">Baseado na média de <?= $num_queries ?> consultas consecutivas.</p>
                    </div>
                </div>

                <div class="glass-table-container mb-8">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th class="w-12 text-center">#</th>
                                <th>Provedor DNS</th>
                                <th>IP / Host</th>
                                <th class="text-center">Min / Max</th>
                                <th>Latência Média</th>
                                <th class="text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Injected by JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State Section -->
            <div id="emptyState" class="glass-panel flex flex-col items-center justify-center p-20 text-center h-[50vh]">
                <div class="w-20 h-20 bg-blue-500/10 border border-blue-500/20 rounded-3xl flex items-center justify-center mb-8 text-blue-500">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Pronto para o Teste?</h3>
                <p class="text-slate-500 text-sm max-w-sm mx-auto font-medium">Iniciaremos <?= $num_queries ?> consultas consecutivas para cada servidor para validar a estabilidade e latência média.</p>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        const BENCHMARK_ROUNDS = 3;
        let chartInstance = null;
        const benchmarkOverlay = document.getElementById('loadingOverlay');

        // Evita que `position: fixed` fique preso ao container animado (`.page-container`).
        if (benchmarkOverlay && benchmarkOverlay.parentElement !== document.body) {
            document.body.appendChild(benchmarkOverlay);
        }

        function setLoaderRound(current, total) {
            const roundEl = document.getElementById('benchmarkLoaderRound');
            const subEl = document.getElementById('benchmarkLoaderSubtitle');
            if (roundEl) {
                roundEl.textContent = `Teste ${current} de ${total}`;
            }
            if (subEl) {
                subEl.textContent = `Medindo latência e estabilidade entre resolvers (rodada ${current}/${total}).`;
            }
        }

        function aggregateRounds(rounds) {
            const acc = {};
            for (const data of rounds) {
                for (const [name, srv] of Object.entries(data)) {
                    if (!acc[name]) {
                        acc[name] = { ip: srv.ip, avgs: [], mins: [], maxs: [], oks: 0, fails: 0 };
                    }
                    if (srv.status === 'ok') {
                        acc[name].avgs.push(srv.avg);
                        acc[name].mins.push(srv.min);
                        acc[name].maxs.push(srv.max);
                        acc[name].oks++;
                    } else {
                        acc[name].fails++;
                    }
                }
            }
            const final = {};
            for (const [name, agg] of Object.entries(acc)) {
                if (agg.oks > 0) {
                    const avgMean = agg.avgs.reduce((s, v) => s + v, 0) / agg.avgs.length;
                    final[name] = {
                        ip: agg.ip,
                        avg: Math.round(avgMean * 100) / 100,
                        min: Math.round(Math.min(...agg.mins) * 100) / 100,
                        max: Math.round(Math.max(...agg.maxs) * 100) / 100,
                        status: 'ok',
                    };
                } else {
                    final[name] = { ip: agg.ip, avg: 0, status: 'fail' };
                }
            }
            const sorted = Object.entries(final).sort((a, b) => {
                if (a[1].status === 'fail') return 1;
                if (b[1].status === 'fail') return -1;
                return a[1].avg - b[1].avg;
            });
            return Object.fromEntries(sorted);
        }

        document.getElementById('benchmarkForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('btnStart');
            const overlay = benchmarkOverlay || document.getElementById('loadingOverlay');
            const resultsDiv = document.getElementById('resultsContainer');
            const emptyState = document.getElementById('emptyState');

            btn.disabled = true;
            btn.querySelector('span').innerText = 'Testando...';
            overlay.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            emptyState.classList.add('hidden');
            setLoaderRound(1, BENCHMARK_ROUNDS);

            const formData = new FormData(this);
            const rounds = [];

            try {
                for (let round = 1; round <= BENCHMARK_ROUNDS; round++) {
                    setLoaderRound(round, BENCHMARK_ROUNDS);
                    const response = await fetch('dns_benchmark.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData,
                    });
                    const json = await response.json();
                    if (json.status !== 'success' || !json.data || !Object.keys(json.data).length) {
                        throw new Error(`Rodada ${round} sem dados`);
                    }
                    rounds.push(json.data);
                }

                const aggregated = aggregateRounds(rounds);
                if (Object.keys(aggregated).length > 0) {
                    renderResults(aggregated);
                    resultsDiv.classList.remove('hidden');
                } else {
                    emptyState.classList.remove('hidden');
                    window.AppUI.toast('Nenhum resultado foi retornado pelo benchmark.', 'warning', { title: 'Benchmark DNS' });
                }
            } catch (error) {
                console.error("Benchmark failed:", error);
                emptyState.classList.remove('hidden');
                window.AppUI.toast('Ocorreu um erro ao executar o benchmark DNS.', 'error', { title: 'Benchmark DNS' });
            } finally {
                btn.disabled = false;
                btn.querySelector('span').innerText = 'Iniciar Diagnóstico';
                overlay.classList.add('hidden');
            }
        });

        function renderResults(data) {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';
            
            const labels = [];
            const chartData = [];
            let rank = 1;
            let fastestName = '';
            let fastestAvg = 9999;
            
            for (const [name, server] of Object.entries(data)) {
                labels.push(name);
                chartData.push(server.avg);
                
                if (server.status === 'ok' && server.avg < fastestAvg) {
                    fastestAvg = server.avg;
                    fastestName = name;
                }

                const isFastest = (rank === 1 && server.status === 'ok');
                const percent = Math.min(100, (server.avg / 150) * 100);
                let colorClass = 'bg-red-500';
                let scoreText = 'Lento';
                let scoreClass = 'bg-red-500/10 text-red-500 border-red-500/20';
                
                if (server.avg < 30) {
                    colorClass = 'bg-emerald-500';
                    scoreText = 'Excelente';
                    scoreClass = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                } else if (server.avg < 70) {
                    colorClass = 'bg-yellow-500';
                    scoreText = 'Bom';
                    scoreClass = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                }

                const tr = document.createElement('tr');
                if(isFastest) tr.className = 'bg-emerald-500/5';
                
                if (server.status === 'ok') {
                    tr.innerHTML = `
                        <td class="text-center font-black text-slate-500 text-xs">${rank}</td>
                        <td class="px-6 py-4 font-black mt-2">${name}</td>
                        <td class="font-mono text-xs text-slate-400">${server.ip}</td>
                        <td class="text-center text-[10px] text-slate-500 font-mono">${server.min} / ${server.max} <span class="opacity-50">ms</span></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-4">
                                <span class="font-mono font-black text-slate-900 dark:text-white w-16">${server.avg.toFixed(2)}ms</span>
                                <div class="flex-1 bg-slate-200 dark:bg-white/5 rounded-full h-1 overflow-hidden max-w-[120px]">
                                    <div class="${colorClass} h-full" style="width: ${percent}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-right">
                            <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full border ${scoreClass}">
                                ${scoreText}
                            </span>
                        </td>
                    `;
                } else {
                    tr.innerHTML = `
                        <td class="text-center font-black text-slate-500 text-xs">${rank}</td>
                        <td class="px-6 py-4 font-black mt-2">${name}</td>
                        <td class="font-mono text-xs text-slate-400">${server.ip}</td>
                        <td class="text-center text-[10px] text-slate-500 font-mono">-</td>
                        <td class="px-6 py-4">
                            <span class="text-red-500 font-black text-[9px] uppercase tracking-widest">TIMEOUT / FAIL</span>
                        </td>
                        <td class="text-right"></td>
                    `;
                }
                tbody.appendChild(tr);
                rank++;
            }
            
            document.getElementById('bestName').innerText = fastestName;
            document.getElementById('bestAvg').innerText = fastestAvg < 9999 ? fastestAvg : 0;
            
            renderChart(labels, chartData);
        }

        function renderChart(labels, data) {
            const ctx = document.getElementById('benchmarkChart').getContext('2d');
            if (chartInstance) {
                chartInstance.destroy();
            }
            
            const isDark = document.documentElement.classList.contains('dark');
            Chart.defaults.color = isDark ? '#94a3b8' : '#475569';
            Chart.defaults.font.family = 'Inter, sans-serif';
            
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'ms',
                        data: data,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4,
                        barThickness: 15
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        x: { grid: { color: isDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } }, 
                        y: { grid: { display: false }, ticks: { color: isDark ? '#e2e8f0' : '#1e293b', font: { size: 11, weight: 'bold' } } } 
                    }
                }
            });
        }
    </script>
</body>
</html>
