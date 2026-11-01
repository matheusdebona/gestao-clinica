<?php

namespace App\Support;

/**
 * Short temporary passwords that are easy to read and type on a phone, e.g. "K7MP4XQ9".
 *
 * 8 characters, uppercase letters and digits only, without the look-alikes
 * 0/O, 1/I/L. Drawn with random_int() (CSPRNG). 31 symbols → ~39.6 bits, which
 * is enough for a single-use password that expires in 60 minutes behind login
 * and per-e-mail rate limits.
 */
class TemporaryPasswordGenerator
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $plain = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $plain .= self::ALPHABET[random_int(0, $max)];
        }

        return $plain;
    }

    /**
     * How a typed temporary password is compared: spaces/hyphens removed and
     * uppercased, so " k7mp 4xq9 " from a phone keyboard still works.
     */
    public static function normalize(string $typed): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/u', '', $typed));
    }
}
