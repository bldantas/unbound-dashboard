<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';
require_once 'src/UnboundManager.php';

\App\Auth::check();
if (!\App\Auth::can('dashboard.read')) { header('Location: index.php'); exit; }

$tmpRet = 0;

// 1. Hardware Metrics
$disk = 0; $diskFree = '?'; $diskTotal = '?';
$diskOut = [];
\App\ShellHelper::exec('/bin/df', ['--output=pcent,avail,size', '/'], $diskOut, $tmpRet, false);
if (!empty($diskOut[1])) {
    $parts = preg_split('/\s+/', trim($diskOut[1]));
    $diskUsePct = $parts[0] ?? '0%';
    $diskFree = $parts[1] ?? '0';
    $diskTotal = $parts[2] ?? '0';
    $disk = intval(str_replace('%', '', $diskUsePct));
}

$mem = 0;
$memUsedMb = 0;
$memTotalMb = 0;
$memOut = [];
\App\ShellHelper::exec('/usr/bin/free', ['-m'], $memOut, $tmpRet, false);
if (!empty($memOut)) {
    foreach ($memOut as $line) {
        if (str_starts_with(trim($line), 'Mem:')) {
            $parts = preg_split('/\s+/', trim($line));
            $memUsedMb = intval($parts[2] ?? 0);
            $memTotalMb = intval($parts[1] ?? 0);
            if ($memTotalMb > 0) $mem = round(($memUsedMb / $memTotalMb) * 100, 1);
            break;
        }
    }
}

$load = sys_getloadavg();

// 2. Uptime do sistema (uptime -p / -s)
$uptimeOut = [];
\App\ShellHelper::exec('/usr/bin/uptime', ['-p'], $uptimeOut, $tmpRet, false);
$systemUptime = trim($uptimeOut[0] ?? '?');
$bootOut = [];
\App\ShellHelper::exec('/usr/bin/uptime', ['-s'], $bootOut, $tmpRet, false);
$systemBoot = trim($bootOut[0] ?? '?');

// 3. Unbound Physical Status
$unboundManager = new \App\UnboundManager();
$unboundActive = $unboundManager->isServiceRunning();

// 4. Detecta service PHP-FPM (varia por versão)
function detectPhpFpmServiceForHealth(): ?string {
    $out = [];
    \App\ShellHelper::exec('/usr/bin/systemctl', ['list-unit-files', '--type=service', '--no-legend', '--no-pager'], $out, $r, false);
    foreach ($out as $line) {
        if (preg_match('/^(php[0-9.]+-fpm)\.service/', trim($line), $m)) return $m[1];
    }
    return null;
}
$phpFpmService = detectPhpFpmServiceForHealth();

// 5. Serviços systemd
$systemdServices = [
    ['name' => 'Daemon Unbound',         'unit' => 'unbound.service',                 'icon' => 'shield'],
    ['name' => 'API (FastAPI/DuckDB)',   'unit' => 'unbound-dashboard-api.service',   'icon' => 'api'],
    ['name' => 'Cache Redis',            'unit' => 'redis-server.service',            'icon' => 'db'],
    ['name' => 'Web Server (Apache)',    'unit' => 'apache2.service',                 'icon' => 'web'],
];
if ($phpFpmService !== null) {
    $systemdServices[] = ['name' => 'PHP-FPM', 'unit' => $phpFpmService . '.service', 'icon' => 'php'];
}
$serviceResults = [];
foreach ($systemdServices as $svc) {
    $stOut = [];
    \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', $svc['unit']], $stOut, $tmpRet, false);
    $state = trim($stOut[0] ?? 'unknown');

    // Uptime do serviço (ActiveEnterTimestamp)
    $upOut = [];
    \App\ShellHelper::exec('/usr/bin/systemctl', ['show', $svc['unit'], '--property=ActiveEnterTimestamp', '--value'], $upOut, $tmpRet, false);
    $upSince = trim($upOut[0] ?? '');

    $serviceResults[] = [
        'name' => $svc['name'],
        'unit' => $svc['unit'],
        'active' => $state === 'active',
        'state' => $state,
        'since' => $upSince,
    ];
}

