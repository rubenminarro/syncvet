<?php

namespace App\Models\Concerns;

use App\Exceptions\CatalogException;
use App\Exceptions\ClinicContextMissingException;
use App\Models\Builders\SharedCatalogBuilder;
use App\Models\Clinic;
use App\Models\Scopes\SharedCatalogScope;
use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo compartido (especies y razas, registro R-004): clinic_id nulo para los
 * globales. Las consultas ven los globales y los de la clínica en contexto
 * (SharedCatalogScope). El alta toma la clínica en contexto; un global no se
 * edita ni se elimina, y clinic_id no cambia al editar. Las escrituras masivas
 * solo alcanzan a los registros de la clínica (SharedCatalogBuilder). Los
 * globales los crea SpeciesSeeder, sin modelo (no hay clínica en contexto).
 * clinic_id no va en $fillable.
 */
trait SharedCatalog
{
    public static function bootSharedCatalog(): void
    {
        static::addGlobalScope(new SharedCatalogScope());

        static::creating(function (Model $model): void {
            $clinicId = CurrentClinic::id();
            $given = $model->getAttribute('clinic_id');

            if ($given !== null && (int) $given !== $clinicId) {
                throw new ClinicContextMissingException(
                    'Se intentó crear un registro de ' . class_basename($model) . ' en una clínica distinta de la del contexto.'
                );
            }

            $model->setAttribute('clinic_id', $clinicId);
        });

        static::updating(function (Model $model): void {
            $model->ensureNotGlobal();
            ClinicIdGuard::ensureUnchanged($model);
        });

        static::deleting(fn (Model $model) => $model->ensureNotGlobal());
    }

    /**
     * Las escrituras masivas (update, delete…) solo alcanzan a los registros propios.
     */
    public function newEloquentBuilder($query): SharedCatalogBuilder
    {
        return new SharedCatalogBuilder($query);
    }

    public function isGlobal(): bool
    {
        return $this->getRawOriginal('clinic_id') === null;
    }

    /**
     * Un global no se edita ni se elimina desde una clínica (403). Los Services lo
     * revisan antes de validar el nombre, para responder 403 y no 422.
     */
    public function ensureNotGlobal(): void
    {
        if ($this->isGlobal()) {
            throw new CatalogException(
                'Este registro es del catálogo común: no se puede modificar ni eliminar.',
                403,
                'CATALOG_GLOBAL_NOT_EDITABLE'
            );
        }
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
