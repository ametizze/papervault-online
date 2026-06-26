<?php

declare(strict_types=1);

namespace SimpleVault\Crypto;

use RuntimeException;

/**
 * Manages the Vault Key envelope.
 *
 * The Vault Key is a random 32-byte secretbox key that encrypts all entries
 * and notes. It is itself encrypted ("wrapped") with a Key Encryption Key
 * derived from the Master Password (+ optional Key File). This is envelope
 * encryption: changing the Master Password only re-wraps the Vault Key, so
 * existing records never need re-encryption.
 */
final class VaultKeyService
{
    public function __construct(
        private CryptoService $crypto,
        private KeyDerivationService $kdf,
    ) {
    }

    public function generateVaultKey(): string
    {
        return $this->crypto->generateKey();
    }

    /**
     * Wrap (encrypt) a raw Vault Key with the KEK derived from credentials.
     *
     * @return array{salt:string, encrypted_vault_key:string, vault_key_nonce:string, kdf_ops_limit:int, kdf_mem_limit:int}
     *         salt/encrypted/nonce are base64; this is what gets stored.
     */
    public function wrapVaultKey(
        string $rawVaultKey,
        string $masterPassword,
        ?string $keyFileMaterial,
        int $opsLimit,
        int $memLimit,
    ): array {
        $salt = $this->kdf->generateSalt();
        $kek = $this->kdf->deriveKey($masterPassword, $keyFileMaterial, $salt, $opsLimit, $memLimit);

        $wrapped = $this->crypto->encrypt($rawVaultKey, $kek);
        sodium_memzero($kek);

        return [
            'salt' => base64_encode($salt),
            'encrypted_vault_key' => $wrapped['ciphertext'],
            'vault_key_nonce' => $wrapped['nonce'],
            'kdf_ops_limit' => $opsLimit,
            'kdf_mem_limit' => $memLimit,
        ];
    }

    /**
     * Unwrap (decrypt) the Vault Key using stored vault parameters.
     *
     * @throws RuntimeException if the Master Password / Key File is wrong
     */
    public function unwrapVaultKey(
        array $vault,
        string $masterPassword,
        ?string $keyFileMaterial,
    ): string {
        $salt = base64_decode((string) $vault['salt'], true);
        if ($salt === false) {
            throw new RuntimeException('Stored salt is invalid.');
        }

        $kek = $this->kdf->deriveKey(
            $masterPassword,
            $keyFileMaterial,
            $salt,
            (int) $vault['kdf_ops_limit'],
            (int) $vault['kdf_mem_limit'],
        );

        try {
            $rawVaultKey = $this->crypto->decrypt(
                (string) $vault['encrypted_vault_key'],
                (string) $vault['vault_key_nonce'],
                $kek,
            );
        } finally {
            sodium_memzero($kek);
        }

        return $rawVaultKey;
    }

    /**
     * Re-wrap an already-decrypted Vault Key under a new Master Password.
     * Produces a fresh salt + nonce. Existing entries/notes are untouched.
     */
    public function rewrapVaultKey(
        string $rawVaultKey,
        string $newMasterPassword,
        ?string $keyFileMaterial,
        int $opsLimit,
        int $memLimit,
    ): array {
        return $this->wrapVaultKey($rawVaultKey, $newMasterPassword, $keyFileMaterial, $opsLimit, $memLimit);
    }
}
