<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $brandId = $this->route('brand')?->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('brands', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($brandId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
