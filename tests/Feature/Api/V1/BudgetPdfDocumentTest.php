<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetPdfDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create(['name' => 'Clínica PDF']);
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        $this->client = Client::factory()->forClinic($this->clinic)->create(['name' => 'Maria']);
        Storage::fake('s3');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function makeProduct(string $name, string $cost, string $sale, ?string $minSale = null): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => $cost,
            'sale_price' => $sale,
            'min_sale_price' => $minSale,
            'stock_quantity' => '100.0000',
            'sku' => fake()->unique()->bothify('PDF-####'),
        ]);
    }

    protected function createBudgetWithDiscount(): int
    {
        Sanctum::actingAs($this->admin);

        $product = $this->makeProduct('Botox 100U', '10.0000', '100.00', '80.00');

        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 90,
            ]],
        ])->assertOk();

        return $this->postJson("/api/v1/sales/{$saleId}/budgets", [
            'notes' => 'Proposta com desconto',
        ])->assertCreated()->json('data.id');
    }

    public function test_generate_budget_pdf_creates_document_with_list_vs_offered_payload(): void
    {
        $budgetId = $this->createBudgetWithDiscount();

        $response = $this->postJson("/api/v1/budgets/{$budgetId}/pdf")
            ->assertCreated()
            ->assertJsonPath('data.type', 'budget_pdf')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.budget_id', $budgetId)
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.payload.items.0.product_name', 'Botox 100U')
            ->assertJsonPath('data.payload.items.0.list_unit_price', '100.00')
            ->assertJsonPath('data.payload.items.0.unit_price', '90.00')
            ->assertJsonPath('data.payload.items.0.list_line_total', '200.00')
            ->assertJsonPath('data.payload.items.0.line_total', '180.00')
            ->assertJsonPath('data.payload.items.0.discount_amount', '20.00')
            ->assertJsonPath('data.payload.budget.list_total', '200.00')
            ->assertJsonPath('data.payload.budget.discount_total', '20.00')
            ->assertJsonPath('data.payload.budget.expected_amount', '180.00');

        $path = $response->json('data.storage_path');
        $this->assertNotEmpty($path);
        Storage::disk('s3')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('s3')->get($path));
    }

    public function test_regenerate_creates_new_document_history(): void
    {
        $budgetId = $this->createBudgetWithDiscount();

        $firstId = $this->postJson("/api/v1/budgets/{$budgetId}/pdf")
            ->assertCreated()
            ->json('data.id');

        $secondId = $this->postJson("/api/v1/budgets/{$budgetId}/pdf")
            ->assertCreated()
            ->json('data.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(2, Document::query()->where('budget_id', $budgetId)->count());
    }

    public function test_list_filter_download_and_delete_document(): void
    {
        $budgetId = $this->createBudgetWithDiscount();

        $documentId = $this->postJson("/api/v1/budgets/{$budgetId}/pdf")
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/documents?budget_id={$budgetId}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $documentId);

        $this->getJson("/api/v1/documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('data.type', 'budget_pdf');

        $download = $this->get("/api/v1/documents/{$documentId}/download");
        $download->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $download->headers->get('content-type'));

        $path = Document::query()->findOrFail($documentId)->storage_path;

        $this->deleteJson("/api/v1/documents/{$documentId}")
            ->assertOk();

        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_clinic_isolation_on_documents(): void
    {
        $budgetId = $this->createBudgetWithDiscount();
        $documentId = $this->postJson("/api/v1/budgets/{$budgetId}/pdf")
            ->assertCreated()
            ->json('data.id');

        $otherClinic = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($otherClinic)->create();
        $otherAdmin->assignRole('admin');

        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($otherClinic->id);

        $this->getJson("/api/v1/documents/{$documentId}")->assertNotFound();
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_forbidden_without_documents_permission(): void
    {
        $budget = Budget::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
        ]);

        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/budgets/{$budget->id}/pdf")->assertForbidden();
        $this->getJson('/api/v1/documents')->assertForbidden();
    }
}
