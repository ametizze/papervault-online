<?php

declare(strict_types=1);

namespace SimpleVault\Repositories;

use PDO;
use SimpleVault\Core\App;

final class VaultRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? App::db();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vaults WHERE user_id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{salt:string,encrypted_vault_key:string,vault_key_nonce:string,kdf_ops_limit:int,kdf_mem_limit:int} $wrapped
     */
    public function create(int $userId, array $wrapped, bool $keyFileRequired): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare(
            'INSERT INTO vaults
                (user_id, salt, encrypted_vault_key, vault_key_nonce, kdf_ops_limit, kdf_mem_limit, key_file_required, created_at, updated_at)
             VALUES
                (:user_id, :salt, :enc, :nonce, :ops, :mem, :kfr, :now, :now)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':salt' => $wrapped['salt'],
            ':enc' => $wrapped['encrypted_vault_key'],
            ':nonce' => $wrapped['vault_key_nonce'],
            ':ops' => $wrapped['kdf_ops_limit'],
            ':mem' => $wrapped['kdf_mem_limit'],
            ':kfr' => $keyFileRequired ? 1 : 0,
            ':now' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update the wrapped vault key (used by Master Password change / key file).
     */
    public function updateWrappedKey(int $userId, array $wrapped, bool $keyFileRequired): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vaults SET
                salt = :salt,
                encrypted_vault_key = :enc,
                vault_key_nonce = :nonce,
                kdf_ops_limit = :ops,
                kdf_mem_limit = :mem,
                key_file_required = :kfr,
                updated_at = :now
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            ':salt' => $wrapped['salt'],
            ':enc' => $wrapped['encrypted_vault_key'],
            ':nonce' => $wrapped['vault_key_nonce'],
            ':ops' => $wrapped['kdf_ops_limit'],
            ':mem' => $wrapped['kdf_mem_limit'],
            ':kfr' => $keyFileRequired ? 1 : 0,
            ':now' => now_iso(),
            ':user_id' => $userId,
        ]);
    }
}
