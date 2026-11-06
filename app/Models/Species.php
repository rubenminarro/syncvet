<?php

namespace App\Models;

use App\Models\Concerns\SharedCatalog;
use App\Support\CatalogName;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Especie: global (clinic_id nulo) o propia de una clínica (SharedCatalog).
 * is_active la oculta de las altas nuevas; la baja (SoftDeletes) solo si nunca
 * se usó (SpeciesService).
 */
class Species extends Model
{
    use SharedCatalog, Auditable, SoftDeletes;

    protected $table = 'species';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * El nombre se guarda sin espacios en los extremos y con los internos repetidos
     * juntados en uno (CatalogName::clean; revisión de la entrega 1.4).
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? CatalogName::clean($value) : $value);
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
