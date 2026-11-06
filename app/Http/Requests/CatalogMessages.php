<?php

namespace App\Http\Requests;

/**
 * Mensajes compartidos por el alta y la edición de especies y razas (FASE 1,
 * entrega 1.4). El nombre repetido lo informa el Service, con el bloqueo de la
 * clínica tomado (SpeciesService::NAME_TAKEN, BreedService::NAME_TAKEN).
 */
class CatalogMessages
{
    /**
     * Letras (con acentos), números, espacios, puntos, guiones, apóstrofos y
     * paréntesis: "Pastor Alemán", "Cuarto de Milla", "Shih Tzu". Sin "/", que es
     * del sufijo de baja.
     */
    public const NAME_REGEX = '/^[\pL\pN\s.\-\'()]+$/u';

    public static function species(): array
    {
        return self::for('El nombre de la especie', 60);
    }

    public static function breed(): array
    {
        return [
            ...self::for('El nombre de la raza', 80),
            'species_id.prohibited' => 'La especie de una raza no se cambia: agregá la raza en la otra especie.',
        ];
    }

    private static function for(string $name, int $max): array
    {
        return [
            'name' => [
                'required' => "{$name} es obligatorio.",
                'string' => "{$name} debe tener el formato correcto.",
                'min' => "{$name} debe tener al menos 2 caracteres.",
                'max' => "{$name} no debe tener más de {$max} caracteres.",
                'regex' => "{$name} solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.",
            ],
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso.',
        ];
    }
}
