<?php

namespace App;

use PDO;
use PDOException;

require_once __DIR__ . '/Environment.php';

$sysTz = ''; 
if (file_exists('/etc/timezone')) {
    $sysTz = trim(file_get_contents('/etc/timezone'));
} else if (is_link('/etc/localtime')) {
    $target = readlink('/etc/localtime');
    if (preg_match('/zoneinfo\/(.*)$/', $target, $matches)) {
        $sysTz = trim($matches[1]);
    }
}

if (!empty($sysTz) && in_array($sysTz, timezone_identifiers_list())) {
    date_default_timezone_set($sysTz);
}

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = Environment::get('DB_HOST', '127.0.0.1');
            $db   = Environment::get('DB_NAME', 'unbound_dash');
            $user = Environment::get('DB_USER', 'unbound');
            $pass = Environment::get('DB_PASS', 'unbound_pass');
            $charset = Environment::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Return generic error to avoid leaking credentials
                die("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
