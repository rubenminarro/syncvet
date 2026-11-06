<?php

namespace App\Rules;

use App\Services\ClinicService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Dígito verificador del RUC paraguayo (R-015; FASE 1, entrega 1.2). Solo se
 * valida un documento con la forma de un RUC, "número-dígito" (NNNNNNN-D): una
 * cédula sin guion o un documento con letras no se revisan. El cálculo es el de
 * ClinicService (módulo 11 de la SET).
 */
class RucCheckDigit implements ValidationRule
{
    public const MESSAGE = 'El RUC no es válido: revisá el número y el dígito verificador.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d+)-(\d)$/', $value, $parts)) {
            return;
        }

        if (ClinicService::rucCheckDigit($parts[1]) !== (int) $parts[2]) {
            $fail(self::MESSAGE);
        }
    }
}
