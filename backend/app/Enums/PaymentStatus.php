<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case REGISTERED = 'registered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registrado',
            self::CANCELLED => 'Anulado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
