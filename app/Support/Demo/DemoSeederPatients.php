<?php

namespace App\Support\Demo;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentConfirmationService;
use App\Services\AppointmentService;
use App\Services\BudgetService;
use App\Services\SalePricingService;
use App\Services\TreatmentService;
use Carbon\CarbonImmutable;

/**
 * Pacientes: consultas, orçamentos, vendas, sessões (com consumo de estoque) e retornos.
 */
trait DemoSeederPatients
{
    use DemoSeederLeads;

    private function scheduleBasePatients(): void
    {
        $count = max(2, (int) round(12 * $this->scale));
        $november = (int) ceil($count / 2);
        $october = $november + max(1, (int) round(3 * $this->scale));
        for ($i = 0; $i < $count; $i++) {
            $at = $this->from->setTime(9, 0)->addMinutes($i * 7);
            $withNovember = $i < $november;
            $withOctober = ! $withNovember && $i < $october;
            $this->tl->at($at, function () use ($withNovember, $withOctober): void {
                $origin = $this->pick(['Indicação' => 5, 'Fachada' => 2, 'Site' => 1]);
                $client = Client::query()->create([
                    'clinic_id' => $this->cid,
                    'name' => $this->name(),
                    'whatsapp' => $this->phone(),
                    'client_origin_id' => $this->origins[$origin],
                    'notes' => 'Paciente da base anterior (cadastro importado).',
                    'is_active' => true,
                ]);
                $this->bump('clients_base');
                $this->demoClients[] = (int) $client->id;
                if ($withOctober) {
                    $book = $this->workTime($this->from->addMonthNoOverflow()->addDays(mt_rand(0, 12))->setTime(9, 0), 0, 300);
                    $this->tl->at($book, fn () => $this->bookConsulta($client, $this->proFor(), $this->spNow()->addDays(mt_rand(2, 8)), $this->sdr));
                }
                if ($withNovember) {
                    $lastMonth = $this->to->startOfMonth();
                    $book = $this->workTime($lastMonth->subDays(mt_rand(3, 12))->setTime(9, 0), 0, 300);
                    $this->tl->at($book, fn () => $this->bookConsulta($client, $this->proFor(), $lastMonth->addDays(mt_rand(2, 24)), $this->sdr, 'Consulta de retorno'));
                }
            });
        }
    }

    private function scheduleDirectPatients(): void
    {
        $count = max(2, (int) round(7 * $this->scale));
        for ($i = 0; $i < $count; $i++) {
            $at = $this->workTime($this->from->addDays(mt_rand(0, 9))->setTime(9, 0), 0, 480);
            $this->tl->at($at, function (): void {
                $origin = $this->pick(['Indicação' => 6, 'Fachada' => 2, 'WhatsApp' => 2]);
                $client = Client::query()->create([
                    'clinic_id' => $this->cid,
                    'name' => $this->name(),
                    'whatsapp' => $this->phone(),
                    'client_origin_id' => $this->origins[$origin],
                    'main_pains' => DemoCatalog::INTERESTS[mt_rand(0, count(DemoCatalog::INTERESTS) - 1)],
                    'is_active' => true,
                ]);
                $this->bump('clients_direct');
                $this->demoClients[] = (int) $client->id;
                $this->bookConsulta($client, $this->proFor(), $this->spNow()->addDays(mt_rand(1, 5)), $this->sdr);
            });
        }
    }

