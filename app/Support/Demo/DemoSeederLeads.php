<?php

namespace App\Support\Demo;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadDailyCheckin;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadService;
use App\Support\EnsureDefaultLeadStatuses;
use Carbon\CarbonImmutable;

/**
 * Leads: chegada, abordagem, resposta, conversão (consulta), perda e cancelamento; check-ins do dia.
 */
trait DemoSeederLeads
{
    private function scheduleLeads(): void
    {
        $day = $this->from;
        $nowSp = $this->now->setTimezone(self::TZ);
        $monthIndex = 0;
        $currentMonth = $day->format('Y-m');
        while ($day->lte($this->to) && $day->startOfDay()->lte($nowSp)) {
            if ($day->format('Y-m') !== $currentMonth) {
                $currentMonth = $day->format('Y-m');
                $monthIndex++;
            }
            $rate = (DemoCatalog::LEADS_PER_DAY[$monthIndex] ?? 2.0) * $this->scale;
            $factor = match ((int) $day->dayOfWeek) {
                0 => 0.55, 6 => 0.7, default => 1.12,
            };
            $n = (int) floor($rate * $factor + mt_rand(0, 999) / 1000);
            for ($k = 0; $k < $n; $k++) {
                $hour = (int) $this->pick(['8' => 2, '9' => 4, '10' => 5, '11' => 5, '12' => 4, '13' => 4, '14' => 4, '15' => 4, '16' => 4, '17' => 4, '18' => 4, '19' => 5, '20' => 5, '21' => 3]);
                $at = $day->setTime($hour, mt_rand(0, 59));
                if ($at->utc()->gt($this->now)) {
                    continue;
                }
                $this->tl->at($at, fn () => $this->leadArrives());
            }
            $day = $day->addDay();
        }
    }

    private function leadArrives(): void
    {
        $origin = $this->pick(DemoCatalog::ORIGINS);
        $social = in_array($origin, ['Instagram', 'Facebook', 'TikTok'], true);
        $noPhone = $social && mt_rand(1, 100) <= 22;
        $handler = $origin === 'Indicação' && mt_rand(1, 100) <= 50
            ? $this->seller
            : (mt_rand(1, 100) <= 80 ? $this->sdr : $this->seller);
        $leads = app(LeadService::class);
        $lead = $leads->create([
            'name' => $this->name(),
            'phone' => $noPhone ? null : $this->phone(),
            'client_origin_id' => $this->origins[$origin],
            'notes' => DemoCatalog::INTERESTS[mt_rand(0, count(DemoCatalog::INTERESTS) - 1)],
        ], $handler);
        $this->bump('leads');
        if ($noPhone) {
            $this->bump('leads_without_phone');
        }

        if (mt_rand(1, 100) > 90) {
            if (mt_rand(1, 100) <= 25) {
                $this->tl->at($this->workTime(CarbonImmutable::now()->addDays(mt_rand(1, 4)), 0, 300), fn () => $this->cancelLead($lead, $handler));
            }

            return; // Ninguém abordou (lead parado).
        }
        $this->tl->at($this->workTime(CarbonImmutable::now(), 8, 240), function () use ($lead, $handler, $noPhone, $leads): void {
            if ($lead->fresh()->isCancelled()) {
                return;
            }
            $leads->markMilestone($lead->fresh(), 'approached', $handler);
            $lead->activities()->create([
                'user_id' => $handler->id,
                'type' => LeadActivity::TYPE_WHATSAPP,
                'note' => $noPhone ? 'Primeira mensagem pelo direct.' : 'Primeira mensagem pelo WhatsApp.',
            ]);
            $leads->changeStatus($lead->fresh(), $this->status('Em contato'), null, $handler);
            if (mt_rand(1, 100) > 64) {
                $this->planNoReply($lead, $handler);

                return;
            }
            $this->tl->at($this->workTime(CarbonImmutable::now(), 15, 1800), fn () => $this->leadReplied($lead, $handler, $noPhone));
        });
    }

    private function planNoReply(Lead $lead, User $handler): void
    {
        $this->tl->at($this->workTime(CarbonImmutable::now()->addDays(mt_rand(1, 3)), 0, 240), function () use ($lead, $handler): void {
            if ($lead->fresh()->isCancelled()) {
                return;
            }
            $lead->activities()->create(['user_id' => $handler->id, 'type' => LeadActivity::TYPE_WHATSAPP, 'note' => 'Follow-up: segunda tentativa de contato.']);
            $r = mt_rand(1, 100);
            $later = $this->workTime(CarbonImmutable::now()->addDays(mt_rand(3, 8)), 0, 300);
            if ($r <= 35) {
                $this->tl->at($later, fn () => $this->loseLead($lead, $handler, 'Sem resposta após 3 tentativas.'));
            } elseif ($r <= 55) {
                $this->tl->at($later, fn () => $this->cancelLead($lead, $handler));
            }
        });
    }

