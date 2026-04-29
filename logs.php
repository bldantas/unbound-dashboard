<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

// Função para detectar arquivo de syslog
function detectSyslogPath() {
    $possiblePaths = [
        '/var/log/syslog',      // Debian, Ubuntu
        '/var/log/messages',    // CentOS, RHEL, Fedora
        '/var/log/system.log',  // macOS
        '/var/log/all.log',     // Alpine
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }
    }

    // Fallback: retorna o primeiro que não existe mas é comum
    return '/var/log/syslog';
}

// Função para detectar arquivo de log do Unbound
function detectUnboundLogPath() {
    $possiblePaths = [
        '/var/log/unbound.log',
        '/var/log/unbound/unbound.log',
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }
    }

    // Fallback: tenta journalctl
    return 'journalctl';
}

// Renderiza shell inicial imediatamente para reduzir percepcao de travamento
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title>Logs - Unbound DNS</title>
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
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando logs em tempo real...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
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

$logFile = $_GET['file'] ?? 'syslog';
$content = '';
$fileTitle = 'Syslog Geral (/var/log/syslog)';
$isLiveCapture = false;

if ($logFile === 'live') {
    $fileTitle = 'Live Query Sniffer (Em Tempo Real)';
    $isLiveCapture = true;
} elseif ($logFile === 'unbound') {
    $fileTitle = 'Daemon Logs (Unbound)';
    $unboundLogPath = detectUnboundLogPath();
    
    if ($unboundLogPath === 'journalctl') {
        \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'unbound', '-n', '300', '--no-pager'], $out, $tmpRet, true);
    } else {
        \App\ShellHelper::exec('/usr/bin/tail', ['-n', '300', $unboundLogPath], $out, $tmpRet, true);
    }
    
    if (empty($out)) {
        $content = '<!-- Nenhum log disponível -->';
    } else {
        $content = implode("\n", $out);
    }
} else {
    // Syslog - detecta o caminho correto
    $syslogPath = detectSyslogPath();
    $fileTitle = "Syslog Geral ($syslogPath)";
    
    if (file_exists($syslogPath)) {
        \App\ShellHelper::exec('/usr/bin/tail', ['-n', '300', $syslogPath], $out, $tmpRet, true);
        if (!empty($out)) {
            $content = implode("\n", $out);
        } else {
            $content = "<!-- Arquivo exists mas está vazio ou sem permissão de leitura -->";
        }
    } else {
        $content = "<!-- Arquivo não encontrado: $syslogPath -->\n<!-- Caminhos procurados: -->\n<!-- " . implode("\n<!-- ", ['/var/log/syslog', '/var/log/messages', '/var/log/system.log', '/var/log/all.log']) . " -->";
    }
}

$currentPage = 'logs.php';
?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php 
        $pageTitle = "Inspeção de Logs";
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container">


                <div class="flex bg-slate-200 dark:bg-slate-900 border border-slate-300 dark:border-white/5 rounded-2xl overflow-hidden p-1 shadow-inner">
                    <a href="logs.php?file=syslog" class="<?= $logFile === 'syslog' ? 'bg-blue-600/20 text-blue-600 dark:text-blue-400 border border-blue-500/30' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 border border-transparent' ?> px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest">
                        Syslog O.S.
                    </a>
                    <a href="logs.php?file=unbound" class="<?= $logFile === 'unbound' ? 'bg-emerald-600/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 border border-transparent' ?> px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest">
                        Unbound Daemon
                    </a>
                    <a href="logs.php?file=live" class="<?= $logFile === 'live' ? 'bg-purple-600/20 text-purple-600 dark:text-purple-400 border border-purple-500/30' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 border border-transparent' ?> px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?= $logFile === 'live' ? 'bg-purple-500 animate-pulse' : 'bg-transparent' ?>"></span> Live Sniffer
                    </a>
                </div>
            </header>

            <div class="glass-panel !p-0 overflow-hidden flex flex-col h-[70vh] border-slate-200 dark:border-white/5">
                <div class="bg-slate-900/5 dark:bg-white/5 px-6 py-4 border-b border-slate-900/10 dark:border-white/5 flex items-center justify-between">
                    <h2 class="text-xs font-black text-slate-700 dark:text-slate-300 tracking-widest uppercase flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <?= htmlspecialchars($fileTitle) ?>
                    </h2>

                    <button onclick="window.location.reload()" class="text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Recarregar
                    </button>
                </div>
                <?php if ($logFile === 'live'): ?>
                    <div class="flex-1 overflow-auto bg-black p-6 font-mono text-[11px] leading-relaxed text-green-500/90" id="liveContainer">
                        <div id="liveStream" class="flex flex-col gap-1">Inicializando interceptador de pacotes...<br><br></div>
                    </div>
                <?php else: ?>
                    <div class="flex-1 overflow-auto bg-black/40 p-6 font-mono text-[11px] leading-relaxed <?= $logFile === 'unbound' ? 'text-emerald-400/90' : 'text-slate-300/90' ?>" id="logContainer">
                        <pre class="whitespace-pre-wrap"><?= htmlspecialchars($content) ?></pre>
                    </div>
                <?php endif; ?>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        window.addEventListener('load', function () {
            var loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
        });

        <?php if ($logFile === 'live'): ?>
            const liveStream = document.getElementById('liveStream');
            const liveContainer = document.getElementById('liveContainer');
            let lastLines = new Set();

            async function pollLiveLogs() {
                try {
                    const res = await fetch('api/live_log.php');
                    const json = await res.json();
                    if (json.status === 'success') {
                        json.data.forEach(q => {
                            if (!lastLines.has(q.raw)) {
                                lastLines.add(q.raw);
                                if (lastLines.size > 200) lastLines.delete(lastLines.values().next().value); // Evita memory leak (mantém set baixo)

                                const div = document.createElement('div');
                                div.className = "animate-fade-in";
                                if (q.type === 'query') {
                                    div.innerHTML = `<span class="text-green-700">[QUERY]</span> <span class="text-blue-400">${q.client}</span> <span class="text-slate-400">pediu</span> <span class="text-white font-bold">${q.domain}</span> <span class="text-purple-400">(${q.qtype})</span>`;
                                } else if (q.type === 'reply') {
                                    const rcodeColor = q.rcode === 'NOERROR' ? 'text-green-400' : 'text-red-500';
                                    div.innerHTML = `<span class="text-slate-600">[REPLY]</span> <span class="text-blue-400">${q.client}</span> <span class="text-slate-500">←</span> <span class="text-white font-bold">${q.domain}</span> <span class="${rcodeColor} font-black">${q.rcode}</span> <span class="text-yellow-500">(${q.time}s)</span>`;
                                }
                                liveStream.appendChild(div);
                                if (liveStream.childElementCount > 150) liveStream.removeChild(liveStream.firstChild);
                                liveContainer.scrollTop = liveContainer.scrollHeight; // Auto-scroll
                            }
                        });
                    }
                } catch (e) {}
                setTimeout(pollLiveLogs, 1500); // Polling amigável a cada 1.5s
            }
            pollLiveLogs();
        <?php else: ?>
            const logContainer = document.getElementById('logContainer');
            logContainer.scrollTop = logContainer.scrollHeight;
        <?php endif; ?>
    </script>
</body>

</html>