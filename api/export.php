<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) {
    http_response_code(403);
    exit('Acesso negado.');
}

// ─── RESTORE (POST) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    restoreConfigBackup();
    exit;
}

// ─── EXPORT (GET) ──────────────────────────────────────────────────
$type = $_GET['type'] ?? '';
$range = $_GET['range'] ?? '24h';

// Whitelist validation
$validTypes = ['logs', 'stats', 'system_log', 'config_backup', 'blacklist'];
$validRanges = ['24h', '7d', '30d', 'all'];

if (!in_array($type, $validTypes)) {
    http_response_code(400);
    exit('Tipo de exportação inválido.');
}
if (!in_array($range, $validRanges)) {
    http_response_code(400);
    exit('Período inválido.');
}

$dateStr = date('Y-m-d_His');

switch ($type) {
    case 'logs':
        exportQueryLogs($range, $dateStr);
        break;
    case 'stats':
        exportStats($dateStr);
        break;
    case 'system_log':
        exportSystemLog($dateStr);
        break;
    case 'config_backup':
        exportConfigBackup($dateStr);
        break;
    case 'blacklist':
        exportBlacklist($dateStr);
        break;
}

// ─── 1. LOGS DE CONSULTAS DNS (CSV) ────────────────────────────────
function exportQueryLogs(string $range, string $dateStr) {
    $rangeMap = [
        '24h' => 86400,
        '7d'  => 604800,
        '30d' => 2592000,
        'all' => 0
    ];

    $filename = "dns_queries_{$range}_{$dateStr}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Data/Hora', 'IP Cliente', 'Domínio', 'Tipo', 'Ação'], ';');

    $since = $rangeMap[$range] > 0 ? time() - $rangeMap[$range] : 0;
    $jwt = $_SESSION['api_jwt'] ?? '';

    $resp = \App\ApiClient::get("/api/v1/exports/query-logs?since={$since}", $jwt);
    $rows = ($resp['ok'] && is_array($resp['data'])) ? $resp['data'] : [];

    foreach ($rows as $row) {
        fputcsv($output, [
            date('d/m/Y H:i:s', (int) $row['timestamp']),
            $row['client_ip'],
            $row['domain'],
            $row['query_type'],
            $row['action'] === 'blocked' ? 'Bloqueado' : 'Resolvido'
        ], ';');
    }

    fclose($output);
    exit;
}

// ─── 2. RELATÓRIO DE ESTATÍSTICAS (JSON) ───────────────────────────
function exportStats(string $dateStr) {
    // Current metrics — leitura local do cache JSON (produzido por unbound_collector worker)
    $cacheFile = __DIR__ . '/../data/latest_stats.json';
    $currentStats = file_exists($cacheFile)
        ? json_decode(file_get_contents($cacheFile), true)
        : [];

    // Histórico + top vem da FastAPI (DuckDB)
    $jwt = $_SESSION['api_jwt'] ?? '';
    $resp = \App\ApiClient::get('/api/v1/exports/stats-report', $jwt);
    $reportData = ($resp['ok'] && is_array($resp['data'])) ? $resp['data'] : [
        'daily_history' => [], 'top_domains_24h' => [], 'top_clients_24h' => [],
    ];

    $report = array_merge([
        'generated_at' => date('Y-m-d H:i:s'),
        'server' => gethostname(),
        'current_metrics' => $currentStats,
    ], $reportData);

    $filename = "unbound_stats_{$dateStr}.json";
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 3. LOG DO SISTEMA (TXT) ───────────────────────────────────────
function exportSystemLog(string $dateStr) {
    $filename = "system_log_{$dateStr}.txt";
    header('Content-Type: text/plain; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    
    echo "════════════════════════════════════════════════════════════\n";
    echo "  UNBOUND DASHBOARD — Exportação de Logs do Sistema\n";
    echo "  Gerado em: " . date('d/m/Y H:i:s') . "\n";
    echo "  Servidor: " . gethostname() . "\n";
    echo "════════════════════════════════════════════════════════════\n\n";
    
    // Unbound daemon logs
    echo "┌─────────────────────────────────────────────────────────┐\n";
    echo "│  UNBOUND DAEMON (últimas 500 linhas)                   │\n";
    echo "└─────────────────────────────────────────────────────────┘\n\n";
    
    $unboundLog = [];
    \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'unbound', '-n', '300', '--no-pager'], $unboundLog, $tmpRet, true);
    echo implode("\n", $unboundLog);
    
    echo "\n\n";
    echo "┌─────────────────────────────────────────────────────────┐\n";
    echo "│  SYSLOG (últimas 300 linhas)                           │\n";
    echo "└─────────────────────────────────────────────────────────┘\n\n";
    
    $syslog = [];
    \App\ShellHelper::exec('/usr/bin/tail', ['-n', '300', '/var/log/syslog'], $syslog, $tmpRet, true);
    echo implode("\n", $syslog);
    
    exit;
}

