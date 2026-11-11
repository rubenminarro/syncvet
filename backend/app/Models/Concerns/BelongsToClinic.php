<?php

namespace App\Models\Concerns;

use App\Exceptions\ClinicContextMissingException;
use App\Models\Clinic;
use App\Models\Scopes\ClinicScope;
use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de negocio de una clínica (D1). Las consultas ven solo la clínica en
 * contexto (ClinicScope), el alta toma esa clínica y clinic_id no cambia al
 * editar. clinic_id no va en $fillable: nunca sale del cuerpo del pedido.
 */
trait BelongsToClinic
{
    public static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope());

        static::creating(function (Model $model): void {
            $model->setAttribute('clinic_id', self::clinicIdForCreation($model));
        });

        // La clínica de un registro no cambia.
        static::updating(fn (Model $model) => ClinicIdGuard::ensureUnchanged($model));
    }

    /**
     * La clínica en contexto. Un registro nunca se crea en otra clínica.
     */
    protected static function clinicIdForCreation(Model $model): int
    {
        $clinicId = CurrentClinic::id();
        $given = $model->getAttribute('clinic_id');

        if ($given !== null && (int) $given !== $clinicId) {
            throw new ClinicContextMissingException(
                'Se intentó crear un registro de ' . class_basename($model) . ' en una clínica distinta de la del contexto.'
            );
        }

        return $clinicId;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
