<?php
require_once 'src/Auth.php';
require_once 'src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) { header('Location: index.php'); exit; }

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = $_POST['target'] ?? '';
    $tool   = $_POST['tool'] ?? '';
    
    $output = '';

    if ($action === 'internet_test') {
        \App\ShellHelper::exec('/bin/sh', ['api/scripts/internet-test.sh', 'all4'], $outLines, $tmpRet, false);
        $output = implode("\n", $outLines);
        $tool = 'Conectividade (internet-test.sh)';
        $target = 'Local Host';
    } elseif ($action === 'run_tool' && !empty($target) && !empty($tool)) {
        if ($tool === 'ping') {
            \App\ShellHelper::exec('/usr/bin/ping', ['-c', '4', $target], $outLines, $tmpRet, false);
        } elseif ($tool === 'traceroute') {
            \App\ShellHelper::exec('/usr/bin/traceroute', ['-m', '20', $target], $outLines, $tmpRet, false);
        } elseif ($tool === 'whois') {
            \App\ShellHelper::exec('/usr/bin/whois', [$target], $outLines, $tmpRet, false);
        } elseif ($tool === 'dns') {
            \App\ShellHelper::exec('/usr/bin/dig', [$target, '+short'], $outLines, $tmpRet, false);
            if(empty($outLines)) $outLines[] = "Nenhum registro encontrado.";
        }
        $output = implode("\n", $outLines);
    }
    
    // Remove ANSI characters if any
    $output = preg_replace('/\e\[[0-9;]*m/', '', $output);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'output' => $output,
            'tool' => $tool,
            'target' => $target
        ]);
        exit;
    }
}

