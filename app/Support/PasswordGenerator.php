<?php

declare(strict_types=1);

namespace SimpleVault\Support;

use InvalidArgumentException;

/**
 * Cryptographically secure password generator using random_int().
 * Never uses rand() or mt_rand().
 */
final class PasswordGenerator
{
    private const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    private const DIGITS = '0123456789';
    private const SYMBOLS = '!@#$%^&*()-_=+[]{};:,.<>?';
    private const AMBIGUOUS = 'O0oIl1|`\'"{}[]()/\\';

    /**
     * @param array{
     *   length?:int, upper?:bool, lower?:bool, digits?:bool,
     *   symbols?:bool, avoid_ambiguous?:bool
     * } $options
     */
    public function generate(array $options = []): string
    {
        $length = max(8, min(128, (int) ($options['length'] ?? 20)));
        $useUpper = $options['upper'] ?? true;
        $useLower = $options['lower'] ?? true;
        $useDigits = $options['digits'] ?? true;
        $useSymbols = $options['symbols'] ?? true;
        $avoidAmbiguous = $options['avoid_ambiguous'] ?? false;

        $pools = [];
        if ($useUpper) {
            $pools[] = self::UPPER;
        }
        if ($useLower) {
            $pools[] = self::LOWER;
        }
        if ($useDigits) {
            $pools[] = self::DIGITS;
        }
        if ($useSymbols) {
            $pools[] = self::SYMBOLS;
        }

        if ($pools === []) {
            throw new InvalidArgumentException('At least one character set must be enabled.');
        }

        if ($avoidAmbiguous) {
            $pools = array_map([$this, 'stripAmbiguous'], $pools);
            $pools = array_values(array_filter($pools, static fn (string $p): bool => $p !== ''));
            if ($pools === []) {
                throw new InvalidArgumentException('No characters remain after removing ambiguous ones.');
            }
        }

        $all = implode('', $pools);

        // Guarantee at least one character from each selected pool.
        $chars = [];
        foreach ($pools as $pool) {
            $chars[] = $this->pick($pool);
        }
        while (count($chars) < $length) {
            $chars[] = $this->pick($all);
        }

        // Secure shuffle (Fisher-Yates with random_int).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', array_slice($chars, 0, $length));
    }

    private function pick(string $pool): string
    {
        return $pool[random_int(0, strlen($pool) - 1)];
    }

    private function stripAmbiguous(string $pool): string
    {
        $ambiguous = str_split(self::AMBIGUOUS);

        return str_replace($ambiguous, '', $pool);
    }
}
