<?php

namespace App\Enums;

/**
 * Roles de una clínica (FASE 2, entrega 2.2a): la lista cerrada que se asigna a un
 * usuario (P3: uno o más). Los roles y sus permisos viven en Spatie
 * (RolesAndPermissionsSeeder); el valor es su nombre.
 */
enum UserRole: string
{
    case ADMINISTRATOR = 'administrador';
    case RECEPTIONIST = 'recepcionista';
    case VETERINARIAN = 'veterinario';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::RECEPTIONIST => 'Recepcionista',
            self::VETERINARIAN => 'Veterinario',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
