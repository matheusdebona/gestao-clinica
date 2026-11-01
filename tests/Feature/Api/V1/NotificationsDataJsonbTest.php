<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\CheckLowStockProductsJob;
use App\Models\Brand;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\LowStockProductNotification;
use App\Services\StockAlertService;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão: no Postgres `notifications.data` era `text` e todo filtro `data->...`
 * (dedupe dos avisos diários e filtro por categoria) quebrava com
 * "operator does not exist: text ->> unknown". Aqui os avisos são gravados de verdade
 * (sem Notification::fake), então a consulta de dedupe roda contra linhas reais.
 */
class NotificationsDataJsonbTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function lowStockProduct(string $name): Product
    {
        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '10.0000', 'sale_price' => '100.00', 'min_sale_price' => '100.00',
            'stock_quantity' => '1.0000', 'min_stock' => '5.0000', 'is_active' => true,
            'sku' => fake()->unique()->bothify('J-####'),
        ]);
    }

    private function runLowStock(): void
    {
        (new CheckLowStockProductsJob)->handle(app(StockAlertService::class));
    }

    private function lowStockRows(): int
    {
        return DB::table('notifications')->where('notifiable_id', $this->admin->id)->where('data->type', 'low_stock')->count();
    }

    public function test_daily_dedupe_queries_real_rows_and_sends_once_per_day(): void
    {
        $a = $this->lowStockProduct('Ácido');
        $this->lowStockProduct('Toxina');

        $this->runLowStock();
        $this->assertSame(2, $this->lowStockRows());

        // Segunda rodada no mesmo dia: a consulta `data->type` / `data->product_id` acha os avisos e não repete.
        $this->runLowStock();
        $this->assertSame(2, $this->lowStockRows());

        // Lido o aviso do Ácido, ele volta na próxima rodada (só o dele).
        $this->admin->notifications->first(fn ($n) => (int) $n->data['product_id'] === $a->id)->markAsRead();
        $this->runLowStock();
        $this->assertSame(3, $this->lowStockRows());
        $this->assertSame(2, DB::table('notifications')->where('data->product_id', $a->id)->count());
    }

    public function test_inbox_category_filter_works_on_real_rows(): void
    {
        $this->admin->notify(new LowStockProductNotification($this->lowStockProduct('Baixo')));

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/notifications?category=stock')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/notifications?category=leads')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_postgres_column_is_jsonb_and_migration_converts_existing_text_rows(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Só no Postgres (no SQLite o JSON é texto e já funciona).');
        }

        $type = fn () => DB::selectOne("select data_type from information_schema.columns where table_schema = current_schema() and table_name = 'notifications' and column_name = 'data'")->data_type;
        $this->assertSame('jsonb', $type());

        // Volta para o esquema antigo (text) com linhas, como estava em produção.
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        $rows = [
            json_encode(['type' => 'low_stock', 'product_id' => 7, 'title' => 'Estoque baixo: Ácido'], JSON_UNESCAPED_UNICODE),
            json_encode(['type' => 'stale_leads', 'count' => 3]),
            '',
        ];
        foreach ($rows as $data) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\X',
                'notifiable_type' => $this->admin->getMorphClass(), 'notifiable_id' => $this->admin->id,
                'data' => $data, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_11_01_140000_notifications_data_jsonb.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertSame('jsonb', $type());
        $this->assertSame(1, DB::table('notifications')->where('data->type', 'low_stock')->where('data->product_id', 7)->count());
        $this->assertSame('Estoque baixo: Ácido', DB::table('notifications')->where('data->type', 'low_stock')->value('data->title'));
        $this->assertSame(1, DB::table('notifications')->whereRaw("data = '{}'::jsonb")->count());
        $this->assertSame(3, DB::table('notifications')->count());

        $migration->down();
        $this->assertSame('text', $type());
        $migration->up();
        $this->assertSame('jsonb', $type());
    }
}
