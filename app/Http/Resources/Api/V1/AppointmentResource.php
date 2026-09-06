<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class AppointmentResource extends JsonResource
{
    /**
     * @param  array{suggested_consumptions?: list<array<string, mixed>>, stock_warnings?: list<array<string, mixed>>, warnings?: list<string>}|null  $extras
     */
    public function __construct($resource, private readonly ?array $extras = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'treatment_id' => $this->treatment_id,
            'client_id' => $this->client_id,
            'professional_user_id' => $this->professional_user_id,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_minutes' => $this->duration_minutes,
            'total_cost' => $this->total_cost,
            'total_charged_on_appointment' => $this->total_charged_on_appointment,
            'stock_warning' => $this->stock_warning,
            'notes' => $this->notes,
            'consumptions' => AppointmentConsumptionResource::collection($this->whenLoaded('consumptions')),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'professional' => UserResource::make($this->whenLoaded('professionalUser')),
            'treatment' => $this->whenLoaded('treatment', fn () => [
                'id' => $this->treatment->id,
                'status' => $this->treatment->status,
                'sale_id' => $this->treatment->sale_id,
                'client_id' => $this->treatment->client_id,
            ]),
            'suggested_consumptions' => $this->extras['suggested_consumptions'] ?? null,
            'stock_warnings' => $this->extras['stock_warnings'] ?? null,
            'warnings' => $this->extras['warnings'] ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
