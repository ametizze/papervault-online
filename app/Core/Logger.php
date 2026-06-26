<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Tiny append-only logger.
 *
 * IMPORTANT: never pass secrets, plaintext passwords, vault keys, key file
 * contents, or decrypted payloads to this logger. Context values are JSON
 * encoded; keep them limited to non-sensitive metadata.
 */
final class Logger
{
    private const DENYLIST = [
        'password', 'master_password', 'master', 'vault_key', 'key_material',
        'keyfile', 'key_file', 'secret', 'token', 'payload', 'plaintext',
    ];

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $context = self::scrub($context);

        $line = sprintf(
            "[%s] %s: %s %s\n",
            now_iso(),
            $level,
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES)
        );

        $file = base_path('storage/logs/app.log');
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Defensive scrub: drop any context key that looks sensitive.
     */
    private static function scrub(array $context): array
    {
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            foreach (self::DENYLIST as $needle) {
                if (str_contains($lower, $needle)) {
                    $context[$key] = '[redacted]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $context[$key] = self::scrub($value);
            }
        }

        return $context;
    }
}
