<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case CONSULTATION = 'consultation';
    case VACCINATION = 'vaccination';
    case SURGERY = 'surgery';
    case LABORATORY = 'laboratory';
    case IMAGING = 'imaging';
    case HOSPITALIZATION = 'hospitalization';
    case GROOMING = 'grooming';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CONSULTATION => 'Consulta',
            self::VACCINATION => 'Vacunación',
            self::SURGERY => 'Cirugía',
            self::LABORATORY => 'Laboratorio',
            self::IMAGING => 'Imágenes',
            self::HOSPITALIZATION => 'Internación',
            self::GROOMING => 'Peluquería',
            self::OTHER => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
