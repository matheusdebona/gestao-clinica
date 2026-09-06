<?php

namespace App\Http\Requests\Api\V1\Products;

use App\Support\ProductTypeBrandValidator;
use Illuminate\Contracts\Validation\Validator;
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
            'lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            $brandId = $this->has('brand_id') ? $this->integer('brand_id') : $product?->brand_id;
            $typeId = $this->has('product_type_id') ? $this->integer('product_type_id') : $product?->product_type_id;

            ProductTypeBrandValidator::assert(
                $validator,
                $brandId ? (int) $brandId : null,
                $typeId ? (int) $typeId : null,
            );
        });
    }
}
