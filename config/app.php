<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Values are read from environment variables (loaded from .env when present)
 * with safe defaults. No secrets or encryption keys are stored here.
 */
return [
    'app_name' => env('APP_NAME', 'SimpleVault'),
    'app_env' => env('APP_ENV', 'production'),
    'app_debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'app_url' => env('APP_URL', 'http://localhost'),

    'db_connection' => env('DB_CONNECTION', 'sqlite'),
    'db_database' => env('DB_DATABASE', dirname(__DIR__) . '/database/database.sqlite'),
    'db_host' => env('DB_HOST', '127.0.0.1'),
    'db_port' => (int) env('DB_PORT', '3306'),
    'db_name' => env('DB_NAME', 'simplevault'),
    'db_user' => env('DB_USER', 'simplevault'),
    'db_pass' => env('DB_PASS', ''),

    'session_lifetime_minutes' => (int) env('SESSION_LIFETIME_MINUTES', '60'),
    'vault_auto_lock_minutes' => (int) env('VAULT_AUTO_LOCK_MINUTES', '15'),

    'allow_public_registration' => filter_var(env('ALLOW_PUBLIC_REGISTRATION', 'false'), FILTER_VALIDATE_BOOLEAN),

    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', '10'),
    'max_markdown_note_kb' => (int) env('MAX_MARKDOWN_NOTE_KB', '512'),
    'max_import_files' => (int) env('MAX_IMPORT_FILES', '100'),

    // Security tuning.
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,

    // KDF parameters used for new vaults. Stored per-vault so they can be
    // tuned over time without breaking existing vaults.
    'kdf_ops_limit' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
    'kdf_mem_limit' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,

    // Minimum password lengths.
    'min_account_password_length' => 10,
    'min_master_password_length' => 12,
];