// 6. Versões dos componentes (snapshot rápido — útil pra suporte/debug)
function getCommandVersion(string $cmd, array $args, string $pattern, int $group = 1): string {
    $out = [];
    \App\ShellHelper::exec($cmd, $args, $out, $r, false);
    foreach ($out as $line) {
        if (preg_match($pattern, $line, $m)) return $m[$group] ?? '?';
    }
    return '?';
}
$versions = [
    'PHP'       => getCommandVersion('/usr/bin/php', ['-v'], '/PHP (\d+\.\d+\.\d+)/'),
    'Python'    => getCommandVersion('/usr/bin/python3', ['--version'], '/Python (\d+\.\d+\.\d+)/'),
    'Apache'    => getCommandVersion('/usr/sbin/apache2', ['-v'], '/Apache\/(\d+\.\d+\.\d+)/'),
    'Unbound'   => getCommandVersion('/usr/sbin/unbound', ['-V'], '/Version (\d+\.\d+\.\d+)/'),
    'Redis'     => getCommandVersion('/usr/bin/redis-server', ['--version'], '/v=(\d+\.\d+\.\d+)/'),
    'DuckDB'    => '—',
];
// Tenta versão DuckDB via venv
$duckOut = [];
$pythonVenv = __DIR__ . '/api_service/.venv/bin/python';
if (is_executable($pythonVenv)) {
    \App\ShellHelper::exec($pythonVenv, ['-c', 'import duckdb; print(duckdb.__version__)'], $duckOut, $tmpRet, false);
    if (!empty($duckOut[0])) $versions['DuckDB'] = trim($duckOut[0]);
}
$dashboardVersion = file_exists(__DIR__ . '/VERSION') ? trim(file_get_contents(__DIR__ . '/VERSION')) : '?';

// 7. Healthz da FastAPI: HTTP status + tempo de resposta
$healthz = ['status' => 'unknown', 'http' => 0, 'ms' => 0, 'body' => ''];
$ch = curl_init('http://127.0.0.1:8001/api/v1/healthz');
if ($ch !== false) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 2,
    ]);
    $t0 = microtime(true);
    $body = curl_exec($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $healthz = [
        'status' => ($body !== false && $http === 200) ? 'ok' : 'fail',
        'http' => $http,
        'ms' => $ms,
        'body' => is_string($body) ? trim(substr($body, 0, 200)) : '',
    ];
}

// 8. Components & Permissions Audit
$components = [
    ['name' => 'Diretório /etc/unbound',          'path' => '/etc/unbound',                                         'type' => 'dir'],
    ['name' => 'Configuração (unbound.conf)',     'path' => '/etc/unbound/unbound.conf',                            'type' => 'file'],
    ['name' => 'Chaves TLS (RNDC Remote)',        'path' => '/etc/unbound/unbound_control.pem',                     'type' => 'file'],
    ['name' => 'DNSSEC Root Anchors',             'path' => '/var/lib/unbound/root.key',                            'type' => 'file'],
    ['name' => 'Arquivo de Log (Daemon)',         'path' => '/var/log/unbound.log',                                 'type' => 'file'],
    ['name' => 'Permissões Sudo (Dashboard)',     'path' => '/etc/sudoers.d/unbound-dashboard',                     'type' => 'file'],
    ['name' => 'Banco DuckDB',                    'path' => '/var/lib/unbound-dashboard/unbound_dash.duckdb',       'type' => 'file'],
    ['name' => 'Env do api_service',              'path' => '/etc/unbound-dashboard/api-v1.env',                    'type' => 'file'],
    ['name' => 'Diretório de backups',            'path' => '/var/backups/unbound-dashboard',                       'type' => 'dir'],
    ['name' => 'Flag .installed',                 'path' => '/var/www/html/unbound-dashboard/data/.installed',      'type' => 'file'],
];
$auditResults = [];
$auditOkCount = 0;
foreach ($components as $comp) {
    $lsOut = [];
    \App\ShellHelper::exec('/bin/ls', ['-ld', $comp['path']], $lsOut, $tmpRet, false);
    $exists = false; $perms = '---'; $owner = '---';
    if (!empty($lsOut[0])) {
        $exists = true;
        $auditOkCount++;
        $parts = preg_split('/\s+/', $lsOut[0]);
        $perms = $parts[0] ?? '???';
        $owner = ($parts[2] ?? '???') . ':' . ($parts[3] ?? '???');
    }
    $auditResults[] = [
        'name' => $comp['name'],
        'path' => $comp['path'],
        'exists' => $exists,
        'perms' => $perms,
        'owner' => $owner,
    ];
}

