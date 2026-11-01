<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\GoalRemuneration;
use App\Models\RemunerationTier;
use App\Services\GoalRemunerationService;
use App\Services\UserGoalService;
use App\Support\CurrentClinic;
use App\Support\RemunerationTierInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remuneração por tipo de meta (valor por ocorrência, bônus ao bater a meta e, em novo
 * agendamento, faixas por quantidade).
 * Lê quem vê comissões (finance.view); altera quem gerencia comissões (finance.manage).
 */
class GoalRemunerationController extends Controller
{
    private const LABELS = [
        GoalRemuneration::METRIC_APPOINTMENTS => ['label' => 'Novo agendamento', 'unit' => 'por agendamento'],
        GoalRemuneration::METRIC_APPROACHED => ['label' => 'Lead abordado', 'unit' => 'por lead abordado'],
        GoalRemuneration::METRIC_SALES => ['label' => 'Valor de vendas', 'unit' => null],
    ];

    public function __construct(private readonly GoalRemunerationService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request, string $metric): JsonResponse
    {
        abort_unless(in_array($metric, GoalRemuneration::METRICS, true), 404);
        $perOccurrence = in_array($metric, GoalRemuneration::PER_OCCURRENCE_METRICS, true);

        $data = $request->validate([
            'per_occurrence_amount' => $perOccurrence
                ? ['required', 'numeric', 'min:0', 'max:'.GoalRemuneration::MAX_AMOUNT, 'decimal:0,2']
                : ['sometimes', 'nullable', 'numeric', 'in:0'],
            'target_bonus_amount' => ['required', 'numeric', 'min:0', 'max:'.GoalRemuneration::MAX_AMOUNT, 'decimal:0,2'],
        ], [
            'per_occurrence_amount.in' => 'Comissão por venda fica nas regras de comissão.',
            '*.required' => 'Informe um valor (pode ser zero).',
            '*.min' => 'Informe um valor a partir de zero.',
            '*.max' => 'Valor alto demais.',
            '*.numeric' => 'Informe um valor válido.',
            '*.decimal' => 'Use no máximo duas casas decimais.',
        ]);

        $tiers = null;
        if ($metric === GoalRemuneration::METRIC_APPOINTMENTS && $request->has('tiers')) {
            $tiers = RemunerationTierInput::validate($request, RemunerationTier::KIND_APPOINTMENTS);
        } elseif ($request->has('tiers') && $request->input('tiers') !== []) {
            abort(response()->json([
                'message' => 'Faixas só existem para novo agendamento.',
                'errors' => ['tiers' => ['Faixas só existem para novo agendamento.']],
            ], 422));
        }

        $clinicId = (int) CurrentClinic::id();
        if ($tiers !== null) {
            $this->service->replaceTiers($clinicId, RemunerationTier::KIND_APPOINTMENTS, null, $tiers, $request->user()->id);
        }
        GoalRemuneration::query()->updateOrCreate(
            ['clinic_id' => $clinicId, 'metric' => $metric],
            [
                'per_occurrence_amount' => $perOccurrence ? $data['per_occurrence_amount'] : 0,
                'target_bonus_amount' => $data['target_bonus_amount'],
                'updated_by_user_id' => $request->user()->id,
            ],
        );

        // Vale para o mês atual inteiro; meses anteriores só com "Recalcular mês".
        $this->service->syncMonth($clinicId, UserGoalService::currentMonth());

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payload(): array
    {
        $clinicId = (int) CurrentClinic::id();
        $appointmentTiers = RemunerationTierInput::payload($this->service->tiers($clinicId, RemunerationTier::KIND_APPOINTMENTS));

        return $this->service->config($clinicId)
            ->map(fn (GoalRemuneration $r, string $metric) => [
                'metric' => $metric,
                'label' => self::LABELS[$metric]['label'],
                'unit_label' => self::LABELS[$metric]['unit'],
                'supports_per_occurrence' => in_array($metric, GoalRemuneration::PER_OCCURRENCE_METRICS, true),
                'per_occurrence_amount' => number_format((float) $r->per_occurrence_amount, 2, '.', ''),
                'target_bonus_amount' => number_format((float) $r->target_bonus_amount, 2, '.', ''),
                'supports_tiers' => $metric === GoalRemuneration::METRIC_APPOINTMENTS,
                'tiers' => $metric === GoalRemuneration::METRIC_APPOINTMENTS ? $appointmentTiers : [],
            ])
            ->values()
            ->all();
    }
}
