<?php

namespace App\Exceptions;

/**
 * Errores de la clínica: suspendida (CLINIC_NOT_AVAILABLE, 403) y alta
 * (CLINIC_RUC_TAKEN, CLINIC_RUC_INVALID, CLINIC_ADMIN_EMAIL_TAKEN).
 */
class ClinicException extends AppException
{
}
