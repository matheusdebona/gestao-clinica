<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('kind');
            $table->boolean('requires_card_meta')->default(false);
            $table->decimal('fee_percent', 8, 4)->nullable();
            $table->decimal('fee_fixed', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
            $table->index(['clinic_id', 'kind']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('card_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('auto_anticipate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('card_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('card_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_brand_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installments');
            $table->decimal('fee_percent', 8, 4)->nullable();
            $table->decimal('fee_fixed', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'payment_method_id', 'card_operator_id', 'card_brand_id', 'installments'],
                'card_fee_rules_unique_lookup'
            );
            $table->index(['clinic_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_fee_rules');
        Schema::dropIfExists('card_brands');
        Schema::dropIfExists('card_operators');
        Schema::dropIfExists('payment_methods');
    }
};
