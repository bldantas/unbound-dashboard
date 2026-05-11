<?php
require_once 'src/Auth.php';
require_once 'src/UnboundConfigManager.php';
require_once 'src/NetworkManager.php';
require_once 'src/SourceBalanceManager.php';
require_once 'src/BlocklistManager.php';
require_once 'src/ShellHelper.php';

\App\Auth::check();
$isAdmin = \App\Auth::isAdmin();
$requestedTab = $_POST['tab'] ?? ($_GET['tab'] ?? 'geral');
if (!$isAdmin && $requestedTab !== 'perfil') {
    header('Location: index.php');
    exit;
}

$configManager = new \App\UnboundConfigManager();
$networkManager = new \App\NetworkManager();
$sourceBalanceManager = new \App\SourceBalanceManager();
$blocklistManager = new \App\BlocklistManager();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Erro Crítico: Token de segurança (CSRF) inválido ou expirado.";
        $messageType = "error";
        $action = '';
    } else {
        $action = $_POST['action'] ?? '';
    }

    if ($action === 'save_unbound_settings') {
        $newConfig = [];
        $opts = [
            'num-threads',
            'msg-cache-size',
            'rrset-cache-size',
            'so-reuseport',
            'prefetch',
            'port',
            'do-ip4',
            'do-ip6',
            'dnssec-enabled',
            'tls-port',
            'https-port',
            'tls-service-key',
            'tls-service-pem'
        ];
        $booleanFlags = ['do-ip4', 'do-ip6', 'so-reuseport', 'prefetch', 'dnssec-enabled'];
        foreach ($opts as $key) {
            if (isset($_POST[$key])) $newConfig[$key] = trim($_POST[$key]);
            else if (in_array($key, $booleanFlags)) $newConfig[$key] = 'no';
        }
        $newConfig['interfaces'] = $_POST['interfaces'] ?? [];
        $newConfig['access-control'] = [];
        if (!empty($_POST['acl_ips'])) {
            foreach ($_POST['acl_ips'] as $idx => $ip) {
                if (!empty(trim($ip))) $newConfig['access-control'][] = ['ip' => trim($ip), 'action' => $_POST['acl_actions'][$idx] ?? 'allow'];
            }
        }
        $newConfig['forward-zones'] = [];
        if (!empty($_POST['forward_ips'])) {
            $addrs = array_filter(array_map('trim', $_POST['forward_ips']));
            if (!empty($addrs)) $newConfig['forward-zones'][] = ['name' => '.', 'addresses' => array_values($addrs)];
        }
        $res = $configManager->applyConfig($newConfig);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_local_dns') {
        $records = [];
        if (!empty($_POST['local_names'])) {
            foreach ($_POST['local_names'] as $idx => $name) {
                $name = trim($name);
                $type = trim($_POST['local_types'][$idx] ?? 'A');
                $val  = trim($_POST['local_values'][$idx] ?? '');
                if (!empty($name) && !empty($val)) {
                    $records[] = ['name' => $name, 'type' => $type, 'value' => $val];
                }
            }
        }
        $res = $configManager->applyConfig(['local_records' => $records]);
        $message = $res['success'] ? "Registros DNS locais atualizados." : "Erro ao aplicar registros: " . $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_rpz') {
        $domains = array_filter(explode("\n", str_replace("\r", "", $_POST['blocked_domains'] ?? '')));
        $settings = ['official_blocklist_enabled' => ($_POST['official_blocklist_enabled'] ?? 'no') === 'yes', 'official_blocklist_update_time' => $_POST['official_blocklist_update_time'] ?? '03:00'];

        if (isset($_POST['blacklist_source'])) {
            $blocklistManager->saveBlocklistSource($_POST['blacklist_source']);
        }

        $configManager->saveSettings($settings);
        $res = $configManager->applyConfig(['blocked_domains' => $domains]);
        $message = $res['success'] ? "Filtros de Bloqueio atualizados." : "Erro ao aplicar bloqueios: " . $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_system_network') {
        $hn = $_POST['hostname_sys'] ?? '';
        $dns = array_filter(array_map('trim', $_POST['system_dns'] ?? []));
        $resHn = $networkManager->setHostname($hn);
        $resDns = $networkManager->setSystemDNS($dns);
        $message = ($resHn['success'] && $resDns['success']) ? "Rede do host salva." : "Erro na rede.";
        $messageType = ($resHn['success'] && $resDns['success']) ? 'success' : 'error';
    } elseif ($action === 'save_interface') {
        $requestedIface = trim((string)($_POST['iface_name'] ?? ''));
        $ifaceFormKey = $requestedIface;
        $targetIface = strtolower($requestedIface) === 'lo' ? 'lo.1' : $requestedIface;

        $mode = $_POST['iface_mode'][$ifaceFormKey] ?? 'dhcp';
        $address = $_POST['iface_address'][$ifaceFormKey] ?? '';
        $gateway = $_POST['iface_gateway'][$ifaceFormKey] ?? '';
        $netmask = $_POST['iface_netmask'][$ifaceFormKey] ?? '';
        $v6_enabled = isset($_POST['iface_v6_enabled'][$ifaceFormKey]);
        $v6_mode = $_POST['iface_v6_mode'][$ifaceFormKey] ?? 'auto';
        $v6_address = $_POST['iface_v6_address'][$ifaceFormKey] ?? '';
        $v6_gateway = $_POST['iface_v6_gateway'][$ifaceFormKey] ?? '';
        $v6_netmask = $_POST['iface_v6_netmask'][$ifaceFormKey] ?? '';

        $res = $networkManager->updateInterfaceConfig($targetIface, $mode, $address, $gateway, $netmask, $v6_enabled, $v6_mode, $v6_address, $v6_gateway, $v6_netmask);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        if ($res['success']) {
            $applyRes = $networkManager->applyInterfaceChanges($targetIface);
            $message .= " " . $applyRes['message'];
            if (!$applyRes['success']) {
                $messageType = 'error';
            }

            if (strtolower($requestedIface) === 'lo') {
                $message .= ' (Configuração aplicada no alias lo.1)';
            }
        }
    } elseif ($action === 'rollback_network' && $isAdmin) {
        $res = $networkManager->restoreLastNetplanBackup();
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_ntp') {
        $ntp_servers = '';
        if (isset($_POST['ntp_servers'])) {
            $ntp_servers = is_array($_POST['ntp_servers']) ? implode(' ', array_filter($_POST['ntp_servers'])) : $_POST['ntp_servers'];
        }
        $resNtp = $networkManager->setNtpServers($ntp_servers);
        $resTz = $networkManager->setSystemTimezone($_POST['system_timezone'] ?? '');
        $message = "NTP: {$resNtp['message']} | Timezone: {$resTz['message']}";
        $messageType = ($resNtp['success'] && $resTz['success']) ? 'success' : 'error';
    } elseif ($action === 'save_source_balance') {
        $sbSettings = ['enabled' => isset($_POST['sb_enabled']), 'instances' => (int)($_POST['sb_instances'] ?? 4), 'anycast_ipv4' => $_POST['anycast_ipv4'] ?? '', 'anycast_ipv6' => $_POST['anycast_ipv6'] ?? ''];
        $sourceBalanceManager->saveSettings($sbSettings);
        $res = $sourceBalanceManager->apply();
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'service_control') {
        $op = $_POST['op'] ?? '';
        if (in_array($op, ['start', 'stop', 'restart'], true)) {
            \App\ShellHelper::exec('/usr/bin/systemctl', [$op, 'unbound'], $out, $ret, true);
            $message = "Serviço Unbound: " . strtoupper($op);
            $messageType = $ret === 0 ? 'success' : 'error';
        }
    } elseif ($action === 'add_user' && $isAdmin) {
        $res = \App\Auth::addUser($_POST['new_username'] ?? '', $_POST['new_password'] ?? '', $_POST['new_role'] ?? 'viewer', $_POST['new_email'] ?? null);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'toggle_user' && $isAdmin) {
        $res = \App\Auth::toggleUserStatus((int)$_POST['user_id']);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'delete_user' && $isAdmin) {
        $res = \App\Auth::deleteUser((int)$_POST['user_id']);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'update_profile_pass') {
        $res = \App\Auth::updatePassword($_SESSION['username'], $_POST['old_pass'], $_POST['new_pass']);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    }
}

