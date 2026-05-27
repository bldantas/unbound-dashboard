<?php
require_once 'src/Auth.php';

\App\Auth::check();
if (!\App\Auth::can('blocklist.read')) {
    header('Location: index.php');
    exit;
}

$currentPage = 'exports.php';
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('exports.title') ?> - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php 
        $pageTitle = t('exports.title');
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container">

            <!-- Header -->
            <div class="mb-8 flex items-start justify-between gap-4 flex-wrap">
                <p class="text-sm text-slate-500 dark:text-slate-400 font-medium max-w-xl">Exporte dados do sistema, estatísticas e configurações para análise externa ou backup de segurança.</p>
                <button onclick="downloadExport('snapshot')"
                        class="glass-btn !bg-indigo-600 !text-white text-[10px] uppercase font-black flex items-center gap-2"
                        title="Download de tudo num único arquivo TAR.GZ (logs + stats + config + blacklist + cache)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    📦 Snapshot Completo
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

                <!-- 1. DNS Query Logs -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-blue-500/30 transition-all duration-300">
                    <div class="absolute -right-6 -bottom-6 text-blue-500/5 group-hover:text-blue-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_queries') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato CSV</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6 flex-1">Exporta o histórico completo de consultas DNS com IP do cliente, domínio, tipo de registro e se foi resolvido ou bloqueado.</p>

                    <div class="space-y-3">
                        <select id="logRange" class="glass-input w-full text-xs">
                            <option value="24h">Últimas 24 horas</option>
                            <option value="7d">Últimos 7 dias</option>
                            <option value="30d">Últimos 30 dias</option>
                            <option value="all">Tudo (completo)</option>
                        </select>
                        <button onclick="downloadExport('logs', document.getElementById('logRange').value)" class="glass-btn glass-btn-primary w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download CSV
                        </button>
                    </div>
                </div>

                <!-- 2. Statistics Report -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-emerald-500/30 transition-all duration-300">
                    <div class="absolute -right-6 -bottom-6 text-emerald-500/5 group-hover:text-emerald-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_stats') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato JSON</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6 flex-1">Relatório completo com métricas atuais do Unbound (QPS, latência, cache, DNSSEC), histórico diário, top domínios e top clientes.</p>

                    <button onclick="downloadExport('stats')" class="glass-btn bg-emerald-600/20 hover:bg-emerald-600 text-emerald-600 dark:text-emerald-400 hover:text-white border-emerald-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2 mt-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download JSON
                    </button>
                </div>

                <!-- 3. System Log -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-purple-500/30 transition-all duration-300">
                    <div class="absolute -right-6 -bottom-6 text-purple-500/5 group-hover:text-purple-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_system_log') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato TXT</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6 flex-1">Exporta as últimas 300 linhas do daemon Unbound (journalctl) e do syslog do sistema operacional em um arquivo de texto consolidado.</p>

                    <button onclick="downloadExport('system_log')" class="glass-btn bg-purple-600/20 hover:bg-purple-600 text-purple-600 dark:text-purple-400 hover:text-white border-purple-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2 mt-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download TXT
                    </button>
                </div>

                <!-- 4. Config Backup & Restore -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-amber-500/30 transition-all duration-300 xl:col-span-2">
                    <div class="absolute -right-6 -bottom-6 text-amber-500/5 group-hover:text-amber-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_backup') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato TAR.GZ</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-4">Backup completo de todas as configurações modulares do Unbound + configurações do dashboard. Restaure a qualquer momento a partir de um arquivo exportado.</p>

                    <div class="bg-amber-500/5 border border-amber-500/10 rounded-xl p-3 mb-5">
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 font-bold leading-relaxed">
                            <span class="font-black">Inclui:</span> unbound.conf, configs modulares, instâncias multicore e settings do dashboard.<br>
                            <span class="font-black">Exclui:</span> blocked_domains.conf, certificados e chaves privadas.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-auto">
                        <!-- Download -->
                        <button onclick="downloadExport('config_backup')" class="glass-btn bg-amber-600/20 hover:bg-amber-600 text-amber-600 dark:text-amber-400 hover:text-white border-amber-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Exportar Backup
                        </button>

                        <!-- Restore -->
                        <button onclick="document.getElementById('restoreModal').classList.remove('hidden')" class="glass-btn bg-cyan-600/20 hover:bg-cyan-600 text-cyan-600 dark:text-cyan-400 hover:text-white border-cyan-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m4-8l-4-4m0 0L16 8m4-4v12"/></svg>
                            Restaurar Backup
                        </button>
                    </div>
                </div>

                <!-- 5. Blacklist -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-red-500/30 transition-all duration-300">
                    <div class="absolute -right-6 -bottom-6 text-red-500/5 group-hover:text-red-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_blacklist') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato CSV</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6 flex-1">Exporta a lista completa de domínios bloqueados com suas respectivas categorias (Malware/Adware, Phishing, Judicial).</p>

                    <button onclick="downloadExport('blacklist')" class="glass-btn bg-red-600/20 hover:bg-red-600 text-red-600 dark:text-red-400 hover:text-white border-red-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2 mt-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download CSV
                    </button>
                </div>

                <!-- 6. Cache DNS -->
                <div class="glass-panel group relative overflow-hidden flex flex-col border-slate-200 dark:border-white/5 hover:border-cyan-500/30 transition-all duration-300">
                    <div class="absolute -right-6 -bottom-6 text-cyan-500/5 group-hover:text-cyan-500/10 transition-colors duration-500">
                        <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 flex items-center justify-center text-cyan-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('exports.card_cache') ?></h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Formato TXT (raw)</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6 flex-1">Dump completo do cache do Unbound (rrset + msg + key cache) no formato bruto do <code>unbound-control dump_cache</code>. Pode ser re-importado com <code>load_cache</code>.</p>

                    <button onclick="downloadExport('cache')" class="glass-btn bg-cyan-600/20 hover:bg-cyan-600 text-cyan-600 dark:text-cyan-400 hover:text-white border-cyan-500/30 w-full justify-center text-[10px] uppercase tracking-widest font-black gap-2 mt-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download dump
                    </button>
                </div>

            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <!-- Toast Notification -->
    <div id="exportToast" class="fixed bottom-6 right-6 z-50 hidden animate-fade-in">
        <div class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 border border-white/10 dark:border-slate-900/10">
            <div class="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-widest" id="toastTitle">Gerando exportação...</p>
                <p class="text-[10px] opacity-60 font-medium" id="toastDesc">Aguarde o download iniciar</p>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div id="restoreModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('restoreModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-white/10 max-w-lg w-full p-8 animate-fade-in">
            <button onclick="document.getElementById('restoreModal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 flex items-center justify-center text-cyan-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m4-8l-4-4m0 0L16 8m4-4v12"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest">Restaurar Backup</h3>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Envie um arquivo .tar.gz</p>
                </div>
            </div>

            <div class="bg-red-500/5 border border-red-500/10 rounded-xl p-4 mb-6">
                <p class="text-xs text-red-600 dark:text-red-400 font-bold leading-relaxed">
                    <span class="font-black">⚠ Atenção:</span> Esta ação irá sobrescrever as configurações atuais do Unbound e reiniciar o serviço. Certifique-se de que o arquivo é um backup válido gerado por este painel.
                </p>
            </div>

            <form id="restoreForm" enctype="multipart/form-data">
                <label class="block mb-6">
                    <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-6 text-center cursor-pointer hover:border-cyan-500/50 transition-colors" id="dropZone">
                        <svg class="w-8 h-8 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-xs text-slate-500 font-bold" id="fileLabel">Clique para selecionar ou arraste o arquivo .tar.gz</p>
                        <input type="file" name="backup_file" accept=".tar.gz,.gz" class="hidden" id="restoreFileInput" onchange="updateFileLabel(this)">
                    </div>
                </label>

                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('restoreModal').classList.add('hidden')" class="glass-btn flex-1 justify-center text-[10px] uppercase tracking-widest font-black">
                        Cancelar
                    </button>
                    <button type="submit" id="restoreBtn" class="glass-btn bg-cyan-600/20 hover:bg-cyan-600 text-cyan-600 dark:text-cyan-400 hover:text-white border-cyan-500/30 flex-1 justify-center text-[10px] uppercase tracking-widest font-black gap-2" disabled>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m4-8l-4-4m0 0L16 8m4-4v12"/></svg>
                        Restaurar Agora
                    </button>
                </div>
            </form>

            <!-- Result area -->
            <div id="restoreResult" class="hidden mt-6 rounded-xl p-4 text-xs font-mono leading-relaxed max-h-48 overflow-auto"></div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

        function downloadExport(type, range = '24h') {
            const toast = document.getElementById('exportToast');
            const titleEl = document.getElementById('toastTitle');
            const descEl = document.getElementById('toastDesc');
            const titles = {
                'logs': 'Exportando consultas DNS...',
                'stats': 'Gerando relatório de estatísticas...',
                'system_log': 'Coletando logs do sistema...',
                'config_backup': 'Empacotando backup de configs...',
                'blacklist': 'Exportando lista de bloqueios...',
                'cache': 'Exportando dump do cache...',
                'snapshot': 'Empacotando snapshot completo (todos os dados)...',
            };
            const descs = {
                'snapshot': 'Pode levar 10-30s — não feche a aba.',
                'logs':     range === 'all' ? 'Dataset grande — pode levar 30s+.' : 'Aguarde o download iniciar.',
            };

            titleEl.innerText = titles[type] || 'Exportando...';
            descEl.innerText  = descs[type] || 'Aguarde o download iniciar.';
            toast.classList.remove('hidden');

            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = `api/export.php?type=${type}&range=${range}`;

            // Toast some quando o iframe receber o evento `load`:
            // significa que o response chegou e o browser disparou o save-as.
            // Garantia: fallback após 60s se algo travar.
            let cleanedUp = false;
            const cleanup = () => {
                if (cleanedUp) return;
                cleanedUp = true;
                toast.classList.add('hidden');
                setTimeout(() => iframe.remove(), 2000);
            };
            iframe.addEventListener('load', () => {
                // load só dispara em downloads servidos com Content-Disposition; o save-as
                // do browser já abriu nesse ponto. Pequeno delay pra UX (toast não some abrupto).
                setTimeout(cleanup, 1200);
            });
            setTimeout(cleanup, 60000); // hard fallback de 60s

            document.body.appendChild(iframe);
        }

        // File label update
        function updateFileLabel(input) {
            const label = document.getElementById('fileLabel');
            const btn = document.getElementById('restoreBtn');
            if (input.files.length > 0) {
                label.innerText = input.files[0].name;
                label.classList.add('text-cyan-500');
                btn.disabled = false;
            } else {
                label.innerText = 'Clique para selecionar ou arraste o arquivo .tar.gz';
                label.classList.remove('text-cyan-500');
                btn.disabled = true;
            }
        }

        // Click on drop zone opens file picker
        document.getElementById('dropZone').addEventListener('click', () => {
            document.getElementById('restoreFileInput').click();
        });

        // Drag & drop
        const dropZone = document.getElementById('dropZone');
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-cyan-500'); });
        dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-cyan-500'); });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-cyan-500');
            const input = document.getElementById('restoreFileInput');
            input.files = e.dataTransfer.files;
            updateFileLabel(input);
        });

        // Restore form submit
        document.getElementById('restoreForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const input = document.getElementById('restoreFileInput');
            const filename = input.files && input.files[0] ? input.files[0].name : '(arquivo)';
            // Confirmação dupla — restore é destrutivo (sobrescreve /etc/unbound/*.conf + restart).
            if (!confirm(`Restaurar configs a partir de "${filename}"?\n\nIsto vai SOBRESCREVER /etc/unbound/*.conf e reiniciar o daemon. Não há rollback automático.`)) {
                return;
            }

            const btn = document.getElementById('restoreBtn');
            const result = document.getElementById('restoreResult');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Restaurando...';
            result.classList.add('hidden');

            try {
                const formData = new FormData(this);
                // CSRF token — backend rejeita POST sem ele (api/export.php).
                formData.append('csrf_token', CSRF_TOKEN);
                const res = await fetch('api/export.php', { method: 'POST', body: formData });
                const json = await res.json();

                result.classList.remove('hidden');

                if (json.status === 'success') {
                    result.className = 'mt-6 rounded-xl p-4 text-xs font-mono leading-relaxed max-h-48 overflow-auto bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300';
                    result.innerHTML = `<p class="font-black mb-2">✅ ${json.message}</p><p class="opacity-70">Arquivos restaurados:</p><ul class="list-disc ml-4 mt-1">${json.files.map(f => `<li>${f}</li>`).join('')}</ul>`;
                } else if (json.status === 'warning') {
                    result.className = 'mt-6 rounded-xl p-4 text-xs font-mono leading-relaxed max-h-48 overflow-auto bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300';
                    result.innerHTML = `<p class="font-black mb-2">⚠️ ${json.message}</p><pre class="opacity-70 mt-2 whitespace-pre-wrap">${json.validation || ''}</pre>`;
                } else {
                    result.className = 'mt-6 rounded-xl p-4 text-xs font-mono leading-relaxed max-h-48 overflow-auto bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-300';
                    result.innerHTML = `<p class="font-black">❌ ${json.message}</p>`;
                }
            } catch (err) {
                result.classList.remove('hidden');
                result.className = 'mt-6 rounded-xl p-4 text-xs font-mono leading-relaxed max-h-48 overflow-auto bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-300';
                result.innerHTML = '<p class="font-black">❌ Erro na comunicação com o servidor.</p>';
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
