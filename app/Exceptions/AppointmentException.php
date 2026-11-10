<?php

namespace App\Exceptions;

/**
 * Errores de turnos (FASE 3, entrega 3.2a): la superposición (409 APPOINTMENT_OVERLAP), fuera
 * del horario de la agenda (422 APPOINTMENT_OUTSIDE_HOURS), en un bloqueo (422
 * APPOINTMENT_IN_BLOCK), un turno que ya no se modifica (422 APPOINTMENT_NOT_EDITABLE) y el
 * conflicto de una operación simultánea después de los reintentos (409 CONFLICT). Las
 * transiciones de la 3.2b suman los suyos.
 */
class AppointmentException extends AppException
{
}
