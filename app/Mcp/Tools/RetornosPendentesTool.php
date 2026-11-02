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

#[Name('retornos_pendentes')]
#[Title('Retornos pendentes')]
#[Description('Retornos de pacientes (ex.: reaplicar toxina) a chamar: vencendo agora (due), atrasados (overdue), próximos (upcoming) ou já feitos (done). Mostra paciente, procedimento, data prevista e dias até/desde o vencimento. Mesma lista da tela Retornos.')]
class RetornosPendentesTool extends HofPayTool
{
    private const FILTERS = ['due' => 'A chamar agora', 'overdue' => 'Atrasados', 'upcoming' => 'Próximos', 'done' => 'Feitos'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'filtro' => $schema->string()->enum(array_keys(self::FILTERS))->description('due (a chamar, padrão), overdue (atrasados), upcoming (próximos) ou done (feitos).'),
            'limite' => $schema->integer()->description('Máximo de itens (padrão 50, máx. 200).'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'clients.view', 'retornos');
        $this->requirePermission($user, 'sales.view', 'retornos');
        $filter = $request->get('filtro') ?: 'due';
        $body = $api->get($user, 'patient-returns', ['filter' => $filter]);
        $items = array_slice($body['data'], 0, $this->limit($request, 50, 200));
        $counts = $body['meta']['counts'];

        $out = ['## Retornos — '.(self::FILTERS[$filter] ?? $filter), ''];
        $out[] = '- A chamar agora: '.Fmt::num($counts['due']).' · Atrasados: '.Fmt::num($counts['overdue']).' (hoje: '.Fmt::date($body['meta']['today']).')';
        $out[] = '';
        if ($items === []) {
            $out[] = 'Nenhum retorno nesta lista.';

            return implode("\n", $out);
        }
        $out[] = Fmt::table(['Paciente', 'Procedimento', 'Previsto para', 'Situação', 'WhatsApp enviado'], array_map(function ($r) {
            $d = $r['days_until_due'];
            $when = $r['status'] === 'done' ? 'feito em '.Fmt::dateTime($r['done_at']) : ($d < 0 ? 'atrasado há '.abs($d).' dia(s)' : ($d === 0 ? 'vence hoje' : 'em '.$d.' dia(s)'));

            return [$r['client']['name'] ?? '—', $r['label'] ?? '—', Fmt::date($r['due_on']), $when, $r['whatsapp_sent_at'] ? Fmt::dateTime($r['whatsapp_sent_at']) : 'não'];
        }, $items));

        return implode("\n", $out);
    }
}
