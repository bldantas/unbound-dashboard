<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';
require_once 'src/UnboundManager.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) { header('Location: index.php'); exit; }

// 1. Hardware Metrics
$disk = 0; $diskFree = 0; $diskTotal = 0;
$diskOut = [];
$tmpRet = 0;
\App\ShellHelper::exec('/bin/df', ['--output=pcent,avail,size', '/'], $diskOut, $tmpRet, false);
if (!empty($diskOut[1])) {
    $parts = preg_split('/\s+/', trim($diskOut[1]));
    $diskUsePct = $parts[0] ?? '0%';
    $diskFree = $parts[1] ?? '0';
    $diskTotal = $parts[2] ?? '0';
    $disk = intval(str_replace('%', '', $diskUsePct));
}

$mem = 0;
$memOut = [];
\App\ShellHelper::exec('/usr/bin/free', ['-m'], $memOut, $tmpRet, false);
if (!empty($memOut)) {
    foreach ($memOut as $line) {
        if (str_starts_with(trim($line), 'Mem:')) {
            $parts = preg_split('/\s+/', trim($line));
            $used = intval($parts[2] ?? 0);
            $total = intval($parts[1] ?? 0);
            if ($total > 0) {
                $mem = round(($used / $total) * 100, 2);
            }
            break;
        }
    }
}

$load = sys_getloadavg();

// 2. Unbound Physical Status
$unboundManager = new \App\UnboundManager();
$unboundActive = $unboundManager->isServiceRunning();

// 3. Components & Permissions Audit
$components = [
    ['name' => 'Diretório /etc/unbound', 'path' => '/etc/unbound', 'type' => 'dir'],
    ['name' => 'Configuração (unbound.conf)', 'path' => '/etc/unbound/unbound.conf', 'type' => 'file'],
    ['name' => 'Chaves TLS (RNDC Remote)', 'path' => '/etc/unbound/unbound_control.pem', 'type' => 'file'],
    ['name' => 'DNSSEC Root Anchors', 'path' => '/var/lib/unbound/root.key', 'type' => 'file'],
    ['name' => 'Arquivo de Log (Daemon)', 'path' => '/var/log/unbound.log', 'type' => 'file'],
    ['name' => 'Permissões Sudo (Dashboard)', 'path' => '/etc/sudoers.d/unbound-dashboard', 'type' => 'file']
];

$auditResults = [];
foreach ($components as $comp) {
    $exists = false;
    $perms = '---';
    $owner = '---';
    
    // Check existence and stats via ls to bypass PHP restriction of open_basedir if any
    $lsOut = [];
    \App\ShellHelper::exec('/bin/ls', ['-ld', $comp['path']], $lsOut, $tmpRet, false);
    
    if (!empty($lsOut[0])) {
        $exists = true;
        $parts = preg_split('/\s+/', $lsOut[0]);
        $perms = $parts[0] ?? '???';
        $owner = ($parts[2] ?? '???') . ':' . ($parts[3] ?? '???');
    }
    
    $auditResults[] = [
        'name' => $comp['name'],
        'path' => $comp['path'],
        'exists' => $exists,
        'perms' => $perms,
        'owner' => $owner
    ];
}

