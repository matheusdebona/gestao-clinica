<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PatientTier;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faixa do paciente pelo total comprado nos últimos 12 meses.
 *
 * Conta: vendas confirmadas (status `confirmed`), pelo valor efetivo (`effective_amount`), com
 * `sold_at` dentro dos últimos 12 meses até agora. Rascunho e venda cancelada não contam.
 * Orçamento não entra: o orçamento aceito vira a venda, então contar os dois duplicaria o valor.
 * É o mesmo critério de receita do DRE e das métricas comerciais.
 *
 * Calculado na consulta (subconsulta agregada com índice), sem coluna em cache: a janela móvel
 * fica sempre certa (nada para "envelhecer" com job diário) e qualquer venda confirmada,
 * cancelada ou editada já muda a faixa na hora.
 */
class PatientTierService
{
    public const WINDOW_MONTHS = 12;

    public const FILTER_NONE = 'none';

    public function windowStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMonthsNoOverflow(self::WINDOW_MONTHS);
    }

    /** @return Collection<int, PatientTier> */
    public function tiers(): Collection
    {
        return PatientTier::query()->orderBy('min_amount')->get();
    }

    /** Soma por paciente (client_id, total) das vendas que contam, para join. */
    public function spendSubquery(int $clinicId): QueryBuilder
    {
        return DB::table('sales')
            ->select('client_id')
            ->selectRaw('SUM(effective_amount) as total')
            ->where('clinic_id', $clinicId)
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->where('sold_at', '>=', $this->windowStart())
            ->where('sold_at', '<=', CarbonImmutable::now())
            ->groupBy('client_id');
    }

    /** Adiciona `purchases_12m` ao select da lista de pacientes. */
    public function withSpend(Builder $query, int $clinicId): Builder
    {
        return $query
            ->leftJoinSub($this->spendSubquery($clinicId), 'tier_spend', 'tier_spend.client_id', '=', 'clients.id')
            ->select('clients.*')
            ->selectRaw('COALESCE(tier_spend.total, 0) as purchases_12m');
    }

    public function spendFor(Client $client): string
    {
        $total = DB::query()
            ->fromSub($this->spendSubquery((int) $client->clinic_id)->where('client_id', $client->id), 'tier_spend')
            ->value('total');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    /**
     * Filtra por faixas: ids de faixa e/ou "none" (sem faixa). Lista vazia = todos.
     *
     * @param  list<int|string>  $tokens
     * @param  Collection<int, PatientTier>  $tiers
     */
    public function applyFilter(Builder $query, array $tokens, Collection $tiers): Builder
    {
        $wantsNone = in_array(self::FILTER_NONE, $tokens, true);
        $ids = array_values(array_filter($tokens, fn ($t) => is_int($t)));
        if (! $wantsNone && $ids === []) {
            return $query;
        }

        $cents = 'ROUND(COALESCE(tier_spend.total, 0) * 100)';
        $ranges = $this->ranges($tiers);

        return $query->where(function (Builder $where) use ($wantsNone, $ids, $ranges, $cents, $tiers): void {
            // Sempre começa com uma condição falsa para que "nenhuma faixa válida" não traga todos.
            $where->whereRaw('1 = 0');
            foreach ($ranges as $range) {
                if (! in_array($range['tier']->id, $ids, true)) {
                    continue;
                }
                $where->orWhere(function (Builder $q) use ($range, $cents): void {
                    $q->whereRaw("$cents >= ?", [$range['min_cents']]);
                    if ($range['next_cents'] !== null) {
                        $q->whereRaw("$cents < ?", [$range['next_cents']]);
                    }
                });
            }
            if ($wantsNone) {
                if ($tiers->isEmpty()) {
                    $where->orWhereRaw('1 = 1');
                } else {
                    $where->orWhereRaw("$cents < ?", [$ranges[0]['min_cents']]);
                }
            }
        });
    }

    /**
     * @param  Collection<int, PatientTier>  $tiers  ordenadas por min_amount
     * @return list<array{tier: PatientTier, min_cents: int, next_cents: int|null}>
     */
    public function ranges(Collection $tiers): array
    {
        $sorted = $tiers->sortBy(fn (PatientTier $t) => $t->minCents())->values();
        $out = [];
        foreach ($sorted as $i => $tier) {
            $next = $sorted->get($i + 1);
            $out[] = ['tier' => $tier, 'min_cents' => $tier->minCents(), 'next_cents' => $next?->minCents()];
        }

        return $out;
    }

    /** @param  Collection<int, PatientTier>  $tiers */
    public function tierFor(string|float|int|null $amount, Collection $tiers): ?PatientTier
    {
        $cents = (int) round(((float) ($amount ?? 0)) * 100);
        $match = null;
        foreach ($this->ranges($tiers) as $range) {
            if ($cents >= $range['min_cents']) {
                $match = $range['tier'];
            }
        }

        return $match;
    }

    /** "R$ 0,00 a R$ 5.000,00", "Acima de R$ 20.000,00". */
    public function rangeLabel(int $minCents, ?int $nextCents): string
    {
        if ($nextCents === null) {
            return $minCents === 0 ? 'Qualquer valor' : 'Acima de '.self::brl($minCents - 1);
        }

        return self::brl($minCents).' a '.self::brl($nextCents - 1);
    }

    public static function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
