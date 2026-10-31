<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadService;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadMilestonesTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $reception;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->reception = User::factory()->forClinic($this->clinic)->create(['name' => 'Rita Recepção']);
        $this->reception->assignRole('receptionist');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bruno Vendas']);
        $this->seller->assignRole('seller');
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Lead Teste',
            'phone' => '11987650000',
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
        ], $extra);
    }

    public function test_creation_only_counts_as_approached_when_ticked(): void
    {
        Sanctum::actingAs($this->reception);
        $id = $this->postJson('/api/v1/leads', $this->payload())->assertCreated()->json('data.id');
        $this->assertNull(Lead::query()->find($id)->approached_at);

        $id2 = $this->postJson('/api/v1/leads', $this->payload(['phone' => '11987650001', 'already_approached' => true]))
            ->assertCreated()->json('data.id');
        $lead = Lead::query()->find($id2);
        $this->assertNotNull($lead->approached_at);
        $this->assertSame($this->reception->id, $lead->approached_by_user_id);
    }

    public function test_milestones_are_set_once_attributed_and_logged(): void
    {
        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
            'lead_status_id' => LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id'),
        ]);

        // First WhatsApp click = abordado by that user.
        Sanctum::actingAs($this->seller);
        $this->postJson("/api/v1/leads/{$lead->id}/whatsapp")->assertCreated();
        $this->assertSame($this->seller->id, $lead->fresh()->approached_by_user_id);

        // Someone else marking it later doesn't overwrite it.
        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/approached")->assertOk()
            ->assertJsonPath('data.approached_by.name', 'Bruno Vendas');
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/replied")->assertOk()
            ->assertJsonPath('data.replied_by.name', 'Rita Recepção');
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/replied")->assertOk();
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/booked")->assertNotFound();

        $types = $lead->activities()->pluck('type')->all();
        $this->assertSame(1, count(array_keys($types, 'approached')));
        $this->assertSame(1, count(array_keys($types, 'replied')));
    }

    public function test_replied_implies_approached_and_cancelled_is_blocked(): void
    {
        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
            'lead_status_id' => LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id'),
        ]);

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/replied")->assertOk()
            ->assertJsonPath('data.approached_by.name', 'Rita Recepção');

        $this->postJson("/api/v1/leads/{$lead->id}/cancel")->assertOk();
        $other = Lead::factory()->forClinic($this->clinic)->create([
            'phone' => '11911112222',
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
            'lead_status_id' => LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id'),
            'cancelled_at' => now(),
        ]);
        $this->postJson("/api/v1/leads/{$other->id}/milestones/approached")->assertUnprocessable();

        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);
        $this->postJson("/api/v1/leads/{$lead->id}/milestones/approached")->assertForbidden();
    }

    public function test_metrics_funnel_by_origin_and_user(): void
    {
        $instagram = ClientOrigin::query()->where('clinic_id', $this->clinic->id)->where('name', 'Instagram')->value('id');
        $status = LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id');
        $service = app(LeadService::class);
        $leads = collect(range(1, 10))->map(fn (int $i) => Lead::factory()->forClinic($this->clinic)->create([
            'phone' => '119800000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'client_origin_id' => $instagram,
            'lead_status_id' => $status,
        ]));
        $leads->each(fn (Lead $lead) => $service->markMilestone($lead, 'approached', $this->seller));
        $leads->take(5)->each(fn (Lead $lead) => $service->markMilestone($lead, 'replied', $this->seller));
        $service->markMilestone($leads->first(), 'booked', $this->seller);

        $admin = User::factory()->forClinic($this->clinic)->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
        $data = $this->getJson('/api/v1/metrics/leads?from='.now()->subDay()->toDateString().'&to='.now()->toDateString())
            ->assertOk()->json('data.funnel');

        $this->assertSame(10, $data['total']['approached']);
        $this->assertSame(5, $data['total']['replied']);
        $this->assertSame(1, $data['total']['booked']);
        $this->assertSame('50.00', $data['total']['reply_rate']);
        $this->assertSame('20.00', $data['total']['booking_rate']);
        $this->assertSame('10.00', $data['total']['total_rate']);
        $this->assertSame('Instagram', $data['by_origin'][0]['label']);
        $this->assertSame('Bruno Vendas', $data['by_user'][0]['label']);
    }
}
