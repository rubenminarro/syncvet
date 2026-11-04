<?php

namespace App\Support;

/**
 * Operaciones decimales exactas sobre strings (BCMath) para
 * cantidades y costos de inventario, evitando errores de float.
 */
class Decimal
{
    /**
     * Normaliza un valor numérico (int, float o string) a string con la escala indicada.
     */
    public static function of(int|float|string|null $value, int $scale): string
    {
        return self::round((string) ($value ?? '0'), $scale);
    }

    /**
     * Redondeo half-up (alejándose de cero) a la escala indicada.
     */
    public static function round(string $value, int $scale): string
    {
        $offset = '0.' . str_repeat('0', $scale) . '5';

        return str_starts_with(ltrim($value), '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);
    }

    public static function hasFraction(string $value): bool
    {
        return bccomp($value, bcadd($value, '0', 0), 8) !== 0;
    }
}
