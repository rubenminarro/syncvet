<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Las fechas de una serie de turnos (FASE 3, entrega 3.3; P1): el turno i es el primero más
 * 7 × every_weeks × i días, a la misma hora de reloj de Asunción (los instantes llegan en la
 * hora de Asunción, ApiDateTime::toLocal, y addDays conserva la hora de reloj). La cantidad
 * sale de count o de until (la última fecha que no pasa de until). Hasta MAX_COUNT turnos.
 */
final class AppointmentRecurrence
{
    public const MIN_COUNT = 2;

    public const MAX_COUNT = 52;

    public const MAX_EVERY_WEEKS = 12;

    /**
     * Cuántos turnos da la serie: count, o los que entran hasta until (inclusive).
     */
    public static function count(CarbonImmutable $first, int $everyWeeks, ?int $count, ?string $until): int
    {
        if ($count !== null) {
            return $count;
        }

        // Las dos fechas en UTC: la diferencia en días no depende de un cambio de hora.
        $days = (int) round(CarbonImmutable::parse($first->toDateString(), 'UTC')->diffInDays(CarbonImmutable::parse((string) $until, 'UTC'), false));

        return $days < 0 ? 0 : intdiv($days, 7 * $everyWeeks) + 1;
    }

    /**
     * El inicio del turno $index de la serie (0 es el primero), a la misma hora de reloj.
     */
    public static function shift(CarbonImmutable $first, int $everyWeeks, int $index): CarbonImmutable
    {
        return $first->addDays(7 * $everyWeeks * $index);
    }
}
