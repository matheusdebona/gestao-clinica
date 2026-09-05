<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('effective_amount', 15, 2)->default(0);
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['sale_id', 'version']);
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'sale_id']);
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_protocol_id')->nullable()->constrained('protocols')->nullOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 15, 4);
            $table->decimal('list_unit_price', 15, 2);
            $table->decimal('list_line_total', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('min_unit_price', 15, 2);
            $table->timestamps();

            $table->index(['budget_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budgets');
    }
};
