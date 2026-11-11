<?php

namespace App\Services;

use App\Enums\AppointmentTransition;
use App\Exceptions\AppointmentException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Veterinarian;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Estados del turno (FASE 3, entrega 3.2b; P3, P7, P12 y preguntas 18 a 20). Cada transición
 * (AppointmentTransition) se admite solo desde sus estados (si no, 422
 * APPOINTMENT_INVALID_TRANSITION); atendido y cancelado son finales.
 *
 * - Confirmar: fija confirmed_at.
 * - Fichar: el turno tiene paciente (422 APPOINTMENT_PATIENT_REQUIRED), no fallecido, y es de hoy
 *   en Asunción (422 APPOINTMENT_CHECK_IN_NOT_TODAY); fija checked_in_at.
 * - Atendido: fija attended_at.
 * - Fichar y atendido, por un veterinario: un usuario con ficha de veterinario y sin
 *   appointments.update marca solo los turnos de su ficha o los de agendas sin veterinario (si
 *   no, 403; pregunta 18 y decisión del usuario en la 3.2b, que la extiende al fichado).
 * - Ausente (P12): solo desde la hora de inicio del turno (422 APPOINTMENT_NO_SHOW_TOO_EARLY);
 *   fija no_show_at.
 * - Cancelar (P3): con motivo (CancelAppointmentRequest), sin límite de anticipación; fija
 *   cancelled_at, cancelled_by y cancellation_reason.
 * - Deshacer el ausente: AppointmentService::undoNoShow (vuelve a ocupar el horario).
 *
 * Concurrencia: cada una en DB::transaction, con el turno lockForUpdate antes de leer su estado:
 * de dos transiciones simultáneas, la segunda ve el estado nuevo y responde 422. No bloquea la
 * agenda: liberan el horario, nunca lo ocupan. Las horas las pone PHP (now()).
 */
class AppointmentTransitionService
{
    public function __construct(
        protected AppointmentService $appointments
    ) {
    }

    public function confirm(Appointment $appointment): Appointment
    {
        return $this->apply($appointment, AppointmentTransition::CONFIRM, fn () => ['confirmed_at' => now()]);
    }

    public function checkIn(Appointment $appointment): Appointment
    {
        return $this->apply($appointment, AppointmentTransition::CHECK_IN, function (Appointment $locked) {
            if ($locked->patient_id === null) {
                throw new AppointmentException('Para fichar el turno, cargá el paciente.', 422, 'APPOINTMENT_PATIENT_REQUIRED');
            }

            // Sin bloquear el paciente: el turno ya existe y su baja lo ve como uso.
            $patient = Patient::query()->withTrashed()->whereKey($locked->patient_id)->first(['id', 'deceased_at']);

            if ($patient?->deceased_at !== null) {
                throw ValidationException::withMessages(['patient_id' => 'El paciente falleció: no se puede fichar el turno.']);
            }

            if (! $locked->startsToday()) {
                throw new AppointmentException('Solo se fichan los turnos del día.', 422, 'APPOINTMENT_CHECK_IN_NOT_TODAY');
            }

            return ['checked_in_at' => now()];
        });
    }

    public function attend(Appointment $appointment): Appointment
    {
        return $this->apply($appointment, AppointmentTransition::ATTEND, fn () => ['attended_at' => now()]);
    }

    public function noShow(Appointment $appointment): Appointment
    {
        return $this->apply($appointment, AppointmentTransition::NO_SHOW, function (Appointment $locked) {
            if ($locked->starts_at->greaterThan(now())) {
                throw new AppointmentException(
                    'El turno todavía no empezó: no se puede marcar como ausente.',
                    422,
                    'APPOINTMENT_NO_SHOW_TOO_EARLY'
                );
            }

            return ['no_show_at' => now()];
        });
    }

    public function undoNoShow(Appointment $appointment): Appointment
    {
        return $this->appointments->undoNoShow($appointment);
    }

    public function cancel(Appointment $appointment, string $reason): Appointment
    {
        return $this->apply($appointment, AppointmentTransition::CANCEL, fn () => [
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Con el turno bloqueado: el permiso de la ficha (fichar y atendido), el estado de origen y
     * las reglas de la transición ($fields); después, el estado nuevo y los campos que devuelve
     * $fields. La auditoría guarda el cambio como updated (Auditable).
     *
     * @param callable(Appointment): array<string, mixed> $fields
     */
    private function apply(Appointment $appointment, AppointmentTransition $transition, callable $fields): Appointment
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($appointment, $transition, $fields) {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            // El permiso de la ficha va antes que el estado: un veterinario no averigua el
            // estado de un turno ajeno por el error.
            if ($transition === AppointmentTransition::CHECK_IN || $transition === AppointmentTransition::ATTEND) {
                $this->ensureOwnAppointment($locked);
            }

            if (! $transition->allowsFrom($locked->status)) {
                throw AppointmentException::invalidTransition($locked->status);
            }

            $locked->fill([...$fields($locked), 'status' => $transition->to()]);
            $locked->save();

            return $locked;
        }));
    }

    /**
     * Pregunta 18: un usuario con ficha de veterinario (no eliminada) y sin
     * appointments.update ficha y marca atendido solo los turnos de su ficha o los de agendas
     * sin veterinario; si no, 403. El administrador (Gate::before) y quien tiene
     * appointments.update, cualquiera.
     */
    private function ensureOwnAppointment(Appointment $appointment): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->can('appointments.update')) {
            return;
        }

        $ownId = Veterinarian::query()->where('user_id', $user->getKey())->value('id');

        if ($ownId === null || $appointment->veterinarian_id === null || (int) $appointment->veterinarian_id === (int) $ownId) {
            return;
        }

        throw new AuthorizationException();
    }
}
