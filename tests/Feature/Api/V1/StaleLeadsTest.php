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

    public function test_list_filter_summary_and_metrics_use_the_clinic_threshold(): void
    {
        ['stale' => $stale] = $this->scenario();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/leads?stale=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stale->id);
        $this->assertNotNull($this->getJson('/api/v1/leads')->json('data.0.last_activity_at'));

        $this->getJson('/api/v1/leads/stale-summary')->assertOk()
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.leads.0.name', 'Parada Antiga');

        $this->getJson('/api/v1/metrics/leads?from='.now()->subDays(30)->toDateString().'&to='.now()->toDateString())->assertOk()
            ->assertJsonPath('data.kpis.stale', 1)
            ->assertJsonPath('data.kpis.stale_days', 3);

        // Threshold is per clinic: 7 days → nothing is parado; 1 day → the touched one too.
        $this->putJson('/api/v1/clinic/settings', ['lead_stale_days' => 7])->assertOk()
            ->assertJsonPath('data.lead_stale_days', 7);
        $this->getJson('/api/v1/leads/stale-summary')->assertJsonPath('data.count', 0);
        $this->putJson('/api/v1/clinic/settings', ['lead_stale_days' => 1])->assertOk();
        $this->getJson('/api/v1/leads?stale=1')->assertJsonCount(2, 'data');
        $this->putJson('/api/v1/clinic/settings', ['lead_stale_days' => 0])->assertUnprocessable();
        $this->putJson('/api/v1/clinic/settings', ['lead_stale_days' => null])->assertOk()
            ->assertJsonPath('data.lead_stale_days', 3);

        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/leads/stale-summary')->assertOk();
        $this->putJson('/api/v1/clinic/settings', ['lead_stale_days' => 5])->assertForbidden();
    }

    public function test_daily_digest_is_sent_once_per_day_to_lead_managers(): void
    {
        $this->scenario();
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        CurrentClinic::forget();

        $service = app(LeadStaleService::class);
        $this->assertSame(2, $service->runDailyDigest());
        $this->assertSame(0, $service->runDailyDigest(), 'second run on the same day must not notify again');

        $rows = DB::table('notifications')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(
            [$this->admin->id, $this->reception->id],
            $rows->pluck('notifiable_id')->map(fn ($id) => (int) $id)->all(),
        );
        $data = json_decode($rows->first()->data, true);
        $this->assertSame(StaleLeadsDigestNotification::TYPE, $data['type']);
        $this->assertSame(1, $data['count']);
        $this->assertSame('1 lead está parado há 3 dias ou mais sem nenhum contato.', $data['message']);

        // Next day it is sent again (still parado).
        CarbonImmutable::setTestNow(now()->addDay());
        $this->travel(1)->days();
        $this->assertSame(2, $service->runDailyDigest());
    }

    public function test_no_digest_when_nothing_is_parado(): void
    {
        $this->lead('Recente', 1);
        CurrentClinic::forget();

        $this->assertSame(0, app(LeadStaleService::class)->runDailyDigest());
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
