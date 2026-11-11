<?php

namespace App\Exceptions;

/**
 * Errores de agendas y bloqueos (FASE 3, entrega 3.1): la baja de una agenda con turnos
 * (409 SCHEDULE_IN_USE) y el cambio de sucursal o de veterinario de una agenda con turnos
 * (422 SCHEDULE_NOT_EDITABLE).
 */
class ScheduleException extends AppException
{
}
