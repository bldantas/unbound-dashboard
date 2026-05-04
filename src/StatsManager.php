<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Manages the retrieval of processed statistics for the dashboard.
 *
 * Reads `data/latest_stats.json` e `data/time_series.json` que são
 * mantidos atualizados pelo worker Python `stats_aggregator.py` no
 * api_service. PHP só consome os snapshots (não dispara refresh).
 */
class StatsManager {
    private string $cacheFile;
    private string $timeSeriesFile;
    private string $refreshLockFile;

    public function __construct() {
        $this->cacheFile = __DIR__ . '/../data/latest_stats.json';
        $this->timeSeriesFile = __DIR__ . '/data/time_series.json';
        $this->refreshLockFile = __DIR__ . '/../data/tmp/stats_refresh.lock';
    }

    /**
     * Antes (v1) disparava `scripts/aggregate_stats.php` em background quando o
     * cache estava velho. Em v2.2.x o worker Python `stats_aggregator.py` mantém
     * o cache atualizado continuamente, então este método é no-op.
     * Mantido como API estável pra callers existentes.
     */
    public function ensureFreshCache(int $maxAgeSeconds = 75, int $retryWindowSeconds = 15): void {
        // no-op: stats_aggregator.py (api_service worker) atualiza os JSONs.
    }

    /**
     * Retrieves processed metrics from the cache.
     * 
     * If the cache file does not exist, it returns a default set of metrics
     * to ensure the dashboard can still render without errors.
     *
     * @return array The processed metrics.
     */
    public function getProcessedMetrics(): array {
        if (!file_exists($this->cacheFile)) {
            return $this->getDefaultMetrics();
        }

        $jsonContent = file_get_contents($this->cacheFile);
        $metrics = json_decode($jsonContent, true);

        // json_decode can return null for invalid json. Fallback if that happens.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->getDefaultMetrics();
        }
        
        return $metrics;
    }

    /**
     * Retorna o payload inicial dos gráficos do dashboard a partir do cache local.
     */
    public function getDashboardChartData(int $historyPoints = 60): array {
        $default = $this->getDefaultChartData($historyPoints);

        if (!file_exists($this->timeSeriesFile)) {
            return $default;
        }

        $jsonContent = file_get_contents($this->timeSeriesFile);
        $payload = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            return $default;
        }

        $samples = $payload['samples'] ?? [];
        if (!is_array($samples) || empty($samples)) {
            return $default;
        }

        $samples = array_slice($samples, -$historyPoints);
        $labels = [];
        $hits = [];
        $misses = [];

        foreach ($samples as $sample) {
            $labels[] = (string) ($sample['label'] ?? date('H:i'));
            $hits[] = (int) ($sample['hits_diff'] ?? 0);
            $misses[] = (int) ($sample['miss_diff'] ?? 0);
        }

        $latestSample = end($samples) ?: [];
        $allTypes = $latestSample['types'] ?? [];

        // Ordena por volume e pega os top 5 tipos com tráfego real
        arsort($allTypes);
        $queryTypes = array_slice($allTypes, 0, 5, true);

        if (empty($queryTypes)) {
            $queryTypes = ['A' => 0, 'AAAA' => 0, 'PTR' => 0, 'CNAME' => 0, 'MX' => 0];
        }

        return [
            'labels' => $labels,
            'hits' => $hits,
            'misses' => $misses,
            'query_types' => $queryTypes,
        ];
    }

    private function isCacheStale(string $filePath, int $maxAgeSeconds): bool {
        if (!file_exists($filePath)) {
            return true;
        }

        $modifiedAt = @filemtime($filePath);
        if ($modifiedAt === false) {
            return true;
        }

        return (time() - $modifiedAt) > $maxAgeSeconds;
    }

    /**
     * Provides a default/empty structure for the metrics.
     *
     * This is used as a fallback when the cache is not available,
     * preventing errors on the frontend.
     *
     * @return array A default set of metrics.
     */
    private function getDefaultMetrics(): array {
        return [
            'online' => false,
            'qps' => 0,
            'latency_avg' => 0,
            'latency_recursion' => 0,
            'latency_median' => 0,
            'hit_ratio' => 0,
            'cache_hits' => 0,
            'cache_miss' => 0,
            'req_list_avg' => 0,
            'req_list_max' => 0,
            'dnssec_ratio' => 0,
            'dnssec_secure' => 0,
            'dnssec_bogus' => 0,
            'total_queries' => 0,
            'tcp_total' => 0,
            'ipv4_total' => 0,
            'ipv6_total' => 0,
            'prefetch' => 0,
            'rrset_mem' => '0 B',
            'msg_mem' => '0 B',
            'unwanted' => 0,
            'unwanted_queries' => 0,
            'unwanted_replies' => 0,
            'blocks' => [
                'adware' => 0,
                'phishing' => 0,
                'judicial' => 0,
                'judicial_enabled' => false
            ],
            'uptime' => 0,
            'uptime_human' => '---',
            'timestamp' => time()
        ];
    }

    /**
     * Estrutura vazia para evitar custo de banco ao renderizar os gráficos.
     */
    private function getDefaultChartData(int $historyPoints): array {
        $labels = [];
        $hits = [];
        $misses = [];

        for ($i = $historyPoints - 1; $i >= 0; $i--) {
            $labels[] = date('H:i', time() - ($i * 60));
            $hits[] = 0;
            $misses[] = 0;
        }

        return [
            'labels' => $labels,
            'hits' => $hits,
            'misses' => $misses,
            'query_types' => ['A' => 0, 'AAAA' => 0, 'PTR' => 0, 'CNAME' => 0, 'MX' => 0],
        ];
    }
}
