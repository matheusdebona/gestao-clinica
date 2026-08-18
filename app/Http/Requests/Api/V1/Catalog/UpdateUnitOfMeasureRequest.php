<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $unitId = $this->route('unit_of_measure')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'symbol' => [
                'sometimes',
                'string',
                'max:32',
                Rule::unique('units_of_measure', 'symbol')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($unitId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
