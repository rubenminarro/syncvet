<?php

namespace App\Models\Scopes;

use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Solo los registros de la clínica en contexto. Sin clínica, lanza
 * ClinicContextMissingException (fallo cerrado).
 */
class ClinicScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('clinic_id'), CurrentClinic::id());
    }
}
