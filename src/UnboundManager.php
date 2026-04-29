<?php

namespace App;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ShellHelper.php';

/**
 * Classe responsável por gerenciar e monitorar o serviço Unbound DNS
 */
class UnboundManager {
    
    private string $unboundControlPath;
    private string $historyFilePath;
    private string $tsFilePath;
    private int $lastKnownUptime = 0;

    public function __construct(string $unboundControlPath = '/usr/sbin/unbound-control') {
        $this->unboundControlPath = escapeshellcmd($unboundControlPath);
        $this->historyFilePath = dirname(__FILE__) . '/data/stats_history.json';
        $this->tsFilePath = dirname(__FILE__) . '/data/time_series.json';
    }

    /**
     * Lê as estatísticas do Unbound sem resetar os contadores.
     * 
     * @return array|null Retorna um array associativo com as estatísticas ou null em caso de erro.
     */
    public function getStats(): ?array {
        // Atenção: É necessário que o usuário www-data (ou o usuário do seu webserver)
        // tenha permissão no arquivo sudoers para rodar este comando sem senha.
        // Exemplo no /etc/sudoers:
        // www-data ALL=(ALL) NOPASSWD: /usr/sbin/unbound-control stats_noreset
        
        $output = [];
        $returnVar = 0;

        \App\ShellHelper::exec($this->unboundControlPath, ['stats_noreset'], $output, $returnVar, true);

        if ($returnVar !== 0) {
            error_log("Erro ao executar unbound-control:\n" . implode("\n", $output));
            return null;
        }

        return $this->parseStats($output);
    }

    /**
     * Retorna um conjunto formatado de métricas para o dashboard
     */
    public function getDashboardMetrics(): array {
        $stats = $this->getStats() ?? [];
        $uptime = $stats['time.up'] ?? 1;
        
        $totalQueries = $stats['total.num.queries'] ?? 0;
        
        return [
            'qps' => $uptime > 0 ? round($totalQueries / $uptime, 2) : 0,
            'latency_avg' => round(($stats['total.recursion.time.avg'] ?? 0) * 1000, 3), // ms
            'latency_median' => round(($stats['total.recursion.time.median'] ?? 0) * 1000, 3), // ms
            'hit_ratio' => $totalQueries > 0 ? round((($stats['total.num.cachehits'] ?? 0) / $totalQueries) * 100, 1) : 0,
            'hits' => $stats['total.num.cachehits'] ?? 0,
            'misses' => $stats['total.num.cachemiss'] ?? 0,
            'requestlist_avg' => round($stats['total.requestlist.avg'] ?? 0, 3),
            'requestlist_max' => $stats['total.requestlist.max'] ?? 0,
            'dnssec_validated' => $stats['num.answer.secure'] ?? 0,
            'dnssec_bogus' => $stats['num.answer.bogus'] ?? 0,
            'dnssec_ratio' => $totalQueries > 0 ? round((($stats['num.answer.secure'] ?? 0) / $totalQueries) * 100, 1) : 0,
            'queries_tcp' => ($stats['num.query.tcp'] ?? 0),
            'queries_ip6' => ($stats['num.query.ipv6'] ?? 0),
            'prefetch' => $stats['total.num.prefetch'] ?? 0,
            'expired' => $stats['total.num.expired'] ?? 0,
            'cache_rrsets' => $stats['rrset.cache.count'] ?? 0,
            'cache_msgs' => $stats['msg.cache.count'] ?? 0,
            'unwanted_replies' => $stats['unwanted.replies'] ?? 0,
            'unwanted_queries' => $stats['unwanted.queries'] ?? 0,
        ];
    }

