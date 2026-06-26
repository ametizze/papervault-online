<?php

declare(strict_types=1);

namespace SimpleVault\Core;

use PDO;
use Throwable;

/**
 * Application container and bootstrap.
 *
 * Loads environment + config, opens the database connection, registers error
 * handling, and dispatches the incoming request through the router.
 */
final class App
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public function __construct(private string $basePath)
    {
    }

    /**
     * Boot the framework: env, config, error handling, session.
     */
    public function boot(): void
    {
        $this->loadEnv($this->basePath . '/.env');
        self::$config = require $this->basePath . '/config/app.php';

        $this->registerErrorHandling();
        Session::start();
    }

    /**
     * Dispatch the current request through the given router.
     */
    public function run(Router $router): void
    {
        try {
            $request = Request::capture();
            $response = $router->dispatch($request);
            $response->send();
        } catch (Throwable $e) {
            $this->renderException($e);
        }
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Shared PDO connection (lazy).
     */
    public static function db(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $connection = self::config('db_connection', 'sqlite');

        if ($connection === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                self::config('db_host'),
                self::config('db_port'),
                self::config('db_name')
            );
            $pdo = new PDO($dsn, self::config('db_user'), self::config('db_pass'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            $path = self::config('db_database');
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        }

        self::$pdo = $pdo;

        return self::$pdo;
    }

    /**
     * Minimal .env parser (avoids requiring vlucas/phpdotenv for the MVP).
     * Lines are KEY=VALUE; quotes are stripped; # starts a comment.
     */
    private function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
            }
            $_ENV[$key] ??= $value;
            $_SERVER[$key] ??= $value;
        }
    }

    private function registerErrorHandling(): void
    {
        $debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $this->basePath . '/storage/logs/php-error.log');

        set_exception_handler(function (Throwable $e): void {
            $this->renderException($e);
        });
    }

    private function renderException(Throwable $e): void
    {
        // Log a sanitized message. Never include request bodies or secrets.
        Logger::error('Unhandled exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $debug = (bool) self::config('app_debug', false);
        http_response_code(500);

        if ($debug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "Error: " . $e->getMessage() . "\n";
            echo $e->getFile() . ':' . $e->getLine() . "\n\n";
            echo $e->getTraceAsString() . "\n";
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        $view = base_path('app/Views/errors/500.php');
        if (is_file($view)) {
            require $view;
        } else {
            echo 'An unexpected error occurred.';
        }
    }
}
