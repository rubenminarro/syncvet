<?php

namespace App\Support;

use App\Exceptions\ClinicContextMissingException;
use App\Models\Clinic;
use Closure;

/**
 * Clínica en contexto (D1). La fija el middleware SetCurrentClinic en cada
 * pedido autenticado; los jobs y los comandos la fijan con run(). Registrada
 * como "scoped": se descarta al terminar cada pedido o job de la cola.
 *
 * Sin clínica, id() y get() lanzan ClinicContextMissingException: una consulta
 * nunca devuelve datos de todas las clínicas (fallo cerrado).
 */
class CurrentClinic
{
    private ?Clinic $clinic = null;

    public static function set(?Clinic $clinic): void
    {
        self::instance()->clinic = $clinic;
    }

    public static function get(): Clinic
    {
        return self::instance()->clinic ?? throw new ClinicContextMissingException();
    }

    public static function id(): int
    {
        return self::get()->getKey();
    }

    public static function has(): bool
    {
        return self::instance()->clinic !== null;
    }

    /**
     * Ejecuta el callback con la clínica fijada y restaura la anterior, también
     * si el callback lanza una excepción.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function run(Clinic $clinic, Closure $callback): mixed
    {
        $instance = self::instance();
        $previous = $instance->clinic;
        $instance->clinic = $clinic;

        try {
            return $callback();
        } finally {
            $instance->clinic = $previous;
        }
    }

    private static function instance(): self
    {
        return app(self::class);
    }
}
