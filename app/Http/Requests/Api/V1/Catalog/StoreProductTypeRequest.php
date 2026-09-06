<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $brandId = $this->integer('brand_id') ?: null;

        return [
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_types', 'name')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('brand_id', $brandId)
                ),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_types', 'slug')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('brand_id', $brandId)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
