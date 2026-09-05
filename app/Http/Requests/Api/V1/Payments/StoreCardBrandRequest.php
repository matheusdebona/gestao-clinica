<?php

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('card_brands', 'code')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
