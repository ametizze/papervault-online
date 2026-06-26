<?php

declare(strict_types=1);

namespace SimpleVault\Repositories;

use PDO;
use SimpleVault\Core\App;

/**
 * Append-only audit log of security-relevant events.
 *
 * Only event TYPES and non-sensitive request metadata are stored. Never pass
 * secrets, passwords, decrypted payloads, or master/vault keys here.
 */
final class AuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? App::db();
    }

    public function log(?int $userId, string $eventType, ?string $ip = null, ?string $userAgent = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, event_type, ip_address, user_agent, created_at)
             VALUES (:user_id, :event, :ip, :ua, :now)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':event' => $eventType,
            ':ip' => $ip,
            ':ua' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            ':now' => now_iso(),
        ]);
    }

    /**
     * @return array<int, array>
     */
    public function recent(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM audit_logs WHERE user_id = :id ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
