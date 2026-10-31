<?php

namespace App\Http\Requests\Api\V1\Leads;

use App\Models\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $status = $this->route('lead_status');
        $ignoreId = $status instanceof LeadStatus ? $status->id : null;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required,
                'string',
                'max:60',
                Rule::unique('lead_statuses', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($ignoreId),
            ],
            'color' => [$required, 'string', Rule::in(LeadStatus::COLORS)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.unique' => 'Já existe um status com esse nome.',
            'color.in' => 'Cor inválida.',
        ];
    }
}
