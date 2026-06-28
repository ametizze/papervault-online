<?php

declare(strict_types=1);

use SimpleVault\Support\Totp;

// RFC 6238 appendix B uses the ASCII secret "12345678901234567890", which is
// this base32 string. The reference 6-digit code at T=59 is 287082.
const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

test('totp matches the RFC 6238 reference vector', function () {
    $otp = Totp::generate(RFC_SECRET, 30, 6, 59);
    assert_equals('287082', $otp['code']);
});

test('totp reports seconds remaining in the window', function () {
    $otp = Totp::generate(RFC_SECRET, 30, 6, 59);
    assert_equals(1, $otp['remaining']);
    assert_equals(30, $otp['period']);
});

test('totp tolerates spaces and lowercase in the secret', function () {
    $spaced = strtolower('GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ');
    $otp = Totp::generate($spaced, 30, 6, 59);
    assert_equals('287082', $otp['code']);
});

test('totp rejects non-base32 secrets', function () {
    assert_false(Totp::isValidSecret('not base32 !!!'));
    assert_true(Totp::isValidSecret(RFC_SECRET));
    assert_equals('', Totp::generate('not base32 !!!')['code']);
});
