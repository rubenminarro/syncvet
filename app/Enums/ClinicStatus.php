<?php

namespace App\Enums;

/**
 * Estado de una clínica. Una clínica suspendida no opera (CLINIC_NOT_AVAILABLE),
 * pero conserva sus datos: no se elimina.
 */
enum ClinicStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::SUSPENDED => 'Suspendida',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
