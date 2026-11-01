<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Protocol;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function rules(): JsonResponse
    {
        $rules = CommissionRule::query()
            ->with(['user:id,name', 'protocol:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (CommissionRule $r) => $this->rulePayload($r))->values(),
            'meta' => [
                'users' => User::query()
                    ->where('clinic_id', CurrentClinic::id())
                    ->where('is_active', true)
                    ->with('roles:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'roles' => $u->roles->pluck('name')->values(),
                    ]),
                'protocols' => Protocol::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $rule = CommissionRule::query()->create($this->validateRule($request));

        return response()->json(['data' => $this->rulePayload($rule->load(['user:id,name', 'protocol:id,name']))], 201);
    }

    public function updateRule(Request $request, CommissionRule $commissionRule): JsonResponse
    {
        $commissionRule->update($this->validateRule($request, partial: true));

        return response()->json(['data' => $this->rulePayload($commissionRule->fresh(['user:id,name', 'protocol:id,name']))]);
    }

    public function destroyRule(CommissionRule $commissionRule): JsonResponse
    {
        $commissionRule->update(['is_active' => false]);

        return response()->json(['message' => 'Regra desativada.']);
    }

    public function entries(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('Y-m', $data['month'])->startOfMonth();

        $entries = CommissionEntry::query()
            ->whereDate('competence_month', $month->toDateString())
            ->where('status', '!=', CommissionEntry::STATUS_CANCELLED)
            ->with(['user:id,name', 'protocol:id,name', 'sale:id,client_id,sold_at', 'sale.client:id,name', 'appointment:id,client_id,finished_at', 'appointment.client:id,name', 'lead:id,name,client_id,approached_at,booked_at', 'lead.client:id,name'])
            ->orderBy('user_id')
            ->orderByDesc('id')
            ->get();

        $byUser = $entries->groupBy('user_id')->map(function ($rows) {
            $first = $rows->first();

            return [
                'user_id' => $first->user_id,
                'user_name' => $first->user?->name,
                'total' => number_format((float) $rows->sum('amount'), 2, '.', ''),
                'pending' => number_format((float) $rows->where('status', CommissionEntry::STATUS_PENDING)->sum('amount'), 2, '.', ''),
                'paid' => number_format((float) $rows->where('status', CommissionEntry::STATUS_PAID)->sum('amount'), 2, '.', ''),
                'count' => $rows->count(),
                'by_origin' => $this->byOrigin($rows),
            ];
        })->sortByDesc('total')->values();

        return response()->json([
            'data' => $entries->map(fn (CommissionEntry $e) => [
                'id' => $e->id,
                'user_id' => $e->user_id,
                'user_name' => $e->user?->name,
                'role' => $e->role,
                'sale_id' => $e->sale_id,
                'appointment_id' => $e->appointment_id,
                'origin' => $this->origin($e),
                'goal_metric' => $e->goal_metric,
                'goal_kind' => $e->goal_kind,
                'tier_threshold' => $e->tier_threshold,
                'lead_id' => $e->lead_id,
                'client_name' => $e->appointment?->client?->name ?? $e->sale?->client?->name ?? $e->lead?->client?->name ?? $e->lead?->name,
                'occurred_at' => $e->appointment?->finished_at ?? $e->sale?->sold_at ?? $this->goalOccurredAt($e),
                'protocol_name' => $e->protocol?->name,
                'base_amount' => $e->base_amount,
                'rate_percent' => $e->rate_percent,
                'amount' => $e->amount,
                'status' => $e->status,
                'paid_at' => $e->paid_at,
            ])->values(),
            'meta' => [
                'month' => $month->format('Y-m'),
                'total' => number_format((float) $entries->sum('amount'), 2, '.', ''),
                'pending' => number_format((float) $entries->where('status', CommissionEntry::STATUS_PENDING)->sum('amount'), 2, '.', ''),
                'by_origin' => $this->byOrigin($entries),
                'by_user' => $byUser,
            ],
        ]);
    }

    public function rebuild(Request $request, CommissionService $service): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $service->rebuildMonth(
            (int) CurrentClinic::id(),
            CarbonImmutable::createFromFormat('Y-m', $data['month'])->startOfMonth(),
        );

        return response()->json(['data' => ['processed' => $count]]);
    }

    public function markPaid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'paid' => ['required', 'boolean'],
        ]);

        $query = CommissionEntry::query()
            ->whereIn('id', $data['ids'])
            ->where('status', $data['paid'] ? CommissionEntry::STATUS_PENDING : CommissionEntry::STATUS_PAID);

        $updated = $query->update($data['paid']
            ? ['status' => CommissionEntry::STATUS_PAID, 'paid_at' => now()]
            : ['status' => CommissionEntry::STATUS_PENDING, 'paid_at' => null]);

        return response()->json(['data' => ['updated' => $updated]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, bool $partial = false): array
    {
        $clinicId = CurrentClinic::id();
        $req = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'role' => [$req, Rule::in(CommissionRule::ROLES)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('clinic_id', $clinicId)],
            'protocol_id' => ['nullable', 'integer', Rule::exists('protocols', 'id')->where('clinic_id', $clinicId)],
            'calc_type' => [$req, Rule::in(CommissionRule::TYPES)],
            'value' => [$req, 'numeric', 'gt:0'],
            'base' => ['sometimes', Rule::in(CommissionRule::BASES)],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'value.gt' => 'Informe um valor maior que zero.',
        ]);

        $type = $data['calc_type'] ?? $request->route('commission_rule')?->calc_type;
        if ($type === CommissionRule::TYPE_PERCENT && isset($data['value']) && (float) $data['value'] > 100) {
            abort(response()->json([
                'message' => 'Percentual máximo de 100%.',
                'errors' => ['value' => ['Percentual máximo de 100%.']],
            ], 422));
        }

        return $data;
    }

    /**
     * sales = comissão de venda, sessions = comissão de sessão, goals = remuneração por meta,
     * revenue = remuneração por faturamento da clínica.
     */
    private function origin(CommissionEntry $e): string
    {
        return match ($e->role) {
            CommissionEntry::ROLE_GOAL => 'goals',
            CommissionEntry::ROLE_REVENUE => 'revenue',
            CommissionRule::ROLE_PROFESSIONAL => 'sessions',
            default => 'sales',
        };
    }

    private function goalOccurredAt(CommissionEntry $e): mixed
    {
        if ($e->role !== CommissionEntry::ROLE_GOAL || $e->lead === null) {
            return null;
        }

        return $e->goal_metric === 'approached' ? $e->lead->approached_at : $e->lead->booked_at;
    }

    /**
     * @param  Collection<int, CommissionEntry>  $rows
     * @return array{sales: string, sessions: string, goals: string, revenue: string}
     */
    private function byOrigin($rows): array
    {
        $out = ['sales' => 0.0, 'sessions' => 0.0, 'goals' => 0.0, 'revenue' => 0.0];
        foreach ($rows as $row) {
            $out[$this->origin($row)] += (float) $row->amount;
        }

        return array_map(fn (float $v) => number_format($v, 2, '.', ''), $out);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulePayload(CommissionRule $r): array
    {
        return [
            'id' => $r->id,
            'role' => $r->role,
            'user_id' => $r->user_id,
            'user_name' => $r->user?->name,
            'protocol_id' => $r->protocol_id,
            'protocol_name' => $r->protocol?->name,
            'calc_type' => $r->calc_type,
            'value' => $r->value,
            'base' => $r->base,
            'is_active' => $r->is_active,
            'notes' => $r->notes,
        ];
    }
}
