<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Fmt;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\Pii;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('leads')]
#[Title('Leads')]
#[Description('Leads da clínica com filtros por status, período de cadastro (Brasília), convertidos em paciente, parados (sem contato) e busca por nome. Traz também o resumo do período: total, conversão em paciente, cancelados, parados e o funil (abordado → respondeu → agendou), quando a conta pode ver métricas. Até 50 leads por chamada. Não traz telefones.')]
class LeadsTool extends HofPayTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'mes' => $schema->string()->description('Mês de cadastro AAAA-MM. Use este OU de/ate.'),
            'de' => $schema->string()->description('Cadastrados a partir de AAAA-MM-DD.'),
            'ate' => $schema->string()->description('Cadastrados até AAAA-MM-DD.'),
            'status' => $schema->string()->description('Nome do status do lead (ex.: "Novo", "Em contato").'),
            'convertidos' => $schema->boolean()->description('true = só os que viraram paciente; false = só os que não viraram.'),
            'parados_ha_dias' => $schema->integer()->description('Só leads sem atividade há N dias ou mais.'),
            'busca' => $schema->string()->description('Parte do nome do lead (só nome; números não são aceitos).'),
            'limite' => $schema->integer()->description('Máximo de leads listados (padrão 20, máx. 50).'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'leads.view', 'leads');
        $hasPeriod = collect(['mes', 'de', 'ate'])->contains(fn ($k) => trim((string) $request->get($k, '')) !== '');
        [$from, $to] = $hasPeriod ? $this->period($request) : [null, null];

        $query = [
            'from' => $from, 'to' => $to, 'q' => Pii::assertNameSearch($request->get('busca')),
            'per_page' => $this->limit($request, 20, 50),
        ];

        $statusName = trim((string) $request->get('status', ''));
        if ($statusName !== '') {
            $statuses = collect($api->get($user, 'lead-statuses')['data']);
            $norm = fn (string $s) => Str::lower(Str::ascii($s));
            $match = $statuses->first(fn ($s) => $norm($s['name']) === $norm($statusName))
                ?? $statuses->first(fn ($s) => Str::contains($norm($s['name']), $norm($statusName)));
            if (! $match) {
                throw new McpToolException('Status não encontrado. Status da clínica: '.$statuses->pluck('name')->implode(', ').'.');
            }
            $query['lead_status_id'] = $match['id'];
        }

        $converted = $request->get('convertidos');
        if ($converted !== null && $converted !== '') {
            $query['converted'] = filter_var($converted, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if ((int) $request->get('parados_ha_dias', 0) > 0) {
            $query['stale_days'] = min((int) $request->get('parados_ha_dias'), 365);
        }

        $body = $api->get($user, 'leads', $query);
        $items = $body['data'];
        $total = $body['meta']['total'] ?? count($items);

        $title = $from ? 'Leads cadastrados em '.Fmt::period($from, $to) : 'Leads';
        $out = ['## '.$title, ''];

        if ($from && $user->can('metrics.view')) {
            $m = $api->get($user, 'metrics/leads', ['from' => $from, 'to' => $to])['data'];
            $k = $m['kpis'];
            $f = $m['funnel']['total'] ?? null;
            $out[] = '### Resumo do período';
            $out[] = '- Leads: '.Fmt::num($k['total']).' · Viraram paciente: '.Fmt::num($k['converted']).' ('.Fmt::pct($k['conversion_rate']).')';
            $out[] = '- Cancelados: '.Fmt::num($k['cancelled']).' · Parados: '.Fmt::num($k['stale']).' · Dias até virar paciente (média): '.Fmt::num($k['avg_days_to_conversion'], 1);
            if ($f) {
                $out[] = '- Funil: abordados '.Fmt::num($f['approached']).' → responderam '.Fmt::num($f['replied']).' → agendaram '.Fmt::num($f['booked']);
            }
            if (! empty($m['by_origin'])) {
                $out[] = '- Por origem: '.collect($m['by_origin'])->map(fn ($o) => $o['label'].' '.$o['total'].' ('.Fmt::pct($o['conversion_rate']).' viraram paciente)')->implode('; ');
            }
            $out[] = '';
        }

        $out[] = '### Lista ('.Fmt::num($total).' encontrados'.($total > count($items) ? ', mostrando '.count($items) : '').')';
        if ($items === []) {
            $out[] = 'Nenhum lead com esses filtros.';

            return implode("\n", $out);
        }
        $out[] = Fmt::table(['Lead', 'Status', 'Origem', 'Cadastrado em', 'Última atividade', 'Abordado', 'Respondeu', 'Agendou', 'Paciente'], array_map(fn ($l) => [
            $l['name'],
            $l['status']['name'] ?? '—',
            $l['client_origin']['name'] ?? '—',
            Fmt::dateTime($l['created_at']),
            Fmt::dateTime($l['last_activity_at'] ?? null),
            $l['approached_at'] ? 'sim' : 'não',
            $l['replied_at'] ? 'sim' : 'não',
            $l['booked_at'] ? 'sim' : 'não',
            $l['client_id'] ? 'sim' : ($l['cancelled_at'] ? 'cancelado' : 'não'),
        ], $items));

        return implode("\n", $out);
    }
}
