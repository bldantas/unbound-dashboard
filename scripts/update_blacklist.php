<?php
require_once dirname(__DIR__) . '/src/Database.php';
use App\Database;

function setProgress($status, $progress) {
    $file = dirname(__DIR__) . '/src/data/tmp/blacklist_progress.json';
    file_put_contents($file, json_encode(['status' => $status, 'progress' => $progress]));
}

setProgress('downloading', 0);

$db = Database::getInstance();
$source = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'blacklist_source'")->fetchColumn();

if ($source === 'hagezi_normal') {
    $url = 'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/normal.txt';
    $name = 'Hagezi Normal';
} elseif ($source === 'hagezi_pro') {
    $url = 'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/pro.txt';
    $name = 'Hagezi Pro';
} else {
    $url = 'https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts';
    $name = 'StevenBlack Unified';
}

echo "[*] Iniciando atualização da Blacklist ($name)...\n";

$options = array(
  'http' => array(
    'method' => "GET",
    'header' => "User-Agent: UnboundDashboard/1.0\r\n"
  )
);
$context = stream_context_create($options);
$data = @file_get_contents($url, false, $context);

if ($data === false) {
    setProgress('idle', 0);
    die("[-] Erro ao baixar blacklist via file_get_contents.\n");
}

setProgress('parsing', 20);
echo "[*] Download concluído. Parseando domínios...\n";

$lines = explode("\n", $data);
$domains = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (strpos($line, '#') === 0 || empty($line)) continue;
    
    if (preg_match('/^0\.0\.0\.0\s+([a-zA-Z0-9.-]+)/', $line, $matches) || preg_match('/^127\.0\.0\.1\s+([a-zA-Z0-9.-]+)/', $line, $matches)) {
        $domain = trim($matches[1]);
        if (!in_array($domain, ['localhost', 'localhost.localdomain', 'local', '0.0.0.0', 'broadcasthost'])) {
            $domains[] = $domain;
        }
    }
}

$domains = array_unique($domains);
$total = count($domains);

echo "[*] Encontrados $total domínios maliciosos na lista. Gravando no banco de dados...\n";

try {
    $db->beginTransaction();
    echo "[*] Limpando dados antigos...\n";
    $db->exec('DELETE FROM domain_blacklist');
    
    $db->exec('SET unique_checks=0;');
    $db->exec('SET foreign_key_checks=0;');
    
    setProgress('inserting', 30);
    $chunks = array_chunk($domains, 2000);
    $insertedCount = 0;
    $totalChunks = count($chunks);
    $chunkIndex = 0;
    
    foreach ($chunks as $chunk) {
        $query = "INSERT IGNORE INTO domain_blacklist (domain, category, severity) VALUES ";
        $placeholders = [];
        $values = [];
        
        foreach ($chunk as $domain) {
            $placeholders[] = "(?, 'Malware/Adware', 'ALTO')";
            $values[] = $domain;
        }
        
        $query .= implode(', ', $placeholders);
        $stmt = $db->prepare($query);
        $stmt->execute($values);
        
        $insertedCount += count($chunk);
        $chunkIndex++;
        $pct = 30 + round(($chunkIndex / $totalChunks) * 65);
        setProgress('inserting', $pct);
        
        if ($insertedCount % 20000 === 0) {
            echo "[*] Inseridos $insertedCount / $total ...\n";
        }
    }
    
    $db->exec('SET unique_checks=1;');
    $db->exec('SET foreign_key_checks=1;');
    
    $db->commit();

    // Atualiza cache de contagens da blocklist
    $countsFile = dirname(__DIR__) . '/data/blocklist_counts.json';
    $adware = $db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Malware/Adware'")->fetchColumn() ?: 0;
    $phishing = $db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Phishing'")->fetchColumn() ?: 0;
    $judicial = $db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Judicial'")->fetchColumn() ?: 0;
    file_put_contents($countsFile, json_encode([
        'adware' => (int) $adware,
        'phishing' => (int) $phishing,
        'judicial' => (int) $judicial,
        'updated_at' => time()
    ]));

    setProgress('done', 100);
    echo "[+] Sucesso! Blacklist atualizada com $insertedCount domínios.\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    setProgress('idle', 0);
    echo "[-] Erro fatal no banco de dados: " . $e->getMessage() . "\n";
    exit(1);
}
