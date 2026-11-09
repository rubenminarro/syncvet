<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Fechas con hora que recibe la API (FASE 2, entrega 2.6; deuda 13). La API envía las
 * fechas en UTC ("Z") y la base guarda la hora de America/Asuncion (config
 * app.timezone). Un valor con zona ("Z" o un desplazamiento, "-03:00") se convierte a
 * la hora de Asunción; uno sin zona se toma como hora de Asunción (pregunta 28).
 *
 * Lo usan el cast AppDateTime (al guardar) y los filtros y validaciones con hora
 * (los filtros from y to de la auditoría, la fecha de fallecimiento).
 */
final class ApiDateTime
{
    /**
     * Una fecha sola, sin hora: "2026-11-09".
     */
    public const DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * Lo que acepta un filtro de fecha: una fecha sola, o una fecha con hora (con "T" o
     * un espacio; segundos y fracción opcionales) con o sin zona ("Z", "+00:00", "-0300").
     */
    public const FILTER_FORMAT = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * El valor en la hora de Asunción. Sin zona, ya es hora de Asunción.
     */
    public static function toLocal(string|DateTimeInterface $value): CarbonImmutable
    {
        $timezone = config('app.timezone');

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, $timezone);

        return $date->setTimezone($timezone);
    }

    public static function isDateOnly(string $value): bool
    {
        return preg_match(self::DATE_ONLY, $value) === 1;
    }

    /**
     * El comienzo de un filtro "desde": una fecha sola, desde las 00:00 de ese día en
     * Asunción; con hora, ese momento.
     */
    public static function startOf(string $value): CarbonImmutable
    {
        return self::isDateOnly($value) ? self::toLocal($value)->startOfDay() : self::toLocal($value);
    }

    /**
     * El final de un filtro "hasta": una fecha sola, hasta las 23:59:59 de ese día en
     * Asunción; con hora, ese momento.
     */
    public static function endOf(string $value): CarbonImmutable
    {
        return self::isDateOnly($value) ? self::toLocal($value)->endOfDay() : self::toLocal($value);
    }
}
