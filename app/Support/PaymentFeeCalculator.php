<?php

namespace App\Support;

use InvalidArgumentException;

class PaymentFeeCalculator
{
    public static function feeAmount(string|float $gross, ?string $feePercent, ?string $feeFixed): string
    {
        $grossAmount = (float) $gross;
        if ($grossAmount < 0) {
            throw new InvalidArgumentException('Gross amount cannot be negative.');
        }

        $percent = $feePercent !== null ? ((float) $feePercent / 100) * $grossAmount : 0.0;
        $fixed = $feeFixed !== null ? (float) $feeFixed : 0.0;

        return number_format($percent + $fixed, 2, '.', '');
    }

    public static function netAmount(string|float $gross, ?string $feePercent, ?string $feeFixed): string
    {
        $fee = (float) self::feeAmount($gross, $feePercent, $feeFixed);

        return number_format((float) $gross - $fee, 2, '.', '');
    }
}