    /**
     * Agenda do resto do último mês: retornos de manutenção marcados nas últimas semanas
     * (vários já para o primeiro dia útil depois de "hoje") e dois pacotes fechados agora
     * com a primeira sessão nesse dia (consome os itens que ficam abaixo do mínimo).
     */
    private function scheduleFutureAgenda(): void
    {
        $nowSp = $this->now->setTimezone(self::TZ);
        $firstDay = $nowSp->addDay()->startOfDay();
        while (! $this->isBusinessDay($firstDay)) {
            $firstDay = $firstDay->addDay();
        }
        $count = max(3, (int) round(22 * $this->scale));
        for ($i = 0; $i < $count; $i++) {
            $bookAt = $this->workTime($nowSp->subDays(mt_rand(1, 14))->setTime(9, 0), 0, 540);
            if ($bookAt->gt($nowSp)) {
                $bookAt = $nowSp->subHours(mt_rand(2, 30));
            }
            $target = $i < max(2, (int) round(5 * $this->scale)) ? $firstDay->setTime(9, 0) : $firstDay->addDays(mt_rand(1, 24))->setTime(9, 0);
            $this->tl->at($bookAt, function () use ($target, $i): void {
                $client = Client::query()->whereIn('id', $this->demoClients)->whereHas('sales', fn ($q) => $q->where('status', Sale::STATUS_CONFIRMED))
                    ->whereNotIn('id', Appointment::query()->where('status', Appointment::STATUS_SCHEDULED)->select('client_id'))
                    ->orderBy('id')->get()->pipe(fn ($c) => $c->isEmpty() ? null : $c[mt_rand(0, $c->count() - 1)])
                    ?? Client::query()->whereIn('id', $this->demoClients)->whereNotIn('id', Appointment::query()->where('status', Appointment::STATUS_SCHEDULED)->select('client_id'))->orderBy('id')->get()->pipe(fn ($c) => $c->isEmpty() ? null : $c[mt_rand(0, $c->count() - 1)]);
                if ($client === null) {
                    return;
                }
                $this->bookConsulta($client, $i % 3 === 0 ? $this->pro2 : $this->pro1, $target, $this->sdr, 'Retorno de manutenção');
                $this->bump('future_maintenance');
            });
        }
        foreach ([['SKIN'], ['SCUL', 'TOX_SUP']] as $n => $keys) {
            $at = $nowSp->subDays(2 + $n)->setTime(11, 0);
            while (! $this->isBusinessDay($at)) {
                $at = $at->subDay();
            }
            $this->tl->at($at, function () use ($keys, $firstDay): void {
                $client = Client::query()->whereIn('id', $this->demoClients)->whereDoesntHave('sales', fn ($q) => $q->where('status', Sale::STATUS_CONFIRMED))
                    ->whereIn('id', Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->select('client_id'))
                    ->orderBy('id')->get()->pipe(fn ($c) => $c->isEmpty() ? null : $c[mt_rand(0, $c->count() - 1)]);
                if ($client === null) {
                    return;
                }
                $this->planSale($client, $this->pro1, $keys, 0.0, 100, 'pacote_futuro', [], $firstDay->setTime(14, 0));
            });
        }
    }

    private function proFor(): User
    {
        return mt_rand(1, 100) <= 60 ? $this->pro1 : $this->pro2;
    }

    private function bookConsulta(Client $client, User $pro, CarbonImmutable $fromSp, User $by, string $note = 'Primeira consulta'): Appointment
    {
        $slot = $this->findSlot($pro->id, $fromSp, 60);
        $appointment = app(AppointmentService::class)->scheduleEvaluation($client, [
            'scheduled_at' => $slot->utc(),
            'professional_user_id' => $pro->id,
            'duration_minutes' => 60,
            'notes' => $note,
        ])['appointment'];
        $this->planConsulta($appointment, $client, $pro, $slot);

        return $appointment;
    }

    /** O que acontece com uma consulta marcada: desmarca, falta ou acontece (e aí pode virar venda). */
    private function planConsulta(Appointment $appointment, Client $client, User $pro, CarbonImmutable $slot): void
    {
        $this->bump('consultas');
        $month = $slot->format('Y-m');
        $this->pendingConsultas[$month] = ($this->pendingConsultas[$month] ?? 0) + 1;
        $this->planConfirmation($appointment, $slot);
        $service = app(AppointmentService::class);

        $roll = mt_rand(1, 100);
        if ($roll <= 7) {
            $minutes = (int) max(30, $this->spNow()->diffInMinutes($slot) * mt_rand(20, 80) / 100);
            $this->tl->at($this->spNow()->addMinutes($minutes), function () use ($appointment, $client, $pro, $month, $service): void {
                $fresh = $appointment->fresh();
                if ($fresh->status !== Appointment::STATUS_SCHEDULED) {
                    return;
                }
                $service->update($fresh, ['notes' => 'Paciente pediu para desmarcar.']);
                $service->cancel($fresh->fresh());
                $this->pendingConsultas[$month]--;
                $this->bump('consultas_cancelled');
                if (mt_rand(1, 100) <= 50) {
                    $this->bookConsulta($client, $pro, $this->spNow()->addDays(mt_rand(3, 10)), $this->sdr, 'Primeira consulta (remarcada)');
                }
            });

            return;
        }
        if ($roll <= 12) {
            $this->tl->at($slot->addMinutes(20), function () use ($appointment, $month, $service): void {
                $fresh = $appointment->fresh();
                if ($fresh->status !== Appointment::STATUS_SCHEDULED) {
                    return;
                }
                $service->update($fresh, ['notes' => 'Paciente não compareceu.']);
                $service->cancel($fresh->fresh());
                $this->pendingConsultas[$month]--;
                $this->bump('consultas_no_show');
            });

            return;
        }
        $duration = mt_rand(40, 60);
        $this->tl->at($slot, fn () => $service->start($appointment->fresh()));
        $this->tl->at($slot->addMinutes($duration), function () use ($appointment, $client, $pro, $month, $service): void {
            $service->complete($appointment->fresh(), $pro);
            $this->pendingConsultas[$month]--;
            $this->bump('consultas_done');
            $this->afterConsulta($client->fresh(), $pro, $month);
        });
    }

