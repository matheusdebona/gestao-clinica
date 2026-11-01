<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\CardBrand;
use App\Models\CardFeeRule;
use App\Models\CardOperator;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\PaymentFeeService;
use App\Services\TaxRateResolver;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFeesTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected Client $client;

    protected PaymentMethod $credit;

    protected CardOperator $operator;

    protected CardBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        $this->client = Client::factory()->forClinic($this->clinic)->create();
        $this->credit = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'credito_t', 'kind' => PaymentMethod::KIND_CREDIT_CARD, 'requires_card_meta' => true,
            'fee_percent' => null, 'fee_fixed' => null,
        ]);
        $this->operator = CardOperator::factory()->forClinic($this->clinic)->create();
        $this->brand = CardBrand::factory()->forClinic($this->clinic)->create();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function product(string $cost = '10.0000', string $price = '1000.00'): Product
    {
        return Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => $cost,
            'sale_price' => $price,
            'min_sale_price' => null,
            'stock_quantity' => '50.0000',
            'sku' => fake()->unique()->bothify('F-####'),
        ]);
    }

    protected function draftWithCardPayment(int $installments = 3): int
    {
        $product = $this->product();
        $saleId = $this->postJson('/api/v1/sales', ['client_id' => $this->client->id])->json('data.id');
        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();
        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [[
                'payment_method_id' => $this->credit->id,
                'amount' => 1000,
                'card_operator_id' => $this->operator->id,
                'card_brand_id' => $this->brand->id,
                'installments' => $installments,
            ]],
        ])->assertOk();

        return (int) $saleId;
    }

    public function test_card_rule_fee_and_anticipation_are_recorded_on_payment(): void
    {
        Sanctum::actingAs($this->admin);
        CardFeeRule::query()->create([
            'clinic_id' => $this->clinic->id, 'payment_method_id' => $this->credit->id,
            'card_operator_id' => $this->operator->id, 'card_brand_id' => $this->brand->id,
            'installments' => 3, 'fee_percent' => '4.0000', 'fee_fixed' => '0.50',
        ]);
        $this->putJson("/api/v1/finance/card-operators/{$this->operator->id}/anticipation", [
            'auto_anticipate' => true, 'anticipation_rate_percent' => 2,
        ])->assertOk()->assertJsonPath('data.anticipation_rate_percent', '2.0000');

        $saleId = $this->draftWithCardPayment(3);

        $payment = SalePayment::query()->where('sale_id', $saleId)->sole();
        $this->assertSame('rule', $payment->fee_source);
        $this->assertSame('40.50', $payment->fee_amount);
        // 3 parcelas → média de 2 meses × 2% = 4% sobre o líquido da taxa (959,50).
        $this->assertSame('4.0000', $payment->anticipation_percent_applied);
        $this->assertSame('38.38', $payment->anticipation_fee_amount);
        $this->assertSame('921.12', $payment->net_amount);

        $this->getJson("/api/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.payments.0.fee_amount', '40.50')
            ->assertJsonPath('data.payments.0.net_amount', '921.12');
    }

    public function test_falls_back_to_method_fee_and_recomputes_on_confirm(): void
    {
        Sanctum::actingAs($this->admin);
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_fee', 'kind' => PaymentMethod::KIND_PIX, 'fee_percent' => '1.0000', 'fee_fixed' => null,
        ]);
        $product = $this->product();
        $saleId = $this->postJson('/api/v1/sales', ['client_id' => $this->client->id])->json('data.id');
        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();
        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [['payment_method_id' => $pix->id, 'amount' => 1000]],
        ])->assertOk();

        $this->assertSame('10.00', SalePayment::query()->where('sale_id', $saleId)->sole()->fee_amount);

        $pix->update(['fee_percent' => '2.0000']);
        $this->acceptBudgetForSale($saleId);
        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $payment = SalePayment::query()->where('sale_id', $saleId)->sole();
        $this->assertSame('method', $payment->fee_source);
        $this->assertSame('20.00', $payment->fee_amount);
        $this->assertSame('980.00', $payment->net_amount);
    }

    public function test_backfill_and_recalculate_endpoint_apply_current_rules(): void
    {
        Sanctum::actingAs($this->admin);
        $saleId = $this->draftWithCardPayment(1);
        $payment = SalePayment::query()->where('sale_id', $saleId)->sole();
        $this->assertSame('none', $payment->fee_source);
        $this->assertSame('1000.00', $payment->net_amount);

        CardFeeRule::query()->create([
            'clinic_id' => $this->clinic->id, 'payment_method_id' => $this->credit->id,
            'card_operator_id' => $this->operator->id, 'card_brand_id' => $this->brand->id,
            'installments' => 1, 'fee_percent' => '3.0000',
        ]);

        $this->postJson('/api/v1/finance/payment-fees/recalculate')
            ->assertOk()
            ->assertJsonPath('data.payments_updated', 1);
        $this->assertSame('30.00', $payment->fresh()->fee_amount);

        SalePayment::query()->whereKey($payment->id)->update(['fee_amount' => 0, 'net_amount' => null]);
        $this->assertSame(1, app(PaymentFeeService::class)->backfill($this->clinic->id));
        $this->assertSame('970.00', $payment->fresh()->net_amount);
    }

    public function test_tax_settings_by_competence_month(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/finance/tax-settings', [
            'effective_month' => '2026-01', 'regime' => 'simples', 'simples_rate_percent' => 6,
        ])->assertCreated()->assertJsonPath('data.effective_rate_percent', '6.0000');

        $this->postJson('/api/v1/finance/tax-settings', [
            'effective_month' => '2026-07', 'regime' => 'presumido',
            'iss_percent' => 5, 'pis_percent' => 0.65, 'cofins_percent' => 3, 'irpj_percent' => 4.8, 'csll_percent' => 2.88,
        ])->assertCreated()->assertJsonPath('data.effective_rate_percent', '16.3300');

        $this->postJson('/api/v1/finance/tax-settings', ['effective_month' => '2026-02', 'regime' => 'simples'])
            ->assertUnprocessable()->assertJsonValidationErrors(['simples_rate_percent']);

        $this->getJson('/api/v1/finance/tax-settings')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.effective_month', '2026-07');

        $resolver = new TaxRateResolver;
        $this->assertSame(0.0, $resolver->ratePercentFor($this->clinic->id, CarbonImmutable::parse('2025-12-20 12:00:00')));
        $this->assertSame(6.0, $resolver->ratePercentFor($this->clinic->id, CarbonImmutable::parse('2026-06-30 12:00:00')));
        $this->assertSame(16.33, $resolver->ratePercentFor($this->clinic->id, CarbonImmutable::parse('2026-07-01 03:00:00')));
        // Instante da venda: 30/06 23:30 em São Paulo (01/07 02:30 UTC) ainda é junho.
        $this->assertSame(6.0, $resolver->ratePercentFor($this->clinic->id, CarbonImmutable::parse('2026-07-01 02:30:00')));
        $this->assertSame(60.0, $resolver->taxFor($this->clinic->id, CarbonImmutable::parse('2026-03-10 12:00:00'), 1000));

        $this->assertSame(2, TaxSetting::query()->count());
    }

    public function test_stock_loss_is_valued_at_average_cost(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->product('12.5000');

        $this->postJson("/api/v1/products/{$product->id}/stock-movements", [
            'type' => 'out', 'quantity' => 2, 'loss_reason' => 'expired', 'reason' => 'Lote vencido',
        ])->assertOk()
            ->assertJsonPath('data.movement.loss_reason', 'expired')
            ->assertJsonPath('data.movement.loss_value', '25.00');

        $this->postJson("/api/v1/products/{$product->id}/stock-movements", [
            'type' => 'in', 'quantity' => 1, 'unit_cost' => 10, 'loss_reason' => 'damaged',
        ])->assertUnprocessable()->assertJsonValidationErrors(['loss_reason']);

        $this->postJson("/api/v1/products/{$product->id}/stock-movements", [
            'type' => 'out', 'quantity' => 1, 'loss_reason' => 'stolen',
        ])->assertUnprocessable()->assertJsonValidationErrors(['loss_reason']);

        $this->assertSame(1, StockMovement::query()->whereNotNull('loss_reason')->count());
    }

    public function test_seller_and_stock_do_not_see_or_manage_fees(): void
    {
        $seller = User::factory()->forClinic($this->clinic)->create();
        $seller->assignRole('seller');
        Sanctum::actingAs($seller);

        $saleId = $this->draftWithCardPayment(1);
        $json = $this->getJson("/api/v1/sales/{$saleId}")->assertOk()->json('data.payments.0');
        $this->assertArrayNotHasKey('fee_amount', $json);
        $this->assertArrayNotHasKey('net_amount', $json);
        $this->assertArrayNotHasKey('fee_percent', $json['payment_method']);

        $this->getJson('/api/v1/card-fee-rules')->assertForbidden();
        $this->getJson('/api/v1/finance/tax-settings')->assertForbidden();
        $this->postJson('/api/v1/finance/payment-fees/recalculate')->assertForbidden();

        $pix = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Pix X', 'code' => 'pix_x', 'kind' => PaymentMethod::KIND_PIX, 'fee_percent' => 9,
        ])->assertCreated();
        $this->assertNull(PaymentMethod::query()->find($pix->json('data.id'))->fee_percent);

        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);
        $product = $this->product('5.0000');
        $this->postJson("/api/v1/products/{$product->id}/stock-movements", [
            'type' => 'out', 'quantity' => 1, 'loss_reason' => 'damaged',
        ])->assertOk()->assertJsonMissingPath('data.movement.loss_value');
        $this->getJson('/api/v1/finance/tax-settings')->assertForbidden();
    }

    public function test_tax_settings_are_isolated_per_clinic(): void
    {
        $other = Clinic::factory()->create();
        $foreign = TaxSetting::query()->create([
            'clinic_id' => $other->id, 'effective_from' => '2026-01-01', 'regime' => 'simples', 'simples_rate_percent' => 8,
        ]);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/tax-settings')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/finance/tax-settings/{$foreign->id}")->assertNotFound();

        $foreignOperator = CardOperator::factory()->forClinic($other)->create();
        $this->putJson("/api/v1/finance/card-operators/{$foreignOperator->id}/anticipation", [
            'auto_anticipate' => true, 'anticipation_rate_percent' => 1,
        ])->assertNotFound();
    }
}
