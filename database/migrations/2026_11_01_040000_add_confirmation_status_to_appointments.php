<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Confirmação da sessão: aguardando / confirmado / não confirmado / desmarcou. */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('confirmation_status', 20)->default('pending');
            $table->timestamp('confirmation_status_at')->nullable();
            $table->foreignId('confirmation_status_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['clinic_id', 'scheduled_at', 'confirmation_status']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'scheduled_at', 'confirmation_status']);
            $table->dropConstrainedForeignId('confirmation_status_by_user_id');
            $table->dropColumn(['confirmation_status', 'confirmation_status_at']);
        });
    }
};
