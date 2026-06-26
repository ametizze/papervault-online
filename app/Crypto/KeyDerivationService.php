<?php

declare(strict_types=1);

namespace SimpleVault\Crypto;

use RuntimeException;

/**
 * Derives a Key Encryption Key (KEK) from the Master Password plus optional
 * Key File material using libsodium's Argon2id (sodium_crypto_pwhash).
 *
 * The KEK is never stored. It is derived on demand to unwrap the Vault Key.
 */
final class KeyDerivationService
{
    /**
     * Derive the KEK.
     *
     * @param string      $masterPassword  the user's Master Password
     * @param string|null $keyFileMaterial raw key file material, or null
     * @param string      $salt            raw salt (SODIUM_CRYPTO_PWHASH_SALTBYTES)
     */
    public function deriveKey(
        string $masterPassword,
        ?string $keyFileMaterial,
        string $salt,
        int $opsLimit,
        int $memLimit,
    ): string {
        if (strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new RuntimeException('Invalid salt length.');
        }

        $secret = $this->combineSecret($masterPassword, $keyFileMaterial);

        $kek = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $secret,
            $salt,
            $opsLimit,
            $memLimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        sodium_memzero($secret);

        return $kek;
    }

    public function generateSalt(): string
    {
        return random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    }

    /**
     * Combine the Master Password with optional Key File material.
     *
     * The key file material is hashed first so its length/format cannot bias
     * the combination, then concatenated with a delimiter.
     */
    private function combineSecret(string $masterPassword, ?string $keyFileMaterial): string
    {
        if ($keyFileMaterial === null || $keyFileMaterial === '') {
            return $masterPassword;
        }

        $keyFileHash = sodium_crypto_generichash($keyFileMaterial);

        return $masterPassword . ':' . sodium_bin2hex($keyFileHash);
    }
}
