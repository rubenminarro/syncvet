<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AppointmentException;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Veterinarian;
use Illuminate\Support\Facades\Auth;

/**
 * Las reglas de attend sobre de quién es un turno (FASE 3, pregunta 18 y decisiones del usuario
 * en la 3.2b), en un solo lugar: las usan fichar y marcar atendido (AppointmentTransitionService)
 * y abrir la consulta desde un turno (ConsultationService, FASE 4, entrega 4.1).
 *
 * Un usuario con el rol veterinario o con una ficha de veterinario, y sin appointments.update,
 * actúa solo sobre los turnos de su ficha (no eliminada) o los de agendas sin veterinario; sin
 * ficha, o con la ficha eliminada, solo sobre los de agendas sin veterinario. Si no, 403
 * APPOINTMENT_NOT_YOURS. El administrador (Gate::before) y quien tiene appointments.update,
 * cualquiera.
 */
class AppointmentOwnership
{
    public function ensure(Appointment $appointment): void
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
