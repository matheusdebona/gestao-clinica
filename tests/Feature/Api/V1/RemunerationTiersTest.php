<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\CommissionEntry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\RemunerationTier;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\GoalRemunerationService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Faixas de remuneração (faturamento da clínica por colaborador e novos agendamentos) e o custo
 * da remuneração por metas/faturamento na DRE e na margem de contribuição.
 */
class RemunerationTiersTest extends TestCase
{
    use RefreshDatabase;

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
        Carbon::setTestNow('2026-10-15 15:00:00');
        CarbonImmutable::setTestNow('2026-10-15 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    /** Faixas do exemplo: 70 mil → R$ 300; 80 mil → R$ 500; 100 mil → R$ 500 + 1% do total. */
    private function exampleRevenueTiers(?User $user = null): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/finance/revenue-remunerations/'.($user ?? $this->receptionist)->id, ['tiers' => [
            ['threshold' => '80000', 'fixed_amount' => '500', 'percent' => null],
            ['threshold' => '70000', 'fixed_amount' => '300'],
            ['threshold' => '100000', 'fixed_amount' => '500', 'percent' => '1'],
        ]])->assertOk();
    }

    private function sale(string $amount, string $soldAt = '2026-10-10 15:00:00', array $attributes = []): Sale
    {
        return Sale::factory()->forClinic($this->clinic)->create([
            'status' => Sale::STATUS_CONFIRMED, 'sold_at' => $soldAt, 'effective_amount' => $amount,
            'sold_by_user_id' => $this->admin->id, ...$attributes,
        ]);
    }

    private function entries(string $role, ?int $userId = null): Collection
    {
        return CommissionEntry::query()->where('role', $role)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')->get();
    }

    private function rebuild(string $month = '2026-10'): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => $month])->assertOk();
    }

    private function newLead(array $attributes = []): Lead
    {
        $this->originId ??= ClientOrigin::factory()->create(['clinic_id' => $this->clinic->id])->id;
        $this->statusId ??= LeadStatus::factory()->create(['clinic_id' => $this->clinic->id])->id;

        return Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => $this->originId,
            'lead_status_id' => $this->statusId,
            'phone' => '119'.str_pad((string) (++$this->phone), 8, '0', STR_PAD_LEFT),
            ...$attributes,
        ]);
    }

    /** @return array{0: Lead, 1: int} lead convertido pelo vendedor e a avaliação marcada */
    private function convertAsSeller(): array
    {
        $lead = $this->newLead();
        Sanctum::actingAs($this->seller);
        $response = $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'scheduled_at' => now()->addDays(2 + $this->phone)->setTime(10, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
            'create_new_client' => true,
        ])->assertCreated();

        return [$lead->fresh(), (int) $response->json('data.appointment.id')];
    }

    // ---------------------------------------------------------------- faturamento

    public function test_revenue_example_highest_tier_only_with_percent_of_total(): void
    {
        $this->exampleRevenueTiers();
        $sale = $this->sale('75000.00');
        $this->rebuild();
        $this->assertSame(['300.00'], $this->entries('revenue', $this->receptionist->id)->pluck('amount')->all());
        $entry = $this->entries('revenue')->first();
        $this->assertSame('75000.00', $entry->base_amount);
        $this->assertSame('70000.00', $entry->tier_threshold);
        $this->assertSame('2026-10-01', $entry->competence_month->toDateString());

        $sale->update(['effective_amount' => '85000.00']);
        $this->rebuild();
        $this->assertSame(['500.00'], $this->entries('revenue')->pluck('amount')->all());

        // 120 mil: só a faixa de 100 mil (não acumula): 500 + 1% de 120 mil = 1.700.
        $sale->update(['effective_amount' => '120000.00']);
        $this->rebuild();
        $this->assertSame(['1700.00'], $this->entries('revenue')->pluck('amount')->all());
        $this->assertSame('1.0000', $this->entries('revenue')->first()->rate_percent);

        // Abaixo do primeiro gatilho: nada.
        $sale->update(['effective_amount' => '69999.99']);
        $this->rebuild();
        $this->assertCount(0, $this->entries('revenue'));
    }

    public function test_revenue_is_the_clinic_total_of_confirmed_sales_in_the_sao_paulo_month(): void
    {
        $this->exampleRevenueTiers();
        // Vendas de várias pessoas somam no faturamento da clínica.
        $this->sale('40000.00', '2026-10-01 03:00:00');            // 01/10 00:00 em SP → conta
        $this->sale('30000.00', '2026-11-01 02:59:59', ['sold_by_user_id' => $this->seller->id]); // 31/10 23:59 SP → conta
        $this->sale('50000.00', '2026-10-01 02:59:59');            // 30/09 em SP → não conta
        $this->sale('90000.00', '2026-10-12 12:00:00', ['status' => Sale::STATUS_DRAFT]);
        $this->sale('90000.00', '2026-10-12 12:00:00', ['status' => Sale::STATUS_CANCELLED]);

        $this->assertSame(70000.0, app(GoalRemunerationService::class)->clinicRevenue($this->clinic->id, '2026-10'));
        $this->rebuild();
        $this->assertSame(['300.00'], $this->entries('revenue')->pluck('amount')->all());
    }

    public function test_confirming_a_sale_updates_the_revenue_entry_and_paid_never_changes(): void
    {
        $this->exampleRevenueTiers();
        $this->sale('72000.00');
        $this->rebuild();
        $this->assertSame(['300.00'], $this->entries('revenue')->pluck('amount')->all());

        // A sincronização da venda (como ao confirmar) refaz o faturamento sem "Recalcular mês".
        $another = $this->sale('10000.00');
        app(CommissionService::class)->syncSale($another);
        $this->assertSame(['500.00'], $this->entries('revenue')->pluck('amount')->all());

        // Pago não muda, nem com o faturamento subindo de faixa.
        $id = $this->entries('revenue')->first()->id;
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => [$id], 'paid' => true])->assertOk();
        $this->sale('50000.00');
        $this->rebuild();
        $this->assertSame([[$id, '500.00', 'paid']], $this->entries('revenue')->map(fn ($e) => [$e->id, $e->amount, $e->status])->all());
    }

    public function test_revenue_entries_show_in_commissions_under_their_own_origin(): void
    {
        $this->exampleRevenueTiers();
        $this->sale('120000.00');
        $this->rebuild();

        $response = $this->getJson('/api/v1/finance/commissions?month=2026-10')->assertOk()
            ->assertJsonPath('meta.by_origin.revenue', '1700.00')
            ->assertJsonPath('meta.by_origin.goals', '0.00');
        $row = collect($response->json('data'))->firstWhere('origin', 'revenue');
        $this->assertSame($this->receptionist->id, $row['user_id']);
        $this->assertSame('tier', $row['goal_kind']);
        $this->assertSame('100000.00', $row['tier_threshold']);
        $this->assertSame('120000.00', $row['base_amount']);
        $person = collect($response->json('meta.by_user'))->firstWhere('user_id', $this->receptionist->id);
        $this->assertSame('1700.00', $person['by_origin']['revenue']);
    }

    public function test_revenue_config_admin_only_validation_and_nothing_without_tiers(): void
    {
        // Sem faixas: nada muda.
        $this->sale('500000.00');
        $this->rebuild();
        $this->assertCount(0, $this->entries('revenue'));
        $this->getJson('/api/v1/finance/revenue-remunerations')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.month', '2026-10')
            ->assertJsonPath('meta.month_revenue', '500000.00');

        $this->exampleRevenueTiers();
        $this->getJson('/api/v1/finance/revenue-remunerations')->assertOk()
            ->assertJsonPath('data.0.user_id', $this->receptionist->id)
            ->assertJsonPath('data.0.tiers.0.threshold', '70000.00')
            ->assertJsonPath('data.0.tiers.0.fixed_amount', '300.00')
            ->assertJsonPath('data.0.tiers.0.percent', '0')
            ->assertJsonPath('data.0.tiers.2.percent', '1');
        // Salvar já refaz o mês atual (500 mil: 500 + 1% = 5.500).
        $this->assertSame(['5500.00'], $this->entries('revenue')->pluck('amount')->all());

        $url = "/api/v1/finance/revenue-remunerations/{$this->receptionist->id}";
        $this->putJson($url, ['tiers' => [['threshold' => '1000']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.0.fixed_amount');
        $this->putJson($url, ['tiers' => [['threshold' => '1000', 'fixed_amount' => '1'], ['threshold' => '1000.00', 'percent' => '2']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.1.threshold');
        $this->putJson($url, ['tiers' => [['threshold' => '0', 'fixed_amount' => '1']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.0.threshold');
        $this->putJson($url, ['tiers' => [['threshold' => '1000', 'percent' => '101']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.0.percent');
        $this->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('tiers');

        foreach ([$this->seller, $this->receptionist] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/finance/revenue-remunerations')->assertForbidden();
            $this->putJson($url, ['tiers' => []])->assertForbidden();
        }

        // Colaborador de outra clínica: não encontrado.
        $other = Clinic::factory()->create();
        $stranger = User::factory()->forClinic($other)->create();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/finance/revenue-remunerations/{$stranger->id}", ['tiers' => []])->assertNotFound();

        // Remover as faixas tira os pendentes do mês atual.
        $this->putJson($url, ['tiers' => []])->assertOk()->assertJsonCount(0, 'data');
        $this->assertCount(0, $this->entries('revenue'));
        $this->assertDatabaseCount('remuneration_tiers', 0);
    }

    // ---------------------------------------------------------------- faixas de agendamentos

    private function appointmentConfig(array $tiers, string $per = '0'): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/finance/goal-remunerations/appointments', [
            'per_occurrence_amount' => $per,
            'target_bonus_amount' => '0',
            'tiers' => $tiers,
        ])->assertOk();
    }

    private function tierEntries(): Collection
    {
        return CommissionEntry::query()->where('role', 'goal')->where('goal_kind', 'tier')
            ->where('user_id', $this->seller->id)->orderBy('id')->get();
    }

    public function test_appointment_tiers_by_count_with_percent_of_patients_sales(): void
    {
        $this->appointmentConfig([
            ['threshold' => 2, 'fixed_amount' => '100'],
            ['threshold' => 3, 'fixed_amount' => '150', 'percent' => '10'],
        ], per: '20');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/goal-remunerations')->assertOk()
            ->assertJsonPath('data.0.supports_tiers', true)
            ->assertJsonPath('data.0.tiers.0.threshold', 2)
            ->assertJsonPath('data.0.tiers.1.percent', '10')
            ->assertJsonPath('data.1.supports_tiers', false);

        [$first, $firstAppointment] = $this->convertAsSeller();
        $this->assertCount(0, $this->tierEntries());
        [$second] = $this->convertAsSeller();
        $this->assertSame(['100.00'], $this->tierEntries()->pluck('amount')->all());
        $this->assertSame('2.00', $this->tierEntries()->first()->tier_threshold);

        [$third] = $this->convertAsSeller();
        // Faixa 3: 150 + 10% das vendas dos pacientes (ainda sem vendas).
        $this->assertSame(['150.00'], $this->tierEntries()->pluck('amount')->all());

        // Venda confirmada de um desses pacientes no mês entra na base; de outro paciente, não.
        $patientSale = $this->sale('1000.00', '2026-10-15 15:00:00', ['client_id' => $first->client_id]);
        app(CommissionService::class)->syncSale($patientSale);
        $this->sale('5000.00', '2026-10-15 15:00:00');
        $this->sale('7000.00', '2026-09-20 15:00:00', ['client_id' => $second->client_id]); // outro mês
        $this->assertSame(['250.00'], $this->tierEntries()->pluck('amount')->all());
        $tier = $this->tierEntries()->first();
        $this->assertSame('1000.00', $tier->base_amount);
        $this->assertSame('10.0000', $tier->rate_percent);

        // Valor por ocorrência continua (os dois pagam).
        $occurrences = CommissionEntry::query()->where('role', 'goal')->where('goal_kind', 'occurrence')->where('user_id', $this->seller->id)->pluck('amount')->all();
        $this->assertSame(['20.00', '20.00', '20.00'], $occurrences);

        // Avaliação cancelada não conta: volta à faixa de 2.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/appointments/{$firstAppointment}/cancel")->assertOk();
        $this->assertSame(['100.00'], $this->tierEntries()->pluck('amount')->all());

        // Faixa paga não muda; recalcular não duplica.
        $id = $this->tierEntries()->first()->id;
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => [$id], 'paid' => true])->assertOk();
        $this->convertAsSeller();
        $this->rebuild();
        $this->assertSame([[$id, '100.00', 'paid']], $this->tierEntries()->map(fn ($e) => [$e->id, $e->amount, $e->status])->all());
        $this->assertNotNull($third);
    }

    public function test_appointment_tiers_alone_validation_and_removal(): void
    {
        // Só faixas, sem valor por ocorrência.
        $this->appointmentConfig([['threshold' => 1, 'percent' => '5']]);
        [$lead] = $this->convertAsSeller();
        $sale = $this->sale('2000.00', '2026-10-16 10:00:00', ['client_id' => $lead->client_id]);
        app(GoalRemunerationService::class)->onSaleChanged($sale);
        $this->assertSame(['100.00'], $this->tierEntries()->pluck('amount')->all());
        $this->assertCount(0, CommissionEntry::query()->where('goal_kind', 'occurrence')->get());

        Sanctum::actingAs($this->admin);
        $url = '/api/v1/finance/goal-remunerations/appointments';
        $this->putJson($url, ['per_occurrence_amount' => '0', 'target_bonus_amount' => '0', 'tiers' => [['threshold' => '1.5', 'fixed_amount' => '10']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.0.threshold');
        $this->putJson($url, ['per_occurrence_amount' => '0', 'target_bonus_amount' => '0', 'tiers' => [['threshold' => 2, 'fixed_amount' => '0', 'percent' => '0']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers.0.fixed_amount');
        $this->putJson('/api/v1/finance/goal-remunerations/sales', ['target_bonus_amount' => '0', 'tiers' => [['threshold' => 1, 'fixed_amount' => '10']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers');

        // Sem "tiers" no corpo: mantém as faixas (compatível com a tela antiga).
        $this->putJson($url, ['per_occurrence_amount' => '0', 'target_bonus_amount' => '0'])->assertOk()
            ->assertJsonCount(1, 'data.0.tiers');
        // Lista vazia remove; o pendente do mês atual sai.
        $this->putJson($url, ['per_occurrence_amount' => '0', 'target_bonus_amount' => '0', 'tiers' => []])->assertOk()
            ->assertJsonCount(0, 'data.0.tiers');
        $this->assertCount(0, $this->tierEntries());
        $this->assertSame(0, RemunerationTier::query()->count());

        Sanctum::actingAs($this->seller);
        $this->putJson($url, ['per_occurrence_amount' => '0', 'target_bonus_amount' => '0', 'tiers' => []])->assertForbidden();
    }

    // ---------------------------------------------------------------- DRE e margem

    public function test_goal_and_revenue_pay_are_variable_costs_in_dre_and_contribution_margin(): void
    {
        $this->sale('10000.00', '2026-10-10 15:00:00');
        $base = ['clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'base_amount' => '0'];
        // Competência outubro: pendente e pago contam (como as comissões de venda); cancelado não.
        CommissionEntry::query()->create($base + ['role' => 'goal', 'goal_metric' => 'appointments', 'goal_kind' => 'occurrence', 'competence_month' => '2026-10-01', 'amount' => '80.00']);
        CommissionEntry::query()->create($base + ['role' => 'goal', 'goal_metric' => 'appointments', 'goal_kind' => 'bonus', 'competence_month' => '2026-10-01', 'amount' => '300.00', 'status' => 'paid', 'paid_at' => now()]);
        CommissionEntry::query()->create($base + ['role' => 'revenue', 'goal_kind' => 'tier', 'competence_month' => '2026-10-01', 'amount' => '500.00']);
        CommissionEntry::query()->create($base + ['role' => 'goal', 'goal_metric' => 'approached', 'goal_kind' => 'occurrence', 'competence_month' => '2026-10-01', 'amount' => '999.00', 'status' => 'cancelled']);
        // Outro mês: fica no mês dele.
        CommissionEntry::query()->create($base + ['role' => 'goal', 'goal_metric' => 'appointments', 'goal_kind' => 'occurrence', 'competence_month' => '2026-09-01', 'amount' => '40.00']);
        // Comissão de venda continua na linha de comissões.
        $saleId = Sale::query()->value('id');
        CommissionEntry::query()->create($base + ['role' => 'seller', 'sale_id' => $saleId, 'competence_month' => '2026-10-01', 'amount' => '200.00']);

        Sanctum::actingAs($this->admin);
        $dre = $this->getJson('/api/v1/finance/dre?month=2026-10')->assertOk();
        $lines = $dre->json('data.current.lines');
        $this->assertSame('10000.00', $lines['net_revenue']);
        $this->assertSame('200.00', $lines['commissions']);
        $this->assertSame('880.00', $lines['goal_commissions']);
        $this->assertSame('1080.00', $lines['variable_costs']);
        $this->assertSame('8920.00', $lines['contribution_margin']);
        $this->assertSame('89.20', $dre->json('data.current.contribution_margin_percent'));
        $this->assertSame('40.00', $dre->json('data.previous.lines.goal_commissions'));

        $cm = $this->getJson('/api/v1/metrics/contribution-margin?from=2026-10-01&to=2026-10-31')->assertOk();
        $this->assertSame('880.00', $cm->json('data.totals.goal_commissions'));
        $this->assertSame('9800.00', $cm->json('data.totals.contribution_margin'));
        $this->assertSame('8920.00', $cm->json('data.totals.clinic_contribution_margin'));
    }
}
