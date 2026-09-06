<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Support\EnsureRolesAndPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::in(EnsureRolesAndPermissions::ASSIGNABLE_ROLES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'roles' => 'papéis',
        ];
    }
}
