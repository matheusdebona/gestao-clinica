<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('muted');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            // System statuses the app relies on (e.g. "converted" set on patient conversion).
            $table->string('system_key', 30)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'name']);
            $table->unique(['clinic_id', 'system_key']);
            $table->index(['clinic_id', 'is_active', 'sort_order']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->foreignId('client_origin_id')->constrained('client_origins')->restrictOnDelete();
            $table->foreignId('lead_status_id')->constrained('lead_statuses')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'created_at']);
            $table->index(['clinic_id', 'created_by_user_id', 'created_at']);
            $table->index(['clinic_id', 'lead_status_id']);
            $table->index(['clinic_id', 'client_origin_id']);
            $table->index(['clinic_id', 'phone']);
            $table->index(['clinic_id', 'client_id']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->foreignId('from_status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('campaign_id')->constrained('leads')->nullOnDelete();
            $table->index(['clinic_id', 'lead_id']);
        });

        // Lead conversion books a first visit (avaliação) before any sale/treatment exists.
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('treatment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'lead_id']);
            $table->dropConstrainedForeignId('lead_id');
        });

        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_statuses');
        // appointments.treatment_id stays nullable: rows without treatment may exist.
    }
};
