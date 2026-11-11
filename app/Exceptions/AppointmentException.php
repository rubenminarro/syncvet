<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;

/**
 * Errores de turnos (FASE 3, entrega 3.2a): la superposición (409 APPOINTMENT_OVERLAP), fuera
 * del horario de la agenda (422 APPOINTMENT_OUTSIDE_HOURS), en un bloqueo (422
 * APPOINTMENT_IN_BLOCK), un turno que ya no se modifica (422 APPOINTMENT_NOT_EDITABLE) y el
 * conflicto de una operación simultánea después de los reintentos (409 CONFLICT).
 *
 * Las transiciones (3.2b): un cambio de estado que el turno no admite (422
 * APPOINTMENT_INVALID_TRANSITION), fichar sin paciente (422 APPOINTMENT_PATIENT_REQUIRED) u otro
 * día (422 APPOINTMENT_CHECK_IN_NOT_TODAY), el ausente antes de la hora (422
 * APPOINTMENT_NO_SHOW_TOO_EARLY) y deshacerlo otro día (422 APPOINTMENT_NO_SHOW_NOT_UNDOABLE).
 */
class AppointmentException extends AppException
{
    /**
     * 422 APPOINTMENT_INVALID_TRANSITION, con la etiqueta del estado actual.
     */
    public static function invalidTransition(AppointmentStatus $status): self
    {
        return new self(
            "El turno está «{$status->label()}» y no admite ese cambio.",
            422,
            'APPOINTMENT_INVALID_TRANSITION'
        );
    }
}
