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
    <title><?= t('dns_security.title') ?> - Unbound DNS</title>
    <meta name="description" content="DNSSEC, upstream DoT (DNS-over-TLS), trust anchors e modo de resolução.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('dns_security.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <?= t('dns_security.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('dns_security.subtitle') ?></p>
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
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('dns_security.section_upstream') ?></h3>
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

            <!-- Privacidade (qname-minimisation) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('dns_security.section_qname') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">RFC 7816: ao resolver `foo.example.com.`, o Unbound pergunta ao root só `com.`, depois ao TLD só `example.com.`, e só pro auth final pergunta o nome completo. Reduz vazamento pros operadores de DNS upstream. Modo `strict` segue o RFC ao pé da letra (mais privacidade, mas pode quebrar com auths mal-configurados).</p>
                </div>
                <div class="p-6">
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="qnameMode" value="no" id="qnNo" class="w-4 h-4">
                            <span class="text-xs font-black uppercase tracking-widest">Off</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="qnameMode" value="yes" id="qnYes" class="w-4 h-4">
                            <span class="text-xs font-black uppercase tracking-widest">Yes (relaxed)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="qnameMode" value="strict" id="qnStrict" class="w-4 h-4">
                            <span class="text-xs font-black uppercase tracking-widest">Strict (RFC 7816)</span>
                        </label>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <button type="button" id="btnQnSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <button type="button" id="btnQnApply" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Aplicar + Restart</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- DoH Inbound v2 (info + cert mgmt) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('dns_security.section_doh_inbound') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Unbound aceita consultas via DoH (porta 8443) e DoT (porta 853). Ambos usam o mesmo cert TLS (<code>tls-service-pem/key</code>). Esta seção mostra info do cert e permite gerar self-signed pra dev/teste.</p>
                </div>
                <div class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Porta DoT</p>
                            <p id="dotPort" class="font-mono text-slate-700 dark:text-slate-300">—</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Porta DoH</p>
                            <p id="dohPort" class="font-mono text-slate-700 dark:text-slate-300">—</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">URL pra cliente</p>
                            <p id="dohUrl" class="font-mono text-slate-700 dark:text-slate-300 break-all">—</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Cert path</p>
                            <p id="certPath" class="font-mono text-slate-700 dark:text-slate-300 break-all text-[10px]">—</p>
                        </div>
                    </div>

                    <div class="border-t border-slate-900/10 dark:border-white/5 pt-4">
                        <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 mb-3">Certificado TLS</h4>
                        <div id="certBox" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                            <div><span class="text-[10px] text-slate-500 uppercase">Subject:</span> <span id="certSubject" class="font-mono">—</span></div>
                            <div><span class="text-[10px] text-slate-500 uppercase">Issuer:</span> <span id="certIssuer" class="font-mono">—</span></div>
                            <div><span class="text-[10px] text-slate-500 uppercase">Válido até:</span> <span id="certNotAfter" class="font-mono">—</span></div>
                            <div><span class="text-[10px] text-slate-500 uppercase">Expira em:</span> <span id="certExpiry" class="font-black">—</span></div>
                            <div class="md:col-span-2"><span class="text-[10px] text-slate-500 uppercase">SAN:</span> <span id="certSan" class="font-mono break-all">—</span></div>
                            <div class="md:col-span-2"><span class="text-[10px] text-slate-500 uppercase">SHA-256:</span> <span id="certFp" class="font-mono break-all text-[10px]">—</span></div>
                        </div>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 mb-3">Gerar Self-signed (dev/teste)</h4>
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Common Name (CN)</span>
                            <input type="text" id="cnInput" placeholder="dns.example.com" class="glass-input w-64 font-mono">
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Validade (dias)</span>
                            <input type="number" id="daysInput" value="365" min="7" max="3650" class="glass-input w-24 font-mono">
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" id="restartAfter" class="w-4 h-4">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Restart Unbound após gerar</span>
                        </label>
                        <button type="button" id="btnGenCert" class="glass-btn !bg-rose-600 !text-white text-[10px] uppercase font-black ml-auto">Gerar e Instalar</button>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-2 italic">Sobrescreve <code>dashboard.crt/key</code>. Sem restart, Unbound continua usando o cert antigo até o próximo reload.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Rate-limit -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('dns_security.section_rate_limit') ?></h3>
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

            <!-- Hardening v2 (D.2) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('dns_security.section_hardening') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Sobrepõem as defaults de <code>/etc/unbound/includes/security.conf</code>. Cada toggle ativa uma diretiva extra no <code>forwarders.conf</code> e exige Apply pra entrar em vigor.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <!-- Identidade -->
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHideIdentity" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Hide Identity</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Oculta resposta a <code>id.server / hostname.bind</code>.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHideVersion" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Hide Version</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Oculta resposta a <code>version.server / version.bind</code>.</p>
                        </div>
                    </label>

                    <!-- Cache & DNSSEC -->
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdAggressiveNsec" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Aggressive NSEC (RFC 8198)</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Cache negativo agressivo via DNSSEC — economiza consultas pros TLDs.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdUseCapsForId" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">0x20 Caps For ID</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Randomiza maiúsculas/minúsculas — mitiga cache poisoning.</p>
                        </div>
                    </label>

                    <!-- Harden suite -->
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHardenGlue" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Harden Glue</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Não aceita glue out-of-zone (anti-poisoning).</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHardenDnssecStripped" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Harden DNSSEC Stripped</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Falha se RRSIG sumir de zona DNSSEC-signed.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHardenBelowNxdomain" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Harden Below NXDOMAIN</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Aceita NXDOMAIN de zonas signed sem reconsulta (RFC 8020).</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHardenReferralPath" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Harden Referral Path</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Valida toda referência ao caminho do auth (custoso).</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdHardenAlgoDowngrade" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Harden Algo Downgrade</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Não aceita downgrade pra algoritmo DNSSEC mais fraco.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-slate-200 dark:border-white/10 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-white/5">
                        <input type="checkbox" id="hdDenyAny" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px]">Deny ANY</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Recusa queries ANY (anti-amplification).</p>
                        </div>
                    </label>

                    <!-- Privacy v2 -->
                    <label class="flex items-start gap-3 cursor-pointer border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/5 bg-emerald-50/40 dark:bg-emerald-500/5">
                        <input type="checkbox" id="hdEcsOff" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px] text-emerald-700 dark:text-emerald-300">ECS Off (EDNS Client Subnet)</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Nunca encaminha a subnet do cliente pros auths upstream. Mais privacidade.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer border border-cyan-200 dark:border-cyan-500/30 rounded-xl p-3 hover:bg-cyan-50 dark:hover:bg-cyan-500/5 bg-cyan-50/40 dark:bg-cyan-500/5">
                        <input type="checkbox" id="hdTlsStrictVerify" class="mt-0.5 w-4 h-4">
                        <div>
                            <p class="font-black uppercase tracking-widest text-[11px] text-cyan-700 dark:text-cyan-300">TLS Strict Verify (DoT)</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Valida cert do upstream DoT contra o CA store do sistema. Só faz sentido em modo DoT.</p>
                        </div>
                    </label>
                </div>
                <?php if ($isAdmin): ?>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <button type="button" id="btnHdSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar hardening</button>
                    <button type="button" id="btnHdApply" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Aplicar + Restart Unbound</button>
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

    // === Privacy (qname-minimisation) ===
    async function loadPrivacy() {
        const r = await fetch('/api/v1/dns-security/privacy/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const mode = (d.settings?.dns_qname_min_mode || 'no').toLowerCase();
        const sel = mode === 'strict' ? 'qnStrict' : (mode === 'yes' ? 'qnYes' : 'qnNo');
        $(sel).checked = true;
    }

    if (IS_ADMIN) {
        $('btnQnSave')?.addEventListener('click', async () => {
            const mode = document.querySelector('input[name=qnameMode]:checked')?.value || 'no';
            const r = await fetch('/api/v1/dns-security/privacy/settings', {
                method: 'PUT', headers: HJ, body: JSON.stringify({ dns_qname_min_mode: mode }),
            });
            (window.customAlert || alert)(r.ok ? 'Salvo. Clique em Aplicar pra recarregar o Unbound.' : 'Erro.');
        });

        $('btnQnApply')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Aplicar e restart Unbound? ~2s de interrupção.') : Promise.resolve(confirm('Aplicar?')));
            if (!ok) return;
            const r = await fetch('/api/v1/dns-security/apply', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok && d.ok ? 'qname-minimisation aplicada.' : `Falha em "${d.stage || '?'}": ${d.error || r.statusText}`);
        });
    }
    loadPrivacy();

    // === Hardening v2 ===
    const HD_KEYS = [
        ['hdHideIdentity', 'dns_hide_identity'],
        ['hdHideVersion', 'dns_hide_version'],
        ['hdAggressiveNsec', 'dns_aggressive_nsec'],
        ['hdUseCapsForId', 'dns_use_caps_for_id'],
        ['hdHardenGlue', 'dns_harden_glue'],
        ['hdHardenDnssecStripped', 'dns_harden_dnssec_stripped'],
        ['hdHardenBelowNxdomain', 'dns_harden_below_nxdomain'],
        ['hdHardenReferralPath', 'dns_harden_referral_path'],
        ['hdHardenAlgoDowngrade', 'dns_harden_algo_downgrade'],
        ['hdDenyAny', 'dns_deny_any'],
        ['hdEcsOff', 'dns_ecs_off'],
        ['hdTlsStrictVerify', 'dns_tls_strict_verify'],
    ];

    async function loadHardening() {
        const r = await fetch('/api/v1/dns-security/hardening/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const s = d.settings || {};
        HD_KEYS.forEach(([el, key]) => {
            const checkbox = $(el);
            if (checkbox) checkbox.checked = String(s[key]) === '1';
        });
    }

    if (IS_ADMIN) {
        $('btnHdSave')?.addEventListener('click', async () => {
            const body = {};
            HD_KEYS.forEach(([el, key]) => { body[key] = $(el)?.checked ? '1' : '0'; });
            const r = await fetch('/api/v1/dns-security/hardening/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            (window.customAlert || alert)(r.ok ? 'Salvo. Clique em Aplicar pra recarregar o Unbound.' : 'Erro ao salvar.');
        });

        $('btnHdApply')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Aplicar hardening e restart Unbound? ~2s de interrupção.') : Promise.resolve(confirm('Aplicar?')));
            if (!ok) return;
            const r = await fetch('/api/v1/dns-security/apply', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok && d.ok ? 'Hardening aplicado.' : `Falha em "${d.stage || '?'}": ${d.error || r.statusText}`);
        });
    }
    loadHardening();

    // === DoH Inbound v2 ===
    async function loadDohInfo() {
        const r = await fetch('/api/v1/doh-inbound/info', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('dotPort').textContent = d.ports?.tls ?? '—';
        $('dohPort').textContent = d.ports?.https ?? '—';
        $('certPath').textContent = d.paths?.cert ?? '—';
        const dohPort = d.ports?.https ?? 8443;
        $('dohUrl').textContent = `https://${window.location.hostname}:${dohPort}${d.doh_path || '/dns-query'}`;

        const c = d.cert || {};
        if (!c.present) {
            $('certSubject').textContent = '⚠ cert ausente';
            $('certIssuer').textContent = '—';
            $('certNotAfter').textContent = '—';
            $('certExpiry').innerHTML = '<span class="text-red-500">ausente</span>';
            return;
        }
        if (c.parse_error) {
            $('certSubject').innerHTML = `<span class="text-red-500">erro: ${c.parse_error}</span>`;
            return;
        }
        $('certSubject').textContent = c.subject || '—';
        $('certIssuer').textContent = c.issuer || '—';
        $('certNotAfter').textContent = (c.not_after || '').replace('T', ' ').slice(0, 19);
        $('certSan').textContent = (c.san || []).join(', ') || '—';
        $('certFp').textContent = c.fingerprint_sha256 || '—';

        const days = c.days_left;
        let cls = 'text-emerald-500';
        let label = `${days}d`;
        if (c.expired) { cls = 'text-red-500'; label = `EXPIRADO há ${-days}d`; }
        else if (c.expiring_soon) { cls = 'text-amber-500'; label = `${days}d (renovar)`; }
        const selfSignedTag = c.self_signed ? ' <span class="text-[10px] text-slate-500">(self-signed)</span>' : '';
        $('certExpiry').innerHTML = `<span class="${cls}">${label}</span>${selfSignedTag}`;
    }

    if (IS_ADMIN) {
        $('btnGenCert')?.addEventListener('click', async () => {
            const cn = $('cnInput').value.trim();
            if (!cn) { (window.customAlert || alert)('Informe o Common Name (CN).'); return; }
            const days = parseInt($('daysInput').value || '365', 10);
            const restart = $('restartAfter').checked;
            const msg = `Gerar self-signed cert CN=${cn}, válido ${days} dias` + (restart ? ' + restart Unbound' : '') + '?';
            const ok = await (window.customConfirm ? customConfirm(msg) : Promise.resolve(confirm(msg)));
            if (!ok) return;
            const r = await fetch('/api/v1/doh-inbound/gen-cert', {
                method: 'POST', headers: HJ,
                body: JSON.stringify({ common_name: cn, days, restart }),
            });
            const d = await r.json().catch(() => ({}));
            if (r.ok && d.ok) {
                (window.customAlert || alert)('Cert gerado e instalado.');
                loadDohInfo();
            } else {
                (window.customAlert || alert)(`Falha: ${d.detail?.error || d.detail || d.error || r.statusText}`);
            }
        });
    }
    loadDohInfo();
})();
</script>

</body>
</html>
