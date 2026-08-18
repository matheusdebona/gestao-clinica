<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('products_sale_total', 15, 2)->default(0);
            $table->decimal('suggested_price', 15, 2)->default(0);
            $table->boolean('suggested_price_is_manual')->default(false);
            $table->decimal('min_price', 15, 2)->default(0);
            $table->boolean('min_price_is_manual')->default(false);
            $table->decimal('special_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('protocol_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->unique(['protocol_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_items');
        Schema::dropIfExists('protocols');
    }
};
