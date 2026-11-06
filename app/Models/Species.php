<?php

namespace App\Models;

use App\Models\Concerns\SharedCatalog;
use App\Traits\Auditable;
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

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
