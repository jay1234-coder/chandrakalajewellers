<?php
/**
 * Load KEY=VALUE pairs from a .env file without Composer.
 * Existing environment variables are never overwritten.
 */

if (!function_exists('cj_env_parse_value')) {
    function cj_env_parse_value(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $inner = substr($value, 1, -1);
            return $quote === '"' ? stripcslashes($inner) : $inner;
        }
        $hashPos = strpos($value, ' #');
        if ($hashPos !== false) {
            $value = rtrim(substr($value, 0, $hashPos));
        }
        return $value;
    }
}

if (!function_exists('cj_load_env_file')) {
    function cj_load_env_file(string $path): bool
    {
        if ($path === '' || !is_readable($path)) {
            return false;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            [$key, $raw] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }
            if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
                continue;
            }
            $existing = getenv($key);
            if ($existing !== false && $existing !== '') {
                $_ENV[$key] = $existing;
                continue;
            }
            $value = cj_env_parse_value($raw);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            if (function_exists('putenv')) {
                @putenv($key . '=' . $value);
            }
        }
        return true;
    }
}

if (!function_exists('cj_load_env')) {
    function cj_load_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $candidates = [
            __DIR__ . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        ];
        foreach ($candidates as $path) {
            if (cj_load_env_file($path)) {
                return;
            }
        }
    }
}

if (!function_exists('cj_env')) {
    function cj_env(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        return $default;
    }
}

cj_load_env();
