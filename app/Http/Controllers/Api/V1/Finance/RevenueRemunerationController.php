<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\RemunerationTier;
use App\Models\User;
use App\Services\GoalRemunerationService;
use App\Services\UserGoalService;
use App\Support\CurrentClinic;
use App\Support\RemunerationTierInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remuneração por faturamento total da clínica no mês: faixas por colaborador (gatilho de
 * faturamento, valor fixo e/ou % do faturamento total). Vale só a maior faixa atingida.
 * Lê quem vê comissões (finance.view); altera quem gerencia comissões (finance.manage).
 */
class RevenueRemunerationController extends Controller
{
    public function __construct(private readonly GoalRemunerationService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $clinicId = (int) CurrentClinic::id();
        abort_unless((int) $user->clinic_id === $clinicId, 404);

        $tiers = RemunerationTierInput::validate($request, RemunerationTier::KIND_REVENUE);
        $this->service->replaceTiers($clinicId, RemunerationTier::KIND_REVENUE, $user->id, $tiers, $request->user()->id);

        // Vale para o mês atual inteiro; meses anteriores só com "Recalcular mês".
        $this->service->syncRevenueMonth($clinicId, UserGoalService::currentMonth());

        return response()->json($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $clinicId = (int) CurrentClinic::id();
        $tiers = RemunerationTier::query()
            ->where('kind', RemunerationTier::KIND_REVENUE)
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->orderBy('threshold')
            ->get()
            ->groupBy('user_id');

        return [
            'data' => $tiers->map(fn ($rows, $userId) => [
                'user_id' => (int) $userId,
                'user_name' => $rows->first()->user?->name,
                'tiers' => RemunerationTierInput::payload($rows),
            ])->sortBy('user_name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'meta' => [
                'month' => UserGoalService::currentMonth(),
                'month_revenue' => number_format($this->service->clinicRevenue($clinicId, UserGoalService::currentMonth()), 2, '.', ''),
                'users' => User::query()
                    ->where('clinic_id', $clinicId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
            ],
        ];
    }
}
