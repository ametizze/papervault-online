<?php

declare(strict_types=1);

namespace SimpleVault\Support;

/**
 * RFC 6238 (TOTP) / RFC 4226 (HOTP) code generator. Used to turn a stored
 * base32 secret (a custom field of type "totp") into a rotating one-time code,
 * so the vault can act as an authenticator app. The secret itself is never
 * exposed to the browser — only the current code is.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A secret is usable if it base32-decodes to at least one byte. */
    public static function isValidSecret(string $secret): bool
    {
        return self::base32Decode($secret) !== '';
    }

    /**
     * Current code plus how many seconds remain in the window.
     *
     * @return array{code:string,remaining:int,period:int}
     */
    public static function generate(string $secret, int $period = 30, int $digits = 6, ?int $time = null): array
    {
        $time ??= time();
        $key = self::base32Decode($secret);
        if ($key === '') {
            return ['code' => '', 'remaining' => 0, 'period' => $period];
        }

        $counter = intdiv($time, $period);
        // 8-byte big-endian counter (high word is always 0 for any realistic time).
        $binCounter = pack('N', 0) . pack('N', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);

        return [
            'code' => $code,
            'remaining' => $period - ($time % $period),
            'period' => $period,
        ];
    }

    /** Decode a base32 secret (spaces/padding tolerated). '' on invalid input. */
    private static function base32Decode(string $secret): string
    {
        $clean = strtoupper((string) preg_replace('/[\s=]/', '', $secret));
        if ($clean === '' || preg_match('/[^A-Z2-7]/', $clean) === 1) {
            return '';
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}
