<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un importe entero, sin decimales, de 0 a $maxDigits dígitos (FASE 2, entrega 2.4;
 * pregunta 21: precios en guaraníes, sin decimales). Llega como entero de JSON o como
 * texto de solo dígitos. Se rechazan los decimales (también "12.0" o 12.0), los
 * negativos, los textos con otros caracteres y los que pasan del máximo, sin usar
 * float: un número de JSON demasiado grande llega como float y se rechaza.
 */
class WholeAmount implements ValidationRule
{
    public function __construct(
        private readonly int $maxDigits = 12
    ) {
    }

    /**
     * El importe como texto de dígitos, sin ceros adelante ("007" → "7"), o null si
     * no es un importe entero válido.
     */
    public static function normalize(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value >= 0 ? (string) $value : null;
        }

        if (! is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        $digits = ltrim($value, '0');

        return $digits === '' ? '0' : $digits;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = self::normalize($value);

        if ($digits === null || strlen($digits) > $this->maxDigits) {
            $fail('El :attribute debe ser un número entero, sin decimales, de 0 a ' . self::maximum($this->maxDigits) . '.');
        }
    }

    /**
     * El máximo con separador de miles: 12 dígitos → "999.999.999.999".
     */
    private static function maximum(int $digits): string
    {
        return strrev(implode('.', str_split(str_repeat('9', $digits), 3)));
    }
}
