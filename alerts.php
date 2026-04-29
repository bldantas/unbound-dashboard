<?php
require_once 'src/Auth.php';
require_once 'src/AlertManager.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$alertManager = new \App\AlertManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve' && isset($_POST['alert_id'])) {
        $alertManager->resolveAlertById((int)$_POST['alert_id']);
    } elseif ($_POST['action'] === 'clear_all') {
        $alertManager->clearResolvedAlerts();
    }
    header('Location: alerts.php');
    exit;
}

// Renderiza shell inicial para exibir loader enquanto metricas sao coletadas
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title>Sistêmica & Alertas - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .metric-card {
            background: rgba(var(--glass-bg), 0.7);
            border: 1px solid rgba(var(--glass-border), 0.1);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        }

        html.dark .metric-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
            border-color: rgba(255, 255, 255, 0.05);
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.5);
            border-color: rgba(var(--glass-border), 0.2);
        }

        .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background: rgba(0, 0, 0, 0.3);
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-value {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease-in-out;
        }
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
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando alertas ativos...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
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

// Alerts
$alerts = $alertManager->getHistory();
$activeCount = $alertManager->getActiveCount();

$currentPage = 'alerts.php';
?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-950">
        <div class="page-container">
            <header class="page-header mb-8">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Saúde do Servidor & Alertas
                    </h1>
                    <p class="page-subtitle">Monitoramento em tempo real de hardware, segurança e conectividade.</p>
                </div>
            </header>

            <!-- Seção de Hardware -->
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4 px-2">Recursos de Hardware & Sistema</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- CPU -->
                <div class="metric-card border-slate-200 dark:border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="icon-box text-blue-500 dark:text-blue-400 border-blue-500/20 bg-blue-500/10"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 10h-4v4h4v-4zM22 6h-2V4c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v2H2v2h2v4H2v2h2v4H2v2h2v2c0 1.1.9 2 2 2h2v2h2v-2h4v2h2v-2h2c1.1 0 2-.9 2-2v-2h2v-2h-2v-4h2v-2h-2V8h2V6zm-4 12H6V6h12v12z" />
                            </svg></div>
                        <span id="alertsCpuLoadBadge" class="text-xs font-black text-slate-500 dark:text-slate-400 bg-slate-900/5 dark:bg-slate-800/50 px-3 py-1 rounded-full">Load: --</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold mb-1">Processador</h3>

                    <p class="text-xs text-slate-400 mb-3">Load Average (1/5/15)</p>
                    <div class="flex justify-between items-end">
                        <div id="alertsCpuLoadValues" class="text-2xl font-black text-blue-500 dark:text-blue-400">-- / -- / --</div>
                    </div>

                </div>

                <!-- RAM -->
                <div class="metric-card border-slate-200 dark:border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="icon-box text-emerald-500 dark:text-emerald-400 border-emerald-500/20 bg-emerald-500/10"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M2 13h2v-2H2v-2h2V7c0-1.1.9-2 2-2h12c1.1 0 2 .9 2 2v2h2v2h-2v2h2v2h-2v2c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2v-2h-2v-2zm4 4h12V7H6v10zm3-8h6v2H9V9zm0 4h6v2H9v-2z"></path>
                            </svg></div>
                        <span id="alertsMemPercent" class="text-xs font-black text-slate-500 dark:text-slate-400 bg-slate-900/5 dark:bg-slate-800/50 px-3 py-1 rounded-full">--% Usado</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold mb-1">Memória RAM</h3>

                    <p id="alertsMemSummary" class="text-xs text-slate-400 mb-3">-- GB de -- GB</p>
                    <div class="progress-bar">
                        <div id="alertsMemBar" class="progress-value bg-emerald-500" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Swap/Disk -->
                <div class="metric-card border-slate-200 dark:border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="icon-box text-purple-500 dark:text-purple-400 border-purple-500/20 bg-purple-500/10"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H8l4-4 4 4h-3v4h-2z"></path>
                            </svg></div>
                        <span id="alertsDiskPercent" class="text-xs font-black text-slate-500 dark:text-slate-400 bg-slate-900/5 dark:bg-slate-800/50 px-3 py-1 rounded-full">--% Disco</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold mb-1">Armazenamento</h3>

                    <p id="alertsDiskSummary" class="text-xs text-slate-400 mb-3">Livre: -- GB</p>
                    <div class="progress-bar">
                        <div id="alertsDiskBar" class="progress-value bg-purple-500" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Network -->
                <div class="metric-card border-slate-200 dark:border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="icon-box text-cyan-500 dark:text-cyan-400 border-cyan-500/20 bg-cyan-500/10"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 4C7.58 4 4 7.58 4 12s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zm-1-9v4h4v-2h-2V9h-2z"></path>
                            </svg></div>
                        <span class="text-xs font-black text-slate-500 dark:text-slate-400 bg-slate-900/5 dark:bg-slate-800/50 px-3 py-1 rounded-full">Rede Saúde</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold mb-1">Interfaces</h3>

                    <p id="alertsNetSummary" class="text-xs text-slate-400 mb-3">Pkt Drops: -- | Eth Errors: --</p>
                    <div class="flex justify-between items-end">
                        <div id="alertsNetTotal" class="text-2xl font-black text-cyan-500 dark:text-cyan-400">--</div>
                    </div>

                </div>
            </div>

            <!-- Seção de Segurança e Aplicações -->
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4 px-2">Segurança & Conectividade</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

                <!-- Sec Card -->
                <div class="metric-card border-slate-200 dark:border-white/5 flex items-start gap-4">
                    <div class="icon-box w-16 h-16 shrink-0 text-red-500 border-red-500/20 bg-red-500/10"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg></div>

                    <div class="flex-1">
                        <div class="flex justify-between">
                            <h3 class="text-slate-900 dark:text-white font-bold text-lg">Tentativas de Autenticação</h3>
                            <span class="text-[10px] font-black bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 pt-1 border border-slate-300 dark:border-white/5 rounded">SSH LOGINS</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-3">Falhas detectadas no auth.log hoje (Acessos suspeitos).</p>
                        <div id="alertsFailedLogins" class="text-3xl font-black text-slate-800 dark:text-slate-300">--</div>
                    </div>
                </div>


                <!-- DB and App Services Card -->
                <div class="metric-card border-slate-200 dark:border-white/5 flex items-start gap-4">
                    <div class="icon-box w-16 h-16 shrink-0 text-orange-500 dark:text-orange-400 border-orange-500/20 bg-orange-500/10"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                        </svg></div>
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <h3 class="text-slate-900 dark:text-white font-bold text-lg">Serviços e Conexões</h3>
                            <span class="text-[10px] font-black bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 pt-1 border border-slate-300 dark:border-white/5 rounded">MARIADB & WEB</span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-3">Bando de Dados: <span id="alertsDbStatus" class="text-slate-900 dark:text-white font-bold">--</span> | Conexões DB: <span id="alertsDbConnections" class="text-slate-900 dark:text-white font-bold">--</span></p>

                        <div class="flex flex-wrap items-center gap-4">
                            <div class="bg-slate-900/5 dark:bg-black/30 border border-slate-200 dark:border-white/5 rounded shadow px-3 py-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Web Server:</span>
                                <span id="alertsWebStatus" class="text-xs font-black uppercase text-slate-500 ml-2">--</span>
                            </div>
                            <div class="bg-slate-900/5 dark:bg-black/30 border border-slate-200 dark:border-white/5 rounded shadow px-3 py-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Portas Externas:</span>
                                <span id="alertsOpenPorts" class="text-xs font-black uppercase text-amber-500 dark:text-amber-400 ml-2">-- LISTEN</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>


            <!-- Active Alerts Table Area -->
            <div class="flex justify-between items-end mb-4 px-2">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Ocorrências Críticas detectadas
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Registros automáticos das falhas e resoluções.</p>
                </div>


                <div class="flex items-center gap-4">
                    <?php if ($activeCount > 0): ?>
                        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest animate-pulse">
                            <?= $activeCount ?> Pendentes
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="glass-btn bg-slate-800 hover:bg-slate-700 text-slate-300 border-white/5 uppercase tracking-widest text-[10px] font-black">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1-1v3M4 7h16"></path>
                            </svg>
                            Limpar Resolvidos
                        </button>
                    </form>
                </div>
            </div>

            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th class="w-32">Status</th>
                            <th class="w-48">Timestamp</th>
                            <th>Evento / Mensagem</th>
                            <th class="w-32">Origem</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alerts)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <p class="text-emerald-500 font-bold uppercase tracking-widest text-[10px] mt-2">Nenhum evento registrado. Sistema impecável.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alerts as $a): ?>
                                <?php $status = empty($a['resolved_at']) ? 'active' : 'resolved'; ?>
                                <tr class="<?= $status === 'active' ? 'bg-red-500/5' : '' ?>">
                                    <td class="px-6 py-4">
                                        <?php if ($status === 'active'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black bg-red-500/10 text-red-400 border border-red-500/20 uppercase tracking-widest">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                                Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 uppercase tracking-widest">
                                                ✔ Resolvido
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-[11px] font-mono text-slate-500"><?= date('d/m/Y', strtotime($a['started_at'])) ?></div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white"><?= date('H:i:s', strtotime($a['started_at'])) ?></div>
                                    </td>
                                    <td class="<?= $status === 'active' ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-400 dark:text-slate-500 line-through' ?>">
                                        <?= htmlspecialchars($a['message']) ?>
                                    </td>

                                    <td>
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 bg-slate-800 rounded-lg text-slate-400 border border-white/5">
                                            <?= htmlspecialchars($a['type']) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($status === 'active'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="resolve">
                                                <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="glass-btn bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-400 border-emerald-500/20 text-[9px] font-black uppercase tracking-widest">
                                                    Reconhecer
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-700 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>
    <script>
        async function loadAlertsMetrics() {
            try {
                const res = await fetch('api/alerts_metrics.php', { cache: 'no-store' });
                if (!res.ok) throw new Error('Falha HTTP ' + res.status);

                const payload = await res.json();
                if (!payload || payload.status !== 'success' || !payload.data) {
                    throw new Error(payload && payload.error ? payload.error : 'Resposta inválida');
                }

                const data = payload.data;

                const cpu = data.cpu || { load1: 0, load5: 0, load15: 0 };
                const mem = data.memory || { total: 0, used: 0, percent: 0 };
                const disk = data.disk || { total: 0, used: 0, percent: 0 };
                const net = data.network || { drops: 0, errors: 0 };
                const db = data.db || { status: 'offline', connections: 0 };
                const failed = Number(data.failed_logins || 0);
                const openPorts = Number(data.open_ports || 0);
                const webStatusRaw = String(data.web_status || 'offline');
                const webStatus = webStatusRaw.replace(/_/g, ' ');

                const cpuBadge = document.getElementById('alertsCpuLoadBadge');
                const cpuValues = document.getElementById('alertsCpuLoadValues');
                if (cpuBadge) cpuBadge.textContent = 'Load: ' + Number(cpu.load1).toFixed(2);
                if (cpuValues) cpuValues.textContent = Number(cpu.load1).toFixed(2) + ' / ' + Number(cpu.load5).toFixed(2) + ' / ' + Number(cpu.load15).toFixed(2);

                const memPercent = Math.max(0, Math.min(100, Number(mem.percent || 0)));
                const memBadge = document.getElementById('alertsMemPercent');
                const memSummary = document.getElementById('alertsMemSummary');
                const memBar = document.getElementById('alertsMemBar');
                if (memBadge) memBadge.textContent = memPercent.toFixed(2) + '% Usado';
                if (memSummary) memSummary.textContent = (Number(mem.used || 0) / 1024).toFixed(2) + ' GB de ' + (Number(mem.total || 0) / 1024).toFixed(2) + ' GB';
                if (memBar) {
                    memBar.style.width = memPercent + '%';
                    memBar.classList.toggle('bg-red-500', memPercent > 85);
                    memBar.classList.toggle('bg-emerald-500', memPercent <= 85);
                }

                const diskPercent = Math.max(0, Math.min(100, Number(disk.percent || 0)));
                const diskBadge = document.getElementById('alertsDiskPercent');
                const diskSummary = document.getElementById('alertsDiskSummary');
                const diskBar = document.getElementById('alertsDiskBar');
                if (diskBadge) diskBadge.textContent = diskPercent.toFixed(2) + '% Disco';
                if (diskSummary) diskSummary.textContent = 'Livre: ' + (Number(disk.total || 0) - Number(disk.used || 0)).toFixed(2) + ' GB';
                if (diskBar) {
                    diskBar.style.width = diskPercent + '%';
                    diskBar.classList.toggle('bg-red-500', diskPercent > 85);
                    diskBar.classList.toggle('bg-purple-500', diskPercent <= 85);
                }

                const totalNetIssues = Number(net.drops || 0) + Number(net.errors || 0);
                const netSummary = document.getElementById('alertsNetSummary');
                const netTotal = document.getElementById('alertsNetTotal');
                if (netSummary) netSummary.textContent = 'Pkt Drops: ' + Number(net.drops || 0) + ' | Eth Errors: ' + Number(net.errors || 0);
                if (netTotal) {
                    netTotal.textContent = String(totalNetIssues);
                    netTotal.classList.toggle('text-red-500', totalNetIssues > 0);
                    netTotal.classList.toggle('text-cyan-500', totalNetIssues === 0);
                    netTotal.classList.toggle('dark:text-cyan-400', totalNetIssues === 0);
                }

                const failedEl = document.getElementById('alertsFailedLogins');
                if (failedEl) {
                    failedEl.textContent = String(failed);
                    failedEl.classList.toggle('text-red-500', failed > 10);
                    failedEl.classList.toggle('animate-pulse', failed > 10);
                    failedEl.classList.toggle('text-slate-800', failed <= 10);
                    failedEl.classList.toggle('dark:text-slate-300', failed <= 10);
                }

                const dbStatus = document.getElementById('alertsDbStatus');
                const dbConnections = document.getElementById('alertsDbConnections');
                if (dbStatus) dbStatus.textContent = String(db.status || '--');
                if (dbConnections) dbConnections.textContent = String(Number(db.connections || 0));

                const webStatusEl = document.getElementById('alertsWebStatus');
                if (webStatusEl) {
                    webStatusEl.textContent = webStatus;
                    const online = webStatusRaw !== 'offline';
                    webStatusEl.classList.toggle('text-emerald-500', online);
                    webStatusEl.classList.toggle('text-red-500', !online);
                    webStatusEl.classList.toggle('text-slate-500', false);
                }

                const openPortsEl = document.getElementById('alertsOpenPorts');
                if (openPortsEl) openPortsEl.textContent = String(openPorts) + ' LISTEN';
            } catch (err) {
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('Métricas do sistema indisponíveis no momento.', 'warning', { title: 'Alertas' });
                }
            }
        }

        window.addEventListener('load', function () {
            var loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
            loadAlertsMetrics();
        });
    </script>
</body>

</html>