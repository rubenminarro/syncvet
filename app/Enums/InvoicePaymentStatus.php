<?php

namespace App\Enums;

enum InvoicePaymentStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PARTIAL => 'Pago parcial',
            self::PAID => 'Pagada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
