<?php

declare(strict_types=1);

namespace SimpleVault\Models;

/**
 * Vault envelope metadata. Holds only the encrypted vault key and KDF
 * parameters — never the decrypted Vault Key or Master Password.
 */
final class Vault
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $salt,
        public readonly string $encryptedVaultKey,
        public readonly string $vaultKeyNonce,
        public readonly int $kdfOpsLimit,
        public readonly int $kdfMemLimit,
        public readonly bool $keyFileRequired,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            salt: (string) $row['salt'],
            encryptedVaultKey: (string) $row['encrypted_vault_key'],
            vaultKeyNonce: (string) $row['vault_key_nonce'],
            kdfOpsLimit: (int) $row['kdf_ops_limit'],
            kdfMemLimit: (int) $row['kdf_mem_limit'],
            keyFileRequired: (bool) $row['key_file_required'],
        );
    }
}
