<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Fmt;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('metas_do_mes')]
#[Title('Metas e remuneração do mês')]
#[Description('Metas da equipe num mês (agendamentos, valor de vendas, leads abordados): meta, realizado, quanto falta e % atingido por colaborador. Com permissão financeira, também a remuneração do mês por colaborador (comissões de vendas e sessões, bônus de metas e de faturamento; pendente e pago), como em Financeiro > Comissões. Sem parâmetro: mês atual.')]
class MetasDoMesTool extends HofPayTool
{
    private const ORIGIN = ['sales' => 'Vendas', 'sessions' => 'Sessões', 'goals' => 'Metas', 'revenue' => 'Faturamento'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'mes' => $schema->string()->description('Mês AAAA-MM. Padrão: mês atual.'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $month = $this->month($request);
        $canGoals = $user->can('users.view') || $user->can('metrics.view');
        $canPay = $user->can('finance.view');
        if (! $canGoals && ! $canPay) {
            throw new McpToolException('Sua conta não tem permissão para ver metas da equipe no HOF Pay.');
        }

        $out = ['## Metas e remuneração — '.Fmt::month($month), ''];

        if ($canGoals) {
            $goals = $api->get($user, 'goals', ['month' => $month])['data'];
            $withGoal = array_values(array_filter($goals, fn ($g) => $g['has_goal']));
            $out[] = '### Metas';
            if ($withGoal === []) {
                $out[] = 'Nenhum colaborador com meta neste mês.';
            } else {
                $rows = [];
                foreach ($withGoal as $g) {
                    foreach ($g['metrics'] as $m) {
                        if ($m['target'] === null) {
                            continue;
                        }
                        $fmt = fn ($v) => $m['money'] ? Fmt::brl($v) : Fmt::num($v);
                        $rows[] = [$g['user']['name'], $m['label'], $fmt($m['target']), $fmt($m['actual']), $fmt($m['remaining']), Fmt::pct($m['percent'])];
                    }
                }
                $out[] = Fmt::table(['Colaborador', 'Meta', 'Alvo', 'Realizado', 'Falta', '% atingido'], $rows);
                $expected = $withGoal[0]['expected_percent'] ?? null;
                if ($expected !== null) {
                    $out[] = '';
                    $out[] = '_Esperado até hoje (proporcional ao mês): '.Fmt::pct($expected).'._';
                }
            }
            $out[] = '';
        }

        if ($canPay) {
            $meta = $api->get($user, 'finance/commissions', ['month' => $month])['meta'];
            $out[] = '### Remuneração variável do mês';
            $out[] = '- Total: **'.Fmt::brl($meta['total']).'** · Pendente: '.Fmt::brl($meta['pending']);
            $out[] = '- Por origem: '.collect($meta['by_origin'])->map(fn ($v, $k) => (self::ORIGIN[$k] ?? $k).' '.Fmt::brl($v))->implode(' · ');
            if (! empty($meta['by_user'])) {
                $out[] = '';
                $out[] = Fmt::table(['Colaborador', 'Total', 'Pendente', 'Pago', 'Vendas', 'Sessões', 'Metas', 'Faturamento'], array_map(fn ($u) => [
                    $u['user_name'] ?? '—', Fmt::brl($u['total']), Fmt::brl($u['pending']), Fmt::brl($u['paid']),
                    Fmt::brl($u['by_origin']['sales']), Fmt::brl($u['by_origin']['sessions']), Fmt::brl($u['by_origin']['goals']), Fmt::brl($u['by_origin']['revenue']),
                ], $meta['by_user']));
            }
            $out[] = '';
            $out[] = '_Salários e pró-labore fixos ficam no DRE (Pessoal e pró-labore)._';
        } else {
            $out[] = '_A remuneração dos colaboradores não aparece: sua conta não tem a permissão "Ver custos, margens e DRE"._';
        }

        return implode("\n", $out);
    }
}
