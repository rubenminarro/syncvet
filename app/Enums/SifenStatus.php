<?php

namespace App\Enums;

enum SifenStatus: string
{
    case NOT_SENT = 'not_sent';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case APPROVED_WITH_OBSERVATION = 'approved_with_observation';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SENT => 'Sin enviar',
            self::QUEUED => 'En cola',
            self::SENT => 'Enviada',
            self::APPROVED => 'Aprobada',
            self::APPROVED_WITH_OBSERVATION => 'Aprobada con observación',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Cancelada en SIFEN',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
