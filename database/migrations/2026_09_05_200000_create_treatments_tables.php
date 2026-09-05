<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('sale_id');
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'client_id']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->decimal('total_charged_on_appointment', 12, 2)->default(0);
            $table->json('stock_warning')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'scheduled_at']);
            $table->index(['treatment_id', 'status']);
        });

        Schema::create('appointment_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->decimal('quantity', 14, 4);
            $table->boolean('is_complimentary')->default(false);
            $table->decimal('charged_amount', 12, 2)->default(0);
            $table->foreignId('sale_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('line_cost', 14, 4)->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_consumptions');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('treatments');
    }
};
