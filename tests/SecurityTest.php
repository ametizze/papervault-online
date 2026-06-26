<?php

declare(strict_types=1);

use SimpleVault\Core\Csrf;
use SimpleVault\Core\RateLimiter;
use SimpleVault\Core\Uuid;
use SimpleVault\Support\PasswordGenerator;

test('csrf token validates and rejects bad tokens', function () {
    $_SESSION = [];
    $token = Csrf::token();
    assert_true(Csrf::validate($token));
    assert_false(Csrf::validate('not-the-token'));
    assert_false(Csrf::validate(null));
    assert_false(Csrf::validate(''));
});

test('csrf rotate changes the token', function () {
    $_SESSION = [];
    $a = Csrf::token();
    Csrf::rotate();
    $b = Csrf::token();
    assert_true($a !== $b);
});

test('rate limiter locks out after max attempts', function () {
    $pdo = test_db();
    $limiter = new RateLimiter($pdo);
    $ip = '203.0.113.5';
    $email = 'user@example.com';

    assert_false($limiter->tooManyAttempts($ip, $email));
    for ($i = 0; $i < 5; $i++) {
        $limiter->recordFailure($ip, $email);
    }
    assert_true($limiter->tooManyAttempts($ip, $email));

    // Reset clears the lockout.
    $limiter->reset($ip, $email);
    assert_false($limiter->tooManyAttempts($ip, $email));
});

test('uuid generation and validation', function () {
    $uuid = Uuid::v4();
    assert_true(Uuid::isValid($uuid));
    assert_false(Uuid::isValid('not-a-uuid'));
    assert_false(Uuid::isValid('11111111-1111-1111-1111-111111111111')); // wrong version
});

test('password generator honors length and charset', function () {
    $gen = new PasswordGenerator();
    $pw = $gen->generate(['length' => 32, 'symbols' => false, 'upper' => true, 'lower' => true, 'digits' => true]);
    assert_equals(32, strlen($pw));
    assert_equals(1, preg_match('/^[A-Za-z0-9]+$/', $pw), 'No symbols expected');
});

test('password generator rejects empty charset', function () {
    $gen = new PasswordGenerator();
    assert_throws(fn () => $gen->generate([
        'upper' => false, 'lower' => false, 'digits' => false, 'symbols' => false,
    ]));
});

test('password generator avoids ambiguous characters when requested', function () {
    $gen = new PasswordGenerator();
    for ($i = 0; $i < 20; $i++) {
        $pw = $gen->generate(['length' => 40, 'avoid_ambiguous' => true]);
        assert_equals(0, preg_match('/[O0oIl1|]/', $pw), 'Ambiguous chars should be absent');
    }
});
