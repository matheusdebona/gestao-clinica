<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Fmt;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('vendas_por_produto')]
#[Title('Vendas por produto')]
#[Description('Unidades vendidas e faturamento de cada produto e protocolo num período (vendas confirmadas, calendário de Brasília). Mesmos números da tela Métricas > Vendas por produto. Filtro opcional por tipo de produto ou nome do produto.')]
class VendasPorProdutoTool extends HofPayTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'mes' => $schema->string()->description('Mês AAAA-MM. Use este OU de/ate.'),
            'de' => $schema->string()->description('Data inicial AAAA-MM-DD.'),
            'ate' => $schema->string()->description('Data final AAAA-MM-DD.'),
            'tipo_de_produto' => $schema->string()->description('Nome do tipo de produto (ex.: "Toxina botulínica").'),
            'produto' => $schema->string()->description('Nome (ou parte do nome) de um produto.'),
            'limite' => $schema->integer()->description('Máximo de produtos listados (padrão 30, máx. 100).'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        [$from, $to] = $this->period($request);
        $query = ['from' => $from, 'to' => $to];

        // Os filtros por nome viram ids usando as opções que o próprio endpoint devolve.
        $type = trim((string) $request->get('tipo_de_produto', ''));
        $product = trim((string) $request->get('produto', ''));
        if ($type !== '' || $product !== '') {
            $options = $api->get($user, 'metrics/product-sales', $query)['data']['options'];
            if ($type !== '') {
                $match = collect($options['product_types'])->first(fn ($o) => Str::lower(Str::ascii($o['name'])) === Str::lower(Str::ascii($type)))
                    ?? collect($options['product_types'])->first(fn ($o) => Str::contains(Str::lower(Str::ascii($o['name'])), Str::lower(Str::ascii($type))));
                if (! $match) {
                    throw new McpToolException('Tipo de produto não encontrado. Tipos: '.collect($options['product_types'])->pluck('name')->implode(', ').'.');
                }
                $query['product_type_id'] = $match['id'];
            }
            if ($product !== '') {
                $match = collect($options['products'])->first(fn ($o) => Str::contains(Str::lower(Str::ascii($o['name'])), Str::lower(Str::ascii($product))));
                if (! $match) {
                    throw new McpToolException('Nenhuma venda desse produto no período.');
                }
                $query['product_id'] = $match['id'];
            }
        }

        $data = $api->get($user, 'metrics/product-sales', $query)['data'];
        $t = $data['totals'];
        $limit = $this->limit($request, 30, 100);

        $out = ['## Vendas por produto — '.Fmt::period($from, $to), ''];
        $out[] = '- Faturamento do período: **'.Fmt::brl($t['period_revenue']).'** em '.Fmt::num($t['period_sales_count']).' vendas confirmadas';
        $out[] = '- Faturamento nos produtos listados: '.Fmt::brl($t['revenue']).' ('.Fmt::pct($t['share_percent']).')';
        if ((float) $t['unallocated_revenue'] > 0) {
            $out[] = '- Vendas sem produtos (ex.: valor manual): '.Fmt::brl($t['unallocated_revenue']);
        }

        $products = $data['products'];
        $out[] = '';
        if ($products === []) {
            $out[] = 'Nenhum produto vendido no período.';
        } else {
            $out[] = Fmt::table(['Produto', 'Tipo', 'Unidades', 'Faturamento', '% do total', 'Vendas', 'Preço médio'], array_map(fn ($p) => [
                $p['product_name'],
                $p['product_type_name'] ?? '—',
                Fmt::num($p['units']).($p['unit_symbol'] ? ' '.$p['unit_symbol'] : ''),
                Fmt::brl($p['revenue']),
                Fmt::pct($p['share_percent']),
                Fmt::num($p['sales_count']),
                Fmt::brl($p['avg_unit_price']),
            ], array_slice($products, 0, $limit)));
            if (count($products) > $limit) {
                $out[] = '';
                $out[] = '_Mostrando '.$limit.' de '.count($products).' produtos._';
            }
        }

        $protocols = array_values(array_filter($data['protocols'] ?? [], fn ($p) => $p['protocol_id'] !== null));
        if ($protocols !== []) {
            $out[] = '';
            $out[] = '### Por protocolo';
            $out[] = Fmt::table(['Protocolo', 'Faturamento', '% do total', 'Vendas'], array_map(fn ($p) => [
                $p['protocol_name'], Fmt::brl($p['revenue']), Fmt::pct($p['share_percent']), Fmt::num($p['sales_count']),
            ], array_slice($protocols, 0, 30)));
        }

        return implode("\n", $out);
    }
}
