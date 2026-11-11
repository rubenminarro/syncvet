<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\TaxRate;
use App\Support\CatalogName;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Servicio del catálogo de la clínica (FASE 2, entrega 2.4): código SRV- de la serie,
 * nombre único en la clínica (CatalogName) y precio único sin decimales mientras la
 * moneda sea PYG (P8, pregunta 21; la columna sigue en decimal(14,2)). Un servicio
 * usado (ServiceCatalogService::USAGE) no cambia de nombre ni se elimina: se inactiva.
 *
 * code lo fija ServiceCatalogService; no cambia al editar.
 */
class Service extends Model
{
    use BelongsToClinic, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'duration_minutes',
        'standard_price',
        'tax_rate',
        'status',
    ];

    /**
     * Los valores por defecto de las columnas: un servicio recién creado ya los muestra.
     */
    protected $attributes = [
        'category' => ServiceCategory::CONSULTATION->value,
        'duration_minutes' => 30,
        'standard_price' => '0.00',
        'tax_rate' => TaxRate::TEN->value,
        'status' => ServiceStatus::ACTIVE->value,
    ];

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'duration_minutes' => 'integer',
            'standard_price' => 'decimal:2',
            'tax_rate' => TaxRate::class,
            'status' => ServiceStatus::class,
        ];
    }

    /**
     * El nombre, sin espacios en los extremos ni repetidos (CatalogName::clean), como
     * sucursales, especies y razas.
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? CatalogName::clean($value) : $value);
    }
}
