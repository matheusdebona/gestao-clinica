<?php

namespace App\Services\Finance;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\TaxRateResolver;

/**
 * Números de uma venda usados por comissões, margem de contribuição e DRE.
 *
 * Grupos: chave = protocol_id dos itens (0 = itens avulsos, sem protocolo). Extras cobrados
 * na sessão ficam no grupo 0.
 *
 * Espera a venda com: items, payments, treatment.appointments.consumptions.saleItem.
 */
class SaleEconomics
{
    public function __construct(private readonly TaxRateResolver $taxes) {}

    /**
     * Receita da venda (valor efetivo) repartida por protocolo, proporcional ao total de cada item.
     *
     * @return array<int, float>
     */
    public function revenueByGroup(Sale $sale): array
    {
        $effective = (float) $sale->effective_amount;
        $lines = [];
        foreach ($sale->items as $item) {
            $key = (int) ($item->source_protocol_id ?? 0);
            $lines[$key] = ($lines[$key] ?? 0) + (float) $item->line_total;
        }
        $sum = array_sum($lines);
        if ($sum <= 0) {
            return [0 => $effective];
        }

        return array_map(fn (float $v) => $effective * $v / $sum, $lines);
    }

    /** Fator efetivo/esperado para levar o preço de cada item ao valor realmente cobrado. */
    public function priceFactor(Sale $sale): float
    {
        $lines = (float) $sale->items->sum(fn (SaleItem $i) => (float) $i->line_total);

        return $lines > 0 ? (float) $sale->effective_amount / $lines : 0.0;
    }

    /** @return list<int> pagamentos de extras cobrados na sessão */
    public function extraPaymentIds(Sale $sale): array
    {
        $ids = [];
        foreach ($this->completedAppointments($sale) as $appointment) {
            foreach ($appointment->consumptions as $c) {
                if ($c->sale_payment_id !== null) {
                    $ids[] = (int) $c->sale_payment_id;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array{card_fees: float, anticipation: float}
     */
    public function paymentCosts(Sale $sale, bool $includeExtras = true): array
    {
        $extras = $includeExtras ? [] : $this->extraPaymentIds($sale);
        $fees = 0.0;
        $anticipation = 0.0;
        foreach ($sale->payments as $payment) {
            if (in_array((int) $payment->id, $extras, true)) {
                continue;
            }
            $fees += (float) $payment->fee_amount;
            $anticipation += (float) $payment->anticipation_fee_amount;
        }

        return ['card_fees' => $fees, 'anticipation' => $anticipation];
    }

    public function taxRatePercent(Sale $sale): float
    {
        return $this->taxes->ratePercentFor((int) $sale->clinic_id, $sale->sold_at ?? now());
    }

    /** Parcela da venda que sobra após taxas de cartão, antecipação e impostos (0..1). */
    public function netRatio(Sale $sale): float
    {
        $effective = (float) $sale->effective_amount;
        if ($effective <= 0) {
            return 0.0;
        }
        $costs = $this->paymentCosts($sale, includeExtras: false);
        $net = $effective - $costs['card_fees'] - $costs['anticipation'] - $effective * $this->taxRatePercent($sale) / 100;

        return max(0.0, $net / $effective);
    }

    /**
     * Valor executado numa sessão: itens vendidos aplicados (ao preço efetivo) + extras cobrados.
     *
     * @return array<int, float> por grupo de protocolo
     */
    public function executedByGroup(Appointment $appointment, ?Sale $sale): array
    {
        $factor = $sale ? $this->priceFactor($sale) : 0.0;
        $groups = [];
        foreach ($appointment->consumptions as $c) {
            /** @var AppointmentConsumption $c */
            $item = $c->saleItem;
            if ($item !== null && (float) $item->quantity > 0) {
                $key = (int) ($item->source_protocol_id ?? 0);
                $value = ((float) $c->quantity / (float) $item->quantity) * (float) $item->line_total * $factor;
                $groups[$key] = ($groups[$key] ?? 0) + $value;
            }
            if ((float) $c->charged_amount > 0) {
                $groups[0] = ($groups[0] ?? 0) + (float) $c->charged_amount;
            }
        }

        return $groups;
    }

    /**
     * @return iterable<Appointment>
     */
    public function completedAppointments(Sale $sale): iterable
    {
        $treatment = $sale->treatment;
        if ($treatment === null) {
            return [];
        }

        return $treatment->appointments->where('status', Appointment::STATUS_COMPLETED);
    }
}
