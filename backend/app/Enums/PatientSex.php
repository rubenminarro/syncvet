<?php

namespace App\Enums;

enum PatientSex: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Macho',
            self::FEMALE => 'Hembra',
            self::UNKNOWN => 'Sin determinar',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
