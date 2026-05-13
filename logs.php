<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::can('blocklist.read')) {
    header('Location: index.php');
    exit;
}

// Detecta arquivo de syslog em vários SOs.
function detectSyslogPath(): string {
    $paths = ['/var/log/syslog', '/var/log/messages', '/var/log/system.log', '/var/log/all.log'];
    foreach ($paths as $p) {
        if (file_exists($p) && is_readable($p)) return $p;
    }
    return '/var/log/syslog';
}

// Detecta arquivo de log do Unbound, com fallback pra journalctl.
function detectUnboundLogPath(): string {
    $paths = ['/var/log/unbound.log', '/var/log/unbound/unbound.log'];
    foreach ($paths as $p) {
        if (file_exists($p) && is_readable($p)) return $p;
    }
    return 'journalctl';
}

// Detecta nome do service PHP-FPM (phpX.Y-fpm) — depende da versão instalada.
function detectPhpFpmService(): ?string {
    $out = [];
    \App\ShellHelper::exec('/usr/bin/systemctl', ['list-unit-files', '--type=service', '--no-legend', '--no-pager'], $out, $ret, false);
    foreach ($out as $line) {
        if (preg_match('/^(php[0-9.]+-fpm)\.service/', trim($line), $m)) {
            return $m[1];
        }
    }
    return null;
}

// -- Parâmetros --
$logFile = $_GET['file'] ?? 'syslog';
$linesParam = max(50, min(5000, (int) ($_GET['lines'] ?? 300)));

$validSources = ['syslog', 'unbound', 'api', 'apache', 'phpfpm', 'live'];
if (!in_array($logFile, $validSources, true)) $logFile = 'syslog';

$content = '';
$fileTitle = 'Syslog Geral';
$accentColor = 'slate';
$isLiveCapture = ($logFile === 'live');

if ($logFile === 'live') {
    $fileTitle = 'Live Query Sniffer (Em Tempo Real)';
    $accentColor = 'purple';

} elseif ($logFile === 'unbound') {
    $unboundLogPath = detectUnboundLogPath();
    $fileTitle = $unboundLogPath === 'journalctl'
        ? "Daemon Unbound (journalctl -u unbound)"
        : "Daemon Unbound ($unboundLogPath)";
    $accentColor = 'emerald';
    if ($unboundLogPath === 'journalctl') {
        \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'unbound', '-n', (string)$linesParam, '--no-pager'], $out, $tmpRet, false);
    } else {
        \App\ShellHelper::exec('/usr/bin/tail', ['-n', (string)$linesParam, $unboundLogPath], $out, $tmpRet, false);
    }
    $content = !empty($out) ? implode("\n", $out) : '<!-- Nenhum log disponível -->';

} elseif ($logFile === 'api') {
    $fileTitle = "API FastAPI (journalctl -u unbound-dashboard-api)";
    $accentColor = 'blue';
    \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'unbound-dashboard-api', '-n', (string)$linesParam, '--no-pager'], $out, $tmpRet, false);
    $content = !empty($out) ? implode("\n", $out) : '<!-- Sem logs (api_service rodando?) -->';

} elseif ($logFile === 'apache') {
    $fileTitle = "Web Server (journalctl -u apache2)";
    $accentColor = 'orange';
    \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'apache2', '-n', (string)$linesParam, '--no-pager'], $out, $tmpRet, false);
    $content = !empty($out) ? implode("\n", $out) : '<!-- Sem logs do apache2 -->';

} elseif ($logFile === 'phpfpm') {
    $phpFpmSvc = detectPhpFpmService();
    if ($phpFpmSvc === null) {
        $fileTitle = "PHP-FPM (não detectado)";
        $content = '<!-- Nenhum serviço phpX.Y-fpm encontrado em systemctl list-unit-files -->';
    } else {
        $fileTitle = "PHP-FPM (journalctl -u $phpFpmSvc)";
        \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', $phpFpmSvc, '-n', (string)$linesParam, '--no-pager'], $out, $tmpRet, false);
        $content = !empty($out) ? implode("\n", $out) : "<!-- Sem logs do $phpFpmSvc -->";
    }
    $accentColor = 'pink';

} else {
    // syslog
    $syslogPath = detectSyslogPath();
    $fileTitle = "Syslog ($syslogPath)";
    $accentColor = 'slate';
    if (file_exists($syslogPath)) {
        \App\ShellHelper::exec('/usr/bin/tail', ['-n', (string)$linesParam, $syslogPath], $out, $tmpRet, false);
        $content = !empty($out) ? implode("\n", $out) : "<!-- Arquivo $syslogPath vazio ou sem permissão -->";
    } else {
        $content = "<!-- Arquivo não encontrado: $syslogPath -->";
    }
}

