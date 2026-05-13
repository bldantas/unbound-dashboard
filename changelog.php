<?php
require_once 'src/Auth.php';

\App\Auth::check();

$currentPage = 'changelog.php';
$version = 'N/A';

$versionFile = __DIR__ . '/VERSION';
if (is_readable($versionFile)) {
    $rawVersion = trim((string) file_get_contents($versionFile));
    if ($rawVersion !== '') {
        $version = $rawVersion;
    }
}

$changelogFile = __DIR__ . '/CHANGELOG.md';
$rawChangelog = '';
$changelogMtime = 0;
if (is_readable($changelogFile)) {
    $rawChangelog = (string) file_get_contents($changelogFile);
    $changelogMtime = (int) filemtime($changelogFile);
}

/**
 * Quebra o CHANGELOG em entries por header `## vX.Y.Z — YYYY-MM-DD`.
 * Cada entry = { version, major, minor, patch, type, date, title, body, raw }.
 */
function parseChangelog(string $raw): array
{
    $entries = [];
    if (trim($raw) === '') return $entries;

    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $current = null;
    $bodyLines = [];

    $flush = function () use (&$entries, &$current, &$bodyLines) {
        if ($current !== null) {
            $current['body'] = trim(implode("\n", $bodyLines));
            $current['raw'] = "## " . $current['title'] . "\n\n" . $current['body'];
            $entries[] = $current;
        }
        $current = null;
        $bodyLines = [];
    };

    foreach ($lines as $line) {
        if (preg_match('/^##\s+v?(\d+)\.(\d+)\.(\d+)\s*[—\-–]\s*(\d{4}-\d{2}-\d{2})/u', $line, $m)) {
            $flush();
            $major = (int) $m[1];
            $minor = (int) $m[2];
            $patch = (int) $m[3];
            $type = $patch === 0 ? ($minor === 0 ? 'major' : 'minor') : 'patch';
            $current = [
                'version' => "{$major}.{$minor}.{$patch}",
                'major'   => $major,
                'minor'   => $minor,
                'patch'   => $patch,
                'type'    => $type,
                'date'    => $m[4],
                'title'   => trim(substr($line, 3)),
            ];
            $bodyLines = [];
        } elseif ($current !== null) {
            $bodyLines[] = $line;
        }
        // Linhas antes do primeiro `## v...` são ignoradas (header do arquivo).
    }
    $flush();

    return $entries;
}

/**
 * Renderiza markdown simples (subset) pra HTML — headers, listas, code, bold, italic, links.
 * Não usa lib externa pra manter zero deps.
 */
function renderChangelogMarkdown(string $md): string
{
    $md = str_replace("\r\n", "\n", $md);
    $lines = explode("\n", $md);
    $html = [];
    $inList = false;
    $inCode = false;
    $codeBuf = [];

    $closeList = function () use (&$inList, &$html) {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            if ($inCode) {
                $html[] = '<pre class="my-3 rounded-xl bg-slate-900/95 dark:bg-black/60 text-slate-100 border border-slate-700/60 dark:border-white/10 p-3 overflow-x-auto text-[11px] leading-relaxed font-mono">' . htmlspecialchars(implode("\n", $codeBuf)) . '</pre>';
                $codeBuf = [];
                $inCode = false;
            } else {
                $closeList();
                $inCode = true;
            }
            continue;
        }
        if ($inCode) { $codeBuf[] = $line; continue; }

        $trim = rtrim($line);
        if ($trim === '') { $closeList(); $html[] = ''; continue; }

        if (preg_match('/^###\s+(.+)$/u', $trim, $m)) {
            $closeList();
            $html[] = '<h4 class="mt-4 mb-2 text-sm font-black text-slate-900 dark:text-white">' . inlineMd($m[1]) . '</h4>';
            continue;
        }
        if (preg_match('/^##\s+(.+)$/u', $trim, $m)) {
            $closeList();
            $html[] = '<h3 class="mt-5 mb-2 text-base font-black text-slate-900 dark:text-white">' . inlineMd($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $trim, $m)) {
            if (!$inList) { $html[] = '<ul class="list-disc list-inside space-y-1 my-2 text-xs text-slate-700 dark:text-slate-300">'; $inList = true; }
            $html[] = '<li>' . inlineMd($m[1]) . '</li>';
            continue;
        }
        if (preg_match('/^---\s*$/', $trim)) {
            $closeList();
            $html[] = '<hr class="my-4 border-slate-200 dark:border-white/10">';
            continue;
        }

        $closeList();
        $html[] = '<p class="my-2 text-xs leading-relaxed text-slate-700 dark:text-slate-300">' . inlineMd($trim) . '</p>';
    }
    $closeList();
    if ($inCode) {
        $html[] = '<pre class="my-3 rounded-xl bg-slate-900/95 dark:bg-black/60 text-slate-100 border border-slate-700/60 dark:border-white/10 p-3 overflow-x-auto text-[11px] leading-relaxed font-mono">' . htmlspecialchars(implode("\n", $codeBuf)) . '</pre>';
    }

    return implode("\n", $html);
}

