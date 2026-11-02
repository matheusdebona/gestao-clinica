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

#[Name('resumo_faturamento')]
#[Title('Resumo de faturamento')]
#[Description('Faturamento da clínica num período (vendas confirmadas, pela data da venda no calendário de Brasília): total, número de vendas, ticket médio, desconto médio, formas de pagamento, funil de orçamentos e evolução por dia/semana/mês. Os mesmos números da tela Métricas > Comercial. Sem parâmetros: mês atual até hoje.')]
class ResumoDoMesTool extends HofPayTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'mes' => $schema->string()->description('Mês no formato AAAA-MM (ex.: 2026-11). Use este OU de/ate.'),
            'de' => $schema->string()->description('Data inicial AAAA-MM-DD (Brasília).'),
            'ate' => $schema->string()->description('Data final AAAA-MM-DD (Brasília).'),
            'agrupar_por' => $schema->string()->enum(['day', 'week', 'month'])->description('Evolução por dia (day), semana (week) ou mês (month). Padrão automático.'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'finance.view', 'faturamento');
        [$from, $to] = $this->period($request);

        $data = $api->get($user, 'metrics/commercial', [
            'from' => $from, 'to' => $to, 'granularity' => $request->get('agrupar_por'),
        ])['data'];
        $k = $data['kpis'];

        $out = ['## Faturamento — '.Fmt::period($from, $to), ''];
        $out[] = '- Faturamento (vendas confirmadas): **'.Fmt::brl($k['revenue']).'**';
        $out[] = '- Vendas: '.Fmt::num($k['sales_count']);
        $out[] = '- Ticket médio: '.Fmt::brl($k['ticket_avg']);
        $out[] = '- Desconto médio: '.Fmt::pct($k['avg_discount_percent']);

        if (! empty($data['payment_mix'])) {
            $out[] = '';
            $out[] = '### Formas de pagamento';
            $out[] = Fmt::table(['Forma', 'Valor', 'Pagamentos'], array_map(fn ($p) => [
                $p['name'],
                Fmt::brl($p['amount']),
                Fmt::num($p['payments_count']),
            ], $data['payment_mix']));
        }

        $f = $data['budget_funnel'] ?? null;
        if ($f) {
            $out[] = '';
            $out[] = '### Orçamentos no período';
            $out[] = '- Enviados: '.Fmt::num($f['sent_in_period']).' · Aceitos: '.Fmt::num($f['accepted_in_period']).' · Recusados: '.Fmt::num($f['rejected_in_period']);
            $out[] = '- Taxa de aceite: '.Fmt::pct($f['acceptance_rate']);
        }

        $series = array_values(array_filter($data['series'] ?? [], fn ($s) => (float) $s['revenue'] > 0 || $s['sales_count'] > 0));
        if ($series !== []) {
            $out[] = '';
            $out[] = '### Evolução ('.['day' => 'por dia', 'week' => 'por semana', 'month' => 'por mês'][$data['granularity']].')';
            $out[] = Fmt::table(['Período', 'Faturamento', 'Vendas'], array_map(fn ($s) => [
                strlen($s['period']) === 10 ? Fmt::date($s['period']) : $s['period'],
                Fmt::brl($s['revenue']),
                Fmt::num($s['sales_count']),
            ], array_slice($series, 0, 62)));
        }

        return implode("\n", $out);
    }
}
