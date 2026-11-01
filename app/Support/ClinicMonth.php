<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Calendário da clínica: horário de Brasília (America/Sao_Paulo).
 *
 * Instantes ficam gravados em UTC; dias, semanas e meses (filtros, agrupamentos, "hoje",
 * "este mês") são sempre os de São Paulo. Colunas só de data (due_on, competence_month, day…)
 * já são datas de calendário e não passam por conversão.
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

    /** Agora, no fuso de São Paulo. */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ);
    }

    /** 00:00 de hoje em São Paulo (no fuso de São Paulo). */
    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /** Hoje em São Paulo ("2026-10-31"). */
    public static function todayDate(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Mês atual de São Paulo como data de calendário (dia 1, 00:00, sem fuso), para comparar com
     * meses de competência (parseMonth, colunas *_month).
     */
    public static function currentMonth(): CarbonImmutable
    {
        return self::parseMonth(self::of());
    }

    /** Hoje em São Paulo como data de calendário (00:00, sem fuso), para comparar com colunas de data. */
    public static function todayCalendar(): CarbonImmutable
    {
        return self::parseDate(self::todayDate());
    }

    /**
     * Mês "Y-m" como dia 1 (data de calendário). Usa "!" para não herdar o dia de hoje:
     * createFromFormat('Y-m', '2026-11') num dia 31 viraria 01/12.
     */
    public static function parseMonth(string $month): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', substr($month, 0, 7))->startOfMonth();
    }

    /** Data de calendário "Y-m-d" (00:00, sem conversão de fuso). */
    public static function parseDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
    }

    /** Início (UTC) do dia de calendário de São Paulo. */
    public static function dayStart(CarbonInterface|string $date): CarbonImmutable
    {
        return self::dayBounds($date, $date)[0];
    }

    /** Fim (UTC) do dia de calendário de São Paulo. */
    public static function dayEnd(CarbonInterface|string $date): CarbonImmutable
    {
        return self::dayBounds($date, $date)[1];
    }

    /**
     * Limite de filtro vindo da API (em UTC): data pura ("2026-10-31") é o dia inteiro de São
     * Paulo; instante com fuso (Z, -03:00) é respeitado como veio; hora sem fuso é hora de São Paulo.
     */
    public static function boundary(string $value, bool $start): CarbonImmutable
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $start ? self::dayStart($value) : self::dayEnd($value);
        }
        $hasZone = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value) === 1;

        return CarbonImmutable::parse($value, $hasZone ? null : self::TZ)->utc();
    }

    /**
     * Expressão SQL que converte uma coluna de instante (gravada em UTC, sem fuso) para a hora
     * local de São Paulo, para agrupar por dia/semana/mês de São Paulo.
     * PostgreSQL usa a base de fusos (horário de verão histórico incluído); SQLite (testes/dev)
     * usa o deslocamento atual de São Paulo (-03:00 desde 2019).
     */
    public static function localSql(string $column, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            return "(({$column}) AT TIME ZONE 'UTC' AT TIME ZONE '".self::TZ."')";
        }
        $offsetMinutes = self::now()->utcOffset();
        $sign = $offsetMinutes < 0 ? '-' : '+';

        return "datetime({$column}, '{$sign}".abs($offsetMinutes)." minutes')";
    }
}