function inlineMd(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    // `code`
    $s = preg_replace('/`([^`]+)`/u', '<code class="px-1.5 py-0.5 rounded-md bg-slate-900/10 dark:bg-white/10 text-[11px] font-mono text-cyan-700 dark:text-cyan-300">$1</code>', $s);
    // **bold**
    $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong class="font-bold text-slate-900 dark:text-white">$1</strong>', $s);
    // *italic*
    $s = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $s);
    // [text](url)
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/u', function ($m) {
        $url = $m[2];
        if (!preg_match('#^https?://#i', $url)) return $m[0];
        return '<a href="' . $url . '" target="_blank" rel="noopener" class="text-cyan-600 dark:text-cyan-400 underline">' . $m[1] . '</a>';
    }, $s);
    return $s;
}

$entries = parseChangelog($rawChangelog);

$typeCounts = ['major' => 0, 'minor' => 0, 'patch' => 0];
foreach ($entries as $e) { $typeCounts[$e['type']]++; }
$totalEntries = count($entries);
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
                        Histórico de Versões
                    </h1>
                    <p class="page-subtitle">Registro completo das atualizações aplicadas no sistema.</p>
                </div>

                <div class="text-right">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Versão atual</p>
                    <p class="text-lg font-black text-cyan-600 dark:text-cyan-400">v<?= htmlspecialchars($version) ?></p>
                    <?php if ($changelogMtime > 0): ?>
                    <p class="text-[10px] text-slate-500 mt-1">Atualizado <?= htmlspecialchars(date('d/m/Y H:i', $changelogMtime)) ?></p>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Total</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $totalEntries ?></p>
                    <p class="text-[10px] text-slate-500 mt-0.5">releases</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-rose-600 dark:text-rose-400 uppercase tracking-widest">Major</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $typeCounts['major'] ?></p>
                    <p class="text-[10px] text-slate-500 mt-0.5">X.0.0</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Minor</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $typeCounts['minor'] ?></p>
                    <p class="text-[10px] text-slate-500 mt-0.5">X.Y.0</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Patch</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $typeCounts['patch'] ?></p>
                    <p class="text-[10px] text-slate-500 mt-0.5">X.Y.Z</p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="glass-panel !p-4 mb-4 border-slate-200 dark:border-white/5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[240px]">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input id="changelog-search" type="text" placeholder="Buscar por versão, texto, recurso..."
                               class="w-full pl-9 pr-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    </div>

                    <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-white/5 rounded-xl">
                        <button data-filter="all" class="changelog-chip is-active px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-widest transition-colors">Todos</button>
                        <button data-filter="major" class="changelog-chip px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-widest transition-colors">Major</button>
                        <button data-filter="minor" class="changelog-chip px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-widest transition-colors">Minor</button>
                        <button data-filter="patch" class="changelog-chip px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-widest transition-colors">Patch</button>
                    </div>

                    <label class="flex items-center gap-2 text-[11px] font-bold text-slate-600 dark:text-slate-300">
                        <input id="expand-all" type="checkbox" class="w-4 h-4 rounded accent-cyan-500">
                        Expandir tudo
                    </label>

                    <span class="ml-auto text-[11px] font-bold text-slate-500">
                        <span id="visible-count"><?= $totalEntries ?></span>/<?= $totalEntries ?> visíveis
                    </span>
                </div>
            </div>

            <!-- Empty state quando filtro zera -->
            <div id="empty-state" class="glass-panel hidden text-center py-10 border-slate-200 dark:border-white/5">
                <p class="text-sm text-slate-500">Nenhuma versão corresponde ao filtro atual.</p>
            </div>

            <!-- Lista de entries -->
            <div id="changelog-list" class="space-y-3">
                <?php if (empty($entries)): ?>
                    <div class="glass-panel text-center py-10 border-slate-200 dark:border-white/5">
                        <p class="text-sm text-slate-500">Nenhum registro disponível em CHANGELOG.md.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $typeColors = [
                        'major' => ['bg' => 'bg-rose-500/15', 'text' => 'text-rose-700 dark:text-rose-300', 'border' => 'border-rose-500/30', 'dot' => 'bg-rose-500'],
                        'minor' => ['bg' => 'bg-amber-500/15', 'text' => 'text-amber-700 dark:text-amber-300', 'border' => 'border-amber-500/30', 'dot' => 'bg-amber-500'],
                        'patch' => ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-700 dark:text-emerald-300', 'border' => 'border-emerald-500/30', 'dot' => 'bg-emerald-500'],
                    ];
                    foreach ($entries as $idx => $e):
                        $c = $typeColors[$e['type']];
                        $isCurrent = $e['version'] === $version;
                        $searchHaystack = strtolower($e['version'] . ' ' . $e['date'] . ' ' . $e['body']);
                    ?>
                        <details class="changelog-entry glass-panel !p-0 overflow-hidden border-slate-200 dark:border-white/5 <?= $isCurrent ? 'ring-2 ring-cyan-500/40' : '' ?>"
                                 data-type="<?= $e['type'] ?>"
                                 data-search="<?= htmlspecialchars($searchHaystack, ENT_QUOTES) ?>"
                                 <?= $idx === 0 ? 'open' : '' ?>>
                            <summary class="cursor-pointer px-5 py-4 flex items-center gap-3 hover:bg-slate-50/60 dark:hover:bg-white/5 transition-colors select-none">
                                <span class="w-2.5 h-2.5 rounded-full <?= $c['dot'] ?> shrink-0"></span>
                                <span class="font-mono text-sm font-black text-slate-900 dark:text-white">v<?= htmlspecialchars($e['version']) ?></span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md <?= $c['bg'] ?> <?= $c['text'] ?> border <?= $c['border'] ?> text-[10px] font-black uppercase tracking-widest">
                                    <?= $e['type'] ?>
                                </span>
                                <?php if ($isCurrent): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30 text-[10px] font-black uppercase tracking-widest">
                                    Atual
                                </span>
                                <?php endif; ?>
                                <span class="ml-auto text-[11px] font-bold text-slate-500"><?= htmlspecialchars($e['date']) ?></span>
                                <svg class="changelog-chevron w-4 h-4 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-5 pb-5 pt-1 border-t border-slate-900/5 dark:border-white/5">
                                <?= renderChangelogMarkdown($e['body']) ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <style>
        .changelog-chip {
            color: rgb(100 116 139);
        }
        html.dark .changelog-chip {
            color: rgb(148 163 184);
        }
        .changelog-chip.is-active {
            background: rgb(8 145 178);
            color: white;
        }
        .changelog-chip:hover:not(.is-active) {
            background: rgba(8 145 178 / 0.1);
        }
        details[open] .changelog-chevron {
            transform: rotate(180deg);
        }
        details.changelog-entry summary::-webkit-details-marker { display: none; }
        details.changelog-entry summary { list-style: none; }
    </style>

    <script>
        (function () {
            const searchInput = document.getElementById('changelog-search');
            const chips = document.querySelectorAll('.changelog-chip');
            const entries = document.querySelectorAll('.changelog-entry');
            const visibleCount = document.getElementById('visible-count');
            const emptyState = document.getElementById('empty-state');
            const expandAll = document.getElementById('expand-all');

            let currentType = 'all';

            function applyFilter() {
                const q = (searchInput.value || '').toLowerCase().trim();
                let visible = 0;
                entries.forEach(el => {
                    const type = el.getAttribute('data-type');
                    const haystack = el.getAttribute('data-search') || '';
                    const matchType = currentType === 'all' || type === currentType;
                    const matchSearch = q === '' || haystack.includes(q);
                    const show = matchType && matchSearch;
                    el.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                visibleCount.textContent = visible;
                emptyState.classList.toggle('hidden', visible !== 0);
            }

            searchInput.addEventListener('input', applyFilter);

            chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('is-active'));
                    chip.classList.add('is-active');
                    currentType = chip.getAttribute('data-filter');
                    applyFilter();
                });
            });

            expandAll.addEventListener('change', () => {
                entries.forEach(el => {
                    if (el.style.display === 'none') return;
                    el.open = expandAll.checked;
                });
            });

            window.addEventListener('load', function () {
                var loader = document.getElementById('global-page-loader');
                if (loader) loader.classList.remove('is-visible');
            });
        })();
    </script>
</body>
</html>
