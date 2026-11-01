<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PatientTier;
use App\Services\PatientTierService;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultPatientTiers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Configurações › Faixas de paciente (total comprado nos últimos 12 meses). */
class PatientTierController extends Controller
{
    public function __construct(private readonly PatientTierService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->presentAll()]);
    }

    public function store(Request $request): JsonResponse
    {
        $tier = PatientTier::query()->create([
            ...$this->validated($request),
            'clinic_id' => CurrentClinic::id(),
        ]);

        return response()->json(['data' => $this->presentOne($tier), 'tiers' => $this->presentAll()], 201);
    }

    public function update(Request $request, PatientTier $tier): JsonResponse
    {
        $tier->update($this->validated($request, $tier));

        return response()->json(['data' => $this->presentOne($tier->fresh()), 'tiers' => $this->presentAll()]);
    }

    public function destroy(PatientTier $tier): JsonResponse
    {
        $tier->delete();

        return response()->json(['data' => ['deleted' => true], 'tiers' => $this->presentAll()]);
    }

    /** Recria Bronze/Prata/Ouro/Diamante; só funciona quando a clínica está sem faixas. */
    public function restoreDefaults(): JsonResponse
    {
        if (! EnsureDefaultPatientTiers::run((int) CurrentClinic::id())) {
            return response()->json([
                'message' => 'A clínica já tem faixas. Exclua todas para usar a sugestão.',
                'errors' => ['tiers' => ['A clínica já tem faixas. Exclua todas para usar a sugestão.']],
            ], 422);
        }

        return response()->json(['data' => $this->presentAll()], 201);
    }

    /**
     * @return array{name: string, color: string|null, min_amount: string}
     */
    private function validated(Request $request, ?PatientTier $tier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', Rule::in(PatientTier::COLORS)],
            'min_amount' => [
                'required', 'numeric', 'min:0', 'max:'.PatientTier::MAX_AMOUNT, 'decimal:0,2',
                Rule::unique('patient_tiers', 'min_amount')
                    ->where('clinic_id', CurrentClinic::id())
                    ->ignore($tier?->id),
            ],
        ], [
            'name.required' => 'Informe o nome da faixa.',
            'name.max' => 'Nome muito longo (máximo de 40 caracteres).',
            'color.in' => 'Escolha uma das cores da lista.',
            'min_amount.required' => 'Informe o valor mínimo.',
            'min_amount.numeric' => 'Informe um valor válido.',
            'min_amount.min' => 'O valor mínimo não pode ser negativo.',
            'min_amount.max' => 'Valor muito alto.',
            'min_amount.decimal' => 'Use no máximo 2 casas decimais.',
            'min_amount.unique' => 'Já existe uma faixa começando nesse valor.',
        ]);

        $name = trim((string) $data['name']);
        if ($name === '') {
            abort(response()->json([
                'message' => 'Informe o nome da faixa.',
                'errors' => ['name' => ['Informe o nome da faixa.']],
            ], 422));
        }

        return [
            'name' => $name,
            'color' => $data['color'] ?? null,
            'min_amount' => number_format((float) $data['min_amount'], 2, '.', ''),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function presentAll(): array
    {
        $ranges = $this->service->ranges($this->service->tiers());

        return array_map(fn (array $r) => $this->present($r['tier'], $r['min_cents'], $r['next_cents']), $ranges);
    }

    /** @return array<string, mixed> */
    private function presentOne(PatientTier $tier): array
    {
        foreach ($this->service->ranges($this->service->tiers()) as $r) {
            if ($r['tier']->id === $tier->id) {
                return $this->present($r['tier'], $r['min_cents'], $r['next_cents']);
            }
        }

        return $this->present($tier, $tier->minCents(), null);
    }

    /** @return array<string, mixed> */
    private function present(PatientTier $tier, int $minCents, ?int $nextCents): array
    {
        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'color' => $tier->color,
            'min_amount' => number_format($minCents / 100, 2, '.', ''),
            'max_amount' => $nextCents === null ? null : number_format(($nextCents - 1) / 100, 2, '.', ''),
            'range_label' => $this->service->rangeLabel($minCents, $nextCents),
        ];
    }
}
