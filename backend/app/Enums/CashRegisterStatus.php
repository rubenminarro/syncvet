<?php

namespace App\Enums;

enum CashRegisterStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::CLOSED => 'Cerrada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
