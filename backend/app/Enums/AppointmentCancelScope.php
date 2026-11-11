<?php

namespace App\Enums;

/**
 * Alcance de la cancelación de un turno (FASE 3, entrega 3.3): solo este turno, este y los
 * siguientes de su serie, o toda la serie. En un turno sin serie, los tres son lo mismo.
 */
enum AppointmentCancelScope: string
{
    case SINGLE = 'single';
    case FOLLOWING = 'following';
    case SERIES = 'series';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Solo este turno',
            self::FOLLOWING => 'Este y los siguientes de la serie',
            self::SERIES => 'Toda la serie',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
