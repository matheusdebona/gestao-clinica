<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Fmt;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('dre_do_mes')]
#[Title('DRE do mês')]
#[Description('Demonstrativo de resultado (DRE) de um mês, comparado com o mês anterior: receita bruta e líquida, custos variáveis (insumos, taxas, impostos, comissões, remuneração por metas, perdas), margem de contribuição, despesas fixas, pessoal, marketing, depreciação e resultado líquido, ponto de equilíbrio e detalhamento das despesas. Mesmos números da tela Financeiro > DRE. Sem parâmetro: mês atual.')]
class DreTool extends HofPayTool
{
    private const LINES = [
        'gross_revenue' => 'Receita bruta',
        'discounts' => '(−) Descontos concedidos',
        'net_revenue' => '(=) Receita líquida',
        'supplies' => '(−) Insumos',
        'card_fees' => '(−) Taxas de cartão',
        'anticipation' => '(−) Antecipação',
        'taxes' => '(−) Impostos sobre a receita',
        'commissions' => '(−) Comissões',
        'goal_commissions' => '(−) Remuneração por metas e faturamento',
        'losses' => '(−) Perdas de estoque',
        'other_variable' => '(−) Outros variáveis',
        'variable_costs' => 'Total de custos variáveis',
        'contribution_margin' => '(=) Margem de contribuição',
        'fixed' => '(−) Despesas fixas',
        'personnel' => '(−) Pessoal e pró-labore',
        'marketing' => '(−) Marketing e mídia',
        'expense_taxes' => '(−) Impostos e taxas fixas',
        'financial' => '(−) Despesas financeiras',
        'depreciation' => '(−) Depreciação',
        'operating_expenses' => 'Total de despesas do mês',
        'net_result' => '(=) Resultado líquido',
    ];

    private const BREAKDOWN = [
        'fixed' => 'Despesas fixas',
        'personnel' => 'Pessoal e pró-labore',
        'marketing' => 'Marketing e mídia',
        'expense_taxes' => 'Impostos e taxas fixas',
        'financial' => 'Despesas financeiras',
        'depreciation' => 'Depreciação',
        'other_variable' => 'Outros variáveis',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'mes' => $schema->string()->description('Mês no formato AAAA-MM (ex.: 2026-10). Padrão: mês atual.'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'finance.view', 'o DRE');
        $month = $this->month($request);
        $data = $api->get($user, 'finance/dre', ['month' => $month])['data'];
        $cur = $data['current'];
        $prev = $data['previous'];

        $out = ['## DRE — '.Fmt::month($data['month']).' (comparado com '.Fmt::month($data['previous_month']).')', ''];
        $out[] = Fmt::table(['Linha', Fmt::month($data['month']), Fmt::month($data['previous_month'])], array_map(
            fn (string $key, string $label) => [$label, Fmt::brl($cur['lines'][$key] ?? null), Fmt::brl($prev['lines'][$key] ?? null)],
            array_keys(self::LINES),
            self::LINES,
        ));
        $out[] = '';
        $out[] = '- Margem de contribuição: '.Fmt::pct($cur['contribution_margin_percent']).' · Margem líquida: '.Fmt::pct($cur['net_margin_percent']);
        if (($cur['break_even_revenue'] ?? null) !== null) {
            $gap = (float) $cur['break_even_gap'];
            $out[] = '- Ponto de equilíbrio (receita líquida): '.Fmt::brl($cur['break_even_revenue']).' · '
                .($gap < 0 ? 'Falta para o equilíbrio: '.Fmt::brl(-$gap) : 'Acima do equilíbrio: '.Fmt::brl($gap));
        }
        $out[] = '- Vendas confirmadas no mês: '.Fmt::num($cur['sales_count'] ?? null).' · Despesas lançadas: '.Fmt::num($cur['expenses_count'] ?? null);
        if ((float) ($cur['supplies_estimated'] ?? 0) > 0) {
            $out[] = '- Insumos estimados (sem consumo registrado): '.Fmt::brl($cur['supplies_estimated']);
        }
        if (($data['checks']['tax_configured'] ?? true) === false) {
            $out[] = '- Atenção: os impostos ainda não foram configurados em Financeiro > Impostos.';
        }

        foreach (self::BREAKDOWN as $key => $label) {
            $items = $cur['breakdown'][$key] ?? [];
            if ($items === []) {
                continue;
            }
            $out[] = '';
            $out[] = '### '.$label;
            foreach (array_slice($items, 0, 30) as $item) {
                $out[] = '- '.$item['name'].': '.Fmt::brl($item['amount']);
            }
        }

        return implode("\n", $out);
    }
}
