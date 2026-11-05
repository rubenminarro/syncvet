<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'A confirmar',
            self::CONFIRMED => 'Confirmado',
            self::CHECKED_IN => 'Fichado',
            self::ATTENDED => 'Atendido',
            self::NO_SHOW => 'Ausente',
            self::CANCELLED => 'Cancelado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
