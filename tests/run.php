<?php

declare(strict_types=1);

/**
 * Test entry point: php tests/run.php  (or composer test)
 */

$basePath = dirname(__DIR__);

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

// Boot config so config() works (no session under CLI).
(new \SimpleVault\Core\App($basePath))->boot();

require __DIR__ . '/TestCase.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

exit(TestRunner::run());
