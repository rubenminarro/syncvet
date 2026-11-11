<?php

namespace App\Enums;

enum ConsultationItemType: string
{
    case SERVICE = 'service';
    case PRODUCT = 'product';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE => 'Servicio',
            self::PRODUCT => 'Producto',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
