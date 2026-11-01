<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\GoalRemunerationService;
use App\Support\ClinicMonth;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mês de referência = mês civil de São Paulo (dia 1 ao último dia); cada evento conta no mês em
 * que aconteceu. E: colaborador desativado não ganha metas/faixas/faturamento; faixa paga não
 * gera diferença.
 */
class ClinicMonthTest extends TestCase
{
    use RefreshDatabase;

    /** 31/10 23:30 em São Paulo (01/11 02:30 UTC). */
    private const OCT_31_2330_SP = '2026-11-01 02:30:00';

    protected Clinic $clinic;

    protected User $admin;

    protected User $seller;

    protected User $receptionist;

    private ?int $originId = null;

    private ?int $statusId = null;

    private int $phone = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bruno Vendas']);
        $this->seller->assignRole('seller');
        $this->receptionist = User::factory()->forClinic($this->clinic)->create(['name' => 'Rita Recepção']);
        $this->receptionist->assignRole('receptionist');
        CurrentClinic::setId($this->clinic->id);
        $this->at('2026-10-15 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow($utc);
        CarbonImmutable::setTestNow($utc);
    }

    private function sale(string $amount, string $soldAt, array $attributes = []): Sale
    {
        return Sale::factory()->forClinic($this->clinic)->create([
            'status' => Sale::STATUS_CONFIRMED, 'sold_at' => $soldAt, 'effective_amount' => $amount,
            'sold_by_user_id' => $this->seller->id, ...$attributes,
        ]);
    }

    private function entries(string $role, ?int $userId = null): Collection
    {
        return CommissionEntry::query()->where('role', $role)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')->get();
    }

