<?php
/**
 * I18n helper — strings traduzíveis (pt-BR / en).
 *
 * Detecção de locale (na ordem):
 *   1. $_SESSION['locale']                 — definido pelo toggle no topbar
 *   2. cookie 'lang'                        — fallback caso a sessão expire
 *   3. header Accept-Language               — primeira vez do user
 *   4. default 'pt-BR'
 *
 * Uso: `t('key.path', ['name' => 'Bruno'])` retorna a string traduzida com
 * placeholders interpolados (`{name}` → Bruno). Falta = mostra a key
 * literal (UX que evita strings vazias).
 *
 * Arquivos em /lang/<locale>.php retornando array nested.
 */

namespace App;

class I18n
{
    private const SUPPORTED = ['pt-BR', 'en'];
    private const DEFAULT_LOCALE = 'pt-BR';

    private static ?array $cache = null;
    private static ?string $current = null;

    public static function detect(): string
    {
        if (!empty($_SESSION['locale']) && in_array($_SESSION['locale'], self::SUPPORTED, true)) {
            return $_SESSION['locale'];
        }
        if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::SUPPORTED, true)) {
            $_SESSION['locale'] = $_COOKIE['lang'];
            return $_SESSION['locale'];
        }
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($accept) {
            $primary = strtolower(trim(explode(',', $accept)[0] ?? ''));
            if (str_starts_with($primary, 'en')) {
                return 'en';
            }
            if (str_starts_with($primary, 'pt')) {
                return 'pt-BR';
            }
        }
        return self::DEFAULT_LOCALE;
    }

    public static function set(string $locale): void
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            return;
        }
        $_SESSION['locale'] = $locale;
        // Cookie 1 ano — sobrevive sessão
        setcookie('lang', $locale, [
            'expires' => time() + 31_536_000,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        self::$current = $locale;
        self::$cache = null;  // força reload
    }

    public static function current(): string
    {
        if (self::$current === null) {
            self::$current = self::detect();
        }
        return self::$current;
    }

    private static function load(string $locale): array
    {
        $file = __DIR__ . '/../lang/' . $locale . '.php';
        if (!file_exists($file)) {
            return [];
        }
        return require $file;
    }

    public static function t(string $key, array $vars = []): string
    {
        if (self::$cache === null) {
            self::$cache = self::load(self::current());
        }
        // Resolve "page.title.dashboard" → cache['page']['title']['dashboard']
        $parts = explode('.', $key);
        $value = self::$cache;
        foreach ($parts as $p) {
            if (is_array($value) && array_key_exists($p, $value)) {
                $value = $value[$p];
            } else {
                $value = null;
                break;
            }
        }
        // Fallback: se string vazia ou ausente, devolve a key (debug-friendly)
        if (!is_string($value)) {
            return $key;
        }
        // Interpolação simples {var}
        foreach ($vars as $k => $v) {
            $value = str_replace('{' . $k . '}', (string) $v, $value);
        }
        return $value;
    }

    public static function supported(): array
    {
        return self::SUPPORTED;
    }
}

// Helper global pra reduzir verbosidade nas views
if (!function_exists('t')) {
    function t(string $key, array $vars = []): string
    {
        return \App\I18n::t($key, $vars);
    }
}
