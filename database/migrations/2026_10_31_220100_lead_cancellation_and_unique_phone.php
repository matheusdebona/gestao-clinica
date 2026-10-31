<?php

use App\Models\LeadActivity;
use App\Support\EnsureDefaultLeadStatuses;
use App\Support\LeadPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'leads_clinic_phone_active_unique';

    /**
     * Leads are never hard-deleted: a system "Cancelado" status + cancelled_at replace deletion.
     * phone_digits (canonical digits, no 55) is unique per clinic among non-cancelled leads.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('phone_digits', 20)->nullable()->after('phone');
            $table->timestamp('cancelled_at')->nullable()->after('converted_at');
        });

        DB::table('leads')->select(['id', 'phone'])->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('leads')->where('id', $row->id)->update([
                    'phone_digits' => LeadPhone::canonical((string) $row->phone),
                ]);
            }
        });

        EnsureDefaultLeadStatuses::runAll();

        $this->cancelExistingDuplicates();

        Schema::table('leads', function (Blueprint $table): void {
            $table->index(['clinic_id', 'phone_digits']);
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON leads (clinic_id, phone_digits) WHERE cancelled_at IS NULL');
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'phone_digits']);
            $table->dropColumn(['phone_digits', 'cancelled_at']);
        });
    }

    /**
     * Keeps the converted (or oldest) lead of each duplicated phone and cancels the rest,
     * so the unique index can be created. Nothing is deleted.
     */
    private function cancelExistingDuplicates(): void
    {
        $groups = DB::table('leads')
            ->whereNull('cancelled_at')
            ->whereNotNull('phone_digits')
            ->groupBy('clinic_id', 'phone_digits')
            ->havingRaw('COUNT(*) > 1')
            ->get(['clinic_id', 'phone_digits']);

        foreach ($groups as $group) {
            $leads = DB::table('leads')
                ->where('clinic_id', $group->clinic_id)
                ->where('phone_digits', $group->phone_digits)
                ->whereNull('cancelled_at')
                ->orderByRaw('client_id is null')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'lead_status_id']);

            $cancelledStatusId = EnsureDefaultLeadStatuses::cancelledFor((int) $group->clinic_id)->id;
            $now = now();

            foreach ($leads->slice(1) as $lead) {
                DB::table('leads')->where('id', $lead->id)->update([
                    'cancelled_at' => $now,
                    'lead_status_id' => $cancelledStatusId,
                    'updated_at' => $now,
                ]);
                DB::table('lead_activities')->insert([
                    'lead_id' => $lead->id,
                    'user_id' => null,
                    'type' => LeadActivity::TYPE_CANCELLED,
                    'from_status_id' => $lead->lead_status_id,
                    'to_status_id' => $cancelledStatusId,
                    'note' => 'Cancelado automaticamente: telefone duplicado.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
