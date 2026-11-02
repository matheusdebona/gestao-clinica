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

#[Name('paciente_detalhe')]
#[Title('Detalhe do paciente')]
#[Description('Ficha resumida de um paciente pelo id (use buscar_pacientes para achar o id): faixa, total comprado em 12 meses, histórico de compras (vendas), orçamentos e consultas/sessões recentes, conforme o que a conta pode ver no app. Não traz telefones nem anotações clínicas.')]
class PacienteDetalheTool extends HofPayTool
{
    private const SALE = ['draft' => 'Rascunho', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada'];

    private const BUDGET = ['draft' => 'Rascunho', 'sent' => 'Enviado', 'accepted' => 'Aceito', 'rejected' => 'Recusado', 'expired' => 'Expirado', 'superseded' => 'Substituído'];

    private const APPOINTMENT = ['scheduled' => 'Agendada', 'in_progress' => 'Em atendimento', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id do paciente (veja buscar_pacientes).')->required(),
        ];
    }

    protected function run(Request $request, ReadOnlyApi $api, User $user): string
    {
        $this->requirePermission($user, 'clients.view', 'pacientes');
        $request->validate(['id' => ['required', 'integer', 'min:1']], [
            'id.*' => 'Informe o id do paciente (número). Use buscar_pacientes para encontrá-lo.',
        ]);
        $id = (int) $request->get('id');
        $c = $api->get($user, 'clients/'.$id)['data'];

        $out = ['## '.$c['name'], ''];
        $out[] = '- Id: '.$c['id'].' · Ativo: '.($c['is_active'] ? 'sim' : 'não').' · Paciente desde: '.Fmt::dateTime($c['created_at']);
        $out[] = '- Faixa: '.($c['tier']['name'] ?? '—').(array_key_exists('purchases_12m', $c) ? ' · Comprado em 12 meses: '.Fmt::brl($c['purchases_12m']) : '');
        $out[] = '- Origem: '.($c['client_origin']['name'] ?? '—').($c['campaign']['name'] ?? null ? ' · Campanha: '.$c['campaign']['name'] : '');
        if ($c['initial_consultation_amount'] !== null) {
            $out[] = '- Valor da consulta: '.Fmt::brl($c['initial_consultation_amount']);
        }

        if ($user->can('sales.view')) {
            // Rascunhos são orçamentos em aberto (listados abaixo): aqui só vendas fechadas, mais novas primeiro.
            $sales = collect($api->get($user, 'sales', ['client_id' => $id])['data'])
                ->reject(fn ($s) => $s['status'] === 'draft')
                ->sortByDesc(fn ($s) => (string) ($s['sold_at'] ?? $s['created_at']))
                ->values()->all();
            $out[] = '';
            $out[] = '### Compras (vendas)';
            $confirmed = collect($sales)->where('status', 'confirmed');
            $out[] = $sales === [] ? 'Nenhuma venda.' : '- Confirmadas: '.$confirmed->count().' · Total confirmado (últimas 20): '.Fmt::brl($confirmed->sum(fn ($s) => (float) $s['effective_amount']));
            if ($sales !== []) {
                $out[] = Fmt::table(['Data', 'Valor', 'Situação', 'Itens'], array_map(fn ($s) => [
                    Fmt::dateTime($s['sold_at'] ?? $s['created_at']),
                    Fmt::brl($s['effective_amount']),
                    self::SALE[$s['status']] ?? $s['status'],
                    collect($s['items'] ?? [])->map(fn ($i) => ($i['product']['name'] ?? $i['description'] ?? 'Item').' × '.Fmt::num($i['quantity'] ?? 1))->implode(', ') ?: '—',
                ], $sales));
            }
        }

        if ($user->can('budgets.view')) {
            $budgets = $api->get($user, 'budgets', ['client_id' => $id])['data'];
            if ($budgets !== []) {
                $out[] = '';
                $out[] = '### Orçamentos';
                $out[] = Fmt::table(['Criado em', 'Valor', 'Situação'], array_map(fn ($b) => [
                    Fmt::dateTime($b['created_at']), Fmt::brl($b['effective_amount']), self::BUDGET[$b['status']] ?? $b['status'],
                ], array_slice($budgets, 0, 20)));
            }
        }

        if ($user->can('appointments.view')) {
            $appointments = $api->get($user, 'appointments', ['client_id' => $id, 'per_page' => 20])['data'];
            if ($appointments !== []) {
                $out[] = '';
                $out[] = '### Consultas e sessões';
                $out[] = Fmt::table(['Quando', 'Profissional', 'Situação', 'Tipo'], array_map(fn ($a) => [
                    Fmt::dateTime($a['scheduled_at']), $a['professional']['name'] ?? '—', self::APPOINTMENT[$a['status']] ?? $a['status'],
                    $a['treatment_id'] ? 'Sessão de tratamento' : 'Consulta',
                ], $appointments));
            }
        }

        return implode("\n", $out);
    }
}