    private function planConfirmation(Appointment $appointment, CarbonImmutable $slot): void
    {
        $day = $slot->subDay();
        while (! $this->isBusinessDay($day)) {
            $day = $day->subDay();
        }
        $at = $day->setTime(mt_rand(10, 16), mt_rand(0, 59));
        if ($at->lte($this->spNow())) {
            return;
        }
        $this->tl->at($at, function () use ($appointment): void {
            $fresh = $appointment->fresh();
            if ($fresh->status !== Appointment::STATUS_SCHEDULED) {
                return;
            }
            $r = mt_rand(1, 100);
            $status = $r <= 85 ? Appointment::CONFIRMATION_CONFIRMED : ($r <= 95 ? Appointment::CONFIRMATION_NOT_CONFIRMED : Appointment::CONFIRMATION_DECLINED);
            app(AppointmentConfirmationService::class)->setStatus($fresh, $status, $this->sdr);
        });
    }

    private function afterConsulta(Client $client, User $pro, string $month): void
    {
        $deck = $this->decks[$month] ?? [];
        $remaining = max(0, $this->pendingConsultas[$month] ?? 0);
        $p = $deck === [] ? 0.0 : min(0.95, max(0.45, count($deck) / ($remaining + 1)));
        if ($deck !== [] && mt_rand(1, 1000) <= (int) ($p * 1000)) {
            [$discount, $purchases, $label] = array_shift($this->decks[$month]);
            if (blank($client->main_pains)) {
                $client->update(['main_pains' => $this->protocolNames($purchases[0])]);
            }
            $this->planSale($client, $pro, $purchases[0], $discount, 55, $label, array_slice($purchases, 1));

            return;
        }
        // Não comprou (ainda): orçamento enviado na maioria dos casos.
        if (mt_rand(1, 100) <= 78) {
            $options = array_merge(DemoCatalog::PROFILES['muito_baixo'], DemoCatalog::PROFILES['baixo']);
            [, $purchases] = $options[mt_rand(0, count($options) - 1)];
            $this->planBudgetOnly($client, $purchases[0]);
        }
    }

