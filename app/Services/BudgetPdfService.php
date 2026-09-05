<?php

namespace App\Services;

use App\Contracts\PdfRenderer;
use App\Models\Budget;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BudgetPdfService
{
    public function __construct(
        private readonly PdfRenderer $pdfRenderer,
    ) {}

    public function generate(Budget $budget, User $user): Document
    {
        $budget->loadMissing(['items', 'client', 'clinic']);

        $clinic = $budget->clinic ?? Clinic::query()->findOrFail($budget->clinic_id);
        $branding = $clinic->branding();
        $payload = $this->buildPayload($budget, $clinic, $branding);

        $html = view('pdf.budget', [
            'payload' => $payload,
            'logoDataUri' => $this->logoDataUri($branding['logo_path'] ?? null),
        ])->render();

        $pdfBytes = $this->pdfRenderer->fromHtml($html);

        $uuid = (string) Str::uuid();
        $version = (int) $budget->version;
        $filename = "orcamento-v{$version}-{$uuid}.pdf";
        $storagePath = "documents/{$budget->clinic_id}/budgets/{$budget->id}/v{$version}-{$uuid}.pdf";

        $stored = Storage::disk('s3')->put($storagePath, $pdfBytes, [
            'ContentType' => 'application/pdf',
        ]);

        if ($stored !== true) {
            throw new RuntimeException('Failed to store budget PDF on S3.');
        }

        return Document::query()->create([
            'clinic_id' => $budget->clinic_id,
            'client_id' => $budget->client_id,
            'budget_id' => $budget->id,
            'sale_id' => $budget->sale_id,
            'type' => Document::TYPE_BUDGET_PDF,
            'status' => Document::STATUS_ISSUED,
            'storage_path' => $storagePath,
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'payload' => $payload,
            'generated_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    public function buildPayload(Budget $budget, Clinic $clinic, array $branding): array
    {
        $items = $budget->items->map(function ($item) {
            $list = (float) $item->list_line_total;
            $offered = (float) $item->line_total;
            $discount = round($list - $offered, 2);
            $discountPercent = $list > 0
                ? round(($discount / $list) * 100, 2)
                : 0.0;

            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => (string) $item->quantity,
                'list_unit_price' => (string) $item->list_unit_price,
                'list_line_total' => (string) $item->list_line_total,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
                'discount_amount' => number_format($discount, 2, '.', ''),
                'discount_percent' => number_format($discountPercent, 2, '.', ''),
                'unit_cost' => (string) $item->unit_cost,
                'min_unit_price' => (string) $item->min_unit_price,
            ];
        })->values()->all();

        $listTotal = array_sum(array_map(
            fn (array $row) => (float) $row['list_line_total'],
            $items
        ));
        $offeredTotal = (float) $budget->expected_amount;
        $discountTotal = round($listTotal - $offeredTotal, 2);

        return [
            'branding' => [
                'display_name' => $branding['display_name'] ?? $clinic->name,
                'primary_color' => $branding['primary_color'] ?? '#0F766E',
                'secondary_color' => $branding['secondary_color'] ?? '#134E4A',
                'logo_path' => $branding['logo_path'] ?? null,
            ],
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'document' => $clinic->document,
                'phone' => $clinic->phone,
                'email' => $clinic->email,
                'address' => $clinic->address,
            ],
            'client' => [
                'id' => $budget->client?->id,
                'name' => $budget->client?->name,
                'whatsapp' => $budget->client?->whatsapp,
            ],
            'budget' => [
                'id' => $budget->id,
                'sale_id' => $budget->sale_id,
                'version' => $budget->version,
                'status' => $budget->status,
                'notes' => $budget->notes,
                'valid_until' => $budget->valid_until?->toIso8601String(),
                'expected_amount' => (string) $budget->expected_amount,
                'effective_amount' => (string) $budget->effective_amount,
                'min_amount' => (string) $budget->min_amount,
                'list_total' => number_format($listTotal, 2, '.', ''),
                'discount_total' => number_format($discountTotal, 2, '.', ''),
            ],
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function logoDataUri(?string $logoPath): ?string
    {
        if ($logoPath === null || $logoPath === '') {
            return null;
        }

        if (! Storage::disk('s3')->exists($logoPath)) {
            return null;
        }

        $bytes = Storage::disk('s3')->get($logoPath);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
