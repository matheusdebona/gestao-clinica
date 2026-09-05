<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\CardBrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        /** @var CardBrand $brand */
        $brand = $this->route('card_brand');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('card_brands', 'code')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($brand->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
