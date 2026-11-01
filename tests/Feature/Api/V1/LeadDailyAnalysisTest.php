<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadDailyAnalysisService;
use App\Services\LeadDailyReviewReminder;
use App\Services\LeadService;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultFollowUpTiers;
use App\Support\EnsureDefaultLeadStatuses;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadDailyAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected User $seller;

    protected int $instagram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        EnsureDefaultFollowUpTiers::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->reception = User::factory()->forClinic($this->clinic)->create(['name' => 'Rita Recepção']);
        $this->reception->assignRole('receptionist');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bruno Vendas']);
        $this->seller->assignRole('seller');
        $this->instagram = (int) ClientOrigin::query()->where('clinic_id', $this->clinic->id)->where('name', 'Instagram')->value('id');
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function createLeads(User $user, int $count, string $prefix): Collection
    {
        Sanctum::actingAs($user);

        return collect(range(1, $count))->map(fn (int $i) => Lead::query()->find(
            $this->postJson('/api/v1/leads', [
                'name' => "{$prefix} {$i}",
                'phone' => '11'.str_pad((string) (900000000 + crc32($prefix) % 1000 * 100 + $i), 9, '0', STR_PAD_LEFT),
                'client_origin_id' => $this->instagram,
            ])->assertCreated()->json('data.id')
        ));
    }

    public function test_user_sees_own_day_with_funnel_queue_and_suggestions(): void
    {
        $mine = $this->createLeads($this->reception, 10, 'Rita');
        $this->createLeads($this->seller, 2, 'Bruno');
        $service = app(LeadService::class);
        $mine->each(fn (Lead $lead) => $service->markMilestone($lead, 'approached', $this->reception));
        $mine->take(5)->each(fn (Lead $lead) => $service->markMilestone($lead, 'replied', $this->reception));
        $service->markMilestone($mine->first(), 'booked', $this->reception);

        Sanctum::actingAs($this->reception);
        // Non-gestão users can't see others even if they ask.
        $data = $this->getJson('/api/v1/leads/daily-analysis?user_ids='.$this->seller->id)->assertOk()->json('data');

        $this->assertFalse($data['team']);
        $this->assertSame([$this->reception->id], $data['filters']['user_ids']);
        $this->assertSame(LeadDailyAnalysisService::today(), $data['from']);
        $this->assertSame(10, $data['kpis']['registered']);
        $this->assertSame(10, $data['kpis']['approached']);
        $this->assertSame(5, $data['kpis']['replied']);
        $this->assertSame(1, $data['kpis']['booked']);
        $this->assertSame(10, $data['funnel']['total']['approached']);
        $this->assertSame('50.00', $data['funnel']['total']['reply_rate']);
        $this->assertSame('Instagram', $data['registered_by_origin'][0]['label']);
        $this->assertArrayNotHasKey('team_rows', $data);
        $this->assertSame(0, $data['previous']['kpis']['registered']);
        $texts = collect($data['suggestions'])->pluck('text')->implode(' | ');
        $this->assertStringContainsString('check-in', $texts);

        // Queue: never approached or stale. Seller's leads are not "mine".
        $this->assertSame(0, $data['follow_up']['count']);
        $this->travel(4)->days();
        $data = $this->getJson('/api/v1/leads/daily-analysis')->assertOk()->json('data');
        $this->assertSame(10, $data['follow_up']['count']);
        $this->assertSame(3, $data['follow_up']['leads'][0]['tier']['days']);
    }

    public function test_gestao_sees_everyone_with_filters_and_team_rows(): void
    {
        $this->createLeads($this->reception, 3, 'Rita');
        $bruno = $this->createLeads($this->seller, 2, 'Bruno');
        app(LeadService::class)->markMilestone($bruno->first(), 'approached', $this->seller);

        Sanctum::actingAs($this->admin);
        $data = $this->getJson('/api/v1/leads/daily-analysis')->assertOk()->json('data');
        $this->assertTrue($data['team']);
        $this->assertSame(5, $data['kpis']['registered']);
        $rows = collect($data['team_rows'])->keyBy('user.name');
        $this->assertSame(3, $rows['Rita Recepção']['registered']);
        $this->assertSame(1, $rows['Bruno Vendas']['approached']);
        $this->assertFalse($rows['Bruno Vendas']['checkin_done']);
        $this->assertGreaterThanOrEqual(3, count($data['users']));

        $data = $this->getJson('/api/v1/leads/daily-analysis?user_ids='.$this->seller->id.'&origin_ids='.$this->instagram)->assertOk()->json('data');
        $this->assertSame(2, $data['kpis']['registered']);
        $this->assertCount(1, $data['team_rows']);

        $other = (int) ClientOrigin::query()->where('clinic_id', $this->clinic->id)->where('id', '!=', $this->instagram)->value('id');
        $this->assertSame(0, $this->getJson('/api/v1/leads/daily-analysis?origin_ids='.$other)->json('data.kpis.registered'));

        $yesterday = CarbonImmutable::now('America/Sao_Paulo')->subDays(7)->toDateString();
        $this->getJson('/api/v1/leads/daily-analysis?from='.$yesterday.'&to='.LeadDailyAnalysisService::today())->assertOk()
            ->assertJsonPath('data.days', 8);
    }

    public function test_checkin_once_per_day_editable_only_today_and_history(): void
    {
        $today = LeadDailyAnalysisService::today();
        Sanctum::actingAs($this->reception);
        $this->putJson("/api/v1/leads/checkins/{$today}", ['worked' => '', 'change' => ''])->assertUnprocessable();
        $this->putJson("/api/v1/leads/checkins/{$today}", ['worked' => 'Instagram respondeu bem', 'change' => 'Ligar mais cedo'])->assertCreated();
        $this->putJson("/api/v1/leads/checkins/{$today}", ['worked' => 'Instagram respondeu muito bem', 'change' => 'Ligar mais cedo', 'notes' => 'ok'])->assertOk()
            ->assertJsonPath('data.worked', 'Instagram respondeu muito bem');
        $this->assertSame(1, DB::table('lead_daily_checkins')->count());
        $this->getJson("/api/v1/leads/checkins/{$today}")->assertOk()->assertJsonPath('editable', true);

        $yesterday = CarbonImmutable::now('America/Sao_Paulo')->subDay()->toDateString();
        $this->putJson("/api/v1/leads/checkins/{$yesterday}", ['worked' => 'x'])->assertUnprocessable();
        $this->getJson("/api/v1/leads/checkins/{$yesterday}")->assertOk()->assertJsonPath('editable', false);

        Sanctum::actingAs($this->seller);
        $this->putJson("/api/v1/leads/checkins/{$today}", ['notes' => 'Dia fraco'])->assertCreated();
        $this->getJson('/api/v1/leads/checkins')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/leads/checkins')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/leads/checkins?user_ids='.$this->reception->id)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.change', 'Ligar mais cedo');
        $rows = collect($this->getJson('/api/v1/leads/daily-analysis')->json('data.team_rows'))->keyBy('user.name');
        $this->assertTrue($rows['Rita Recepção']['checkin_done']);
        $this->assertSame('Ligar mais cedo', $rows['Rita Recepção']['checkin']['change']);
    }

    public function test_end_of_day_reminder_once_and_only_when_needed(): void
    {
        $this->createLeads($this->reception, 2, 'Rita');
        CurrentClinic::forget();
        $reminder = app(LeadDailyReviewReminder::class);

        // reception (pending + no check-in), admin and seller (no check-in) → 3; again → 0.
        $this->assertSame(3, $reminder->run());
        $this->assertSame(0, $reminder->run());
        $data = json_decode(DB::table('notifications')->where('notifiable_id', $this->reception->id)->value('data'), true);
        $this->assertSame('daily_review', $data['type']);
        $this->assertSame('2 leads esperando acompanhamento e check-in do dia não feito.', $data['message']);
    }
}
