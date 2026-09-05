<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('sold_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('effective_amount', 15, 2)->default(0);
            $table->boolean('effective_amount_is_manual')->default(false);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'client_id']);
            $table->index(['clinic_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_protocol_id')->nullable()->constrained('protocols')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('min_unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->unique(['sale_id', 'product_id']);
            $table->index(['sale_id', 'source_protocol_id']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('card_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_brand_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('installments')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
