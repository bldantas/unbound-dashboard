<?php
require_once __DIR__ . '/../src/Database.php';
use App\Database;

$logFile = '/var/log/syslog';

echo "Waiting for syslog file...\n";
while (!file_exists($logFile)) {
    sleep(1);
}
echo "Syslog file found. Starting tail...\n";

$db = Database::getInstance();

// Tail -F survives log rotation, grep filters only unbound info messages
$handle = popen("tail -q -n 0 -F " . escapeshellarg($logFile) . " | grep --line-buffered 'unbound'", "r");

$stmt = $db->prepare('INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) VALUES (?, ?, ?, ?, ?)');
$dailyStmt = $db->prepare(
    'INSERT INTO daily_stats (stat_date, total_queries, cache_hits, cache_misses, blocked_count)
     VALUES (?, 1, 0, 0, ?)
     ON DUPLICATE KEY UPDATE total_queries = total_queries + 1, blocked_count = blocked_count + VALUES(blocked_count)'
);

$lastPrune = time();
$pruneInterval = 3600; // 1 hour

while (!feof($handle)) {
    $line = fgets($handle);
    if ($line !== false) {
        $action = 'query';
        // Example Unbound reply log:
        // Jun 19 14:01:23 unbound[123:0] info: 192.168.1.50 google.com. A IN NOERROR 0.000000 1 64
        // Example Unbound query log:
        // Jun 19 14:01:23 unbound[123:0] info: 192.168.1.50 google.com. A IN
        
        // We capture both query and replies to determine if it was blocked (NXDOMAIN / CNAME 0.0.0.0 via RPZ)
        if (strpos($line, 'info: ') !== false) {
            if (preg_match('/info:\s+([0-9a-fA-F:\.]+)\s+([^\s]+)\.\s+([A-Z0-9]+)\s+IN(\s+([A-Z]+))?/', $line, $matches)) {
                $clientIp = $matches[1];
                $domain = strtolower($matches[2]);
                $qtype = $matches[3];
                $status = $matches[5] ?? '';
                
                if (empty($status)) {
                    $action = 'query';
                } else {
                    if ($status === 'NXDOMAIN' || strpos($line, '0.0.0.0') !== false) {
                        $action = 'blocked';
                    } else {
                        $action = 'resolved';
                    }
                }
                
                // Only insert query start or if we want to update the action.
                // To save DB space, let's just insert queries (ignore replies if we just want volume).
                // Or insert resolved/blocked directly. Since Unbound logs both, let's just log queries, but if we need blocked vs resolved, we should log replies, not queries, because replies have the NOERROR/NXDOMAIN status.
                // Actually, if we only log replies:
                if (!empty($status)) {
                    try {
                        $stmt->execute([time(), $clientIp, $domain, $qtype, $action]);
                        $dailyStmt->execute([date('Y-m-d'), $action === 'blocked' ? 1 : 0]);
                    } catch (Exception $e) {
                        // ignore
                    }
                }
            }
        }
        
        if (time() - $lastPrune > $pruneInterval) {
            $settingStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'log_retention_days'");
            $days = (int) $settingStmt->fetchColumn() ?: 7;

            // Prune query_logs
            $cutoffTimestamp = time() - ($days * 86400);
            $pruneStmt = $db->prepare("DELETE FROM query_logs WHERE timestamp < ?");
            $pruneStmt->execute([$cutoffTimestamp]);

            // Prune daily_stats
            $pruneStatsStmt = $db->prepare("DELETE FROM daily_stats WHERE stat_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)");
            $pruneStatsStmt->execute([$days]);
            
            $lastPrune = time();
        }
    }
}
pclose($handle);
