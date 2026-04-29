<?php

namespace App;

class Environment
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (($value = getenv($key)) !== false) {
            return $value;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (!self::$loaded) {
            self::loadDotEnvFiles();
        }

        return self::$values[$key] ?? $default;
    }

    private static function loadDotEnvFiles(): void
    {
        $paths = [
            '/etc/unbound-dashboard.env',
            dirname(__DIR__) . '/.env',
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                self::loadDotEnv($path);
            }
        }

        self::$loaded = true;
    }

    private static function loadDotEnv(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$dataKey, $dataValue] = array_pad(explode('=', $line, 2), 2, '');
            $dataKey = trim($dataKey);
            $dataValue = trim($dataValue);

            if ($dataKey === '' || isset(self::$values[$dataKey])) {
                continue;
            }

            if (str_starts_with($dataValue, '"') && str_ends_with($dataValue, '"')) {
                $dataValue = substr($dataValue, 1, -1);
            }

            self::$values[$dataKey] = $dataValue;
        }
    }
}
