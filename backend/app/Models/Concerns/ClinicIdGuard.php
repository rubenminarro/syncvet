<?php

namespace App\Models\Concerns;

use App\Exceptions\ClinicContextMissingException;
use Illuminate\Database\Eloquent\Model;

/**
 * clinic_id es inmutable (D1): un registro no se pasa a otra clínica, ni un
 * registro propio del catálogo a global. Lo usan BelongsToClinic y SharedCatalog
 * al editar.
 */
final class ClinicIdGuard
{
    public static function ensureUnchanged(Model $model): void
    {
        if ($model->isDirty('clinic_id')) {
            throw new ClinicContextMissingException(
                'Se intentó cambiar la clínica de un registro de ' . class_basename($model) . '.'
            );
        }
    }
}
