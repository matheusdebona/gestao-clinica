<?php

namespace App\Http\Requests\Api\V1\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNotificationRequest extends FormRequest
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
            'unread' => ['sometimes', 'nullable', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(['stock', 'agenda'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Categoria inválida. Use stock ou agenda.',
        ];
    }
}
