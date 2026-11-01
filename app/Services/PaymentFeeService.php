<?php

namespace App\Services;

use App\Models\CardFeeRule;
use App\Models\CardOperator;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;

/**
 * Taxa de maquininha/forma de pagamento e antecipação gravadas em cada pagamento.
 *
 * Precedência da taxa: regra de cartão ativa (método + operadora + bandeira + parcelas)
 * > taxa padrão do método (payment_methods.fee_percent/fee_fixed) > sem taxa.
 *
 * Antecipação: só para crédito, quando a operadora antecipa automaticamente e tem
 * taxa de antecipação (% ao mês). Cada parcela i é recebida no mês i; antecipar tudo
 * equivale a, em média, (n + 1) / 2 meses de desconto sobre o valor já líquido da taxa.
 */
class PaymentFeeService
{
    public const SOURCE_RULE = 'rule';

    public const SOURCE_METHOD = 'method';

    public const SOURCE_NONE = 'none';

    /**
     * @return array{fee_source: string, fee_percent_applied: ?string, fee_fixed_applied: ?string, fee_amount: string, anticipation_percent_applied: ?string, anticipation_fee_amount: string, net_amount: string}
     */
    public function compute(
        int $clinicId,
        PaymentMethod $method,
        float $amount,
        ?int $operatorId,
        ?int $brandId,
        ?int $installments,
    ): array {
        $percent = null;
        $fixed = null;
        $source = self::SOURCE_NONE;

        if ($method->isCardKind() && $operatorId && $brandId && $installments) {
            $rule = CardFeeRule::query()
                ->withoutGlobalScope('clinic')
                ->where('clinic_id', $clinicId)
                ->where('payment_method_id', $method->id)
                ->where('card_operator_id', $operatorId)
                ->where('card_brand_id', $brandId)
                ->where('installments', $installments)
                ->where('is_active', true)
                ->first();

            if ($rule !== null) {
                $percent = $rule->fee_percent;
                $fixed = $rule->fee_fixed;
                $source = self::SOURCE_RULE;
            }
        }

        if ($source === self::SOURCE_NONE && ($method->fee_percent !== null || $method->fee_fixed !== null)) {
            $percent = $method->fee_percent;
            $fixed = $method->fee_fixed;
            $source = self::SOURCE_METHOD;
        }

        $fee = 0.0;
        if ($source !== self::SOURCE_NONE) {
            $fee = round($amount * ((float) $percent) / 100 + (float) $fixed, 2);
            $fee = min($fee, max($amount, 0));
        }

        $anticipationPercent = null;
        $anticipation = 0.0;
        if ($method->kind === PaymentMethod::KIND_CREDIT_CARD && $operatorId) {
            $operator = CardOperator::query()->withoutGlobalScope('clinic')->find($operatorId);
            $rate = $operator?->anticipation_rate_percent !== null ? (float) $operator->anticipation_rate_percent : 0.0;
            if ($operator?->auto_anticipate && $rate > 0) {
                $n = max(1, (int) $installments);
                $months = ($n + 1) / 2;
                $anticipationPercent = round($rate * $months, 4);
                $anticipation = round(($amount - $fee) * $anticipationPercent / 100, 2);
            }
        }

        return [
            'fee_source' => $source,
            'fee_percent_applied' => $percent !== null ? number_format((float) $percent, 4, '.', '') : null,
            'fee_fixed_applied' => $fixed !== null ? number_format((float) $fixed, 2, '.', '') : null,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'anticipation_percent_applied' => $anticipationPercent !== null ? number_format($anticipationPercent, 4, '.', '') : null,
            'anticipation_fee_amount' => number_format($anticipation, 2, '.', ''),
            'net_amount' => number_format($amount - $fee - $anticipation, 2, '.', ''),
        ];
    }

    public function apply(SalePayment $payment, ?int $clinicId = null): SalePayment
    {
        $clinicId ??= (int) Sale::query()->withoutGlobalScope('clinic')->whereKey($payment->sale_id)->value('clinic_id');
        $method = PaymentMethod::query()->withoutGlobalScope('clinic')->find($payment->payment_method_id);
        if ($method === null) {
            return $payment;
        }

        $payment->forceFill($this->compute(
            $clinicId,
            $method,
            (float) $payment->amount,
            $payment->card_operator_id,
            $payment->card_brand_id,
            $payment->installments,
        ))->save();

        return $payment;
    }

    public function applyToSale(Sale $sale): void
    {
        $sale->loadMissing('payments');
        foreach ($sale->payments as $payment) {
            $this->apply($payment, (int) $sale->clinic_id);
        }
    }

    /**
     * Recalcula todos os pagamentos (opcionalmente só de uma clínica). Idempotente.
     */
    public function backfill(?int $clinicId = null): int
    {
        $count = 0;
        $query = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->select('sale_payments.id', 'sales.clinic_id')
            ->orderBy('sale_payments.id');
        if ($clinicId !== null) {
            $query->where('sales.clinic_id', $clinicId);
        }

        $query->chunk(500, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payment = SalePayment::query()->find($row->id);
                if ($payment !== null) {
                    $this->apply($payment, (int) $row->clinic_id);
                    $count++;
                }
            }
        });

        return $count;
    }
}
