<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\ConsultationItemBillingStatus;
use App\Enums\ConsultationItemType;
use App\Enums\TaxRate;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationItem extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'consultation_id',
        'type',
        'service_id',
        'product_id',
        'product_lot_id',
        'veterinarian_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'total',
        'billing_status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConsultationItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => TaxRate::class,
            'total' => 'decimal:2',
            'billing_status' => ConsultationItemBillingStatus::class,
        ];
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
