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
     * Horas de 00 a 23, minutos y segundos de 00 a 59, y desplazamientos de hasta ±14:00:
     * "T24:00:00" y "-2400" no se aceptan (revisión de la 2.6). El día lo valida la regla
     * "date".
     */
    public const FILTER_FORMAT = '/^\d{4}-\d{2}-\d{2}(?:[T ](?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-](?:(?:0\d|1[0-3]):?[0-5]\d|14:?00))?)?$/';

    /**
     * Un instante que recibe la API (FASE 3, entrega 3.1: los bloqueos de una agenda): como
     * FILTER_FORMAT, pero con la hora obligatoria. Sin zona, hora de Asunción.
     */
    public const DATE_TIME_FORMAT = '/^\d{4}-\d{2}-\d{2}[T ](?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-](?:(?:0\d|1[0-3]):?[0-5]\d|14:?00))?$/';

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

    /**
     * El comienzo de un filtro contra columnas sin fracción de segundo: con fracción, se
     * redondea hacia arriba al segundo siguiente ("11:00:00.5" no incluye las 11:00:00).
     */
    public static function ceilSecond(CarbonImmutable $value): CarbonImmutable
    {
        return $value->microsecond > 0 ? $value->startOfSecond()->addSecond() : $value;
    }

    /**
     * El final de un filtro contra columnas sin fracción de segundo: la fracción se
     * descarta (hacia abajo; "23:59:59.999999" queda en 23:59:59).
     */
    public static function floorSecond(CarbonImmutable $value): CarbonImmutable
    {
        return $value->startOfSecond();
    }
}
