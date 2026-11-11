<?php

namespace App\Enums;

/**
 * Tasas de IVA de Paraguay.
 */
enum TaxRate: int
{
    case EXEMPT = 0;
    case FIVE = 5;
    case TEN = 10;

    public function label(): string {
        return match($this) {
            self::EXEMPT => 'Exenta',
            self::FIVE => 'IVA 5%',
            self::TEN => 'IVA 10%',
        };
    }

    /**
     * Divisor de liquidación sobre precios con IVA incluido (10% → 11, 5% → 21).
     */
    public function liquidationDivisor(): ?string
    {
        return match($this) {
            self::EXEMPT => null,
            self::FIVE => '21',
            self::TEN => '11',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
