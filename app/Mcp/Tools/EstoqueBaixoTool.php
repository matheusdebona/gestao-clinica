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

#[Name('estoque_baixo')]
#[Title('Estoque baixo')]
#[Description('Produtos ativos com estoque no mínimo ou abaixo, com estoque atual, mínimo, prazo de reposição e cobertura em dias (pelo consumo médio dos últimos 30 dias). Também o valor total do estoque e quantos produtos estão negativos. Mesmos números de Métricas > Estoque.')]
class EstoqueBaixoTool extends HofPayTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'metrics.view', 'métricas de estoque');
        $data = $api->get($user, 'metrics/inventory')['data'];
        $k = $data['kpis'];

        $out = ['## Estoque baixo', ''];
        $out[] = '- Produtos no mínimo ou abaixo: **'.Fmt::num($k['low_stock_count']).'** · Com estoque negativo: '.Fmt::num($k['negative_stock_count']);
        $out[] = '- Valor do estoque (custo): '.Fmt::brl($k['inventory_value']);
        $out[] = '- Consumo em atendimentos de '.Fmt::period($data['from'], $data['to']).': '.Fmt::num($k['consumption_quantity']);
        $items = $data['low_stock_products'];
        $out[] = '';
        if ($items === []) {
            $out[] = 'Nenhum produto com estoque baixo.';
        } else {
            $out[] = Fmt::table(['Produto', 'SKU', 'Estoque', 'Mínimo', 'Reposição (dias)', 'Cobertura (dias)'], array_map(fn ($p) => [
                $p['name'], $p['sku'] ?? '—', Fmt::num($p['stock_quantity']), Fmt::num($p['min_stock']),
                $p['lead_time_days'] ?: '—', $p['coverage_days'] !== null ? Fmt::num($p['coverage_days'], 1) : 'sem consumo',
            ], array_slice($items, 0, 100)));
        }

        return implode("\n", $out);
    }
}
