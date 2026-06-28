<?php

declare(strict_types=1);

namespace SimpleVault\Models;

/**
 * A decrypted Markdown note, combining plain metadata with the decrypted
 * payload (title, client, project, tags, markdown body).
 */
final class Note
{
    /** Ticket workflow states (value => label). Empty status means "plain note". */
    public const STATUSES = [
        'open' => 'Open',
        'in-progress' => 'In progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

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

    /** Ticket number or external reference, if any. */
    public function ticket(): string
    {
        return (string) ($this->payload['ticket'] ?? '');
    }

    /** Workflow status key, or '' for a plain note. Unknown values normalize to ''. */
    public function status(): string
    {
        $status = (string) ($this->payload['status'] ?? '');

        return isset(self::STATUSES[$status]) ? $status : '';
    }

    /** Human label for the current status, or '' when none. */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status()] ?? '';
    }

    /** Due/expiry date (yyyy-mm-dd), or null. */
    public function expiresAt(): ?string
    {
        $value = (string) ($this->payload['expiresAt'] ?? '');

        return $value !== '' ? $value : null;
    }

    /** @return array<int,string> */
    public function tags(): array
    {
        $tags = $this->payload['tags'] ?? [];

        return is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];
    }
}
