<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case PURCHASE = 'purchase';
    case CONSUMPTION = 'consumption';
    case SALE = 'sale';
    case RETURN = 'return';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case EXPIRATION = 'expiration';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra',
            self::CONSUMPTION => 'Consumo en consulta',
            self::SALE => 'Venta',
            self::RETURN => 'Devolución',
            self::ADJUSTMENT_IN => 'Ajuste positivo',
            self::ADJUSTMENT_OUT => 'Ajuste negativo',
            self::EXPIRATION => 'Baja por vencimiento',
            self::TRANSFER_IN => 'Transferencia recibida',
            self::TRANSFER_OUT => 'Transferencia enviada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
