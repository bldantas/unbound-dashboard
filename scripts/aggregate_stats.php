<?php

namespace App;

// Bootstrap the application environment
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/UnboundConfigManager.php';
require_once __DIR__ . '/../src/ShellHelper.php';

/**
 * This script is intended to be run as a cron job.
 * It fetches stats from unbound-control, processes them, and saves them to a cache file.
 * This prevents the web interface from needing to run slow exec() calls on every page load.
 */

class StatsAggregator {
    private ?\PDO $db;
    private string $cacheFile;
    private string $timeSeriesFile;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            // MariaDB não configurada (sistema usa API v2/DuckDB); opera sem DB
            $this->db = null;
        }
        $this->cacheFile = __DIR__ . '/../data/latest_stats.json';
        $this->timeSeriesFile = __DIR__ . '/../src/data/time_series.json';
    }

    public function run() {
        $stats = $this->getUnboundStats();
        $processedMetrics = $this->processMetrics($stats);
        $this->saveMetricsToCache($processedMetrics);
        $this->appendTimeSeriesSample($stats);
    }

    private function getUnboundStats(): array {
        $isMulticore = false;
        if ($this->db !== null) {
            try {
                $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'source_balance_enabled'");
                $stmt->execute();
                $isMulticore = ($stmt->fetchColumn() === 'yes');
            } catch (\Exception $e) {
                $isMulticore = false;
            }
        }

        if ($isMulticore) {
            $instances = 4;
            if ($this->db !== null) {
                try {
                    $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'source_balance_instances'");
                    $stmt->execute();
                    $instances = (int)$stmt->fetchColumn() ?: 4;
                } catch (\Exception $e) {
                    $instances = 4;
                }
            }
            
            $aggregated = [];
            for ($i = 1; $i <= $instances; $i++) {
                $id = str_pad($i, 2, '0', STR_PAD_LEFT);
                $output = [];
                // Note: Ensure your cron user has sudo permissions for this command without a password
                \App\ShellHelper::exec('/usr/sbin/unbound-control', ['-c', "/etc/unbound/unbound{$id}.conf", 'stats_noreset'], $output, $returnVar, true);
                
                foreach ($output as $line) {
                    if (strpos($line, '=') !== false) {
                        list($key, $val) = explode('=', $line);
                        $key = trim($key);
                        $val = floatval(trim($val));
                        
                        if (!isset($aggregated[$key])) {
                            $aggregated[$key] = $val;
                        } else {
                            if (strpos($key, 'time.up') !== false || strpos($key, 'time.elapsed') !== false) {
                                $aggregated[$key] = max($aggregated[$key], $val);
                            } else {
                                $aggregated[$key] += $val;
                            }
                        }
                    }
                }
            }

            // Métricas de tempo (avg/median) são somadas no loop acima, mas precisam
            // ser calculadas como média entre as instâncias, não soma.
            foreach ($aggregated as $key => $val) {
                if (preg_match('/\.time\.(avg|median)$/', $key)) {
                    $aggregated[$key] = $val / $instances;
                }
            }

            return $aggregated;
        }

        $output = [];
        // Note: Ensure your cron user has sudo permissions for this command without a password
        \App\ShellHelper::exec('/usr/sbin/unbound-control', ['stats_noreset'], $output, $returnVar, true);
        
        $stats = [];
        foreach ($output as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line);
                $stats[trim($key)] = floatval(trim($val));
            }
        }
        return $stats;
    }

    private function processMetrics(array $stats): array {
        $isUnboundRunning = !empty($stats);

        $totalQueries = $stats['total.num.queries'] ?? 0;
        $uptimeSecs = $stats['time.up'] ?? 1;
        if ($uptimeSecs <= 0) $uptimeSecs = 1;

        $qps = round($totalQueries / $uptimeSecs, 2);
        $latencyRecursion = round(($stats['total.recursion.time.avg'] ?? 0) * 1000, 2);
        $latencyMedian = round(($stats['total.recursion.time.median'] ?? 0) * 1000, 2);

        $cacheHits = $stats['total.num.cachehits'] ?? 0;
        $cacheMiss = $stats['total.num.cachemiss'] ?? 0;
        $hitRatio = $totalQueries > 0 ? round(($cacheHits / ($cacheHits + $cacheMiss)) * 100, 2) : 0;

        // Latência efetiva ponderada: cache hits ≈ 0ms, apenas cache miss gera recursão
        $missRatio = $totalQueries > 0 ? ($cacheMiss / ($cacheHits + $cacheMiss)) : 1;
        $latencyAvg = round($latencyRecursion * $missRatio, 2);
        
        $dnssecSecure = $stats['num.answer.secure'] ?? 0;
        $dnssecBogus = $stats['num.answer.bogus'] ?? 0;
        $dnssecTotal = $dnssecSecure + $dnssecBogus;
        $dnssecRatio = $dnssecTotal > 0 ? round(($dnssecSecure / $dnssecTotal) * 100, 2) : 0;

        // Blocklist counts — lê de cache (atualizado apenas na importação da blocklist)
        $adwareBlocks = 0; $phishBlocks = 0; $anatelBlocks = 0;
        $isJudicialEnabled = false;
        try {
            $configManager = new \App\UnboundConfigManager();
            $settings = $configManager->loadSettings();
            $isJudicialEnabled = $settings['official_blocklist_enabled'] ?? false;

            $countsFile = __DIR__ . '/../data/blocklist_counts.json';
            $counts = null;
            if (file_exists($countsFile)) {
                $counts = json_decode(file_get_contents($countsFile), true);
            }

            if (!is_array($counts)) {
                // Cache não existe — tenta gerar pelo DB se disponível
                if ($this->db !== null) {
                    try {
                        $adwareBlocks = $this->db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Malware/Adware'")->fetchColumn() ?: 0;
                        $phishBlocks = $this->db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Phishing'")->fetchColumn() ?: 0;
                        $anatelBlocks = $this->db->query("SELECT COUNT(*) FROM domain_blacklist WHERE category = 'Judicial'")->fetchColumn() ?: 0;
                        file_put_contents($countsFile, json_encode([
                            'adware' => (int) $adwareBlocks,
                            'phishing' => (int) $phishBlocks,
                            'judicial' => (int) $anatelBlocks,
                            'updated_at' => time()
                        ]));
                    } catch (\Exception $e) {
                        // DB indisponível — mantém zeros
                    }
                }
            } else {
                $adwareBlocks = $counts['adware'] ?? 0;
                $phishBlocks = $counts['phishing'] ?? 0;
                $anatelBlocks = $counts['judicial'] ?? 0;
            }

            if (!$isJudicialEnabled) {
                $anatelBlocks = 0;
            }
        } catch (\Exception $e) {}

        return [
            'online' => $isUnboundRunning,
            'qps' => $qps,
            'latency_avg' => $latencyAvg,
            'latency_recursion' => $latencyRecursion,
            'latency_median' => $latencyMedian,
            'hit_ratio' => $hitRatio,
            'dnssec_ratio' => $dnssecRatio,
            'dnssec_secure' => $dnssecSecure,
            'dnssec_bogus' => $dnssecBogus,
            'total_queries' => (int)$totalQueries,
            'cache_hits' => (int)$cacheHits,
            'cache_miss' => (int)$cacheMiss,
            'req_list_avg' => round($stats['total.requestlist.avg'] ?? 0, 2),
            'req_list_max' => $stats['total.requestlist.max'] ?? 0,
            'tcp_total' => $stats['num.query.tcp'] ?? 0,
            'ipv6_total' => $stats['num.query.ipv6'] ?? 0,
            'ipv4_total' => max(0, $totalQueries - ($stats['num.query.ipv6'] ?? 0)),
            'prefetch' => $stats['total.num.prefetch'] ?? 0,
            'rrset_mem' => $this->formatBytes($stats['mem.cache.rrset'] ?? 0),
            'msg_mem' => $this->formatBytes($stats['mem.cache.message'] ?? 0),
            'unwanted_queries' => $stats['unwanted.queries'] ?? 0,
            'unwanted_replies' => $stats['unwanted.replies'] ?? 0,
            'unwanted' => ($stats['unwanted.queries'] ?? 0) + ($stats['unwanted.replies'] ?? 0),
            'blocks' => [
                'adware' => (int)$adwareBlocks,
                'phishing' => (int)$phishBlocks,
                'judicial' => (int)$anatelBlocks,
                'judicial_enabled' => $isJudicialEnabled
            ],
            'uptime' => (int)$uptimeSecs,
            'uptime_human' => $this->formatUptime($uptimeSecs),
            'timestamp' => time()
        ];
    }

    private function saveMetricsToCache(array $metrics) {
        file_put_contents($this->cacheFile, json_encode($metrics, JSON_PRETTY_PRINT));
    }

    private function appendTimeSeriesSample(array $stats): void {
        if (empty($stats)) {
            return;
        }

        $tsData = $this->loadTimeSeries();
        $now = time();

        if (count($tsData['samples']) >= 60) {
            array_shift($tsData['samples']);
        }

        $current = [
            'timestamp' => $now,
            'label' => date('H:i', $now),
            'total_queries' => (int) ($stats['total.num.queries'] ?? 0),
            'cache_hits' => (int) ($stats['total.num.cachehits'] ?? 0),
            'cache_miss' => (int) ($stats['total.num.cachemiss'] ?? 0),
            'latency_avg' => (float) (($stats['total.recursion.time.avg'] ?? 0) * 1000),
            'latency_median' => (float) (($stats['total.recursion.time.median'] ?? 0) * 1000),
            'secure' => (int) ($stats['num.answer.secure'] ?? 0),
            'bogus' => (int) ($stats['num.answer.bogus'] ?? 0),
            'queries_tcp' => (int) ($stats['num.query.tcp'] ?? 0),
            'queries_ip6' => (int) ($stats['num.query.ipv6'] ?? 0),
            'types' => [],
            'types_diff' => [],
        ];

        foreach ($stats as $key => $value) {
            if (strpos($key, 'num.query.type.') !== 0) {
                continue;
            }

            $type = substr($key, 15);
            $current['types'][$type] = (int) $value;
        }

        $lastSample = end($tsData['samples']) ?: null;
        if ($lastSample && $current['timestamp'] > ($lastSample['timestamp'] ?? 0)) {
            $timeDiff = max(1, $current['timestamp'] - (int) $lastSample['timestamp']);

            $deltaTotalQueries = $this->counterDelta($current['total_queries'], (int) ($lastSample['total_queries'] ?? 0));
            $deltaHits = $this->counterDelta($current['cache_hits'], (int) ($lastSample['cache_hits'] ?? 0));
            $deltaMiss = $this->counterDelta($current['cache_miss'], (int) ($lastSample['cache_miss'] ?? 0));

            $current['queries_per_sec'] = round($deltaTotalQueries / $timeDiff, 2);
            $current['hits_diff'] = $deltaHits;
            $current['miss_diff'] = $deltaMiss;
            $current['secure_diff'] = $this->counterDelta($current['secure'], (int) ($lastSample['secure'] ?? 0));
            $current['bogus_diff'] = $this->counterDelta($current['bogus'], (int) ($lastSample['bogus'] ?? 0));
            $current['tcp_diff'] = $this->counterDelta($current['queries_tcp'], (int) ($lastSample['queries_tcp'] ?? 0));
            $current['ip6_diff'] = $this->counterDelta($current['queries_ip6'], (int) ($lastSample['queries_ip6'] ?? 0));

            foreach ($current['types'] as $type => $value) {
                $lastValue = (int) ($lastSample['types'][$type] ?? 0);
                $current['types_diff'][$type] = $this->counterDelta($value, $lastValue);
            }
        } else {
            $current['queries_per_sec'] = 0;
            $current['hits_diff'] = 0;
            $current['miss_diff'] = 0;
            $current['secure_diff'] = 0;
            $current['bogus_diff'] = 0;
            $current['tcp_diff'] = 0;
            $current['ip6_diff'] = 0;

            foreach ($current['types'] as $type => $value) {
                $current['types_diff'][$type] = 0;
            }
        }

        $tsData['samples'][] = $current;
        file_put_contents($this->timeSeriesFile, json_encode($tsData));
    }

    private function loadTimeSeries(): array {
        if (!file_exists($this->timeSeriesFile)) {
            return ['samples' => []];
        }

        $decoded = json_decode(file_get_contents($this->timeSeriesFile), true);
        return is_array($decoded) ? $decoded : ['samples' => []];
    }

    private function counterDelta(int $current, int $previous): int {
        if ($current < $previous) {
            return max(0, $current);
        }

        return max(0, $current - $previous);
    }

    private function formatBytes($bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    private function formatUptime(int $seconds): string {
        $days = floor($seconds / (3600*24));
        $seconds -= $days * 3600 * 24;
        $hours = floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = floor($seconds / 60);
        
        $str = '';
        if ($days > 0) $str .= $days . 'd ';
        if ($hours > 0) $str .= $hours . 'h ';
        if ($minutes > 0) $str .= $minutes . 'm';

        return trim($str) ?: '0m';
    }
}

// Execute the process
try {
    $aggregator = new StatsAggregator();
    $aggregator->run();
    echo "Successfully aggregated stats and updated cache.\n";
} catch (\Exception $e) {
    // Log error to stderr for cron
    file_put_contents('php://stderr', 'Error aggregating stats: ' . $e->getMessage() . "\n");
    exit(1);
}
