<?php

namespace App\Support;

/**
 * Strong but readable temporary passwords, e.g. "Kp7m-X3qr-9tZa".
 *
 * Skips look-alike characters (0/O/o, 1/l/I, 5/S, 2/Z...) so it can be typed
 * from an e-mail on a phone. 12 random chars over a 50-symbol alphabet
 * (~67 bits), grouped 4-4-4 with hyphens; always has upper, lower and digit.
 */
class TemporaryPasswordGenerator
{
    private const UPPER = 'ABCDEFGHJKMNPQRTUVWXY';

    private const LOWER = 'abcdefghjkmnpqrstuvwxyz';

    private const DIGITS = '346789';

    public static function generate(): string
    {
        $all = self::UPPER.self::LOWER.self::DIGITS;

        do {
            $chars = [];
            for ($i = 0; $i < 12; $i++) {
                $chars[] = $all[random_int(0, strlen($all) - 1)];
            }
            $plain = implode('', $chars);
        } while (
            ! preg_match('/[A-Z]/', $plain)
            || ! preg_match('/[a-z]/', $plain)
            || ! preg_match('/\d/', $plain)
        );

        return implode('-', str_split($plain, 4));
    }
}