$currentPage = 'diagnostics.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Diagnóstico - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .loader {
            border-top-color: #3b82f6;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }
        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php 
        $pageTitle = "Painel de Diagnóstico";
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container relative">
            
            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="hidden absolute inset-0 z-50 bg-slate-50/80 dark:bg-slate-950/80 backdrop-blur-sm flex flex-col items-center justify-center rounded-2xl animate-fade-in">
                <div class="loader ease-linear rounded-full border-4 border-t-4 border-slate-300 dark:border-slate-700 h-16 w-16 mb-6"></div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-widest mb-2" id="loadingTitle">Executando Comando</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 font-medium" id="loadingDesc">Isso pode levar alguns segundos ou até minutos (no caso de testes profundos)...</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 mt-4">
                <!-- PING -->
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 dark:text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">ICMP Ping</h3>
                    </div>
                    <form class="diag-form space-y-4">
                        <input type="hidden" name="action" value="run_tool">
                        <input type="hidden" name="tool" value="ping">
                        <input type="text" name="target" placeholder="google.com" required class="glass-input w-full">
                        <button type="submit" class="glass-btn glass-btn-primary w-full justify-center text-[10px] uppercase tracking-widest btn-submit">Pingar Host</button>
                    </form>
                </div>

                <!-- TRACEROUTE -->
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-500 dark:text-purple-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Traceroute</h3>
                    </div>
                    <form class="diag-form space-y-4">
                        <input type="hidden" name="action" value="run_tool">
                        <input type="hidden" name="tool" value="traceroute">
                        <input type="text" name="target" placeholder="8.8.8.8" required class="glass-input w-full border-purple-500/20">
                        <button type="submit" class="glass-btn bg-purple-600/20 hover:bg-purple-600 text-purple-500 dark:text-purple-400 hover:text-white border-purple-500/30 w-full justify-center text-[10px] uppercase tracking-widest btn-submit">Traçar Rota</button>
                    </form>
                </div>

                <!-- DNS LOOKUP -->
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 dark:text-emerald-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">DNS Lookup</h3>
                    </div>
                    <form class="diag-form space-y-4">
                        <input type="hidden" name="action" value="run_tool">
                        <input type="hidden" name="tool" value="dns">
                        <input type="text" name="target" placeholder="uol.com.br" required class="glass-input w-full border-emerald-500/20">
                        <button type="submit" class="glass-btn bg-emerald-600/20 hover:bg-emerald-600 text-emerald-500 dark:text-emerald-400 hover:text-white border-emerald-500/30 w-full justify-center text-[10px] uppercase tracking-widest btn-submit">Resolver DNS</button>
                    </form>
                </div>

                <!-- INTERNET TEST -->
                <div class="glass-panel group border-slate-200 dark:border-white/5">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-500 dark:text-orange-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Conectividade</h3>
                    </div>
                    <div class="space-y-4">
                        <p class="text-[10px] text-slate-500 font-medium leading-relaxed">Verifica rotas de saída IPv4/IPv6, gateway padrão e resolução externa.</p>
                        <form class="diag-form">
                            <input type="hidden" name="action" value="internet_test">
                            <button type="submit" class="glass-btn bg-orange-600/20 hover:bg-orange-600 text-orange-500 dark:text-orange-400 hover:text-white border-orange-500/30 w-full justify-center text-[10px] uppercase tracking-widest btn-submit">Check Internet</button>
                        </form>
                    </div>
                </div>
            </div>


            <!-- Pre-rendered DOM element hidden instead of dynamic -->
            <div id="outputContainer" class="hidden glass-panel !p-0 overflow-hidden animate-fade-in relative border-slate-200 dark:border-white/5">
                <div class="bg-slate-900/5 dark:bg-white/5 px-6 py-4 border-b border-slate-900/10 dark:border-white/5 flex items-center justify-between">
                    <h3 class="text-[10px] font-black text-slate-900 dark:text-white uppercase tracking-widest" id="outputHeader">Output</h3>
                    <button onclick="document.getElementById('outputContainer').classList.add('hidden')" class="text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                <div class="bg-black/40 p-6 font-mono text-[11px] leading-relaxed text-blue-500 dark:text-blue-400/90 whitespace-pre-wrap max-h-[500px] overflow-auto" id="outputPre">
                    <!-- Injected by JS -->
                </div>
            </div>


            <?php include 'includes/footer.php'; ?>
        </div>
    </main>
    
    <script>
        document.querySelectorAll('.diag-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const btn = this.querySelector('.btn-submit');
                const overlay = document.getElementById('loadingOverlay');
                const outputContainer = document.getElementById('outputContainer');
                const outputPre = document.getElementById('outputPre');
                const outputHeader = document.getElementById('outputHeader');
                const action = this.querySelector('input[name="action"]').value;
                const toolTarget = this.querySelector('input[name="target"]')?.value || 'Local Host';
                
                // Set loading texts based on action
                if(action === 'internet_test') {
                    document.getElementById('loadingTitle').innerText = 'Teste de Internet (Profundo)';
                    document.getElementById('loadingDesc').innerText = 'Avaliando gateway, ICMP, Amazon AWS, MyIP, DNS. Isso levará cerca de 10-40 segundos...';
                } else {
                    document.getElementById('loadingTitle').innerText = 'Processando Diagnóstico';
                    document.getElementById('loadingDesc').innerText = `Alvo: ${toolTarget}`;
                }

                const originalBtnText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Aguarde...';
                overlay.classList.remove('hidden');
                outputContainer.classList.add('hidden');
                outputPre.innerHTML = '';

                const formData = new FormData(this);

                try {
                    const response = await fetch('diagnostics.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    
                    const json = await response.json();
                    
                    if (json.status === 'success') {
                        outputHeader.innerText = `Output: ${json.tool} (${json.target || toolTarget})`;
                        outputPre.innerText = json.output || 'Sem saída do comando.';
                        outputContainer.classList.remove('hidden');
                        outputContainer.scrollIntoView({ behavior: 'smooth' });
                    } else {
                        window.AppUI.toast('Falha interna do servidor ao executar o diagnóstico.', 'error', { title: 'Diagnóstico' });
                    }
                } catch (error) {
                    console.error("Diagnosis failed:", error);
                    window.AppUI.toast('Houve um erro na requisição AJAX do diagnóstico.', 'error', { title: 'Diagnóstico' });
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnText;
                    overlay.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
