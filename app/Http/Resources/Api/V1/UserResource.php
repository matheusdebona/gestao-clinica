<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'clinic_id' => $this->clinic_id,
            'clinic' => ClinicResource::make($this->whenLoaded('clinic')),
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->values()),
            'permissions' => $this->when(
                $this->relationLoaded('permissions') || $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()->pluck('name')->values()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
