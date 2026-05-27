<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
require_once 'src/UnboundConfigManager.php';
require_once 'src/NetworkManager.php';
require_once 'src/SourceBalanceManager.php';
require_once 'src/BlocklistManager.php';
require_once 'src/ShellHelper.php';
require_once 'src/TlsCertManager.php';

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
$tlsCertManager = new \App\TlsCertManager();

$message = '';
$messageType = '';
$tempPassword = null;
$tempPasswordUser = null;

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
        $antiDohEnabled = ($_POST['anti_doh_enabled'] ?? 'no') === 'yes';
        // Merge com settings antigas pra não dropar outras chaves (ex: SMTP).
        $prevSettings = $configManager->loadSettings();
        $settings = array_merge($prevSettings, [
            'official_blocklist_enabled'     => ($_POST['official_blocklist_enabled'] ?? 'no') === 'yes',
            'official_blocklist_update_time' => $_POST['official_blocklist_update_time'] ?? '03:00',
            'anti_doh_enabled'               => $antiDohEnabled,
        ]);

        if (isset($_POST['blacklist_source'])) {
            $blocklistManager->saveBlocklistSource($_POST['blacklist_source']);
        }

        // Toggle: fonte ativa/pausada — checkbox vem como 'yes' quando marcado, ausente quando não.
        $blocklistManager->saveBlacklistSourceEnabled(($_POST['blacklist_source_enabled'] ?? 'no') === 'yes');

        $configManager->saveSettings($settings);
        $res = $configManager->applyConfig([
            'blocked_domains' => $domains,
            'anti_doh_enabled' => $antiDohEnabled,
        ]);
        $message = $res['success'] ? "Filtros de Bloqueio atualizados." : "Erro ao aplicar bloqueios: " . $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_system_network') {
        $hn = $_POST['hostname_sys'] ?? '';
        $dns = array_filter(array_map('trim', $_POST['system_dns'] ?? []));
        $resHn = $networkManager->setHostname($hn);
        $resDns = $networkManager->setSystemDNS($dns);
        $message = ($resHn['success'] && $resDns['success']) ? "Rede do host salva." : "Erro na rede.";
        $messageType = ($resHn['success'] && $resDns['success']) ? 'success' : 'error';
    } elseif ($action === 'tls_generate_cert') {
        $cn = trim((string)($_POST['cert_cn'] ?? ''));
        $sansRaw = trim((string)($_POST['cert_sans'] ?? ''));
        $days = (int)($_POST['cert_days'] ?? 825);
        $sans = $sansRaw === '' ? [] : array_filter(array_map('trim', preg_split('/[\s,]+/', $sansRaw)));
        $res = $tlsCertManager->generateSelfSigned($cn, $sans, $days);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        // Auto-aplica os paths gerenciados no unbound.conf — sem ter que digitar.
        if ($res['success']) {
            $applyRes = $configManager->applyConfig([
                'tls-service-pem' => \App\TlsCertManager::MANAGED_CRT,
                'tls-service-key' => \App\TlsCertManager::MANAGED_KEY,
            ]);
            if ($applyRes['success']) {
                $message .= ' Caminhos auto-preenchidos no Unbound.';
            } else {
                $message .= ' (Atenção: cert gerado mas falha ao aplicar paths no Unbound: ' . $applyRes['message'] . ')';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'tls_upload_cert') {
        $certPem = (string)($_POST['cert_pem'] ?? '');
        $keyPem  = (string)($_POST['cert_key'] ?? '');
        $res = $tlsCertManager->uploadCert($certPem, $keyPem);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        if ($res['success']) {
            $applyRes = $configManager->applyConfig([
                'tls-service-pem' => \App\TlsCertManager::MANAGED_CRT,
                'tls-service-key' => \App\TlsCertManager::MANAGED_KEY,
            ]);
            if ($applyRes['success']) {
                $message .= ' Caminhos auto-preenchidos no Unbound.';
            }
        }
    } elseif ($action === 'tls_import_letsencrypt') {
        $lineage = trim((string)($_POST['le_lineage'] ?? ''));
        $res = $tlsCertManager->importFromLetsEncrypt($lineage);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        // Auto-aplica paths no unbound.conf (mesmo padrão do tls_generate_cert)
        if ($res['success']) {
            $configManager->applyConfig([
                'tls-service-pem' => \App\TlsCertManager::MANAGED_CRT,
                'tls-service-key' => \App\TlsCertManager::MANAGED_KEY,
            ]);
            $message .= ' Caminhos atualizados no Unbound. Reinicie o serviço pra ativar.';
        }
    } elseif ($action === 'tls_remove_cert') {
        $res = $tlsCertManager->removeCert();
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        // Limpa os paths do unbound.conf quando o managed é removido
        if ($res['success']) {
            $configManager->applyConfig([
                'tls-service-pem' => '',
                'tls-service-key' => '',
            ]);
        }
    } elseif ($action === 'delete_interface') {
        $requestedIface = trim((string)($_POST['iface_name'] ?? ''));
        // Pra card da loopback, o "remover" age no alias lo.1 (mesma
        // semântica do save_interface).
        $targetIface = strtolower($requestedIface) === 'lo' ? 'lo.1' : $requestedIface;
        $res = $networkManager->removeInterfaceConfig($targetIface);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'save_interface') {
        $requestedIface = trim((string)($_POST['iface_name'] ?? ''));
        $ifaceFormKey = $requestedIface;
        $isLoAlias = strtolower($requestedIface) === 'lo';
        $targetIface = $isLoAlias ? 'lo.1' : $requestedIface;

        // No card da loopback, o <select> de modo fica `disabled` (UX: só
        // faz sentido static no lo.1). Mas inputs disabled NÃO são enviados
        // no POST, então o backend recebia vazio e caía no default 'dhcp'
        // — gerava `iface lo.1 inet dhcp` mesmo com IP preenchido. Força
        // static aqui pra fechar a brecha.
        $mode = $_POST['iface_mode'][$ifaceFormKey] ?? 'dhcp';
        if ($isLoAlias) $mode = 'static';
        $address = $_POST['iface_address'][$ifaceFormKey] ?? '';
        $gateway = $_POST['iface_gateway'][$ifaceFormKey] ?? '';
        $netmask = $_POST['iface_netmask'][$ifaceFormKey] ?? '';
        $v6_enabled = isset($_POST['iface_v6_enabled'][$ifaceFormKey]);
        $v6_mode = $_POST['iface_v6_mode'][$ifaceFormKey] ?? 'auto';
        if ($isLoAlias && $v6_enabled) $v6_mode = 'static';
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
        // Mantido pra compat com forms antigos que mandam os dois juntos.
        $ntp_servers = '';
        if (isset($_POST['ntp_servers'])) {
            $ntp_servers = is_array($_POST['ntp_servers']) ? implode(' ', array_filter($_POST['ntp_servers'])) : $_POST['ntp_servers'];
        }
        $resNtp = $networkManager->setNtpServers($ntp_servers);
        $resTz = $networkManager->setSystemTimezone($_POST['system_timezone'] ?? '');
        $message = "NTP: {$resNtp['message']} | Timezone: {$resTz['message']}";
        $messageType = ($resNtp['success'] && $resTz['success']) ? 'success' : 'error';
    } elseif ($action === 'save_ntp_only') {
        $ntp_servers = '';
        if (isset($_POST['ntp_servers'])) {
            $ntp_servers = is_array($_POST['ntp_servers']) ? implode(' ', array_filter($_POST['ntp_servers'])) : $_POST['ntp_servers'];
        }
        $resNtp = $networkManager->setNtpServers($ntp_servers);
        $message = $resNtp['message'];
        $messageType = $resNtp['success'] ? 'success' : 'error';
    } elseif ($action === 'save_timezone_only') {
        $tz = trim($_POST['system_timezone'] ?? '');
        if ($tz === '') {
            $message = 'Nenhum fuso horário informado.';
            $messageType = 'error';
        } else {
            $resTz = $networkManager->setSystemTimezone($tz);
            $message = $resTz['message'];
            $messageType = $resTz['success'] ? 'success' : 'error';
        }
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
    } elseif ($action === 'save_email_config' && $isAdmin) {
        require_once __DIR__ . '/src/Mailer.php';
        $entries = [
            'smtp_enabled'    => isset($_POST['smtp_enabled']) ? '1' : '0',
            'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'       => (int)($_POST['smtp_port'] ?? 587),
            'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['none','tls','ssl'], true) ? $_POST['smtp_encryption'] : 'tls',
            'smtp_user'       => trim($_POST['smtp_user'] ?? ''),
            'smtp_from'       => trim($_POST['smtp_from'] ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? 'Unbound Dashboard'),
            'notify_email_on_release' => isset($_POST['notify_email_on_release']) ? '1' : '0',
        ];
        // Só sobrescreve a senha se o user digitou algo novo (campo vem vazio
        // se ele não tocou — evita perda acidental).
        $newPass = $_POST['smtp_password'] ?? '';
        if ($newPass !== '') {
            $entries['smtp_password'] = $newPass;
        }
        $res = \App\Mailer::saveConfig($entries);
        $message = $res['message'];
        $messageType = !empty($res['success']) ? 'success' : 'error';
    } elseif ($action === 'test_email' && $isAdmin) {
        require_once __DIR__ . '/src/Mailer.php';
        $testTo = trim($_POST['test_email_to'] ?? '');
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email de destino inválido.';
            $messageType = 'error';
        } else {
            $mailer = new \App\Mailer();
            $res = $mailer->send(
                $testTo,
                '[Unbound Dashboard] Email de teste',
                "Este é um email de teste enviado pelo Unbound Dashboard.\n\n"
              . "Se você recebeu, a configuração SMTP está funcionando.\n\n"
              . "Data: " . date('Y-m-d H:i:s')
              . "\nHost: " . gethostname()
            );
            $message = $res['message'];
            $messageType = !empty($res['success']) ? 'success' : 'error';
            // Salva log + hint pra UI mostrar destacado
            $_SESSION['smtp_test_log'] = implode("\n", $mailer->getLog());
            $_SESSION['smtp_test_hint'] = $res['hint'] ?? '';
        }
    } elseif ($action === 'save_webhook_config' && $isAdmin) {
        require_once __DIR__ . '/src/ApiClient.php';
        $jwt = $_SESSION['api_jwt'] ?? '';
        $payload = [
            'enabled'      => isset($_POST['webhook_enabled']),
            'url'          => trim($_POST['webhook_url'] ?? ''),
            'type'         => in_array($_POST['webhook_type'] ?? '', ['slack','discord','teams','telegram','generic'], true) ? $_POST['webhook_type'] : 'generic',
            'severity_min' => in_array($_POST['webhook_severity_min'] ?? '', ['warning','critical'], true) ? $_POST['webhook_severity_min'] : 'critical',
            'notify_on_release' => isset($_POST['webhook_notify_on_release']),
            'telegram_chat_id' => trim($_POST['webhook_telegram_chat_id'] ?? ''),
        ];
        $res = \App\ApiClient::put('/api/v1/webhooks/config', $jwt, $payload);
        $message = $res['ok'] ? 'Webhook salvo.' : 'Falha ao salvar webhook: ' . ($res['reason'] ?? 'erro');
        $messageType = $res['ok'] ? 'success' : 'error';
    } elseif ($action === 'test_webhook' && $isAdmin) {
        require_once __DIR__ . '/src/ApiClient.php';
        $jwt = $_SESSION['api_jwt'] ?? '';
        $payload = ['message' => trim($_POST['test_message'] ?? '') ?: null];
        $res = \App\ApiClient::post('/api/v1/webhooks/test', $jwt, $payload);
        if ($res['ok']) {
            $sent = !empty($res['data']['sent']);
            $reason = $res['data']['reason'] ?? '';
            $message = $sent
                ? 'Webhook enviado com sucesso (HTTP ' . ($res['data']['http_status'] ?? '?') . ').'
                : 'Webhook não enviou: ' . $reason . (isset($res['data']['http_status']) ? ' (HTTP ' . $res['data']['http_status'] . ')' : '');
            $messageType = $sent ? 'success' : 'error';
            $_SESSION['webhook_test_body'] = $res['data']['body'] ?? ($res['data']['error'] ?? '');
        } else {
            $message = 'Erro ao chamar API: ' . ($res['reason'] ?? '?');
            $messageType = 'error';
        }
    } elseif ($action === 'revoke_session') {
        $hash = trim($_POST['session_hash'] ?? '');
        if ($hash !== '') {
            $res = \App\Auth::revokeMySession($hash);
            $message = $res['message'];
            $messageType = !empty($res['success']) ? 'success' : 'error';
        }
    } elseif ($action === 'setup_totp') {
        $res = \App\Auth::setup2fa();
        if ($res['success']) {
            $_SESSION['totp_setup'] = ['secret' => $res['secret'], 'provisioning_uri' => $res['provisioning_uri']];
            $message = 'Escaneie o QR code e digite o código pra confirmar.';
            $messageType = 'success';
        } else {
            $message = $res['message'] ?? 'Falha ao iniciar 2FA.';
            $messageType = 'error';
        }
    } elseif ($action === 'confirm_totp') {
        $res = \App\Auth::confirm2fa($_POST['secret'] ?? '', $_POST['code'] ?? '');
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'disable_totp') {
        $res = \App\Auth::disable2fa($_POST['code'] ?? '');
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'admin_reset_totp' && $isAdmin) {
        $res = \App\Auth::adminReset2fa((int) ($_POST['user_id'] ?? 0));
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'update_profile_pass') {
        $res = \App\Auth::updatePassword($_SESSION['username'], $_POST['old_pass'], $_POST['new_pass']);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'add_user' && $isAdmin) {
        $res = \App\Auth::addUser(
            $_POST['new_username'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['new_role'] ?? 'viewer',
            $_POST['new_email'] ?? null
        );
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
    } elseif ($action === 'update_role' && $isAdmin) {
        $res = \App\Auth::updateRole((int)$_POST['user_id'], $_POST['new_role_value'] ?? '');
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
    } elseif ($action === 'update_org' && $isAdmin) {
        $userId = (int)($_POST['user_id'] ?? 0);
        $orgRaw = $_POST['new_org_value'] ?? '';
        $orgId  = $orgRaw === '' ? null : (int)$orgRaw;
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($userId < 1 || $jwt === '') {
            $message = 'Dados inválidos.';
            $messageType = 'error';
        } else {
            $resp = \App\ApiClient::post('/api/v1/organizations/assign-user', $jwt, [
                'user_id' => $userId,
                'org_id'  => $orgId,
            ]);
            if ($resp['ok']) {
                $message = $orgId ? 'Usuário atribuído à organização.' : 'Usuário desvinculado de organização.';
                $messageType = 'success';
            } else {
                $message = 'Erro ao atribuir org: ' . ($resp['data']['detail'] ?? 'desconhecido');
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_email' && $isAdmin) {
        $newEmail = trim($_POST['new_email_value'] ?? '');
        $username = $_POST['target_username'] ?? '';
        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email inválido.';
            $messageType = 'error';
        } else {
            $res = \App\Auth::updateEmail($username, $newEmail);
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
        }
    } elseif ($action === 'reset_password' && $isAdmin) {
        $res = \App\Auth::adminResetPassword((int)$_POST['user_id']);
        $message = $res['message'];
        $messageType = $res['success'] ? 'success' : 'error';
        if (!empty($res['success'])) {
            $tempPassword = $res['temporary_password'] ?? null;
            $tempPasswordUser = $_POST['target_username'] ?? '';
        }
    }
}

$currentConfig = $configManager->parseConfig();
$settings = $configManager->loadSettings();
$localRecords = $configManager->loadLocalRecords();
$ifacesDetails = $networkManager->getInterfacesDetailed();
$tlsCertStatus = $tlsCertManager->getStatus();
// Status do serviço DoT/DoH — lê portas/cert do parsed config atual.
// Passa os IPs reais das interfaces non-loopback configuradas pra o handshake
// testar onde Unbound de fato escuta (não em 127.0.0.1).
$_tlsTestIps = [];
foreach (($currentConfig['interfaces'] ?? []) as $_iface) {
    $_ip = preg_replace('/@\d+$/', '', (string) $_iface);
    if ($_ip === '' || $_ip === '127.0.0.1' || $_ip === '::1' || str_starts_with($_ip, '127.')) continue;
    $_tlsTestIps[] = $_ip;
}
// Fallback se config não trouxer (instala recente sem nada)
if (empty($_tlsTestIps)) $_tlsTestIps = ['127.0.0.1'];

$tlsLeLineages = $tlsCertManager->listLetsEncryptLineages();
$tlsServiceStatus = $tlsCertManager->getServiceStatus(
    (int) ($currentConfig['tls-port'] ?? 0),
    (int) ($currentConfig['https-port'] ?? 0),
    (string) ($currentConfig['tls-service-pem'] ?? ''),
    (string) ($currentConfig['tls-service-key'] ?? ''),
    $_tlsTestIps
);
$systemHostname = $networkManager->getHostname();
$systemDnsList = array_pad($networkManager->getSystemDNS(), 2, '');
$blockedDomainsTxt = implode("\n", $configManager->loadBlocklist());
$currentNtp = $networkManager->getNtpServers();
$currentTz = $networkManager->getSystemTimezone();
$timezoneOptions = $networkManager->getAvailableTimezones();
$networkBackend = $networkManager->detectBackend();
$lastNetplanBackup = $networkBackend === 'netplan' ? $networkManager->getLastNetplanBackup() : null;

// Gestão de Usuários (admin-only) — fonte de dados pra aba 'usuarios'.
$allUsers = $isAdmin ? \App\Auth::getAllUsers() : [];
$allOrgs = [];
if ($isAdmin && !empty($_SESSION['api_jwt'])) {
    $orgsResp = \App\ApiClient::get('/api/v1/organizations/', $_SESSION['api_jwt']);
    if ($orgsResp['ok'] && isset($orgsResp['data']['items']) && is_array($orgsResp['data']['items'])) {
        $allOrgs = $orgsResp['data']['items'];
    }
}
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// Helpers de formatação usados na tabela de usuários
$fmtUserDate = function ($iso) {
    if (!$iso) return null;
    try { return (new DateTime($iso))->format('d/m/Y H:i'); } catch (Exception $e) { return $iso; }
};
$relativeUserTime = function ($iso) {
    if (!$iso) return 'nunca';
    try {
        $ts = (new DateTime($iso))->getTimestamp();
        $diff = time() - $ts;
        if ($diff < 60) return 'agora';
        if ($diff < 3600) return floor($diff / 60) . ' min atrás';
        if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
        if ($diff < 86400 * 30) return floor($diff / 86400) . 'd atrás';
        return floor($diff / (86400 * 30)) . 'mes atrás';
    } catch (Exception $e) { return $iso; }
};
$timezoneGroups = [];
foreach ($timezoneOptions as $timezoneOption) {
    $timezoneGroup = explode('/', $timezoneOption, 2)[0] ?: 'Outros';
    $timezoneGroups[$timezoneGroup][] = $timezoneOption;
}
if (!empty($currentTz) && !in_array($currentTz, $timezoneOptions, true)) {
    $timezoneGroups['Atual'][] = $currentTz;
}
$sbSettings = $sourceBalanceManager->getSettings();
$currentBlocklistSource = $blocklistManager->getBlocklistSource();
$blacklistSourceEnabled = $blocklistManager->isBlacklistSourceEnabled();
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
    <title><?= t('config.title') ?> - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: tabFadeIn 0.4s ease-out; }
        /* Botão "Verificar atualizações" — destaque visual + pulse sutil em idle */
        .updates-check-btn::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: inherit;
            background: linear-gradient(45deg, rgba(34, 211, 238, 0.4), rgba(59, 130, 246, 0.4));
            filter: blur(8px);
            opacity: 0;
            z-index: -1;
            transition: opacity 0.3s ease;
        }
        .updates-check-btn:hover::before { opacity: 1; }
        .updates-check-btn.is-checking #updates-refresh-icon { animation: updates-spin 0.8s linear infinite; }
        @keyframes updates-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
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
        $pageTitle = t('config.title');
        include 'includes/topbar.php'; 
        ?>
        <div class="page-container">
            <div class="flex flex-col lg:flex-row gap-10">
                <aside class="w-full lg:w-72 flex-shrink-0">
                    <nav class="glass-panel !p-2 rounded-3xl border border-slate-200 dark:border-white/5 space-y-1">
                        <?php $tabs = $isAdmin
                            ? ['geral' => t('config.tab_geral'), 'tls' => t('config.tab_tls'), 'local_dns' => t('config.tab_local_dns'), 'source_balance' => t('config.tab_source_balance'), 'forwarders' => t('config.tab_forwarders'), 'rpz' => t('config.tab_rpz'), 'acl' => t('config.tab_acl'), 'config_rede' => t('config.tab_config_rede'), 'ntp' => t('config.tab_ntp'), 'email' => t('config.tab_email'), 'webhooks' => t('config.tab_webhooks'), 'updates' => t('config.tab_updates'), 'auditoria' => t('config.tab_auditoria'), 'api_tokens' => t('config.tab_api_tokens'), 'usuarios' => t('config.tab_usuarios'), 'perfil' => t('config.tab_perfil')]
                            : ['perfil' => t('config.tab_perfil')];
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

                            <!-- Painel de Status do Serviço -->
                            <?php
                            // Helpers visuais
                            $serviceCard = function(string $title, int $port, bool $listening, bool $handshakeOk) {
                                if ($port <= 0) {
                                    $cls = 'border-slate-500/30 bg-slate-500/5';
                                    $badge = 'bg-slate-500/15 text-slate-500';
                                    $label = 'Desabilitado';
                                    $detail = 'Porta em branco — feature off.';
                                } elseif ($listening && $handshakeOk) {
                                    $cls = 'border-emerald-500/30 bg-emerald-500/5';
                                    $badge = 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
                                    $label = 'Funcionando';
                                    $detail = 'Listening na porta ' . $port . ' • Handshake TLS OK';
                                } elseif ($listening) {
                                    $cls = 'border-amber-500/30 bg-amber-500/5';
                                    $badge = 'bg-amber-500/15 text-amber-600 dark:text-amber-400';
                                    $label = 'Sem TLS';
                                    $detail = 'Listening em ' . $port . ' mas handshake falhou — cert/key incorretos ou unbound não recarregou.';
                                } else {
                                    $cls = 'border-red-500/30 bg-red-500/5';
                                    $badge = 'bg-red-500/15 text-red-600 dark:text-red-400';
                                    $label = 'Inativo';
                                    $detail = 'Nada escutando em ' . $port . '. Reinicie o Unbound após salvar a config.';
                                }
                                return [
                                    'title' => $title,
                                    'badge_cls' => $badge,
                                    'panel_cls' => $cls,
                                    'label' => $label,
                                    'detail' => $detail,
                                ];
                            };
                            $dotCard = $serviceCard('DoT (porta ' . ($tlsServiceStatus['dot_port'] ?: '?') . ')', (int) $tlsServiceStatus['dot_port'], $tlsServiceStatus['dot_listening'], $tlsServiceStatus['dot_handshake_ok']);
                            $dohCard = $serviceCard('DoH (porta ' . ($tlsServiceStatus['doh_port'] ?: '?') . ')', (int) $tlsServiceStatus['doh_port'], $tlsServiceStatus['doh_listening'], $tlsServiceStatus['doh_handshake_ok']);

                            $certCardCls = 'border-slate-500/30 bg-slate-500/5';
                            $certBadgeCls = 'bg-slate-500/15 text-slate-500';
                            $certLabel = 'Não configurado';
                            $certDetail = 'Preencha os caminhos abaixo ou clique em "Gerar Self-Signed".';
                            if ($tlsServiceStatus['cert_present']) {
                                $days = $tlsServiceStatus['cert_days_remaining'];
                                if ($days !== null && $days < 0) {
                                    $certCardCls = 'border-red-500/30 bg-red-500/5';
                                    $certBadgeCls = 'bg-red-500/15 text-red-600 dark:text-red-400';
                                    $certLabel = 'Expirado';
                                    $certDetail = 'Expirou em ' . date('d/m/Y', $tlsServiceStatus['cert_expires_at']) . '. Gere um novo ou suba outro.';
                                } elseif ($days !== null && $days < 30) {
                                    $certCardCls = 'border-amber-500/30 bg-amber-500/5';
                                    $certBadgeCls = 'bg-amber-500/15 text-amber-600 dark:text-amber-400';
                                    $certLabel = 'Expira em breve';
                                    $certDetail = 'Faltam ' . $days . ' dias. CN: ' . ($tlsServiceStatus['cert_subject'] ?: '?');
                                } else {
                                    $certCardCls = 'border-emerald-500/30 bg-emerald-500/5';
                                    $certBadgeCls = 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
                                    $certLabel = 'Válido';
                                    $certDetail = 'CN: ' . ($tlsServiceStatus['cert_subject'] ?: '?') . ($days !== null ? ' • ' . $days . ' dias restantes' : '');
                                }
                            }
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <?php foreach ([$dotCard, $dohCard] as $card): ?>
                                    <div class="glass-panel border-l-4 <?= $card['panel_cls'] ?>">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($card['title']) ?></p>
                                            <span class="px-2 py-0.5 rounded-md border text-[9px] font-black uppercase tracking-widest <?= $card['badge_cls'] ?>"><?= htmlspecialchars($card['label']) ?></span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400"><?= htmlspecialchars($card['detail']) ?></p>
                                    </div>
                                <?php endforeach; ?>

                                <div class="glass-panel border-l-4 <?= $certCardCls ?>">
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Certificado SSL</p>
                                        <span class="px-2 py-0.5 rounded-md border text-[9px] font-black uppercase tracking-widest <?= $certBadgeCls ?>"><?= htmlspecialchars($certLabel) ?></span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400"><?= htmlspecialchars($certDetail) ?></p>
                                    <?php if (!empty($tlsServiceStatus['cert_sans'])): ?>
                                        <p class="text-[10px] text-slate-500 mt-2 font-mono break-all"><?= htmlspecialchars(implode(', ', array_slice($tlsServiceStatus['cert_sans'], 0, 4))) ?><?= count($tlsServiceStatus['cert_sans']) > 4 ? ' …' : '' ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($tlsServiceStatus['warnings'])): ?>
                                <div class="glass-panel border-l-4 border-amber-500/40 bg-amber-500/5">
                                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-2">Avisos</p>
                                    <ul class="text-[11px] text-amber-700 dark:text-amber-300 space-y-1 list-disc list-inside">
                                        <?php foreach ($tlsServiceStatus['warnings'] as $w): ?>
                                            <li><?= htmlspecialchars($w) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 border-b border-slate-900/10 dark:border-white/5 pb-4">DNS over TLS (DoT) & HTTPS (DoH)</h3>

                                <p class="text-xs text-slate-400 mb-6 leading-relaxed">Habilite a escuta criptografada do Unbound. Quando ligado, o sistema adiciona listeners <code>interface:&lt;ip&gt;@porta</code> automaticamente pras interfaces não-loopback.</p>

                                <?php
                                $tlsPortVal   = $currentConfig['tls-port']   ?? '';
                                $httpsPortVal = $currentConfig['https-port'] ?? '';
                                // Master switch: feature está on se há alguma porta TLS configurada.
                                $tlsEnabled = ($tlsPortVal !== '' && $tlsPortVal !== '0')
                                           || ($httpsPortVal !== '' && $httpsPortVal !== '0');
                                ?>

                                <!-- Master toggle: 1 switch controla DoT + DoH -->
                                <div class="bg-cyan-600/10 border border-cyan-500/30 p-5 rounded-3xl mb-6">
                                    <!-- Hidden submitted quando o checkbox NÃO marcado (HTML não envia checkboxes off) -->
                                    <input type="hidden" name="tls-enabled" value="no">
                                    <label class="flex items-center gap-4 cursor-pointer">
                                        <input type="checkbox" id="enable-tls-doh" name="tls-enabled" value="yes" <?= $tlsEnabled ? 'checked' : '' ?>
                                               onchange="document.getElementById('tls-port-input').disabled = !this.checked; document.getElementById('https-port-input').disabled = !this.checked;"
                                               class="w-6 h-6 text-cyan-500 bg-slate-900 border-white/10 rounded-lg">
                                        <div>
                                            <span class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Habilitar DoT / DoH</span>
                                            <p class="text-[10px] text-slate-500 mt-1">Liga DNS-over-TLS e DNS-over-HTTPS no Unbound usando as portas abaixo. Quando desabilitado, os <code>tls-port</code> / <code>https-port</code> e os listeners <code>@porta</code> são removidos do config.</p>
                                        </div>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Porta DoT (DNS-over-TLS)</label>
                                        <input type="number" min="1" max="65535" id="tls-port-input" name="tls-port" value="<?= htmlspecialchars($tlsPortVal !== '' ? $tlsPortVal : '853') ?>" <?= $tlsEnabled ? '' : 'disabled' ?> class="glass-input w-full font-mono">
                                        <p class="text-[9px] text-slate-500 italic mt-1">Padrão: 853. Cobrar TCP só.</p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Porta DoH (DNS-over-HTTPS)</label>
                                        <input type="number" min="1" max="65535" id="https-port-input" name="https-port" value="<?= htmlspecialchars($httpsPortVal !== '' ? $httpsPortVal : '443') ?>" <?= $tlsEnabled ? '' : 'disabled' ?> class="glass-input w-full font-mono">
                                        <p class="text-[9px] text-slate-500 italic mt-1">Padrão: 443. Cuidado se já tiver web server na mesma porta.</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <?= field('tls-service-pem', 'Caminho do Certificado Público (.pem / .crt)', 'Auto-preenchido quando você Gera/Faz Upload abaixo. Sobrescreva pra usar cert externo (Let\'s Encrypt etc).') ?>
                                    <?= field('tls-service-key', 'Caminho da Chave Privada (.key)', 'Auto-preenchido junto com o certificado.') ?>
                                </div>
                            </div>

                            <!-- Certificado gerenciado pelo dashboard -->
                            <div class="glass-panel">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 border-b border-slate-900/10 dark:border-white/5 pb-4">Certificado SSL Gerenciado</h3>
                                <p class="text-xs text-slate-400 mb-4 leading-relaxed">Gere ou envie um par de cert+key armazenado em <code class="text-slate-300">/etc/unbound/certs/dashboard.{crt,key}</code>. Os campos de caminho acima podem apontar pra esses arquivos.</p>

                                <?php if ($tlsCertStatus['managed_by_dashboard']): ?>
                                    <?php $isLE = !empty($tlsCertStatus['is_letsencrypt']); ?>
                                    <div class="p-4 rounded-2xl border <?= $isLE ? 'border-blue-500/30 bg-blue-500/10' : 'border-emerald-500/30 bg-emerald-500/10' ?> mb-4">
                                        <p class="text-[10px] font-black <?= $isLE ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400' ?> uppercase tracking-widest mb-2">
                                            Certificado ativo
                                            <?php if ($isLE): ?>
                                                <span class="ml-2 px-2 py-0.5 rounded-md bg-blue-500/20 border border-blue-500/40 text-[9px]">Let's Encrypt</span>
                                            <?php endif; ?>
                                        </p>
                                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-[11px] font-mono">
                                            <div><span class="text-slate-500">CN:</span> <span class="text-slate-900 dark:text-white"><?= htmlspecialchars($tlsCertStatus['subject'] ?? '?') ?></span></div>
                                            <div><span class="text-slate-500">Expira:</span> <span class="text-slate-900 dark:text-white"><?= $tlsCertStatus['expires_at'] ? date('d/m/Y H:i', $tlsCertStatus['expires_at']) : '?' ?></span></div>
                                            <div class="sm:col-span-2"><span class="text-slate-500">Emissor:</span> <span class="text-slate-900 dark:text-white break-all"><?= htmlspecialchars($tlsCertStatus['issuer'] ?? '?') ?></span></div>
                                            <?php if (!empty($tlsCertStatus['sans'])): ?>
                                                <div class="sm:col-span-2"><span class="text-slate-500">SAN:</span> <span class="text-slate-900 dark:text-white break-all"><?= htmlspecialchars(implode(', ', $tlsCertStatus['sans'])) ?></span></div>
                                            <?php endif; ?>
                                        </dl>
                                        <?php if ($isLE): ?>
                                            <p class="text-[10px] text-blue-700 dark:text-blue-300 mt-3 leading-relaxed">
                                                ⚠ Este é um certificado <b>Let's Encrypt válido publicamente</b>. Clicar em "Gerar Self-Signed" ou "Upload PEM" vai <b>sobrescrever</b> ele — o DoH/DoT vai parar de funcionar nos clientes que validam o cert. Só clica se realmente quiser trocar.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 rounded-2xl border border-slate-500/20 bg-slate-500/5 mb-4 text-[11px] text-slate-500">
                                        Nenhum certificado gerenciado instalado em <code>/etc/unbound/certs/dashboard.{crt,key}</code>.
                                    </div>
                                <?php endif; ?>

                                <?php $tlsIsLE = !empty($tlsCertStatus['is_letsencrypt']); ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php if (!empty($tlsLeLineages)): ?>
                                        <button type="button" onclick="document.getElementById('tls-le-modal').classList.remove('hidden')" class="glass-btn text-[10px] uppercase font-black !bg-blue-600 !text-white flex items-center gap-2" title="Importar cert do certbot já emitido neste servidor">
                                            🔁 Importar Let's Encrypt
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" data-tls-action="generate" data-is-le="<?= $tlsIsLE ? '1' : '0' ?>" class="glass-btn text-[10px] uppercase font-black !bg-cyan-600 !text-white flex items-center gap-2">
                                        🔐 Gerar Self-Signed
                                    </button>
                                    <button type="button" data-tls-action="upload" data-is-le="<?= $tlsIsLE ? '1' : '0' ?>" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2">
                                        ⬆ Upload PEM
                                    </button>
                                    <?php if ($tlsCertStatus['managed_by_dashboard']): ?>
                                        <button type="button" onclick="document.getElementById('tls-remove-modal').classList.remove('hidden')" class="glass-btn text-[10px] uppercase font-black !bg-red-500/15 !text-red-600 dark:!text-red-400">
                                            ✗ Remover
                                        </button>
                                    <?php endif; ?>
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

                                <!-- Toggle: liga/desliga o auto-update da fonte (cron + botão "Atualizar Agora") -->
                                <label class="bg-blue-600/5 dark:bg-white/5 border border-slate-200 dark:border-white/5 p-6 rounded-3xl flex items-center justify-between cursor-pointer mb-8">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="blacklist_source_enabled" value="yes" <?= $blacklistSourceEnabled ? 'checked' : '' ?> class="w-6 h-6 text-emerald-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                        <div>
                                            <span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Fonte da Blacklist Principal Ativa</span>
                                            <p class="text-[10px] text-slate-500 font-medium mt-1">Quando desligada, o cron de auto-update e o botão "Atualizar Agora" ficam inertes. Dados atuais ficam preservados no banco.</p>
                                        </div>
                                    </div>
                                </label>

                                <hr class="border-white/10 my-8">

                                <label class="bg-blue-600/5 dark:bg-white/5 border border-slate-200 dark:border-white/5 p-6 rounded-3xl flex items-center justify-between cursor-pointer mb-8">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="official_blocklist_enabled" value="yes" <?= ($settings['official_blocklist_enabled'] ?? false) ? 'checked' : '' ?> class="w-6 h-6 text-blue-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                        <div><span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Sincronização Anablock (Judicial)</span></div>
                                    </div>
                                    <input type="time" name="official_blocklist_update_time" value="<?= htmlspecialchars($settings['official_blocklist_update_time'] ?? '03:00') ?>" class="glass-input !py-2 !px-4 text-xs font-mono">
                                </label>

                                <!-- Toggle: Anti-DoH (DNS-over-HTTPS dos navegadores) -->
                                <label class="bg-red-600/5 dark:bg-white/5 border border-slate-200 dark:border-white/5 p-6 rounded-3xl flex items-center justify-between cursor-pointer mb-8">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="anti_doh_enabled" value="yes" <?= ($settings['anti_doh_enabled'] ?? false) ? 'checked' : '' ?> class="w-6 h-6 text-red-600 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-white/10 rounded-lg">
                                        <div>
                                            <span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Bloquear DNS-over-HTTPS de terceiros</span>
                                            <p class="text-[10px] text-slate-500 font-medium mt-1">
                                                Adiciona <?= count($configManager->loadAntiDohHosts()) ?> hostnames de DoH conhecidos (Cloudflare, Google, Quad9, AdGuard, NextDNS…) ao Unbound como <code>always_nxdomain</code>. Os navegadores não conseguem mais resolver o endpoint DoH e caem pro DNS local — voltam a respeitar seus bloqueios sem mexer no dispositivo. Firefox tb desliga DoH automaticamente via canary <code>use-application-dns.net</code>.
                                            </p>
                                        </div>
                                    </div>
                                </label>


                                <h3 class="text-[10px] font-black text-slate-500 uppercase mb-4">Domínios Locais</h3>
                                <textarea name="blocked_domains" rows="12" class="glass-input w-full font-mono text-xs"><?= htmlspecialchars($blockedDomainsTxt) ?></textarea>
                            </div>
                        </div>

                        <div id="tab-acl" class="tab-content space-y-6">
                            <?php
                                // Contagens por ação pra header dos chips
                                $aclCounts = ['allow' => 0, 'deny' => 0, 'refuse' => 0];
                                foreach ($currentConfig['access-control'] ?? [] as $aclItem) {
                                    $a = $aclItem['action'] ?? 'allow';
                                    if (isset($aclCounts[$a])) $aclCounts[$a]++;
                                }
                                $aclTotal = array_sum($aclCounts);
                            ?>

                            <div class="glass-panel">
                                <div class="flex justify-between items-center mb-6 border-b border-slate-900/10 dark:border-white/5 pb-4 flex-wrap gap-3">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">ACLs (Controle de Acesso)</h3>
                                    <button type="button" onclick="addAclRow()" class="glass-btn text-[10px] font-black uppercase">+ Nova Regra</button>
                                </div>

                                <!-- Toolbar: busca + filtro de ação + chips de contagem -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar IP / CIDR</label>
                                        <input type="text" id="aclSearch" oninput="filterAclRows()" placeholder="ex: 192.168, 10.0.0.0/8, ::1" class="glass-input w-full font-mono text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Filtrar ação</label>
                                        <select id="aclActionFilter" onchange="filterAclRows()" class="glass-input w-full uppercase text-[10px] font-black">
                                            <option value="">TODAS (<?= $aclTotal ?>)</option>
                                            <option value="allow">ALLOW (<?= $aclCounts['allow'] ?>)</option>
                                            <option value="deny">DENY (<?= $aclCounts['deny'] ?>)</option>
                                            <option value="refuse">REFUSE (<?= $aclCounts['refuse'] ?>)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Chips por ação — clique aplica filtro -->
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <button type="button" onclick="setAclFilter('')" class="acl-chip glass-btn !py-1 !px-3 text-[9px] uppercase tracking-widest font-black">
                                        Todas (<span id="aclCountAll"><?= $aclTotal ?></span>)
                                    </button>
                                    <button type="button" onclick="setAclFilter('allow')" class="acl-chip glass-btn !py-1 !px-3 text-[9px] uppercase tracking-widest font-black border-emerald-500/30 text-emerald-500">
                                        Allow (<span id="aclCountAllow"><?= $aclCounts['allow'] ?></span>)
                                    </button>
                                    <button type="button" onclick="setAclFilter('deny')" class="acl-chip glass-btn !py-1 !px-3 text-[9px] uppercase tracking-widest font-black border-red-500/30 text-red-500">
                                        Deny (<span id="aclCountDeny"><?= $aclCounts['deny'] ?></span>)
                                    </button>
                                    <button type="button" onclick="setAclFilter('refuse')" class="acl-chip glass-btn !py-1 !px-3 text-[9px] uppercase tracking-widest font-black border-amber-500/30 text-amber-500">
                                        Refuse (<span id="aclCountRefuse"><?= $aclCounts['refuse'] ?></span>)
                                    </button>
                                    <span class="ml-auto text-[10px] text-slate-500 font-black uppercase tracking-widest self-center">
                                        Visíveis: <span id="aclCountVisible"><?= $aclTotal ?></span> / <?= $aclTotal ?>
                                    </span>
                                </div>

                                <div id="acl-list" class="space-y-3">
                                    <?php foreach ($currentConfig['access-control'] ?? [] as $acl):
                                        $aclIp = $acl['ip'] ?? '';
                                        $aclAction = $acl['action'] ?? 'allow';
                                        ?>
                                        <div class="acl-row flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5"
                                             data-ip="<?= htmlspecialchars(strtolower($aclIp)) ?>"
                                             data-action="<?= htmlspecialchars($aclAction) ?>">
                                            <input type="text" name="acl_ips[]" value="<?= htmlspecialchars($aclIp) ?>"
                                                   oninput="this.parentElement.dataset.ip = this.value.toLowerCase(); filterAclRows();"
                                                   class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none">
                                            <select name="acl_actions[]"
                                                    onchange="this.parentElement.dataset.action = this.value; updateAclCounts(); filterAclRows();"
                                                    class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none">
                                                <option value="allow" <?= $aclAction === 'allow' ? 'selected' : '' ?>>ALLOW</option>
                                                <option value="deny" <?= $aclAction === 'deny' ? 'selected' : '' ?>>DENY</option>
                                                <option value="refuse" <?= $aclAction === 'refuse' ? 'selected' : '' ?>>REFUSE</option>
                                            </select>
                                            <button type="button" onclick="this.parentElement.remove(); updateAclCounts(); filterAclRows();" class="text-red-500/50 p-2" title="Remover">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p id="aclEmpty" class="hidden text-center text-slate-500 text-sm py-6">Nenhuma regra atende ao filtro.</p>
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
                                                data-confirm="Restaurar a versão anterior do YAML netplan e re-aplicar? Isto reverte a última mudança de rede salva."
                                                data-confirm-title="Reverter mudança de rede"
                                                data-confirm-variant="warning"
                                                data-confirm-ok-label="Reverter"
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
                                                        // Card do `lo` reflete a config do alias `lo.1` (que é o que o
                                                        // dashboard de fato escreve no /etc/network/interfaces). Sem esse
                                                        // remap, os inputs aparecem vazios depois de salvar.
                                                        $confLookup = $iface['ifname'] === 'lo' ? 'lo.1' : $iface['ifname'];
                                                        $ifConf = $networkManager->getInterfaceConfig($confLookup); ?>
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
                                        <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-slate-900/10 dark:border-white/5">
                                            <?php if ($iface['ifname'] === 'lo' && trim($ifConf['address'] ?? '') !== ''): ?>
                                                <button type="submit" name="action" value="delete_interface"
                                                        data-confirm="Remover o IP estático de lo.1 do /etc/network/interfaces?"
                                                        data-confirm-title="Remover LO.1"
                                                        data-confirm-variant="danger"
                                                        data-confirm-ok-label="Remover"
                                                        data-pre-click="setIfaceName('<?= htmlspecialchars($iface['ifname']) ?>')"
                                                        class="glass-btn text-[10px] font-black uppercase !bg-red-500/15 !text-red-600 dark:!text-red-400">
                                                    Remover LO.1
                                                </button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="save_interface" onclick="setIfaceName('<?= htmlspecialchars($iface['ifname']) ?>')" class="glass-btn text-[10px] font-black uppercase"><?= $iface['ifname'] === 'lo' ? 'Salvar como LO.1' : 'Salvar Interface' ?></button>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- tab-ntp foi movido pra fora do mainConfigForm (form-em-form é inválido). -->

                        <div id="btnSaveFloating" class="mt-10 flex justify-end animate-fade-in shadow-2xl"><button type="submit" id="btnSaveMain" class="glass-btn bg-blue-600 text-white px-12 py-4 text-xs font-black uppercase tracking-[0.2em] hover:bg-blue-500 transform hover:scale-105 transition-all">Sincronizar Todas Alterações</button></div>
                    </form>

                    <!-- tab-ntp — forms independentes (NTP e Timezone), FORA do mainConfigForm.
                         Sem isso, browser ignoraria os <form> internos e submeteria o mainConfigForm
                         com action=save_unbound_settings, fazendo save_ntp_only/save_timezone_only nunca rodar. -->
                    <div id="tab-ntp" class="tab-content <?= $activeTab === 'ntp' ? 'active' : '' ?> space-y-6">

                        <!-- Card NTP — form independente -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-4">Servidores NTP</h3>
                            <form method="POST" data-loader="off">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="ntp">
                                <input type="hidden" name="action" value="save_ntp_only">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase">Pool de servidores</label>
                                    <button type="button" onclick="addNtpRow()" class="glass-btn text-[10px] font-black uppercase">+ Adicionar Servidor</button>
                                </div>
                                <div id="ntp-list" class="space-y-3 mb-4">
                                    <?php
                                    $ntpServers = !empty($currentNtp) ? explode(' ', $currentNtp) : [''];
                                    foreach ($ntpServers as $server): ?>
                                        <div class="flex gap-3 animate-fade-in">
                                            <input type="text" name="ntp_servers[]" value="<?= htmlspecialchars($server) ?>" placeholder="pool.ntp.br" class="glass-input flex-1 font-mono">
                                            <button type="button" onclick="this.parentElement.remove()" class="p-4 text-red-500 hover:bg-red-500/10 rounded-2xl transition-all" title="Remover">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Salvar NTP</button>
                                </div>
                            </form>
                        </div>

                        <!-- Card Timezone — form independente -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-4">Fuso Horário</h3>
                            <form method="POST" data-loader="off">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="ntp">
                                <input type="hidden" name="action" value="save_timezone_only">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Atual</p>
                                        <p class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                            <?= htmlspecialchars($currentTz ?: '— não detectado —') ?>
                                        </p>
                                        <p class="text-[10px] text-slate-500 mt-1">Hora local agora: <span class="font-mono"><?= htmlspecialchars(date('d/m/Y H:i:s')) ?></span></p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">
                                            Novo fuso (digite para filtrar — <?= count($timezoneOptions) ?> opções)
                                        </label>
                                        <input
                                            type="text"
                                            name="system_timezone"
                                            list="tz-options"
                                            value="<?= htmlspecialchars($currentTz) ?>"
                                            placeholder="ex: America/Sao_Paulo"
                                            pattern="[A-Za-z_./+\-]+"
                                            required
                                            class="glass-input w-full font-mono"
                                            autocomplete="off">
                                        <datalist id="tz-options">
                                            <?php foreach ($timezoneOptions as $timezoneOption): ?>
                                                <option value="<?= htmlspecialchars($timezoneOption) ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                        <p class="mt-2 text-[10px] text-slate-500">Digite "America/" para ver as Américas, "Europe/" Europa, etc.</p>
                                    </div>
                                </div>

                                <div class="flex justify-end mt-6 pt-6 border-t border-slate-900/10 dark:border-white/5">
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Salvar Fuso Horário</button>
                                </div>
                            </form>
                            <?php if (empty($timezoneOptions)): ?>
                                <div class="mt-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                                    <p class="text-[11px] text-amber-700 dark:text-amber-300 font-bold">
                                        ⚠ Lista de timezones vazia. Verifique se <code>tzdata</code> está instalado:
                                        <code class="text-[10px]">sudo apt install -y tzdata</code>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- Modais do certificado TLS (fora do mainConfigForm)            -->
                    <!-- ============================================================ -->

                    <!-- Gerar self-signed -->
                    <div id="tls-generate-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <form method="POST" action="config.php?tab=tls" class="glass-panel max-w-lg w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
                            <input type="hidden" name="action" value="tls_generate_cert">
                            <input type="hidden" name="tab" value="tls">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Gerar certificado self-signed</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Common Name (CN) *</label>
                                    <input type="text" name="cert_cn" required maxlength="100" placeholder="ex: dns.empresa.local" class="glass-input w-full font-mono text-xs" value="<?= htmlspecialchars($systemHostname) ?>">
                                    <p class="text-[10px] text-slate-500 mt-1">FQDN principal do servidor (entra no Subject e como primeiro SAN).</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Subject Alternative Names (SANs)</label>
                                    <?php
                                    // Pré-popular com hostname + IPs reais das interfaces não-loopback.
                                    $sanDefaults = [];
                                    foreach ($ifacesDetails as $_if) {
                                        if (($_if['ifname'] ?? '') === 'lo') continue;
                                        foreach (($_if['addr_info'] ?? []) as $_a) {
                                            $_ip = $_a['local'] ?? '';
                                            // Skip link-local IPv6 (fe80::) — não é útil no SAN
                                            if ($_ip === '' || str_starts_with(strtolower($_ip), 'fe80:')) continue;
                                            $sanDefaults[] = $_ip;
                                        }
                                    }
                                    $sanDefaults = array_values(array_unique($sanDefaults));
                                    ?>
                                    <textarea name="cert_sans" rows="4" class="glass-input w-full font-mono text-xs" placeholder="Um por linha ou vírgula-separado. Hostnames OU IPs."><?= htmlspecialchars(implode("\n", $sanDefaults)) ?></textarea>
                                    <p class="text-[10px] text-slate-500 mt-1">Pré-preenchido com os IPs detectados nas interfaces não-loopback. Adicione hostnames/FQDNs que clientes vão usar.</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Validade (dias)</label>
                                    <input type="number" name="cert_days" min="1" max="3650" value="825" class="glass-input w-full font-mono text-xs">
                                    <p class="text-[10px] text-slate-500 mt-1">Default 825 (limite aceito por iOS/Safari sem warning).</p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-6">
                                <button type="button" onclick="document.getElementById('tls-generate-modal').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                <button type="submit" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Gerar</button>
                            </div>
                        </form>
                    </div>

                    <!-- Upload PEM -->
                    <div id="tls-upload-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <form method="POST" action="config.php?tab=tls" class="glass-panel max-w-2xl w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
                            <input type="hidden" name="action" value="tls_upload_cert">
                            <input type="hidden" name="tab" value="tls">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Upload de certificado PEM</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Certificado público (PEM) *</label>
                                    <textarea name="cert_pem" rows="8" required class="glass-input w-full font-mono text-[10px]" placeholder="-----BEGIN CERTIFICATE-----&#10;MIID...&#10;-----END CERTIFICATE-----"></textarea>
                                    <p class="text-[10px] text-slate-500 mt-1">Cole o conteúdo do <code>.crt</code> ou <code>fullchain.pem</code>.</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Chave privada (PEM) *</label>
                                    <textarea name="cert_key" rows="8" required class="glass-input w-full font-mono text-[10px]" placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIE...&#10;-----END PRIVATE KEY-----"></textarea>
                                    <p class="text-[10px] text-slate-500 mt-1">Validamos com openssl + match cert↔key antes de instalar.</p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-6">
                                <button type="button" onclick="document.getElementById('tls-upload-modal').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                <button type="submit" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Instalar</button>
                            </div>
                        </form>
                    </div>

                    <!-- Importar Let's Encrypt -->
                    <?php if (!empty($tlsLeLineages)): ?>
                    <div id="tls-le-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <form method="POST" action="config.php?tab=tls" class="glass-panel max-w-lg w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
                            <input type="hidden" name="action" value="tls_import_letsencrypt">
                            <input type="hidden" name="tab" value="tls">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-2">Importar Let's Encrypt</h3>
                            <p class="text-[11px] text-slate-500 mb-4">Detectei <?= count($tlsLeLineages) ?> lineage(s) em <code>/etc/letsencrypt/live/</code>. Selecione um — o cert e a key vão ser copiados pros paths managed do dashboard e o deploy hook do certbot é instalado pra auto-renovar.</p>
                            <div class="space-y-2 mb-4">
                                <?php foreach ($tlsLeLineages as $i => $_le): ?>
                                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-900/5 dark:bg-white/5 cursor-pointer hover:border-blue-500/40 hover:bg-blue-500/5 transition-colors">
                                        <input type="radio" name="le_lineage" value="<?= htmlspecialchars($_le) ?>" <?= $i === 0 ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                                        <span class="font-mono text-[12px] text-slate-900 dark:text-white"><?= htmlspecialchars($_le) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('tls-le-modal').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Importar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Remover -->
                    <div id="tls-remove-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <form method="POST" action="config.php?tab=tls" class="glass-panel max-w-md w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
                            <input type="hidden" name="action" value="tls_remove_cert">
                            <input type="hidden" name="tab" value="tls">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Remover certificado gerenciado</h3>
                            <p class="text-[12px] text-slate-600 dark:text-slate-400 mb-4">Apaga <code>/etc/unbound/certs/dashboard.crt</code> e <code>dashboard.key</code>. Não afeta certificados externos (ex: <code>/etc/letsencrypt/</code>). Lembre de limpar os caminhos em Configurações depois.</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('tls-remove-modal').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                <button type="submit" class="glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black">Remover</button>
                            </div>
                        </form>
                    </div>

                    <!-- tab-email — config SMTP (admin only) — fora do mainConfigForm, igual ao tab-ntp -->
                    <?php
                        if ($isAdmin) {
                            require_once __DIR__ . '/src/Mailer.php';
                            $smtpConfig = \App\Mailer::loadConfig();
                            $smtpTestLog = $_SESSION['smtp_test_log'] ?? '';
                            $smtpTestHint = $_SESSION['smtp_test_hint'] ?? '';
                            unset($_SESSION['smtp_test_log'], $_SESSION['smtp_test_hint']);
                        }
                    ?>
                    <?php if ($isAdmin): ?>
                    <div id="tab-email" class="tab-content <?= $activeTab === 'email' ? 'active' : '' ?> space-y-6">

                        <!-- Status atual -->
                        <div class="glass-panel border-l-4 <?= $smtpConfig['smtp_enabled'] ? 'border-emerald-500' : 'border-slate-500' ?>">
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Status</p>
                            <p class="text-sm font-bold">
                                <?php if ($smtpConfig['smtp_enabled']): ?>
                                    <span class="text-emerald-500">● SMTP habilitado</span> —
                                    enviando via <code class="text-xs"><?= htmlspecialchars($smtpConfig['smtp_host']) ?>:<?= $smtpConfig['smtp_port'] ?></code>
                                    (<?= htmlspecialchars($smtpConfig['smtp_encryption']) ?>)
                                <?php else: ?>
                                    <span class="text-slate-500">○ SMTP desabilitado</span> —
                                    sistema usa <code class="text-xs">mail()</code> do PHP (depende de MTA local)
                                <?php endif; ?>
                            </p>
                            <p class="text-[11px] text-slate-500 mt-2">
                                Usado por: recuperação de senha, geração de relatórios por email (futuro). Senhas no DB são em plaintext —
                                use uma conta dedicada de SMTP, não compartilhe.
                            </p>
                        </div>

                        <!-- Config form -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-6 border-b border-slate-900/10 dark:border-white/5 pb-4">Configuração do servidor SMTP</h3>

                            <form method="POST" data-loader="off" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="email">
                                <input type="hidden" name="action" value="save_email_config">

                                <label class="flex items-center gap-3 cursor-pointer p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">
                                    <input type="checkbox" name="smtp_enabled" value="1" <?= $smtpConfig['smtp_enabled'] ? 'checked' : '' ?> class="w-5 h-5">
                                    <div>
                                        <p class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Habilitar SMTP</p>
                                        <p class="text-[10px] text-slate-500">Quando desligado, usa mail() do PHP (MTA local).</p>
                                    </div>
                                </label>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Host SMTP</label>
                                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtpConfig['smtp_host']) ?>" placeholder="smtp.gmail.com" class="glass-input w-full font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Porta</label>
                                        <input type="number" name="smtp_port" value="<?= (int)$smtpConfig['smtp_port'] ?>" min="1" max="65535" class="glass-input w-full font-mono">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Encriptação</label>
                                    <select name="smtp_encryption" class="glass-input w-full uppercase text-[10px] font-black">
                                        <option value="tls"  <?= $smtpConfig['smtp_encryption'] === 'tls'  ? 'selected' : '' ?>>STARTTLS (porta 587, recomendado)</option>
                                        <option value="ssl"  <?= $smtpConfig['smtp_encryption'] === 'ssl'  ? 'selected' : '' ?>>SMTPS (porta 465, TLS direto)</option>
                                        <option value="none" <?= $smtpConfig['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Nenhuma (porta 25, NÃO use em produção)</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Usuário (auth)</label>
                                        <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtpConfig['smtp_user']) ?>" placeholder="seu-email@gmail.com" class="glass-input w-full font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Senha (auth)</label>
                                        <input type="password" name="smtp_password"
                                               placeholder="<?= !empty($smtpConfig['smtp_password']) ? '••••••• (deixe vazio pra manter)' : 'app-password ou senha' ?>"
                                               class="glass-input w-full font-mono"
                                               autocomplete="new-password">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">From (endereço)</label>
                                        <input type="email" name="smtp_from" value="<?= htmlspecialchars($smtpConfig['smtp_from']) ?>" placeholder="dns-noreply@empresa.com" class="glass-input w-full font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">From (nome)</label>
                                        <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtpConfig['smtp_from_name']) ?>" placeholder="Unbound Dashboard" class="glass-input w-full">
                                    </div>
                                </div>

                                <!-- Notificações de release nova -->
                                <div class="pt-4 border-t border-slate-900/10 dark:border-white/5">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" name="notify_email_on_release" value="1" <?= !empty($smtpConfig['notify_email_on_release']) ? 'checked' : '' ?> class="w-5 h-5 mt-0.5">
                                        <span>
                                            <span class="text-xs font-bold text-slate-900 dark:text-white">Notificar nova release por email</span>
                                            <span class="block text-[10px] text-slate-500 mt-0.5">Quando o worker detectar uma nova versão no GitHub, envia email pra todos admins ativos com email cadastrado. Frequência: até 1x por versão.</span>
                                        </span>
                                    </label>
                                </div>

                                <div class="flex justify-end pt-4 border-t border-slate-900/10 dark:border-white/5">
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Salvar Configuração</button>
                                </div>
                            </form>
                        </div>

                        <!-- Teste de envio -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3">Teste de envio</h3>
                            <p class="text-[11px] text-slate-500 mb-4">Envia um email de teste pra validar a configuração SMTP. O log SMTP detalhado aparece abaixo após executar.</p>
                            <form method="POST" data-loader="off" class="flex gap-3 flex-wrap">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="email">
                                <input type="hidden" name="action" value="test_email">
                                <input type="email" name="test_email_to" required placeholder="destinatario@exemplo.com" class="glass-input flex-1 font-mono" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>">
                                <button type="submit" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Enviar Teste</button>
                            </form>

                            <?php if (!empty($smtpTestHint)): ?>
                                <div class="mt-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-300 uppercase tracking-widest mb-2">💡 Dica baseada no erro</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-200 leading-relaxed"><?= htmlspecialchars($smtpTestHint) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($smtpTestLog)): ?>
                                <div class="mt-4">
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Log SMTP da última tentativa</p>
                                    <pre class="text-[10px] font-mono bg-black/60 text-emerald-300 p-4 rounded-xl overflow-x-auto max-h-80"><?= htmlspecialchars($smtpTestLog) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Cheat-sheet -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3">Provedores comuns</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[11px]">
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Gmail</p>
                                    <p class="text-slate-500">Host: <code>smtp.gmail.com</code></p>
                                    <p class="text-slate-500">Porta: <code>587</code> · STARTTLS</p>
                                    <p class="text-slate-500 text-[10px]">Use <strong>App Password</strong> (não a senha da conta).</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Outlook 365</p>
                                    <p class="text-slate-500">Host: <code>smtp.office365.com</code></p>
                                    <p class="text-slate-500">Porta: <code>587</code> · STARTTLS</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">AWS SES</p>
                                    <p class="text-slate-500">Host: <code>email-smtp.region.amazonaws.com</code></p>
                                    <p class="text-slate-500">Porta: <code>587</code> · STARTTLS</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Mailgun</p>
                                    <p class="text-slate-500">Host: <code>smtp.mailgun.org</code></p>
                                    <p class="text-slate-500">Porta: <code>587</code> · STARTTLS</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">SendGrid</p>
                                    <p class="text-slate-500">Host: <code>smtp.sendgrid.net</code></p>
                                    <p class="text-slate-500">Porta: <code>587</code> · STARTTLS</p>
                                    <p class="text-slate-500 text-[10px]">User = <code>apikey</code>, pass = sua key.</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Postfix local</p>
                                    <p class="text-slate-500">Host: <code>localhost</code></p>
                                    <p class="text-slate-500">Porta: <code>25</code> · Sem encriptação</p>
                                    <p class="text-slate-500 text-[10px]">Sem auth se relay local.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- tab-webhooks — webhook de alertas (admin only) — fora do mainConfigForm -->
                    <?php if ($isAdmin):
                        require_once __DIR__ . '/src/ApiClient.php';
                        $jwtForWebhook = $_SESSION['api_jwt'] ?? '';
                        $whCfg = ['enabled' => false, 'url' => '', 'type' => 'generic', 'severity_min' => 'critical', 'telegram_chat_id' => ''];
                        $whRes = \App\ApiClient::get('/api/v1/webhooks/config', $jwtForWebhook);
                        if ($whRes['ok'] && is_array($whRes['data'])) {
                            $whCfg = array_merge($whCfg, $whRes['data']);
                        }
                        $whTestBody = $_SESSION['webhook_test_body'] ?? '';
                        unset($_SESSION['webhook_test_body']);
                    ?>
                    <div id="tab-webhooks" class="tab-content <?= $activeTab === 'webhooks' ? 'active' : '' ?> space-y-6">

                        <!-- Status -->
                        <div class="glass-panel border-l-4 <?= $whCfg['enabled'] ? 'border-emerald-500' : 'border-slate-500' ?>">
                            <div class="flex items-center gap-3">
                                <span class="w-3 h-3 rounded-full <?= $whCfg['enabled'] ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                <?php if ($whCfg['enabled'] && !empty($whCfg['url'])): ?>
                                    <p class="text-sm text-slate-700 dark:text-slate-300">Webhook <strong class="text-emerald-600 dark:text-emerald-400">ATIVO</strong> — tipo <code class="text-xs"><?= htmlspecialchars($whCfg['type']) ?></code>, severity mínima <code class="text-xs"><?= htmlspecialchars($whCfg['severity_min']) ?></code>.</p>
                                <?php else: ?>
                                    <p class="text-sm text-slate-500">Webhook <strong>DESATIVADO</strong> — alertas não disparam notificação externa.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Form config -->
                        <form method="POST" action="config.php?tab=webhooks" class="glass-panel space-y-5">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="save_webhook_config">
                            <input type="hidden" name="tab" value="webhooks">

                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                        <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        Webhook de Alertas
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-1">Notifica Slack, Discord, Teams ou endpoint genérico quando alertas críticos disparam.</p>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-bold">
                                    <input type="checkbox" name="webhook_enabled" value="1" <?= !empty($whCfg['enabled']) ? 'checked' : '' ?> class="w-5 h-5">
                                    <span>Habilitar</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-2 space-y-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">URL do webhook</label>
                                    <input type="url" name="webhook_url" value="<?= htmlspecialchars($whCfg['url']) ?>"
                                           placeholder="https://hooks.slack.com/services/T00.../B00.../xxx"
                                           class="glass-input w-full font-mono">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Tipo</label>
                                    <select name="webhook_type" id="webhookTypeSelect" class="glass-input w-full" onchange="toggleTelegramFields()">
                                        <option value="slack"    <?= $whCfg['type'] === 'slack'    ? 'selected' : '' ?>>Slack</option>
                                        <option value="discord"  <?= $whCfg['type'] === 'discord'  ? 'selected' : '' ?>>Discord</option>
                                        <option value="teams"    <?= $whCfg['type'] === 'teams'    ? 'selected' : '' ?>>Microsoft Teams</option>
                                        <option value="telegram" <?= $whCfg['type'] === 'telegram' ? 'selected' : '' ?>>Telegram (Bot API)</option>
                                        <option value="generic"  <?= $whCfg['type'] === 'generic'  ? 'selected' : '' ?>>Genérico (JSON)</option>
                                    </select>
                                </div>
                            </div>

                            <div id="telegramFields" class="grid grid-cols-1 sm:grid-cols-3 gap-4" style="display:none">
                                <div class="sm:col-span-3 space-y-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Telegram chat_id</label>
                                    <input type="text" name="webhook_telegram_chat_id" value="<?= htmlspecialchars($whCfg['telegram_chat_id'] ?? '') ?>"
                                           placeholder="123456789 (user) ou -1001234567890 (canal)"
                                           class="glass-input w-full font-mono">
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        URL deve ser <code>https://api.telegram.org/bot&lt;TOKEN&gt;/sendMessage</code>.
                                        O <code>chat_id</code> é o ID do chat/canal pra onde o bot vai enviar.
                                        Pegue via <code>@userinfobot</code> (user) ou adicione o bot ao canal e veja em <code>/getUpdates</code>.
                                    </p>
                                </div>
                            </div>
                            <script>
                            function toggleTelegramFields() {
                                const sel = document.getElementById('webhookTypeSelect');
                                const box = document.getElementById('telegramFields');
                                if (sel && box) box.style.display = sel.value === 'telegram' ? '' : 'none';
                            }
                            toggleTelegramFields();
                            </script>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Severity mínima</label>
                                    <select name="webhook_severity_min" class="glass-input w-full">
                                        <option value="critical" <?= $whCfg['severity_min'] === 'critical' ? 'selected' : '' ?>>Apenas críticos</option>
                                        <option value="warning"  <?= $whCfg['severity_min'] === 'warning'  ? 'selected' : '' ?>>Warning e críticos</option>
                                    </select>
                                    <p class="text-[9px] text-slate-600 font-bold italic">Alertas abaixo da severity não notificam.</p>
                                </div>
                                <div class="sm:col-span-2 flex items-end">
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        <strong>Throttle:</strong> mesmo tipo de alerta não re-notifica por 15min após envio.
                                        Estado mantido em Redis. <strong>Best-effort:</strong> falhas HTTP/conexão são logadas
                                        mas não derrubam o worker (timeout 5s).
                                    </p>
                                </div>
                            </div>

                            <!-- Notificação de nova release -->
                            <div class="pt-4 border-t border-slate-900/10 dark:border-white/5">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="webhook_notify_on_release" value="1" <?= !empty($whCfg['notify_on_release']) ? 'checked' : '' ?> class="w-5 h-5 mt-0.5">
                                    <span>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">Notificar nova release via webhook</span>
                                        <span class="block text-[10px] text-slate-500 mt-0.5">Quando o worker detectar uma nova versão no GitHub, dispara webhook (Slack/Discord/Teams/Genérico). Independe do severity_min — releases são sempre informativas. Até 1x por versão.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Salvar webhook</button>
                            </div>
                        </form>

                        <!-- Teste -->
                        <form method="POST" action="config.php?tab=webhooks" class="glass-panel space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="test_webhook">
                            <input type="hidden" name="tab" value="webhooks">

                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Enviar teste</h3>
                            <p class="text-[11px] text-slate-500">Dispara mensagem de teste pra confirmar a integração. Ignora throttle e severity mínima.</p>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input type="text" name="test_message" placeholder="Mensagem (opcional)" class="glass-input flex-1">
                                <button type="submit" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black whitespace-nowrap">Enviar teste</button>
                            </div>
                            <?php if ($whTestBody !== ''): ?>
                                <div>
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Resposta do servidor</p>
                                    <pre class="bg-slate-900/90 text-slate-100 rounded-xl border border-slate-700/60 p-3 overflow-auto text-[11px] leading-relaxed font-mono"><?= htmlspecialchars($whTestBody) ?></pre>
                                </div>
                            <?php endif; ?>
                        </form>

                        <!-- Cheat-sheet -->
                        <div class="glass-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3">Como criar o webhook</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-[11px]">
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Slack</p>
                                    <p class="text-slate-500">Apps → "Incoming Webhooks" → Add to Slack → escolha canal → copie a URL.</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Discord</p>
                                    <p class="text-slate-500">Canal → engrenagem → Integrações → Webhooks → Novo → copie URL.</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Microsoft Teams</p>
                                    <p class="text-slate-500">Canal → ⋯ → Conectores → Incoming Webhook → configurar.</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="font-black text-slate-700 dark:text-slate-300 mb-1">Genérico</p>
                                    <p class="text-slate-500">Qualquer endpoint que aceite POST JSON. Body: <code>{type, severity, message, timestamp, source}</code>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- tab-updates — self-update via UI (admin only, capability config.write) -->
                    <?php if ($isAdmin): ?>
                    <div id="tab-updates" class="tab-content <?= $activeTab === 'updates' ? 'active' : '' ?> space-y-6">

                        <!-- Status card (populado pelo JS) -->
                        <div class="glass-panel border-l-4 border-slate-500" id="updates-status-card">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div class="flex-1 min-w-[260px]">
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                        <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Atualizações do Sistema
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-1">Aplica updates direto do GitHub Releases com verificação SHA256 e rollback automático em caso de falha.</p>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <button type="button" id="updates-refresh-btn"
                                            class="updates-check-btn group relative flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-black text-xs uppercase tracking-widest shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:transform-none"
                                            title="Consultar GitHub Releases por nova versão">
                                        <svg id="updates-refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                        </svg>
                                        <span>Verificar atualizações</span>
                                    </button>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest" id="updates-last-check">Auto-check 6h · clique pra verificar agora</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Versão atual</p>
                                    <p class="text-lg font-black text-slate-900 dark:text-white mt-1 font-mono" id="updates-current">…</p>
                                </div>
                                <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Última no GitHub</p>
                                    <p class="text-lg font-black mt-1 font-mono" id="updates-latest">…</p>
                                    <p class="text-[10px] text-slate-500" id="updates-published"></p>
                                </div>
                            </div>

                            <!-- Banner contextual: up-to-date | update available | major bump warning | github off -->
                            <div id="updates-banner" class="mt-4 hidden"></div>

                            <!-- Botão Atualizar (oculto até /check confirmar update disponível) -->
                            <div id="updates-action" class="mt-4 hidden">
                                <label id="updates-ack-wrapper" class="flex items-center gap-2 text-xs font-bold mb-3 hidden">
                                    <input type="checkbox" id="updates-ack-checkbox" class="w-4 h-4 accent-amber-500">
                                    <span>Estou ciente de que esta é uma <strong>major version</strong> e pode trazer breaking changes. Li o CHANGELOG.</span>
                                </label>
                                <button type="button" id="updates-apply-btn" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black" disabled>
                                    Atualizar agora
                                </button>
                            </div>
                        </div>

                        <!-- Notas da release -->
                        <div class="glass-panel hidden" id="updates-notes-panel">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3">Notas da release</h3>
                            <pre id="updates-notes" class="text-xs leading-relaxed text-slate-700 dark:text-slate-300 font-mono whitespace-pre-wrap"></pre>
                            <p class="mt-3"><a id="updates-release-url" href="#" target="_blank" rel="noopener" class="text-cyan-600 dark:text-cyan-400 underline text-xs">Ver no GitHub</a></p>
                        </div>

                        <!-- Histórico de backups -->
                        <div class="glass-panel" id="updates-backups-panel">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4M3 7l9 4 9-4M12 11v8"/></svg>
                                    Histórico de backups
                                </h3>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest" id="updates-backups-count">Últimos 10</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mb-4">
                                Cada update gera um backup automático antes de aplicar. Você pode restaurar qualquer um — o sistema reinicia automaticamente.
                                Restore é destrutivo: precisa confirmar digitando <code class="bg-slate-900/10 dark:bg-white/10 px-1 rounded text-[10px]">RESTAURAR</code>.
                            </p>
                            <div id="updates-backups-list" class="space-y-2">
                                <p class="text-xs text-slate-500 italic">Carregando…</p>
                            </div>
                        </div>

                    </div>

                    <!-- Modal de confirmação de restore -->
                    <?php if ($isAdmin): ?>
                    <div id="restore-confirm-modal" class="hidden fixed inset-0 z-[115] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <div class="glass-panel max-w-md w-full !p-6 border-red-500/30 shadow-2xl shadow-red-500/20">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="shrink-0 w-10 h-10 rounded-full bg-red-500/20 text-red-500 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.732 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                </div>
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Confirmar restore</h3>
                            </div>
                            <p class="text-xs text-slate-700 dark:text-slate-300 mb-3">
                                Restaurar o backup de <strong id="restore-confirm-ts" class="font-mono"></strong>?
                            </p>
                            <p class="text-[11px] text-amber-700 dark:text-amber-300 mb-4">
                                Vai sobrescrever código, banco DuckDB e env file. Sistema reinicia automaticamente. Um snapshot do estado atual é gravado em <code class="bg-slate-900/10 dark:bg-white/10 px-1 rounded text-[10px]">/var/backups/.../pre-restore-*.tar.gz</code>.
                            </p>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Digite <code class="text-red-600 dark:text-red-400">RESTAURAR</code> pra confirmar</label>
                            <input type="text" id="restore-confirm-input" autocomplete="off" class="glass-input w-full font-mono text-sm mb-4" placeholder="RESTAURAR">
                            <div class="flex justify-end gap-2">
                                <button type="button" id="restore-confirm-cancel" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                <button type="button" id="restore-confirm-go" class="glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black" disabled>Restaurar</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- tab-auditoria — trilha de auditoria de updates/restores (capability users.read) -->
                    <?php if ($isAdmin): ?>
                    <div id="tab-auditoria" class="tab-content <?= $activeTab === 'auditoria' ? 'active' : '' ?> space-y-6">
                        <div class="glass-panel">
                            <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                                <div>
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Auditoria de Updates
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-1">Trilha completa de updates e restores aplicados via UI — quem, quando, qual versão, qual resultado.</p>
                                </div>
                                <button type="button" id="audit-refresh-btn" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2" title="Recarregar histórico">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                    Recarregar
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-white/10 text-[10px] uppercase tracking-widest text-slate-500 font-black">
                                            <th class="py-2 px-2 text-left">Quando</th>
                                            <th class="py-2 px-2 text-left">Tipo</th>
                                            <th class="py-2 px-2 text-left">Quem</th>
                                            <th class="py-2 px-2 text-left">IP</th>
                                            <th class="py-2 px-2 text-left">Versão</th>
                                            <th class="py-2 px-2 text-left">Status</th>
                                            <th class="py-2 px-2 text-right">Duração</th>
                                        </tr>
                                    </thead>
                                    <tbody id="audit-tbody">
                                        <tr><td colspan="7" class="py-6 text-center text-slate-500 italic text-xs">Carregando…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-3" id="audit-summary"></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- tab-api_tokens — API tokens long-lived pra master multi-host (capability tokens.manage) -->
                    <?php if ($isAdmin): ?>
                    <div id="tab-api_tokens" class="tab-content <?= $activeTab === 'api_tokens' ? 'active' : '' ?> space-y-6">

                        <div class="glass-panel">
                            <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                                <div class="flex-1 min-w-[260px]">
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                                        API Tokens (multi-host)
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-1">
                                        Tokens long-lived pra autenticação master → agent. Diferente do JWT (sessão de user),
                                        estes são gerados aqui e copiados pro <em>master</em> de um deployment multi-host.
                                    </p>
                                </div>
                                <button type="button" id="api-token-new-btn" class="glass-btn !bg-purple-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Gerar novo token
                                </button>
                            </div>

                            <div id="api-tokens-list" class="space-y-2">
                                <p class="text-xs text-slate-500 italic">Carregando…</p>
                            </div>
                        </div>

                        <!-- Modal: gerar token novo -->
                        <div id="api-token-new-modal" class="hidden fixed inset-0 z-[115] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                            <div class="glass-panel max-w-md w-full !p-6 border-purple-500/30 shadow-2xl shadow-purple-500/20">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Gerar novo API token</h3>
                                <p class="text-xs text-slate-500 mb-4">
                                    Use uma label clara pra identificar o uso (ex: "master-orchestrator", "monitoring-readonly").
                                    O token <strong>aparece UMA vez</strong>; copie e cole no master imediatamente.
                                </p>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Label</label>
                                <input type="text" id="api-token-label-input" autocomplete="off" maxlength="100" class="glass-input w-full font-mono text-sm mb-4" placeholder="master-orchestrator">
                                <div class="flex justify-end gap-2">
                                    <button type="button" id="api-token-cancel-btn" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                    <button type="button" id="api-token-create-btn" class="glass-btn !bg-purple-600 !text-white text-[10px] uppercase font-black" disabled>Gerar</button>
                                </div>
                            </div>
                        </div>

                        <!-- Modal: mostrar token recém-criado (UMA vez) -->
                        <div id="api-token-reveal-modal" class="hidden fixed inset-0 z-[115] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
                            <div class="glass-panel max-w-lg w-full !p-6 border-emerald-500/30 shadow-2xl shadow-emerald-500/20">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="text-emerald-500">✓</span> Token gerado
                                </h3>
                                <p class="text-xs text-amber-700 dark:text-amber-300 font-bold mb-4">
                                    ⚠ Este token só aparece AGORA. Copie e guarde em local seguro — depois disso, só pode revogar e gerar outro.
                                </p>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Token (clique pra selecionar)</label>
                                <pre id="api-token-reveal-value" onclick="this.select && this.select(); document.execCommand && document.execCommand('selectAll'); window.getSelection().selectAllChildren(this);" class="bg-slate-900/95 text-emerald-300 rounded-xl border border-emerald-500/30 p-4 text-[11px] font-mono break-all cursor-text select-all mb-4"></pre>
                                <div class="flex justify-end gap-2">
                                    <button type="button" id="api-token-copy-btn" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Copiar pra área de transferência</button>
                                    <button type="button" id="api-token-reveal-close-btn" class="glass-btn text-[10px] uppercase font-black">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Modal global de update — fora do tab-updates pra cobrir tela inteira -->
                    <div id="updates-console-panel" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="updates-modal-title">
                        <div class="glass-panel w-full max-w-3xl max-h-[90vh] flex flex-col !p-0 overflow-hidden border-slate-200 dark:border-white/5 shadow-2xl shadow-cyan-500/20">
                            <!-- Header -->
                            <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div id="updates-modal-spinner" class="relative shrink-0 w-8 h-8">
                                        <div class="absolute inset-0 rounded-full border-2 border-cyan-500/30"></div>
                                        <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-cyan-500 animate-spin"></div>
                                    </div>
                                    <div id="updates-modal-final-icon" class="hidden shrink-0 w-8 h-8 rounded-full flex items-center justify-center"></div>
                                    <div class="min-w-0">
                                        <h3 id="updates-modal-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest truncate">Aplicando atualização</h3>
                                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 truncate">
                                            <span id="updates-job-version" class="text-cyan-600 dark:text-cyan-400">…</span>
                                            <span id="updates-job-id" class="ml-2 opacity-60"></span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" id="updates-modal-close" class="hidden glass-btn !p-2 text-slate-500 hover:text-slate-900 dark:hover:text-white" title="Fechar" aria-label="Fechar modal">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Body: log + banner final -->
                            <div class="flex-1 overflow-hidden flex flex-col gap-3 p-5">
                                <pre id="updates-console" class="flex-1 min-h-[280px] bg-slate-900/95 text-slate-100 rounded-xl border border-slate-700/60 p-4 overflow-auto text-[11px] leading-relaxed font-mono"></pre>
                                <div id="updates-final-banner" class="hidden"></div>
                            </div>

                            <!-- Footer com hint quando rodando -->
                            <div id="updates-modal-footer" class="px-6 py-3 border-t border-slate-200 dark:border-white/10 bg-slate-900/5 dark:bg-white/5">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                                    Não feche esta janela — o update precisa terminar pra evitar inconsistências.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                    <div id="tab-usuarios" class="tab-content <?= $activeTab === 'usuarios' ? 'active' : '' ?> space-y-6">

                        <?php if (empty($allUsers)): ?>
                            <div class="glass-panel border-l-4 border-amber-500">
                                <p class="text-[10px] font-black text-amber-700 dark:text-amber-300 uppercase tracking-widest mb-2">Nenhum usuário retornado</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300 mb-3">
                                    A FastAPI não retornou usuários — provavelmente seu <strong>JWT da sessão expirou</strong>
                                    (default 60min). Faça <a href="logout.php" class="text-amber-600 dark:text-amber-400 hover:underline font-bold">logout</a>
                                    e login novamente pra renovar.
                                </p>
                                <p class="text-[10px] text-slate-500">
                                    Se persistir após re-login, confira <code>/var/log/apache2/error.log</code> por mensagens
                                    "<code>ApiClient::get falhou</code>" e o status do <code>unbound-dashboard-api.service</code>.
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($tempPassword): ?>
                            <div class="glass-panel border-l-4 border-amber-500">
                                <p class="text-[10px] font-black text-amber-700 dark:text-amber-300 uppercase tracking-widest mb-2">Senha temporária gerada</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300 mb-3">Entregue manualmente ao usuário <strong><?= htmlspecialchars($tempPasswordUser ?? '') ?></strong>. Esta senha não será exibida novamente.</p>
                                <div class="flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-4 rounded-2xl border border-slate-200 dark:border-white/10">
                                    <code id="temp-pw" class="font-mono text-base font-bold text-slate-900 dark:text-white tracking-wider"><?= htmlspecialchars($tempPassword) ?></code>
                                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('temp-pw').textContent); this.textContent='Copiada!';"
                                            class="glass-btn text-[10px] uppercase font-black">Copiar</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Header da aba: título + botão Novo Usuário -->
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Gestão de Usuários
                                </h2>
                                <p class="text-sm text-slate-500 mt-1">Listar, criar, editar role/email, suspender, resetar senha e excluir contas.</p>
                            </div>
                            <button type="button" onclick="document.getElementById('modal-new-user').classList.remove('hidden')"
                                    class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                Novo Usuário
                            </button>
                        </div>

                        <!-- Toolbar: busca + filtros -->
                        <div class="glass-panel">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar (username ou email)</label>
                                    <input type="text" id="filter-search" oninput="filterUsers()" placeholder="ex: admin@empresa.com" class="glass-input w-full">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Role</label>
                                    <select id="filter-role" onchange="filterUsers()" class="glass-input w-full uppercase text-[10px] font-black">
                                        <option value="">TODOS</option>
                                        <?php foreach (\App\Auth::rolesCatalog() as $rk => $rmeta): ?>
                                            <option value="<?= htmlspecialchars($rk) ?>"><?= strtoupper(htmlspecialchars($rmeta['label'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Status</label>
                                    <select id="filter-status" onchange="filterUsers()" class="glass-input w-full uppercase text-[10px] font-black">
                                        <option value="">TODOS</option>
                                        <option value="active">ATIVOS</option>
                                        <option value="inactive">SUSPENSOS</option>
                                        <option value="locked">BLOQUEADOS</option>
                                    </select>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-3">
                                Total: <span id="users-count-total"><?= count($allUsers) ?></span> · Visíveis: <span id="users-count-visible"><?= count($allUsers) ?></span>
                            </p>
                        </div>

                        <!-- Tabela de usuários -->
                        <div class="glass-panel overflow-x-auto">
                            <table class="w-full text-sm" id="users-table">
                                <thead class="text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-900/10 dark:border-white/5">
                                    <tr>
                                        <th class="text-left py-3 px-2">Usuário</th>
                                        <th class="text-left py-3 px-2">Email</th>
                                        <th class="text-left py-3 px-2">Role</th>
                                        <th class="text-left py-3 px-2">Organização</th>
                                        <th class="text-left py-3 px-2">Status</th>
                                        <th class="text-left py-3 px-2">Último Login</th>
                                        <th class="text-left py-3 px-2">Criado</th>
                                        <th class="text-right py-3 px-2">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allUsers as $u):
                                        $isSelf = ((int)$u['id']) === $currentUserId;
                                        $isLocked = !empty($u['locked_until']) && (new DateTime($u['locked_until']))->getTimestamp() > time();
                                        $statusKey = $isLocked ? 'locked' : (!empty($u['is_active']) ? 'active' : 'inactive');
                                        $username = $u['username'] ?? '';
                                        $email = $u['email'] ?? '';
                                        ?>
                                        <tr class="user-row border-b border-slate-900/5 dark:border-white/5 hover:bg-slate-900/5 dark:hover:bg-white/5"
                                            data-username="<?= htmlspecialchars(strtolower($username)) ?>"
                                            data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                                            data-role="<?= htmlspecialchars($u['role'] ?? '') ?>"
                                            data-status="<?= $statusKey ?>">
                                            <td class="py-3 px-2">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 rounded-2xl bg-<?= ($u['role'] ?? '') === 'admin' ? 'red' : 'blue' ?>-600/10 text-<?= ($u['role'] ?? '') === 'admin' ? 'red' : 'blue' ?>-500 flex items-center justify-center font-black text-xs">
                                                        <?= strtoupper(substr($username, 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($username) ?>
                                                            <?php if ($isSelf): ?><span class="text-[9px] uppercase font-black text-blue-500 ml-1">(você)</span><?php endif; ?>
                                                        </p>
                                                        <p class="text-[10px] text-slate-500 font-mono">ID #<?= (int)$u['id'] ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-2">
                                                <form method="POST" class="flex items-center gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="tab" value="usuarios">
                                                    <input type="hidden" name="action" value="update_email">
                                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                    <input type="hidden" name="target_username" value="<?= htmlspecialchars($username) ?>">
                                                    <input type="email" name="new_email_value" value="<?= htmlspecialchars($email) ?>" placeholder="—"
                                                           class="glass-input !py-1 !px-2 text-xs w-48 font-mono">
                                                    <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black opacity-50 hover:opacity-100" title="Salvar email">✓</button>
                                                </form>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?php if ($isSelf): ?>
                                                    <span class="text-xs font-mono uppercase font-bold text-slate-500"><?= htmlspecialchars($u['role'] ?? '') ?></span>
                                                <?php else: ?>
                                                    <form method="POST" class="flex items-center gap-1">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="tab" value="usuarios">
                                                        <input type="hidden" name="action" value="update_role">
                                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                        <select name="new_role_value" onchange="this.form.submit()" class="glass-input !py-1 !px-2 text-xs uppercase font-black" title="<?= htmlspecialchars(\App\Auth::rolesCatalog()[$u['role']]['desc'] ?? '') ?>">
                                                            <?php foreach (\App\Auth::rolesCatalog() as $rk => $rmeta): ?>
                                                                <option value="<?= htmlspecialchars($rk) ?>" <?= ($u['role'] ?? '') === $rk ? 'selected' : '' ?> title="<?= htmlspecialchars($rmeta['desc']) ?>">
                                                                    <?= strtoupper(htmlspecialchars($rmeta['label'])) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?php if (empty($allOrgs)): ?>
                                                    <span class="text-[10px] text-slate-500 italic">—</span>
                                                <?php else: ?>
                                                    <form method="POST" class="flex items-center gap-1">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="tab" value="usuarios">
                                                        <input type="hidden" name="action" value="update_org">
                                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                        <select name="new_org_value" onchange="this.form.submit()" class="glass-input !py-1 !px-2 text-xs" title="Atribuir org (vazio = global/system)">
                                                            <option value="">— Global —</option>
                                                            <?php foreach ($allOrgs as $org): ?>
                                                                <option value="<?= (int)$org['id'] ?>" <?= ((int)($u['org_id'] ?? 0)) === (int)$org['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($org['name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?php if ($isLocked): ?>
                                                    <span class="inline-block px-2 py-1 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 text-[10px] uppercase font-black">Bloqueado</span>
                                                    <p class="text-[9px] text-slate-500 mt-1"><?= htmlspecialchars((string)($u['failed_logins'] ?? 0)) ?> falhas · até <?= htmlspecialchars($fmtUserDate($u['locked_until']) ?? '') ?></p>
                                                <?php elseif (!empty($u['is_active'])): ?>
                                                    <span class="inline-block px-2 py-1 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] uppercase font-black">Ativo</span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2 py-1 rounded-lg bg-red-500/15 text-red-600 dark:text-red-400 text-[10px] uppercase font-black">Suspenso</span>
                                                <?php endif; ?>
                                                <?php if (!empty($u['totp_enabled'])): ?>
                                                    <span class="inline-block ml-1 px-2 py-1 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 text-[10px] uppercase font-black" title="2FA habilitado">🛡️ 2FA</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-2 text-xs text-slate-600 dark:text-slate-400"><?= htmlspecialchars($relativeUserTime($u['last_login_at'] ?? null)) ?>
                                                <?php if (!empty($u['last_login_at'])): ?>
                                                    <p class="text-[9px] text-slate-500"><?= htmlspecialchars($fmtUserDate($u['last_login_at'])) ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-2 text-xs text-slate-600 dark:text-slate-400"><?= htmlspecialchars($fmtUserDate($u['created_at'] ?? null) ?? '—') ?></td>
                                            <td class="py-3 px-2 text-right">
                                                <div class="flex justify-end gap-1 flex-wrap">
                                                    <form method="POST" data-confirm-message="Gerar nova senha temporária para <?= htmlspecialchars($username) ?>?" data-confirm-title="Reset de senha" data-confirm-text="Gerar senha">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="tab" value="usuarios">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($username) ?>">
                                                        <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black" title="Resetar senha">🔑</button>
                                                    </form>
                                                    <?php if (!empty($u['totp_enabled'])): ?>
                                                    <form method="POST" data-confirm-message="Resetar 2FA de <?= htmlspecialchars($username) ?>? Ele(a) precisará reconfigurar no app autenticador." data-confirm-title="Reset 2FA" data-confirm-text="Resetar 2FA" data-confirm-variant="danger">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="tab" value="usuarios">
                                                        <input type="hidden" name="action" value="admin_reset_totp">
                                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                        <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black bg-amber-500/15 text-amber-600" title="Resetar 2FA">🛡️↺</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <?php if (!$isSelf): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="tab" value="usuarios">
                                                            <input type="hidden" name="action" value="toggle_user">
                                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                            <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black" title="<?= !empty($u['is_active']) ? 'Suspender' : 'Ativar' ?>"><?= !empty($u['is_active']) ? '⏸' : '▶' ?></button>
                                                        </form>
                                                        <form method="POST" data-confirm-message="Excluir permanentemente <?= htmlspecialchars($username) ?>? Esta ação não pode ser desfeita." data-confirm-title="Confirmar exclusão" data-confirm-text="Excluir" data-confirm-variant="danger">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="tab" value="usuarios">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                            <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black bg-red-500/10 text-red-500" title="Excluir">✕</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p id="users-empty" class="hidden text-center text-slate-500 text-sm py-8">Nenhum usuário atende aos filtros.</p>
                        </div>
                    </div>

                    <!-- Modal: Novo Usuário (admin-only) -->
                    <div id="modal-new-user" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4" onclick="if (event.target === this) this.classList.add('hidden')">
                        <div class="glass-panel max-w-md w-full">
                            <h3 class="text-sm font-black uppercase tracking-widest mb-4 text-slate-900 dark:text-white">Novo Usuário</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="usuarios">
                                <input type="hidden" name="action" value="add_user">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Login</label>
                                    <input type="text" name="new_username" required pattern="[a-zA-Z0-9._-]+" class="glass-input w-full" placeholder="ex: maria.silva">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Email (opcional)</label>
                                    <input type="email" name="new_email" class="glass-input w-full" placeholder="maria@empresa.com">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Senha (mín. 6)</label>
                                    <input type="password" name="new_password" required minlength="6" class="glass-input w-full" placeholder="••••••">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Role</label>
                                    <select name="new_role" class="glass-input w-full text-[10px] font-black">
                                        <?php foreach (\App\Auth::rolesCatalog() as $rk => $rmeta): ?>
                                            <option value="<?= htmlspecialchars($rk) ?>" <?= $rk === 'viewer' ? 'selected' : '' ?> title="<?= htmlspecialchars($rmeta['desc']) ?>">
                                                <?= htmlspecialchars(strtoupper($rmeta['label']) . ' — ' . $rmeta['desc']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex justify-end gap-2 pt-4 border-t border-slate-900/10 dark:border-white/5">
                                    <button type="button" onclick="document.getElementById('modal-new-user').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Criar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; /* isAdmin */ ?>

                    <div id="tab-perfil" class="tab-content <?= $activeTab === 'perfil' ? 'active' : '' ?> space-y-6">
                        <div class="glass-panel border-slate-900/10 dark:border-white/5">
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase mb-6">Alterar Minha Senha</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tab" value="perfil">
                                <input type="hidden" name="action" value="update_profile_pass">
                                <input type="password" name="old_pass" placeholder="SENHA ATUAL" class="glass-input w-full">
                                <input type="password" name="new_pass" placeholder="NOVA SENHA" class="glass-input w-full">
                                <button type="submit" class="glass-btn w-full text-[10px] font-black uppercase">SALVAR NOVA SENHA</button>
                            </form>
                        </div>

                        <!-- 2FA TOTP -->
                        <?php
                            $totpEnabled = !empty($_SESSION['totp_enabled']);
                            $totpSetup = $_SESSION['totp_setup'] ?? null;  // {secret, uri} populado pelo handler setup_totp
                            unset($_SESSION['totp_setup']);
                        ?>
                        <div class="glass-panel border-l-4 <?= $totpEnabled ? 'border-emerald-500' : 'border-slate-500' ?>">
                            <div class="flex items-start justify-between gap-3 mb-4 border-b border-slate-900/10 dark:border-white/5 pb-3">
                                <div>
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase flex items-center gap-2">
                                        <svg class="w-4 h-4 <?= $totpEnabled ? 'text-emerald-500' : 'text-slate-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Autenticação em 2 fatores (TOTP)
                                    </h3>
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        <?php if ($totpEnabled): ?>
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">ATIVADO</span> — login pede um código de 6 dígitos após senha.
                                        <?php else: ?>
                                            <span class="text-slate-500 font-bold">DESATIVADO</span> — opcional. Apps: Google Authenticator, Authy, 1Password, Aegis.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ($totpSetup): ?>
                                <!-- Estágio 2: mostrar QR code + pedir confirmação -->
                                <div class="space-y-4">
                                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                        <strong>1.</strong> Escaneie o QR code com seu app autenticador.<br>
                                        <strong>2.</strong> Digite o código de 6 dígitos que aparecer.<br>
                                        <strong>3.</strong> Clique em <em>Confirmar</em>.
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                        <div class="bg-white p-4 rounded-2xl flex items-center justify-center">
                                            <div id="totp-qr"></div>
                                        </div>
                                        <div class="space-y-3">
                                            <div class="bg-slate-900/5 dark:bg-white/5 p-3 rounded-xl">
                                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Não consegue ler o QR? Digite o secret</p>
                                                <code class="text-[11px] font-mono break-all text-slate-700 dark:text-slate-300"><?= htmlspecialchars($totpSetup['secret']) ?></code>
                                            </div>
                                            <form method="POST" class="space-y-3">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="tab" value="perfil">
                                                <input type="hidden" name="action" value="confirm_totp">
                                                <input type="hidden" name="secret" value="<?= htmlspecialchars($totpSetup['secret']) ?>">
                                                <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus placeholder="000000" class="glass-input w-full text-center text-xl font-mono tracking-widest">
                                                <button type="submit" class="glass-btn !bg-emerald-600 !text-white w-full text-[10px] font-black uppercase">Confirmar e ativar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                                <script>
                                    QRCode.toCanvas(document.getElementById('totp-qr'), <?= json_encode($totpSetup['provisioning_uri']) ?>, { width: 192, margin: 1 });
                                </script>

                            <?php elseif ($totpEnabled): ?>
                                <!-- Já ativado: form pra desativar -->
                                <form method="POST" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="tab" value="perfil">
                                    <input type="hidden" name="action" value="disable_totp">
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Digite o código atual pra desativar</label>
                                        <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required placeholder="000000" class="glass-input w-full font-mono tracking-widest">
                                    </div>
                                    <button type="submit" class="glass-btn !bg-red-600 !text-white text-[10px] font-black uppercase whitespace-nowrap">Desativar 2FA</button>
                                </form>

                            <?php else: ?>
                                <!-- Desativado: botão pra começar setup -->
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="tab" value="perfil">
                                    <input type="hidden" name="action" value="setup_totp">
                                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] font-black uppercase">Ativar 2FA</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Sessões Ativas (Redis tracking) -->
                        <?php
                            $mySessions = \App\Auth::listMySessions();
                            $fmtSessTime = function($ts) {
                                if (!$ts) return '—';
                                $diff = time() - (int)$ts;
                                if ($diff < 60) return 'agora';
                                if ($diff < 3600) return floor($diff / 60) . ' min atrás';
                                if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
                                return floor($diff / 86400) . 'd atrás';
                            };
                            $shortenUA = function($ua) {
                                if (preg_match('/(Firefox|Chrome|Safari|Edge|Opera)\/(\d+)/i', $ua, $m)) {
                                    $browser = $m[1] . ' ' . $m[2];
                                    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
                                    elseif (stripos($ua, 'Mac OS') !== false) $os = 'macOS';
                                    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';
                                    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
                                    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
                                    else $os = '?';
                                    return "$browser · $os";
                                }
                                return strlen($ua) > 60 ? substr($ua, 0, 57) . '...' : $ua;
                            };
                        ?>
                        <div class="glass-panel border-slate-900/10 dark:border-white/5">
                            <div class="flex flex-wrap justify-between items-center gap-3 mb-4 border-b border-slate-900/10 dark:border-white/5 pb-3">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase">Sessões Ativas</h3>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Exibir
                                        <select id="sessions-limit" class="glass-input !py-1 !px-2 text-[10px] uppercase font-black">
                                            <option value="10" selected>10</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="all">Todas</option>
                                        </select>
                                    </label>
                                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        <span id="sessions-visible-count"><?= min(10, count($mySessions)) ?></span>/<?= count($mySessions) ?> sessão(ões)
                                    </span>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 mb-4">Cada navegador/dispositivo onde você está logado. Revogar uma sessão força logout naquela máquina imediatamente (denylist Redis).</p>

                            <?php if (empty($mySessions)): ?>
                                <p class="text-sm text-slate-500 italic py-4">Nenhuma sessão ativa detectada. Se você está logado agora, recarregue a página em alguns segundos pra o tracking iniciar.</p>
                            <?php else: ?>
                                <div id="sessions-list" class="space-y-2">
                                    <?php foreach ($mySessions as $sess):
                                        $ip = htmlspecialchars($sess['ip'] ?? '?');
                                        $ua = htmlspecialchars($shortenUA($sess['user_agent'] ?? '?'));
                                        $lastSeen = $fmtSessTime($sess['last_seen'] ?? 0);
                                        $loginAt = date('d/m/Y H:i', (int)($sess['login_at'] ?? 0));
                                        $hash = $sess['token_hash'] ?? '';
                                        ?>
                                        <div class="session-row flex items-center justify-between gap-3 p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">
                                            <div class="flex-1 min-w-0">
                                                <p class="font-bold text-xs text-slate-900 dark:text-white"><?= $ua ?></p>
                                                <p class="text-[10px] text-slate-500 font-mono">IP <?= $ip ?> · login em <?= htmlspecialchars($loginAt) ?></p>
                                                <p class="text-[10px] text-emerald-500 font-mono">Última atividade: <?= htmlspecialchars($lastSeen) ?></p>
                                            </div>
                                            <form method="POST" data-confirm-message="Encerrar esta sessão? Você será deslogado nessa máquina imediatamente." data-confirm-title="Encerrar sessão" data-confirm-text="Encerrar" data-confirm-variant="danger">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="tab" value="perfil">
                                                <input type="hidden" name="action" value="revoke_session">
                                                <input type="hidden" name="session_hash" value="<?= htmlspecialchars($hash) ?>">
                                                <button type="submit" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black bg-red-500/10 text-red-500" title="Encerrar essa sessão">Encerrar</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <script>
                                    (function () {
                                        const sel  = document.getElementById('sessions-limit');
                                        const rows = document.querySelectorAll('#sessions-list .session-row');
                                        const cnt  = document.getElementById('sessions-visible-count');
                                        if (!sel || !rows.length || !cnt) return;
                                        function applyLimit() {
                                            const v = sel.value;
                                            const max = (v === 'all') ? rows.length : parseInt(v, 10);
                                            let shown = 0;
                                            rows.forEach((row, idx) => {
                                                const visible = idx < max;
                                                row.style.display = visible ? '' : 'none';
                                                if (visible) shown++;
                                            });
                                            cnt.textContent = shown;
                                        }
                                        sel.addEventListener('change', applyLimit);
                                        applyLimit();
                                    })();
                                </script>
                            <?php endif; ?>
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

            // Abas que têm forms próprios (não usam o "Sincronizar Todas") — esconder o botão.
            const tabsWithOwnForms = ['usuarios', 'ntp', 'perfil', 'email', 'webhooks', 'updates', 'auditoria', 'api_tokens'];
            document.getElementById('btnSaveFloating').classList.toggle('hidden', tabsWithOwnForms.includes(tabId));

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
            div.className = "acl-row flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-3 rounded-2xl border border-slate-200 dark:border-white/5 animate-fade-in";
            div.dataset.ip = '';
            div.dataset.action = 'allow';
            div.innerHTML = `<input type="text" name="acl_ips[]" oninput="this.parentElement.dataset.ip = this.value.toLowerCase(); filterAclRows();" class="flex-1 bg-transparent text-slate-900 dark:text-white font-mono text-sm border-none"><select name="acl_actions[]" onchange="this.parentElement.dataset.action = this.value; updateAclCounts(); filterAclRows();" class="bg-slate-200 dark:bg-slate-800 text-[10px] font-black text-slate-900 dark:text-white uppercase rounded-xl border-none"><option value="allow">ALLOW</option><option value="deny">DENY</option><option value="refuse">REFUSE</option></select><button type="button" onclick="this.parentElement.remove(); updateAclCounts(); filterAclRows();" class="text-red-500/50 p-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>`;
            list.appendChild(div);
            updateAclCounts();
            filterAclRows();
        }

        // -- Filtros e contagens da aba Controle de Acesso (ACL) --
        function filterAclRows() {
            const searchEl = document.getElementById('aclSearch');
            const filterEl = document.getElementById('aclActionFilter');
            if (!searchEl || !filterEl) return; // aba não renderizada (não-admin)
            const q = (searchEl.value || '').trim().toLowerCase();
            const act = filterEl.value;
            const rows = document.querySelectorAll('.acl-row');
            let visible = 0;
            rows.forEach(row => {
                const matchQ = !q || (row.dataset.ip || '').includes(q);
                const matchAct = !act || row.dataset.action === act;
                const show = matchQ && matchAct;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const visEl = document.getElementById('aclCountVisible');
            const emptyEl = document.getElementById('aclEmpty');
            if (visEl) visEl.textContent = visible;
            if (emptyEl) emptyEl.classList.toggle('hidden', visible !== 0 || rows.length === 0);
        }

        function setAclFilter(action) {
            const el = document.getElementById('aclActionFilter');
            if (el) { el.value = action; filterAclRows(); }
        }

        function updateAclCounts() {
            const rows = document.querySelectorAll('.acl-row');
            let allow = 0, deny = 0, refuse = 0;
            rows.forEach(r => {
                if (r.dataset.action === 'allow') allow++;
                else if (r.dataset.action === 'deny') deny++;
                else if (r.dataset.action === 'refuse') refuse++;
            });
            const setIf = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            const total = allow + deny + refuse;
            setIf('aclCountAll', total);
            setIf('aclCountAllow', allow);
            setIf('aclCountDeny', deny);
            setIf('aclCountRefuse', refuse);
            // Atualiza também as opções do select (textos com contagem)
            const sel = document.getElementById('aclActionFilter');
            if (sel) {
                const opts = sel.querySelectorAll('option');
                if (opts[0]) opts[0].textContent = `TODAS (${total})`;
                if (opts[1]) opts[1].textContent = `ALLOW (${allow})`;
                if (opts[2]) opts[2].textContent = `DENY (${deny})`;
                if (opts[3]) opts[3].textContent = `REFUSE (${refuse})`;
            }
        }
        // Expostas globalmente pros onclick inline
        window.filterAclRows = filterAclRows;
        window.setAclFilter = setAclFilter;
        window.updateAclCounts = updateAclCounts;

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

        // ============================================================
        // Aba "Sistema / Atualizações" — self-update via /api/v1/updates/*
        // ============================================================
        (function () {
            const jwtMeta = document.querySelector('meta[name="api-jwt"]');
            const JWT = jwtMeta ? jwtMeta.content : '';
            const HEADERS = JWT ? { 'Authorization': 'Bearer ' + JWT, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' };

            const el = {
                current:        document.getElementById('updates-current'),
                latest:         document.getElementById('updates-latest'),
                published:      document.getElementById('updates-published'),
                banner:         document.getElementById('updates-banner'),
                action:         document.getElementById('updates-action'),
                ackWrapper:     document.getElementById('updates-ack-wrapper'),
                ackCheckbox:    document.getElementById('updates-ack-checkbox'),
                applyBtn:       document.getElementById('updates-apply-btn'),
                refreshBtn:     document.getElementById('updates-refresh-btn'),
                refreshIcon:    document.getElementById('updates-refresh-icon'),
                lastCheck:      document.getElementById('updates-last-check'),
                notesPanel:     document.getElementById('updates-notes-panel'),
                notes:          document.getElementById('updates-notes'),
                releaseUrl:     document.getElementById('updates-release-url'),
                consolePanel:   document.getElementById('updates-console-panel'),
                consoleEl:      document.getElementById('updates-console'),
                jobVersion:     document.getElementById('updates-job-version'),
                jobId:          document.getElementById('updates-job-id'),
                finalBanner:    document.getElementById('updates-final-banner'),
                backupsList:    document.getElementById('updates-backups-list'),
                backupsCount:   document.getElementById('updates-backups-count'),
                restoreModal:   document.getElementById('restore-confirm-modal'),
                restoreTs:      document.getElementById('restore-confirm-ts'),
                restoreInput:   document.getElementById('restore-confirm-input'),
                restoreCancel:  document.getElementById('restore-confirm-cancel'),
                restoreGo:      document.getElementById('restore-confirm-go'),
                modalSpinner:   document.getElementById('updates-modal-spinner'),
                modalFinalIcon: document.getElementById('updates-modal-final-icon'),
                modalTitle:     document.getElementById('updates-modal-title'),
                modalClose:     document.getElementById('updates-modal-close'),
                modalFooter:    document.getElementById('updates-modal-footer'),
            };
            if (!el.current) return;  // aba não renderizada (não-admin)

            let lastCheckAt = null;  // Date do último /check bem-sucedido

            let lastCheck = null;

            function setBanner(html, color) {
                el.banner.className = `mt-4 p-4 rounded-xl border ${color}`;
                el.banner.innerHTML = html;
                el.banner.classList.remove('hidden');
            }

            function clearBanner() {
                el.banner.classList.add('hidden');
            }

            async function checkUpdates() {
                el.refreshBtn.disabled = true;
                el.refreshBtn.classList.add('is-checking');
                if (el.lastCheck) el.lastCheck.textContent = 'Verificando…';
                try {
                    const resp = await fetch('/api/v1/updates/check', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    lastCheck = data;
                    lastCheckAt = new Date();
                    renderState(data);
                    updateLastCheckLabel();
                } catch (err) {
                    setBanner(
                        `<p class="text-sm text-red-700 dark:text-red-300"><strong>Erro:</strong> ${err.message}</p>`,
                        'bg-red-500/10 border-red-500/30'
                    );
                    if (el.lastCheck) el.lastCheck.textContent = 'Falha na verificação';
                } finally {
                    el.refreshBtn.classList.remove('is-checking');
                    el.refreshBtn.disabled = false;
                }
            }

            function updateLastCheckLabel() {
                if (!el.lastCheck || !lastCheckAt) return;
                const diff = Math.round((Date.now() - lastCheckAt.getTime()) / 1000);
                let when;
                if (diff < 5)        when = 'agora';
                else if (diff < 60)  when = `há ${diff}s`;
                else if (diff < 3600) when = `há ${Math.floor(diff/60)} min`;
                else                  when = `há ${Math.floor(diff/3600)}h`;
                el.lastCheck.textContent = `Última verificação ${when} · auto-check 6h`;
            }
            // Refresca o label a cada 30s pra ficar coerente sem polling do backend
            setInterval(updateLastCheckLabel, 30000);

            // ============================================================
            // Histórico de backups + restore manual
            // ============================================================
            async function loadBackups() {
                if (!el.backupsList) return;
                try {
                    const resp = await fetch('/api/v1/updates/backups', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    renderBackups(data.backups || []);
                } catch (err) {
                    el.backupsList.innerHTML = `<p class="text-xs text-red-500">Erro ao listar backups: ${err.message}</p>`;
                }
            }

            function renderBackups(backups) {
                if (!backups.length) {
                    el.backupsList.innerHTML = '<p class="text-xs text-slate-500 italic">Nenhum backup ainda. Aplicar um update gera o primeiro.</p>';
                    el.backupsCount.textContent = '0 backups';
                    return;
                }
                el.backupsCount.textContent = `${backups.length} backup${backups.length > 1 ? 's' : ''}`;
                el.backupsList.innerHTML = backups.map(b => {
                    const date = new Date(b.created_at * 1000);
                    const dateStr = date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    const sizeMB = (b.size_bytes / 1024 / 1024).toFixed(1);
                    const dbSizeMB = b.has_duckdb ? (b.duckdb_size_bytes / 1024 / 1024).toFixed(1) : null;
                    const dbTag = b.has_duckdb ? `<span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase">+ DuckDB ${dbSizeMB}MB</span>` : '';
                    const envTag = b.has_env ? `<span class="text-[9px] font-bold text-cyan-600 dark:text-cyan-400 uppercase">+ Env</span>` : '';
                    return `
                        <div class="flex items-center justify-between gap-3 p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">
                            <div class="flex-1 min-w-0">
                                <p class="font-mono text-xs font-bold text-slate-900 dark:text-white">${b.timestamp}</p>
                                <p class="text-[10px] text-slate-500">${dateStr} · ${sizeMB} MB de código</p>
                                <div class="flex gap-2 mt-1">${dbTag} ${envTag}</div>
                            </div>
                            <button type="button" data-ts="${b.timestamp}" class="restore-btn glass-btn !bg-amber-500/15 !text-amber-700 dark:!text-amber-400 !border-amber-500/30 text-[10px] uppercase font-black" title="Restaurar este backup">
                                ↺ Restaurar
                            </button>
                        </div>
                    `;
                }).join('');
                // Wire restore buttons
                el.backupsList.querySelectorAll('.restore-btn').forEach(btn => {
                    btn.addEventListener('click', () => openRestoreConfirm(btn.getAttribute('data-ts')));
                });
            }

            function openRestoreConfirm(ts) {
                el.restoreTs.textContent = ts;
                el.restoreInput.value = '';
                el.restoreGo.disabled = true;
                el.restoreModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                el.restoreInput.focus();
            }

            function closeRestoreConfirm() {
                el.restoreModal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            if (el.restoreInput) {
                el.restoreInput.addEventListener('input', () => {
                    el.restoreGo.disabled = el.restoreInput.value.trim() !== 'RESTAURAR';
                });
            }
            if (el.restoreCancel) el.restoreCancel.addEventListener('click', closeRestoreConfirm);
            if (el.restoreGo) {
                el.restoreGo.addEventListener('click', async () => {
                    const ts = el.restoreTs.textContent;
                    if (!ts) return;
                    el.restoreGo.disabled = true;
                    el.restoreCancel.disabled = true;
                    try {
                        const resp = await fetch('/api/v1/updates/restore', {
                            method: 'POST',
                            headers: HEADERS,
                            body: JSON.stringify({ timestamp: ts }),
                        });
                        const data = await resp.json();
                        if (!resp.ok) throw new Error(data.detail || `HTTP ${resp.status}`);
                        closeRestoreConfirm();
                        // Abre o mesmo modal de update pra acompanhar log + status
                        el.jobId.textContent = `job ${data.job_id} (restore)`;
                        el.jobVersion.textContent = `restore → ${ts}`;
                        el.consoleEl.textContent = '';
                        el.finalBanner.classList.add('hidden');
                        el.modalFinalIcon.classList.add('hidden');
                        el.modalSpinner.classList.remove('hidden');
                        el.modalTitle.textContent = 'Restaurando backup';
                        el.modalClose.classList.add('hidden');
                        el.modalFooter.classList.remove('hidden');
                        el.consolePanel.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                        window.__updateRunning = true;
                        streamLog(data.job_id);
                    } catch (err) {
                        await window.customAlert('Erro ao iniciar restore', err.message, 'error');
                        el.restoreGo.disabled = false;
                        el.restoreCancel.disabled = false;
                    }
                });
            }


            function renderState(d) {
                el.current.textContent = 'v' + (d.current || '?');

                if (d.error) {
                    el.latest.textContent = '—';
                    el.latest.className = 'text-lg font-black mt-1 font-mono text-slate-500';
                    el.published.textContent = '';
                    setBanner(
                        `<p class="text-xs text-amber-700 dark:text-amber-300"><strong>GitHub indisponível:</strong> ${d.error}</p>`,
                        'bg-amber-500/10 border-amber-500/30'
                    );
                    el.action.classList.add('hidden');
                    el.notesPanel.classList.add('hidden');
                    return;
                }

                el.latest.textContent = 'v' + (d.latest || '?');
                el.published.textContent = d.published_at ? ('publicada ' + formatDate(d.published_at)) : '';

                if (!d.has_update) {
                    el.latest.className = 'text-lg font-black mt-1 font-mono text-emerald-600 dark:text-emerald-400';
                    setBanner(
                        `<p class="text-sm text-emerald-700 dark:text-emerald-300"><strong>✓ Sistema atualizado</strong> — você está na última versão.</p>`,
                        'bg-emerald-500/10 border-emerald-500/30'
                    );
                    el.action.classList.add('hidden');
                    showNotes(d);
                    return;
                }

                // Update disponível
                el.latest.className = 'text-lg font-black mt-1 font-mono text-cyan-600 dark:text-cyan-400';
                if (d.is_major_bump) {
                    setBanner(
                        `<p class="text-sm text-red-700 dark:text-red-300"><strong>⚠ Major version bump (v${d.current} → v${d.latest})</strong> — pode incluir <em>breaking changes</em>. Leia o CHANGELOG antes de aplicar.</p>`,
                        'bg-red-500/10 border-red-500/30'
                    );
                    el.ackWrapper.classList.remove('hidden');
                    el.applyBtn.disabled = !el.ackCheckbox.checked;
                } else {
                    setBanner(
                        `<p class="text-sm text-blue-700 dark:text-blue-300"><strong>↑ Update disponível</strong> — v${d.current} → v${d.latest}</p>`,
                        'bg-blue-500/10 border-blue-500/30'
                    );
                    el.ackWrapper.classList.add('hidden');
                    el.applyBtn.disabled = false;
                }
                el.applyBtn.textContent = `Atualizar pra v${d.latest}`;
                el.action.classList.remove('hidden');
                showNotes(d);
            }

            function showNotes(d) {
                if (!d.body || !d.body.trim()) {
                    el.notesPanel.classList.add('hidden');
                    return;
                }
                el.notes.textContent = d.body;
                el.releaseUrl.href = d.release_url || '#';
                el.notesPanel.classList.remove('hidden');
            }

            function formatDate(iso) {
                try {
                    const d = new Date(iso);
                    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                } catch (_) { return iso; }
            }

            el.ackCheckbox.addEventListener('change', () => {
                if (lastCheck && lastCheck.is_major_bump) {
                    el.applyBtn.disabled = !el.ackCheckbox.checked;
                }
            });

            el.refreshBtn.addEventListener('click', checkUpdates);

            el.applyBtn.addEventListener('click', async () => {
                if (!lastCheck || !lastCheck.has_update) return;
                const ok = await window.customConfirm(
                    'Aplicar update do sistema',
                    `Aplicar update v${lastCheck.current} → v${lastCheck.latest}?\n\nO sistema será reiniciado e há rollback automático se o health check falhar.`,
                    { variant: 'warning', okLabel: `Atualizar pra v${lastCheck.latest}` }
                );
                if (!ok) return;

                el.applyBtn.disabled = true;
                el.refreshBtn.disabled = true;
                try {
                    const resp = await fetch('/api/v1/updates/apply', {
                        method: 'POST',
                        headers: HEADERS,
                        body: JSON.stringify({
                            version: lastCheck.latest,
                            acknowledge_breaking: lastCheck.is_major_bump && el.ackCheckbox.checked,
                        }),
                    });
                    const data = await resp.json();
                    if (!resp.ok) throw new Error(data.detail || `HTTP ${resp.status}`);

                    el.jobId.textContent = `job ${data.job_id}`;
                    el.jobVersion.textContent = `v${lastCheck.current} → v${lastCheck.latest}`;
                    el.consoleEl.textContent = '';
                    el.finalBanner.classList.add('hidden');
                    el.modalFinalIcon.classList.add('hidden');
                    el.modalSpinner.classList.remove('hidden');
                    el.modalTitle.textContent = 'Aplicando atualização';
                    el.modalClose.classList.add('hidden');  // não pode fechar enquanto roda
                    el.modalFooter.classList.remove('hidden');
                    el.consolePanel.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';  // trava scroll do fundo
                    // Bloqueia navegação acidental enquanto o update roda
                    window.__updateRunning = true;
                    el.action.classList.add('hidden');
                    streamLog(data.job_id);
                } catch (err) {
                    setBanner(
                        `<p class="text-sm text-red-700 dark:text-red-300"><strong>Erro ao iniciar update:</strong> ${err.message}</p>`,
                        'bg-red-500/10 border-red-500/30'
                    );
                    el.applyBtn.disabled = false;
                    el.refreshBtn.disabled = false;
                }
            });

            function streamLog(jobId) {
                // Apache proxy não passa Authorization em EventSource por default.
                // Alternativa: ?jwt=... como query param. Mas como o endpoint já está
                // protegido por require_capability e é admin, posso passar o JWT
                // via query (vai no log do Apache mas não é mais sensível que o cookie).
                // Solução robusta: usar fetch() + ReadableStream (mantém Authorization
                // header funcionando).
                fetch('/api/v1/updates/log/' + jobId, {
                    headers: { 'Authorization': 'Bearer ' + JWT, 'Accept': 'text/event-stream' },
                }).then(async (resp) => {
                    if (!resp.ok || !resp.body) throw new Error(`HTTP ${resp.status}`);
                    const reader = resp.body.getReader();
                    const decoder = new TextDecoder();
                    let buf = '';
                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;
                        buf += decoder.decode(value, { stream: true });
                        // Processa eventos SSE (separados por \n\n)
                        let idx;
                        while ((idx = buf.indexOf('\n\n')) >= 0) {
                            const raw = buf.slice(0, idx);
                            buf = buf.slice(idx + 2);
                            handleSseEvent(raw);
                        }
                    }
                }).catch((err) => {
                    appendLine(`\n[!] Erro no stream do log: ${err.message}\n`);
                });
            }

            function handleSseEvent(raw) {
                let event = null;
                const dataLines = [];
                for (const line of raw.split('\n')) {
                    if (line.startsWith(':')) continue;  // comment (heartbeat)
                    if (line.startsWith('event: ')) event = line.slice(7).trim();
                    else if (line.startsWith('data: ')) dataLines.push(line.slice(6));
                }
                const data = dataLines.join('\n');
                if (event === 'done') {
                    try {
                        const final = JSON.parse(data);
                        renderFinal(final);
                    } catch (_) { /* ignore */ }
                } else if (event === 'error') {
                    appendLine(`\n[!] Erro: ${data}\n`);
                } else if (data) {
                    appendLine(data);
                }
            }

            function appendLine(text) {
                el.consoleEl.textContent += text;
                el.consoleEl.scrollTop = el.consoleEl.scrollHeight;
            }

            function renderFinal(state) {
                const status = state.status || 'unknown';
                const styles = {
                    succeeded: {
                        title: '✓ Update aplicado',
                        msg:   `<p class="text-sm text-emerald-700 dark:text-emerald-300"><strong>Update aplicado com sucesso</strong> — sistema agora está em v${state.to_version || '?'}.</p>`,
                        bannerColor: 'bg-emerald-500/10 border-emerald-500/30',
                        iconBg: 'bg-emerald-500/20 text-emerald-500',
                        iconSvg: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                        ctaLabel: 'Recarregar página',
                        ctaClass: '!bg-emerald-600 !text-white',
                    },
                    rolled_back: {
                        title: '⚠ Rollback executado',
                        msg:   `<p class="text-sm text-amber-700 dark:text-amber-300"><strong>Rollback automático executado</strong> — o health check pós-restart falhou, sistema voltou pra v${state.from_version || '?'}. Veja o log acima.</p>`,
                        bannerColor: 'bg-amber-500/10 border-amber-500/30',
                        iconBg: 'bg-amber-500/20 text-amber-500',
                        iconSvg: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>',
                        ctaLabel: 'Fechar',
                        ctaClass: '!bg-amber-600 !text-white',
                    },
                    rollback_failed: {
                        title: '✗ ROLLBACK FALHOU',
                        msg:   '<p class="text-sm text-red-700 dark:text-red-300"><strong>ROLLBACK FALHOU — estado inconsistente.</strong> Intervenção manual via SSH necessária. Veja o log acima.</p>',
                        bannerColor: 'bg-red-500/10 border-red-500/30',
                        iconBg: 'bg-red-500/20 text-red-500',
                        iconSvg: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.732 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>',
                        ctaLabel: 'Fechar',
                        ctaClass: '!bg-red-600 !text-white',
                    },
                    failed: {
                        title: '✗ Update falhou',
                        msg:   '<p class="text-sm text-red-700 dark:text-red-300"><strong>Update falhou</strong> — veja o log acima pra detalhes.</p>',
                        bannerColor: 'bg-red-500/10 border-red-500/30',
                        iconBg: 'bg-red-500/20 text-red-500',
                        iconSvg: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
                        ctaLabel: 'Fechar',
                        ctaClass: '!bg-red-600 !text-white',
                    },
                };
                const s = styles[status] || styles.failed;

                el.finalBanner.className = 'p-4 rounded-xl border ' + s.bannerColor;
                el.finalBanner.innerHTML = s.msg;
                el.finalBanner.classList.remove('hidden');

                // Atualiza header do modal
                el.modalSpinner.classList.add('hidden');
                el.modalFinalIcon.className = 'shrink-0 w-8 h-8 rounded-full flex items-center justify-center ' + s.iconBg;
                el.modalFinalIcon.innerHTML = s.iconSvg;
                el.modalFinalIcon.classList.remove('hidden');
                el.modalTitle.textContent = s.title;

                // Libera fechamento
                el.modalClose.classList.remove('hidden');
                window.__updateRunning = false;

                // Footer vira CTA — succeeded recarrega; demais fecham modal
                const isSuccess = status === 'succeeded';
                el.modalFooter.innerHTML = `
                    <div class="flex justify-end gap-2">
                        ${isSuccess ? '' : '<button type="button" id="updates-modal-cancel-btn" class="glass-btn text-[10px] uppercase font-black">Fechar</button>'}
                        <button type="button" id="updates-modal-cta" class="glass-btn ${s.ctaClass} text-[10px] uppercase font-black">${s.ctaLabel}</button>
                    </div>
                `;
                document.getElementById('updates-modal-cta').addEventListener('click', () => {
                    if (isSuccess) location.reload();
                    else closeUpdateModal();
                });
                const cancelBtn = document.getElementById('updates-modal-cancel-btn');
                if (cancelBtn) cancelBtn.addEventListener('click', closeUpdateModal);
            }

            function closeUpdateModal() {
                if (window.__updateRunning) return;  // protege contra fechar no meio
                el.consolePanel.classList.add('hidden');
                document.body.style.overflow = '';
                // Re-verifica status após fechar (pode ter mudado VERSION)
                checkUpdates();
            }

            // Botão X no header do modal — só funciona quando update terminou
            el.modalClose.addEventListener('click', closeUpdateModal);
            // ESC pra fechar (também só quando terminou)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !el.consolePanel.classList.contains('hidden') && !window.__updateRunning) {
                    closeUpdateModal();
                }
            });
            // beforeunload pra avisar se tentar fechar aba durante update
            window.addEventListener('beforeunload', (e) => {
                if (window.__updateRunning) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Check inicial quando a aba abre (lazy — só quando user clica na aba)
            let firstCheckDone = false;
            const tabUpdates = document.getElementById('tab-updates');
            const observer = new MutationObserver(() => {
                if (!firstCheckDone && tabUpdates.classList.contains('active')) {
                    firstCheckDone = true;
                    checkUpdates();
                    loadBackups();
                }
            });
            observer.observe(tabUpdates, { attributes: true, attributeFilter: ['class'] });
            // Se a aba já é a ativa no load (via ?tab=updates), check imediato
            if (tabUpdates.classList.contains('active')) {
                firstCheckDone = true;
                checkUpdates();
                loadBackups();
            }
        })();

        // ============================================================
        // Aba "Auditoria" — trilha de updates/restores
        // ============================================================
        (function () {
            const jwtMeta = document.querySelector('meta[name="api-jwt"]');
            const JWT = jwtMeta ? jwtMeta.content : '';
            const HEADERS = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};

            const tbody = document.getElementById('audit-tbody');
            const summary = document.getElementById('audit-summary');
            const refreshBtn = document.getElementById('audit-refresh-btn');
            if (!tbody) return;  // aba não renderizada

            const STATUS_BADGE = {
                running:          { label: 'Running',          color: 'bg-blue-500/15 text-blue-600 dark:text-blue-300 border-blue-500/30' },
                succeeded:        { label: '✓ Succeeded',      color: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30' },
                rolled_back:     { label: '⚠ Rolled back',    color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                rollback_failed:{ label: '✗ Rollback failed', color: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
                failed:          { label: '✗ Failed',          color: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
            };

            function fmtDate(ts) {
                if (!ts) return '—';
                try { return new Date(ts * 1000).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }); }
                catch (_) { return String(ts); }
            }
            function fmtDuration(secs) {
                if (secs === null || secs === undefined) return '—';
                if (secs < 60) return `${secs}s`;
                if (secs < 3600) return `${Math.floor(secs/60)}m${secs%60}s`;
                return `${Math.floor(secs/3600)}h${Math.floor((secs%3600)/60)}m`;
            }

            async function load() {
                refreshBtn.disabled = true;
                tbody.innerHTML = '<tr><td colspan="7" class="py-6 text-center text-slate-500 italic text-xs">Carregando…</td></tr>';
                try {
                    const resp = await fetch('/api/v1/audit/updates?limit=50', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    render(data.audit || []);
                } catch (err) {
                    tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500 text-xs">Erro: ${err.message}</td></tr>`;
                } finally {
                    refreshBtn.disabled = false;
                }
            }

            function render(entries) {
                if (!entries.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="py-6 text-center text-slate-500 italic text-xs">Nenhuma operação registrada ainda.</td></tr>';
                    summary.textContent = '';
                    return;
                }
                tbody.innerHTML = entries.map(e => {
                    const badge = STATUS_BADGE[e.status] || { label: e.status, color: 'bg-slate-500/15 text-slate-500 border-slate-500/30' };
                    const versionCell = e.kind === 'restore'
                        ? `restore <span class="font-mono">${e.backup_timestamp || '?'}</span>`
                        : `v${e.from_version || '?'} → v${e.to_version || '?'}` + (e.acknowledge_breaking ? ' ⚠' : '');
                    const kindBadge = e.kind === 'restore'
                        ? '<span class="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">↺ Restore</span>'
                        : '<span class="text-[9px] font-black uppercase tracking-widest text-cyan-600 dark:text-cyan-400">↑ Update</span>';
                    return `
                        <tr class="border-b border-slate-200/50 dark:border-white/5 hover:bg-slate-50/40 dark:hover:bg-white/5">
                            <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 whitespace-nowrap">${fmtDate(e.started_at)}</td>
                            <td class="py-2.5 px-2">${kindBadge}</td>
                            <td class="py-2.5 px-2 font-mono">${e.username || '?'}</td>
                            <td class="py-2.5 px-2 text-slate-500 font-mono text-[10px]">${e.ip || '?'}</td>
                            <td class="py-2.5 px-2 font-mono text-[11px]">${versionCell}</td>
                            <td class="py-2.5 px-2"><span class="inline-block px-2 py-0.5 rounded-md border text-[10px] font-black uppercase tracking-widest ${badge.color}">${badge.label}</span></td>
                            <td class="py-2.5 px-2 text-right text-slate-500 font-mono text-[10px]">${fmtDuration(e.duration_seconds)}</td>
                        </tr>
                    `;
                }).join('');
                summary.textContent = `${entries.length} operação(ões) listadas`;
            }

            refreshBtn.addEventListener('click', load);

            // Carrega quando a aba abrir
            const tabAudit = document.getElementById('tab-auditoria');
            let firstLoad = false;
            const obs = new MutationObserver(() => {
                if (!firstLoad && tabAudit.classList.contains('active')) {
                    firstLoad = true;
                    load();
                }
            });
            obs.observe(tabAudit, { attributes: true, attributeFilter: ['class'] });
            if (tabAudit.classList.contains('active')) {
                firstLoad = true;
                load();
            }
        })();

        // ============================================================
        // Aba "API Tokens" — multi-host master ↔ agent auth
        // ============================================================
        (function () {
            const jwtMeta = document.querySelector('meta[name="api-jwt"]');
            const JWT = jwtMeta ? jwtMeta.content : '';
            const HEADERS = JWT ? { 'Authorization': 'Bearer ' + JWT, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' };

            const list      = document.getElementById('api-tokens-list');
            const newBtn    = document.getElementById('api-token-new-btn');
            const newModal  = document.getElementById('api-token-new-modal');
            const labelInp  = document.getElementById('api-token-label-input');
            const cancelBtn = document.getElementById('api-token-cancel-btn');
            const createBtn = document.getElementById('api-token-create-btn');
            const revealModal = document.getElementById('api-token-reveal-modal');
            const revealValue = document.getElementById('api-token-reveal-value');
            const copyBtn   = document.getElementById('api-token-copy-btn');
            const revealCloseBtn = document.getElementById('api-token-reveal-close-btn');
            if (!list) return;

            function fmtDate(iso) {
                if (!iso) return '—';
                try { return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
                catch (_) { return iso; }
            }

            async function load() {
                list.innerHTML = '<p class="text-xs text-slate-500 italic">Carregando…</p>';
                try {
                    const resp = await fetch('/api/v1/api-tokens', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    render(data.tokens || []);
                } catch (err) {
                    list.innerHTML = `<p class="text-xs text-red-500">Erro: ${err.message}</p>`;
                }
            }

            function render(tokens) {
                if (!tokens.length) {
                    list.innerHTML = '<p class="text-xs text-slate-500 italic py-4">Nenhum token criado ainda. Clique em "Gerar novo token" pra começar.</p>';
                    return;
                }
                list.innerHTML = tokens.map(t => `
                    <div class="flex items-center justify-between gap-3 p-3 bg-slate-900/5 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-mono text-xs font-bold text-slate-900 dark:text-white">${t.label}</p>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">#${t.id}</span>
                            </div>
                            <p class="text-[10px] text-slate-500">
                                Criado ${fmtDate(t.created_at)}
                                ${t.last_used_at ? ` · Último uso ${fmtDate(t.last_used_at)}` : ' · Nunca usado'}
                                ${t.last_used_ip ? ` (${t.last_used_ip})` : ''}
                            </p>
                        </div>
                        <button type="button" data-id="${t.id}" data-label="${t.label}" class="revoke-btn glass-btn !py-1 !px-3 text-[10px] uppercase font-black bg-red-500/15 text-red-600 dark:text-red-400">Revogar</button>
                    </div>
                `).join('');
                list.querySelectorAll('.revoke-btn').forEach(btn => {
                    btn.addEventListener('click', () => revokeToken(btn.getAttribute('data-id'), btn.getAttribute('data-label')));
                });
            }

            async function revokeToken(id, label) {
                const ok = await window.customConfirm(
                    'Revogar API token',
                    `Revogar token "${label}"? Master que usa este token vai perder acesso imediatamente.`,
                    { variant: 'danger', okLabel: 'Revogar' }
                );
                if (!ok) return;
                try {
                    const resp = await fetch('/api/v1/api-tokens/' + id, { method: 'DELETE', headers: HEADERS });
                    if (!resp.ok && resp.status !== 204) throw new Error(`HTTP ${resp.status}`);
                    load();
                } catch (err) {
                    await window.customAlert('Erro ao revogar', err.message, 'error');
                }
            }

            function openNew() {
                labelInp.value = '';
                createBtn.disabled = true;
                newModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                labelInp.focus();
            }
            function closeNew() {
                newModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            function closeReveal() {
                revealModal.classList.add('hidden');
                document.body.style.overflow = '';
                revealValue.textContent = '';  // clear from DOM
                load();
            }

            newBtn.addEventListener('click', openNew);
            cancelBtn.addEventListener('click', closeNew);
            labelInp.addEventListener('input', () => {
                createBtn.disabled = labelInp.value.trim().length < 1;
            });
            createBtn.addEventListener('click', async () => {
                const label = labelInp.value.trim();
                if (!label) return;
                createBtn.disabled = true;
                cancelBtn.disabled = true;
                try {
                    const resp = await fetch('/api/v1/api-tokens', {
                        method: 'POST',
                        headers: HEADERS,
                        body: JSON.stringify({ label }),
                    });
                    const data = await resp.json();
                    if (!resp.ok) throw new Error(data.detail || `HTTP ${resp.status}`);
                    closeNew();
                    // Mostra o token
                    revealValue.textContent = data.raw_token;
                    revealModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                } catch (err) {
                    await window.customAlert('Erro ao gerar token', err.message, 'error');
                } finally {
                    createBtn.disabled = false;
                    cancelBtn.disabled = false;
                }
            });
            copyBtn.addEventListener('click', () => {
                const txt = revealValue.textContent;
                if (!txt) return;
                navigator.clipboard.writeText(txt).then(() => {
                    copyBtn.textContent = '✓ Copiado!';
                    setTimeout(() => copyBtn.textContent = 'Copiar pra área de transferência', 2000);
                });
            });
            revealCloseBtn.addEventListener('click', closeReveal);

            // Lazy-load ao abrir aba
            const tabApiTokens = document.getElementById('tab-api_tokens');
            let firstApiTokenLoad = false;
            const obsApiTokens = new MutationObserver(() => {
                if (!firstApiTokenLoad && tabApiTokens.classList.contains('active')) {
                    firstApiTokenLoad = true;
                    load();
                }
            });
            obsApiTokens.observe(tabApiTokens, { attributes: true, attributeFilter: ['class'] });
            if (tabApiTokens.classList.contains('active')) {
                firstApiTokenLoad = true;
                load();
            }
        })();

        // --- Aba Gestão de Usuários: filtros client-side ---
        function filterUsers() {
            const qEl = document.getElementById('filter-search');
            const rEl = document.getElementById('filter-role');
            const sEl = document.getElementById('filter-status');
            if (!qEl) return; // aba inexistente (não-admin)
            const q = (qEl.value || '').trim().toLowerCase();
            const role = rEl.value;
            const status = sEl.value;
            let visible = 0;
            document.querySelectorAll('.user-row').forEach(row => {
                const matchQ = !q || row.dataset.username.includes(q) || row.dataset.email.includes(q);
                const matchRole = !role || row.dataset.role === role;
                const matchStatus = !status || row.dataset.status === status;
                const show = matchQ && matchRole && matchStatus;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const visEl = document.getElementById('users-count-visible');
            const empty = document.getElementById('users-empty');
            if (visEl) visEl.textContent = visible;
            if (empty) empty.classList.toggle('hidden', visible !== 0);
        }

        // Botões "Gerar Self-Signed" / "Upload PEM" — confirm via customConfirm
        // quando cert atual é Let's Encrypt (evita sobrescrever cert público).
        document.querySelectorAll('[data-tls-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.getAttribute('data-tls-action');
                const isLE = btn.getAttribute('data-is-le') === '1';
                const modalId = action === 'generate' ? 'tls-generate-modal' : 'tls-upload-modal';
                if (isLE && window.customConfirm) {
                    const ok = await window.customConfirm(
                        'Cert Let\'s Encrypt ativo',
                        'O certificado atual é Let\'s Encrypt válido publicamente. Sobrescrever vai quebrar DoT/DoH pros clientes que validam o cert. Continuar?',
                        { variant: 'danger', okLabel: 'Sobrescrever' }
                    );
                    if (!ok) return;
                }
                document.getElementById(modalId).classList.remove('hidden');
            });
        });

        // Handler genérico pra botões com data-confirm. Intercepta o submit/click,
        // abre customConfirm, e se OK re-dispara o click com a flag _confirmed
        // (que evita loop infinito).
        document.querySelectorAll('[data-confirm]').forEach(btn => {
            btn.addEventListener('click', async function (e) {
                if (this.dataset._confirmed === '1') {
                    // Segundo click — passa direto (já confirmado)
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                // Pré-side-effect (ex: setIfaceName antes do submit)
                const pre = this.getAttribute('data-pre-click');
                if (pre) {
                    try { eval(pre); } catch (err) { console.error(err); }
                }
                const ok = await window.customConfirm(
                    this.getAttribute('data-confirm-title') || 'Confirmar',
                    this.getAttribute('data-confirm') || '',
                    {
                        variant: this.getAttribute('data-confirm-variant') || 'warning',
                        okLabel: this.getAttribute('data-confirm-ok-label') || 'Confirmar',
                    }
                );
                if (ok) {
                    this.dataset._confirmed = '1';
                    this.click();
                }
            }, true);  // capture phase pra interceptar antes dos outros handlers
        });
    </script>

    <?php include 'includes/custom_modals.php'; ?>
</body>

</html>