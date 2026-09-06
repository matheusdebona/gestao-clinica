<?php

namespace App\Support;

use App\Models\ProductType;
use Illuminate\Validation\Validator;

class ProductTypeBrandValidator
{
    public static function assert(Validator $validator, ?int $brandId, ?int $typeId): void
    {
        if ($brandId === null || $typeId === null) {
            return;
        }

        $matches = ProductType::query()
            ->whereKey($typeId)
            ->where('brand_id', $brandId)
            ->exists();

        if (! $matches) {
            $validator->errors()->add('product_type_id', 'O tipo deve pertencer à marca selecionada.');
        }
    }
}
