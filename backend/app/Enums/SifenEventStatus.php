<?php

namespace App\Enums;

enum SifenEventStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::APPROVED => 'Aprobado',
            self::REJECTED => 'Rechazado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
