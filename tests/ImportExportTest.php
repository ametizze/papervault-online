<?php

declare(strict_types=1);

use SimpleVault\Crypto\CryptoService;
use SimpleVault\Crypto\KeyDerivationService;
use SimpleVault\Crypto\VaultKeyService;
use SimpleVault\Repositories\EntryRepository;
use SimpleVault\Repositories\NoteRepository;
use SimpleVault\Repositories\UserRepository;
use SimpleVault\Repositories\VaultRepository;
use SimpleVault\Support\BackupService;

/**
 * Seed an in-memory DB with one user + vault + one entry + one note.
 *
 * @return array{pdo: PDO, userId: int}
 */
function seed_vault(): array
{
    $pdo = test_db();
    $users = new UserRepository($pdo);
    $vaults = new VaultRepository($pdo);
    $entries = new EntryRepository($pdo);
    $notes = new NoteRepository($pdo);

    $userId = $users->create('user@example.com', password_hash('account-pass', PASSWORD_ARGON2ID));

    $crypto = new CryptoService();
    $vaultKeyService = new VaultKeyService($crypto, new KeyDerivationService());
    $rawVaultKey = $vaultKeyService->generateVaultKey();
    $wrapped = $vaultKeyService->wrapVaultKey(
        $rawVaultKey,
        'master-pass',
        null,
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );
    $vaults->create($userId, $wrapped, false);

    $entryEnc = $crypto->encryptJson(['title' => 'GitHub', 'password' => 'secret'], $rawVaultKey);
    $entries->create($userId, \SimpleVault\Core\Uuid::v4(), $entryEnc['ciphertext'], $entryEnc['nonce'], false);

    $noteEnc = $crypto->encryptJson(['title' => 'Note', 'markdown' => '# Hi'], $rawVaultKey);
    $notes->create($userId, \SimpleVault\Core\Uuid::v4(), $noteEnc['ciphertext'], $noteEnc['nonce'], false);

    return ['pdo' => $pdo, 'userId' => $userId];
}

test('backup export produces a valid structure', function () {
    ['pdo' => $pdo, 'userId' => $userId] = seed_vault();
    $backup = new BackupService(new VaultRepository($pdo), new EntryRepository($pdo), new NoteRepository($pdo));
    $data = $backup->export($userId);

    assert_equals('SimpleVault', $data['app']);
    assert_equals(1, $data['version']);
    assert_equals(1, count($data['payload']['entries']));
    assert_equals(1, count($data['payload']['notes']));

    // Should validate without throwing.
    $backup->validate($data);
    assert_true(true);
});

test('backup round trips through JSON', function () {
    ['pdo' => $pdo, 'userId' => $userId] = seed_vault();
    $backup = new BackupService(new VaultRepository($pdo), new EntryRepository($pdo), new NoteRepository($pdo));
    $data = $backup->export($userId);
    $json = $backup->toJson($data);
    $decoded = json_decode($json, true);
    $backup->validate($decoded);
    assert_true(true);
});

test('validate rejects wrong app name', function () {
    $backup = new BackupService();
    assert_throws(fn () => $backup->validate(['app' => 'Other', 'version' => 1, 'payload' => []]));
});

test('validate rejects bad nonce length', function () {
    ['pdo' => $pdo, 'userId' => $userId] = seed_vault();
    $backup = new BackupService(new VaultRepository($pdo), new EntryRepository($pdo), new NoteRepository($pdo));
    $data = $backup->export($userId);
    $data['payload']['vault_key_nonce'] = base64_encode('too-short');
    assert_throws(fn () => $backup->validate($data));
});

test('exported backup can be decrypted with master password', function () {
    ['pdo' => $pdo, 'userId' => $userId] = seed_vault();
    $backup = (new BackupService(new VaultRepository($pdo), new EntryRepository($pdo), new NoteRepository($pdo)))
        ->export($userId);

    $payload = $backup['payload'];
    $vaultKeyService = new VaultKeyService(new CryptoService(), new KeyDerivationService());
    $vaultKey = $vaultKeyService->unwrapVaultKey([
        'salt' => $payload['salt'],
        'encrypted_vault_key' => $payload['encrypted_vault_key'],
        'vault_key_nonce' => $payload['vault_key_nonce'],
        'kdf_ops_limit' => $payload['kdf_ops_limit'],
        'kdf_mem_limit' => $payload['kdf_mem_limit'],
    ], 'master-pass', null);

    $crypto = new CryptoService();
    $entry = $payload['entries'][0];
    $decoded = $crypto->decryptJson($entry['encrypted_payload'], $entry['payload_nonce'], $vaultKey);
    assert_equals('GitHub', $decoded['title']);
});
