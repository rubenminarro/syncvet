<?php

namespace App\Enums;

enum DocumentTemplateType: string
{
    case PRESCRIPTION = 'prescription';
    case CERTIFICATE = 'certificate';
    case LAB_ORDER = 'lab_order';
    case AUTHORIZATION = 'authorization';
    case VACCINATION_RECORD = 'vaccination_record';
    case DEWORMING_CERTIFICATE = 'deworming_certificate';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PRESCRIPTION => 'Receta',
            self::CERTIFICATE => 'Certificado',
            self::LAB_ORDER => 'Orden de análisis',
            self::AUTHORIZATION => 'Autorización',
            self::VACCINATION_RECORD => 'Constancia de vacunación',
            self::DEWORMING_CERTIFICATE => 'Certificado de desparasitación',
            self::OTHER => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
