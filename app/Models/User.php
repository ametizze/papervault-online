<?php

declare(strict_types=1);

namespace SimpleVault\Models;

/**
 * Authenticated user (non-sensitive fields only). The password hash is never
 * exposed through this model.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            email: (string) $row['email'],
            createdAt: (string) $row['created_at'],
        );
    }
}