// ─── 4. BACKUP DE CONFIGURAÇÕES (TAR.GZ) ───────────────────────────
function exportConfigBackup(string $dateStr) {
    $tmpDir = __DIR__ . '/../data/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    
    $tarFile = "{$tmpDir}/unbound_backup_{$dateStr}.tar.gz";
    
    // Build list of config files to include (exclude blocked_domains.conf and certs)
    $includeFiles = [
        '/etc/unbound/unbound.conf'
    ];
    
    // Add modular includes (skip blocked_domains.conf and certs/keys)
    $includesDir = '/etc/unbound/includes/';
    $excludePatterns = ['blocked_domains.conf', '*.key', '*.pem'];
    
    if (is_dir($includesDir)) {
        $files = scandir($includesDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            
            $excluded = false;
            foreach ($excludePatterns as $pat) {
                if (fnmatch($pat, $f)) {
                    $excluded = true;
                    break;
                }
            }
            if (!$excluded) {
                $includeFiles[] = $includesDir . $f;
            }
        }
    }
    
    // Add multicore configs if they exist
    for ($i = 1; $i <= 4; $i++) {
        $id = str_pad($i, 2, '0', STR_PAD_LEFT);
        $mcFile = "/etc/unbound/unbound{$id}.conf";
        if (file_exists($mcFile)) {
            $includeFiles[] = $mcFile;
        }
    }
    
    // Export dashboard settings (FastAPI/DuckDB)
    $jwt = $_SESSION['api_jwt'] ?? '';
    $resp = \App\ApiClient::get('/api/v1/exports/settings', $jwt);
    $settings = ($resp['ok'] && is_array($resp['data'])) ? $resp['data'] : [];
    $settingsFile = "{$tmpDir}/dashboard_settings.json";
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $includeFiles[] = $settingsFile;
    
    // Build tar command
    $fileList = array_merge([$tarFile], $includeFiles);
    $tarOut = [];
    $tarRet = 0;
    \App\ShellHelper::exec('/usr/bin/tar', array_merge(['-czf', $tarFile], $includeFiles), $tarOut, $tarRet, false);
    
    if ($tarRet !== 0 || !file_exists($tarFile) || filesize($tarFile) === 0) {
        http_response_code(500);
        exit('Falha ao gerar backup.');
    }
    
    $filename = "unbound_backup_{$dateStr}.tar.gz";
    header('Content-Type: application/gzip');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . filesize($tarFile));
    
    readfile($tarFile);
    
    // Cleanup
    @unlink($tarFile);
    @unlink($settingsFile);
    exit;
}

// ─── 5. LISTA DE DOMÍNIOS BLOQUEADOS (CSV) ─────────────────────────
function exportBlacklist(string $dateStr) {
    $filename = "blacklist_{$dateStr}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Domínio', 'Categoria'], ';');

    $jwt = $_SESSION['api_jwt'] ?? '';
    $resp = \App\ApiClient::get('/api/v1/exports/blocklist', $jwt);
    $rows = ($resp['ok'] && is_array($resp['data'])) ? $resp['data'] : [];
    foreach ($rows as $row) {
        fputcsv($output, [$row['domain'], $row['category']], ';');
    }

    fclose($output);
    exit;
}

