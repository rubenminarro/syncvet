<?php

namespace App\Enums;

enum PatientDocumentStatus: string
{
    case ISSUED = 'issued';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Emitido',
            self::CANCELLED => 'Anulado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
