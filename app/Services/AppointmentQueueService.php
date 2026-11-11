<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentException;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Veterinarian;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cola del día (FASE 3, entrega 3.4; P8 y pregunta 25): los turnos de un día en la sucursal
 * actual del usuario, sin los cancelados, por starts_at e id, hasta LIMIT, sin paginar.
 *
 * Sin sucursal actual → 422 APPOINTMENT_QUEUE_NO_BRANCH. Es una vista por defecto, no una
 * restricción (pregunta 25): quien tiene una ficha de veterinario (no eliminada) ve por
 * defecto los turnos de su ficha, y con all los de toda la sucursal; la recepción y el
 * administrador, los de toda la sucursal. veterinarian_id, si llega, manda sobre los dos.
 * schedule_id filtra por una agenda. Todo con una cantidad fija de consultas (sin N+1).
 */
class AppointmentQueueService
{
    public const LIMIT = 200;

    public const RELATIONS = [
        'schedule', 'branch', 'veterinarian.user', 'service', 'patient.species', 'patient.breed', 'client',
    ];

    /**
     * @return array{0: Collection<int, Appointment>, 1: ?int} los turnos y el veterinario por el que se filtró
     */
    public function forUser(User $user, CarbonImmutable $day, ?int $veterinarianId, ?int $scheduleId, bool $all): array
    {
        if ($user->current_branch_id === null) {
            throw new AppointmentException(
                'Elegí tu sucursal actual para ver la cola del día.',
                422,
                'APPOINTMENT_QUEUE_NO_BRANCH'
            );
        }

        if ($veterinarianId === null && ! $all) {
            // Solo una ficha no eliminada (el scope de SoftDeletes).
            $own = Veterinarian::query()->where('user_id', $user->id)->value('id');
            $veterinarianId = $own === null ? null : (int) $own;
        }

        $appointments = Appointment::query()
            ->with(self::RELATIONS)
            ->where('branch_id', $user->current_branch_id)
            ->where('status', '!=', AppointmentStatus::CANCELLED->value)
            ->where('starts_at', '>=', $day->startOfDay()->format('Y-m-d H:i:s'))
            ->where('starts_at', '<=', $day->endOfDay()->format('Y-m-d H:i:s'))
            ->when($veterinarianId !== null, fn ($query) => $query->where('veterinarian_id', $veterinarianId))
            ->when($scheduleId !== null, fn ($query) => $query->where('schedule_id', $scheduleId))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        return [$appointments, $veterinarianId];
    }
}
