<?php

declare(strict_types=1);

/**
 * Delete encrypted backups older than a retention window.
 *
 * Usage: php scripts/rotate-backups.php [days]   (default: 30)
 */

$basePath = require __DIR__ . '/bootstrap.php';

$days = isset($argv[1]) ? max(1, (int) $argv[1]) : 30;
$cutoff = time() - ($days * 86400);
$dir = $basePath . '/storage/backups';

$deleted = 0;
foreach (glob($dir . '/*.json') ?: [] as $file) {
    if (filemtime($file) < $cutoff) {
        @unlink($file);
        $deleted++;
    }
}

echo "Removed {$deleted} backup(s) older than {$days} day(s).\n";
