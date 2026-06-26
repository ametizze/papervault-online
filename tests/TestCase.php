<?php

declare(strict_types=1);

/**
 * Tiny zero-dependency test harness. Tests register cases via test(); run.php
 * executes them and prints a summary.
 */

final class TestRunner
{
    /** @var array<int, array{name:string, fn:callable}> */
    public static array $cases = [];
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var array<int,string> */
    public static array $failures = [];

    public static function add(string $name, callable $fn): void
    {
        self::$cases[] = ['name' => $name, 'fn' => $fn];
    }

    public static function run(): int
    {
        foreach (self::$cases as $case) {
            try {
                ($case['fn'])();
                self::$passed++;
                fwrite(STDOUT, "  \033[32mPASS\033[0m " . $case['name'] . "\n");
            } catch (Throwable $e) {
                self::$failed++;
                self::$failures[] = $case['name'] . ' -> ' . $e->getMessage();
                fwrite(STDOUT, "  \033[31mFAIL\033[0m " . $case['name'] . " -> " . $e->getMessage() . "\n");
            }
        }

        fwrite(STDOUT, "\n" . self::$passed . " passed, " . self::$failed . " failed.\n");

        return self::$failed === 0 ? 0 : 1;
    }
}

function test(string $name, callable $fn): void
{
    TestRunner::add($name, $fn);
}

function assert_true(bool $condition, string $message = 'Expected true'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(bool $condition, string $message = 'Expected false'): void
{
    assert_true(!$condition, $message);
}

function assert_equals(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $message = $message !== '' ? $message : 'Values are not equal';
        throw new RuntimeException($message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}

function assert_throws(callable $fn, string $message = 'Expected an exception'): void
{
    try {
        $fn();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

/**
 * Build an in-memory SQLite PDO with the schema applied (for DB-backed tests).
 */
function test_db(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    $pdo->exec((string) $schema);

    return $pdo;
}