    /**
     * Transforma as linhas retornadas pelo comando em um array estruturado.
     */
    private function parseStats(array $lines): array {
        $stats = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Converte valores numéricos adequadamente
                if (is_numeric($value)) {
                    $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
                }
                
                $stats[$key] = $value;
            }
        }
        
        return $stats;
    }

    /**
     * Checa se o serviço do Unbound está ativo
     */
    public function isServiceRunning(): bool {
        $output = [];
        $returnVar = 0;

        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'unbound'], $output, $returnVar, true);

        $outputString = trim(implode(' ', $output));
        return strtolower($outputString) === 'active';
    }

    public function sampleTimeSeries(): void {
        $stats = $this->getStats();
        if (!$stats) return;

        $tsData = $this->loadTimeSeries();
        $now = time();
        
        // Mantemos apenas os últimos 60 registros (1 hora se amostrado a cada minuto)
        if (count($tsData['samples']) >= 60) {
            array_shift($tsData['samples']);
        }

        $current = [
            'timestamp' => $now,
            'label' => date('H:i', $now),
            'total_queries' => $stats['total.num.queries'] ?? 0,
            'cache_hits' => $stats['total.num.cachehits'] ?? 0,
            'cache_miss' => $stats['total.num.cachemiss'] ?? 0,
            'latency_avg' => ($stats['total.recursion.time.avg'] ?? 0) * 1000,
            'latency_median' => ($stats['total.recursion.time.median'] ?? 0) * 1000,
            'secure' => $stats['num.answer.secure'] ?? 0,
            'bogus' => $stats['num.answer.bogus'] ?? 0,
            'queries_tcp' => $stats['num.query.tcp'] ?? 0,
            'queries_ip6' => $stats['num.query.ipv6'] ?? 0,
            'types' => []
        ];

        // Tipos de consulta (A, AAAA, PTR, etc)
        foreach ($stats as $key => $val) {
            if (strpos($key, 'num.query.type.') === 0) {
                $type = substr($key, 15);
                $current['types'][$type] = $val;
            }
        }

        $lastSample = end($tsData['samples']);
        $delta = $current;
        
        if ($lastSample && $current['timestamp'] > $lastSample['timestamp']) {
            $timeDiff = $current['timestamp'] - $lastSample['timestamp'];
            
            // Calculamos deltas para métricas incrementais
            $delta['queries_per_sec'] = round(max(0, $current['total_queries'] - $lastSample['total_queries']) / $timeDiff, 2);
            $delta['hits_diff'] = max(0, $current['cache_hits'] - $lastSample['cache_hits']);
            $delta['miss_diff'] = max(0, $current['cache_miss'] - $lastSample['cache_miss']);
            $delta['secure_diff'] = max(0, $current['secure'] - $lastSample['secure']);
            $delta['bogus_diff'] = max(0, $current['bogus'] - $lastSample['bogus']);
            $delta['tcp_diff'] = max(0, $current['queries_tcp'] - $lastSample['queries_tcp']);
            $delta['ip6_diff'] = max(0, $current['queries_ip6'] - $lastSample['queries_ip6']);
            
            foreach ($current['types'] as $type => $val) {
                $lastVal = $lastSample['types'][$type] ?? 0;
                $delta['types_diff'][$type] = max(0, $val - $lastVal);
            }
        } else {
            // Primeiro ponto ou reinício
            $delta['queries_per_sec'] = 0;
            $delta['hits_diff'] = 0;
            $delta['miss_diff'] = 0;
            $delta['secure_diff'] = 0;
            $delta['bogus_diff'] = 0;
            $delta['tcp_diff'] = 0;
            $delta['ip6_diff'] = 0;
            foreach ($current['types'] as $type => $val) {
                $delta['types_diff'][$type] = 0;
            }
        }

        $tsData['samples'][] = $delta;
        $this->saveTimeSeries($tsData);
    }

    public function loadTimeSeries(): array {
        if (!file_exists($this->tsFilePath)) {
            return ['samples' => []];
        }
        $data = json_decode(file_get_contents($this->tsFilePath), true);
        return is_array($data) ? $data : ['samples' => []];
    }

    private function saveTimeSeries(array $data): void {
        file_put_contents($this->tsFilePath, json_encode($data));
    }

    /**
     * Atualiza as estatísticas acumuladas salvando o estado atual no MariaDB.
     *
     * @PERFORMANCE: Esta função executa consultas de leitura e gravação no banco de dados.
     * Não deve ser chamada em cada carregamento de página do dashboard.
     * Idealmente, execute-a através de um cron job a cada 1-5 minutos.
     */
    public function updateCumulativeStats(): void {
        $stats = $this->getStats();
        if (!$stats) return;

        $db = \App\Database::getInstance();
        $today = date('Y-m-d');
        
        $currentTotalQueries = $stats['total.num.queries'] ?? 0;
        $currentCacheHits = $stats['total.num.cachehits'] ?? 0;
        $currentCacheMiss = $stats['total.num.cachemiss'] ?? 0;
        $currentUptime = $stats['time.up'] ?? 0;

        $settingStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'last_measurement'");
        $lastJson = $settingStmt->fetchColumn();
        $last = $lastJson ? json_decode($lastJson, true) : null;
        
        if (!$last) {
            $last = ['queries' => 0, 'hits' => 0, 'miss' => 0, 'uptime' => 0];
        }

        if ($currentUptime < $last['uptime']) {
            $diffQueries = $currentTotalQueries;
            $diffHits = $currentCacheHits;
            $diffMiss = $currentCacheMiss;
        } else {
            $diffQueries = max(0, $currentTotalQueries - $last['queries']);
            $diffHits = max(0, $currentCacheHits - $last['hits']);
            $diffMiss = max(0, $currentCacheMiss - $last['miss']);
        }

        if ($diffQueries > 0 || $diffHits > 0 || $diffMiss > 0) {
            $stmt = $db->prepare("INSERT INTO daily_stats (stat_date, total_queries, cache_hits, cache_misses) 
                                 VALUES (?, ?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE 
                                 total_queries = total_queries + VALUES(total_queries), 
                                 cache_hits = cache_hits + VALUES(cache_hits), 
                                 cache_misses = cache_misses + VALUES(cache_misses)");
            $stmt->execute([$today, $diffQueries, $diffHits, $diffMiss]);
        }

        $newLast = [
            'queries' => $currentTotalQueries,
            'hits' => $currentCacheHits,
            'miss' => $currentCacheMiss,
            'uptime' => $currentUptime
        ];
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('last_measurement', ?) 
                              ON DUPLICATE KEY UPDATE setting_value = ?");
        $encoded = json_encode($newLast);
        $stmt->execute([$encoded, $encoded]);
    }

    public function loadHistory(): array {
        $db = \App\Database::getInstance();
        $dailyObj = $db->query('SELECT stat_date as date, total_queries as queries, cache_hits as hits, cache_misses as miss FROM daily_stats ORDER BY stat_date ASC')->fetchAll();
        $daily = [];
        foreach ($dailyObj as $row) {
            $daily[$row['date']] = [
                'queries' => (int)$row['queries'],
                'hits' => (int)$row['hits'],
                'miss' => (int)$row['miss']
            ];
        }
        
        $totalsRow = $db->query('SELECT SUM(total_queries) as queries, SUM(cache_hits) as hits, SUM(cache_misses) as miss FROM daily_stats')->fetch();
        $totals = [
            'queries' => (int)($totalsRow['queries'] ?? 0),
            'hits' => (int)($totalsRow['hits'] ?? 0),
            'miss' => (int)($totalsRow['miss'] ?? 0)
        ];
        
        return ['daily' => $daily, 'totals' => $totals];
    }

    /**
     * Analisa o cache do Unbound para encontrar os domínios mais frequentes via banco de dados.
     * Retorna os top domínios base.
     *
     * @PERFORMANCE: Esta consulta pode ser muito lenta em tabelas 'query_logs' grandes.
     * Certifique-se de que a coluna 'domain' está indexada. Considere armazenar em cache os resultados.
     * Retorna os top domínios base.
     */
    public function getTopDomains(int $limit = 10): array {
        $db = \App\Database::getInstance();
        // Limita ao último 1 dia para evitar full scan na tabela inteira
        $cutoff = time() - 86400;
        $stmt = $db->prepare(
            'SELECT domain, COUNT(*) as count FROM query_logs WHERE timestamp >= ? GROUP BY domain ORDER BY count DESC LIMIT ?'
        );
        $stmt->bindValue(1, $cutoff, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna a versão instalada do Unbound
     */
    public function getVersion(): string {
        $version = $this->detectVersionFromBinary();
        if ($version !== null) {
            return $version;
        }

        $version = $this->detectVersionFromPackageManager();
        if ($version !== null) {
            return $version;
        }

        return 'Desconhecida';
    }

    private function detectVersionFromBinary(): ?string {
        $binary = $this->getDetectedBinaryPath();
        $commands = [
            [$binary, ['-V']],
            [$binary, ['-h']],
            ['/usr/local/sbin/unbound', ['-V']],
            ['/usr/sbin/unbound', ['-V']],
        ];

        $seen = [];

        foreach ($commands as [$binary, $args]) {
            if (!is_executable($binary) || isset($seen[$binary . '|' . implode(' ', $args)])) {
                continue;
            }
            $seen[$binary . '|' . implode(' ', $args)] = true;

            $output = [];
            $returnVar = 0;
            \App\ShellHelper::exec($binary, $args, $output, $returnVar, false);

            $version = $this->extractVersionFromOutput($output);
            if ($version !== null) {
                return $version;
            }
        }

        return null;
    }

    public function getDetectedBinaryPath(): string {
        $path = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $dirs = array_filter(array_map('trim', explode(':', $path)));

        foreach ($dirs as $dir) {
            $candidate = rtrim($dir, '/') . '/unbound';
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $fallbacks = [
            '/usr/local/sbin/unbound',
            '/usr/sbin/unbound',
            '/sbin/unbound',
        ];

        foreach ($fallbacks as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return '/usr/sbin/unbound';
    }

    private function detectVersionFromPackageManager(): ?string {
        $packageCommands = [
            ['/usr/bin/dpkg-query', ['-W', '-f=${Version}', 'unbound']],
            ['/usr/bin/rpm', ['-q', '--queryformat', '%{VERSION}-%{RELEASE}', 'unbound']],
        ];

        foreach ($packageCommands as [$binary, $args]) {
            if (!is_executable($binary)) {
                continue;
            }

            $output = [];
            $returnVar = 0;
            \App\ShellHelper::exec($binary, $args, $output, $returnVar, false);

            $version = $this->extractVersionFromOutput($output);
            if ($version !== null) {
                return $version;
            }
        }

        return null;
    }

    private function extractVersionFromOutput(array $output): ?string {
        foreach ($output as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^Version\s+(.+)$/i', $line, $matches)) {
                return trim($matches[1]);
            }

            if (preg_match('/\bunbound(?:\s+version)?\s+v?([0-9][0-9A-Za-z.+:~\-]*)/i', $line, $matches)) {
                return trim($matches[1]);
            }

            if (preg_match('/\b([0-9]+(?:\.[0-9]+)+(?:[0-9A-Za-z.+:~\-]*)?)\b/', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}
