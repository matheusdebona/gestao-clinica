<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\CheckLowStockProductsJob;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\Treatment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\AppointmentStockWarningNotification;
use App\Notifications\LowStockProductNotification;
use App\Notifications\ProjectedLowStockNotification;
use App\Services\StockAlertService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsLowStockTest extends TestCase
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
        $this->client = Client::factory()->forClinic($this->clinic)->create();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function makeProduct(
        string $name,
        string $stock,
        string $minStock = '5.0000',
        ?Clinic $clinic = null,
    ): Product {
        $clinic ??= $this->clinic;
        CurrentClinic::setId($clinic->id);

        return Product::factory()->forClinic($clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $clinic->id])->id,
            'cost' => '10.0000',
            'sale_price' => '100.00',
            'min_sale_price' => '100.00',
            'stock_quantity' => $stock,
            'min_stock' => $minStock,
            'is_active' => true,
            'sku' => fake()->unique()->bothify('N-####'),
        ]);
    }

    protected function createConfirmedSale(Product $product, float $qty = 3, ?User $as = null): Sale
    {
        $as ??= $this->admin;
        Sanctum::actingAs($as);
        CurrentClinic::setId($product->clinic_id);

        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => (float) $product->sale_price,
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

        return Sale::query()->findOrFail($saleId);
    }

    public function test_daily_job_notifies_already_low_stock_products(): void
    {
        Notification::fake();

        $product = $this->makeProduct('Ácido', '3.0000', '5.0000');
        $this->makeProduct('Ok', '20.0000', '5.0000');

        (new CheckLowStockProductsJob)->handle(app(StockAlertService::class));

        Notification::assertSentTo($this->admin, LowStockProductNotification::class, function ($n) use ($product) {
            return $n->product->is($product);
        });
    }

    public function test_daily_job_isolates_clinics(): void
    {
        Notification::fake();

        $otherClinic = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($otherClinic)->create();
        $otherAdmin->assignRole('admin');

        $this->makeProduct('Baixo A', '1.0000', '5.0000', $this->clinic);
        $this->makeProduct('Baixo B', '1.0000', '5.0000', $otherClinic);

        (new CheckLowStockProductsJob)->handle(app(StockAlertService::class));

        Notification::assertSentTo($this->admin, LowStockProductNotification::class, function ($n) {
            return $n->product->clinic_id === $this->clinic->id;
        });
        Notification::assertSentTo($otherAdmin, LowStockProductNotification::class, function ($n) use ($otherClinic) {
            return $n->product->clinic_id === $otherClinic->id;
        });
        Notification::assertNotSentTo($this->admin, LowStockProductNotification::class, function ($n) use ($otherClinic) {
            return $n->product->clinic_id === $otherClinic->id;
        });
    }

    public function test_daily_job_notifies_projected_low_stock_from_todays_appointments(): void
    {
        $day = CarbonImmutable::parse('2026-09-05 10:00:00');
        $this->travelTo($day);

        $product = $this->makeProduct('Botox', '10.0000', '5.0000');
        $sale = $this->createConfirmedSale($product, 6);

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/treatments/{$treatmentId}/appointments", [
            'scheduled_at' => $day->setTime(14, 0)->toIso8601String(),
        ])->assertCreated();

        Notification::fake();

        (new CheckLowStockProductsJob($day->toDateString()))
            ->handle(app(StockAlertService::class));

        Notification::assertSentTo($this->admin, ProjectedLowStockNotification::class, function ($n) use ($product) {
            return $n->product->is($product)
                && $n->plannedQuantity === '6.0000'
                && $n->projectedQuantity === '4.0000';
        });
        Notification::assertNotSentTo($this->admin, LowStockProductNotification::class);
    }

    public function test_schedule_appointment_returns_warnings_and_notifies(): void
    {
        Notification::fake();

        $product = $this->makeProduct('Preenchimento', '2.0000', '5.0000');
        $sale = $this->createConfirmedSale($product, 3);

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()
            ->json('data.id');

        $response = $this->postJson("/api/v1/treatments/{$treatmentId}/appointments", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.warnings'));
        $this->assertSame(Treatment::STATUS_OPEN, Treatment::query()->find($treatmentId)?->status);

        Notification::assertSentTo($this->admin, AppointmentStockWarningNotification::class);
    }

    public function test_user_without_products_view_does_not_receive_low_stock(): void
    {
        Notification::fake();

        $viewer = User::factory()->forClinic($this->clinic)->create(['is_active' => true]);
        $viewer->givePermissionTo('clients.view');

        $this->makeProduct('Baixo', '1.0000', '5.0000');

        (new CheckLowStockProductsJob)->handle(app(StockAlertService::class));

        Notification::assertSentTo($this->admin, LowStockProductNotification::class);
        Notification::assertNotSentTo($viewer, LowStockProductNotification::class);
    }

    public function test_inbox_list_and_mark_read(): void
    {
        $product = $this->makeProduct('Baixo', '1.0000', '5.0000');
        $this->admin->notify(new LowStockProductNotification($product));

        Sanctum::actingAs($this->admin);

        $list = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.type', 'low_stock');

        $id = $list->json('data.0.id');
        $this->assertNotNull($id);

        $marked = $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk();

        $this->assertNotNull($marked->json('data.read_at'));

        $this->admin->notify(new LowStockProductNotification($product));

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $this->admin->fresh()->unreadNotifications()->count());
    }

    public function test_inbox_does_not_expose_other_users_notifications(): void
    {
        $product = $this->makeProduct('Baixo', '1.0000', '5.0000');
        $other = User::factory()->forClinic($this->clinic)->create();
        $other->assignRole('admin');
        $other->notify(new LowStockProductNotification($product));

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $foreignId = $other->notifications()->firstOrFail()->id;
        $this->postJson("/api/v1/notifications/{$foreignId}/read")
            ->assertNotFound();
    }
}