    /**
     * Orçamento → (revisão) → aceite → pagamento e confirmação → tratamento e sessões.
     *
     * @param  list<string>  $protocolKeys
     * @param  list<list<string>>  $followUps
     */
    private function planSale(Client $client, User $pro, array $protocolKeys, float $discount, int $sameDayChance, string $label, array $followUps = [], ?CarbonImmutable $firstSession = null): void
    {
        $seller = mt_rand(1, 100) <= 80 ? $this->seller : $this->sdr;
        $draftAt = CarbonImmutable::now()->addMinutes(mt_rand(5, 20));
        $sameDay = mt_rand(1, 100) <= $sameDayChance;
        $ctx = new \stdClass;
        $budgets = app(BudgetService::class);

        $this->tl->at($draftAt, function () use ($ctx, $client, $seller, $protocolKeys, $discount, $budgets): void {
            $ctx->sale = $this->draftSale($client, $seller, $protocolKeys, $discount);
            $ctx->budget = $budgets->createFromSale($ctx->sale, $seller->id, null, CarbonImmutable::now()->addDays(15)->utc());
            $this->bump('budgets');
        });
        $this->tl->at($draftAt->addMinutes(3), function () use ($ctx, $budgets): void {
            $ctx->budget = $budgets->send($ctx->budget->fresh());
        });

        if ($sameDay) {
            $acceptAt = $draftAt->addMinutes(mt_rand(10, 30));
        } else {
            $acceptAt = $this->workTime($draftAt->addDays(mt_rand(1, 5))->setTime(9, 0), 0, 540);
            $reviseAt = $this->workTime($draftAt->addDay()->setTime(9, 0), 0, 480);
            if (mt_rand(1, 100) <= 20 && $reviseAt->lt($acceptAt)) {
                $this->tl->at($reviseAt, function () use ($ctx, $seller, $budgets): void {
                    $sale = $ctx->sale->fresh(['items']);
                    $sale = app(SalePricingService::class)->setEffectiveAmount($sale, $this->safeAmount($sale, floor((float) $sale->effective_amount * 0.95 / 10) * 10));
                    $ctx->sale = $sale;
                    $ctx->budget = $budgets->createFromSale($sale, $seller->id, 'Nova proposta com condição especial.', CarbonImmutable::now()->addDays(15)->utc());
                    $ctx->budget = $budgets->send($ctx->budget);
                    $this->bump('budgets_revised');
                });
            }
        }
        if ($acceptAt->utc()->gt($this->now)) {
            $this->bump('budgets_in_negotiation');

            return; // Ainda em negociação: o orçamento fica enviado.
        }
        $this->tl->at($acceptAt, function () use ($ctx, $budgets): void {
            $budgets->accept($ctx->budget->fresh());
        });
        $this->tl->at($acceptAt->addMinutes(8), function () use ($ctx, $client, $pro, $seller, $protocolKeys, $label, $followUps, $sameDay, $firstSession): void {
            $sale = $this->confirmSale($ctx->sale->fresh(['items']));
            $this->bump('sales');
            $this->bump('sales_'.$label);
            $treatment = app(TreatmentService::class)->openFromSale($sale, $seller, $this->protocolNames($protocolKeys));
            $this->forceFirstSession = $firstSession;
            $this->planSessions($treatment, $sale, $pro, $protocolKeys, $sameDay);
            foreach ($followUps as $i => $next) {
                $this->planRepeatPurchase($client, $pro, $next, $i + 1, $label);
            }
        });
    }

    /** Segunda compra (vip/excelente): consulta de retorno marcada pela recepção e venda no dia. */
    private function planRepeatPurchase(Client $client, User $pro, array $protocolKeys, int $n, string $label): void
    {
        $when = $this->spNow()->addDays(mt_rand(24, 32) * $n)->setTime(9, 0);
        $bookAt = $this->workTime($when->subDays(mt_rand(5, 9)), 0, 480);
        if ($bookAt->lte($this->spNow())) {
            $bookAt = $this->spNow()->addMinutes(30);
        }
        $this->tl->at($bookAt, function () use ($client, $pro, $when, $protocolKeys, $label): void {
            $service = app(AppointmentService::class);
            $slot = $this->findSlot($pro->id, $when, 60);
            $appt = $service->scheduleEvaluation($client, [
                'scheduled_at' => $slot->utc(), 'professional_user_id' => $pro->id,
                'duration_minutes' => 60, 'notes' => 'Retorno — consulta para novo plano',
            ])['appointment'];
            $this->bump('consultas_retorno');
            $this->planConfirmation($appt, $slot);
            $this->tl->at($slot, fn () => $service->start($appt->fresh()));
            $this->tl->at($slot->addMinutes(45), function () use ($appt, $client, $pro, $protocolKeys, $label, $service): void {
                $service->complete($appt->fresh(), $pro);
                $this->planSale($client, $pro, $protocolKeys, 0.03, 90, $label.'_2');
            });
        });
    }

    /** @param list<string> $protocolKeys */
    private function planBudgetOnly(Client $client, array $protocolKeys): void
    {
        $seller = mt_rand(1, 100) <= 75 ? $this->seller : $this->sdr;
        $at = CarbonImmutable::now()->addMinutes(mt_rand(10, 40));
        $ctx = new \stdClass;
        $budgets = app(BudgetService::class);
        $this->tl->at($at, function () use ($ctx, $client, $seller, $protocolKeys, $budgets): void {
            $sale = $this->draftSale($client, $seller, $protocolKeys, -1);
            $ctx->budget = $budgets->createFromSale($sale, $seller->id, null, CarbonImmutable::now()->addDays(15)->utc());
            $ctx->budget = $budgets->send($ctx->budget);
            $this->bump('budgets');
            $this->bump('budgets_not_bought');
        });
        $r = mt_rand(1, 100);
        if ($r <= 35) {
            $this->tl->at($this->workTime($at->addDays(mt_rand(2, 7))->setTime(9, 0), 0, 480), function () use ($ctx, $budgets): void {
                if ($ctx->budget->fresh()->status === Budget::STATUS_SENT) {
                    $budgets->reject($ctx->budget->fresh());
                    $this->bump('budgets_rejected');
                }
            });
        } elseif ($r <= 62) {
            $this->tl->at($at->addDays(16)->setTime(8, 0), function () use ($ctx, $budgets): void {
                if ($ctx->budget->fresh()->status === Budget::STATUS_SENT) {
                    $budgets->expire($ctx->budget->fresh());
                    $this->bump('budgets_expired');
                }
            });
        }
    }