    private function rebuild(string $month): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => $month])->assertOk();
    }

    private function convertAsSeller(): Lead
    {
        $this->originId ??= ClientOrigin::factory()->create(['clinic_id' => $this->clinic->id])->id;
        $this->statusId ??= LeadStatus::factory()->create(['clinic_id' => $this->clinic->id])->id;
        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => $this->originId,
            'lead_status_id' => $this->statusId,
            'phone' => '119'.str_pad((string) (++$this->phone), 8, '0', STR_PAD_LEFT),
        ]);
        Sanctum::actingAs($this->seller);
        $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'scheduled_at' => now()->addDays(2 + $this->phone)->setTime(13, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
            'create_new_client' => true,
        ])->assertCreated();

        return $lead->fresh();
    }

    private function revenueTiers(array $tiers, ?User $user = null): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/finance/revenue-remunerations/'.($user ?? $this->receptionist)->id, ['tiers' => $tiers])->assertOk();
    }

    private function appointmentConfig(array $tiers, string $per = '0', string $bonus = '0'): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/finance/goal-remunerations/appointments', [
            'per_occurrence_amount' => $per, 'target_bonus_amount' => $bonus, 'tiers' => $tiers,
        ])->assertOk();
    }

    private function goalEntries(?int $userId = null): Collection
    {
        return CommissionEntry::query()->where('role', 'goal')->where('user_id', $userId ?? $this->seller->id)
            ->orderBy('id')->get();
    }

    private function markPaid(int $id): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => [$id], 'paid' => true])->assertOk();
    }

    // ---------------------------------------------------------------- mês de São Paulo

    public function test_clinic_month_helpers_use_the_sao_paulo_calendar(): void
    {
        $this->assertSame('2026-10', ClinicMonth::of(self::OCT_31_2330_SP));
        $this->assertSame('2026-10-01', ClinicMonth::competence(self::OCT_31_2330_SP));
        $this->assertSame('2026-10-31', ClinicMonth::date(self::OCT_31_2330_SP));
        $this->assertSame('2026-11', ClinicMonth::of('2026-11-01 03:00:00')); // 01/11 00:00 SP
        [$start, $end] = ClinicMonth::bounds('2026-10');
        $this->assertSame('2026-10-01 03:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 02:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_sale_commission_competence_rebuild_and_display_date_follow_the_sao_paulo_month(): void
    {
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'seller', 'calc_type' => 'percent', 'value' => 10]);
        $late = $this->sale('1000.00', self::OCT_31_2330_SP);           // 31/10 23:30 SP → outubro
        $early = $this->sale('500.00', '2026-10-01 02:30:00');           // 30/09 23:30 SP → setembro
        $november = $this->sale('300.00', '2026-11-01 03:00:00');        // 01/11 00:00 SP → novembro
        foreach ([$late, $early, $november] as $sale) {
            app(CommissionService::class)->syncSale($sale);
        }
        $competence = fn () => $this->entries('seller')->mapWithKeys(fn ($e) => [$e->sale_id => $e->competence_month->toDateString()])->all();
        $this->assertSame([$late->id => '2026-10-01', $early->id => '2026-09-01', $november->id => '2026-11-01'], $competence());

        // "Recalcular mês" seleciona as vendas pelo mês de São Paulo: outubro refaz só a de 31/10 23:30.
        CommissionEntry::query()->delete();
        $this->rebuild('2026-10');
        $this->assertSame([$late->id => '2026-10-01'], $competence());
        $this->rebuild('2026-11');
        $this->assertSame([$late->id => '2026-10-01', $november->id => '2026-11-01'], $competence());

        // Comissões: data do evento no dia de São Paulo.
        $rows = collect($this->getJson('/api/v1/finance/commissions?month=2026-10')->assertOk()->json('data'));
        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-31', $rows->first()['occurred_on']);
    }

    public function test_month_query_parameter_is_the_calendar_month_even_on_the_31st(): void
    {
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'seller', 'calc_type' => 'percent', 'value' => 10]);
        app(CommissionService::class)->syncSale($this->sale('1000.00', '2026-11-10 15:00:00'));
        $this->at('2026-10-31 15:00:00'); // "2026-11" não pode virar 31/11 → dezembro
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/commissions?month=2026-11')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_pending_competence_migration_fixes_utc_months_and_keeps_paid(): void
    {
        $sale = $this->sale('1000.00', self::OCT_31_2330_SP);
        $other = $this->sale('1000.00', self::OCT_31_2330_SP);
        $base = ['clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'role' => 'seller', 'base_amount' => '1000', 'amount' => '100', 'competence_month' => '2026-11-01'];
        $pending = CommissionEntry::query()->create($base + ['sale_id' => $sale->id]);
        $paid = CommissionEntry::query()->create($base + ['sale_id' => $other->id, 'status' => 'paid', 'paid_at' => now()]);

        (require base_path('database/migrations/2026_11_01_130000_sao_paulo_commission_competence.php'))->up();

        $this->assertSame('2026-10-01', $pending->fresh()->competence_month->toDateString());
        $this->assertSame('2026-11-01', $paid->fresh()->competence_month->toDateString());
    }

    public function test_dre_and_contribution_margin_count_sales_in_the_sao_paulo_month_and_day(): void
    {
        $this->sale('1000.00', self::OCT_31_2330_SP);
        $this->sale('200.00', '2026-10-01 02:30:00'); // setembro em SP
        Sanctum::actingAs($this->admin);
        $this->assertSame('1000.00', $this->getJson('/api/v1/finance/dre?month=2026-10')->assertOk()->json('data.current.lines.net_revenue'));
        $this->assertSame('200.00', $this->getJson('/api/v1/finance/dre?month=2026-09')->assertOk()->json('data.current.lines.net_revenue'));
        $cm = $this->getJson('/api/v1/metrics/contribution-margin?from=2026-10-31&to=2026-10-31')->assertOk();
        $this->assertSame('1000.00', $cm->json('data.totals.revenue'));
        $this->assertSame('2026-10-31', $cm->json('data.from'));
    }

    public function test_appointment_counts_in_its_month_and_the_later_sale_in_the_sale_month(): void
    {
        // Faixa de agendamentos: 1 agendamento → R$ 50 + 10% das vendas dos pacientes no mesmo mês.
        $this->appointmentConfig([['threshold' => 1, 'fixed_amount' => '50', 'percent' => '10']]);
        $this->revenueTiers([['threshold' => '1000', 'fixed_amount' => '10']]);

        // Agendou em 31/10 23:30 (SP) → o agendamento conta em outubro.
        $this->at(self::OCT_31_2330_SP);
        $lead = $this->convertAsSeller();
        $this->assertSame('2026-10', ClinicMonth::of($lead->booked_at));

        // O paciente só compra em novembro → a venda conta em novembro.
        $this->at('2026-11-10 15:00:00');
        $sale = $this->sale('2000.00', '2026-11-05 15:00:00', ['client_id' => $lead->client_id]);
        app(CommissionService::class)->syncSale($sale);
        $this->rebuild('2026-10');
        $this->rebuild('2026-11');

        $tiers = $this->goalEntries()->where('goal_kind', 'tier');
        // Outubro: faixa atingida com base só nas vendas de outubro (nenhuma) → 50.
        $this->assertSame([['2026-10-01', '50.00', '0.00']], $tiers->map(fn ($e) => [$e->competence_month->toDateString(), $e->amount, $e->base_amount])->values()->all());
        // Novembro: a venda entra no faturamento de novembro (não no de outubro).
        $service = app(GoalRemunerationService::class);
        $this->assertSame(0.0, $service->clinicRevenue($this->clinic->id, '2026-10'));
        $this->assertSame(2000.0, $service->clinicRevenue($this->clinic->id, '2026-11'));
        $this->assertSame([['2026-11-01', '10.00']], $this->entries('revenue')->map(fn ($e) => [$e->competence_month->toDateString(), $e->amount])->all());
    }

    // ---------------------------------------------------------------- faixa paga

    public function test_paid_tier_gets_no_top_up_when_a_higher_tier_is_reached_later(): void
    {
        // Faturamento: pago na faixa de 70 mil; depois o mês chega a 120 mil → nada a mais.
        $this->revenueTiers([
            ['threshold' => '70000', 'fixed_amount' => '300'],
            ['threshold' => '100000', 'fixed_amount' => '500', 'percent' => '1'],
        ]);
        $this->sale('75000.00', '2026-10-10 15:00:00');
        $this->rebuild('2026-10');
        $paidRevenue = $this->entries('revenue')->sole();
        $this->markPaid($paidRevenue->id);
        app(CommissionService::class)->syncSale($this->sale('45000.00', '2026-10-12 15:00:00'));
        $this->rebuild('2026-10');
        $this->assertSame([[$paidRevenue->id, '300.00', 'paid', '70000.00']], $this->entries('revenue')
            ->map(fn ($e) => [$e->id, $e->amount, $e->status, $e->tier_threshold])->all());

        // Agendamentos: pago na faixa de 1; depois chega à faixa de 2 → nada a mais.
        $this->appointmentConfig([['threshold' => 1, 'fixed_amount' => '100'], ['threshold' => 2, 'fixed_amount' => '250']]);
        $this->convertAsSeller();
        $paidTier = $this->goalEntries()->where('goal_kind', 'tier')->sole();
        $this->markPaid($paidTier->id);
        $this->convertAsSeller();
        $this->rebuild('2026-10');
        $this->assertSame([[$paidTier->id, '100.00', 'paid']], $this->goalEntries()->where('goal_kind', 'tier')
            ->map(fn ($e) => [$e->id, $e->amount, $e->status])->values()->all());
    }

    // ---------------------------------------------------------------- desativado

    public function test_deactivated_collaborator_earns_no_goal_or_revenue_pay_and_paid_stays(): void
    {
        $this->appointmentConfig([['threshold' => 1, 'fixed_amount' => '100']], per: '20');
        $this->revenueTiers([['threshold' => '1000', 'fixed_amount' => '300']]);
        $this->revenueTiers([['threshold' => '1000', 'fixed_amount' => '150']], $this->seller);
        $this->convertAsSeller();
        $this->convertAsSeller();
        $this->sale('5000.00', '2026-10-10 15:00:00');
        $this->rebuild('2026-10');
        $this->assertSame(['20.00', '20.00', '100.00'], $this->goalEntries()->pluck('amount')->sortBy(fn ($a) => (float) $a)->values()->all());
        $this->assertCount(2, $this->entries('revenue'));

        // Uma ocorrência já paga fica; o resto do vendedor sai ao desativar.
        $paid = $this->goalEntries()->firstWhere('goal_kind', 'occurrence');
        $this->markPaid($paid->id);
        $this->deleteJson("/api/v1/users/{$this->seller->id}")->assertOk();
        $this->assertSame([[$paid->id, 'paid']], $this->goalEntries()->map(fn ($e) => [$e->id, $e->status])->all());
        $this->assertSame([$this->receptionist->id], $this->entries('revenue')->pluck('user_id')->all());

        // Recalcular não volta a lançar para o desativado (metas nem faturamento).
        $this->rebuild('2026-10');
        $this->assertSame([[$paid->id, 'paid']], $this->goalEntries()->map(fn ($e) => [$e->id, $e->status])->all());
        $this->assertSame([$this->receptionist->id], $this->entries('revenue')->pluck('user_id')->all());

        // Faturamento pago do desativado também fica.
        $this->revenueTiers([['threshold' => '1000', 'fixed_amount' => '300']]);
        $receptionistEntry = $this->entries('revenue', $this->receptionist->id)->sole();
        $this->markPaid($receptionistEntry->id);
        $this->deleteJson("/api/v1/users/{$this->receptionist->id}")->assertOk();
        $this->rebuild('2026-10');
        $this->assertSame([[$receptionistEntry->id, 'paid']], $this->entries('revenue')->map(fn ($e) => [$e->id, $e->status])->all());

        // Reativado: volta a ganhar no mês atual (o pago não duplica).
        $this->putJson("/api/v1/users/{$this->seller->id}", ['is_active' => true])->assertOk();
        $this->assertSame(['20.00', '20.00', '100.00'], $this->goalEntries()->pluck('amount')->sortBy(fn ($a) => (float) $a)->values()->all());
        $this->assertSame(1, $this->goalEntries()->where('status', 'paid')->count());
        $this->assertSame(['150.00'], $this->entries('revenue', $this->seller->id)->pluck('amount')->all());
    }
}
