<?php

namespace App\Services;

use App\Enums\AppointmentCancelScope;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentTransition;
use App\Enums\UserRole;
use App\Exceptions\AppointmentException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Veterinarian;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
 * - Fichar y atendido, por un veterinario: un usuario con el rol veterinario (o con ficha) y sin
 *   appointments.update marca solo los turnos de su ficha o los de agendas sin veterinario; sin
 *   ficha, o con la ficha eliminada, solo los de agendas sin veterinario (si no, 403
 *   APPOINTMENT_NOT_YOURS; pregunta 18 y decisiones del usuario en la 3.2b).
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
    /**
     * Los demás turnos de la serie que cancela un alcance following o series (3.3).
     */
    private const CANCELLABLE_IN_SERIES = [AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED];

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

    public function undoNoShow(Appointment $appointment, bool $overbook = false): Appointment
    {
        return $this->appointments->undoNoShow($appointment, $overbook);
    }

    /**
     * Cancelar (P3) con alcance (3.3): el turno pedido, con las reglas de siempre (si no admite
     * cancelarse, 422 y no cambia nada); con following, además los turnos de su serie que
     * empiezan después (por starts_at, y por id en el mismo instante); con series, todos los
     * demás de la serie. De esos, solo los que están a confirmar o confirmados y todavía no
     * empezaron: los fichados, los finales, los ausentes y los que ya empezaron quedan como
     * están. El mismo motivo para todos, cada uno con su auditoría. En un turno sin serie, el
     * alcance no cambia nada.
     *
     * Concurrencia: los turnos de la serie con lockForUpdate, por id ascendente, antes de leer
     * sus estados; sin serie, solo el turno (como cada transición).
     *
     * @return array{0: Appointment, 1: int} el turno pedido y cuántos turnos se cancelaron
     */
    public function cancel(Appointment $appointment, string $reason, AppointmentCancelScope $scope = AppointmentCancelScope::SINGLE): array
    {
        // Una sola hora para toda la cancelación (revisión de la 3.3, hallazgo 9): la de
        // cancelled_at en todos los turnos es la misma con la que se decide cuáles ya empezaron.
        $fields = fn (CarbonInterface $now) => [
            'cancelled_at' => $now,
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
        ];

        // series_id no cambia nunca: se lee sin bloqueo.
        $seriesId = $scope === AppointmentCancelScope::SINGLE
            ? null
            : Appointment::query()->whereKey($appointment->getKey())->value('series_id');

        if ($seriesId === null) {
            return [$this->apply($appointment, AppointmentTransition::CANCEL, fn () => $fields(now())), 1];
        }

        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($appointment, $scope, $seriesId, $fields) {
            $series = Appointment::query()->where('series_id', $seriesId)->orderBy('id')->lockForUpdate()->get();
            $locked = $series->firstWhere('id', $appointment->getKey()) ?? throw new ModelNotFoundException();

            if (! AppointmentTransition::CANCEL->allowsFrom($locked->status)) {
                throw AppointmentException::invalidTransition($locked->status);
            }

            $now = now();
            $others = $series->filter(fn (Appointment $other) => $other->getKey() !== $locked->getKey()
                && ($scope === AppointmentCancelScope::SERIES || $this->isAfter($other, $locked))
                && in_array($other->status, self::CANCELLABLE_IN_SERIES, true)
                && $other->starts_at->greaterThan($now));

            $values = [...$fields($now), 'status' => AppointmentTransition::CANCEL->to()];

            foreach ([$locked, ...$others->all()] as $cancelled) {
                $cancelled->fill($values);
                $cancelled->save();
            }

            return [$locked, 1 + $others->count()];
        }));
    }

    /**
     * Si $other va después de $appointment en la serie: por starts_at y, en el mismo instante
     * (un turno movido), por id.
     */
    private function isAfter(Appointment $other, Appointment $appointment): bool
    {
        return $other->starts_at->greaterThan($appointment->starts_at)
            || ($other->starts_at->equalTo($appointment->starts_at) && $other->getKey() > $appointment->getKey());
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
     * Pregunta 18 (decisiones del usuario en la 3.2b): un usuario con el rol veterinario o con
     * una ficha de veterinario, y sin appointments.update, ficha y marca atendido solo los turnos
     * de su ficha (no eliminada) o los de agendas sin veterinario; sin ficha, o con la ficha
     * eliminada, solo los de agendas sin veterinario. Si no, 403 APPOINTMENT_NOT_YOURS. El
     * administrador (Gate::before) y quien tiene appointments.update, cualquiera.
     */
    private function ensureOwnAppointment(Appointment $appointment): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->can('appointments.update') || $appointment->veterinarian_id === null) {
            return;
        }

        // Solo una ficha no eliminada (el scope de SoftDeletes).
        $ownId = Veterinarian::query()->where('user_id', $user->getKey())->value('id');

        if ($ownId === null && ! $user->hasRole(UserRole::VETERINARIAN->value, 'api')) {
            return;
        }

        if ($ownId !== null && (int) $appointment->veterinarian_id === (int) $ownId) {
            return;
        }

        throw AppointmentException::notYours();
    }
}
