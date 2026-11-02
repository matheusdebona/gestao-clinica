<?php

namespace App\Mcp\Support;

use App\Support\ClinicMonth;
use Carbon\CarbonImmutable;

/** Formatação das respostas do conector MCP: R$, datas e horários de Brasília. */
class Fmt
{
    public static function brl(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $n = (float) $value;
        $s = 'R$ '.number_format(abs($n), 2, ',', '.');

        return $n < 0 ? '-'.$s : $s;
    }

    public static function num(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $n = (float) $value;
        if ($decimals === 0 && floor($n) != $n) {
            $decimals = 2;
        }

        return number_format($n, $decimals, ',', '.');
    }

    public static function pct(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : number_format((float) $value, 1, ',', '.').'%';
    }

    /** "2026-11-03" → "03/11/2026". */
    public static function date(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return ClinicMonth::parseDate($value)->format('d/m/Y');
    }

    /** Instante (UTC/ISO) → "03/11/2026 14:30" no horário de Brasília. */
    public static function dateTime(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return CarbonImmutable::parse($value)->setTimezone(ClinicMonth::TZ)->format('d/m/Y H:i');
    }

    /** "2026-11" → "novembro de 2026". */
    public static function month(string $month): string
    {
        $names = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $date = ClinicMonth::parseMonth($month);

        return $names[$date->month - 1].' de '.$date->year;
    }

    public static function period(string $from, string $to): string
    {
        return $from === $to ? self::date($from) : self::date($from).' a '.self::date($to);
    }

    /** Linha de tabela Markdown, sem quebrar com "|" no conteúdo. */
    public static function row(array $cells): string
    {
        return '| '.implode(' | ', array_map(fn ($c) => str_replace(['|', "\n"], ['/', ' '], (string) $c), $cells)).' |';
    }

    /** @param  list<string>  $headers */
    public static function table(array $headers, array $rows): string
    {
        $lines = [self::row($headers), self::row(array_fill(0, count($headers), '---'))];
        foreach ($rows as $row) {
            $lines[] = self::row($row);
        }

        return implode("\n", $lines);
    }
}
