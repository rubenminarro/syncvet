<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\CatalogName;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sucursal (FASE 2, entrega 2.1). Siempre hay una por defecto en la clínica; no
 * se elimina, solo se inactiva (BranchService). establishment es el código de
 * establecimiento SIFEN (dEst): no cambia si la sucursal tiene facturas.
 */
class Branch extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'establishment',
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

    /**
     * El nombre, sin espacios en los extremos ni repetidos (CatalogName::clean), como especies y razas.
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? CatalogName::clean($value) : $value);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
