<?php
/**
 * Página exibida quando o sistema é acessado antes do install.sh ter
 * concluído (data/.installed ausente OU sem usuários no DuckDB).
 *
 * Substitui o antigo wizard PHP (setup.php), removido em v2.2.x junto
 * com o tear-down do MariaDB. O bootstrap do admin agora é feito
 * exclusivamente pelo install.sh / api_service/tools/create_admin.py.
 *
 * Diagnóstico inline: mostra se o problema é flag ausente, api_service
 * offline ou DuckDB sem users.
 */

// Diagnóstico em tempo real pra ajudar o admin a entender em qual etapa parou.
$installedFlag = file_exists(__DIR__ . '/data/.installed');
$apiStatus = 'unknown';
$apiUsersExist = null;
$ch = curl_init('http://127.0.0.1:8001/api/v1/healthz');
if ($ch !== false) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 2,
    ]);
    $resp = curl_exec($ch);
    $apiStatus = ($resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) ? 'online' : 'offline';
    curl_close($ch);
}
if ($apiStatus === 'online') {
    $ch2 = curl_init('http://127.0.0.1:8001/api/v1/users/exists');
    if ($ch2 !== false) {
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 2,
        ]);
        $r = curl_exec($ch2);
        if ($r !== false && curl_getinfo($ch2, CURLINFO_HTTP_CODE) === 200) {
            $j = json_decode($r, true);
            $apiUsersExist = is_array($j) && !empty($j['exists']);
        }
        curl_close($ch2);
    }
}

// Diagnóstico narrativo
$diag = '';
if ($apiStatus === 'offline') {
    $diag = 'API offline';
    $diagDetail = $installedFlag
        ? 'data/.installed existe mas unbound-dashboard-api.service não responde em 127.0.0.1:8001. Sistema provavelmente foi instalado mas o serviço caiu — reinicie e tente de novo.'
        : 'data/.installed ausente E api_service offline. Possível install.sh interrompido antes do fim.';
} elseif ($apiUsersExist === false) {
    $diag = 'API online, DuckDB sem users';
    $diagDetail = 'api_service responde mas a tabela users está vazia. O install.sh provavelmente chegou na Etapa 7 mas falhou no create_admin.py da Etapa 8. Re-rode o install.sh ou use tools/create_admin.py manualmente.';
} elseif ($apiUsersExist === true) {
    $diag = 'API e users OK — mas você está aqui?';
    $diagDetail = 'Há users no DuckDB. Você não deveria estar nesta página. Possível cache do browser ou redirect infinito — limpe cookies do site e tente novamente.';
} else {
    $diag = 'Estado indeterminado';
    $diagDetail = 'Não foi possível diagnosticar via /api/v1/users/exists (HTTP não-200). Cheque journalctl -u unbound-dashboard-api.';
}

http_response_code(503);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema não instalado — Unbound Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0b1120; color: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 24px; }
        .card { max-width: 720px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 40px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4); }
        h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 8px; color: #f59e0b; }
        h2 { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 24px 0 8px; }
        p { line-height: 1.6; color: #cbd5e1; margin: 8px 0; }
        code { background: rgba(15, 23, 42, 0.8); padding: 2px 8px; border-radius: 4px; font-size: 0.9em; color: #67e8f9; }
        pre { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 16px; overflow-x: auto; font-size: 0.85rem; color: #e2e8f0; }
        .muted { color: #64748b; font-size: 0.85rem; }
        .diag { background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 16px; margin: 20px 0; }
        .diag-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; color: #fbbf24; margin: 0 0 6px; }
        .check { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; margin: 4px 0; }
        .check-ok { color: #10b981; }
        .check-bad { color: #ef4444; }
        .check-unk { color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚠ Sistema ainda não instalado</h1>
        <p>O Unbound Dashboard não detectou uma instalação concluída neste servidor.</p>

        <div class="diag">
            <p class="diag-title">📊 Diagnóstico automático</p>
            <p class="check"><span class="<?= $installedFlag ? 'check-ok' : 'check-bad' ?>"><?= $installedFlag ? '✓' : '✗' ?></span>
                <code>data/.installed</code> <?= $installedFlag ? 'existe' : 'ausente' ?></p>
            <p class="check"><span class="<?= $apiStatus === 'online' ? 'check-ok' : 'check-bad' ?>"><?= $apiStatus === 'online' ? '✓' : '✗' ?></span>
                <code>unbound-dashboard-api.service</code> em 127.0.0.1:8001: <strong><?= htmlspecialchars($apiStatus) ?></strong></p>
            <p class="check"><span class="<?= $apiUsersExist === true ? 'check-ok' : ($apiUsersExist === false ? 'check-bad' : 'check-unk') ?>"><?= $apiUsersExist === true ? '✓' : ($apiUsersExist === false ? '✗' : '?') ?></span>
                Tabela <code>users</code> no DuckDB: <strong><?= $apiUsersExist === true ? 'tem usuários' : ($apiUsersExist === false ? 'vazia' : 'não verificada') ?></strong></p>
            <p style="margin-top: 12px; color: #fbbf24; font-weight: 600;"><?= htmlspecialchars($diag) ?></p>
            <p class="muted" style="margin-top: 4px;"><?= htmlspecialchars($diagDetail) ?></p>
        </div>

        <h2>Como resolver</h2>
        <?php if ($apiStatus === 'offline'): ?>
            <p>O serviço FastAPI não está respondendo. Verifique:</p>
            <pre>sudo systemctl status unbound-dashboard-api
sudo journalctl -u unbound-dashboard-api -n 50 --no-pager
sudo systemctl restart unbound-dashboard-api</pre>
        <?php elseif ($apiUsersExist === false): ?>
            <p>API funcionando mas <code>users</code> vazio. Crie o admin manualmente:</p>
            <pre>cd /var/www/html/unbound-dashboard/api_service
sudo systemctl stop unbound-dashboard-api
sudo -u www-data env \
    ADMIN_USERNAME=admin \
    ADMIN_EMAIL=admin@empresa.com \
    ADMIN_PASSWORD='senhaSegura' \
    PYTHONPATH=$(pwd) \
    .venv/bin/python tools/create_admin.py
sudo systemctl start unbound-dashboard-api</pre>
            <p class="muted">Depois recarregue esta página.</p>
        <?php else: ?>
            <p>Instale via pacote oficial:</p>
            <pre>tar xzf unbound-dashboard-v&lt;X.Y.Z&gt;.tar.gz
cd unbound-dashboard-v&lt;X.Y.Z&gt;
sudo bash install.sh</pre>
            <p>Para modo não-interativo:</p>
            <pre>ADMIN_USERNAME=admin ADMIN_PASSWORD='senhaSegura' \
    sudo -E bash install.sh</pre>
        <?php endif; ?>

        <h2>Comandos de diagnóstico rápido</h2>
        <pre>ls -la /var/www/html/unbound-dashboard/data/.installed
sudo systemctl status unbound-dashboard-api
curl -sf http://127.0.0.1:8001/api/v1/users/exists
sudo journalctl -u unbound-dashboard-api -n 30 --no-pager</pre>
        <p class="muted">Logs e troubleshooting em <code>docs/TROUBLESHOOTING.md</code>.</p>
    </div>
</body>
</html>
