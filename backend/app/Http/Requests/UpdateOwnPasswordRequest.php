<?php

namespace App\Http\Requests;

use App\Support\UserPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de la contraseña propia (FASE 2, entrega 2.2b; pregunta 20): la actual y
 * la nueva, con la regla del proyecto (UserPassword, R-009) y distinta de la
 * actual. Que la actual sea correcta lo revisa AuthService, con el límite de
 * intentos.
 */
class UpdateOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [...UserPassword::rules(), 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            ...UserPassword::messages(),
            'current_password.required' => 'Ingresá tu contraseña actual.',
            'current_password.string' => 'Ingresá una contraseña válida.',
            'password.different' => 'La contraseña nueva tiene que ser distinta de la actual.',
        ];
    }
}
