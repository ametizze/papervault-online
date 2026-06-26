<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * These are intentionally tiny and side-effect free (except `env` which reads
 * the process environment). They are autoloaded via composer "files".
 */

if (!function_exists('env')) {
    /**
     * Read an environment variable with a default fallback.
     */
    function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }
}

if (!function_exists('e')) {
    /**
     * Escape a string for safe HTML output.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /**
     * Read a configuration value loaded by the application bootstrap.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \SimpleVault\Core\App::config($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $relative = ''): string
    {
        $root = dirname(__DIR__);

        return $relative === '' ? $root : $root . '/' . ltrim($relative, '/');
    }
}

if (!function_exists('now_iso')) {
    /**
     * Current UTC timestamp in ISO-8601 format.
     */
    function now_iso(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:sP');
    }
}
