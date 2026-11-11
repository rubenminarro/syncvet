<?php

namespace App\Enums;

enum ConsultationStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::CLOSED => 'Cerrada',
            self::CANCELLED => 'Anulada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
