<?php

declare(strict_types=1);

/**
 * Guided installer: applies the schema and creates the first user + vault.
 *
 * Interactive usage:   php scripts/install.php
 * Non-interactive:     SV_EMAIL=you@example.com SV_ACCOUNT_PASSWORD=... \
 *                      SV_MASTER_PASSWORD=... php scripts/install.php
 *
 * A Master Password is never stored; only the wrapped vault key is persisted.
 */

$basePath = require __DIR__ . '/bootstrap.php';

use SimpleVault\Core\App;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Crypto\KeyDerivationService;
use SimpleVault\Crypto\VaultKeyService;
use SimpleVault\Repositories\UserRepository;
use SimpleVault\Repositories\VaultRepository;

// Ensure schema exists first.
require __DIR__ . '/migrate.php';

$users = new UserRepository();
if ($users->count() > 0) {
    echo "A user already exists. Nothing to do.\n";
    exit(0);
}

$prompt = static function (string $label, bool $hidden = false): string {
    $envMap = [
        'Email' => 'SV_EMAIL',
        'Account password' => 'SV_ACCOUNT_PASSWORD',
        'Master Password' => 'SV_MASTER_PASSWORD',
    ];
    $env = getenv($envMap[$label] ?? '');
    if ($env !== false && $env !== '') {
        return $env;
    }

    fwrite(STDOUT, $label . ': ');
    if ($hidden && stripos(PHP_OS, 'WIN') === false) {
        shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
        return $value;
    }

    return trim((string) fgets(STDIN));
};

$email = strtolower(trim($prompt('Email')));
$accountPassword = $prompt('Account password', true);
$masterPassword = $prompt('Master Password', true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}
if (strlen($accountPassword) < (int) App::config('min_account_password_length', 10)) {
    fwrite(STDERR, "Account password is too short.\n");
    exit(1);
}
if (strlen($masterPassword) < (int) App::config('min_master_password_length', 12)) {
    fwrite(STDERR, "Master Password is too short.\n");
    exit(1);
}

$userId = $users->create($email, password_hash($accountPassword, PASSWORD_ARGON2ID));

$vaultKeyService = new VaultKeyService(new CryptoService(), new KeyDerivationService());
$rawVaultKey = $vaultKeyService->generateVaultKey();
$wrapped = $vaultKeyService->wrapVaultKey(
    $rawVaultKey,
    $masterPassword,
    null,
    (int) App::config('kdf_ops_limit'),
    (int) App::config('kdf_mem_limit'),
);
(new VaultRepository())->create($userId, $wrapped, false);
sodium_memzero($rawVaultKey);

echo "Created user {$email} and vault. You can now log in.\n";
