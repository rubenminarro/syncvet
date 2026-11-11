<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ScheduleHour;
use Carbon\CarbonImmutable;

/**
 * Las reglas de un horario de una agenda que comparten el alta de un turno (3.2a), la serie
 * (3.3) y la disponibilidad (3.4): dentro del horario semanal (los tramos del mismo día que se
 * tocan cuentan como uno solo), fuera de los bloqueos y sin superponerse con un turno que ocupa
 * el horario en la agenda o del mismo veterinario en sus otras agendas (pregunta 15). Es el
 * mismo código para los tres: la disponibilidad ofrece un horario solo si el alta lo aceptaría.
 *
 * load() lee una vez, para una ventana [from, to], los tramos de la agenda, los bloqueos y los
 * turnos que ocupan el horario y se superponen con la ventana; fit() decide sobre cada horario
 * en memoria, con la misma regla de superposición que la consulta (a.starts_at < b.ends_at y
 * a.ends_at > b.starts_at; tocarse no choca), comparando la hora de Asunción "Y-m-d H:i:s" como
 * la base. El alta la carga con la ventana del turno, con la agenda (y su veterinario) bloqueada;
 * la disponibilidad, con los días pedidos, sin bloquear nada.
 */
final class ScheduleOccupancy
{
    private const FORMAT = 'Y-m-d H:i:s';

    /**
     * @param array<int, list<array{0: string, 1: string}>> $hours los tramos por día de la semana, "HH:MM", por inicio
     * @param list<array{0: string, 1: string}> $blocks [starts_at, ends_at] en "Y-m-d H:i:s" de Asunción
     * @param list<array{0: string, 1: string}> $appointments [starts_at, ends_at] en "Y-m-d H:i:s" de Asunción
     */
    private function __construct(
        private readonly array $hours,
        private readonly array $blocks,
        private readonly array $appointments,
    ) {
    }

    /**
     * Lo que hace falta para decidir sobre cualquier horario dentro de [from, to], sin contar
     * $ignore (el turno que se mueve o cuyo ausente se deshace).
     */
    public static function load(Schedule $schedule, CarbonImmutable $from, CarbonImmutable $to, ?Appointment $ignore = null): self
    {
        $hours = [];

        foreach (ScheduleHour::query()
            ->where('schedule_id', $schedule->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get(['weekday', 'starts_at', 'ends_at']) as $hour) {
            $hours[(int) $hour->weekday][] = [substr((string) $hour->starts_at, 0, 5), substr((string) $hour->ends_at, 0, 5)];
        }

        $blocks = ScheduleBlock::query()
            ->where('schedule_id', $schedule->id)
            ->where('starts_at', '<', $to->format(self::FORMAT))
            ->where('ends_at', '>', $from->format(self::FORMAT))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (ScheduleBlock $block) => [self::local($block->starts_at), self::local($block->ends_at)])
            ->all();

        $appointments = Appointment::query()
            ->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('starts_at', '<', $to->format(self::FORMAT))
            ->where('ends_at', '>', $from->format(self::FORMAT))
            ->where(fn ($query) => $query
                ->where('schedule_id', $schedule->id)
                ->when($schedule->veterinarian_id !== null, fn ($query) => $query->orWhere('veterinarian_id', $schedule->veterinarian_id)))
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [self::local($appointment->starts_at), self::local($appointment->ends_at)])
            ->all();

        return new self($hours, $blocks, $appointments);
    }

    /**
     * Los tramos del día de la semana (1, lunes, a 7, domingo), "HH:MM", por inicio.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function rangesOf(int $weekday): array
    {
        return $this->hours[$weekday] ?? [];
    }

    /**
     * El primer motivo por el que el horario no entra (AppointmentService::OUTSIDE_HOURS,
     * IN_BLOCK u OVERLAP; null si entra) y si se superpone con otro turno. Un sobreturno
     * ($overbook) saltea solo la superposición.
     *
     * @return array{0: ?string, 1: bool}
     */
    public function fit(CarbonImmutable $startsAt, CarbonImmutable $endsAt, bool $overbook): array
    {
        if (! $this->insideHours($startsAt, $endsAt)) {
            return [AppointmentService::OUTSIDE_HOURS, false];
        }

        if ($this->inBlock($startsAt, $endsAt)) {
            return [AppointmentService::IN_BLOCK, false];
        }

        $overlaps = $this->overlaps($startsAt, $endsAt);

        return [$overlaps && ! $overbook ? AppointmentService::OVERLAP : null, $overlaps];
    }

    /**
     * P6: el rango entero dentro del horario del día de la semana de starts_at, en hora de reloj
     * de Asunción. Los tramos que se tocan (08:00–12:00 y 12:00–16:00) cuentan como un horario
     * continuo (decisión del usuario en la revisión de la 3.2a); un hueco entre tramos, no. Los
     * tramos del mismo día no se superponen (3.1) y van en múltiplos de 5 minutos, sin segundos.
     */
    public function insideHours(CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $start = $startsAt->format('H:i');
        $end = $endsAt->format('H:i');
        $merged = null;

        foreach ($this->rangesOf($startsAt->isoWeekday()) as [$from, $to]) {
            if ($merged !== null && $from === $merged[1]) {
                $merged[1] = $to;

                continue;
            }

            if ($merged !== null && $merged[0] <= $start && $end <= $merged[1]) {
                return true;
            }

            $merged = [$from, $to];
        }

        return $merged !== null && $merged[0] <= $start && $end <= $merged[1];
    }

    public function inBlock(CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $this->anyOverlap($this->blocks, $startsAt, $endsAt);
    }

    /**
     * Si se superpone con un turno que ocupa el horario, en la agenda o del mismo veterinario
     * en sus otras agendas.
     */
    public function overlaps(CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $this->anyOverlap($this->appointments, $startsAt, $endsAt);
    }

    /**
     * @param list<array{0: string, 1: string}> $ranges
     */
    private function anyOverlap(array $ranges, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $start = self::local($startsAt);
        $end = self::local($endsAt);

        foreach ($ranges as [$from, $to]) {
            if ($from < $end && $to > $start) {
                return true;
            }
        }

        return false;
    }

    private static function local(\DateTimeInterface $value): string
    {
        return CarbonImmutable::instance($value)->setTimezone(config('app.timezone'))->format(self::FORMAT);
    }
}
