<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Check-in diário: a auto análise de cada usuário (uma por dia, editável só no dia). */
    public function up(): void
    {
        Schema::create('lead_daily_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->text('worked')->nullable();
            $table->text('change')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'day']);
            $table->index(['clinic_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_daily_checkins');
    }
};
