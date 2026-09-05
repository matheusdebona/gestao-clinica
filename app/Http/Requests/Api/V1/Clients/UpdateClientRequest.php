<?php

namespace App\Http\Requests\Api\V1\Clients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'whatsapp' => ['sometimes', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'main_pains' => ['nullable', 'string'],
            'service_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
