<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Señal interna de ScheduleService::update (FASE 3, entrega 3.1): el veterinario de la
 * agenda cambió entre la lectura sin bloqueo y el bloqueo de la agenda, así que el que se
 * bloqueó antes ya no es el suyo. Deshace la transacción y la edición se repite; nunca
 * llega a la respuesta (tras los intentos, 409 CONFLICT).
 */
class ScheduleChangedWhileLocking extends RuntimeException
{
}
