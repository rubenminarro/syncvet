<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\TaxRate;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'barcode',
        'name',
        'type',
        'unit',
        'sale_price',
        'tax_rate',
        'tracks_stock',
        'tracks_lots',
        'minimum_stock',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'sale_price' => 'decimal:2',
            'tax_rate' => TaxRate::class,
            'tracks_stock' => 'boolean',
            'tracks_lots' => 'boolean',
            'minimum_stock' => 'decimal:2',
            'status' => ProductStatus::class,
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
