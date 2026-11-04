<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CHECK = 'check';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case TRANSFER = 'transfer';
    case E_WALLET = 'e_wallet';
    case QR = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Efectivo',
            self::CHECK => 'Cheque',
            self::CREDIT_CARD => 'Tarjeta de crédito',
            self::DEBIT_CARD => 'Tarjeta de débito',
            self::TRANSFER => 'Transferencia',
            self::E_WALLET => 'Billetera electrónica',
            self::QR => 'Pago con QR',
        };
    }

    /**
     * iTiPago del Manual Técnico SIFEN (verificar contra la versión vigente).
     */
    public function sifenCode(): int
    {
        return match ($this) {
            self::CASH => 1,
            self::CHECK => 2,
            self::CREDIT_CARD => 3,
            self::DEBIT_CARD => 4,
            self::TRANSFER => 5,
            self::E_WALLET => 7,
            self::QR => 21,
        };
    }

    /**
     * Si suma al efectivo esperado en el cierre de caja.
     */
    public function countsAsCash(): bool
    {
        return $this === self::CASH;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
