<?php

namespace App\Enums;

enum ClientKind: string
{
    case PERSON = 'person';
    case COMPANY = 'company';

    public function label(): string
    {
        return match ($this) {
            self::PERSON => 'Persona',
            self::COMPANY => 'Empresa',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