    /** @param list<string> $protocolKeys */
    private function draftSale(Client $client, User $seller, array $protocolKeys, float $discount): Sale
    {
        $sale = Sale::query()->create([
            'clinic_id' => $this->cid,
            'client_id' => $client->id,
            'sold_by_user_id' => $seller->id,
            'sold_at' => CarbonImmutable::now()->utc(),
            'status' => Sale::STATUS_DRAFT,
        ]);
        $pricing = app(SalePricingService::class);
        foreach ($protocolKeys as $key) {
            $sale = $pricing->applyProtocol($sale, $this->protocols[$key]);
        }
        $sale = $sale->fresh(['items']);
        if ($discount < 0) {
            $discount = (float) $this->pick(['0' => 50, '0.03' => 20, '0.05' => 20, '0.08' => 10]);
        }
        if ($discount > 0) {
            $target = floor(((float) $sale->expected_amount * (1 - $discount)) / 10) * 10;
            $sale = $pricing->setEffectiveAmount($sale, $this->safeAmount($sale, $target));
        }

        return $sale->fresh(['items']);
    }

    private function safeAmount(Sale $sale, float $target): string
    {
        $sale->loadMissing('items');
        $min = (float) $sale->minAmount();

        return number_format(max($target, ceil($min / 10) * 10, $min), 2, '.', '');
    }

    private function confirmSale(Sale $sale): Sale
    {
        $pricing = app(SalePricingService::class);
        $r = mt_rand(1, 100);
        if ($r <= 33 && ! $sale->effective_amount_is_manual) {
            // PIX à vista com 5% de desconto.
            $sale = $pricing->setEffectiveAmount($sale, $this->safeAmount($sale, floor((float) $sale->effective_amount * 0.95 / 10) * 10));
        }
        $effective = round((float) $sale->effective_amount, 2);
        $now = CarbonImmutable::now()->utc();
        $card = fn (string $method, float $amount, int $n) => [
            'payment_method_id' => $this->methods[$method]->id,
            'amount' => number_format($amount, 2, '.', ''),
            'card_operator_id' => $this->stoneId,
            'card_brand_id' => mt_rand(1, 100) <= 55 ? $this->cardBrands['visa'] : $this->cardBrands['mastercard'],
            'installments' => $n,
            'paid_at' => $now,
        ];
        $simple = fn (string $method, float $amount) => [
            'payment_method_id' => $this->methods[$method]->id,
            'amount' => number_format($amount, 2, '.', ''),
            'paid_at' => $now,
        ];
        $n = (int) $this->pick(['1' => 15, '2' => 10, '3' => 20, '4' => 10, '5' => 10, '6' => 15, '10' => 20]);
        if ($r <= 33) {
            $payments = [$simple('pix', $effective)];
        } elseif ($r <= 73) {
            $payments = [$card('cartao_credito', $effective, $n)];
        } elseif ($r <= 78) {
            $payments = [$card('cartao_debito', $effective, 1)];
        } elseif ($r <= 85) {
            $payments = [$simple('boleto', $effective)];
        } elseif ($r <= 90) {
            $payments = [$simple('dinheiro', $effective)];
        } else {
            $entry = round($effective * 0.3, 2);
            $payments = [$simple('pix', $entry), $card('cartao_credito', round($effective - $entry, 2), mt_rand(3, 6))];
        }
        $sale = $pricing->syncPayments($sale, $payments);
        $sale->update(['sold_at' => $now]);

        return $pricing->confirm($sale->fresh(['items', 'payments']));
    }

