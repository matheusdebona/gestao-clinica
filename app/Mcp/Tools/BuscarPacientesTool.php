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

#[Name('buscar_pacientes')]
#[Title('Buscar pacientes')]
#[Description('Busca pacientes por nome, com a faixa (tier) pelo total comprado em 12 meses e esse total (quando a conta pode ver vendas). Filtro opcional por faixa. 20 por página. Para o histórico de um paciente use paciente_detalhe com o id. Não traz telefones nem anotações clínicas.')]
class BuscarPacientesTool extends HofPayTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'busca' => $schema->string()->description('Parte do nome do paciente (só nome; números não são aceitos).'),
            'faixa' => $schema->string()->description('Nome da faixa (ex.: "Ouro"), ou "sem faixa".'),
            'pagina' => $schema->integer()->description('Página (20 pacientes por página). Padrão 1.'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'clients.view', 'pacientes');
        $query = [
            'q' => Pii::assertNameSearch($request->get('busca')),
            'page' => max(1, min((int) $request->get('pagina', 1), 500)),
        ];

        $tierName = trim((string) $request->get('faixa', ''));
        if ($tierName !== '') {
            $norm = fn (string $s) => Str::lower(Str::ascii($s));
            if (in_array($norm($tierName), ['sem faixa', 'nenhuma', 'none'], true)) {
                $query['tiers'] = 'none';
            } else {
                $tiers = collect($api->get($user, 'patient-tiers')['data'] ?? []);
                $match = $tiers->first(fn ($t) => $norm($t['name']) === $norm($tierName));
                if (! $match) {
                    throw new McpToolException('Faixa não encontrada. Faixas: '.$tiers->pluck('name')->implode(', ').'.');
                }
                $query['tiers'] = (string) $match['id'];
            }
        }

        $body = $api->get($user, 'clients', $query);
        $items = $body['data'];
        $meta = $body['meta'] ?? [];
        $out = ['## Pacientes'.($query['q'] ? ' — busca "'.$query['q'].'"' : ''), ''];
        if ($items === []) {
            $out[] = 'Nenhum paciente encontrado.';

            return implode("\n", $out);
        }

        $showSpend = array_key_exists('purchases_12m', $items[0]);
        $headers = ['Id', 'Paciente', 'Faixa', ...($showSpend ? ['Comprado em 12 meses'] : []), 'Origem', 'Ativo', 'Desde'];
        $out[] = Fmt::table($headers, array_map(fn ($c) => [
            $c['id'],
            $c['name'],
            $c['tier']['name'] ?? '—',
            ...($showSpend ? [Fmt::brl($c['purchases_12m'] ?? null)] : []),
            $c['client_origin']['name'] ?? '—',
            $c['is_active'] ? 'sim' : 'não',
            Fmt::dateTime($c['created_at']),
        ], $items));
        $out[] = '';
        $out[] = '_Página '.($meta['current_page'] ?? 1).' de '.($meta['last_page'] ?? 1).' · '.Fmt::num($meta['total'] ?? count($items)).' pacientes._';

        return implode("\n", $out);
    }
}
