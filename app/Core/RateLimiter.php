<?php

declare(strict_types=1);

namespace SimpleVault\Core;

use PDO;

/**
 * Database-backed login rate limiter keyed by (ip_address, email).
 */
final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Returns true if the (ip, email) pair is currently locked out.
     */
    public function tooManyAttempts(string $ip, string $email): bool
    {
        $max = (int) App::config('login_max_attempts', 5);
        $window = (int) App::config('login_lockout_minutes', 15) * 60;

        $row = $this->find($ip, $email);
        if ($row === null) {
            return false;
        }

        $lastAttempt = strtotime((string) $row['last_attempt_at']);
        if ($lastAttempt !== false && (time() - $lastAttempt) > $window) {
            // Window expired; reset.
            $this->reset($ip, $email);
            return false;
        }

        return (int) $row['attempts'] >= $max;
    }

    public function recordFailure(string $ip, string $email): void
    {
        $row = $this->find($ip, $email);
        $now = now_iso();

        if ($row === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO login_attempts (ip_address, email, attempts, last_attempt_at)
                 VALUES (:ip, :email, 1, :now)'
            );
            $stmt->execute([':ip' => $ip, ':email' => $email, ':now' => $now]);
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE login_attempts SET attempts = attempts + 1, last_attempt_at = :now WHERE id = :id'
        );
        $stmt->execute([':now' => $now, ':id' => $row['id']]);
    }

    public function reset(string $ip, string $email): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts WHERE ip_address = :ip AND (email = :email OR email IS NULL)'
        );
        $stmt->execute([':ip' => $ip, ':email' => $email]);
    }

    public function secondsUntilRetry(string $ip, string $email): int
    {
        $window = (int) App::config('login_lockout_minutes', 15) * 60;
        $row = $this->find($ip, $email);
        if ($row === null) {
            return 0;
        }
        $lastAttempt = strtotime((string) $row['last_attempt_at']) ?: time();

        return max(0, $window - (time() - $lastAttempt));
    }

    private function find(string $ip, string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM login_attempts WHERE ip_address = :ip AND email = :email LIMIT 1'
        );
        $stmt->execute([':ip' => $ip, ':email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
