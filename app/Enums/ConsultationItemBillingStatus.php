<?php

namespace App\Enums;

enum ConsultationItemBillingStatus: string
{
    case PENDING = 'pending';
    case INVOICED = 'invoiced';
    case NO_CHARGE = 'no_charge';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente de cobro',
            self::INVOICED => 'Facturado',
            self::NO_CHARGE => 'Sin cargo',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
