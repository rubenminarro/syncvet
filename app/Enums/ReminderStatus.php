<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case PENDING = 'pending';
    case NOTIFIED = 'notified';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::NOTIFIED => 'Avisado',
            self::COMPLETED => 'Cumplido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
