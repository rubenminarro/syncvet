<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Exceptions\InventoryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'product_lot_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'average_cost_after',
        'document_number',
        'reference_type',
        'reference_id',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'average_cost_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new InventoryException(
            'Los movimientos de inventario no pueden modificarse ni eliminarse: se corrigen con otro movimiento.',
            409,
            'INVENTORY_MOVEMENT_IMMUTABLE'
        ));

        static::deleting(fn () => throw new InventoryException(
            'Los movimientos de inventario no pueden modificarse ni eliminarse: se corrigen con otro movimiento.',
            409,
            'INVENTORY_MOVEMENT_IMMUTABLE'
        ));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