    private function leadReplied(Lead $lead, User $handler, bool $noPhone): void
    {
        if ($lead->fresh()->isCancelled()) {
            return;
        }
        app(LeadService::class)->markMilestone($lead->fresh(), 'replied', $handler);
        if (mt_rand(1, 100) <= 70) {
            $lead->activities()->create([
                'user_id' => $handler->id,
                'type' => LeadActivity::TYPE_COMMENT,
                'note' => DemoCatalog::COMMENTS[mt_rand(0, count(DemoCatalog::COMMENTS) - 1)],
            ]);
        }
        if (! $noPhone && mt_rand(1, 100) <= 70) {
            $this->tl->at($this->workTime(CarbonImmutable::now(), 5, 900), fn () => $this->convertLead($lead, $handler));

            return;
        }
        $r = mt_rand(1, 100);
        $later = $this->workTime(CarbonImmutable::now()->addDays(mt_rand(2, 7)), 0, 300);
        if ($r <= 50) {
            $this->tl->at($later, fn () => $this->loseLead($lead, $handler, DemoCatalog::LOST_REASONS[mt_rand(0, count(DemoCatalog::LOST_REASONS) - 1)]));
        } elseif ($r <= 62) {
            $this->tl->at($later, fn () => $this->cancelLead($lead, $handler));
        }
    }

    private function convertLead(Lead $lead, User $handler): void
    {
        $lead = $lead->fresh();
        if ($lead->isCancelled() || $lead->isConverted()) {
            return;
        }
        $pro = $this->proFor();
        $slot = $this->findSlot($pro->id, $this->spNow()->addDays(mt_rand(1, 8))->setTime(9, 0), 60);
        $result = app(LeadService::class)->convert($lead, [
            'scheduled_at' => $slot->utc(),
            'professional_user_id' => $pro->id,
            'duration_minutes' => 60,
            'notes' => 'Primeira consulta',
            'create_new_client' => true,
        ], $handler);
        $this->bump('leads_converted');
        $client = $result['client'];
        $this->demoClients[] = (int) $client->id;
        $origin = array_search((int) $lead->client_origin_id, $this->origins, true);
        $campaign = match (true) {
            $origin === 'Instagram' && $this->spNow()->month === 10 && mt_rand(1, 100) <= 35 => $this->campaigns['rosa'],
            $origin === 'Instagram' && mt_rand(1, 100) <= 65 => $this->campaigns['meta'],
            $origin === 'Google' && mt_rand(1, 100) <= 70 => $this->campaigns['google'],
            default => null,
        };
        $client->update(['main_pains' => $lead->notes, 'campaign_id' => $campaign]);
        $this->planConsulta($result['appointment'], $client, $pro, $slot);
    }

    private function loseLead(Lead $lead, User $handler, string $reason): void
    {
        $lead = $lead->fresh();
        if ($lead->isCancelled() || $lead->isConverted()) {
            return;
        }
        app(LeadService::class)->changeStatus($lead, $this->status('Perdido'), $reason, $handler);
        $this->bump('leads_lost');
    }

    private function cancelLead(Lead $lead, User $handler): void
    {
        $lead = $lead->fresh();
        if ($lead->isCancelled() || $lead->isConverted()) {
            return;
        }
        app(LeadService::class)->cancel($lead, DemoCatalog::CANCEL_REASONS[mt_rand(0, count(DemoCatalog::CANCEL_REASONS) - 1)], $handler);
        $this->bump('leads_cancelled');
    }

    private function status(string $name): int
    {
        $status = LeadStatus::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        return (int) ($status?->id ?? EnsureDefaultLeadStatuses::defaultFor($this->cid)->id);
    }

    private function scheduleCheckins(): void
    {
        $day = $this->from;
        while ($day->lte($this->to)) {
            if ($this->isBusinessDay($day) && ! $day->isSaturday()) {
                foreach ([$this->sdr, $this->seller] as $user) {
                    if (mt_rand(1, 100) > 72) {
                        continue;
                    }
                    $at = $day->setTime(18, mt_rand(0, 40));
                    $this->tl->at($at, function () use ($user, $day): void {
                        LeadDailyCheckin::query()->create([
                            'clinic_id' => $this->cid,
                            'user_id' => $user->id,
                            'day' => $day->toDateString(),
                            'worked' => DemoCatalog::CHECKIN_WORKED[mt_rand(0, count(DemoCatalog::CHECKIN_WORKED) - 1)],
                            'change' => DemoCatalog::CHECKIN_CHANGE[mt_rand(0, count(DemoCatalog::CHECKIN_CHANGE) - 1)],
                        ]);
                        $this->bump('lead_daily_checkins');
                    });
                }
            }
            $day = $day->addDay();
        }
    }
}
