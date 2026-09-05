<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Clinic */
class ClinicBrandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branding = $this->branding();

        return [
            'display_name' => $branding['display_name'],
            'primary_color' => $branding['primary_color'],
            'secondary_color' => $branding['secondary_color'],
            'logo_path' => $branding['logo_path'],
            'has_logo' => filled($branding['logo_path']),
        ];
    }
}
