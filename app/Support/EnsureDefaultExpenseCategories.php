<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\ExpenseCategory;

class EnsureDefaultExpenseCategories
{
    /** @var list<array{0: string, 1: string}> */
    public const DEFAULTS = [
        ['Aluguel', ExpenseCategory::KIND_FIXED],
        ['Condomínio e IPTU', ExpenseCategory::KIND_FIXED],
        ['Energia elétrica', ExpenseCategory::KIND_FIXED],
        ['Água', ExpenseCategory::KIND_FIXED],
        ['Internet e telefone', ExpenseCategory::KIND_FIXED],
        ['Software e sistemas', ExpenseCategory::KIND_FIXED],
        ['Contabilidade', ExpenseCategory::KIND_FIXED],
        ['Manutenção', ExpenseCategory::KIND_FIXED],
        ['Limpeza e lavanderia', ExpenseCategory::KIND_FIXED],
        ['Seguros', ExpenseCategory::KIND_FIXED],
        ['Material de escritório', ExpenseCategory::KIND_FIXED],
        ['Salários', ExpenseCategory::KIND_PERSONNEL],
        ['Encargos e benefícios', ExpenseCategory::KIND_PERSONNEL],
        ['Pró-labore', ExpenseCategory::KIND_PERSONNEL],
        ['Mídia externa (anúncios)', ExpenseCategory::KIND_MARKETING],
        ['Agência e produção de conteúdo', ExpenseCategory::KIND_MARKETING],
        ['Impostos e taxas fixas', ExpenseCategory::KIND_TAXES],
        ['Tarifas bancárias', ExpenseCategory::KIND_FINANCIAL],
        ['Juros e multas', ExpenseCategory::KIND_FINANCIAL],
        ['Depreciação de equipamentos', ExpenseCategory::KIND_DEPRECIATION],
        ['Outros custos variáveis', ExpenseCategory::KIND_VARIABLE],
    ];

    public static function run(Clinic $clinic): void
    {
        foreach (self::DEFAULTS as $index => [$name, $kind]) {
            ExpenseCategory::query()->withoutGlobalScope('clinic')->firstOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $name],
                ['kind' => $kind, 'is_system' => true, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    public static function runAll(): int
    {
        $count = 0;
        Clinic::query()->each(function (Clinic $clinic) use (&$count) {
            self::run($clinic);
            $count++;
        });

        return $count;
    }
}
