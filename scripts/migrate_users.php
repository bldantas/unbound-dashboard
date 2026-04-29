<?php
require_once __DIR__ . '/../src/Database.php';
use App\Database;

$usersFile = __DIR__ . '/../data/users.json';
if (file_exists($usersFile)) {
    $data = json_decode(file_get_contents($usersFile), true);
    if (is_array($data)) {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT IGNORE INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        foreach ($data as $username => $hash) {
            $stmt->execute([$username, $hash, 'admin']);
        }
        echo "Users migrated successfully.\n";
    }
} else {
    echo "No users.json found.\n";
}