$currentConfig = $configManager->parseConfig();
$settings = $configManager->loadSettings();
$localRecords = $configManager->loadLocalRecords();
$ifacesDetails = $networkManager->getInterfacesDetailed();
$systemHostname = $networkManager->getHostname();
$systemDnsList = array_pad($networkManager->getSystemDNS(), 2, '');
$blockedDomainsTxt = implode("\n", $configManager->loadBlocklist());
$currentNtp = $networkManager->getNtpServers();
$currentTz = $networkManager->getSystemTimezone();
$timezoneOptions = $networkManager->getAvailableTimezones();
$networkBackend = $networkManager->detectBackend();
$lastNetplanBackup = $networkBackend === 'netplan' ? $networkManager->getLastNetplanBackup() : null;
$timezoneGroups = [];
foreach ($timezoneOptions as $timezoneOption) {
    $timezoneGroup = explode('/', $timezoneOption, 2)[0] ?: 'Outros';
    $timezoneGroups[$timezoneGroup][] = $timezoneOption;
}
if (!empty($currentTz) && !in_array($currentTz, $timezoneOptions, true)) {
    $timezoneGroups['Atual'][] = $currentTz;
}
$allUsers = $isAdmin ? \App\Auth::getAllUsers() : [];
$me = null;
foreach ($allUsers as $u) {
    if ($u['id'] == $_SESSION['user_id']) {
        $me = $u;
        break;
    }
}
$sbSettings = $sourceBalanceManager->getSettings();
$currentBlocklistSource = $blocklistManager->getBlocklistSource();
\App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'unbound'], $statusOut, $tmpRet, false);
$isUnboundActive = trim($statusOut[0] ?? '') === 'active';
$sbInstances = [];
for ($i = 1; $i <= $sbSettings['instances']; $i++) {
    $id = str_pad($i, 2, '0', STR_PAD_LEFT);
    \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', "unbound{$id}"], $out, $tmpRet, false);
    $sbInstances[] = ['id' => $id, 'active' => trim($out[0] ?? '') === 'active'];
    unset($out);
}

/**
 * Retorna o valor de configuração do Unbound ou um valor padrão seguro.
 *
 * @param array $cfg Array de configurações carregadas
 * @param string $key Chave do parâmetro de configuração
 * @param string $def Valor padrão quando a chave não existir ou estiver vazia
 * @return string Valor escapado para uso seguro em formulários
 */
function getVal($cfg, $key, $def = '')
{
    return (isset($cfg[$key]) && $cfg[$key] !== '') ? htmlspecialchars($cfg[$key]) : $def;
}

/**
 * Renderiza uma caixa de seleção personalizada para uma opção booleana.
 *
 * @param string $key Nome do campo de formulário
 * @param string $label Etiqueta exibida para o checkbox
 * @param string $desc Descrição adicional exibida abaixo da etiqueta
 * @return string HTML do bloco de opção booleana
 */
function tbox($key, $label, $desc = '')
{
    global $currentConfig; // Recomendado num futuro refactor: passar por parâmetro
    $v = getVal($currentConfig, $key, 'no');
    $checked = ($v === 'yes') ? 'checked' : '';

    $descHtml = $desc ? '<p class="text-[10px] text-slate-500 mt-1 font-medium leading-relaxed">' . $desc . '</p>' : '';

    return '<label class="flex items-start bg-slate-900/5 dark:bg-white/5 p-5 rounded-2xl border border-slate-200 dark:border-white/5 transition-all hover:bg-slate-900/10 dark:hover:bg-white/10 h-full cursor-pointer group">' .
        '<div class="flex items-center h-5 mt-0.5"><input type="checkbox" name="' . $key . '" value="yes" ' . $checked . ' class="w-5 h-5 text-blue-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg focus:ring-blue-500"></div>' .
        '<div class="ml-4"><span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest group-hover:text-blue-400 transition-colors">' . $label . '</span>' . $descHtml . '</div>' .
        '</label>';
}

/**
 * Renderiza um campo de entrada de texto com etiqueta e descrição opcional.
 *
 * @param string $key Nome do campo de formulário
 * @param string $label Texto de etiqueta exibido acima do campo
 * @param string $desc Texto de ajuda exibido abaixo do campo
 * @param string $def Valor padrão que substitui a configuração atual quando informado
 * @return string HTML do campo de formulário
 */
