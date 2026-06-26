<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * CSRF token generation and validation.
 *
 * A single per-session token is used (synchronizer token pattern). The token
 * is rotated periodically and after privileged actions.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const ISSUED_AT_KEY = '_csrf_issued_at';
    private const ROTATE_AFTER_SECONDS = 3600;

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        $issuedAt = (int) Session::get(self::ISSUED_AT_KEY, 0);

        if (!is_string($token) || $token === '' || (time() - $issuedAt) > self::ROTATE_AFTER_SECONDS) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
            Session::put(self::ISSUED_AT_KEY, time());
        }

        return $token;
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Force a new token (e.g. after login / master password change).
     */
    public static function rotate(): void
    {
        Session::put(self::SESSION_KEY, bin2hex(random_bytes(32)));
        Session::put(self::ISSUED_AT_KEY, time());
    }

    /**
     * Hidden input field for forms.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }
}
