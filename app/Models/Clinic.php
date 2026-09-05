<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'document',
        'phone',
        'email',
        'address',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return array{display_name: string|null, primary_color: string, secondary_color: string, logo_path: string|null}
     */
    public function branding(): array
    {
        $branding = is_array($this->settings['branding'] ?? null)
            ? $this->settings['branding']
            : [];

        return [
            'display_name' => $branding['display_name'] ?? $this->name,
            'primary_color' => $branding['primary_color'] ?? '#0F766E',
            'secondary_color' => $branding['secondary_color'] ?? '#134E4A',
            'logo_path' => $branding['logo_path'] ?? null,
        ];
    }

    /**
     * @param  array{display_name?: string|null, primary_color?: string, secondary_color?: string, logo_path?: string|null}  $branding
     */
    public function updateBranding(array $branding): void
    {
        $settings = $this->settings ?? [];
        $current = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];
        $settings['branding'] = array_merge($current, $branding);
        $this->settings = $settings;
        $this->save();
    }
}
