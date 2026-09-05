<?php

namespace App\Http\Requests\Api\V1\ClientOrigins;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientOriginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_origins', 'name')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