// 4. Database Integrity Audit
try {
    $db = \App\Database::getInstance();
    $dbStatus = true;
    $tables = ['users', 'query_logs', 'domain_blacklist', 'daily_stats'];
    foreach ($tables as $t) {
        $check = $db->query("SHOW TABLES LIKE '$t'")->fetch();
        $auditResults[] = [
            'name' => "Tabela SQL: $t",
            'path' => "Database: unbound_dash",
            'exists' => !empty($check),
            'perms' => 'DRW-',
            'owner' => 'mariadb:unbound'
        ];
    }
} catch (\Exception $e) {
    $auditResults[] = [
        'name' => 'Conexão MariaDB',
        'path' => 'localhost:3306',
        'exists' => false,
        'perms' => 'ERR!',
        'owner' => 'offline'
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
            <header class="flex justify-between items-center mb-10">
                <div class="hidden">
                    <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Auditoria & Saúde do Sistema
                    </h1>
                    <p class="text-slate-400 mt-1">Verificação de componentes, permissões e integridade do serviço.</p>
                </div>
                
                <button onclick="runHealthFix()" id="fixBtn" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-emerald-900/40 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Executar Auto-Reparo
                </button>
            </header>


            <!-- Status de Hardware & Daemon -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="glass-panel p-6 rounded-2xl border border-slate-200 dark:border-white/5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full <?= $unboundActive ? 'bg-emerald-500/20 text-emerald-500 dark:text-emerald-400' : 'bg-red-500/20 text-red-500 dark:text-red-400' ?> flex items-center justify-center border border-current opacity-80">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-black tracking-widest">Serviço Unbound</div>
                        <div class="text-xl font-black <?= $unboundActive ? 'text-emerald-500 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' ?>"><?= $unboundActive ? 'Rodando' : 'Parado/Erro' ?></div>
                    </div>
                </div>

                
                <div class="glass-panel p-6 rounded-2xl border border-slate-200 dark:border-white/5">
                    <div class="text-xs text-slate-500 uppercase font-black mb-1 tracking-widest">Carga do Sistema</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white"><?= $load[0] ?> <span class="text-sm font-normal text-slate-500 ml-1">Média 1m</span></div>
                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-blue-500 h-full rounded-full transition-all duration-1000" style="width: <?= min(100, $load[0]*10) ?>%"></div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-2xl border border-slate-200 dark:border-white/5">
                    <div class="text-xs text-slate-500 uppercase font-black mb-1 tracking-widest">Memória RAM</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white"><?= $mem ?>%</div>
                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-purple-500 h-full rounded-full transition-all duration-1000" style="width: <?= $mem ?>%"></div>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <!-- Auditoria de Componentes -->
                <div class="glass-panel rounded-3xl border border-slate-200 dark:border-white/5 overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-slate-900/50 flex items-center justify-between">
                        <h2 class="text-xs font-black text-slate-900 dark:text-slate-200 uppercase tracking-widest">Checklist de Integridade</h2>
                        <span class="text-[9px] font-black bg-blue-500/20 text-blue-500 dark:text-blue-400 px-2 py-0.5 rounded border border-blue-500/30 tracking-widest uppercase">Ativo</span>
                    </div>

                    <div class="p-4">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-500 text-[10px] uppercase font-bold border-b border-white/5">
                                    <th class="pb-3 px-2">Componente</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Permissões</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($auditResults as $res): ?>
                                <tr class="audit-row transition-colors hover:bg-slate-900/5 dark:hover:bg-white/5">
                                    <td class="py-4 px-2">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= $res['name'] ?></div>
                                        <div class="text-[10px] font-mono text-slate-500"><?= $res['path'] ?></div>
                                    </td>

                                    <td class="py-4">
                                        <?php if ($res['exists']): ?>
                                            <span class="text-emerald-500 font-bold flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                OK
                                            </span>
                                        <?php else: ?>
                                            <span class="text-red-500 font-bold flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                                MIS
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 text-right font-mono text-xs text-slate-400">
                                        <?= $res['perms'] ?> 
                                        <div class="text-[9px] text-slate-500"><?= $res['owner'] ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Terminal Output do Health Fix -->
                <div class="glass-panel rounded-3xl border border-slate-200 dark:border-white/5 overflow-hidden flex flex-col h-full min-h-[400px]">
                    <div class="bg-slate-900 dark:bg-black/60 px-4 py-3 flex items-center justify-between border-b border-slate-800 dark:border-white/5">
                        <div class="flex items-center gap-2">
                             <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                             <div class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                             <div class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                        </div>
                        <span class="text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-widest">Console de Reparo</span>
                    </div>

                    <div id="fixTerminal" class="flex-1 p-6 bg-[#0a0a0a] overflow-auto font-mono text-xs text-slate-500 italic">
                        Aguardando execução da rotina de reparo em '/usr/local/bin/unbound-health-fix.sh'...
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
                variant: 'warning'
            });
            if (!confirmed) return;
            
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            term.innerHTML = '<span class="text-blue-400 animate-pulse">Iniciando script de reparo via Sudo...</span>\n';
            term.classList.remove('italic');
            term.classList.add('text-slate-300');

            try {
                const response = await fetch('api/fix_health.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await response.json();
                
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
    </script>
</body>
</html>
