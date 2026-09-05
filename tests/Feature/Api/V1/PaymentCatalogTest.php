<?php

namespace Tests\Feature\Api\V1;

use App\Models\CardBrand;
use App\Models\CardFeeRule;
use App\Models\CardOperator;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\PaymentFeeCalculator;
use Database\Seeders\PaymentCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_can_manage_payment_methods_including_boleto_fee(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Boleto',
            'code' => 'boleto',
            'kind' => PaymentMethod::KIND_BOLETO,
            'fee_fixed' => 2.5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Boleto')
            ->assertJsonPath('data.kind', 'boleto')
            ->assertJsonPath('data.requires_card_meta', false)
            ->assertJsonPath('data.fee_fixed', '2.50');

        $method = PaymentMethod::query()->findOrFail($response->json('data.id'));
        $this->assertSame('2.50', $method->feeAmountFor(100));
        $this->assertSame('97.50', $method->netAmountFor(100));

        $this->putJson('/api/v1/payment-methods/'.$method->id, [
            'fee_fixed' => 3,
        ])->assertOk()->assertJsonPath('data.fee_fixed', '3.00');

        $this->deleteJson('/api/v1/payment-methods/'.$method->id)
            ->assertOk()
            ->assertJsonPath('message', 'Payment method deactivated.');

        $this->assertFalse($method->fresh()->is_active);
    }

    public function test_card_payment_method_clears_method_level_fees(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/payment-methods', [
            'name' => 'Cartão de crédito',
            'code' => 'cartao_credito',
            'kind' => PaymentMethod::KIND_CREDIT_CARD,
            'fee_percent' => 5,
            'fee_fixed' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.requires_card_meta', true)
            ->assertJsonPath('data.fee_percent', null)
            ->assertJsonPath('data.fee_fixed', null);
    }

    public function test_can_manage_operators_brands_and_fee_rules(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        $credit = PaymentMethod::factory()->forClinic($this->clinic)->creditCard()->create();
        $debit = PaymentMethod::factory()->forClinic($this->clinic)->debitCard()->create();

        $operator = $this->postJson('/api/v1/card-operators', [
            'name' => 'Stone Antecipada',
            'code' => 'stone_antecipada',
            'auto_anticipate' => true,
        ])->assertCreated()
            ->assertJsonPath('data.auto_anticipate', true)
            ->json('data');

        $brand = $this->postJson('/api/v1/card-brands', [
            'name' => 'Visa',
            'code' => 'visa',
        ])->assertCreated()->json('data');

        $creditRule = $this->postJson('/api/v1/card-fee-rules', [
            'payment_method_id' => $credit->id,
            'card_operator_id' => $operator['id'],
            'card_brand_id' => $brand['id'],
            'installments' => 3,
            'fee_percent' => 4.49,
        ])->assertCreated()
            ->assertJsonPath('data.installments', 3)
            ->assertJsonPath('data.fee_percent', '4.4900');

        $rule = CardFeeRule::query()->findOrFail($creditRule->json('data.id'));
        $this->assertSame('4.49', $rule->feeAmountFor(100));
        $this->assertSame('95.51', $rule->netAmountFor(100));

        $this->postJson('/api/v1/card-fee-rules', [
            'payment_method_id' => $debit->id,
            'card_operator_id' => $operator['id'],
            'card_brand_id' => $brand['id'],
            'installments' => 1,
            'fee_percent' => 1.4,
            'fee_fixed' => 0.1,
        ])->assertCreated();

        $this->assertSame('1.50', PaymentFeeCalculator::feeAmount(100, '1.4', '0.1'));
        $this->assertSame('98.50', PaymentFeeCalculator::netAmount(100, '1.4', '0.1'));
    }

    public function test_debit_fee_rule_requires_one_installment_and_brand(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        $debit = PaymentMethod::factory()->forClinic($this->clinic)->debitCard()->create();
        $operator = CardOperator::factory()->forClinic($this->clinic)->create();
        $brand = CardBrand::factory()->forClinic($this->clinic)->create();

        $this->postJson('/api/v1/card-fee-rules', [
            'payment_method_id' => $debit->id,
            'card_operator_id' => $operator->id,
            'card_brand_id' => $brand->id,
            'installments' => 2,
            'fee_percent' => 1.5,
        ])->assertStatus(422)->assertJsonValidationErrors(['installments']);

        $this->postJson('/api/v1/card-fee-rules', [
            'payment_method_id' => $debit->id,
            'card_operator_id' => $operator->id,
            'installments' => 1,
            'fee_percent' => 1.5,
        ])->assertStatus(422)->assertJsonValidationErrors(['card_brand_id']);
    }

    public function test_user_without_permission_cannot_manage_payments(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payment-methods', [
            'name' => 'PIX',
            'code' => 'pix',
            'kind' => PaymentMethod::KIND_PIX,
        ])->assertForbidden();
    }

    public function test_payment_catalog_is_isolated_by_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        PaymentMethod::factory()->forClinic($this->clinic)->create([
            'name' => 'PIX Local',
            'code' => 'pix_local',
        ]);

        $other = Clinic::factory()->create();
        PaymentMethod::factory()->forClinic($other)->create([
            'name' => 'PIX Outra',
            'code' => 'pix_outra',
        ]);

        $names = collect($this->getJson('/api/v1/payment-methods')->assertOk()->json('data'))
            ->pluck('name');

        $this->assertTrue($names->contains('PIX Local'));
        $this->assertFalse($names->contains('PIX Outra'));
    }

    public function test_payment_catalog_seeder_creates_defaults(): void
    {
        CurrentClinic::setId($this->clinic->id);
        $this->seed(PaymentCatalogSeeder::class);

        $this->assertDatabaseHas('payment_methods', [
            'clinic_id' => $this->clinic->id,
            'code' => 'cartao_credito',
            'kind' => PaymentMethod::KIND_CREDIT_CARD,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'clinic_id' => $this->clinic->id,
            'code' => 'boleto',
            'kind' => PaymentMethod::KIND_BOLETO,
        ]);
        $this->assertDatabaseHas('card_brands', [
            'clinic_id' => $this->clinic->id,
            'code' => 'visa',
        ]);
        $this->assertDatabaseHas('card_brands', [
            'clinic_id' => $this->clinic->id,
            'code' => 'mastercard',
        ]);
    }
}
