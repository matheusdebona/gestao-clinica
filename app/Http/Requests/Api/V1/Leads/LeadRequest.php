<?php

namespace App\Http\Requests\Api\V1\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => preg_replace('/\D+/', '', (string) $this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'phone' => [$required, 'string', 'min:10', 'max:13'],
            'client_origin_id' => [
                $required,
                'integer',
                Rule::exists('client_origins', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'lead_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('lead_statuses', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'already_approached' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'phone.required' => 'Informe o telefone.',
            'phone.min' => 'Informe DDD e número.',
            'phone.max' => 'Telefone muito longo.',
            'client_origin_id.required' => 'Selecione a origem.',
            'client_origin_id.exists' => 'Origem inválida para esta clínica.',
            'lead_status_id.exists' => 'Status inválido para esta clínica.',
        ];
    }
}
