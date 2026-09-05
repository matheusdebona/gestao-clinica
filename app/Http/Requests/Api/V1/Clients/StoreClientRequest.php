<?php

namespace App\Http\Requests\Api\V1\Clients;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'main_pains' => ['nullable', 'string'],
            'service_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
