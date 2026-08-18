<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'product_type_id' => [
                'required',
                'integer',
                Rule::exists('product_types', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'purpose' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