function field($key, $label, $desc = '', $def = '')
{
    global $currentConfig; // Recomendado num futuro refactor: passar por parâmetro
    $v = ($def !== '') ? $def : getVal($currentConfig, $key, '');

    $descHtml = $desc ? '<p class="text-[9px] text-slate-600 font-bold italic">' . $desc . '</p>' : '';

    return '<div class="space-y-2">' .
        '<label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">' . $label . '</label>' .
        '<input type="text" name="' . $key . '" value="' . $v . '" class="glass-input w-full">' . $descHtml .
        '</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title>Configurações - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: tabFadeIn 0.4s ease-out; }
        @keyframes tabFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .v-tab.active { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-left: 3px solid #3b82f6; border-radius: 0 16px 16px 0; }
        .v-tab:not(.active) { color: #94a3b8; }
        .v-tab:hover:not(.active) { color: #3b82f6; background: rgba(0, 0, 0, 0.02); }
        html.dark .v-tab:hover:not(.active) { color: #f1f5f9; background: rgba(255, 255, 255, 0.02); }
        .v-tab[data-unsaved-changed="true"] { border-left: 3px solid rgba(245, 158, 11, 0.9); }
        .v-tab[data-unsaved-changed="true"]::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 9999px;
            background: rgb(245, 158, 11);
            float: right;
            margin-top: 4px;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        }
        .tab-content[data-unsaved-changed="true"] > .glass-panel:first-child {
            border-color: rgba(245, 158, 11, 0.4);
            box-shadow: 0 18px 45px rgba(245, 158, 11, 0.08);
        }
        #unsavedSummaryBadge {
            display: none;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.75rem;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: rgb(180, 83, 9);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        #unsavedSummaryBadge[data-visible="true"] { display: inline-flex; }
        html.dark #unsavedSummaryBadge {
            color: rgb(253, 230, 138);
            background: rgba(245, 158, 11, 0.16);
            border-color: rgba(245, 158, 11, 0.45);
        }
        #activeTabUnsavedHint {
            display: none;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1.25rem;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.18);
            color: rgb(29, 78, 216);
            font-size: 12px;
            font-weight: 700;
        }
        #activeTabUnsavedHint[data-visible="true"] { display: flex; }
        html.dark #activeTabUnsavedHint {
            color: rgb(191, 219, 254);
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.22);
        }
        .v-tab[data-unsaved-changed="true"]:not(.active) {
            color: rgb(180, 83, 9);
            background: rgba(245, 158, 11, 0.08);
        }
        html.dark .v-tab[data-unsaved-changed="true"]:not(.active) {
            color: rgb(253, 230, 138);
            background: rgba(245, 158, 11, 0.12);
        }
        #btnSaveMain[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none;
            filter: grayscale(0.15);
        }
        #btnSaveMain[data-has-unsaved="true"] {
            background: linear-gradient(135deg, rgb(245, 158, 11), rgb(217, 119, 6));
            box-shadow: 0 18px 40px rgba(245, 158, 11, 0.28);
        }
        #btnSaveMain[data-has-unsaved="true"]:hover {
            background: linear-gradient(135deg, rgb(251, 191, 36), rgb(245, 158, 11));
        }
    </style>
</head>

