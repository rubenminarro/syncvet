<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un usuario de la clínica con sus roles y sucursales (FASE 2, entrega 2.2a).
 * clinic_id no se recibe: lo fija UserService. El email único lo revisa el Service.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $names = UserMessages::nameRules();
        $roles = UserMessages::roleRules();
        $branches = UserMessages::branchRules();

        return [
            'first_name' => ['required', ...$names['first_name']],
            'last_name' => ['required', ...$names['last_name']],
            'email' => ['required', ...UserMessages::emailRules()],
            'password' => ['required', ...UserMessages::passwordRules()],
            'roles' => ['required', ...$roles['roles']],
            'roles.*' => $roles['roles.*'],
            'branch_ids' => ['required', ...$branches['branch_ids']],
            'branch_ids.*' => $branches['branch_ids.*'],
        ];
    }

    public function messages(): array
    {
        return UserMessages::messages();
    }
}
