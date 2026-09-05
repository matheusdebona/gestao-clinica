<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'clinic_id' => Clinic::factory(),
            'client_id' => Client::factory(),
            'budget_id' => null,
            'sale_id' => null,
            'type' => Document::TYPE_BUDGET_PDF,
            'status' => Document::STATUS_ISSUED,
            'storage_path' => "documents/1/budgets/1/v1-{$uuid}.pdf",
            'filename' => "orcamento-v1-{$uuid}.pdf",
            'mime_type' => 'application/pdf',
            'payload' => [
                'branding' => [
                    'display_name' => 'Clínica Demo',
                    'primary_color' => '#0F766E',
                    'secondary_color' => '#134E4A',
                    'logo_path' => null,
                ],
                'budget' => [
                    'version' => 1,
                    'status' => 'draft',
                    'expected_amount' => '100.00',
                    'effective_amount' => '90.00',
                    'min_amount' => '80.00',
                ],
                'items' => [],
                'client' => [
                    'name' => 'Cliente Teste',
                ],
            ],
            'generated_by_user_id' => User::factory(),
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'client_id' => Client::factory()->forClinic($clinic),
            'budget_id' => Budget::factory()->forClinic($clinic),
            'generated_by_user_id' => User::factory()->forClinic($clinic),
        ]);
    }

    public function budgetPdf(): static
    {
        return $this->state(fn () => [
            'type' => Document::TYPE_BUDGET_PDF,
        ]);
    }
}
