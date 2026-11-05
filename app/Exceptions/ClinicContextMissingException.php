<?php

namespace App\Exceptions;

use App\Support\ExecutionOrigin;
use LogicException;

/**
 * Se consultó o se creó un modelo de una clínica sin clínica en contexto, o se
 * intentó crearlo en otra clínica. Es un error de programación, no de negocio:
 * responde el 500 genérico (INTERNAL_SERVER_ERROR) sin traza y queda en el log
 * con su origen (método y ruta del pedido, o la clase del job o del comando),
 * registro R-005.
 */
class ClinicContextMissingException extends LogicException
{
    /**
     * Origen capturado al lanzarla: después, el pedido o el job ya pueden haber terminado.
     *
     * @var array<string, string>
     */
    private array $origin;

    public function __construct(string $message = 'No hay una clínica en contexto.')
    {
        parent::__construct($message);

        $this->origin = ExecutionOrigin::describe();
    }

    /**
     * Contexto del log (Laravel lo agrega al reportar la excepción).
     *
     * @return array<string, string>
     */
    public function context(): array
    {
        return $this->origin;
    }
}
