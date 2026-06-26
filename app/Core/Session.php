<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Secure session wrapper.
 *
 * Responsibilities: hardened session start, ID regeneration, flash messages,
 * authentication state, and the decrypted Vault Key lifecycle (in-session only,
 * with inactivity auto-lock).
 *
 * SECURITY TRADEOFF: the decrypted Vault Key is held in the PHP session after
 * unlock so the user does not have to re-enter the Master Password on every
 * request. The key is removed on lock, logout, and inactivity timeout. This is
 * a usability/security compromise documented in docs/CRYPTO_DESIGN.md.
 */
final class Session
{
    private const KEY_USER_ID = 'user_id';
    private const KEY_VAULT_KEY = 'vault_key_b64';
    private const KEY_VAULT_UNLOCKED_AT = 'vault_unlocked_at';
    private const KEY_LAST_ACTIVITY = 'last_activity';
    private const KEY_FLASH = '_flash';

    public static function start(): void
    {
        // Sessions are meaningless under the CLI SAPI (migrate/install/tests).
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = (int) (App::config('session_lifetime_minutes', 60)) * 60;
        $secure = self::isHttps();

        session_name('simplevault_session');
        session_set_cookie_params([
            'lifetime' => 0, // session cookie; expiry enforced server-side
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_start();

        self::enforceLifetime($lifetime);
        self::enforceAutoLock();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    // --- Authentication ----------------------------------------------------

    public static function login(int $userId): void
    {
        // Regenerate to prevent session fixation.
        self::regenerate();
        $_SESSION[self::KEY_USER_ID] = $userId;
        $_SESSION[self::KEY_LAST_ACTIVITY] = time();
    }

    public static function logout(): void
    {
        self::lockVault();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION[self::KEY_USER_ID]);
    }

    public static function userId(): ?int
    {
        $id = $_SESSION[self::KEY_USER_ID] ?? null;

        return $id === null ? null : (int) $id;
    }

    // --- Vault key lifecycle ----------------------------------------------

    /**
     * Store the decrypted vault key (raw bytes) after a successful unlock.
     */
    public static function unlockVault(string $rawVaultKey): void
    {
        // Regenerate session id on unlock as an extra precaution.
        self::regenerate();
        $_SESSION[self::KEY_VAULT_KEY] = base64_encode($rawVaultKey);
        $_SESSION[self::KEY_VAULT_UNLOCKED_AT] = time();
        $_SESSION[self::KEY_LAST_ACTIVITY] = time();
    }

    public static function isVaultUnlocked(): bool
    {
        return isset($_SESSION[self::KEY_VAULT_KEY]);
    }

    /**
     * Return the raw vault key bytes, or null if locked.
     */
    public static function vaultKey(): ?string
    {
        $b64 = $_SESSION[self::KEY_VAULT_KEY] ?? null;
        if ($b64 === null) {
            return null;
        }

        $raw = base64_decode($b64, true);

        return $raw === false ? null : $raw;
    }

    public static function lockVault(): void
    {
        if (isset($_SESSION[self::KEY_VAULT_KEY])) {
            // Best-effort wipe of the reference before unsetting.
            $_SESSION[self::KEY_VAULT_KEY] = str_repeat("\0", strlen((string) $_SESSION[self::KEY_VAULT_KEY]));
        }
        unset($_SESSION[self::KEY_VAULT_KEY], $_SESSION[self::KEY_VAULT_UNLOCKED_AT]);
    }

    public static function touch(): void
    {
        $_SESSION[self::KEY_LAST_ACTIVITY] = time();
    }

    // --- Flash messages ----------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION[self::KEY_FLASH][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<int, array{type:string,message:string}>
     */
    public static function takeFlash(): array
    {
        $flash = $_SESSION[self::KEY_FLASH] ?? [];
        unset($_SESSION[self::KEY_FLASH]);

        return $flash;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // --- Internal lifetime / auto-lock ------------------------------------

    private static function enforceLifetime(int $lifetime): void
    {
        $last = $_SESSION[self::KEY_LAST_ACTIVITY] ?? null;
        if ($last !== null && (time() - (int) $last) > $lifetime) {
            // Hard session expiry: destroy everything.
            $_SESSION = [];
            session_destroy();
            session_start();
        }
    }

    private static function enforceAutoLock(): void
    {
        if (!self::isVaultUnlocked()) {
            self::touch();
            return;
        }

        $autoLock = (int) App::config('vault_auto_lock_minutes', 15) * 60;
        $last = (int) ($_SESSION[self::KEY_LAST_ACTIVITY] ?? time());

        if ((time() - $last) > $autoLock) {
            self::lockVault();
            self::flash('warning', 'Your vault was locked automatically due to inactivity.');
        }

        self::touch();
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return true;
        }
        // Trust common proxy header only for cookie "secure" decision.
        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
