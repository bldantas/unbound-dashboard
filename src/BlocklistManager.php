<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';

/**
 * BlocklistManager — get/set do setting `blacklist_source`.
 * Migrado pra FastAPI 2026-05-04 (era PDO/MariaDB).
 */
class BlocklistManager
{
    public function getBlocklistSource(): string
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return 'stevenblack';
        $resp = ApiClient::get('/api/v1/exports/settings', $jwt);
        if (!$resp['ok'] || !is_array($resp['data'])) return 'stevenblack';
        foreach ($resp['data'] as $row) {
            if (($row['setting_key'] ?? '') === 'blacklist_source') {
                return (string) ($row['setting_value'] ?? 'stevenblack');
            }
        }
        return 'stevenblack';
    }

    public function saveBlocklistSource(string $source): bool
    {
        $allowedSources = ['stevenblack', 'hagezi_normal', 'hagezi_pro'];
        if (!in_array($source, $allowedSources, true)) {
            return false;
        }
        $jwt = $_SESSION['api_jwt'] ?? '';
        $resp = ApiClient::post('/api/v1/exports/settings/bulk', $jwt, [
            ['setting_key' => 'blacklist_source', 'setting_value' => $source],
        ]);
        return (bool) ($resp['ok'] ?? false);
    }

    /**
     * Estado da fonte: ativa (auto-update roda) ou pausada.
     * Setting persistida via /api/v1/exports/settings. Default: ativa.
     */
    public function isBlacklistSourceEnabled(): bool
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return true;
        $resp = ApiClient::get('/api/v1/exports/settings', $jwt);
        if (!$resp['ok'] || !is_array($resp['data'])) return true;
        foreach ($resp['data'] as $row) {
            if (($row['setting_key'] ?? '') === 'blacklist_source_enabled') {
                return ((string) ($row['setting_value'] ?? '1')) !== '0';
            }
        }
        return true;
    }

    public function saveBlacklistSourceEnabled(bool $enabled): bool
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        $resp = ApiClient::post('/api/v1/exports/settings/bulk', $jwt, [
            ['setting_key' => 'blacklist_source_enabled', 'setting_value' => $enabled ? '1' : '0'],
        ]);
        return (bool) ($resp['ok'] ?? false);
    }
}
