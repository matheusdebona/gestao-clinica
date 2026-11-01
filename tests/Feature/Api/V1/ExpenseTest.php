<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseGenerator;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultExpenseCategories;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function category(string $name): int
    {
        return (int) ExpenseCategory::query()->where('name', $name)->value('id');
    }

    public function test_default_categories_cover_every_kind(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/finance/expense-categories')->assertOk();
        $kinds = collect($res->json('data'))->pluck('kind')->unique()->sort()->values()->all();
        $this->assertSame(collect(ExpenseCategory::KINDS)->sort()->values()->all(), $kinds);
        $this->assertContains('Aluguel', collect($res->json('data'))->pluck('name'));
        $this->assertContains('Pró-labore', collect($res->json('data'))->pluck('name'));

        $this->postJson('/api/v1/finance/expense-categories', ['name' => 'Aluguel', 'kind' => 'fixed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->postJson('/api/v1/finance/expense-categories', ['name' => 'Estacionamento', 'kind' => 'fixed'])
            ->assertCreated()->assertJsonPath('data.is_system', false);
    }

    public function test_expense_crud_by_competence_month_with_marketing_link(): void
    {
        Sanctum::actingAs($this->admin);
        $origin = ClientOrigin::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Instagram']);

        $id = $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $this->category('Mídia externa (anúncios)'),
            'description' => 'Meta Ads outubro',
            'amount' => '1500.00',
            'competence_month' => '2026-10',
            'client_origin_id' => $origin->id,
        ])->assertCreated()->assertJsonPath('data.client_origin_name', 'Instagram')->json('data.id');

        $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $this->category('Energia elétrica'),
            'description' => 'Conta de luz', 'amount' => '480.50', 'competence_month' => '2026-10', 'paid_on' => '2026-10-10',
        ])->assertCreated();

        $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', '1980.50')
            ->assertJsonPath('meta.paid', '480.50')
            ->assertJsonPath('meta.by_kind.marketing', '1500.00')
            ->assertJsonPath('meta.by_kind.fixed', '480.50');

        $this->putJson("/api/v1/finance/expenses/{$id}", ['amount' => '1600'])->assertOk()->assertJsonPath('data.amount', '1600.00');
        $this->deleteJson("/api/v1/finance/expenses/{$id}")->assertOk();
        $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertJsonPath('meta.total', '480.50');
        $this->getJson('/api/v1/finance/expenses?month=2026-09')->assertJsonCount(0, 'data');
    }

    public function test_recurring_expenses_generate_monthly_and_are_not_recreated(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/finance/recurring-expenses', [
            'expense_category_id' => $this->category('Aluguel'),
            'description' => 'Aluguel da sala', 'amount' => '4000', 'due_day' => 31, 'starts_month' => '2026-08',
        ])->assertCreated();

        // Gera ago, set e out na criação.
        $this->assertSame(3, Expense::query()->count());
        $sept = Expense::query()->whereDate('competence_month', '2026-09-01')->sole();
        $this->assertSame('2026-09-30', $sept->due_on->toDateString());

        // Editar o valor de um mês e excluir outro não é desfeito pela geração.
        $this->putJson("/api/v1/finance/expenses/{$sept->id}", ['amount' => '4100'])->assertOk();
        $oct = Expense::query()->whereDate('competence_month', '2026-10-01')->sole();
        $this->deleteJson("/api/v1/finance/expenses/{$oct->id}")->assertOk();
        app(ExpenseGenerator::class)->generateRange($this->clinic->id, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-10-01'));
        $this->assertSame(2, Expense::query()->count());
        $this->assertSame('4100.00', $sept->fresh()->amount);

        // Novembro aparece quando o mês chega (job diário).
        $this->travelTo(CarbonImmutable::parse('2026-11-01 04:00:00'));
        $this->artisan('finance:generate-expenses')->assertSuccessful();
        $this->assertSame(1, Expense::query()->whereDate('competence_month', '2026-11-01')->count());
    }

    public function test_equipment_depreciation_is_straight_line_over_useful_life(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->postJson('/api/v1/finance/equipment', [
            'name' => 'Laser', 'purchase_value' => '62000', 'residual_value' => '2000',
            'useful_life_months' => 60, 'depreciation_start_month' => '2026-09',
        ])->assertCreated()
            ->assertJsonPath('data.monthly_depreciation', '1000.00')
            ->assertJsonPath('data.depreciation_end_month', '2031-08')
            ->assertJsonPath('data.months_elapsed', 2)
            ->assertJsonPath('data.book_value', '60000.00');

        $this->assertSame('Depreciação de equipamentos', ExpenseCategory::query()->find($res->json('data.expense_category_id'))->name);
        $this->assertSame(2, Expense::query()->whereNotNull('equipment_asset_id')->count());
        $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertJsonPath('meta.by_kind.depreciation', '1000.00');

        $this->postJson('/api/v1/finance/equipment', [
            'name' => 'X', 'purchase_value' => '100', 'residual_value' => '100', 'useful_life_months' => 12, 'depreciation_start_month' => '2026-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['residual_value']);
    }

    public function test_only_finance_roles_and_clinic_isolation(): void
    {
        foreach (['receptionist', 'seller', 'stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertForbidden();
            $this->getJson('/api/v1/finance/expense-categories')->assertForbidden();
            $this->postJson('/api/v1/finance/expenses', [])->assertForbidden();
        }

        $other = Clinic::factory()->create();
        EnsureDefaultExpenseCategories::run($other);
        $foreignCategory = ExpenseCategory::query()->withoutGlobalScope('clinic')->where('clinic_id', $other->id)->value('id');
        $foreign = Expense::query()->create([
            'clinic_id' => $other->id, 'expense_category_id' => $foreignCategory, 'description' => 'X', 'amount' => 10, 'competence_month' => '2026-10-01',
        ]);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/finance/expenses?month=2026-10')->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/finance/expenses/{$foreign->id}", ['amount' => 1])->assertNotFound();
        $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $foreignCategory, 'description' => 'Y', 'amount' => 10, 'competence_month' => '2026-10',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expense_category_id']);
    }
}
