<?php

declare(strict_types=1);

use SimpleVault\Crypto\CryptoService;
use SimpleVault\Crypto\KeyDerivationService;
use SimpleVault\Crypto\VaultKeyService;

$ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
$mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

test('KDF is deterministic for same inputs', function () use ($ops, $mem) {
    $kdf = new KeyDerivationService();
    $salt = $kdf->generateSalt();
    $a = $kdf->deriveKey('master-password', null, $salt, $ops, $mem);
    $b = $kdf->deriveKey('master-password', null, $salt, $ops, $mem);
    assert_equals(bin2hex($a), bin2hex($b));
    assert_equals(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($a));
});

test('KDF differs with different salt', function () use ($ops, $mem) {
    $kdf = new KeyDerivationService();
    $a = $kdf->deriveKey('pw', null, $kdf->generateSalt(), $ops, $mem);
    $b = $kdf->deriveKey('pw', null, $kdf->generateSalt(), $ops, $mem);
    assert_true(bin2hex($a) !== bin2hex($b));
});

test('KDF differs with key file material', function () use ($ops, $mem) {
    $kdf = new KeyDerivationService();
    $salt = $kdf->generateSalt();
    $a = $kdf->deriveKey('pw', null, $salt, $ops, $mem);
    $b = $kdf->deriveKey('pw', 'key-file-material', $salt, $ops, $mem);
    assert_true(bin2hex($a) !== bin2hex($b));
});

test('invalid salt length is rejected', function () use ($ops, $mem) {
    $kdf = new KeyDerivationService();
    assert_throws(fn () => $kdf->deriveKey('pw', null, 'short', $ops, $mem));
});

test('vault key wrap/unwrap round trip', function () use ($ops, $mem) {
    $service = new VaultKeyService(new CryptoService(), new KeyDerivationService());
    $vaultKey = $service->generateVaultKey();
    $wrapped = $service->wrapVaultKey($vaultKey, 'master', null, $ops, $mem);

    $vault = [
        'salt' => $wrapped['salt'],
        'encrypted_vault_key' => $wrapped['encrypted_vault_key'],
        'vault_key_nonce' => $wrapped['vault_key_nonce'],
        'kdf_ops_limit' => $wrapped['kdf_ops_limit'],
        'kdf_mem_limit' => $wrapped['kdf_mem_limit'],
    ];
    $unwrapped = $service->unwrapVaultKey($vault, 'master', null);
    assert_equals(bin2hex($vaultKey), bin2hex($unwrapped));
});

test('vault key unwrap fails with wrong master password', function () use ($ops, $mem) {
    $service = new VaultKeyService(new CryptoService(), new KeyDerivationService());
    $vaultKey = $service->generateVaultKey();
    $wrapped = $service->wrapVaultKey($vaultKey, 'correct', null, $ops, $mem);
    $vault = [
        'salt' => $wrapped['salt'],
        'encrypted_vault_key' => $wrapped['encrypted_vault_key'],
        'vault_key_nonce' => $wrapped['vault_key_nonce'],
        'kdf_ops_limit' => $wrapped['kdf_ops_limit'],
        'kdf_mem_limit' => $wrapped['kdf_mem_limit'],
    ];
    assert_throws(fn () => $service->unwrapVaultKey($vault, 'wrong', null));
});

test('master password change re-wraps same vault key', function () use ($ops, $mem) {
    $service = new VaultKeyService(new CryptoService(), new KeyDerivationService());
    $vaultKey = $service->generateVaultKey();
    $old = $service->wrapVaultKey($vaultKey, 'old-master', null, $ops, $mem);

    // Unwrap with old, re-wrap with new.
    $vault = [
        'salt' => $old['salt'],
        'encrypted_vault_key' => $old['encrypted_vault_key'],
        'vault_key_nonce' => $old['vault_key_nonce'],
        'kdf_ops_limit' => $old['kdf_ops_limit'],
        'kdf_mem_limit' => $old['kdf_mem_limit'],
    ];
    $raw = $service->unwrapVaultKey($vault, 'old-master', null);
    $new = $service->rewrapVaultKey($raw, 'new-master', null, $ops, $mem);

    // New salt must differ; the underlying vault key must be unchanged.
    assert_true($new['salt'] !== $old['salt'], 'Salt should be rotated');
    $newVault = [
        'salt' => $new['salt'],
        'encrypted_vault_key' => $new['encrypted_vault_key'],
        'vault_key_nonce' => $new['vault_key_nonce'],
        'kdf_ops_limit' => $new['kdf_ops_limit'],
        'kdf_mem_limit' => $new['kdf_mem_limit'],
    ];
    $unwrapped = $service->unwrapVaultKey($newVault, 'new-master', null);
    assert_equals(bin2hex($vaultKey), bin2hex($unwrapped));
});
