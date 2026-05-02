<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';

use Exception;

class BlocklistManager {
    private ApiClient $api;

    public function __construct() {
        $this->api = new ApiClient();
    }

    public function getBlocklistSource(): string {
        try {
            return $this->api->getSetting('blacklist_source') ?? 'stevenblack';
        } catch (Exception $e) {
            return 'stevenblack';
        }
    }

    public function saveBlocklistSource(string $source): bool {
        $allowedSources = ['stevenblack', 'hagezi_normal', 'hagezi_pro'];
        if (!in_array($source, $allowedSources)) {
            return false;
        }
        try {
            $this->api->setSetting('blacklist_source', $source);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
