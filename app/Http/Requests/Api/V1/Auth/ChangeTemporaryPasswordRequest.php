<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeTemporaryPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temporary_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'different:temporary_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'temporary_password' => 'senha temporária',
            'password' => 'nova senha',
            'password_confirmation' => 'confirmação da nova senha',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'A nova senha deve ser diferente da senha temporária.',
            'password.confirmed' => 'As senhas não coincidem.',
        ];
    }
}
