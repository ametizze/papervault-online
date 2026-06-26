<?php

declare(strict_types=1);

namespace SimpleVault\Repositories;

use PDO;
use SimpleVault\Core\App;

final class EntryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? App::db();
    }

    /**
     * @return array<int, array>
     */
    public function allForUser(int $userId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM entries WHERE user_id = :id';
        if (!$includeArchived) {
            $sql .= ' AND archived = 0';
        }
        $sql .= ' ORDER BY favorite DESC, updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $userId, string $uuid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM entries WHERE user_id = :id AND uuid = :uuid LIMIT 1');
        $stmt->execute([':id' => $userId, ':uuid' => $uuid]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $userId, string $uuid, string $encryptedPayload, string $nonce, bool $favorite): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare(
            'INSERT INTO entries (user_id, uuid, encrypted_payload, payload_nonce, favorite, archived, created_at, updated_at)
             VALUES (:user_id, :uuid, :enc, :nonce, :fav, 0, :now, :now)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':uuid' => $uuid,
            ':enc' => $encryptedPayload,
            ':nonce' => $nonce,
            ':fav' => $favorite ? 1 : 0,
            ':now' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $userId, string $uuid, string $encryptedPayload, string $nonce, bool $favorite): void
    {
        $stmt = $this->db->prepare(
            'UPDATE entries SET encrypted_payload = :enc, payload_nonce = :nonce, favorite = :fav, updated_at = :now
             WHERE user_id = :user_id AND uuid = :uuid'
        );
        $stmt->execute([
            ':enc' => $encryptedPayload,
            ':nonce' => $nonce,
            ':fav' => $favorite ? 1 : 0,
            ':now' => now_iso(),
            ':user_id' => $userId,
            ':uuid' => $uuid,
        ]);
    }

    public function setArchived(int $userId, string $uuid, bool $archived): void
    {
        $stmt = $this->db->prepare(
            'UPDATE entries SET archived = :archived, updated_at = :now WHERE user_id = :user_id AND uuid = :uuid'
        );
        $stmt->execute([
            ':archived' => $archived ? 1 : 0,
            ':now' => now_iso(),
            ':user_id' => $userId,
            ':uuid' => $uuid,
        ]);
    }

    public function delete(int $userId, string $uuid): void
    {
        $stmt = $this->db->prepare('DELETE FROM entries WHERE user_id = :user_id AND uuid = :uuid');
        $stmt->execute([':user_id' => $userId, ':uuid' => $uuid]);
    }

    /**
     * Bulk insert used by import "replace" / "merge" flows.
     */
    public function insertRaw(int $userId, array $row): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO entries (user_id, uuid, encrypted_payload, payload_nonce, favorite, archived, created_at, updated_at)
             VALUES (:user_id, :uuid, :enc, :nonce, :fav, :arch, :created, :updated)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':uuid' => $row['uuid'],
            ':enc' => $row['encrypted_payload'],
            ':nonce' => $row['payload_nonce'],
            ':fav' => (int) ($row['favorite'] ?? 0),
            ':arch' => (int) ($row['archived'] ?? 0),
            ':created' => $row['created_at'] ?? now_iso(),
            ':updated' => $row['updated_at'] ?? now_iso(),
        ]);
    }

    public function deleteAllForUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM entries WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
    }

    public function existsByUuid(int $userId, string $uuid): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM entries WHERE user_id = :id AND uuid = :uuid LIMIT 1');
        $stmt->execute([':id' => $userId, ':uuid' => $uuid]);

        return $stmt->fetchColumn() !== false;
    }
}
