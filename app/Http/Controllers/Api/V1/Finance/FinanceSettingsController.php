<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CardOperatorResource;
use App\Models\CardOperator;
use App\Models\Sale;
use App\Models\TaxSetting;
use App\Services\PaymentFeeService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceSettingsController extends Controller
{
    public function taxSettings(): JsonResponse
    {
        $settings = TaxSetting::query()->orderByDesc('effective_from')->get();

        return response()->json([
            'data' => $settings->map(fn (TaxSetting $s) => $this->taxPayload($s))->values(),
        ]);
    }

    public function storeTaxSetting(Request $request): JsonResponse
    {
        $percent = ['nullable', 'numeric', 'min:0', 'max:100'];
        $data = $request->validate([
            'effective_month' => ['required', 'date_format:Y-m'],
            'regime' => ['required', Rule::in(TaxSetting::REGIMES)],
            'simples_rate_percent' => [...$percent, 'required_if:regime,simples'],
            'iss_percent' => $percent,
            'pis_percent' => $percent,
            'cofins_percent' => $percent,
            'irpj_percent' => $percent,
            'csll_percent' => $percent,
            'other_percent' => $percent,
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'simples_rate_percent.required_if' => 'Informe a alíquota efetiva do Simples.',
        ]);

        $from = CarbonImmutable::createFromFormat('Y-m', $data['effective_month'])->startOfMonth()->toDateString();
        unset($data['effective_month']);

        $setting = TaxSetting::query()->updateOrCreate(
            ['clinic_id' => CurrentClinic::id(), 'effective_from' => $from],
            $data,
        );

        return response()->json(['data' => $this->taxPayload($setting->fresh())], 201);
    }

    public function destroyTaxSetting(TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting->delete();

        return response()->json(['message' => 'Vigência removida.']);
    }

    public function updateAnticipation(Request $request, CardOperator $cardOperator): CardOperatorResource
    {
        $data = $request->validate([
            'auto_anticipate' => ['required', 'boolean'],
            'anticipation_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $cardOperator->update($data);

        return new CardOperatorResource($cardOperator->fresh());
    }

    /**
     * Recalcula taxa e líquido dos pagamentos com as regras atuais (ex.: depois de cadastrar as taxas).
     */
    public function recalculatePaymentFees(Request $request, PaymentFeeService $fees): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $count = 0;
        Sale::query()
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where(
                fn ($w) => $w->whereNull('sold_at')->orWhere('sold_at', '>=', $from)
            ))
            ->with('payments')
            ->chunkById(200, function ($sales) use ($fees, &$count) {
                foreach ($sales as $sale) {
                    $fees->applyToSale($sale);
                    $count += $sale->payments->count();
                }
            });

        return response()->json(['data' => ['payments_updated' => $count]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taxPayload(TaxSetting $s): array
    {
        return [
            'id' => $s->id,
            'effective_month' => $s->effective_from->format('Y-m'),
            'regime' => $s->regime,
            'simples_rate_percent' => $s->simples_rate_percent,
            'iss_percent' => $s->iss_percent,
            'pis_percent' => $s->pis_percent,
            'cofins_percent' => $s->cofins_percent,
            'irpj_percent' => $s->irpj_percent,
            'csll_percent' => $s->csll_percent,
            'other_percent' => $s->other_percent,
            'effective_rate_percent' => number_format($s->effectiveRatePercent(), 4, '.', ''),
            'notes' => $s->notes,
        ];
    }
}
