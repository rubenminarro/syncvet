<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\PersonName;
use App\Support\UserPassword;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Reglas y mensajes compartidos por el alta y la edición de usuarios (FASE 2,
 * entrega 2.2a). El email único (en toda la base, también entre eliminados) y el
 * último administrador los revisa UserService con la clínica bloqueada.
 */
class UserMessages
{
    /**
     * El email es el login y queda lugar en la columna (255) para el sufijo de baja.
     */
    public const EMAIL_MAX = 150;

    public const EMAIL_TAKEN = 'Ya hay un usuario con ese email.';

    public const BRANCH_NOT_AVAILABLE = 'La sucursal no existe o está inactiva.';

    /**
     * La contraseña propia no se cambia desde la edición de usuarios.
     */
    public const OWN_PASSWORD = 'Para cambiar tu contraseña usá tu perfil.';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function nameRules(): array
    {
        return [
            'first_name' => ['string', 'min:2', 'max:100', 'regex:' . PersonName::REGEX],
            'last_name' => ['string', 'min:2', 'max:100', 'regex:' . PersonName::REGEX],
        ];
    }

    /**
     * Solo ASCII, como el login (la collation de MySQL iguala "ā" y "a").
     *
     * @return array<int, string>
     */
    public static function emailRules(): array
    {
        return ['string', 'ascii', config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc', 'max:' . self::EMAIL_MAX];
    }

    /**
     * Uno o más roles de la clínica, sin repetidos (P3).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function roleRules(): array
    {
        return [
            'roles' => ['array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(UserRole::values())],
        ];
    }

    /**
     * Una o más sucursales activas de la clínica, sin repetidas. Al editar se acepta
     * también una que el usuario ya tiene aunque se haya inactivado (el formulario la
     * reenvía).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function branchRules(?User $user = null): array
    {
        $kept = $user?->branches()->pluck('branches.id')->all() ?? [];

        return [
            'branch_ids' => ['array', 'min:1'],
            'branch_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('branches', 'id')
                    ->where('clinic_id', CurrentClinic::id())
                    ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $kept)),
            ],
        ];
    }

    /**
     * La contraseña, con la regla del proyecto (UserPassword, R-009).
     *
     * @return array<int, mixed>
     */
    public static function passwordRules(): array
    {
        return array_values(array_filter(UserPassword::rules(), fn (mixed $rule) => $rule !== 'required'));
    }

    /**
     * En puntos (Arr::dot): anidado, el comodín de "roles.*" también coincidiría con
     * "roles.required" y una lista vacía respondería el mensaje de un elemento.
     */
    public static function messages(): array
    {
        return Arr::dot([
            'first_name' => [
                'required' => 'El nombre es obligatorio.',
                'string' => 'El nombre debe tener el formato correcto.',
                'min' => 'El nombre debe tener al menos 2 caracteres.',
                'max' => 'El nombre no debe tener más de 100 caracteres.',
                'regex' => 'El nombre solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
            ],
            'last_name' => [
                'required' => 'El apellido es obligatorio.',
                'string' => 'El apellido debe tener el formato correcto.',
                'min' => 'El apellido debe tener al menos 2 caracteres.',
                'max' => 'El apellido no debe tener más de 100 caracteres.',
                'regex' => 'El apellido solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
            ],
            'email' => [
                'required' => 'El email es obligatorio.',
                'string' => 'El email debe tener el formato correcto.',
                'ascii' => 'El email no puede tener acentos ni caracteres especiales.',
                'email' => 'El email debe tener el formato correcto.',
                'max' => 'El email no debe tener más de ' . self::EMAIL_MAX . ' caracteres.',
            ],
            ...UserPassword::messages(),
            'roles' => [
                'required' => 'Elegí al menos un rol.',
                'array' => 'Los roles deben ser una lista.',
                'min' => 'Elegí al menos un rol.',
            ],
            'roles.*' => [
                'required' => 'El rol no es válido.',
                'string' => 'El rol no es válido.',
                'distinct' => 'Un rol está repetido.',
                'in' => 'El rol no es válido: los roles son ' . implode(', ', UserRole::values()) . '.',
            ],
            'branch_ids' => [
                'required' => 'Elegí al menos una sucursal.',
                'array' => 'Las sucursales deben ser una lista.',
                'min' => 'Elegí al menos una sucursal.',
            ],
            'branch_ids.*' => [
                'required' => self::BRANCH_NOT_AVAILABLE,
                'integer' => self::BRANCH_NOT_AVAILABLE,
                'distinct' => 'Una sucursal está repetida.',
                'exists' => self::BRANCH_NOT_AVAILABLE,
            ],
        ]);
    }
}
