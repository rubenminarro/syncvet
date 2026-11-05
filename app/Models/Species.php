<?php

namespace App\Models;

use App\Models\Concerns\SharedCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Especie: global (clinic_id nulo) o propia de una clínica (SharedCatalog).
 */
class Species extends Model
{
    use SharedCatalog;

    protected $table = 'species';

    protected $fillable = [
        'name',
        'is_active',
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
