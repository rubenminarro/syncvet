<?php

namespace App\Models\Scopes;

use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Catálogos compartidos (especies y razas): los globales (clinic_id nulo) más los
 * de la clínica en contexto. Sin clínica, lanza ClinicContextMissingException
 * (fallo cerrado, registro R-004).
 */
class SharedCatalogScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('clinic_id');
        $clinicId = CurrentClinic::id();

        $builder->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, $clinicId));
    }
}
