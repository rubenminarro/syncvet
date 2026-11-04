<?php

namespace App\Enums;

/**
 * Series de códigos internos (no fiscales). El valor es la clave en
 * document_sequences. Una serie nueva se agrega aquí.
 */
enum DocumentSeries: string
{
    case PATIENT = 'patient';
    case CONSULTATION = 'consultation';
    case SERVICE = 'service';
    case PRODUCT = 'product';
    case VETERINARIAN = 'veterinarian';

    public function prefix(): string
    {
        return match ($this) {
            self::PATIENT => 'PAC-',
            self::CONSULTATION => 'CON-',
            self::SERVICE => 'SRV-',
            self::PRODUCT => 'PRO-',
            self::VETERINARIAN => 'VET-',
        };
    }

    /**
     * Tabla de los documentos de la serie (columna única `code`).
     */
    public function table(): string
    {
        return match ($this) {
            self::PATIENT => 'patients',
            self::CONSULTATION => 'consultations',
            self::SERVICE => 'services',
            self::PRODUCT => 'products',
            self::VETERINARIAN => 'veterinarians',
        };
    }
}
