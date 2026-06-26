<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * HTTP response value object.
 */
final class Response
{
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        $response = new self($body, $status);
        $response->headers['Content-Type'] = 'text/html; charset=utf-8';

        return $response;
    }

    public static function redirect(string $location, int $status = 302): self
    {
        $response = new self('', $status);
        $response->headers['Location'] = $location;

        return $response;
    }

    public static function json(array $data, int $status = 200): self
    {
        $response = new self(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status);
        $response->headers['Content-Type'] = 'application/json; charset=utf-8';

        return $response;
    }

    /**
     * A downloadable file response (kept in memory).
     */
    public static function download(string $content, string $filename, string $contentType): self
    {
        $response = new self($content, 200);
        $response->headers['Content-Type'] = $contentType;
        $response->headers['Content-Disposition'] =
            'attachment; filename="' . str_replace('"', '', $filename) . '"';
        $response->headers['Content-Length'] = (string) strlen($content);

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        $this->sendSecurityHeaders();

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header(
            "Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
            . "object-src 'none'; base-uri 'self'; form-action 'self'; "
            . "frame-ancestors 'none';"
        );
        // Belt-and-suspenders; the cookie is also set HttpOnly/Secure/SameSite.
        header_remove('X-Powered-By');
    }
}
