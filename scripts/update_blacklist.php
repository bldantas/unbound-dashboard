<?php
/**
 * update_blacklist — baixa StevenBlack/Hagezi e ingere no DuckDB via FastAPI.
 * Migrado de PDO/MariaDB pra ApiClient em 2026-05-04.
 *
 * Invocado em background por api/service_control.php (action=update_blacklist),
 * que passa o JWT via env var API_JWT.
 */

require_once dirname(__DIR__) . '/src/ApiClient.php';

use App\ApiClient;

function setProgress(string $status, int $progress): void {
    $file = dirname(__DIR__) . '/src/data/tmp/blacklist_progress.json';
    file_put_contents($file, json_encode(['status' => $status, 'progress' => $progress]));
}

setProgress('downloading', 0);

$jwt = getenv('API_JWT') ?: '';
if ($jwt === '') {
    setProgress('idle', 0);
    fwrite(STDERR, "[-] API_JWT não fornecido — abort.\n");
    exit(1);
}

// Lê fonte da blocklist via /api/v1/exports/settings
$source = 'stevenblack';
$resp = ApiClient::get('/api/v1/exports/settings', $jwt);
if ($resp['ok'] && is_array($resp['data'])) {
    foreach ($resp['data'] as $row) {
        if (($row['setting_key'] ?? '') === 'blacklist_source') {
            $source = $row['setting_value'] ?? 'stevenblack';
            break;
        }
    }
}

if ($source === 'hagezi_normal') {
    $url  = 'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/normal.txt';
    $name = 'Hagezi Normal';
} elseif ($source === 'hagezi_pro') {
    $url  = 'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/pro.txt';
    $name = 'Hagezi Pro';
} else {
    $url  = 'https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts';
    $name = 'StevenBlack Unified';
}

echo "[*] Iniciando atualização da Blacklist ($name)...\n";

$context = stream_context_create([
    'http' => ['method' => 'GET', 'header' => "User-Agent: UnboundDashboard/1.0\r\n"],
]);
$data = @file_get_contents($url, false, $context);

if ($data === false) {
    setProgress('idle', 0);
    fwrite(STDERR, "[-] Erro ao baixar blacklist via file_get_contents.\n");
    exit(1);
}

setProgress('parsing', 20);
echo "[*] Download concluído. Parseando domínios...\n";

$lines = explode("\n", $data);
$domains = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (preg_match('/^(?:0\.0\.0\.0|127\.0\.0\.1)\s+([a-zA-Z0-9.-]+)/', $line, $matches)) {
        $domain = trim($matches[1]);
        if (!in_array($domain, ['localhost', 'localhost.localdomain', 'local', '0.0.0.0', 'broadcasthost'], true)) {
            $domains[] = $domain;
        }
    }
}
$domains = array_unique($domains);
$total = count($domains);

echo "[*] Encontrados $total domínios. Gravando via FastAPI...\n";

// 1. Limpa categoria Malware/Adware (preserva Judicial e outras)
ApiClient::post('/api/v1/blocklist/clear-category', $jwt, ['category' => 'Malware/Adware']);

// 2. Bulk insert em chunks
setProgress('inserting', 30);
$chunks = array_chunk($domains, 2000);
$totalChunks = count($chunks);
$chunkIndex = 0;
$insertedCount = 0;

foreach ($chunks as $chunk) {
    $payload = array_map(static function (string $d): array {
        return ['domain' => $d, 'category' => 'Malware/Adware', 'severity' => 'ALTO'];
    }, $chunk);
    $resp = ApiClient::post('/api/v1/blocklist/bulk-insert', $jwt, $payload);
    if ($resp['ok'] && isset($resp['data']['inserted'])) {
        $insertedCount += (int) $resp['data']['inserted'];
    }
    $chunkIndex++;
    $pct = 30 + (int) round(($chunkIndex / $totalChunks) * 65);
    setProgress('inserting', $pct);

    if ($insertedCount > 0 && $insertedCount % 20000 === 0) {
        echo "[*] Inseridos $insertedCount / $total ...\n";
    }
}

// 3. Atualiza cache de contagens via FastAPI
$countsFile = dirname(__DIR__) . '/data/blocklist_counts.json';
$resp = ApiClient::get('/api/v1/blocklist/counts', $jwt);
if ($resp['ok'] && is_array($resp['data'])) {
    file_put_contents($countsFile, json_encode([
        'adware'     => (int) ($resp['data']['adware'] ?? 0),
        'phishing'   => (int) ($resp['data']['phishing'] ?? 0),
        'judicial'   => (int) ($resp['data']['judicial'] ?? 0),
        'updated_at' => time(),
    ]));
}

setProgress('done', 100);
echo "[+] Sucesso! Blacklist atualizada com $insertedCount domínios.\n";
