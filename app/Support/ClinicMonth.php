<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Mês e dia de referência da clínica: calendário de America/Sao_Paulo (dia 1 ao último dia).
 * Datas ficam gravadas em UTC; cada evento conta no mês/dia em que aconteceu em São Paulo.
 */
final class ClinicMonth
{
    public const TZ = 'America/Sao_Paulo';

    /** "2026-10" do instante (padrão: agora) no calendário de São Paulo. */
    public static function of(CarbonInterface|string|null $at = null): string
    {
        return CarbonImmutable::parse($at ?? now())->setTimezone(self::TZ)->format('Y-m');
    }

    /** Competência ("2026-10-01") do instante no calendário de São Paulo. */
    public static function competence(CarbonInterface|string|null $at = null): string
    {
        return self::of($at).'-01';
    }

    /** Data ("2026-10-31") do instante no calendário de São Paulo, ou null. */
    public static function date(CarbonInterface|string|null $at): ?string
    {
        return $at === null ? null : CarbonImmutable::parse($at)->setTimezone(self::TZ)->format('Y-m-d');
    }

    /**
     * Limites em UTC do mês "Y-m" de São Paulo (00:00:00 do dia 1 até 23:59:59.999999 do último dia).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', substr($month, 0, 7).'-01 00:00:00', self::TZ);

        return [$start->utc(), $start->endOfMonth()->utc()];
    }

    /**
     * Limites em UTC dos dias de calendário [from, to] de São Paulo (as datas são lidas como dias).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayBounds(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $f = $from instanceof CarbonInterface ? $from->format('Y-m-d') : substr($from, 0, 10);
        $t = $to instanceof CarbonInterface ? $to->format('Y-m-d') : substr($to, 0, 10);

        return [
            CarbonImmutable::createFromFormat('Y-m-d H:i:s', $f.' 00:00:00', self::TZ)->utc(),
            CarbonImmutable::createFromFormat('Y-m-d H:i:s', $t.' 00:00:00', self::TZ)->endOfDay()->utc(),
        ];
    }
}
