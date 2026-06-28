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

    /**
     * Extra named secrets grouped under this entry (e.g. mysql / redis / ssh
     * passwords for a single server or project). Each one is individually
     * copyable and optionally masked.
     *
     * @return list<array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:?string,updatedAt:?string}>
     */
    public function fields(): array
    {
        return self::normalizeFields($this->payload['fields'] ?? []);
    }

    /**
     * Canonicalize the raw "fields" payload into a stable shape, tolerating
     * older records: pre-rename entries stored the name under "label" and had
     * no id/observation/timestamps. Rows with no name, value or observation
     * are dropped.
     *
     * @return list<array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:?string,updatedAt:?string}>
     */
    public static function normalizeFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $out = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? $field['label'] ?? '');
            $value = (string) ($field['value'] ?? '');
            $observation = (string) ($field['observation'] ?? '');
            if ($name === '' && $value === '' && $observation === '') {
                continue;
            }
            $out[] = [
                'id' => (isset($field['id']) && is_string($field['id'])) ? $field['id'] : '',
                'name' => $name,
                'value' => $value,
                'secret' => !empty($field['secret']),
                'observation' => $observation,
                'createdAt' => isset($field['createdAt']) ? (string) $field['createdAt'] : null,
                'updatedAt' => isset($field['updatedAt']) ? (string) $field['updatedAt'] : null,
            ];
        }

        return $out;
    }
}
