<?php

namespace App\Http\Requests\Api\V1\Protocols;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'suggested_price' => ['sometimes', 'numeric', 'min:0'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'recalculate_from_products' => ['sometimes', 'boolean'],
            'reset_suggested_price' => ['sometimes', 'boolean'],
            'reset_min_price' => ['sometimes', 'boolean'],
        ];
    }
}
