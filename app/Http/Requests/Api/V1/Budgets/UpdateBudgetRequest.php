<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
