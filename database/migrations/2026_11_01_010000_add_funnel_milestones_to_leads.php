<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Funil fixo (independente dos status): Abordado → Respondeu → Agendou. */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('approached_at')->nullable();
            $table->foreignId('approached_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['clinic_id', 'approached_at']);
        });
        $this->restorePartialPhoneIndex();

        // Backfill: first WhatsApp touch → abordado; conversion → agendou (by who converted).
        $firstWhatsapp = DB::table('lead_activities')
            ->where('type', 'whatsapp')
            ->orderBy('created_at')->orderBy('id')
            ->get(['lead_id', 'user_id', 'created_at'])
            ->unique('lead_id');
        foreach ($firstWhatsapp as $row) {
            DB::table('leads')->where('id', $row->lead_id)->whereNull('approached_at')->update([
                'approached_at' => $row->created_at,
                'approached_by_user_id' => $row->user_id,
            ]);
        }

        $conversions = DB::table('lead_activities')
            ->where('type', 'converted')
            ->orderBy('created_at')->orderBy('id')
            ->get(['lead_id', 'user_id', 'created_at'])
            ->unique('lead_id');
        foreach ($conversions as $row) {
            DB::table('leads')->where('id', $row->lead_id)->whereNull('booked_at')->update([
                'booked_at' => $row->created_at,
                'booked_by_user_id' => $row->user_id,
            ]);
        }
        DB::table('leads')->whereNotNull('converted_at')->whereNull('booked_at')
            ->update(['booked_at' => DB::raw('converted_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'approached_at']);
            $table->dropConstrainedForeignId('approached_by_user_id');
            $table->dropConstrainedForeignId('replied_by_user_id');
            $table->dropConstrainedForeignId('booked_by_user_id');
            $table->dropColumn(['approached_at', 'replied_at', 'booked_at']);
        });
        $this->restorePartialPhoneIndex();
    }

    /** SQLite rebuilds the table on ALTER and loses the WHERE of the partial unique index. */
    private function restorePartialPhoneIndex(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS leads_clinic_phone_active_unique');
        DB::statement('CREATE UNIQUE INDEX leads_clinic_phone_active_unique ON leads (clinic_id, phone_digits) WHERE cancelled_at IS NULL');
    }
};
