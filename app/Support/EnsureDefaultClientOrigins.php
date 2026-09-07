<?php

namespace App\Support;

use App\Models\ClientOrigin;
use App\Models\Clinic;

class EnsureDefaultClientOrigins
{
    /** @var list<string> */
    public const NAMES = [
        'Google',
        'Instagram',
        'Facebook',
        'TikTok',
        'Indicação',
        'WhatsApp',
        'Outros',
        'Site',
        'YouTube',
        'Fachada',
    ];

    public static function run(Clinic $clinic): void
    {
        $previous = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            foreach (self::NAMES as $name) {
                ClientOrigin::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'name' => $name,
                    ],
                    [
                        'is_active' => true,
                    ]
                );
            }
        } finally {
            CurrentClinic::setId($previous);
        }
    }
}
