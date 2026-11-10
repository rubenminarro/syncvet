<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueos de una agenda (FASE 3, entrega 3.1; preguntas 22 y 23). Se pueden superponer
 * entre sí y también en una agenda inactiva. Un bloqueo sobre turnos que ya existen se
 * permite: el alta devuelve los turnos que ocupan el horario y se superponen con él
 * (affected_appointments), para que la recepción los reprograme. No se editan: se eliminan
 * y se crea otro.
 *
 * Concurrencia: el alta y la baja bloquean la agenda (lockForUpdate) antes de escribir; el
 * alta de un turno (3.2a) toma la misma fila, así que un turno y un bloqueo no se cruzan.
 */
class ScheduleBlockService
{
    /**
     * @param array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason?: ?string} $data
     * @return array{0: ScheduleBlock, 1: Collection<int, Appointment>} el bloqueo y los turnos afectados
     */
    public function create(Schedule $schedule, array $data): array
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule = Schedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            $block = ScheduleBlock::create([
                'schedule_id' => $schedule->getKey(),
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'reason' => $data['reason'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return [$block, $this->affectedAppointments($block)];
        });
    }

    public function delete(ScheduleBlock $block): void
    {
        DB::transaction(function () use ($block) {
            Schedule::query()->whereKey($block->schedule_id)->lockForUpdate()->firstOrFail();

            ScheduleBlock::query()->whereKey($block->getKey())->firstOrFail()->delete();
        });
    }

    /**
     * Los turnos de la agenda que ocupan el horario (AppointmentStatus::occupiesSlot) y se
     * superponen con el bloqueo: a.starts_at < b.ends_at y a.ends_at > b.starts_at (uno que
     * solo se toca no cuenta).
     *
     * @return Collection<int, Appointment>
     */
    private function affectedAppointments(ScheduleBlock $block): Collection
    {
        return Appointment::query()
            ->where('schedule_id', $block->schedule_id)
            ->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('starts_at', '<', $block->ends_at->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $block->starts_at->format('Y-m-d H:i:s'))
            ->with(['patient', 'client'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }
}
