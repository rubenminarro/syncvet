<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceStamp extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'branch_id',
        'document_type',
        'stamp_number',
        'establishment',
        'expedition_point',
        'valid_from',
        'valid_until',
        'next_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