    /**
     * Sessões do tratamento conforme o plano de cada protocolo (todas marcadas na hora da venda).
     *
     * @param  list<string>  $protocolKeys
     */
    private function planSessions(Treatment $treatment, Sale $sale, User $pro, array $protocolKeys, bool $sameDay): void
    {
        $sessions = [];
        foreach ($protocolKeys as $key) {
            [$label, $items, , $plan] = DemoCatalog::PROTOCOLS[$key];
            foreach ($plan as [$offset, $duration, $fractions]) {
                $sessions[$offset]['duration'] = min(150, ($sessions[$offset]['duration'] ?? 0) + $duration);
                foreach ($fractions as $product => $fraction) {
                    $sessions[$offset]['items'][$product] = ($sessions[$offset]['items'][$product] ?? 0) + $items[$product] * $fraction;
                }
                $sessions[$offset]['labels'][] = $label;
            }
        }
        ksort($sessions);
        $total = count($sessions);
        $quick = $sameDay && mt_rand(1, 100) <= 50 && ($sessions[array_key_first($sessions)]['duration'] ?? 999) <= 60;
        $firstFrom = $quick ? $this->spNow()->addMinutes(10) : $this->spNow()->addDays(mt_rand(2, 9))->setTime(9, 0);
        if ($this->forceFirstSession !== null) {
            $firstFrom = $this->forceFirstSession;
            $this->forceFirstSession = null;
        }
        $first = null;
        $i = 0;
        $lastOffset = array_key_last($sessions);
        foreach ($sessions as $offset => $session) {
            $i++;
            $from = $first === null ? $firstFrom : $first->addDays($offset)->setTime(9, 0);
            $duration = (int) $session['duration'];
            $slot = $this->findSlot($pro->id, $from, $duration);
            $first ??= $slot;
            $appt = app(AppointmentService::class)->schedule($treatment->fresh(), [
                'scheduled_at' => $slot->utc(),
                'professional_user_id' => $pro->id,
                'duration_minutes' => $duration,
                'notes' => sprintf('Sessão %d/%d — %s', $i, $total, implode(' + ', array_unique($session['labels']))),
            ])['appointment'];
            $this->bump('sessions');
            foreach ($session['items'] as $product => $qty) {
                $this->planned[$this->products[$product]->id][] = [$slot->utc(), (float) $qty];
            }
            $this->planConfirmation($appt, $slot);
            $isLast = $offset === $lastOffset;
            $items = $session['items'];
            $this->tl->at($slot, fn () => app(AppointmentService::class)->start($appt->fresh()));
            $this->tl->at($slot->addMinutes(max(20, $duration - mt_rand(0, 10))), function () use ($appt, $sale, $items, $isLast, $treatment, $pro, $protocolKeys): void {
                $this->executeSession($appt->fresh(['treatment']), $sale, $items, $isLast, $protocolKeys, $pro);
                $this->bump('sessions_done');
                if ($isLast) {
                    app(TreatmentService::class)->complete($treatment->fresh());
                    $this->bump('treatments_completed');
                }
            });
        }
    }

    /**
     * @param  array<string, float>  $items
     * @param  list<string>  $protocolKeys
     */
    private function executeSession(Appointment $appt, Sale $sale, array $items, bool $isLast, array $protocolKeys, User $pro): void
    {
        $saleItems = $sale->items()->get()->keyBy('product_id');
        $fulfillment = collect(app(TreatmentService::class)->fulfillment($appt->treatment))->keyBy('sale_item_id');
        $lines = [];
        foreach ($items as $productKey => $qty) {
            $product = $this->products[$productKey];
            $saleItem = $saleItems->get($product->id);
            if ($saleItem === null) {
                continue;
            }
            $remaining = (float) ($fulfillment->get($saleItem->id)['remaining_quantity'] ?? 0);
            $use = $isLast ? $remaining : min($remaining, round($qty, 4));
            if ($use <= 0) {
                continue;
            }
            $this->ensureStock($productKey, $use);
            $lines[] = ['source' => AppointmentConsumption::SOURCE_SUGGESTED, 'sale_item_id' => $saleItem->id, 'product_id' => $product->id, 'quantity' => $use];
        }
        $needsAnesthesia = (bool) array_intersect($protocolKeys, ['LAB', 'OLH', 'MAND', 'SULCO', 'HFF', 'PDO', 'RAD', 'SKIN']);
        $supplies = ['LUVA' => 2, 'SERINGA' => mt_rand(1, 2), 'AGULHA' => mt_rand(2, 6)];
        if ($needsAnesthesia) {
            $supplies['LIDO'] = mt_rand(1, 2);
        }
        foreach ($supplies as $key => $qty) {
            $this->ensureStock($key, $qty);
            $lines[] = ['source' => AppointmentConsumption::SOURCE_EXTRA, 'product_id' => $this->products[$key]->id, 'quantity' => $qty, 'is_complimentary' => true];
        }
        $service = app(AppointmentService::class);
        $service->syncConsumptions($appt, $lines);
        $service->complete($appt->fresh(), $pro);
    }
}
