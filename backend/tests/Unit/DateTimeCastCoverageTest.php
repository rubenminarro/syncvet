<?php

namespace Tests\Unit;

use App\Casts\AppDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Deuda 13 (FASE 2, entrega 2.6): todo atributo con hora de los modelos usa el cast
 * AppDateTime, que convierte la zona al guardar. El cast "datetime" de Eloquent guarda la
 * hora de un valor con zona sin convertirla.
 *
 * Revisa cada modelo de app/Models, también en las subcarpetas: ningún cast "datetime"
 * (ni sus variantes), y toda columna datetime o timestamp de $fillable con AppDateTime.
 * Las fechas solas ("date") y las horas del día ("time") no tienen zona.
 */
class DateTimeCastCoverageTest extends TestCase
{
    private const FRAMEWORK_TIMESTAMPS = ['created_at', 'updated_at', 'deleted_at'];

    public function test_every_assignable_date_time_attribute_uses_app_date_time(): void
    {
        $missing = [];
        $checked = 0;

        foreach (self::classesIn(app_path('Models'), 'App\\Models') as $class) {
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

    /**
     * Las clases de app/Models se buscan también en las subcarpetas (revisión de la 2.6): un
     * modelo en app/Models/Inventory/… se revisa igual.
     */
    public function test_the_models_are_searched_also_in_subfolders(): void
    {
        $classes = self::classesIn(app_path('Models'), 'App\\Models');

        $this->assertContains('App\\Models\\Patient', $classes);
        $this->assertContains('App\\Models\\Scopes\\ClinicScope', $classes);
        $this->assertContains('App\\Models\\Builders\\SharedCatalogBuilder', $classes);
    }

    /**
     * Las clases de una carpeta y sus subcarpetas, con el espacio de nombres de PSR-4.
     *
     * @return list<string>
     */
    private static function classesIn(string $directory, string $namespace): array
    {
        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $classes[] = $namespace . '\\' . str_replace(['/', '\\'], '\\', $relative);
        }

        sort($classes);

        return $classes;
    }
}
