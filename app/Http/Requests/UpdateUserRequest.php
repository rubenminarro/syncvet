<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de un usuario (FASE 2, entrega 2.2a): solo se
 * validan los campos enviados. La contraseña es opcional; si llega, se cambia y se
 * revocan los tokens del usuario (pregunta 20). La propia no se cambia por aquí: el
 * usuario la cambia desde su perfil (PATCH me/password, 2.2b), que pide la actual y
 * conserva el token en uso. El último administrador lo revisa UserService.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $names = UserMessages::nameRules();
        $roles = UserMessages::roleRules();
        $branches = UserMessages::branchRules($user instanceof User ? $user : null);

        return [
            'first_name' => ['sometimes', 'required', ...$names['first_name']],
            'last_name' => ['sometimes', 'required', ...$names['last_name']],
            'email' => ['sometimes', 'required', ...UserMessages::emailRules()],
            'password' => $this->editsOwnUser($user)
                // "required" también: "prohibited" deja pasar un valor vacío.
                ? ['sometimes', 'required', 'prohibited']
                : ['sometimes', 'required', ...UserMessages::passwordRules()],
            'roles' => ['sometimes', 'required', ...$roles['roles']],
            'roles.*' => $roles['roles.*'],
            'branch_ids' => ['sometimes', 'required', ...$branches['branch_ids']],
            'branch_ids.*' => $branches['branch_ids.*'],
        ];
    }

    public function messages(): array
    {
        $messages = UserMessages::messages();

        if ($this->editsOwnUser($this->route('user'))) {
            $messages['password.required'] = UserMessages::OWN_PASSWORD;
            $messages['password.prohibited'] = UserMessages::OWN_PASSWORD;
        }

        return $messages;
    }

    /**
     * El usuario editado es el que hace el pedido.
     */
    private function editsOwnUser(mixed $user): bool
    {
        return $user instanceof User && $this->user() instanceof User && $user->is($this->user());
    }
}