<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php 
        $pageTitle = "Configurações do Sistema";
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container">
            <div class="flex flex-col lg:flex-row gap-10">
                <aside class="w-full lg:w-72 flex-shrink-0">
                    <nav class="glass-panel !p-2 rounded-3xl border border-slate-200 dark:border-white/5 space-y-1">
                        <?php $tabs = $isAdmin ? ['geral' => 'Configurações Unbound', 'tls' => 'Criptografia DoT/DoH', 'local_dns' => 'Registros Locais', 'source_balance' => 'Múltiplos Processos', 'forwarders' => 'DNS Forwarders', 'rpz' => 'Lista de Bloqueios', 'acl' => 'Controle de Acesso', 'config_rede' => 'Configurações de Rede', 'ntp' => 'Tempo & NTP', 'usuarios' => 'Gestão de Usuários'] : ['perfil' => 'Meu Perfil'];
                        $activeTab = in_array($requestedTab, array_keys($tabs)) ? $requestedTab : array_key_first($tabs);
                        foreach ($tabs as $id => $label): ?>
                            <button onclick="switchTab('<?= $id ?>')" id="vtab-<?= $id ?>" class="v-tab <?= $id === $activeTab ? 'active' : '' ?> w-full text-left px-6 py-4 text-[11px] font-black uppercase tracking-widest transition-all"><?= $label ?></button>
                        <?php endforeach; ?>
                    </nav>
                </aside>


                <div class="flex-1 min-w-0">
                    <form method="POST" id="mainConfigForm"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="action" value="save_unbound_settings" id="unboundActionField"><input type="hidden" name="iface_name" id="ifaceNameField" value=""><input type="hidden" name="tab" value="<?= $activeTab ?>" id="tabField">
                        <div id="unsavedSummaryBadge" data-visible="false">
                            <span>Alterações pendentes:</span>
                            <strong id="unsavedSummaryCount">0 campos</strong>
                        </div>
                        <div id="activeTabUnsavedHint" data-visible="false">
                            <span id="activeTabUnsavedText">Esta aba possui alterações pendentes.</span>
                        </div>

                        <div id="tab-geral" class="tab-content <?= $activeTab === 'geral' ? 'active' : '' ?> space-y-8">
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Desempenho & Daemon</h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10"><?= field('num-threads', 'Núcleos Unbound') ?><?= field('port', 'Porta DNS') ?></div>
                                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?= tbox('do-ip4', 'IPv4 Ativo') ?>
                                        <?= tbox('do-ip6', 'IPv6 Ativo') ?>
                                        <?= tbox('prefetch', 'Cache Prefetch') ?>
                                        <?= tbox('so-reuseport', 'SO_REUSEPORT') ?>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4">
                                        <?= tbox('dnssec-enabled', 'DNSSEC Ativo', 'Habilite validação DNSSEC e auto-trust-anchor-file para o Unbound.') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Interfaces de Escuta</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4"><?php $listenIfaces = $currentConfig['interfaces'] ?? [];
                                                                                    foreach ($ifacesDetails as $iface): foreach ($iface['addr_info'] as $info): $ip = $info['local'] ?? null;
                                                                                            if (!$ip) continue;
                                                                                            $family = ($info['family'] === 'inet') ? 'IPv4' : 'IPv6';
                                                                                            $checked = in_array($ip, $listenIfaces) ? 'checked' : ''; ?>
                                            <label class="flex items-center bg-slate-900/5 dark:bg-white/5 p-4 rounded-2xl border border-slate-200 dark:border-white/5 hover:bg-blue-600/5 transition-all cursor-pointer group"><input type="checkbox" name="interfaces[]" value="<?= $ip ?>" <?= $checked ?> class="w-5 h-5 text-blue-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                                <div class="ml-4">
                                                    <div class="text-[10px] font-black text-slate-900 dark:text-white uppercase"><?= htmlspecialchars($iface['ifname']) ?> <span class="text-slate-500 font-bold ml-1"><?= $family ?></span></div>
                                                    <div class="text-[11px] font-mono text-blue-400"><?= htmlspecialchars($ip) ?></div>
                                                </div>
                                            </label><?php endforeach;
                                                                                    endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div id="tab-tls" class="tab-content <?= $activeTab === 'tls' ? 'active' : '' ?> space-y-8">
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">DNS over TLS (DoT) & HTTPS (DoH)</h3>

                                <p class="text-xs text-slate-400 mb-6 leading-relaxed">Habilite a escuta criptografada do Unbound fornecendo as portas e os caminhos absolutos dos certificados SSL (Requer reinício manual posterior).</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                                    <?= field('tls-port', 'Porta TLS (DoT)', 'Padrão Unbound: 853. Deixe em branco para inativar.') ?>
                                    <?= field('https-port', 'Porta HTTPS (DoH)', 'Padrão Unbound: 443. Deixe em branco para inativar.') ?>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <?= field('tls-service-pem', 'Caminho do Certificado Público (.pem / .crt)', 'Exemplo: /etc/letsencrypt/live/seusite.com/fullchain.pem') ?>
                                    <?= field('tls-service-key', 'Caminho da Chave Privada (.key)', 'Exemplo: /etc/letsencrypt/live/seusite.com/privkey.pem') ?>
                                </div>
                            </div>
                        </div>

                        <div id="tab-local_dns" class="tab-content space-y-8">
                            <div class="glass-panel">
                                <div class="flex justify-between items-center mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Zonas Locais Customizadas</h3>
                                    <button type="button" onclick="addLocalDnsRow()" class="glass-btn text-[10px] font-black uppercase border-slate-900/10 dark:border-white/5">Adicionar Registro</button>
                                </div>

                                <p class="text-xs text-slate-400 mb-6">Redirecione domínios para IPs locais específicos da sua infraestrutura (A, AAAA, CNAME).</p>
                                <div id="local-dns-list" class="space-y-3">
                                    <?php foreach ($localRecords as $rec): ?>
                                        <div class="flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5 animate-fade-in">
                                            <input type="text" name="local_names[]" value="<?= htmlspecialchars($rec['name']) ?>" placeholder="Ex: roteador.lan" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none">
                                            <select name="local_types[]" class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none">
                                                <option value="A" <?= $rec['type'] === 'A' ? 'selected' : '' ?>>A (IPv4)</option>
                                                <option value="AAAA" <?= $rec['type'] === 'AAAA' ? 'selected' : '' ?>>AAAA (IPv6)</option>
                                                <option value="CNAME" <?= $rec['type'] === 'CNAME' ? 'selected' : '' ?>>CNAME</option>
                                            </select>
                                            <input type="text" name="local_values[]" value="<?= htmlspecialchars($rec['value']) ?>" placeholder="Ex: 192.168.1.1" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none">

                                            <button type="button" onclick="this.parentElement.remove()" class="text-red-500/50 p-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 18L18 6M6 6l12 12"></path>
                                                </svg></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div id="tab-source_balance" class="tab-content space-y-8">
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                                <div class="glass-panel">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Múltiplos Processos (Multi-core)</h3><label class="bg-blue-600/10 border border-blue-500/20 p-6 rounded-3xl flex items-center gap-5 cursor-pointer mb-8"><input type="checkbox" name="sb_enabled" value="yes" <?= $sbSettings['enabled'] ? 'checked' : '' ?> class="w-6 h-6 text-blue-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                        <div><span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Habilitar Multi-Instance</span>

                                            <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase italic"><?= $sbSettings['enabled'] ? 'Ativo' : 'Inativo' ?></p>
                                        </div>
                                    </label>
                                    <div class="space-y-6"><?= field('sb_instances', 'Instâncias (Cores)', 'Padrão: 4', $sbSettings['instances']) ?><div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div><label class="block text-[10px] font-black text-slate-500 mb-2 uppercase">IPv4 Anycast</label><input type="text" name="anycast_ipv4" value="<?= htmlspecialchars($sbSettings['anycast_ipv4']) ?>" class="glass-input w-full font-mono text-xs"></div>
                                            <div><label class="block text-[10px] font-black text-slate-500 mb-2 uppercase">IPv6 Anycast</label><input type="text" name="anycast_ipv6" value="<?= htmlspecialchars($sbSettings['anycast_ipv6']) ?>" class="glass-input w-full font-mono text-xs"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="glass-panel">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Status Instâncias</h3>
                                    <div class="grid grid-cols-3 gap-3"><?php foreach ($sbInstances as $si): ?><div class="bg-slate-900/5 dark:bg-white/5 p-4 rounded-2xl border border-slate-200 dark:border-white/5 text-center">

                                                <div class="w-8 h-8 rounded-full mx-auto flex items-center justify-center <?= $si['active'] ? 'bg-emerald-500 text-white' : 'bg-slate-800 text-slate-600' ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                    </svg></div>
                                                <p class="text-[10px] font-black text-slate-900 dark:text-white mt-2"><?= $si['id'] ?></p>
                                                <p class="text-[8px] font-bold <?= $si['active'] ? 'text-emerald-500' : 'text-red-500' ?>"><?= $si['active'] ? 'UP' : 'DOWN' ?></p>

                                            </div><?php endforeach; ?></div>
                                </div>
                            </div>
                        </div>

                        <div id="tab-forwarders" class="tab-content space-y-8">
                            <div class="glass-panel">
                                <div class="flex justify-between items-center mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">DNS Forwarders</h3><button type="button" onclick="addFwdRow()" class="glass-btn text-[10px] font-black uppercase">Adicionar IP</button>
                                </div>

                                <div id="fwd-list" class="space-y-3"><?php $fwds = [];
                                                                        foreach ($currentConfig['forward-zones'] ?? [] as $fz) if ($fz['name'] === '.') $fwds = $fz['addresses'];
                                                                        foreach ($fwds as $ip): ?><div class="flex gap-3 animate-fade-in"><input type="text" name="forward_ips[]" value="<?= htmlspecialchars($ip) ?>" class="glass-input flex-1 font-mono"><button type="button" onclick="this.parentElement.remove()" class="p-4 text-red-500 hover:bg-red-500/10 rounded-2xl transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 18L18 6M6 6l12 12"></path>
                                                </svg></button></div><?php endforeach; ?></div>
                            </div>
                        </div>

                        <div id="tab-rpz" class="tab-content space-y-8">
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Listas de Bloqueio</h3>


                                <div class="mb-8">
                                    <label for="blacklist_source" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Fonte da Blacklist Principal</label>
                                    <select name="blacklist_source" id="blacklist_source" class="glass-input w-full">
                                        <option value="stevenblack" <?= $currentBlocklistSource === 'stevenblack' ? 'selected' : '' ?>>StevenBlack (Padrão Unificado)</option>
                                        <option value="hagezi_normal" <?= $currentBlocklistSource === 'hagezi_normal' ? 'selected' : '' ?>>Hagezi Normal</option>
                                        <option value="hagezi_pro" <?= $currentBlocklistSource === 'hagezi_pro' ? 'selected' : '' ?>>Hagezi Pro</option>
                                    </select>
                                    <p class="text-[9px] text-slate-600 font-bold italic mt-2">A lista selecionada será baixada quando a atualização da blacklist for acionada.</p>
                                </div>

                                <hr class="border-white/10 my-8">

                                <label class="bg-blue-600/5 dark:bg-white/5 border border-slate-200 dark:border-white/5 p-6 rounded-3xl flex items-center justify-between cursor-pointer mb-8">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="official_blocklist_enabled" value="yes" <?= ($settings['official_blocklist_enabled'] ?? false) ? 'checked' : '' ?> class="w-6 h-6 text-blue-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                        <div><span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Sincronização Anablock (Judicial)</span></div>
                                    </div>
                                    <input type="time" name="official_blocklist_update_time" value="<?= htmlspecialchars($settings['official_blocklist_update_time'] ?? '03:00') ?>" class="glass-input !py-2 !px-4 text-xs font-mono">
                                </label>


                                <h3 class="text-[10px] font-black text-slate-500 uppercase mb-4">Domínios Locais</h3>
                                <textarea name="blocked_domains" rows="12" class="glass-input w-full font-mono text-xs"><?= htmlspecialchars($blockedDomainsTxt) ?></textarea>
                            </div>
                        </div>

                        <div id="tab-acl" class="tab-content space-y-8">
                            <div class="glass-panel">
                                <div class="flex justify-between items-center mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">ACLs</h3><button type="button" onclick="addAclRow()" class="glass-btn text-[10px] font-black uppercase">Nova Regra</button>
                                </div>

                                <div id="acl-list" class="space-y-3"><?php foreach ($currentConfig['access-control'] ?? [] as $acl): ?><div class="flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5"><input type="text" name="acl_ips[]" value="<?= htmlspecialchars($acl['ip']) ?>" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none"><select name="acl_actions[]" class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none">
                                                <option value="allow" <?= $acl['action'] === 'allow' ? 'selected' : '' ?>>ALLOW</option>
                                                <option value="deny" <?= $acl['action'] === 'deny' ? 'selected' : '' ?>>DENY</option>
                                                <option value="refuse" <?= $acl['action'] === 'refuse' ? 'selected' : '' ?>>REFUSE</option>
                                            </select><button type="button" onclick="this.parentElement.remove()" class="text-red-500/50 p-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 18L18 6M6 6l12 12"></path>
                                                </svg></button></div><?php endforeach; ?></div>

                            </div>
                        </div>

                        <div id="tab-config_rede" class="tab-content space-y-8">
                            <div class="glass-panel mb-4 border-l-4 <?= $networkBackend === 'netplan' ? 'border-blue-500' : 'border-amber-500' ?>">
                                <div class="flex items-center justify-between gap-4 flex-wrap">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Backend de Rede Detectado</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            <?php if ($networkBackend === 'netplan'): ?>
                                                <span class="text-blue-600 dark:text-blue-400">netplan</span> — mudanças escritas em <code class="text-xs">/etc/netplan/99-unbound-dashboard.yaml</code> e aplicadas com <code class="text-xs">netplan apply</code>.
                                            <?php else: ?>
                                                <span class="text-amber-600 dark:text-amber-400">ifupdown (legacy)</span> — <code class="text-xs">/etc/network/interfaces</code> + <code class="text-xs">ifdown/ifup</code>.
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-2">
                                            ⚠ Mudanças de IP/gateway na interface que serve sua sessão SSH podem derrubar a conexão. Tenha acesso ao console local antes de salvar.
                                        </p>
                                    </div>
                                    <?php if ($networkBackend === 'netplan' && $lastNetplanBackup): ?>
                                        <button type="submit" name="action" value="rollback_network"
                                                onclick="return confirm('Restaurar a versão anterior do YAML netplan e re-aplicar? Isto reverte a última mudança de rede salva.');"
                                                class="glass-btn text-[10px] font-black uppercase border border-amber-500/40 text-amber-700 dark:text-amber-300">
                                            ↩ Reverter última mudança
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="glass-panel mb-8">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-4">Rede do Host</h3>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8"><?= field('hostname_sys', 'Nome do Servidor', '', $systemHostname) ?><div><label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Internal DNS</label>
                                        <div class="space-y-2"><?php foreach ($systemDnsList as $dns): ?><input type="text" name="system_dns[]" value="<?= htmlspecialchars($dns) ?>" class="glass-input w-full font-mono"><?php endforeach; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-6"><?php foreach ($ifacesDetails as $iface): if (strpos($iface['ifname'], 'veth') !== false || strpos($iface['ifname'], 'docker') !== false) continue;
                                                        $ifConf = $networkManager->getInterfaceConfig($iface['ifname']); ?>
                                    <div class="glass-panel border-l-4 <?= $iface['ifname'] === 'lo' ? 'border-yellow-500' : 'border-blue-500' ?> border-slate-900/10 dark:border-white/5">
                                        <h4 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6"><?= htmlspecialchars($iface['ifname']) ?></h4>
                                        <?php if ($iface['ifname'] === 'lo'): ?>
                                            <div class="mb-6 p-4 rounded-2xl border border-yellow-500/30 bg-yellow-500/10 text-yellow-700 dark:text-yellow-300 text-[11px] font-bold uppercase tracking-wider">
                                                Alterações da LO serão aplicadas no alias LO.1
                                            </div>
                                        <?php endif; ?>

                                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                                            <div class="space-y-4"><select name="iface_mode[<?= $iface['ifname'] ?>]" onchange="toggleIfaceMode(this, '<?= $iface['ifname'] ?>')" class="glass-input w-full uppercase text-[10px] font-black" <?= $iface['ifname'] === 'lo' ? 'disabled' : '' ?>>
                                                    <option value="dhcp" <?= $ifConf['mode'] === 'dhcp' ? 'selected' : '' ?>>DHCP</option>
                                                    <option value="static" <?= $ifConf['mode'] === 'static' || $iface['ifname'] === 'lo' ? 'selected' : '' ?>>STATIC</option>
                                                </select>
                                                <div id="<?= $iface['ifname'] ?>-static" class="<?= ($ifConf['mode'] === 'dhcp' && $iface['ifname'] !== 'lo') ? 'hidden' : '' ?> space-y-3"><input type="text" name="iface_address[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['address']) ?>" placeholder="IPv4 Address" class="glass-input w-full font-mono text-xs"><input type="text" name="iface_netmask[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['netmask']) ?>" placeholder="Netmask" class="glass-input w-full font-mono text-xs"><input type="text" name="iface_gateway[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['gateway']) ?>" placeholder="Gateway" class="glass-input w-full font-mono text-xs"></div>
                                            </div>
                                            <div class="space-y-4"><label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="iface_v6_enabled[<?= $iface['ifname'] ?>]" value="yes" <?= $ifConf['v6_enabled'] ? 'checked' : '' ?> onchange="toggleIpv6(this, '<?= $iface['ifname'] ?>')" class="w-5 h-5 text-emerald-500 bg-slate-900 border-white/10 rounded-lg"><span class="text-[10px] font-black text-slate-500 uppercase">Habilitar IPv6</span></label>
                                                <div id="<?= $iface['ifname'] ?>-v6" class="<?= !$ifConf['v6_enabled'] ? 'hidden' : '' ?> space-y-3"><select name="iface_v6_mode[<?= $iface['ifname'] ?>]" onchange="toggleIfaceModeV6(this, '<?= $iface['ifname'] ?>')" class="glass-input w-full uppercase text-[10px] font-black" <?= $iface['ifname'] === 'lo' ? 'disabled' : '' ?>>
                                                        <option value="auto" <?= $ifConf['v6_mode'] === 'auto' ? 'selected' : '' ?>>AUTO</option>
                                                        <option value="static" <?= $ifConf['v6_mode'] === 'static' || $iface['ifname'] === 'lo' ? 'selected' : '' ?>>STATIC</option>
                                                    </select>
                                                    <div id="<?= $iface['ifname'] ?>-v6-static" class="<?= ($ifConf['v6_mode'] === 'auto' && $iface['ifname'] !== 'lo') ? 'hidden' : '' ?> space-y-3"><input type="text" name="iface_v6_address[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['v6_address'] ?? '') ?>" placeholder="IPv6 Address" class="glass-input w-full font-mono text-xs"><input type="text" name="iface_v6_netmask[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['v6_netmask'] ?? '') ?>" placeholder="Prefix (e.g. 64)" class="glass-input w-full font-mono text-xs"><input type="text" name="iface_v6_gateway[<?= $iface['ifname'] ?>]" value="<?= htmlspecialchars($ifConf['v6_gateway'] ?? '') ?>" placeholder="IPv6 Gateway" class="glass-input w-full font-mono text-xs"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end mt-6 pt-6 border-t border-slate-900/10 dark:border-white/5"><button type="submit" name="action" value="save_interface" onclick="setIfaceName('<?= htmlspecialchars($iface['ifname']) ?>')" class="glass-btn text-[10px] font-black uppercase"><?= $iface['ifname'] === 'lo' ? 'Salvar como LO.1' : 'Salvar Interface' ?></button></div>
                                    </div>

                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="tab-ntp" class="tab-content space-y-8">
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Tempo & NTP</h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <label class="block text-[10px] font-black text-slate-500 uppercase">NTP Servers</label>
                                            <button type="button" onclick="addNtpRow()" class="glass-btn text-[10px] font-black uppercase">Adicionar Servidor</button>
                                        </div>
                                        <div id="ntp-list" class="space-y-3">
                                            <?php
                                            $ntpServers = !empty($currentNtp) ? explode(' ', $currentNtp) : [''];
                                            foreach ($ntpServers as $server): ?>
                                                <div class="flex gap-3 animate-fade-in">
                                                    <input type="text" name="ntp_servers[]" value="<?= htmlspecialchars($server) ?>" class="glass-input flex-1 font-mono">
                                                    <button type="button" onclick="this.parentElement.remove()" class="p-4 text-red-500 hover:bg-red-500/10 rounded-2xl transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg></button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">Timezone</label>
                                        <select name="system_timezone" class="glass-input w-full font-mono">
                                            <?php if (empty($currentTz)): ?>
                                                <option value="" selected>Selecione um fuso horário</option>
                                            <?php endif; ?>
                                            <?php foreach ($timezoneGroups as $groupLabel => $groupTimezones): ?>
                                                <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                                                    <?php foreach ($groupTimezones as $timezoneOption): ?>
                                                        <option value="<?= htmlspecialchars($timezoneOption) ?>" <?= $timezoneOption === $currentTz ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($timezoneOption) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="mt-2 text-[10px] text-slate-500 font-medium">A lista contém apenas fusos reconhecidos pelo sistema para evitar erro no salvamento.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="btnSaveFloating" class="mt-10 flex justify-end animate-fade-in shadow-2xl"><button type="submit" id="btnSaveMain" class="glass-btn bg-blue-600 text-white px-12 py-4 text-xs font-black uppercase tracking-[0.2em] hover:bg-blue-500 transform hover:scale-105 transition-all">Sincronizar Todas Alterações</button></div>
                    </form>

                    <div id="tab-usuarios" class="tab-content space-y-8">
                        <?php if ($isAdmin): ?>
                            <div class="glass-panel border-l-4 border-blue-500 border-slate-900/10 dark:border-white/5">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">Gestão Admin</h3>
                                <div class="space-y-3">
                                    <?php foreach ($allUsers as $u): ?>
                                        <div class="flex items-center justify-between bg-slate-900/5 dark:bg-white/5 p-4 rounded-3xl border border-slate-200 dark:border-white/5">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-2xl bg-blue-600/10 text-blue-500 flex items-center justify-center font-black"><?= substr($u['username'], 0, 2) ?></div>
                                                <div>
                                                    <p class="text-sm font-black text-slate-900 dark:text-white"><?= htmlspecialchars($u['username']) ?></p>
                                                    <p class="text-[9px] text-slate-500 font-bold uppercase"><?= $u['role'] ?> • <?= $u['email'] ?></p>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="tab" value="usuarios">
                                                    <input type="hidden" name="action" value="toggle_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="glass-btn !py-2 !px-3 text-[9px]"><?= $u['status'] === 'active' ? 'SUSPENDER' : 'ATIVAR' ?></button>
                                                </form>
                                                <form method="POST" data-confirm-message="Deseja excluir este usuário?" data-confirm-title="Confirmar exclusão" data-confirm-text="Excluir" data-confirm-variant="danger">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="tab" value="usuarios">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="glass-btn !py-2 !px-3 text-[9px] bg-red-500/10 text-red-500">EXCLUIR</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="glass-panel border-l-4 border-emerald-500 border-slate-900/10 dark:border-white/5">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase mb-6">Novo Usuário</h3>
                                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="tab" value="usuarios">
                                    <input type="hidden" name="action" value="add_user">
                                    <input type="text" name="new_username" placeholder="Login" class="glass-input">
                                    <input type="password" name="new_password" placeholder="Senha" class="glass-input">
                                    <select name="new_role" class="glass-input">
                                        <option value="viewer">VIEWER</option>
                                        <option value="admin">ADMIN</option>
                                    </select>
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Adicionar</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="glass-panel border-slate-900/10 dark:border-white/5">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase mb-6">Alterar Senha</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="usuarios">
                                <input type="hidden" name="action" value="update_profile_pass">
                                <input type="password" name="old_pass" placeholder="SENHA ATUAL" class="glass-input w-full">
                                <input type="password" name="new_pass" placeholder="NOVA SENHA" class="glass-input w-full">
                                <button type="submit" class="glass-btn w-full text-[10px] font-black uppercase">SALVAR NOVA SENHA</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <script>
        const configUiState = {
            form: null,
            initialSnapshot: '',
            initialFieldState: {},
            changedFieldCount: 0,
            changedTabs: {},
            isDirty: false,
            isSubmitting: false,
            allowNavigation: false,
            ready: false,
        };

        function getTabIdFromElement(element) {
            const tabContainer = element.closest('.tab-content');
            if (!tabContainer || !tabContainer.id || !tabContainer.id.startsWith('tab-')) return null;
            return tabContainer.id.slice(4);
        }

        function updateUnsavedSummaryBadge() {
            const badge = document.getElementById('unsavedSummaryBadge');
            const countEl = document.getElementById('unsavedSummaryCount');
            if (!badge || !countEl) return;

            if (!configUiState.isDirty || configUiState.changedFieldCount <= 0) {
                badge.setAttribute('data-visible', 'false');
                countEl.textContent = '0 campos';
                return;
            }

            const count = configUiState.changedFieldCount;
            countEl.textContent = count + (count === 1 ? ' campo' : ' campos');
            badge.setAttribute('data-visible', 'true');
        }

        function getCurrentConfigTabId() {
            return document.getElementById('tabField')?.value || null;
        }

        function getTabLabel(tabId) {
            const button = document.getElementById('vtab-' + tabId);
            return button ? button.textContent.trim() : 'esta aba';
        }

        function updateActiveTabHint() {
            const hint = document.getElementById('activeTabUnsavedHint');
            const text = document.getElementById('activeTabUnsavedText');
            if (!hint || !text) return;

            const currentTabId = getCurrentConfigTabId();
            const currentCount = currentTabId ? (configUiState.changedTabs[currentTabId] || 0) : 0;

            if (!configUiState.isDirty || currentCount <= 0) {
                hint.setAttribute('data-visible', 'false');
                text.textContent = 'Esta aba possui alterações pendentes.';
                return;
            }

            const label = getTabLabel(currentTabId);
            text.textContent = label + ': ' + currentCount + (currentCount === 1 ? ' alteração pendente.' : ' alterações pendentes.');
            hint.setAttribute('data-visible', 'true');
        }

        function updateSaveButtonLabel() {
            const button = document.getElementById('btnSaveMain');
            if (!button) return;

            if (!configUiState.isDirty || configUiState.changedFieldCount <= 0) {
                button.disabled = true;
                button.dataset.hasUnsaved = 'false';
                button.textContent = 'Sincronizar Todas Alterações';
                return;
            }

            const count = configUiState.changedFieldCount;
            button.disabled = false;
            button.dataset.hasUnsaved = 'true';
            button.textContent = 'Salvar ' + count + (count === 1 ? ' alteração' : ' alterações');
        }

        function submitConfigForm() {
            if (!configUiState.form || configUiState.isSubmitting || !configUiState.isDirty) return;
            const currentTab = getCurrentConfigTabId();
            if (currentTab === 'usuarios') return;
            configUiState.form.requestSubmit();
        }

        function getTrackedConfigFields() {
            if (!configUiState.form) return [];

            return Array.from(configUiState.form.querySelectorAll('input, select, textarea')).filter((field) => {
                if (!field.name || field.disabled) return false;
                return !['hidden', 'submit', 'button'].includes(field.type);
            });
        }

        function getFieldStateValue(field) {
            if (field.tagName === 'SELECT' && field.multiple) {
                return Array.from(field.selectedOptions).map((option) => option.value).join('\u001f');
            }

            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked ? '1' : '0';
            }

            return field.value;
        }

        function collectConfigFieldState() {
            const countsByName = {};
            const state = {};

            getTrackedConfigFields().forEach((field) => {
                const index = countsByName[field.name] ?? 0;
                countsByName[field.name] = index + 1;
                state[field.name + '__' + index] = getFieldStateValue(field);
            });

            return state;
        }

        function setFieldChangedState(field, isChanged) {
            field.dataset.unsavedChanged = isChanged ? 'true' : 'false';

            const label = field.closest('label');
            if (label) {
                label.dataset.unsavedChanged = isChanged ? 'true' : 'false';
            }
        }

        function updateChangedFieldHighlights() {
            const currentState = collectConfigFieldState();
            const countsByName = {};
            const changedNames = new Set();
            const trackedFields = getTrackedConfigFields();
            const changedTabs = {};
            let changedFieldCount = 0;

            trackedFields.forEach((field) => {
                const index = countsByName[field.name] ?? 0;
                countsByName[field.name] = index + 1;

                const key = field.name + '__' + index;
                const initialValue = configUiState.initialFieldState[key];
                const isChanged = initialValue === undefined || initialValue !== getFieldStateValue(field);
                setFieldChangedState(field, isChanged);

                if (isChanged) {
                    changedNames.add(field.name);
                    changedFieldCount += 1;
                    const tabId = getTabIdFromElement(field);
                    if (tabId) {
                        changedTabs[tabId] = (changedTabs[tabId] || 0) + 1;
                    }
                }
            });

            Object.keys(configUiState.initialFieldState).forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(currentState, key)) return;
                const fieldName = key.replace(/__\d+$/, '');
                changedNames.add(fieldName);
                changedFieldCount += 1;

                const fallbackField = trackedFields.find((field) => field.name === fieldName);
                const tabId = fallbackField ? getTabIdFromElement(fallbackField) : null;
                if (tabId) {
                    changedTabs[tabId] = (changedTabs[tabId] || 0) + 1;
                }
            });

            configUiState.changedFieldCount = changedFieldCount;
            configUiState.changedTabs = changedTabs;

            document.querySelectorAll('.tab-content').forEach((tabContent) => {
                tabContent.setAttribute('data-unsaved-changed', 'false');
            });
            document.querySelectorAll('.v-tab').forEach((tabButton) => {
                tabButton.setAttribute('data-unsaved-changed', 'false');
            });

            Object.keys(changedTabs).forEach((tabId) => {
                const tabContent = document.getElementById('tab-' + tabId);
                const tabButton = document.getElementById('vtab-' + tabId);
                if (tabContent) tabContent.setAttribute('data-unsaved-changed', 'true');
                if (tabButton) tabButton.setAttribute('data-unsaved-changed', 'true');
            });

            if (changedNames.size === 0) {
                updateUnsavedSummaryBadge();
                updateActiveTabHint();
                updateSaveButtonLabel();
                return;
            }

            trackedFields.forEach((field) => {
                if (!changedNames.has(field.name)) return;
                const alreadyMarked = field.dataset.unsavedChanged === 'true';
                if (!alreadyMarked) {
                    setFieldChangedState(field, true);
                }
            });

            updateUnsavedSummaryBadge();
            updateActiveTabHint();
            updateSaveButtonLabel();
        }

        function serializeConfigForm() {
            if (!configUiState.form) return '[]';

            const fields = Array.from(configUiState.form.querySelectorAll('input, select, textarea'));
            const serialized = [];

            fields.forEach((field) => {
                if (!field.name || field.disabled) return;
                if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return;

                if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                    return;
                }

                if (field.tagName === 'SELECT' && field.multiple) {
                    Array.from(field.selectedOptions).forEach((option) => {
                        serialized.push([field.name, option.value]);
                    });
                    return;
                }

                serialized.push([field.name, field.value]);
            });

            serialized.sort((left, right) => {
                const leftKey = left[0] + '::' + left[1];
                const rightKey = right[0] + '::' + right[1];
                return leftKey.localeCompare(rightKey);
            });

            return JSON.stringify(serialized);
        }

        function updateConfigDirtyState() {
            if (!configUiState.ready || configUiState.isSubmitting) return;
            configUiState.isDirty = serializeConfigForm() !== configUiState.initialSnapshot;
            updateChangedFieldHighlights();
        }

        function markConfigClean() {
            configUiState.initialSnapshot = serializeConfigForm();
            configUiState.initialFieldState = collectConfigFieldState();
            configUiState.isDirty = false;
            configUiState.changedFieldCount = 0;
            configUiState.changedTabs = {};
            updateChangedFieldHighlights();
        }

        async function confirmDiscardChanges() {
            if (!configUiState.isDirty) return true;
            return window.AppUI.confirm({
                title: 'Alterações não salvas',
                message: 'Existem alterações pendentes nesta tela. Se continuar, elas serão perdidas.',
                confirmText: 'Descartar',
                cancelText: 'Continuar editando',
                variant: 'danger'
            });
        }

        function applyTabSwitch(tabId) {
            document.querySelectorAll('.v-tab').forEach(b => b.classList.remove('active'));
            document.getElementById('vtab-' + tabId).classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById('tabField').value = tabId;

            document.getElementById('btnSaveFloating').classList.toggle('hidden', tabId === 'usuarios');

            const actionMap = {
                'rpz': 'save_rpz',
                'source_balance': 'save_source_balance',
                'ntp': 'save_ntp',
                'config_rede': 'save_system_network',
                'local_dns': 'save_local_dns'
            };
            document.getElementById('unboundActionField').value = actionMap[tabId] || 'save_unbound_settings';
            updateActiveTabHint();
            updateSaveButtonLabel();
        }

        // Alterna as abas do menu de configuração e define a ação correta do formulário.
        function switchTab(tabId) {
            applyTabSwitch(tabId);
        }

        // Adiciona uma nova linha de servidor NTP ao formulário.
        function addNtpRow() {
            const list = document.getElementById('ntp-list');
            const div = document.createElement('div');
            div.className = "flex gap-3 animate-fade-in";
            div.innerHTML = `<input type="text" name="ntp_servers[]" class="glass-input flex-1 font-mono"><button type="button" onclick="this.parentElement.remove()" class="p-4 text-red-500 hover:bg-red-500/10 rounded-2xl transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>`;
            list.appendChild(div);
        }

        // Adiciona uma nova linha de ACL ao formulário de controle de acesso.
        function addAclRow() {
            const list = document.getElementById('acl-list');
            const div = document.createElement('div');
            div.className = "flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5 animate-fade-in";
            div.innerHTML = `<input type="text" name="acl_ips[]" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none"><select name="acl_actions[]" class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none"><option value="allow">ALLOW</option><option value="deny">DENY</option><option value="refuse">REFUSE</option></select><button type="button" onclick="this.parentElement.remove()" class="text-red-500/50 p-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>`;
            list.appendChild(div);
        }

        // Adiciona uma nova linha de entrada para encaminhadores DNS.
        function addFwdRow() {
            const list = document.getElementById('fwd-list');
            const div = document.createElement('div');
            div.className = "flex gap-3 animate-fade-in";
            div.innerHTML = `<input type="text" name="forward_ips[]" class="glass-input flex-1 font-mono"><button type="button" onclick="this.parentElement.remove()" class="p-4 text-red-500 hover:bg-red-500/10 rounded-2xl transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>`;
            list.appendChild(div);
        }

        // Adiciona uma nova linha de registro DNS local ao formulário.
        function addLocalDnsRow() {
            const list = document.getElementById('local-dns-list');
            const div = document.createElement('div');
            div.className = "flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5 animate-fade-in";
            div.innerHTML = `<input type="text" name="local_names[]" placeholder="Ex: intranet.local" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none"><select name="local_types[]" class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none"><option value="A">A (IPv4)</option><option value="AAAA">AAAA (IPv6)</option><option value="CNAME">CNAME</option></select><input type="text" name="local_values[]" placeholder="Ex: 10.0.0.50" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none"><button type="button" onclick="this.parentElement.remove()" class="text-red-500/50 p-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>`;
            list.appendChild(div);
        }

        // Alterna a exibição dos campos estáticos de IPv4 conforme o modo da interface.
        function toggleIfaceMode(sel, name) {
            document.getElementById(name + '-static').classList.toggle('hidden', sel.value !== 'static');
        }

        function toggleIpv6(checkbox, name) {
            const v6Block = document.getElementById(name + '-v6');
            const v6StaticBlock = document.getElementById(name + '-v6-static');
            if (!v6Block) return;

            v6Block.classList.toggle('hidden', !checkbox.checked);

            if (!checkbox.checked) {
                if (v6StaticBlock) v6StaticBlock.classList.add('hidden');
                return;
            }

            const v6Mode = v6Block.querySelector('select');
            if (v6Mode && v6Mode.value !== 'static' && v6StaticBlock) {
                v6StaticBlock.classList.add('hidden');
            }
        }

        function setIfaceName(name) {
            const field = document.getElementById('ifaceNameField');
            if (field) field.value = name;
        }

        // Alterna a exibição dos campos estáticos de IPv6 conforme o modo da interface.
        function toggleIfaceModeV6(sel, name) {
            document.getElementById(name + '-v6-static').classList.toggle('hidden', sel.value !== 'static');
        }
        window.addEventListener('DOMContentLoaded', () => {
            configUiState.form = document.getElementById('mainConfigForm');
            const initialTab = "<?= $activeTab ?>";
            applyTabSwitch(initialTab);

            if (configUiState.form) {
                configUiState.form.addEventListener('input', updateConfigDirtyState);
                configUiState.form.addEventListener('change', updateConfigDirtyState);
                configUiState.form.addEventListener('submit', () => {
                    configUiState.isSubmitting = true;
                    configUiState.allowNavigation = true;
                    configUiState.isDirty = false;
                    const saveButton = document.getElementById('btnSaveMain');
                    if (saveButton) {
                        saveButton.disabled = true;
                        saveButton.dataset.hasUnsaved = 'false';
                        saveButton.textContent = 'Salvando...';
                    }
                });

                let mutationTimer = null;
                const observer = new MutationObserver(() => {
                    if (!configUiState.ready) return;
                    clearTimeout(mutationTimer);
                    mutationTimer = setTimeout(updateConfigDirtyState, 80);
                });
                observer.observe(configUiState.form, { childList: true, subtree: true });

                // Atrasa o snapshot para depois do navegador finalizar autofill e renderização
                requestAnimationFrame(() => {
                    markConfigClean();
                    configUiState.ready = true;
                });
            }

            document.querySelectorAll('a[href]').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    const href = link.getAttribute('href') || '';
                    if (!configUiState.isDirty || configUiState.allowNavigation) return;
                    if (link.target === '_blank' || href.startsWith('#') || href.startsWith('javascript:')) return;
                    if (/^https?:\/\//i.test(href) && !href.startsWith(window.location.origin)) return;

                    event.preventDefault();
                    const confirmed = await confirmDiscardChanges();
                    if (!confirmed) return;

                    configUiState.allowNavigation = true;
                    window.location.href = href;
                });
            });

            window.addEventListener('beforeunload', (event) => {
                if (!configUiState.isDirty || configUiState.isSubmitting || configUiState.allowNavigation) return;
                event.preventDefault();
                event.returnValue = '';
            });

            window.addEventListener('keydown', (event) => {
                const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';
                if (!isSaveShortcut) return;
                if (!configUiState.isDirty || configUiState.isSubmitting) return;

                event.preventDefault();
                submitConfigForm();
            });

            <?php if ($message): ?>
                window.AppUI.toast(<?= json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode($messageType ?: 'info') ?>, {
                    title: <?= json_encode($messageType === 'success' ? 'Alterações aplicadas' : 'Operação não concluída', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>