$currentPage = 'health.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Saúde - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Auditoria & Saúde do Sistema";
        include 'includes/topbar.php';
        ?>
        <div class="page-container relative z-10">
            <header class="flex items-start justify-between mb-6 flex-wrap gap-4">
                <div>
                    <p class="text-sm text-slate-500 mt-1">Snapshot de hardware, serviços, versões e integridade. Última verificação: <span class="font-mono"><?= date('H:i:s') ?></span>.</p>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest cursor-pointer">
                        <input type="checkbox" id="healthAutoRefresh" class="w-4 h-4 rounded">
                        Auto-refresh 30s
                    </label>
                    <button onclick="runHealthFix()" id="fixBtn" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Auto-Reparo
                    </button>
                </div>
            </header>

            <!-- Hardware + Healthz cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-panel">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Carga (1m)</p>
                    <p class="text-2xl font-black text-blue-500"><?= htmlspecialchars((string)$load[0]) ?></p>
                    <div class="w-full bg-slate-200 dark:bg-white/5 h-1 rounded-full mt-2 overflow-hidden">
                        <div class="bg-blue-500 h-full rounded-full" style="width: <?= min(100, $load[0] * 25) ?>%"></div>
                    </div>
                    <p class="text-[9px] text-slate-500 mt-1 uppercase tracking-widest font-bold">5m: <?= htmlspecialchars((string)$load[1]) ?> · 15m: <?= htmlspecialchars((string)$load[2]) ?></p>
                </div>
                <div class="glass-panel">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">RAM</p>
                    <p class="text-2xl font-black text-emerald-500"><?= $mem ?>%</p>
                    <div class="w-full bg-slate-200 dark:bg-white/5 h-1 rounded-full mt-2 overflow-hidden">
                        <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $mem ?>%"></div>
                    </div>
                    <p class="text-[9px] text-slate-500 mt-1 uppercase tracking-widest font-bold"><?= $memUsedMb ?> / <?= $memTotalMb ?> MB</p>
                </div>
                <div class="glass-panel">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Disco /</p>
                    <p class="text-2xl font-black text-purple-500"><?= $disk ?>%</p>
                    <div class="w-full bg-slate-200 dark:bg-white/5 h-1 rounded-full mt-2 overflow-hidden">
                        <div class="bg-purple-500 h-full rounded-full" style="width: <?= $disk ?>%"></div>
                    </div>
                    <p class="text-[9px] text-slate-500 mt-1 uppercase tracking-widest font-bold">Livre: <?= htmlspecialchars($diskFree) ?></p>
                </div>
                <div class="glass-panel border-l-4 <?= $healthz['status'] === 'ok' ? 'border-emerald-500' : 'border-red-500' ?>">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">API /healthz</p>
                    <p class="text-2xl font-black <?= $healthz['status'] === 'ok' ? 'text-emerald-500' : 'text-red-500' ?>">
                        <?= htmlspecialchars(strtoupper($healthz['status'])) ?>
                    </p>
                    <p class="text-[9px] text-slate-500 mt-1 uppercase tracking-widest font-bold">HTTP <?= $healthz['http'] ?: '—' ?> · <?= $healthz['ms'] ?> ms</p>
                    <p class="text-[9px] font-mono text-slate-500 mt-1 truncate" title="<?= htmlspecialchars($healthz['body']) ?>"><?= htmlspecialchars($healthz['body']) ?></p>
                </div>
            </div>

            <!-- Uptime + Versões -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div class="glass-panel">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Sistema</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">Uptime: <span class="font-mono text-emerald-500"><?= htmlspecialchars($systemUptime) ?></span></p>
                    <p class="text-[10px] text-slate-500 mt-1 font-mono">Booted: <?= htmlspecialchars($systemBoot) ?></p>
                    <p class="text-[10px] text-slate-500 mt-1 font-mono">Host: <?= htmlspecialchars(gethostname() ?: '?') ?></p>
                </div>
                <div class="glass-panel lg:col-span-2">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Versões dos Componentes</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                        <?php foreach ($versions as $name => $ver): ?>
                            <div class="flex items-center justify-between bg-slate-900/5 dark:bg-white/5 px-3 py-2 rounded-xl">
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($name) ?></span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($ver) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="flex items-center justify-between bg-blue-500/10 px-3 py-2 rounded-xl border border-blue-500/20 col-span-2 sm:col-span-4 mt-1">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Unbound Dashboard</span>
                            <span class="font-mono font-bold text-blue-500">v<?= htmlspecialchars($dashboardVersion) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Serviços + Checklist -->
                <div class="glass-panel !p-0 overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-slate-900/50 flex items-center justify-between">
                        <h2 class="text-xs font-black uppercase tracking-widest">Checklist de Integridade</h2>
                        <span class="text-[9px] font-black bg-emerald-500/10 text-emerald-500 px-2 py-1 rounded border border-emerald-500/30 tracking-widest uppercase">
                            <?= $auditOkCount ?>/<?= count($auditResults) ?> OK
                        </span>
                    </div>

                    <div class="p-4">
                        <div class="mb-4 pb-3 border-b border-slate-200 dark:border-white/5">
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-500 mb-2 px-2">Serviços Systemd (<?= count($serviceResults) ?>)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 px-2">
                                <?php foreach ($serviceResults as $svc): ?>
                                    <?php
                                        $sinceShort = '';
                                        if (!empty($svc['since']) && $svc['since'] !== 'n/a') {
                                            try {
                                                $sinceShort = (new DateTime($svc['since']))->format('d/m H:i');
                                            } catch (Exception $e) { $sinceShort = ''; }
                                        }
                                    ?>
                                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-900/5 dark:bg-white/5 border border-slate-200 dark:border-white/5">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-xs text-slate-900 dark:text-white truncate"><?= htmlspecialchars($svc['name']) ?></p>
                                            <p class="text-[9px] font-mono text-slate-500 truncate"><?= htmlspecialchars($svc['unit']) ?></p>
                                            <?php if ($sinceShort): ?>
                                                <p class="text-[9px] text-slate-500 font-mono">↑ <?= htmlspecialchars($sinceShort) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full border <?= $svc['active'] ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20' ?> ml-2">
                                            <?= htmlspecialchars($svc['state']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <p class="text-[10px] uppercase font-black tracking-widest text-slate-500 mb-2 px-2">Componentes (<?= count($auditResults) ?>)</p>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-500 text-[10px] uppercase font-bold border-b border-slate-200 dark:border-white/5">
                                    <th class="pb-2 px-2">Componente</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2 text-right">Permissões</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                                <?php foreach ($auditResults as $res): ?>
                                    <tr>
                                        <td class="py-3 px-2">
                                            <p class="font-bold text-xs text-slate-900 dark:text-white"><?= htmlspecialchars($res['name']) ?></p>
                                            <p class="text-[9px] font-mono text-slate-500"><?= htmlspecialchars($res['path']) ?></p>
                                        </td>
                                        <td class="py-3">
                                            <?php if ($res['exists']): ?>
                                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">OK</span>
                                            <?php else: ?>
                                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full bg-red-500/10 text-red-500 border border-red-500/20">MIS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-right font-mono text-[10px] text-slate-500">
                                            <?= htmlspecialchars($res['perms']) ?>
                                            <p class="text-[9px] text-slate-500"><?= htmlspecialchars($res['owner']) ?></p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Terminal Output -->
                <div class="glass-panel !p-0 overflow-hidden flex flex-col min-h-[400px]">
                    <div class="bg-slate-900 dark:bg-black/60 px-4 py-3 flex items-center justify-between border-b border-slate-800 dark:border-white/5">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                        </div>
                        <span class="text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-widest">Console de Reparo</span>
                    </div>
                    <div id="fixTerminal" class="flex-1 p-6 bg-[#0a0a0a] overflow-auto font-mono text-xs text-slate-500 italic">
                        Aguardando execução de '/usr/local/bin/unbound-health-fix.sh'. Roda <code class="text-slate-400">chown</code> + <code class="text-slate-400">chmod</code> + cria chaves TLS faltantes.
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        async function runHealthFix() {
            const btn = document.getElementById('fixBtn');
            const term = document.getElementById('fixTerminal');
            const confirmed = await window.AppUI.confirm({
                title: 'Executar auto-reparo',
                message: 'Deseja executar a rotina de auto-reparo de permissões e chaves agora?',
                confirmText: 'Executar',
                cancelText: 'Cancelar',
                variant: 'warning',
            });
            if (!confirmed) return;
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            term.innerHTML = '<span class="text-blue-400 animate-pulse">Iniciando script de reparo via Sudo...</span>\n';
            term.classList.remove('italic');
            term.classList.add('text-slate-300');
            try {
                const res = await fetch('api/fix_health.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
                const data = await res.json();
                term.innerHTML += data.log ? data.log : 'Sem log retornado.';
                if (data.success) {
                    term.innerHTML += '\n\n<span class="text-emerald-500 font-bold">✓ PROCESSO CONCLUÍDO COM SUCESSO.</span>';
                    window.AppUI.toast('Rotina de auto-reparo concluída com sucesso.', 'success', { title: 'Auto-reparo' });
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    term.innerHTML += '\n\n<span class="text-red-500 font-bold">✗ ERRO NA EXECUÇÃO: ' + (data.error || data.message) + '</span>';
                    window.AppUI.toast(data.error || data.message || 'Falha ao executar a rotina de auto-reparo.', 'error', { title: 'Auto-reparo' });
                }
            } catch (err) {
                term.innerHTML += '\n\n<span class="text-red-500 font-bold">✗ ERRO DE CONEXÃO COM A API.</span>';
                window.AppUI.toast('Erro de conexão com a API de auto-reparo.', 'error', { title: 'Auto-reparo' });
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Auto-refresh (30s) — recarrega a página inteira pra trazer snapshot novo
        let healthRefreshTimer = null;
        document.getElementById('healthAutoRefresh').addEventListener('change', function () {
            if (this.checked) {
                healthRefreshTimer = setInterval(() => window.location.reload(), 30000);
            } else {
                clearInterval(healthRefreshTimer);
                healthRefreshTimer = null;
            }
        });
    </script>
</body>
</html>
