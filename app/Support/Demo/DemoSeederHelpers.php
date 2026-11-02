<?php

namespace App\Support\Demo;

use App\Models\Client;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Relógio, expediente, agenda e sorteios do seed de demonstração.
 */
trait DemoSeederHelpers
{
    private function clock(CarbonImmutable $at): void
    {
        Carbon::setTestNow($at->utc());
    }

    private function spNow(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone(self::TZ);
    }

    public function isBusinessDay(CarbonImmutable $day): bool
    {
        $d = $day->setTimezone(self::TZ);

        return ! $d->isSunday() && ! in_array($d->toDateString(), self::HOLIDAYS, true);
    }

    /** @return array{0: int, 1: int} minutos de abertura e fechamento */
    private function hours(CarbonImmutable $day): array
    {
        return $day->isSaturday() ? [9 * 60, 13 * 60] : [9 * 60, 19 * 60];
    }

    /** Próximo instante de expediente depois de $t + atraso aleatório (em minutos). */
    private function workTime(CarbonImmutable $t, int $minDelay, int $maxDelay): CarbonImmutable
    {
        $at = $t->setTimezone(self::TZ)->addMinutes(mt_rand($minDelay, max($minDelay, $maxDelay)));
        for ($i = 0; $i < 20; $i++) {
            [$open, $close] = $this->hours($at);
            $minute = $at->hour * 60 + $at->minute;
            if ($this->isBusinessDay($at) && $minute >= $open && $minute < $close) {
                return $at;
            }
            if ($this->isBusinessDay($at) && $minute < $open) {
                return $at->startOfDay()->addMinutes($open + mt_rand(0, 90));
            }
            $at = $at->addDay()->startOfDay();
            if ($this->isBusinessDay($at)) {
                [$open] = $this->hours($at);

                return $at->addMinutes($open + mt_rand(0, 120));
            }
        }

        return $at;
    }

    /** Primeiro horário livre do profissional a partir de $from (de 30 em 30 min, sem almoço). */
    private function findSlot(int $proId, CarbonImmutable $from, int $duration): CarbonImmutable
    {
        $from = $from->setTimezone(self::TZ);
        for ($d = 0; $d < 60; $d++) {
            $day = $from->startOfDay()->addDays($d);
            if (! $this->isBusinessDay($day)) {
                continue;
            }
            [$open, $close] = $this->hours($day);
            $candidates = [];
            for ($m = $open; $m + $duration <= $close; $m += 30) {
                if (! $day->isSaturday() && $m < 13 * 60 && $m + $duration > 12 * 60) {
                    continue;
                }
                if ($d === 0 && $m < $from->hour * 60 + $from->minute) {
                    continue;
                }
                $candidates[] = $m;
            }
            $this->shuffle($candidates);
            $key = $day->toDateString();
            foreach ($candidates as $m) {
                $clash = false;
                foreach ($this->busy[$proId][$key] ?? [] as [$s, $e]) {
                    if ($s < $m + $duration && $e > $m) {
                        $clash = true;
                        break;
                    }
                }
                if (! $clash) {
                    $this->busy[$proId][$key][] = [$m, $m + $duration];

                    return $day->addMinutes($m);
                }
            }
        }
        throw new RuntimeException('Sem horário livre na agenda de demonstração.');
    }

    private function name(): string
    {
        for ($i = 0; $i < 500; $i++) {
            $female = mt_rand(1, 100) <= 85;
            $first = $female
                ? DemoCatalog::FEMALE[mt_rand(0, count(DemoCatalog::FEMALE) - 1)]
                : DemoCatalog::MALE[mt_rand(0, count(DemoCatalog::MALE) - 1)];
            $name = $first.' '.DemoCatalog::SURNAMES[mt_rand(0, count(DemoCatalog::SURNAMES) - 1)];
            if (! isset($this->usedNames[$name])) {
                $this->usedNames[$name] = true;

                return $name;
            }
        }
        throw new RuntimeException('Sem nomes livres.');
    }

    /** Telefones fictícios em sequência: (11) 90000-0001, (11) 90000-0002… (só dígitos, como o app grava). */
    private function phone(): string
    {
        for ($i = 0; $i < 2000; $i++) {
            $this->phoneSeq++;
            $phone = sprintf('1190000%04d', $this->phoneSeq);
            $taken = Lead::query()->where('phone_digits', $phone)->exists()
                || Client::query()->whereIn('whatsapp', [$phone, '55'.$phone])->exists();
            if (! $taken) {
                return $phone;
            }
        }
        throw new RuntimeException('Sem telefones fictícios livres.');
    }

    /**
     * @param  array<string|int, int>  $weights
     */
    private function pick(array $weights): string
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    private function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    /** @param list<string> $keys */
    private function protocolNames(array $keys): string
    {
        return implode(' + ', array_map(fn ($k) => DemoCatalog::PROTOCOLS[$k][0], $keys));
    }

    private function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }
}
