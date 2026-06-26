<?php

declare(strict_types=1);

namespace SimpleVault\Models;

/**
 * A decrypted Markdown note, combining plain metadata with the decrypted
 * payload (title, client, project, tags, markdown body).
 */
final class Note
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

    public function client(): string
    {
        return (string) ($this->payload['client'] ?? '');
    }

    public function project(): string
    {
        return (string) ($this->payload['project'] ?? '');
    }

    public function markdown(): string
    {
        return (string) ($this->payload['markdown'] ?? '');
    }

    /** @return array<int,string> */
    public function tags(): array
    {
        $tags = $this->payload['tags'] ?? [];

        return is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];
    }
}
