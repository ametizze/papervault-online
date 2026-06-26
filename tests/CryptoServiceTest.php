<?php

declare(strict_types=1);

use SimpleVault\Crypto\CryptoService;

test('encrypt/decrypt round trip', function () {
    $crypto = new CryptoService();
    $key = $crypto->generateKey();
    $enc = $crypto->encrypt('hello world', $key);
    $plain = $crypto->decrypt($enc['ciphertext'], $enc['nonce'], $key);
    assert_equals('hello world', $plain);
});

test('encryptJson/decryptJson round trip', function () {
    $crypto = new CryptoService();
    $key = $crypto->generateKey();
    $payload = ['title' => 'GitHub', 'tags' => ['dev', 'work']];
    $enc = $crypto->encryptJson($payload, $key);
    $out = $crypto->decryptJson($enc['ciphertext'], $enc['nonce'], $key);
    assert_equals('GitHub', $out['title']);
    assert_equals(['dev', 'work'], $out['tags']);
});

test('decryption fails with wrong key', function () {
    $crypto = new CryptoService();
    $key = $crypto->generateKey();
    $wrong = $crypto->generateKey();
    $enc = $crypto->encrypt('secret', $key);
    assert_throws(fn () => $crypto->decrypt($enc['ciphertext'], $enc['nonce'], $wrong));
});

test('decryption fails on tampered ciphertext', function () {
    $crypto = new CryptoService();
    $key = $crypto->generateKey();
    $enc = $crypto->encrypt('secret', $key);
    $raw = base64_decode($enc['ciphertext']);
    $raw[0] = $raw[0] ^ "\x01"; // flip a bit
    $tampered = base64_encode($raw);
    assert_throws(fn () => $crypto->decrypt($tampered, $enc['nonce'], $key));
});

test('nonces are unique across encryptions', function () {
    $crypto = new CryptoService();
    $key = $crypto->generateKey();
    $nonces = [];
    for ($i = 0; $i < 200; $i++) {
        $enc = $crypto->encrypt('x', $key);
        $nonces[$enc['nonce']] = true;
    }
    assert_equals(200, count($nonces), 'All nonces should be distinct');
});

test('invalid key length is rejected', function () {
    $crypto = new CryptoService();
    assert_throws(fn () => $crypto->encrypt('x', 'short-key'));
});
