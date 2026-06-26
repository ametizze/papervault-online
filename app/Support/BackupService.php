<?php

declare(strict_types=1);

namespace SimpleVault\Support;

use RuntimeException;
use SimpleVault\Repositories\EntryRepository;
use SimpleVault\Repositories\NoteRepository;
use SimpleVault\Repositories\VaultRepository;

/**
 * Builds and validates the encrypted full-vault backup format.
 *
 * The backup contains the vault envelope (encrypted vault key + KDF params)
 * plus the already-encrypted entry/note rows. Because everything sensitive is
 * encrypted under the Vault Key (which is itself wrapped by the Master
 * Password), the backup contains NO plaintext secrets.
 */
final class BackupService
{
    private const APP = 'SimpleVault';
    private const VERSION = 1;
    private const EXPECTED_NONCE_LEN = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    public function __construct(
        private VaultRepository $vaults = new VaultRepository(),
        private EntryRepository $entries = new EntryRepository(),
        private NoteRepository $notes = new NoteRepository(),
    ) {
    }

    /**
     * Build the encrypted backup array for a user.
     */
    public function export(int $userId): array
    {
        $vault = $this->vaults->findByUserId($userId);
        if ($vault === null) {
            throw new RuntimeException('Vault not found.');
        }

        return [
            'app' => self::APP,
            'version' => self::VERSION,
            'exported_at' => now_iso(),
            'crypto' => [
                'algorithm' => 'sodium_crypto_secretbox',
                'kdf' => 'sodium_crypto_pwhash',
            ],
            'payload' => [
                'encrypted_vault_key' => $vault['encrypted_vault_key'],
                'vault_key_nonce' => $vault['vault_key_nonce'],
                'salt' => $vault['salt'],
                'kdf_ops_limit' => (int) $vault['kdf_ops_limit'],
                'kdf_mem_limit' => (int) $vault['kdf_mem_limit'],
                'key_file_required' => (int) $vault['key_file_required'],
                'entries' => $this->mapRecords($this->entries->allForUser($userId, true)),
                'notes' => $this->mapRecords($this->notes->allForUser($userId, true)),
            ],
        ];
    }

    public function toJson(array $backup): string
    {
        return json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Validate the structure of a decoded backup. Throws on any problem.
     */
    public function validate(array $backup): void
    {
        if (($backup['app'] ?? null) !== self::APP) {
            throw new RuntimeException('Unrecognized backup file.');
        }
        if ((int) ($backup['version'] ?? 0) !== self::VERSION) {
            throw new RuntimeException('Unsupported backup version.');
        }

        $payload = $backup['payload'] ?? null;
        if (!is_array($payload)) {
            throw new RuntimeException('Backup payload is missing.');
        }

        foreach (['encrypted_vault_key', 'vault_key_nonce', 'salt'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || $payload[$field] === '') {
                throw new RuntimeException("Backup payload missing field: $field.");
            }
            if (base64_decode($payload[$field], true) === false) {
                throw new RuntimeException("Backup field is not valid base64: $field.");
            }
        }

        $this->assertNonceLength((string) $payload['vault_key_nonce'], 'vault key');

        $salt = base64_decode((string) $payload['salt'], true);
        if ($salt === false || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new RuntimeException('Backup salt has an invalid length.');
        }

        if (!is_int($payload['kdf_ops_limit'] ?? null) && !ctype_digit((string) ($payload['kdf_ops_limit'] ?? 'x'))) {
            throw new RuntimeException('Backup KDF ops limit is invalid.');
        }
        if (!is_int($payload['kdf_mem_limit'] ?? null) && !ctype_digit((string) ($payload['kdf_mem_limit'] ?? 'x'))) {
            throw new RuntimeException('Backup KDF mem limit is invalid.');
        }

        $this->validateRecords($payload['entries'] ?? [], 'entry');
        $this->validateRecords($payload['notes'] ?? [], 'note');
    }

    /**
     * @return array<int, array{uuid:string,encrypted_payload:string,payload_nonce:string,favorite:int,archived:int,created_at:string,updated_at:string}>
     */
    private function mapRecords(array $rows): array
    {
        return array_map(static function (array $row): array {
            return [
                'uuid' => (string) $row['uuid'],
                'encrypted_payload' => (string) $row['encrypted_payload'],
                'payload_nonce' => (string) $row['payload_nonce'],
                'favorite' => (int) $row['favorite'],
                'archived' => (int) $row['archived'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }, $rows);
    }

    private function validateRecords(mixed $records, string $label): void
    {
        if (!is_array($records)) {
            throw new RuntimeException("Backup $label list is invalid.");
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new RuntimeException("Backup $label record is invalid.");
            }
            foreach (['uuid', 'encrypted_payload', 'payload_nonce'] as $field) {
                if (!isset($record[$field]) || !is_string($record[$field]) || $record[$field] === '') {
                    throw new RuntimeException("Backup $label record missing field: $field.");
                }
            }
            if (!\SimpleVault\Core\Uuid::isValid((string) $record['uuid'])) {
                throw new RuntimeException("Backup $label record has an invalid UUID.");
            }
            if (base64_decode((string) $record['encrypted_payload'], true) === false) {
                throw new RuntimeException("Backup $label payload is not valid base64.");
            }
            $this->assertNonceLength((string) $record['payload_nonce'], $label);
        }
    }

    private function assertNonceLength(string $nonceB64, string $label): void
    {
        $nonce = base64_decode($nonceB64, true);
        if ($nonce === false || strlen($nonce) !== self::EXPECTED_NONCE_LEN) {
            throw new RuntimeException("Backup $label nonce has an invalid length.");
        }
    }
}
