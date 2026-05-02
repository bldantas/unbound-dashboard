<?php

namespace App;

/**
 * HTTP client para a API v2 (FastAPI/DuckDB).
 *
 * Centraliza todas as chamadas HTTP ao backend v2 e fornece cache
 * transparente via Redis para leituras frequentes.
 *
 * Configuração via variáveis de ambiente:
 *   API_V2_URL   → ex.: http://127.0.0.1:8000  (padrão)
 *   REDIS_URL    → ex.: redis://127.0.0.1:6379/0 (opcional; sem Redis, sem cache)
 *   API_V2_TIMEOUT → segundos de timeout HTTP (padrão: 5)
 */
class ApiClient
{
    private string $baseUrl;
    private int    $timeout;
    private ?string $jwt = null;

    /** @var \Redis|null */
    private static $redis = null;
    private static bool $redisChecked = false;

    public function __construct()
    {
        $this->baseUrl = rtrim(Environment::get('API_V2_URL', 'http://127.0.0.1:8000'), '/');
        $this->timeout = (int) Environment::get('API_V2_TIMEOUT', '5');
    }

    // ------------------------------------------------------------------
    // JWT — obtido da sessão ou gerado via login de serviço
    // ------------------------------------------------------------------

    /**
     * Define o token JWT a usar nas requisições.
     * Quando nulo, tenta pegar de $_SESSION['api_jwt'].
     */
    public function setJwt(?string $jwt): void
    {
        $this->jwt = $jwt;
    }

