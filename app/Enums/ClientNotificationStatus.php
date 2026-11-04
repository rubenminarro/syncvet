<?php

namespace App\Enums;

enum ClientNotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::SENT => 'Enviado',
            self::DELIVERED => 'Entregado',
            self::READ => 'Leído',
            self::FAILED => 'Fallido',
            self::SKIPPED => 'Omitido',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
