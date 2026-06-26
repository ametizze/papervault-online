<?php

declare(strict_types=1);

namespace SimpleVault\Crypto;

use RuntimeException;

/**
 * Generates and validates optional Key Files.
 *
 * A Key File holds high-entropy random bytes (base64) inside a small JSON
 * envelope. Its contents are NEVER stored in the database or on the server.
 * The user keeps the file; it is uploaded transiently during vault unlock.
 */
final class KeyFileService
{
    private const TYPE = 'simplevault-keyfile';
    private const VERSION = 1;
    private const KEY_MATERIAL_BYTES = 32;
    private const MAX_FILE_BYTES = 8192;

    /**
     * Generate a new Key File structure (ready to JSON-encode and download).
     *
     * @return array{type:string,version:int,created_at:string,key_material:string}
     */
    public function generate(): array
    {
        return [
            'type' => self::TYPE,
            'version' => self::VERSION,
            'created_at' => now_iso(),
            'key_material' => base64_encode(random_bytes(self::KEY_MATERIAL_BYTES)),
        ];
    }

    public function toJson(array $keyFile): string
    {
        return json_encode($keyFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Validate and extract the raw key material from uploaded JSON content.
     *
     * @throws RuntimeException if the structure is invalid
     */
    public function extractMaterial(string $jsonContent): string
    {
        if (strlen($jsonContent) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Key File is too large.');
        }

        try {
            $data = json_decode($jsonContent, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Key File is not valid JSON.');
        }

        if (!is_array($data)) {
            throw new RuntimeException('Key File structure is invalid.');
        }

        if (($data['type'] ?? null) !== self::TYPE) {
            throw new RuntimeException('Key File type is invalid.');
        }

        if ((int) ($data['version'] ?? 0) !== self::VERSION) {
            throw new RuntimeException('Key File version is unsupported.');
        }

        $materialB64 = $data['key_material'] ?? null;
        if (!is_string($materialB64) || $materialB64 === '') {
            throw new RuntimeException('Key File material is missing.');
        }

        $material = base64_decode($materialB64, true);
        if ($material === false || strlen($material) < 16) {
            throw new RuntimeException('Key File material is invalid.');
        }

        return $material;
    }
}
