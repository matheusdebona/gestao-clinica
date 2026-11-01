<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultExpenseCategories;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DreTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        CurrentClinic::setId($this->clinic->id);
        EnsureDefaultExpenseCategories::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function expense(string $category, string $amount, string $month = '2026-10-01', ?int $clinicId = null): void
    {
        $clinicId ??= $this->clinic->id;
        $catId = ExpenseCategory::withoutGlobalScope('clinic')->where('clinic_id', $clinicId)->where('name', $category)->value('id');
        Expense::withoutGlobalScope('clinic')->create([
            'clinic_id' => $clinicId, 'expense_category_id' => $catId, 'description' => $category,
            'amount' => $amount, 'competence_month' => $month,
        ]);
    }

    protected function scenario(): void
    {
        TaxSetting::query()->create([
            'clinic_id' => $this->clinic->id, 'effective_from' => '2026-01-01',
            'regime' => 'simples', 'simples_rate_percent' => '10.0000',
        ]);
        $client = Client::factory()->forClinic($this->clinic)->create();
        $sale = Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $client->id, 'sold_by_user_id' => $this->admin->id,
            'sold_at' => '2026-10-10 12:00:00', 'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => '1500.00', 'effective_amount' => '1350.00',
        ]);
        $product = Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '0.0000',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => '1.0000', 'unit_price' => '1500.00', 'line_total' => '1500.00',
            'list_unit_price' => '1500.00', 'list_line_total' => '1500.00', 'unit_cost' => '0.0000',
        ]);
        $method = PaymentMethod::factory()->forClinic($this->clinic)->create(['code' => 'dre_pix', 'kind' => PaymentMethod::KIND_PIX]);
        SalePayment::factory()->create([
            'sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => '1350.00',
            'fee_source' => 'none', 'fee_amount' => '0.00', 'anticipation_fee_amount' => '0.00', 'net_amount' => '1350.00',
        ]);

        $this->expense('Aluguel', '3000.00');
        $this->expense('Pró-labore', '2000.00');
        $this->expense('Mídia externa (anúncios)', '500.00');
        $this->expense('Tarifas bancárias', '50.00');
        $this->expense('Outros custos variáveis', '15.00');
        $this->expense('Aluguel', '2800.00', '2026-09-01');
    }

    public function test_monthly_dre_lines_break_even_and_previous_month(): void
    {
        $this->scenario();
        $other = Clinic::factory()->create();
        EnsureDefaultExpenseCategories::run($other);
        $this->expense('Aluguel', '9999.00', '2026-10-01', $other->id);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/finance/dre?month=2026-10')->assertOk();

        $res->assertJsonPath('data.month', '2026-10')
            ->assertJsonPath('data.previous_month', '2026-09')
            ->assertJsonPath('data.current.lines.gross_revenue', '1500.00')
            ->assertJsonPath('data.current.lines.discounts', '150.00')
            ->assertJsonPath('data.current.lines.net_revenue', '1350.00')
            ->assertJsonPath('data.current.lines.taxes', '135.00')
            ->assertJsonPath('data.current.lines.other_variable', '15.00')
            ->assertJsonPath('data.current.lines.variable_costs', '150.00')
            ->assertJsonPath('data.current.lines.contribution_margin', '1200.00')
            ->assertJsonPath('data.current.lines.fixed', '3000.00')
            ->assertJsonPath('data.current.lines.personnel', '2000.00')
            ->assertJsonPath('data.current.lines.marketing', '500.00')
            ->assertJsonPath('data.current.lines.financial', '50.00')
            ->assertJsonPath('data.current.lines.operating_expenses', '5550.00')
            ->assertJsonPath('data.current.lines.net_result', '-4350.00')
            ->assertJsonPath('data.current.contribution_margin_percent', '88.89')
            ->assertJsonPath('data.current.net_margin_percent', '-322.22')
            ->assertJsonPath('data.current.break_even_revenue', '6243.75')
            ->assertJsonPath('data.current.breakdown.fixed.0.name', 'Aluguel')
            ->assertJsonPath('data.previous.lines.fixed', '2800.00')
            ->assertJsonPath('data.previous.lines.net_result', '-2800.00')
            ->assertJsonPath('data.previous.break_even_revenue', null);
    }

    public function test_recurring_and_depreciation_flow_into_the_dre(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/recurring-expenses', [
            'expense_category_id' => ExpenseCategory::query()->where('name', 'Internet e telefone')->value('id'),
            'description' => 'Fibra', 'amount' => '200.00', 'due_day' => 5, 'starts_month' => '2026-10',
        ])->assertCreated();
        $this->postJson('/api/v1/finance/equipment', [
            'name' => 'Laser', 'purchase_value' => '60000.00', 'useful_life_months' => 60,
            'depreciation_start_month' => '2026-10',
        ])->assertCreated();

        $this->getJson('/api/v1/finance/dre?month=2026-10')->assertOk()
            ->assertJsonPath('data.current.lines.fixed', '200.00')
            ->assertJsonPath('data.current.lines.depreciation', '1000.00')
            ->assertJsonPath('data.current.lines.net_result', '-1200.00')
            ->assertJsonPath('data.current.contribution_margin_percent', null);
    }

    public function test_only_admin_and_management_see_the_dre(): void
    {
        foreach (['seller', 'receptionist', 'stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/finance/dre?month=2026-10')->assertForbidden();
            $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertForbidden();
        }
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/dre?month=outubro')->assertUnprocessable();
    }
}
