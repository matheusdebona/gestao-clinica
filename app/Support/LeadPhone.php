<?php

namespace App\Support;

class LeadPhone
{
    /**
     * Digits only, without the Brazilian country code (55) when it is present.
     */
    public static function canonical(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Stored forms that represent the same number (with and without 55).
     *
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $canonical = self::canonical($phone);
        if ($canonical === '') {
            return [];
        }

        return [$canonical, '55'.$canonical];
    }

    /**
     * Forms a patient WhatsApp may be stored in: digits (with/without 55) and the
     * masked "(11) 98765-4321" form used by older records.
     *
     * @return list<string>
     */
    public static function clientCandidates(string $phone): array
    {
        $candidates = self::variants($phone);
        $canonical = self::canonical($phone);
        $length = strlen($canonical);

        if ($length === 10 || $length === 11) {
            $ddd = substr($canonical, 0, 2);
            $number = substr($canonical, 2);
            $split = strlen($number) - 4;
            $candidates[] = sprintf('(%s) %s-%s', $ddd, substr($number, 0, $split), substr($number, $split));
        }

        return $candidates;
    }
}
