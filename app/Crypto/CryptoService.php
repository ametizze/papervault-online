<?php

declare(strict_types=1);

namespace SimpleVault\Crypto;

use RuntimeException;

/**
 * Symmetric authenticated encryption using libsodium secretbox
 * (XSalsa20-Poly1305).
 *
 * All methods operate on raw bytes for keys/nonces and return base64 strings
 * for storage. No custom cryptography is implemented here — only thin wrappers
 * around Sodium primitives.
 */
final class CryptoService
{
    /**
     * Encrypt a plaintext string with a raw 32-byte key.
     *
     * @return array{ciphertext:string, nonce:string} both base64 encoded
     */
    public function encrypt(string $plaintext, string $key): array
    {
        $this->assertKey($key);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        $result = [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
        ];

        sodium_memzero($plaintext);

        return $result;
    }

    /**
     * Decrypt base64 ciphertext + base64 nonce with a raw 32-byte key.
     *
     * @throws RuntimeException on authentication/decryption failure
     */
    public function decrypt(string $ciphertextB64, string $nonceB64, string $key): string
    {
        $this->assertKey($key);

        $ciphertext = $this->fromBase64($ciphertextB64);
        $nonce = $this->fromBase64($nonceB64);

        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid nonce length.');
        }

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            // Wrong key or tampered ciphertext. Do not leak which.
            throw new RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Encrypt an array payload as JSON.
     *
     * @return array{ciphertext:string, nonce:string}
     */
    public function encryptJson(array $payload, string $key): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->encrypt($json, $key);
    }

    /**
     * Decrypt and JSON-decode a payload.
     */
    public function decryptJson(string $ciphertextB64, string $nonceB64, string $key): array
    {
        $json = $this->decrypt($ciphertextB64, $nonceB64, $key);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        sodium_memzero($json);

        if (!is_array($data)) {
            throw new RuntimeException('Decrypted payload is not a valid object.');
        }

        return $data;
    }

    public function generateKey(): string
    {
        return random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private function assertKey(string $key): void
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Invalid key length.');
        }
    }

    private function fromBase64(string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 data.');
        }

        return $decoded;
    }
}
