<?php

namespace App\Models;

use App\Models\Concerns\SharedCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raza: global (clinic_id nulo) o propia de una clínica (SharedCatalog).
 */
class Breed extends Model
{
    use SharedCatalog;

    protected $fillable = [
        'species_id',
        'name',
        'is_active',
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
}
