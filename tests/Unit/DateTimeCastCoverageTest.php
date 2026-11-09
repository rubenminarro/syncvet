<?php

namespace Tests\Unit;

use App\Casts\AppDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Deuda 13 (FASE 2, entrega 2.6): todo atributo con hora de los modelos usa el cast
 * AppDateTime, que convierte la zona al guardar. El cast "datetime" de Eloquent guarda la
 * hora de un valor con zona sin convertirla.
 *
 * Revisa cada modelo de app/Models: ningún cast "datetime" (ni sus variantes), y toda
 * columna datetime o timestamp de $fillable con AppDateTime. Las fechas solas ("date") y
 * las horas del día ("time") no tienen zona.
 */
class DateTimeCastCoverageTest extends TestCase
{
    private const FRAMEWORK_TIMESTAMPS = ['created_at', 'updated_at', 'deleted_at'];

    public function test_every_assignable_date_time_attribute_uses_app_date_time(): void
    {
        $missing = [];
        $checked = 0;

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            if (! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            /** @var Model $model */
            $model = new $class();
            $casts = $model->getCasts();

            foreach ($casts as $attribute => $cast) {
                // created_at, updated_at y deleted_at los llena Eloquent con now(), ya en la hora
                // de Asunción (SoftDeletes suma su cast "datetime"); salvo que sean asignables.
                if (in_array($attribute, self::FRAMEWORK_TIMESTAMPS, true) && ! $model->isFillable($attribute)) {
                    continue;
                }

                if (preg_match('/^(immutable_)?(datetime|date_time|custom_datetime)/', (string) $cast) === 1) {
                    $missing[] = "{$class}::{$attribute} usa el cast {$cast}";
                }
            }

            $columns = collect(Schema::getColumns($model->getTable()))->keyBy('name');

            foreach ($model->getFillable() as $attribute) {
                $type = strtolower((string) ($columns[$attribute]['type_name'] ?? ''));

                if (! in_array($type, ['datetime', 'timestamp'], true)) {
                    continue;
                }

                $checked++;

                if (($casts[$attribute] ?? null) !== AppDateTime::class) {
                    $missing[] = "{$class}::{$attribute} ({$type}) sin AppDateTime";
                }
            }
        }

        $this->assertSame([], $missing);
        // Que el test no pase en vacío: hoy son 25 atributos asignables con hora.
        $this->assertGreaterThanOrEqual(20, $checked);
    }
}
