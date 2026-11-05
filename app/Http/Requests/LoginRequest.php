<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login con email y contraseña (público).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Ingresá tu email.',
            'email.string' => 'Ingresá un email válido.',
            'email.email' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede tener más de 255 caracteres.',
            'password.required' => 'Ingresá tu contraseña.',
            'password.string' => 'Ingresá una contraseña válida.',
        ];
    }
}
