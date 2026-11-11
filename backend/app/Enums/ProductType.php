<?php

namespace App\Enums;

enum ProductType: string
{
    case MEDICINE = 'medicine';
    case VACCINE = 'vaccine';
    case ANTIPARASITIC = 'antiparasitic';
    case SUPPLY = 'supply';
    case FOOD = 'food';
    case ACCESSORY = 'accessory';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEDICINE => 'Medicamento',
            self::VACCINE => 'Vacuna',
            self::ANTIPARASITIC => 'Antiparasitario',
            self::SUPPLY => 'Insumo',
            self::FOOD => 'Alimento',
            self::ACCESSORY => 'Accesorio',
            self::OTHER => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
