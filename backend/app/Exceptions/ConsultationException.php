<?php

namespace App\Exceptions;

/**
 * Errores de la consulta (FASE 4; diseño R-099, "Errores nuevos"). En la 4.1: el turno que ya
 * tiene consulta (409 CONSULTATION_ALREADY_EXISTS, también la carrera del índice único), editar
 * una que no está abierta (422 CONSULTATION_NOT_EDITABLE), la consulta de otra ficha (403
 * CONSULTATION_NOT_YOURS), el turno atendido otro día (422 CONSULTATION_APPOINTMENT_NOT_TODAY),
 * sin turno y sin sucursal actual (422 CONSULTATION_NO_BRANCH) y el paciente fallecido (422
 * PATIENT_DECEASED, el código de la FASE 1).
 */
class ConsultationException extends AppException
{
    public static function alreadyExists(): self
    {
        return new self('El turno ya tiene una consulta.', 409, 'CONSULTATION_ALREADY_EXISTS');
    }

    public static function notEditable(): self
    {
        return new self('La consulta no está abierta: no se puede modificar.', 422, 'CONSULTATION_NOT_EDITABLE');
    }

    public static function notYours(): self
    {
        return new self('Esa consulta no es de tu ficha.', 403, 'CONSULTATION_NOT_YOURS');
    }

    public static function appointmentNotToday(): self
    {
        return new self(
            'El turno ya fue atendido otro día: su consulta se abre solo el día del turno.',
            422,
            'CONSULTATION_APPOINTMENT_NOT_TODAY'
        );
    }

    public static function noBranch(): self
    {
        return new self('Elegí tu sucursal actual para abrir una consulta sin turno.', 422, 'CONSULTATION_NO_BRANCH');
    }

    public static function patientDeceased(): self
    {
        return new self('El paciente falleció: no se le puede abrir una consulta.', 422, 'PATIENT_DECEASED');
    }
}
