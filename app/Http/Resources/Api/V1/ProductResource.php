<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'purpose' => $this->purpose,
            'product_type_id' => $this->product_type_id,
            'brand_id' => $this->brand_id,
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'product_type' => ProductTypeResource::make($this->whenLoaded('productType')),
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'unit_of_measure' => UnitOfMeasureResource::make($this->whenLoaded('unitOfMeasure')),
            'cost' => $this->cost,
            'sale_price' => $this->sale_price,
            'min_sale_price' => $this->min_sale_price,
            'stock_quantity' => $this->stock_quantity,
            'min_stock' => $this->min_stock,
            'lead_time_days' => $this->lead_time_days,
            'unit_margin' => $this->unitMargin(),
            'unit_margin_percent' => $this->unitMarginPercent(),
            'inventory_value' => $this->inventoryValue(),
            'is_low_stock' => $this->isLowStock(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
