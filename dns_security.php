<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'dns_security.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Segurança DNS - Unbound DNS</title>
    <meta name="description" content="DNSSEC, upstream DoT (DNS-over-TLS), trust anchors e modo de resolução.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Segurança DNS";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Segurança DNS
                </h1>
                <p class="page-subtitle">DNSSEC, trust anchors e modo de resolução upstream (recursivo direto ou via DoT).</p>
            </header>

            <!-- DNSSEC -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">DNSSEC ratio</p>
                    <p id="kpiDnssec" class="text-3xl font-black text-amber-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Respostas seguras</p>
                    <p id="kpiSecure" class="text-3xl font-black text-emerald-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Respostas bogus</p>
                    <p id="kpiBogus" class="text-3xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Trust anchor</p>
                    <p id="kpiAnchor" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-2">—</p>
                </div>
            </div>

            <!-- Upstream config -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Modo Upstream</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Recursivo: Unbound consulta diretamente os servidores autoritativos a partir do root (padrão, mais privacidade). DoT: Unbound encaminha pra um resolver via DNS-over-TLS (criptografado, mas confia no provedor).</p>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-6 flex-wrap">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="recursive" id="modeRecursive" class="w-4 h-4">
                            <span class="text-xs font-black uppercase tracking-widest">Recursivo (padrão)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="dot" id="modeDot" class="w-4 h-4">
                            <span class="text-xs font-black uppercase tracking-widest">DoT (forward-tls-upstream)</span>
                        </label>
                    </div>

                    <div id="dotConfig" class="hidden space-y-4 border-t border-slate-900/10 dark:border-white/5 pt-4">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Provedor</label>
                            <select id="provider" class="glass-input w-full mt-1 text-sm">
                                <option value="quad9">Quad9 (filtra malware)</option>
                                <option value="cloudflare">Cloudflare 1.1.1.1</option>
                                <option value="google">Google Public DNS</option>
                                <option value="adguard">AdGuard (unfiltered)</option>
                                <option value="custom">Custom (lista própria)</option>
                            </select>
                        </div>
                        <div id="customWrap" class="hidden">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lista JSON</label>
                            <textarea id="customList" rows="4" class="glass-input w-full mt-1 font-mono text-xs" placeholder='[{"addr":"9.9.9.9","port":853,"hostname":"dns.quad9.net"}]'></textarea>
                            <p class="text-[10px] text-slate-500 mt-1">Cada item: addr (IP), port (default 853), hostname (SNI/cert).</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <?php if ($isAdmin): ?>
                    <button type="button" id="btnSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar settings</button>
                    <button type="button" id="btnApply" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Aplicar + Restart Unbound</button>
                    <?php else: ?>
                    <span class="text-[10px] text-slate-500 italic">Somente admin pode editar.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rate-limit -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Rate Limit (defesa contra abuso)</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Limita queries por segundo (QPS) por IP cliente e/ou por domínio destino. <code>factor</code> = "1 a cada N queries passa mesmo limitado" (evita NXDOMAIN amplification).</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Per-IP -->
                    <div class="space-y-3 border border-slate-200 dark:border-white/10 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Per-IP (cliente)</h4>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="rlIpEnabled" class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-300 dark:bg-slate-700 rounded-full peer-checked:bg-emerald-500 transition-colors relative">
                                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                                </div>
                            </label>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">QPS máximo</label>
                            <input type="number" id="rlIpQps" min="0" max="100000" class="glass-input w-full mt-1 font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">Queries/s por IP. 0 = ilimitado.</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Factor (passagem)</label>
                            <input type="number" id="rlIpFactor" min="0" max="1000" class="glass-input w-full mt-1 font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">10 = ~10% passa mesmo limitado.</p>
                        </div>
                    </div>
                    <!-- Per-domain -->
                    <div class="space-y-3 border border-slate-200 dark:border-white/10 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Per-domínio (destino)</h4>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="rlDomEnabled" class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-300 dark:bg-slate-700 rounded-full peer-checked:bg-emerald-500 transition-colors relative">
                                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                                </div>
                            </label>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">QPS máximo</label>
                            <input type="number" id="rlDomQps" min="0" max="100000" class="glass-input w-full mt-1 font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">Queries/s por nome de domínio. 0 = ilimitado.</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Factor (passagem)</label>
                            <input type="number" id="rlDomFactor" min="0" max="1000" class="glass-input w-full mt-1 font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">10 = ~10% passa mesmo limitado.</p>
                        </div>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <button type="button" id="btnRlSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar ratelimit</button>
                    <button type="button" id="btnRlApply" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Aplicar + Restart Unbound</button>
                </div>
                <?php endif; ?>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    const $ = (id) => document.getElementById(id);

    async function loadInfo() {
        const r = await fetch('/api/v1/dns-security/info', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('kpiDnssec').textContent = (d.dnssec_ratio ?? 0).toFixed(1) + '%';
        $('kpiSecure').textContent = (d.dnssec_secure ?? 0).toLocaleString('pt-BR');
        $('kpiBogus').textContent = (d.dnssec_bogus ?? 0).toLocaleString('pt-BR');
        $('kpiAnchor').innerHTML = d.trust_anchor_present
            ? `<span class="text-emerald-500">● presente</span><br><span class="text-[10px] text-slate-500">${d.trust_anchor_size}b · ${d.trust_anchor_path}</span>`
            : '<span class="text-red-500">● ausente</span>';
    }

    async function loadSettings() {
        const r = await fetch('/api/v1/dns-security/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const s = d.settings || {};
        const mode = s.dns_upstream_mode || 'recursive';
        const provider = s.dns_upstream_provider || 'quad9';
        const custom = s.dns_upstream_custom || '[]';
        ($(mode === 'dot' ? 'modeDot' : 'modeRecursive')).checked = true;
        $('provider').value = provider;
        $('customList').value = custom;
        renderConditional();
    }

    function renderConditional() {
        const isDot = $('modeDot').checked;
        $('dotConfig').classList.toggle('hidden', !isDot);
        $('customWrap').classList.toggle('hidden', !isDot || $('provider').value !== 'custom');
    }

    function disableInputs(disabled) {
        ['modeRecursive','modeDot','provider','customList','btnSave','btnApply'].forEach(id => {
            const el = $(id);
            if (el) el.disabled = disabled;
        });
    }

    document.querySelectorAll('input[name=mode]').forEach(el => el.addEventListener('change', renderConditional));
    $('provider')?.addEventListener('change', renderConditional);

    if (IS_ADMIN) {
        $('btnSave').addEventListener('click', async () => {
            const body = {
                dns_upstream_mode: $('modeDot').checked ? 'dot' : 'recursive',
                dns_upstream_provider: $('provider').value,
                dns_upstream_custom: $('customList').value || '[]',
            };
            const r = await fetch('/api/v1/dns-security/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            if (r.ok) (window.customAlert || alert)('Salvo. Clique em "Aplicar" pra recarregar o Unbound.');
            else (window.customAlert || alert)('Erro ao salvar.');
        });

        $('btnApply').addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Aplicar config e restart Unbound? Resolução DNS é interrompida por ~2s.') : Promise.resolve(confirm('Aplicar?')));
            if (!ok) return;
            disableInputs(true);
            try {
                const r = await fetch('/api/v1/dns-security/apply', { method: 'POST', headers: H });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.ok) {
                    (window.customAlert || alert)(`Aplicado: modo=${d.mode}, addresses=${d.addresses_written}.`);
                    loadInfo();
                } else {
                    const msg = `Falha em "${d.stage || '?'}": ${d.error || r.statusText}` + (d.rollback ? `\nRollback: ${d.rollback}` : '');
                    (window.customAlert || alert)(msg);
                }
            } finally {
                disableInputs(false);
            }
        });
    }

    loadInfo();
    loadSettings();
    setInterval(loadInfo, 60000);

    // === Rate-limit ===
    async function loadRatelimit() {
        const r = await fetch('/api/v1/dns-security/ratelimit/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const s = d.settings || {};
        $('rlIpEnabled').checked = String(s.dns_ratelimit_ip_enabled) === '1';
        $('rlIpQps').value = parseInt(s.dns_ratelimit_ip_qps || '0', 10);
        $('rlIpFactor').value = parseInt(s.dns_ratelimit_ip_factor || '10', 10);
        $('rlDomEnabled').checked = String(s.dns_ratelimit_domain_enabled) === '1';
        $('rlDomQps').value = parseInt(s.dns_ratelimit_domain_qps || '0', 10);
        $('rlDomFactor').value = parseInt(s.dns_ratelimit_domain_factor || '10', 10);
    }

    if (IS_ADMIN) {
        $('btnRlSave')?.addEventListener('click', async () => {
            const body = {
                dns_ratelimit_ip_enabled:     $('rlIpEnabled').checked ? '1' : '0',
                dns_ratelimit_ip_qps:         String(Math.max(0, parseInt($('rlIpQps').value || '0', 10))),
                dns_ratelimit_ip_factor:      String(Math.max(0, parseInt($('rlIpFactor').value || '10', 10))),
                dns_ratelimit_domain_enabled: $('rlDomEnabled').checked ? '1' : '0',
                dns_ratelimit_domain_qps:     String(Math.max(0, parseInt($('rlDomQps').value || '0', 10))),
                dns_ratelimit_domain_factor:  String(Math.max(0, parseInt($('rlDomFactor').value || '10', 10))),
            };
            const r = await fetch('/api/v1/dns-security/ratelimit/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            (window.customAlert || alert)(r.ok ? 'Salvo. Clique em Aplicar pra recarregar o Unbound.' : 'Erro ao salvar.');
        });

        $('btnRlApply')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Aplicar e restart Unbound? ~2s de interrupção.') : Promise.resolve(confirm('Aplicar?')));
            if (!ok) return;
            const r = await fetch('/api/v1/dns-security/apply', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            if (r.ok && d.ok) (window.customAlert || alert)('Ratelimit aplicado.');
            else (window.customAlert || alert)(`Falha em "${d.stage || '?'}": ${d.error || r.statusText}`);
        });
    }
    loadRatelimit();
})();
</script>

</body>
</html>
