<?php

namespace App\Models;

use App\Models\Concerns\SharedCatalog;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Raza: global (clinic_id nulo) o propia de una clínica (SharedCatalog). Única
 * dentro de su especie; no cambia de especie. La baja (SoftDeletes) solo si
 * nunca se usó (BreedService).
 */
class Breed extends Model
{
    use SharedCatalog, Auditable, SoftDeletes;

    protected $fillable = [
        'species_id',
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

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
