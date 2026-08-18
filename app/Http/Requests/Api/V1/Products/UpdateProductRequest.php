<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $productId = $this->route('product')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($productId),
            ],
            'product_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('product_types', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'brand_id' => [
                'sometimes',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'unit_of_measure_id' => [
                'sometimes',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'purpose' => ['nullable', 'string'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'min_sale_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
