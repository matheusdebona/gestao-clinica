<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
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
            'symbol' => [
                'required',
                'string',
                'max:32',
                Rule::unique('units_of_measure', 'symbol')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
