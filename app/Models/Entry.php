<?php

declare(strict_types=1);

namespace SimpleVault\Models;

/**
 * A decrypted password entry, combining plain metadata with the decrypted
 * payload. Instances only exist after the vault is unlocked.
 */
final class Entry
{
    public function __construct(
        public readonly string $uuid,
        public readonly bool $favorite,
        public readonly bool $archived,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        /** @var array<string,mixed> */
        public readonly array $payload,
    ) {
    }

    public static function fromRow(array $row, array $payload): self
    {
        return new self(
            uuid: (string) $row['uuid'],
            favorite: (bool) $row['favorite'],
            archived: (bool) $row['archived'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
            payload: $payload,
        );
    }

    public function title(): string
    {
        return (string) ($this->payload['title'] ?? '(untitled)');
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->payload[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /** @return array<int,string> */
    public function tags(): array
    {
        $tags = $this->payload['tags'] ?? [];

        return is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];
    }
}
