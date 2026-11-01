<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comentários editáveis: marca "(editado)" com o horário da última edição. */
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('edited_by_user_id');
            $table->dropColumn('edited_at');
        });
    }
};
