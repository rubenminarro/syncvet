<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'A confirmar',
            self::CONFIRMED => 'Confirmado',
            self::CHECKED_IN => 'Fichado',
            self::ATTENDED => 'Atendido',
            self::NO_SHOW => 'Ausente',
            self::CANCELLED => 'Cancelado',
        };
    }

    /**
     * Ocupa el horario de la agenda (diseño de la FASE 3, decisiones comunes): a confirmar,
     * confirmado, fichado y atendido. Un cancelado o un ausente lo liberan. La entrega 3.1
     * lo usa para los turnos afectados por un bloqueo; la 3.2a, para la superposición.
     */
    public function occupiesSlot(): bool
    {
        return match ($this) {
            self::PENDING_CONFIRMATION, self::CONFIRMED, self::CHECKED_IN, self::ATTENDED => true,
            self::NO_SHOW, self::CANCELLED => false,
        };
    }

    /**
     * Los valores de los estados que ocupan el horario, para las consultas.
     *
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->occupiesSlot())
        ));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
