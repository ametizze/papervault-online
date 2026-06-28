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

    /** Field value types that drive how a custom field is rendered. */
    public const FIELD_TYPES = ['text', 'password', 'url', 'email', 'totp'];

    /**
     * Canonicalize the raw "fields" payload into a stable shape, tolerating
     * older records: pre-rename entries stored the name under "label" and had
     * no id/type/observation/timestamps. Rows with no name, value or
     * observation are dropped.
     *
     * @return list<array{id:string,name:string,type:string,value:string,secret:bool,observation:string,expiresAt:?string,createdAt:?string,updatedAt:?string,history:list<array{value:string,at:string}>}>
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
            $type = (string) ($field['type'] ?? 'text');
            if (!in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }
            // password and totp values are always treated as secret.
            $secret = !empty($field['secret']) || in_array($type, ['password', 'totp'], true);
            $expiresAt = isset($field['expiresAt']) && (string) $field['expiresAt'] !== ''
                ? (string) $field['expiresAt']
                : null;

            $history = [];
            if (isset($field['history']) && is_array($field['history'])) {
                foreach ($field['history'] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $history[] = [
                        'value' => (string) ($entry['value'] ?? ''),
                        'at' => (string) ($entry['at'] ?? ''),
                    ];
                }
            }

            $out[] = [
                'id' => (isset($field['id']) && is_string($field['id'])) ? $field['id'] : '',
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'secret' => $secret,
                'observation' => $observation,
                'expiresAt' => $expiresAt,
                'createdAt' => isset($field['createdAt']) ? (string) $field['createdAt'] : null,
                'updatedAt' => isset($field['updatedAt']) ? (string) $field['updatedAt'] : null,
                'history' => $history,
            ];
        }

        return $out;
    }
}
