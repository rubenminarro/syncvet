<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Models\Concerns\BelongsToClinic;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'invoice_id',
        'cash_register_id',
        'receipt_sequence',
        'receipt_number',
        'payment_date',
        'amount',
        'cash_change',
        'method',
        'reference',
        'bank',
        'notes',
        'status',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'cash_change' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'cancelled_at' => AppDateTime::class,
        ];
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
