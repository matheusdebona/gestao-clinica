<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_origin_id')->constrained('client_origins')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'client_origin_id', 'name']);
            $table->index(['clinic_id', 'client_origin_id']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('whatsapp');
            $table->text('notes')->nullable();
            $table->text('main_pains')->nullable();
            $table->unsignedInteger('service_duration_minutes')->nullable();
            $table->foreignId('client_origin_id')->nullable()->constrained('client_origins')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->decimal('initial_consultation_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'name']);
            $table->index(['clinic_id', 'whatsapp']);
            $table->index(['clinic_id', 'is_active']);
            $table->index(['clinic_id', 'client_origin_id']);
            $table->index(['clinic_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('client_origins');
    }
};
