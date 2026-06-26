<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Immutable snapshot of the incoming HTTP request.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Support method override for clients that can only send POST.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        return new self(
            method: $method,
            path: $path,
            query: $_GET ?? [],
            body: $_POST ?? [],
            files: $_FILES ?? [],
            ip: self::clientIp(),
            userAgent: substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_string($value) ? $value : $default;
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    private static function clientIp(): string
    {
        // We intentionally trust only REMOTE_ADDR by default. Behind a reverse
        // proxy, configure the proxy to set REMOTE_ADDR correctly. Trusting
        // arbitrary X-Forwarded-For headers would let attackers spoof IPs.
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
