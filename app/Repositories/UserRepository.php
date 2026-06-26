<?php

declare(strict_types=1);

namespace SimpleVault\Repositories;

use PDO;
use SimpleVault\Core\App;

final class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? App::db();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $email, string $passwordHash): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, created_at, updated_at)
             VALUES (:email, :hash, :now, :now)'
        );
        $stmt->execute([
            ':email' => strtolower(trim($email)),
            ':hash' => $passwordHash,
            ':now' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :hash, updated_at = :now WHERE id = :id'
        );
        $stmt->execute([':hash' => $passwordHash, ':now' => now_iso(), ':id' => $userId]);
    }
}
