<?php

namespace App\Http\Requests\Api\V1\Treatments;

use App\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Treatment::STATUSES)],
            'client_id' => ['sometimes', 'nullable', 'integer'],
            'sale_id' => ['sometimes', 'nullable', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status de tratamento inválido.',
        ];
    }
}