$sources = [
    'syslog'  => ['label' => 'Syslog O.S.',     'color' => 'slate'],
    'unbound' => ['label' => 'Unbound Daemon',  'color' => 'emerald'],
    'api'     => ['label' => 'API FastAPI',     'color' => 'blue'],
    'apache'  => ['label' => 'Apache',          'color' => 'orange'],
    'phpfpm'  => ['label' => 'PHP-FPM',         'color' => 'pink'],
    'live'    => ['label' => 'Live Sniffer',    'color' => 'purple'],
];

$currentPage = 'logs.php';

// Renderiza shell + loader imediatamente
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
        el.innerHTML = '<div class="loader-card"><span class="loader-dot"></span><span>Carregando logs...</span></div><div class="loader-progress-track"><div class="loader-progress-bar"></div></div>';
        document.body.appendChild(el);
    }
    el.classList.add('is-visible');
})();
</script>
<?php
ob_end_flush();
if (function_exists('ob_flush')) ob_flush();
flush();
?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Inspeção de Logs";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <!-- Tab bar: sources -->
            <div class="flex flex-wrap gap-2 mb-4 bg-slate-200 dark:bg-slate-900 border border-slate-300 dark:border-white/5 rounded-2xl overflow-hidden p-1 shadow-inner">
                <?php foreach ($sources as $key => $meta):
                    $isActive = $logFile === $key;
                    $color = $meta['color'];
                    $extras = $key === 'live' ? 'flex items-center gap-2' : '';
                    ?>
                    <a href="logs.php?file=<?= $key ?>&lines=<?= $linesParam ?>"
                       class="<?= $isActive ? "bg-${color}-600/20 text-${color}-600 dark:text-${color}-400 border border-${color}-500/30" : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 border border-transparent' ?> px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest <?= $extras ?>">
                        <?php if ($key === 'live'): ?>
                            <span class="w-2 h-2 rounded-full <?= $isActive ? "bg-${color}-500 animate-pulse" : 'bg-transparent border border-slate-400' ?>"></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($meta['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!$isLiveCapture): ?>
            <!-- Toolbar: busca + filtro de nível + linhas + auto-refresh -->
            <div class="glass-panel mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar no buffer</label>
                        <input type="text" id="logSearch" oninput="filterLogLines()" placeholder="ex: error, 192.168, query" class="glass-input w-full font-mono text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Filtrar nível</label>
                        <select id="logLevel" onchange="filterLogLines()" class="glass-input w-full uppercase text-[10px] font-black">
                            <option value="">TODOS</option>
                            <option value="error">ERROR</option>
                            <option value="warn">WARN</option>
                            <option value="info">INFO</option>
                            <option value="debug">DEBUG</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Linhas</label>
                        <select onchange="changeLines(this.value)" class="glass-input w-full uppercase text-[10px] font-black">
                            <?php foreach ([100, 300, 500, 1000, 2000] as $n): ?>
                                <option value="<?= $n ?>" <?= $linesParam === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-900/10 dark:border-white/5 flex-wrap">
                    <label class="flex items-center gap-2 cursor-pointer text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        <input type="checkbox" id="logAutoRefresh" class="w-4 h-4 rounded">
                        Auto-refresh 5s
                    </label>
                    <p class="text-[10px] text-slate-500">
                        Total: <span id="logCountTotal">0</span> · Visíveis: <span id="logCountVisible">0</span>
                    </p>
                    <span class="ml-auto text-[10px] text-slate-500 font-mono">Última: <span id="logLastRefresh"><?= date('H:i:s') ?></span></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Painel de log -->
            <div class="glass-panel !p-0 overflow-hidden flex flex-col h-[68vh] border-slate-200 dark:border-white/5">
                <div class="bg-slate-900/5 dark:bg-white/5 px-6 py-4 border-b border-slate-900/10 dark:border-white/5 flex items-center justify-between gap-3 flex-wrap">
                    <h2 class="text-xs font-black text-slate-700 dark:text-slate-300 tracking-widest uppercase flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-<?= $accentColor ?>-500 animate-pulse"></span>
                        <?= htmlspecialchars($fileTitle) ?>
                    </h2>
                    <div class="flex items-center gap-2">
                        <?php if ($isLiveCapture): ?>
                            <button type="button" id="livePauseBtn" onclick="toggleLivePause()" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black">⏸ Pause</button>
                            <button type="button" onclick="clearLiveStream()" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black">🗑 Limpar</button>
                        <?php else: ?>
                            <button type="button" onclick="copyLogContent()" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black" id="copyBtn">📋 Copiar</button>
                            <button type="button" onclick="window.location.reload()" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black">↻ Recarregar</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($isLiveCapture): ?>
                    <div class="flex-1 overflow-auto bg-black p-6 font-mono text-[11px] leading-relaxed text-green-500/90" id="liveContainer">
                        <div id="liveStream" class="flex flex-col gap-1">Inicializando interceptador de pacotes...<br><br></div>
                    </div>
                <?php else: ?>
                    <div class="flex-1 overflow-auto bg-black/40 p-4 font-mono text-[11px] leading-relaxed text-slate-300/90" id="logContainer">
                        <div id="logLines"><?php
                            $lines = explode("\n", $content);
                            foreach ($lines as $rawLine) {
                                $line = $rawLine;
                                $level = '';
                                // Detecção simples de nível pra colorização
                                $lower = strtolower($line);
                                if (preg_match('/\b(error|err|fatal|critical|crit)\b/i', $line)) $level = 'error';
                                elseif (preg_match('/\b(warning|warn)\b/i', $line)) $level = 'warn';
                                elseif (preg_match('/\b(debug|trace)\b/i', $line)) $level = 'debug';
                                elseif (preg_match('/\b(info|notice)\b/i', $line)) $level = 'info';
                                $colorClass = '';
                                if ($level === 'error')  $colorClass = 'text-red-400';
                                elseif ($level === 'warn')   $colorClass = 'text-amber-400';
                                elseif ($level === 'info')   $colorClass = 'text-slate-400';
                                elseif ($level === 'debug')  $colorClass = 'text-slate-600';
                                ?>
                                <div class="log-line whitespace-pre-wrap <?= $colorClass ?>" data-level="<?= $level ?>" data-text="<?= htmlspecialchars(strtolower($line)) ?>"><?= htmlspecialchars($line) ?></div>
                            <?php } ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        window.addEventListener('load', function () {
            const loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
        });

        <?php if ($isLiveCapture): ?>
        // -- Live Sniffer com pause/clear --
        const liveStream = document.getElementById('liveStream');
        const liveContainer = document.getElementById('liveContainer');
        let lastLines = new Set();
        let livePaused = false;

        function toggleLivePause() {
            livePaused = !livePaused;
            document.getElementById('livePauseBtn').textContent = livePaused ? '▶ Resume' : '⏸ Pause';
        }
        function clearLiveStream() {
            liveStream.innerHTML = '';
            lastLines.clear();
        }
        // Expostas globalmente pros onclick inline
        window.toggleLivePause = toggleLivePause;
        window.clearLiveStream = clearLiveStream;

        async function pollLiveLogs() {
            if (livePaused) {
                setTimeout(pollLiveLogs, 1500);
                return;
            }
            try {
                const res = await fetch('api/live_log.php');
                const json = await res.json();
                if (json.status === 'success') {
                    json.data.forEach(q => {
                        if (!lastLines.has(q.raw)) {
                            lastLines.add(q.raw);
                            if (lastLines.size > 200) lastLines.delete(lastLines.values().next().value);
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
                            liveContainer.scrollTop = liveContainer.scrollHeight;
                        }
                    });
                }
            } catch (e) {}
            setTimeout(pollLiveLogs, 1500);
        }
        pollLiveLogs();
        <?php else: ?>
        // -- Logs estáticos: filter/search/auto-refresh --
        const logContainer = document.getElementById('logContainer');
        const logLinesEl = document.getElementById('logLines');
        logContainer.scrollTop = logContainer.scrollHeight;

        function filterLogLines() {
            const q = (document.getElementById('logSearch').value || '').trim().toLowerCase();
            const lvl = document.getElementById('logLevel').value;
            const lines = document.querySelectorAll('.log-line');
            let visible = 0;
            lines.forEach(el => {
                const matchQ = !q || el.dataset.text.includes(q);
                const matchLvl = !lvl || el.dataset.level === lvl;
                const show = matchQ && matchLvl;
                el.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            document.getElementById('logCountTotal').textContent = lines.length;
            document.getElementById('logCountVisible').textContent = visible;
        }

        function changeLines(n) {
            const url = new URL(window.location.href);
            url.searchParams.set('lines', n);
            window.location = url.toString();
        }

        function copyLogContent() {
            const visible = Array.from(document.querySelectorAll('.log-line'))
                .filter(el => el.style.display !== 'none')
                .map(el => el.textContent).join('\n');
            navigator.clipboard.writeText(visible).then(() => {
                const btn = document.getElementById('copyBtn');
                const orig = btn.textContent;
                btn.textContent = '✓ Copiado';
                setTimeout(() => btn.textContent = orig, 1500);
            });
        }

        window.filterLogLines = filterLogLines;
        window.changeLines = changeLines;
        window.copyLogContent = copyLogContent;

        // Auto-refresh
        let autoRefreshInterval = null;
        document.getElementById('logAutoRefresh').addEventListener('change', function () {
            if (this.checked) {
                autoRefreshInterval = setInterval(() => window.location.reload(), 5000);
            } else {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        });

        // Conta inicial
        filterLogLines();
        <?php endif; ?>
    </script>
</body>
</html>