    private function resolveJwt(): ?string
    {
        if ($this->jwt !== null) {
            return $this->jwt;
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['api_jwt'])) {
            return $_SESSION['api_jwt'];
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Métodos públicos de acesso a dados (com cache Redis)
    // ------------------------------------------------------------------

    /** GET /api/v2/alerts */
    public function getAlerts(bool $unreadOnly = false): array
    {
        $key   = 'alerts:' . ($unreadOnly ? 'unread' : 'all');
        $query = $unreadOnly ? '?is_read=false' : '';
        return $this->cachedGet("/api/v2/alerts{$query}", $key, 15);
    }

    /** GET /api/v2/alerts/count */
    public function getAlertsCount(): int
    {
        $data = $this->cachedGet('/api/v2/alerts/count', 'alerts:count', 15);
        return (int) ($data['unread'] ?? 0);
    }

    /** POST /api/v2/alerts */
    public function createAlert(string $type, string $message, string $severity): array
    {
        $result = $this->post('/api/v2/alerts', compact('type', 'message', 'severity'));
        $this->invalidateCache('alerts:');
        return $result;
    }

    /** POST /api/v2/alerts/{id}/read */
    public function markAlertRead(int $id): void
    {
        $this->post("/api/v2/alerts/{$id}/read", []);
        $this->invalidateCache('alerts:');
    }

    /** POST /api/v2/alerts/read-all */
    public function markAllAlertsRead(): void
    {
        $this->post('/api/v2/alerts/read-all', []);
        $this->invalidateCache('alerts:');
    }

    /** DELETE /api/v2/alerts/{id} */
    public function deleteAlert(int $id): void
    {
        $this->delete("/api/v2/alerts/{$id}");
        $this->invalidateCache('alerts:');
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    /** GET /api/v2/settings */
    public function getSettings(): array
    {
        return $this->cachedGet('/api/v2/settings', 'settings:all', 60);
    }

    /** GET /api/v2/settings/{key} */
    public function getSetting(string $key): ?string
    {
        $data = $this->cachedGet("/api/v2/settings/{$key}", "settings:{$key}", 60);
        return $data['value'] ?? null;
    }

    /** PUT /api/v2/settings/{key} */
    public function setSetting(string $key, string $value): void
    {
        $this->put("/api/v2/settings/{$key}", ['value' => $value]);
        $this->invalidateCache('settings:');
    }

    /** Salva múltiplas settings de uma vez */
    public function saveSettings(array $map): void
    {
        foreach ($map as $k => $v) {
            $this->put("/api/v2/settings/{$k}", ['value' => (string) $v]);
        }
        $this->invalidateCache('settings:');
    }

    // ------------------------------------------------------------------
    // Blocklist
    // ------------------------------------------------------------------

    /** GET /api/v2/blocklist/sources */
    public function getBlocklistSources(): array
    {
        return $this->cachedGet('/api/v2/blocklist/sources', 'blocklist:sources', 120);
    }

    /** POST /api/v2/blocklist/sync */
    public function syncBlocklist(): array
    {
        $result = $this->post('/api/v2/blocklist/sync', []);
        $this->invalidateCache('blocklist:');
        return $result;
    }

    /** GET /api/v2/blocklist/domains */
    public function getBlocklistDomains(): array
    {
        return $this->cachedGet('/api/v2/blocklist/domains', 'blocklist:domains', 120);
    }

    // ------------------------------------------------------------------
    // Stats
    // ------------------------------------------------------------------

    /** GET /api/v2/stats/live?hours={h} */
    public function getLiveStats(int $hours = 24): array
    {
        $hours = max(1, min(168, $hours));
        $key   = $hours === 24 ? 'stats:live' : "stats:live:{$hours}h";
        return $this->cachedGet("/api/v2/stats/live?hours={$hours}", $key, 10);
    }

    /** GET /api/v2/stats/history?days={n} */
    public function getStatsHistory(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        return $this->cachedGet("/api/v2/stats/history?days={$days}", "stats:history:{$days}", 30);
    }

    /** GET /api/v2/stats/logs?limit={n}&action={action}&domain={domain} */
    public function getQueryLogs(int $limit = 100, string $action = '', string $domain = ''): array
    {
        $query = "/api/v2/stats/logs?limit={$limit}";
        $cacheKey = "stats:logs:{$limit}";
        if ($action !== '') {
            $query .= '&action=' . urlencode($action);
            $cacheKey .= ":{$action}";
        }
        if ($domain !== '') {
            $query .= '&domain=' . urlencode($domain);
            $cacheKey .= ':d' . md5($domain);
        }
        return $this->cachedGet($query, $cacheKey, 15);
    }

    // ------------------------------------------------------------------
    // Health / Unbound
    // ------------------------------------------------------------------

    /** GET /api/v2/unbound/stats */
    public function getUnboundStats(): array
    {
        return $this->cachedGet('/api/v2/unbound/stats', 'unbound:stats', 10);
    }

    /** GET /api/v2/unbound/status */
    public function getUnboundStatus(): array
    {
        return $this->cachedGet('/api/v2/unbound/status', 'unbound:status', 10);
    }

    /** GET /healthz */
    public function healthz(): array
    {
        return $this->cachedGet('/healthz', 'healthz', 10);
    }

    /** GET /api/v2/health */
    public function getHealth(): array
    {
        return $this->cachedGet('/api/v2/health', 'health:full', 15);
    }

    // ------------------------------------------------------------------
    // Auth — usado pelo Auth.php
    // ------------------------------------------------------------------

    /**
     * POST /api/v2/auth/login
     * Retorna ['access_token' => '...', 'token_type' => 'bearer'] ou lança exceção.
     */
    public function login(string $username, string $password): array
    {
        return $this->post('/api/v2/auth/login', [
            'username' => $username,
            'password' => $password,
        ], false); // sem JWT no header
    }

    /** GET /api/v2/auth/me */
    public function me(): array
    {
        return $this->get('/api/v2/auth/me');
    }

    /** POST /api/v2/auth/logout */
    public function logout(): void
    {
        try {
            $this->post('/api/v2/auth/logout', []);
        } catch (\Exception $e) {
            // ignora erro de logout remoto
        }
    }

    // ------------------------------------------------------------------
    // Network / Source Balance
    // ------------------------------------------------------------------

    /** GET /api/v2/network/interfaces */
    public function getNetworkInterfaces(): array
    {
        return $this->cachedGet('/api/v2/network/interfaces', 'net:interfaces', 30);
    }

    /** GET /api/v2/source-balance/upstreams */
    public function getSourceBalanceUpstreams(): array
    {
        return $this->cachedGet('/api/v2/source-balance/upstreams', 'srcbal:upstreams', 60);
    }

    /** GET /api/v2/source-balance/health */
    public function getSourceBalanceHealth(): array
    {
        return $this->cachedGet('/api/v2/source-balance/health', 'srcbal:health', 15);
    }

    // ------------------------------------------------------------------
    // HTTP primitives
    // ------------------------------------------------------------------

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body, bool $withAuth = true): array
    {
        return $this->request('POST', $path, $body, $withAuth);
    }

    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, $body);
    }

    public function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, array $body = [], bool $withAuth = true): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($withAuth) {
            $jwt = $this->resolveJwt();
            if ($jwt !== null) {
                $headers[] = "Authorization: Bearer {$jwt}";
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new \RuntimeException("API v2 inacessível [{$method} {$path}]: {$err}");
        }

        if ($httpCode >= 400) {
            $detail = json_decode($raw, true)['detail'] ?? $raw;
            throw new \RuntimeException("API v2 erro {$httpCode} [{$method} {$path}]: {$detail}");
        }

        if ($raw === '' || $httpCode === 204) {
            return [];
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : ['value' => $decoded];
    }

    // ------------------------------------------------------------------
    // Cache Redis
    // ------------------------------------------------------------------

    private function cachedGet(string $path, string $cacheKey, int $ttlSeconds): array
    {
        $redis = $this->getRedis();
        $fullKey = 'udash:' . $cacheKey;

        if ($redis !== null) {
            $cached = $redis->get($fullKey);
            if ($cached !== false) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $data = $this->request('GET', $path);

        if ($redis !== null) {
            $redis->setex($fullKey, $ttlSeconds, json_encode($data, JSON_THROW_ON_ERROR));
        }

        return $data;
    }

    private function invalidateCache(string $prefix): void
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return;
        }
        $keys = $redis->keys('udash:' . $prefix . '*');
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }

    private function getRedis(): ?\Redis
    {
        if (self::$redisChecked) {
            return self::$redis;
        }
        self::$redisChecked = true;

        if (!class_exists('\Redis')) {
            return null;
        }

        $redisUrl = Environment::get('REDIS_URL', 'redis://127.0.0.1:6379/0');
        if (empty($redisUrl)) {
            return null;
        }

        try {
            $parsed = parse_url($redisUrl);
            $host   = $parsed['host'] ?? '127.0.0.1';
            $port   = (int) ($parsed['port'] ?? 6379);
            $db     = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '0';

            $r = new \Redis();
            $r->connect($host, $port, 2.0);
            if (!empty($parsed['pass'])) {
                $r->auth($parsed['pass']);
            }
            $r->select((int) $db);
            $r->ping();
            self::$redis = $r;
        } catch (\Exception $e) {
            self::$redis = null;
        }

        return self::$redis;
    }
}