// ─── 6. RESTAURAR BACKUP DE CONFIGURAÇÕES (POST) ───────────────────
function restoreConfigBackup() {
    $tmpDir = __DIR__ . '/../src/data/tmp';
    $extractDir = "{$tmpDir}/restore_" . uniqid();

    try {
        // Validate upload
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado ou erro no upload.']);
            return;
        }

        $file = $_FILES['backup_file'];

        // Validate extension
        if (!preg_match('/\.tar\.gz$/i', $file['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Formato inválido. Envie um arquivo .tar.gz gerado por este painel.']);
            return;
        }

        // Max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'Arquivo muito grande (máx. 5MB).']);
            return;
        }

        // Create temp dirs
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        if (!is_dir($extractDir)) mkdir($extractDir, 0755, true);

        // Move uploaded file
        $uploadedTar = "{$tmpDir}/restore_upload.tar.gz";
        move_uploaded_file($file['tmp_name'], $uploadedTar);

        // Extract
        \App\ShellHelper::exec('/usr/bin/tar', ['-xzf', $uploadedTar, '-C', $extractDir], $tarOutput, $tarReturn, false);
        @unlink($uploadedTar);

        if ($tarReturn !== 0) {
            cleanupDir($extractDir);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao extrair arquivo. Verifique se é um backup válido.']);
            return;
        }

        // Find all extracted files recursively
        $allFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            $allFiles[] = $fileInfo->getPathname();
        }

        if (empty($allFiles)) {
            cleanupDir($extractDir);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo de backup vazio.']);
            return;
        }

        // Strict whitelist: only .conf and .json files
        $allowedExtensions = ['conf', 'json'];
        foreach ($allFiles as $f) {
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            if (!in_array($ext, $allowedExtensions)) {
                cleanupDir($extractDir);
                echo json_encode(['status' => 'error', 'message' => "Arquivo não permitido detectado: " . basename($f)]);
                return;
            }
        }

        // Categorize files
        $configFiles = [];
        $settingsFile = null;
        $restoredFiles = [];

        foreach ($allFiles as $f) {
            $basename = basename($f);
            if ($basename === 'dashboard_settings.json') {
                $settingsFile = $f;
            } elseif (pathinfo($f, PATHINFO_EXTENSION) === 'conf') {
                $configFiles[] = $f;
            }
        }

        // Known include files
        $knownIncludes = ['general.conf', 'interfaces.conf', 'optimization.conf', 'performance.conf', 'security.conf', 'forwarders.conf', 'local_records.conf', 'remote-control.conf'];

        // Restore config files to /etc/unbound/
        foreach ($configFiles as $confFile) {
            $basename = basename($confFile);

            // Determine target path
            $originalPath = '';
            if (in_array($basename, $knownIncludes)) {
                $originalPath = "/etc/unbound/includes/{$basename}";
            } elseif (preg_match('/^unbound\d{0,2}\.conf$/', $basename)) {
                $originalPath = "/etc/unbound/{$basename}";
            }

            if (empty($originalPath)) continue;

            // Copy to staging area then via sudo to destination
            $stagingFile = "{$tmpDir}/unbound_restore_{$basename}";
            copy($confFile, $stagingFile);
            \App\ShellHelper::exec('/usr/bin/cp', [$stagingFile, $originalPath], $cpOut, $cpRet, true);
            @unlink($stagingFile);

            if ($cpRet === 0) {
                $restoredFiles[] = $originalPath;
            }
        }

        // Restore dashboard settings via FastAPI bulk upsert
        if ($settingsFile && file_exists($settingsFile)) {
            $settingsData = json_decode(file_get_contents($settingsFile), true);
            if (is_array($settingsData)) {
                $jwt = $_SESSION['api_jwt'] ?? '';
                $resp = \App\ApiClient::post('/api/v1/exports/settings/bulk', $jwt, $settingsData);
                if ($resp['ok']) {
                    $restoredFiles[] = 'dashboard_settings (DuckDB via FastAPI)';
                }
            }
        }

        // Cleanup extraction
        cleanupDir($extractDir);

        if (empty($restoredFiles)) {
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo de configuração reconhecido no backup.']);
            return;
        }

        // Validate Unbound config
        \App\ShellHelper::exec('/usr/sbin/unbound-checkconf', [], $checkOutput, $checkReturn, true);
        $checkResult = implode("\n", $checkOutput);

        if ($checkReturn !== 0) {
            echo json_encode([
                'status' => 'warning',
                'message' => 'Arquivos restaurados, mas a validação do Unbound encontrou erros. Revise antes de reiniciar.',
                'files' => $restoredFiles,
                'validation' => $checkResult
            ]);
            return;
        }

        // Restart Unbound
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', 'unbound'], $restartOut, $restartReturn, true);

        echo json_encode([
            'status' => 'success',
            'message' => 'Backup restaurado com sucesso! Unbound reiniciado.',
            'files' => $restoredFiles,
            'validation' => $checkResult
        ]);

    } catch (\Exception $e) {
        if (is_dir($extractDir)) cleanupDir($extractDir);
        echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}

function cleanupDir(string $dir) {
    if (!is_dir($dir)) return;
    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}
