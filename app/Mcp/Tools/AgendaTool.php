<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Fmt;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\Pii;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\User;
use App\Support\ClinicMonth;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('agenda')]
#[Title('Agenda')]
#[Description('Consultas e sessões agendadas num dia ou período (horário de Brasília): paciente, profissional, horário, duração, situação e confirmação pelo WhatsApp. Sem parâmetros: hoje. Período de até 31 dias, até 100 itens.')]
class AgendaTool extends HofPayTool
{
    private const STATUS = [
        'scheduled' => 'Agendada', 'in_progress' => 'Em atendimento', 'completed' => 'Concluída', 'cancelled' => 'Cancelada',
    ];

    private const CONFIRMATION = [
        'pending' => 'Aguardando confirmação', 'confirmed' => 'Confirmado', 'not_confirmed' => 'Não confirmado', 'declined' => 'Desmarcou',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'de' => $schema->string()->description('Data inicial AAAA-MM-DD (Brasília). Padrão: hoje.'),
            'ate' => $schema->string()->description('Data final AAAA-MM-DD. Padrão: igual à inicial.'),
            'situacao' => $schema->string()->enum(array_keys(self::STATUS))->description('scheduled (agendada), in_progress, completed (concluída) ou cancelled.'),
            'paciente' => $schema->string()->description('Parte do nome do paciente (só nome).'),
            'limite' => $schema->integer()->description('Máximo de itens (padrão 50, máx. 100).'),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'appointments.view', 'a agenda');
        $today = ClinicMonth::todayDate();
        [$from, $to] = $this->period($request, [$today, $today]);
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 31) {
            throw new McpToolException('Para a agenda, use um período de até 31 dias.');
        }
        $status = $request->get('situacao');
        if ($status !== null && $status !== '' && ! isset(self::STATUS[$status])) {
            throw new McpToolException('Situação inválida. Use: '.implode(', ', array_keys(self::STATUS)).'.');
        }

        $body = $api->get($user, 'appointments', [
            'from' => $from, 'to' => $to, 'status' => $status,
            'q' => Pii::assertNameSearch($request->get('paciente'), 'busca por paciente'), 'per_page' => $this->limit($request, 50, 100),
        ]);
        $items = $body['data'];
        $total = $body['meta']['total'] ?? count($items);

        $out = ['## Agenda — '.Fmt::period($from, $to), ''];
        if ($items === []) {
            $out[] = 'Nenhum agendamento no período.';

            return implode("\n", $out);
        }

        $counts = collect($items)->countBy('status');
        $out[] = '- Total: '.Fmt::num($total).' ('.collect(self::STATUS)->map(fn ($l, $k) => $counts[$k] ?? 0 ? $l.': '.$counts[$k] : null)->filter()->implode(' · ').')';
        $out[] = '';
        $out[] = Fmt::table(['Quando', 'Paciente', 'Profissional', 'Duração', 'Situação', 'Confirmação', 'Tipo'], array_map(fn ($a) => [
            Fmt::dateTime($a['scheduled_at']),
            $a['client']['name'] ?? '—',
            $a['professional']['name'] ?? '—',
            $a['duration_minutes'] ? $a['duration_minutes'].' min' : '—',
            self::STATUS[$a['status']] ?? $a['status'],
            self::CONFIRMATION[$a['confirmation_status'] ?? ''] ?? '—',
            $a['treatment_id'] ? 'Sessão de tratamento' : 'Consulta',
        ], $items));
        if ($total > count($items)) {
            $out[] = '';
            $out[] = '_Mostrando '.count($items).' de '.$total.'. Use um período menor ou o parâmetro limite._';
        }

        return implode("\n", $out);
    }
}
