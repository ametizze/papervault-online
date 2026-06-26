<?php

declare(strict_types=1);

/**
 * Write an encrypted full-vault backup to storage/backups for every user.
 *
 * Usage: php scripts/backup.php
 *
 * The output contains only encrypted data; it is safe to copy off-server.
 * Intended to be run from cron (see docs/DEPLOYMENT.md).
 */

$basePath = require __DIR__ . '/bootstrap.php';

use SimpleVault\Core\App;
use SimpleVault\Support\BackupService;

$pdo = App::db();
$userIds = $pdo->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN);

if ($userIds === []) {
    echo "No users to back up.\n";
    exit(0);
}

$service = new BackupService();
$dir = $basePath . '/storage/backups';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

foreach ($userIds as $userId) {
    $data = $service->export((int) $userId);
    $json = $service->toJson($data);
    $file = $dir . '/cron-' . (int) $userId . '-' . date('Y-m-d-His') . '.json';
    file_put_contents($file, $json, LOCK_EX);
    @chmod($file, 0640);
    echo "Wrote {$file}\n";
}
