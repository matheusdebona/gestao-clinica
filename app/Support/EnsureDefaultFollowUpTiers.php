<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\LeadFollowUpTier;
use Illuminate\Support\Facades\DB;

class EnsureDefaultFollowUpTiers
{
    /** @var list<array{days: int, color: string}> */
    public const DEFAULTS = [
        ['days' => 3, 'color' => 'warning'],
        ['days' => 7, 'color' => 'warning'],
        ['days' => 30, 'color' => 'danger'],
        ['days' => 60, 'color' => 'danger'],
    ];

    /** Seeds the defaults only for a clinic that never had tiers (creation / migration). */
    public static function run(Clinic|int $clinic): void
    {
        $clinicId = $clinic instanceof Clinic ? $clinic->id : $clinic;

        if (DB::table('lead_follow_up_tiers')->where('clinic_id', $clinicId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $row) {
            LeadFollowUpTier::query()->create(['clinic_id' => $clinicId, ...$row]);
        }
    }

    public static function runAll(): void
    {
        Clinic::query()->orderBy('id')->pluck('id')->each(fn (int $id) => self::run($id));
    }
}
