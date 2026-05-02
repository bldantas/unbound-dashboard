<?php

namespace App;

/**
 * LocalDataAdapter — lê snapshots JSON exportados pelo worker Python (JsonExporter).
 *
 * O worker grava arquivos em /var/lib/unbound-dashboard/export/ a cada 30 segundos.
 * Este adapter fornece os mesmos dados que o ApiClient, mas sem chamadas HTTP.
 *
 * Quando a v2 estiver offline os dados ficam congelados no último export.
 * A property $isStale indica se os dados têm mais de $maxAge segundos.
 *
 * Uso:
 *   $local = new \App\LocalDataAdapter();
 *   if ($local->isAvailable()) {
 *       $alerts    = $local->getAlerts();
 *       $stats     = $local->getLiveStats();
 *       $blocked   = $local->getBlockedLogs();
 *       $queries   = $local->getQueryLogs();
 *   }
 */
class LocalDataAdapter
{
    /** Diretório onde o worker Python escreve os JSONs. */
    private string $exportDir;

    /** Idade máxima (em segundos) aceitável para os dados serem considerados frescos. */
    private int $maxAge;

    /** Timestamp da última exportação lida de metadata.json (ou null se ausente). */
    public ?int $exportedAt = null;

    /** true se os dados estiverem mais velhos que $maxAge. */
    public bool $isStale = true;

    public function __construct(string $exportDir = '/var/lib/unbound-dashboard/export', int $maxAge = 120)
    {
        $this->exportDir = rtrim($exportDir, '/');
        $this->maxAge    = $maxAge;
        $this->_loadMetadata();
    }

    // ------------------------------------------------------------------
    // Estado do export
    // ------------------------------------------------------------------

    /**
     * Retorna true se os arquivos de export existem (independente de frescor).
     */
    public function isAvailable(): bool
    {
        return $this->exportedAt !== null;
    }

    /**
     * Retorna true se o export foi feito nos últimos $maxAge segundos.
     */
    public function isFresh(): bool
    {
        return !$this->isStale;
    }

    /**
     * Retorna quantos segundos atrás foi o último export, ou null se desconhecido.
     */
    public function ageSeconds(): ?int
    {
        return $this->exportedAt !== null ? (time() - $this->exportedAt) : null;
    }

    // ------------------------------------------------------------------
    // Alertas
    // ------------------------------------------------------------------

    /**
     * Retorna alertas não lidos.
     * @return array{unread_count: int, alerts: list<array>}
     */
    public function getAlerts(): array
    {
        $data = $this->_read('alerts.json');
        return [
            'unread_count' => (int) ($data['unread_count'] ?? 0),
            'alerts'       => (array) ($data['alerts'] ?? []),
        ];
    }

    // ------------------------------------------------------------------
    // Estatísticas ao vivo
    // ------------------------------------------------------------------

    /**
     * Retorna métricas das últimas 24h no mesmo formato que ApiClient::getLiveStats().
     * @return array{total: int, blocked: int, resolved: int, cache_hits: int, top_domains: list<array>, top_clients: list<array>}
     */
    public function getLiveStats(): array
    {
        $data = $this->_read('stats_live.json');
        return [
            'window_hours' => (int) ($data['window_hours'] ?? 24),
            'total'        => (int) ($data['total'] ?? 0),
            'blocked'      => (int) ($data['blocked'] ?? 0),
            'resolved'     => (int) ($data['resolved'] ?? 0),
            'cache_hits'   => (int) ($data['cache_hits'] ?? 0),
            'top_domains'  => (array) ($data['top_domains'] ?? []),
            'top_clients'  => (array) ($data['top_clients'] ?? []),
        ];
    }

    // ------------------------------------------------------------------
    // Logs
    // ------------------------------------------------------------------

    /**
     * Retorna logs bloqueados recentes (até 500), no formato de query_logs.
     * @return list<array{timestamp: int, client_ip: string, domain: string, query_type: string, action: string}>
     */
    public function getBlockedLogs(int $limit = 100): array
    {
        $data = $this->_read('logs_blocked.json');
        $logs = (array) ($data['logs'] ?? []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * Retorna os 100 últimos query logs de qualquer ação.
     * @return list<array{timestamp: int, client_ip: string, domain: string, query_type: string, action: string}>
     */
    public function getQueryLogs(int $limit = 100): array
    {
        $data = $this->_read('query_logs.json');
        $logs = (array) ($data['logs'] ?? []);
        return array_slice($logs, 0, $limit);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function _loadMetadata(): void
    {
        $meta = $this->_read('metadata.json');
        if (isset($meta['exported_at'])) {
            $this->exportedAt = (int) $meta['exported_at'];
            $this->isStale    = (time() - $this->exportedAt) > $this->maxAge;
        }
    }

    /**
     * Lê e decodifica um arquivo JSON do diretório de export.
     * Retorna array vazio em caso de erro.
     */
    private function _read(string $filename): array
    {
        $path = $this->exportDir . '/' . $filename;
        $raw  = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $decoded = @json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
