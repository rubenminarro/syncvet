<?php

namespace App\Http\Requests;

use App\Enums\VeterinarianStatus;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Reglas y mensajes compartidos por el alta y la edición de veterinarios (FASE 2,
 * entrega 2.3). El rol veterinario del usuario y su ficha (activa o eliminada) los
 * revisa VeterinarianService con el usuario bloqueado.
 */
class VeterinarianMessages
{
    /**
     * La columna admite 60: la matrícula más el sufijo de baja entero (pregunta 29).
     */
    public const LICENSE_MAX = 30;

    public const USER_NOT_AVAILABLE = 'El usuario no existe o está inactivo.';

    public const USER_WITHOUT_ROLE = 'El usuario no tiene el rol veterinario.';

    public const USER_ALREADY_VETERINARIAN = 'Ese usuario ya es veterinario.';

    public const LICENSE_TAKEN = 'Ya hay un veterinario con esa matrícula.';

    /**
     * La matrícula como se guarda y se compara: en mayúsculas y sin espacios en los
     * extremos ("mv-1001" es la misma que "MV-1001" en SQLite y en MySQL). Vacía, nula.
     * Otro tipo de valor llega igual a la validación, que lo rechaza.
     */
    public static function normalizeLicense(mixed $license): mixed
    {
        if (! is_string($license)) {
            return $license;
        }

        $license = mb_strtoupper(trim($license));

        return $license === '' ? null : $license;
    }

    /**
     * El pedido con la matrícula normalizada, para que unique compare el valor que se
     * guarda (alta, restauración y edición).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalizedInput(array $input): array
    {
        return array_key_exists('license_number', $input)
            ? ['license_number' => self::normalizeLicense($input['license_number'])]
            : [];
    }

    /**
     * Los datos de la ficha. En la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function dataRules(?Veterinarian $ignore = null): array
    {
        return [
            'code' => ['prohibited'],
            'license_number' => ['nullable', 'string', 'max:' . self::LICENSE_MAX, self::uniqueLicense($ignore)],
            'specialty' => ['nullable', 'string', 'max:100'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'status' => ['required', Rule::enum(VeterinarianStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Única en la clínica. Las fichas eliminadas llevan el sufijo de baja: no chocan.
     * El índice único es el respaldo de dos pedidos simultáneos (VeterinarianService).
     */
    private static function uniqueLicense(?Veterinarian $ignore): mixed
    {
        $rule = Rule::unique('veterinarians', 'license_number')->where('clinic_id', CurrentClinic::id());

        return $ignore === null ? $rule : $rule->ignore($ignore->getKey());
    }

    public static function messages(): array
    {
        return [
            'user_id.required' => 'Elegí el usuario del veterinario.',
            'user_id.integer' => self::USER_NOT_AVAILABLE,
            'user_id.exists' => self::USER_NOT_AVAILABLE,
            'user_id.prohibited' => 'El usuario del veterinario no se cambia.',
            'code.prohibited' => 'El código del veterinario se asigna automáticamente.',
            'license_number.string' => 'La matrícula debe tener el formato correcto.',
            'license_number.max' => 'La matrícula no debe tener más de ' . self::LICENSE_MAX . ' caracteres.',
            'license_number.unique' => self::LICENSE_TAKEN,
            'commission_percentage.required' => 'La comisión es obligatoria.',
            'commission_percentage.numeric' => 'La comisión debe ser un número.',
            'commission_percentage.min' => 'La comisión debe estar entre 0 y 100.',
            'commission_percentage.max' => 'La comisión debe estar entre 0 y 100.',
            'commission_percentage.decimal' => 'La comisión puede tener hasta 2 decimales.',
            'status.required' => 'El estado es obligatorio.',
            'status.' . Enum::class => 'El estado no es válido: debe ser active (activo) o inactive (inactivo).',
        ];
    }
}
