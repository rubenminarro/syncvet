<?php

namespace App\Casts;

use App\Support\ApiDateTime;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Fecha con hora de un modelo (FASE 2, entrega 2.6; deuda 13), en lugar del cast
 * "datetime" de Eloquent, que guarda la hora de un valor con zona sin convertirla
 * ("2025-11-05T13:00:00Z" quedaba como las 13:00 de Asunción, 3 horas corrida).
 *
 * Al guardar, un valor con zona ("Z" o un desplazamiento) o un DateTime se convierte a
 * la hora de Asunción; uno sin zona se toma como hora de Asunción (ApiDateTime). Al
 * leer, un Carbon en la hora de Asunción, como "datetime". La salida de la API sigue en
 * UTC ("Z"): los Resources usan toISOString() y la serialización, toJSON().
 *
 * Lo usa todo atributo con hora de los modelos (DateTimeCastCoverageTest).
 *
 * @implements CastsAttributes<Carbon, string|DateTimeInterface>
 */
class AppDateTime implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof DateTimeInterface
            ? Date::instance($value)->setTimezone(config('app.timezone'))
            : Date::parse($value, config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ApiDateTime::toLocal($value)->format($model->getDateFormat());
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->toJSON();
    }
}
