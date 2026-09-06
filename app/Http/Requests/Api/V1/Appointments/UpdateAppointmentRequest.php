<?php

namespace App\Http\Requests\Api\V1\Appointments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
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
        $clinicId = $this->user()?->clinic_id;

        return [
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'professional_user_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'professional_user_id.required' => 'Selecione o profissional.',
            'professional_user_id.exists' => 'Profissional inválido para esta clínica.',
            'duration_minutes.min' => 'Informe a duração em minutos (1 a 1440).',
            'duration_minutes.max' => 'Informe a duração em minutos (1 a 1440).',
        ];
    }
}
