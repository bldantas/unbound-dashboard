<?php

namespace App;

use PDO;

require_once __DIR__ . '/Database.php';

class BlocklistManager {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBlocklistSource(): string {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blacklist_source'");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'stevenblack'; // Default to StevenBlack
    }

    public function saveBlocklistSource(string $source): bool {
        $allowedSources = ['stevenblack', 'hagezi_normal', 'hagezi_pro'];
        if (!in_array($source, $allowedSources)) {
            return false;
        }

        $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('blacklist_source', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$source, $source]);
    }
}
