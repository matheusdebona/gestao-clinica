<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metas mensais por colaborador (definidas pela administração). Uma linha por usuário e mês
 * (YYYY-MM); um mês sem linha herda a meta mais recente anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);
            $table->unsignedInteger('appointments_target')->nullable();
            $table->decimal('sales_target', 12, 2)->nullable();
            $table->unsignedInteger('approached_target')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['clinic_id', 'user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
    }
};
