<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
