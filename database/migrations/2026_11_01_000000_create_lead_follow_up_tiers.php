<?php

use App\Support\EnsureDefaultFollowUpTiers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prazos de acompanhamento ("lead parado"): ex. 3, 7, 30, 60 dias sem contato.
        Schema::create('lead_follow_up_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days');
            $table->string('label', 60)->nullable();
            $table->string('color', 20)->default('warning');
            $table->timestamps();
            $table->unique(['clinic_id', 'days']);
        });

        // Which lead/threshold pairs were already alerted (re-armed by new activity).
        Schema::create('lead_follow_up_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days');
            $table->timestamp('alerted_at');
            $table->timestamps();
            $table->unique(['lead_id', 'days']);
        });

        EnsureDefaultFollowUpTiers::runAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_follow_up_alerts');
        Schema::dropIfExists('lead_follow_up_tiers');
    }
};
