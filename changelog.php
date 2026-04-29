<?php
require_once 'src/Auth.php';

\App\Auth::check();

$currentPage = 'changelog.php';
$version = 'N/A';
$changelogContent = "# Changelog\n\nNenhum registro disponivel.";

$versionFile = __DIR__ . '/VERSION';
if (is_readable($versionFile)) {
    $rawVersion = trim((string) file_get_contents($versionFile));
    if ($rawVersion !== '') {
        $version = $rawVersion;
    }
}

$changelogFile = __DIR__ . '/CHANGELOG.md';
if (is_readable($changelogFile)) {
    $rawChangelog = (string) file_get_contents($changelogFile);
    if (trim($rawChangelog) !== '') {
        $changelogContent = $rawChangelog;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Changelog - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Changelog";
        include 'includes/topbar.php';
        ?>

        <div class="page-container">
            <header class="page-header mb-6">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Historico de Versoes
                    </h1>
                    <p class="page-subtitle">Registro das ultimas atualizacoes aplicadas no sistema.</p>
                </div>

                <div class="text-right">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Versao Atual</p>
                    <p class="text-lg font-black text-cyan-600 dark:text-cyan-400">v<?= htmlspecialchars($version) ?></p>
                </div>
            </header>

            <div class="glass-panel !p-0 overflow-hidden border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">CHANGELOG.md</h3>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Atualize a cada release</span>
                </div>

                <div class="p-6">
                    <pre class="bg-slate-900/90 text-slate-100 rounded-2xl border border-slate-700/60 p-5 overflow-auto text-[12px] leading-relaxed font-mono"><?= htmlspecialchars($changelogContent) ?></pre>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        window.addEventListener('load', function () {
            var loader = document.getElementById('global-page-loader');
            if (loader) loader.classList.remove('is-visible');
        });
    </script>
</body>
</html>