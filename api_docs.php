<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'api_docs.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>API & Integrações - Unbound DNS</title>
    <meta name="description" content="API pública (Swagger UI) e instruções de integração com Prometheus + Grafana.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "API & Integrações";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    API & Integrações
                </h1>
                <p class="page-subtitle">Endpoints REST documentados (OpenAPI 3.1) e integração com Prometheus + Grafana.</p>
            </header>

            <!-- Swagger UI / OpenAPI -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">OpenAPI / Swagger UI</h3>
                        <p class="text-[10px] text-slate-500 mt-1">Documentação interativa gerada por FastAPI — testa direto do browser.</p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <a href="/api/v1/docs" target="_blank" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            Abrir Swagger
                        </a>
                        <a href="/api/v1/redoc" target="_blank" class="glass-btn text-[10px] uppercase font-black inline-flex items-center gap-2">Abrir ReDoc</a>
                        <a href="/api/v1/openapi.json" target="_blank" class="glass-btn text-[10px] uppercase font-black inline-flex items-center gap-2">openapi.json</a>
                    </div>
                </div>
                <div class="p-6 text-sm text-slate-600 dark:text-slate-400 space-y-2">
                    <p><strong class="text-slate-900 dark:text-white">Autenticação:</strong> Bearer JWT (login humano) OU header <code class="text-xs bg-slate-100 dark:bg-white/5 px-1.5 py-0.5 rounded">X-Api-Token: &lt;raw&gt;</code> (token long-lived, recomendado p/ máquinas).</p>
                    <p><strong class="text-slate-900 dark:text-white">Criar API tokens:</strong> Configurações → API Tokens (capability <code class="text-xs bg-slate-100 dark:bg-white/5 px-1.5 py-0.5 rounded">tokens.manage</code>).</p>
                </div>
            </div>

            <!-- Prometheus -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Prometheus / Metrics</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Endpoint <code>/metrics</code> em formato OpenMetrics, instrumentação automática + counters customizados.</p>
                </div>
                <div class="p-6 space-y-4 text-sm">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">URL</p>
                        <code class="block bg-slate-900 text-emerald-400 px-4 py-3 rounded-lg font-mono text-xs">https://&lt;seu-dashboard&gt;/metrics</code>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Exemplo de scrape config (prometheus.yml)</p>
                        <pre class="bg-slate-900 text-slate-300 px-4 py-3 rounded-lg font-mono text-[11px] overflow-x-auto">scrape_configs:
  - job_name: unbound-dashboard
    metrics_path: /metrics
    scheme: https
    static_configs:
      - targets: ['dashboard.example.com']</pre>
                    </div>
                </div>
            </div>

            <!-- Grafana Infinity -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Grafana (Infinity datasource)</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Alternativa ao Prometheus — JSON simples, sem precisar scraper. Bom pra 5-10 métricas chave.</p>
                </div>
                <div class="p-6 space-y-4 text-sm">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Snapshot atual (todas métricas chave)</p>
                        <code class="block bg-slate-900 text-emerald-400 px-4 py-3 rounded-lg font-mono text-xs">GET /api/v1/grafana/snapshot</code>
                        <p class="text-[10px] text-slate-500 mt-2">Retorna lista flat: qps, hit_ratio, latency_avg_ms, latency_median_ms, dnssec_ratio, dnssec_secure, dnssec_bogus, uptime_seconds, queries_today, blocked_today, online.</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Série temporal (queries/blocked por hora)</p>
                        <code class="block bg-slate-900 text-emerald-400 px-4 py-3 rounded-lg font-mono text-xs">GET /api/v1/grafana/timeseries?metric=total|blocked&amp;hours=24</code>
                        <p class="text-[10px] text-slate-500 mt-2">Janela: 1..720h. Formato `[{time: ISO, value: int, metric: str}]`.</p>
                    </div>
                    <div class="border-t border-slate-200 dark:border-white/5 pt-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Setup no Grafana</p>
                        <ol class="list-decimal list-inside text-xs space-y-1 text-slate-600 dark:text-slate-400">
                            <li>Instale o plugin <strong>yesoreyeram-infinity-datasource</strong>.</li>
                            <li>Nova datasource: URL <code class="text-[10px] bg-slate-100 dark:bg-white/5 px-1 rounded">https://&lt;seu-dashboard&gt;</code>.</li>
                            <li>Auth → "Forward OAuth identity" → header custom: <code class="text-[10px] bg-slate-100 dark:bg-white/5 px-1 rounded">X-Api-Token: &lt;raw token&gt;</code>.</li>
                            <li>Em um panel: type=JSON, URL=<code class="text-[10px] bg-slate-100 dark:bg-white/5 px-1 rounded">/api/v1/grafana/snapshot</code>, parser=Backend.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- cURL examples -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Exemplos cURL</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Endpoints mais usados pra automação externa.</p>
                </div>
                <div class="p-6 space-y-3 font-mono text-[11px]">
                    <pre class="bg-slate-900 text-slate-300 px-4 py-3 rounded-lg overflow-x-auto"># Snapshot atual
curl -H "X-Api-Token: $TOKEN" \
     https://&lt;dashboard&gt;/api/v1/grafana/snapshot</pre>
                    <pre class="bg-slate-900 text-slate-300 px-4 py-3 rounded-lg overflow-x-auto"># Últimas 24h de queries por hora
curl -H "X-Api-Token: $TOKEN" \
     "https://&lt;dashboard&gt;/api/v1/grafana/timeseries?metric=total&amp;hours=24"</pre>
                    <pre class="bg-slate-900 text-slate-300 px-4 py-3 rounded-lg overflow-x-auto"># Forçar prune dos query logs antigos
curl -X POST -H "X-Api-Token: $TOKEN" \
     https://&lt;dashboard&gt;/api/v1/analytics/retention/prune-now</pre>
                    <pre class="bg-slate-900 text-slate-300 px-4 py-3 rounded-lg overflow-x-auto"># Listar fontes de blocklist
curl -H "X-Api-Token: $TOKEN" \
     https://&lt;dashboard&gt;/api/v1/blocklist/sources</pre>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

</body>
</html>
