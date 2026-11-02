<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Carbon;
use SplHeap;

/**
 * Fila de eventos em ordem cronológica. Cada evento roda com o relógio do app (now())
 * parado no instante dele, para que os serviços reais gravem datas coerentes.
 */
final class DemoTimeline
{
    private SplHeap $heap;

    private int $seq = 0;

    public int $executed = 0;

    public int $skippedFuture = 0;

    public function __construct()
    {
        $this->heap = new class extends SplHeap
        {
            /** @param array{0: float, 1: int, 2: Closure} $a */
            protected function compare(mixed $a, mixed $b): int
            {
                // SplHeap é max-heap: o menor instante precisa ser o "maior".
                return [$b[0], $b[1]] <=> [$a[0], $a[1]];
            }
        };
    }

    public function at(CarbonImmutable $at, Closure $fn): void
    {
        $this->heap->insert([(float) $at->utc()->format('U.u'), $this->seq++, $fn, $at->utc()]);
    }

    public function run(CarbonImmutable $until, Closure $beforeEach): void
    {
        while (! $this->heap->isEmpty()) {
            [, , $fn, $at] = $this->heap->extract();
            if ($at->gt($until)) {
                $this->skippedFuture++;

                continue;
            }
            Carbon::setTestNow($at);
            $beforeEach();
            $fn($at);
            $this->executed++;
        }
        Carbon::setTestNow();
    }
}
