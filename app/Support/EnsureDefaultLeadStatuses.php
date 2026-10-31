<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\LeadStatus;

class EnsureDefaultLeadStatuses
{
    /**
     * @var list<array{name: string, color: string, is_default?: bool, system_key?: string}>
     */
    public const DEFAULTS = [
        ['name' => 'Novo', 'color' => 'muted', 'is_default' => true],
        ['name' => 'Em contato', 'color' => 'warning'],
        ['name' => 'Agendado', 'color' => 'purple'],
        ['name' => 'Virou paciente', 'color' => 'success', 'system_key' => LeadStatus::SYSTEM_CONVERTED],
        ['name' => 'Perdido', 'color' => 'danger'],
        ['name' => 'Cancelado', 'color' => 'muted', 'system_key' => LeadStatus::SYSTEM_CANCELLED],
    ];

    /**
     * Seeds defaults only when the clinic has no lead status yet; afterwards only makes
     * sure the system statuses ("converted", "cancelled") and a default status exist. Never edits names.
     */
    public static function run(Clinic|int $clinic): void
    {
        $clinicId = $clinic instanceof Clinic ? $clinic->id : $clinic;
        $previous = CurrentClinic::id();
        CurrentClinic::setId($clinicId);

        try {
            $query = fn () => LeadStatus::query()->where('clinic_id', $clinicId);

            if (! $query()->exists()) {
                foreach (self::DEFAULTS as $index => $row) {
                    LeadStatus::query()->create([
                        'clinic_id' => $clinicId,
                        'name' => $row['name'],
                        'color' => $row['color'],
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                        'is_default' => $row['is_default'] ?? false,
                        'system_key' => $row['system_key'] ?? null,
                    ]);
                }

                return;
            }

            if (! $query()->where('system_key', LeadStatus::SYSTEM_CONVERTED)->exists()) {
                $converted = $query()->whereIn('name', ['Virou paciente', 'Convertido'])->first();
                if ($converted !== null) {
                    $converted->update(['system_key' => LeadStatus::SYSTEM_CONVERTED, 'is_active' => true]);
                } else {
                    LeadStatus::query()->create([
                        'clinic_id' => $clinicId,
                        'name' => 'Virou paciente',
                        'color' => 'success',
                        'sort_order' => ((int) $query()->max('sort_order')) + 10,
                        'system_key' => LeadStatus::SYSTEM_CONVERTED,
                    ]);
                }
            }

            if (! $query()->where('system_key', LeadStatus::SYSTEM_CANCELLED)->exists()) {
                $cancelled = $query()->whereNull('system_key')->where('name', 'Cancelado')->first();
                if ($cancelled !== null) {
                    $cancelled->update(['system_key' => LeadStatus::SYSTEM_CANCELLED, 'is_active' => true, 'is_default' => false]);
                } else {
                    LeadStatus::query()->create([
                        'clinic_id' => $clinicId,
                        'name' => 'Cancelado',
                        'color' => 'muted',
                        'sort_order' => ((int) $query()->max('sort_order')) + 10,
                        'system_key' => LeadStatus::SYSTEM_CANCELLED,
                    ]);
                }
            }

            if (! $query()->where('is_default', true)->where('is_active', true)->exists()) {
                $first = $query()->where('is_active', true)->whereNull('system_key')->orderBy('sort_order')->orderBy('id')->first();
                $first?->update(['is_default' => true]);
            }
        } finally {
            CurrentClinic::setId($previous);
        }
    }

    public static function runAll(): int
    {
        $count = 0;
        foreach (Clinic::query()->pluck('id') as $id) {
            self::run((int) $id);
            $count++;
        }

        return $count;
    }

    public static function defaultFor(int $clinicId): LeadStatus
    {
        self::run($clinicId);

        return LeadStatus::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();
    }

    public static function convertedFor(int $clinicId): LeadStatus
    {
        self::run($clinicId);

        return LeadStatus::query()
            ->where('clinic_id', $clinicId)
            ->where('system_key', LeadStatus::SYSTEM_CONVERTED)
            ->firstOrFail();
    }

    public static function cancelledFor(int $clinicId): LeadStatus
    {
        self::run($clinicId);

        return LeadStatus::query()
            ->where('clinic_id', $clinicId)
            ->where('system_key', LeadStatus::SYSTEM_CANCELLED)
            ->firstOrFail();
    }
}
