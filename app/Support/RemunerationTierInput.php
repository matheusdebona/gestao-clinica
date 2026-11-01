<?php

namespace App\Support;

use App\Models\GoalRemuneration;
use App\Models\RemunerationTier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validação e formato das faixas (agendamentos e faturamento), um só lugar para os dois usos.
 */
final class RemunerationTierInput
{
    /**
     * @return list<array{threshold: float, fixed_amount: float, percent: float}>
     */
    public static function validate(Request $request, string $kind, string $field = 'tiers'): array
    {
        $count = $kind === RemunerationTier::KIND_APPOINTMENTS;
        $request->validate([
            $field => ['present', 'array', 'max:'.RemunerationTier::MAX_TIERS],
            "{$field}.*.threshold" => $count
                ? ['required', 'integer', 'min:1', 'max:100000']
                : ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            "{$field}.*.fixed_amount" => ['nullable', 'numeric', 'min:0', 'max:'.GoalRemuneration::MAX_AMOUNT, 'decimal:0,2'],
            "{$field}.*.percent" => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
        ], [
            "{$field}.max" => 'No máximo '.RemunerationTier::MAX_TIERS.' faixas.',
            "{$field}.*.threshold.required" => 'Informe o gatilho.',
            "{$field}.*.threshold.integer" => 'Informe um número inteiro de agendamentos.',
            "{$field}.*.threshold.min" => 'O gatilho precisa ser de pelo menos 1.',
            "{$field}.*.threshold.gt" => 'Informe um gatilho maior que zero.',
            "{$field}.*.threshold.max" => 'Gatilho alto demais.',
            "{$field}.*.fixed_amount.max" => 'Valor alto demais.',
            "{$field}.*.percent.max" => 'Percentual máximo de 100%.',
            "{$field}.*.*.min" => 'Informe um valor a partir de zero.',
            "{$field}.*.*.numeric" => 'Informe um valor válido.',
            "{$field}.*.*.decimal" => 'Casas decimais demais.',
        ]);

        $tiers = [];
        $errors = [];
        $seen = [];
        foreach (array_values((array) $request->input($field, [])) as $i => $row) {
            $threshold = round((float) ($row['threshold'] ?? 0), 2);
            $fixed = round((float) ($row['fixed_amount'] ?? 0), 2);
            $percent = round((float) ($row['percent'] ?? 0), 4);
            if ($fixed <= 0 && $percent <= 0) {
                $errors["{$field}.{$i}.fixed_amount"] = ['Informe um valor fixo, um percentual ou os dois.'];
            }
            $key = number_format($threshold, 2, '.', '');
            if (isset($seen[$key])) {
                $errors["{$field}.{$i}.threshold"] = ['Já existe uma faixa com este gatilho.'];
            }
            $seen[$key] = true;
            $tiers[] = ['threshold' => $threshold, 'fixed_amount' => $fixed, 'percent' => $percent];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort($tiers, fn ($a, $b) => $a['threshold'] <=> $b['threshold']);

        return $tiers;
    }

    /**
     * @param  iterable<RemunerationTier>  $tiers
     * @return list<array{threshold: string|int, fixed_amount: string, percent: string}>
     */
    public static function payload(iterable $tiers): array
    {
        $out = [];
        foreach ($tiers as $t) {
            $out[] = [
                'threshold' => $t->kind === RemunerationTier::KIND_APPOINTMENTS
                    ? (int) round((float) $t->threshold)
                    : number_format((float) $t->threshold, 2, '.', ''),
                'fixed_amount' => number_format((float) $t->fixed_amount, 2, '.', ''),
                'percent' => rtrim(rtrim(number_format((float) $t->percent, 4, '.', ''), '0'), '.') ?: '0',
            ];
        }

        return $out;
    }
}
