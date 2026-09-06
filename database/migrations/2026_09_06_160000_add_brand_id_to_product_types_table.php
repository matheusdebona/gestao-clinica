<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('brands')
                ->restrictOnDelete();
            $table->dropUnique(['clinic_id', 'slug']);
        });

        $this->backfillBrandIds();

        Schema::table('product_types', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable(false)->change();
            $table->unique(['clinic_id', 'brand_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropUnique(['clinic_id', 'brand_id', 'slug']);
            $table->dropConstrainedForeignId('brand_id');
            $table->unique(['clinic_id', 'slug']);
        });
    }

    private function backfillBrandIds(): void
    {
        $types = ProductType::query()->withoutGlobalScopes()->whereNull('brand_id')->get();

        foreach ($types as $type) {
            $brandIds = Product::query()
                ->withoutGlobalScopes()
                ->where('product_type_id', $type->id)
                ->distinct()
                ->orderBy('brand_id')
                ->pluck('brand_id')
                ->filter()
                ->values();

            if ($brandIds->isEmpty()) {
                $type->brand_id = $this->fallbackBrandId((int) $type->clinic_id);
                $type->save();

                continue;
            }

            $firstBrandId = (int) $brandIds->shift();
            $type->brand_id = $firstBrandId;
            $type->save();

            foreach ($brandIds as $otherBrandId) {
                $clone = $type->replicate();
                $clone->brand_id = (int) $otherBrandId;
                $clone->save();

                Product::query()
                    ->withoutGlobalScopes()
                    ->where('product_type_id', $type->id)
                    ->where('brand_id', $otherBrandId)
                    ->update(['product_type_id' => $clone->id]);
            }
        }
    }

    private function fallbackBrandId(int $clinicId): int
    {
        $existing = Brand::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->orderBy('id')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $brand = new Brand;
        $brand->forceFill([
            'clinic_id' => $clinicId,
            'name' => 'Geral',
            'is_active' => true,
        ]);
        $brand->save();

        return (int) $brand->id;
    }
};
