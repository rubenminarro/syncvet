<?php

namespace App\Models;

use App\Enums\CashRegisterStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegister extends Model
{
    use Auditable;

    protected $fillable = [
        'branch_id',
        'user_id',
        'status',
        'opened_at',
        'opening_amount',
        'closed_at',
        'declared_amount',
        'expected_amount',
        'difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashRegisterStatus::class,
            'opened_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'declared_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
