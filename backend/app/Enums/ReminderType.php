<?php

namespace App\Enums;

enum ReminderType: string
{
    case VACCINE = 'vaccine';
    case DEWORMING = 'deworming';
    case MEDICATION = 'medication';
    case CHECKUP = 'checkup';
    case APPOINTMENT = 'appointment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VACCINE => 'Vacuna',
            self::DEWORMING => 'Desparasitación',
            self::MEDICATION => 'Medicamento',
            self::CHECKUP => 'Control',
            self::APPOINTMENT => 'Turno',
            self::OTHER => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
