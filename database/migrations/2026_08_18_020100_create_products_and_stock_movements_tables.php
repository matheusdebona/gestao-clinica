<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained('product_types')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('purpose')->nullable();
            $table->decimal('cost', 15, 4)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('min_sale_price', 15, 2)->nullable();
            $table->decimal('stock_quantity', 15, 4)->default(0);
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'is_active']);
            $table->unique(['clinic_id', 'sku']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('cost_before', 15, 4);
            $table->decimal('cost_after', 15, 4);
            $table->decimal('stock_before', 15, 4);
            $table->decimal('stock_after', 15, 4);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamps();

            $table->index(['clinic_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
    }
};
