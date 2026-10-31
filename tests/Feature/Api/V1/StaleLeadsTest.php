<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\User;
use App\Notifications\StaleLeadsDigestNotification;
use App\Services\LeadStaleService;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultFollowUpTiers;
use App\Support\EnsureDefaultLeadStatuses;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaleLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        EnsureDefaultFollowUpTiers::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        $this->reception = User::factory()->forClinic($this->clinic)->create();
        $this->reception->assignRole('receptionist');
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function lead(string $name, int $daysAgo, array $attributes = []): Lead
    {
        $lead = Lead::factory()->forClinic($this->clinic)->create(array_merge([
            'name' => $name,
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
            'lead_status_id' => LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id'),
        ], $attributes));
        $lead->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        LeadActivity::query()->create([
            'clinic_id' => $this->clinic->id,
            'lead_id' => $lead->id,
            'type' => 'created',
        ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $lead;
    }

    /**
     * @return array{stale: Lead, touched: Lead}
     */
    private function scenario(): array
    {
        $stale = $this->lead('Parada Antiga', 5);
        $touched = $this->lead('Com Comentário', 5);
        LeadActivity::query()->create([
            'clinic_id' => $this->clinic->id,
            'lead_id' => $touched->id,
            'type' => LeadActivity::TYPE_WHATSAPP,
            'user_id' => $this->reception->id,
        ])->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->lead('Recente', 0);
        $this->lead('Cancelada', 6, ['cancelled_at' => now()->subDays(6)]);
        $client = Client::factory()->forClinic($this->clinic)->create();
        $this->lead('Virou Paciente', 7, ['client_id' => $client->id, 'converted_at' => now()->subDays(7)]);

        return ['stale' => $stale, 'touched' => $touched];
    }

    public function test_list_filter_summary_and_metrics_use_the_clinic_tiers(): void
    {
        ['stale' => $stale] = $this->scenario();
        $old = $this->lead('Bem Antiga', 40);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/leads?stale=1')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $old->id)
            ->assertJsonPath('data.1.id', $stale->id);
        $this->getJson('/api/v1/leads?stale_days=30')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id);
        $this->assertNotNull($this->getJson('/api/v1/leads')->json('data.0.last_activity_at'));

        $this->getJson('/api/v1/leads/stale-summary')->assertOk()
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.tiers.0.days', 3)
            ->assertJsonPath('data.tiers.0.count', 2)
            ->assertJsonPath('data.tiers.1.count', 1)
            ->assertJsonPath('data.tiers.2.count', 1)
            ->assertJsonPath('data.tiers.3.count', 0)
            ->assertJsonPath('data.leads.0.name', 'Bem Antiga');

        $this->getJson('/api/v1/metrics/leads?from='.now()->subDays(60)->toDateString().'&to='.now()->toDateString())->assertOk()
            ->assertJsonPath('data.kpis.stale', 2)
            ->assertJsonPath('data.stale_tiers.2.days', 30)
            ->assertJsonPath('data.stale_tiers.2.count', 1);

        // CRUD of tiers (admin has clinics.branding).
        $this->getJson('/api/v1/lead-follow-up-tiers')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.display_label', 'Parados há 3+ dias');
        $id = $this->postJson('/api/v1/lead-follow-up-tiers', ['days' => 1, 'label' => 'Primeiro dia', 'color' => 'purple'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/lead-follow-up-tiers', ['days' => 1])->assertUnprocessable();
        $this->getJson('/api/v1/leads?stale=1')->assertJsonCount(3, 'data');
        $this->putJson("/api/v1/lead-follow-up-tiers/{$id}", ['days' => 2, 'label' => '', 'color' => 'danger'])->assertOk()
            ->assertJsonPath('data.label', null)
            ->assertJsonPath('data.display_label', 'Parados há 2+ dias');
        $this->deleteJson("/api/v1/lead-follow-up-tiers/{$id}")->assertOk();
        $this->postJson('/api/v1/lead-follow-up-tiers', ['days' => 0])->assertUnprocessable();

        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/leads/stale-summary')->assertOk();
        $this->getJson('/api/v1/lead-follow-up-tiers')->assertOk();
        $this->postJson('/api/v1/lead-follow-up-tiers', ['days' => 5])->assertForbidden();
    }

    public function test_digest_alerts_each_lead_tier_crossing_once_and_rearms_on_activity(): void
    {
        ['stale' => $stale] = $this->scenario();
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        CurrentClinic::forget();
        $service = app(LeadStaleService::class);

        // Day 0: "Parada Antiga" (5 dias) crossed 3+ → 2 recipients (admin + recepção), not stock.
        $this->assertSame(2, $service->runDailyDigest());
        $rows = DB::table('notifications')->get();
        $this->assertEqualsCanonicalizing(
            [$this->admin->id, $this->reception->id],
            $rows->pluck('notifiable_id')->map(fn ($id) => (int) $id)->all(),
        );
        $data = json_decode($rows->first()->data, true);
        $this->assertSame(StaleLeadsDigestNotification::TYPE, $data['type']);
        $this->assertSame(1, $data['count']);
        $this->assertSame('1 lead passou de um prazo de acompanhamento sem contato: 1 em 3+ dias.', $data['message']);
        $this->assertSame(0, $service->runDailyDigest(), 'same day: nothing new');

        // Next day: "Parada Antiga" is still in 3+ (already alerted); only "Com Comentário" crosses 3+ now.
        $this->travel(25)->hours();
        $this->assertSame(2, $service->runDailyDigest());
        $this->assertSame(1, json_decode(DB::table('notifications')->orderByDesc('created_at')->first()->data, true)['count']);

        // Two days later it reaches 7+ → new crossing.
        $this->travel(2)->days();
        $this->assertSame(2, $service->runDailyDigest());
        $this->assertDatabaseHas('lead_follow_up_alerts', ['lead_id' => $stale->id, 'days' => 7]);

        // New activity re-arms: after 3 more quiet days it crosses 3+ again.
        LeadActivity::query()->create([
            'clinic_id' => $this->clinic->id,
            'lead_id' => $stale->id,
            'type' => LeadActivity::TYPE_COMMENT,
            'user_id' => $this->reception->id,
            'body' => 'Liguei, vai pensar.',
        ]);
        $this->travel(1)->days();
        $this->assertSame(0, $service->runDailyDigest());
        $this->travel(3)->days();
        $before = DB::table('notifications')->count();
        $this->assertSame(2, $service->runDailyDigest());
        $last = json_decode(DB::table('notifications')->orderByDesc('created_at')->first()->data, true);
        $this->assertSame(3, $last['groups'][0]['days']);
        $this->assertSame($before + 2, DB::table('notifications')->count());
    }

    public function test_no_digest_when_nothing_is_parado(): void
    {
        $this->lead('Recente', 1);
        CurrentClinic::forget();

        $this->assertSame(0, app(LeadStaleService::class)->runDailyDigest());
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
