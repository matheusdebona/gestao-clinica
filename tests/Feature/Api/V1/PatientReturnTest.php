<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PatientReturn;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AppointmentService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientReturnTest extends TestCase
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
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        $this->client = Client::factory()->forClinic($this->clinic)->create(['name' => 'Camila Souza']);
        Carbon::setTestNow('2026-10-10 15:00:00');
        CarbonImmutable::setTestNow('2026-10-10 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function protocol(string $name, ?int $returnDays): int
    {
        $product = Product::factory()->forClinic($this->clinic)->create([
            'name' => $name.' insumo',
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '10.0000', 'sale_price' => '100.00', 'min_sale_price' => '100.00', 'stock_quantity' => '100.0000',
            'sku' => fake()->unique()->bothify('R-####'),
        ]);

        return $this->postJson('/api/v1/protocols', array_filter([
            'name' => $name,
            'return_after_days' => $returnDays,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], fn ($v) => $v !== null))->assertCreated()
            ->assertJsonPath('data.return_after_days', $returnDays)
            ->json('data.id');
    }

    /** @param list<int> $protocolIds */
    private function confirmedSale(array $protocolIds): Sale
    {
        $saleId = $this->postJson('/api/v1/sales', ['client_id' => $this->client->id])->assertCreated()->json('data.id');
        foreach ($protocolIds as $protocolId) {
            $this->postJson("/api/v1/sales/{$saleId}/apply-protocol", ['protocol_id' => $protocolId])->assertOk();
        }
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_'.fake()->unique()->numerify('###'), 'kind' => PaymentMethod::KIND_PIX,
        ]);
        $effective = $this->getJson("/api/v1/sales/{$saleId}")->json('data.effective_amount');
        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [['payment_method_id' => $pix->id, 'amount' => (float) $effective]],
        ])->assertOk();
        $this->acceptBudgetForSale($saleId);
        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        return Sale::query()->findOrFail($saleId);
    }

    public function test_confirmed_sale_creates_returns_from_protocols_and_the_list_shows_due_ones(): void
    {
        Sanctum::actingAs($this->admin);
        $toxina = $this->protocol('Toxina', 5);
        $peeling = $this->protocol('Peeling', 30);
        $semRetorno = $this->protocol('Limpeza', null);

        $sale = $this->confirmedSale([$toxina, $peeling, $semRetorno]);

        $returns = PatientReturn::query()->where('sale_id', $sale->id)->orderBy('due_on')->get();
        $this->assertSame(['Toxina', 'Peeling'], $returns->pluck('label')->all());
        $this->assertSame(['2026-10-15', '2026-11-09'], $returns->map(fn ($r) => $r->due_on->format('Y-m-d'))->all());

        // "Vence em breve": within 7 days (Toxina in 5); Peeling only in "upcoming".
        $this->getJson('/api/v1/patient-returns')->assertOk()
            ->assertJsonPath('meta.counts.due', 1)
            ->assertJsonPath('data.0.label', 'Toxina')
            ->assertJsonPath('data.0.days_until_due', 5)
            ->assertJsonPath('data.0.client.name', 'Camila Souza');
        $this->getJson('/api/v1/patient-returns?filter=upcoming')->assertOk()->assertJsonCount(2, 'data');

        // Later: overdue.
        Carbon::setTestNow('2026-10-20 15:00:00');
        CarbonImmutable::setTestNow('2026-10-20 15:00:00');
        $this->getJson('/api/v1/patient-returns?filter=overdue')->assertOk()
            ->assertJsonPath('meta.counts.overdue', 1)
            ->assertJsonPath('data.0.days_until_due', -5);
    }

    public function test_return_is_done_on_new_appointment_new_sale_or_manually_and_cancelled_with_the_sale(): void
    {
        Sanctum::actingAs($this->admin);
        $toxina = $this->protocol('Toxina', 20);
        $sale = $this->confirmedSale([$toxina]);
        $return = PatientReturn::query()->where('sale_id', $sale->id)->firstOrFail();

        // A session of the same sale's treatment does not count as the return.
        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")->assertCreated()->json('data.id');
        $this->postJson("/api/v1/treatments/{$treatmentId}/appointments", [
            'professional_user_id' => $this->admin->id, 'scheduled_at' => '2026-10-12T13:00:00Z',
        ])->assertCreated();
        $this->assertTrue($return->fresh()->isPending());

        // A new appointment outside that treatment (e.g. an avaliação) closes it.
        app(AppointmentService::class)->scheduleEvaluation($this->client, [
            'professional_user_id' => $this->admin->id, 'scheduled_at' => '2026-10-25 13:00:00',
        ]);
        $this->assertSame('appointment', $return->fresh()->done_reason);

        // New sale closes the previous pending return; manual done works; cancelling cancels.
        $second = $this->confirmedSale([$toxina]);
        $secondReturn = PatientReturn::query()->where('sale_id', $second->id)->firstOrFail();
        $third = $this->confirmedSale([$toxina]);
        $this->assertSame('sale', $secondReturn->fresh()->done_reason);
        $thirdReturn = PatientReturn::query()->where('sale_id', $third->id)->firstOrFail();

        $this->postJson("/api/v1/patient-returns/{$thirdReturn->id}/whatsapp")->assertOk();
        $this->assertNotNull($thirdReturn->fresh()->whatsapp_sent_at);
        $this->postJson("/api/v1/patient-returns/{$thirdReturn->id}/done")->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertSame('manual', $thirdReturn->fresh()->done_reason);
        $this->assertSame($this->admin->id, $thirdReturn->fresh()->done_by_user_id);

        $fourth = $this->confirmedSale([$toxina]);
        $this->postJson("/api/v1/sales/{$fourth->id}/cancel")->assertOk();
        $this->assertSame('cancelled', PatientReturn::query()->where('sale_id', $fourth->id)->value('status'));
    }

    public function test_completing_the_treatment_re_dates_the_return(): void
    {
        Sanctum::actingAs($this->admin);
        $sale = $this->confirmedSale([$this->protocol('Bioestimulador', 60)]);
        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-30 15:00:00');
        CarbonImmutable::setTestNow('2026-10-30 15:00:00');
        $this->postJson("/api/v1/treatments/{$treatmentId}/complete")->assertOk();

        $return = PatientReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('2026-12-29', $return->due_on->format('Y-m-d'));
        $this->assertSame($treatmentId, $return->treatment_id);
    }

    public function test_isolation_permissions_and_daily_queue(): void
    {
        Sanctum::actingAs($this->admin);
        $sale = $this->confirmedSale([$this->protocol('Toxina', 3)]);
        $return = PatientReturn::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->getJson('/api/v1/leads/daily-analysis')->assertOk()
            ->assertJsonPath('data.follow_up.returns.count', 1)
            ->assertJsonPath('data.follow_up.returns.items.0.label', 'Toxina');

        $other = Clinic::factory()->create();
        $foreign = User::factory()->forClinic($other)->create();
        $foreign->assignRole('admin');
        Sanctum::actingAs($foreign);
        CurrentClinic::forget();
        $this->getJson('/api/v1/patient-returns')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/patient-returns/{$return->id}/done")->assertNotFound();

        foreach (['stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/patient-returns')->assertForbidden();
        }
    }
}
