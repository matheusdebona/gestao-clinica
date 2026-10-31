<?php

namespace App\Http\Requests\Api\V1\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'scheduled_at' => ['required', 'date'],
            'professional_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'notes' => ['sometimes', 'nullable', 'string'],
            // Link to an existing patient (same phone) instead of creating a new one.
            'client_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            // Explicitly create a new patient even when one with the same phone exists.
            'create_new_client' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.required' => 'Informe data e hora.',
            'professional_user_id.required' => 'Selecione o profissional.',
            'professional_user_id.exists' => 'Profissional inválido para esta clínica.',
            'client_id.exists' => 'Paciente inválido para esta clínica.',
        ];
    }
}
