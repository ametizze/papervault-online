<?php

declare(strict_types=1);

/**
 * Apply the database schema.
 *
 * Usage: php scripts/migrate.php
 *
 * For SQLite, creates the database file if needed and applies schema.sql.
 * For MySQL, translates the SQLite dialect to MySQL on the fly.
 */

$basePath = require __DIR__ . '/bootstrap.php';

use SimpleVault\Core\App;

$connection = (string) App::config('db_connection', 'sqlite');
$schema = file_get_contents($basePath . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read schema.sql\n");
    exit(1);
}

if ($connection === 'sqlite') {
    $dbPath = (string) App::config('db_database');
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!file_exists($dbPath)) {
        touch($dbPath);
        @chmod($dbPath, 0640);
    }
} else {
    // Minimal SQLite -> MySQL translation for the schema statements.
    $schema = str_replace(
        'INTEGER PRIMARY KEY AUTOINCREMENT',
        'INT AUTO_INCREMENT PRIMARY KEY',
        $schema
    );
}

$pdo = App::db();
$pdo->exec($schema);

echo "Migration complete ({$connection}).\n";
