<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLot extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'lot_number',
        'expires_at',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'quantity_on_hand' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
