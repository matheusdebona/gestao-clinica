<?php

namespace App\Http\Requests\Api\V1\Products;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required_if:type,in', 'nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'loss_reason' => ['nullable', 'prohibited_unless:type,out', Rule::in(StockMovement::LOSS_REASONS)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
