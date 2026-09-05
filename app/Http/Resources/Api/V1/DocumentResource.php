<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'client_id' => $this->client_id,
            'budget_id' => $this->budget_id,
            'sale_id' => $this->sale_id,
            'type' => $this->type,
            'status' => $this->status,
            'storage_path' => $this->storage_path,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'payload' => $this->payload,
            'generated_by_user_id' => $this->generated_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
