<?php

namespace App\Http\Requests\Api\V1\Appointments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
