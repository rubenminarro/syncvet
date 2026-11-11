<?php

namespace App\Enums;

enum CashMovementType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Egreso',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
