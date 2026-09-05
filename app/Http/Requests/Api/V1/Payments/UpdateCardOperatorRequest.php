<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\CardOperator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        /** @var CardOperator $operator */
        $operator = $this->route('card_operator');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('card_operators', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($operator->id),
            ],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'auto_anticipate' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
