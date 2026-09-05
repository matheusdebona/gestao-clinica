<?php

namespace App\Http\Requests\Api\V1\ClientOrigins;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientOriginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $originId = $this->route('client_origin')?->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('client_origins', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($originId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
