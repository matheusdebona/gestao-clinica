<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeadFollowUpTier;
use App\Models\LeadStatus;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Configurações › Alertas de acompanhamento (prazos de "lead parado"). */
class LeadFollowUpTierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => LeadFollowUpTier::query()->orderBy('days')->get()->map(fn (LeadFollowUpTier $tier) => $this->present($tier)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tier = LeadFollowUpTier::query()->create([
            ...$this->validated($request),
            'clinic_id' => CurrentClinic::id(),
        ]);

        return response()->json(['data' => $this->present($tier)], 201);
    }

    public function update(Request $request, LeadFollowUpTier $tier): JsonResponse
    {
        $tier->update($this->validated($request, $tier));

        return response()->json(['data' => $this->present($tier->fresh())]);
    }

    public function destroy(LeadFollowUpTier $tier): JsonResponse
    {
        $tier->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array{days: int, label: string|null, color: string}
     */
    private function validated(Request $request, ?LeadFollowUpTier $tier = null): array
    {
        $data = $request->validate([
            'days' => [
                'required', 'integer', 'min:'.LeadFollowUpTier::MIN_DAYS, 'max:'.LeadFollowUpTier::MAX_DAYS,
                Rule::unique('lead_follow_up_tiers', 'days')
                    ->where('clinic_id', CurrentClinic::id())
                    ->ignore($tier?->id),
            ],
            'label' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', Rule::in(LeadStatus::COLORS)],
        ], [
            'days.required' => 'Informe os dias.',
            'days.integer' => 'Informe um número inteiro de dias.',
            'days.min' => 'Use pelo menos '.LeadFollowUpTier::MIN_DAYS.' dia.',
            'days.max' => 'Use no máximo '.LeadFollowUpTier::MAX_DAYS.' dias.',
            'days.unique' => 'Já existe um alerta com esse número de dias.',
            'label.max' => 'Nome muito longo (máximo de 60 caracteres).',
        ]);

        $label = isset($data['label']) ? trim((string) $data['label']) : '';

        return [
            'days' => (int) $data['days'],
            'label' => $label !== '' ? $label : null,
            'color' => $data['color'] ?? 'warning',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeadFollowUpTier $tier): array
    {
        return [
            'id' => $tier->id,
            'days' => $tier->days,
            'label' => $tier->label,
            'display_label' => $tier->displayLabel(),
            'color' => $tier->color,
        ];
    }
}
