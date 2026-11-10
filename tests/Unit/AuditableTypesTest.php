<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Tests\TestCase;

/**
 * El filtro auditable_type de la auditoría (FASE 2, entrega 2.6) acepta una lista cerrada:
 * AuditLog::AUDITABLE_TYPES tiene que ser exactamente el tipo de cada modelo con
 * Auditable. Un modelo auditable nuevo hace fallar el test hasta que se agregue.
 */
class AuditableTypesTest extends TestCase
{
    public function test_the_list_matches_the_auditable_models(): void
    {
        $types = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            if (! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (in_array(Auditable::class, class_uses_recursive($class), true)) {
                $types[] = (new $class())->auditType();
            }
        }

        sort($types);
        $expected = AuditLog::AUDITABLE_TYPES;
        sort($expected);

        $this->assertSame($expected, $types);
    }
}
