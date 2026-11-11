<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\CatalogName;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Depósito de una sucursal (FASE 2, entrega 2.1). Uno por defecto en cada
 * sucursal; no se elimina, solo se inactiva (WarehouseService). No cambia de
 * sucursal.
 */
class Warehouse extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'is_default',
        'is_active',
    ];

    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * El código se guarda en mayúsculas (pregunta 16 del diseño de la FASE 2).
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? strtoupper(trim($value)) : $value);
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? CatalogName::clean($value) : $value);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }
}
