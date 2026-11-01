<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orçamentos esquecidos: registra a última cobrança pelo WhatsApp de um orçamento enviado.
 * Os prazos (ex.: 2/5/10 dias) ficam em clinics.settings.budget_follow_up_days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->timestamp('follow_up_sent_at')->nullable()->after('rejected_at');
            $table->foreignId('follow_up_sent_by_user_id')->nullable()->after('follow_up_sent_at')
                ->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('follow_up_count')->default(0)->after('follow_up_sent_by_user_id');
            $table->index(['clinic_id', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'status', 'sent_at']);
            $table->dropConstrainedForeignId('follow_up_sent_by_user_id');
            $table->dropColumn(['follow_up_sent_at', 'follow_up_count']);
        });
    }
};
