<?php

namespace App\Enums;

enum InvoiceCondition: string
{
    case CASH = 'cash';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Contado',
            self::CREDIT => 'Crédito',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
