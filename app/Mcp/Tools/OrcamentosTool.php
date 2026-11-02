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

#[Name('orcamentos')]
#[Title('Orçamentos')]
#[Description('Orçamentos da clínica por situação (rascunho, enviado, aceito, recusado, expirado) ou os esquecidos (enviados e sem resposta além do prazo de follow-up). Mostra paciente, valor, envio e dias sem resposta. 20 por página, mais recentes primeiro.')]
class OrcamentosTool extends HofPayTool
{
    private const STATUS = ['draft' => 'Rascunho', 'sent' => 'Enviado', 'accepted' => 'Aceito', 'rejected' => 'Recusado', 'expired' => 'Expirado', 'superseded' => 'Substituído'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'situacao' => $schema->string()->enum(['draft', 'sent', 'accepted', 'rejected', 'expired'])->description('draft, sent (enviado), accepted (aceito), rejected (recusado) ou expired.'),
            'esquecidos' => $schema->boolean()->description('true = só orçamentos esquecidos (enviados sem resposta além do prazo).'),
            'pagina' => $schema->integer()->description('Página (20 por página).'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'budgets.view', 'orçamentos');
        $status = $request->get('situacao');
        if ($status !== null && $status !== '' && ! isset(self::STATUS[$status])) {
            throw new McpToolException('Situação inválida. Use: draft, sent, accepted, rejected ou expired.');
        }
        $forgotten = filter_var($request->get('esquecidos', false), FILTER_VALIDATE_BOOLEAN);

        $body = $api->get($user, 'budgets', [
            'status' => $forgotten ? null : $status,
            'forgotten' => $forgotten ? 1 : null,
            'page' => max(1, min((int) $request->get('pagina', 1), 500)),
        ]);
        $items = $body['data'];
        $meta = $body['meta'] ?? [];
        $title = $forgotten ? 'esquecidos' : ($status ? Str::lower(self::STATUS[$status]).'s' : 'todos');

        $out = ['## Orçamentos — '.$title, ''];
        if ($items === []) {
            $out[] = 'Nenhum orçamento nesta lista.';

            return implode("\n", $out);
        }
        $out[] = '- Encontrados: '.Fmt::num($meta['total'] ?? count($items)).' · Soma desta página: '.Fmt::brl(collect($items)->sum(fn ($b) => (float) $b['effective_amount']));
        $out[] = '';
        $out[] = Fmt::table(['Paciente', 'Valor', 'Situação', 'Criado em', 'Enviado em', 'Sem resposta há', 'Follow-ups'], array_map(fn ($b) => [
            $b['client']['name'] ?? '—',
            Fmt::brl($b['effective_amount']),
            self::STATUS[$b['status']] ?? $b['status'],
            Fmt::dateTime($b['created_at']),
            Fmt::dateTime($b['sent_at']),
            isset($b['forgotten']['days']) && $b['status'] === 'sent' ? $b['forgotten']['days'].' dia(s)' : '—',
            Fmt::num($b['follow_up_count'] ?? 0),
        ], $items));
        $out[] = '';
        $out[] = '_Página '.($meta['current_page'] ?? 1).' de '.($meta['last_page'] ?? 1).'._';

        return implode("\n", $out);
    }
}
