<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Service;
use App\Models\Veterinarian;
use Carbon\CarbonImmutable;

/**
 * Disponibilidad de una agenda (FASE 3, entrega 3.4): los horarios que el alta de un turno
 * aceptaría, día por día. Solo lectura: no bloquea nada y el alta vuelve a revisar todo.
 *
 * Usa las reglas del alta, no una copia: la agenda activa con su veterinario activo
 * (AppointmentService::ensureScheduleIsAvailable, con el error en "schedule"), el servicio
 * activo (ensureServiceIsAvailable), la duración (duration_minutes del pedido, que manda; si no,
 * durationMinutes: la del servicio pedido, o la del servicio de la agenda, o slot_minutes), el día y la duración (timingProblem) y el horario
 * semanal con los tramos que se tocan como uno solo, los bloqueos y la superposición en la
 * agenda y del mismo veterinario en sus otras agendas (ScheduleOccupancy::fit, sin sobreturno).
 *
 * Los candidatos de cada día empiezan en el comienzo de cada tramo y avanzan cada slot_minutes
 * mientras empiecen dentro del tramo. Hoy (en Asunción) no se ofrecen los que ya empezaron; un
 * día anterior a hoy no ofrece ninguno (el alta lo rechaza).
 */
class ScheduleAvailabilityService
{
    public function __construct(
        protected AppointmentService $appointments
    ) {
    }

    /**
     * @return array{schedule_id: int, duration_minutes: int, days: list<array{date: string, slots: list<array{starts_at: string, ends_at: string}>}>}
     */
    public function days(Schedule $schedule, CarbonImmutable $fromDay, CarbonImmutable $toDay, ?int $serviceId, ?int $durationMinutes = null): array
    {
        $veterinarian = $schedule->veterinarian_id === null
            ? null
            : Veterinarian::query()->whereKey($schedule->veterinarian_id)->first();
        $this->appointments->ensureScheduleIsAvailable($schedule, $veterinarian, 'schedule');

        $serviceId ??= $schedule->service_id;
        $service = $serviceId === null ? null : Service::query()->whereKey($serviceId)->first();

        if ($serviceId !== null) {
            $this->appointments->ensureServiceIsAvailable($service);
        }

        // D2: la duración del pedido manda sobre la del servicio y slot_minutes (el servicio
        // pedido se revisa igual, como en el alta con ends_at propio).
        $duration = $durationMinutes ?? AppointmentService::durationMinutes($service, $schedule);
        $timezone = config('app.timezone');
        $from = CarbonImmutable::parse($fromDay->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($toDay->toDateString(), $timezone)->endOfDay();
        $occupancy = ScheduleOccupancy::load($schedule, $from, $to);
        $now = now()->toImmutable();
        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $slots = [];

            foreach ($occupancy->rangesOf($day->isoWeekday()) as [$rangeStart, $rangeEnd]) {
                $end = CarbonImmutable::parse("{$day->toDateString()} {$rangeEnd}", $timezone);

                for ($startsAt = CarbonImmutable::parse("{$day->toDateString()} {$rangeStart}", $timezone);
                    $startsAt->lessThan($end);
                    $startsAt = $startsAt->addMinutes((int) $schedule->slot_minutes)) {
                    $endsAt = $startsAt->addMinutes($duration);
                    // Hoy, solo los que todavía no empezaron.
                    if ($startsAt->lessThan($now)
                        || $this->appointments->timingProblem($startsAt, $endsAt) !== null
                        || $occupancy->fit($startsAt, $endsAt, false)[0] !== null) {
                        continue;
                    }

                    $slots[$startsAt->format('H:i')] = [
                        'starts_at' => $startsAt->toISOString(),
                        'ends_at' => $endsAt->toISOString(),
                    ];
                }
            }

            ksort($slots);
            $days[] = ['date' => $day->toDateString(), 'slots' => array_values($slots)];
        }

        return [
            'schedule_id' => (int) $schedule->id,
            'duration_minutes' => $duration,
            'days' => $days,
        ];
    }
}
