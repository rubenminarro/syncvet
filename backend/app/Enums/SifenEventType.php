<?php

namespace App\Enums;

enum SifenEventType: string
{
    case CANCELLATION = 'cancellation';
    case VOIDING = 'voiding';
    case CONFORMITY = 'conformity';
    case DISCONFORMITY = 'disconformity';
    case DISREGARD = 'disregard';
    case RECEIPT_NOTICE = 'receipt_notice';

    public function label(): string
    {
        return match ($this) {
            self::CANCELLATION => 'Cancelación',
            self::VOIDING => 'Inutilización',
            self::CONFORMITY => 'Conformidad',
            self::DISCONFORMITY => 'Disconformidad',
            self::DISREGARD => 'Desconocimiento',
            self::RECEIPT_NOTICE => 'Notificación de recepción',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
