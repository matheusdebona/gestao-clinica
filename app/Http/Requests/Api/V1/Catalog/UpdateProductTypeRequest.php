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
        $type = $this->route('product_type');
        $typeId = $type?->id;
        $brandId = $this->integer('brand_id') ?: $type?->brand_id;

        return [
            'brand_id' => [
                'sometimes',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_types', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)->where('brand_id', $brandId))
                    ->ignore($typeId),
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_types', 'slug')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)->where('brand_id', $brandId))
                    ->ignore($typeId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
