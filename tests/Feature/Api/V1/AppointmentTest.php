<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        $this->client = Client::factory()->forClinic($this->clinic)->create([
            'service_duration_minutes' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function makeProduct(): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => 'Botox',
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '10.0000',
            'sale_price' => '100.00',
            'min_sale_price' => '100.00',
            'stock_quantity' => '10.0000',
            'sku' => fake()->unique()->bothify('A-####'),
        ]);
    }

    protected function openTreatment(?User $as = null): array
    {
        Sanctum::actingAs($as ?? $this->admin);
        CurrentClinic::setId($this->clinic->id);

        $product = $this->makeProduct();

        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertOk();

        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_'.fake()->unique()->numerify('###'),
            'kind' => PaymentMethod::KIND_PIX,
        ]);

        $effective = $this->getJson("/api/v1/sales/{$saleId}")->json('data.effective_amount');

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => (float) $effective],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $treatmentId = $this->postJson("/api/v1/sales/{$saleId}/treatments")
            ->assertCreated()
            ->json('data.id');

        return [
            'treatment_id' => $treatmentId,
            'product' => $product,
            'sale' => Sale::query()->findOrFail($saleId),
        ];
    }

    protected function makeProfessional(): User
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        $user->assignRole('professional');

        return $user;
    }

    public function test_index_filters_by_window_status_professional_and_client(): void
    {
        $proA = $this->makeProfessional();
        $proB = $this->makeProfessional();
        $opened = $this->openTreatment();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $proA->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $proB->id,
            'scheduled_at' => '2026-09-10T10:00:00Z',
        ])->assertCreated();

        $this->getJson('/api/v1/appointments?from=2026-09-08&to=2026-09-08')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professional_user_id', $proA->id)
            ->assertJsonPath('data.0.client.id', $this->client->id)
            ->assertJsonPath('data.0.treatment.id', $opened['treatment_id']);

        $this->getJson('/api/v1/appointments?from=2026-09-08&to=2026-09-11&professional_user_id='.$proB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professional_user_id', $proB->id);

        $this->getJson('/api/v1/appointments?from=2026-09-08&to=2026-09-11&client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/appointments?from=2026-09-08&to=2026-09-11&status=scheduled')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/appointments?from=2026-09-08&to=2026-09-11&status=cancelled')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_create_requires_professional(): void
    {
        $opened = $this->openTreatment();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['professional_user_id']);
    }

    public function test_overlap_same_professional_is_rejected(): void
    {
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:30:00Z',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_adjacent_slots_and_other_professional_are_allowed(): void
    {
        $proA = $this->makeProfessional();
        $proB = $this->makeProfessional();
        $opened = $this->openTreatment();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $proA->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
            'duration_minutes' => 60,
        ])->assertCreated();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $proA->id,
            'scheduled_at' => '2026-09-08T11:00:00Z',
        ])->assertCreated();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $proB->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated();
    }

    public function test_cancelled_slot_does_not_block_overlap(): void
    {
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();

        $id = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/appointments/{$id}/cancel")->assertOk();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated();
    }

    public function test_reschedule_rejects_overlap_and_does_not_change_stock(): void
    {
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();
        $stock = $opened['product']->fresh()->stock_quantity;

        $first = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])->assertCreated()->json('data.id');

        $second = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T14:00:00Z',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/appointments/{$second}", [
            'scheduled_at' => '2026-09-08T10:15:00Z',
        ])->assertStatus(422);

        $this->patchJson("/api/v1/appointments/{$second}", [
            'scheduled_at' => '2026-09-08T16:00:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertSame($stock, $opened['product']->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertNotNull(Appointment::query()->find($first));
    }

    public function test_start_does_not_change_stock(): void
    {
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();
        $stock = $opened['product']->fresh()->stock_quantity;

        $id = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/appointments/{$id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertSame($stock, $opened['product']->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_index_and_actions_are_permission_gated(): void
    {
        $opened = $this->openTreatment();
        $pro = $this->makeProfessional();

        $bare = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($bare);
        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/appointments')->assertForbidden();
        $this->getJson('/api/v1/professionals')->assertForbidden();

        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('treatments.view');
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/appointments')->assertForbidden();

        $receptionist = User::factory()->forClinic($this->clinic)->create();
        $receptionist->assignRole('receptionist');
        Sanctum::actingAs($receptionist);
        CurrentClinic::setId($this->clinic->id);

        $this->getJson('/api/v1/appointments')->assertOk();
        $this->getJson('/api/v1/professionals')
            ->assertOk()
            ->assertJsonFragment(['id' => $pro->id]);

        $id = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/appointments/{$id}", [
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
        ])->assertOk();

        $this->postJson("/api/v1/appointments/{$id}/start")->assertOk();
        $this->postJson("/api/v1/appointments/{$id}/complete")->assertForbidden();
        $this->postJson("/api/v1/appointments/{$id}/cancel")->assertOk();
    }

    public function test_appointments_are_clinic_isolated(): void
    {
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();
        $id = $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);

        $this->getJson("/api/v1/appointments/{$id}")->assertNotFound();
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_duration_from_client_is_used_for_overlap(): void
    {
        $this->client->update(['service_duration_minutes' => 45]);
        $pro = $this->makeProfessional();
        $opened = $this->openTreatment();

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.duration_minutes', 45);

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:30:00Z',
        ])->assertStatus(422);

        $this->postJson("/api/v1/treatments/{$opened['treatment_id']}/appointments", [
            'professional_user_id' => $pro->id,
            'scheduled_at' => '2026-09-08T10:45:00Z',
        ])->assertCreated();
    }
}
