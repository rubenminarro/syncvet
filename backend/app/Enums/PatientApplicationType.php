<?php

namespace App\Enums;

enum PatientApplicationType: string
{
    case VACCINE = 'vaccine';
    case INTERNAL_DEWORMING = 'internal_deworming';
    case EXTERNAL_DEWORMING = 'external_deworming';
    case MEDICATION = 'medication';

    public function label(): string
    {
        return match ($this) {
            self::VACCINE => 'Vacuna',
            self::INTERNAL_DEWORMING => 'Desparasitación interna',
            self::EXTERNAL_DEWORMING => 'Desparasitación externa',
            self::MEDICATION => 'Medicamento',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
