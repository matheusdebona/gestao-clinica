<?php

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardOperatorRequest extends FormRequest
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
                Rule::unique('card_operators', 'name')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'auto_anticipate' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
