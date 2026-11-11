<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Señal interna del reintento de ScheduleService::update (FASE 3, entrega 3.1) y de
 * AppointmentService (3.2a): la agenda (su veterinario o su servicio) o el turno cambió entre
 * la lectura sin bloqueo y el bloqueo, así que lo que se bloqueó antes ya no corresponde.
 * Deshace la transacción y la operación se repite; nunca llega a la respuesta (tras los
 * intentos, 409 CONFLICT).
 */
class ScheduleChangedWhileLocking extends RuntimeException
{
}
