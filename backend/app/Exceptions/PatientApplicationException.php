<?php

namespace App\Exceptions;

/**
 * Errores de las aplicaciones (FASE 4, entrega 4.4): el paciente fallecido (422 PATIENT_DECEASED, el
 * código de la FASE 1), anular una ya anulada (422 PATIENT_APPLICATION_NOT_CANCELLABLE) y un
 * veterinario que anula una aplicación sin consulta que no cargó él el mismo día (403
 * PATIENT_APPLICATION_NOT_YOURS; decisión del usuario en la 4.4).
 */
class PatientApplicationException extends AppException
{
    public static function patientDeceased(): self
    {
        return new self('El paciente falleció: no se le pueden cargar aplicaciones.', 422, 'PATIENT_DECEASED');
    }

    public static function notCancellable(): self
    {
        return new self('La aplicación ya está anulada.', 422, 'PATIENT_APPLICATION_NOT_CANCELLABLE');
    }

    public static function notYours(): self
    {
        return new self(
            'Solo podés anular una aplicación sin consulta que cargaste vos, el mismo día; si no, la anula el administrador.',
            403,
            'PATIENT_APPLICATION_NOT_YOURS'
        );
    }
}
