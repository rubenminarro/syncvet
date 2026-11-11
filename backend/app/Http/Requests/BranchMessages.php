<?php

namespace App\Http\Requests;

/**
 * Reglas y mensajes compartidos por el alta y la edición de sucursales y depósitos
 * (FASE 2, entrega 2.1). Los únicos (código, nombre y establecimiento) los revisa el
 * Service con la fila de la clínica bloqueada (BranchService, WarehouseService).
 */
class BranchMessages
{
    /**
     * Código que escribe el administrador (pregunta 16): letras sin acentos, números y
     * guiones. Se compara después de pasarlo a mayúsculas (prepareForValidation).
     */
    public const CODE_REGEX = '/^[A-Z0-9-]+$/';

    /**
     * Código de establecimiento SIFEN (dEst): tres dígitos, de 001 a 999.
     */
    public const ESTABLISHMENT_REGEX = '/^(?!000)\d{3}$/';

    /**
     * Teléfono con la regla de clientes.
     */
    public const PHONE_REGEX = '/^\+?[1-9]\d{7,14}$/';

    /**
     * Dirección con los caracteres de la de clientes.
     */
    public const ADDRESS_REGEX = '/^[\pL\pN\s.,#\-\/°º&()\']+$/u';

    public const DEFAULT_WAREHOUSE_NAME = 'Principal';

    /**
     * Los códigos que llegan como texto, en mayúsculas y sin espacios en los extremos,
     * para validarlos y compararlos como se guardan.
     *
     * @param array<string, mixed> $input
     * @param list<string> $fields
     * @return array<string, string>
     */
    public static function upperCodes(array $input, array $fields): array
    {
        $codes = [];

        foreach ($fields as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $codes[$field] = strtoupper(trim($input[$field]));
            }
        }

        return $codes;
    }

    public static function code(): array
    {
        return ['string', 'max:20', 'regex:' . self::CODE_REGEX];
    }

    public static function name(): array
    {
        return ['string', 'min:2', 'max:100', 'regex:' . CatalogMessages::NAME_REGEX];
    }

    public static function branch(): array
    {
        return [
            ...self::codeMessages('code', 'El código de la sucursal'),
            ...self::nameMessages('name', 'El nombre de la sucursal'),
            'establishment' => [
                'required' => 'El código de establecimiento es obligatorio.',
                'string' => 'El código de establecimiento debe tener el formato correcto.',
                'regex' => 'El código de establecimiento debe tener tres dígitos, de 001 a 999.',
            ],
            'address' => [
                'string' => 'La dirección debe tener el formato correcto.',
                'max' => 'La dirección no debe tener más de 255 caracteres.',
                'regex' => 'La dirección contiene caracteres no permitidos.',
            ],
            'phone' => [
                'string' => 'El teléfono debe tener el formato correcto.',
                'regex' => 'El teléfono debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
            ],
            'is_default.boolean' => 'El campo por defecto debe ser verdadero o falso.',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso.',
            ...self::codeMessages('warehouse_code', 'El código del depósito'),
            ...self::nameMessages('warehouse_name', 'El nombre del depósito'),
        ];
    }

    public static function warehouse(): array
    {
        return [
            ...self::codeMessages('code', 'El código del depósito'),
            ...self::nameMessages('name', 'El nombre del depósito'),
            'is_default.boolean' => 'El campo por defecto debe ser verdadero o falso.',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso.',
            'branch_id.prohibited' => 'Un depósito no cambia de sucursal: creá uno nuevo en la otra sucursal.',
        ];
    }

    private static function codeMessages(string $field, string $label): array
    {
        return [
            $field => [
                'required' => "{$label} es obligatorio.",
                'string' => "{$label} debe tener el formato correcto.",
                'max' => "{$label} no debe tener más de 20 caracteres.",
                'regex' => "{$label} solo puede contener letras sin acentos, números y guiones.",
            ],
        ];
    }

    private static function nameMessages(string $field, string $label): array
    {
        return [
            $field => [
                'required' => "{$label} es obligatorio.",
                'string' => "{$label} debe tener el formato correcto.",
                'min' => "{$label} debe tener al menos 2 caracteres.",
                'max' => "{$label} no debe tener más de 100 caracteres.",
                'regex' => "{$label} debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.",
            ],
        ];
    }
}
