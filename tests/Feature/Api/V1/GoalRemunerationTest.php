<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\CommissionEntry;
use App\Models\GoalRemuneration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserGoal;
use App\Services\GoalRemunerationService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoalRemunerationTest extends TestCase
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

    private function configure(string $metric, string $per, string $bonus = '0'): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/finance/goal-remunerations/{$metric}", [
            'per_occurrence_amount' => $per,
            'target_bonus_amount' => $bonus,
        ])->assertOk();
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

    /** Converte um lead como o vendedor (marco "Agendou" + consulta marcada). */
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

    private function goalEntries(?int $userId = null): Collection
    {
        return CommissionEntry::query()
            ->where('role', CommissionEntry::ROLE_GOAL)
            ->where('user_id', $userId ?? $this->seller->id)
            ->get();
    }

    public function test_defaults_are_zero_and_nothing_is_generated(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/goal-remunerations')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.metric', 'appointments')
            ->assertJsonPath('data.0.label', 'Novo agendamento')
            ->assertJsonPath('data.0.per_occurrence_amount', '0.00')
            ->assertJsonPath('data.0.target_bonus_amount', '0.00')
            ->assertJsonPath('data.2.metric', 'sales')
            ->assertJsonPath('data.2.supports_per_occurrence', false);

        $this->convertAsSeller();
        $this->assertCount(0, $this->goalEntries());
        $this->assertDatabaseCount('goal_remunerations', 0);
    }

    public function test_admin_configures_and_others_cannot(): void
    {
        $this->configure('appointments', '80.00', '300');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/goal-remunerations')->assertOk()
            ->assertJsonPath('data.0.per_occurrence_amount', '80.00')
            ->assertJsonPath('data.0.target_bonus_amount', '300.00');

        $this->putJson('/api/v1/finance/goal-remunerations/appointments', ['per_occurrence_amount' => '-1', 'target_bonus_amount' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('per_occurrence_amount');
        $this->putJson('/api/v1/finance/goal-remunerations/appointments', ['per_occurrence_amount' => '1.234', 'target_bonus_amount' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('per_occurrence_amount');
        $this->putJson('/api/v1/finance/goal-remunerations/appointments', ['per_occurrence_amount' => '10'])
            ->assertUnprocessable()->assertJsonValidationErrors('target_bonus_amount');
        $this->putJson('/api/v1/finance/goal-remunerations/sales', ['per_occurrence_amount' => '50', 'target_bonus_amount' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('per_occurrence_amount');
        $this->putJson('/api/v1/finance/goal-remunerations/sales', ['target_bonus_amount' => '500'])
            ->assertOk()->assertJsonPath('data.2.target_bonus_amount', '500.00');
        $this->putJson('/api/v1/finance/goal-remunerations/unknown', ['per_occurrence_amount' => '1', 'target_bonus_amount' => '0'])
            ->assertNotFound();

        // Zerar volta ao padrão.
        $this->configure('appointments', '0', '0');
        $this->getJson('/api/v1/finance/goal-remunerations')->assertJsonPath('data.0.per_occurrence_amount', '0.00');

        foreach ([$this->seller, $this->receptionist] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/finance/goal-remunerations')->assertForbidden();
            $this->putJson('/api/v1/finance/goal-remunerations/appointments', ['per_occurrence_amount' => '80', 'target_bonus_amount' => '0'])
                ->assertForbidden();
        }

        // Outra clínica não vê nem herda a configuração.
        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        CurrentClinic::setId($other->id);
        Sanctum::actingAs($otherAdmin);
        $this->getJson('/api/v1/finance/goal-remunerations')->assertOk()
            ->assertJsonPath('data.2.target_bonus_amount', '0.00');
    }

    public function test_each_new_appointment_pays_the_fixed_amount_and_shows_in_commissions(): void
    {
        $this->configure('appointments', '80.00');
        [$lead] = $this->convertAsSeller();
        $this->convertAsSeller();

        $entries = $this->goalEntries();
        $this->assertCount(2, $entries);
        $this->assertSame(['80.00', '80.00'], $entries->pluck('amount')->all());
        $this->assertSame('2026-10-01', $entries->first()->competence_month->toDateString());

        // Comissão de venda já existente fica lado a lado, separada por origem.
        CommissionEntry::query()->create([
            'clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'role' => 'seller',
            'competence_month' => '2026-10-01', 'base_amount' => '1000', 'amount' => '50.00',
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/finance/commissions?month=2026-10')->assertOk()
            ->assertJsonPath('meta.total', '210.00')
            ->assertJsonPath('meta.by_origin.sales', '50.00')
            ->assertJsonPath('meta.by_origin.goals', '160.00')
            ->assertJsonPath('meta.by_user.0.user_id', $this->seller->id)
            ->assertJsonPath('meta.by_user.0.by_origin.goals', '160.00');

        $goalRow = collect($response->json('data'))->firstWhere('lead_id', $lead->id);
        $this->assertSame('goals', $goalRow['origin']);
        $this->assertSame('appointments', $goalRow['goal_metric']);
        $this->assertSame('occurrence', $goalRow['goal_kind']);
        $this->assertSame($lead->name, $goalRow['client_name']);
        $this->assertNotNull($goalRow['occurred_at']);
    }

    public function test_cancelled_evaluation_no_longer_counts_but_paid_entries_stay(): void
    {
        $this->configure('appointments', '80.00');
        [, $first] = $this->convertAsSeller();
        [, $second] = $this->convertAsSeller();
        $this->assertCount(2, $this->goalEntries());

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/appointments/{$first}/cancel")->assertOk();
        $this->assertCount(1, $this->goalEntries());
        $this->assertSame(Appointment::STATUS_CANCELLED, Appointment::query()->find($first)->status);

        // Pago não volta atrás, mesmo cancelando depois.
        $paidId = $this->goalEntries()->first()->id;
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => [$paidId], 'paid' => true])->assertOk();
        $this->postJson("/api/v1/appointments/{$second}/cancel")->assertOk();
        $this->assertSame([CommissionEntry::STATUS_PAID], $this->goalEntries()->pluck('status')->all());

        // Recalcular o mês não duplica nem remove o pago.
        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => '2026-10'])->assertOk();
        $this->assertSame([$paidId], $this->goalEntries()->pluck('id')->all());
    }

    public function test_cancelled_lead_no_longer_pays_approached_but_paid_entries_stay(): void
    {
        $this->configure('approached', '5.50');
        $lead = $this->newLead();
        $kept = $this->newLead();
        $paidLead = $this->newLead();
        Sanctum::actingAs($this->seller);
        foreach ([$lead, $kept, $paidLead] as $l) {
            $this->postJson("/api/v1/leads/{$l->id}/milestones/approached")->assertOk();
        }
        // Marcar "Respondeu" não paga de novo (o abordado já existia).
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/replied")->assertOk();
        $this->assertSame(['5.50', '5.50', '5.50'], $this->goalEntries()->pluck('amount')->all());

        // Paga a abordagem de um deles antes do cancelamento.
        Sanctum::actingAs($this->admin);
        $paidId = $this->goalEntries()->firstWhere('lead_id', $paidLead->id)->id;
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => [$paidId], 'paid' => true])->assertOk();

        Sanctum::actingAs($this->seller);
        $this->postJson("/api/v1/leads/{$lead->id}/cancel")->assertOk();
        $this->postJson("/api/v1/leads/{$paidLead->id}/cancel")->assertOk();

        // O pendente do lead cancelado sai; o pago fica; o outro lead segue pagando.
        $entries = $this->goalEntries()->sortBy('lead_id')->values();
        $this->assertSame([$kept->id, $paidLead->id], $entries->pluck('lead_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([CommissionEntry::STATUS_PENDING, CommissionEntry::STATUS_PAID], $entries->pluck('status')->all());

        // "Recalcular mês" chega ao mesmo resultado e nunca mexe no pago.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => '2026-10'])->assertOk();
        $this->assertSame([$kept->id, $paidLead->id], $this->goalEntries()->sortBy('lead_id')->pluck('lead_id')->map(fn ($id) => (int) $id)->values()->all());
        $this->assertSame(CommissionEntry::STATUS_PAID, CommissionEntry::query()->find($paidId)->status);

        // A tela de Metas usa a mesma contagem: só o lead não cancelado.
        Sanctum::actingAs($this->seller);
        $metrics = collect($this->getJson('/api/v1/goals/me?month=2026-10')->assertOk()->json('data.metrics'))->keyBy('key');
        $this->assertSame(1, $metrics['approached']['actual']);
    }

    public function test_recalculation_drops_pending_approach_of_a_lead_cancelled_without_hooks(): void
    {
        GoalRemuneration::query()->create(['clinic_id' => $this->clinic->id, 'metric' => 'approached', 'per_occurrence_amount' => '5']);
        $lead = $this->newLead(['approached_at' => '2026-10-10 12:00:00', 'approached_by_user_id' => $this->seller->id]);
        $service = app(GoalRemunerationService::class);
        $service->syncUserMonth($this->clinic->id, $this->seller->id, '2026-10');
        $this->assertCount(1, $this->goalEntries());

        // Cancelado direto no banco (dados antigos): o "Recalcular mês" tira o pendente.
        Lead::query()->whereKey($lead->id)->update(['cancelled_at' => now()]);
        $service->syncMonth($this->clinic->id, '2026-10');
        $this->assertCount(0, $this->goalEntries());
    }

    public function test_conversion_without_prior_approach_pays_only_the_appointment(): void
    {
        $this->configure('approached', '5.00');
        $this->configure('appointments', '80.00');

        // Converteu direto (sem "Abordado" antes): só o agendamento.
        [$direct] = $this->convertAsSeller();
        $this->assertNotNull($direct->approached_at, 'a conversão continua marcando o funil inteiro');
        $this->assertSame([['appointments', '80.00']], $this->goalEntries()->map(fn ($e) => [$e->goal_metric, $e->amount])->all());

        // Abordado antes pela mesma pessoa e depois convertido: paga os dois.
        $approachedFirst = $this->newLead();
        Sanctum::actingAs($this->seller);
        $this->postJson("/api/v1/leads/{$approachedFirst->id}/milestones/approached")->assertOk();
        Carbon::setTestNow('2026-10-15 16:00:00');
        CarbonImmutable::setTestNow('2026-10-15 16:00:00');
        $this->postJson("/api/v1/leads/{$approachedFirst->id}/convert", [
            'scheduled_at' => now()->addDays(3)->setTime(11, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
            'create_new_client' => true,
        ])->assertCreated();

        $byMetric = $this->goalEntries()->groupBy('goal_metric')->map->count()->all();
        ksort($byMetric);
        $this->assertSame(['appointments' => 2, 'approached' => 1], $byMetric);
        $this->assertSame($approachedFirst->id, (int) $this->goalEntries()->firstWhere('goal_metric', 'approached')->lead_id);

        // Abordado por outra pessoa e convertido pelo vendedor: a abordagem é de quem abordou.
        $other = $this->newLead(['approached_at' => '2026-10-15 16:00:00', 'approached_by_user_id' => $this->receptionist->id,
            'booked_at' => '2026-10-15 16:00:00', 'booked_by_user_id' => $this->seller->id]);
        app(GoalRemunerationService::class)->syncUserMonth($this->clinic->id, $this->receptionist->id, '2026-10');
        $this->assertSame([$other->id], $this->goalEntries($this->receptionist->id)->pluck('lead_id')->map(fn ($id) => (int) $id)->all());

        // Metas segue a mesma regra: 1 abordado (o anterior), 3 agendamentos (as conversões contam).
        $metrics = collect($this->getJson('/api/v1/goals/me?month=2026-10')->assertOk()->json('data.metrics'))->keyBy('key');
        $this->assertSame(1, $metrics['approached']['actual']);
        $this->assertSame(3, $metrics['appointments']['actual']);
        Sanctum::actingAs($this->receptionist);
        $metrics = collect($this->getJson('/api/v1/goals/me?month=2026-10')->assertOk()->json('data.metrics'))->keyBy('key');
        $this->assertSame(1, $metrics['approached']['actual']);
    }

    public function test_goals_screen_excludes_cancelled_evaluations_like_the_payout(): void
    {
        UserGoal::query()->create(['clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'month' => '2026-10', 'appointments_target' => 2]);
        $this->configure('appointments', '80.00');
        [, $first] = $this->convertAsSeller();
        $this->convertAsSeller();

        Sanctum::actingAs($this->seller);
        $metrics = collect($this->getJson('/api/v1/goals/me')->assertOk()->json('data.metrics'))->keyBy('key');
        $this->assertSame(2, $metrics['appointments']['actual']);
        $this->assertSame(100, $metrics['appointments']['percent']);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/appointments/{$first}/cancel")->assertOk();

        Sanctum::actingAs($this->seller);
        $metrics = collect($this->getJson('/api/v1/goals/me')->assertOk()->json('data.metrics'))->keyBy('key');
        $this->assertSame(1, $metrics['appointments']['actual']);
        $this->assertSame(1, $metrics['appointments']['remaining']);
        // Mesmo número que o pagamento.
        $this->assertCount(1, $this->goalEntries()->where('goal_metric', 'appointments'));

        // Visão da equipe (admin) também.
        Sanctum::actingAs($this->admin);
        $row = collect($this->getJson('/api/v1/goals?month=2026-10')->assertOk()->json('data'))->firstWhere('user.id', $this->seller->id);
        $this->assertSame(1, collect($row['metrics'])->firstWhere('key', 'appointments')['actual']);
    }

    public function test_month_boundaries_follow_sao_paulo_time(): void
    {
        GoalRemuneration::query()->create(['clinic_id' => $this->clinic->id, 'metric' => 'appointments', 'per_occurrence_amount' => '80']);
        // 30/09 23:59:59 em SP = 01/10 02:59:59 UTC → setembro.
        $this->newLead(['booked_at' => '2026-10-01 02:59:59', 'booked_by_user_id' => $this->seller->id]);
        // 01/10 00:00:00 em SP → outubro.
        $this->newLead(['booked_at' => '2026-10-01 03:00:00', 'booked_by_user_id' => $this->seller->id]);
        // 31/10 23:59:59 em SP → outubro.
        $this->newLead(['booked_at' => '2026-11-01 02:59:59', 'booked_by_user_id' => $this->seller->id]);
        // 01/11 00:00:00 em SP → novembro.
        $this->newLead(['booked_at' => '2026-11-01 03:00:00', 'booked_by_user_id' => $this->seller->id]);
        // De outra pessoa: não conta para o vendedor.
        $this->newLead(['booked_at' => '2026-10-10 12:00:00', 'booked_by_user_id' => $this->admin->id]);

        $service = app(GoalRemunerationService::class);
        foreach (['2026-09', '2026-10', '2026-11'] as $month) {
            $service->syncUserMonth($this->clinic->id, $this->seller->id, $month);
        }

        $byMonth = $this->goalEntries()->groupBy(fn ($e) => $e->competence_month->format('Y-m'))->map->count()->all();
        ksort($byMonth);
        $this->assertSame(['2026-09' => 1, '2026-10' => 2, '2026-11' => 1], $byMonth);

        // O recálculo do mês pela tela chega ao mesmo resultado e inclui a outra pessoa.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => '2026-10'])->assertOk();
        $this->assertCount(2, $this->goalEntries()->filter(fn ($e) => $e->competence_month->format('Y-m') === '2026-10'));
        $this->assertCount(1, $this->goalEntries($this->admin->id));
    }

    public function test_bonus_when_the_monthly_target_is_reached(): void
    {
        UserGoal::query()->create(['clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'month' => '2026-09', 'appointments_target' => 2]);
        $this->configure('appointments', '80.00', '300.00');

        [, $first] = $this->convertAsSeller();
        $this->assertNull($this->goalEntries()->firstWhere('goal_kind', 'bonus'));

        // Meta herdada de setembro (2): a segunda bate a meta.
        $this->convertAsSeller();
        $bonus = $this->goalEntries()->firstWhere('goal_kind', 'bonus');
        $this->assertNotNull($bonus);
        $this->assertSame('300.00', $bonus->amount);
        $this->assertSame('2.00', $bonus->base_amount);
        $this->assertSame('460.00', number_format((float) $this->goalEntries()->sum('amount'), 2, '.', ''));

        // Cancelar uma consulta tira o agendamento e o bônus.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/appointments/{$first}/cancel")->assertOk();
        $this->assertNull($this->goalEntries()->firstWhere('goal_kind', 'bonus'));

        // Subir a meta pela tela de metas recalcula; baixar para 1 devolve o bônus.
        $this->putJson("/api/v1/goals/{$this->seller->id}", [
            'month' => '2026-10', 'appointments_target' => 1, 'sales_target' => null, 'approached_target' => null,
        ])->assertOk();
        $this->assertNotNull($this->goalEntries()->firstWhere('goal_kind', 'bonus'));

        // Sem meta (ou meta zero) não há bônus.
        $this->putJson("/api/v1/goals/{$this->seller->id}", [
            'month' => '2026-10', 'appointments_target' => 0, 'sales_target' => null, 'approached_target' => null,
        ])->assertOk();
        $this->assertNull($this->goalEntries()->firstWhere('goal_kind', 'bonus'));
    }

    public function test_sales_target_bonus_counts_only_confirmed_sales_in_the_month(): void
    {
        UserGoal::query()->create(['clinic_id' => $this->clinic->id, 'user_id' => $this->seller->id, 'month' => '2026-10', 'sales_target' => '2000.00']);
        $this->configure('sales', '0', '250.00');

        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_CONFIRMED, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now()->subDays(2), 'effective_amount' => '1999.99']);
        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_DRAFT, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now(), 'effective_amount' => '9000.00']);
        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_CONFIRMED, 'sold_by_user_id' => $this->seller->id, 'sold_at' => '2026-09-30 12:00:00', 'effective_amount' => '9000.00']);
        app(GoalRemunerationService::class)->syncUserMonth($this->clinic->id, $this->seller->id, '2026-10');
        $this->assertCount(0, $this->goalEntries());

        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_CONFIRMED, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now(), 'effective_amount' => '0.01']);
        app(GoalRemunerationService::class)->syncUserMonth($this->clinic->id, $this->seller->id, '2026-10');
        $this->assertSame(['250.00'], $this->goalEntries()->pluck('amount')->all());
        $this->assertSame('sales', $this->goalEntries()->first()->goal_metric);
    }

    public function test_changing_the_amount_updates_pending_entries_of_the_current_month_only(): void
    {
        $this->configure('appointments', '80.00');
        $this->convertAsSeller();
        // Um agendamento de setembro, lançado com 80.
        $this->newLead(['booked_at' => '2026-09-10 12:00:00', 'booked_by_user_id' => $this->seller->id]);
        app(GoalRemunerationService::class)->syncUserMonth($this->clinic->id, $this->seller->id, '2026-09');

        $this->configure('appointments', '100.00');
        $amounts = $this->goalEntries()->mapWithKeys(fn ($e) => [$e->competence_month->format('Y-m') => $e->amount])->all();
        $this->assertSame('100.00', $amounts['2026-10']);
        $this->assertSame('80.00', $amounts['2026-09']);

        // Zerar remove os pendentes do mês atual.
        $this->configure('appointments', '0');
        $this->assertCount(1, $this->goalEntries());
    }

    public function test_migration_links_existing_conversions_to_their_evaluation(): void
    {
        [$lead, $appointmentId] = $this->convertAsSeller();
        $this->assertSame($appointmentId, (int) $lead->booked_appointment_id);

        Lead::query()->whereKey($lead->id)->update(['booked_appointment_id' => null]);
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_01_120000_add_goal_remunerations.php']);
        Artisan::call('migrate');
        $this->assertSame($appointmentId, (int) Lead::query()->find($lead->id)->booked_appointment_id);
    }
}
