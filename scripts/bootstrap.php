<?php

declare(strict_types=1);

/**
 * Shared CLI bootstrap: loads autoloading + env + config so scripts can use
 * the same App container as the web entry point.
 */

$basePath = dirname(__DIR__);

$composerAutoload = $basePath . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require $basePath . '/app/helpers.php';
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'SimpleVault\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $basePath . '/app/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$app = new \SimpleVault\Core\App($basePath);
$app->boot();

return $basePath;
