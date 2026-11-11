<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case RECEPTION = 'reception';
    case PHONE = 'phone';
    case WHATSAPP = 'whatsapp';
    case ONLINE = 'online';

    public function label(): string
    {
        return match ($this) {
            self::RECEPTION => 'Recepción',
            self::PHONE => 'Teléfono',
            self::WHATSAPP => 'WhatsApp',
            self::ONLINE => 'Reserva online',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
