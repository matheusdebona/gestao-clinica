<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Protocol;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vendas por produto: unidades, faturamento (com descontos do item e da venda), dias de São Paulo,
 * só vendas confirmadas e conciliação com o faturamento das métricas comerciais.
 */
class ProductSalesMetricsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/metrics/product-sales?from=2026-10-01&to=2026-10-31';

    protected Clinic $clinic;

    protected User $admin;

    protected Client $client;

    protected ProductType $toxina;

    protected ProductType $preenchedor;

    protected Product $botox;

    protected Product $acido;

    protected Protocol $protocol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        Sanctum::actingAs($this->admin);
        $this->client = Client::factory()->forClinic($this->clinic)->create();
        $this->toxina = ProductType::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Toxina']);
        $this->preenchedor = ProductType::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Preenchedor']);
        $this->botox = $this->product('Botox 100U', $this->toxina, 'U');
        $this->acido = $this->product('Ácido hialurônico', $this->preenchedor, 'ml');
        $this->protocol = Protocol::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Full face']);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function product(string $name, ProductType $type, string $symbol): Product
    {
        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => $type->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id, 'symbol' => $symbol])->id,
            'sku' => fake()->unique()->bothify('PS-####'),
        ]);
    }

    /**
     * @param  list<array{0: Product, 1: string, 2: string, 3: string, 4?: Protocol|null}>  $lines  [produto, qtd, total da linha, total de tabela, protocolo]
     */
    private function sale(string $soldAt, string $effective, array $lines = [], string $status = Sale::STATUS_CONFIRMED): Sale
    {
        $sale = Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id, 'sold_by_user_id' => $this->admin->id, 'sold_at' => $soldAt,
            'status' => $status, 'expected_amount' => $effective, 'effective_amount' => $effective,
        ]);
        foreach ($lines as $line) {
            [$product, $qty, $total, $list] = $line;
            SaleItem::factory()->create([
                'sale_id' => $sale->id, 'product_id' => $product->id, 'product_name' => $product->name,
                'source_protocol_id' => ($line[4] ?? null)?->id,
                'quantity' => $qty, 'unit_price' => (float) $total / (float) $qty, 'line_total' => $total,
                'list_unit_price' => (float) $list / (float) $qty, 'list_line_total' => $list,
                'unit_cost' => '10.0000', 'min_unit_price' => '1.00',
            ]);
        }

        return $sale;
    }

    private function seedOctober(): void
    {
        // Desconto no item (tabela 1.200 → 1.000) e desconto de 10% na venda (2.000 → 1.800).
        $this->sale('2026-10-10 15:00:00', '1800.00', [
            [$this->botox, '2', '1000.00', '1200.00'],
            [$this->acido, '1', '1000.00', '1000.00', $this->protocol],
        ]);
        // 31/10 23:30 em São Paulo (01/11 02:30 UTC) → outubro.
        $this->sale('2026-11-01 02:30:00', '600.00', [[$this->botox, '1', '600.00', '600.00']]);
        // 01/11 00:00 em São Paulo → novembro, fora.
        $this->sale('2026-11-01 03:00:00', '9999.00', [[$this->botox, '5', '9999.00', '9999.00']]);
        // 30/09 23:30 em São Paulo → setembro, fora.
        $this->sale('2026-10-01 02:30:00', '777.00', [[$this->acido, '1', '777.00', '777.00']]);
        // Não confirmadas: fora.
        $this->sale('2026-10-12 12:00:00', '5000.00', [[$this->botox, '10', '5000.00', '5000.00']], Sale::STATUS_DRAFT);
        $this->sale('2026-10-12 12:00:00', '5000.00', [[$this->acido, '10', '5000.00', '5000.00']], Sale::STATUS_CANCELLED);
        // Confirmada sem itens: só no total (sem produto).
        $this->sale('2026-10-20 12:00:00', '300.00');
    }

    public function test_units_revenue_with_discounts_shares_and_order(): void
    {
        $this->seedOctober();
        $response = $this->getJson(self::URL)->assertOk();
        $products = collect($response->json('data.products'));

        $this->assertSame(['Botox 100U', 'Ácido hialurônico'], $products->pluck('product_name')->all());
        $botox = $products[0];
        $this->assertSame('3.0000', $botox['units']);
        $this->assertSame('1500.00', $botox['revenue']); // 900 (1.000 × 0,9) + 600
        $this->assertSame(2, $botox['sales_count']);
        $this->assertSame('500.00', $botox['avg_unit_price']);
        $this->assertSame('55.56', $botox['share_percent']); // de 2.700
        $this->assertSame('U', $botox['unit_symbol']);
        $this->assertSame('Toxina', $botox['product_type_name']);
        $this->assertSame('1800.00', $botox['list_total']);
        $this->assertSame('300.00', $botox['discount_total']);
        $this->assertSame('0.00', $botox['protocol_revenue']);

        $acido = $products[1];
        $this->assertSame(['1.0000', '900.00', 1, '900.00', '33.33', '900.00'],
            [$acido['units'], $acido['revenue'], $acido['sales_count'], $acido['avg_unit_price'], $acido['share_percent'], $acido['protocol_revenue']]);

        $response->assertJsonPath('data.totals.units', '4.0000')
            ->assertJsonPath('data.totals.revenue', '2400.00')
            ->assertJsonPath('data.totals.unallocated_revenue', '300.00')
            ->assertJsonPath('data.totals.period_revenue', '2700.00')
            ->assertJsonPath('data.totals.sales_count', 3)
            ->assertJsonPath('data.totals.products_count', 2);

        // Por protocolo: as mesmas linhas pela origem.
        $protocols = collect($response->json('data.protocols'))->map(fn ($p) => [$p['protocol_name'], $p['revenue'], $p['sales_count']])->all();
        $this->assertSame([[null, '1500.00', 2], ['Full face', '900.00', 1]], $protocols);

        // Opções dos filtros: tipos da clínica e produtos vendidos no período.
        $this->assertSame(['Preenchedor', 'Toxina'], collect($response->json('data.options.product_types'))->pluck('name')->all());
        $this->assertSame(['Botox 100U', 'Ácido hialurônico'], collect($response->json('data.options.products'))->pluck('name')->sort()->values()->all());
    }

    public function test_total_reconciles_with_commercial_revenue(): void
    {
        $this->seedOctober();
        $commercial = $this->getJson('/api/v1/metrics/commercial?from=2026-10-01&to=2026-10-31')->assertOk()->json('data.kpis.revenue');
        $totals = $this->getJson(self::URL)->assertOk()->json('data.totals');
        $this->assertSame((float) $commercial, (float) $totals['period_revenue']);
        $this->assertSame((float) $commercial, round((float) $totals['revenue'] + (float) $totals['unallocated_revenue'], 2));

        // Só o dia 31/10 (dia de São Paulo).
        $day = $this->getJson('/api/v1/metrics/product-sales?from=2026-10-31&to=2026-10-31')->assertOk();
        $day->assertJsonPath('data.totals.revenue', '600.00')->assertJsonPath('data.products.0.units', '1.0000');
    }

    public function test_filters_by_product_type_and_product(): void
    {
        $this->seedOctober();
        $byType = $this->getJson(self::URL.'&product_type_id='.$this->preenchedor->id)->assertOk();
        $this->assertSame(['Ácido hialurônico'], collect($byType->json('data.products'))->pluck('product_name')->all());
        $byType->assertJsonPath('data.totals.revenue', '900.00')
            ->assertJsonPath('data.totals.sales_count', 1)
            ->assertJsonPath('data.totals.unallocated_revenue', '0.00')
            ->assertJsonPath('data.totals.share_percent', '33.33')
            ->assertJsonPath('data.protocols', []);

        $byProduct = $this->getJson(self::URL.'&product_id='.$this->botox->id)->assertOk();
        $this->assertSame(['Botox 100U'], collect($byProduct->json('data.products'))->pluck('product_name')->all());
        $byProduct->assertJsonPath('data.totals.units', '3.0000')->assertJsonPath('data.totals.sales_count', 2);

        // Produto/tipo de outra clínica: inválido.
        $other = Clinic::factory()->create();
        $foreignType = ProductType::factory()->create(['clinic_id' => $other->id]);
        $this->getJson(self::URL.'&product_type_id='.$foreignType->id)->assertUnprocessable()->assertJsonValidationErrors('product_type_id');
        $this->getJson('/api/v1/metrics/product-sales?from=2026-10-31&to=2026-10-01')->assertUnprocessable();
    }

    public function test_requires_metrics_and_finance_permissions_and_isolates_clinics(): void
    {
        $this->seedOctober();
        $metricsOnly = User::factory()->forClinic($this->clinic)->create();
        $metricsOnly->givePermissionTo('metrics.view');
        Sanctum::actingAs($metricsOnly);
        $this->getJson(self::URL)->assertForbidden();
        $this->getJson('/api/v1/metrics/commercial?from=2026-10-01&to=2026-10-31')->assertOk();

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.products', [])->assertJsonPath('data.totals.period_revenue', '0.00');
    }
}
