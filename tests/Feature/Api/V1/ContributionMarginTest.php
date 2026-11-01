<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Protocol;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\TaxSetting;
use App\Models\Treatment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContributionMarginTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $seller;

    protected User $pro;

    protected Protocol $protocol;

    protected Sale $sale;

    protected SaleItem $item1;

    protected Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        CurrentClinic::setId($this->clinic->id);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Admin']);
        $this->admin->assignRole('admin');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Vera Vendedora']);
        $this->seller->assignRole('seller');
        $this->pro = User::factory()->forClinic($this->clinic)->create(['name' => 'Dra. Paula']);
        $this->pro->assignRole('professional');
        $this->protocol = Protocol::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Full face']);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function product(string $cost): Product
    {
        return Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => $cost,
            'stock_quantity' => '50.0000',
            'sku' => fake()->unique()->bothify('C-####'),
        ]);
    }

    /**
     * Venda de R$ 1.350 (lista 1.500): Full face 1.000 + avulso 500; taxa de cartão R$ 50; Simples 10%.
     * Uma sessão concluída pela Dra. Paula aplicou metade do item do protocolo (custo R$ 100).
     */
    protected function scenario(): void
    {
        $a = $this->product('100.0000');
        $b = $this->product('50.0000');
        $client = Client::factory()->forClinic($this->clinic)->create();

        TaxSetting::query()->create([
            'clinic_id' => $this->clinic->id, 'effective_from' => '2026-01-01',
            'regime' => 'simples', 'simples_rate_percent' => '10.0000',
        ]);
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'seller', 'calc_type' => 'percent', 'value' => 5]);
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'seller', 'protocol_id' => $this->protocol->id, 'calc_type' => 'percent', 'value' => 10, 'base' => 'net']);
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'professional', 'calc_type' => 'fixed', 'value' => 30]);

        $this->sale = Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $client->id, 'sold_by_user_id' => $this->seller->id,
            'sold_at' => '2026-10-10 12:00:00', 'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => '1500.00', 'effective_amount' => '1350.00',
        ]);
        $this->item1 = SaleItem::factory()->create([
            'sale_id' => $this->sale->id, 'product_id' => $a->id, 'source_protocol_id' => $this->protocol->id,
            'quantity' => '2.0000', 'unit_price' => '500.00', 'line_total' => '1000.00', 'unit_cost' => '100.0000',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $this->sale->id, 'product_id' => $b->id, 'source_protocol_id' => null,
            'quantity' => '1.0000', 'unit_price' => '500.00', 'line_total' => '500.00', 'unit_cost' => '50.0000',
        ]);
        $method = PaymentMethod::factory()->forClinic($this->clinic)->create(['code' => 'cm_credit', 'kind' => PaymentMethod::KIND_CREDIT_CARD]);
        SalePayment::factory()->create([
            'sale_id' => $this->sale->id, 'payment_method_id' => $method->id, 'amount' => '1350.00',
            'fee_source' => 'rule', 'fee_amount' => '50.00', 'anticipation_fee_amount' => '0.00', 'net_amount' => '1300.00',
        ]);

        $treatment = Treatment::factory()->forClinic($this->clinic)->create([
            'sale_id' => $this->sale->id, 'client_id' => $client->id, 'opened_by_user_id' => $this->admin->id,
            'status' => Treatment::STATUS_OPEN,
        ]);
        $this->appointment = Appointment::factory()->forClinic($this->clinic)->create([
            'treatment_id' => $treatment->id, 'client_id' => $client->id, 'professional_user_id' => $this->pro->id,
            'status' => Appointment::STATUS_COMPLETED, 'scheduled_at' => '2026-10-20 10:00:00',
            'started_at' => '2026-10-20 10:00:00', 'finished_at' => '2026-10-20 11:00:00',
            'total_cost' => '100.0000', 'total_charged_on_appointment' => '0.00',
        ]);
        AppointmentConsumption::factory()->create([
            'appointment_id' => $this->appointment->id, 'product_id' => $a->id, 'sale_item_id' => $this->item1->id,
            'quantity' => '1.0000', 'unit_cost' => '100.0000', 'line_cost' => '100.0000',
        ]);

        $loss = StockMovement::query()->create([
            'clinic_id' => $this->clinic->id, 'product_id' => $b->id, 'type' => 'out', 'quantity' => '1.0000',
            'cost_before' => '20.0000', 'cost_after' => '20.0000', 'stock_before' => '5.0000', 'stock_after' => '4.0000',
            'loss_reason' => 'expired', 'loss_value' => '20.00',
        ]);
        $loss->forceFill(['created_at' => '2026-10-15 09:00:00'])->save();

        $service = app(CommissionService::class);
        $service->syncSale($this->sale->fresh());
        $service->syncAppointment($this->appointment->fresh());
    }

    public function test_commissions_follow_most_specific_rule(): void
    {
        $this->scenario();

        $seller = CommissionEntry::query()->where('role', 'seller')->orderBy('amount')->get();
        $this->assertCount(2, $seller);
        // Avulso: 5% de 450 (bruto).
        $this->assertSame('22.50', $seller[0]->amount);
        // Full face: 10% de 900 × (1350 − 50 − 135) / 1350.
        $this->assertSame('776.67', $seller[1]->base_amount);
        $this->assertSame('77.67', $seller[1]->amount);
        $this->assertSame($this->protocol->id, $seller[1]->protocol_id);
        $this->assertSame('2026-10-01', $seller[1]->competence_month->toDateString());

        $pro = CommissionEntry::query()->where('role', 'professional')->sole();
        $this->assertSame('30.00', $pro->amount);
        $this->assertSame('450.00', $pro->base_amount);
        $this->assertSame($this->pro->id, $pro->user_id);
        $this->assertSame($this->sale->id, $pro->sale_id);

        // Paga não é recalculada; cancelar a venda cancela só as comissões pendentes do vendedor.
        $seller[0]->update(['status' => 'paid', 'paid_at' => now()]);
        $this->sale->update(['status' => Sale::STATUS_CANCELLED]);
        app(CommissionService::class)->syncSale($this->sale->fresh());
        $this->assertSame(['cancelled', 'paid'], CommissionEntry::query()->where('role', 'seller')->orderBy('status')->pluck('status')->all());
        $this->assertSame('pending', $pro->fresh()->status);
    }

    public function test_contribution_margin_breaks_down_each_deduction(): void
    {
        $this->scenario();
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/metrics/contribution-margin?from=2026-10-01&to=2026-10-31')->assertOk();

        $res->assertJsonPath('data.totals.revenue', '1350.00')
            ->assertJsonPath('data.totals.supplies', '250.00')
            ->assertJsonPath('data.totals.supplies_estimated', '150.00')
            ->assertJsonPath('data.totals.card_fees', '50.00')
            ->assertJsonPath('data.totals.taxes', '135.00')
            ->assertJsonPath('data.totals.commissions', '130.17')
            ->assertJsonPath('data.totals.contribution_margin', '784.83')
            ->assertJsonPath('data.totals.losses', '20.00')
            ->assertJsonPath('data.totals.losses_by_reason.expired', '20.00')
            ->assertJsonPath('data.totals.clinic_contribution_margin', '764.83')
            ->assertJsonPath('data.sales.0.supplies_estimated', true)
            ->assertJsonPath('data.by_seller.0.label', 'Vera Vendedora')
            ->assertJsonPath('data.by_professional.0.label', 'Dra. Paula')
            ->assertJsonPath('data.by_professional.0.contribution_margin', '784.83');

        $protocols = collect($res->json('data.by_protocol'))->keyBy('label');
        $this->assertSame('469.00', $protocols['Full face']['contribution_margin']);
        $this->assertSame('315.83', $protocols['Itens avulsos e extras']['contribution_margin']);

        // Margem bruta existente continua igual.
        $this->getJson('/api/v1/metrics/margin?from=2026-10-01&to=2026-10-31&mode=cohort_sale')
            ->assertOk()->assertJsonPath('data.kpis.gross_margin', '1250.00');
    }

    public function test_commission_endpoints_and_permissions(): void
    {
        $this->scenario();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/finance/commissions?month=2026-10')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', '130.17')
            ->assertJsonPath('meta.by_user.0.user_name', 'Vera Vendedora');

        $rule = $this->postJson('/api/v1/finance/commission-rules', [
            'role' => 'professional', 'user_id' => $this->pro->id, 'calc_type' => 'percent', 'value' => 10,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/commission-rules', ['role' => 'seller', 'calc_type' => 'percent', 'value' => 150])
            ->assertUnprocessable()->assertJsonValidationErrors(['value']);

        $this->postJson('/api/v1/finance/commissions/rebuild', ['month' => '2026-10'])->assertOk();
        // Regra específica da Dra. Paula (10% de 450) substitui o fixo geral.
        $this->assertSame('45.00', CommissionEntry::query()->where('role', 'professional')->sole()->amount);
        $this->assertSame($rule, CommissionEntry::query()->where('role', 'professional')->sole()->commission_rule_id);

        $ids = CommissionEntry::query()->pluck('id')->all();
        $this->postJson('/api/v1/finance/commissions/mark-paid', ['ids' => $ids, 'paid' => true])
            ->assertOk()->assertJsonPath('data.updated', 3);

        $this->getJson('/api/v1/finance/commission-rules')->assertOk()
            ->assertJsonCount(4, 'data')->assertJsonCount(3, 'meta.users');

        foreach ([$this->seller, $this->pro] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/finance/commissions?month=2026-10')->assertForbidden();
            $this->getJson('/api/v1/finance/commission-rules')->assertForbidden();
            $this->getJson('/api/v1/metrics/contribution-margin?from=2026-10-01&to=2026-10-31')->assertForbidden();
        }
    }

    public function test_contribution_margin_is_isolated_per_clinic(): void
    {
        $this->scenario();
        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        CurrentClinic::forget();
        Sanctum::actingAs($otherAdmin);

        $this->getJson('/api/v1/metrics/contribution-margin?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertJsonPath('data.totals.revenue', '0.00')
            ->assertJsonPath('data.totals.losses', '0.00');
        $this->getJson('/api/v1/finance/commissions?month=2026-10')->assertOk()->assertJsonCount(0, 'data');
        $ruleId = CommissionRule::query()->withoutGlobalScope('clinic')->where('clinic_id', $this->clinic->id)->value('id');
        $this->putJson("/api/v1/finance/commission-rules/{$ruleId}", ['value' => 1])->assertNotFound();
    }

    public function test_confirm_and_complete_generate_entries_automatically(): void
    {
        CommissionRule::query()->create(['clinic_id' => $this->clinic->id, 'role' => 'seller', 'calc_type' => 'fixed', 'value' => 15]);
        Sanctum::actingAs($this->admin);
        $product = $this->product('10.0000');
        $product->update(['sale_price' => '200.00', 'min_sale_price' => null]);
        $client = Client::factory()->forClinic($this->clinic)->create();
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create(['code' => 'pix_cm', 'kind' => PaymentMethod::KIND_PIX]);

        $saleId = $this->postJson('/api/v1/sales', ['client_id' => $client->id])->json('data.id');
        $this->putJson("/api/v1/sales/{$saleId}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertOk();
        $this->putJson("/api/v1/sales/{$saleId}/payments", ['payments' => [['payment_method_id' => $pix->id, 'amount' => 200]]])->assertOk();
        $this->acceptBudgetForSale($saleId);
        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $entry = CommissionEntry::query()->sole();
        $this->assertSame('15.00', $entry->amount);
        $this->assertSame($this->admin->id, $entry->user_id);

        $this->postJson("/api/v1/sales/{$saleId}/cancel")->assertOk();
        $this->assertSame('cancelled', $entry->fresh()->status);
        $this->assertSame(CarbonImmutable::now()->startOfMonth()->toDateString(), $entry->competence_month->toDateString());
    }
}
