<?php

namespace App\Http\Requests;

/**
 * Mensajes de las aplicaciones (FASE 4, entrega 4.4).
 */
class PatientApplicationMessages
{
    public const CONSULTATION_OF_ANOTHER_PATIENT = 'La consulta no es de este paciente.';

    public const APPLIED_AT_FUTURE = 'La fecha de aplicación no puede ser futura.';

    public const NEXT_DOSE_NOT_AFTER = 'La próxima dosis debe ser posterior a la fecha de aplicación.';

    public const DATE_FORMAT = 'Ingresá una fecha con el formato AAAA-MM-DD.';
}
