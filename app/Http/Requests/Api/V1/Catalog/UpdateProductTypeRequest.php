<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $typeId = $this->route('product_type')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_types', 'slug')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($typeId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
